<?php
/**
 * Mock WordPress functions for unit testing.
 *
 * These are lightweight mocks for isolated unit tests.
 *
 * @package FormFlowPro
 * @subpackage Tests
 */

// WordPress constants
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

// Global mock data storage
global $wp_options, $wp_transients, $wp_cache, $wpdb;

$wp_options = [];
$wp_transients = [];
$wp_cache = [];

// Mock wpdb class
if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $options = 'wp_options';
        public $insert_id = 1;
        public $last_error = '';
        public $last_query = '';

        private $mock_results = [];
        private $mock_inserts = [];

        public function prepare($query, ...$args)
        {
            $this->last_query = vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
            return $this->last_query;
        }

        public function get_row($query, $output = OBJECT)
        {
            $this->last_query = $query;
            return $this->mock_results['get_row'] ?? null;
        }

        public function get_var($query)
        {
            $this->last_query = $query;
            return $this->mock_results['get_var'] ?? null;
        }

        public function get_results($query)
        {
            $this->last_query = $query;
            return $this->mock_results['get_results'] ?? [];
        }

        public function insert($table, $data, $format = null)
        {
            $this->mock_inserts[] = ['table' => $table, 'data' => $data];
            return true;
        }

        public function update($table, $data, $where, $format = null, $where_format = null)
        {
            return 1;
        }

        public function delete($table, $where, $where_format = null)
        {
            return 1;
        }

        public function replace($table, $data, $format = null)
        {
            return $this->insert($table, $data, $format);
        }

        public function query($query)
        {
            $this->last_query = $query;
            return true;
        }

        public function get_charset_collate()
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function set_mock_result($method, $value)
        {
            $this->mock_results[$method] = $value;
        }

        public function get_mock_inserts()
        {
            return $this->mock_inserts;
        }

        public function clear_mock_data()
        {
            $this->mock_results = [];
            $this->mock_inserts = [];
        }
    }
}

$wpdb = new wpdb();

// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($option, $default = false)
    {
        global $wp_options;
        return $wp_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value)
    {
        global $wp_options;
        $wp_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option)
    {
        global $wp_options;
        unset($wp_options[$option]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient)
    {
        global $wp_transients;
        return $wp_transients[$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0)
    {
        global $wp_transients;
        $wp_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient)
    {
        global $wp_transients;
        unset($wp_transients[$transient]);
        return true;
    }
}

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '')
    {
        global $wp_cache;
        $cache_key = $group ? "{$group}:{$key}" : $key;
        return $wp_cache[$cache_key] ?? false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0)
    {
        global $wp_cache;
        $cache_key = $group ? "{$group}:{$key}" : $key;
        $wp_cache[$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = '')
    {
        global $wp_cache;
        $cache_key = $group ? "{$group}:{$key}" : $key;
        unset($wp_cache[$cache_key]);
        return true;
    }
}

if (!function_exists('wp_using_ext_object_cache')) {
    function wp_using_ext_object_cache()
    {
        return false;
    }
}

if (!function_exists('current_time')) {
    function current_time($type)
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str)
    {
        return strip_tags($str);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data)
    {
        return json_encode($data);
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data)
    {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        return @unserialize($data) !== false;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args)
    {
        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args)
    {
        // No-op
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

function reset_wp_mocks()
{
    global $wp_options, $wp_transients, $wp_cache, $wpdb;
    $wp_options = [];
    $wp_transients = [];
    $wp_cache = [];
    if ($wpdb && method_exists($wpdb, 'clear_mock_data')) {
        $wpdb->clear_mock_data();
    }
}
