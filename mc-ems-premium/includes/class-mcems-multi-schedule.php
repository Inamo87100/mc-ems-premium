<?php
/**
 * MC-EMS Premium – Multi-Schedule (Multiple Session Times)
 *
 * When the premium license is active, this class adds a "Session Times" meta
 * box to the session create/edit screen that replaces the single time input
 * with a textarea allowing the administrator to enter multiple times per day
 * (one per line, format HH:MM).
 *
 * How it works:
 *  - On add_meta_boxes: registers a "Session Times" meta box containing a
 *    textarea (one HH:MM time per line).
 *  - On admin_enqueue_scripts: enqueues premium JS/CSS on the session CPT
 *    edit page and injects a small script that hides the base plugin's single
 *    time input row and keeps it in sync with the first valid time entered in
 *    the textarea (for backward compatibility with the base plugin's save).
 *  - On save_post (priority 25, after the base plugin's priority 10): reads
 *    the textarea value, splits it into lines, validates each line as a valid
 *    HH:MM time, deduplicates, saves the full list to _mcems_premium_schedule_times,
 *    and also writes the first (primary) time to the base plugin's meta key for
 *    backward compatibility with any feature that reads the single time.
 *  - A static helper get_schedule_times() is provided for use by other
 *    premium classes that display session time information.
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

    /** POST field name for the textarea containing multiple times. */
    const TEXTAREA_FIELD = 'mcems_schedule_times_text';

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    public static function init(): void {
        add_action( 'add_meta_boxes',                 [ __CLASS__, 'register_meta_box' ] );
        add_action( 'admin_enqueue_scripts',          [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'save_post_' . self::SESSION_CPT, [ __CLASS__, 'save_schedule_times' ], 25 );
    }

    // -------------------------------------------------------------------------
    // Meta box
    // -------------------------------------------------------------------------

    /**
     * Register the "Session Times" meta box on the session CPT edit screen.
     */
    public static function register_meta_box(): void {
        add_meta_box(
            'mcems-schedule-times',
            __( 'Session Times', 'mc-ems' ),
            [ __CLASS__, 'render_meta_box' ],
            self::SESSION_CPT,
            'normal',
            'high'
        );
    }

    /**
     * Render the "Session Times" meta box.
     *
     * Displays a textarea pre-populated with the existing scheduled times,
     * one per line in HH:MM format.
     *
     * @param WP_Post $post The current post object.
     */
    public static function render_meta_box( WP_Post $post ): void {
        $times         = self::get_schedule_times( $post->ID );
        $textarea_val  = implode( "\n", $times );
        ?>
        <p>
            <label for="mcems-schedule-times-textarea">
                <?php esc_html_e( 'Session Times (one per line, format HH:MM)', 'mc-ems' ); ?>
            </label>
        </p>
        <textarea
            id="mcems-schedule-times-textarea"
            name="<?php echo esc_attr( self::TEXTAREA_FIELD ); ?>"
            rows="5"
            style="width:100%;font-family:monospace;"
            placeholder="<?php esc_attr_e( '09:00', 'mc-ems' ); ?>"
        ><?php echo esc_textarea( $textarea_val ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Enter one time per line in HH:MM format (e.g. 09:00). Invalid or empty lines are ignored. The first valid time is also used as the primary session time.', 'mc-ems' ); ?>
        </p>
        <?php
    }

    // -------------------------------------------------------------------------
    // Asset enqueuing
    // -------------------------------------------------------------------------

    /**
     * Enqueue premium JS/CSS on the session CPT edit screen.
     *
     * The JS hides the base plugin's single time input row and keeps it in
     * sync with the first valid time entered in the textarea so that the base
     * plugin's save logic continues to work correctly.
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

        // Pass the base plugin's input field name so the JS can hide it and
        // keep it in sync with the first time in our textarea.
        wp_localize_script( 'mcems-premium-js', 'mcemsMultiSchedule', [
            'baseField'    => self::BASE_TIME_FIELD,
            'textareaId'   => 'mcems-schedule-times-textarea',
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

        // Only process when the textarea field is present in the request.
        if ( ! isset( $_POST[ self::TEXTAREA_FIELD ] ) ) {
            return;
        }

        // Split textarea value into lines and validate each as a HH:MM time.
        $raw_text  = sanitize_textarea_field( wp_unslash( $_POST[ self::TEXTAREA_FIELD ] ) );
        $raw_lines = explode( "\n", $raw_text );
        $times     = [];

        foreach ( $raw_lines as $line ) {
            $t = trim( $line );
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
