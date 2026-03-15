<?php

if (!defined('ABSPATH')) {
    exit;
}

class WP_Email_Connector_Logger
{
    /**
     * @var WP_Email_Connector_Settings
     */
    private $settings;

    /**
     * @var string
     */
    private $table_name;

    public function __construct(WP_Email_Connector_Settings $settings)
    {
        global $wpdb;

        $this->settings = $settings;
        $this->table_name = $wpdb->prefix . 'wp_email_connector_logs';
    }

    public function init()
    {
        add_action('wp_mail_succeeded', array($this, 'handle_mail_succeeded'));
        add_action('wp_mail_failed', array($this, 'handle_mail_failed'));
    }

    public static function create_table()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . 'wp_email_connector_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            to_email text NOT NULL,
            subject text NOT NULL,
            message longtext NULL,
            status varchar(20) NOT NULL,
            error_message text NULL,
            mailer varchar(20) NOT NULL,
            headers longtext NULL,
            attachments longtext NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * @param array<string, mixed> $mail_data
     */
    public function handle_mail_succeeded($mail_data)
    {
        if (!$this->is_logging_enabled()) {
            return;
        }

        $this->insert_log($mail_data, 'sent', '');
    }

    /**
     * @param WP_Error $wp_error
     */
    public function handle_mail_failed($wp_error)
    {
        if (!$this->is_logging_enabled()) {
            return;
        }

        $mail_data = array();

        if ($wp_error instanceof WP_Error) {
            $error_data = $wp_error->get_error_data();
            if (is_array($error_data) && isset($error_data['wp_mail_failed']) && is_array($error_data['wp_mail_failed'])) {
                $mail_data = $error_data['wp_mail_failed'];
            }

            $message = $wp_error->get_error_message();
        } else {
            $message = __('Unbekannter Fehler', 'wordpress-email');
        }

        $this->insert_log($mail_data, 'failed', $message);
    }

    /**
     * @param array<string, mixed> $mail_data
     * @param string $status
     * @param string $error_message
     */
    private function insert_log($mail_data, $status, $error_message)
    {
        global $wpdb;

        $to_value = '';
        if (isset($mail_data['to'])) {
            $to_value = $this->normalize_list_field($mail_data['to']);
        }

        $subject = isset($mail_data['subject']) ? sanitize_text_field((string) $mail_data['subject']) : '';
        $message = isset($mail_data['message']) ? (string) $mail_data['message'] : '';
        $headers = isset($mail_data['headers']) ? $this->normalize_list_field($mail_data['headers']) : '';
        $attachments = isset($mail_data['attachments']) ? $this->normalize_list_field($mail_data['attachments']) : '';

        $mailer = (string) $this->settings->get('transport', 'phpmailer') === 'external' ? 'external' : 'phpmailer';

        $wpdb->insert(
            $this->table_name,
            array(
                'to_email' => $to_value,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'error_message' => $error_message,
                'mailer' => $mailer,
                'headers' => $headers,
                'attachments' => $attachments,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function normalize_list_field($value)
    {
        if (is_array($value)) {
            $safe = array();
            foreach ($value as $item) {
                $safe[] = (string) $item;
            }

            return implode(', ', $safe);
        }

        return (string) $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_logs($limit = 50, $offset = 0)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT id, created_at, to_email, subject, message, status, error_message, mailer
             FROM {$this->table_name}
             ORDER BY id DESC
             LIMIT %d OFFSET %d",
            (int) $limit,
            (int) $offset
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        return is_array($results) ? $results : array();
    }

    /**
     * @return int
     */
    public function count_logs()
    {
        global $wpdb;

        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        return (int) $count;
    }

    /**
     * @return array<string, int>
     */
    public function get_status_summary()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) as total
             FROM {$this->table_name}
             GROUP BY status",
            ARRAY_A
        );

        $summary = array(
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
        );

        if (!is_array($rows)) {
            return $summary;
        }

        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $count = isset($row['total']) ? (int) $row['total'] : 0;

            $summary['total'] += $count;

            if ($status === 'sent') {
                $summary['sent'] += $count;
            } elseif ($status === 'failed') {
                $summary['failed'] += $count;
            }
        }

        return $summary;
    }

    /**
     * @param string $since_datetime
     * @return int
     */
    public function count_failed_since($since_datetime)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$this->table_name}
             WHERE status = %s AND created_at >= %s",
            'failed',
            $since_datetime
        );

        $count = $wpdb->get_var($query);

        return (int) $count;
    }

    /**
     * @param string $since_datetime
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function get_failed_logs_since($since_datetime, $limit = 10)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT id, created_at, to_email, subject, status, error_message, mailer
             FROM {$this->table_name}
             WHERE status = %s AND created_at >= %s
             ORDER BY id DESC
             LIMIT %d",
            'failed',
            $since_datetime,
            (int) $limit
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        return is_array($results) ? $results : array();
    }

    public function clear_logs()
    {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * @return bool
     */
    private function is_logging_enabled()
    {
        return (int) $this->settings->get('log_enabled', 1) === 1;
    }
}
