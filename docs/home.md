---
title: ArtisanPack UI Bing Places
---

# ArtisanPack UI Bing Places

OAuth-free, UI-free API client for the Bing Places for Business management
API. Part of the ArtisanPack UI Local SEO stack. Mirrors the shape of
`artisanpack-ui/google-business-profile` (typed client, DTOs, `TokenProvider`
contract, shared `Http` factory for `Http::fake()`) and consumes the
`TokenProvider` published by `artisanpack-ui/microsoft-oauth`.

## Restricted-access reality

Access to the Bing Places management API is granted by Microsoft through
its agency / partner program, not through self-service signup. Until
access is granted for a given Bing Places account, live API calls will
fail. This package is designed to be developed and released against
`Http::fake()` fixtures so the code path is ready the day access lands.

The recommended first-party alternative for most listings is Bing Places'
own **"Sync from Google Business Profile"** import. See the
[GBP-sync path guide](guide/gbp-sync.md) for when to reach for it.

## What's in this package

- A **`TokenProvider` contract** — consumed from
  `artisanpack-ui/microsoft-oauth`. Every client calls
  `accessTokenFor( $userId )` on it before each outgoing request. No OAuth
  lives here; the host binds whichever provider fits.
- A **typed API client** — resource clients that inherit a shared
  `BaseClient` (auth, retries, JSON encoding, error mapping) and decode
  responses into typed DTOs. Client and DTO classes land in follow-up
  issues; the [reference map](reference.md) is populated as they land.
- **First-class `Http::fake()` support** — the client resolves the same
  factory the `Http` facade resolves, so tests can stub responses without
  any bespoke test doubles. Fixtures ship alongside the client so the
  package can be developed and exercised end-to-end while management-API
  access is still pending.
- **A `bing-places` container binding and facade** — auto-registered via
  Laravel's package discovery. Feature methods (client factories, DTOs,
  scope helpers) are added as the integration is fleshed out.

## Documentation

- [Getting started](getting-started.md) — install the package, bind a
  `TokenProvider`, and understand the restricted-access implication
  before your first call.
- [Guide](guide.md) — worked notes on the token contract, the
  restricted-access reality, the GBP-sync path, and testing with
  `Http::fake()`.
- [Reference](reference.md) — API surface map. Populated as client work
  lands.
