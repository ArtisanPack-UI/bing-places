---
title: Token Provider Contract
---

# Token Provider Contract

Every resource client in this package accepts a `TokenProvider` in its
constructor and calls `accessToken()` on it before each outgoing
request. The client itself performs no OAuth — it delegates token
acquisition (and refresh) to whichever provider the host application
binds.

The contract lives in this package. Hosts typically bind it to an
adapter around a Microsoft OAuth manager they already have — in
Keystone CMS, the manager exposed by `artisanpack-ui/microsoft-oauth`.

## The interface

```php
namespace ArtisanPackUI\BingPlaces\Contracts;

interface TokenProvider
{
    /**
     * Return a valid Microsoft OAuth access token to use as the Bearer
     * credential on Bing Places for Business API requests.
     *
     * Implementations are responsible for refreshing expired tokens
     * before returning; the client will use the returned string
     * verbatim.
     *
     * @return string A non-empty OAuth access token.
     */
    public function accessToken(): string;
}
```

Three properties matter:

1. **It is called on every request.** There is no client-side cache. If
   the provider is expensive to invoke (for example, it hits a remote
   OAuth server), cache inside the provider.
2. **The returned token must already be fresh.** The client attaches it
   verbatim as `Authorization: Bearer <token>` and does not attempt to
   refresh on `401`.
3. **The provider is per-host, not per-request.** The client passes no
   user id or other selector to `accessToken()`. Hosts whose token
   choice depends on the current caller wrap that resolution inside
   their own provider (for example, resolving the current user or the
   current tenant from the container before delegating to a shared
   OAuth manager).

## Binding patterns

### Stub (tests, local dev)

```php
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    return new class implements TokenProvider {
        public function accessToken(): string
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
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use Illuminate\Support\Facades\Cache;

class CachedTokenProvider implements TokenProvider
{
    public function __construct( private string $cacheKey ) {}

    public function accessToken(): string
    {
        return Cache::remember(
            $this->cacheKey,
            now()->addMinutes( 55 ),
            function () {
                return $this->mintFreshToken();
            },
        );
    }

    private function mintFreshToken(): string
    {
        // …
    }
}
```

Microsoft access tokens are typically valid for 60 minutes; caching for
55 leaves a safe refresh window.

### MicrosoftOAuth-backed (Keystone and other hosts)

Inside Keystone CMS — and any other host that consumes
`artisanpack-ui/microsoft-oauth` — the plugin binds this package's
`TokenProvider` to a thin adapter around the manager that OAuth package
exposes. That manager already handles refresh, per-user connection
lookup, and storage; the adapter is a one-liner that resolves the
current user from the container, calls the manager's per-user access
token accessor, and returns the string. The binding — and the adapter
— live in the Keystone plugin's service provider, not in this
package.

This package deliberately does not depend on
`artisanpack-ui/microsoft-oauth`. Any implementation of the local
`TokenProvider` contract works, and hosts that use a different OAuth
manager can wire that one up instead without pulling
`artisanpack-ui/microsoft-oauth` in as a transitive dependency.

## Failure handling

If the provider cannot supply a token, throw. The client makes no
attempt to recover — an unauthenticated request will be rejected by
Microsoft with `401`, and the client will surface that as an
`ApiException`. Surfacing the failure from the provider instead lets
the caller distinguish "we never had a token" from "Microsoft rejected
our token".

See also: [Testing with `Http::fake()`](testing.md) for stubbing the
provider under fakes.
