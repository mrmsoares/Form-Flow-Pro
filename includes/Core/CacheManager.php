<?php
/**
 * Cache Manager - Multi-tier caching system for performance optimization.
 *
 * Implements a sophisticated caching strategy:
 * - L1: Object Cache (Redis/Memcached via WP Object Cache)
 * - L2: Transient Cache (WordPress transients)
 * - L3: Database Cache (fallback table)
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro\Core;

/**
 * Cache Manager class.
 *
 * Provides high-performance caching with automatic failover
 * and cache warming capabilities.
 *
 * @since 2.0.0
 */
class CacheManager
{
    /**
     * Cache version for invalidation.
     *
     * @since 2.0.0
     * @var string
     */
    const CACHE_VERSION = '2.0.0';

    /**
     * Cache key prefix.
     *
     * @since 2.0.0
     * @var string
     */
    const CACHE_PREFIX = 'formflow_';

    /**
     * Default TTL in seconds (1 hour).
     *
     * @since 2.0.0
     * @var int
     */
    const DEFAULT_TTL = 3600;

    /**
     * WordPress database object.
     *
     * @since 2.0.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Cache enabled flag.
     *
     * @since 2.0.0
     * @var bool
     */
    private $cache_enabled;

    /**
     * Object cache available flag.
     *
     * @since 2.0.0
     * @var bool
     */
    private $has_object_cache;

    /**
     * Cache statistics.
     *
     * @since 2.0.0
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    /**
     * Constructor.
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->cache_enabled = (bool) get_option('formflow_cache_enabled', true);
        $this->has_object_cache = wp_using_ext_object_cache();
    }

    /**
     * Get cached value with multi-tier fallback.
     *
     * @since 2.0.0
     * @param string $key Cache key.
     * @param mixed $default Default value if not found.
     * @return mixed Cached value or default.
     */
    public function get($key, $default = null)
    {
        if (!$this->cache_enabled) {
            $this->stats['misses']++;
            return $default;
        }

        $cache_key = $this->get_cache_key($key);

        // Try L1: Object Cache (Redis/Memcached)
        if ($this->has_object_cache) {
            $value = wp_cache_get($cache_key, 'formflow');
            if ($value !== false) {
                $this->stats['hits']++;
                return $this->maybe_unserialize($value);
            }
        }

        // Try L2: Transient Cache
        $value = get_transient($cache_key);
        if ($value !== false) {
            $this->stats['hits']++;

            // Warm up L1 cache if available
            if ($this->has_object_cache) {
                wp_cache_set($cache_key, $value, 'formflow', $this->get_ttl());
            }

            return $this->maybe_unserialize($value);
        }

        // Try L3: Database Cache
        $value = $this->get_from_database($cache_key);
        if ($value !== null) {
            $this->stats['hits']++;

            // Warm up higher cache layers
            if ($this->has_object_cache) {
                wp_cache_set($cache_key, $value, 'formflow', $this->get_ttl());
            }
            set_transient($cache_key, $value, $this->get_ttl());

            return $this->maybe_unserialize($value);
        }

        $this->stats['misses']++;
        return $default;
    }

    /**
     * Set cached value in all cache layers.
     *
     * @since 2.0.0
     * @param string $key Cache key.
     * @param mixed $value Value to cache.
     * @param int|null $ttl Time to live in seconds.
     * @return bool True on success.
     */
    public function set($key, $value, $ttl = null)
    {
        if (!$this->cache_enabled) {
            return false;
        }

        $cache_key = $this->get_cache_key($key);
        $ttl = $ttl ?? $this->get_ttl();
        $serialized_value = $this->maybe_serialize($value);

        // Set in all cache layers for redundancy
        $success = true;

        // L1: Object Cache
        if ($this->has_object_cache) {
            wp_cache_set($cache_key, $serialized_value, 'formflow', $ttl);
        }

        // L2: Transient Cache
        set_transient($cache_key, $serialized_value, $ttl);

        // L3: Database Cache
        $success = $this->set_in_database($cache_key, $serialized_value, $ttl);

        if ($success) {
            $this->stats['writes']++;
        }

        return $success;
    }

    /**
     * Delete cached value from all layers.
     *
     * @since 2.0.0
     * @param string $key Cache key.
     * @return bool True on success.
     */
    public function delete($key)
    {
        if (!$this->cache_enabled) {
            return false;
        }

        $cache_key = $this->get_cache_key($key);

        // Delete from all layers
        if ($this->has_object_cache) {
            wp_cache_delete($cache_key, 'formflow');
        }

        delete_transient($cache_key);
        $this->delete_from_database($cache_key);

        $this->stats['deletes']++;

        return true;
    }

    /**
     * Flush all plugin cache.
     *
     * @since 2.0.0
     * @return bool True on success.
     */
    public function flush()
    {
        // Clear object cache group
        if ($this->has_object_cache && function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('formflow');
        }

        // Clear all transients with our prefix
        $this->delete_all_transients();

        // Clear database cache
        $this->wpdb->query(
            "DELETE FROM {$this->wpdb->prefix}formflow_cache"
        );

        return true;
    }

