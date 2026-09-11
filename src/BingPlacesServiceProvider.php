<?php

/**
 * Bing Places service provider.
 *
 * Bootstraps the Bing Places package by registering services and bindings.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Bing Places package.
 *
 * Bootstraps the package by registering services and bindings. Future
 * releases will publish the package configuration, load routes, and
 * register the Bing Places API client bindings here.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class BingPlacesServiceProvider extends ServiceProvider
{
    /**
     * Registers any application services.
     *
     * Binds the BingPlaces class as a singleton in the container.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton( 'bing-places', function ( $app ) {
            return new BingPlaces();
        } );
    }

    /**
     * Bootstraps any application services.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        // Config publishing, migrations, routes, etc. will be added in future releases.
    }
}
