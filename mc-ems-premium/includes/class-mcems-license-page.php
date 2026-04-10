<?php
/**
 * MC-EMS Premium – Pagina Admin "Licenza Premium"
 *
 * Aggiunge una sotto-pagina nelle opzioni di WordPress dove l'amministratore
 * può inserire / aggiornare la chiave licenza e visualizzarne lo stato.
 * Mostra anche admin-notice sull'intera area admin se la licenza non è valida.
 *
 * @package MC-EMS-Premium
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MCEMS_License_Page {

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Register all WordPress hooks.
     */
    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init',    [ __CLASS__, 'handle_form' ] );
        add_action( 'admin_notices', [ __CLASS__, 'show_notices' ] );
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    /**
     * Add "MC-EMS Licenza" under Settings → MC-EMS Licenza.
     */
    public static function register_menu(): void {
        add_options_page(
            /* $page_title */ __( 'MC-EMS Licenza Premium', 'mc-ems' ),
            /* $menu_title */ __( 'MC-EMS Licenza', 'mc-ems' ),
            /* $capability */ 'manage_options',
            /* $menu_slug  */ 'mcems-premium-license',
            /* $callback   */ [ __CLASS__, 'render_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Form handler
    // -------------------------------------------------------------------------

    /**
     * Process POST submission: verify nonce, sanitize input, delegate to
     * MCEMS_License::save_and_verify(), then redirect with a query-string flag
     * so the page can display a confirmation notice after the redirect.
     */
    public static function handle_form(): void {
        if ( ! isset( $_POST['mcems_license_save'] ) ) {
            return;
        }

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
            add_query_arg(
                'mcems_updated',
                '1',
                admin_url( 'options-general.php?page=mcems-premium-license' )
            )
        );
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin notices
    // -------------------------------------------------------------------------

    /**
     * Display a persistent admin notice on ALL admin pages when the license is
     * not valid.  The notice is only shown to administrators and only when the
     * MC-EMS – Exam Center for Tutor LMS plugin is active (otherwise the "missing base" notice takes
     * priority).
     *
     * Note: this method never blocks the site or the free plugin – it only
     * informs the admin about the premium license status.
     */
    public static function show_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Only show license notice when the base plugin is active; the
        // "missing base" notice (registered separately) takes priority otherwise.
        if ( ! ( defined( 'MCEMEXCE_VERSION' ) && class_exists( 'MCEMEXCE_Settings' ) ) ) {
            return;
        }

        $status = MCEMS_License::get_cached_status();

        // No notice needed when license is valid
        if ( isset( $status['valid'] ) && true === $status['valid'] ) {
            return;
        }

        $settings_url = admin_url( 'options-general.php?page=mcems-premium-license' );
        $api_status   = isset( $status['status'] ) ? $status['status'] : '';

        switch ( $api_status ) {

            case 'no_key':
                $msg = sprintf(
                    /* translators: %s: URL della pagina licenza */
                    __( '<strong>MC-EMS Premium:</strong> Nessuna chiave licenza inserita. Le funzioni premium sono disattivate. <a href="%s">Inserisci la licenza &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'server_error':
                $msg = sprintf(
                    /* translators: %s: URL della pagina licenza */
                    __( '<strong>MC-EMS Premium:</strong> Impossibile verificare lo stato licenza, riprova pi&ugrave; tardi. Le funzioni premium sono temporaneamente disattivate. <a href="%s">Gestisci licenza &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'expired':
                $msg = sprintf(
                    /* translators: %s: URL della pagina licenza */
                    __( '<strong>MC-EMS Premium:</strong> La licenza &egrave; scaduta. Le funzioni premium sono disattivate. <a href="%s">Rinnova la licenza &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            case 'inactive':
                $msg = sprintf(
                    /* translators: %s: URL della pagina licenza */
                    __( '<strong>MC-EMS Premium:</strong> La licenza non &egrave; attivata su questo sito. Le funzioni premium sono disattivate. <a href="%s">Attiva la licenza &rarr;</a>', 'mc-ems' ),
                    esc_url( $settings_url )
                );
                break;

            default:
                $msg = sprintf(
                    /* translators: %s: URL della pagina licenza */
                    __( '<strong>MC-EMS Premium:</strong> Licenza non valida. Le funzioni premium sono disattivate. <a href="%s">Verifica la licenza &rarr;</a>', 'mc-ems' ),
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
     * Render the "MC-EMS Licenza Premium" settings page.
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

        ?>
        <div class="wrap">

            <h1><?php esc_html_e( 'MC-EMS Licenza Premium', 'mc-ems' ); ?></h1>

            <?php
            // Confirmation notice after a successful form save + redirect
            $mcems_updated = filter_input( INPUT_GET, 'mcems_updated', FILTER_SANITIZE_NUMBER_INT );
            if ( '1' === (string) $mcems_updated ) :
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Impostazioni licenza aggiornate.', 'mc-ems' ); ?></p>
            </div>
            <?php endif; ?>

            <div class="card" style="max-width:640px;padding:20px 24px;margin-top:20px;">

                <h2 style="margin-top:0;"><?php esc_html_e( 'Stato Licenza', 'mc-ems' ); ?></h2>

                <?php if ( $valid ) : ?>
                    <p style="color:#2e7d32;font-weight:bold;">
                        &#9989; <?php esc_html_e( 'Licenza attiva e valida. Tutte le funzioni premium sono sbloccate.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'server_error' === $api_status ) : ?>
                    <p style="color:#e65100;font-weight:bold;">
                        &#9888;&#65039; <?php esc_html_e( 'Impossibile verificare lo stato licenza, riprova pi&ugrave; tardi. Le funzioni premium sono temporaneamente disattivate.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'no_key' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'Nessuna chiave licenza inserita. Inserisci la tua chiave per sbloccare le funzioni premium.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'expired' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'La licenza &egrave; scaduta. Rinnova la licenza per continuare a usare le funzioni premium.', 'mc-ems' ); ?>
                    </p>

                <?php elseif ( 'inactive' === $api_status ) : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'La licenza non &egrave; attivata su questo sito.', 'mc-ems' ); ?>
                    </p>

                <?php else : ?>
                    <p style="color:#c62828;font-weight:bold;">
                        &#10060; <?php esc_html_e( 'Licenza non valida. Verifica la chiave e riprova.', 'mc-ems' ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $checked_at > 0 ) : ?>
                    <p style="color:#555;font-size:0.9em;margin-top:4px;">
                        <?php
                        printf(
                            /* translators: %s: data e ora dell'ultima verifica */
                            esc_html__( 'Ultima verifica: %s', 'mc-ems' ),
                            esc_html(
                                date_i18n(
                                    get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                                    $checked_at
                                )
                            )
                        );
                        ?>
                    </p>
                <?php endif; ?>

                <hr style="margin:20px 0;">

                <h2><?php esc_html_e( 'Inserisci / Aggiorna Chiave Licenza', 'mc-ems' ); ?></h2>

                <form method="post" action="">
                    <?php wp_nonce_field( 'mcems_license_action', 'mcems_license_nonce' ); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="mcems_license_key">
                                    <?php esc_html_e( 'Chiave Licenza', 'mc-ems' ); ?>
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
                                    <?php esc_html_e( 'Inserisci la chiave licenza ricevuta al momento dell\'acquisto.', 'mc-ems' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php
                    submit_button(
                        __( 'Salva e Verifica', 'mc-ems' ),
                        'primary',
                        'mcems_license_save'
                    );
                    ?>
                </form>

            </div><!-- /.card -->

        </div><!-- /.wrap -->
        <?php
    }
}
