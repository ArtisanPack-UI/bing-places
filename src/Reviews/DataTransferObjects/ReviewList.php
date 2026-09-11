<?php

/**
 * Paginated review list DTO for the Bing Places management API.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Reviews\DataTransferObjects;

/**
 * Immutable, single-page result returned by
 * {@see \ArtisanPackUI\BingPlaces\Reviews\ReviewsClient::listReviews()}.
 *
 * Wraps the array of {@see Review} DTOs together with the pagination
 * token the API returns when more pages are available, the total
 * account-wide count, and the aggregate average rating. Bing's reviews
 * surface typically exposes both a paginated review feed and the
 * account-wide average rating on the same list response; both are
 * preserved here so a consumer does not need a second call to render a
 * rating summary alongside individual reviews.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
final class ReviewList
{
    /**
     * Construct a ReviewList.
     *
     * @since 1.0.0
     *
     * @param  list<Review>  $reviews  Reviews on this page.
     * @param  string|null  $nextPageToken  Token for fetching the next
     *                                      page, or null when this is
     *                                      the final page.
     * @param  int|null  $totalSize  Total review count for the business,
     *                               or null when the API did not include
     *                               it.
     * @param  float|null  $averageRating  Aggregate average rating on a
     *                                     1-5 scale, or null when the
     *                                     API did not include it.
     */
    public function __construct(
        public readonly array $reviews,
        public readonly ?string $nextPageToken = null,
        public readonly ?int $totalSize = null,
        public readonly ?float $averageRating = null,
    ) {
    }

    /**
     * Build a ReviewList from a decoded `reviews.list` response.
     *
     * A response with no `reviews` key (or a non-array value there) is
     * treated as an empty page. Any entry that is not itself a non-empty
     * associative array is skipped so a malformed row does not silently
     * inflate the page count.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rawReviews = $data['reviews'] ?? [];

        $reviews = [];

        if ( is_array( $rawReviews ) ) {
            foreach ( $rawReviews as $rawReview ) {
                if ( is_array( $rawReview ) && [] !== $rawReview && ! array_is_list( $rawReview ) ) {
                    $reviews[] = Review::fromArray( $rawReview );
                }
            }
        }

        $nextPageToken = null;

        if (
            isset( $data['nextPageToken'] )
            && is_string( $data['nextPageToken'] )
            && '' !== $data['nextPageToken']
        ) {
            $nextPageToken = $data['nextPageToken'];
        }

        $totalSize = null;

        if ( isset( $data['totalSize'] ) && is_numeric( $data['totalSize'] ) ) {
            $totalSize = (int) $data['totalSize'];
        }

        $averageRating = null;

        if ( isset( $data['averageRating'] ) && is_numeric( $data['averageRating'] ) ) {
            $averageRating = (float) $data['averageRating'];
        }

        return new self( $reviews, $nextPageToken, $totalSize, $averageRating );
    }

    /**
     * Whether this page carries a token that would fetch a further page.
     *
     * @since 1.0.0
     */
    public function hasMore(): bool
    {
        return null !== $this->nextPageToken;
    }
}
