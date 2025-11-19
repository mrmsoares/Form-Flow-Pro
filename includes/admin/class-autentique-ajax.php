<?php

/**
 * Autentique AJAX Handlers
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Autentique AJAX Handler
 */
class Autentique_Ajax
{
    /**
     * Initialize AJAX handlers
     */
    public static function init()
    {
        add_action('wp_ajax_formflow_get_autentique_documents', [__CLASS__, 'get_documents']);
        add_action('wp_ajax_formflow_get_document_details', [__CLASS__, 'get_document_details']);
        add_action('wp_ajax_formflow_resend_signature_link', [__CLASS__, 'resend_signature_link']);
        add_action('wp_ajax_formflow_delete_document', [__CLASS__, 'delete_document']);
    }

    /**
     * Get documents for DataTable
     */
    public static function get_documents()
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        global $wpdb;

        // DataTables parameters
        $draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
        $start = isset($_POST['start']) ? intval($_POST['start']) : 0;
        $length = isset($_POST['length']) ? intval($_POST['length']) : 25;
        $search = isset($_POST['search']['value']) ? sanitize_text_field($_POST['search']['value']) : '';
        $order_column_index = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 5;
        $order_dir = isset($_POST['order'][0]['dir']) ? sanitize_text_field($_POST['order'][0]['dir']) : 'desc';

        // Filters
        $status_filter = isset($_POST['status_filter']) ? sanitize_text_field($_POST['status_filter']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        // Column mapping
        $columns = [
            0 => 'document_id',
            1 => 'document_name',
            2 => 'submission_id',
            3 => 'signer_email',
            4 => 'status',
            5 => 'created_at',
            6 => 'signed_at',
        ];

        $order_column = $columns[$order_column_index] ?? 'created_at';

        // Build query
        $where = ['1=1'];
        $where_values = [];

        if (!empty($status_filter)) {
            $where[] = 'status = %s';
            $where_values[] = $status_filter;
        }

        if (!empty($date_from)) {
            $where[] = 'created_at >= %s';
            $where_values[] = $date_from . ' 00:00:00';
        }

        if (!empty($date_to)) {
            $where[] = 'created_at <= %s';
            $where_values[] = $date_to . ' 23:59:59';
        }

        if (!empty($search)) {
            $where[] = '(document_name LIKE %s OR signer_email LIKE %s OR document_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);

        // Get total records
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents";
        $total_records = $wpdb->get_var($total_query);

        // Get filtered records count
        $filtered_query = "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE $where_clause";
        if (!empty($where_values)) {
            $filtered_query = $wpdb->prepare($filtered_query, $where_values);
        }
        $filtered_records = $wpdb->get_var($filtered_query);

        // Get data
        $data_query = "SELECT * FROM {$wpdb->prefix}formflow_autentique_documents
                      WHERE $where_clause
                      ORDER BY $order_column $order_dir
                      LIMIT %d OFFSET %d";

        $query_values = array_merge($where_values, [$length, $start]);
        $data_query = $wpdb->prepare($data_query, $query_values);
        $documents = $wpdb->get_results($data_query, ARRAY_A);

        // Format data for DataTables
        $data = [];
        foreach ($documents as $doc) {
            $data[] = [
                'document_id' => $doc['document_id'],
                'document_name' => $doc['document_name'],
                'submission_id' => $doc['submission_id'],
                'signer_email' => $doc['signer_email'],
                'signer_name' => $doc['signer_name'],
                'status' => $doc['status'],
                'signature_url' => $doc['signature_url'],
                'created_at' => $doc['created_at'],
                'signed_at' => $doc['signed_at'],
            ];
        }

        wp_send_json([
            'draw' => $draw,
            'recordsTotal' => intval($total_records),
            'recordsFiltered' => intval($filtered_records),
            'data' => $data,
        ]);
    }

    /**
     * Get document details
     */
    public static function get_document_details()
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $document_id = isset($_POST['document_id']) ? sanitize_text_field($_POST['document_id']) : '';

        if (empty($document_id)) {
            wp_send_json_error(['message' => 'Document ID is required']);
        }

        global $wpdb;
        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_autentique_documents WHERE document_id = %s",
            $document_id
        ), ARRAY_A);

        if (!$document) {
            wp_send_json_error(['message' => 'Document not found']);
        }

        wp_send_json_success($document);
    }

    /**
     * Resend signature link
     */
    public static function resend_signature_link()
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $document_id = isset($_POST['document_id']) ? sanitize_text_field($_POST['document_id']) : '';

        if (empty($document_id)) {
            wp_send_json_error(['message' => 'Document ID is required']);
        }

        try {
            require_once FORMFLOW_PATH . 'includes/autentique/class-autentique-service.php';
            $autentique = new \FormFlowPro\Autentique\Autentique_Service();

            $result = $autentique->resend_signature_link($document_id);

            if ($result) {
                wp_send_json_success(['message' => 'Signature link resent successfully']);
            } else {
                wp_send_json_error(['message' => 'Failed to resend signature link']);
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Delete document
     */
    public static function delete_document()
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        $document_id = isset($_POST['document_id']) ? sanitize_text_field($_POST['document_id']) : '';

        if (empty($document_id)) {
            wp_send_json_error(['message' => 'Document ID is required']);
        }

        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'formflow_autentique_documents',
            ['document_id' => $document_id],
            ['%s']
        );

        if ($result) {
            wp_send_json_success(['message' => 'Document deleted successfully']);
        } else {
            wp_send_json_error(['message' => 'Failed to delete document']);
        }
    }
}
