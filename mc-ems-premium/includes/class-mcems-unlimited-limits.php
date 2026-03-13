<?php
/**
 * MC-EMS Premium - Override Limiti Illimitati
 * 
 * Rimuove i limiti della versione base e consente:
 * - Sessioni illimitate
 * - Capienza illimitata (fino a 500)
 * - Multipli slot per giorno
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCEMS_Unlimited_Limits {
    
    private static $instance;
    
    public static function init() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter( 'mcems_base_max_sessions', array( $this, 'override_max_sessions' ) );
        add_filter( 'mcems_base_max_capacity', array( $this, 'override_max_capacity' ) );
        add_filter( 'mcems_check_sessions_limit', array( $this, 'override_sessions_limit_check' ), 10, 2 );
        add_filter( 'mcems_check_capacity_limit', array( $this, 'override_capacity_limit_check' ), 10, 2 );
        add_filter( 'mcems_slots_per_day_limit', array( $this, 'override_slots_per_day_limit' ) );
    }
    
    /**
     * Override: Sessioni massime illimitate
     * Base permette max 5 sessioni, premium permette illimitato
     */
    public function override_max_sessions( $limit ) {
        return 999999; // Illimitato
    }
    
    /**
     * Override: Capienza massima = 500 (invece di 5)
     */
    public function override_max_capacity( $limit ) {
        return 500; // Max 500 posti per sessione
    }
    
    /**
     * Override: Controlla limite sessioni
     * Disabilita il check del limite delle 5 sessioni
     */
    public function override_sessions_limit_check( $check_passed, $future_sessions_count ) {
        // Premium: sempre true, nessun limite
        return true;
    }
    
    /**
     * Override: Controlla limite capienza
     * Disabilita il check della capienza massima a 5
     */
    public function override_capacity_limit_check( $check_passed, $capacity ) {
        // Premium: permetti fino a 500
        if ( $capacity <= 500 ) {
            return true;
        }
        return false;
    }
    
    /**
     * Override: Limite slot per giorno
     * Base permette 1 slot al giorno per corso
     * Premium permette multipli slot per giorno
     */
    public function override_slots_per_day_limit() {
        return 999; // Illimitati slot per giorno
    }
    
    /**
     * Verifica se Premium è attivo
     */
    public static function is_premium_active() {
        return defined( 'MCEMS_PREMIUM_VERSION' );
    }
}

// Istanzia solo se premium è attivo
if ( class_exists( 'MCEMS_Unlimited_Limits' ) ) {
    MCEMS_Unlimited_Limits::init();
}