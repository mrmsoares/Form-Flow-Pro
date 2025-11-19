<?php

declare(strict_types=1);

namespace FormFlowPro;

if (!defined('ABSPATH')) exit;

/**
 * Cron Schedules Manager
 *
 * Registers custom WordPress cron intervals
 */
class Cron_Schedules
{
    /**
     * Register custom cron schedules
     */
    public static function register_schedules(array $schedules): array
    {
        // 5 minutes interval for queue processing
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = [
                'interval' => 300, // 5 minutes in seconds
                'display'  => __('Every 5 minutes', 'formflow-pro'),
            ];
        }

        // Weekly interval for maintenance tasks
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => 604800, // 1 week in seconds
                'display'  => __('Weekly', 'formflow-pro'),
            ];
        }

        return $schedules;
    }

    /**
     * Initialize cron schedules
     */
    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'register_schedules'], 10);
    }
}
