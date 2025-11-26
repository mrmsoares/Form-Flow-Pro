<?php

declare(strict_types=1);

/**
 * Singleton Trait
 *
 * Provides singleton pattern implementation for classes.
 *
 * @package FormFlowPro\Core
 * @since 2.0.0
 */

namespace FormFlowPro\Core;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Singleton Trait
 *
 * Usage:
 * ```php
 * class MyClass {
 *     use SingletonTrait;
 *
 *     protected function __construct() {
 *         // Initialize
 *     }
 * }
 *
 * $instance = MyClass::getInstance();
 * ```
 */
trait SingletonTrait
{
    /**
     * Singleton instance
     *
     * @var static|null
     */
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return static
     */
    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
            // Call init method if it exists
            if (method_exists(self::$instance, 'init')) {
                self::$instance->init();
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     *
     * @return void
     */
    private function __clone(): void
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     *
     * @return void
     * @throws \Exception
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    /**
     * Reset the singleton instance (useful for testing)
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
