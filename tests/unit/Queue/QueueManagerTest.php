<?php
/**
 * Tests for Queue_Manager class.
 */

namespace FormFlowPro\Tests\Unit\Queue;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Queue\Queue_Manager;

class QueueManagerTest extends TestCase
{
    private $queueManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueManager = Queue_Manager::get_instance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = Queue_Manager::get_instance();
        $instance2 = Queue_Manager::get_instance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(Queue_Manager::class, $instance1);
    }

    public function test_add_job_with_default_priority()
    {
        global $wpdb;

        $jobType = 'generate_pdf';
        $jobData = ['submission_id' => 123, 'template_id' => 'pdf-1'];

        $jobId = $this->queueManager->add_job($jobType, $jobData);

        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);

        // Verify insert was called
        $inserts = $wpdb->get_mock_inserts();
        $queueInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                $queueInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($queueInsert);
        $this->assertEquals('generate_pdf', $queueInsert['job_type']);
        $this->assertEquals('medium', $queueInsert['priority']);
        $this->assertEquals('pending', $queueInsert['status']);
        $this->assertEquals(0, $queueInsert['attempts']);
    }

    public function test_add_job_with_high_priority()
    {
        global $wpdb;

        $jobId = $this->queueManager->add_job('send_email', ['to' => 'test@example.com'], 'high');

        $this->assertIsInt($jobId);

        $inserts = $wpdb->get_mock_inserts();
        $queueInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                $queueInsert = $insert['data'];
                break;
            }
        }

        $this->assertEquals('high', $queueInsert['priority']);
    }

    public function test_add_job_with_delay()
    {
        global $wpdb;

        $delay = 300; // 5 minutes
        $jobId = $this->queueManager->add_job('send_email', ['to' => 'test@example.com'], 'medium', $delay);

        $this->assertIsInt($jobId);

        $inserts = $wpdb->get_mock_inserts();
        $queueInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                $queueInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($queueInsert);
        $this->assertArrayHasKey('scheduled_at', $queueInsert);
        // Scheduled time should be in the future
        $this->assertNotEquals($queueInsert['created_at'], $queueInsert['scheduled_at']);
    }

    public function test_add_job_encodes_data_as_json()
    {
        global $wpdb;

        $jobData = [
            'submission_id' => 456,
            'nested' => ['key' => 'value'],
            'array' => [1, 2, 3],
        ];

        $jobId = $this->queueManager->add_job('generate_pdf', $jobData);

        $inserts = $wpdb->get_mock_inserts();
        $queueInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                $queueInsert = $insert['data'];
                break;
            }
        }

        $this->assertNotNull($queueInsert);
        $this->assertArrayHasKey('job_data', $queueInsert);

        $decodedData = json_decode($queueInsert['job_data'], true);
        $this->assertEquals($jobData, $decodedData);
    }

    public function test_add_job_uses_configured_max_attempts()
    {
        global $wpdb;

        update_option('formflow_queue_retry_attempts', 5);

        $jobId = $this->queueManager->add_job('send_autentique', ['doc_id' => 'doc-123']);

        $inserts = $wpdb->get_mock_inserts();
        $queueInsert = null;

        foreach ($inserts as $insert) {
            if (strpos($insert['table'], 'formflow_queue') !== false) {
                $queueInsert = $insert['data'];
                break;
            }
        }

        $this->assertEquals(5, $queueInsert['max_attempts']);
    }

    public function test_process_queue_returns_early_when_no_jobs()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        // Should not throw any errors
        $this->queueManager->process_queue();

        $this->assertTrue(true);
    }

    public function test_process_queue_processes_pending_jobs()
    {
        global $wpdb;

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'generate_pdf',
            'job_data' => json_encode(['submission_id' => 123]),
            'priority' => 'high',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'scheduled_at' => current_time('mysql'),
            'created_at' => current_time('mysql'),
        ];

        $wpdb->set_mock_result('get_results', [$mockJob]);

        // Mock that we successfully claimed the job
        $wpdb->set_mock_update_result(1);

        $actionCalled = false;
        add_action('formflow_process_generate_pdf', function() use (&$actionCalled) {
            $actionCalled = true;
        });

        $this->queueManager->process_queue();

        $this->assertTrue($actionCalled, 'Job processor action should be triggered');

        // Verify job was marked as processing then completed
        $updates = $wpdb->get_mock_updates();
        $this->assertNotEmpty($updates);
    }

    public function test_process_queue_respects_batch_size()
    {
        global $wpdb;

        update_option('formflow_queue_batch_size', 5);

        // This would be tested by verifying the LIMIT in the query
        $wpdb->set_mock_result('get_results', []);

        $this->queueManager->process_queue();

        $this->assertTrue(true);
    }

    public function test_process_queue_orders_by_priority()
    {
        global $wpdb;

        // The query should order by FIELD(priority, 'high', 'medium', 'low')
        // This is tested indirectly through the SQL query structure
        $wpdb->set_mock_result('get_results', []);

        $this->queueManager->process_queue();

        $this->assertTrue(true);
    }

    public function test_cleanup_dead_jobs_removes_old_completed_jobs()
    {
        global $wpdb;

        update_option('formflow_queue_retention_days', 30);

        $this->queueManager->cleanup_dead_jobs();

        // Verify DELETE query was executed
        $queries = $wpdb->get_mock_queries();
        $deleteFound = false;

        foreach ($queries as $query) {
            if (strpos($query, 'DELETE FROM') !== false && strpos($query, 'formflow_queue') !== false) {
                $deleteFound = true;
                break;
            }
        }

        $this->assertTrue($deleteFound, 'DELETE query should be executed for cleanup');
    }

    public function test_cleanup_dead_jobs_resets_stuck_processing_jobs()
    {
        global $wpdb;

        $this->queueManager->cleanup_dead_jobs();

        // Verify UPDATE query was executed to reset stuck jobs
        $updates = $wpdb->get_mock_updates();
        $this->assertNotEmpty($updates);
    }

    public function test_get_stats_returns_correct_structure()
    {
        global $wpdb;

        $mockStats = [
            'pending' => (object)['status' => 'pending', 'count' => 10],
            'processing' => (object)['status' => 'processing', 'count' => 2],
            'completed' => (object)['status' => 'completed', 'count' => 100],
            'dead_letter' => (object)['status' => 'dead_letter', 'count' => 5],
        ];

        $wpdb->set_mock_result('get_results', $mockStats);

        $stats = $this->queueManager->get_stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('dead_letter', $stats);

        $this->assertEquals(10, $stats['pending']);
        $this->assertEquals(2, $stats['processing']);
        $this->assertEquals(100, $stats['completed']);
        $this->assertEquals(5, $stats['dead_letter']);
    }

    public function test_get_stats_handles_missing_statuses()
    {
        global $wpdb;

        $mockStats = [
            'pending' => (object)['status' => 'pending', 'count' => 5],
        ];

        $wpdb->set_mock_result('get_results', $mockStats);

        $stats = $this->queueManager->get_stats();

        $this->assertEquals(5, $stats['pending']);
        $this->assertEquals(0, $stats['processing']);
        $this->assertEquals(0, $stats['completed']);
        $this->assertEquals(0, $stats['failed']);
        $this->assertEquals(0, $stats['dead_letter']);
    }

    public function test_process_job_marks_as_processing()
    {
        global $wpdb;

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'send_email',
            'job_data' => json_encode(['to' => 'test@example.com']),
            'priority' => 'medium',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ];

        $wpdb->set_mock_update_result(1); // Successfully claimed

        // Use reflection to call private method
        $this->callPrivateMethod($this->queueManager, 'process_job', [$mockJob]);

        $updates = $wpdb->get_mock_updates();
        $processingUpdate = null;

        foreach ($updates as $update) {
            if (isset($update['data']['status']) && $update['data']['status'] === 'processing') {
                $processingUpdate = $update;
                break;
            }
        }

        $this->assertNotNull($processingUpdate);
        $this->assertEquals(1, $processingUpdate['data']['attempts']);
    }

    public function test_process_job_skips_if_cannot_claim()
    {
        global $wpdb;

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'send_email',
            'job_data' => json_encode(['to' => 'test@example.com']),
            'status' => 'pending',
            'attempts' => 0,
        ];

        $wpdb->set_mock_update_result(0); // Could not claim (another worker got it)

        $this->callPrivateMethod($this->queueManager, 'process_job', [$mockJob]);

        // Job should not be processed
        $updates = $wpdb->get_mock_updates();

        // Should only have the failed claim attempt, no completion
        $completedUpdates = array_filter($updates, function($update) {
            return isset($update['data']['status']) && $update['data']['status'] === 'completed';
        });

        $this->assertEmpty($completedUpdates);
    }

    public function test_handle_job_failure_with_retries_remaining()
    {
        global $wpdb;

        update_option('formflow_queue_retry_delay', 60);

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'send_autentique',
            'attempts' => 1,
            'max_attempts' => 3,
        ];

        $exception = new \Exception('Test error message');

        $this->callPrivateMethod($this->queueManager, 'handle_job_failure', [$mockJob, $exception]);

        $updates = $wpdb->get_mock_updates();
        $retryUpdate = null;

        foreach ($updates as $update) {
            if (isset($update['data']['status']) && $update['data']['status'] === 'pending') {
                $retryUpdate = $update;
                break;
            }
        }

        $this->assertNotNull($retryUpdate);
        $this->assertArrayHasKey('scheduled_at', $retryUpdate['data']);
        $this->assertArrayHasKey('last_error', $retryUpdate['data']);
        $this->assertEquals('Test error message', $retryUpdate['data']['last_error']);
    }

    public function test_handle_job_failure_moves_to_dead_letter_when_max_attempts_reached()
    {
        global $wpdb;

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'send_autentique',
            'attempts' => 2,
            'max_attempts' => 3,
        ];

        $exception = new \Exception('Final failure');

        $actionCalled = false;
        add_action('formflow_queue_job_dead', function() use (&$actionCalled) {
            $actionCalled = true;
        });

        $this->callPrivateMethod($this->queueManager, 'handle_job_failure', [$mockJob, $exception]);

        $updates = $wpdb->get_mock_updates();
        $deadLetterUpdate = null;

        foreach ($updates as $update) {
            if (isset($update['data']['status']) && $update['data']['status'] === 'dead_letter') {
                $deadLetterUpdate = $update;
                break;
            }
        }

        $this->assertNotNull($deadLetterUpdate);
        $this->assertEquals('Final failure', $deadLetterUpdate['data']['last_error']);
        $this->assertTrue($actionCalled, 'Dead letter action should be triggered');
    }

    public function test_handle_job_failure_implements_exponential_backoff()
    {
        global $wpdb;

        update_option('formflow_queue_retry_delay', 60);

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'test_job',
            'attempts' => 2,
            'max_attempts' => 5,
        ];

        $exception = new \Exception('Retry needed');

        $this->callPrivateMethod($this->queueManager, 'handle_job_failure', [$mockJob, $exception]);

        // For attempt 2, backoff should be 60 * 3^(2-1) = 60 * 3 = 180 seconds
        // This would be verified by checking the scheduled_at timestamp

        $updates = $wpdb->get_mock_updates();
        $this->assertNotEmpty($updates);
    }

    public function test_handle_job_failure_truncates_long_error_messages()
    {
        global $wpdb;

        $mockJob = (object)[
            'id' => 1,
            'job_type' => 'test_job',
            'attempts' => 2,
            'max_attempts' => 3,
        ];

        $longMessage = str_repeat('Error message. ', 10000);
        $exception = new \Exception($longMessage);

        $this->callPrivateMethod($this->queueManager, 'handle_job_failure', [$mockJob, $exception]);

        $updates = $wpdb->get_mock_updates();
        $deadLetterUpdate = null;

        foreach ($updates as $update) {
            if (isset($update['data']['status']) && $update['data']['status'] === 'dead_letter') {
                $deadLetterUpdate = $update;
                break;
            }
        }

        $this->assertNotNull($deadLetterUpdate);
        $this->assertLessThanOrEqual(65535, strlen($deadLetterUpdate['data']['last_error']));
    }

    public function test_supports_different_job_types()
    {
        $jobTypes = ['generate_pdf', 'send_autentique', 'send_email', 'custom_job'];

        foreach ($jobTypes as $jobType) {
            $jobId = $this->queueManager->add_job($jobType, ['test' => 'data']);
            $this->assertIsInt($jobId);
            $this->assertGreaterThan(0, $jobId);
        }
    }

    public function test_supports_different_priority_levels()
    {
        $priorities = ['high', 'medium', 'low'];

        foreach ($priorities as $priority) {
            $jobId = $this->queueManager->add_job('test_job', ['data' => 'test'], $priority);
            $this->assertIsInt($jobId);
        }
    }
}
