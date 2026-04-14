<?php
/**
 * MC-EMS Premium – Multi-Schedule (Multiple Session Times)
 *
 * When the premium plugin is active, this class overrides the time input
 * field on the base plugin's "Create sessions" admin view with a textarea
 * that allows the administrator to enter multiple HH:MM times (one per line),
 * using the 'mcems_admin_create_session_time_field_html' filter provided by
 * the base plugin.
 *
 * How it works:
 *  - Hooks 'mcems_admin_create_session_time_field_html' (filter, priority 10):
 *    returns textarea HTML that the base plugin outputs in place of its
 *    default <input type="time"> on the "Create sessions" page only.
 *    The "Edit session" metabox is NOT affected; it always keeps the single
 *    time input.
 *  - On admin_enqueue_scripts: enqueues premium JS/CSS on the "Create
 *    sessions" admin page.
 *  - A static helper get_schedule_times() is provided for use by other
 *    premium classes that display session time information.
 *
 * Design principles:
 *  - Premium extends base; this class never modifies base plugin code.
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

    /** POST field name for the textarea containing multiple times. */
    const TEXTAREA_FIELD = 'mcems_schedule_times_text';

    /**
     * Admin page slug for the base plugin's "Create sessions" page.
     * Used to scope asset enqueuing to that page only.
     */
    const CREATE_SESSIONS_PAGE = 'mcems-create-sessions';

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    public static function init(): void {
        // Hook ONLY into the "Create sessions" filter; the "Edit session"
        // metabox always keeps its original single time input.
        add_filter( 'mcems_admin_create_session_time_field_html', [ __CLASS__, 'filter_create_session_time_field_html' ], 10, 3 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Save all scheduled times from the textarea when a session post is created
        // via the "Create sessions" admin page.
        add_action( 'save_post_' . self::SESSION_CPT, [ __CLASS__, 'save_schedule_times' ], 20 );
    }

    // -------------------------------------------------------------------------
    // Filter callback – replaces the time input on "Create sessions" only
    // -------------------------------------------------------------------------

    /**
     * Return a multi-time textarea for the base plugin's "Create sessions" view.
     *
     * Hooked onto 'mcems_admin_create_session_time_field_html', which is
     * applied by the base plugin on the "Create sessions" admin page only.
     * Returning a non-empty string causes the base plugin to output this HTML
     * instead of its default <input type="time">.
     *
     * The "Edit session" metabox is NOT affected by this filter.
     *
     * @param string $html     Existing HTML (empty by default).
     * @param string $value    Default time value (if any) pre-set by the base plugin.
     * @param string $disabled 'disabled' attribute string, or empty.
     * @return string          Textarea HTML to output in place of the time input.
     */
    public static function filter_create_session_time_field_html( string $html, string $value, string $disabled ): string {
        ob_start();
        ?>
        <?php
        /*
         * Hidden input keeps the base plugin's 'time' POST field in sync
         * with the first valid time from the textarea (handled by JS), so
         * the base plugin's PHP save handler can read a single primary time
         * while this plugin stores the full list of times in post meta.
         */
        ?>
        <input
            type="hidden"
            name="time"
            value="<?php echo esc_attr( $value ); ?>"
            <?php echo $disabled ? 'disabled' : ''; ?>
        >
        <textarea
            id="mcems-schedule-times-textarea"
            name="<?php echo esc_attr( self::TEXTAREA_FIELD ); ?>"
            rows="5"
            placeholder="<?php esc_attr_e( '09:00', 'mc-ems' ); ?>"
            <?php echo $disabled ? 'disabled' : ''; ?>
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Enter one time per line in HH:MM 24-hour format (e.g. 09:00). Empty lines are ignored.', 'mc-ems' ); ?>
        </p>
        <?php
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Asset enqueuing
    // -------------------------------------------------------------------------

    /**
     * Enqueue premium JS/CSS on relevant admin pages.
     *
     * Assets are enqueued on:
     *  - The "Create sessions" custom admin page (mcems-create-sessions).
     *  - The session CPT edit and new-post screens (post.php / post-new.php).
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public static function enqueue_assets( string $hook_suffix ): void {
        $on_create_sessions = 'mcemexce_session_page_' . self::CREATE_SESSIONS_PAGE === $hook_suffix;

        $on_edit_screen = false;
        if ( in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_type = get_post_type( (int) ( $_GET['post'] ?? 0 ) );
            if ( ! $post_type ) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $post_type = isset( $_GET['post_type'] )
                    ? sanitize_key( $_GET['post_type'] )
                    : '';
            }
            $on_edit_screen = ( self::SESSION_CPT === $post_type );
        }

        if ( ! $on_create_sessions && ! $on_edit_screen ) {
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

        // Provide configuration for the multi-time textarea on the "Create sessions"
        // page only.  This enables the JS module to sync the hidden base-plugin
        // time field and to validate each line of the textarea before submission.
        if ( $on_create_sessions ) {
            wp_localize_script( 'mcems-premium-js', 'mcemsMultiSchedule', [
                'textareaId'        => 'mcems-schedule-times-textarea',
                'syncTo'            => 'time',
                /* translators: %s is the invalid time value entered by the user */
                'errorInvalidTime'  => __( 'Invalid time "%s". Use 24-hour HH:MM format (e.g. 09:00).', 'mc-ems' ),
                /* translators: shown when the textarea is completely empty */
                'errorEmptyTimes'   => __( 'Enter at least one time in HH:MM format (e.g. 09:00).', 'mc-ems' ),
            ] );
        }
    }

    // -------------------------------------------------------------------------
    // Post save handler – persist all scheduled times
    // -------------------------------------------------------------------------

    /**
     * Save all valid times from the multi-time textarea into post meta.
     *
     * Hooked onto 'save_post_mcemexce_session' (priority 20) so it runs after
     * the base plugin's own save logic.  Only acts when the Create sessions
     * form is being processed (i.e. the premium textarea field is present in
     * the POST data).
     *
     * Parsing rules (NF-Tools pattern):
     *  1. Split the raw textarea value on newlines.
     *  2. Trim leading/trailing whitespace from each line.
     *  3. Skip empty lines silently.
     *  4. Accept only lines matching 24-hour HH:MM (00:00 – 23:59).
     *  5. Accumulate all valid times; silently skip invalid lines
     *     (the JS layer prevents the form from being submitted with invalid lines).
     *
     * @param int $post_id The session post being saved.
     */
    public static function save_schedule_times( int $post_id ): void {
        // Only process requests that include the premium textarea field.
        // Nonce verification is intentionally omitted here: this hook fires
        // inside the base plugin's form-submission flow, which already verifies
        // its own nonce before calling wp_insert_post() (and therefore before
        // this hook runs).  Any other context where save_post fires will not
        // have TEXTAREA_FIELD in $_POST, so the guard below exits early.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST[ self::TEXTAREA_FIELD ] ) ) {
            return;
        }

        // Skip auto-saves and post revisions.
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        // Require the current user to have permission to edit this post.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked by base plugin.
        $raw   = sanitize_textarea_field( wp_unslash( $_POST[ self::TEXTAREA_FIELD ] ) );
        $lines = explode( "\n", $raw );
        $times = [];

        // Step 1-5: iterate, trim, skip empty, validate, accumulate.
        foreach ( $lines as $line ) {
            $t = trim( $line );

            if ( '' === $t ) {
                continue; // skip empty / whitespace-only lines
            }

            // Accept only valid 24-hour HH:MM values (00:00 – 23:59).
            if ( preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $t ) ) {
                $times[] = $t;
            }
            // Non-matching non-empty lines are silently skipped; the JS layer
            // prevents submission of invalid times before they reach PHP.
        }

        if ( ! empty( $times ) ) {
            update_post_meta( $post_id, self::META_KEY, $times );
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
