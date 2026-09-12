---
title: Getting Started
---

# Getting Started

This page walks through installing the package, binding a `TokenProvider`,
and understanding the restricted-access implication before your first
call.

## Installation

```bash
composer require artisanpack-ui/bing-places
```

The service provider and the `BingPlaces` facade alias are auto-registered
via Laravel's package discovery. There is no `migrate` step — the package
holds no state of its own.

## Prerequisites

Before the client can talk to the real Bing Places for Business
management API you need:

1. A Microsoft Bing Places for Business account that has been **granted
   management-API access by Microsoft**. Access is gated behind
   Microsoft's agency / partner program and is not self-service.
   Development against `Http::fake()` does not require access; production
   traffic does. See the [restricted-access reality](guide/restricted-access.md)
   for the full breakdown.
2. A Microsoft OAuth access token issued for the scope the Bing Places
   API requires. The token can be minted by
   [`artisanpack-ui/microsoft-oauth`](https://github.com/ArtisanPack-UI/microsoft-oauth)
   or by any other source the host wires up.
3. A binding for `ArtisanPackUI\BingPlaces\Contracts\TokenProvider` in
   the host application's service container — see the next section.

## Bind a `TokenProvider`

Every client accepts an `ArtisanPackUI\BingPlaces\Contracts\TokenProvider`
and calls `accessToken()` on it before each request. Bind whichever
implementation fits your host:

```php
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    // In Keystone CMS this resolves to an adapter that delegates to the
    // manager exposed by artisanpack-ui/microsoft-oauth, which handles
    // refresh and connection lookup. Anywhere else, wire up whatever
    // exposes a fresh access token.
    return new MyTokenProvider();
} );
```

See the [`TokenProvider` guide](guide/token-provider.md) for stub, cached,
and MicrosoftOAuth-backed examples.

## Before your first call: understand the access gate

Unlike Google Business Profile — where every developer can request API
access through a public form — Bing Places' management API is granted
through Microsoft's agency / partner program. There is no self-service
path. The practical implications:

- **Local development is unblocked.** Write and test all client code
  against `Http::fake()` — the [testing guide](guide/testing.md) covers
  how the shared `Http` factory makes fakes trivial.
- **Live requests will `403` without partner access.** Bing Places
  documents `403` for missing credentials, a missing client certificate,
  or an account that is not enrolled as a Trusted Partner. The client
  surfaces those as `ApiException`. Treat that exception as the signal
  that partner access is not yet granted (or has been revoked), not as a
  bug in the client. Rejected-token authentication failures are surfaced
  as `401` instead.
- **For most listings, prefer Bing's GBP sync.** Bing Places offers a
  first-party import from Google Business Profile that does not need
  management-API access. See the [GBP-sync path guide](guide/gbp-sync.md)
  for when to reach for it and when to skip straight to the API.

## Make your first call

The package ships two resource clients:
`ArtisanPackUI\BingPlaces\Businesses\BusinessesClient` and
`ArtisanPackUI\BingPlaces\Reviews\ReviewsClient`. Resolve either from
the container; the `TokenProvider` and the shared `Http` factory are
wired in automatically:

```php
use ArtisanPackUI\BingPlaces\Businesses\BusinessesClient;

$client = app( BusinessesClient::class );
$page   = $client->listBusinesses();

foreach ( $page->businesses as $business ) {
    logger()->info( $business->id . ': ' . $business->businessName );
}
```

Every response is decoded into a typed DTO — `Business`, `BusinessList`,
`Review`, `ReviewList` — under each family's `DataTransferObjects/`
namespace. See the [API surface map](reference/api-families.md) for the
full list of methods and DTOs.

## Next steps

- Read the [restricted-access reality](guide/restricted-access.md) so
  the failure mode when access has not been granted is not a surprise.
- Learn how [testing with `Http::fake()`](guide/testing.md) exercises
  the client end-to-end without ever touching Microsoft.
- Understand when to use the [GBP-sync path](guide/gbp-sync.md) instead
  of, or in addition to, the management API.
