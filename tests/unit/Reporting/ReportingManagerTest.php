<?php
/**
 * Tests for ReportingManager class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\ReportingManager;

class ReportingManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = ReportingManager::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = ReportingManager::getInstance();
        $instance2 = ReportingManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_create_schedule()
    {
        global $wpdb;

        $scheduleData = [
            'name' => 'Weekly Executive Report',
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => ['admin@example.com', 'manager@example.com'],
            'enabled' => true,
        ];

        $result = $this->manager->createSchedule($scheduleData);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('schedule_id', $result);
    }

    public function test_create_schedule_validation_fails_without_name()
    {
        $scheduleData = [
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => ['admin@example.com'],
        ];

        $result = $this->manager->createSchedule($scheduleData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_create_schedule_validation_fails_without_recipients()
    {
        $scheduleData = [
            'name' => 'Test Schedule',
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => [],
        ];

        $result = $this->manager->createSchedule($scheduleData);

        $this->assertFalse($result['success']);
    }

    public function test_get_schedule()
    {
        global $wpdb;

        $mockSchedule = (object)[
            'id' => '1',
            'name' => 'Weekly Report',
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => json_encode(['admin@example.com']),
            'enabled' => 1,
            'next_run' => '2024-01-22 09:00:00',
            'created_at' => '2024-01-01 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSchedule);

        $schedule = $this->manager->getSchedule('1');

        $this->assertIsObject($schedule);
        $this->assertEquals('Weekly Report', $schedule->name);
        $this->assertIsArray($schedule->recipients);
    }

    public function test_update_schedule()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Old Name',
        ]);

        $updateData = [
            'name' => 'Updated Schedule Name',
            'frequency' => 'monthly',
        ];

        $result = $this->manager->updateSchedule('1', $updateData);

        $this->assertTrue($result['success']);
    }

    public function test_delete_schedule()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Test Schedule',
        ]);

        $result = $this->manager->deleteSchedule('1');

        $this->assertTrue($result);
    }

    public function test_toggle_schedule()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'enabled' => 1,
        ]);

        $result = $this->manager->toggleSchedule('1', false);

        $this->assertTrue($result['success']);
    }

    public function test_get_all_schedules()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Schedule 1',
                'frequency' => 'weekly',
                'enabled' => 1,
            ],
            (object)[
                'id' => '2',
                'name' => 'Schedule 2',
                'frequency' => 'monthly',
                'enabled' => 0,
            ],
        ]);

        $schedules = $this->manager->getSchedules();

        $this->assertIsArray($schedules);
        $this->assertCount(2, $schedules);
    }

    public function test_calculate_next_run_weekly()
    {
        $nextRun = $this->callPrivateMethod($this->manager, 'calculateNextRun', ['weekly']);

        $this->assertIsString($nextRun);

        $nextRunDate = new \DateTime($nextRun);
        $now = new \DateTime();
        $diff = $now->diff($nextRunDate);

        $this->assertLessThanOrEqual(7, $diff->days);
    }

    public function test_calculate_next_run_monthly()
    {
        $nextRun = $this->callPrivateMethod($this->manager, 'calculateNextRun', ['monthly']);

        $this->assertIsString($nextRun);

        $nextRunDate = new \DateTime($nextRun);
        $now = new \DateTime();

        $this->assertGreaterThan($now, $nextRunDate);
    }

    public function test_process_scheduled_reports()
    {
        global $wpdb;

        // Mock due schedules
        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Due Report',
                'report_type' => 'executive_summary',
                'frequency' => 'daily',
                'recipients' => json_encode(['admin@example.com']),
                'next_run' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ],
        ]);

        $processed = $this->manager->processScheduledReports();

        $this->assertIsInt($processed);
    }

    public function test_get_report_history()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Report 1',
                'report_type' => 'executive_summary',
                'format' => 'pdf',
                'file_size' => 1024,
                'created_at' => '2024-01-15 10:00:00',
            ],
        ]);

        $history = $this->manager->getReportHistory(1, 10);

        $this->assertIsArray($history);
        $this->assertArrayHasKey('reports', $history);
    }

    public function test_cleanup_old_reports()
    {
        global $wpdb;

        // Mock old reports
        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'file_path' => '/tmp/old-report.pdf',
                'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
            ],
        ]);

        $deleted = $this->manager->cleanupOldReports(30);

        $this->assertIsInt($deleted);
    }

    public function test_validate_email_recipients()
    {
        $validEmails = ['admin@example.com', 'user@test.org'];
        $isValid = $this->callPrivateMethod($this->manager, 'validateRecipients', [$validEmails]);

        $this->assertTrue($isValid);
    }

    public function test_validate_email_recipients_with_invalid_email()
    {
        $invalidEmails = ['admin@example.com', 'invalid-email'];
        $isValid = $this->callPrivateMethod($this->manager, 'validateRecipients', [$invalidEmails]);

        $this->assertFalse($isValid);
    }

    public function test_get_frequency_options()
    {
        $options = $this->manager->getFrequencyOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('daily', $options);
        $this->assertArrayHasKey('weekly', $options);
        $this->assertArrayHasKey('monthly', $options);
        $this->assertArrayHasKey('quarterly', $options);
    }

    public function test_run_schedule_now()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Test Schedule',
            'report_type' => 'executive_summary',
            'frequency' => 'weekly',
            'recipients' => json_encode(['admin@example.com']),
            'enabled' => 1,
        ]);

        $result = $this->manager->runScheduleNow('1');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
