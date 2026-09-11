<?php

/**
 * Bing Places helper functions.
 *
 * Global helper functions for the Bing Places package.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */

use ArtisanPackUI\BingPlaces\BingPlaces;

if ( ! function_exists( 'bingPlaces' ) ) {
    /**
     * Gets the BingPlaces instance.
     *
     * @since 1.0.0
     *
     * @return BingPlaces
     */
    function bingPlaces(): BingPlaces
    {
        return app( 'bing-places' );
    }
}
