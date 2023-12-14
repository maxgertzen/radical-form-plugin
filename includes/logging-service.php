<?php

class Radical_Logging_Service
{
    private static $instance = null;

    public function __construct()
    {
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        add_action('init', array($this, 'schedule_cron_jobs'));
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Radical_Logging_Service();
        }

        return self::$instance;
    }

    // Creating the Tables
    public function create_tables()
    {
        $this->create_logs_table();
        $this->create_archive_table();
    }

    private function create_logs_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            user_id mediumint(9) NOT NULL,
            user_email TEXT DEFAULT '' NOT NULL,
            action varchar(255) NOT NULL,
            details longtext NOT NULL,
            plugin_version varchar(10) NOT NULL,
            severity ENUM('WARNING', 'ERROR', 'INFO') NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function create_archive_table()
    {
        global $wpdb;
        $archive_table_name = $wpdb->prefix . 'radical_form_logs_archive';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $archive_table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            user_id mediumint(9) NOT NULL,
            user_email TEXT DEFAULT '' NOT NULL,
            action varchar(255) NOT NULL,
            details longtext NOT NULL,
            plugin_version varchar(10) NOT NULL,
            severity ENUM('WARNING', 'ERROR', 'INFO') NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Encryption & Decryption
    private function encrypt_data($data)
    {
        $encryption_key = get_option('radical_form_encryption_key') || 'default_secret_key';
        return openssl_encrypt($data, 'AES-128-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));
    }

    private function decrypt_data($data)
    {
        $encryption_key = get_option('radical_form_encryption_key') || 'default_secret_key';
        return openssl_decrypt($data, 'AES-128-CBC', $encryption_key, 0, substr($encryption_key, 0, 16));
    }

    // Logging
    private function prepare_logging_data($action, $details, $email = null)
    {
        $user_id = get_current_user_id();
        $user_email = '';

        if ($user_id) {
            $user_info = get_userdata($user_id);
            $user_email = $user_info->user_email;
        }

        if (!$user_id && $email) {
            $user_email = sanitize_email($email);
        }

        if ($user_email) {
            $user_email = $this->encrypt_data($user_email);
        }

        $serialized_details = maybe_serialize($details);

        return array(
            'time' => current_time('mysql'),
            'user_id' => $user_id ?: 0,
            'user_email' => $user_email,
            'action' => $action,
            'details' => $serialized_details,
            'plugin_version' => PLUGIN_VERSION,
        );
    }

    public function log_warning($action, $details, $email = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';

        $data = $this->prepare_logging_data($action, $details, $email);

        $wpdb->insert(
            $table_name,
            array(
                'time' => $data['time'],
                'user_id' => $data['user_id'],
                'user_email' => $data['user_email'],
                'action' => $data['action'],
                'details' => $data['details'],
                'plugin_version' => PLUGIN_VERSION,
                'severity' => 'WARNING',
            )
        );
    }

    public function log_error($action, $details, $email = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';

        $data = $this->prepare_logging_data($action, $details, $email);

        $wpdb->insert(
            $table_name,
            array(
                'time' => $data['time'],
                'user_id' => $data['user_id'],
                'user_email' => $data['user_email'],
                'action' => $data['action'],
                'details' => $data['details'],
                'plugin_version' => PLUGIN_VERSION,
                'severity' => 'ERROR',
            )
        );
    }

    public function log_info($action, $details, $email = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';

        $data = $this->prepare_logging_data($action, $details, $email);

        $wpdb->insert(
            $table_name,
            array(
                'time' => $data['time'],
                'user_id' => $data['user_id'],
                'user_email' => $data['user_email'],
                'action' => $data['action'],
                'details' => $data['details'],
                'plugin_version' => PLUGIN_VERSION,
                'severity' => 'INFO',
            )
        );
    }

    // Export to CSV
    public function export_logs_to_csv()
    {
        if (!current_user_can('administrator')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=logs.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('Time', 'User ID', 'Email', 'Action', 'Details'));

        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        foreach ($logs as $log) {
            $log['user_email'] = $this->decrypt_data($log['user_email']);
            $log['details'] = maybe_unserialize($log['details']);
            fputcsv($output, $log);
        }
        fclose($output);
        exit;
    }

    // Cron Jobs (Cleanup & Archive)
    private function schedule_cron_jobs()
    {
        if (!wp_next_scheduled('radical_form_archive_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', array($this, 'archive_cleanup_logs'));
        }
        if (!wp_next_scheduled('radical_form_archive_old_logs')) {
            wp_schedule_event(time(), 'daily', array($this, 'archive_old_logs'));
        }
    }

    public function archive_cleanup_logs()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs_archive';
        $wpdb->query("DELETE FROM $table_name WHERE time < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
    }

    public function archive_old_logs()
    {
        global $wpdb;
        $log_table_name = $wpdb->prefix . 'radical_form_logs';
        $archive_table_name = $wpdb->prefix . 'radical_form_logs_archive';

        // Define the age threshold for archiving logs (e.g., older than 6 months)
        $interval = '3 MONTH';

        // Insert older logs into the archive table
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $archive_table_name (time, user_id, action, details, plugin_version)
         SELECT time, user_id, action, details, plugin_version FROM $log_table_name
         WHERE time < DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));

        // Delete the older logs from the original table
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $log_table_name WHERE time < DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
    }
}
