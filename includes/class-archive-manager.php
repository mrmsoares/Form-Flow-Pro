<?php

declare(strict_types=1);

namespace FormFlowPro;

if (!defined('ABSPATH')) exit;

/**
 * Archive Manager - Old Submissions Archiving
 */
class Archive_Manager
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
        add_action('formflow_archive_submissions', [$this, 'archive_old_submissions']);
    }

    /**
     * Archive old submissions
     */
    public function archive_old_submissions(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';

        $archive_days = get_option('formflow_archive_after_days', 90);
        $auto_archive = get_option('formflow_auto_archive_enabled', false);

        if (!$auto_archive) {
            return;
        }

        // Get old completed submissions
        $old_submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE status = 'completed'
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
             AND signature_status != 'pending'
             LIMIT 1000",
            $archive_days
        ));

        if (empty($old_submissions)) {
            return;
        }

        $archived_count = 0;

        foreach ($old_submissions as $submission) {
            // Create archive file
            $this->create_archive_file($submission);

            // Update submission status
            $wpdb->update(
                $table,
                [
                    'status' => 'archived',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $submission->id],
                ['%s', '%s'],
                ['%d']
            );

            $archived_count++;
        }

        // Log archiving
        $log = \FormFlowPro\Logs\Log_Manager::get_instance();
        $log->info('Submissions archived', [
            'count' => $archived_count,
            'archive_days' => $archive_days,
        ]);
    }

    /**
     * Create archive file
     */
    private function create_archive_file(object $submission): void
    {
        $upload_dir = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/formflow-archives';

        if (!file_exists($archive_dir)) {
            wp_mkdir_p($archive_dir);
        }

        $year = date('Y', strtotime($submission->created_at));
        $month = date('m', strtotime($submission->created_at));
        $year_month_dir = "$archive_dir/$year/$month";

        if (!file_exists($year_month_dir)) {
            wp_mkdir_p($year_month_dir);
        }

        $filename = "submission-{$submission->id}.json";
        $filepath = "$year_month_dir/$filename";

        // Prepare archive data
        $archive_data = [
            'id' => $submission->id,
            'form_id' => $submission->form_id,
            'form_data' => json_decode($submission->form_data, true),
            'metadata' => json_decode($submission->metadata, true),
            'status' => $submission->status,
            'signature_document_id' => $submission->signature_document_id,
            'signature_status' => $submission->signature_status,
            'signature_completed_at' => $submission->signature_completed_at,
            'ip_address' => $submission->ip_address,
            'user_agent' => $submission->user_agent,
            'created_at' => $submission->created_at,
            'updated_at' => $submission->updated_at,
            'archived_at' => current_time('mysql'),
        ];

        file_put_contents($filepath, wp_json_encode($archive_data, JSON_PRETTY_PRINT));
    }

    /**
     * Restore submission from archive
     */
    public function restore_submission(int $submission_id): bool
    {
        $upload_dir = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/formflow-archives';

        // Find archive file
        $archive_file = $this->find_archive_file($archive_dir, $submission_id);

        if (!$archive_file) {
            return false;
        }

        $archive_data = json_decode(file_get_contents($archive_file), true);

        if (!$archive_data) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';

        // Restore submission
        $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $submission_id],
            ['%s', '%s'],
            ['%d']
        );

        return true;
    }

    /**
     * Find archive file
     */
    private function find_archive_file(string $archive_dir, int $submission_id): ?string
    {
        $pattern = "$archive_dir/*/*/submission-{$submission_id}.json";
        $files = glob($pattern);

        return $files[0] ?? null;
    }

    /**
     * Get archive statistics
     */
    public function get_archive_stats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'formflow_submissions';

        $total_archived = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'archived'"
        );

        $upload_dir = wp_upload_dir();
        $archive_dir = $upload_dir['basedir'] . '/formflow-archives';
        $archive_size = 0;

        if (file_exists($archive_dir)) {
            $archive_size = $this->get_directory_size($archive_dir);
        }

        return [
            'total_archived' => $total_archived,
            'archive_size_mb' => round($archive_size / 1024 / 1024, 2),
            'archive_dir' => $archive_dir,
        ];
    }

    /**
     * Get directory size
     */
    private function get_directory_size(string $dir): int
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
