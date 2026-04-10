<?php
/**
 * MC-EMS Premium – Gestione Licenza
 *
 * Gestisce la verifica e il caching della licenza premium.
 * Endpoint: POST https://mambacoding.com/wp-json/mcems/v1/license/verify
 *
 * @package MC-EMS-Premium
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MCEMS_License {

    /** Option name for storing the license key */
    const OPTION_KEY   = 'mcems_premium_license_key';

    /** Option name for storing the cached license status */
    const OPTION_CACHE = 'mcems_premium_license_cache';

    /** Cache TTL in seconds (24 hours) */
    const CACHE_TTL    = 86400;

    /** Grace period in seconds added on top of TTL (handles cron delay) */
    const CACHE_GRACE  = 3600;

    /** Remote API endpoint for license verification */
    const API_URL      = 'https://mambacoding.com/wp-json/mcems/v1/license/verify';

    /** WP-Cron hook name for background check */
    const CRON_HOOK    = 'mcems_premium_license_check';

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Register hooks.
     * Called early – before plugins_loaded priority 20 – so that cron and
     * deactivation hooks are in place before premium features boot.
     */
    public static function init(): void {
        add_action( 'plugins_loaded', [ __CLASS__, 'maybe_schedule_cron' ], 5 );
        add_action( self::CRON_HOOK,  [ __CLASS__, 'run_background_check' ] );
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Returns true only when a cached, fresh, and valid license is found.
     *
     * "Fresh" = checked within the last CACHE_TTL + CACHE_GRACE seconds.
     * The cache is populated either by save_and_verify() (synchronous, user-
     * triggered) or by run_background_check() (WP-Cron, every 24 h).
     */
    public static function is_valid(): bool {
        $cache = get_option( self::OPTION_CACHE, [] );

        if ( ! is_array( $cache ) || empty( $cache['checked_at'] ) ) {
            return false;
        }

        // Reject stale cache (older than TTL + grace period)
        $age = time() - (int) $cache['checked_at'];
        if ( $age > ( self::CACHE_TTL + self::CACHE_GRACE ) ) {
            return false;
        }

        return isset( $cache['valid'] ) && true === $cache['valid'];
    }

    /**
     * Returns the cached status array for display purposes.
     * If the cache is stale (> TTL + grace), the status is overridden to
     * 'server_error' so that the UI can show the correct warning.
     *
     * @return array {
     *   bool   $valid      Whether the license is currently valid.
     *   string $status     One of: valid, expired, inactive, invalid, no_key, server_error.
     *   string $message    Human-readable message from the API (may be empty).
     *   int    $checked_at Unix timestamp of the last verification.
     * }
     */
    public static function get_cached_status(): array {
        $cache = get_option( self::OPTION_CACHE, [] );

        if ( ! is_array( $cache ) || empty( $cache['checked_at'] ) ) {
            return [
                'valid'      => false,
                'status'     => 'no_key',
                'message'    => '',
                'checked_at' => 0,
            ];
        }

        // If cache is stale, treat as server_error (cannot confirm validity)
        $age = time() - (int) $cache['checked_at'];
        if ( $age > ( self::CACHE_TTL + self::CACHE_GRACE ) ) {
            return array_merge( $cache, [
                'valid'  => false,
                'status' => 'server_error',
            ] );
        }

        return $cache;
    }

    /**
     * Save a new license key, clear the existing cache, and run an immediate
     * synchronous verification against the remote API.
     *
     * @param string $key License key entered by the administrator.
     * @return array Resulting status array (same structure as get_cached_status()).
     */
    public static function save_and_verify( string $key ): array {
        $key = sanitize_text_field( $key );
        update_option( self::OPTION_KEY, $key, false );
        return self::verify_and_cache( $key );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Call the remote API, build the status array, persist it to the cache
     * option and return it.
     *
     * Any previously cached value is discarded before the fresh result is
     * written so callers do not need to call delete_option() themselves.
     *
     * @param string $key License key to verify.
     * @return array Status array.
     */
    public static function verify_and_cache( string $key ): array {
        // Discard stale cache before writing the fresh result.
        delete_option( self::OPTION_CACHE );
        if ( '' === $key ) {
            $result = [
                'valid'      => false,
                'status'     => 'no_key',
                'message'    => '',
                'checked_at' => time(),
            ];
            update_option( self::OPTION_CACHE, $result, false );
            return $result;
        }

        $result = self::remote_verify( $key );
        update_option( self::OPTION_CACHE, $result, false );
        return $result;
    }

    /**
     * Perform the HTTP POST to the license API and return a normalised status
     * array.  On any network or server error the status is set to 'server_error'
     * so that the caller can show the appropriate warning without blocking the
     * site.
     *
     * @param string $key License key.
     * @return array Status array.
     */
    private static function remote_verify( string $key ): array {
        $response = wp_remote_post( self::API_URL, [
            'timeout'     => 15,
            'redirection' => 3,
            'httpversion' => '1.1',
            'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'        => wp_json_encode( [
                'license_key' => $key,
                'site_url'    => home_url(),
            ] ),
        ] );

        // Network / transport error
        if ( is_wp_error( $response ) ) {
            return [
                'valid'      => false,
                'status'     => 'server_error',
                'message'    => $response->get_error_message(),
                'checked_at' => time(),
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        // Unexpected HTTP status or malformed response body
        if ( 200 !== $http_code || ! is_array( $data ) ) {
            return [
                'valid'      => false,
                'status'     => 'server_error',
                'message'    => '',
                'checked_at' => time(),
            ];
        }

        $api_status = isset( $data['status'] ) ? (string) $data['status'] : 'invalid';

        return [
            'valid'      => ( 'valid' === $api_status ),
            'status'     => $api_status,
            'message'    => isset( $data['message'] ) ? (string) $data['message'] : '',
            'checked_at' => time(),
        ];
    }

    // -------------------------------------------------------------------------
    // WP-Cron
    // -------------------------------------------------------------------------

    /**
     * Schedule the daily background check if it is not already in the queue.
     * Called at plugins_loaded priority 5.
     */
    public static function maybe_schedule_cron(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + self::CACHE_TTL, 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Background cron callback: perform a fresh API verification and update
     * the cache.  This keeps the license status up to date without requiring
     * any user interaction.
     */
    public static function run_background_check(): void {
        $key = (string) get_option( self::OPTION_KEY, '' );
        self::verify_and_cache( $key );
    }

    // -------------------------------------------------------------------------
    // Deactivation
    // -------------------------------------------------------------------------

    /**
     * Clean up the WP-Cron event when the plugin is deactivated.
     * License key and cache options are intentionally retained so that
     * re-activating the plugin does not require the administrator to
     * re-enter the key.
     */
    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}
