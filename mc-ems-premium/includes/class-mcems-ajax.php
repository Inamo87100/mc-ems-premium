<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * MC-EMS Premium – AJAX Handlers
 * Ricerca utenti per Proctor, Associated Candidate e Special Sessions.
 */
class MCEMS_Premium_Ajax {

    public static function init(): void {
        add_action( 'wp_ajax_mcems_premium_search_candidates', [ __CLASS__, 'search_candidates' ] );
    }

    /**
     * AJAX: ricerca candidati per nome, cognome o email.
     * Risponde con un array JSON di { id, name, first_name, last_name, email }.
     */
    public static function search_candidates(): void {
        if ( ! check_ajax_referer( 'mcems_premium_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Nonce non valido.', 403 );
            return;
        }

        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Permessi insufficienti.', 403 );
            return;
        }

        $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( mb_strlen( $query ) < 2 ) {
            wp_send_json_success( [] );
            return;
        }

        // Search by user_email / user_login / display_name
        $by_email = get_users( [
            'search'         => '*' . $query . '*',
            'search_columns' => [ 'user_email', 'user_login', 'display_name' ],
            'number'         => 20,
        ] );

        // Search by first_name meta
        $by_first = get_users( [
            'meta_query' => [ [
                'key'     => 'first_name',
                'value'   => $query,
                'compare' => 'LIKE',
            ] ],
            'number' => 20,
        ] );

        // Search by last_name meta
        $by_last = get_users( [
            'meta_query' => [ [
                'key'     => 'last_name',
                'value'   => $query,
                'compare' => 'LIKE',
            ] ],
            'number' => 20,
        ] );

        // Merge and deduplicate, then return max 20 results
        $merged = array_merge( $by_email, $by_first, $by_last );
        $seen   = [];
        $result = [];

        foreach ( $merged as $u ) {
            if ( count( $result ) >= 20 ) {
                break;
            }
            if ( isset( $seen[ $u->ID ] ) ) {
                continue;
            }
            $seen[ $u->ID ] = true;

            $fn = (string) get_user_meta( $u->ID, 'first_name', true );
            $ln = (string) get_user_meta( $u->ID, 'last_name',  true );

            $result[] = [
                'id'         => $u->ID,
                'name'       => $u->display_name,
                'first_name' => $fn,
                'last_name'  => $ln,
                'email'      => $u->user_email,
            ];
        }

        wp_send_json_success( $result );
    }
}
