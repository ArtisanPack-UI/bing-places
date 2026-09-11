<?php

/**
 * Review DTO for the Bing Places management API.
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
 * Immutable representation of a single Bing Places review.
 *
 * Bing surfaces reviews aggregated from partner sources rather than
 * accepting first-party review submissions, so the DTO focuses on the
 * fields a downstream Local SEO surface needs to display the review:
 * rating on a 1-5 scale, review body, reviewer identifier, source
 * label, and timestamps. The reviewer sub-resource is kept as a plain
 * associative array so the DTO does not silently drop identity fields
 * whose exact wire shape we cannot yet pin down. The full payload is
 * preserved on {@see self::$raw}.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
final class Review
{
    /**
     * Construct a Review DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Bing-assigned review identifier.
     * @param  int|null  $rating  Star rating on a 1-5 scale, or null when
     *                            absent.
     * @param  string|null  $comment  Review body, or null when the source
     *                                supplied a rating without text.
     * @param  string|null  $source  Human-readable source label
     *                               (`"Yelp"`, `"Tripadvisor"`, ...), or
     *                               null when the API did not attribute
     *                               the review to a specific origin.
     * @param  array<string, mixed>|null  $reviewer  Reviewer payload
     *                                               (display name,
     *                                               avatar URL, ...), or
     *                                               null when absent.
     * @param  string|null  $createdAt  ISO-8601 review creation
     *                                  timestamp, or null when absent.
     * @param  string|null  $updatedAt  ISO-8601 review update timestamp,
     *                                  or null when absent.
     * @param  array<string, mixed>  $raw  The raw payload for this
     *                                     review exactly as returned by
     *                                     the API.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?int $rating = null,
        public readonly ?string $comment = null,
        public readonly ?string $source = null,
        public readonly ?array $reviewer = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a Review from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than
     * throwing: an emitted review is guaranteed by the API only to carry
     * an identifier, and even the rating may be omitted for
     * text-only feedback. Unknown fields survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        $rating = self::ratingOrNull( $data );

        return new self(
            id       : isset( $data['id'] ) && is_scalar( $data['id'] ) ? (string) $data['id'] : '',
            rating   : $rating,
            comment  : self::stringOrNull( $data, 'comment' ),
            source   : self::stringOrNull( $data, 'source' ),
            reviewer : self::arrayOrNull( $data, 'reviewer' ),
            createdAt: self::stringOrNull( $data, 'createdAt' ),
            updatedAt: self::stringOrNull( $data, 'updatedAt' ),
            raw      : $data,
        );
    }

    /**
     * Coerce a `rating` payload into an integer on the documented 1-5
     * scale, or null when the value is missing, non-numeric, fractional,
     * or out of range.
     *
     * Fractional inputs (e.g. `4.9`, `"4.9"`) are rejected rather than
     * silently truncated to `4`, because losing precision on a rating
     * misleads any consumer that renders it. An aggregate float belongs
     * on {@see ReviewList::$averageRating}, not on an individual review.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    private static function ratingOrNull( array $data ): ?int
    {
        if ( ! isset( $data['rating'] ) ) {
            return null;
        }

        $value = $data['rating'];

        if ( is_int( $value ) ) {
            return ( $value >= 1 && $value <= 5 ) ? $value : null;
        }

        if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
            $parsed = (int) $value;

            return ( $parsed >= 1 && $parsed <= 5 ) ? $parsed : null;
        }

        return null;
    }

    /**
     * Return the value at `$key` as a string, or null when missing or
     * not scalar.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    private static function stringOrNull( array $data, string $key ): ?string
    {
        if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
            return null;
        }

        return (string) $data[ $key ];
    }

    /**
     * Return the value at `$key` when it is an array, otherwise null.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     *
     * @return array<string, mixed>|null
     */
    private static function arrayOrNull( array $data, string $key ): ?array
    {
        if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
            return null;
        }

        return $data[ $key ];
    }
}
