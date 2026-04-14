<?php
/*
Plugin Name: MC-EMS Premium – Add-on for Exam Session Management
Plugin URI: https://github.com/Inamo87100/mc-ems-premium
Description: Premium add-on for MC-EMS – Exam Center for Tutor LMS. Removes limits and adds advanced exam booking features: unlimited exam sessions, up to 500 seats per session, advanced booking search with date-range filters, bulk management, and optimized CSV export. Requires MC-EMS – Exam Center for Tutor LMS (Base) plugin.
Version: 2.2.6.4-premium
Requires at least: 5.0
Requires PHP: 7.0
Author: Mamba Coding
Author URI: https://mambacoding.com/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mc-ems
Domain Path: /languages
Requires Plugins: mc-ems-exam-center-for-tutor-lms
*/

if (!defined('ABSPATH')) exit;


/** Load translations */
function mc_ems_load_textdomain() {
    load_plugin_textdomain('mc-ems', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'mc_ems_load_textdomain');
define('EMS_PREMIUM_VERSION', '1.1.4');

// ---------------------------------------------------------------------------
// License management – loaded early so cron hooks and admin UI are always
// registered, independently of whether the base plugin is active.
// ---------------------------------------------------------------------------
require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-license.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-license-page.php';

MCEMS_License::init();
MCEMS_License_Page::init();

// Clean up cron event on plugin deactivation.
register_deactivation_hook(__FILE__, ['MCEMS_License', 'deactivate']);

// ---------------------------------------------------------------------------
// Premium bootstrap
// ---------------------------------------------------------------------------

final class EMS_Premium_Bootstrap {

    public static function init(): void {
        add_action('plugins_loaded', [__CLASS__, 'boot_unlimited_limits'], 1);
        add_action('plugins_loaded', [__CLASS__, 'boot'], 20);
        add_action('admin_notices', [__CLASS__, 'notice_missing_base']);
    }

    public static function base_active(): bool {
        // Base defines MCEMEXCE_VERSION and provides these classes.
        return defined('MCEMEXCE_VERSION') && class_exists('MCEMEXCE_Settings') && class_exists('MCEMEXCE_CPT_Sessioni_Esame');
    }

    public static function notice_missing_base(): void {
        if (!current_user_can('activate_plugins')) return;
        if (self::base_active()) return;

        /* translators: both plugin names should not be translated */
        echo '<div class="notice notice-error"><p>'
            . wp_kses(
                __( '<strong>MC-EMS Premium</strong> requires the <strong>MC-EMS &ndash; Exam Center for Tutor LMS</strong> plugin to be installed and active.', 'mc-ems' ),
                [ 'strong' => [] ]
            )
            . '</p></div>';
    }

    public static function boot(): void {
        if (!self::base_active()) return;

        // Multi-schedule (repeatable HTML5 time-input override for the
        // "Create sessions" time field) is always initialised as long as the
        // base plugin is active, so that the
        // mcems_admin_create_session_time_field_html filter is always registered
        // and repeatable time inputs are visible whenever the premium plugin is present.
        // The "Edit session" metabox keeps its original single time input.
        require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-multi-schedule.php';

        if (class_exists('MCEMS_Multi_Schedule')) {
            MCEMS_Multi_Schedule::init();
        }

        // Gate all other premium features behind a valid license.
        // The site and the free plugin are never affected.
        if (!MCEMS_License::is_valid()) return;

        require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-bookings-list.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-ajax.php';

        // Replace placeholder shortcode (Base) with real one
        if (shortcode_exists('mcems_bookings_list')) {
            remove_shortcode('mcems_bookings_list');
        }

        if (class_exists('MCEMS_Bookings_List')) {
            MCEMS_Bookings_List::init();
        }

        if (class_exists('MCEMS_Premium_Ajax')) {
            MCEMS_Premium_Ajax::init();
        }
    }

    public static function boot_unlimited_limits(): void {
        if (!self::base_active()) return;
        if (!MCEMS_License::is_valid()) return;

        require_once plugin_dir_path(__FILE__) . 'includes/class-mcems-unlimited-limits.php';

        if (class_exists('MCEMS_Unlimited_Limits')) {
            MCEMS_Unlimited_Limits::init();
            error_log('PREMIUM: Unlimited limits booted early on plugins_loaded.');
        }
    }
}

EMS_Premium_Bootstrap::init();
