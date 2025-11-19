<?php

declare(strict_types=1);

/**
 * Elementor Widget Base
 *
 * Base class for all FormFlow Elementor widgets.
 *
 * @package FormFlowPro\Integrations\Elementor\Widgets
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor\Widgets;

use Elementor\Widget_Base as Elementor_Widget_Base;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Base Class
 */
abstract class Widget_Base extends Elementor_Widget_Base
{
    /**
     * Get widget categories
     *
     * @return array Widget categories.
     */
    public function get_categories(): array
    {
        return ['formflow-pro'];
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-form-horizontal';
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords.
     */
    public function get_keywords(): array
    {
        return ['formflow', 'form', 'contact', 'signature', 'autentique'];
    }

    /**
     * Check if script debug is enabled
     *
     * @return bool
     */
    protected function is_script_debug(): bool
    {
        return defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
    }

    /**
     * Get asset URL
     *
     * @param string $path Asset path.
     * @return string Asset URL.
     */
    protected function get_asset_url(string $path): string
    {
        return FORMFLOW_URL . 'assets/' . $path;
    }
}
