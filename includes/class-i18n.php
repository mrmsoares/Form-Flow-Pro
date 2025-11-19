<?php
/**
 * Define the internationalization functionality.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

namespace FormFlowPro;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since 2.0.0
 */
class I18n
{
    /**
     * Load the plugin text domain for translation.
     *
     * @since 2.0.0
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            'formflow-pro',
            false,
            dirname(FORMFLOW_BASENAME) . '/languages/'
        );
    }
}
