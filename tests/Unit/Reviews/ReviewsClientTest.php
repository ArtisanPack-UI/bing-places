<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\ReviewList;
use ArtisanPackUI\BingPlaces\Reviews\ReviewsClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

class BingReviewsTokenProvider implements TokenProvider
{
    public function accessToken(): string
    {
        return 'stub-access-token';
    }
}

function bingReviewsClient( HttpFactory $factory ): ReviewsClient
{
    return new ReviewsClient(
        tokenProvider: new BingReviewsTokenProvider(),
        http: $factory,
        timeout: 5,
        maxAttempts: 1,
        retrySleepMs: 0,
    );
}

test( 'listReviews hits the businesses/{id}/reviews endpoint and hydrates a ReviewList', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses/biz-1/reviews*' => $factory::response( [
            'reviews'       => [
                [ 'id' => 'rev-1', 'rating' => 5, 'comment' => 'Great' ],
                [ 'id' => 'rev-2', 'rating' => 4 ],
            ],
            'nextPageToken' => 'page-2',
            'totalSize'     => 5,
            'averageRating' => 4.5,
        ], 200 ),
    ] );

    $list = bingReviewsClient( $factory )->listReviews( 'biz-1', pageSize: 25, pageToken: 'page-1' );

    expect( $list )->toBeInstanceOf( ReviewList::class );
    expect( $list->reviews )->toHaveCount( 2 );
    expect( $list->reviews[0]->id )->toBe( 'rev-1' );
    expect( $list->averageRating )->toBe( 4.5 );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return 'GET' === $request->method()
            && str_starts_with( $url, 'https://bingplaces.microsoft.com/api/v2/businesses/biz-1/reviews' )
            && str_contains( $url, 'pageSize=25' )
            && str_contains( $url, 'pageToken=page-1' );
    } );
} );

test( 'listReviews rejects an empty businessId', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    expect( fn () => bingReviewsClient( $factory )->listReviews( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'listReviews rejects a pageSize outside the documented range', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = bingReviewsClient( $factory );

    expect( fn () => $client->listReviews( 'biz-1', pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn () => $client->listReviews( 'biz-1', pageSize: ReviewsClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'listReviews percent-encodes reserved characters in the businessId path segment', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'reviews' => [] ], 200 ) ] );

    bingReviewsClient( $factory )->listReviews( 'biz/space bar' );

    $factory->assertSent( function ( Request $request ): bool {
        return str_starts_with(
            $request->url(),
            'https://bingplaces.microsoft.com/api/v2/businesses/biz%2Fspace%20bar/reviews',
        );
    } );
} );
