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
   traffic does. See [restricted-access reality](guide/restricted-access.md)
   for the full breakdown.
2. A Microsoft OAuth access token issued for the scope the Bing Places
   API requires. The token is minted by
   [`artisanpack-ui/microsoft-oauth`](https://github.com/ArtisanPack-UI/microsoft-oauth)
   or any implementation of its `TokenProvider` contract.
3. A `TokenProvider` binding in the host application's service container
   — see the next section.

## Bind a `TokenProvider`

Every client accepts a `TokenProvider` and calls `accessTokenFor( $userId )`
on it before each request. Bind whichever implementation fits your host:

```php
use ArtisanPackUI\MicrosoftOAuth\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    // In Keystone CMS, this resolves to the MicrosoftOAuth-backed
    // provider, which handles per-user connection lookup and refresh.
    // Anywhere else, wire up whatever exposes a fresh access token.
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
- **Live requests will `401` / `403` without access.** The client
  surfaces those as `ApiException`. Treat the exception as the signal
  that access is not yet granted (or has been revoked), not as a bug in
  the client.
- **For most listings, prefer Bing's GBP sync.** Bing Places offers a
  first-party import from Google Business Profile that does not need
  management-API access. See the [GBP-sync path guide](guide/gbp-sync.md)
  for when to reach for it and when to skip straight to the API.

## Make your first call

The client and DTOs land in follow-up feature issues on the
`release/1.0` branch. Once they are in place, resolving a client from
the container will look like:

```php
use ArtisanPackUI\BingPlaces\Client\BingPlacesClient;

$client = app( BingPlacesClient::class );
// … call typed methods; each returns a typed DTO.
```

Until then, the [`bingPlaces()` helper](../README.md#usage), the
`BingPlaces` facade, and the `bing-places` container binding are the
extension points, and the [reference map](reference.md) is updated as
each client family lands.

## Next steps

- Read the [restricted-access reality](guide/restricted-access.md) so
  the failure mode when access has not been granted is not a surprise.
- Learn how [testing with `Http::fake()`](guide/testing.md) exercises
  the client end-to-end without ever touching Microsoft.
- Understand when to use the [GBP-sync path](guide/gbp-sync.md) instead
  of, or in addition to, the management API.
