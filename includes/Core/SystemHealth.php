<?php
/**
 * FormFlow Pro - System Health Check
 *
 * Provides health monitoring, diagnostics, and system status information
 * for debugging and monitoring purposes.
 *
 * @package FormFlowPro
 * @subpackage Core
 * @since 2.5.0
 */

namespace FormFlowPro\Core;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * System Health Check Class
 */
class SystemHealth
{
    use SingletonTrait;

    /**
     * Status constants
     */
    public const STATUS_HEALTHY = 'healthy';
    public const STATUS_WARNING = 'warning';
    public const STATUS_CRITICAL = 'critical';

    /**
     * Required PHP version
     */
    private const REQUIRED_PHP_VERSION = '8.1.0';

    /**
     * Required WordPress version
     */
    private const REQUIRED_WP_VERSION = '6.0';

    /**
     * Get full system health report
     *
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     checks: array,
     *     summary: array
     * }
     */
    public function getHealthReport(): array
    {
        $checks = [
            'php' => $this->checkPHP(),
            'wordpress' => $this->checkWordPress(),
            'database' => $this->checkDatabase(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
            'tables' => $this->checkTables(),
            'cron' => $this->checkCron(),
            'ssl' => $this->checkSSL(),
            'permissions' => $this->checkPermissions(),
        ];

        $overallStatus = $this->calculateOverallStatus($checks);

        return [
            'status' => $overallStatus,
            'timestamp' => current_time('c'),
            'checks' => $checks,
            'summary' => $this->generateSummary($checks),
        ];
    }

    /**
     * Quick health check (for API endpoints)
     *
     * @return array{status: string, message: string}
     */
    public function quickCheck(): array
    {
        $criticalChecks = [
            $this->checkDatabase()['status'],
            $this->checkTables()['status'],
        ];

        if (in_array(self::STATUS_CRITICAL, $criticalChecks)) {
            return [
                'status' => self::STATUS_CRITICAL,
                'message' => 'System is experiencing critical issues',
            ];
        }

        return [
            'status' => self::STATUS_HEALTHY,
            'message' => 'System is operating normally',
        ];
    }

    /**
     * Check PHP version and extensions
     */
    private function checkPHP(): array
    {
        $version = PHP_VERSION;
        $isVersionOk = version_compare($version, self::REQUIRED_PHP_VERSION, '>=');

        $requiredExtensions = ['json', 'mbstring', 'pdo', 'curl'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }

        $status = self::STATUS_HEALTHY;
        $messages = [];

        if (!$isVersionOk) {
            $status = self::STATUS_CRITICAL;
            $messages[] = "PHP {$version} is below required version " . self::REQUIRED_PHP_VERSION;
        }

        if (!empty($missingExtensions)) {
            $status = self::STATUS_CRITICAL;
            $messages[] = 'Missing extensions: ' . implode(', ', $missingExtensions);
        }

        return [
            'name' => 'PHP Environment',
            'status' => $status,
            'value' => $version,
            'required' => self::REQUIRED_PHP_VERSION,
            'messages' => $messages,
            'details' => [
                'version' => $version,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ];
    }

    /**
     * Check WordPress version
     */
    private function checkWordPress(): array
    {
        global $wp_version;

        $isVersionOk = version_compare($wp_version, self::REQUIRED_WP_VERSION, '>=');

        return [
            'name' => 'WordPress',
            'status' => $isVersionOk ? self::STATUS_HEALTHY : self::STATUS_CRITICAL,
            'value' => $wp_version,
            'required' => self::REQUIRED_WP_VERSION,
            'messages' => $isVersionOk ? [] : ["WordPress {$wp_version} is below required version " . self::REQUIRED_WP_VERSION],
            'details' => [
                'version' => $wp_version,
                'multisite' => is_multisite(),
                'debug_mode' => WP_DEBUG,
            ],
        ];
    }

    /**
     * Check database connection and performance
     */
    private function checkDatabase(): array
    {
        global $wpdb;

        $status = self::STATUS_HEALTHY;
        $messages = [];

        // Test connection
        $startTime = microtime(true);
        $result = $wpdb->query('SELECT 1');
        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($result === false) {
            return [
                'name' => 'Database',
                'status' => self::STATUS_CRITICAL,
                'value' => 'Connection failed',
                'messages' => ['Unable to connect to database'],
                'details' => [],
            ];
        }

        // Check response time
        if ($responseTime > 1000) {
            $status = self::STATUS_WARNING;
            $messages[] = sprintf('Slow database response: %.2fms', $responseTime);
        }

        // Check database size
        $dbSize = $wpdb->get_var(
            "SELECT SUM(data_length + index_length) / 1024 / 1024
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE()"
        );

        return [
            'name' => 'Database',
            'status' => $status,
            'value' => sprintf('%.2fms response', $responseTime),
            'messages' => $messages,
            'details' => [
                'response_time_ms' => round($responseTime, 2),
                'database_size_mb' => round($dbSize ?? 0, 2),
                'charset' => $wpdb->charset,
                'collate' => $wpdb->collate,
            ],
        ];
    }

    /**
     * Check disk space
     */
    private function checkDiskSpace(): array
    {
        $uploadDir = wp_upload_dir();
        $path = $uploadDir['basedir'];

        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);

        if ($totalSpace === false || $freeSpace === false) {
            return [
                'name' => 'Disk Space',
                'status' => self::STATUS_WARNING,
                'value' => 'Unable to determine',
                'messages' => ['Could not determine disk space'],
                'details' => [],
            ];
        }

        $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        $freeGB = $freeSpace / 1024 / 1024 / 1024;

        $status = self::STATUS_HEALTHY;
        $messages = [];

        if ($usedPercent > 95) {
            $status = self::STATUS_CRITICAL;
            $messages[] = 'Disk space is critically low';
        } elseif ($usedPercent > 85) {
            $status = self::STATUS_WARNING;
            $messages[] = 'Disk space is running low';
        }

        return [
            'name' => 'Disk Space',
            'status' => $status,
            'value' => sprintf('%.1f%% used', $usedPercent),
            'messages' => $messages,
            'details' => [
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeGB, 2),
                'used_percent' => round($usedPercent, 1),
            ],
        ];
    }

    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);

        $usedPercent = ($currentUsage / $memoryLimitBytes) * 100;

        $status = self::STATUS_HEALTHY;
        $messages = [];

        if ($usedPercent > 90) {
            $status = self::STATUS_CRITICAL;
            $messages[] = 'Memory usage is critically high';
        } elseif ($usedPercent > 75) {
            $status = self::STATUS_WARNING;
            $messages[] = 'Memory usage is high';
        }

        return [
            'name' => 'Memory',
            'status' => $status,
            'value' => sprintf('%.1f%% of %s', $usedPercent, $memoryLimit),
            'messages' => $messages,
            'details' => [
                'limit' => $memoryLimit,
                'current_mb' => round($currentUsage / 1024 / 1024, 2),
                'peak_mb' => round($peakUsage / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 1),
            ],
        ];
    }

    /**
     * Check FormFlow Pro tables
     */
    private function checkTables(): array
    {
        global $wpdb;

        $requiredTables = [
            $wpdb->prefix . 'ffp_forms',
            $wpdb->prefix . 'ffp_submissions',
            $wpdb->prefix . 'ffp_form_fields',
            $wpdb->prefix . 'ffp_payments',
            $wpdb->prefix . 'ffp_audit_log',
        ];

        $missingTables = [];
        $tableStats = [];

        foreach ($requiredTables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = %s",
                $table
            ));

            if (!$exists) {
                $missingTables[] = $table;
            } else {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
                $tableStats[$table] = (int) $count;
            }
        }

        $status = empty($missingTables) ? self::STATUS_HEALTHY : self::STATUS_CRITICAL;

        return [
            'name' => 'Database Tables',
            'status' => $status,
            'value' => empty($missingTables) ? 'All tables present' : 'Missing tables',
            'messages' => empty($missingTables) ? [] : ['Missing: ' . implode(', ', $missingTables)],
            'details' => [
                'required_count' => count($requiredTables),
                'missing' => $missingTables,
                'row_counts' => $tableStats,
            ],
        ];
    }

    /**
     * Check WordPress cron
     */
    private function checkCron(): array
    {
        $cronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $nextScheduled = wp_next_scheduled('ffp_cleanup_cron');

        $status = self::STATUS_HEALTHY;
        $messages = [];

        if ($cronDisabled) {
            $status = self::STATUS_WARNING;
            $messages[] = 'WordPress cron is disabled - ensure server cron is configured';
        }

        if (!$nextScheduled) {
            $messages[] = 'FormFlow cleanup cron not scheduled';
        }

        return [
            'name' => 'Cron Jobs',
            'status' => $status,
            'value' => $cronDisabled ? 'Disabled' : 'Enabled',
            'messages' => $messages,
            'details' => [
                'wp_cron_disabled' => $cronDisabled,
                'next_cleanup' => $nextScheduled ? date('Y-m-d H:i:s', $nextScheduled) : null,
            ],
        ];
    }

    /**
     * Check SSL status
     */
    private function checkSSL(): array
    {
        $isSSL = is_ssl();
        $siteUrl = get_site_url();

        $status = $isSSL ? self::STATUS_HEALTHY : self::STATUS_WARNING;

        return [
            'name' => 'SSL/HTTPS',
            'status' => $status,
            'value' => $isSSL ? 'Enabled' : 'Not enabled',
            'messages' => $isSSL ? [] : ['HTTPS is recommended for security'],
            'details' => [
                'is_ssl' => $isSSL,
                'site_url' => $siteUrl,
            ],
        ];
    }

    /**
     * Check file permissions
     */
    private function checkPermissions(): array
    {
        $uploadDir = wp_upload_dir();
        $isWritable = wp_is_writable($uploadDir['basedir']);

        return [
            'name' => 'File Permissions',
            'status' => $isWritable ? self::STATUS_HEALTHY : self::STATUS_CRITICAL,
            'value' => $isWritable ? 'Writable' : 'Not writable',
            'messages' => $isWritable ? [] : ['Upload directory is not writable'],
            'details' => [
                'upload_dir' => $uploadDir['basedir'],
                'is_writable' => $isWritable,
            ],
        ];
    }

    /**
     * Calculate overall status from all checks
     */
    private function calculateOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array(self::STATUS_CRITICAL, $statuses)) {
            return self::STATUS_CRITICAL;
        }

        if (in_array(self::STATUS_WARNING, $statuses)) {
            return self::STATUS_WARNING;
        }

        return self::STATUS_HEALTHY;
    }

    /**
     * Generate summary from checks
     */
    private function generateSummary(array $checks): array
    {
        $total = count($checks);
        $healthy = count(array_filter($checks, fn($c) => $c['status'] === self::STATUS_HEALTHY));
        $warnings = count(array_filter($checks, fn($c) => $c['status'] === self::STATUS_WARNING));
        $critical = count(array_filter($checks, fn($c) => $c['status'] === self::STATUS_CRITICAL));

        return [
            'total_checks' => $total,
            'healthy' => $healthy,
            'warnings' => $warnings,
            'critical' => $critical,
        ];
    }

    /**
     * Convert memory string to bytes
     */
    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $numValue = (int) $value;

        switch ($last) {
            case 'g':
                $numValue *= 1024;
                // fall through
            case 'm':
                $numValue *= 1024;
                // fall through
            case 'k':
                $numValue *= 1024;
        }

        return $numValue;
    }
}
