<?php

/**
 * API-side exception for Bing Places client failures.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Represents a failure returned by (or preventing a call to) the Bing
 * Places for Business HTTP APIs.
 *
 * Instances are produced by the base client's error mapper and always
 * carry the observed HTTP status (0 for transport failures) and, when
 * available, the raw response body so callers can log or surface it.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class ApiException extends BingPlacesException
{
    /**
     * The HTTP status code observed for the failure, or 0 when the request
     * never produced a response (transport failure).
     *
     * @since 1.0.0
     */
    protected int $statusCode = 0;

    /**
     * The raw response body, or null when the request produced no response.
     *
     * @since 1.0.0
     */
    protected ?string $responseBody = null;

    /**
     * Build an exception from an unsuccessful Response.
     *
     * The message is derived from the status class so callers get a
     * meaningful default without having to inspect the response, while
     * the raw body remains available via {@see responseBody()} for
     * logging or richer error surfaces.
     *
     * @since 1.0.0
     *
     * @param  Response  $response  The unsuccessful HTTP response.
     */
    public static function fromResponse( Response $response ): self
    {
        $status = $response->status();
        $body   = (string) $response->body();

        $message = match ( true ) {
            401 === $status                 => 'Bing Places authentication failed (HTTP 401): the supplied access token was rejected.',
            403 === $status                 => 'Bing Places authorization failed (HTTP 403): the token lacks the required scope or the account is not enrolled in the Bing Places for Business partner/agency program.',
            404 === $status                 => 'Bing Places resource not found (HTTP 404).',
            429 === $status                 => 'Bing Places request was rate-limited (HTTP 429).',
            $status >= 500 && $status < 600 => sprintf( 'Bing Places server error (HTTP %d).', $status ),
            default                         => sprintf( 'Bing Places request failed (HTTP %d).', $status ),
        };

        $exception               = new self( $message, $status );
        $exception->statusCode   = $status;
        $exception->responseBody = $body;

        return $exception;
    }

    /**
     * Build an exception representing a transport-level failure (network
     * error, DNS failure, timeout, TLS error, etc.).
     *
     * @since 1.0.0
     *
     * @param  ConnectionException|Throwable  $previous  The underlying
     *                                                   transport exception.
     */
    public static function transportFailure( Throwable $previous ): self
    {
        return new self(
            'Bing Places request failed before receiving a response: ' . $previous->getMessage(),
            0,
            $previous,
        );
    }

    /**
     * The observed HTTP status code, or 0 when no response was received.
     *
     * @since 1.0.0
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The raw response body, or null when no response was received.
     *
     * @since 1.0.0
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }
}
