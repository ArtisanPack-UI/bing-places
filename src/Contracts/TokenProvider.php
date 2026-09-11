<?php

/**
 * TokenProvider contract.
 *
 * Contract implemented by any service that can supply a valid Microsoft OAuth
 * access token for the Bing Places for Business API client. The client itself
 * performs no OAuth — it delegates token acquisition to whichever provider the
 * host application binds (in Keystone: the manager exposed by
 * `artisanpack-ui/microsoft-oauth`; in tests: a stub; elsewhere: whatever the
 * host provides).
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\BingPlaces\Contracts;

/**
 * TokenProvider contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage BingPlaces
 *
 * @since      1.0.0
 */
interface TokenProvider
{
    /**
     * Return a valid Microsoft OAuth access token to use as the Bearer
     * credential on Bing Places for Business API requests.
     *
     * Implementations are responsible for refreshing expired tokens before
     * returning; the client will use the returned string verbatim.
     *
     * @since 1.0.0
     *
     * @return string A non-empty OAuth access token.
     */
    public function accessToken(): string;
}
