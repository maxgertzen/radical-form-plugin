<?php

/**
 * Plugin Name: Radical Form Plugin
 * Description: Provides a form for users to submit their details and select a product variation
 * Version: 2.2.1
 * Author: Max Gertzen
 * Text Domain: radical-form
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('RADICAL_FORM_PLUGIN_VERSION', '2.2.1');

require_once plugin_dir_path(__FILE__) . 'includes/enqueue-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/variation-selection.php';
require_once plugin_dir_path(__FILE__) . 'includes/utilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-options.php';

global $logger_service_instance;
if (!class_exists('Radical_Logging_Service')) {
    require_once plugin_dir_path(__FILE__) . 'includes/logging-service.php';
}
$logger_service_instance = Radical_Logging_Service::getInstance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'radical_form_activate');
register_deactivation_hook(__FILE__, 'radical_form_deactivate');

function radical_form_init()
{
    require_once plugin_dir_path(__FILE__) . 'includes/rest-api.php';
    new Radical_Form_REST_API();
}

function load_logger_and_textdomain()
{
    global $logger_service_instance;
    add_action('admin_post_export_logs_to_csv', array($logger_service_instance, 'export_logs_to_csv'));

    load_plugin_textdomain('radical-form', false, basename(dirname(__FILE__)) . '/languages/');
}


add_action('woocommerce_loaded', 'radical_form_init');
add_action('plugins_loaded', 'load_logger_and_textdomain');

add_action('admin_menu', 'radical_form_add_admin_menu');
add_action('admin_init', 'radical_form_register_settings');

function radical_form_activate()
{

    if (!get_option('radical_form_encryption_key')) {
        $key = wp_generate_password(64, true, true);
        update_option('radical_form_encryption_key', $key);
    }

    global $wpdb;
    global $logger_service_instance;

    $logger_service_instance = Radical_Logging_Service::getInstance();
    $logger_service_instance->create_tables();

    $table_name = $wpdb->prefix . 'radical_session_tokens';

    // SQL to create your table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        token VARCHAR(255) NOT NULL,
        expiry BIGINT NOT NULL,
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

    if (wp_next_scheduled('radical_form_process_log_queue')) {
        wp_clear_scheduled_hook('radical_form_process_log_queue');
    }
}
