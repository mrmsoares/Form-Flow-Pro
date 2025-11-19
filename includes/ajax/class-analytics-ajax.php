<?php

declare(strict_types=1);

/**
 * Analytics AJAX Handlers
 *
 * Handles all AJAX requests for Analytics.
 *
 * @package FormFlowPro\Ajax
 * @since 2.0.0
 */

namespace FormFlowPro\Ajax;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Analytics AJAX Handler Class
 */
class Analytics_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_get_analytics_data', [__CLASS__, 'get_analytics_data']);
        add_action('wp_ajax_formflow_export_analytics', [__CLASS__, 'export_analytics']);
    }

    /**
     * Get analytics data
     *
     * @return void
     */
    public static function get_analytics_data(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        global $wpdb;
        $submissions_table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '30days';

        // Calculate date range
        $date_where = self::get_date_where_clause($date_range);

        // Get submissions over time
        $submissions_over_time = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM $submissions_table
             WHERE $date_where
             GROUP BY DATE(created_at)
             ORDER BY date ASC"
        );

        // Get submissions by status
        $submissions_by_status = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM $submissions_table
             WHERE $date_where
             GROUP BY status"
        );

        // Get top forms
        $top_forms = $wpdb->get_results(
            "SELECT f.name, COUNT(s.id) as submissions
             FROM $submissions_table s
             LEFT JOIN $forms_table f ON s.form_id = f.id
             WHERE $date_where
             GROUP BY s.form_id
             ORDER BY submissions DESC
             LIMIT 10"
        );

        // Get conversion rate (completed / total)
        $total_submissions = $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table WHERE $date_where"
        );

        $completed_submissions = $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE status = 'completed' AND $date_where"
        );

        $conversion_rate = $total_submissions > 0
            ? ($completed_submissions / $total_submissions) * 100
            : 0;

        wp_send_json_success([
            'submissions_over_time' => $submissions_over_time,
            'submissions_by_status' => $submissions_by_status,
            'top_forms' => $top_forms,
            'conversion_rate' => round($conversion_rate, 2),
            'total_submissions' => (int) $total_submissions,
            'completed_submissions' => (int) $completed_submissions,
        ]);
    }

    /**
     * Export analytics report
     *
     * @return void
     */
    public static function export_analytics(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_die(__('Security check failed.', 'formflow-pro'), 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'formflow-pro'), 403);
        }

        global $wpdb;
        $submissions_table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        $date_range = isset($_POST['date_range']) ? sanitize_text_field($_POST['date_range']) : '30days';
        $date_where = self::get_date_where_clause($date_range);

        // Get comprehensive analytics data
        $total_submissions = $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table WHERE $date_where"
        );

        $status_breakdown = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM $submissions_table
             WHERE $date_where
             GROUP BY status",
            ARRAY_A
        );

        $form_breakdown = $wpdb->get_results(
            "SELECT f.name as form_name, COUNT(s.id) as submissions
             FROM $submissions_table s
             LEFT JOIN $forms_table f ON s.form_id = f.id
             WHERE $date_where
             GROUP BY s.form_id
             ORDER BY submissions DESC",
            ARRAY_A
        );

        $daily_submissions = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM $submissions_table
             WHERE $date_where
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            ARRAY_A
        );

        // Generate CSV
        $filename = 'formflow-analytics-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Summary Section
        fputcsv($output, ['FORMFLOW PRO - ANALYTICS REPORT']);
        fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
        fputcsv($output, ['Date Range', ucfirst(str_replace('days', ' Days', $date_range))]);
        fputcsv($output, ['Total Submissions', $total_submissions]);
        fputcsv($output, []);

        // Status Breakdown
        fputcsv($output, ['SUBMISSIONS BY STATUS']);
        fputcsv($output, ['Status', 'Count', 'Percentage']);
        foreach ($status_breakdown as $row) {
            $percentage = $total_submissions > 0 ? ($row['count'] / $total_submissions) * 100 : 0;
            fputcsv($output, [
                ucfirst($row['status']),
                $row['count'],
                round($percentage, 2) . '%',
            ]);
        }
        fputcsv($output, []);

        // Form Breakdown
        fputcsv($output, ['SUBMISSIONS BY FORM']);
        fputcsv($output, ['Form Name', 'Submissions']);
        foreach ($form_breakdown as $row) {
            fputcsv($output, [
                $row['form_name'] ?? 'Unknown',
                $row['submissions'],
            ]);
        }
        fputcsv($output, []);

        // Daily Breakdown
        fputcsv($output, ['DAILY SUBMISSIONS']);
        fputcsv($output, ['Date', 'Count']);
        foreach ($daily_submissions as $row) {
            fputcsv($output, [
                $row['date'],
                $row['count'],
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Get date WHERE clause based on range
     *
     * @param string $range Date range.
     * @return string SQL WHERE clause.
     */
    private static function get_date_where_clause(string $range): string
    {
        switch ($range) {
            case '7days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90days':
                return "created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            case 'year':
                return "YEAR(created_at) = YEAR(NOW())";
            case 'all':
            default:
                return "1=1";
        }
    }
}
