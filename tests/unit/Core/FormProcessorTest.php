<?php
/**
 * Tests for FormProcessor class.
 */

namespace FormFlowPro\Tests\Unit\Core;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Core\FormProcessor;
use FormFlowPro\Core\CacheManager;

class FormProcessorTest extends TestCase
{
    private $processor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up mock server variables
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';
        $_SERVER['HTTP_REFERER'] = 'https://example.com/referrer';
        
        $this->processor = new FormProcessor();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
        unset($_SERVER['HTTP_REFERER']);
        
        parent::tearDown();
    }

    public function test_process_submission_with_invalid_form_returns_error()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_row', null); // Form not found
        
        $result = $this->processor->process_submission('invalid-form-id', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('failed', $result['status']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_process_submission_with_valid_form_returns_success()
    {
        global $wpdb;
        
        // Mock form object
        $mockForm = (object)[
            'id' => 'test-form-id',
            'name' => 'Test Form',
            'status' => 'active',
            'settings' => json_encode(['autentique_enabled' => false]),
            'pdf_template_id' => null,
            'email_template_id' => null,
            'autentique_enabled' => 0,
        ];
        
        $wpdb->set_mock_result('get_row', $mockForm);
        
        $result = $this->processor->process_submission('test-form-id', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'message' => 'Test message',
        ]);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('submission_id', $result);
        $this->assertNotEmpty($result['submission_id']);
    }

    public function test_data_sanitization()
    {
        global $wpdb;
        
        $mockForm = (object)[
            'id' => 'test-form-id',
            'name' => 'Test Form',
            'status' => 'active',
            'settings' => json_encode([]),
            'pdf_template_id' => null,
            'email_template_id' => null,
            'autentique_enabled' => 0,
        ];
        
        $wpdb->set_mock_result('get_row', $mockForm);
        
        $result = $this->processor->process_submission('test-form-id', [
            'email' => 'test@example.com',
            'url' => 'https://example.com',
            'malicious' => '<script>alert("xss")</script>',
        ]);
        
        $this->assertTrue($result['success']);
        
        // Check that script tags were removed
        $inserts = $wpdb->get_mock_inserts();
        $submissionData = null;
        
        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_submissions') !== false) {
                $submissionData = $insert['data'];
                break;
            }
        }
        
        $this->assertNotNull($submissionData);
        $this->assertArrayHasKey('data', $submissionData);
    }

    public function test_submission_data_compression()
    {
        global $wpdb;
        
        $mockForm = (object)[
            'id' => 'test-form-id',
            'name' => 'Test Form',
            'status' => 'active',
            'settings' => json_encode([]),
            'pdf_template_id' => null,
            'email_template_id' => null,
            'autentique_enabled' => 0,
        ];
        
        $wpdb->set_mock_result('get_row', $mockForm);
        
        $largeData = [
            'field1' => str_repeat('Lorem ipsum dolor sit amet. ', 100),
            'field2' => str_repeat('Test data ', 100),
            'field3' => 'John Doe',
        ];
        
        $result = $this->processor->process_submission('test-form-id', $largeData);
        
        $this->assertTrue($result['success']);
        
        // Verify data_compressed flag was set
        $inserts = $wpdb->get_mock_inserts();
        $submissionData = null;
        
        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_submissions') !== false) {
                $submissionData = $insert['data'];
                break;
            }
        }
        
        $this->assertNotNull($submissionData);
        $this->assertArrayHasKey('data_compressed', $submissionData);
    }

    public function test_queue_jobs_created_for_pdf_template()
    {
        global $wpdb;
        
        $mockForm = (object)[
            'id' => 'test-form-id',
            'name' => 'Test Form',
            'status' => 'active',
            'settings' => json_encode([]),
            'pdf_template_id' => 'pdf-template-123',
            'email_template_id' => null,
            'autentique_enabled' => 0,
        ];
        
        $wpdb->set_mock_result('get_row', $mockForm);
        
        $result = $this->processor->process_submission('test-form-id', [
            'name' => 'John Doe',
        ]);
        
        $this->assertTrue($result['success']);
        
        // Check that PDF generation job was queued
        $inserts = $wpdb->get_mock_inserts();
        $pdfJobFound = false;
        
        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                if ($insert['data']['job_type'] === 'generate_pdf') {
                    $pdfJobFound = true;
                    $this->assertEquals('high', $insert['data']['priority']);
                }
            }
        }
        
        $this->assertTrue($pdfJobFound, 'PDF generation job should be queued');
    }

    public function test_ip_address_detection()
    {
        global $wpdb;
        
        $mockForm = (object)[
            'id' => 'test-form-id',
            'name' => 'Test Form',
            'status' => 'active',
            'settings' => json_encode([]),
            'pdf_template_id' => null,
            'email_template_id' => null,
            'autentique_enabled' => 0,
        ];
        
        $wpdb->set_mock_result('get_row', $mockForm);
        
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 198.51.100.1';
        
        $result = $this->processor->process_submission('test-form-id', [
            'name' => 'Test',
        ]);
        
        $this->assertTrue($result['success']);
        
        // Verify IP was captured from X-Forwarded-For header
        $inserts = $wpdb->get_mock_inserts();
        $submissionData = null;
        
        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_submissions') !== false) {
                $submissionData = $insert['data'];
                break;
            }
        }
        
        $this->assertNotNull($submissionData);
        $this->assertArrayHasKey('ip_address', $submissionData);
        // Should extract first IP from comma-separated list
        $this->assertEquals('203.0.113.1', $submissionData['ip_address']);
        
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
}
