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

### TokenProvider contract

This package performs no OAuth. The API client (once introduced) accepts an
implementation of `ArtisanPackUI\BingPlaces\Contracts\TokenProvider`, which
returns a valid Microsoft OAuth access token to use as the Bearer credential on
Bing Places for Business API requests:

```php
namespace ArtisanPackUI\BingPlaces\Contracts;

interface TokenProvider
{
    public function accessToken(): string;
}
```

Implementations are responsible for refreshing expired tokens before returning;
the client will use the returned string verbatim.

**Intended binding.** Host applications bind the contract to whichever service
supplies Microsoft OAuth access tokens. In Keystone (and any other consumer of
[`artisanpack-ui/microsoft-oauth`](https://github.com/ArtisanPack-UI/microsoft-oauth)),
this is the manager exposed by that package. A typical binding in an
application service provider looks like:

```php
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use ArtisanPackUI\MicrosoftOauth\Support\MicrosoftTokenProvider;

$this->app->bind( TokenProvider::class, MicrosoftTokenProvider::class );
```

The exact class name from `artisanpack-ui/microsoft-oauth` will be documented
once that package's manager surface is finalized; the shape above is stable.
Tests may bind a stub returning a fixed string.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
