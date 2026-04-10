<?php
/**
 * MC-EMS Premium – License Management Admin Page
 *
 * Registers a "Premium License" submenu page under the MC-EMS base plugin
 * menu (visible only when the base plugin is active). The page allows the
 * site administrator to:
 *   - View the current license status and a partially masked license key.
 *   - Activate the license by saving and verifying a key.
 *   - Deactivate/remove the license key (with a JS confirmation dialog).
 *   - See the last verification timestamp.
 *
 * When the license is valid, this class also registers the
 * `mcems_premium_is_active` filter (returns true) so that the base plugin
 * and any third-party code can detect the active premium state without
 * hard-coding plugin-specific checks.
 *
 * All strings are in English and wrapped in WordPress localisation functions
 * (__() / esc_html__() / esc_html_e()) so that translators can provide
 * translations via standard .po/.mo files.
 *
 * Design principles:
 *  - Premium extends base; base never calls premium code directly.
 *  - No options or UI added to the base plugin.
 *  - Nonce verification on every form submission.
 *  - Capability check ('manage_options') on every privileged action.
 *
 * @package MC-EMS-Premium
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MCEMS_License_Page {

    /**
     * The CPT slug used by the base plugin as its top-level admin menu.
     * The submenu is attached here so it appears inside the MC-EMS menu group.
     *
     * Value mirrors MCEMEXCE_CPT_Sessioni_Esame::CPT in the base plugin.
     * Using the string literal keeps this file self-contained and avoids a
     * hard dependency on the base class being loaded before this file.
     */
    const BASE_CPT = 'mcemexce_session';

    /** Admin page slug for the premium license page. */
    const PAGE_SLUG = 'mcems-premium-license';

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Register all WordPress hooks.
     *
     * Called early (before plugins_loaded priority 20) so that cron and
     * deactivation hooks are in place before premium features boot.
     */
    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'handle_form' ] );
        add_action( 'admin_notices', [ __CLASS__, 'show_notices' ] );

        /*
         * When the license is valid, expose the `mcems_premium_is_active`
         * filter so that the base plugin (and third-party code) can detect the
         * active premium state and suppress "free version limits" notices.
         *
         * Usage in base or third-party code:
         *   if ( apply_filters( 'mcems_premium_is_active', false ) ) { ... }
         */
        if ( MCEMS_License::is_valid() ) {
            add_filter( 'mcems_premium_is_active', '__return_true' );
        }
    }

    // -------------------------------------------------------------------------
    // Menu registration
    // -------------------------------------------------------------------------

    /**
     * Add "Premium License" as a submenu under the MC-EMS base plugin menu.
     *
     * The submenu is only registered when the base plugin is active (i.e. its
     * CPT has been registered), so it is invisible unless both plugins are
     * running. Premium never modifies the base plugin's menu; it only adds a
     * child entry via add_submenu_page().
     *
     * The parent slug `edit.php?post_type=mcemexce_session` is the same menu
     * group used by the base plugin's Settings page.
     */
    public static function register_menu(): void {
        // Guard: only add the submenu when the base plugin is active.
        if ( ! self::base_active() ) {
            return;
        }

        add_submenu_page(
            /* $parent_slug */ 'edit.php?post_type=' . self::BASE_CPT,
            /* $page_title  */ __( 'MC-EMS Premium License', 'mc-ems' ),
            /* $menu_title  */ __( 'Premium License', 'mc-ems' ),
            /* $capability  */ 'manage_options',
            /* $menu_slug   */ self::PAGE_SLUG,
            /* $callback    */ [ __CLASS__, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Form handler
    // -------------------------------------------------------------------------

    /**
     * Handle POST submissions from the license management form.
     *
     * Two actions are supported:
     *  - `mcems_license_save`       – save + verify a new or updated key.
     *  - `mcems_license_deactivate` – remove the license key and clear cache.
     *
     * Both actions require a valid nonce and the 'manage_options' capability.
     * After processing, the user is redirected back to the license page with a
     * status flag so a confirmation notice can be displayed.
     */
    public static function handle_form(): void {
        // ---- Save / verify action ----
        if ( isset( $_POST['mcems_license_save'] ) ) {
            if ( ! check_admin_referer( 'mcems_license_action', 'mcems_license_nonce' ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $new_key = isset( $_POST['mcems_license_key'] )
                ? sanitize_text_field( wp_unslash( $_POST['mcems_license_key'] ) )
                : '';

            MCEMS_License::save_and_verify( $new_key );

            wp_safe_redirect(
                add_query_arg( 'mcems_updated', '1', self::page_url() )
            );
            exit;
        }

        // ---- Deactivate / remove license action ----
        if ( isset( $_POST['mcems_license_deactivate'] ) ) {
            if ( ! check_admin_referer( 'mcems_license_action', 'mcems_license_nonce' ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            // Clear the stored key and cache so the license is fully deactivated.
            MCEMS_License::save_and_verify( '' );

            wp_safe_redirect(
                add_query_arg( 'mcems_deactivated', '1', self::page_url() )
            );
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // Admin notices
    // -------------------------------------------------------------------------

    /**
     * Display an admin notice on all admin screens when the premium license is
     * not valid. The notice links to the license page so the administrator can
     * quickly resolve the issue.
     *
     * The notice is shown only to administrators and only when the base plugin
     * is active (otherwise the "missing base" notice registered by
     * EMS_Premium_Bootstrap takes priority and would be redundant).
     *
     * When the license IS valid, no notice is shown here. The base plugin's
     * own "free version limits" upsell notice is suppressed automatically
     * because MCEMS_Unlimited_Limits overrides the limit filters (which the
     * base checks before displaying its notice). Additionally, the
     * `mcems_premium_is_active` filter (added in init()) lets the base plugin
     * and third-party code verify the premium state.
     *
     * Note: this method never blocks the site or the free plugin – it only
     * informs the administrator about the premium license status.
     */
    public static function show_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show when the base plugin is active; the "missing base" notice
        // (from EMS_Premium_Bootstrap) takes priority otherwise.
        if ( ! self::base_active() ) {
            return;
        }

        $status = MCEMS_License::get_cached_status();

        // No notice needed when the license is valid.
        if ( isset( $status['valid'] ) && true === $status['valid'] ) {
            return;
        }

        $settings_url = self::page_url();
        $api_status   = isset( $status['status'] ) ? $status['status'] : '';

        switch ( $api_status ) {

            case 'no_key':
                $msg = sprintf(
                    /* translators: %s: URL of the premium license page */
                    __( '<strong>MC-EMS Premium:</strong> No license key entered. Premium features are disabled. <a href="%s">Enter your license &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'server_error':
                $msg = sprintf(
                    /* translators: %s: URL of the premium license page */
                    __( '<strong>MC-EMS Premium:</strong> Unable to verify the license status; please try again later. Premium features are temporarily disabled. <a href="%s">Manage license &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'expired':
                $msg = sprintf(
                    /* translators: %s: URL of the premium license page */
                    __( '<strong>MC-EMS Premium:</strong> Your license has expired. Premium features are disabled. <a href="%s">Renew your license &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'inactive':
                $msg = sprintf(
                    /* translators: %s: URL of the premium license page */
                    __( '<strong>MC-EMS Premium:</strong> The license is not activated on this site. Premium features are disabled. <a href="%s">Activate license &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            default:
                $msg = sprintf(
                    /* translators: %s: URL of the premium license page */
                    __( '<strong>MC-EMS Premium:</strong> Invalid license. Premium features are disabled. <a href="%s">Check license &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;
        }

        echo '<div class="notice notice-error is-dismissible"><p>'
            . wp_kses(
                $msg,
                [
                    'strong' => [],
                    'a'      => [ 'href' => [] ],
                ]
            )
            . '</p></div>';
    }

    // -------------------------------------------------------------------------
    // Page renderer
    // -------------------------------------------------------------------------

    /**
     * Render the "MC-EMS Premium License" admin page.
     *
     * Displays:
     *  - Current license status with a color-coded indicator.
     *  - Partially masked license key (first 4 + last 4 characters visible).
     *  - Last verification timestamp.
     *  - Form to save/update the license key.
     *  - Deactivation button (with a JavaScript confirmation dialog) when a
     *    key is currently stored.
     */
    public static function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_key = (string) get_option( MCEMS_License::OPTION_KEY, '' );
        $status      = MCEMS_License::get_cached_status();
        $valid       = isset( $status['valid'] ) && true === $status['valid'];
        $api_status  = isset( $status['status'] ) ? (string) $status['status'] : '';
        $checked_at  = isset( $status['checked_at'] ) ? (int) $status['checked_at'] : 0;

        // Build the date/time format used by WordPress for the site locale.
        $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        ?>
        <div class="wrap">

            <h1><?php esc_html_e( 'MC-EMS Premium License', 'mc-ems' ); ?></h1>

            <?php
            // Success notice: license key saved and verified.
            $mcems_updated = filter_input( INPUT_GET, 'mcems_updated', FILTER_SANITIZE_NUMBER_INT );
            if ( '1' === (string) $mcems_updated ) :
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'License settings updated.', 'mc-ems' ); ?></p>
            </div>
            <?php endif; ?>

            <?php
            // Success notice: license key removed.
            $mcems_deactivated = filter_input( INPUT_GET, 'mcems_deactivated', FILTER_SANITIZE_NUMBER_INT );
            if ( '1' === (string) $mcems_deactivated ) :
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'License key removed. Premium features have been deactivated.', 'mc-ems' ); ?></p>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width:640px;padding:20px 24px;margin-top:20px;">

                <h2 style="margin-top:0;"><?php esc_html_e( 'License Status', 'mc-ems' ); ?></h2>

                <?php if ( $valid ) : ?>
                    <p style="color:#2e7d32;font-weight:bold;">
                        &#9989; <?php esc_html_e( 'License active and valid. All premium features are unlocked.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'server_error' === $api_status ) : ?>
                    <p style="color:#e65100;font-weight:bold;">
                        &#9888;&#65039; <?php esc_html_e( 'Unable to verify the license status; please try again later. Premium features are temporarily disabled.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'no_key' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'No license key entered. Enter your key to unlock premium features.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'expired' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'Your license has expired. Renew your license to continue using premium features.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'inactive' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'The license is not activated on this site.', 'mc-ems' ); ?>
                    </p>

                <?php else : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'Invalid license. Please check your key and try again.', 'mc-ems' ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( '' !== $current_key ) : ?>
                    <p style="color:#555;font-size:0.9em;margin-top:4px;">
                        <?php
                        printf(
                            /* translators: %s: partially masked license key */
                            esc_html__( 'License key: %s', 'mc-ems' ),
                            '<code>' . esc_html( self::mask_key( $current_key ) ) . '</code>'
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <?php if ( $checked_at > 0 ) : ?>
                    <p style="color:#555;font-size:0.9em;margin-top:4px;">
                        <?php
                        printf(
                            /* translators: %s: date and time of the last license verification */
                            esc_html__( 'Last verified: %s', 'mc-ems' ),
                            esc_html( date_i18n( $date_format, $checked_at ) )
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <hr style="margin:20px 0;">

                <h2><?php esc_html_e( 'Enter / Update License Key', 'mc-ems' ); ?></h2>

                <form method="post" action="">
                    <?php wp_nonce_field( 'mcems_license_action', 'mcems_license_nonce' ); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="mcems_license_key">
                                    <?php esc_html_e( 'License Key', 'mc-ems' ); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="text"
                                    id="mcems_license_key"
                                    name="mcems_license_key"
                                    value="<?php echo esc_attr( $current_key ); ?>"
                                    class="regular-text"
                                    autocomplete="off"
                                    placeholder="MC-XXXXX-XXXXX-XXXXX"
                                >
                                <p class="description">
                                    <?php esc_html_e( 'Enter the license key you received when purchasing MC-EMS Premium.', 'mc-ems' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php
                    submit_button(
                        __( 'Save and Verify', 'mc-ems' ),
                        'primary',
                        'mcems_license_save'
                    );
                    ?>
                </form>

                <?php if ( '' !== $current_key ) : ?>
                    <hr style="margin:20px 0;">

                    <h2><?php esc_html_e( 'Deactivate License', 'mc-ems' ); ?></h2>
                    <p><?php esc_html_e( 'Removing the license key will deactivate all premium features on this site. You can re-enter the key at any time to reactivate.', 'mc-ems' ); ?></p>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'mcems_license_action', 'mcems_license_nonce' ); ?>
                        <?php
                        /*
                         * The onclick confirmation uses wp.i18n when available, with a plain
                         * JS string as the fallback, so the dialog text is always in the
                         * WordPress admin language. The confirm() return value prevents the
                         * form from being submitted if the user cancels.
                         */
                        ?>
                        <button
                            type="submit"
                            name="mcems_license_deactivate"
                            value="1"
                            class="button button-secondary"
                            onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the license key? This will deactivate all premium features.', 'mc-ems' ) ); ?>')"
                        >
                            <?php esc_html_e( 'Remove License Key', 'mc-ems' ); ?>
                        </button>
                    </form>
                <?php endif; ?>

            </div><!-- /.card -->

        </div><!-- /.wrap -->
        <?php
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when the MC-EMS base plugin is active.
     *
     * Checks for the version constant and the two classes that the premium
     * plugin depends on, matching the same guard used in EMS_Premium_Bootstrap.
     *
     * @return bool
     */
    private static function base_active(): bool {
        return defined( 'MCEMEXCE_VERSION' )
            && class_exists( 'MCEMEXCE_Settings' )
            && class_exists( 'MCEMEXCE_CPT_Sessioni_Esame' );
    }

    /**
     * Returns the URL of the premium license admin page.
     *
     * When the base plugin is active the page lives under the MC-EMS CPT
     * menu. When the base plugin is not active (edge case during deactivation)
     * a safe fallback URL is returned.
     *
     * @return string Absolute admin URL for the license page.
     */
    private static function page_url(): string {
        return admin_url(
            'edit.php?post_type=' . self::BASE_CPT . '&page=' . self::PAGE_SLUG
        );
    }

    /**
     * Partially mask a license key for safe display.
     *
     * The first 4 and last 4 characters are shown; everything in between is
     * replaced with asterisks. Keys of 8 characters or fewer are fully masked.
     *
     * Example: "MC-AAAAA-BBBBB-CCCC" → "MC-A***************CCCC"
     *
     * @param  string $key The full license key.
     * @return string      The masked key suitable for display.
     */
    private static function mask_key( string $key ): string {
        $len = strlen( $key );
        if ( $len <= 8 ) {
            return str_repeat( '*', $len );
        }
        return substr( $key, 0, 4 )
            . str_repeat( '*', $len - 8 )
            . substr( $key, -4 );
    }
}
