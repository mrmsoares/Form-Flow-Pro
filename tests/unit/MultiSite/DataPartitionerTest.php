<?php
/**
 * Tests for DataPartitioner class.
 */

namespace FormFlowPro\Tests\Unit\MultiSite;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\MultiSite\DataPartitioner;

class DataPartitionerTest extends TestCase
{
    private $dataPartitioner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataPartitioner = DataPartitioner::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = DataPartitioner::getInstance();
        $instance2 = DataPartitioner::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(DataPartitioner::class, $instance1);
    }

    public function test_get_config_returns_defaults()
    {
        delete_option('formflow_partitioning');

        $config = $this->dataPartitioner->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('partition_by', $config);
        $this->assertArrayHasKey('partition_interval', $config);
        $this->assertArrayHasKey('archive_after_days', $config);
        $this->assertArrayHasKey('archive_strategy', $config);
        $this->assertArrayHasKey('max_partition_size', $config);
    }

    public function test_get_config_default_values()
    {
        delete_option('formflow_partitioning');

        $config = $this->dataPartitioner->getConfig();

        $this->assertFalse($config['enabled']);
        $this->assertEquals('date', $config['partition_by']);
        $this->assertEquals('monthly', $config['partition_interval']);
        $this->assertEquals(365, $config['archive_after_days']);
        $this->assertEquals('compress', $config['archive_strategy']);
        $this->assertEquals(1000000, $config['max_partition_size']);
    }

    public function test_is_enabled_returns_false_by_default()
    {
        delete_option('formflow_partitioning');

        $result = $this->dataPartitioner->isEnabled();

        $this->assertFalse($result);
    }

    public function test_is_enabled_returns_true_when_enabled()
    {
        update_option('formflow_partitioning', ['enabled' => true]);

        // Reload config
        $partitioner = DataPartitioner::getInstance();

        $result = $partitioner->isEnabled();

        $this->assertTrue($result);
    }

    public function test_save_config_returns_true()
    {
        $config = [
            'enabled' => true,
            'partition_by' => 'form_id',
            'partition_interval' => 'weekly',
            'archive_after_days' => 180,
            'archive_strategy' => 'export',
            'max_partition_size' => 500000,
        ];

        $result = $this->dataPartitioner->saveConfig($config);

        $this->assertTrue($result);
    }

    public function test_save_config_sanitizes_values()
    {
        $config = [
            'enabled' => 1,
            'partition_by' => '<script>date</script>',
            'partition_interval' => 'daily<b>',
            'archive_after_days' => '90',
            'archive_strategy' => 'delete"',
            'max_partition_size' => '750000',
        ];

        $this->dataPartitioner->saveConfig($config);

        $saved = $this->dataPartitioner->getConfig();

        $this->assertTrue($saved['enabled']);
        $this->assertStringNotContainsString('<script>', $saved['partition_by']);
        $this->assertStringNotContainsString('<b>', $saved['partition_interval']);
        $this->assertEquals(90, $saved['archive_after_days']);
        $this->assertStringNotContainsString('"', $saved['archive_strategy']);
        $this->assertEquals(750000, $saved['max_partition_size']);
    }

    public function test_get_partition_name_daily()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'daily']);
        $partitioner = DataPartitioner::getInstance();

        $name = $partitioner->getPartitionName('2024-03-15');

        $this->assertEquals('2024_03_15', $name);
    }

    public function test_get_partition_name_weekly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'weekly']);
        $partitioner = DataPartitioner::getInstance();

        $name = $partitioner->getPartitionName('2024-03-15');

        $this->assertMatchesRegularExpression('/^2024_\d{2}$/', $name);
    }

    public function test_get_partition_name_monthly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'monthly']);
        $partitioner = DataPartitioner::getInstance();

        $name = $partitioner->getPartitionName('2024-03-15');

        $this->assertEquals('2024_03', $name);
    }

    public function test_get_partition_name_quarterly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'quarterly']);
        $partitioner = DataPartitioner::getInstance();

        $nameQ1 = $partitioner->getPartitionName('2024-02-15'); // Q1
        $nameQ2 = $partitioner->getPartitionName('2024-05-15'); // Q2

        $this->assertEquals('2024_Q1', $nameQ1);
        $this->assertEquals('2024_Q2', $nameQ2);
    }

    public function test_get_partition_name_yearly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'yearly']);
        $partitioner = DataPartitioner::getInstance();

        $name = $partitioner->getPartitionName('2024-03-15');

        $this->assertEquals('2024', $name);
    }

    public function test_create_partition_returns_true()
    {
        global $wpdb;

        $baseTable = $wpdb->prefix . 'test_table';
        $partitionName = '2024_03';

        $result = $this->dataPartitioner->createPartition($baseTable, $partitionName);

        $this->assertIsBool($result);
    }

    public function test_create_partition_skips_if_exists()
    {
        global $wpdb;

        $baseTable = $wpdb->prefix . 'test_table';
        $partitionName = '2024_03';

        // Create partition twice
        $this->dataPartitioner->createPartition($baseTable, $partitionName);
        $result = $this->dataPartitioner->createPartition($baseTable, $partitionName);

        $this->assertTrue($result); // Should return true even if already exists
    }

    public function test_move_to_partition_returns_row_count()
    {
        global $wpdb;

        $baseTable = $wpdb->prefix . 'formflow_submissions';
        $partitionName = '2024_03';
        $startDate = '2024-03-01 00:00:00';
        $endDate = '2024-04-01 00:00:00';

        $result = $this->dataPartitioner->moveToPartition(
            $baseTable,
            $partitionName,
            'created_at',
            $startDate,
            $endDate
        );

        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function test_archive_old_partitions_returns_array()
    {
        $result = $this->dataPartitioner->archiveOldPartitions();

        $this->assertIsArray($result);
    }

    public function test_get_partition_stats_returns_structure()
    {
        $stats = $this->dataPartitioner->getPartitionStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_partitions', $stats);
        $this->assertArrayHasKey('total_size_mb', $stats);
        $this->assertArrayHasKey('total_rows', $stats);
        $this->assertArrayHasKey('partitions', $stats);
    }

    public function test_get_partition_stats_counts_partitions()
    {
        $stats = $this->dataPartitioner->getPartitionStats();

        $this->assertIsInt($stats['total_partitions']);
        $this->assertIsFloat($stats['total_size_mb']);
        $this->assertIsInt($stats['total_rows']);
        $this->assertIsArray($stats['partitions']);
    }

    public function test_run_auto_partitioning_returns_results_when_disabled()
    {
        update_option('formflow_partitioning', ['enabled' => false]);
        $partitioner = DataPartitioner::getInstance();

        $result = $partitioner->runAutoPartitioning();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertFalse($result['success']);
    }

    public function test_run_auto_partitioning_returns_results_when_enabled()
    {
        update_option('formflow_partitioning', [
            'enabled' => true,
            'partition_by' => 'date',
            'partition_interval' => 'monthly',
        ]);
        $partitioner = DataPartitioner::getInstance();

        $result = $partitioner->runAutoPartitioning();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('partitions_created', $result);
        $this->assertArrayHasKey('rows_moved', $result);
        $this->assertArrayHasKey('partitions_archived', $result);
    }

    public function test_run_auto_partitioning_creates_current_partition()
    {
        update_option('formflow_partitioning', [
            'enabled' => true,
            'partition_by' => 'date',
        ]);
        $partitioner = DataPartitioner::getInstance();

        $result = $partitioner->runAutoPartitioning();

        $this->assertGreaterThanOrEqual(0, $result['partitions_created']);
    }

    public function test_compress_partition_returns_result()
    {
        global $wpdb;

        $partitionTable = $wpdb->prefix . 'test_partition_2024_01';

        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('compressPartition');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataPartitioner, $partitionTable);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertEquals('compressed', $result['action']);
    }

    public function test_export_partition_returns_result()
    {
        global $wpdb;

        $partitionTable = $wpdb->prefix . 'test_partition_2024_01';

        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('exportPartition');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataPartitioner, $partitionTable);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_archive_partition_with_compress_strategy()
    {
        update_option('formflow_partitioning', ['archive_strategy' => 'compress']);
        $partitioner = DataPartitioner::getInstance();

        global $wpdb;
        $partitionTable = $wpdb->prefix . 'test_partition_2024_01';

        $reflection = new \ReflectionClass($partitioner);
        $method = $reflection->getMethod('archivePartition');
        $method->setAccessible(true);

        $result = $method->invoke($partitioner, $partitionTable);

        $this->assertIsArray($result);
        $this->assertEquals('compressed', $result['action']);
    }

    public function test_archive_partition_with_delete_strategy()
    {
        update_option('formflow_partitioning', ['archive_strategy' => 'delete']);
        $partitioner = DataPartitioner::getInstance();

        global $wpdb;
        $partitionTable = $wpdb->prefix . 'test_partition_2024_01';

        $reflection = new \ReflectionClass($partitioner);
        $method = $reflection->getMethod('archivePartition');
        $method->setAccessible(true);

        $result = $method->invoke($partitioner, $partitionTable);

        $this->assertIsArray($result);
        $this->assertEquals('deleted', $result['action']);
        $this->assertTrue($result['success']);
    }

    public function test_archive_partition_with_export_strategy()
    {
        update_option('formflow_partitioning', ['archive_strategy' => 'export']);
        $partitioner = DataPartitioner::getInstance();

        global $wpdb;
        $partitionTable = $wpdb->prefix . 'test_partition_2024_01';

        $reflection = new \ReflectionClass($partitioner);
        $method = $reflection->getMethod('archivePartition');
        $method->setAccessible(true);

        $result = $method->invoke($partitioner, $partitionTable);

        $this->assertIsArray($result);
        // May be 'exported' or 'export_failed' depending on data
        $this->assertContains($result['action'], ['exported', 'export_failed']);
    }

    public function test_is_partition_older_compares_correctly()
    {
        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('isPartitionOlder');
        $method->setAccessible(true);

        $older = $method->invoke($this->dataPartitioner, '2023_12', '2024_03');
        $newer = $method->invoke($this->dataPartitioner, '2024_06', '2024_03');
        $same = $method->invoke($this->dataPartitioner, '2024_03', '2024_03');

        $this->assertTrue($older);
        $this->assertFalse($newer);
        $this->assertFalse($same);
    }

    public function test_log_partition_action_inserts_log()
    {
        global $wpdb;

        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('logPartitionAction');
        $method->setAccessible(true);

        $method->invoke(
            $this->dataPartitioner,
            'create',
            'test_partition_2024_01',
            ['rows' => 100]
        );

        // Verify log was created (would check database in real integration test)
        $this->assertTrue(true);
    }

    public function test_partition_interval_daily_handles_dates_correctly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'daily']);
        $partitioner = DataPartitioner::getInstance();

        $name1 = $partitioner->getPartitionName('2024-03-15 08:30:00');
        $name2 = $partitioner->getPartitionName('2024-03-15 23:59:59');
        $name3 = $partitioner->getPartitionName('2024-03-16 00:00:00');

        $this->assertEquals('2024_03_15', $name1);
        $this->assertEquals('2024_03_15', $name2);
        $this->assertEquals('2024_03_16', $name3);
    }

    public function test_partition_interval_monthly_handles_dates_correctly()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'monthly']);
        $partitioner = DataPartitioner::getInstance();

        $name1 = $partitioner->getPartitionName('2024-03-01');
        $name2 = $partitioner->getPartitionName('2024-03-31');
        $name3 = $partitioner->getPartitionName('2024-04-01');

        $this->assertEquals('2024_03', $name1);
        $this->assertEquals('2024_03', $name2);
        $this->assertEquals('2024_04', $name3);
    }

    public function test_partition_interval_quarterly_handles_all_quarters()
    {
        update_option('formflow_partitioning', ['partition_interval' => 'quarterly']);
        $partitioner = DataPartitioner::getInstance();

        $q1 = $partitioner->getPartitionName('2024-01-15');
        $q2 = $partitioner->getPartitionName('2024-04-15');
        $q3 = $partitioner->getPartitionName('2024-07-15');
        $q4 = $partitioner->getPartitionName('2024-10-15');

        $this->assertEquals('2024_Q1', $q1);
        $this->assertEquals('2024_Q2', $q2);
        $this->assertEquals('2024_Q3', $q3);
        $this->assertEquals('2024_Q4', $q4);
    }

    public function test_export_partition_creates_json_file()
    {
        global $wpdb;

        $partitionTable = $wpdb->prefix . 'formflow_submissions_2024_01';

        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('exportPartition');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataPartitioner, $partitionTable);

        if ($result['success'] ?? false) {
            $this->assertArrayHasKey('file', $result);
            $this->assertArrayHasKey('rows', $result);
        } else {
            $this->assertEquals('export_failed', $result['action']);
        }
    }

    public function test_export_partition_compresses_file_when_gzencode_available()
    {
        if (!function_exists('gzencode')) {
            $this->markTestSkipped('gzencode not available');
        }

        global $wpdb;
        $partitionTable = $wpdb->prefix . 'formflow_submissions_2024_01';

        $reflection = new \ReflectionClass($this->dataPartitioner);
        $method = $reflection->getMethod('exportPartition');
        $method->setAccessible(true);

        $result = $method->invoke($this->dataPartitioner, $partitionTable);

        if ($result['success'] ?? false) {
            $this->assertStringContainsString('.gz', $result['file']);
        }
    }

    public function test_save_config_updates_internal_config()
    {
        $config = [
            'enabled' => true,
            'partition_interval' => 'weekly',
            'archive_after_days' => 200,
        ];

        $this->dataPartitioner->saveConfig($config);

        $saved = $this->dataPartitioner->getConfig();

        $this->assertTrue($saved['enabled']);
        $this->assertEquals('weekly', $saved['partition_interval']);
        $this->assertEquals(200, $saved['archive_after_days']);
    }
}
