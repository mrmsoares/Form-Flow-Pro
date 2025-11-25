<?php

declare(strict_types=1);

namespace FormFlowPro\Analytics;

if (!defined('ABSPATH')) exit;

/**
 * Advanced Analytics Service
 *
 * Provides comprehensive analytics and metrics for FormFlow Pro Enterprise.
 *
 * Features:
 * - Advanced submission metrics
 * - Performance tracking (processing time, queue stats)
 * - Autentique signature analytics
 * - Period comparison
 * - Export capabilities (CSV, PDF)
 *
 * @package FormFlowPro
 * @since 2.2.0
 */
class Analytics_Service
{
    private static ?self $instance = null;

    /**
     * WordPress database object.
     *
     * @var \wpdb
     */
    private $wpdb;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get comprehensive dashboard metrics.
     *
     * @param string $date_from Start date (Y-m-d).
     * @param string $date_to End date (Y-m-d).
     * @param int $form_id Optional form filter.
     * @return array Dashboard metrics.
     */
    public function get_dashboard_metrics(string $date_from, string $date_to, int $form_id = 0): array
    {
        $where = $this->build_where_clause($date_from, $date_to, $form_id);
        $previous_period = $this->get_previous_period($date_from, $date_to);

        return [
            'overview' => $this->get_overview_metrics($where),
            'previous_period' => $this->get_overview_metrics(
                $this->build_where_clause($previous_period['from'], $previous_period['to'], $form_id)
            ),
            'performance' => $this->get_performance_metrics($where),
            'autentique' => $this->get_autentique_metrics($where),
            'queue' => $this->get_queue_metrics(),
            'cache' => $this->get_cache_metrics(),
            'trends' => $this->get_trend_data($date_from, $date_to, $form_id),
            'top_forms' => $this->get_top_forms($where),
            'geographic' => $this->get_geographic_distribution($where),
        ];
    }

