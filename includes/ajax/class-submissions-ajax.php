<?php

declare(strict_types=1);

/**
 * Submissions AJAX Handlers
 *
 * Handles all AJAX requests for Submissions management.
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
 * Submissions AJAX Handler Class
 */
class Submissions_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        // Submissions operations
        add_action('wp_ajax_formflow_get_submissions', [__CLASS__, 'get_submissions']);
        add_action('wp_ajax_formflow_get_submission', [__CLASS__, 'get_submission']);
        add_action('wp_ajax_formflow_delete_submission', [__CLASS__, 'delete_submission']);
        add_action('wp_ajax_formflow_bulk_delete_submissions', [__CLASS__, 'bulk_delete_submissions']);
        add_action('wp_ajax_formflow_export_submissions', [__CLASS__, 'export_submissions']);
    }

    /**
     * Get submissions (DataTables server-side processing)
     *
     * @return void
     */
    public static function get_submissions(): void
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
        $table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        // DataTables parameters
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $search_value = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
        $order_direction = isset($_POST['order'][0]['dir']) ? sanitize_text_field($_POST['order'][0]['dir']) : 'desc';

        // Filters
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Column mapping
        $columns = ['id', 'id', 'form_name', 'status', 'signature_status', 'ip_address', 'created_at'];
        $order_column = $columns[$order_column_index] ?? 'id';

        // Build WHERE clause
        $where_clauses = ['1=1'];
        $where_params = [];

        if ($form_id > 0) {
            $where_clauses[] = 's.form_id = %d';
            $where_params[] = $form_id;
        }

        if (!empty($status)) {
            $where_clauses[] = 's.status = %s';
            $where_params[] = $status;
        }

        if (!empty($date_from)) {
            $where_clauses[] = 'DATE(s.created_at) >= %s';
            $where_params[] = $date_from;
        }

        if (!empty($date_to)) {
            $where_clauses[] = 'DATE(s.created_at) <= %s';
            $where_params[] = $date_to;
        }

        if (!empty($search_value)) {
            $where_clauses[] = '(s.ip_address LIKE %s OR f.name LIKE %s OR s.form_data LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($search_value) . '%';
            $where_params[] = $search_param;
            $where_params[] = $search_param;
            $where_params[] = $search_param;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Get total records (without filtering)
        $total_query = "SELECT COUNT(*) FROM $table";
        $total_records = (int) $wpdb->get_var($total_query);

        // Get filtered records count
        $filtered_query = "SELECT COUNT(*) FROM $table s
                          LEFT JOIN $forms_table f ON s.form_id = f.id
                          WHERE $where_sql";

        if (!empty($where_params)) {
            $filtered_query = $wpdb->prepare($filtered_query, $where_params);
        }

        $filtered_records = (int) $wpdb->get_var($filtered_query);

        // Get data
        $data_query = "SELECT s.*, f.name as form_name
                      FROM $table s
                      LEFT JOIN $forms_table f ON s.form_id = f.id
                      WHERE $where_sql
                      ORDER BY $order_column $order_direction
                      LIMIT %d OFFSET %d";

        $query_params = array_merge($where_params, [$length, $start]);
        $data_query = $wpdb->prepare($data_query, $query_params);

        $submissions = $wpdb->get_results($data_query, ARRAY_A);

        // Format data for DataTables
        $data = [];
        foreach ($submissions as $submission) {
            $data[] = [
                'id' => $submission['id'],
                'form_name' => $submission['form_name'] ?? 'N/A',
                'status' => $submission['status'],
                'signature_status' => $submission['signature_status'] ?? null,
                'ip_address' => $submission['ip_address'],
                'created_at' => $submission['created_at'],
            ];
        }

        wp_send_json([
            'draw' => $draw,
            'recordsTotal' => $total_records,
            'recordsFiltered' => $filtered_records,
            'data' => $data,
        ]);
    }

    /**
     * Get single submission
     *
     * @return void
     */
    public static function get_submission(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if (!$submission_id) {
            wp_send_json_error(['message' => __('Submission ID is required.', 'formflow-pro')], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, f.name as form_name
             FROM $table s
             LEFT JOIN $forms_table f ON s.form_id = f.id
             WHERE s.id = %d",
            $submission_id
        ), ARRAY_A);

        if (!$submission) {
            wp_send_json_error(['message' => __('Submission not found.', 'formflow-pro')], 404);
        }

        wp_send_json_success($submission);
    }

    /**
     * Delete single submission
     *
     * @return void
     */
    public static function delete_submission(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;

        if (!$submission_id) {
            wp_send_json_error(['message' => __('Submission ID is required.', 'formflow-pro')], 400);
        }

        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . 'formflow_submissions',
            ['id' => $submission_id],
            ['%d']
        );

        if (!$result) {
            wp_send_json_error([
                'message' => __('Failed to delete submission.', 'formflow-pro'),
            ], 500);
        }

        wp_send_json_success([
            'message' => __('Submission deleted successfully.', 'formflow-pro'),
        ]);
    }

    /**
     * Bulk delete submissions
     *
     * @return void
     */
    public static function bulk_delete_submissions(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : [];

        if (empty($submission_ids)) {
            wp_send_json_error([
                'message' => __('No submissions selected.', 'formflow-pro'),
            ], 400);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));

        $query = "DELETE FROM $table WHERE id IN ($placeholders)";
        $result = $wpdb->query($wpdb->prepare($query, $submission_ids));

        if ($result === false) {
            wp_send_json_error([
                'message' => __('Failed to delete submissions.', 'formflow-pro'),
            ], 500);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('%d submission(s) deleted successfully.', 'formflow-pro'),
                $result
            ),
            'deleted_count' => $result,
        ]);
    }

    /**
     * Export submissions to CSV
     *
     * @return void
     */
    public static function export_submissions(): void
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
        $table = $wpdb->prefix . 'formflow_submissions';
        $forms_table = $wpdb->prefix . 'formflow_forms';

        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'all';

        // Build query based on export type
        if ($export_type === 'single') {
            $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
            $query = $wpdb->prepare(
                "SELECT s.*, f.name as form_name
                 FROM $table s
                 LEFT JOIN $forms_table f ON s.form_id = f.id
                 WHERE s.id = %d",
                $submission_id
            );
            $submissions = $wpdb->get_results($query, ARRAY_A);
        } elseif ($export_type === 'selected') {
            $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : [];
            if (empty($submission_ids)) {
                wp_die(__('No submissions selected.', 'formflow-pro'), 400);
            }
            $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
            $query = "SELECT s.*, f.name as form_name
                     FROM $table s
                     LEFT JOIN $forms_table f ON s.form_id = f.id
                     WHERE s.id IN ($placeholders)";
            $submissions = $wpdb->get_results($wpdb->prepare($query, $submission_ids), ARRAY_A);
        } else {
            // Export all with filters
            $where_clauses = ['1=1'];
            $where_params = [];

            $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
            $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
            $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
            $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

            if ($form_id > 0) {
                $where_clauses[] = 's.form_id = %d';
                $where_params[] = $form_id;
            }

            if (!empty($status)) {
                $where_clauses[] = 's.status = %s';
                $where_params[] = $status;
            }

            if (!empty($date_from)) {
                $where_clauses[] = 'DATE(s.created_at) >= %s';
                $where_params[] = $date_from;
            }

            if (!empty($date_to)) {
                $where_clauses[] = 'DATE(s.created_at) <= %s';
                $where_params[] = $date_to;
            }

            $where_sql = implode(' AND ', $where_clauses);
            $query = "SELECT s.*, f.name as form_name
                     FROM $table s
                     LEFT JOIN $forms_table f ON s.form_id = f.id
                     WHERE $where_sql";

            if (!empty($where_params)) {
                $query = $wpdb->prepare($query, $where_params);
            }

            $submissions = $wpdb->get_results($query, ARRAY_A);
        }

        if (empty($submissions)) {
            wp_die(__('No submissions found to export.', 'formflow-pro'), 404);
        }

        // Generate CSV
        $filename = 'formflow-submissions-' . date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write UTF-8 BOM
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        $headers = [
            'ID',
            'Form',
            'Status',
            'Signature Status',
            'IP Address',
            'User Agent',
            'Created At',
            'Form Data',
            'Metadata',
        ];

        fputcsv($output, $headers);

        // Write data rows
        foreach ($submissions as $submission) {
            $row = [
                $submission['id'],
                $submission['form_name'] ?? 'N/A',
                $submission['status'],
                $submission['signature_status'] ?? 'N/A',
                $submission['ip_address'],
                $submission['user_agent'],
                $submission['created_at'],
                $submission['form_data'],
                $submission['metadata'] ?? '',
            ];

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
