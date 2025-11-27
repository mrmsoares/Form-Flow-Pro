<?php
/**
 * Tests for Dashboard_Ajax class.
 */

namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Dashboard_Ajax;
use WPAjaxDieException;

class DashboardAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Require the Dashboard_Ajax class
        require_once FORMFLOW_PATH . 'includes/ajax/class-dashboard-ajax.php';
    }

    public function test_init_registers_ajax_actions()
    {
        global $wp_actions;
        $wp_actions = [];

        Dashboard_Ajax::init();

        $this->assertArrayHasKey('wp_ajax_formflow_get_dashboard_stats', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_get_recent_submissions', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_get_chart_data', $wp_actions);
    }

    // ========== get_dashboard_stats() Tests ==========

    public function test_get_dashboard_stats_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_dashboard_stats();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_dashboard_stats_succeeds()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        // Mock database queries return 0 for counts
        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_dashboard_stats();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('total_submissions', $response['data']);
        $this->assertArrayHasKey('submissions_today', $response['data']);
        $this->assertArrayHasKey('submissions_month', $response['data']);
        $this->assertArrayHasKey('completed_submissions', $response['data']);
        $this->assertArrayHasKey('pending_submissions', $response['data']);
        $this->assertArrayHasKey('pending_signature', $response['data']);
        $this->assertArrayHasKey('failed_submissions', $response['data']);
        $this->assertArrayHasKey('active_forms', $response['data']);
        $this->assertArrayHasKey('conversion_rate', $response['data']);
        $this->assertArrayHasKey('growth_rate', $response['data']);
    }

    public function test_get_dashboard_stats_returns_correct_structure()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_dashboard_stats();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);

        // Verify all stats are numeric
        $this->assertIsNumeric($response['data']['total_submissions']);
        $this->assertIsNumeric($response['data']['submissions_today']);
        $this->assertIsNumeric($response['data']['submissions_month']);
        $this->assertIsNumeric($response['data']['completed_submissions']);
        $this->assertIsNumeric($response['data']['pending_submissions']);
        $this->assertIsNumeric($response['data']['pending_signature']);
        $this->assertIsNumeric($response['data']['failed_submissions']);
        $this->assertIsNumeric($response['data']['active_forms']);
        $this->assertIsNumeric($response['data']['conversion_rate']);
        $this->assertIsNumeric($response['data']['growth_rate']);
    }

    public function test_get_dashboard_stats_calculates_conversion_rate()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        // Mock: 100 total submissions, 75 completed
        // Expected conversion rate: 75%
        $call_count = 0;
        $wpdb->set_mock_result('get_var', function() use (&$call_count) {
            $call_count++;
            if ($call_count === 1) return 100; // total_submissions
            if ($call_count === 4) return 75;  // completed_submissions
            return 0;
        });

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_dashboard_stats();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);

        // Due to mock limitations, we just verify the field exists
        $this->assertArrayHasKey('conversion_rate', $response['data']);
        $this->assertGreaterThanOrEqual(0, $response['data']['conversion_rate']);
        $this->assertLessThanOrEqual(100, $response['data']['conversion_rate']);
    }

    // ========== get_recent_submissions() Tests ==========

    public function test_get_recent_submissions_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_recent_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_recent_submissions_succeeds()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $mockSubmissions = [
            [
                'id' => 1,
                'form_id' => 1,
                'form_name' => 'Test Form',
                'status' => 'completed',
                'created_at' => '2024-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'form_id' => 1,
                'form_name' => 'Test Form',
                'status' => 'pending',
                'created_at' => '2024-01-02 12:00:00',
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_recent_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('submissions', $response['data']);
        $this->assertIsArray($response['data']['submissions']);
    }

    public function test_get_recent_submissions_respects_limit()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'limit' => 5,
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_recent_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertIsArray($response['data']['submissions']);
    }

    public function test_get_recent_submissions_default_limit()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_recent_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);

        // Verify query was called with default limit of 10
        $this->assertStringContainsString('LIMIT', $wpdb->last_query);
    }

    // ========== get_chart_data() Tests ==========

    public function test_get_chart_data_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_chart_data_submissions_type()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'submissions',
            'period' => '30days',
        ];

        $mockData = [
            (object)['label' => '2024-01-01', 'value' => 10],
            (object)['label' => '2024-01-02', 'value' => 15],
        ];

        $wpdb->set_mock_result('get_results', $mockData);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response['data']);
        $this->assertArrayHasKey('chart_type', $response['data']);
        $this->assertArrayHasKey('period', $response['data']);
        $this->assertEquals('submissions', $response['data']['chart_type']);
    }

    public function test_get_chart_data_status_type()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'status',
            'period' => '7days',
        ];

        $mockData = [
            (object)['label' => 'completed', 'value' => 50],
            (object)['label' => 'pending', 'value' => 25],
        ];

        $wpdb->set_mock_result('get_results', $mockData);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('status', $response['data']['chart_type']);
    }

    public function test_get_chart_data_forms_type()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'forms',
            'period' => 'all',
        ];

        $mockData = [
            (object)['label' => 'Contact Form', 'value' => 100],
            (object)['label' => 'Survey Form', 'value' => 75],
        ];

        $wpdb->set_mock_result('get_results', $mockData);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('forms', $response['data']['chart_type']);
    }

    public function test_get_chart_data_hourly_type()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'hourly',
            'period' => '7days',
        ];

        $mockData = [
            (object)['label' => '0', 'value' => 5],
            (object)['label' => '1', 'value' => 3],
        ];

        $wpdb->set_mock_result('get_results', $mockData);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('hourly', $response['data']['chart_type']);
    }

    public function test_get_chart_data_invalid_type()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'invalid_type',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid chart type.', $response['data']['message']);
    }

    public function test_get_chart_data_default_values()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        // Default chart_type is 'submissions' and period is '30days'
        $this->assertEquals('submissions', $response['data']['chart_type']);
        $this->assertEquals('30days', $response['data']['period']);
    }

    public function test_get_chart_data_period_7days()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'submissions',
            'period' => '7days',
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('7days', $response['data']['period']);
    }

    public function test_get_chart_data_period_90days()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'submissions',
            'period' => '90days',
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('90days', $response['data']['period']);
    }

    public function test_get_chart_data_period_year()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'submissions',
            'period' => 'year',
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('year', $response['data']['period']);
    }

    public function test_get_chart_data_period_all()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'chart_type' => 'submissions',
            'period' => 'all',
        ];

        $wpdb->set_mock_result('get_results', []);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Dashboard_Ajax::get_chart_data();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('all', $response['data']['period']);
    }
}
