<?php

declare( strict_types=1 );

use ArtisanPackUI\BingPlaces\Businesses\BusinessesClient;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\Business;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\BusinessList;
use ArtisanPackUI\BingPlaces\Contracts\TokenProvider;
use ArtisanPackUI\BingPlaces\Exceptions\ApiException;
use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\ReviewList;
use ArtisanPackUI\BingPlaces\Reviews\ReviewsClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end contract test suite.
 *
 * These tests exercise the typed client stack the way a downstream
 * consumer will once partner-program access is granted, so the contract
 * is verified before real API access exists. Every fixture is a JSON
 * file on disk — the payload passes through `json_decode` on load, so a
 * malformed fixture fails loudly at read time rather than pretending to
 * be a valid API response.
 */

const BP_BASE_URL = 'https://bingplaces.microsoft.com/api/v2';

/**
 * TokenProvider that hands out a fixed token so the outgoing Bearer
 * header can be asserted end-to-end.
 */
class ContractTokenProvider implements TokenProvider
{
    public function accessToken(): string
    {
        return 'contract-token';
    }
}

function loadFixture( string $relativePath ): array
{
    $path = __DIR__ . '/fixtures/' . ltrim( $relativePath, '/' );

    $raw = file_get_contents( $path );
    if ( false === $raw ) {
        throw new RuntimeException( 'Missing fixture: ' . $path );
    }

    $decoded = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );

    if ( ! is_array( $decoded ) ) {
        throw new RuntimeException( 'Fixture is not a JSON object: ' . $path );
    }

    return $decoded;
}

function bindContractHttp( HttpFactory $factory ): void
{
    // Feature tests go through the Http facade so consumers can call
    // Http::fake() without knowing how the clients are constructed.
    app()->instance( HttpFactory::class, $factory );
}

function makeBusinessesClient(): BusinessesClient
{
    return new BusinessesClient(
        tokenProvider: new ContractTokenProvider(),
        http: app( HttpFactory::class ),
        timeout: 5,
        maxAttempts: 2,
        retrySleepMs: 0,
    );
}

function makeReviewsClient(): ReviewsClient
{
    return new ReviewsClient(
        tokenProvider: new ContractTokenProvider(),
        http: app( HttpFactory::class ),
        timeout: 5,
        maxAttempts: 2,
        retrySleepMs: 0,
    );
}

it( 'exercises the full business lifecycle end-to-end against Http::fake fixtures', function (): void {
    // Order matters: `.../businesses?*` must precede `.../businesses` so the
    // GET list request matches the paginated stub before falling through to
    // the POST create stub. Do not alphabetize this array.
    Http::fake( [
        BP_BASE_URL . '/businesses?*'                         => Http::response( loadFixture( 'businesses/business-list-page-1.json' ), 200 ),
        BP_BASE_URL . '/businesses'                           => Http::response( loadFixture( 'businesses/business-created.json' ), 200 ),
        BP_BASE_URL . '/businesses/biz-abc-123'               => Http::sequence()
            ->push( loadFixture( 'businesses/business.json' ), 200 )
            ->push( loadFixture( 'businesses/business-patched.json' ), 200 )
            ->push( '', 204 ),
    ] );
    bindContractHttp( Http::getFacadeRoot() );

    $client = makeBusinessesClient();

    // 1. List — pagination metadata comes back on the DTO.
    $page1 = $client->listBusinesses( pageSize: 25 );

    expect( $page1 )->toBeInstanceOf( BusinessList::class );
    expect( $page1->businesses )->toHaveCount( 2 );
    expect( $page1->businesses[0]->id )->toBe( 'biz-abc-123' );
    expect( $page1->businesses[0]->address )->toMatchArray( [ 'city' => 'Springfield' ] );
    expect( $page1->nextPageToken )->toBe( 'page-token-2' );
    expect( $page1->totalSize )->toBe( 3 );

    // 2. Get — every typed field on the DTO is populated from the fixture.
    $one = $client->getBusiness( 'biz-abc-123' );

    expect( $one )->toBeInstanceOf( Business::class );
    expect( $one->id )->toBe( 'biz-abc-123' );
    expect( $one->storeId )->toBe( 'store-4200' );
    expect( $one->businessName )->toBe( 'ArtisanPack Bakery' );
    expect( $one->website )->toBe( 'https://artisanpack-bakery.test' );
    expect( $one->phone )->toBe( '+1-555-0142' );
    expect( $one->description )->toBe( 'Wood-fired sourdough and pastries.' );
    expect( $one->address )->toMatchArray( [ 'zipOrPostalCode' => '62704' ] );
    expect( $one->location )->toMatchArray( [ 'latitude' => 39.7817 ] );
    expect( $one->categories )->toMatchArray( [ 'primary' => 'Bakery' ] );
    expect( $one->businessHours )->toHaveKey( 'monday' );
    expect( $one->specialHours )->toBeArray()->toHaveCount( 1 );
    expect( $one->verification )->toMatchArray( [ 'status' => 'verified' ] );
    expect( $one->photos )->toBeArray()->toHaveCount( 1 );
    expect( $one->createdAt )->toBe( '2026-01-10T09:00:00Z' );
    expect( $one->updatedAt )->toBe( '2026-02-01T11:30:00Z' );
    expect( $one->raw )->toHaveKey( 'photos' ); // raw preserves the full payload for downstream consumers.

    // 3. Create — POST payload goes as JSON, response hydrates the DTO.
    $created = $client->createBusiness( [
        'storeId'      => 'store-9000',
        'businessName' => 'New ArtisanPack Cafe',
        'address'      => [ 'addressLine1' => '9 Oak Boulevard', 'city' => 'Capital City' ],
    ] );

    expect( $created->id )->toBe( 'biz-new-999' );
    expect( $created->verification )->toMatchArray( [ 'status' => 'pending' ] );

    // 4. Patch — only supplied fields go on the wire, response hydrates the DTO.
    $patched = $client->patchBusiness( 'biz-abc-123', [ 'phone' => '+1-555-9999' ] );

    expect( $patched )->toBeInstanceOf( Business::class );
    expect( $patched?->phone )->toBe( '+1-555-9999' );
    expect( $patched?->updatedAt )->toBe( '2026-03-15T08:20:00Z' );

    // 5. Delete — 204 No Content resolves to void with no throw.
    $client->deleteBusiness( 'biz-abc-123' );

    // Contract assertions on the recorded traffic.
    Http::assertSentCount( 5 );

    Http::assertSent( function ( Request $request ): bool {
        return 'Bearer contract-token' === ( $request->header( 'Authorization' )[0] ?? '' );
    } );

    Http::assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), BP_BASE_URL . '/businesses' )
            && str_contains( $request->url(), 'pageSize=25' );
    } );

    Http::assertSent( function ( Request $request ): bool {
        return 'POST' === $request->method()
            && BP_BASE_URL . '/businesses' === $request->url()
            && 'store-9000' === ( $request->data()['storeId'] ?? null );
    } );

    Http::assertSent( function ( Request $request ): bool {
        return 'PATCH' === $request->method()
            && BP_BASE_URL . '/businesses/biz-abc-123' === $request->url()
            && '+1-555-9999' === ( $request->data()['phone'] ?? null );
    } );

    Http::assertSent( function ( Request $request ): bool {
        return 'DELETE' === $request->method()
            && BP_BASE_URL . '/businesses/biz-abc-123' === $request->url();
    } );
} );

