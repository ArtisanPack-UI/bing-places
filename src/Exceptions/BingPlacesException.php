<?php

/**
 * Base exception for the Bing Places package.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Exceptions;

use RuntimeException;

/**
 * Marker base class for every exception thrown by this package.
 *
 * Callers can `catch ( BingPlacesException $e )` to handle any failure
 * originating in this package without also swallowing unrelated runtime
 * exceptions.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class BingPlacesException extends RuntimeException
{
}
