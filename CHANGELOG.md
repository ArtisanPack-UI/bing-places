# ArtisanPack UI Bing Places Changelog

## [1.0.0] - 2026-09-18

### Added
- Scaffold the package from `artisanpack-ui/package-blueprint`: composer manifest, service provider, main class, facade, helper, PHPCS + PHP-CS-Fixer config, and Pest test harness (#1).
- Add the `TokenProvider` contract so Bing Places delegates Microsoft OAuth token acquisition to `artisanpack-ui/microsoft-oauth` (or any user-supplied implementation) instead of managing credentials itself (#2).
- Add the Http-facade `BaseClient` foundation: Bearer-token injection from the resolved `TokenProvider`, JSON accept headers, retry on 429/5xx with backoff, and consistent error mapping for every downstream client (#3).
- Add a contract-first, typed management API surface for Bing Places for Business (#4):
  - `BusinessesClient` — `list`, `get`, `create`, `patch`, `delete` with `Business` and `BusinessList` DTOs.
  - `ReviewsClient` — `list` with `Review` and `ReviewList` DTOs.
  - Every request rides the shared `BaseClient` so auth, retries, and error mapping stay consistent across endpoints.
- Add an `Http::fake()`-backed contract test suite with fixtures covering every client operation, tightened with operation-specific `Http::assertSent` matchers (#6).

### Documentation
- Add README overview plus a full `docs/` tree covering token wiring via `TokenProvider`, the restricted-access / partner-program flow, and Google Business Profile sync guidance (#5).
