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

    private $log_queue = [];

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

    private function get_sql_query($table_name, $charset_collate)
    {
        return "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            user_id mediumint(9) NOT NULL,
            user_email TEXT DEFAULT '' NOT NULL,
            action varchar(255) NOT NULL,
            details longtext NOT NULL,
            plugin_version varchar(10) NOT NULL,
            severity ENUM('WARNING', 'ERROR', 'INFO') NOT NULL,
            PRIMARY KEY  (id)
            INDEX (time)
        ) $charset_collate;";
    }

    private function create_logs_table()
    {
        global $wpdb;
        $logs_table_name = $wpdb->prefix . 'radical_form_logs';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = $this->get_sql_query($logs_table_name, $charset_collate);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function create_archive_table()
    {
        global $wpdb;
        $archive_table_name = $wpdb->prefix . 'radical_form_logs_archive';

        $charset_collate = $wpdb->get_charset_collate();
        $sql = $this->get_sql_query($archive_table_name, $charset_collate);

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

    public function process_log_queue()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'radical_form_logs';

        if (!empty($this->log_queue)) {
            $query = "INSERT INTO $table_name (time, user_id, user_email, action, details, plugin_version, severity) VALUES ";

            $rows = [];

            foreach ($this->log_queue as $log_entry) {
                $rows[] = $wpdb->prepare("(%s, %d, %s, %s, %s, %s, %s)", [
                    $log_entry['time'],
                    $log_entry['user_id'],
                    $log_entry['user_email'],
                    $log_entry['action'],
                    $log_entry['details'],
                    $log_entry['plugin_version'],
                    $log_entry['severity']
                ]);
            }

            $query .= implode(',', $rows);

            $wpdb->query($query);

            $this->log_queue = [];
        }
    }

    private function enqueue_log($severity, $action, $details, $email)
    {
        $data = $this->prepare_logging_data($action, $details, $email);
        $data['severity'] = $severity;
        $this->log_queue[] = $data;
    }

    public function log_warning($action, $details, $email = null)
    {
        $this->enqueue_log('WARNING', $action, $details, $email);
    }

    public function log_error($action, $details, $email = null)
    {
        $this->enqueue_log('ERROR', $action, $details, $email);
    }

    public function log_info($action, $details, $email = null)
    {
        $this->enqueue_log('INFO', $action, $details, $email);
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
        fputcsv($output, array('Time', 'User ID', 'Email', 'Action', 'Details', 'Plugin Version', 'Severity'));

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
    private function get_timestamp($hours, $minutes = 0)
    {
        $israel_time = new DateTime('now', new DateTimeZone('Asia/Jerusalem'));
        $israel_time = $israel_time->setTime($hours, $minutes);
        $utc_time = $israel_time->setTimezone(new DateTimeZone('UTC'));
        return $utc_time->getTimestamp();
    }

    private function schedule_cron_jobs()
    {
        if (!wp_next_scheduled('radical_form_archive_cleanup_logs')) {
            wp_schedule_event($this->get_timestamp(4), 'daily', array($this, 'archive_cleanup_logs'));
        }
        if (!wp_next_scheduled('radical_form_archive_old_logs')) {
            wp_schedule_event($this->get_timestamp(5), 'daily', array($this, 'archive_old_logs'));
        }

        if (!wp_next_scheduled('radical_form_process_log_queue')) {
            wp_schedule_event(time(), 'hourly', array($this, 'process_log_queue'));
        }
    }

    public function archive_cleanup_logs()
    {
        global $wpdb;
        $archive_table_name = $wpdb->prefix . 'radical_form_logs_archive';

        error_log("archive_cleanup_logs started at " . date("Y-m-d H:i:s"));
        $wpdb->query("DELETE FROM $archive_table_name WHERE time < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
        error_log("archive_cleanup_logs finished at " . date("Y-m-d H:i:s"));
    }

    public function archive_old_logs()
    {
        global $wpdb;
        $log_table_name = $wpdb->prefix . 'radical_form_logs';
        $archive_table_name = $wpdb->prefix . 'radical_form_logs_archive';

        $interval = '3 MONTH';

        error_log("transfering logs to archive started at " . date("Y-m-d H:i:s"));
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $archive_table_name (time, user_id, action, details, plugin_version)
         SELECT time, user_id, action, details, plugin_version FROM $log_table_name
         WHERE time < DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
        error_log("transfering logs finished at " . date("Y-m-d H:i:s"));


        error_log("deleting form logs started at " . date("Y-m-d H:i:s"));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $log_table_name WHERE time < DATE_SUB(NOW(), INTERVAL %s)",
            $interval
        ));
        error_log("deleting form logs finished at " . date("Y-m-d H:i:s"));
    }
}
