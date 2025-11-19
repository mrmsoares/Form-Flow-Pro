<?php
/**
 * Plugin Name: FormFlow Pro Enterprise
 * Plugin URI: https://github.com/mrmsoares/Form-Flow-Pro
 * Description: Processamento avançado de formulários Elementor com integração Autentique, geração de PDFs, sistema de queue e analytics em tempo real.
 * Version: 2.0.0
 * Author: FormFlow Pro Team
 * Author URI: https://formflowpro.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: formflow-pro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package FormFlowPro
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version.
 * Start at version 2.0.0 and use SemVer - https://semver.org
 */
define('FORMFLOW_VERSION', '2.0.0');

/**
 * Plugin paths and URLs
 */
define('FORMFLOW_FILE', __FILE__);
define('FORMFLOW_PATH', plugin_dir_path(__FILE__));
define('FORMFLOW_URL', plugin_dir_url(__FILE__));
define('FORMFLOW_BASENAME', plugin_basename(__FILE__));

/**
 * Database version for migrations
 */
define('FORMFLOW_DB_VERSION', '2.0.0');

/**
 * Performance settings
 */
if (!defined('FORMFLOW_CACHE_ENABLED')) {
    define('FORMFLOW_CACHE_ENABLED', true);
}

if (!defined('FORMFLOW_CACHE_TTL')) {
    define('FORMFLOW_CACHE_TTL', 3600); // 1 hour default
}

/**
 * Debug mode
 */
if (!defined('FORMFLOW_DEBUG')) {
    define('FORMFLOW_DEBUG', false);
}

/**
 * Composer autoloader
 */
if (file_exists(FORMFLOW_PATH . 'vendor/autoload.php')) {
    require_once FORMFLOW_PATH . 'vendor/autoload.php';
}

/**
 * The code that runs during plugin activation.
 */
function activate_formflow_pro() {
    require_once FORMFLOW_PATH . 'includes/class-activator.php';
    FormFlowPro\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_formflow_pro() {
    require_once FORMFLOW_PATH . 'includes/class-deactivator.php';
    FormFlowPro\Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_formflow_pro');
register_deactivation_hook(__FILE__, 'deactivate_formflow_pro');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once FORMFLOW_PATH . 'includes/class-formflow-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 2.0.0
 */
function run_formflow_pro() {
    $plugin = new FormFlowPro\FormFlowPlugin();
    $plugin->run();
}

run_formflow_pro();

/**
 * Initialize Elementor Integration
 *
 * @since 2.0.0
 */
function formflow_init_elementor_integration() {
    require_once FORMFLOW_PATH . 'includes/integrations/elementor/class-elementor-integration.php';
    FormFlowPro\Integrations\Elementor\Elementor_Integration::get_instance();
}

add_action('plugins_loaded', 'formflow_init_elementor_integration');
