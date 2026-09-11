<?php

/**
 * Bing Places Reviews API client.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Reviews;

use ArtisanPackUI\BingPlaces\Http\BaseClient;
use ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects\ReviewList;
use InvalidArgumentException;

/**
 * Typed client for the Bing Places for Business reviews API.
 *
 * Bing aggregates reviews from partner sources rather than accepting
 * first-party submissions, so this client is currently read-only. Once
 * partner-program access is granted the endpoint drops in behind this
 * shape without any change visible to consumers.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
class ReviewsClient extends BaseClient
{
    /**
     * Upper bound for the `pageSize` parameter on
     * {@see listReviews()}.
     *
     * @since 1.0.0
     *
     * @var int
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * List the reviews attached to a business listing.
     *
     * Returns a single page. When {@see ReviewList::$nextPageToken} is
     * non-null the caller should re-invoke this method with that token
     * to fetch the next page. The aggregate {@see ReviewList::$averageRating}
     * and {@see ReviewList::$totalSize} the API returns alongside the
     * page are exposed on the DTO so a consumer does not need a second
     * call to render a rating summary.
     *
     * @since 1.0.0
     *
     * @param  string  $businessId  Bing-assigned business identifier.
     * @param  int|null  $pageSize  Requested page size
     *                              (1-{@see self::MAX_PAGE_SIZE}). Null
     *                              omits the parameter and lets the API
     *                              pick its default.
     * @param  string|null  $pageToken  Token returned by a prior call's
     *                                  `nextPageToken`, or null for the
     *                                  first page.
     *
     * @throws InvalidArgumentException When `businessId` is empty or
     *                                  `pageSize` is outside the
     *                                  1-{@see self::MAX_PAGE_SIZE}
     *                                  range.
     * @throws \ArtisanPackUI\BingPlaces\Exceptions\ApiException When the
     *         API returns an error or the request fails at the transport
     *         level after all retries.
     */
    public function listReviews(
        string $businessId,
        ?int $pageSize = null,
        ?string $pageToken = null,
    ): ReviewList {
        if ( '' === $businessId ) {
            throw new InvalidArgumentException( 'businessId is required and must be a non-empty business identifier.' );
        }

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

        $body = $this->request(
            'GET',
            'businesses/' . rawurlencode( $businessId ) . '/reviews',
            $query,
        );

        return ReviewList::fromArray( $body );
    }

    /**
     * Base URL for the Bing Places for Business management API surface.
     *
     * @since 1.0.0
     */
    protected function baseUrl(): string
    {
        return 'https://bingplaces.microsoft.com/api/v2';
    }
}
