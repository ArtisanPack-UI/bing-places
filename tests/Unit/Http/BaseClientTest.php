<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use ArtisanPackUI\BingPlaces\Exceptions\ApiException;
use ArtisanPackUI\BingPlaces\Http\BaseClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

/**
 * Concrete BaseClient subclass used only in tests. Exposes the protected
 * request() method so we can drive it directly, and points at a stable
 * fake base URL.
 */
class FakeBingClient extends BaseClient
{
    public function call( string $method, string $path, array $query = [], ?array $json = null ): array
    {
        return $this->request( $method, $path, $query, $json );
    }

    protected function baseUrl(): string
    {
        return 'https://bingplaces.microsoft.com/api/v2';
    }
}

/**
 * TokenProvider that hands out a deterministic token and records how many
 * times it was asked for one. Used to prove the client fetches a fresh
 * token per request.
 */
class RecordingTokenProvider implements TokenProvider
{
    public int $callCount = 0;

    public function __construct( public string $token = 'stub-access-token' )
    {
    }

    public function accessToken(): string
    {
        $this->callCount++;

        return $this->token;
    }
}

/**
 * TokenProvider that returns a different token on each call so tests can
 * assert the second attempt used a freshly obtained credential.
 */
class RotatingTokenProvider implements TokenProvider
{
    /** @var list<string> */
    public array $issued = [];

    public function accessToken(): string
    {
        $token          = 'token-' . ( count( $this->issued ) + 1 );
        $this->issued[] = $token;

        return $token;
    }
}

function bingMakeClient(
    HttpFactory $http,
    ?TokenProvider $provider = null,
    int $maxAttempts = 3,
): FakeBingClient {
    return new FakeBingClient(
        tokenProvider: $provider ?? new RecordingTokenProvider(),
        http: $http,
        timeout: 5,
        maxAttempts: $maxAttempts,
        retrySleepMs: 0,
    );
}

test( 'sends a GET with Bearer token, JSON accept header, and decoded body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses' => $factory::response(
            [ 'businesses' => [ [ 'id' => 'biz-1' ] ] ],
            200,
        ),
    ] );

    $provider = new RecordingTokenProvider( 'the-real-token' );
    $client   = bingMakeClient( $factory, $provider );

    $body = $client->call( 'GET', 'businesses' );

    expect( $body )->toBe( [ 'businesses' => [ [ 'id' => 'biz-1' ] ] ] );
    expect( $provider->callCount )->toBe( 1 );

    $factory->assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && 'https://bingplaces.microsoft.com/api/v2/businesses' === $request->url()
            && 'Bearer the-real-token' === $request->header( 'Authorization' )[0]
            && str_contains( $request->header( 'Accept' )[0], 'application/json' );
    } );
} );

test( 'joins base URL and path with a single slash regardless of surrounding slashes', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = bingMakeClient( $factory );
    $client->call( 'GET', '/businesses' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://bingplaces.microsoft.com/api/v2/businesses' === $request->url();
    } );
} );

test( 'appends query parameters when provided', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = bingMakeClient( $factory );
    $client->call( 'GET', 'businesses', [ 'pageSize' => 50, 'fields' => 'id,name' ] );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return str_contains( $url, 'pageSize=50' )
            && str_contains( $url, 'fields=id%2Cname' );
    } );
} );

test( 'sends a JSON body when provided and uses the requested verb', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'ok' => true ], 200 ) ] );

    $client = bingMakeClient( $factory );
    $client->call( 'POST', 'businesses', [], [ 'name' => 'Shop' ] );

    $factory->assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && [ 'name' => 'Shop' ] === $request->data()
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' );
    } );
} );

test( 'retries 429 responses and returns the successful body on a later attempt', function (): void {
    $factory = new HttpFactory();
    $factory->fakeSequence( 'bingplaces.microsoft.com/*' )
        ->push( 'slow down', 429 )
        ->push( 'slow down', 429 )
        ->push( [ 'ok' => true ], 200 );

    $provider = new RotatingTokenProvider();
    $client   = bingMakeClient( $factory, $provider, maxAttempts: 3 );

    $body = $client->call( 'GET', 'businesses' );

    expect( $body )->toBe( [ 'ok' => true ] );
    expect( $provider->issued )->toBe( [ 'token-1', 'token-2', 'token-3' ] );
    $factory->assertSentCount( 3 );
} );

test( 'retries 5xx responses and eventually surfaces an ApiException carrying the last status', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'server oops', 503 ) ] );

    $client = bingMakeClient( $factory, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'GET', 'businesses' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 503 );
    expect( $thrown->responseBody() )->toBe( 'server oops' );
    $factory->assertSentCount( 3 );
} );

test( 'does not retry 4xx client errors other than 429', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'nope', 404 ) ] );

    $client = bingMakeClient( $factory, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'GET', 'businesses/missing' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 404 );
    $factory->assertSentCount( 1 );
} );

test( 'does not retry ConnectionException on non-idempotent verbs (POST/PATCH/DELETE)', function (): void {
    $factory  = new HttpFactory();
    $attempts = 0;
    $factory->fake( function () use ( &$attempts ): void {
        $attempts++;
        throw new ConnectionException( 'network unreachable' );
    } );

    $client = bingMakeClient( $factory, null, maxAttempts: 3 );

    $thrown = null;
    try {
        $client->call( 'POST', 'businesses', [], [ 'name' => 'Shop' ] );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 0 );
    expect( $attempts )->toBe( 1 );
} );