it( 'hydrates a review list end-to-end from a realistic fixture', function (): void {
    Http::fake( [
        BP_BASE_URL . '/businesses/biz-abc-123/reviews*' => Http::response( loadFixture( 'reviews/review-list.json' ), 200 ),
    ] );
    bindContractHttp( Http::getFacadeRoot() );

    $list = makeReviewsClient()->listReviews( 'biz-abc-123', pageSize: 25 );

    expect( $list )->toBeInstanceOf( ReviewList::class );
    expect( $list->reviews )->toHaveCount( 3 );
    expect( $list->reviews[0]->id )->toBe( 'review-1' );
    expect( $list->reviews[0]->rating )->toBe( 5 );
    expect( $list->reviews[0]->comment )->toBe( 'Best sourdough in town.' );
    expect( $list->reviews[0]->source )->toBe( 'Yelp' );
    expect( $list->reviews[0]->reviewer )->toMatchArray( [ 'displayName' => 'Alex P.' ] );
    expect( $list->reviews[2]->comment )->toBeNull(); // text-less review still hydrates.
    expect( $list->nextPageToken )->toBe( 'reviews-page-2' );
    expect( $list->totalSize )->toBe( 12 );
    expect( $list->averageRating )->toBe( 4.2 );

    Http::assertSent( function ( Request $request ): bool {
        return 'GET' === $request->method()
            && str_starts_with( $request->url(), BP_BASE_URL . '/businesses/biz-abc-123/reviews' )
            && str_contains( $request->url(), 'pageSize=25' )
            && 'Bearer contract-token' === ( $request->header( 'Authorization' )[0] ?? '' );
    } );
} );

it( 'walks a paginated business list using nextPageToken', function (): void {
    Http::fakeSequence()
        ->push( loadFixture( 'businesses/business-list-page-1.json' ), 200 )
        ->push( loadFixture( 'businesses/business-list-page-2.json' ), 200 );
    bindContractHttp( Http::getFacadeRoot() );

    $client = makeBusinessesClient();

    $page1 = $client->listBusinesses( pageSize: 2 );
    expect( $page1->nextPageToken )->toBe( 'page-token-2' );

    $page2 = $client->listBusinesses( pageSize: 2, pageToken: $page1->nextPageToken );

    expect( $page2->businesses )->toHaveCount( 1 );
    expect( $page2->businesses[0]->id )->toBe( 'biz-ghi-789' );
    expect( $page2->nextPageToken )->toBeNull();
    expect( $page2->totalSize )->toBe( 3 );

    Http::assertSent( function ( Request $request ): bool {
        return str_contains( $request->url(), 'pageToken=page-token-2' );
    } );
} );

it( 'maps a 404 error body to an ApiException that preserves the raw response body', function (): void {
    $errorBody = loadFixture( 'businesses/error-not-found.json' );

    Http::fake( [
        BP_BASE_URL . '/businesses/biz-missing' => Http::response( $errorBody, 404 ),
    ] );
    bindContractHttp( Http::getFacadeRoot() );

    $client = makeBusinessesClient();

    try {
        $client->getBusiness( 'biz-missing' );
        $this->fail( 'ApiException was expected but no exception was thrown.' );
    } catch ( ApiException $exception ) {
        expect( $exception->statusCode() )->toBe( 404 );
        expect( $exception->responseBody() )->not->toBeNull();
        expect( $exception->responseBody() )->toContain( 'NOT_FOUND' );
    }
} );

it( 'retries a transient 503 for GETs and succeeds on the second attempt', function (): void {
    Http::fakeSequence()
        ->push( '', 503 )
        ->push( loadFixture( 'businesses/business.json' ), 200 );
    bindContractHttp( Http::getFacadeRoot() );

    $business = makeBusinessesClient()->getBusiness( 'biz-abc-123' );

    expect( $business->id )->toBe( 'biz-abc-123' );
    Http::assertSentCount( 2 );
} );
