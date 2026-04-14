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

        // Save all scheduled times from repeatable time inputs when a session post is created
        // via the "Create sessions" admin page.
        add_action( 'save_post_' . self::SESSION_CPT, [ __CLASS__, 'save_schedule_times' ], 20 );
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
                var syncInput = document.querySelector('input[name="time"]');

                    var getRows = function() {
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
                        var disable = getRows().length <= 1;
                        rowsContainer.querySelectorAll('.remove-time-btn').forEach(function(button) {
                            button.disabled = disable;
                        });
                    };

                    var buildRow = function() {
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
                            var newRow = buildRow();
                            rowsContainer.appendChild(newRow);
                            toggleRemoveButtons();
                            syncPrimaryInput();
                            newRow.querySelector('.session-time-input').focus();
                            return;
                        }

                        var removeButton = event.target.closest('.remove-time-btn');
                        if (removeButton && wrapper.contains(removeButton)) {
                            event.preventDefault();
                            if (getRows().length <= 1) {
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
     * the POST data).
     *
     * @param int $post_id The session post being saved.
     */
    public static function save_schedule_times( int $post_id ): void {
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
        $times = [];

        foreach ( $raw_times as $raw_time ) {
            $time = sanitize_text_field( (string) $raw_time );
            if ( '' === $time ) {
                continue;
            }

            $times[] = $time;
        }

        if ( ! empty( $times ) ) {
            update_post_meta( $post_id, self::META_KEY, $times );
            error_log( 'PREMIUM: Saved premium multi-schedule times for session post.' );
        } else {
            error_log( 'PREMIUM: No valid premium multi-schedule times found during save.' );
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
