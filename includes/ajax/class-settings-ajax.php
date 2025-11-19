<?php

declare(strict_types=1);

/**
 * Settings AJAX Handlers
 *
 * Handles all AJAX requests for Settings page.
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
 * Settings AJAX Handler Class
 */
class Settings_Ajax
{
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public static function init(): void
    {
        add_action('wp_ajax_formflow_test_api_connection', [__CLASS__, 'test_api_connection']);
        add_action('wp_ajax_formflow_check_cache_driver', [__CLASS__, 'check_cache_driver']);
        add_action('wp_ajax_formflow_clear_cache', [__CLASS__, 'clear_cache']);
        add_action('wp_ajax_formflow_test_email', [__CLASS__, 'test_email']);
    }

    /**
     * Test Autentique API connection
     *
     * @return void
     */
    public static function test_api_connection(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('API key is required.', 'formflow-pro'),
            ], 400);
        }

        // Test API connection
        $response = wp_remote_get('https://api.autentique.com.br/v2/graphql', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'query' => '{
                    __schema {
                        types {
                            name
                        }
                    }
                }',
            ]),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error([
                'message' => sprintf(
                    __('Connection error: %s', 'formflow-pro'),
                    $response->get_error_message()
                ),
            ], 500);
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 200) {
            wp_send_json_success([
                'message' => __('API connection successful! Your API key is valid.', 'formflow-pro'),
            ]);
        } elseif ($status_code === 401) {
            wp_send_json_error([
                'message' => __('Invalid API key. Please check your credentials.', 'formflow-pro'),
            ], 401);
        } else {
            $body = wp_remote_retrieve_body($response);
            wp_send_json_error([
                'message' => sprintf(
                    __('API returned error code %d: %s', 'formflow-pro'),
                    $status_code,
                    $body
                ),
            ], $status_code);
        }
    }

    /**
     * Check if cache driver is available
     *
     * @return void
     */
    public static function check_cache_driver(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $driver = isset($_POST['driver']) ? sanitize_text_field($_POST['driver']) : '';

        if (empty($driver)) {
            wp_send_json_error(['message' => __('Driver is required.', 'formflow-pro')], 400);
        }

        $is_available = false;
        $message = '';

        switch ($driver) {
            case 'redis':
                if (class_exists('Redis')) {
                    try {
                        $redis = new \Redis();
                        $is_available = true;
                        $message = __('Redis extension is installed and available.', 'formflow-pro');
                    } catch (\Exception $e) {
                        $message = sprintf(
                            __('Redis extension found but connection failed: %s', 'formflow-pro'),
                            $e->getMessage()
                        );
                    }
                } else {
                    $message = __('Redis extension is not installed.', 'formflow-pro');
                }
                break;

            case 'memcached':
                if (class_exists('Memcached')) {
                    try {
                        $memcached = new \Memcached();
                        $is_available = true;
                        $message = __('Memcached extension is installed and available.', 'formflow-pro');
                    } catch (\Exception $e) {
                        $message = sprintf(
                            __('Memcached extension found but initialization failed: %s', 'formflow-pro'),
                            $e->getMessage()
                        );
                    }
                } else {
                    $message = __('Memcached extension is not installed.', 'formflow-pro');
                }
                break;

            case 'apcu':
                if (function_exists('apcu_cache_info')) {
                    $is_available = true;
                    $message = __('APCu is installed and available.', 'formflow-pro');
                } else {
                    $message = __('APCu is not installed.', 'formflow-pro');
                }
                break;

            case 'file':
            case 'database':
                $is_available = true;
                $message = sprintf(
                    __('%s cache is always available.', 'formflow-pro'),
                    ucfirst($driver)
                );
                break;

            default:
                wp_send_json_error(['message' => __('Unknown cache driver.', 'formflow-pro')], 400);
                return;
        }

        if ($is_available) {
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => $message], 500);
        }
    }

    /**
     * Clear cache
     *
     * @return void
     */
    public static function clear_cache(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        // Clear WordPress transients
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_formflow_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_formflow_%'");

        wp_send_json_success([
            'message' => __('Cache cleared successfully.', 'formflow-pro'),
        ]);
    }

    /**
     * Test email configuration
     *
     * @return void
     */
    public static function test_email(): void
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'formflow_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'formflow-pro')], 403);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $to_email = isset($_POST['to_email']) ? sanitize_email($_POST['to_email']) : '';

        if (empty($to_email) || !is_email($to_email)) {
            wp_send_json_error([
                'message' => __('Please provide a valid email address.', 'formflow-pro'),
            ], 400);
        }

        $subject = __('FormFlow Pro - Test Email', 'formflow-pro');
        $message = __('This is a test email from FormFlow Pro.', 'formflow-pro') . "\n\n";
        $message .= __('If you received this email, your email configuration is working correctly.', 'formflow-pro') . "\n\n";
        $message .= __('Sent from:', 'formflow-pro') . ' ' . get_bloginfo('name') . "\n";
        $message .= __('Time:', 'formflow-pro') . ' ' . current_time('Y-m-d H:i:s') . "\n";

        $sent = wp_mail($to_email, $subject, $message);

        if ($sent) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Test email sent successfully to %s', 'formflow-pro'),
                    $to_email
                ),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to send test email. Please check your email configuration.', 'formflow-pro'),
            ], 500);
        }
    }
}