    /**
     * Flush cache by pattern.
     *
     * @since 2.0.0
     * @param string $pattern Pattern to match (supports * wildcard).
     * @return int Number of keys deleted.
     */
    public function flush_pattern($pattern)
    {
        $pattern = $this->get_cache_key($pattern);
        $pattern = str_replace('*', '%', $pattern);
        $count = 0;

        // Delete from database cache
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}formflow_cache WHERE cache_key LIKE %s",
                $pattern
            )
        );

        if ($result) {
            $count += $result;
        }

        // Delete matching transients
        $this->delete_transients_by_pattern($pattern);

        return $count;
    }

    /**
     * Get cache statistics.
     *
     * @since 2.0.0
     * @return array Cache stats.
     */
    public function get_stats()
    {
        $total_requests = $this->stats['hits'] + $this->stats['misses'];
        $hit_rate = $total_requests > 0
            ? round(($this->stats['hits'] / $total_requests) * 100, 2)
            : 0;

        return array_merge($this->stats, [
            'total_requests' => $total_requests,
            'hit_rate' => $hit_rate,
            'enabled' => $this->cache_enabled,
            'has_object_cache' => $this->has_object_cache,
        ]);
    }

    /**
     * Remember - cache with callback for value generation.
     *
     * @since 2.0.0
     * @param string $key Cache key.
     * @param callable $callback Callback to generate value if not cached.
     * @param int|null $ttl Time to live in seconds.
     * @return mixed Cached or generated value.
     */
    public function remember($key, callable $callback, $ttl = null)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get value from database cache.
     *
     * @since 2.0.0
     * @param string $cache_key Cache key.
     * @return mixed|null Cached value or null.
     */
    private function get_from_database($cache_key)
    {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT cache_value, expires_at
                FROM {$this->wpdb->prefix}formflow_cache
                WHERE cache_key = %s
                AND expires_at > NOW()",
                $cache_key
            )
        );

        if ($result && isset($result->cache_value)) {
            return $result->cache_value;
        }

        return null;
    }

    /**
     * Set value in database cache.
     *
     * @since 2.0.0
     * @param string $cache_key Cache key.
     * @param mixed $value Value to cache.
     * @param int $ttl Time to live in seconds.
     * @return bool True on success.
     */
    private function set_in_database($cache_key, $value, $ttl)
    {
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        $result = $this->wpdb->replace(
            $this->wpdb->prefix . 'formflow_cache',
            [
                'cache_key' => $cache_key,
                'cache_value' => $value,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Delete value from database cache.
     *
     * @since 2.0.0
     * @param string $cache_key Cache key.
     * @return bool True on success.
     */
    private function delete_from_database($cache_key)
    {
        $result = $this->wpdb->delete(
            $this->wpdb->prefix . 'formflow_cache',
            ['cache_key' => $cache_key],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Delete all plugin transients.
     *
     * @since 2.0.0
     */
    private function delete_all_transients()
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
    }

    /**
     * Delete transients by pattern.
     *
     * @since 2.0.0
     * @param string $pattern SQL LIKE pattern.
     */
    private function delete_transients_by_pattern($pattern)
    {
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                '_transient_' . $pattern,
                '_transient_timeout_' . $pattern
            )
        );
    }

    /**
     * Get cache key with prefix and version.
     *
     * @since 2.0.0
     * @param string $key Raw cache key.
     * @return string Prefixed cache key.
     */
    private function get_cache_key($key)
    {
        return self::CACHE_PREFIX . self::CACHE_VERSION . '_' . $key;
    }

    /**
     * Get TTL from settings.
     *
     * @since 2.0.0
     * @return int TTL in seconds.
     */
    private function get_ttl()
    {
        return (int) get_option('formflow_cache_ttl', self::DEFAULT_TTL);
    }

    /**
     * Maybe serialize value.
     *
     * @since 2.0.0
     * @param mixed $value Value to serialize.
     * @return string|mixed Serialized value or original.
     */
    private function maybe_serialize($value)
    {
        if (is_array($value) || is_object($value)) {
            return serialize($value);
        }

        return $value;
    }

    /**
     * Maybe unserialize value.
     *
     * @since 2.0.0
     * @param mixed $value Value to unserialize.
     * @return mixed Unserialized value or original.
     */
    private function maybe_unserialize($value)
    {
        if (is_serialized($value)) {
            return unserialize($value);
        }

        return $value;
    }

    /**
     * Clean up expired cache entries.
     *
     * Scheduled to run via cron.
     *
     * @since 2.0.0
     * @return int Number of entries deleted.
     */
    public function cleanup_expired()
    {
        $result = $this->wpdb->query(
            "DELETE FROM {$this->wpdb->prefix}formflow_cache
            WHERE expires_at < NOW()"
        );

        return $result ? $result : 0;
    }

    /**
     * Warm up cache for common queries.
     *
     * @since 2.0.0
     */
    public function warm_cache()
    {
        // This will be implemented with specific cache warming strategies
        // based on frequently accessed data patterns
        do_action('formflow_cache_warm_up', $this);
    }
}
