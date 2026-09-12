---
title: API Surface Map
---

# API Surface Map

The Bing Places for Business management API is a single-host surface
(unlike Google Business Profile, which splits its API across four
hosts). This page will list every method the resource clients expose,
alongside the DTO each method returns, once the client work lands on
the `release/1.0` branch.

## Base URL

The production host for the Bing Places for Business management API is:

```
https://ssl.bingplacespartner.microsoft.com/
```

The client encapsulates the full path segment for each resource;
callers only interact with the typed methods.

## Clients

Client and DTO tables are populated as feature issues land. Placeholder
shape, matching the reference map in
`artisanpack-ui/google-business-profile`:

| Client                                     | Purpose                                                                                                                                      |
|--------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------|
| `ArtisanPackUI\BingPlaces\Client\…Client`  | (Populated as client families land — businesses, reviews, media, and their DTOs will appear here alongside the methods each client exposes.) |

Until then, treat the [Getting Started page](../getting-started.md) as
the source of truth for the wiring, the
[TokenProvider contract](../guide/token-provider.md) as the
authentication contract, and the
[Testing guide](../guide/testing.md) as the recipe for exercising the
client against fakes.

## Shared behaviour

Every method the clients expose will:

- Delegate authentication to the injected `TokenProvider` (see the
  [Token provider contract](../guide/token-provider.md)).
- Retry `HTTP 429`, `HTTP 5xx`, and `ConnectionException` — up to three
  total attempts by default (two retries after the initial request),
  with a 250ms sleep between attempts.
- Map every non-2xx response and every terminal `ConnectionException`
  to `ArtisanPackUI\BingPlaces\Exceptions\ApiException`.
- Decode the JSON response into a typed DTO under the client's
  `DataTransferObjects/` namespace.

See [Testing with `Http::fake()`](../guide/testing.md) for how to
exercise these methods under fakes, and
[Restricted-access reality](../guide/restricted-access.md) for why
`403` from a live call is expected while management-API access is
pending.
