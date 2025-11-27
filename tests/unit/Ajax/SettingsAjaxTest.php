<?php
/**
 * Tests for Settings_Ajax class.
 */

namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Settings_Ajax;
use WPAjaxDieException;

class SettingsAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Require the Settings_Ajax class
        require_once FORMFLOW_PATH . 'includes/ajax/class-settings-ajax.php';
    }

    public function test_init_registers_ajax_actions()
    {
        global $wp_actions;
        $wp_actions = [];

        Settings_Ajax::init();

        $this->assertArrayHasKey('wp_ajax_formflow_test_api_connection', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_check_cache_driver', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_clear_cache', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_test_email', $wp_actions);
    }

    // ========== test_api_connection() Tests ==========

    public function test_test_api_connection_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_test_api_connection_fails_without_api_key()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'api_key' => '',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('API key is required.', $response['data']['message']);
    }

    public function test_test_api_connection_succeeds_with_valid_key()
    {
        global $wp_http_mock_response;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'api_key' => 'test_api_key_123',
        ];

        // Mock successful API response
        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode(['data' => ['__schema' => ['types' => []]]]),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('API connection successful', $response['data']['message']);
    }

    public function test_test_api_connection_fails_with_invalid_key()
    {
        global $wp_http_mock_response;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'api_key' => 'invalid_key',
        ];

        // Mock 401 Unauthorized response
        $wp_http_mock_response = [
            'response' => ['code' => 401],
            'body' => json_encode(['error' => 'Unauthorized']),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Invalid API key', $response['data']['message']);
    }

    public function test_test_api_connection_handles_wp_error()
    {
        global $wp_http_mock_error;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'api_key' => 'test_key',
        ];

        // Mock WP_Error response
        $wp_http_mock_error = 'Connection timeout';

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Connection error', $response['data']['message']);
    }

    public function test_test_api_connection_handles_other_error_codes()
    {
        global $wp_http_mock_response;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'api_key' => 'test_key',
        ];

        // Mock 500 error response
        $wp_http_mock_response = [
            'response' => ['code' => 500],
            'body' => 'Internal Server Error',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_api_connection();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('API returned error code 500', $response['data']['message']);
    }

    // ========== check_cache_driver() Tests ==========

    public function test_check_cache_driver_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_check_cache_driver_fails_without_driver()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => '',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Driver is required.', $response['data']['message']);
    }

    public function test_check_cache_driver_file_always_available()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'file',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('File cache is always available', $response['data']['message']);
    }

    public function test_check_cache_driver_database_always_available()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'database',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Database cache is always available', $response['data']['message']);
    }

    public function test_check_cache_driver_redis_not_available()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'redis',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        // In test environment, Redis is not available
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Redis extension is not installed', $response['data']['message']);
    }

    public function test_check_cache_driver_memcached_not_available()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'memcached',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        // In test environment, Memcached is not available
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Memcached extension is not installed', $response['data']['message']);
    }

    public function test_check_cache_driver_apcu_not_available()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'apcu',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        // In test environment, APCu is not available
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('APCu is not installed', $response['data']['message']);
    }

    public function test_check_cache_driver_unknown_driver()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'driver' => 'unknown_driver',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::check_cache_driver();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Unknown cache driver.', $response['data']['message']);
    }

    // ========== clear_cache() Tests ==========

    public function test_clear_cache_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::clear_cache();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_clear_cache_succeeds()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::clear_cache();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Cache cleared successfully.', $response['data']['message']);
    }

    // ========== test_email() Tests ==========

    public function test_test_email_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_email();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_test_email_fails_without_email()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'to_email' => '',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_email();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('valid email address', $response['data']['message']);
    }

    public function test_test_email_fails_with_invalid_email()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'to_email' => 'invalid-email',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_email();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertStringContainsString('valid email address', $response['data']['message']);
    }

    public function test_test_email_succeeds()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'to_email' => 'test@example.com',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_email();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Test email sent successfully', $response['data']['message']);
        $this->assertStringContainsString('test@example.com', $response['data']['message']);
    }

    public function test_test_email_sanitizes_email()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'to_email' => '  test@example.com  ',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Settings_Ajax::test_email();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }
}
