<?php

/**
 * BingPlaces Facade.
 *
 * Provides static access to the BingPlaces class.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * BingPlaces Facade.
 *
 * @see \ArtisanPackUI\BingPlaces\BingPlaces
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class BingPlaces extends Facade
{
    /**
     * Gets the registered name of the component.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bing-places';
    }
}
