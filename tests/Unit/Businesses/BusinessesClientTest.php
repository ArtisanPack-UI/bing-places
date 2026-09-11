<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Businesses\BusinessesClient;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\Business;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\BusinessList;
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;

/**
 * TokenProvider that hands out a fixed access token so client tests can
 * assert the outgoing Bearer header without wiring a real OAuth stack.
 */
class BingBusinessesTokenProvider implements TokenProvider
{
    public function accessToken(): string
    {
        return 'stub-access-token';
    }
}

function bingBusinessesClient( HttpFactory $factory ): BusinessesClient
{
    return new BusinessesClient(
        tokenProvider: new BingBusinessesTokenProvider(),
        http: $factory,
        timeout: 5,
        maxAttempts: 1,
        retrySleepMs: 0,
    );
}

test( 'listBusinesses hits the businesses endpoint and hydrates a BusinessList', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses*' => $factory::response( [
            'businesses'    => [
                [ 'id' => 'biz-1', 'businessName' => 'Shop 1' ],
                [ 'id' => 'biz-2', 'businessName' => 'Shop 2' ],
            ],
            'nextPageToken' => 'page-2',
            'totalSize'     => 5,
        ], 200 ),
    ] );

    $list = bingBusinessesClient( $factory )->listBusinesses( pageSize: 50, pageToken: 'page-1', filter: 'city:Springfield' );

    expect( $list )->toBeInstanceOf( BusinessList::class );
    expect( $list->businesses )->toHaveCount( 2 );
    expect( $list->businesses[0]->id )->toBe( 'biz-1' );
    expect( $list->nextPageToken )->toBe( 'page-2' );
    expect( $list->totalSize )->toBe( 5 );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return 'GET' === $request->method()
            && str_starts_with( $url, 'https://bingplaces.microsoft.com/api/v2/businesses' )
            && str_contains( $url, 'pageSize=50' )
            && str_contains( $url, 'pageToken=page-1' )
            && str_contains( $url, 'filter=city' );
    } );
} );

test( 'listBusinesses rejects a pageSize outside the documented range', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = bingBusinessesClient( $factory );

    expect( fn () => $client->listBusinesses( pageSize: 0 ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn () => $client->listBusinesses( pageSize: BusinessesClient::MAX_PAGE_SIZE + 1 ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'listBusinesses omits optional query params when null or empty', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'businesses' => [] ], 200 ) ] );

    bingBusinessesClient( $factory )->listBusinesses( pageToken: '', filter: '' );

    $factory->assertSent( function ( Request $request ): bool {
        $url = $request->url();

        return 'https://bingplaces.microsoft.com/api/v2/businesses' === $url;
    } );
} );

test( 'getBusiness fetches a single business and hydrates the DTO', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses/biz-1' => $factory::response( [
            'id'           => 'biz-1',
            'storeId'      => 'store-42',
            'businessName' => 'ArtisanPack Bakery',
            'website'      => 'https://example.test',
        ], 200 ),
    ] );

    $business = bingBusinessesClient( $factory )->getBusiness( 'biz-1' );

    expect( $business )->toBeInstanceOf( Business::class );
    expect( $business->id )->toBe( 'biz-1' );
    expect( $business->storeId )->toBe( 'store-42' );
    expect( $business->businessName )->toBe( 'ArtisanPack Bakery' );
    expect( $business->website )->toBe( 'https://example.test' );
} );

test( 'getBusiness rejects an empty id', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    expect( fn () => bingBusinessesClient( $factory )->getBusiness( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'getBusiness percent-encodes ids with reserved characters', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [ 'id' => 'biz/space bar', 'businessName' => 'Shop' ], 200 ) ] );

    bingBusinessesClient( $factory )->getBusiness( 'biz/space bar' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'https://bingplaces.microsoft.com/api/v2/businesses/biz%2Fspace%20bar' === $request->url();
    } );
} );

test( 'createBusiness POSTs the payload and hydrates the created listing', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses' => $factory::response( [
            'id'           => 'biz-new',
            'storeId'      => 'store-1',
            'businessName' => 'Shop',
            'verification' => [ 'status' => 'pending' ],
            'createdAt'    => '2026-01-01T00:00:00Z',
        ], 200 ),
    ] );

    $business = bingBusinessesClient( $factory )->createBusiness( [
        'storeId'      => 'store-1',
        'businessName' => 'Shop',
        'address'      => [ 'addressLine1' => '1 Main St', 'city' => 'Springfield' ],
    ] );

    expect( $business->id )->toBe( 'biz-new' );
    expect( $business->businessName )->toBe( 'Shop' );
    expect( $business->verification )->toBe( [ 'status' => 'pending' ] );

    $factory->assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && 'https://bingplaces.microsoft.com/api/v2/businesses' === $request->url()
            && 'store-1' === $request->data()['storeId']
            && 'Shop' === $request->data()['businessName']
            && str_contains( $request->header( 'Content-Type' )[0], 'application/json' );
    } );
} );

test( 'createBusiness rejects an empty payload', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    expect( fn () => bingBusinessesClient( $factory )->createBusiness( [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'patchBusiness PATCHes the payload and hydrates the updated listing', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [
        'bingplaces.microsoft.com/api/v2/businesses/biz-1' => $factory::response( [
            'id'           => 'biz-1',
            'businessName' => 'Updated Shop',
            'phone'        => '+1-555-9999',
        ], 200 ),
    ] );

    $business = bingBusinessesClient( $factory )->patchBusiness( 'biz-1', [
        'phone' => '+1-555-9999',
    ] );

    expect( $business )->toBeInstanceOf( Business::class );
    expect( $business?->phone )->toBe( '+1-555-9999' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'PATCH' === $request->method()
            && 'https://bingplaces.microsoft.com/api/v2/businesses/biz-1' === $request->url()
            && '+1-555-9999' === $request->data()['phone'];
    } );
} );

test( 'patchBusiness returns null on a successful validateOnly dry run with empty body', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 200 ) ] );

    $result = bingBusinessesClient( $factory )->patchBusiness( 'biz-1', [ 'phone' => '+1' ], validateOnly: true );

    expect( $result )->toBeNull();

    $factory->assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'validateOnly=true' );
    } );
} );

test( 'patchBusiness rejects an empty id or empty payload', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( [], 200 ) ] );

    $client = bingBusinessesClient( $factory );

    expect( fn () => $client->patchBusiness( '', [ 'phone' => '+1' ] ) )
        ->toThrow( InvalidArgumentException::class );

    expect( fn () => $client->patchBusiness( 'biz-1', [] ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );

test( 'deleteBusiness DELETEs the resource and returns void on 204', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 204 ) ] );

    bingBusinessesClient( $factory )->deleteBusiness( 'biz-1' );

    $factory->assertSent( function ( Request $request ): bool {
        return 'DELETE' === $request->method()
            && 'https://bingplaces.microsoft.com/api/v2/businesses/biz-1' === $request->url();
    } );
} );

test( 'deleteBusiness rejects an empty id', function (): void {
    $factory = new HttpFactory();
    $factory->fake( [ '*' => $factory::response( '', 204 ) ] );

    expect( fn () => bingBusinessesClient( $factory )->deleteBusiness( '' ) )
        ->toThrow( InvalidArgumentException::class );

    $factory->assertNothingSent();
} );
