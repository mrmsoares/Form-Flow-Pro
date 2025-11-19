<?php

declare(strict_types=1);

/**
 * Dashboard AJAX Handlers
 *
 * Handles all AJAX requests for Dashboard.
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
 * Dashboard AJAX Handler Class
 */
class Dashboard_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_get_dashboard_stats', [__CLASS__, 'get_dashboard_stats']);
        add_action('wp_ajax_formflow_get_recent_submissions', [__CLASS__, 'get_recent_submissions']);
        add_action('wp_ajax_formflow_get_chart_data', [__CLASS__, 'get_chart_data']);
    }

    /**
     * Get dashboard statistics
     *
     * @return void
     */
    public static function get_dashboard_stats(): void
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

        // Total submissions
        $total_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table"
        );

        // Total submissions today
        $submissions_today = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE DATE(created_at) = CURDATE()"
        );

        // Total submissions this month
        $submissions_month = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE YEAR(created_at) = YEAR(CURDATE())
             AND MONTH(created_at) = MONTH(CURDATE())"
        );

        // Completed submissions
        $completed_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE status = 'completed'"
        );

        // Pending submissions
        $pending_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE status = 'pending'"
        );

        // Pending signature
        $pending_signature = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE status = 'pending_signature'"
        );

        // Failed submissions
        $failed_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE status = 'failed'"
        );

        // Total active forms
        $active_forms = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $forms_table
             WHERE status = 'active'"
        );

        // Conversion rate
        $conversion_rate = $total_submissions > 0
            ? ($completed_submissions / $total_submissions) * 100
            : 0;

        // Growth rate (comparing this month vs last month)
        $last_month_submissions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $submissions_table
             WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
             AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
        );

        $growth_rate = $last_month_submissions > 0
            ? (($submissions_month - $last_month_submissions) / $last_month_submissions) * 100
            : 0;

        wp_send_json_success([
            'total_submissions' => $total_submissions,
            'submissions_today' => $submissions_today,
            'submissions_month' => $submissions_month,
            'completed_submissions' => $completed_submissions,
            'pending_submissions' => $pending_submissions,
            'pending_signature' => $pending_signature,
            'failed_submissions' => $failed_submissions,
            'active_forms' => $active_forms,
            'conversion_rate' => round($conversion_rate, 2),
            'growth_rate' => round($growth_rate, 2),
        ]);
    }

    /**
     * Get recent submissions
     *
     * @return void
     */
    public static function get_recent_submissions(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;

        global $wpdb;
        $submissions_table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        $recent_submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, f.name as form_name
             FROM $submissions_table s
             LEFT JOIN $forms_table f ON s.form_id = f.id
             ORDER BY s.created_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        wp_send_json_success([
            'submissions' => $recent_submissions,
        ]);
    }

    /**
     * Get chart data
     *
     * @return void
     */
    public static function get_chart_data(): void
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

        $chart_type = isset($_POST['chart_type']) ? sanitize_text_field($_POST['chart_type']) : 'submissions';
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30days';

        $date_where = self::get_date_where_clause($period);

        switch ($chart_type) {
            case 'submissions':
                // Submissions over time
                $data = $wpdb->get_results(
                    "SELECT DATE(created_at) as label, COUNT(*) as value
                     FROM $submissions_table
                     WHERE $date_where
                     GROUP BY DATE(created_at)
                     ORDER BY label ASC"
                );
                break;

            case 'status':
                // Submissions by status
                $data = $wpdb->get_results(
                    "SELECT status as label, COUNT(*) as value
                     FROM $submissions_table
                     WHERE $date_where
                     GROUP BY status"
                );
                break;

            case 'forms':
                // Submissions by form
                $data = $wpdb->get_results(
                    "SELECT f.name as label, COUNT(s.id) as value
                     FROM $submissions_table s
                     LEFT JOIN $forms_table f ON s.form_id = f.id
                     WHERE $date_where
                     GROUP BY s.form_id
                     ORDER BY value DESC
                     LIMIT 10"
                );
                break;

            case 'hourly':
                // Submissions by hour of day
                $data = $wpdb->get_results(
                    "SELECT HOUR(created_at) as label, COUNT(*) as value
                     FROM $submissions_table
                     WHERE $date_where
                     GROUP BY HOUR(created_at)
                     ORDER BY label ASC"
                );
                break;

            default:
                wp_send_json_error(['message' => __('Invalid chart type.', 'formflow-pro')], 400);
                return;
        }

        wp_send_json_success([
            'data' => $data,
            'chart_type' => $chart_type,
            'period' => $period,
        ]);
    }

    /**
     * Get date WHERE clause based on period
     *
     * @param string $period Date period.
     * @return string SQL WHERE clause.
     */
    private static function get_date_where_clause(string $period): string
    {
        switch ($period) {
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
