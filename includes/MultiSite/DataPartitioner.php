<?php

declare(strict_types=1);

/**
 * Data Partitioner
 *
 * Handles data partitioning for high-scale deployments.
 *
 * @package FormFlowPro\MultiSite
 * @since 2.3.0
 */

namespace FormFlowPro\MultiSite;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Data Partitioner Class
 */
class DataPartitioner
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Configuration
     *
     * @var array
     */
    private array $config;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load configuration
     *
     * @return void
     */
    private function loadConfig(): void
    {
        $defaults = [
            'enabled' => false,
            'partition_by' => 'date', // date, form_id, hash
            'partition_interval' => 'monthly', // daily, weekly, monthly, quarterly, yearly
            'archive_after_days' => 365,
            'archive_strategy' => 'compress', // compress, delete, export
            'max_partition_size' => 1000000, // rows
        ];

        $this->config = wp_parse_args(
            get_option('formflow_partitioning', []),
            $defaults
        );
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Save configuration
     *
     * @param array $config Configuration
     * @return bool
     */
    public function saveConfig(array $config): bool
    {
        $sanitized = [
            'enabled' => !empty($config['enabled']),
            'partition_by' => sanitize_text_field($config['partition_by'] ?? 'date'),
            'partition_interval' => sanitize_text_field($config['partition_interval'] ?? 'monthly'),
            'archive_after_days' => (int) ($config['archive_after_days'] ?? 365),
            'archive_strategy' => sanitize_text_field($config['archive_strategy'] ?? 'compress'),
            'max_partition_size' => (int) ($config['max_partition_size'] ?? 1000000),
        ];

        $result = update_option('formflow_partitioning', $sanitized);

        if ($result) {
            $this->config = $sanitized;
        }

        return $result;
    }

    /**
     * Check if partitioning is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    /**
     * Get partition name for date
     *
     * @param string $date Date string
     * @return string
     */
    public function getPartitionName(string $date): string
    {
        $timestamp = strtotime($date);

        switch ($this->config['partition_interval']) {
            case 'daily':
                return date('Y_m_d', $timestamp);
            case 'weekly':
                return date('Y_W', $timestamp);
            case 'monthly':
                return date('Y_m', $timestamp);
            case 'quarterly':
                $quarter = ceil(date('n', $timestamp) / 3);
                return date('Y', $timestamp) . '_Q' . $quarter;
            case 'yearly':
                return date('Y', $timestamp);
            default:
                return date('Y_m', $timestamp);
        }
    }

    /**
     * Create partition table
     *
     * @param string $baseTable Base table name
     * @param string $partitionName Partition name
     * @return bool
     */
    public function createPartition(string $baseTable, string $partitionName): bool
    {
        global $wpdb;

        $partitionTable = $baseTable . '_' . $partitionName;

        // Check if partition already exists
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $partitionTable)
        );

        if ($tableExists) {
            return true;
        }

        // Create partition table with same structure
        $result = $wpdb->query(
            "CREATE TABLE {$partitionTable} LIKE {$baseTable}"
        );

        if ($result !== false) {
            $this->logPartitionAction('create', $partitionTable);
        }

        return $result !== false;
    }

    /**
     * Move data to partition
     *
     * @param string $baseTable Base table name
     * @param string $partitionName Partition name
     * @param string $dateColumn Date column name
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return int Number of rows moved
     */
    public function moveToPartition(
        string $baseTable,
        string $partitionName,
        string $dateColumn,
        string $startDate,
        string $endDate
    ): int {
        global $wpdb;

        $partitionTable = $baseTable . '_' . $partitionName;

        // Ensure partition exists
        $this->createPartition($baseTable, $partitionName);

        // Begin transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Insert into partition
            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$partitionTable}
                 SELECT * FROM {$baseTable}
                 WHERE {$dateColumn} >= %s AND {$dateColumn} < %s",
                $startDate,
                $endDate
            ));

            // Delete from base table
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$baseTable}
                 WHERE {$dateColumn} >= %s AND {$dateColumn} < %s",
                $startDate,
                $endDate
            ));

            $wpdb->query('COMMIT');

            $this->logPartitionAction('move', $partitionTable, ['rows' => $inserted]);

            return (int) $inserted;
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->logPartitionAction('error', $partitionTable, ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Archive old partitions
     *
     * @return array Results
     */
    public function archiveOldPartitions(): array
    {
        global $wpdb;

        $results = [];
        $archiveDate = date('Y-m-d', strtotime("-{$this->config['archive_after_days']} days"));
        $archivePartition = $this->getPartitionName($archiveDate);

        $tables = [
            $wpdb->prefix . 'formflow_submissions',
            $wpdb->prefix . 'formflow_logs',
        ];

        foreach ($tables as $baseTable) {
            // Find old partitions
            $pattern = str_replace('_', '\\_', $baseTable) . '_%';
            $partitions = $wpdb->get_col($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $pattern
            ));

            foreach ($partitions as $partition) {
                // Extract partition name
                $partitionName = str_replace($baseTable . '_', '', $partition);

                // Check if older than archive threshold
                if ($this->isPartitionOlder($partitionName, $archivePartition)) {
                    $result = $this->archivePartition($partition);
                    $results[$partition] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * Check if partition is older
     *
     * @param string $partitionName Partition name
     * @param string $threshold Threshold partition name
     * @return bool
     */
    private function isPartitionOlder(string $partitionName, string $threshold): bool
    {
        // Simple string comparison works for our naming scheme
        return strcmp($partitionName, $threshold) < 0;
    }

    /**
     * Archive a partition
     *
     * @param string $partitionTable Partition table name
     * @return array Result
     */
    private function archivePartition(string $partitionTable): array
    {
        global $wpdb;

        switch ($this->config['archive_strategy']) {
            case 'compress':
                return $this->compressPartition($partitionTable);

            case 'delete':
                $wpdb->query("DROP TABLE IF EXISTS {$partitionTable}");
                $this->logPartitionAction('delete', $partitionTable);
                return ['action' => 'deleted', 'success' => true];

            case 'export':
                return $this->exportPartition($partitionTable);

            default:
                return ['action' => 'none', 'success' => false];
        }
    }

    /**
     * Compress partition (optimize table)
     *
     * @param string $partitionTable Partition table
     * @return array Result
     */
    private function compressPartition(string $partitionTable): array
    {
        global $wpdb;

        // Optimize table
        $wpdb->query("OPTIMIZE TABLE {$partitionTable}");

        // Get table size
        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT ROUND((data_length + index_length) / 1024 / 1024, 2)
             FROM information_schema.tables
             WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $partitionTable
        ));

        $this->logPartitionAction('compress', $partitionTable, ['size_mb' => $size]);

        return [
            'action' => 'compressed',
            'success' => true,
            'size_mb' => $size,
        ];
    }

    /**
     * Export partition to file
     *
     * @param string $partitionTable Partition table
     * @return array Result
     */
    private function exportPartition(string $partitionTable): array
    {
        global $wpdb;

        $uploadDir = wp_upload_dir();
        $exportDir = $uploadDir['basedir'] . '/formflow-archives';

        if (!file_exists($exportDir)) {
            wp_mkdir_p($exportDir);
        }

        $filename = $partitionTable . '_' . date('Y-m-d-His') . '.json';
        $filepath = $exportDir . '/' . $filename;

        // Export data
        $data = $wpdb->get_results("SELECT * FROM {$partitionTable}", ARRAY_A);

        if ($data) {
            file_put_contents($filepath, wp_json_encode($data, JSON_PRETTY_PRINT));

            // Compress the file
            if (function_exists('gzencode')) {
                $compressed = gzencode(file_get_contents($filepath));
                file_put_contents($filepath . '.gz', $compressed);
                unlink($filepath);
                $filepath .= '.gz';
            }

            // Drop the table after successful export
            $wpdb->query("DROP TABLE IF EXISTS {$partitionTable}");

            $this->logPartitionAction('export', $partitionTable, ['file' => $filepath]);

            return [
                'action' => 'exported',
                'success' => true,
                'file' => $filepath,
                'rows' => count($data),
            ];
        }

        return [
            'action' => 'export_failed',
            'success' => false,
        ];
    }

    /**
     * Get partition statistics
     *
     * @return array
     */
    public function getPartitionStats(): array
    {
        global $wpdb;

        $stats = [
            'total_partitions' => 0,
            'total_size_mb' => 0,
            'total_rows' => 0,
            'partitions' => [],
        ];

        $baseTables = [
            $wpdb->prefix . 'formflow_submissions',
            $wpdb->prefix . 'formflow_logs',
        ];

        foreach ($baseTables as $baseTable) {
            $pattern = str_replace('_', '\\_', $baseTable) . '_%';
            $partitions = $wpdb->get_col($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $pattern
            ));

            foreach ($partitions as $partition) {
                $info = $wpdb->get_row($wpdb->prepare(
                    "SELECT
                        table_rows as rows,
                        ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
                     FROM information_schema.tables
                     WHERE table_schema = %s AND table_name = %s",
                    DB_NAME,
                    $partition
                ));

                if ($info) {
                    $stats['total_partitions']++;
                    $stats['total_size_mb'] += (float) $info->size_mb;
                    $stats['total_rows'] += (int) $info->rows;
                    $stats['partitions'][] = [
                        'name' => $partition,
                        'rows' => (int) $info->rows,
                        'size_mb' => (float) $info->size_mb,
                    ];
                }
            }
        }

        return $stats;
    }

    /**
     * Run automatic partitioning
     *
     * @return array Results
     */
    public function runAutoPartitioning(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Partitioning not enabled'];
        }

        global $wpdb;

        $results = [
            'partitions_created' => 0,
            'rows_moved' => 0,
            'partitions_archived' => 0,
        ];

        $baseTable = $wpdb->prefix . 'formflow_submissions';
        $currentPartition = $this->getPartitionName(current_time('mysql'));

        // Create current partition
        if ($this->createPartition($baseTable, $currentPartition)) {
            $results['partitions_created']++;
        }

        // Move old data to partitions
        if ($this->config['partition_by'] === 'date') {
            $oldestDate = $wpdb->get_var(
                "SELECT MIN(created_at) FROM {$baseTable}"
            );

            if ($oldestDate) {
                $archiveThreshold = date('Y-m-d', strtotime('-30 days'));

                if ($oldestDate < $archiveThreshold) {
                    $partitionName = $this->getPartitionName($oldestDate);
                    $startDate = $oldestDate;
                    $endDate = $archiveThreshold;

                    $moved = $this->moveToPartition(
                        $baseTable,
                        $partitionName,
                        'created_at',
                        $startDate,
                        $endDate
                    );

                    $results['rows_moved'] += $moved;
                }
            }
        }

        // Archive old partitions
        $archived = $this->archiveOldPartitions();
        $results['partitions_archived'] = count(array_filter($archived, function ($r) {
            return $r['success'] ?? false;
        }));

        return $results;
    }

    /**
     * Log partition action
     *
     * @param string $action Action type
     * @param string $partition Partition name
     * @param array $data Additional data
     * @return void
     */
    private function logPartitionAction(string $action, string $partition, array $data = []): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_logs',
            [
                'level' => 'info',
                'context' => 'partitioner',
                'message' => "Partition {$action}: {$partition}",
                'data' => wp_json_encode($data),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
}
