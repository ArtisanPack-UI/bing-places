# ArtisanPack UI Bing Places

OAuth-free, UI-free API client for the Bing Places for Business management API. Part of the ArtisanPack UI Local SEO stack; mirrors the shape of `artisanpack-ui/google-business-profile` (typed client, DTOs, `TokenProvider` contract, shared `Http` factory for `Http::fake()`) and consumes the `TokenProvider` published by `artisanpack-ui/microsoft-oauth`.

> **Access to the Bing Places management API is restricted.** Microsoft grants it through its agency / partner program, not through self-service signup. Until access is granted for a given Bing Places account, live API calls will fail. This package is designed to be developed and released against `Http::fake()` fixtures so the code path is ready the day access lands. See the [restricted-access reality](docs/guide/restricted-access.md) for what is and is not gated.

## Installation

```
composer require artisanpack-ui/bing-places
```

The service provider and the `BingPlaces` facade alias are auto-registered via Laravel's package discovery.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- `artisanpack-ui/core`
- `artisanpack-ui/microsoft-oauth` — supplies the `TokenProvider` this package consumes. Added as a runtime dependency once the API client work lands; until then, host applications can bind any implementation of the contract themselves.

## `TokenProvider` contract

Every client this package ships accepts a `TokenProvider` in its constructor and calls `accessTokenFor( $userId )` on it before each outgoing request. No OAuth logic lives in this package — the host application binds whichever implementation fits.

```php
use ArtisanPackUI\MicrosoftOAuth\Contracts\TokenProvider;

$this->app->bind( TokenProvider::class, function () {
    // In Keystone CMS this resolves to the MicrosoftOAuth-backed provider,
    // which handles refresh and per-user connection lookup transparently.
    return new MyTokenProvider();
} );
```

See the [Token provider guide](docs/guide/token-provider.md) for stub, cached, and MicrosoftOAuth-backed examples.

## Sync from Google Business Profile

The Bing Places management API is restricted, but Bing Places itself offers a first-party **"Sync from Google Business Profile"** import that most listings should use. When a business is already published to Google Business Profile, running the sync in the Bing Places UI is the recommended path — Bing pulls location data, hours, categories, and photos directly from Google, no management-API access required.

This package still ships the management-API client so that:

- Ongoing updates (post-sync edits, review replies, media uploads) can be pushed programmatically once API access is granted.
- Hosts that cannot rely on GBP as the source of truth (chains that publish to Bing independently, businesses without an active GBP profile) have a code path ready.

For most Keystone CMS deployments the recommendation is: **sync from GBP first, then use this package for the incremental writes management-API access unlocks.** See the [GBP-sync path guide](docs/guide/gbp-sync.md) for the workflow.

## Usage

Full usage docs will land as feature code is added. The container binding and helper are already available:

```php
use ArtisanPackUI\BingPlaces\Facades\BingPlaces;

BingPlaces::…;        // static facade
bingPlaces();         // helper
app( 'bing-places' ); // container binding
```

## Documentation

Full documentation lives in the [`docs/`](docs/home.md) directory:

- [Home](docs/home.md) — package overview and what's inside.
- [Getting started](docs/getting-started.md) — install, bind a `TokenProvider`, and understand the restricted-access implication before your first call.
- [Guide](docs/guide.md) — the `TokenProvider` contract, the restricted-access reality, the GBP-sync path, and testing with `Http::fake()`.
- [Reference](docs/reference.md) — API surface map (populated as client work lands).

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
