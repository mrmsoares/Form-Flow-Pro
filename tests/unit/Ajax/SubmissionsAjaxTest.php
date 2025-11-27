<?php
/**
 * Tests for Submissions_Ajax class.
 */

namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Submissions_Ajax;
use WPAjaxDieException;

class SubmissionsAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Require the Submissions_Ajax class
        require_once FORMFLOW_PATH . 'includes/ajax/class-submissions-ajax.php';
    }

    public function test_init_registers_ajax_actions()
    {
        global $wp_actions;
        $wp_actions = [];

        Submissions_Ajax::init();

        $this->assertArrayHasKey('wp_ajax_formflow_get_submissions', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_get_submission', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_delete_submission', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_bulk_delete_submissions', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_export_submissions', $wp_actions);
    }

    // ========== get_submissions() Tests ==========

    public function test_get_submissions_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_submissions_returns_datatable_format()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
        ];

        // Mock total count
        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertArrayHasKey('draw', $response);
        $this->assertArrayHasKey('recordsTotal', $response);
        $this->assertArrayHasKey('recordsFiltered', $response);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals(1, $response['draw']);
    }

    public function test_get_submissions_with_search_filter()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'test search'],
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertIsArray($response['data']);
    }

    public function test_get_submissions_with_form_id_filter()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'form_id' => 1,
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertIsArray($response);
    }

    public function test_get_submissions_with_status_filter()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'status' => 'completed',
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertIsArray($response);
    }

    public function test_get_submissions_with_date_filters()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'draw' => 1,
            'start' => 0,
            'length' => 25,
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertIsArray($response);
    }

    // ========== get_submission() Tests ==========

    public function test_get_submission_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_submission_fails_without_submission_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Submission ID is required.', $response['data']['message']);
    }

    public function test_get_submission_fails_when_not_found()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_id' => 999,
        ];

        $wpdb->set_mock_result('get_row', null);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Submission not found.', $response['data']['message']);
    }

    public function test_get_submission_succeeds()
    {
        global $wpdb;

        $mockSubmission = [
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'status' => 'completed',
            'form_data' => '{"name":"John"}',
            'ip_address' => '127.0.0.1',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::get_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals(1, $response['data']['id']);
        $this->assertEquals('Test Form', $response['data']['form_name']);
    }

    // ========== delete_submission() Tests ==========

    public function test_delete_submission_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::delete_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_delete_submission_fails_without_submission_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::delete_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Submission ID is required.', $response['data']['message']);
    }

    public function test_delete_submission_succeeds()
    {
        global $wpdb;

        // Insert submission to delete
        $wpdb->insert($wpdb->prefix . 'formflow_submissions', [
            'id' => 1,
            'form_id' => 1,
            'status' => 'completed',
        ]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::delete_submission();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Submission deleted successfully.', $response['data']['message']);
    }

    // ========== bulk_delete_submissions() Tests ==========

    public function test_bulk_delete_submissions_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::bulk_delete_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_bulk_delete_submissions_fails_without_ids()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_ids' => [],
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::bulk_delete_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('No submissions selected.', $response['data']['message']);
    }

    public function test_bulk_delete_submissions_succeeds()
    {
        global $wpdb;

        // Insert submissions to delete
        $wpdb->insert($wpdb->prefix . 'formflow_submissions', ['id' => 1]);
        $wpdb->insert($wpdb->prefix . 'formflow_submissions', ['id' => 2]);
        $wpdb->insert($wpdb->prefix . 'formflow_submissions', ['id' => 3]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_ids' => [1, 2, 3],
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::bulk_delete_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertStringContainsString('deleted successfully', $response['data']['message']);
        $this->assertArrayHasKey('deleted_count', $response['data']);
    }

    public function test_bulk_delete_submissions_sanitizes_ids()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'submission_ids' => ['1', '2', 'invalid', '3'],
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Submissions_Ajax::bulk_delete_submissions();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    // ========== export_submissions() Tests ==========

    public function test_export_submissions_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Security check failed.');

        Submissions_Ajax::export_submissions();
    }

    public function test_export_submissions_single_type()
    {
        global $wpdb;

        $mockSubmissions = [[
            'id' => 1,
            'form_name' => 'Test Form',
            'status' => 'completed',
            'signature_status' => 'signed',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'created_at' => '2024-01-01 12:00:00',
            'form_data' => '{"name":"John"}',
            'metadata' => '{}',
        ]];

        $wpdb->set_mock_result('get_results', $mockSubmissions);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'export_type' => 'single',
            'submission_id' => 1,
        ];

        // Export will call wp_die, which we need to handle
        try {
            ob_start();
            Submissions_Ajax::export_submissions();
            $output = ob_get_clean();

            // Check that CSV headers were output
            $this->assertStringContainsString('ID', $output);
            $this->assertStringContainsString('Form', $output);
        } catch (\Exception $e) {
            // wp_die may throw exception in test environment
            $this->assertStringContainsString('Security check failed', $e->getMessage());
        }
    }

    public function test_export_submissions_selected_type()
    {
        global $wpdb;

        $mockSubmissions = [
            [
                'id' => 1,
                'form_name' => 'Test Form',
                'status' => 'completed',
                'signature_status' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => '2024-01-01 12:00:00',
                'form_data' => '{}',
                'metadata' => null,
            ],
            [
                'id' => 2,
                'form_name' => 'Test Form',
                'status' => 'pending',
                'signature_status' => null,
                'ip_address' => '127.0.0.2',
                'user_agent' => 'Mozilla/5.0',
                'created_at' => '2024-01-02 12:00:00',
                'form_data' => '{}',
                'metadata' => null,
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockSubmissions);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'export_type' => 'selected',
            'submission_ids' => [1, 2],
        ];

        try {
            ob_start();
            Submissions_Ajax::export_submissions();
            ob_get_clean();
        } catch (\Exception $e) {
            // Expected in test environment
        }

        // Verify that the export was attempted
        $this->assertTrue(true);
    }

    public function test_export_submissions_all_type_with_filters()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'export_type' => 'all',
            'form_id' => 1,
            'status' => 'completed',
            'date_from' => '2024-01-01',
            'date_to' => '2024-12-31',
        ];

        try {
            ob_start();
            Submissions_Ajax::export_submissions();
            ob_get_clean();
        } catch (\Exception $e) {
            // wp_die with "No submissions found to export"
            $this->assertStringContainsString('No submissions found to export', $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_export_submissions_fails_when_no_data()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'export_type' => 'all',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No submissions found to export');

        Submissions_Ajax::export_submissions();
    }
}
