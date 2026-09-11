<?php

/**
 * Paginated business list DTO for the Bing Places management API.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Businesses\DataTransferObjects;

/**
 * Immutable, single-page result returned by
 * {@see \ArtisanPackUI\BingPlaces\Businesses\BusinessesClient::listBusinesses()}.
 *
 * Wraps the array of {@see Business} DTOs together with the pagination
 * token the API returns when more pages are available and the total
 * account-wide count. `nextPageToken` is null on the final page,
 * matching the contract of only emitting the field when there is more
 * to fetch. `totalSize` is null when the API omits it.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
final class BusinessList
{
    /**
     * Construct a BusinessList.
     *
     * @since 1.0.0
     *
     * @param  list<Business>  $businesses  Businesses on this page.
     * @param  string|null  $nextPageToken  Token for fetching the next
     *                                      page, or null when this is
     *                                      the final page.
     * @param  int|null  $totalSize  Total account-wide business count, or
     *                               null when the API did not include it.
     */
    public function __construct(
        public readonly array $businesses,
        public readonly ?string $nextPageToken = null,
        public readonly ?int $totalSize = null,
    ) {
    }

    /**
     * Build a BusinessList from a decoded `businesses.list` response.
     *
     * A response with no `businesses` key (or a non-array value there) is
     * treated as an empty page, matching how the API is expected to omit
     * the key when the account has no listings. Any entry that is not
     * itself a non-empty associative array is skipped so a malformed row
     * (empty `{}`, list-shaped payload, scalar, ...) does not silently
     * inflate the page count with blank {@see Business} rows.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rawBusinesses = $data['businesses'] ?? [];

        $businesses = [];

        if ( is_array( $rawBusinesses ) ) {
            foreach ( $rawBusinesses as $rawBusiness ) {
                if ( is_array( $rawBusiness ) && [] !== $rawBusiness && ! array_is_list( $rawBusiness ) ) {
                    $businesses[] = Business::fromArray( $rawBusiness );
                }
            }
        }

        $nextPageToken = null;

        if ( isset( $data['nextPageToken'] ) && '' !== $data['nextPageToken'] ) {
            $nextPageToken = (string) $data['nextPageToken'];
        }

        $totalSize = null;

        if ( isset( $data['totalSize'] ) && is_numeric( $data['totalSize'] ) ) {
            $totalSize = (int) $data['totalSize'];
        }

        return new self( $businesses, $nextPageToken, $totalSize );
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
