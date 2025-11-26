<?php
/**
 * Tests for ReportingManager class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\ReportingManager;

class ReportingManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton
        $reflection = new \ReflectionClass(ReportingManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ReportingManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        parent::tearDown();
    }

    public function test_singleton_instance()
    {
        $instance1 = ReportingManager::getInstance();
        $instance2 = ReportingManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ReportingManager::class, $instance1);
    }

    public function test_get_presets_returns_array()
    {
        $manager = ReportingManager::getInstance();
        $presets = $manager->getPresets();

        $this->assertIsArray($presets);
    }

    public function test_get_preset_returns_null_for_nonexistent()
    {
        $manager = ReportingManager::getInstance();
        $preset = $manager->getPreset('nonexistent_preset');

        $this->assertNull($preset);
    }

    public function test_get_scheduled_reports_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => 1,
                'name' => 'Schedule 1',
                'frequency' => 'weekly',
                'enabled' => 1,
            ],
        ]);

        $manager = ReportingManager::getInstance();
        $reports = $manager->getScheduledReports();

        $this->assertIsArray($reports);
    }

    public function test_get_scheduled_report_returns_null_for_nonexistent()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $manager = ReportingManager::getInstance();
        $report = $manager->getScheduledReport(999);

        $this->assertNull($report);
    }

    public function test_get_scheduled_report_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => 1,
            'name' => 'Test Report',
            'frequency' => 'weekly',
            'recipients' => json_encode(['admin@example.com']),
        ]);

        $manager = ReportingManager::getInstance();
        $report = $manager->getScheduledReport(1);

        $this->assertIsArray($report);
        $this->assertEquals('Test Report', $report['name']);
    }

    public function test_save_scheduled_report_returns_id()
    {
        global $wpdb;

        $wpdb->insert_id = 42;

        $manager = ReportingManager::getInstance();

        $data = [
            'name' => 'New Scheduled Report',
            'template' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => ['admin@example.com'],
        ];

        $id = $manager->saveScheduledReport($data);

        $this->assertIsInt($id);
    }

    public function test_delete_scheduled_report()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => 1,
            'name' => 'Test Report',
        ]);

        $manager = ReportingManager::getInstance();

        // Should not throw exception
        $manager->deleteScheduledReport(1);

        $this->assertTrue(true);
    }

    public function test_get_report_history_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => 1,
                'name' => 'Report 1',
                'format' => 'pdf',
                'file_size' => 1024,
                'created_at' => '2024-01-15 10:00:00',
            ],
        ]);

        $manager = ReportingManager::getInstance();
        $history = $manager->getReportHistory(10);

        $this->assertIsArray($history);
    }

    public function test_process_scheduled_reports()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $manager = ReportingManager::getInstance();

        // Should not throw exception
        $manager->processScheduledReports();

        $this->assertTrue(true);
    }

    public function test_run_scheduled_report_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => 1,
            'name' => 'Test Report',
            'template' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => json_encode(['admin@example.com']),
            'format' => 'pdf',
            'settings' => json_encode([]),
        ]);

        $manager = ReportingManager::getInstance();
        $result = $manager->runScheduledReport(1);

        $this->assertIsArray($result);
    }
}
