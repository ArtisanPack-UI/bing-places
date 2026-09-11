<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\Business;

test( 'hydrates typed scalar fields and preserves the raw payload', function (): void {
    $payload = [
        'id'           => 'biz-1',
        'storeId'      => 'store-42',
        'businessName' => 'ArtisanPack Bakery',
        'website'      => 'https://example.test',
        'phone'        => '+1-555-0100',
        'description'  => 'A test bakery.',
        'createdAt'    => '2026-01-01T00:00:00Z',
        'updatedAt'    => '2026-01-02T00:00:00Z',
        'unknownField' => 'preserved on raw',
    ];

    $business = Business::fromArray( $payload );

    expect( $business->id )->toBe( 'biz-1' );
    expect( $business->storeId )->toBe( 'store-42' );
    expect( $business->businessName )->toBe( 'ArtisanPack Bakery' );
    expect( $business->website )->toBe( 'https://example.test' );
    expect( $business->phone )->toBe( '+1-555-0100' );
    expect( $business->description )->toBe( 'A test bakery.' );
    expect( $business->createdAt )->toBe( '2026-01-01T00:00:00Z' );
    expect( $business->updatedAt )->toBe( '2026-01-02T00:00:00Z' );
    expect( $business->raw )->toBe( $payload );
} );

test( 'hydrates nested associative sub-resources as arrays', function (): void {
    $business = Business::fromArray( [
        'id'            => 'biz-1',
        'businessName'  => 'Shop',
        'address'       => [ 'addressLine1' => '1 Main St', 'city' => 'Springfield' ],
        'location'      => [ 'latitude' => 40.0, 'longitude' => -80.0 ],
        'categories'    => [ 'primary' => 'Bakery', 'additional' => [ 'Cafe' ] ],
        'businessHours' => [ 'monday' => [ [ 'open' => '09:00', 'close' => '17:00' ] ] ],
        'specialHours'  => [ [ 'date' => '2026-12-25', 'closed' => true ] ],
        'verification'  => [ 'status' => 'verified', 'method' => 'postcard' ],
        'photos'        => [ 'logo' => 'https://example.test/logo.png' ],
    ] );

    expect( $business->address )->toBe( [ 'addressLine1' => '1 Main St', 'city' => 'Springfield' ] );
    expect( $business->location )->toBe( [ 'latitude' => 40.0, 'longitude' => -80.0 ] );
    expect( $business->categories )->toBe( [ 'primary' => 'Bakery', 'additional' => [ 'Cafe' ] ] );
    expect( $business->businessHours )->toBe( [ 'monday' => [ [ 'open' => '09:00', 'close' => '17:00' ] ] ] );
    expect( $business->specialHours )->toBe( [ [ 'date' => '2026-12-25', 'closed' => true ] ] );
    expect( $business->verification )->toBe( [ 'status' => 'verified', 'method' => 'postcard' ] );
    expect( $business->photos )->toBe( [ 'logo' => 'https://example.test/logo.png' ] );
} );

test( 'coerces missing string fields to empty and optional fields to null', function (): void {
    $business = Business::fromArray( [] );

    expect( $business->id )->toBe( '' );
    expect( $business->storeId )->toBe( '' );
    expect( $business->businessName )->toBe( '' );
    expect( $business->website )->toBeNull();
    expect( $business->phone )->toBeNull();
    expect( $business->description )->toBeNull();
    expect( $business->address )->toBeNull();
    expect( $business->location )->toBeNull();
    expect( $business->categories )->toBeNull();
    expect( $business->businessHours )->toBeNull();
    expect( $business->specialHours )->toBeNull();
    expect( $business->verification )->toBeNull();
    expect( $business->photos )->toBeNull();
    expect( $business->createdAt )->toBeNull();
    expect( $business->updatedAt )->toBeNull();
    expect( $business->raw )->toBe( [] );
} );

test( 'ignores scalar values where an object sub-resource is expected', function (): void {
    $business = Business::fromArray( [
        'id'      => 'biz-1',
        'address' => 'not-an-array',
    ] );

    expect( $business->address )->toBeNull();
    expect( $business->raw['address'] )->toBe( 'not-an-array' );
} );