    /**
     * Get overview metrics (total, completed, pending, failed).
     *
     * @param array $where WHERE clause data.
     * @return array Overview metrics.
     */
    private function get_overview_metrics(array $where): array
    {
        $table = $this->wpdb->prefix . 'formflow_submissions';

        $total = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where['sql']}",
            ...$where['values']
        ));

        $completed = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND {$where['sql']}",
            ...$where['values']
        ));

        $pending = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending', 'processing', 'pending_signature') AND {$where['sql']}",
            ...$where['values']
        ));

        $failed = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND {$where['sql']}",
            ...$where['values']
        ));

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'failed' => $failed,
            'conversion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get performance metrics (processing times).
     *
     * @param array $where WHERE clause data.
     * @return array Performance metrics.
     */
    private function get_performance_metrics(array $where): array
    {
        $table = $this->wpdb->prefix . 'formflow_submissions';

        $avg_processing_time = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT AVG(processing_time_ms) FROM {$table}
             WHERE processing_time_ms IS NOT NULL AND {$where['sql']}",
            ...$where['values']
        ));

        $max_processing_time = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MAX(processing_time_ms) FROM {$table}
             WHERE processing_time_ms IS NOT NULL AND {$where['sql']}",
            ...$where['values']
        ));

        $min_processing_time = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT MIN(processing_time_ms) FROM {$table}
             WHERE processing_time_ms IS NOT NULL AND {$where['sql']}",
            ...$where['values']
        ));

        $percentile_95 = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT processing_time_ms FROM {$table}
             WHERE processing_time_ms IS NOT NULL AND {$where['sql']}
             ORDER BY processing_time_ms ASC
             LIMIT 1 OFFSET (
                SELECT FLOOR(COUNT(*) * 0.95) FROM {$table}
                WHERE processing_time_ms IS NOT NULL AND {$where['sql']}
             )",
            ...array_merge($where['values'], $where['values'])
        ));

        return [
            'avg_processing_time_ms' => round((float) $avg_processing_time, 2),
            'max_processing_time_ms' => (int) $max_processing_time,
            'min_processing_time_ms' => (int) $min_processing_time,
            'p95_processing_time_ms' => (int) $percentile_95,
        ];
    }

    /**
     * Get Autentique signature metrics.
     *
     * @param array $where WHERE clause data.
     * @return array Autentique metrics.
     */
    private function get_autentique_metrics(array $where): array
    {
        $submissions_table = $this->wpdb->prefix . 'formflow_submissions';
        $autentique_table = $this->wpdb->prefix . 'formflow_autentique_documents';

        // Check if autentique table exists
        $table_exists = $this->wpdb->get_var(
            "SHOW TABLES LIKE '{$autentique_table}'"
        );

        if (!$table_exists) {
            return [
                'total_documents' => 0,
                'signed' => 0,
                'pending' => 0,
                'refused' => 0,
                'signature_rate' => 0,
                'avg_sign_time_hours' => 0,
            ];
        }

        $total_docs = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$autentique_table}"
        );

        $signed = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$autentique_table} WHERE status = 'signed'"
        );

        $pending = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$autentique_table} WHERE status = 'pending'"
        );

        $refused = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$autentique_table} WHERE status = 'refused'"
        );

        // Average time to sign (in hours)
        $avg_sign_time = $this->wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, signed_at))
             FROM {$autentique_table}
             WHERE status = 'signed' AND signed_at IS NOT NULL"
        );

        return [
            'total_documents' => $total_docs,
            'signed' => $signed,
            'pending' => $pending,
            'refused' => $refused,
            'signature_rate' => $total_docs > 0 ? round(($signed / $total_docs) * 100, 2) : 0,
            'avg_sign_time_hours' => round((float) $avg_sign_time, 1),
        ];
    }

    /**
     * Get queue system metrics.
     *
     * @return array Queue metrics.
     */
    private function get_queue_metrics(): array
    {
        $table = $this->wpdb->prefix . 'formflow_queue';

        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table}'");
        if (!$table_exists) {
            return [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'dead_letter' => 0,
                'avg_wait_time_seconds' => 0,
            ];
        }

        $stats = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
            OBJECT_K
        );

        $avg_wait_time = $this->wpdb->get_var(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, started_at))
             FROM {$table}
             WHERE started_at IS NOT NULL"
        );

        return [
            'pending' => (int) ($stats['pending']->count ?? 0),
            'processing' => (int) ($stats['processing']->count ?? 0),
            'completed' => (int) ($stats['completed']->count ?? 0),
            'failed' => (int) ($stats['failed']->count ?? 0),
            'dead_letter' => (int) ($stats['dead_letter']->count ?? 0),
            'avg_wait_time_seconds' => round((float) $avg_wait_time, 2),
        ];
    }

    /**
     * Get cache performance metrics.
     *
     * @return array Cache metrics.
     */
    private function get_cache_metrics(): array
    {
        $cache_table = $this->wpdb->prefix . 'formflow_cache';

        // Check if table exists
        $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$cache_table}'");
        if (!$table_exists) {
            return [
                'total_entries' => 0,
                'expired_entries' => 0,
                'cache_size_kb' => 0,
            ];
        }

        $total = (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$cache_table}");

        $expired = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$cache_table} WHERE expires_at < %s",
                current_time('mysql')
            )
        );

        $size = $this->wpdb->get_var(
            "SELECT ROUND(SUM(LENGTH(cache_value)) / 1024, 2) FROM {$cache_table}"
        );

        return [
            'total_entries' => $total,
            'expired_entries' => $expired,
            'active_entries' => $total - $expired,
            'cache_size_kb' => round((float) $size, 2),
        ];
    }

    /**
     * Get trend data for charts.
     *
     * @param string $date_from Start date.
     * @param string $date_to End date.
     * @param int $form_id Optional form filter.
     * @return array Trend data.
     */
    private function get_trend_data(string $date_from, string $date_to, int $form_id = 0): array
    {
        $table = $this->wpdb->prefix . 'formflow_submissions';
        $where = $this->build_where_clause($date_from, $date_to, $form_id);

        // Daily submissions
        $daily = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
             FROM {$table}
             WHERE {$where['sql']}
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            ...$where['values']
        ), ARRAY_A);

        // Hourly distribution
        $hourly = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count
             FROM {$table}
             WHERE {$where['sql']}
             GROUP BY HOUR(created_at)
             ORDER BY hour ASC",
            ...$where['values']
        ), ARRAY_A);

        // Day of week distribution
        $day_of_week = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT DAYOFWEEK(created_at) as day, COUNT(*) as count
             FROM {$table}
             WHERE {$where['sql']}
             GROUP BY DAYOFWEEK(created_at)
             ORDER BY day ASC",
            ...$where['values']
        ), ARRAY_A);

        return [
            'daily' => $daily,
            'hourly' => $this->fill_hourly_data($hourly),
            'day_of_week' => $this->fill_day_of_week_data($day_of_week),
        ];
    }

    /**
     * Get top performing forms.
     *
     * @param array $where WHERE clause data.
     * @return array Top forms.
     */
    private function get_top_forms(array $where): array
    {
        $submissions = $this->wpdb->prefix . 'formflow_submissions';
        $forms = $this->wpdb->prefix . 'formflow_forms';

        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT f.id, f.name,
                    COUNT(s.id) as total_submissions,
                    SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    ROUND(AVG(s.processing_time_ms), 2) as avg_processing_time
             FROM {$forms} f
             LEFT JOIN {$submissions} s ON f.id = s.form_id AND {$where['sql']}
             GROUP BY f.id, f.name
             HAVING total_submissions > 0
             ORDER BY total_submissions DESC
             LIMIT 10",
            ...$where['values']
        ), ARRAY_A);
    }

    /**
     * Get geographic distribution by IP.
     *
     * @param array $where WHERE clause data.
     * @return array Geographic data.
     */
    private function get_geographic_distribution(array $where): array
    {
        $table = $this->wpdb->prefix . 'formflow_submissions';

        // Group by first two octets for privacy
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
                SUBSTRING_INDEX(ip_address, '.', 2) as ip_prefix,
                COUNT(*) as count
             FROM {$table}
             WHERE ip_address IS NOT NULL AND ip_address != '' AND {$where['sql']}
             GROUP BY ip_prefix
             ORDER BY count DESC
             LIMIT 20",
            ...$where['values']
        ), ARRAY_A);
    }

    /**
     * Export analytics data to CSV.
     *
     * @param string $date_from Start date.
     * @param string $date_to End date.
     * @param int $form_id Optional form filter.
     * @return string CSV content.
     */
    public function export_to_csv(string $date_from, string $date_to, int $form_id = 0): string
    {
        $metrics = $this->get_dashboard_metrics($date_from, $date_to, $form_id);

        $csv = [];

        // Header
        $csv[] = ['FormFlow Pro Analytics Report'];
        $csv[] = ['Period', "{$date_from} to {$date_to}"];
        $csv[] = ['Generated', current_time('Y-m-d H:i:s')];
        $csv[] = [];

        // Overview
        $csv[] = ['=== Overview ==='];
        $csv[] = ['Metric', 'Value'];
        $csv[] = ['Total Submissions', $metrics['overview']['total']];
        $csv[] = ['Completed', $metrics['overview']['completed']];
        $csv[] = ['Pending', $metrics['overview']['pending']];
        $csv[] = ['Failed', $metrics['overview']['failed']];
        $csv[] = ['Conversion Rate', $metrics['overview']['conversion_rate'] . '%'];
        $csv[] = [];

        // Performance
        $csv[] = ['=== Performance ==='];
        $csv[] = ['Avg Processing Time (ms)', $metrics['performance']['avg_processing_time_ms']];
        $csv[] = ['Max Processing Time (ms)', $metrics['performance']['max_processing_time_ms']];
        $csv[] = ['P95 Processing Time (ms)', $metrics['performance']['p95_processing_time_ms']];
        $csv[] = [];

        // Autentique
        $csv[] = ['=== Digital Signatures ==='];
        $csv[] = ['Total Documents', $metrics['autentique']['total_documents']];
        $csv[] = ['Signed', $metrics['autentique']['signed']];
        $csv[] = ['Pending', $metrics['autentique']['pending']];
        $csv[] = ['Refused', $metrics['autentique']['refused']];
        $csv[] = ['Signature Rate', $metrics['autentique']['signature_rate'] . '%'];
        $csv[] = [];

        // Queue
        $csv[] = ['=== Queue System ==='];
        $csv[] = ['Pending Jobs', $metrics['queue']['pending']];
        $csv[] = ['Processing', $metrics['queue']['processing']];
        $csv[] = ['Completed', $metrics['queue']['completed']];
        $csv[] = ['Failed', $metrics['queue']['failed']];
        $csv[] = ['Dead Letter', $metrics['queue']['dead_letter']];
        $csv[] = [];

        // Top Forms
        $csv[] = ['=== Top Forms ==='];
        $csv[] = ['Form Name', 'Submissions', 'Completed', 'Avg Processing (ms)'];
        foreach ($metrics['top_forms'] as $form) {
            $csv[] = [
                $form['name'],
                $form['total_submissions'],
                $form['completed'],
                $form['avg_processing_time'],
            ];
        }

        // Convert to CSV string
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', array_map(function($cell) {
                return '"' . str_replace('"', '""', (string) $cell) . '"';
            }, $row)) . "\n";
        }

        return $output;
    }

    /**
     * Build WHERE clause for queries.
     *
     * @param string $date_from Start date.
     * @param string $date_to End date.
     * @param int $form_id Optional form filter.
     * @return array WHERE clause SQL and values.
     */
    private function build_where_clause(string $date_from, string $date_to, int $form_id = 0): array
    {
        $clauses = ['DATE(created_at) BETWEEN %s AND %s'];
        $values = [$date_from, $date_to];

        if ($form_id > 0) {
            $clauses[] = 'form_id = %d';
            $values[] = $form_id;
        }

        return [
            'sql' => implode(' AND ', $clauses),
            'values' => $values,
        ];
    }

    /**
     * Get previous period for comparison.
     *
     * @param string $date_from Current start date.
     * @param string $date_to Current end date.
     * @return array Previous period dates.
     */
    private function get_previous_period(string $date_from, string $date_to): array
    {
        $days = (strtotime($date_to) - strtotime($date_from)) / 86400;

        return [
            'from' => date('Y-m-d', strtotime("-" . ($days * 2 + 1) . " days", strtotime($date_to))),
            'to' => date('Y-m-d', strtotime("-" . ($days + 1) . " days", strtotime($date_to))),
        ];
    }

    /**
     * Fill missing hours with zeros.
     *
     * @param array $data Hourly data.
     * @return array Complete hourly data.
     */
    private function fill_hourly_data(array $data): array
    {
        $hourly = array_fill(0, 24, 0);
        foreach ($data as $row) {
            $hourly[(int) $row['hour']] = (int) $row['count'];
        }
        return $hourly;
    }

    /**
     * Fill missing days of week with zeros.
     *
     * @param array $data Day of week data.
     * @return array Complete day of week data.
     */
    private function fill_day_of_week_data(array $data): array
    {
        $days = array_fill(1, 7, 0); // Sunday = 1, Saturday = 7
        foreach ($data as $row) {
            $days[(int) $row['day']] = (int) $row['count'];
        }
        return array_values($days);
    }
}
