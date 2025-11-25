<?php

declare(strict_types=1);

/**
 * AI AJAX Handlers
 *
 * Handles AJAX requests for AI features.
 *
 * @package FormFlowPro\Ajax
 * @since 2.3.0
 */

namespace FormFlowPro\Ajax;

use FormFlowPro\AI\AIService;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI AJAX Handler Class
 */
class AI_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_get_ai_settings', [__CLASS__, 'get_settings']);
        add_action('wp_ajax_formflow_save_ai_settings', [__CLASS__, 'save_settings']);
        add_action('wp_ajax_formflow_test_ai_connection', [__CLASS__, 'test_connection']);
        add_action('wp_ajax_formflow_ai_analyze_spam', [__CLASS__, 'analyze_spam']);
        add_action('wp_ajax_formflow_ai_classify', [__CLASS__, 'classify_content']);
        add_action('wp_ajax_formflow_ai_sentiment', [__CLASS__, 'analyze_sentiment']);
        add_action('wp_ajax_formflow_ai_suggestions', [__CLASS__, 'get_suggestions']);
        add_action('wp_ajax_formflow_ai_get_models', [__CLASS__, 'get_models']);
        add_action('wp_ajax_formflow_ai_get_usage', [__CLASS__, 'get_usage']);

        // Public AJAX (for frontend features)
        add_action('wp_ajax_nopriv_formflow_ai_validate_field', [__CLASS__, 'validate_field']);
        add_action('wp_ajax_formflow_ai_validate_field', [__CLASS__, 'validate_field']);
    }

    /**
     * Get AI settings
     *
     * @return void
     */
    public static function get_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $config = $aiService->getConfig();

        // Mask API key
        if (!empty($config['api_key'])) {
            $config['api_key'] = substr($config['api_key'], 0, 8) . '...' . substr($config['api_key'], -4);
            $config['api_key_set'] = true;
        } else {
            $config['api_key_set'] = false;
        }

        wp_send_json_success($config);
    }

    /**
     * Save AI settings
     *
     * @return void
     */
    public static function save_settings(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $config = isset($_POST['config']) ? (array) $_POST['config'] : [];

        // If API key is masked, keep existing
        if (!empty($config['api_key']) && strpos($config['api_key'], '...') !== false) {
            $existing = get_option('formflow_ai_settings', []);
            $config['api_key'] = $existing['api_key'] ?? '';
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $result = $aiService->saveConfig($config);

        if ($result) {
            wp_send_json_success([
                'message' => __('AI settings saved successfully.', 'formflow-pro'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save settings.', 'formflow-pro')], 500);
        }
    }

    /**
     * Test AI connection
     *
     * @return void
     */
    public static function test_connection(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $result = $aiService->testConnection();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, 400);
        }
    }

    /**
     * Analyze content for spam
     *
     * @return void
     */
    public static function analyze_spam(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $content = isset($_POST['content']) ? (array) $_POST['content'] : [];

        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $score = $aiService->analyzeForSpam(['form_data' => $content]);
        $threshold = $aiService->getConfig()['spam_threshold'] ?? 0.7;

        wp_send_json_success([
            'score' => round($score, 2),
            'is_spam' => $score >= $threshold,
            'threshold' => $threshold,
            'confidence' => $score >= 0.8 || $score <= 0.2 ? 'high' : 'medium',
        ]);
    }

    /**
     * Classify content
     *
     * @return void
     */
    public static function classify_content(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $content = sanitize_textarea_field($_POST['content'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => __('No content provided.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $classification = $aiService->classifyContent($content);

        if ($classification) {
            wp_send_json_success($classification);
        } else {
            wp_send_json_error(['message' => __('Classification failed.', 'formflow-pro')], 500);
        }
    }

    /**
     * Analyze sentiment
     *
     * @return void
     */
    public static function analyze_sentiment(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $text = sanitize_textarea_field($_POST['text'] ?? '');

        if (empty($text)) {
            wp_send_json_error(['message' => __('No text provided.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $analysis = $aiService->analyzeSentiment($text);

        if ($analysis) {
            wp_send_json_success($analysis);
        } else {
            wp_send_json_error(['message' => __('Sentiment analysis failed.', 'formflow-pro')], 500);
        }
    }

    /**
     * Get auto-suggestions
     *
     * @return void
     */
    public static function get_suggestions(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        $fieldName = sanitize_text_field($_POST['field_name'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if (empty($fieldName)) {
            wp_send_json_error(['message' => __('Field name required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        $suggestions = $aiService->getAutoSuggestions($fieldName, $value);

        wp_send_json_success(['suggestions' => $suggestions]);
    }

    /**
     * Get available AI models
     *
     * @return void
     */
    public static function get_models(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIProviderInterface.php';
        require_once FORMFLOW_PATH . 'includes/AI/OpenAIProvider.php';

        $provider = new \FormFlowPro\AI\OpenAIProvider('', '');
        $models = $provider->getModels();

        wp_send_json_success($models);
    }

    /**
     * Get AI usage statistics
     *
     * @return void
     */
    public static function get_usage(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Get usage from transient
        $rateCount = (int) get_transient('formflow_ai_rate_count');
        $config = get_option('formflow_ai_settings', []);

        wp_send_json_success([
            'requests_this_hour' => $rateCount,
            'rate_limit' => (int) ($config['rate_limit'] ?? 100),
            'remaining' => max(0, (int) ($config['rate_limit'] ?? 100) - $rateCount),
        ]);
    }

    /**
     * Validate field (public endpoint)
     *
     * @return void
     */
    public static function validate_field(): void
    {
        // Rate limit for public endpoint
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rateKey = 'formflow_ai_rate_' . md5($ip);
        $count = (int) get_transient($rateKey);

        if ($count > 20) { // 20 requests per minute per IP
            wp_send_json_error(['message' => __('Rate limit exceeded.', 'formflow-pro')], 429);
        }

        set_transient($rateKey, $count + 1, 60);

        // Get field data
        $fieldName = sanitize_text_field($_POST['field_name'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        if (empty($fieldName)) {
            wp_send_json_error(['message' => __('Field name required.', 'formflow-pro')], 400);
        }

        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';
        $aiService = AIService::getInstance();

        // Use smart validation
        $errors = $aiService->smartValidation([], $fieldName, $value);

        if (empty($errors)) {
            wp_send_json_success(['valid' => true]);
        } else {
            wp_send_json_success([
                'valid' => false,
                'errors' => $errors,
            ]);
        }
    }
}
