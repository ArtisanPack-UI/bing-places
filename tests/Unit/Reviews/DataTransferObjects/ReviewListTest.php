<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\Review;
use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\ReviewList;

test( 'hydrates a page of Review DTOs plus pagination, totals, and average rating', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'       => [
            [ 'id' => 'rev-1', 'rating' => 5 ],
            [ 'id' => 'rev-2', 'rating' => 4 ],
        ],
        'nextPageToken' => 'page-2',
        'totalSize'     => 128,
        'averageRating' => 4.6,
    ] );

    expect( $list->reviews )->toHaveCount( 2 );
    expect( $list->reviews[0] )->toBeInstanceOf( Review::class );
    expect( $list->reviews[0]->id )->toBe( 'rev-1' );
    expect( $list->nextPageToken )->toBe( 'page-2' );
    expect( $list->totalSize )->toBe( 128 );
    expect( $list->averageRating )->toBe( 4.6 );
    expect( $list->hasMore() )->toBeTrue();
} );

test( 'treats a missing reviews key as an empty page', function (): void {
    $list = ReviewList::fromArray( [] );

    expect( $list->reviews )->toBe( [] );
    expect( $list->nextPageToken )->toBeNull();
    expect( $list->totalSize )->toBeNull();
    expect( $list->averageRating )->toBeNull();
    expect( $list->hasMore() )->toBeFalse();
} );

test( 'skips malformed review rows', function (): void {
    $list = ReviewList::fromArray( [
        'reviews' => [
            [ 'id' => 'rev-1', 'rating' => 5 ],
            [],
            'scalar',
            [ 'positional', 'array' ],
        ],
    ] );

    expect( $list->reviews )->toHaveCount( 1 );
    expect( $list->reviews[0]->id )->toBe( 'rev-1' );
} );

test( 'coerces numeric-string averageRating into a float', function (): void {
    $list = ReviewList::fromArray( [
        'reviews'       => [],
        'averageRating' => '3.9',
    ] );

    expect( $list->averageRating )->toBe( 3.9 );
} );
