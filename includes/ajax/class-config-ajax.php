<?php

declare(strict_types=1);

/**
 * Configuration Export/Import AJAX Handlers
 *
 * Handles AJAX requests for configuration export and import.
 *
 * @package FormFlowPro\Ajax
 * @since 2.2.0
 */

namespace FormFlowPro\Ajax;

use FormFlowPro\Core\ConfigExporter;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Config AJAX Handler Class
 */
class Config_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_export_config', [__CLASS__, 'export_config']);
        add_action('wp_ajax_formflow_import_config', [__CLASS__, 'import_config']);
        add_action('wp_ajax_formflow_preview_import', [__CLASS__, 'preview_import']);
    }

    /**
     * Export configuration
     *
     * @return void
     */
    public static function export_config(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Get export options
        $options = [
            'include_forms' => isset($_POST['include_forms']) ? (bool) $_POST['include_forms'] : true,
            'include_templates' => isset($_POST['include_templates']) ? (bool) $_POST['include_templates'] : true,
            'include_settings' => isset($_POST['include_settings']) ? (bool) $_POST['include_settings'] : true,
            'include_webhooks' => isset($_POST['include_webhooks']) ? (bool) $_POST['include_webhooks'] : true,
            'form_ids' => !empty($_POST['form_ids']) ? array_map('sanitize_text_field', (array) $_POST['form_ids']) : [],
        ];

        require_once FORMFLOW_PATH . 'includes/Core/ConfigExporter.php';
        $exporter = new ConfigExporter();

        $export_data = $exporter->export($options);

        // Return JSON for download
        wp_send_json_success([
            'data' => $export_data,
            'filename' => ConfigExporter::generate_filename(),
        ]);
    }

    /**
     * Import configuration
     *
     * @return void
     */
    public static function import_config(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Get import data
        if (empty($_POST['import_data'])) {
            wp_send_json_error(['message' => __('No import data provided.', 'formflow-pro')], 400);
        }

        // Decode import data
        $import_data = json_decode(stripslashes($_POST['import_data']), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('Invalid JSON data.', 'formflow-pro'),
                'error' => json_last_error_msg(),
            ], 400);
        }

        // Get import options
        $options = [
            'overwrite_existing' => isset($_POST['overwrite_existing']) ? (bool) $_POST['overwrite_existing'] : false,
            'import_forms' => isset($_POST['import_forms']) ? (bool) $_POST['import_forms'] : true,
            'import_templates' => isset($_POST['import_templates']) ? (bool) $_POST['import_templates'] : true,
            'import_settings' => isset($_POST['import_settings']) ? (bool) $_POST['import_settings'] : true,
            'import_webhooks' => isset($_POST['import_webhooks']) ? (bool) $_POST['import_webhooks'] : true,
        ];

        require_once FORMFLOW_PATH . 'includes/Core/ConfigExporter.php';
        $exporter = new ConfigExporter();

        $results = $exporter->import($import_data, $options);

        if ($results['success']) {
            wp_send_json_success([
                'message' => __('Configuration imported successfully.', 'formflow-pro'),
                'imported' => $results['imported'],
                'skipped' => $results['skipped'],
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Import failed.', 'formflow-pro'),
                'errors' => $results['errors'],
            ], 400);
        }
    }

    /**
     * Preview import (show what will be imported)
     *
     * @return void
     */
    public static function preview_import(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Get import data
        if (empty($_POST['import_data'])) {
            wp_send_json_error(['message' => __('No import data provided.', 'formflow-pro')], 400);
        }

        // Decode import data
        $import_data = json_decode(stripslashes($_POST['import_data']), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error([
                'message' => __('Invalid JSON data.', 'formflow-pro'),
                'error' => json_last_error_msg(),
            ], 400);
        }

        // Build preview
        $preview = [
            'meta' => $import_data['meta'] ?? [],
            'summary' => [
                'forms' => 0,
                'templates' => 0,
                'settings' => 0,
                'webhooks' => 0,
            ],
            'items' => [],
        ];

        if (!empty($import_data['data']['forms'])) {
            $preview['summary']['forms'] = count($import_data['data']['forms']);
            $preview['items']['forms'] = array_map(function ($form) {
                return [
                    'id' => $form['id'],
                    'name' => $form['name'],
                    'elementor_form_id' => $form['elementor_form_id'],
                    'status' => $form['status'] ?? 'active',
                ];
            }, $import_data['data']['forms']);
        }

        if (!empty($import_data['data']['templates'])) {
            $preview['summary']['templates'] = count($import_data['data']['templates']);
            $preview['items']['templates'] = array_map(function ($template) {
                return [
                    'id' => $template['id'],
                    'name' => $template['name'],
                    'type' => $template['type'],
                ];
            }, $import_data['data']['templates']);
        }

        if (!empty($import_data['data']['settings']['plugin_settings'])) {
            $preview['summary']['settings'] = count($import_data['data']['settings']['plugin_settings']);
        }

        if (!empty($import_data['data']['webhooks'])) {
            $preview['summary']['webhooks'] = count($import_data['data']['webhooks']);
            $preview['items']['webhooks'] = array_map(function ($webhook) {
                return [
                    'name' => $webhook['name'],
                    'event' => $webhook['event'],
                    'url' => $webhook['url'],
                ];
            }, $import_data['data']['webhooks']);
        }

        wp_send_json_success($preview);
    }
}
