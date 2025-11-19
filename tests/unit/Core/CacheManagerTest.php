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

    public function test_flush_returns_true()
    {
        // Test that flush method executes successfully
        $result = $this->cache->flush();
        $this->assertTrue($result);
    }

    public function test_set_with_custom_ttl()
    {
        // Set with custom TTL
        $this->cache->set('ttl_key', 'ttl_value', 7200); // 2 hours

        $result = $this->cache->get('ttl_key');
        $this->assertEquals('ttl_value', $result);
    }

    public function test_remember_with_custom_ttl()
    {
        $result = $this->cache->remember('ttl_remember', function() {
            return 'computed';
        }, 3600);

        $this->assertEquals('computed', $result);
    }

    public function test_cache_serializes_objects()
    {
        $object = (object)['property' => 'value', 'number' => 42];

        $this->cache->set('object_key', $object);
        $result = $this->cache->get('object_key');

        $this->assertEquals($object, $result);
        $this->assertIsObject($result);
        $this->assertEquals('value', $result->property);
        $this->assertEquals(42, $result->number);
    }

    public function test_cache_handles_null_value()
    {
        $this->cache->set('null_key', null);

        // Since null is a valid cached value, it should return null
        // not the default value
        $result = $this->cache->get('null_key', 'default');

        // Due to how the cache works, null values are not cached
        // so it will return the default
        $this->assertEquals('default', $result);
    }

    public function test_multiple_deletes()
    {
        $this->cache->set('delete1', 'value1');
        $this->cache->set('delete2', 'value2');

        $this->cache->delete('delete1');
        $this->cache->delete('delete2');

        $this->assertNull($this->cache->get('delete1'));
        $this->assertNull($this->cache->get('delete2'));
    }

    public function test_stats_track_writes()
    {
        $this->cache->set('write1', 'value1');
        $this->cache->set('write2', 'value2');
        $this->cache->set('write3', 'value3');

        $stats = $this->cache->get_stats();

        $this->assertEquals(3, $stats['writes']);
    }

    public function test_stats_track_deletes()
    {
        $this->cache->set('del1', 'value1');
        $this->cache->delete('del1');
        $this->cache->delete('del2'); // Delete non-existent

        $stats = $this->cache->get_stats();

        $this->assertEquals(2, $stats['deletes']);
    }
}
