<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\Review;

test( 'hydrates typed scalar fields and preserves the raw payload', function (): void {
    $payload = [
        'id'        => 'rev-1',
        'rating'    => 5,
        'comment'   => 'Fantastic.',
        'source'    => 'Yelp',
        'reviewer'  => [ 'displayName' => 'Alex', 'avatar' => 'https://example.test/a.png' ],
        'createdAt' => '2026-02-01T12:00:00Z',
        'updatedAt' => '2026-02-01T12:30:00Z',
    ];

    $review = Review::fromArray( $payload );

    expect( $review->id )->toBe( 'rev-1' );
    expect( $review->rating )->toBe( 5 );
    expect( $review->comment )->toBe( 'Fantastic.' );
    expect( $review->source )->toBe( 'Yelp' );
    expect( $review->reviewer )->toBe( [ 'displayName' => 'Alex', 'avatar' => 'https://example.test/a.png' ] );
    expect( $review->createdAt )->toBe( '2026-02-01T12:00:00Z' );
    expect( $review->updatedAt )->toBe( '2026-02-01T12:30:00Z' );
    expect( $review->raw )->toBe( $payload );
} );

test( 'coerces numeric-string rating into an int', function (): void {
    $review = Review::fromArray( [ 'id' => 'rev-1', 'rating' => '4' ] );

    expect( $review->rating )->toBe( 4 );
} );

test( 'coerces missing rating and comment to null', function (): void {
    $review = Review::fromArray( [ 'id' => 'rev-1' ] );

    expect( $review->rating )->toBeNull();
    expect( $review->comment )->toBeNull();
    expect( $review->source )->toBeNull();
    expect( $review->reviewer )->toBeNull();
} );

test( 'coerces missing id to an empty string', function (): void {
    $review = Review::fromArray( [] );

    expect( $review->id )->toBe( '' );
    expect( $review->raw )->toBe( [] );
} );