test( 'does not retry 5xx responses on non-idempotent verbs (POST)', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'server oops', 503 ) ] );

    $client = bingMakeClient( $factory, null, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'POST', 'businesses', [], [ 'name' => 'Shop' ] );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 503 );
    // POST is not replayed on 5xx because the server may have already
    // processed the write; retrying would create a duplicate.
    $factory->assertSentCount( 1 );
} );

test( 'does not retry 429 responses on non-idempotent verbs (POST)', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'slow down', 429 ) ] );

    $client = bingMakeClient( $factory, null, maxAttempts: 3 );

    $thrown = null;

    try {
        $client->call( 'POST', 'businesses', [], [ 'name' => 'Shop' ] );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 429 );
    $factory->assertSentCount( 1 );
} );

test( 'honors a numeric Retry-After header when retrying 429 responses', function (): void {
    $factory = new HttpFactory();
    $factory->fakeSequence( '*' )
        ->push( 'slow down', 429, [ 'Retry-After' => '0' ] )
        ->push( [ 'ok' => true ], 200 );

    // Wrap client so we can capture the sleep argument.
    $client = new class(
        tokenProvider: new RecordingTokenProvider(),
        http: $factory,
        timeout: 5,
        maxAttempts: 3,
        retrySleepMs: 999999,
    ) extends BaseClient {
        public ?int $lastSleepMs = null;

        public function call( string $method, string $path ): array
        {
            return $this->request( $method, $path );
        }

        protected function baseUrl(): string
        {
            return 'https://bingplaces.microsoft.com/api/v2';
        }

        protected function sleep( ?int $overrideMs = null ): void
        {
            $this->lastSleepMs = $overrideMs ?? $this->retrySleepMs;
            // Skip the actual usleep so tests stay fast.
        }
    };

    $body = $client->call( 'GET', 'businesses' );

    expect( $body )->toBe( [ 'ok' => true ] );
    // Retry-After: 0 should be rejected and the client should fall back to
    // the configured retrySleepMs rather than looping tight.
    expect( $client->lastSleepMs )->toBe( 999999 );
} );

test( 'honors a positive Retry-After header value over the configured sleep', function (): void {
    $factory = new HttpFactory();
    $factory->fakeSequence( '*' )
        ->push( 'slow down', 429, [ 'Retry-After' => '2' ] )
        ->push( [ 'ok' => true ], 200 );

    $client = new class(
        tokenProvider: new RecordingTokenProvider(),
        http: $factory,
        timeout: 5,
        maxAttempts: 3,
        retrySleepMs: 100,
    ) extends BaseClient {
        public ?int $lastSleepMs = null;

        public function call( string $method, string $path ): array
        {
            return $this->request( $method, $path );
        }

        protected function baseUrl(): string
        {
            return 'https://bingplaces.microsoft.com/api/v2';
        }

        protected function sleep( ?int $overrideMs = null ): void
        {
            $this->lastSleepMs = $overrideMs ?? $this->retrySleepMs;
        }
    };

    $client->call( 'GET', 'businesses' );

    expect( $client->lastSleepMs )->toBe( 2000 );
} );

test( 'maps ConnectionException to a transport-failure ApiException after retries exhausted', function (): void {
    $factory = new HttpFactory();
    $factory->fake( function (): void {
        throw new ConnectionException( 'network unreachable' );
    } );

    $provider = new RecordingTokenProvider();
    $client   = bingMakeClient( $factory, $provider, maxAttempts: 2 );

    $thrown = null;

    try {
        $client->call( 'GET', 'businesses' );
    } catch ( ApiException $exception ) {
        $thrown = $exception;
    }

    expect( $thrown )->not->toBeNull();
    expect( $thrown->statusCode() )->toBe( 0 );
    expect( $thrown->getPrevious() )->toBeInstanceOf( ConnectionException::class );
    // Every attempt fetches a fresh token, so the provider call count is the attempt count.
    expect( $provider->callCount )->toBe( 2 );
} );

test( 'returns an empty array when the successful response has no JSON body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 204 ) ] );

    $client = bingMakeClient( $factory );

    expect( $client->call( 'DELETE', 'businesses/9' ) )->toBe( [] );
} );

test( 'ApiException carries a helpful message for 401 responses', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'no', 401 ) ] );

    $client = bingMakeClient( $factory, maxAttempts: 1 );

    try {
        $client->call( 'GET', 'businesses' );
        $this->fail( 'ApiException was not thrown.' );
    } catch ( ApiException $exception ) {
        expect( $exception->statusCode() )->toBe( 401 );
        expect( $exception->getMessage() )->toContain( 'HTTP 401' );
    }
} );

test( 'ApiException carries a helpful message for 403 responses', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( 'nope', 403 ) ] );

    $client = bingMakeClient( $factory, maxAttempts: 1 );

    try {
        $client->call( 'GET', 'businesses' );
        $this->fail( 'ApiException was not thrown.' );
    } catch ( ApiException $exception ) {
        expect( $exception->statusCode() )->toBe( 403 );
        expect( $exception->getMessage() )->toContain( 'partner/agency program' );
    }
} );
