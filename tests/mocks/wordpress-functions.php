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

// FormFlow constants needed for coverage analysis
if (!defined('FORMFLOW_CACHE_ENABLED')) {
    define('FORMFLOW_CACHE_ENABLED', true);
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
        private $storage = []; // In-memory data storage

        public function prepare($query, ...$args)
        {
            $this->last_query = vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $query)), $args);
            return $this->last_query;
        }

        public function get_row($query, $output = OBJECT)
        {
            $this->last_query = $query;

            // Check if explicit mock result is set
            if (isset($this->mock_results['get_row'])) {
                return $this->mock_results['get_row'];
            }

            // Try to extract data from storage based on query
            return $this->query_storage($query, 'row');
        }

        public function get_var($query)
        {
            $this->last_query = $query;

            // Check if explicit mock result is set
            if (isset($this->mock_results['get_var'])) {
                return $this->mock_results['get_var'];
            }

            // Try to extract data from storage based on query
            return $this->query_storage($query, 'var');
        }

        public function get_results($query, $output = OBJECT)
        {
            $this->last_query = $query;

            // Check if explicit mock result is set
            if (isset($this->mock_results['get_results'])) {
                return $this->mock_results['get_results'];
            }

            // Try to extract data from storage based on query
            return $this->query_storage($query, 'results') ?? [];
        }

        public function insert($table, $data, $format = null)
        {
            $this->mock_inserts[] = ['table' => $table, 'data' => $data];

            // Store in memory
            if (!isset($this->storage[$table])) {
                $this->storage[$table] = [];
            }

            // Auto-increment ID if not provided
            if (!isset($data['id'])) {
                $data['id'] = (string) $this->insert_id;
            }

            $this->storage[$table][] = (object) $data;
            $this->insert_id++; // Increment for next insert

            return 1; // Return number of rows inserted (WordPress behavior)
        }

        public function update($table, $data, $where, $format = null, $where_format = null)
        {
            if (!isset($this->storage[$table])) {
                return 0;
            }

            $updated = 0;
            foreach ($this->storage[$table] as &$row) {
                $match = true;
                foreach ($where as $key => $value) {
                    if (!isset($row->$key) || $row->$key !== $value) {
                        $match = false;
                        break;
                    }
                }

                if ($match) {
                    foreach ($data as $key => $value) {
                        $row->$key = $value;
                    }
                    $updated++;
                }
            }

            return $updated;
        }

        public function delete($table, $where, $where_format = null)
        {
            if (!isset($this->storage[$table])) {
                return 0;
            }

            $original_count = count($this->storage[$table]);
            $this->storage[$table] = array_filter($this->storage[$table], function ($row) use ($where) {
                foreach ($where as $key => $value) {
                    if (!isset($row->$key) || $row->$key !== $value) {
                        return true; // Keep this row
                    }
                }
                return false; // Delete this row
            });

            return $original_count - count($this->storage[$table]);
        }

        public function replace($table, $data, $format = null)
        {
            return $this->insert($table, $data, $format);
        }

        public function query($query)
        {
            $this->last_query = $query;

            // Handle DELETE queries
            if (preg_match('/DELETE FROM (\S+) WHERE (.+)/i', $query, $matches)) {
                $table = $matches[1];
                // Simple parsing - just count existing rows for mock
                return isset($this->storage[$table]) ? count($this->storage[$table]) : 0;
            }

            return true;
        }

        public function get_charset_collate()
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        private function query_storage($query, $type)
        {
            // Handle COUNT queries
            if (preg_match('/SELECT\s+COUNT\(\*\)/i', $query)) {
                if (!preg_match('/FROM\s+(\S+)/i', $query, $matches)) {
                    return 0;
                }

                $table = $matches[1];

                if (!isset($this->storage[$table])) {
                    return 0;
                }

                // Extract WHERE conditions
                $where_conditions = [];
                if (preg_match('/WHERE\s+(.+?)(?:ORDER BY|LIMIT|GROUP BY|$)/i', $query, $where_matches)) {
                    $where_clause = $where_matches[1];

                    // Parse simple WHERE conditions (field = 'value')
                    preg_match_all("/(\w+)\s*=\s*'([^']+)'/", $where_clause, $condition_matches, PREG_SET_ORDER);
                    foreach ($condition_matches as $match) {
                        $where_conditions[$match[1]] = $match[2];
                    }

                    // Parse IN conditions
                    if (preg_match("/(\w+)\s+IN\s*\('([^']+)'(?:,\s*'([^']+)')*\)/i", $where_clause, $in_match)) {
                        $where_conditions[$in_match[1]] = [$in_match[2]];
                        if (isset($in_match[3])) {
                            $where_conditions[$in_match[1]][] = $in_match[3];
                        }
                    }
                }

                // Filter and count
                $results = $this->storage[$table];
                if (!empty($where_conditions)) {
                    $results = array_filter($results, function ($row) use ($where_conditions) {
                        foreach ($where_conditions as $key => $value) {
                            if (is_array($value)) {
                                if (!isset($row->$key) || !in_array($row->$key, $value)) {
                                    return false;
                                }
                            } else {
                                if (!isset($row->$key) || $row->$key !== $value) {
                                    return false;
                                }
                            }
                        }
                        return true;
                    });
                }

                return count($results);
            }

            // Extract table name from query
            if (!preg_match('/FROM\s+(\S+)/i', $query, $matches)) {
                return $type === 'results' ? [] : null;
            }

            $table = $matches[1];

            if (!isset($this->storage[$table]) || empty($this->storage[$table])) {
                return $type === 'results' ? [] : null;
            }

            // Extract WHERE conditions
            $where_conditions = [];
            if (preg_match('/WHERE\s+(.+?)(?:ORDER BY|LIMIT|$)/i', $query, $where_matches)) {
                $where_clause = $where_matches[1];

                // Parse simple WHERE conditions (field = 'value')
                preg_match_all("/(\w+)\s*=\s*'([^']+)'/", $where_clause, $condition_matches, PREG_SET_ORDER);
                foreach ($condition_matches as $match) {
                    $where_conditions[$match[1]] = $match[2];
                }
            }

            // Filter results based on WHERE conditions
            $results = $this->storage[$table];
            if (!empty($where_conditions)) {
                $results = array_filter($results, function ($row) use ($where_conditions) {
                    foreach ($where_conditions as $key => $value) {
                        if (!isset($row->$key) || $row->$key !== $value) {
                            return false;
                        }
                    }
                    return true;
                });
                $results = array_values($results); // Re-index
            }

            if (empty($results)) {
                return $type === 'results' ? [] : null;
            }

            // Handle different return types
            switch ($type) {
                case 'row':
                    return $results[0];
                case 'var':
                    // Check if a specific column is selected
                    if (preg_match('/SELECT\s+(\w+)\s+FROM/i', $query, $select_match)) {
                        $column = $select_match[1];
                        $first = $results[0];
                        return $first->$column ?? null;
                    }
                    // Return first column of first row
                    $first = $results[0];
                    $values = get_object_vars($first);
                    return reset($values);
                case 'results':
                    return $results;
                default:
                    return null;
            }
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
            $this->storage = [];
            $this->insert_id = 1;
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

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return is_string($value) ? stripslashes($value) : $value;
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
    function wp_json_encode($data, $options = 0, $depth = 512)
    {
        return json_encode($data, $options, $depth);
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

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        // No-op for testing - filters are not actually registered
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args)
    {
        // No-op
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        // No-op for testing - actions are not actually registered
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($tag, $function_to_remove, $priority = 10)
    {
        return true;
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false)
    {
        return false;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

// HTTP mocking globals
global $wp_http_mock_response, $wp_http_mock_error, $wp_http_download_response;
$wp_http_mock_response = null;
$wp_http_mock_error = null;
$wp_http_download_response = null;

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private $errors = [];
        private $error_data = [];

        public function __construct($code = '', $message = '', $data = '')
        {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_message($code = '')
        {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_code()
        {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function add($code, $message, $data = '')
        {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}

// Mock WP_REST_Request class
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private $body = '';
        private $headers = [];
        private $params = [];
        private $json_params = [];

        public function get_body()
        {
            return $this->body;
        }

        public function set_body($body)
        {
            $this->body = $body;
        }

        public function get_header($key)
        {
            return $this->headers[$key] ?? null;
        }

        public function set_header($key, $value)
        {
            $this->headers[$key] = $value;
        }

        public function get_params()
        {
            return $this->params;
        }

        public function set_params($params)
        {
            $this->params = $params;
        }

        public function get_json_params()
        {
            if (!empty($this->json_params)) {
                return $this->json_params;
            }
            $body = $this->get_body();
            if (!empty($body)) {
                return json_decode($body, true) ?? [];
            }
            return [];
        }

        public function set_json_params($params)
        {
            $this->json_params = $params;
        }
    }
}

// Mock WP_REST_Response class
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        private $data;
        private $status;
        private $headers = [];

        public function __construct($data = null, $status = 200, $headers = [])
        {
            $this->data = $data;
            $this->status = $status;
            $this->headers = $headers;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status()
        {
            return $this->status;
        }

        public function get_headers()
        {
            return $this->headers;
        }

        public function set_data($data)
        {
            $this->data = $data;
        }

        public function set_status($status)
        {
            $this->status = $status;
        }

        public function set_headers($headers)
        {
            $this->headers = $headers;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = [])
    {
        global $wp_http_mock_response, $wp_http_mock_error;

        // Check for error mock
        if ($wp_http_mock_error !== null) {
            $error = new WP_Error('http_request_failed', $wp_http_mock_error);
            $wp_http_mock_error = null; // Reset after use
            return $error;
        }

        // Check for response mock
        if ($wp_http_mock_response !== null) {
            $response = $wp_http_mock_response;
            $wp_http_mock_response = null; // Reset after use
            return $response;
        }

        // Default success response
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true]),
        ];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = [])
    {
        global $wp_http_download_response;

        // Check for download mock
        if ($wp_http_download_response !== null) {
            $response = $wp_http_download_response;
            $wp_http_download_response = null; // Reset after use
            return $response;
        }

        return wp_remote_request($url, array_merge($args, ['method' => 'GET']));
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        if (is_wp_error($response)) {
            return 0;
        }
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '/')
    {
        return 'https://example.com/wp-json' . $path;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args = [])
    {
        // No-op for testing
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null)
    {
        $upload_path = '/tmp/wordpress-uploads';
        return [
            'path' => $upload_path,
            'url' => 'https://example.com/wp-content/uploads',
            'subdir' => '',
            'basedir' => $upload_path,
            'baseurl' => 'https://example.com/wp-content/uploads',
            'error' => false,
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target)
    {
        if (file_exists($target)) {
            return is_dir($target);
        }
        return @mkdir($target, 0755, true);
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '')
    {
        if (empty($url)) {
            $url = $_SERVER['REQUEST_URI'] ?? '';
        }

        $parsed = parse_url($url);
        $query = $parsed['query'] ?? '';
        parse_str($query, $query_args);

        $query_args = array_merge($query_args, $args);
        $query_string = http_build_query($query_args);

        $base_url = $parsed['scheme'] . '://' . $parsed['host'] . $parsed['path'];
        return $base_url . '?' . $query_string;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('__return_true')) {
    function __return_true()
    {
        return true;
    }
}

function reset_wp_mocks()
{
    global $wp_options, $wp_transients, $wp_cache, $wpdb;
    global $wp_http_mock_response, $wp_http_mock_error, $wp_http_download_response;

    $wp_options = [];
    $wp_transients = [];
    $wp_cache = [];
    $wp_http_mock_response = null;
    $wp_http_mock_error = null;
    $wp_http_download_response = null;

    if ($wpdb && method_exists($wpdb, 'clear_mock_data')) {
        $wpdb->clear_mock_data();
    }
}
