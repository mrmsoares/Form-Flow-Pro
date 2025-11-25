<?php

declare(strict_types=1);

/**
 * Multi-Site Manager
 *
 * Handles WordPress Multisite network functionality.
 *
 * @package FormFlowPro\MultiSite
 * @since 2.3.0
 */

namespace FormFlowPro\MultiSite;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multi-Site Manager Class
 */
class MultiSiteManager
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Whether multisite is active
     *
     * @var bool
     */
    private bool $isMultisite;

    /**
     * Network settings option name
     *
     * @var string
     */
    private const NETWORK_OPTION = 'formflow_network_settings';

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->isMultisite = is_multisite();
        $this->setupHooks();
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        if (!$this->isMultisite) {
            return;
        }

        // Network admin hooks
        add_action('network_admin_menu', [$this, 'addNetworkMenu']);
        add_action('network_admin_notices', [$this, 'displayNetworkNotices']);

        // Site activation/deactivation
        add_action('activate_blog', [$this, 'onSiteActivate']);
        add_action('deactivate_blog', [$this, 'onSiteDeactivate']);
        add_action('delete_blog', [$this, 'onSiteDelete'], 10, 2);

        // New site created
        add_action('wp_initialize_site', [$this, 'onNewSite'], 10, 2);

        // Plugin activation hooks
        add_action('formflow_network_activate', [$this, 'networkActivate']);
        add_action('formflow_network_deactivate', [$this, 'networkDeactivate']);

        // Data sync hooks
        add_action('formflow_sync_network_data', [$this, 'syncNetworkData']);

        // REST API routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Check if multisite is active
     *
     * @return bool
     */
    public function isMultisite(): bool
    {
        return $this->isMultisite;
    }

    /**
     * Add network admin menu
     *
     * @return void
     */
    public function addNetworkMenu(): void
    {
        add_menu_page(
            __('FormFlow Pro Network', 'formflow-pro'),
            __('FormFlow Pro', 'formflow-pro'),
            'manage_network_options',
            'formflow-network',
            [$this, 'renderNetworkDashboard'],
            'dashicons-forms',
            30
        );

        add_submenu_page(
            'formflow-network',
            __('Network Dashboard', 'formflow-pro'),
            __('Dashboard', 'formflow-pro'),
            'manage_network_options',
            'formflow-network',
            [$this, 'renderNetworkDashboard']
        );

        add_submenu_page(
            'formflow-network',
            __('Sites Overview', 'formflow-pro'),
            __('Sites', 'formflow-pro'),
            'manage_network_options',
            'formflow-network-sites',
            [$this, 'renderSitesOverview']
        );

        add_submenu_page(
            'formflow-network',
            __('Network Settings', 'formflow-pro'),
            __('Settings', 'formflow-pro'),
            'manage_network_options',
            'formflow-network-settings',
            [$this, 'renderNetworkSettings']
        );

        add_submenu_page(
            'formflow-network',
            __('License Management', 'formflow-pro'),
            __('Licenses', 'formflow-pro'),
            'manage_network_options',
            'formflow-network-licenses',
            [$this, 'renderLicenseManagement']
        );
    }

    /**
     * Render network dashboard
     *
     * @return void
     */
    public function renderNetworkDashboard(): void
    {
        $stats = $this->getNetworkStats();
        include FORMFLOW_PATH . 'includes/admin/views/network-dashboard.php';
    }

    /**
     * Render sites overview
     *
     * @return void
     */
    public function renderSitesOverview(): void
    {
        $sites = $this->getAllSitesData();
        include FORMFLOW_PATH . 'includes/admin/views/network-sites.php';
    }

    /**
     * Render network settings
     *
     * @return void
     */
    public function renderNetworkSettings(): void
    {
        $settings = $this->getNetworkSettings();
        include FORMFLOW_PATH . 'includes/admin/views/network-settings.php';
    }

    /**
     * Render license management
     *
     * @return void
     */
    public function renderLicenseManagement(): void
    {
        $licenses = $this->getLicenseInfo();
        include FORMFLOW_PATH . 'includes/admin/views/network-licenses.php';
    }

    /**
     * Get network settings
     *
     * @return array
     */
    public function getNetworkSettings(): array
    {
        $defaults = [
            'network_activated' => false,
            'sync_settings' => false,
            'shared_templates' => true,
            'centralized_analytics' => true,
            'global_integrations' => false,
            'license_key' => '',
            'license_type' => 'network',
            'max_sites' => 0, // 0 = unlimited
            'data_retention_days' => 90,
            'enable_cross_site_reporting' => true,
        ];

        $settings = get_site_option(self::NETWORK_OPTION, []);
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Save network settings
     *
     * @param array $settings Settings to save
     * @return bool
     */
    public function saveNetworkSettings(array $settings): bool
    {
        $sanitized = [
            'network_activated' => !empty($settings['network_activated']),
            'sync_settings' => !empty($settings['sync_settings']),
            'shared_templates' => !empty($settings['shared_templates']),
            'centralized_analytics' => !empty($settings['centralized_analytics']),
            'global_integrations' => !empty($settings['global_integrations']),
            'license_key' => sanitize_text_field($settings['license_key'] ?? ''),
            'license_type' => sanitize_text_field($settings['license_type'] ?? 'network'),
            'max_sites' => (int) ($settings['max_sites'] ?? 0),
            'data_retention_days' => (int) ($settings['data_retention_days'] ?? 90),
            'enable_cross_site_reporting' => !empty($settings['enable_cross_site_reporting']),
        ];

        return update_site_option(self::NETWORK_OPTION, $sanitized);
    }

    /**
     * Get network statistics
     *
     * @return array
     */
    public function getNetworkStats(): array
    {
        if (!$this->isMultisite) {
            return [];
        }

        $cache_key = 'formflow_network_stats';
        $cached = get_site_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $sites = get_sites(['number' => 0]);
        $totalForms = 0;
        $totalSubmissions = 0;
        $totalDocuments = 0;
        $activeSites = 0;

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            // Check if plugin is active on this site
            if ($this->isPluginActiveOnSite($site->blog_id)) {
                $activeSites++;

                global $wpdb;

                // Count forms
                $forms = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms");
                $totalForms += (int) $forms;

                // Count submissions
                $submissions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions");
                $totalSubmissions += (int) $submissions;

                // Count documents
                $tableExists = $wpdb->get_var(
                    $wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'formflow_autentique_documents')
                );
                if ($tableExists) {
                    $documents = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents");
                    $totalDocuments += (int) $documents;
                }
            }

            restore_current_blog();
        }

        $stats = [
            'total_sites' => count($sites),
            'active_sites' => $activeSites,
            'total_forms' => $totalForms,
            'total_submissions' => $totalSubmissions,
            'total_documents' => $totalDocuments,
            'submissions_today' => $this->getNetworkSubmissionsToday(),
            'top_sites' => $this->getTopSitesBySubmissions(5),
        ];

        set_site_transient($cache_key, $stats, 300); // 5 min cache

        return $stats;
    }

    /**
     * Get network submissions today
     *
     * @return int
     */
    private function getNetworkSubmissionsToday(): int
    {
        $total = 0;
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            if ($this->isPluginActiveOnSite($site->blog_id)) {
                global $wpdb;
                $count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions
                     WHERE DATE(created_at) = CURDATE()"
                );
                $total += (int) $count;
            }

            restore_current_blog();
        }

        return $total;
    }

    /**
     * Get top sites by submissions
     *
     * @param int $limit Number of sites to return
     * @return array
     */
    private function getTopSitesBySubmissions(int $limit = 5): array
    {
        $sitesData = [];
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            if ($this->isPluginActiveOnSite($site->blog_id)) {
                global $wpdb;
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions");

                $sitesData[] = [
                    'blog_id' => $site->blog_id,
                    'name' => get_bloginfo('name'),
                    'url' => get_site_url(),
                    'submissions' => (int) $count,
                ];
            }

            restore_current_blog();
        }

        // Sort by submissions descending
        usort($sitesData, function ($a, $b) {
            return $b['submissions'] - $a['submissions'];
        });

        return array_slice($sitesData, 0, $limit);
    }

    /**
     * Get all sites data
     *
     * @return array
     */
    public function getAllSitesData(): array
    {
        if (!$this->isMultisite) {
            return [];
        }

        $sites = get_sites(['number' => 0]);
        $sitesData = [];

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $isActive = $this->isPluginActiveOnSite($site->blog_id);

            $data = [
                'blog_id' => $site->blog_id,
                'name' => get_bloginfo('name'),
                'url' => get_site_url(),
                'admin_email' => get_option('admin_email'),
                'plugin_active' => $isActive,
                'registered' => $site->registered,
                'last_updated' => $site->last_updated,
            ];

            if ($isActive) {
                global $wpdb;

                $data['forms_count'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms"
                );
                $data['submissions_count'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions"
                );
                $data['submissions_today'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions
                     WHERE DATE(created_at) = CURDATE()"
                );
                $data['db_version'] = get_option('formflow_db_version', '0.0.0');
            }

            $sitesData[] = $data;

            restore_current_blog();
        }

        return $sitesData;
    }

    /**
     * Check if plugin is active on site
     *
     * @param int $blogId Blog ID
     * @return bool
     */
    public function isPluginActiveOnSite(int $blogId): bool
    {
        $plugins = get_blog_option($blogId, 'active_plugins', []);
        $networkPlugins = get_site_option('active_sitewide_plugins', []);

        $pluginFile = 'formflow-pro/formflow-pro.php';

        return in_array($pluginFile, $plugins) || isset($networkPlugins[$pluginFile]);
    }

    /**
     * Activate plugin for site
     *
     * @param int $blogId Blog ID
     * @return bool
     */
    public function activateForSite(int $blogId): bool
    {
        switch_to_blog($blogId);

        // Run activation routine
        do_action('formflow_activate');

        restore_current_blog();

        return true;
    }

    /**
     * Deactivate plugin for site
     *
     * @param int $blogId Blog ID
     * @return bool
     */
    public function deactivateForSite(int $blogId): bool
    {
        switch_to_blog($blogId);

        // Run deactivation routine
        do_action('formflow_deactivate');

        restore_current_blog();

        return true;
    }

    /**
     * Handle new site creation
     *
     * @param \WP_Site $site New site object
     * @param array $args Additional arguments
     * @return void
     */
    public function onNewSite(\WP_Site $site, array $args): void
    {
        $settings = $this->getNetworkSettings();

        // Auto-activate if network activated
        if (!empty($settings['network_activated'])) {
            $this->activateForSite($site->blog_id);
        }

        // Sync settings if enabled
        if (!empty($settings['sync_settings'])) {
            $this->syncSettingsToSite($site->blog_id);
        }
    }

    /**
     * Handle site activation
     *
     * @param int $blogId Blog ID
     * @return void
     */
    public function onSiteActivate(int $blogId): void
    {
        $settings = $this->getNetworkSettings();

        if (!empty($settings['network_activated'])) {
            $this->activateForSite($blogId);
        }
    }

    /**
     * Handle site deactivation
     *
     * @param int $blogId Blog ID
     * @return void
     */
    public function onSiteDeactivate(int $blogId): void
    {
        // Nothing special needed
    }

    /**
     * Handle site deletion
     *
     * @param int $blogId Blog ID
     * @param bool $drop Whether to drop tables
     * @return void
     */
    public function onSiteDelete(int $blogId, bool $drop): void
    {
        if ($drop) {
            switch_to_blog($blogId);

            // Clean up FormFlow tables
            global $wpdb;

            $tables = [
                'formflow_forms',
                'formflow_submissions',
                'formflow_templates',
                'formflow_queue',
                'formflow_logs',
                'formflow_settings',
                'formflow_autentique_documents',
                'formflow_integration_sync',
            ];

            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
            }

            restore_current_blog();
        }
    }

    /**
     * Network activate plugin
     *
     * @return void
     */
    public function networkActivate(): void
    {
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            $this->activateForSite($site->blog_id);
        }

        // Mark as network activated
        $settings = $this->getNetworkSettings();
        $settings['network_activated'] = true;
        $this->saveNetworkSettings($settings);
    }

    /**
     * Network deactivate plugin
     *
     * @return void
     */
    public function networkDeactivate(): void
    {
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            $this->deactivateForSite($site->blog_id);
        }

        // Clear network transients
        delete_site_transient('formflow_network_stats');
    }

    /**
     * Sync settings to a site
     *
     * @param int $blogId Blog ID
     * @return bool
     */
    public function syncSettingsToSite(int $blogId): bool
    {
        $mainSiteId = get_main_site_id();

        if ($blogId === $mainSiteId) {
            return false;
        }

        switch_to_blog($mainSiteId);
        $mainSettings = get_option('formflow_settings', []);
        restore_current_blog();

        switch_to_blog($blogId);
        update_option('formflow_settings', $mainSettings);
        restore_current_blog();

        return true;
    }

    /**
     * Sync network data
     *
     * @return void
     */
    public function syncNetworkData(): void
    {
        $settings = $this->getNetworkSettings();

        if (!empty($settings['sync_settings'])) {
            $sites = get_sites(['number' => 0]);

            foreach ($sites as $site) {
                $this->syncSettingsToSite($site->blog_id);
            }
        }

        // Clear stats cache
        delete_site_transient('formflow_network_stats');
    }

    /**
     * Get license info
     *
     * @return array
     */
    public function getLicenseInfo(): array
    {
        $settings = $this->getNetworkSettings();

        return [
            'key' => $settings['license_key'],
            'type' => $settings['license_type'],
            'max_sites' => $settings['max_sites'],
            'active_sites' => $this->getActiveSitesCount(),
            'status' => !empty($settings['license_key']) ? 'active' : 'inactive',
        ];
    }

    /**
     * Get active sites count
     *
     * @return int
     */
    private function getActiveSitesCount(): int
    {
        $count = 0;
        $sites = get_sites(['number' => 0]);

        foreach ($sites as $site) {
            if ($this->isPluginActiveOnSite($site->blog_id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Display network admin notices
     *
     * @return void
     */
    public function displayNetworkNotices(): void
    {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'formflow') === false) {
            return;
        }

        $settings = $this->getNetworkSettings();

        // License warning
        if (empty($settings['license_key'])) {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('FormFlow Pro network license not configured. Some features may be limited.', 'formflow-pro')
            );
        }

        // Sites limit warning
        if ($settings['max_sites'] > 0) {
            $activeSites = $this->getActiveSitesCount();
            if ($activeSites >= $settings['max_sites']) {
                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    sprintf(
                        /* translators: %d: max sites, %d: active sites */
                        esc_html__('License limit reached: %1$d of %2$d sites active.', 'formflow-pro'),
                        $activeSites,
                        $settings['max_sites']
                    )
                );
            }
        }
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/network/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetNetworkStats'],
            'permission_callback' => function () {
                return current_user_can('manage_network_options');
            },
        ]);

        register_rest_route('formflow/v1', '/network/sites', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSites'],
            'permission_callback' => function () {
                return current_user_can('manage_network_options');
            },
        ]);

        register_rest_route('formflow/v1', '/network/settings', [
            'methods' => ['GET', 'POST'],
            'callback' => [$this, 'restNetworkSettings'],
            'permission_callback' => function () {
                return current_user_can('manage_network_options');
            },
        ]);
    }

    /**
     * REST: Get network stats
     *
     * @return \WP_REST_Response
     */
    public function restGetNetworkStats(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->getNetworkStats());
    }

    /**
     * REST: Get sites
     *
     * @return \WP_REST_Response
     */
    public function restGetSites(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->getAllSitesData());
    }

    /**
     * REST: Network settings
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response
     */
    public function restNetworkSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($request->get_method() === 'POST') {
            $settings = $request->get_json_params();
            $result = $this->saveNetworkSettings($settings);

            return new \WP_REST_Response([
                'success' => $result,
                'message' => $result
                    ? __('Settings saved.', 'formflow-pro')
                    : __('Failed to save settings.', 'formflow-pro'),
            ]);
        }

        return new \WP_REST_Response($this->getNetworkSettings());
    }
}
