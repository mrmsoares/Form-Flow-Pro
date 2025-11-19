<?php

declare(strict_types=1);

namespace FormFlowPro\Queue;

if (!defined('ABSPATH')) exit;

/**
 * Queue Manager - Background Processing System
 */
class Queue_Manager
{
    private static ?self $instance = null;

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
        add_action('init', [$this, 'schedule_cron']);
    }

    public function schedule_cron(): void
    {
        if (!wp_next_scheduled('formflow_process_queue')) {
            wp_schedule_event(time(), 'hourly', 'formflow_process_queue');
        }
    }

    public function add_job(string $type, array $data, int $priority = 10): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';

        $wpdb->insert($table, [
            'job_type' => $type,
            'job_data' => wp_json_encode($data),
            'priority' => $priority,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%d', '%s', '%d', '%s']);

        return $wpdb->insert_id;
    }

    public function process_queue(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';
        $max_attempts = get_option('formflow_queue_max_attempts', 3);

        $jobs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status = 'pending'
             AND attempts < %d
             ORDER BY priority DESC, created_at ASC
             LIMIT 10",
            $max_attempts
        ));

        foreach ($jobs as $job) {
            $this->process_job($job);
        }
    }

    private function process_job(object $job): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_queue';

        $wpdb->update($table, [
            'status' => 'processing',
            'attempts' => $job->attempts + 1,
            'updated_at' => current_time('mysql'),
        ], ['id' => $job->id], ['%s', '%d', '%s'], ['%d']);

        try {
            $data = json_decode($job->job_data, true);
            do_action("formflow_process_{$job->job_type}", $data, $job->id);

            $wpdb->update($table, [
                'status' => 'completed',
                'completed_at' => current_time('mysql'),
            ], ['id' => $job->id], ['%s', '%s'], ['%d']);
        } catch (\Exception $e) {
            $wpdb->update($table, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ], ['id' => $job->id], ['%s', '%s'], ['%d']);
        }
    }
}
