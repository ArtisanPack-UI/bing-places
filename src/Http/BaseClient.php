<?php

/**
 * Shared HTTP client foundation for the Bing Places package.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Http;

use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use ArtisanPackUI\BingPlaces\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Base class for every resource-specific Bing Places client.
 *
 * Responsibilities:
 *
 * - Fetch a fresh access token from the injected {@see TokenProvider} on
 *   every request (delegation only; no OAuth lives here).
 * - Attach the token as a Bearer credential and set JSON accept/content
 *   headers on the outgoing request.
 * - Retry `HTTP 429` and `HTTP 5xx` responses (honoring `Retry-After` when
 *   present) and transport failures — but, in both cases, only for
 *   idempotent verbs so a write the server may already have processed is
 *   never replayed. Up to the configured attempt count.
 * - Map every non-2xx response and every {@see ConnectionException} to an
 *   {@see ApiException} carrying the status code and raw body.
 *
 * The client uses the injected {@see HttpFactory}, which is the same
 * singleton the `Http` facade resolves; tests can therefore call
 * `Http::fake()` and record/stub requests without extra wiring.
 *
 * Subclasses only need to implement {@see baseUrl()} to point at their
 * API surface (for example, the Bing Places for Business dashboard API
 * base URL).
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
abstract class BaseClient
{
    /**
     * Construct a base client.
     *
     * @since 1.0.0
     *
     * @param  TokenProvider  $tokenProvider  Supplies a valid OAuth access
     *                                        token on every request.
     * @param  HttpFactory  $http  The Laravel HTTP client factory. Injecting
     *                             the factory keeps the client compatible
     *                             with `Http::fake()`.
     * @param  int  $timeout  Per-request timeout, in seconds.
     * @param  int  $maxAttempts  Total number of attempts (retries + 1).
     *                            A value below 1 is coerced to 1.
     * @param  int  $retrySleepMs  Sleep between retry attempts, in
     *                             milliseconds.
     */
    public function __construct(
        protected TokenProvider $tokenProvider,
        protected HttpFactory $http,
        protected int $timeout = 30,
        protected int $maxAttempts = 3,
        protected int $retrySleepMs = 250,
    ) {
    }

    /**
     * The absolute base URL for the resource-specific API surface.
     *
     * Trailing slashes are tolerated; the request path is joined with a
     * single separating slash.
     *
     * @since 1.0.0
     */
    abstract protected function baseUrl(): string;

    /**
     * Send a request and return the decoded JSON body as an associative
     * array (or an empty array when the response has no body).
     *
     * @since 1.0.0
     *
     * @param  string  $method  HTTP verb (GET, POST, PATCH, DELETE, PUT).
     * @param  string  $path  Path relative to {@see baseUrl()}.
     * @param  array<string, mixed>  $query  Query-string parameters.
     * @param  array<string, mixed>|null  $json  JSON body for write verbs;
     *                                           null for verbs without a
     *                                           body.
     *
     * @throws ApiException When the request fails after all retry attempts,
     *                      or when the API returns a non-2xx status.
     *
     * @return array<string, mixed>
     */
    protected function request(
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
    ): array {
        $url         = $this->buildUrl( $path );
        $method      = strtoupper( $method );
        $maxAttempts = max( 1, $this->maxAttempts );

        // The loop always exits via `break`, `return`, or `throw` — a
        // transport failure on the final attempt throws inside the catch,
        // and every other path assigns $response before breaking.
        $response = null;

        for ( $attempt = 1; $attempt <= $maxAttempts; $attempt++ ) {
            try {
                $response = $this->dispatch( $method, $url, $query, $json );
            } catch ( ConnectionException $exception ) {
                // Only retry transport failures for verbs we can safely
                // replay. A POST/PATCH that timed out after the server
                // processed it must not be repeated, or we duplicate the
                // write.
                if ( $attempt < $maxAttempts && $this->isIdempotentMethod( $method ) ) {
                    $this->sleep();
                    continue;
                }

                throw ApiException::transportFailure( $exception );
            }

            if (
                $this->shouldRetryStatus( $response->status() )
                && $attempt < $maxAttempts
                && $this->isIdempotentMethod( $method )
            ) {
                $this->sleep( $this->retryAfterMs( $response ) );
                continue;
            }

            break;
        }

        if ( ! $response->successful() ) {
            throw ApiException::fromResponse( $response );
        }

        $body = $response->json();

        return is_array( $body ) ? $body : [];
    }

    /**
     * Perform a single HTTP request using a fresh access token.
     *
     * Extracted so the retry loop stays focused on control flow and so
     * subclasses may override request construction (e.g. adding extra
     * headers) without reimplementing the loop.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    protected function dispatch( string $method, string $url, array $query, ?array $json ): Response
    {
        $pending = $this->http
            ->withToken( $this->tokenProvider->accessToken() )
            ->timeout( $this->timeout )
            ->acceptJson();

        if ( [] !== $query ) {
            $pending = $pending->withOptions( [ 'query' => $query ] );
        }

        if ( null !== $json ) {
            return $pending->asJson()->send( $method, $url, [ 'json' => $json ] );
        }

        return $pending->send( $method, $url );
    }

    /**
     * Whether the given HTTP status code is one the client will retry.
     *
     * Only `429` (rate-limited) and the `5xx` server-error class are
     * retried; `4xx` client errors are surfaced immediately because
     * retrying them will not change the outcome.
     *
     * @since 1.0.0
     */
    protected function shouldRetryStatus( int $status ): bool
    {
        return 429 === $status || ( $status >= 500 && $status < 600 );
    }

    /**
     * Whether the given HTTP verb is safe to replay after a transport
     * failure.
     *
     * A GET/HEAD/OPTIONS request either produces no side effect or is
     * defined by RFC 7231 as idempotent, so replaying one after a socket
     * timeout cannot duplicate work. POST/PATCH may have been fully
     * processed server-side before the response was lost — replaying
     * those would create a duplicate business, review, or edit.
     *
     * @since 1.0.0
     */
    protected function isIdempotentMethod( string $method ): bool
    {
        return in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true );
    }

    /**
     * Sleep between retry attempts.
     *
     * Extracted so tests can override with a zero-cost stub. Accepts an
     * optional override so callers can honor a server-provided delay
     * (e.g. `Retry-After` from a `429`).
     *
     * @since 1.0.0
     *
     * @param  int|null  $overrideMs  Sleep duration in milliseconds, or
     *                                null to use the configured default.
     */
    protected function sleep( ?int $overrideMs = null ): void
    {
        $ms = $overrideMs ?? $this->retrySleepMs;

        if ( $ms > 0 ) {
            usleep( $ms * 1000 );
        }
    }

    /**
     * The delay requested by a `Retry-After` header, in milliseconds, or
     * null when the response does not carry a usable value.
     *
     * Supports both the delta-seconds form (`Retry-After: 30`) and the
     * HTTP-date form (`Retry-After: Wed, 21 Oct 2015 07:28:00 GMT`), per
     * RFC 7231 §7.1.3. Negative or unparseable values fall back to the
     * configured retry sleep.
     *
     * @since 1.0.0
     */
    protected function retryAfterMs( Response $response ): ?int
    {
        $header = $response->header( 'Retry-After' );

        if ( '' === $header ) {
            return null;
        }

        if ( ctype_digit( ltrim( $header, '-' ) ) ) {
            $seconds = (int) $header;

            return $seconds > 0 ? $seconds * 1000 : null;
        }

        $timestamp = strtotime( $header );
        if ( false === $timestamp ) {
            return null;
        }

        $deltaSeconds = $timestamp - time();

        return $deltaSeconds > 0 ? $deltaSeconds * 1000 : null;
    }

    /**
     * Join the configured base URL with the requested path using a single
     * separating slash.
     *
     * @since 1.0.0
     */
    protected function buildUrl( string $path ): string
    {
        return rtrim( $this->baseUrl(), '/' ) . '/' . ltrim( $path, '/' );
    }
}
