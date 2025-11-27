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
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', '');
}
if (!defined('ADMIN_COOKIE_PATH')) {
    define('ADMIN_COOKIE_PATH', '/wp-admin');
}
if (!defined('PLUGINS_COOKIE_PATH')) {
    define('PLUGINS_COOKIE_PATH', '/wp-content/plugins');
}
if (!defined('SITECOOKIEPATH')) {
    define('SITECOOKIEPATH', '/');
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}
if (!defined('MONTH_IN_SECONDS')) {
    define('MONTH_IN_SECONDS', 2592000);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

// FormFlow constants needed for coverage analysis
if (!defined('FORMFLOW_CACHE_ENABLED')) {
    define('FORMFLOW_CACHE_ENABLED', true);
}

// Global mock data storage
global $wp_options, $wp_transients, $wp_cache, $wpdb, $wp_actions, $wp_scheduled_events;

$wp_options = [];
$wp_transients = [];
$wp_cache = [];
$wp_actions = [];
$wp_scheduled_events = [];

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
        private $mock_updates = [];
        private $mock_deletes = [];
        private $storage = []; // In-memory data storage
        private $next_insert_id = 1; // Internal counter for auto-increment

        public function prepare($query, ...$args)
        {
            // Escape % characters that are not WordPress placeholders (%s, %d, %f)
            // This prevents vsprintf errors from format specifiers like %M in date formats
            $escaped_query = preg_replace('/%(?![sdf])/', '%%', $query);
            $escaped_query = str_replace('%s', "'%s'", $escaped_query);

            if (empty($args)) {
                $this->last_query = $escaped_query;
            } else {
                $this->last_query = vsprintf($escaped_query, $args);
            }
            return $this->last_query;
        }

        public function get_row($query, $output = OBJECT)
        {
            $this->last_query = $query;

            // Check if explicit mock result is set
            if (isset($this->mock_results['get_row'])) {
                $result = $this->mock_results['get_row'];
                return $this->convert_output($result, $output);
            }

            // Try to extract data from storage based on query
            $result = $this->query_storage($query, 'row');
            return $this->convert_output($result, $output);
        }

        /**
         * Convert result to the appropriate output type
         */
        private function convert_output($result, $output = OBJECT)
        {
            if ($result === null) {
                return null;
            }

            if ($output === ARRAY_A) {
                if (is_object($result)) {
                    return get_object_vars($result);
                }
                return (array) $result;
            }

            if ($output === ARRAY_N) {
                if (is_object($result)) {
                    return array_values(get_object_vars($result));
                }
                return array_values((array) $result);
            }

            // Default OBJECT output
            if (is_array($result)) {
                return (object) $result;
            }
            return $result;
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
                $results = $this->mock_results['get_results'];
                return $this->convert_results_output($results, $output);
            }

            // Try to extract data from storage based on query
            $results = $this->query_storage($query, 'results') ?? [];
            return $this->convert_results_output($results, $output);
        }

        public function get_col($query, $col = 0)
        {
            $this->last_query = $query;

            // Check if explicit mock result is set
            if (isset($this->mock_results['get_col'])) {
                return $this->mock_results['get_col'];
            }

            // Get results and extract column
            $results = $this->query_storage($query, 'results') ?? [];
            $column_values = [];
            foreach ($results as $row) {
                if (is_object($row)) {
                    $values = array_values(get_object_vars($row));
                } else {
                    $values = array_values((array) $row);
                }
                if (isset($values[$col])) {
                    $column_values[] = $values[$col];
                }
            }
            return $column_values;
        }

        /**
         * Convert array of results to the appropriate output type
         */
        private function convert_results_output($results, $output = OBJECT)
        {
            if (!is_array($results)) {
                return [];
            }

            return array_map(function ($result) use ($output) {
                return $this->convert_output($result, $output);
            }, $results);
        }

        public function insert($table, $data, $format = null)
        {
            $this->mock_inserts[] = ['table' => $table, 'data' => $data];

            // Store in memory
            if (!isset($this->storage[$table])) {
                $this->storage[$table] = [];
            }

            // Use internal counter for auto-increment, update insert_id to reflect this insert
            $this->insert_id = $this->next_insert_id;

            // Auto-increment ID if not provided
            if (!isset($data['id'])) {
                $data['id'] = (string) $this->insert_id;
            }

            $this->storage[$table][] = (object) $data;

            // Increment internal counter for next insert
            // insert_id stays at current value until next insert (WordPress behavior)
            $this->next_insert_id++;

            return 1; // Return number of rows inserted (WordPress behavior)
        }

        public function update($table, $data, $where, $format = null, $where_format = null)
        {
            // Track the update call
            $this->mock_updates[] = ['table' => $table, 'data' => $data, 'where' => $where];

            // Check if explicit mock result is set
            if (isset($this->mock_results['update'])) {
                return $this->mock_results['update'];
            }

            if (!isset($this->storage[$table])) {
                return 1; // Return 1 to indicate success even if nothing was updated
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

            return $updated > 0 ? $updated : 1;
        }

        public function delete($table, $where, $where_format = null)
        {
            // Track the delete call
            $this->mock_deletes[] = ['table' => $table, 'where' => $where];

            // Check if explicit mock result is set
            if (isset($this->mock_results['delete'])) {
                return $this->mock_results['delete'];
            }

            if (!isset($this->storage[$table])) {
                return 1; // Return 1 to indicate success
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

            $deleted = $original_count - count($this->storage[$table]);
            return $deleted > 0 ? $deleted : 1;
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

        public function set_mock_update_result($value)
        {
            $this->mock_results['update'] = $value;
        }

        public function set_mock_delete_result($value)
        {
            $this->mock_results['delete'] = $value;
        }

        public function get_mock_inserts()
        {
            return $this->mock_inserts;
        }

        public function get_mock_updates()
        {
            return $this->mock_updates;
        }

        public function get_mock_deletes()
        {
            return $this->mock_deletes;
        }

        public function get_mock_queries()
        {
            // Return all recorded queries
            return array_merge(
                array_map(fn($i) => ['type' => 'insert', 'data' => $i], $this->mock_inserts),
                array_map(fn($u) => ['type' => 'update', 'data' => $u], $this->mock_updates),
                array_map(fn($d) => ['type' => 'delete', 'data' => $d], $this->mock_deletes)
            );
        }

        public function clear_mock_data()
        {
            $this->mock_results = [];
            $this->mock_inserts = [];
            $this->mock_updates = [];
            $this->mock_deletes = [];
            $this->storage = [];
            $this->insert_id = 1;
            $this->next_insert_id = 1;
        }

        // Alias for clear_mock_data
        public function reset_mock_data()
        {
            $this->clear_mock_data();
        }
    }
}

// Global for controlling current_user_can behavior
global $wp_mock_user_caps;
$wp_mock_user_caps = [];

function set_current_user_can($capability, $can = true)
{
    global $wp_mock_user_caps;
    $wp_mock_user_caps[$capability] = $can;
}

// Global for controlling nonce verification behavior
global $wp_mock_nonce_verified;
$wp_mock_nonce_verified = true;

function set_nonce_verified($verified = true)
{
    global $wp_mock_nonce_verified;
    $wp_mock_nonce_verified = $verified;
}

// Helper function to set options in tests (alias for update_option)
function set_option($option, $value)
{
    global $wp_options;
    $wp_options[$option] = $value;
    return true;
}

// Helper function to set bloginfo values in tests
global $wp_mock_bloginfo;
$wp_mock_bloginfo = [];

function set_bloginfo($key, $value)
{
    global $wp_mock_bloginfo;
    $wp_mock_bloginfo[$key] = $value;
}

// HTTP mock handlers
global $wp_mock_remote_post_handler, $wp_mock_remote_get_handler, $wp_mock_remote_request_handler;
$wp_mock_remote_post_handler = null;
$wp_mock_remote_get_handler = null;
$wp_mock_remote_request_handler = null;

// Screen mock
global $wp_mock_current_screen;
$wp_mock_current_screen = null;

function set_current_screen($screen_id)
{
    global $wp_mock_current_screen;
    $wp_mock_current_screen = (object) ['id' => $screen_id, 'base' => $screen_id];
}

// Site transients
global $wp_site_transients;
$wp_site_transients = [];

function set_site_transient($transient, $value, $expiration = 0)
{
    global $wp_site_transients;
    $wp_site_transients[$transient] = $value;
    return true;
}

// Current user ID helper
function set_current_user_id($user_id)
{
    global $wp_current_user_id;
    $wp_current_user_id = $user_id;
}

// Function handler for mocking arbitrary functions
global $wp_mock_function_handlers;
$wp_mock_function_handlers = [];

function set_function_handler($function_name, $handler)
{
    global $wp_mock_function_handlers;
    $wp_mock_function_handlers[$function_name] = $handler;
}

// Helper function to mock class_exists behavior
global $wp_mock_class_exists;
$wp_mock_class_exists = [];

function mock_class_exists($class_name, $exists = true)
{
    global $wp_mock_class_exists;
    $wp_mock_class_exists[$class_name] = $exists;
}

// getallheaders function mock (not always available in CLI)
if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) === 'HTTP_') {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

function set_wp_remote_post_handler($handler)
{
    global $wp_mock_remote_post_handler;
    $wp_mock_remote_post_handler = $handler;
}

function set_wp_remote_get_handler($handler)
{
    global $wp_mock_remote_get_handler;
    $wp_mock_remote_get_handler = $handler;
}

function set_wp_remote_request_handler($handler)
{
    global $wp_mock_remote_request_handler;
    $wp_mock_remote_request_handler = $handler;
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
        global $wp_actions;
        if (isset($wp_actions[$tag])) {
            foreach ($wp_actions[$tag] as $callback) {
                call_user_func_array($callback['function'], $args);
            }
        }
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1)
    {
        global $wp_actions;
        if (!isset($wp_actions[$tag])) {
            $wp_actions[$tag] = [];
        }
        $wp_actions[$tag][] = [
            'function' => $function_to_add,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
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

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 1; // Default mock user ID
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = [])
    {
        if (is_object($args)) {
            $r = get_object_vars($args);
        } elseif (is_array($args)) {
            $r = &$args;
        } else {
            parse_str($args, $r);
        }
        return array_merge($defaults, $r);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true)
    {
        global $wp_mock_nonce_verified;
        return $wp_mock_nonce_verified ?? true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args)
    {
        global $wp_mock_user_caps;
        if (isset($wp_mock_user_caps[$capability])) {
            return $wp_mock_user_caps[$capability];
        }
        return true; // Default: user has all capabilities
    }
}

// Custom exception for AJAX response termination (simulates wp_die)
class WPAjaxDieException extends \Exception
{
    public string $response;

    public function __construct(string $response)
    {
        $this->response = $response;
        parent::__construct('AJAX response sent');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null)
    {
        $response = json_encode(['success' => true, 'data' => $data]);
        echo $response;
        throw new WPAjaxDieException($response);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null)
    {
        $response = json_encode(['success' => false, 'data' => $data]);
        echo $response;
        throw new WPAjaxDieException($response);
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = [])
    {
        if (is_numeric($title)) {
            // Title is actually status code
            http_response_code($title);
        }
        throw new \Exception($message);
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($response, $status_code = null)
    {
        $json = json_encode($response);
        echo $json;
        throw new WPAjaxDieException($json);
    }
}

if (!function_exists('wp_add_inline_script')) {
    function wp_add_inline_script($handle, $data, $position = 'after')
    {
        return true;
    }
}

if (!function_exists('wp_add_inline_style')) {
    function wp_add_inline_style($handle, $data)
    {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n)
    {
        return true;
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
        private $method = 'GET';
        private $route = '';
        private $attributes = [];

        public function __construct($method = 'GET', $route = '', $attributes = [])
        {
            $this->method = $method;
            $this->route = $route;
            $this->attributes = $attributes;
        }

        public function get_method()
        {
            return $this->method;
        }

        public function set_method($method)
        {
            $this->method = $method;
        }

        public function get_route()
        {
            return $this->route;
        }

        public function set_route($route)
        {
            $this->route = $route;
        }

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

        public function get_param($key)
        {
            return $this->params[$key] ?? null;
        }

        public function has_param($key)
        {
            return array_key_exists($key, $this->params);
        }

        public function set_param($key, $value)
        {
            $this->params[$key] = $value;
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
        global $wp_http_mock_response, $wp_http_mock_error, $wp_mock_remote_request_handler;

        // Check for request handler (callback function)
        if ($wp_mock_remote_request_handler !== null && is_callable($wp_mock_remote_request_handler)) {
            return call_user_func($wp_mock_remote_request_handler, $url, $args);
        }

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
        global $wp_http_download_response, $wp_mock_remote_get_handler;

        // Check for get handler (callback function)
        if ($wp_mock_remote_get_handler !== null && is_callable($wp_mock_remote_get_handler)) {
            return call_user_func($wp_mock_remote_get_handler, $url, $args);
        }

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
            $url = $_SERVER['REQUEST_URI'] ?? '/';
        }

        $parsed = parse_url($url);
        $query = $parsed['query'] ?? '';
        parse_str($query, $query_args);

        $query_args = array_merge($query_args, $args);
        $query_string = http_build_query($query_args);

        // Build base URL safely
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';

        $base_url = $scheme . $host . $path;
        return $base_url . '?' . $query_string;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data)
    {
        // Simplified mock - allows basic HTML tags commonly used in posts
        return strip_tags($data, '<a><b><strong><i><em><p><br><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><span><div><table><tr><td><th><thead><tbody>');
    }
}

if (!function_exists('__return_true')) {
    function __return_true()
    {
        return true;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1)
    {
        return substr(md5($action . 'salt'), 0, 10);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1)
    {
        $expected = wp_create_nonce($action);
        return hash_equals($expected, $nonce) ? 1 : false;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = [])
    {
        global $wp_mock_remote_post_handler;

        // Check for post handler (callback function)
        if ($wp_mock_remote_post_handler !== null && is_callable($wp_mock_remote_post_handler)) {
            return call_user_func($wp_mock_remote_post_handler, $url, $args);
        }

        return wp_remote_request($url, array_merge($args, ['method' => 'POST']));
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw')
    {
        global $wp_mock_bloginfo;
        $info = array_merge([
            'name' => 'Test Site',
            'description' => 'Just another WordPress site',
            'url' => 'https://example.com',
            'admin_email' => 'admin@example.com',
            'charset' => 'UTF-8',
            'version' => '6.4.0',
            'language' => 'en-US',
        ], $wp_mock_bloginfo ?? []);
        return $info[$show] ?? '';
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'https://example.com/wp-admin/' . $path;
    }
}

if (!function_exists('plugins_url')) {
    function plugins_url($path = '', $file = '')
    {
        return 'https://example.com/wp-content/plugins/' . $path;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all')
    {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default')
    {
        echo esc_html($text);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default')
    {
        echo esc_attr($text);
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = 'default')
    {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam($filename = '', $dir = '')
    {
        if (empty($dir)) {
            $dir = sys_get_temp_dir();
        }
        return tempnam($dir, 'wp_' . $filename);
    }
}

if (!function_exists('WP_Filesystem')) {
    function WP_Filesystem($args = false, $context = false)
    {
        return true;
    }
}

if (!function_exists('unzip_file')) {
    function unzip_file($file, $to)
    {
        return true; // Mock always succeeds
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true)
    {
        $nonce = wp_create_nonce($action);
        $field = '<input type="hidden" name="' . $name . '" value="' . $nonce . '" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce')
    {
        return true;
    }
}

if (!function_exists('absint')) {
    function absint($value)
    {
        return abs(intval($value));
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true)
    {
        $result = $checked == $current ? " checked='checked'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true)
    {
        $result = $selected == $current ? " selected='selected'" : '';
        if ($echo) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function = '')
    {
        return $menu_slug;
    }
}

if (!function_exists('number_format_i18n')) {
    function number_format_i18n($number, $decimals = 0)
    {
        return number_format($number, $decimals);
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        return (object)[
            'ID' => $user_id,
            'user_login' => 'testuser',
            'user_email' => 'test@example.com',
            'display_name' => 'Test User'
        ];
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user()
    {
        return get_userdata(1);
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in()
    {
        return true;
    }
}

if (!function_exists('wp_logout')) {
    function wp_logout()
    {
        // No-op
    }
}

if (!function_exists('wp_set_auth_cookie')) {
    function wp_set_auth_cookie($user_id, $remember = false, $secure = '', $token = '')
    {
        return true;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str)
    {
        return strip_tags($str);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback_title = '', $context = 'save')
    {
        $title = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $title);
        $title = strtolower(str_replace(' ', '-', $title));
        return $title ?: $fallback_title;
    }
}

if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename)
    {
        // Remove special characters and sanitize
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        return $filename ?: 'file';
    }
}

if (!function_exists('human_time_diff')) {
    function human_time_diff($from, $to = 0)
    {
        if ($to === 0) {
            $to = time();
        }
        $diff = abs($to - $from);
        if ($diff < 60) {
            return $diff . ' seconds';
        } elseif ($diff < 3600) {
            return round($diff / 60) . ' minutes';
        } elseif ($diff < 86400) {
            return round($diff / 3600) . ' hours';
        } else {
            return round($diff / 86400) . ' days';
        }
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0)
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), $decimals) . ' ' . $sizes[$i];
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : false;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = [])
    {
        return true; // Mock always succeeds
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false, $gmt = false)
    {
        if ($timestamp === false) {
            $timestamp = time();
        }
        return date($format, $timestamp);
    }
}

if (!function_exists('wp_delete_file')) {
    function wp_delete_file($file)
    {
        if (file_exists($file)) {
            return @unlink($file);
        }
        return false;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = [])
    {
        global $wp_scheduled_events;
        $key = $hook . '_' . md5(serialize($args));
        return $wp_scheduled_events[$key] ?? false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = [], $wp_error = false)
    {
        global $wp_scheduled_events;
        $key = $hook . '_' . md5(serialize($args));
        $wp_scheduled_events[$key] = $timestamp;
        return true;
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook, $args = [])
    {
        global $wp_scheduled_events;
        $key = $hook . '_' . md5(serialize($args));
        unset($wp_scheduled_events[$key]);
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = [])
    {
        global $wp_scheduled_events;
        $count = 0;
        foreach ($wp_scheduled_events as $key => $timestamp) {
            if (strpos($key, $hook . '_') === 0) {
                unset($wp_scheduled_events[$key]);
                $count++;
            }
        }
        return $count;
    }
}

// Site transient functions
if (!function_exists('get_site_transient')) {
    function get_site_transient($transient)
    {
        global $wp_site_transients;
        return $wp_site_transients[$transient] ?? false;
    }
}

if (!function_exists('delete_site_transient')) {
    function delete_site_transient($transient)
    {
        global $wp_site_transients;
        unset($wp_site_transients[$transient]);
        return true;
    }
}

// Site options
global $wp_site_options;
$wp_site_options = [];

if (!function_exists('get_site_option')) {
    function get_site_option($option, $default = false, $deprecated = true)
    {
        global $wp_site_options;
        return $wp_site_options[$option] ?? $default;
    }
}

if (!function_exists('update_site_option')) {
    function update_site_option($option, $value)
    {
        global $wp_site_options;
        $wp_site_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_site_option')) {
    function delete_site_option($option)
    {
        global $wp_site_options;
        unset($wp_site_options[$option]);
        return true;
    }
}

// Blog options for multisite
global $wp_blog_options;
$wp_blog_options = [];

if (!function_exists('get_blog_option')) {
    function get_blog_option($blog_id, $option, $default = false)
    {
        global $wp_blog_options;
        $key = $blog_id . '_' . $option;
        return $wp_blog_options[$key] ?? $default;
    }
}

if (!function_exists('update_blog_option')) {
    function update_blog_option($blog_id, $option, $value)
    {
        global $wp_blog_options;
        $key = $blog_id . '_' . $option;
        $wp_blog_options[$key] = $value;
        return true;
    }
}

// Get users function
if (!function_exists('get_users')) {
    function get_users($args = [])
    {
        $users = [];
        $count = $args['number'] ?? 10;
        for ($i = 1; $i <= $count; $i++) {
            $users[] = new WP_User($i);
        }
        return $users;
    }
}

// Get the title function
if (!function_exists('get_the_title')) {
    function get_the_title($post = 0)
    {
        if (is_object($post)) {
            return $post->post_title ?? 'Test Post';
        }
        return 'Test Post ' . $post;
    }
}

// Get main site ID for multisite
if (!function_exists('get_main_site_id')) {
    function get_main_site_id($network_id = null)
    {
        return 1;
    }
}

// Get current screen
if (!function_exists('get_current_screen')) {
    function get_current_screen()
    {
        global $wp_mock_current_screen;
        return $wp_mock_current_screen;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($queries = '', $execute = true)
    {
        return []; // Mock returns empty array (no changes)
    }
}

if (!function_exists('wp_trim_words')) {
    function wp_trim_words($text, $num_words = 55, $more = null)
    {
        if (null === $more) {
            $more = '&hellip;';
        }
        $words = explode(' ', $text);
        if (count($words) > $num_words) {
            array_splice($words, $num_words);
            $text = implode(' ', $words) . $more;
        }
        return $text;
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text)
    {
        return addslashes($text);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default')
    {
        echo esc_attr($text);
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Post meta functions
if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '')
    {
        global $wp_post_meta;
        if (!isset($wp_post_meta)) {
            $wp_post_meta = [];
        }
        if (!isset($wp_post_meta[$post_id])) {
            $wp_post_meta[$post_id] = [];
        }
        $wp_post_meta[$post_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false)
    {
        global $wp_post_meta;
        if (!isset($wp_post_meta) || !isset($wp_post_meta[$post_id])) {
            return $single ? '' : [];
        }
        if (empty($key)) {
            return $wp_post_meta[$post_id];
        }
        $value = $wp_post_meta[$post_id][$key] ?? null;
        if ($single) {
            return $value ?? '';
        }
        return $value !== null ? [$value] : [];
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $meta_key, $meta_value = '')
    {
        global $wp_post_meta;
        if (isset($wp_post_meta[$post_id][$meta_key])) {
            unset($wp_post_meta[$post_id][$meta_key]);
            return true;
        }
        return false;
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta($post_id, $meta_key, $meta_value, $unique = false)
    {
        global $wp_post_meta;
        if (!isset($wp_post_meta)) {
            $wp_post_meta = [];
        }
        if ($unique && isset($wp_post_meta[$post_id][$meta_key])) {
            return false;
        }
        if (!isset($wp_post_meta[$post_id])) {
            $wp_post_meta[$post_id] = [];
        }
        $wp_post_meta[$post_id][$meta_key] = $meta_value;
        return true;
    }
}

// User meta functions
if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '')
    {
        global $wp_user_meta;
        if (!isset($wp_user_meta)) {
            $wp_user_meta = [];
        }
        if (!isset($wp_user_meta[$user_id])) {
            $wp_user_meta[$user_id] = [];
        }
        $wp_user_meta[$user_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false)
    {
        global $wp_user_meta;
        if (!isset($wp_user_meta) || !isset($wp_user_meta[$user_id])) {
            return $single ? '' : [];
        }
        if (empty($key)) {
            return $wp_user_meta[$user_id];
        }
        $value = $wp_user_meta[$user_id][$key] ?? null;
        if ($single) {
            return $value ?? '';
        }
        return $value !== null ? [$value] : [];
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = '')
    {
        global $wp_user_meta;
        if (isset($wp_user_meta[$user_id][$meta_key])) {
            unset($wp_user_meta[$user_id][$meta_key]);
            return true;
        }
        return false;
    }
}

// Additional WordPress functions
if (!function_exists('wp_roles')) {
    function wp_roles()
    {
        return new class {
            public function get_names()
            {
                return [
                    'administrator' => 'Administrator',
                    'editor' => 'Editor',
                    'author' => 'Author',
                    'contributor' => 'Contributor',
                    'subscriber' => 'Subscriber',
                ];
            }
        };
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl()
    {
        return true;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules()
    {
        return [
            'hourly' => ['interval' => 3600, 'display' => 'Once Hourly'],
            'twicedaily' => ['interval' => 43200, 'display' => 'Twice Daily'],
            'daily' => ['interval' => 86400, 'display' => 'Once Daily'],
            'weekly' => ['interval' => 604800, 'display' => 'Once Weekly'],
        ];
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite()
    {
        return false;
    }
}

if (!function_exists('get_sites')) {
    function get_sites($args = [])
    {
        return [];
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id()
    {
        return 1;
    }
}

if (!function_exists('switch_to_blog')) {
    function switch_to_blog($blog_id)
    {
        return true;
    }
}

if (!function_exists('restore_current_blog')) {
    function restore_current_blog()
    {
        return true;
    }
}

if (!function_exists('get_site_option')) {
    function get_site_option($option, $default = false)
    {
        return get_option('site_' . $option, $default);
    }
}

if (!function_exists('update_site_option')) {
    function update_site_option($option, $value)
    {
        return update_option('site_' . $option, $value);
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = [], $wp_error = false)
    {
        global $wp_scheduled_events;
        $key = $hook . '_single_' . md5(serialize($args));
        $wp_scheduled_events[$key] = $timestamp;
        return true;
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null, $output = OBJECT, $filter = 'raw')
    {
        if (is_numeric($post)) {
            return (object) [
                'ID' => $post,
                'post_title' => 'Test Post',
                'post_content' => 'Test content',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_author' => 1,
            ];
        }
        return $post;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true)
    {
        static $post_id = 100;
        return $post_id++;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false, $fire_after_hooks = true)
    {
        return $postarr['ID'] ?? 0;
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postid, $force_delete = false)
    {
        return get_post($postid);
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = null)
    {
        return [];
    }
}

if (!function_exists('wp_insert_user')) {
    function wp_insert_user($userdata)
    {
        static $user_id = 100;
        return $user_id++;
    }
}

if (!function_exists('wp_hash_password')) {
    function wp_hash_password($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('wp_check_password')) {
    function wp_check_password($password, $hash, $user_id = '')
    {
        return password_verify($password, $hash);
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

if (!function_exists('get_user_by')) {
    function get_user_by($field, $value)
    {
        if ($field === 'email' && $value === 'test@example.com') {
            return get_userdata(1);
        }
        if ($field === 'id' && is_numeric($value)) {
            return get_userdata($value);
        }
        return false;
    }
}

if (!function_exists('wp_set_current_user')) {
    function wp_set_current_user($id, $name = '')
    {
        global $wp_current_user_id;
        $wp_current_user_id = $id;
        return get_userdata($id);
    }
}

if (!function_exists('add_option')) {
    function add_option($option, $value = '', $deprecated = '', $autoload = 'yes')
    {
        global $wp_options;
        if (!isset($wp_options[$option])) {
            $wp_options[$option] = $value;
            return true;
        }
        return false;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
    {
        return true;
    }
}

if (!function_exists('wp_redirect')) {
    function wp_redirect($location, $status = 302, $x_redirect_by = 'WordPress')
    {
        return true;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('wp_doing_cron')) {
    function wp_doing_cron()
    {
        return defined('DOING_CRON') && DOING_CRON;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $function_to_remove, $priority = 10)
    {
        return true;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($tag, $function_to_check = false)
    {
        return false;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '')
    {
        return array_merge($pairs, (array) $atts);
    }
}

if (!function_exists('add_shortcode')) {
    function add_shortcode($tag, $callback)
    {
        return true;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content, $ignore_html = false)
    {
        return $content;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string, $remove_breaks = false)
    {
        $string = strip_tags($string);
        if ($remove_breaks) {
            $string = preg_replace('/[\r\n\t ]+/', ' ', $string);
        }
        return trim($string);
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id)
    {
        return 'https://example.com/wp-content/uploads/file.pdf';
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id, $unfiltered = false)
    {
        return '/tmp/wordpress-uploads/file.pdf';
    }
}

if (!function_exists('wp_get_attachment_metadata')) {
    function wp_get_attachment_metadata($attachment_id, $unfiltered = false)
    {
        return [
            'width' => 800,
            'height' => 600,
            'file' => 'file.pdf',
        ];
    }
}

// Mock WP_User class
if (!class_exists('WP_User')) {
    class WP_User
    {
        public $ID = 0;
        public $user_login = '';
        public $user_email = '';
        public $user_pass = '';
        public $user_nicename = '';
        public $user_url = '';
        public $user_registered = '';
        public $user_activation_key = '';
        public $user_status = 0;
        public $display_name = '';
        public $caps = [];
        public $cap_key = '';
        public $roles = [];
        public $allcaps = [];
        public $data;

        public function __construct($id = 0, $name = '', $site_id = '')
        {
            if (is_numeric($id) && $id > 0) {
                $this->ID = (int) $id;
                $this->user_login = 'testuser' . $id;
                $this->user_email = 'test' . $id . '@example.com';
                $this->display_name = 'Test User ' . $id;
                $this->roles = ['subscriber'];
                $this->allcaps = ['read' => true];
                $this->data = (object) [
                    'ID' => $this->ID,
                    'user_login' => $this->user_login,
                    'user_email' => $this->user_email,
                    'display_name' => $this->display_name,
                ];
            } elseif (is_object($id)) {
                foreach (get_object_vars($id) as $key => $value) {
                    $this->$key = $value;
                }
                $this->data = $id;
            }
        }

        public function exists()
        {
            return $this->ID > 0;
        }

        public function has_cap($cap)
        {
            return isset($this->allcaps[$cap]) && $this->allcaps[$cap];
        }

        public function get($key)
        {
            return $this->$key ?? null;
        }

        public function __get($key)
        {
            if (isset($this->data->$key)) {
                return $this->data->$key;
            }
            return null;
        }

        public function __isset($key)
        {
            return isset($this->data->$key);
        }
    }
}

// Mock WP_Site class for multisite
if (!class_exists('WP_Site')) {
    class WP_Site
    {
        public $blog_id = 1;
        public $domain = 'example.com';
        public $path = '/';
        public $site_id = 1;
        public $registered = '';
        public $last_updated = '';
        public $public = 1;
        public $archived = 0;
        public $mature = 0;
        public $spam = 0;
        public $deleted = 0;
        public $lang_id = 0;

        public function __construct($site = null)
        {
            if (is_numeric($site)) {
                $this->blog_id = (int) $site;
            } elseif (is_object($site)) {
                foreach (get_object_vars($site) as $key => $value) {
                    $this->$key = $value;
                }
            }
            $this->registered = date('Y-m-d H:i:s');
            $this->last_updated = date('Y-m-d H:i:s');
        }

        public static function get_instance($site_id)
        {
            return new self($site_id);
        }

        public function __get($key)
        {
            return $this->$key ?? null;
        }
    }
}

// Missing WordPress functions
if (!function_exists('set_query_var')) {
    function set_query_var($var, $value)
    {
        global $wp_query_vars;
        if (!isset($wp_query_vars)) {
            $wp_query_vars = [];
        }
        $wp_query_vars[$var] = $value;
    }
}

if (!function_exists('get_query_var')) {
    function get_query_var($var, $default = '')
    {
        global $wp_query_vars;
        return $wp_query_vars[$var] ?? $default;
    }
}

if (!function_exists('wp_enqueue_media')) {
    function wp_enqueue_media($args = [])
    {
        return true;
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule($regex, $query, $after = 'bottom')
    {
        return true;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = [])
    {
        return true;
    }
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page)
    {
        return true;
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = 'default', $args = [])
    {
        return true;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group)
    {
        echo '<input type="hidden" name="option_page" value="' . esc_attr($option_group) . '" />';
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page)
    {
        // Mock - no output
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null)
    {
        $html = '<input type="submit" name="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '" value="' . esc_attr($text ?? 'Save Changes') . '" />';
        if ($wrap) {
            $html = '<p class="submit">' . $html . '</p>';
        }
        echo $html;
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function = '', $icon_url = '', $position = null)
    {
        return $menu_slug;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true)
    {
        return true;
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $function)
    {
        return true;
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $function)
    {
        return true;
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook($file, $callback)
    {
        return true;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file)
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file)
    {
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file)
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
    {
        return defined('WP_ADMIN') && WP_ADMIN;
    }
}

if (!function_exists('wp_register_script')) {
    function wp_register_script($handle, $src, $deps = [], $ver = false, $in_footer = false)
    {
        return true;
    }
}

if (!function_exists('wp_register_style')) {
    function wp_register_style($handle, $src, $deps = [], $ver = false, $media = 'all')
    {
        return true;
    }
}

if (!function_exists('wp_dequeue_script')) {
    function wp_dequeue_script($handle)
    {
        return true;
    }
}

if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style($handle)
    {
        return true;
    }
}

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $list = 'enqueued')
    {
        return false;
    }
}

if (!function_exists('wp_style_is')) {
    function wp_style_is($handle, $list = 'enqueued')
    {
        return false;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = 0, $leavename = false)
    {
        $id = is_object($post) ? $post->ID : $post;
        return 'https://example.com/?p=' . $id;
    }
}

if (!function_exists('get_edit_post_link')) {
    function get_edit_post_link($id = 0, $context = 'display')
    {
        return admin_url('post.php?post=' . $id . '&action=edit');
    }
}

if (!function_exists('get_delete_post_link')) {
    function get_delete_post_link($id = 0, $deprecated = '', $force_delete = false)
    {
        return admin_url('post.php?post=' . $id . '&action=trash');
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post = null)
    {
        if (is_object($post)) {
            return $post->post_type ?? 'post';
        }
        return 'post';
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status($post = null)
    {
        if (is_object($post)) {
            return $post->post_status ?? 'publish';
        }
        return 'publish';
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type)
    {
        return in_array($post_type, ['post', 'page', 'attachment', 'revision', 'nav_menu_item']);
    }
}

if (!function_exists('register_post_type')) {
    function register_post_type($post_type, $args = [])
    {
        return (object) ['name' => $post_type];
    }
}

if (!function_exists('wp_set_object_terms')) {
    function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false)
    {
        return is_array($terms) ? $terms : [$terms];
    }
}

if (!function_exists('wp_get_object_terms')) {
    function wp_get_object_terms($object_ids, $taxonomies, $args = [])
    {
        return [];
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args = [], $deprecated = '')
    {
        return [];
    }
}

if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy = '', $parent = null)
    {
        return null;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy, $args = [])
    {
        return ['term_id' => 1, 'term_taxonomy_id' => 1];
    }
}

if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $object_type, $args = [])
    {
        return (object) ['name' => $taxonomy];
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy)
    {
        return in_array($taxonomy, ['category', 'post_tag']);
    }
}

if (!function_exists('wp_set_post_terms')) {
    function wp_set_post_terms($post_id, $tags = '', $taxonomy = 'post_tag', $append = false)
    {
        return true;
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy)
    {
        return [];
    }
}

if (!function_exists('wp_dropdown_categories')) {
    function wp_dropdown_categories($args = '')
    {
        return '<select></select>';
    }
}

if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, $header)
    {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['headers'][$header] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers($response)
    {
        if (is_wp_error($response)) {
            return [];
        }
        return $response['headers'] ?? [];
    }
}

if (!function_exists('setcookie')) {
    // PHP's setcookie but in case we need to mock
}

if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1)
    {
        return parse_url($url, $component);
    }
}

if (!function_exists('wp_sprintf')) {
    function wp_sprintf($pattern, ...$args)
    {
        return vsprintf($pattern, $args);
    }
}

if (!function_exists('_x')) {
    function _x($text, $context, $domain = 'default')
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default')
    {
        echo $text;
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default')
    {
        return esc_attr($text);
    }
}

if (!function_exists('_nx')) {
    function _nx($single, $plural, $number, $context, $domain = 'default')
    {
        return $number === 1 ? $single : $plural;
    }
}

if (!function_exists('__return_false')) {
    function __return_false()
    {
        return false;
    }
}

if (!function_exists('__return_empty_array')) {
    function __return_empty_array()
    {
        return [];
    }
}

if (!function_exists('__return_null')) {
    function __return_null()
    {
        return null;
    }
}

if (!function_exists('__return_zero')) {
    function __return_zero()
    {
        return 0;
    }
}

if (!function_exists('__return_empty_string')) {
    function __return_empty_string()
    {
        return '';
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path)
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        if (':' === substr($path, 1, 1)) {
            $path = ucfirst($path);
        }
        return $path;
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return rtrim($string, '/\\') . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($string)
    {
        return rtrim($string, '/\\');
    }
}

if (!function_exists('wp_is_json_request')) {
    function wp_is_json_request()
    {
        return false;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax()
    {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '', $scheme = null)
    {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('network_admin_url')) {
    function network_admin_url($path = '', $scheme = 'admin')
    {
        return 'https://example.com/wp-admin/network/' . $path;
    }
}

if (!function_exists('get_admin_url')) {
    function get_admin_url($blog_id = null, $path = '', $scheme = 'admin')
    {
        return admin_url($path);
    }
}

if (!function_exists('content_url')) {
    function content_url($path = '')
    {
        return 'https://example.com/wp-content' . $path;
    }
}

if (!function_exists('includes_url')) {
    function includes_url($path = '', $scheme = null)
    {
        return 'https://example.com/wp-includes/' . $path;
    }
}

if (!function_exists('wp_handle_upload')) {
    function wp_handle_upload($file, $overrides = false, $time = null)
    {
        return [
            'file' => '/tmp/wordpress-uploads/' . $file['name'],
            'url' => 'https://example.com/wp-content/uploads/' . $file['name'],
            'type' => $file['type'] ?? 'application/octet-stream',
        ];
    }
}

if (!function_exists('media_handle_upload')) {
    function media_handle_upload($file_id, $post_id, $post_data = [], $overrides = [])
    {
        return 123; // Return attachment ID
    }
}

if (!function_exists('wp_insert_attachment')) {
    function wp_insert_attachment($attachment, $filename = false, $parent_post_id = 0, $wp_error = false)
    {
        return 123; // Return attachment ID
    }
}

if (!function_exists('wp_update_attachment_metadata')) {
    function wp_update_attachment_metadata($attachment_id, $data)
    {
        return true;
    }
}

if (!function_exists('wp_generate_attachment_metadata')) {
    function wp_generate_attachment_metadata($attachment_id, $file)
    {
        return [
            'width' => 800,
            'height' => 600,
            'file' => basename($file),
        ];
    }
}

if (!function_exists('set_post_thumbnail')) {
    function set_post_thumbnail($post, $thumbnail_id)
    {
        return true;
    }
}

if (!function_exists('has_post_thumbnail')) {
    function has_post_thumbnail($post = null)
    {
        return false;
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        return 0;
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail')
    {
        return '';
    }
}

if (!function_exists('wp_check_filetype')) {
    function wp_check_filetype($filename, $mimes = null)
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $type = '';
        switch (strtolower($ext)) {
            case 'jpg':
            case 'jpeg':
                $type = 'image/jpeg';
                break;
            case 'png':
                $type = 'image/png';
                break;
            case 'gif':
                $type = 'image/gif';
                break;
            case 'pdf':
                $type = 'application/pdf';
                break;
            default:
                $type = 'application/octet-stream';
        }
        return ['ext' => $ext, 'type' => $type];
    }
}

if (!function_exists('wp_get_mime_types')) {
    function wp_get_mime_types()
    {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
        ];
    }
}

if (!function_exists('get_allowed_mime_types')) {
    function get_allowed_mime_types($user = null)
    {
        return wp_get_mime_types();
    }
}

if (!function_exists('wp_get_image_mime')) {
    function wp_get_image_mime($file)
    {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return wp_check_filetype($file)['type'];
    }
}

if (!function_exists('wp_attachment_is_image')) {
    function wp_attachment_is_image($post = null)
    {
        return true;
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers()
    {
        return true;
    }
}

if (!function_exists('status_header')) {
    function status_header($code, $description = '')
    {
        return true;
    }
}

if (!function_exists('wp_list_pluck')) {
    function wp_list_pluck($list, $field, $index_key = null)
    {
        $result = [];
        foreach ($list as $key => $value) {
            if (is_object($value)) {
                $val = $value->$field ?? null;
            } else {
                $val = $value[$field] ?? null;
            }

            if ($index_key === null) {
                $result[] = $val;
            } else {
                if (is_object($value)) {
                    $index = $value->$index_key ?? null;
                } else {
                    $index = $value[$index_key] ?? null;
                }
                if ($index !== null) {
                    $result[$index] = $val;
                }
            }
        }
        return $result;
    }
}

if (!function_exists('wp_list_filter')) {
    function wp_list_filter($list, $args = [], $operator = 'AND')
    {
        if (empty($args)) {
            return $list;
        }

        return array_filter($list, function ($item) use ($args, $operator) {
            $matched = 0;
            foreach ($args as $key => $value) {
                if (is_object($item)) {
                    $item_value = $item->$key ?? null;
                } else {
                    $item_value = $item[$key] ?? null;
                }

                if ($item_value === $value) {
                    $matched++;
                }
            }

            if ($operator === 'AND') {
                return $matched === count($args);
            } elseif ($operator === 'OR') {
                return $matched > 0;
            } elseif ($operator === 'NOT') {
                return $matched === 0;
            }
            return false;
        });
    }
}

if (!function_exists('wp_array_slice_assoc')) {
    function wp_array_slice_assoc($array, $keys)
    {
        return array_intersect_key($array, array_flip($keys));
    }
}

if (!function_exists('map_deep')) {
    function map_deep($value, $callback)
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = map_deep($item, $callback);
            }
        } elseif (is_object($value)) {
            $vars = get_object_vars($value);
            foreach ($vars as $name => $content) {
                $value->$name = map_deep($content, $callback);
            }
        } else {
            $value = call_user_func($callback, $value);
        }
        return $value;
    }
}

if (!function_exists('stripslashes_deep')) {
    function stripslashes_deep($value)
    {
        return map_deep($value, 'stripslashes');
    }
}

if (!function_exists('urlencode_deep')) {
    function urlencode_deep($value)
    {
        return map_deep($value, 'urlencode');
    }
}

if (!function_exists('rawurlencode_deep')) {
    function rawurlencode_deep($value)
    {
        return map_deep($value, 'rawurlencode');
    }
}

if (!function_exists('get_locale')) {
    function get_locale()
    {
        return 'en_US';
    }
}

if (!function_exists('determine_locale')) {
    function determine_locale()
    {
        return 'en_US';
    }
}

if (!function_exists('is_rtl')) {
    function is_rtl()
    {
        return false;
    }
}

if (!function_exists('load_plugin_textdomain')) {
    function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false)
    {
        return true;
    }
}

if (!function_exists('load_textdomain')) {
    function load_textdomain($domain, $mofile)
    {
        return true;
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data)
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }
        return $data;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data)
    {
        if (is_serialized($data)) {
            return @unserialize(trim($data));
        }
        return $data;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush()
    {
        global $wp_cache;
        $wp_cache = [];
        return true;
    }
}

if (!function_exists('wp_cache_add')) {
    function wp_cache_add($key, $data, $group = '', $expire = 0)
    {
        global $wp_cache;
        $cache_key = $group ? "{$group}:{$key}" : $key;
        if (isset($wp_cache[$cache_key])) {
            return false;
        }
        $wp_cache[$cache_key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_replace')) {
    function wp_cache_replace($key, $data, $group = '', $expire = 0)
    {
        global $wp_cache;
        $cache_key = $group ? "{$group}:{$key}" : $key;
        if (!isset($wp_cache[$cache_key])) {
            return false;
        }
        $wp_cache[$cache_key] = $data;
        return true;
    }
}

function reset_wp_mocks()
{
    global $wp_options, $wp_transients, $wp_cache, $wpdb, $wp_actions, $wp_scheduled_events;
    global $wp_http_mock_response, $wp_http_mock_error, $wp_http_download_response;
    global $wp_post_meta, $wp_user_meta, $wp_current_user_id, $wp_query_vars;
    global $wp_mock_user_caps, $wp_mock_nonce_verified, $wp_mock_bloginfo;
    global $wp_mock_remote_post_handler, $wp_mock_remote_get_handler, $wp_mock_remote_request_handler;
    global $wp_mock_current_screen, $wp_site_transients, $wp_site_options, $wp_blog_options;
    global $wp_mock_function_handlers;

    $wp_options = [];
    $wp_transients = [];
    $wp_cache = [];
    $wp_actions = [];
    $wp_scheduled_events = [];
    $wp_http_mock_response = null;
    $wp_http_mock_error = null;
    $wp_http_download_response = null;
    $wp_post_meta = [];
    $wp_user_meta = [];
    $wp_current_user_id = 1;
    $wp_query_vars = [];
    $wp_mock_user_caps = [];
    $wp_mock_nonce_verified = true;
    $wp_mock_bloginfo = [];
    $wp_mock_remote_post_handler = null;
    $wp_mock_remote_get_handler = null;
    $wp_mock_remote_request_handler = null;
    $wp_mock_current_screen = null;
    $wp_site_transients = [];
    $wp_site_options = [];
    $wp_blog_options = [];
    $wp_mock_function_handlers = [];

    // Clear $_POST and $_GET to prevent test pollution
    $_POST = [];
    $_GET = [];
    $_REQUEST = [];

    // Reset $_SERVER to minimal values
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';

    if ($wpdb && method_exists($wpdb, 'clear_mock_data')) {
        $wpdb->clear_mock_data();
    }
}
