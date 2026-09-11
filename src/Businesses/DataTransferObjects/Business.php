<?php

/**
 * Business DTO for the Bing Places for Business management API.
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
 * Immutable representation of a single Bing Places business listing.
 *
 * Models the shape we expect the Bing Places for Business management API
 * to return once partner-program access is granted. Bing's management
 * surface today is documented publicly only through the bulk-upload
 * schema, so the field set here mirrors the identifiers, contact detail,
 * address, and metadata that schema settles on. Scalars are typed for
 * ergonomics; the nested, still-in-flux sub-resources the API attaches
 * to a listing (address, categories, hours, verification, photos) stay
 * as plain associative arrays so the DTO does not silently drop or
 * reshape fields whose exact wire form we cannot yet pin down.
 *
 * The full listing payload — every field the API returned, whether or
 * not it also appears as a typed property — is preserved on
 * {@see self::$raw} so consumers can reach fields the typed surface
 * does not yet expose without a round-trip.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
final class Business
{
    /**
     * Construct a Business DTO.
     *
     * @since 1.0.0
     *
     * @param  string  $id  Bing-assigned business identifier
     *                      (`yourBusinessId` in the bulk-upload schema).
     * @param  string  $storeId  Merchant-supplied external store code, or
     *                           the empty string when the listing was not
     *                           created with one.
     * @param  string  $businessName  Human-readable business name.
     * @param  string|null  $website  Public-facing website URL, or null
     *                                when absent.
     * @param  string|null  $phone  E.164 or locally-formatted phone
     *                              number, or null when absent.
     * @param  string|null  $description  Business description, or null
     *                                    when absent.
     * @param  array<string, mixed>|null  $address  Address payload
     *                                              (`addressLine1`, `city`,
     *                                              `stateOrProvince`,
     *                                              `zipOrPostalCode`,
     *                                              `countryOrRegion`), or
     *                                              null when absent.
     * @param  array<string, mixed>|null  $location  Geo location payload
     *                                               (`latitude`,
     *                                               `longitude`), or null
     *                                               when absent.
     * @param  array<string, mixed>|null  $categories  Categories payload
     *                                                 (`primary`,
     *                                                 `additional`), or
     *                                                 null when absent.
     * @param  array<string, mixed>|null  $businessHours  Weekly hours
     *                                                    payload, or null
     *                                                    when absent.
     * @param  array<string, mixed>|null  $specialHours  Holiday/special
     *                                                   hours payload, or
     *                                                   null when absent.
     * @param  array<string, mixed>|null  $verification  Verification
     *                                                   status payload
     *                                                   (`status`,
     *                                                   `method`,
     *                                                   `verifiedAt`), or
     *                                                   null when absent.
     * @param  array<string, mixed>|null  $photos  Photos payload, or null
     *                                             when absent.
     * @param  string|null  $createdAt  ISO-8601 creation timestamp, or
     *                                  null when absent.
     * @param  string|null  $updatedAt  ISO-8601 last-updated timestamp, or
     *                                  null when absent.
     * @param  array<string, mixed>  $raw  The raw payload for this
     *                                     business exactly as returned by
     *                                     the API.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $storeId,
        public readonly string $businessName,
        public readonly ?string $website = null,
        public readonly ?string $phone = null,
        public readonly ?string $description = null,
        public readonly ?array $address = null,
        public readonly ?array $location = null,
        public readonly ?array $categories = null,
        public readonly ?array $businessHours = null,
        public readonly ?array $specialHours = null,
        public readonly ?array $verification = null,
        public readonly ?array $photos = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly array $raw = [],
    ) {
    }

    /**
     * Build a Business from an API payload.
     *
     * Missing fields are coerced to sensible defaults rather than
     * throwing: only `id` and `businessName` are reasonable minimums for
     * an emitted listing, and even `businessName` may be blank for a
     * draft that has not yet been submitted for verification. Unknown
     * fields survive on {@see self::$raw}.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray( array $data ): self
    {
        return new self(
            id           : self::stringOrEmpty( $data, 'id' ),
            storeId      : self::stringOrEmpty( $data, 'storeId' ),
            businessName : self::stringOrEmpty( $data, 'businessName' ),
            website      : self::stringOrNull( $data, 'website' ),
            phone        : self::stringOrNull( $data, 'phone' ),
            description  : self::stringOrNull( $data, 'description' ),
            address      : self::arrayOrNull( $data, 'address' ),
            location     : self::arrayOrNull( $data, 'location' ),
            categories   : self::arrayOrNull( $data, 'categories' ),
            businessHours: self::arrayOrNull( $data, 'businessHours' ),
            specialHours : self::arrayOrNull( $data, 'specialHours' ),
            verification : self::arrayOrNull( $data, 'verification' ),
            photos       : self::arrayOrNull( $data, 'photos' ),
            createdAt    : self::stringOrNull( $data, 'createdAt' ),
            updatedAt    : self::stringOrNull( $data, 'updatedAt' ),
            raw          : $data,
        );
    }

    /**
     * Return the value at `$key` as a string, or the empty string when it
     * is missing or not scalar.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data
     */
    private static function stringOrEmpty( array $data, string $key ): string
    {
        if ( ! isset( $data[ $key ] ) || ! is_scalar( $data[ $key ] ) ) {
            return '';
        }

        return (string) $data[ $key ];
    }

    /**
     * Return the value at `$key` as a string, or null when it is missing
     * or not scalar.
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
     * Guards against malformed payloads (scalar where an object is
     * expected) so a rogue value on one field does not blow up the whole
     * DTO.
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
