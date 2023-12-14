<?php

/**
 * Plugin Name: Radical Form Plugin
 * Description: Provides a form for users to submit their details and select a product variation
 * Version: 1.7.0
 * Author: Max Gertzen
 * Text Domain: radical-form
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('PLUGIN_VERSION', '1.8.0');

require_once plugin_dir_path(__FILE__) . 'includes/enqueue-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/utilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-options.php';
require_once plugin_dir_path(__FILE__) . 'includes/logging-service.php';

function radical_form_init()
{
    require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
    new Radical_Form_REST_API();
}

function load_plugin_logger()
{
    global $logger;
    $logger = Radical_Logging_Service::getInstance();
}

register_activation_hook(__FILE__, 'radical_form_activate');
register_deactivation_hook(__FILE__, 'radical_form_deactivate');
add_action('init', 'radical_form_init');
add_action('admin_menu', 'radical_form_add_admin_menu');
add_action('admin_init', 'radical_form_register_settings');
add_action('plugins_loaded', 'load_plugin_logger');

function radical_form_activate()
{
    if (!get_option('radical_form_encryption_key')) {
        $key = wp_generate_password(64, true, true);
        update_option('radical_form_encryption_key', $key);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    // SQL to create your table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        token VARCHAR(255) NOT NULL,
        expiry DATETIME NOT NULL,
        PRIMARY KEY  (id)
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Schedule CRON job for token cleanup
    if (!wp_next_scheduled('radical_form_cleanup_tokens')) {
        wp_schedule_event(time(), 'hourly', 'radical_form_cleanup_tokens');
    }
}

function radical_form_deactivate()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    // SQL to drop your table
    $sql = "DROP TABLE IF EXISTS $table_name;";
    $wpdb->query($sql);

    // Remove scheduled CRON job
    $timestamp = wp_next_scheduled('radical_form_cleanup_tokens');
    wp_unschedule_event($timestamp, 'radical_form_cleanup_tokens');
}
