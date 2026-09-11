# ArtisanPack UI Bing Places

A Laravel package that provides a typed API client for the Bing Places for Business API. This is part of the ArtisanPack UI Local SEO stack and mirrors the shape of `artisanpack-ui/google-business-profile`.

> **Note:** The Bing Places management API is restricted (Microsoft agency / partner program). Until access is granted, this package ships the contract, DTOs, and `Http::fake()`-friendly fixtures — no live API calls.

## Installation

```
composer require artisanpack-ui/bing-places
```

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- `artisanpack-ui/core`
- `artisanpack-ui/microsoft-oauth` — will be added as a runtime dependency once the API client work lands; it provides the `TokenProvider` consumed by this package

## Usage

Full usage docs will land as feature code is added. The container binding and helper are already available:

```php
use ArtisanPackUI\BingPlaces\Facades\BingPlaces;

BingPlaces::…; // static facade
bingPlaces();  // helper
app( 'bing-places' ); // container binding
```

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
