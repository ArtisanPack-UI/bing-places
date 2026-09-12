---
title: Testing with Http::fake()
---

# Testing with `Http::fake()`

The client this package ships (landing in follow-up feature issues on
the `release/1.0` branch) is built on the same pattern as
`artisanpack-ui/google-business-profile`: a shared `BaseClient` accepts
an `Illuminate\Http\Client\Factory` instance in its constructor, and
the service provider wires it to the same singleton the `Http` facade
resolves. That means `Http::fake()` works against every client with no
bespoke test doubles.

While management-API access is still pending (see the
[restricted-access guide](restricted-access.md)), this is the primary
way the whole package is exercised end-to-end.

## Faking a Bing Places request

Match on the Bing Places management-API host and return whatever
payload the client DTOs expect. The exact URL prefix will be added to
the reference map as client work lands; the shape below shows the
pattern:

```php
use Illuminate\Support\Facades\Http;

Http::fake( [
    'ssl.bingplacespartner.microsoft.com/*' => Http::response( [
        'businesses' => [
            [
                'id'   => '123',
                'name' => 'Test Business',
            ],
        ],
    ] ),
] );

// … resolve the client and call it as normal; the fake intercepts.
```

## Testing retry behaviour

Following the shape of the sister GBP client, `BaseClient` retries
transport failures (`ConnectionException`), `HTTP 429`, and `HTTP 5xx`
responses up to its configured attempt count (default 3). `Http::fake()`
accepts a response sequence, which is enough to exercise the retry
loop end-to-end:

```php
Http::fake( [
    'ssl.bingplacespartner.microsoft.com/*' => Http::sequence()
        ->push( [ 'error' => 'temporary' ], 503 )
        ->push( [ 'error' => 'temporary' ], 503 )
        ->push( [ 'businesses' => [] ],    200 ),
] );

// The two 503s are retried transparently; the caller sees the 200.
```

Retryable statuses are `429` and any `5xx`. `4xx` responses are
surfaced immediately — retrying them would not change the outcome.

## Speeding up retry sleeps

`BaseClient` sleeps `retrySleepMs` milliseconds between attempts
(default `250`). In tests you can construct a client with
`retrySleepMs: 0` to skip the sleep entirely, or rebind the service in
your `TestCase` and pass `retrySleepMs: 0` when constructing the client.

## Asserting the request that went out

Because the client uses the same factory as the `Http` facade,
`Http::assertSent()` works directly:

```php
Http::assertSent( function ( $request ) {
    return $request->hasHeader( 'Authorization', 'Bearer test-token' )
        && str_starts_with(
            $request->url(),
            'https://ssl.bingplacespartner.microsoft.com/',
        );
} );
```

## Testing error mapping

Any non-2xx response — after retries are exhausted — is mapped to
`ApiException::fromResponse()`. A `ConnectionException` on the final
attempt is mapped to `ApiException::transportFailure()`.

```php
use ArtisanPackUI\BingPlaces\Exceptions\ApiException;

Http::fake( [
    'ssl.bingplacespartner.microsoft.com/*' => Http::response(
        [ 'error' => [ 'code' => 403, 'message' => 'Not enrolled' ] ],
        403,
    ),
] );

// … resolving and calling the client throws ApiException.
```

The two factories carry different information:

- `ApiException::fromResponse()` preserves the observed HTTP status via
  `statusCode()` and the raw response body via `responseBody()`. Tests
  can assert on either.
- `ApiException::transportFailure()` reports status `0` (no response
  was received) and `responseBody()` returns `null`. The underlying
  transport exception is chained as `->getPrevious()`, so tests assert
  the previous exception instead of a body.

`403` in particular is the signature signal that management-API access
has not been granted for the Bing Places account (see the
[restricted-access guide](restricted-access.md)); it is a legitimate
`ApiException`, not a client bug.

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
