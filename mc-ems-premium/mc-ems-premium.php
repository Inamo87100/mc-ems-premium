<?php
/*
Plugin Name: MC-EMS Premium – Add-on for Exam Session Management
Plugin URI: https://github.com/Inamo87100/mc-ems-premium
Description: Premium add-on for MC-EMS Base. Removes limits and adds advanced exam booking features: unlimited exam sessions, up to 500 seats per session, advanced booking search with date-range filters, bulk management, and optimized CSV export. Requires MC-EMS – Exam Session Management (Base) plugin.
Version: 2.2.6.4-premium
Requires at least: 6.0
Requires PHP: 7.0
Author: MC Tools
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mc-ems
Domain Path: /languages
Requires Plugins: mc-ems-base
*/

if (!defined('ABSPATH')) exit;


/** Load translations */
function mc_ems_load_textdomain() {
    load_plugin_textdomain('mc-ems', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'mc_ems_load_textdomain');
define('EMS_PREMIUM_VERSION', '1.1.4');

final class EMS_Premium_Bootstrap {

    public static function init(): void {
        add_action('plugins_loaded', [__CLASS__, 'boot'], 20);
        add_action('admin_notices', [__CLASS__, 'notice_missing_base']);
    }

    public static function base_active(): bool {
        // Base defines MCEMS_VERSION and provides these classes.
        return defined('MCEMS_VERSION') && class_exists('MCEMS_Settings') && class_exists('MCEMS_CPT_Sessioni_Esame');
    }

    public static function notice_missing_base(): void {
        if (!current_user_can('activate_plugins')) return;
        if (self::base_active()) return;

        echo '<div class="notice notice-error"><p><strong>EMS Premium</strong> richiede il plugin <strong>EMS – Exam Management System (Base)</strong> attivo.</p></div>';
    }

    public static function boot(): void {
        if (!self::base_active()) return;

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
}

EMS_Premium_Bootstrap::init();
