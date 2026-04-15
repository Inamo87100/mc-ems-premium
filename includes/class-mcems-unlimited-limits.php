<?php
/**
 * MC-EMS Premium - Unlimited Limits Overrides.
 *
 * Removes base limits and allows:
 * - Unlimited sessions
 * - Capacity up to 500 seats
 * - Multiple slots per day
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCEMS_Unlimited_Limits {
    
    private static $instance;
    const MAX_SESSIONS = 999999;
    const MAX_CAPACITY = 500;
    const MAX_SLOTS_PER_DAY = 999;
    
    public static function init() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
            error_log( 'PREMIUM: MCEMS_Unlimited_Limits initialized.' );
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter( 'mcems_base_max_sessions', array( $this, 'override_max_sessions' ), PHP_INT_MAX );
        add_filter( 'mcems_base_max_capacity', array( $this, 'override_max_capacity' ), PHP_INT_MAX );
        add_filter( 'mcems_check_sessions_limit', array( $this, 'override_sessions_limit_check' ), PHP_INT_MAX, 2 );
        add_filter( 'mcems_check_capacity_limit', array( $this, 'override_capacity_limit_check' ), PHP_INT_MAX, 2 );
        add_filter( 'mcems_slots_per_day_limit', array( $this, 'override_slots_per_day_limit' ), PHP_INT_MAX );
        add_filter( 'mcems_premium_is_active', array( $this, 'force_premium_active_flag' ), PHP_INT_MAX );
        error_log( 'PREMIUM: Unlimited limits filters registered.' );
    }
    
    /**
     * Override: unlimited max sessions.
     */
    public function override_max_sessions( $limit ) {
        error_log( 'PREMIUM: override_max_sessions applied.' );
        return self::MAX_SESSIONS;
    }
    
    /**
     * Override: max capacity = 500.
     */
    public function override_max_capacity( $limit ) {
        error_log( 'PREMIUM: override_max_capacity applied.' );
        return self::MAX_CAPACITY;
    }
    
    /**
     * Override: always pass sessions-limit checks.
     */
    public function override_sessions_limit_check( $check_passed, $future_sessions_count ) {
        error_log( 'PREMIUM: override_sessions_limit_check forced to true.' );
        return true;
    }
    
    /**
     * Override: allow capacities up to MAX_CAPACITY.
     */
    public function override_capacity_limit_check( $check_passed, $capacity ) {
        error_log( 'PREMIUM: override_capacity_limit_check evaluated.' );
        if ( (int) $capacity <= self::MAX_CAPACITY ) {
            return true;
        }
        return false;
    }
    
    /**
     * Override: multiple slots per day.
     */
    public function override_slots_per_day_limit() {
        error_log( 'PREMIUM: override_slots_per_day_limit applied.' );
        return self::MAX_SLOTS_PER_DAY;
    }
    
    /**
     * Force premium-active flag used by base notices.
     */
    public function force_premium_active_flag( $is_active ) {
        error_log( 'PREMIUM: force_premium_active_flag applied.' );
        return true;
    }

    /**
     * Check if Premium is active.
     */
    public static function is_premium_active() {
        return defined( 'EMS_PREMIUM_VERSION' );
    }
}
