<?php
/**
 * MC-EMS Premium – Multi-Schedule (Multiple Session Times)
 *
 * When the premium plugin is active, this class overrides the time input
 * field on the base plugin's "Create sessions" admin view with a repeatable
 * list of HTML5 time inputs,
 * using the 'mcems_admin_create_session_time_field_html' filter provided by
 * the base plugin.
 *
 * How it works:
 *  - Hooks 'mcems_admin_create_session_time_field_html' (filter, priority 10):
 *    returns repeatable HTML5 time-input markup that the base plugin outputs
 *    in place of its default <input type="time"> on the "Create sessions"
 *    page only.
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

    /** POST field name for the repeatable list of session time values. */
    const TIMES_FIELD = 'session_times';

    /** Internal marker meta to identify premium-generated sibling sessions. */
    const GENERATED_FROM_META = '_mcems_premium_generated_from';

    /**
     * Strict 24-hour HH:MM time validation pattern.
     * Accepted range: 00:00 to 23:59.
     */
    const TIME_24H_PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    /** Minimum allowed seats/capacity value when normalising numeric metadata. */
    const MIN_SESSION_CAPACITY = 1;

    /**
     * Admin page slug for the base plugin's "Create sessions" page.
     * Used to scope asset enqueuing to that page only.
     */
    const CREATE_SESSIONS_PAGE = 'mcems-create-sessions';

    /**
     * Re-entrancy guard to avoid recursive duplicate creation when this class
     * inserts additional sessions during the same request.
     *
     * @var bool
     */
    private static $is_generating_extra_sessions = false;

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    public static function init(): void {
        // Hook ONLY into the "Create sessions" filter; the "Edit session"
        // metabox always keeps its original single time input.
        add_filter( 'mcems_admin_create_session_time_field_html', [ __CLASS__, 'filter_create_session_time_field_html' ], 10, 3 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // Save all scheduled times from repeatable time inputs when a session post is created
        // via the "Create sessions" admin page.
        // Uses all 3 save_post args so create-only sibling generation can rely
        // on $post details and the $update flag.
        add_action( 'save_post_' . self::SESSION_CPT, [ __CLASS__, 'save_schedule_times' ], 20, 3 );
        error_log( 'PREMIUM: Multi-schedule hooks registered.' );
    }

    // -------------------------------------------------------------------------
    // Filter callback – replaces the time input on "Create sessions" only
    // -------------------------------------------------------------------------

    /**
     * Return repeatable time inputs for the base plugin's "Create sessions" view.
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
     * @return string          Repeatable time-input HTML to output in place of the time input.
     */
    public static function filter_create_session_time_field_html( string $html, string $value, string $disabled ): string {
        error_log( 'PREMIUM: Overriding create-session time field with premium repeatable inputs.' );
        ob_start();
        ?>
        <?php /* Keep base-plugin primary time in sync with the first repeatable time value. */ ?>
        <input
            type="hidden"
            name="time"
            value="<?php echo esc_attr( $value ); ?>"
            <?php echo $disabled ? 'disabled' : ''; ?>
        >
        <div
            id="mcems-schedule-times-repeater"
            class="session-times-wrapper"
            data-input-name="<?php echo esc_attr( self::TIMES_FIELD ); ?>[]"
            data-remove-label="<?php echo esc_attr( __( 'Remove', 'mc-ems' ) ); ?>"
            data-sync-input-name="time"
        >
            <div class="session-time-rows">
                <div class="session-time-row">
                    <input
                        type="time"
                        name="<?php echo esc_attr( self::TIMES_FIELD ); ?>[]"
                        class="session-time-input"
                        value="<?php echo esc_attr( $value ); ?>"
                        step="60"
                        <?php echo $disabled ? 'disabled' : ''; ?>
                    >
                    <button
                        type="button"
                        class="button-link-delete remove-time-btn"
                        <?php echo $disabled ? 'disabled' : ''; ?>
                    >
                        <?php esc_html_e( 'Remove', 'mc-ems' ); ?>
                    </button>
                </div>
            </div>
            <button
                type="button"
                class="button add-time-btn"
                <?php echo $disabled ? 'disabled' : ''; ?>
            >
                <?php esc_html_e( 'Add time', 'mc-ems' ); ?>
            </button>
        </div>
        <p class="description">
            <?php esc_html_e( 'Add one or more session times using the time fields below.', 'mc-ems' ); ?>
        </p>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait one tick so the enqueued premium.js ready callback can bind first.
            // If it already bound handlers, the fallback exits immediately.
            window.setTimeout(function() {
                var wrapper = document.getElementById('mcems-schedule-times-repeater');
                if (!wrapper || !wrapper.classList.contains('session-times-wrapper')) {
                    return;
                }

                if (wrapper.getAttribute('data-mcems-time-ui-bound') === '1') {
                    return;
                }
                wrapper.setAttribute('data-mcems-time-ui-bound', '1');

                var rowsContainer = wrapper.querySelector('.session-time-rows');
                if (!rowsContainer) {
                    return;
                }

                var inputName = wrapper.getAttribute('data-input-name') || 'session_times[]';
                var removeLabel = wrapper.getAttribute('data-remove-label') || 'Remove';
                var syncInputName = wrapper.getAttribute('data-sync-input-name') || 'time';
                var syncInput = document.querySelector('input[name="' + syncInputName + '"]');

                var getRowElements = function() {
                    return rowsContainer.querySelectorAll('.session-time-row');
                };

                var syncPrimaryInput = function() {
                    if (!syncInput) {
                        return;
                    }

                    var firstNonEmptyTime = '';
                    rowsContainer.querySelectorAll('.session-time-input').forEach(function(input) {
                        if (firstNonEmptyTime !== '') {
                            return;
                        }
                        var value = (input.value || '').trim();
                        if (value !== '') {
                            firstNonEmptyTime = value;
                        }
                    });
                    syncInput.value = firstNonEmptyTime;
                };

                var toggleRemoveButtons = function() {
                    var disable = getRowElements().length <= 1;
                    rowsContainer.querySelectorAll('.remove-time-btn').forEach(function(button) {
                        button.disabled = disable;
                    });
                };

                var createRowElement = function() {
                    var row = document.createElement('div');
                    row.className = 'session-time-row';

                    var input = document.createElement('input');
                    input.type = 'time';
                    input.name = inputName;
                    input.className = 'session-time-input';
                    input.step = '60';

                    var removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'button-link-delete remove-time-btn';
                    removeButton.textContent = removeLabel;

                    row.appendChild(input);
                    row.appendChild(removeButton);
                    return row;
                };

                wrapper.addEventListener('click', function(event) {
                    var addButton = event.target.closest('.add-time-btn');
                    if (addButton && wrapper.contains(addButton)) {
                        event.preventDefault();
                        var newRow = createRowElement();
                        rowsContainer.appendChild(newRow);
                        toggleRemoveButtons();
                        syncPrimaryInput();
                        newRow.querySelector('.session-time-input').focus();
                        return;
                    }

                    var removeButton = event.target.closest('.remove-time-btn');
                    if (removeButton && wrapper.contains(removeButton)) {
                        event.preventDefault();
                        if (getRowElements().length <= 1) {
                            return;
                        }

                        var row = removeButton.closest('.session-time-row');
                        if (row) {
                            row.remove();
                            toggleRemoveButtons();
                            syncPrimaryInput();
                        }
                    }
                });

                wrapper.addEventListener('input', function(event) {
                    if (event.target.classList.contains('session-time-input')) {
                        syncPrimaryInput();
                    }
                });

                wrapper.addEventListener('change', function(event) {
                    if (event.target.classList.contains('session-time-input')) {
                        syncPrimaryInput();
                    }
                });

                toggleRemoveButtons();
                syncPrimaryInput();
            }, 0);
        });
        </script>
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

        // Provide configuration for repeatable multi-time inputs on the
        // "Create sessions" page only.
        if ( $on_create_sessions ) {
            wp_localize_script( 'mcems-premium-js', 'mcemsMultiSchedule', [
                'repeaterId'  => 'mcems-schedule-times-repeater',
                'syncTo'      => 'time',
                'inputName'   => self::TIMES_FIELD . '[]',
                'removeLabel' => __( 'Remove', 'mc-ems' ),
            ] );
        }
    }

    // -------------------------------------------------------------------------
    // Post save handler – persist all scheduled times
    // -------------------------------------------------------------------------

    /**
     * Save all submitted times from repeatable session-time fields into post meta.
     *
     * Hooked onto 'save_post_mcemexce_session' (priority 20) so it runs after
     * the base plugin's own save logic.  Only acts when the Create sessions
     * form is being processed (i.e. the premium repeatable time fields are present in
     * the POST data). During new-session creation only, this also generates
     * one sibling session for each additional valid time so that every selected
     * day receives sessions for all submitted times (day × time).
     *
     * @param int      $post_id The session post being saved.
     * @param \WP_Post $post    Post object being saved.
     * @param bool     $update  Whether this save is an existing-post update.
     */
    public static function save_schedule_times( int $post_id, \WP_Post $post, bool $update ): void {
        // Only process requests that include premium repeatable time fields.
        // Nonce verification is intentionally omitted here: this hook fires
        // inside the base plugin's form-submission flow, which already verifies
        // its own nonce before calling wp_insert_post() (and therefore before
        // this hook runs).  Any other context where save_post fires will not
        // have TIMES_FIELD in $_POST, so the guard below exits early.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST[ self::TIMES_FIELD ] ) || ! is_array( $_POST[ self::TIMES_FIELD ] ) ) {
            return;
        }

        // Prevent recursion when premium creates extra sibling sessions.
        if ( self::$is_generating_extra_sessions ) {
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
        $raw_times = wp_unslash( $_POST[ self::TIMES_FIELD ] );
        $times = self::sanitize_and_validate_times( $raw_times );
        if ( count( $times ) < count( $raw_times ) ) {
            error_log( 'PREMIUM: One or more submitted session times were ignored because they were empty, duplicated, or invalid.' );
        }

        if ( ! empty( $times ) ) {
            update_post_meta( $post_id, self::META_KEY, $times );
            self::create_additional_sessions_for_new_creation( $post_id, $post, $times, $update );
            error_log( 'PREMIUM: Saved premium multi-schedule times for session post.' );
        } else {
            error_log( 'PREMIUM: No valid premium multi-schedule times found during save.' );
        }
    }

    /**
     * Sanitize and validate submitted time values from session_times[].
     *
     * Accepted format is strict 24-hour "HH:MM" (00:00 to 23:59). Empty values
     * and invalid values are discarded. Duplicate valid times are removed while
     * preserving the original order provided by the user.
     *
     * @param array $raw_times Raw values from POST.
     * @return string[]        Sanitized valid times.
     */
    private static function sanitize_and_validate_times( array $raw_times ): array {
        $times = [];

        foreach ( $raw_times as $raw_time ) {
            $time = trim( sanitize_text_field( (string) $raw_time ) );
            if ( '' === $time ) {
                continue;
            }

            if ( ! preg_match( self::TIME_24H_PATTERN, $time ) ) {
                continue;
            }

            $times[] = $time;
        }

        // Input is trimmed and validated before uniqueness, so duplicate checks are
        // deterministic string comparisons on canonical HH:MM values.
        return array_values( array_unique( $times ) );
    }

    /**
     * During new-session creation only, generate one sibling session per extra time.
     *
     * The base plugin still creates one post per selected day using the primary
     * time value. This method expands that result by cloning the newly created
     * day-session for every additional validated time so that each day receives
     * all submitted times (day × time). Existing-session edits are intentionally
     * excluded by checking $update.
     *
     * @param int      $post_id Original session post ID created by the base flow.
     * @param \WP_Post $post    Post object for the saved post.
     * @param string[] $times   Validated list of submitted times.
     * @param bool     $update  True when editing an existing post.
     */
    private static function create_additional_sessions_for_new_creation( int $post_id, \WP_Post $post, array $times, bool $update ): void {
        // Requirement scope: create flow only; never generate siblings on edit.
        if ( empty( $times ) || $update || count( $times ) <= 1 ) {
            return;
        }

        if ( ! class_exists( 'MCEMEXCE_CPT_Sessioni_Esame' ) ) {
            error_log( 'PREMIUM: Base session class missing; skipping multi-time sibling generation.' );
            return;
        }

        // First valid time is assigned to the base-created session post.
        $primary_time = $times[0];
        $extra_times  = array_slice( $times, 1 );
        $time_meta_key = MCEMEXCE_CPT_Sessioni_Esame::MK_TIME;

        // Ensure the base-created post keeps the first valid time.
        update_post_meta( $post_id, $time_meta_key, $primary_time );

        $all_meta = get_post_meta( $post_id );
        $date_meta_key = self::get_defined_base_meta_key( [ 'MK_DATE', 'L_MK_DATE' ] );
        $exam_meta_key = self::get_defined_base_meta_key( [ 'MK_EXAM_ID', 'MK_COURSE_ID' ] );
        $capacity_meta_key = self::get_defined_base_meta_key( [ 'MK_CAPACITY', 'L_MK_CAPACITY' ] );

        $session_date = '';
        if ( null !== $date_meta_key ) {
            $session_date = self::sanitize_and_validate_date( (string) get_post_meta( $post_id, $date_meta_key, true ) );
        }

        $session_exam_id = 0;
        if ( null !== $exam_meta_key ) {
            $session_exam_id = absint( get_post_meta( $post_id, $exam_meta_key, true ) );
        }

        $session_capacity = 0;
        if ( null !== $capacity_meta_key ) {
            $session_capacity = max( self::MIN_SESSION_CAPACITY, absint( get_post_meta( $post_id, $capacity_meta_key, true ) ) );
        }

        self::$is_generating_extra_sessions = true;

        try {
            foreach ( $extra_times as $time ) {
                $clone_id = wp_insert_post( [
                    'post_type'      => self::SESSION_CPT,
                    'post_status'    => $post->post_status,
                    'post_author'    => $post->post_author,
                    'post_title'     => self::build_session_title( $session_date, $time, $post->post_title ),
                    'post_content'   => $post->post_content,
                    'post_excerpt'   => $post->post_excerpt,
                    'post_parent'    => $post->post_parent,
                    'comment_status' => $post->comment_status,
                    'ping_status'    => $post->ping_status,
                    'menu_order'     => $post->menu_order,
                ], true );

                if ( is_wp_error( $clone_id ) ) {
                    error_log( 'PREMIUM: Failed to create multi-time sibling session: ' . $clone_id->get_error_message() );
                    continue;
                }

                $excluded_meta_keys = self::get_clone_excluded_meta_keys( $time_meta_key );

                foreach ( $all_meta as $meta_key => $meta_values ) {
                    if ( in_array( $meta_key, $excluded_meta_keys, true ) ) {
                        continue;
                    }

                    foreach ( $meta_values as $meta_value ) {
                        add_post_meta( $clone_id, $meta_key, maybe_unserialize( $meta_value ) );
                    }
                }

                update_post_meta( $clone_id, $time_meta_key, $time );
                if ( null !== $date_meta_key && '' !== $session_date ) {
                    update_post_meta( $clone_id, $date_meta_key, $session_date );
                }
                if ( null !== $exam_meta_key ) {
                    update_post_meta( $clone_id, $exam_meta_key, $session_exam_id );
                }
                if ( null !== $capacity_meta_key ) {
                    update_post_meta( $clone_id, $capacity_meta_key, $session_capacity );
                }

                self::clone_session_taxonomies( $post_id, (int) $clone_id );
                update_post_meta( $clone_id, self::META_KEY, $times );
                update_post_meta( $clone_id, self::GENERATED_FROM_META, $post_id );
            }
        } finally {
            self::$is_generating_extra_sessions = false;
        }
    }

    /**
     * Return meta keys excluded from sibling-session cloning.
     *
     * A filter is exposed so projects can extend this safely if they introduce
     * additional internal/session-specific meta keys that must not be copied.
     *
     * @param string $time_meta_key Time meta key that must be excluded from cloning
     *                              so sibling sessions can store their own time value.
     * @return string[]
     */
    private static function get_clone_excluded_meta_keys( string $time_meta_key ): array {
        $default = [
            $time_meta_key,
            self::META_KEY,
            self::GENERATED_FROM_META,
            '_edit_lock',
            '_edit_last',
        ];

        // Developers can extend/replace excluded keys by returning a string[].
        // Hook name: mcems_premium_multitime_clone_excluded_meta_keys.
        $filtered = apply_filters( 'mcems_premium_multitime_clone_excluded_meta_keys', $default, $time_meta_key );
        return is_array( $filtered ) ? $filtered : $default;
    }

    /**
     * Return the first defined base-plugin meta-key constant from a candidate list.
     *
     * This keeps premium compatible across base-plugin versions where exam/capacity
     * constants can have different names.
     *
     * @param string[] $constant_names Candidate constant names on MCEMEXCE_CPT_Sessioni_Esame.
     * @return string|null             Resolved meta key or null when no constant exists.
     */
    private static function get_defined_base_meta_key( array $constant_names ): ?string {
        foreach ( $constant_names as $constant_name ) {
            $fqcn_constant = 'MCEMEXCE_CPT_Sessioni_Esame::' . $constant_name;
            if ( defined( $fqcn_constant ) ) {
                $resolved = constant( $fqcn_constant );
                if ( is_string( $resolved ) && '' !== $resolved ) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * Sanitize and validate a single session date string.
     *
     * @param string $raw_date Raw date string that should be in Y-m-d format.
     * @return string          Sanitized date or empty string when invalid.
     */
    private static function sanitize_and_validate_date( string $raw_date ): string {
        $date = trim( sanitize_text_field( $raw_date ) );
        if ( '' === $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        $errors = \DateTimeImmutable::getLastErrors();
        if ( false === $parsed ) {
            return '';
        }

        if (
            is_array( $errors ) &&
            (
                ! empty( $errors['warning_count'] ) ||
                ! empty( $errors['error_count'] )
            )
        ) {
            return '';
        }

        if ( $parsed->format( 'Y-m-d' ) !== $date ) {
            return '';
        }

        return $date;
    }

    /**
     * Build a sibling-session title from the current date/time combination.
     *
     * @param string $date           Session date in Y-m-d format.
     * @param string $time           Session time in H:i format.
     * @param string $fallback_title Existing title used when date/time is unavailable.
     * @return string                Title for the current (day, time) pair.
     */
    private static function build_session_title( string $date, string $time, string $fallback_title ): string {
        if ( '' !== $date && '' !== $time ) {
            return sprintf(
                /* translators: 1: session date in Y-m-d format, 2: session time in H:i format */
                __( 'Session %1$s %2$s', 'mc-ems' ),
                $date,
                $time
            );
        }

        return sanitize_text_field( $fallback_title );
    }

    /**
     * Copy taxonomy terms from source session to cloned session.
     *
     * Some base-plugin fields may be represented as taxonomy terms instead of
     * post meta; cloning terms guarantees those fields stay aligned per clone.
     *
     * @param int $source_post_id Source session post ID.
     * @param int $clone_id       Clone session post ID.
     */
    private static function clone_session_taxonomies( int $source_post_id, int $clone_id ): void {
        $taxonomies = get_object_taxonomies( self::SESSION_CPT, 'names' );
        if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
            return;
        }

        foreach ( $taxonomies as $taxonomy ) {
            $term_ids = wp_get_object_terms( $source_post_id, $taxonomy, [ 'fields' => 'ids' ] );
            if ( is_wp_error( $term_ids ) ) {
                error_log(
                    sprintf(
                        'PREMIUM: Failed to read source taxonomy terms for taxonomy "%s" on session #%d: %s',
                        $taxonomy,
                        $source_post_id,
                        $term_ids->get_error_message()
                    )
                );
                continue;
            }

            if ( ! is_array( $term_ids ) ) {
                continue;
            }

            $append = false;
            $set_terms_result = wp_set_object_terms( $clone_id, array_map( 'absint', $term_ids ), $taxonomy, $append );
            if ( is_wp_error( $set_terms_result ) ) {
                error_log(
                    sprintf(
                        'PREMIUM: Failed to clone taxonomy terms for taxonomy "%s" on session clone #%d: %s',
                        $taxonomy,
                        $clone_id,
                        $set_terms_result->get_error_message()
                    )
                );
            }
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
