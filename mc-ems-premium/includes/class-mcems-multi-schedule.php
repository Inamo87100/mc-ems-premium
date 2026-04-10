<?php
/**
 * MC-EMS Premium – Multi-Schedule (Multiple Session Times)
 *
 * When the premium license is active, this class replaces the single time
 * input on the session create/edit screen with a multi-time widget that lets
 * the administrator schedule the same exam session at several times per day.
 *
 * How it works:
 *  - On admin_enqueue_scripts: enqueues premium JS/CSS on the session CPT
 *    edit page and passes existing schedule times + i18n strings to JS via
 *    wp_localize_script() so the widget is pre-populated.
 *  - On save_post (priority 25, after the base plugin's priority 10): reads
 *    the submitted mcems_schedule_times[] array, validates and deduplicates
 *    the values, saves the full list to _mcems_premium_schedule_times, and
 *    also writes the first (primary) time to the base plugin's meta key for
 *    backward compatibility with any feature that reads the single time.
 *  - A static helper get_schedule_times() is provided for use by other
 *    premium classes that display session time information.
 *
 * The JS widget (MCEMSMultiSchedule in premium.js):
 *  - Hides the original single time input and injects a repeater widget in
 *    its place via DOM manipulation on DOMContentLoaded.
 *  - Each time entry is an <input type="time" name="mcems_schedule_times[]">.
 *  - The original <input name="mcemexce_time"> is kept as a hidden field and
 *    is always mirrored to the first entry so the base plugin's save logic
 *    (which reads mcemexce_time) continues to work correctly.
 *
 * Design principles:
 *  - Premium extends base; this class never modifies base plugin code.
 *  - Nonce verification is delegated to the base plugin (same nonce re-used).
 *  - Capability check on every save.
 *  - All strings are in English and wrapped in localisation functions.
 *
 * @package MC-EMS-Premium
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MCEMS_Multi_Schedule {

    /** Meta key for storing the array of scheduled times (private, hidden). */
    const META_KEY = '_mcems_premium_schedule_times';

    /** Post type slug – mirrors MCEMEXCE_CPT_Sessioni_Esame::CPT. */
    const SESSION_CPT = 'mcemexce_session';

    /** Base plugin input name for the single time field. */
    const BASE_TIME_FIELD = 'mcemexce_time';

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    public static function init(): void {
        add_action( 'admin_enqueue_scripts',      [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'save_post_' . self::SESSION_CPT, [ __CLASS__, 'save_schedule_times' ], 25 );
    }

    // -------------------------------------------------------------------------
    // Asset enqueuing
    // -------------------------------------------------------------------------

    /**
     * Enqueue premium JS/CSS on the session CPT edit screen and pass the
     * existing schedule times to JavaScript so the widget is pre-populated.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets( string $hook_suffix ): void {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        // Resolve post type for both "new post" and "edit post" screens.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_type = get_post_type( (int) ( $_GET['post'] ?? 0 ) );
        if ( ! $post_type ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = isset( $_GET['post_type'] )
                ? sanitize_key( $_GET['post_type'] )
                : '';
        }

        if ( self::SESSION_CPT !== $post_type ) {
            return;
        }

        $plugin_url = plugin_dir_url( dirname( __FILE__ ) );

        wp_enqueue_style(
            'mcems-premium-css',
            $plugin_url . 'assets/css/premium.css',
            [],
            EMS_PREMIUM_VERSION
        );

        wp_enqueue_script(
            'mcems-premium-js',
            $plugin_url . 'assets/js/premium.js',
            [ 'jquery' ],
            EMS_PREMIUM_VERSION,
            true
        );

        // Provide AJAX URL and nonce used by MCEMSUserSearchSelector
        // (proctor / associated-candidate search on the session edit form).
        wp_localize_script( 'mcems-premium-js', 'mcems_premium', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'mcems_premium_nonce' ),
        ] );

        // Load existing schedule times for pre-populating the widget.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $post_id = (int) ( $_GET['post'] ?? 0 );
        $times   = self::get_schedule_times( $post_id );

        wp_localize_script( 'mcems-premium-js', 'mcemsMultiSchedule', [
            'times'         => array_values( $times ),
            'baseField'     => self::BASE_TIME_FIELD,
            /* translators: button label to add a new time slot in the multi-schedule widget */
            'addLabel'      => __( 'Add time slot', 'mc-ems' ),
            /* translators: button label to remove a time slot from the multi-schedule widget */
            'removeLabel'   => __( 'Remove', 'mc-ems' ),
            'minOneMessage' => __( 'At least one time slot is required.', 'mc-ems' ),
        ] );
    }

    // -------------------------------------------------------------------------
    // Save handler
    // -------------------------------------------------------------------------

    /**
     * Save multiple schedule times when the session post is saved.
     *
     * Runs at priority 25 so that the base plugin's save (priority 10) has
     * already written the primary time to the base meta key before we update
     * it with the sanitised first entry from our list.
     *
     * @param int $post_id The post ID being saved.
     */
    public static function save_schedule_times( int $post_id ): void {
        // Skip autosave and revisions.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Verify the base plugin's meta box nonce so this only fires on
        // intentional form submissions from the session edit screen.
        if ( ! isset( $_POST['mcemexce_session_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_POST['mcemexce_session_nonce'] ) ),
            'mcemexce_session_save'
        ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Only process when the multi-schedule field is present in the request.
        if ( ! isset( $_POST['mcems_schedule_times'] ) ) {
            return;
        }

        $raw_times = (array) wp_unslash( $_POST['mcems_schedule_times'] );
        $times     = [];

        foreach ( $raw_times as $t ) {
            $t = sanitize_text_field( $t );
            // Accept only valid H:i values (00:00 – 23:59).
            if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $t ) ) {
                $times[] = $t;
            }
        }

        // Deduplicate and sort chronologically.
        $times = array_values( array_unique( $times ) );
        sort( $times );

        if ( empty( $times ) ) {
            return;
        }

        // Persist the full list.
        update_post_meta( $post_id, self::META_KEY, $times );

        // Keep the base plugin's single-time meta key in sync with the first
        // (earliest) entry so that any feature reading that key stays correct.
        if ( class_exists( 'MCEMEXCE_CPT_Sessioni_Esame' ) ) {
            update_post_meta( $post_id, MCEMEXCE_CPT_Sessioni_Esame::MK_TIME, $times[0] );
        }
    }

    // -------------------------------------------------------------------------
    // Public helper
    // -------------------------------------------------------------------------

    /**
     * Return all scheduled times for a session post.
     *
     * Checks the premium meta key first; if no premium data exists yet, falls
     * back to the base plugin's single-time meta key so that sessions created
     * before the premium plugin was activated are handled transparently.
     *
     * @param  int      $post_id Session post ID.
     * @return string[] Array of H:i time strings (may be empty).
     */
    public static function get_schedule_times( int $post_id ): array {
        if ( $post_id <= 0 ) {
            return [];
        }

        $times = get_post_meta( $post_id, self::META_KEY, true );

        if ( is_array( $times ) && ! empty( $times ) ) {
            return $times;
        }

        // Fallback: single time from base plugin meta key.
        if ( class_exists( 'MCEMEXCE_CPT_Sessioni_Esame' ) ) {
            $single = (string) get_post_meta( $post_id, MCEMEXCE_CPT_Sessioni_Esame::MK_TIME, true );
            if ( '' !== $single ) {
                return [ $single ];
            }
        }

        return [];
    }
}
