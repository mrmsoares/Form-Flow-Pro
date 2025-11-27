<?php
/**
 * PHPUnit bootstrap file for FormFlow Pro Enterprise tests.
 *
 * @package FormFlowPro
 * @subpackage Tests
 */

// Define test constants
define('FORMFLOW_TESTS_DIR', __DIR__);
define('FORMFLOW_PLUGIN_DIR', dirname(__DIR__));

// Require composer autoloader
require_once FORMFLOW_PLUGIN_DIR . '/vendor/autoload.php';

// Define WordPress test constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Define plugin constants for testing
if (!defined('FORMFLOW_VERSION')) {
    define('FORMFLOW_VERSION', '2.0.0');
}

if (!defined('FORMFLOW_PATH')) {
    define('FORMFLOW_PATH', FORMFLOW_PLUGIN_DIR . '/');
}

if (!defined('FORMFLOW_URL')) {
    define('FORMFLOW_URL', 'http://formflow.test/wp-content/plugins/formflow-pro/');
}

if (!defined('FORMFLOW_DB_VERSION')) {
    define('FORMFLOW_DB_VERSION', '2.0.0');
}

if (!defined('FORMFLOW_BASENAME')) {
    define('FORMFLOW_BASENAME', 'formflow-pro/formflow-pro.php');
}

// Legacy constant alias (used by some components)
if (!defined('JEFORM_VERSION')) {
    define('JEFORM_VERSION', FORMFLOW_VERSION);
}

// Database constants for testing
if (!defined('DB_NAME')) {
    define('DB_NAME', 'formflow_test');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'test');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Mock WordPress functions for unit tests
require_once FORMFLOW_TESTS_DIR . '/mocks/wordpress-functions.php';

// Load test case base class
require_once FORMFLOW_TESTS_DIR . '/TestCase.php';

echo "FormFlow Pro Test Bootstrap Loaded\n";
echo "Plugin Directory: " . FORMFLOW_PLUGIN_DIR . "\n";
echo "Tests Directory: " . FORMFLOW_TESTS_DIR . "\n";
