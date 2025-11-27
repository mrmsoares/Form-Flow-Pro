<?php
/**
 * Tests for Forms_Ajax class.
 */

namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Forms_Ajax;
use WPAjaxDieException;

class FormsAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock ABSPATH constant
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Require the Forms_Ajax class
        require_once FORMFLOW_PATH . 'includes/ajax/class-forms-ajax.php';
    }

    public function test_init_registers_ajax_actions()
    {
        global $wp_actions;
        $wp_actions = [];

        Forms_Ajax::init();

        $this->assertArrayHasKey('wp_ajax_formflow_save_form', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_get_form', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_delete_form', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_duplicate_form', $wp_actions);
        $this->assertArrayHasKey('wp_ajax_formflow_update_form_status', $wp_actions);
    }

    // ========== save_form() Tests ==========

    public function test_save_form_fails_without_nonce()
    {
        $_POST = [];

        $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

        $this->assertFalse($response['success']);
        $this->assertEquals('Security check failed.', $response['data']['message']);
    }

    public function test_save_form_fails_with_invalid_nonce()
    {
        $_POST = [
            'nonce' => 'invalid_nonce',
        ];

        $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

        $this->assertFalse($response['success']);
    }

    public function test_save_form_fails_without_name()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'name' => '',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::save_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form name is required.', $response['data']['message']);
    }

    public function test_save_form_creates_new_form_successfully()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'name' => 'Test Form',
            'description' => 'Test Description',
            'fields' => ['field1' => 'value1'],
            'settings' => ['setting1' => 'value1'],
            'status' => 'active',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::save_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Form created successfully.', $response['data']['message']);
        $this->assertArrayHasKey('form_id', $response['data']);

        // Verify database insert
        $inserts = $wpdb->get_mock_inserts();
        $this->assertNotEmpty($inserts);
        $this->assertEquals('Test Form', $inserts[0]['data']['name']);
    }

    public function test_save_form_updates_existing_form_successfully()
    {
        global $wpdb;

        // Insert a form to update
        $wpdb->insert($wpdb->prefix . 'formflow_forms', [
            'id' => 1,
            'name' => 'Original Name',
            'description' => 'Original Description',
            'fields' => '[]',
            'settings' => '{}',
            'status' => 'draft',
        ]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
            'name' => 'Updated Form',
            'description' => 'Updated Description',
            'fields' => ['field1' => 'value1'],
            'settings' => ['setting1' => 'value1'],
            'status' => 'active',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::save_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Form updated successfully.', $response['data']['message']);
        $this->assertEquals(1, $response['data']['form_id']);
    }

    // ========== get_form() Tests ==========

    public function test_get_form_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::get_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_get_form_fails_without_form_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::get_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form ID is required.', $response['data']['message']);
    }

    public function test_get_form_fails_when_form_not_found()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 999,
        ];

        $wpdb->set_mock_result('get_row', null);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::get_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form not found.', $response['data']['message']);
    }

    public function test_get_form_returns_form_successfully()
    {
        global $wpdb;

        $mockForm = (object)[
            'id' => 1,
            'name' => 'Test Form',
            'description' => 'Test Description',
            'fields' => '["field1","field2"]',
            'settings' => '{"setting1":"value1"}',
            'status' => 'active',
        ];

        $wpdb->set_mock_result('get_row', $mockForm);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::get_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('form', $response['data']);
        $this->assertEquals('Test Form', $response['data']['form']->name);
    }

    // ========== delete_form() Tests ==========

    public function test_delete_form_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::delete_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_delete_form_fails_without_form_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::delete_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form ID is required.', $response['data']['message']);
    }

    public function test_delete_form_fails_when_form_not_found()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 999,
        ];

        $wpdb->set_mock_result('get_var', 0);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::delete_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form not found.', $response['data']['message']);
    }

    public function test_delete_form_succeeds()
    {
        global $wpdb;

        // Insert form to delete
        $wpdb->insert($wpdb->prefix . 'formflow_forms', [
            'id' => 1,
            'name' => 'Test Form',
        ]);

        // Mock form exists check
        $wpdb->set_mock_result('get_var', 1);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::delete_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Form deleted successfully.', $response['data']['message']);
    }

    // ========== duplicate_form() Tests ==========

    public function test_duplicate_form_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::duplicate_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_duplicate_form_fails_without_form_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::duplicate_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form ID is required.', $response['data']['message']);
    }

    public function test_duplicate_form_fails_when_form_not_found()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 999,
        ];

        $wpdb->set_mock_result('get_row', null);

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::duplicate_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form not found.', $response['data']['message']);
    }

    public function test_duplicate_form_succeeds()
    {
        global $wpdb;

        $mockForm = [
            'id' => 1,
            'name' => 'Original Form',
            'description' => 'Original Description',
            'fields' => '[]',
            'settings' => '{}',
            'status' => 'active',
        ];

        $wpdb->set_mock_result('get_row', $mockForm);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::duplicate_form();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Form duplicated successfully.', $response['data']['message']);
        $this->assertArrayHasKey('form_id', $response['data']);

        // Verify the name was modified
        $inserts = $wpdb->get_mock_inserts();
        $this->assertNotEmpty($inserts);
        $this->assertEquals('Original Form (Copy)', $inserts[0]['data']['name']);
        $this->assertEquals('draft', $inserts[0]['data']['status']);
    }

    // ========== update_form_status() Tests ==========

    public function test_update_form_status_fails_without_nonce()
    {
        $_POST = [];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_update_form_status_fails_without_form_id()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'status' => 'active',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form ID and status are required.', $response['data']['message']);
    }

    public function test_update_form_status_fails_without_status()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Form ID and status are required.', $response['data']['message']);
    }

    public function test_update_form_status_fails_with_invalid_status()
    {
        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
            'status' => 'invalid_status',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
        $this->assertEquals('Invalid status.', $response['data']['message']);
    }

    public function test_update_form_status_succeeds_with_active()
    {
        global $wpdb;

        // Insert form to update
        $wpdb->insert($wpdb->prefix . 'formflow_forms', [
            'id' => 1,
            'name' => 'Test Form',
            'status' => 'draft',
        ]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
            'status' => 'active',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertEquals('Form status updated successfully.', $response['data']['message']);
    }

    public function test_update_form_status_succeeds_with_draft()
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'formflow_forms', [
            'id' => 1,
            'name' => 'Test Form',
            'status' => 'active',
        ]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
            'status' => 'draft',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    public function test_update_form_status_succeeds_with_archived()
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'formflow_forms', [
            'id' => 1,
            'name' => 'Test Form',
            'status' => 'active',
        ]);

        $_POST = [
            'nonce' => wp_create_nonce('formflow_nonce'),
            'form_id' => 1,
            'status' => 'archived',
        ];

        $this->expectException(WPAjaxDieException::class);

        ob_start();
        Forms_Ajax::update_form_status();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }
}
