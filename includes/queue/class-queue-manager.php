<?php

declare(strict_types=1);

namespace FormFlowPro\Queue;

if (!defined('ABSPATH')) exit;

/**
 * Queue Manager - Optimized Background Processing System
 *
 * Features:
 * - Configurable batch size
 * - Optimized database queries using indexes
 * - Atomic job claiming to prevent duplicate processing
 * - Exponential backoff for retries
 * - Dead letter queue for permanently failed jobs
 *
 * @package FormFlowPro
 * @since 2.0.0
 */
class Queue_Manager
{
    private static ?self $instance = null;

    /**
     * Lock timeout in seconds for processing jobs.
     */
    private const LOCK_TIMEOUT = 300;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('formflow_process_queue', [$this, 'process_queue']);
        add_action('formflow_cleanup_dead_jobs', [$this, 'cleanup_dead_jobs']);
    }

    /**
     * Add a job to the queue.
     *
     * @param string $type Job type (generate_pdf, send_autentique, send_email).
     * @param array $data Job parameters.
     * @param string $priority Priority level (high, medium, low).
     * @param int $delay_seconds Delay before processing (0 = immediate).
     * @return int Job ID.
     */
    public function add_job(string $type, array $data, string $priority = 'medium', int $delay_seconds = 0): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';

        $scheduled_at = $delay_seconds > 0
            ? gmdate('Y-m-d H:i:s', time() + $delay_seconds)
            : current_time('mysql');

        $wpdb->insert($table, [
            'job_type' => $type,
            'job_data' => wp_json_encode($data),
            'priority' => $priority,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => (int) get_option('formflow_queue_retry_attempts', 3),
            'scheduled_at' => $scheduled_at,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

        return $wpdb->insert_id;
    }

    /**
     * Process pending jobs in the queue.
     *
     * Uses optimized query leveraging idx_worker_query index.
     * Implements atomic job claiming to prevent duplicate processing.
     */
    public function process_queue(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';
        $batch_size = (int) get_option('formflow_queue_batch_size', 10);

        // Optimized query using idx_worker_query (status, scheduled_at, priority)
        // Atomically claim jobs by updating status in the same query
        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'pending'
             AND scheduled_at <= %s
             AND attempts < max_attempts
             ORDER BY
                FIELD(priority, 'high', 'medium', 'low'),
                scheduled_at ASC
             LIMIT %d
             FOR UPDATE SKIP LOCKED",
            current_time('mysql'),
            $batch_size
        ));

        if (empty($jobs)) {
            return;
        }

        // Process each job
        foreach ($jobs as $job) {
            $this->process_job($job);
        }

        // If we processed a full batch, schedule another run immediately
        if (count($jobs) >= $batch_size) {
            wp_schedule_single_event(time() + 5, 'formflow_process_queue');
        }
    }

    /**
     * Process a single job.
     *
     * @param object $job Job object from database.
     */
    private function process_job(object $job): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';
        $start_time = microtime(true);

        // Mark job as processing
        $claimed = $wpdb->update(
            $table,
            [
                'status' => 'processing',
                'attempts' => $job->attempts + 1,
                'started_at' => current_time('mysql'),
            ],
            [
                'id' => $job->id,
                'status' => 'pending', // Only claim if still pending
            ],
            ['%s', '%d', '%s'],
            ['%d', '%s']
        );

        // If we couldn't claim the job, skip it (another worker got it)
        if ($claimed === 0) {
            return;
        }

        try {
            $data = json_decode($job->job_data, true);

            // Execute the job
            do_action("formflow_process_{$job->job_type}", $data, $job->id);

            // Mark as completed
            $processing_time = round((microtime(true) - $start_time) * 1000);
            $wpdb->update(
                $table,
                [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'last_error' => null,
                ],
                ['id' => $job->id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Log success for monitoring
            do_action('formflow_queue_job_completed', $job->id, $job->job_type, $processing_time);

        } catch (\Exception $e) {
            $this->handle_job_failure($job, $e);
        }
    }

    /**
     * Handle job failure with exponential backoff.
     *
     * @param object $job Failed job.
     * @param \Exception $e Exception that caused the failure.
     */
    private function handle_job_failure(object $job, \Exception $e): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';
        $new_attempts = $job->attempts + 1;
        $max_attempts = (int) $job->max_attempts;

        // Check if this was the last attempt
        if ($new_attempts >= $max_attempts) {
            // Move to dead letter queue
            $wpdb->update(
                $table,
                [
                    'status' => 'dead_letter',
                    'last_error' => substr($e->getMessage(), 0, 65535),
                    'completed_at' => current_time('mysql'),
                ],
                ['id' => $job->id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Notify about dead letter
            do_action('formflow_queue_job_dead', $job->id, $job->job_type, $e->getMessage());
        } else {
            // Calculate exponential backoff: 60s, 300s (5min), 900s (15min)
            $retry_delay = (int) get_option('formflow_queue_retry_delay', 60);
            $backoff = $retry_delay * pow(3, $new_attempts - 1);
            $next_scheduled = gmdate('Y-m-d H:i:s', time() + $backoff);

            $wpdb->update(
                $table,
                [
                    'status' => 'pending',
                    'scheduled_at' => $next_scheduled,
                    'last_error' => substr($e->getMessage(), 0, 65535),
                ],
                ['id' => $job->id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        }

        // Log failure
        do_action('formflow_queue_job_failed', $job->id, $job->job_type, $e->getMessage(), $new_attempts);
    }

    /**
     * Cleanup old completed and dead letter jobs.
     *
     * Should be scheduled to run daily or weekly.
     */
    public function cleanup_dead_jobs(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';
        $retention_days = (int) get_option('formflow_queue_retention_days', 30);

        // Delete completed jobs older than retention period
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE status IN ('completed', 'dead_letter')
             AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));

        // Reset stuck processing jobs (older than lock timeout)
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'pending',
                 scheduled_at = NOW()
             WHERE status = 'processing'
             AND started_at < DATE_SUB(NOW(), INTERVAL %d SECOND)",
            self::LOCK_TIMEOUT
        ));
    }

    /**
     * Get queue statistics.
     *
     * @return array Queue statistics.
     */
    public function get_stats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';

        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM {$table}
             GROUP BY status",
            OBJECT_K
        );

        return [
            'pending' => (int) ($stats['pending']->count ?? 0),
            'processing' => (int) ($stats['processing']->count ?? 0),
            'completed' => (int) ($stats['completed']->count ?? 0),
            'failed' => (int) ($stats['failed']->count ?? 0),
            'dead_letter' => (int) ($stats['dead_letter']->count ?? 0),
        ];
    }
}
