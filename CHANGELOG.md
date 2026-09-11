# ArtisanPack UI Bing Places Changelog

## Unreleased

- Scaffold package from `artisanpack-ui/package-blueprint`: composer manifest, service provider, main class, facade, helper, PHPCS + PHP-CS-Fixer config, and Pest test harness. No feature code yet (#1).
- Model the Bing Places for Business management API as typed clients + DTOs (contract-first pending partner-program access): `BusinessesClient` (list/get/create/patch/delete) with `Business` / `BusinessList` DTOs, and `ReviewsClient` (list) with `Review` / `ReviewList` DTOs. Every request rides the shared `BaseClient` (Bearer token from the injected `TokenProvider`, JSON accept, retry on 429/5xx, error mapping) and is exercised end-to-end with `Http::fake()` fixtures (#4).
