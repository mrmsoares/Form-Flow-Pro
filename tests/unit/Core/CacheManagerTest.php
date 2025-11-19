<?php
/**
 * Tests for CacheManager class.
 */

namespace FormFlowPro\Tests\Unit\Core;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Core\CacheManager;

class CacheManagerTest extends TestCase
{
    private $cache;

    protected function setUp(): void
    {
        parent::setUp();
        update_option('formflow_cache_enabled', true);
        $this->cache = new CacheManager();
    }

    public function test_set_and_get_simple_value()
    {
        $this->cache->set('test_key', 'test_value');
        $result = $this->cache->get('test_key');
        
        $this->assertEquals('test_value', $result);
    }

    public function test_set_and_get_array()
    {
        $data = ['foo' => 'bar', 'baz' => 123];
        $this->cache->set('array_key', $data);
        $result = $this->cache->get('array_key');
        
        $this->assertEquals($data, $result);
    }

    public function test_get_returns_default_for_missing_key()
    {
        $result = $this->cache->get('nonexistent', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function test_delete_removes_cached_value()
    {
        $this->cache->set('delete_test', 'value');
        $this->cache->delete('delete_test');
        
        $result = $this->cache->get('delete_test');
        $this->assertNull($result);
    }

    public function test_remember_returns_cached_value()
    {
        $callbackCalled = false;
        
        // First call - cache miss, callback executes
        $result1 = $this->cache->remember('remember_key', function() use (&$callbackCalled) {
            $callbackCalled = true;
            return 'computed_value';
        });
        
        $this->assertTrue($callbackCalled);
        $this->assertEquals('computed_value', $result1);
        
        // Reset flag
        $callbackCalled = false;
        
        // Second call - cache hit, callback should NOT execute
        $result2 = $this->cache->get('remember_key');
        
        $this->assertFalse($callbackCalled);
        $this->assertEquals('computed_value', $result2);
    }

    public function test_get_stats_returns_correct_structure()
    {
        $stats = $this->cache->get_stats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('enabled', $stats);
    }

    public function test_cache_disabled_returns_default()
    {
        update_option('formflow_cache_enabled', false);
        $cache = new CacheManager();
        
        $cache->set('test', 'value');
        $result = $cache->get('test', 'default');
        
        $this->assertEquals('default', $result);
    }

    public function test_hit_rate_calculation()
    {
        // Force some hits and misses
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        
        $this->cache->get('key1'); // hit
        $this->cache->get('key2'); // hit
        $this->cache->get('missing1'); // miss
        $this->cache->get('missing2'); // miss
        
        $stats = $this->cache->get_stats();
        
        // 2 hits, 2 misses = 50% hit rate
        $this->assertEquals(50.0, $stats['hit_rate']);
    }
}
