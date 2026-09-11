<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\Business;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\BusinessList;

test( 'hydrates a page of Business DTOs plus pagination and totals', function (): void {
    $list = BusinessList::fromArray( [
        'businesses'    => [
            [ 'id' => 'biz-1', 'businessName' => 'Shop 1' ],
            [ 'id' => 'biz-2', 'businessName' => 'Shop 2' ],
        ],
        'nextPageToken' => 'page-2',
        'totalSize'     => 42,
    ] );

    expect( $list->businesses )->toHaveCount( 2 );
    expect( $list->businesses[0] )->toBeInstanceOf( Business::class );
    expect( $list->businesses[0]->id )->toBe( 'biz-1' );
    expect( $list->businesses[1]->id )->toBe( 'biz-2' );
    expect( $list->nextPageToken )->toBe( 'page-2' );
    expect( $list->totalSize )->toBe( 42 );
    expect( $list->hasMore() )->toBeTrue();
} );

test( 'treats a missing businesses key as an empty page', function (): void {
    $list = BusinessList::fromArray( [] );

    expect( $list->businesses )->toBe( [] );
    expect( $list->nextPageToken )->toBeNull();
    expect( $list->totalSize )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'skips malformed business rows (empty, list-shaped, scalar)', function (): void {
    $list = BusinessList::fromArray( [
        'businesses' => [
            [ 'id' => 'biz-1', 'businessName' => 'Real' ],
            [],
            [ 'not', 'an', 'object' ],
            'scalar',
            null,
        ],
    ] );

    expect( $list->businesses )->toHaveCount( 1 );
    expect( $list->businesses[0]->id )->toBe( 'biz-1' );
} );

test( 'nextPageToken is null when empty and hasMore is false on the final page', function (): void {
    $list = BusinessList::fromArray( [
        'businesses'    => [ [ 'id' => 'biz-1', 'businessName' => 'Shop' ] ],
        'nextPageToken' => '',
    ] );

    expect( $list->nextPageToken )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'rejects non-string nextPageToken (array, int) as null without a PHP warning', function (): void {
    $arrayToken = BusinessList::fromArray( [
        'businesses'    => [],
        'nextPageToken' => [ 'not', 'a', 'string' ],
    ] );

    $intToken = BusinessList::fromArray( [
        'businesses'    => [],
        'nextPageToken' => 42,
    ] );

    expect( $arrayToken->nextPageToken )->toBeNull();
    expect( $arrayToken->hasMore() )->toBeFalse();
    expect( $intToken->nextPageToken )->toBeNull();
    expect( $intToken->hasMore() )->toBeFalse();
} );

test( 'coerces numeric-string totalSize into an int', function (): void {
    $list = BusinessList::fromArray( [
        'businesses' => [],
        'totalSize'  => '17',
    ] );

    expect( $list->totalSize )->toBe( 17 );
} );
