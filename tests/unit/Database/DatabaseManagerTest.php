<?php
/**
 * Tests for DatabaseManager class.
 */

namespace FormFlowPro\Tests\Unit\Database;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Database\DatabaseManager;

class DatabaseManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new DatabaseManager();
    }

    public function test_get_table_name()
    {
        $result = $this->manager->get_table_name('forms');
        $this->assertEquals('wp_formflow_forms', $result);
    }

    public function test_get_charset_collate()
    {
        $result = $this->manager->get_charset_collate();
        $this->assertStringContainsString('utf8mb4', $result);
    }

    public function test_table_exists_returns_false_for_nonexistent_table()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_var', null);
        
        $result = $this->manager->table_exists('nonexistent');
        $this->assertFalse($result);
    }

    public function test_table_exists_returns_true_for_existing_table()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_var', 'wp_formflow_forms');
        
        $result = $this->manager->table_exists('forms');
        $this->assertTrue($result);
    }
}
