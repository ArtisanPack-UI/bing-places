<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\BingPlaces;

it( 'resolves the bing-places binding from the container', function (): void {
    expect( app( 'bing-places' ) )->toBeInstanceOf( BingPlaces::class );
} );

it( 'exposes the bingPlaces() helper', function (): void {
    expect( bingPlaces() )->toBeInstanceOf( BingPlaces::class );
} );
