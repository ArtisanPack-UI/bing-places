---
title: Token Provider Contract
---

# Token Provider Contract

Every resource client in this package accepts a `TokenProvider` in its
constructor and calls `accessTokenFor( $userId )` on it before each
outgoing request. The client itself performs no OAuth — it delegates
token acquisition (and refresh) to whichever provider the host
application binds.

The contract is not defined in this package. It is consumed directly
from `artisanpack-ui/microsoft-oauth`, so any host that wires that
package's OAuth manager gets a valid binding for free.

## The interface

```php
namespace ArtisanPackUI\MicrosoftOAuth\Contracts;

interface TokenProvider
{
    /**
     * Return a valid Microsoft OAuth access token for the given user.
     *
     * Implementations MUST refresh the token if the stored one is expired,
     * and MUST return a non-empty bearer token suitable for use verbatim
     * as `Authorization: Bearer …`.
     *
     * @throws MissingConnectionException When the user has no Microsoft
     *                                    connection on file at all.
     * @throws TokenRefreshException      When a connection exists but its
     *                                    token cannot be refreshed.
     */
    public function accessTokenFor( int|string $userId ): string;
}
```

Three properties matter:

1. **The provider is per-user.** Every call takes the application user
   id whose Microsoft connection the token is drawn from. Multi-tenant
   hosts pass whichever user id owns the Bing Places connection for the
   request.
2. **It is called on every request.** There is no client-side cache. If
   the provider is expensive to invoke, cache inside the provider.
3. **The returned token must already be fresh.** The client attaches it
   verbatim as `Authorization: Bearer <token>` and does not attempt to
   refresh on `401`.

## Binding patterns

### Stub (tests, local dev)

```php
use ArtisanPackUI\MicrosoftOAuth\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    return new class implements TokenProvider {
        public function accessTokenFor( int|string $userId ): string
        {
            return 'test-token';
        }
    };
} );
```

Paired with `Http::fake()`, this is enough to exercise every client
without ever touching Microsoft. This is the recommended binding for
the whole test suite while management-API access is still pending.

### Cached provider

```php
use ArtisanPackUI\MicrosoftOAuth\Contracts\TokenProvider;
use Illuminate\Support\Facades\Cache;

class CachedTokenProvider implements TokenProvider
{
    public function accessTokenFor( int|string $userId ): string
    {
        return Cache::remember(
            "bing-places:token:$userId",
            now()->addMinutes( 55 ),
            function () use ( $userId ) {
                return $this->mintFreshToken( $userId );
            },
        );
    }

    private function mintFreshToken( int|string $userId ): string
    {
        // …
    }
}
```

Microsoft access tokens are typically valid for 60 minutes; caching for
55 leaves a safe refresh window.

### MicrosoftOAuth-backed (Keystone and other hosts)

The `artisanpack-ui/microsoft-oauth` package ships the default binding.
Its provider resolves the current `MicrosoftConnection` for the given
user id, refreshes the stored token transparently when it has expired,
and returns the bearer string. Hosts that install
`artisanpack-ui/microsoft-oauth` and register its service provider
inherit the binding — no adapter code required.

If the host stores Bing Places connections under a different model or
identifier, wrap the manager in an adapter that maps the incoming
`$userId` to whatever the underlying store keys on, and bind that
adapter to `TokenProvider`.

## Failure handling

If the provider cannot supply a token, throw. The
`MicrosoftOAuth`-backed default already surfaces two typed exceptions:

- `MissingConnectionException` — the user has no Microsoft connection
  on file. The caller should route them through the initial connect
  flow rather than retrying.
- `TokenRefreshException` — a connection exists but its token could
  not be refreshed into a usable value.

The client makes no attempt to recover from either. An unauthenticated
request that does reach Microsoft will be rejected with `401`, and the
client surfaces that as an `ApiException`. Surfacing the failure from
the provider instead lets the caller distinguish "we never had a token"
from "Microsoft rejected our token".

See also: [Testing with `Http::fake()`](testing.md) for stubbing the
provider under fakes.
