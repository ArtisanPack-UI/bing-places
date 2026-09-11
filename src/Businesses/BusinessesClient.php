<?php

/**
 * Bing Places Businesses API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Businesses;

use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\Business;
use ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects\BusinessList;
use ArtisanPackUI\BingPlaces\Http\BaseClient;
use InvalidArgumentException;

/**
 * Typed client for the Bing Places for Business management API.
 *
 * Exposes the endpoints needed to enumerate, fetch, create, update, and
 * delete business listings on a partner-enrolled Bing Places account.
 * Every request rides the shared {@see BaseClient} plumbing: a Bearer
 * token fetched from the injected `TokenProvider`, JSON accept headers,
 * retry on `429`/`5xx`, and mapping of errors to
 * {@see \ArtisanPackUI\BingPlaces\Exceptions\ApiException}.
 *
 * The Bing Places management API is currently restricted to accounts
 * enrolled in the agency/partner program, and its published surface is
 * limited to the bulk-upload schema. The endpoints below model the shape
 * we expect the granted API to expose; when access is approved the base
 * URL and any endpoint drift can be addressed by changing this class
 * alone, leaving the DTO layer and consumer-facing shape untouched.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class BusinessesClient extends BaseClient
{
    /**
     * Upper bound for the `pageSize` parameter on
     * {@see listBusinesses()}. The API is expected to reject page sizes
     * larger than this; the client validates the argument up front so
     * misuse fails loudly during development rather than after a network
     * round-trip.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * List the businesses attached to the caller's account.
     *
     * Returns a single page. When {@see BusinessList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token
     * to fetch the next page.
     *
     * @since 1.0.0
     *
     * @param  int|null  $pageSize  Requested page size
     *                              (1-{@see self::MAX_PAGE_SIZE}). Null
     *                              omits the parameter and lets the API
     *                              pick its default.
     * @param  string|null  $pageToken  Token returned by a prior call's
     *                                  `nextPageToken`, or null for the
     *                                  first page.
     * @param  string|null  $filter  Optional API-side filter expression,
     *                               or null to omit.
     *
     * @throws InvalidArgumentException When `pageSize` is outside the
     *                                  1-{@see self::MAX_PAGE_SIZE}
     *                                  range.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function listBusinesses(
        ?int $pageSize = null,
        ?string $pageToken = null,
        ?string $filter = null,
    ): BusinessList {
        $query = [];

        if ( null !== $pageSize ) {
            if ( $pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE ) {
                throw new InvalidArgumentException( sprintf(
                    'pageSize must be between 1 and %d; %d given.',
                    self::MAX_PAGE_SIZE,
                    $pageSize,
                ) );
            }

            $query['pageSize'] = $pageSize;
        }

        if ( null !== $pageToken && '' !== $pageToken ) {
            $query['pageToken'] = $pageToken;
        }

        if ( null !== $filter && '' !== $filter ) {
            $query['filter'] = $filter;
        }

        $body = $this->request( 'GET', 'businesses', $query );

        return BusinessList::fromArray( $body );
    }

    /**
     * Fetch a single business by its Bing-assigned identifier.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Bing-assigned business identifier.
     *
     * @throws InvalidArgumentException When `id` is empty.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function getBusiness( string $id ): Business
    {
        if ( '' === $id ) {
            throw new InvalidArgumentException( 'id is required and must be a non-empty business identifier.' );
        }

        $body = $this->request( 'GET', 'businesses/' . rawurlencode( $id ) );

        return Business::fromArray( $body );
    }

    /**
     * Create a new business listing.
     *
     * The API assigns and returns an `id` for the created business; the
     * merchant-supplied `storeId` is preserved as an external
     * identifier. The full payload the API echoes back is used to
     * hydrate the returned DTO so callers can immediately read every
     * field the API populated at creation time (verification status,
     * timestamps, ...).
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $business  Business payload. Must
     *                                          include at minimum the
     *                                          fields the API requires
     *                                          for a listing (business
     *                                          name, primary category,
     *                                          address).
     *
     * @throws InvalidArgumentException When `business` is empty.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function createBusiness( array $business ): Business
    {
        if ( [] === $business ) {
            throw new InvalidArgumentException( 'business payload is required and must contain at least one field.' );
        }

        $body = $this->request( 'POST', 'businesses', [], $business );

        return Business::fromArray( $body );
    }

    /**
     * Patch the writable fields of a single business.
     *
     * Only the fields present on `$business` are sent to the API; the
     * client does not attempt to compute a diff or otherwise second-guess
     * the caller. `validateOnly` performs a dry run when true: the API
     * validates the request without persisting any change, and returns
     * an empty body on success — in that case this method returns null.
     * Every non-dry-run success returns the updated {@see Business}.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Bing-assigned business identifier.
     * @param  array<string, mixed>  $business  Partial Business payload
     *                                          containing only the
     *                                          fields being updated.
     * @param  bool  $validateOnly  When true, ask the API to validate
     *                              the request without persisting; a
     *                              successful dry run responds with an
     *                              empty body and this method returns
     *                              null.
     *
     * @throws InvalidArgumentException When `id` is empty or `business`
     *                                  is empty.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function patchBusiness(
        string $id,
        array $business,
        bool $validateOnly = false,
    ): ?Business {
        if ( '' === $id ) {
            throw new InvalidArgumentException( 'id is required and must be a non-empty business identifier.' );
        }

        if ( [] === $business ) {
            throw new InvalidArgumentException( 'business payload is required and must contain at least one field to update.' );
        }

        $query = [];

        if ( true === $validateOnly ) {
            $query['validateOnly'] = 'true';
        }

        $body = $this->request( 'PATCH', 'businesses/' . rawurlencode( $id ), $query, $business );

        if ( true === $validateOnly && [] === $body ) {
            return null;
        }

        return Business::fromArray( $body );
    }

    /**
     * Delete a business listing.
     *
     * The API is expected to respond with `204 No Content` on success;
     * the base client already normalises an empty body to an empty
     * array, so this method simply returns void on success and throws
     * on any non-2xx response.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Bing-assigned business identifier.
     *
     * @throws InvalidArgumentException When `id` is empty.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function deleteBusiness( string $id ): void
    {
        if ( '' === $id ) {
            throw new InvalidArgumentException( 'id is required and must be a non-empty business identifier.' );
        }

        $this->request( 'DELETE', 'businesses/' . rawurlencode( $id ) );
    }

    /**
     * Base URL for the Bing Places for Business management API surface.
     *
     * Contract-first placeholder pending partner-program approval; when
     * the real endpoint is granted, updating this method alone is
     * sufficient to point the client at the production host.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://bingplaces.microsoft.com/api/v2';
    }
}
