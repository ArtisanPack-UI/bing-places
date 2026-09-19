---
title: Testing with Http::fake()
---

# Testing with `Http::fake()`

The shared `BaseClient` accepts an `Illuminate\Http\Client\Factory`
instance in its constructor. The service provider wires it to the same
singleton the `Http` facade resolves, so `Http::fake()` works against
every client with no bespoke test doubles.

While management-API access is still pending (see the
[restricted-access guide](restricted-access.md)), this is the primary
way the whole package is exercised end-to-end.

## Faking a Bing Places request

Match on the Bing Places management-API host and return whatever
payload the client DTOs expect. Both `BusinessesClient` and
`ReviewsClient` currently point at `https://bingplaces.microsoft.com/api/v2`
— when Microsoft grants a different production host to the partner
program, changing each client's `baseUrl()` (and these fake matchers)
is the only update required.

```php
use ArtisanPackUI\BingPlaces\Businesses\BusinessesClient;
use Illuminate\Support\Facades\Http;

Http::fake( [
    'bingplaces.microsoft.com/*' => Http::response( [
        'businesses' => [
            [
                'id'           => '123',
                'storeId'      => 'store-1',
                'businessName' => 'Test Business',
            ],
        ],
    ] ),
] );

$page = app( BusinessesClient::class )->listBusinesses();

expect( $page->businesses )->toHaveCount( 1 );
expect( $page->businesses[0]->id )->toBe( '123' );
```

## Testing retry behaviour

`BaseClient` retries `HTTP 429`, `HTTP 5xx`, and `ConnectionException`
— but **only for idempotent verbs** (`GET`, `HEAD`, `OPTIONS`). A
`POST`, `PATCH`, `PUT`, or `DELETE` that fails or times out is
surfaced immediately, because replaying it could duplicate a write the
server may already have processed. Up to `maxAttempts` total attempts
(default 3), with `retrySleepMs` milliseconds between them (default
250). A valid positive `Retry-After` header (delta-seconds or HTTP
date) overrides the configured sleep for that one attempt; an
absent, unparseable, or non-positive value falls back to
`retrySleepMs`.

`Http::fake()` accepts a response sequence, which is enough to
exercise the retry loop end-to-end on an idempotent method:

```php
Http::fake( [
    'bingplaces.microsoft.com/*' => Http::sequence()
        ->push( [ 'error' => 'temporary' ], 503 )
        ->push( [ 'error' => 'temporary' ], 503 )
        ->push( [ 'businesses' => [] ],    200 ),
] );

// GET is idempotent, so the two 503s are retried transparently
// and the caller sees the 200.
$page = app( BusinessesClient::class )->listBusinesses();

expect( $page->businesses )->toBeEmpty();
```

To exercise the "do not retry writes" branch, sequence the same
statuses under a `POST`-driven call (`createBusiness`, for example) and
assert the first non-2xx surfaces as `ApiException` immediately —
without pulling a further response from the sequence.

## Speeding up retry sleeps

`BaseClient` sleeps `retrySleepMs` milliseconds between attempts
(default `250`). In tests you can construct a client with
`retrySleepMs: 0` to skip the sleep entirely, or rebind the service in
your `TestCase`:

```php
$this->app->bind( BusinessesClient::class, function ( $app ) {
    return new BusinessesClient(
        tokenProvider: $app->make( TokenProvider::class ),
        http:          $app->make( \Illuminate\Http\Client\Factory::class ),
        retrySleepMs:  0,
    );
} );
```

## Asserting the request that went out

Because the client uses the same factory as the `Http` facade,
`Http::assertSent()` works directly:

```php
Http::assertSent( function ( $request ) {
    return $request->hasHeader( 'Authorization', 'Bearer test-token' )
        && str_starts_with(
            $request->url(),
            'https://bingplaces.microsoft.com/api/v2/',
        );
} );
```

## Testing error mapping

Any non-2xx response — after retries are exhausted, on idempotent
verbs, or immediately on non-idempotent verbs — is mapped to
`ApiException::fromResponse()`. A `ConnectionException` on the final
attempt (or the first attempt for a non-idempotent verb) is mapped to
`ApiException::transportFailure()`.

```php
use ArtisanPackUI\BingPlaces\Exceptions\ApiException;

Http::fake( [
    'bingplaces.microsoft.com/*' => Http::response(
        [ 'error' => [ 'code' => 403, 'message' => 'Not enrolled' ] ],
        403,
    ),
] );

expect( fn () => app( BusinessesClient::class )->listBusinesses() )
    ->toThrow( ApiException::class );
```

The two factories carry different information:

- `ApiException::fromResponse()` preserves the observed HTTP status via
  `statusCode()` and the raw response body via `responseBody()`. Tests
  can assert on either.
- `ApiException::transportFailure()` reports status `0` (no response
  was received) and `responseBody()` returns `null`. The underlying
  transport exception is chained as `->getPrevious()`, so tests assert
  the previous exception instead of a body.

`403` in particular is the signature signal that either the token
lacks the required OAuth scope **or** the Bing Places account is not
enrolled in the Trusted Partner / agency program. The exception's
message covers both causes; the status code alone does not identify
which one applies. See the
[restricted-access guide](restricted-access.md) for the full
breakdown. Rejected-token failures — expired, revoked, malformed, or
issued for a different tenant — surface as `401` instead.

## Stubbing the `TokenProvider`

Pair `Http::fake()` with a stub `TokenProvider` binding so no real
Microsoft token is ever required. See the
[token-provider guide](token-provider.md) for a stub example. The
recommended default while management-API access is pending is: stub
provider + `Http::fake()` for every test, everywhere.

See also: [Token provider contract](token-provider.md) for wiring the
provider that ships tokens into these tests, and
[Restricted-access reality](restricted-access.md) for why the whole
suite runs against fakes today.
