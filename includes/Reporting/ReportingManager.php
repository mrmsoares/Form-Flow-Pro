<?php
/**
 * Reporting Manager - Central Advanced Reporting System
 *
 * Coordinates report generation, scheduling, D3 visualization,
 * and provides the admin interface for reporting and analytics.
 *
 * @package FormFlowPro
 * @subpackage Reporting
 * @since 2.3.0
 */

namespace FormFlowPro\Reporting;

use FormFlowPro\Core\SingletonTrait;

/**
 * Scheduled Report model
 */
class ScheduledReport
{
    public int $id;
    public string $uuid;
    public string $name;
    public string $description;
    public string $template;
    public array $config;
    public string $schedule_type;
    public array $schedule_config;
    public array $recipients;
    public string $format;
    public string $status;
    public int $author_id;
    public string $last_run;
    public string $next_run;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->uuid = $data['uuid'] ?? wp_generate_uuid4();
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->template = $data['template'] ?? 'executive';
        $this->config = $data['config'] ?? [];
        $this->schedule_type = $data['schedule_type'] ?? 'daily';
        $this->schedule_config = $data['schedule_config'] ?? [];
        $this->recipients = $data['recipients'] ?? [];
        $this->format = $data['format'] ?? 'pdf';
        $this->status = $data['status'] ?? 'active';
        $this->author_id = $data['author_id'] ?? get_current_user_id();
        $this->last_run = $data['last_run'] ?? '';
        $this->next_run = $data['next_run'] ?? '';
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'template' => $this->template,
            'config' => $this->config,
            'schedule_type' => $this->schedule_type,
            'schedule_config' => $this->schedule_config,
            'recipients' => $this->recipients,
            'format' => $this->format,
            'status' => $this->status,
            'author_id' => $this->author_id,
            'last_run' => $this->last_run,
            'next_run' => $this->next_run,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

/**
 * Report History model
 */
class ReportHistory
{
    public int $id;
    public int $scheduled_report_id;
    public string $report_uuid;
    public string $status;
    public string $format;
    public string $file_path;
    public int $file_size;
    public array $delivery_status;
    public string $error_message;
    public int $generation_time_ms;
    public string $created_at;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->scheduled_report_id = $data['scheduled_report_id'] ?? 0;
        $this->report_uuid = $data['report_uuid'] ?? wp_generate_uuid4();
        $this->status = $data['status'] ?? 'pending';
        $this->format = $data['format'] ?? 'pdf';
        $this->file_path = $data['file_path'] ?? '';
        $this->file_size = $data['file_size'] ?? 0;
        $this->delivery_status = $data['delivery_status'] ?? [];
        $this->error_message = $data['error_message'] ?? '';
        $this->generation_time_ms = $data['generation_time_ms'] ?? 0;
        $this->created_at = $data['created_at'] ?? current_time('mysql');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'scheduled_report_id' => $this->scheduled_report_id,
            'report_uuid' => $this->report_uuid,
            'status' => $this->status,
            'format' => $this->format,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'delivery_status' => $this->delivery_status,
            'error_message' => $this->error_message,
            'generation_time_ms' => $this->generation_time_ms,
            'created_at' => $this->created_at
        ];
    }
}

/**
 * Dashboard Preset model
 */
class DashboardPreset
{
    public string $id;
    public string $name;
    public string $description;
    public array $widgets;
    public array $layout;
    public bool $is_default;
    public bool $is_system;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? wp_generate_uuid4();
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->widgets = $data['widgets'] ?? [];
        $this->layout = $data['layout'] ?? [];
        $this->is_default = $data['is_default'] ?? false;
        $this->is_system = $data['is_system'] ?? false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'widgets' => $this->widgets,
            'layout' => $this->layout,
            'is_default' => $this->is_default,
            'is_system' => $this->is_system
        ];
    }
}

/**
 * Reporting Manager class
 */
class ReportingManager
{
    use SingletonTrait;

    private ReportGenerator $generator;
    private D3Visualization $visualization;
    private array $dashboard_presets = [];

    /**
     * Initialize reporting manager
     */
    protected function init(): void
    {
        $this->generator = ReportGenerator::getInstance();
        $this->visualization = D3Visualization::getInstance();

        $this->registerDefaultPresets();
        $this->registerHooks();
        $this->createDatabaseTables();
        $this->registerCronJobs();
    }

    /**
     * Register hooks
     */
    private function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Cron hooks
        add_action('ffp_process_scheduled_reports', [$this, 'processScheduledReports']);
        add_action('ffp_cleanup_old_reports', [$this, 'cleanupOldReports']);

