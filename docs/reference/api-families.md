---
title: API Surface Map
---

# API Surface Map

Unlike Google Business Profile — which splits its API across four
hosts — the Bing Places for Business management API is a single-host
surface. This page lists every method the resource clients expose,
alongside the DTO each method returns.

## Base URL

Both resource clients today build requests against:

```
https://bingplaces.microsoft.com/api/v2
```

This is the contract-first base URL every client returns from its
`baseUrl()` method. When Microsoft grants a different production host
to the partner program, changing `baseUrl()` on each client is
sufficient — the DTO layer and consumer-facing method shapes stay put.

## 1. Businesses

- **Client**: `ArtisanPackUI\BingPlaces\Businesses\BusinessesClient`
- **DTO namespace**: `ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects`

| Method                                                           | Returns          | Purpose                                                                                                                                                        |
|------------------------------------------------------------------|------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `listBusinesses( ?int $pageSize, ?string $pageToken, ?string $filter )` | `BusinessList` | Fetch one page of businesses. `pageSize` is bounded by `BusinessesClient::MAX_PAGE_SIZE`; `nextPageToken` on the result is non-null while more pages exist.    |
| `getBusiness( string $id )`                                      | `Business`       | Fetch a single business by Bing-assigned id.                                                                                                                    |
| `createBusiness( array $business )`                              | `Business`       | Create a new business listing; the API assigns an `id` and echoes the full record back.                                                                        |
| `patchBusiness( string $id, array $business, bool $validateOnly = false )` | `?Business` | Update the writable fields of a single business. When `validateOnly` is true the API validates without persisting and this method returns `null` on empty body. |
| `deleteBusiness( string $id )`                                   | `void`           | Delete a business listing. Responds with `204 No Content` on success.                                                                                          |

DTOs: `Business` (id, storeId, businessName, website, phone,
description, address, location, categories, businessHours, specialHours,
verification, photos, createdAt, updatedAt, raw) and `BusinessList`
(`businesses`, `nextPageToken`, `totalSize`, `hasMore()`).

## 2. Reviews

- **Client**: `ArtisanPackUI\BingPlaces\Reviews\ReviewsClient`
- **DTO namespace**: `ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects`

| Method                                                                       | Returns      | Purpose                                                                                                                                                                             |
|------------------------------------------------------------------------------|--------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `listReviews( string $businessId, ?int $pageSize, ?string $pageToken )`     | `ReviewList` | Fetch one page of reviews for a business. `pageSize` is bounded by `ReviewsClient::MAX_PAGE_SIZE`; `nextPageToken` on the result is non-null while more pages exist. |

DTOs: `Review` (id, rating, comment, source, reviewer, createdAt,
updatedAt, raw) and `ReviewList` (`reviews`, `nextPageToken`,
`totalSize`, `averageRating`, `hasMore()`).

Review-reply and other write endpoints will land in follow-up feature
issues on the `release/1.0` branch.

## Shared behaviour

Every method above:

- Delegates authentication to the injected `TokenProvider` (see the
  [Token provider contract](../guide/token-provider.md)), which is
  called on every request.
- Retries `HTTP 429`, `HTTP 5xx`, and `ConnectionException` — **but
  only for idempotent verbs** (`GET`, `HEAD`, `OPTIONS`). Writes
  (`POST`, `PATCH`, `PUT`, `DELETE`) are surfaced immediately, since
  replaying them could duplicate work the server may already have
  processed. Up to three total attempts by default (two retries after
  the initial request), with a 250ms sleep between attempts. A valid
  positive `Retry-After` header (delta-seconds or HTTP date) overrides
  that sleep for the corresponding attempt.
- Maps every non-2xx response and every terminal `ConnectionException`
  to `ArtisanPackUI\BingPlaces\Exceptions\ApiException`. `403` covers
  both a missing OAuth scope and a Bing Places account that is not
  enrolled in the Trusted Partner / agency program; `401` covers
  rejected tokens. See the
  [restricted-access guide](../guide/restricted-access.md).
- Decodes the JSON response into a typed DTO under the family's
  `DataTransferObjects/` namespace.

See [Testing with `Http::fake()`](../guide/testing.md) for how to
exercise these methods under fakes, and
[Restricted-access reality](../guide/restricted-access.md) for why
`403` from a live call is expected while management-API access is
pending.
