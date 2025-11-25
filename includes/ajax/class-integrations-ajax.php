<?php

declare(strict_types=1);

/**
 * Integrations AJAX Handlers
 *
 * Handles AJAX requests for integration management.
 *
 * @package FormFlowPro\Ajax
 * @since 2.3.0
 */

namespace FormFlowPro\Ajax;

use FormFlowPro\Integrations\IntegrationManager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integrations AJAX Handler Class
 */
class Integrations_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_get_integrations', [__CLASS__, 'get_integrations']);
        add_action('wp_ajax_formflow_get_integration', [__CLASS__, 'get_integration']);
        add_action('wp_ajax_formflow_save_integration', [__CLASS__, 'save_integration']);
        add_action('wp_ajax_formflow_test_integration', [__CLASS__, 'test_integration']);
        add_action('wp_ajax_formflow_get_integration_fields', [__CLASS__, 'get_integration_fields']);
        add_action('wp_ajax_formflow_save_form_mapping', [__CLASS__, 'save_form_mapping']);
        add_action('wp_ajax_formflow_get_form_mapping', [__CLASS__, 'get_form_mapping']);
        add_action('wp_ajax_formflow_get_sync_stats', [__CLASS__, 'get_sync_stats']);
        add_action('wp_ajax_formflow_get_sync_history', [__CLASS__, 'get_sync_history']);
        add_action('wp_ajax_formflow_retry_sync', [__CLASS__, 'retry_sync']);
    }

    /**
     * Get all integrations
     *
     * @return void
     */
    public static function get_integrations(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        wp_send_json_success($manager->getIntegrationsList());
    }

    /**
     * Get single integration details
     *
     * @return void
     */
    public static function get_integration(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');

        if (empty($integrationId)) {
            wp_send_json_error(['message' => __('Integration ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();
        $integration = $manager->get($integrationId);

        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found.', 'formflow-pro')], 404);
        }

        wp_send_json_success([
            'id' => $integration->getId(),
            'name' => $integration->getName(),
            'description' => $integration->getDescription(),
            'icon' => $integration->getIcon(),
            'configured' => $integration->isConfigured(),
            'enabled' => $integration->isEnabled(),
            'config_fields' => $integration->getConfigFields(),
        ]);
    }

    /**
     * Save integration configuration
     *
     * @return void
     */
    public static function save_integration(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');
        $config = isset($_POST['config']) ? (array) $_POST['config'] : [];

        if (empty($integrationId)) {
            wp_send_json_error(['message' => __('Integration ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();
        $integration = $manager->get($integrationId);

        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found.', 'formflow-pro')], 404);
        }

        $result = $integration->saveConfig($config);

        if ($result) {
            wp_send_json_success([
                'message' => __('Configuration saved successfully.', 'formflow-pro'),
                'configured' => $integration->isConfigured(),
                'enabled' => $integration->isEnabled(),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save configuration.', 'formflow-pro')], 500);
        }
    }

    /**
     * Test integration connection
     *
     * @return void
     */
    public static function test_integration(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');

        if (empty($integrationId)) {
            wp_send_json_error(['message' => __('Integration ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();
        $integration = $manager->get($integrationId);

        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found.', 'formflow-pro')], 404);
        }

        $result = $integration->testConnection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, 400);
        }
    }

    /**
     * Get available fields from integration
     *
     * @return void
     */
    public static function get_integration_fields(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');

        if (empty($integrationId)) {
            wp_send_json_error(['message' => __('Integration ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();
        $integration = $manager->get($integrationId);

        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found.', 'formflow-pro')], 404);
        }

        wp_send_json_success($integration->getAvailableFields());
    }

    /**
     * Save form field mapping
     *
     * @return void
     */
    public static function save_form_mapping(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $formId = (int) ($_POST['form_id'] ?? 0);
        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');
        $mapping = isset($_POST['mapping']) ? (array) $_POST['mapping'] : [];

        if (empty($formId) || empty($integrationId)) {
            wp_send_json_error(['message' => __('Form ID and Integration ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        // Sanitize mapping
        $sanitizedMapping = [];
        foreach ($mapping as $key => $value) {
            $sanitizedMapping[sanitize_text_field($key)] = sanitize_text_field($value);
        }

        $result = $manager->saveFormMapping($formId, $integrationId, $sanitizedMapping);

        if ($result) {
            wp_send_json_success(['message' => __('Mapping saved successfully.', 'formflow-pro')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save mapping.', 'formflow-pro')], 500);
        }
    }

    /**
     * Get form field mapping
     *
     * @return void
     */
    public static function get_form_mapping(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $formId = (int) ($_POST['form_id'] ?? 0);

        if (empty($formId)) {
            wp_send_json_error(['message' => __('Form ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        wp_send_json_success($manager->getFormMappings($formId));
    }

    /**
     * Get sync statistics
     *
     * @return void
     */
    public static function get_sync_stats(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');
        $period = sanitize_text_field($_POST['period'] ?? 'all');

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        $stats = $manager->getSyncStats($integrationId ?: null, $period);

        wp_send_json_success($stats);
    }

    /**
     * Get sync history for submission
     *
     * @return void
     */
    public static function get_sync_history(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $submissionId = (int) ($_POST['submission_id'] ?? 0);

        if (empty($submissionId)) {
            wp_send_json_error(['message' => __('Submission ID required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        wp_send_json_success($manager->getSyncHistory($submissionId));
    }

    /**
     * Retry failed sync
     *
     * @return void
     */
    public static function retry_sync(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $integrationId = sanitize_text_field($_POST['integration_id'] ?? '');

        if (empty($submissionId) || empty($integrationId)) {
            wp_send_json_error(['message' => __('Submission ID and Integration ID required.', 'formflow-pro')], 400);
        }

        // Get submission data
        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $submissionId
        ), ARRAY_A);

        if (!$submission) {
            wp_send_json_error(['message' => __('Submission not found.', 'formflow-pro')], 404);
        }

        // Decode form data
        $submission['form_data'] = json_decode($submission['form_data'] ?? '{}', true);

        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        $manager = IntegrationManager::getInstance();

        $integration = $manager->get($integrationId);
        if (!$integration) {
            wp_send_json_error(['message' => __('Integration not found.', 'formflow-pro')], 404);
        }

        // Get mapping
        $mappings = $manager->getFormMappings($submission['form_id'] ?? 0);
        $mapping = $mappings[$integrationId] ?? [];

        // Retry sync
        $result = $integration->sendSubmission($submission, $mapping);

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Sync completed successfully.', 'formflow-pro'),
                'result' => $result,
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'] ?? __('Sync failed.', 'formflow-pro'),
            ], 400);
        }
    }
}
