<?php

declare(strict_types=1);

namespace FormFlowPro\Logs;

if (!defined('ABSPATH')) exit;

/**
 * Log Manager - Error and Activity Tracking
 */
class Log_Manager
{
    private static ?self $instance = null;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('formflow_cleanup_logs', [$this, 'cleanup_old_logs']);
    }

    /**
     * Log message
     */
    public function log(string $type, string $message, array $context = []): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_logs';

        $wpdb->insert($table, [
            'type' => $type,
            'message' => $message,
            'context' => wp_json_encode($context),
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%d', '%s', '%s']);
    }

    /**
     * Log error
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log info
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log warning
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log debug
     */
    public function debug(string $message, array $context = []): void
    {
        if (get_option('formflow_debug_mode')) {
            $this->log('debug', $message, $context);
        }
    }

    /**
     * Get logs
     */
    public function get_logs(array $args = []): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_logs';

        $where = ['1=1'];
        $values = [];

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = $args['user_id'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'DATE(created_at) >= %s';
            $values[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'DATE(created_at) <= %s';
            $values[] = $args['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $limit = $args['limit'] ?? 100;

        $query = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d";
        $values[] = $limit;

        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_logs';

        $retention_days = get_option('formflow_log_retention_days', 30);

        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        $this->info('Old logs cleaned up', [
            'retention_days' => $retention_days
        ]);
    }

    /**
     * Get client IP
     */
    private function get_client_ip(): string
    {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return sanitize_text_field($_SERVER[$key]);
            }
        }

        return '0.0.0.0';
    }
}