        // AJAX handlers
        add_action('wp_ajax_ffp_generate_report', [$this, 'ajaxGenerateReport']);
        add_action('wp_ajax_ffp_download_report', [$this, 'ajaxDownloadReport']);
        add_action('wp_ajax_ffp_save_scheduled_report', [$this, 'ajaxSaveScheduledReport']);
        add_action('wp_ajax_ffp_delete_scheduled_report', [$this, 'ajaxDeleteScheduledReport']);
        add_action('wp_ajax_ffp_run_scheduled_report', [$this, 'ajaxRunScheduledReport']);
        add_action('wp_ajax_ffp_get_report_preview', [$this, 'ajaxGetReportPreview']);
        add_action('wp_ajax_ffp_save_dashboard_preset', [$this, 'ajaxSaveDashboardPreset']);
        add_action('wp_ajax_ffp_get_analytics_data', [$this, 'ajaxGetAnalyticsData']);
    }

    /**
     * Create database tables
     */
    private function createDatabaseTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Scheduled reports table
        $sql_scheduled = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_scheduled_reports (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            template VARCHAR(50) NOT NULL DEFAULT 'executive',
            config LONGTEXT,
            schedule_type VARCHAR(20) NOT NULL DEFAULT 'daily',
            schedule_config LONGTEXT,
            recipients LONGTEXT,
            format VARCHAR(10) NOT NULL DEFAULT 'pdf',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            author_id BIGINT UNSIGNED NOT NULL,
            last_run DATETIME,
            next_run DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY status (status),
            KEY next_run (next_run)
        ) {$charset_collate};";

        // Report history table
        $sql_history = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_report_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scheduled_report_id BIGINT UNSIGNED,
            report_uuid VARCHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            format VARCHAR(10) NOT NULL DEFAULT 'pdf',
            file_path VARCHAR(500),
            file_size BIGINT UNSIGNED DEFAULT 0,
            delivery_status LONGTEXT,
            error_message TEXT,
            generation_time_ms INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY report_uuid (report_uuid),
            KEY scheduled_report_id (scheduled_report_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        // Dashboard presets table
        $sql_presets = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_dashboard_presets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            preset_id VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            widgets LONGTEXT,
            layout LONGTEXT,
            is_default TINYINT(1) DEFAULT 0,
            is_system TINYINT(1) DEFAULT 0,
            user_id BIGINT UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY preset_id (preset_id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_scheduled);
        dbDelta($sql_history);
        dbDelta($sql_presets);
    }

    /**
     * Register cron jobs
     */
    private function registerCronJobs(): void
    {
        if (!wp_next_scheduled('ffp_process_scheduled_reports')) {
            wp_schedule_event(time(), 'hourly', 'ffp_process_scheduled_reports');
        }

        if (!wp_next_scheduled('ffp_cleanup_old_reports')) {
            wp_schedule_event(time(), 'daily', 'ffp_cleanup_old_reports');
        }
    }

    /**
     * Register default dashboard presets
     */
    private function registerDefaultPresets(): void
    {
        // Executive Overview
        $this->registerPreset(new DashboardPreset([
            'id' => 'executive_overview',
            'name' => __('Executive Overview', 'form-flow-pro'),
            'description' => __('High-level KPIs and trends', 'form-flow-pro'),
            'is_system' => true,
            'is_default' => true,
            'widgets' => [
                ['id' => 'total_submissions', 'type' => 'kpi', 'width' => 3, 'height' => 120],
                ['id' => 'conversion_rate', 'type' => 'kpi', 'width' => 3, 'height' => 120],
                ['id' => 'avg_completion_time', 'type' => 'kpi', 'width' => 3, 'height' => 120],
                ['id' => 'active_forms', 'type' => 'kpi', 'width' => 3, 'height' => 120],
                ['id' => 'submissions_timeline', 'type' => 'chart', 'width' => 8, 'height' => 300, 'chart_config' => [
                    'type' => 'line',
                    'title' => __('Submissions Over Time', 'form-flow-pro')
                ]],
                ['id' => 'device_breakdown', 'type' => 'chart', 'width' => 4, 'height' => 300, 'chart_config' => [
                    'type' => 'donut',
                    'title' => __('Device Breakdown', 'form-flow-pro')
                ]],
                ['id' => 'top_forms', 'type' => 'chart', 'width' => 6, 'height' => 350, 'chart_config' => [
                    'type' => 'bar',
                    'title' => __('Top Performing Forms', 'form-flow-pro')
                ]],
                ['id' => 'conversion_funnel', 'type' => 'chart', 'width' => 6, 'height' => 350, 'chart_config' => [
                    'type' => 'funnel',
                    'title' => __('Conversion Funnel', 'form-flow-pro')
                ]]
            ]
        ]));

        // Form Analytics
        $this->registerPreset(new DashboardPreset([
            'id' => 'form_analytics',
            'name' => __('Form Analytics', 'form-flow-pro'),
            'description' => __('Detailed form performance analysis', 'form-flow-pro'),
            'is_system' => true,
            'widgets' => [
                ['id' => 'form_comparison', 'type' => 'chart', 'width' => 12, 'height' => 400, 'chart_config' => [
                    'type' => 'bar',
                    'title' => __('Form Comparison', 'form-flow-pro')
                ]],
                ['id' => 'field_completion', 'type' => 'chart', 'width' => 6, 'height' => 350, 'chart_config' => [
                    'type' => 'heatmap',
                    'title' => __('Field Completion Rates', 'form-flow-pro')
                ]],
                ['id' => 'drop_off_points', 'type' => 'chart', 'width' => 6, 'height' => 350, 'chart_config' => [
                    'type' => 'funnel',
                    'title' => __('Drop-off Points', 'form-flow-pro')
                ]]
            ]
        ]));

        // Real-time Monitor
        $this->registerPreset(new DashboardPreset([
            'id' => 'realtime_monitor',
            'name' => __('Real-time Monitor', 'form-flow-pro'),
            'description' => __('Live activity monitoring', 'form-flow-pro'),
            'is_system' => true,
            'widgets' => [
                ['id' => 'live_submissions', 'type' => 'kpi', 'width' => 4, 'height' => 120, 'refresh' => ['enabled' => true, 'interval' => 10]],
                ['id' => 'active_users', 'type' => 'kpi', 'width' => 4, 'height' => 120, 'refresh' => ['enabled' => true, 'interval' => 10]],
                ['id' => 'error_rate', 'type' => 'kpi', 'width' => 4, 'height' => 120, 'refresh' => ['enabled' => true, 'interval' => 10]],
                ['id' => 'live_timeline', 'type' => 'chart', 'width' => 12, 'height' => 300, 'chart_config' => [
                    'type' => 'line',
                    'title' => __('Live Activity', 'form-flow-pro')
                ], 'refresh' => ['enabled' => true, 'interval' => 30]]
            ]
        ]));

        do_action('ffp_register_dashboard_presets', $this);
    }

    /**
     * Register a dashboard preset
     */
    public function registerPreset(DashboardPreset $preset): void
    {
        $this->dashboard_presets[$preset->id] = $preset;
    }

    /**
     * Get all presets
     */
    public function getPresets(): array
    {
        return $this->dashboard_presets;
    }

    /**
     * Get preset by ID
     */
    public function getPreset(string $id): ?DashboardPreset
    {
        return $this->dashboard_presets[$id] ?? null;
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Reports & Analytics', 'form-flow-pro'),
            __('Reports', 'form-flow-pro'),
            'manage_options',
            'ffp-reports',
            [$this, 'renderReportsPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Analytics Dashboard', 'form-flow-pro'),
            __('Dashboard', 'form-flow-pro'),
            'manage_options',
            'ffp-dashboard',
            [$this, 'renderDashboardPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'ffp-reports') === false && strpos($hook, 'ffp-dashboard') === false) {
            return;
        }

        wp_enqueue_style(
            'ffp-reporting',
            FORMFLOW_PRO_URL . 'assets/css/reporting.css',
            [],
            FORMFLOW_PRO_VERSION
        );

        wp_enqueue_script(
            'ffp-reporting',
            FORMFLOW_PRO_URL . 'assets/js/reporting.js',
            ['jquery', 'wp-element', 'ffp-visualization'],
            FORMFLOW_PRO_VERSION,
            true
        );

        wp_localize_script('ffp-reporting', 'ffpReporting', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formflow/v1/reporting/'),
            'nonce' => wp_create_nonce('ffp_reporting'),
            'templates' => $this->generator->getTemplates(),
            'presets' => array_map(function ($p) {
                return $p->toArray();
            }, $this->dashboard_presets),
            'chartTypes' => $this->visualization->getChartTypes(),
            'colorSchemes' => $this->visualization->getColorSchemes(),
            'periods' => $this->getAvailablePeriods(),
            'formats' => $this->getAvailableFormats(),
            'scheduleTypes' => $this->getScheduleTypes(),
            'i18n' => [
                'generate' => __('Generate Report', 'form-flow-pro'),
                'download' => __('Download', 'form-flow-pro'),
                'schedule' => __('Schedule', 'form-flow-pro'),
                'preview' => __('Preview', 'form-flow-pro'),
                'delete' => __('Delete', 'form-flow-pro'),
                'confirmDelete' => __('Are you sure you want to delete this report?', 'form-flow-pro'),
                'generating' => __('Generating report...', 'form-flow-pro'),
                'reportReady' => __('Report ready for download', 'form-flow-pro'),
                'error' => __('An error occurred', 'form-flow-pro')
            ]
        ]);
    }

    /**
     * Get available periods
     */
    private function getAvailablePeriods(): array
    {
        return [
            'today' => __('Today', 'form-flow-pro'),
            'yesterday' => __('Yesterday', 'form-flow-pro'),
            'this_week' => __('This Week', 'form-flow-pro'),
            'last_week' => __('Last Week', 'form-flow-pro'),
            'last_7_days' => __('Last 7 Days', 'form-flow-pro'),
            'this_month' => __('This Month', 'form-flow-pro'),
            'last_month' => __('Last Month', 'form-flow-pro'),
            'last_30_days' => __('Last 30 Days', 'form-flow-pro'),
            'last_90_days' => __('Last 90 Days', 'form-flow-pro'),
            'this_quarter' => __('This Quarter', 'form-flow-pro'),
            'last_quarter' => __('Last Quarter', 'form-flow-pro'),
            'this_year' => __('This Year', 'form-flow-pro'),
            'last_year' => __('Last Year', 'form-flow-pro'),
            'custom' => __('Custom Range', 'form-flow-pro')
        ];
    }

    /**
     * Get available export formats
     */
    private function getAvailableFormats(): array
    {
        return [
            'pdf' => ['label' => __('PDF', 'form-flow-pro'), 'icon' => 'media-document', 'mime' => 'application/pdf'],
            'excel' => ['label' => __('Excel', 'form-flow-pro'), 'icon' => 'media-spreadsheet', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'csv' => ['label' => __('CSV', 'form-flow-pro'), 'icon' => 'media-text', 'mime' => 'text/csv'],
            'json' => ['label' => __('JSON', 'form-flow-pro'), 'icon' => 'media-code', 'mime' => 'application/json'],
            'html' => ['label' => __('HTML', 'form-flow-pro'), 'icon' => 'admin-site', 'mime' => 'text/html']
        ];
    }

    /**
     * Get schedule types
     */
    private function getScheduleTypes(): array
    {
        return [
            'once' => ['label' => __('One Time', 'form-flow-pro'), 'icon' => 'calendar'],
            'daily' => ['label' => __('Daily', 'form-flow-pro'), 'icon' => 'calendar-alt'],
            'weekly' => ['label' => __('Weekly', 'form-flow-pro'), 'icon' => 'calendar-alt'],
            'monthly' => ['label' => __('Monthly', 'form-flow-pro'), 'icon' => 'calendar-alt'],
            'quarterly' => ['label' => __('Quarterly', 'form-flow-pro'), 'icon' => 'calendar-alt']
        ];
    }

    /**
     * Render reports page
     */
    public function renderReportsPage(): void
    {
        $scheduled_reports = $this->getScheduledReports();
        $recent_reports = $this->getReportHistory(20);
        $templates = $this->generator->getTemplates();

        ?>
        <div class="wrap ffp-reports-wrap">
            <div class="ffp-reports-header">
                <h1><?php esc_html_e('Reports & Analytics', 'form-flow-pro'); ?></h1>
                <div class="ffp-header-actions">
                    <button type="button" class="button button-secondary" id="ffp-schedule-report">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php esc_html_e('Schedule Report', 'form-flow-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="ffp-generate-report">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Generate Report', 'form-flow-pro'); ?>
                    </button>
                </div>
            </div>

            <div class="ffp-reports-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#generate" class="nav-tab nav-tab-active" data-tab="generate">
                        <?php esc_html_e('Generate', 'form-flow-pro'); ?>
                    </a>
                    <a href="#scheduled" class="nav-tab" data-tab="scheduled">
                        <?php esc_html_e('Scheduled Reports', 'form-flow-pro'); ?>
                        <span class="count"><?php echo count($scheduled_reports); ?></span>
                    </a>
                    <a href="#history" class="nav-tab" data-tab="history">
                        <?php esc_html_e('History', 'form-flow-pro'); ?>
                    </a>
                </nav>

                <!-- Generate Tab -->
                <div id="tab-generate" class="ffp-tab-content active">
                    <div class="ffp-report-builder">
                        <div class="ffp-report-config">
                            <h3><?php esc_html_e('Report Configuration', 'form-flow-pro'); ?></h3>

                            <div class="ffp-form-row">
                                <label for="report-template"><?php esc_html_e('Template', 'form-flow-pro'); ?></label>
                                <select id="report-template" name="template">
                                    <?php foreach ($templates as $id => $template): ?>
                                    <option value="<?php echo esc_attr($id); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ffp-form-row">
                                <label for="report-period"><?php esc_html_e('Time Period', 'form-flow-pro'); ?></label>
                                <select id="report-period" name="period">
                                    <?php foreach ($this->getAvailablePeriods() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'last_30_days'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ffp-form-row ffp-custom-range" style="display: none;">
                                <label><?php esc_html_e('Custom Range', 'form-flow-pro'); ?></label>
                                <div class="ffp-date-range">
                                    <input type="date" id="report-start-date" name="start_date">
                                    <span><?php esc_html_e('to', 'form-flow-pro'); ?></span>
                                    <input type="date" id="report-end-date" name="end_date">
                                </div>
                            </div>

                            <div class="ffp-form-row">
                                <label for="report-form"><?php esc_html_e('Form (optional)', 'form-flow-pro'); ?></label>
                                <select id="report-form" name="form_id">
                                    <option value=""><?php esc_html_e('All Forms', 'form-flow-pro'); ?></option>
                                    <?php
                                    $forms = $this->getForms();
                                    foreach ($forms as $form):
                                    ?>
                                    <option value="<?php echo esc_attr($form['id']); ?>">
                                        <?php echo esc_html($form['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ffp-form-row">
                                <label for="report-format"><?php esc_html_e('Export Format', 'form-flow-pro'); ?></label>
                                <div class="ffp-format-options">
                                    <?php foreach ($this->getAvailableFormats() as $key => $format): ?>
                                    <label class="ffp-format-option">
                                        <input type="radio" name="format" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'pdf'); ?>>
                                        <span class="dashicons dashicons-<?php echo esc_attr($format['icon']); ?>"></span>
                                        <span><?php echo esc_html($format['label']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="ffp-form-actions">
                                <button type="button" class="button button-secondary" id="ffp-preview-report">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php esc_html_e('Preview', 'form-flow-pro'); ?>
                                </button>
                                <button type="button" class="button button-primary" id="ffp-generate-now">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Generate & Download', 'form-flow-pro'); ?>
                                </button>
                            </div>
                        </div>

                        <div class="ffp-report-preview">
                            <h3><?php esc_html_e('Preview', 'form-flow-pro'); ?></h3>
                            <div id="ffp-preview-container">
                                <div class="ffp-preview-placeholder">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <p><?php esc_html_e('Configure your report and click Preview to see a sample', 'form-flow-pro'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scheduled Reports Tab -->
                <div id="tab-scheduled" class="ffp-tab-content">
                    <div class="ffp-scheduled-list">
                        <?php if (empty($scheduled_reports)): ?>
                        <div class="ffp-empty-state">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <h3><?php esc_html_e('No Scheduled Reports', 'form-flow-pro'); ?></h3>
                            <p><?php esc_html_e('Create a scheduled report to automatically generate and send reports.', 'form-flow-pro'); ?></p>
                            <button type="button" class="button button-primary" id="ffp-new-scheduled">
                                <?php esc_html_e('Create Scheduled Report', 'form-flow-pro'); ?>
                            </button>
                        </div>
                        <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Name', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Template', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Schedule', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Recipients', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Last Run', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Next Run', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Status', 'form-flow-pro'); ?></th>
                                    <th><?php esc_html_e('Actions', 'form-flow-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheduled_reports as $report): ?>
                                <?php $this->renderScheduledReportRow($report); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History Tab -->
                <div id="tab-history" class="ffp-tab-content">
                    <div class="ffp-history-filters">
                        <select id="history-status-filter">
                            <option value=""><?php esc_html_e('All Status', 'form-flow-pro'); ?></option>
                            <option value="completed"><?php esc_html_e('Completed', 'form-flow-pro'); ?></option>
                            <option value="failed"><?php esc_html_e('Failed', 'form-flow-pro'); ?></option>
                        </select>
                        <input type="text" id="history-search" placeholder="<?php esc_attr_e('Search reports...', 'form-flow-pro'); ?>">
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Report', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Format', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Size', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Generated', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Status', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Actions', 'form-flow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ffp-history-tbody">
                            <?php foreach ($recent_reports as $report): ?>
                            <?php $this->renderHistoryRow($report); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Schedule Report Modal -->
            <div id="ffp-schedule-modal" class="ffp-modal" style="display: none;">
                <div class="ffp-modal-content">
                    <div class="ffp-modal-header">
                        <h2><?php esc_html_e('Schedule Report', 'form-flow-pro'); ?></h2>
                        <button type="button" class="ffp-modal-close">&times;</button>
                    </div>
                    <div class="ffp-modal-body">
                        <form id="ffp-schedule-form">
                            <div class="ffp-form-row">
                                <label for="schedule-name"><?php esc_html_e('Report Name', 'form-flow-pro'); ?></label>
                                <input type="text" id="schedule-name" name="name" required>
                            </div>

                            <div class="ffp-form-row">
                                <label for="schedule-template"><?php esc_html_e('Template', 'form-flow-pro'); ?></label>
                                <select id="schedule-template" name="template">
                                    <?php foreach ($templates as $id => $template): ?>
                                    <option value="<?php echo esc_attr($id); ?>">
                                        <?php echo esc_html($template['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ffp-form-row">
                                <label for="schedule-type"><?php esc_html_e('Schedule', 'form-flow-pro'); ?></label>
                                <select id="schedule-type" name="schedule_type">
                                    <?php foreach ($this->getScheduleTypes() as $key => $type): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($type['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ffp-form-row ffp-schedule-config">
                                <label><?php esc_html_e('Run at', 'form-flow-pro'); ?></label>
                                <div class="ffp-time-picker">
                                    <input type="time" id="schedule-time" name="time" value="09:00">
                                </div>
                            </div>

                            <div class="ffp-form-row ffp-weekly-config" style="display: none;">
                                <label><?php esc_html_e('Day of Week', 'form-flow-pro'); ?></label>
                                <select id="schedule-day-of-week" name="day_of_week">
                                    <option value="1"><?php esc_html_e('Monday', 'form-flow-pro'); ?></option>
                                    <option value="2"><?php esc_html_e('Tuesday', 'form-flow-pro'); ?></option>
                                    <option value="3"><?php esc_html_e('Wednesday', 'form-flow-pro'); ?></option>
                                    <option value="4"><?php esc_html_e('Thursday', 'form-flow-pro'); ?></option>
                                    <option value="5"><?php esc_html_e('Friday', 'form-flow-pro'); ?></option>
                                    <option value="6"><?php esc_html_e('Saturday', 'form-flow-pro'); ?></option>
                                    <option value="0"><?php esc_html_e('Sunday', 'form-flow-pro'); ?></option>
                                </select>
                            </div>

                            <div class="ffp-form-row ffp-monthly-config" style="display: none;">
                                <label><?php esc_html_e('Day of Month', 'form-flow-pro'); ?></label>
                                <select id="schedule-day-of-month" name="day_of_month">
                                    <?php for ($i = 1; $i <= 28; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                    <option value="last"><?php esc_html_e('Last day', 'form-flow-pro'); ?></option>
                                </select>
                            </div>

                            <div class="ffp-form-row">
                                <label for="schedule-recipients"><?php esc_html_e('Recipients', 'form-flow-pro'); ?></label>
                                <textarea id="schedule-recipients" name="recipients" rows="3" placeholder="<?php esc_attr_e('Enter email addresses, one per line', 'form-flow-pro'); ?>"></textarea>
                            </div>

                            <div class="ffp-form-row">
                                <label for="schedule-format"><?php esc_html_e('Format', 'form-flow-pro'); ?></label>
                                <select id="schedule-format" name="format">
                                    <?php foreach ($this->getAvailableFormats() as $key => $format): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($format['label']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="ffp-modal-footer">
                        <button type="button" class="button button-secondary ffp-modal-cancel">
                            <?php esc_html_e('Cancel', 'form-flow-pro'); ?>
                        </button>
                        <button type="button" class="button button-primary" id="ffp-save-schedule">
                            <?php esc_html_e('Save Schedule', 'form-flow-pro'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .ffp-reports-wrap {
                margin: 20px 20px 20px 0;
            }
            .ffp-reports-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            .ffp-header-actions {
                display: flex;
                gap: 10px;
            }
            .ffp-header-actions .dashicons {
                margin-right: 4px;
            }
            .nav-tab .count {
                display: inline-block;
                padding: 2px 8px;
                background: #2271b1;
                color: #fff;
                border-radius: 10px;
                font-size: 11px;
                margin-left: 6px;
            }
            .ffp-tab-content {
                display: none;
                background: #fff;
                padding: 24px;
                border: 1px solid #c3c4c7;
                border-top: none;
            }
            .ffp-tab-content.active {
                display: block;
            }
            .ffp-report-builder {
                display: grid;
                grid-template-columns: 400px 1fr;
                gap: 30px;
            }
            .ffp-report-config h3,
            .ffp-report-preview h3 {
                margin-top: 0;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 1px solid #dcdcde;
            }
            .ffp-form-row {
                margin-bottom: 20px;
            }
            .ffp-form-row label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
            }
            .ffp-form-row select,
            .ffp-form-row input[type="text"],
            .ffp-form-row input[type="date"],
            .ffp-form-row input[type="time"],
            .ffp-form-row textarea {
                width: 100%;
            }
            .ffp-date-range {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .ffp-date-range input {
                flex: 1;
            }
            .ffp-format-options {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            .ffp-format-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 16px 20px;
                background: #f6f7f7;
                border: 2px solid transparent;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
            }
            .ffp-format-option:hover {
                background: #f0f0f1;
            }
            .ffp-format-option input {
                display: none;
            }
            .ffp-format-option input:checked + .dashicons {
                color: #2271b1;
            }
            .ffp-format-option input:checked ~ span {
                color: #2271b1;
            }
            .ffp-format-option:has(input:checked) {
                border-color: #2271b1;
                background: #f0f6fc;
            }
            .ffp-format-option .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                margin-bottom: 8px;
            }
            .ffp-form-actions {
                display: flex;
                gap: 10px;
                margin-top: 30px;
            }
            .ffp-form-actions .dashicons {
                margin-right: 4px;
            }
            .ffp-preview-placeholder {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 400px;
                background: #f6f7f7;
                border-radius: 8px;
                color: #646970;
            }
            .ffp-preview-placeholder .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                margin-bottom: 16px;
            }
            .ffp-empty-state {
                text-align: center;
                padding: 60px 20px;
            }
            .ffp-empty-state .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #c3c4c7;
                margin-bottom: 16px;
            }
            .ffp-empty-state h3 {
                margin: 0 0 8px 0;
            }
            .ffp-empty-state p {
                color: #646970;
                margin: 0 0 20px 0;
            }
            .ffp-status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }
            .ffp-status-active { background: #d4edda; color: #155724; }
            .ffp-status-paused { background: #fff3cd; color: #856404; }
            .ffp-status-completed { background: #d4edda; color: #155724; }
            .ffp-status-failed { background: #f8d7da; color: #721c24; }
            .ffp-status-pending { background: #e2e3e5; color: #383d41; }
            .ffp-modal {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ffp-modal-content {
                background: #fff;
                border-radius: 8px;
                width: 500px;
                max-width: 90vw;
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }
            .ffp-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #dcdcde;
            }
            .ffp-modal-header h2 {
                margin: 0;
            }
            .ffp-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #646970;
            }
            .ffp-modal-body {
                padding: 20px;
                overflow-y: auto;
            }
            .ffp-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                padding: 16px 20px;
                border-top: 1px solid #dcdcde;
            }
            .ffp-history-filters {
                display: flex;
                gap: 12px;
                margin-bottom: 20px;
            }
            .ffp-history-filters select {
                width: 200px;
            }
            .ffp-history-filters input {
                flex: 1;
                max-width: 300px;
            }
        </style>
        <?php
    }

    /**
     * Render scheduled report row
     */
    private function renderScheduledReportRow($report): void
    {
        $template = $this->generator->getTemplate($report['template']);
        $schedule_types = $this->getScheduleTypes();
        $recipients = json_decode($report['recipients'] ?? '[]', true);

        ?>
        <tr data-id="<?php echo esc_attr($report['id']); ?>">
            <td>
                <strong><?php echo esc_html($report['name']); ?></strong>
                <?php if ($report['description']): ?>
                <p class="description"><?php echo esc_html(wp_trim_words($report['description'], 10)); ?></p>
                <?php endif; ?>
            </td>
            <td><?php echo esc_html($template['name'] ?? $report['template']); ?></td>
            <td>
                <span class="dashicons dashicons-<?php echo esc_attr($schedule_types[$report['schedule_type']]['icon'] ?? 'calendar'); ?>"></span>
                <?php echo esc_html($schedule_types[$report['schedule_type']]['label'] ?? $report['schedule_type']); ?>
            </td>
            <td>
                <?php echo esc_html(count($recipients)); ?>
                <?php esc_html_e('recipients', 'form-flow-pro'); ?>
            </td>
            <td>
                <?php if ($report['last_run']): ?>
                <?php echo esc_html(human_time_diff(strtotime($report['last_run']), time())); ?>
                <?php esc_html_e('ago', 'form-flow-pro'); ?>
                <?php else: ?>
                <em><?php esc_html_e('Never', 'form-flow-pro'); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($report['next_run'] && $report['status'] === 'active'): ?>
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($report['next_run']))); ?>
                <?php else: ?>
                <em>-</em>
                <?php endif; ?>
            </td>
            <td>
                <span class="ffp-status-badge ffp-status-<?php echo esc_attr($report['status']); ?>">
                    <?php echo esc_html(ucfirst($report['status'])); ?>
                </span>
            </td>
            <td>
                <div class="ffp-row-actions">
                    <button type="button" class="button button-small ffp-run-now" data-id="<?php echo esc_attr($report['id']); ?>">
                        <?php esc_html_e('Run Now', 'form-flow-pro'); ?>
                    </button>
                    <button type="button" class="button button-small ffp-edit-scheduled" data-id="<?php echo esc_attr($report['id']); ?>">
                        <?php esc_html_e('Edit', 'form-flow-pro'); ?>
                    </button>
                    <button type="button" class="button button-small ffp-delete-scheduled" data-id="<?php echo esc_attr($report['id']); ?>">
                        <?php esc_html_e('Delete', 'form-flow-pro'); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Render history row
     */
    private function renderHistoryRow($report): void
    {
        $formats = $this->getAvailableFormats();

        ?>
        <tr data-id="<?php echo esc_attr($report['id']); ?>">
            <td>
                <strong><?php echo esc_html($report['report_uuid']); ?></strong>
            </td>
            <td>
                <span class="dashicons dashicons-<?php echo esc_attr($formats[$report['format']]['icon'] ?? 'media-default'); ?>"></span>
                <?php echo esc_html(strtoupper($report['format'])); ?>
            </td>
            <td>
                <?php echo esc_html(size_format($report['file_size'])); ?>
            </td>
            <td>
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($report['created_at']))); ?>
            </td>
            <td>
                <span class="ffp-status-badge ffp-status-<?php echo esc_attr($report['status']); ?>">
                    <?php echo esc_html(ucfirst($report['status'])); ?>
                </span>
            </td>
            <td>
                <?php if ($report['status'] === 'completed' && $report['file_path']): ?>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-ajax.php?action=ffp_download_report&id=' . $report['id']), 'ffp_download_report')); ?>" class="button button-small">
                    <?php esc_html_e('Download', 'form-flow-pro'); ?>
                </a>
                <?php elseif ($report['error_message']): ?>
                <span class="description"><?php echo esc_html(wp_trim_words($report['error_message'], 10)); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void
    {
        $current_preset = get_user_meta(get_current_user_id(), 'ffp_dashboard_preset', true) ?: 'executive_overview';
        $preset = $this->getPreset($current_preset);

        ?>
        <div class="wrap ffp-dashboard-wrap">
            <div class="ffp-dashboard-header">
                <h1><?php esc_html_e('Analytics Dashboard', 'form-flow-pro'); ?></h1>
                <div class="ffp-header-controls">
                    <select id="ffp-period-selector">
                        <?php foreach ($this->getAvailablePeriods() as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'last_30_days'); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="ffp-preset-selector">
                        <?php foreach ($this->dashboard_presets as $id => $p): ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($id, $current_preset); ?>>
                            <?php echo esc_html($p->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button" class="button" id="ffp-refresh-dashboard">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'form-flow-pro'); ?>
                    </button>

                    <button type="button" class="button" id="ffp-customize-dashboard">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e('Customize', 'form-flow-pro'); ?>
                    </button>
                </div>
            </div>

            <div id="ffp-dashboard-container">
                <?php
                if ($preset) {
                    echo $this->visualization->renderDashboard($preset->widgets);
                }
                ?>
            </div>
        </div>

        <style>
            .ffp-dashboard-wrap {
                margin: 20px 20px 20px 0;
            }
            .ffp-dashboard-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #dcdcde;
            }
            .ffp-header-controls {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .ffp-header-controls .dashicons {
                margin-right: 4px;
            }
        </style>
        <?php
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/reporting/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'restGenerateReport'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/reporting/scheduled', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetScheduledReports'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateScheduledReport'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ]
        ]);

        register_rest_route('formflow/v1', '/reporting/scheduled/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetScheduledReport'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'restUpdateScheduledReport'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteScheduledReport'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ]
        ]);

        register_rest_route('formflow/v1', '/reporting/scheduled/(?P<id>\d+)/run', [
            'methods' => 'POST',
            'callback' => [$this, 'restRunScheduledReport'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/reporting/history', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetHistory'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/reporting/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAnalytics'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    // ==================== REST Handlers ====================

    public function restGenerateReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $config = $request->get_json_params();
        $format = $config['format'] ?? 'pdf';

        try {
            $start_time = microtime(true);

            $report_data = $this->generator->generate($config);

            $file_path = match ($format) {
                'pdf' => $this->generator->exportPDF($report_data),
                'excel' => $this->generator->exportExcel($report_data),
                'csv' => $this->generator->exportCSV($report_data),
                'json' => $this->generator->exportJSON($report_data),
                default => $this->generator->exportPDF($report_data)
            };

            $generation_time = round((microtime(true) - $start_time) * 1000);

            // Log to history
            $this->logReportHistory(0, [
                'status' => 'completed',
                'format' => $format,
                'file_path' => $file_path,
                'file_size' => filesize($file_path),
                'generation_time_ms' => $generation_time
            ]);

            return new \WP_REST_Response([
                'success' => true,
                'file_path' => $file_path,
                'download_url' => $this->getDownloadUrl($file_path),
                'generation_time_ms' => $generation_time
            ]);

        } catch (\Exception $e) {
            $this->logReportHistory(0, [
                'status' => 'failed',
                'format' => $format,
                'error_message' => $e->getMessage()
            ]);

            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function restGetScheduledReports(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'reports' => $this->getScheduledReports()
        ]);
    }

    public function restGetScheduledReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $report = $this->getScheduledReport($id);

        if (!$report) {
            return new \WP_REST_Response(['error' => 'Report not found'], 404);
        }

        return new \WP_REST_Response(['report' => $report]);
    }

    public function restCreateScheduledReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $id = $this->saveScheduledReport($data);

        return new \WP_REST_Response([
            'success' => true,
            'id' => $id
        ], 201);
    }

    public function restUpdateScheduledReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $data = $request->get_json_params();
        $data['id'] = $id;

        $this->saveScheduledReport($data);

        return new \WP_REST_Response(['success' => true]);
    }

    public function restDeleteScheduledReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->deleteScheduledReport($id);

        return new \WP_REST_Response(['success' => true]);
    }

    public function restRunScheduledReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $result = $this->runScheduledReport($id);
            return new \WP_REST_Response($result);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function restGetHistory(\WP_REST_Request $request): \WP_REST_Response
    {
        $limit = (int) ($request->get_param('limit') ?? 50);

        return new \WP_REST_Response([
            'history' => $this->getReportHistory($limit)
        ]);
    }

    public function restGetAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        $period = $request->get_param('period') ?? 'last_30_days';
        $form_id = $request->get_param('form_id');

        $config = ['period' => $period];
        if ($form_id) {
            $config['form_id'] = (int) $form_id;
        }

        $report_data = $this->generator->generate($config);

        return new \WP_REST_Response([
            'metrics' => $report_data->metrics,
            'charts' => $report_data->charts,
            'tables' => $report_data->tables
        ]);
    }

    // ==================== Data Methods ====================

    /**
     * Get scheduled reports
     */
    public function getScheduledReports(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ffp_scheduled_reports ORDER BY created_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get scheduled report by ID
     */
    public function getScheduledReport(int $id): ?array
    {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_scheduled_reports WHERE id = %d",
            $id
        ), ARRAY_A);
    }

    /**
     * Save scheduled report
     */
    public function saveScheduledReport(array $data): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_scheduled_reports';
        $id = $data['id'] ?? 0;

        $row = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'template' => sanitize_key($data['template'] ?? 'executive'),
            'config' => wp_json_encode($data['config'] ?? []),
            'schedule_type' => sanitize_key($data['schedule_type'] ?? 'daily'),
            'schedule_config' => wp_json_encode($data['schedule_config'] ?? []),
            'recipients' => wp_json_encode($data['recipients'] ?? []),
            'format' => sanitize_key($data['format'] ?? 'pdf'),
            'status' => sanitize_key($data['status'] ?? 'active'),
            'author_id' => get_current_user_id()
        ];

        // Calculate next run
        $row['next_run'] = $this->calculateNextRun(
            $data['schedule_type'] ?? 'daily',
            $data['schedule_config'] ?? []
        );

        if ($id > 0) {
            $wpdb->update($table, $row, ['id' => $id]);
        } else {
            $row['uuid'] = wp_generate_uuid4();
            $wpdb->insert($table, $row);
            $id = $wpdb->insert_id;
        }

        return $id;
    }

    /**
     * Delete scheduled report
     */
    public function deleteScheduledReport(int $id): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'ffp_scheduled_reports', ['id' => $id]);
    }

    /**
     * Calculate next run time
     */
    private function calculateNextRun(string $schedule_type, array $config): string
    {
        $time = $config['time'] ?? '09:00';
        $now = current_time('timestamp');

        switch ($schedule_type) {
            case 'daily':
                $next = strtotime("today {$time}");
                if ($next <= $now) {
                    $next = strtotime("tomorrow {$time}");
                }
                break;

            case 'weekly':
                $day = $config['day_of_week'] ?? 1;
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $day_name = $days[$day] ?? 'Monday';
                $next = strtotime("next {$day_name} {$time}");
                break;

            case 'monthly':
                $day = $config['day_of_month'] ?? 1;
                if ($day === 'last') {
                    $next = strtotime("last day of this month {$time}");
                    if ($next <= $now) {
                        $next = strtotime("last day of next month {$time}");
                    }
                } else {
                    $next = strtotime(date('Y-m-') . sprintf('%02d', $day) . " {$time}");
                    if ($next <= $now) {
                        $next = strtotime('+1 month', $next);
                    }
                }
                break;

            case 'quarterly':
                $quarter_months = [1, 4, 7, 10];
                $current_month = (int) date('n');
                $next_quarter_month = null;

                foreach ($quarter_months as $qm) {
                    if ($qm > $current_month) {
                        $next_quarter_month = $qm;
                        break;
                    }
                }

                if (!$next_quarter_month) {
                    $next_quarter_month = 1;
                    $year = date('Y') + 1;
                } else {
                    $year = date('Y');
                }

                $next = strtotime("{$year}-{$next_quarter_month}-01 {$time}");
                break;

            case 'once':
            default:
                $date = $config['date'] ?? date('Y-m-d');
                $next = strtotime("{$date} {$time}");
                break;
        }

        return date('Y-m-d H:i:s', $next);
    }

    /**
     * Get report history
     */
    public function getReportHistory(int $limit = 50): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_report_history ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * Log report to history
     */
    private function logReportHistory(int $scheduled_report_id, array $data): int
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'ffp_report_history', [
            'scheduled_report_id' => $scheduled_report_id,
            'report_uuid' => wp_generate_uuid4(),
            'status' => $data['status'],
            'format' => $data['format'] ?? 'pdf',
            'file_path' => $data['file_path'] ?? '',
            'file_size' => $data['file_size'] ?? 0,
            'delivery_status' => wp_json_encode($data['delivery_status'] ?? []),
            'error_message' => $data['error_message'] ?? '',
            'generation_time_ms' => $data['generation_time_ms'] ?? 0
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Get download URL for a report file
     */
    private function getDownloadUrl(string $file_path): string
    {
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'], '', $file_path);
        return $upload_dir['baseurl'] . $relative_path;
    }

    /**
     * Get forms list
     */
    private function getForms(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, title FROM {$wpdb->prefix}ffp_forms WHERE status = 'active' ORDER BY title",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Process scheduled reports (cron job)
     */
    public function processScheduledReports(): void
    {
        global $wpdb;

        $now = current_time('mysql');

        $due_reports = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_scheduled_reports
             WHERE status = 'active' AND next_run <= %s",
            $now
        ), ARRAY_A);

        foreach ($due_reports as $report) {
            try {
                $this->runScheduledReport($report['id']);
            } catch (\Exception $e) {
                error_log('FormFlow Pro: Failed to run scheduled report ' . $report['id'] . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Run a scheduled report
     */
    public function runScheduledReport(int $id): array
    {
        global $wpdb;

        $report = $this->getScheduledReport($id);

        if (!$report) {
            throw new \Exception('Scheduled report not found');
        }

        $start_time = microtime(true);

        // Generate report
        $config = json_decode($report['config'] ?? '{}', true);
        $config['template'] = $report['template'];

        $report_data = $this->generator->generate($config);

        // Export to file
        $file_path = match ($report['format']) {
            'pdf' => $this->generator->exportPDF($report_data),
            'excel' => $this->generator->exportExcel($report_data),
            'csv' => $this->generator->exportCSV($report_data),
            'json' => $this->generator->exportJSON($report_data),
            default => $this->generator->exportPDF($report_data)
        };

        $generation_time = round((microtime(true) - $start_time) * 1000);

        // Send to recipients
        $recipients = json_decode($report['recipients'] ?? '[]', true);
        $delivery_status = $this->sendReportToRecipients($file_path, $recipients, $report);

        // Log to history
        $history_id = $this->logReportHistory($id, [
            'status' => 'completed',
            'format' => $report['format'],
            'file_path' => $file_path,
            'file_size' => filesize($file_path),
            'delivery_status' => $delivery_status,
            'generation_time_ms' => $generation_time
        ]);

        // Update scheduled report
        $wpdb->update(
            $wpdb->prefix . 'ffp_scheduled_reports',
            [
                'last_run' => current_time('mysql'),
                'next_run' => $this->calculateNextRun(
                    $report['schedule_type'],
                    json_decode($report['schedule_config'] ?? '{}', true)
                )
            ],
            ['id' => $id]
        );

        return [
            'success' => true,
            'history_id' => $history_id,
            'file_path' => $file_path,
            'generation_time_ms' => $generation_time,
            'recipients_count' => count($recipients)
        ];
    }

    /**
     * Send report to recipients
     */
    private function sendReportToRecipients(string $file_path, array $recipients, array $report): array
    {
        $status = [];

        foreach ($recipients as $email) {
            $email = sanitize_email($email);
            if (!is_email($email)) {
                $status[$email] = ['success' => false, 'error' => 'Invalid email'];
                continue;
            }

            $subject = sprintf(
                __('[%s] Scheduled Report: %s', 'form-flow-pro'),
                get_bloginfo('name'),
                $report['name']
            );

            $message = sprintf(
                __("Your scheduled report '%s' has been generated.\n\nPlease find the report attached to this email.", 'form-flow-pro'),
                $report['name']
            );

            $headers = ['Content-Type: text/plain; charset=UTF-8'];

            $sent = wp_mail($email, $subject, $message, $headers, [$file_path]);

            $status[$email] = ['success' => $sent];
        }

        return $status;
    }

    /**
     * Cleanup old reports (cron job)
     */
    public function cleanupOldReports(): void
    {
        $retention_days = apply_filters('ffp_report_retention_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        global $wpdb;

        // Get old reports
        $old_reports = $wpdb->get_results($wpdb->prepare(
            "SELECT file_path FROM {$wpdb->prefix}ffp_report_history WHERE created_at < %s",
            $cutoff_date
        ));

        // Delete files
        foreach ($old_reports as $report) {
            if (!empty($report->file_path) && file_exists($report->file_path)) {
                wp_delete_file($report->file_path);
            }
        }

        // Delete records
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ffp_report_history WHERE created_at < %s",
            $cutoff_date
        ));
    }

    // ==================== AJAX Handlers ====================

    public function ajaxGenerateReport(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $config = [
            'template' => sanitize_key($_POST['template'] ?? 'executive'),
            'period' => sanitize_key($_POST['period'] ?? 'last_30_days'),
            'form_id' => intval($_POST['form_id'] ?? 0) ?: null
        ];

        if ($_POST['period'] === 'custom') {
            $config['period_start'] = sanitize_text_field($_POST['start_date'] ?? '');
            $config['period_end'] = sanitize_text_field($_POST['end_date'] ?? '');
        }

        $format = sanitize_key($_POST['format'] ?? 'pdf');

        try {
            $report_data = $this->generator->generate($config);

            $file_path = match ($format) {
                'pdf' => $this->generator->exportPDF($report_data),
                'excel' => $this->generator->exportExcel($report_data),
                'csv' => $this->generator->exportCSV($report_data),
                'json' => $this->generator->exportJSON($report_data),
                default => $this->generator->exportPDF($report_data)
            };

            wp_send_json_success([
                'file_path' => $file_path,
                'download_url' => $this->getDownloadUrl($file_path)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxDownloadReport(): void
    {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ffp_download_report')) {
            wp_die('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $id = intval($_GET['id'] ?? 0);

        global $wpdb;
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_report_history WHERE id = %d",
            $id
        ));

        if (!$report || !file_exists($report->file_path)) {
            wp_die('Report not found');
        }

        $formats = $this->getAvailableFormats();
        $mime = $formats[$report->format]['mime'] ?? 'application/octet-stream';
        $filename = basename($report->file_path);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($report->file_path));

        readfile($report->file_path);
        exit;
    }

    public function ajaxSaveScheduledReport(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $data = [
            'id' => intval($_POST['id'] ?? 0),
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'template' => sanitize_key($_POST['template'] ?? 'executive'),
            'schedule_type' => sanitize_key($_POST['schedule_type'] ?? 'daily'),
            'schedule_config' => [
                'time' => sanitize_text_field($_POST['time'] ?? '09:00'),
                'day_of_week' => intval($_POST['day_of_week'] ?? 1),
                'day_of_month' => sanitize_text_field($_POST['day_of_month'] ?? '1')
            ],
            'recipients' => array_filter(array_map('trim', explode("\n", $_POST['recipients'] ?? ''))),
            'format' => sanitize_key($_POST['format'] ?? 'pdf')
        ];

        $id = $this->saveScheduledReport($data);

        wp_send_json_success(['id' => $id]);
    }

    public function ajaxDeleteScheduledReport(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);
        $this->deleteScheduledReport($id);

        wp_send_json_success();
    }

    public function ajaxRunScheduledReport(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['id'] ?? 0);

        try {
            $result = $this->runScheduledReport($id);
            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxGetReportPreview(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $config = [
            'template' => sanitize_key($_POST['template'] ?? 'executive'),
            'period' => sanitize_key($_POST['period'] ?? 'last_30_days'),
            'form_id' => intval($_POST['form_id'] ?? 0) ?: null
        ];

        try {
            $report_data = $this->generator->generate($config);
            $html = $this->generator->renderReportHTML($report_data);

            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxSaveDashboardPreset(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $preset_id = sanitize_key($_POST['preset_id'] ?? '');

        update_user_meta(get_current_user_id(), 'ffp_dashboard_preset', $preset_id);

        wp_send_json_success();
    }

    public function ajaxGetAnalyticsData(): void
    {
        check_ajax_referer('ffp_reporting', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $period = sanitize_key($_POST['period'] ?? 'last_30_days');
        $form_id = intval($_POST['form_id'] ?? 0) ?: null;

        $config = ['period' => $period];
        if ($form_id) {
            $config['form_id'] = $form_id;
        }

        try {
            $report_data = $this->generator->generate($config);

            wp_send_json_success([
                'metrics' => $report_data->metrics,
                'charts' => $report_data->charts
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
