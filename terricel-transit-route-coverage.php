<?php
/**
 * Plugin Name: Terricel Transit Dispatch
 * Plugin URI: https://kineticmktg.com
 * Description: Dispatch child module for Terricel Transit Operations.
 * Version: 0.28.11
 * Author: Kinetic Marketing LLC
 * Author URI: https://kineticmktg.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: terricel-transit-route-coverage
 * Requires Plugins: terricel-logistics-plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TERRICEL_ROUTE_COVERAGE_VERSION', '0.28.11');
define('TERRICEL_ROUTE_COVERAGE_FILE', __FILE__);
define('TERRICEL_ROUTE_COVERAGE_PATH', plugin_dir_path(__FILE__));
define('TERRICEL_ROUTE_COVERAGE_URL', plugin_dir_url(__FILE__));
define('TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN', 'terricel-transit-route-coverage');

add_action('plugins_loaded', 'terricel_route_coverage_load_textdomain');
add_action('plugins_loaded', 'terricel_route_coverage_bootstrap', 15);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'terricel_route_coverage_plugin_action_links');

function terricel_route_coverage_load_textdomain() {
    load_plugin_textdomain(
        TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN,
        false,
        dirname(plugin_basename(TERRICEL_ROUTE_COVERAGE_FILE)) . '/languages'
    );
}

function terricel_route_coverage_bootstrap() {
    if (!class_exists('Terricel_Logistics_Module')) {
        add_action('admin_notices', 'terricel_route_coverage_missing_parent_notice');
        return;
    }

    require_once TERRICEL_ROUTE_COVERAGE_PATH . 'includes/class-terricel-route-coverage-module.php';

    add_action('terricel_logistics_register_modules', 'terricel_route_coverage_register_module');
}

function terricel_route_coverage_register_module($registry) {
    $registry->add(new Terricel_Route_Coverage_Module());
}

function terricel_route_coverage_plugin_action_links($links) {
    $module_link = '<a href="' . esc_url(admin_url('admin.php?page=terricel-transit-modules')) . '">' . esc_html__('Parent Modules', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN) . '</a>';
    array_unshift($links, $module_link);

    return $links;
}

function terricel_route_coverage_missing_parent_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Terricel Transit Route Coverage requires the Terricel Transit Operations parent plugin to be active.', TERRICEL_ROUTE_COVERAGE_TEXT_DOMAIN);
    echo '</p></div>';
}
