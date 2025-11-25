<?php

declare(strict_types=1);

namespace FormFlowPro\Security;

/**
 * Security Manager
 *
 * Central security hub that coordinates all security components:
 * - Two-Factor Authentication
 * - GDPR Compliance
 * - Audit Logging
 * - Access Control (IP/Geo/Session)
 *
 * Provides unified API and admin interface for security management.
 *
 * @package FormFlowPro\Security
 * @since 2.4.0
 */
class SecurityManager
{
    private static ?SecurityManager $instance = null;

    private TwoFactorAuth $twoFactorAuth;
    private GDPRCompliance $gdprCompliance;
    private AuditLogger $auditLogger;
    private AccessControl $accessControl;

    private function __construct()
    {
        $this->initComponents();
        $this->initHooks();
    }

    public static function getInstance(): SecurityManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initComponents(): void
    {
        $this->twoFactorAuth = TwoFactorAuth::getInstance();
        $this->gdprCompliance = GDPRCompliance::getInstance();
        $this->auditLogger = AuditLogger::getInstance();
        $this->accessControl = AccessControl::getInstance();
    }

    private function initHooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Security headers
        add_action('send_headers', [$this, 'addSecurityHeaders']);

        // Content Security Policy
        add_filter('wp_headers', [$this, 'addCSPHeader']);
    }

    /**
     * Install/upgrade database tables
     */
    public function install(): void
    {
        $this->twoFactorAuth->createTables();
        $this->gdprCompliance->createTables();
        $this->auditLogger->createTables();
        $this->accessControl->createTables();

        // Set default options
        $defaults = [
            'formflow_2fa_enforced_roles' => ['administrator'],
            'formflow_session_ip_strict' => false,
            'formflow_max_sessions' => 3,
            'formflow_geo_blocking_enabled' => false,
            'formflow_audit_retention_days' => 90,
            'formflow_gdpr_auto_delete_days' => 365,
            'formflow_security_headers_enabled' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        update_option('formflow_security_version', '2.4.0');
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Security', 'formflow-pro'),
            __('Security', 'formflow-pro'),
            'manage_options',
            'formflow-security',
            [$this, 'renderSecurityPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'formflow-security') === false) {
            return;
        }

        wp_enqueue_style(
            'formflow-security',
            FORMFLOW_URL . 'assets/css/admin-security.css',
            [],
            FORMFLOW_VERSION
        );

        wp_enqueue_script(
            'formflow-security',
            FORMFLOW_URL . 'assets/js/admin-security.js',
            ['jquery', 'wp-api-fetch'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('formflow-security', 'formflowSecurity', [
            'nonce' => wp_create_nonce('formflow_security_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formflow/v1/security'),
            'i18n' => [
                'confirm_disable_2fa' => __('Are you sure you want to disable 2FA?', 'formflow-pro'),
                'confirm_terminate_session' => __('Terminate this session?', 'formflow-pro'),
                'confirm_block_ip' => __('Block this IP address?', 'formflow-pro'),
                'saved' => __('Settings saved', 'formflow-pro'),
                'error' => __('An error occurred', 'formflow-pro'),
            ],
        ]);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        $namespace = 'formflow/v1';

        // Security overview
        register_rest_route($namespace, '/security/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOverview'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // 2FA endpoints
        register_rest_route($namespace, '/security/2fa/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGet2FAStatus'],
            'permission_callback' => [$this, 'checkUserPermission'],
        ]);

        register_rest_route($namespace, '/security/2fa/setup', [
            'methods' => 'POST',
            'callback' => [$this, 'restSetup2FA'],
            'permission_callback' => [$this, 'checkUserPermission'],
        ]);

        // Sessions endpoints
        register_rest_route($namespace, '/security/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSessions'],
            'permission_callback' => [$this, 'checkUserPermission'],
        ]);

        register_rest_route($namespace, '/security/sessions/(?P<token>[a-zA-Z0-9]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'restTerminateSession'],
            'permission_callback' => [$this, 'checkUserPermission'],
        ]);

        // IP rules endpoints
        register_rest_route($namespace, '/security/ip-rules', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetIPRules'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route($namespace, '/security/ip-rules', [
            'methods' => 'POST',
            'callback' => [$this, 'restAddIPRule'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Audit logs endpoints
        register_rest_route($namespace, '/security/audit-logs', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetAuditLogs'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // GDPR endpoints
        register_rest_route($namespace, '/security/gdpr/requests', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetGDPRRequests'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        // Settings endpoints
        register_rest_route($namespace, '/security/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route($namespace, '/security/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'restUpdateSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    /**
     * Permission callbacks
     */
    public function checkAdminPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkUserPermission(): bool
    {
        return current_user_can('read');
    }

    /**
     * REST: Get security overview
     */
    public function restGetOverview(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            '2fa' => $this->twoFactorAuth->getStatistics(),
            'gdpr' => $this->gdprCompliance->getStatistics(),
            'audit' => $this->auditLogger->getStatistics('day'),
            'access' => $this->accessControl->getStatistics(),
        ]);
    }

    /**
     * REST: Get 2FA status
     */
    public function restGet2FAStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();

        return new \WP_REST_Response([
            'enabled' => $this->twoFactorAuth->is2FAEnabled($userId),
            'settings' => $this->twoFactorAuth->get2FASettings($userId),
            'devices' => $this->twoFactorAuth->getUserDevices($userId),
            'backup_codes_remaining' => $this->twoFactorAuth->getRemainingBackupCodesCount($userId),
        ]);
    }

    /**
     * REST: Get sessions
     */
    public function restGetSessions(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $sessions = $this->accessControl->getUserSessions($userId);

        // Mark current session
        $currentToken = sanitize_text_field($_COOKIE['formflow_session_token'] ?? '');
        foreach ($sessions as &$session) {
            $session['is_current'] = ($session['session_token'] === $currentToken);
            unset($session['session_token']); // Don't expose token
        }

        return new \WP_REST_Response($sessions);
    }

    /**
     * REST: Terminate session
     */
    public function restTerminateSession(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = $request->get_param('token');

        if ($this->accessControl->terminateSession($token)) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Failed to terminate session'], 400);
    }

    /**
     * REST: Get IP rules
     */
    public function restGetIPRules(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'rules' => $this->accessControl->getIPRules(),
            'blocked' => $this->accessControl->getBlockedIPs(),
            'geo_rules' => $this->accessControl->getGeoRules(),
        ]);
    }

    /**
     * REST: Add IP rule
     */
    public function restAddIPRule(\WP_REST_Request $request): \WP_REST_Response
    {
        $result = $this->accessControl->addIPRule([
            'ip_address' => $request->get_param('ip_address'),
            'rule_type' => $request->get_param('rule_type'),
            'scope' => $request->get_param('scope'),
            'description' => $request->get_param('description'),
        ]);

        if ($result) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Failed to add rule'], 400);
    }

    /**
     * REST: Get audit logs
     */
    public function restGetAuditLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [
            'category' => $request->get_param('category'),
            'severity' => $request->get_param('severity'),
            'user_id' => $request->get_param('user_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
            'search' => $request->get_param('search'),
        ];

        $limit = (int) ($request->get_param('limit') ?? 100);
        $offset = (int) ($request->get_param('offset') ?? 0);

        return new \WP_REST_Response([
            'logs' => $this->auditLogger->query(array_filter($filters), $limit, $offset),
            'total' => $this->auditLogger->getCount(array_filter($filters)),
        ]);
    }

    /**
     * REST: Get GDPR requests
     */
    public function restGetGDPRRequests(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [
            'type' => $request->get_param('type'),
            'status' => $request->get_param('status'),
            'email' => $request->get_param('email'),
        ];

        return new \WP_REST_Response([
            'requests' => $this->gdprCompliance->getRequests(array_filter($filters)),
            'statistics' => $this->gdprCompliance->getStatistics(),
        ]);
    }

    /**
     * REST: Get settings
     */
    public function restGetSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            '2fa_enforced_roles' => get_option('formflow_2fa_enforced_roles', []),
            'session_ip_strict' => get_option('formflow_session_ip_strict', false),
            'max_sessions' => get_option('formflow_max_sessions', 3),
            'geo_blocking_enabled' => get_option('formflow_geo_blocking_enabled', false),
            'audit_retention_days' => get_option('formflow_audit_retention_days', 90),
            'gdpr_auto_delete_days' => get_option('formflow_gdpr_auto_delete_days', 365),
            'security_headers_enabled' => get_option('formflow_security_headers_enabled', true),
            'security_alert_emails' => get_option('formflow_security_alert_emails', ''),
        ]);
    }

    /**
     * REST: Update settings
     */
    public function restUpdateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = [
            'formflow_2fa_enforced_roles' => $request->get_param('2fa_enforced_roles'),
            'formflow_session_ip_strict' => $request->get_param('session_ip_strict'),
            'formflow_max_sessions' => $request->get_param('max_sessions'),
            'formflow_geo_blocking_enabled' => $request->get_param('geo_blocking_enabled'),
            'formflow_audit_retention_days' => $request->get_param('audit_retention_days'),
            'formflow_gdpr_auto_delete_days' => $request->get_param('gdpr_auto_delete_days'),
            'formflow_security_headers_enabled' => $request->get_param('security_headers_enabled'),
            'formflow_security_alert_emails' => $request->get_param('security_alert_emails'),
        ];

        $oldSettings = [];
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $oldSettings[$key] = get_option($key);
                update_option($key, $value);
            }
        }

        // Log settings change
        do_action('formflow_settings_updated', [
            'old' => $oldSettings,
            'new' => array_filter($settings, fn($v) => $v !== null),
            'section' => 'security',
        ]);

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Add security headers
     */
    public function addSecurityHeaders(): void
    {
        if (!get_option('formflow_security_headers_enabled', true)) {
            return;
        }

        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // HSTS (only on HTTPS)
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Add Content Security Policy header
     */
    public function addCSPHeader(array $headers): array
    {
        if (!get_option('formflow_security_headers_enabled', true)) {
            return $headers;
        }

        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://chart.googleapis.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https: blob:",
            "font-src 'self' data:",
            "connect-src 'self' https://ip-api.com",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
        ];

        $headers['Content-Security-Policy'] = implode('; ', $csp);

        return $headers;
    }

    /**
     * Render security admin page
     */
    public function renderSecurityPage(): void
    {
        $activeTab = sanitize_text_field($_GET['tab'] ?? 'overview');
        ?>
        <div class="wrap formflow-security-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-shield-alt"></span>
                <?php esc_html_e('Security Center', 'formflow-pro'); ?>
            </h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=formflow-security&tab=overview"
                   class="nav-tab <?php echo $activeTab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Overview', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=2fa"
                   class="nav-tab <?php echo $activeTab === '2fa' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Two-Factor Auth', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=access"
                   class="nav-tab <?php echo $activeTab === 'access' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Access Control', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=sessions"
                   class="nav-tab <?php echo $activeTab === 'sessions' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Sessions', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=audit"
                   class="nav-tab <?php echo $activeTab === 'audit' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Audit Logs', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=gdpr"
                   class="nav-tab <?php echo $activeTab === 'gdpr' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('GDPR', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-security&tab=settings"
                   class="nav-tab <?php echo $activeTab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'formflow-pro'); ?>
                </a>
            </nav>

            <div class="tab-content" id="formflow-security-content">
                <?php
                switch ($activeTab) {
                    case '2fa':
                        $this->render2FATab();
                        break;
                    case 'access':
                        $this->renderAccessTab();
                        break;
                    case 'sessions':
                        $this->renderSessionsTab();
                        break;
                    case 'audit':
                        $this->renderAuditTab();
                        break;
                    case 'gdpr':
                        $this->renderGDPRTab();
                        break;
                    case 'settings':
                        $this->renderSettingsTab();
                        break;
                    default:
                        $this->renderOverviewTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview tab
     */
    private function renderOverviewTab(): void
    {
        $stats2fa = $this->twoFactorAuth->getStatistics();
        $statsGdpr = $this->gdprCompliance->getStatistics();
        $statsAudit = $this->auditLogger->getStatistics('day');
        $statsAccess = $this->accessControl->getStatistics();
        ?>
        <div class="security-overview">
            <div class="security-cards">
                <div class="security-card">
                    <div class="card-icon">🔐</div>
                    <div class="card-content">
                        <h3><?php esc_html_e('Two-Factor Authentication', 'formflow-pro'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($stats2fa['users_with_2fa']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Users with 2FA enabled', 'formflow-pro'); ?></div>
                    </div>
                </div>

                <div class="security-card">
                    <div class="card-icon">🛡️</div>
                    <div class="card-content">
                        <h3><?php esc_html_e('Access Control', 'formflow-pro'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($statsAccess['blocked_ips']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Currently blocked IPs', 'formflow-pro'); ?></div>
                    </div>
                </div>

                <div class="security-card">
                    <div class="card-icon">📋</div>
                    <div class="card-content">
                        <h3><?php esc_html_e('Audit Logs', 'formflow-pro'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($statsAudit['total']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Events today', 'formflow-pro'); ?></div>
                    </div>
                </div>

                <div class="security-card">
                    <div class="card-icon">📜</div>
                    <div class="card-content">
                        <h3><?php esc_html_e('GDPR Requests', 'formflow-pro'); ?></h3>
                        <div class="stat-value"><?php echo esc_html($statsGdpr['pending_requests']); ?></div>
                        <div class="stat-label"><?php esc_html_e('Pending requests', 'formflow-pro'); ?></div>
                    </div>
                </div>
            </div>

            <div class="security-summary">
                <div class="summary-section">
                    <h3><?php esc_html_e('Security Status', 'formflow-pro'); ?></h3>
                    <ul class="status-list">
                        <li class="<?php echo $stats2fa['users_with_2fa'] > 0 ? 'status-good' : 'status-warning'; ?>">
                            <span class="status-icon"></span>
                            <?php esc_html_e('Two-Factor Authentication', 'formflow-pro'); ?>
                        </li>
                        <li class="<?php echo get_option('formflow_security_headers_enabled') ? 'status-good' : 'status-warning'; ?>">
                            <span class="status-icon"></span>
                            <?php esc_html_e('Security Headers', 'formflow-pro'); ?>
                        </li>
                        <li class="<?php echo $statsAccess['whitelist_rules'] > 0 ? 'status-good' : 'status-neutral'; ?>">
                            <span class="status-icon"></span>
                            <?php esc_html_e('IP Whitelist', 'formflow-pro'); ?>
                        </li>
                        <li class="status-good">
                            <span class="status-icon"></span>
                            <?php esc_html_e('Audit Logging Active', 'formflow-pro'); ?>
                        </li>
                    </ul>
                </div>

                <div class="summary-section">
                    <h3><?php esc_html_e('Recent Activity', 'formflow-pro'); ?></h3>
                    <?php
                    $recentLogs = $this->auditLogger->query([], 5, 0);
                    if ($recentLogs):
                    ?>
                    <table class="widefat striped">
                        <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td>
                                <span class="severity-badge severity-<?php echo esc_attr($log['severity']); ?>">
                                    <?php echo esc_html(ucfirst($log['severity'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log['event_type']); ?></td>
                            <td><?php echo esc_html($log['user_email'] ?? 'System'); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($log['created_at']))); ?> ago</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php esc_html_e('No recent activity', 'formflow-pro'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        .security-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .security-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            gap: 15px;
        }
        .card-icon {
            font-size: 40px;
        }
        .card-content h3 {
            margin: 0 0 10px;
            font-size: 14px;
            color: #666;
        }
        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 12px;
            color: #888;
        }
        .security-summary {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
            margin-top: 20px;
        }
        .summary-section {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }
        .summary-section h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .status-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .status-list li {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status-icon {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .status-good .status-icon { background: #46b450; }
        .status-warning .status-icon { background: #ffb900; }
        .status-neutral .status-icon { background: #ccc; }
        .severity-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .severity-info { background: #e7f3ff; color: #0073aa; }
        .severity-warning { background: #fff8e5; color: #996800; }
        .severity-error { background: #fbeaea; color: #dc3232; }
        .severity-critical { background: #dc3232; color: #fff; }
        </style>
        <?php
    }

    /**
     * Render 2FA tab
     */
    private function render2FATab(): void
    {
        $userId = get_current_user_id();
        $is2FAEnabled = $this->twoFactorAuth->is2FAEnabled($userId);
        $devices = $this->twoFactorAuth->getUserDevices($userId);
        $backupCodesCount = $this->twoFactorAuth->getRemainingBackupCodesCount($userId);
        ?>
        <div class="two-factor-setup">
            <div class="setup-section">
                <h2><?php esc_html_e('Your Two-Factor Authentication', 'formflow-pro'); ?></h2>

                <?php if ($is2FAEnabled): ?>
                <div class="notice notice-success">
                    <p><strong><?php esc_html_e('Two-factor authentication is enabled', 'formflow-pro'); ?></strong></p>
                </div>

                <div class="backup-codes-status">
                    <h3><?php esc_html_e('Backup Codes', 'formflow-pro'); ?></h3>
                    <p>
                        <?php printf(
                            esc_html__('You have %d backup codes remaining.', 'formflow-pro'),
                            $backupCodesCount
                        ); ?>
                    </p>
                    <?php if ($backupCodesCount < 3): ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Consider regenerating your backup codes.', 'formflow-pro'); ?></p>
                    </div>
                    <?php endif; ?>
                    <button type="button" class="button" id="regenerate-backup-codes">
                        <?php esc_html_e('Regenerate Backup Codes', 'formflow-pro'); ?>
                    </button>
                </div>

                <div class="trusted-devices">
                    <h3><?php esc_html_e('Trusted Devices', 'formflow-pro'); ?></h3>
                    <?php if ($devices): ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Device', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Location', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Last Used', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($devices as $device): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($device['device_name']); ?></strong><br>
                                    <small><?php echo esc_html($device['ip_address']); ?></small>
                                </td>
                                <td><?php echo esc_html($device['location']); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($device['last_used']))); ?> ago</td>
                                <td>
                                    <button type="button" class="button button-small revoke-device"
                                            data-device-id="<?php echo esc_attr($device['id']); ?>">
                                        <?php esc_html_e('Revoke', 'formflow-pro'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p><?php esc_html_e('No trusted devices.', 'formflow-pro'); ?></p>
                    <?php endif; ?>
                </div>

                <div class="disable-2fa">
                    <h3><?php esc_html_e('Disable Two-Factor Authentication', 'formflow-pro'); ?></h3>
                    <p class="description">
                        <?php esc_html_e('Warning: This will reduce the security of your account.', 'formflow-pro'); ?>
                    </p>
                    <button type="button" class="button button-secondary" id="disable-2fa">
                        <?php esc_html_e('Disable 2FA', 'formflow-pro'); ?>
                    </button>
                </div>

                <?php else: ?>
                <div class="enable-2fa-wizard" id="2fa-setup-wizard">
                    <p><?php esc_html_e('Protect your account with two-factor authentication.', 'formflow-pro'); ?></p>

                    <div class="wizard-step" id="step-1">
                        <button type="button" class="button button-primary button-hero" id="start-2fa-setup">
                            <?php esc_html_e('Enable Two-Factor Authentication', 'formflow-pro'); ?>
                        </button>
                    </div>

                    <div class="wizard-step hidden" id="step-2">
                        <h3><?php esc_html_e('Scan QR Code', 'formflow-pro'); ?></h3>
                        <p><?php esc_html_e('Scan this QR code with your authenticator app:', 'formflow-pro'); ?></p>
                        <div id="qr-code-container"></div>
                        <p class="manual-entry">
                            <?php esc_html_e('Or enter this code manually:', 'formflow-pro'); ?>
                            <code id="manual-secret"></code>
                        </p>
                    </div>

                    <div class="wizard-step hidden" id="step-3">
                        <h3><?php esc_html_e('Verify Setup', 'formflow-pro'); ?></h3>
                        <p><?php esc_html_e('Enter the 6-digit code from your authenticator app:', 'formflow-pro'); ?></p>
                        <input type="text" id="verify-code" maxlength="6" pattern="[0-9]{6}"
                               placeholder="000000" class="regular-text">
                        <button type="button" class="button button-primary" id="verify-2fa-setup">
                            <?php esc_html_e('Verify & Enable', 'formflow-pro'); ?>
                        </button>
                    </div>

                    <div class="wizard-step hidden" id="step-4">
                        <h3><?php esc_html_e('Save Your Backup Codes', 'formflow-pro'); ?></h3>
                        <p><?php esc_html_e('Store these codes in a safe place:', 'formflow-pro'); ?></p>
                        <div id="backup-codes-list"></div>
                        <button type="button" class="button" id="download-backup-codes">
                            <?php esc_html_e('Download Codes', 'formflow-pro'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render access control tab
     */
    private function renderAccessTab(): void
    {
        $ipRules = $this->accessControl->getIPRules();
        $blockedIPs = $this->accessControl->getBlockedIPs();
        ?>
        <div class="access-control">
            <div class="access-section">
                <h2><?php esc_html_e('IP Rules', 'formflow-pro'); ?></h2>

                <div class="add-rule-form">
                    <h3><?php esc_html_e('Add IP Rule', 'formflow-pro'); ?></h3>
                    <form id="add-ip-rule-form">
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('IP Address/CIDR', 'formflow-pro'); ?></th>
                                <td>
                                    <input type="text" name="ip_address" class="regular-text"
                                           placeholder="192.168.1.1 or 192.168.1.0/24" required>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Rule Type', 'formflow-pro'); ?></th>
                                <td>
                                    <select name="rule_type">
                                        <option value="whitelist"><?php esc_html_e('Whitelist (Allow)', 'formflow-pro'); ?></option>
                                        <option value="blacklist"><?php esc_html_e('Blacklist (Block)', 'formflow-pro'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Scope', 'formflow-pro'); ?></th>
                                <td>
                                    <select name="scope">
                                        <option value="all"><?php esc_html_e('All', 'formflow-pro'); ?></option>
                                        <option value="admin"><?php esc_html_e('Admin Only', 'formflow-pro'); ?></option>
                                        <option value="api"><?php esc_html_e('API Only', 'formflow-pro'); ?></option>
                                        <option value="forms"><?php esc_html_e('Forms Only', 'formflow-pro'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                                <td>
                                    <input type="text" name="description" class="regular-text"
                                           placeholder="<?php esc_attr_e('Optional description', 'formflow-pro'); ?>">
                                </td>
                            </tr>
                        </table>
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Add Rule', 'formflow-pro'); ?>
                        </button>
                    </form>
                </div>

                <h3><?php esc_html_e('Current Rules', 'formflow-pro'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('IP/Range', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Type', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Scope', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($ipRules): ?>
                        <?php foreach ($ipRules as $rule): ?>
                        <tr>
                            <td>
                                <code><?php echo esc_html($rule['cidr'] ?? $rule['ip_address']); ?></code>
                            </td>
                            <td>
                                <span class="rule-type rule-<?php echo esc_attr($rule['rule_type']); ?>">
                                    <?php echo esc_html(ucfirst($rule['rule_type'])); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(ucfirst($rule['scope'])); ?></td>
                            <td><?php echo esc_html($rule['description']); ?></td>
                            <td>
                                <button type="button" class="button button-small remove-ip-rule"
                                        data-rule-id="<?php echo esc_attr($rule['id']); ?>">
                                    <?php esc_html_e('Remove', 'formflow-pro'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No IP rules configured.', 'formflow-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="access-section">
                <h2><?php esc_html_e('Temporarily Blocked IPs', 'formflow-pro'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('IP Address', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Reason', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Attempts', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Blocked At', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Expires', 'formflow-pro'); ?></th>
                            <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($blockedIPs): ?>
                        <?php foreach ($blockedIPs as $blocked): ?>
                        <tr>
                            <td><code><?php echo esc_html($blocked['ip_address']); ?></code></td>
                            <td><?php echo esc_html($blocked['reason']); ?></td>
                            <td><?php echo esc_html($blocked['attempts_count']); ?></td>
                            <td><?php echo esc_html($blocked['blocked_at']); ?></td>
                            <td><?php echo esc_html($blocked['expires_at']); ?></td>
                            <td>
                                <button type="button" class="button button-small unblock-ip"
                                        data-ip="<?php echo esc_attr($blocked['ip_address']); ?>">
                                    <?php esc_html_e('Unblock', 'formflow-pro'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php esc_html_e('No blocked IPs.', 'formflow-pro'); ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <style>
        .rule-type {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
        }
        .rule-whitelist { background: #d4edda; color: #155724; }
        .rule-blacklist { background: #f8d7da; color: #721c24; }
        .add-rule-form {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .access-section {
            margin-bottom: 30px;
        }
        </style>
        <?php
    }

    /**
     * Render sessions tab
     */
    private function renderSessionsTab(): void
    {
        $userId = get_current_user_id();
        $sessions = $this->accessControl->getUserSessions($userId);
        $currentToken = sanitize_text_field($_COOKIE['formflow_session_token'] ?? '');
        ?>
        <div class="sessions-management">
            <h2><?php esc_html_e('Active Sessions', 'formflow-pro'); ?></h2>
            <p><?php esc_html_e('These are the devices currently logged into your account.', 'formflow-pro'); ?></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Device', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Location', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('IP Address', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Last Activity', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php $isCurrent = ($session['session_token'] === $currentToken); ?>
                    <tr class="<?php echo $isCurrent ? 'current-session' : ''; ?>">
                        <td>
                            <strong><?php echo esc_html($session['browser']); ?></strong>
                            on <?php echo esc_html($session['os']); ?>
                            <?php if ($isCurrent): ?>
                                <span class="current-badge"><?php esc_html_e('Current', 'formflow-pro'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($session['location']); ?></td>
                        <td><code><?php echo esc_html($session['ip_address']); ?></code></td>
                        <td><?php echo esc_html(human_time_diff(strtotime($session['last_activity']))); ?> ago</td>
                        <td>
                            <?php if (!$isCurrent): ?>
                            <button type="button" class="button button-small terminate-session"
                                    data-token="<?php echo esc_attr($session['session_token']); ?>">
                                <?php esc_html_e('Terminate', 'formflow-pro'); ?>
                            </button>
                            <?php else: ?>
                            <em><?php esc_html_e('Active', 'formflow-pro'); ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="button" class="button" id="terminate-all-sessions">
                    <?php esc_html_e('Terminate All Other Sessions', 'formflow-pro'); ?>
                </button>
            </p>
        </div>

        <style>
        .current-session { background: #f0f7ff !important; }
        .current-badge {
            background: #0073aa;
            color: #fff;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }
        </style>
        <?php
    }

    /**
     * Render audit tab
     */
    private function renderAuditTab(): void
    {
        $logs = $this->auditLogger->query([], 50, 0);
        ?>
        <div class="audit-logs">
            <h2><?php esc_html_e('Audit Logs', 'formflow-pro'); ?></h2>

            <div class="audit-filters">
                <select id="filter-category">
                    <option value=""><?php esc_html_e('All Categories', 'formflow-pro'); ?></option>
                    <option value="authentication"><?php esc_html_e('Authentication', 'formflow-pro'); ?></option>
                    <option value="data_access"><?php esc_html_e('Data Access', 'formflow-pro'); ?></option>
                    <option value="administration"><?php esc_html_e('Administration', 'formflow-pro'); ?></option>
                    <option value="security"><?php esc_html_e('Security', 'formflow-pro'); ?></option>
                    <option value="compliance"><?php esc_html_e('Compliance', 'formflow-pro'); ?></option>
                </select>

                <select id="filter-severity">
                    <option value=""><?php esc_html_e('All Severities', 'formflow-pro'); ?></option>
                    <option value="debug"><?php esc_html_e('Debug', 'formflow-pro'); ?></option>
                    <option value="info"><?php esc_html_e('Info', 'formflow-pro'); ?></option>
                    <option value="warning"><?php esc_html_e('Warning', 'formflow-pro'); ?></option>
                    <option value="error"><?php esc_html_e('Error', 'formflow-pro'); ?></option>
                    <option value="critical"><?php esc_html_e('Critical', 'formflow-pro'); ?></option>
                </select>

                <input type="text" id="filter-search" placeholder="<?php esc_attr_e('Search...', 'formflow-pro'); ?>">

                <button type="button" class="button" id="export-logs">
                    <?php esc_html_e('Export', 'formflow-pro'); ?>
                </button>
            </div>

            <table class="widefat striped" id="audit-logs-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Severity', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Event', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('User', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('IP', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <span title="<?php echo esc_attr($log['created_at']); ?>">
                                <?php echo esc_html(human_time_diff(strtotime($log['created_at']))); ?> ago
                            </span>
                        </td>
                        <td>
                            <span class="severity-badge severity-<?php echo esc_attr($log['severity']); ?>">
                                <?php echo esc_html(ucfirst($log['severity'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['event_type']); ?></td>
                        <td><?php echo esc_html($log['user_email'] ?? 'System'); ?></td>
                        <td><code><?php echo esc_html($log['ip_address']); ?></code></td>
                        <td><?php echo esc_html($log['description']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <style>
        .audit-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .audit-filters select, .audit-filters input {
            min-width: 150px;
        }
        </style>
        <?php
    }

    /**
     * Render GDPR tab
     */
    private function renderGDPRTab(): void
    {
        $requests = $this->gdprCompliance->getRequests();
        $stats = $this->gdprCompliance->getStatistics();
        ?>
        <div class="gdpr-management">
            <div class="gdpr-stats">
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html($stats['total_requests']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Total Requests', 'formflow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html($stats['pending_requests']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Pending', 'formflow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html($stats['completed_requests']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Completed', 'formflow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo esc_html($stats['active_consents']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Active Consents', 'formflow-pro'); ?></span>
                </div>
            </div>

            <h2><?php esc_html_e('Privacy Requests', 'formflow-pro'); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Request ID', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Type', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Email', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Created', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($requests): ?>
                    <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><code><?php echo esc_html($request['request_id']); ?></code></td>
                        <td>
                            <span class="request-type type-<?php echo esc_attr($request['type']); ?>">
                                <?php echo esc_html(ucfirst($request['type'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($request['email']); ?></td>
                        <td>
                            <span class="request-status status-<?php echo esc_attr($request['status']); ?>">
                                <?php echo esc_html(ucfirst($request['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($request['created_at']); ?></td>
                        <td>
                            <?php if ($request['status'] === 'processing'): ?>
                            <button type="button" class="button button-small process-request"
                                    data-request-id="<?php echo esc_attr($request['request_id']); ?>"
                                    data-action="approve">
                                <?php esc_html_e('Approve', 'formflow-pro'); ?>
                            </button>
                            <button type="button" class="button button-small process-request"
                                    data-request-id="<?php echo esc_attr($request['request_id']); ?>"
                                    data-action="reject">
                                <?php esc_html_e('Reject', 'formflow-pro'); ?>
                            </button>
                            <?php elseif ($request['status'] === 'completed' && $request['export_file']): ?>
                            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=formflow_gdpr_download&request=' . $request['request_id'])); ?>"
                               class="button button-small">
                                <?php esc_html_e('Download', 'formflow-pro'); ?>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No privacy requests.', 'formflow-pro'); ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
        .gdpr-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            display: block;
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            color: #666;
            font-size: 12px;
        }
        .request-type, .request-status {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
        }
        .type-export { background: #e7f3ff; color: #0073aa; }
        .type-erasure { background: #fbeaea; color: #dc3232; }
        .status-pending { background: #fff8e5; color: #996800; }
        .status-processing { background: #e7f3ff; color: #0073aa; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        </style>
        <?php
    }

    /**
     * Render settings tab
     */
    private function renderSettingsTab(): void
    {
        ?>
        <div class="security-settings">
            <h2><?php esc_html_e('Security Settings', 'formflow-pro'); ?></h2>

            <form id="security-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="2fa-enforced-roles">
                                <?php esc_html_e('Enforce 2FA for Roles', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <?php
                            $enforcedRoles = get_option('formflow_2fa_enforced_roles', []);
                            $roles = wp_roles()->get_names();
                            foreach ($roles as $role => $name):
                            ?>
                            <label>
                                <input type="checkbox" name="2fa_enforced_roles[]" value="<?php echo esc_attr($role); ?>"
                                    <?php checked(in_array($role, $enforcedRoles, true)); ?>>
                                <?php echo esc_html($name); ?>
                            </label><br>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="session-ip-strict">
                                <?php esc_html_e('Strict IP Checking', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="session_ip_strict" value="1"
                                    <?php checked(get_option('formflow_session_ip_strict', false)); ?>>
                                <?php esc_html_e('Terminate session when IP changes', 'formflow-pro'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Enable this for maximum security, but may cause issues with mobile users.', 'formflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max-sessions">
                                <?php esc_html_e('Max Concurrent Sessions', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" name="max_sessions" min="1" max="10"
                                   value="<?php echo esc_attr(get_option('formflow_max_sessions', 3)); ?>"
                                   class="small-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="geo-blocking">
                                <?php esc_html_e('Geo Blocking', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="geo_blocking_enabled" value="1"
                                    <?php checked(get_option('formflow_geo_blocking_enabled', false)); ?>>
                                <?php esc_html_e('Enable country-based access restrictions', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="audit-retention">
                                <?php esc_html_e('Audit Log Retention', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" name="audit_retention_days" min="30" max="365"
                                   value="<?php echo esc_attr(get_option('formflow_audit_retention_days', 90)); ?>"
                                   class="small-text">
                            <?php esc_html_e('days', 'formflow-pro'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="security-headers">
                                <?php esc_html_e('Security Headers', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="security_headers_enabled" value="1"
                                    <?php checked(get_option('formflow_security_headers_enabled', true)); ?>>
                                <?php esc_html_e('Enable security headers (X-Frame-Options, CSP, etc.)', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="alert-emails">
                                <?php esc_html_e('Security Alert Emails', 'formflow-pro'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="email" name="security_alert_emails" class="regular-text"
                                   value="<?php echo esc_attr(get_option('formflow_security_alert_emails', get_option('admin_email'))); ?>"
                                   placeholder="<?php esc_attr_e('admin@example.com', 'formflow-pro'); ?>">
                            <p class="description">
                                <?php esc_html_e('Email address for critical security alerts.', 'formflow-pro'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Save Settings', 'formflow-pro'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Get component instances
     */
    public function getTwoFactorAuth(): TwoFactorAuth
    {
        return $this->twoFactorAuth;
    }

    public function getGDPRCompliance(): GDPRCompliance
    {
        return $this->gdprCompliance;
    }

    public function getAuditLogger(): AuditLogger
    {
        return $this->auditLogger;
    }

    public function getAccessControl(): AccessControl
    {
        return $this->accessControl;
    }
}
