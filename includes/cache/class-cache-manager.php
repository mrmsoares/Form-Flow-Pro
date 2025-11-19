<?php

declare(strict_types=1);

namespace FormFlowPro\Cache;

if (!defined('ABSPATH')) exit;

/**
 * Cache Manager - Multi-Driver Caching System
 */
class Cache_Manager
{
    private static ?self $instance = null;
    private $driver;
    private int $ttl;

    public static function get_instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->ttl = (int) get_option('formflow_cache_ttl', 3600);
        $this->init_driver();
    }

    private function init_driver(): void
    {
        $driver = get_option('formflow_cache_driver', 'database');

        switch ($driver) {
            case 'redis':
                if (class_exists('Redis')) {
                    $this->driver = new Redis_Driver();
                    break;
                }
            case 'memcached':
                if (class_exists('Memcached')) {
                    $this->driver = new Memcached_Driver();
                    break;
                }
            case 'apcu':
                if (function_exists('apcu_fetch')) {
                    $this->driver = new APCu_Driver();
                    break;
                }
            case 'file':
                $this->driver = new File_Driver();
                break;
            default:
                $this->driver = new Database_Driver();
        }
    }

    public function get(string $key, $default = null)
    {
        if (!FORMFLOW_CACHE_ENABLED) {
            return $default;
        }

        $value = $this->driver->get($this->prefix_key($key));
        return $value !== false ? $value : $default;
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        if (!FORMFLOW_CACHE_ENABLED) {
            return false;
        }

        $ttl = $ttl ?? $this->ttl;
        return $this->driver->set($this->prefix_key($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($this->prefix_key($key));
    }

    public function flush(): bool
    {
        return $this->driver->flush();
    }

    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function prefix_key(string $key): string
    {
        return 'formflow_' . $key;
    }
}

/**
 * Database Cache Driver
 */
class Database_Driver
{
    public function get(string $key)
    {
        return get_transient($key);
    }

    public function set(string $key, $value, int $ttl): bool
    {
        return set_transient($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return delete_transient($key);
    }

    public function flush(): bool
    {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_formflow_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_formflow_%'");
        return true;
    }
}

/**
 * File Cache Driver
 */
class File_Driver
{
    private string $cache_dir;

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->cache_dir = $upload_dir['basedir'] . '/formflow-cache';
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    public function get(string $key)
    {
        $file = $this->get_file_path($key);
        if (!file_exists($file)) {
            return false;
        }

        $data = file_get_contents($file);
        $data = unserialize($data);

        if ($data['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return $data['value'];
    }

    public function set(string $key, $value, int $ttl): bool
    {
        $file = $this->get_file_path($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
        ];
        return file_put_contents($file, serialize($data)) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->get_file_path($key);
        return file_exists($file) && unlink($file);
    }

    public function flush(): bool
    {
        $files = glob($this->cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    private function get_file_path(string $key): string
    {
        return $this->cache_dir . '/' . md5($key) . '.cache';
    }
}

/**
 * Redis Cache Driver
 */
class Redis_Driver
{
    private $redis;

    public function __construct()
    {
        $this->redis = new \Redis();
        $this->redis->connect(
            get_option('formflow_redis_host', '127.0.0.1'),
            (int) get_option('formflow_redis_port', 6379)
        );
    }

    public function get(string $key)
    {
        $value = $this->redis->get($key);
        return $value !== false ? unserialize($value) : false;
    }

    public function set(string $key, $value, int $ttl): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    public function delete(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys('formflow_*');
        if ($keys) {
            $this->redis->del($keys);
        }
        return true;
    }
}

/**
 * Memcached Cache Driver
 */
class Memcached_Driver
{
    private $memcached;

    public function __construct()
    {
        $this->memcached = new \Memcached();
        $this->memcached->addServer(
            get_option('formflow_memcached_host', '127.0.0.1'),
            (int) get_option('formflow_memcached_port', 11211)
        );
    }

    public function get(string $key)
    {
        return $this->memcached->get($key);
    }

    public function set(string $key, $value, int $ttl): bool
    {
        return $this->memcached->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->memcached->delete($key);
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
    }
}

/**
 * APCu Cache Driver
 */
class APCu_Driver
{
    public function get(string $key)
    {
        return apcu_fetch($key);
    }

    public function set(string $key, $value, int $ttl): bool
    {
        return apcu_store($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return apcu_delete($key);
    }

    public function flush(): bool
    {
        return apcu_clear_cache();
    }
}
