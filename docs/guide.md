---
title: Guide
---

# Guide

Practical guides for wiring up the Bing Places package inside a host
application or downstream ArtisanPack UI package.

## Topics

- [Token provider contract](guide/token-provider.md) — the
  `accessTokenFor( $userId )` interface consumed from
  `artisanpack-ui/microsoft-oauth`, plus stub, cached, and
  MicrosoftOAuth-backed binding patterns.
- [Restricted-access reality](guide/restricted-access.md) — what
  Microsoft's agency / partner program gates, what it does not, and how
  to keep local development unblocked while access is pending.
- [Sync from Google Business Profile](guide/gbp-sync.md) — Bing Places'
  first-party GBP import, when to reach for it, and how it fits
  alongside the management-API path this package wraps.
- [Testing with `Http::fake()`](guide/testing.md) — how the shared
  `Http` factory makes fakes trivial, how retries interact with fake
  sequences, and how `ApiException` maps to non-2xx responses and
  transport failures.

See also: [API surface map](reference/api-families.md) for the reference
map of methods each client exposes (populated as client work lands).
