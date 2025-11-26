<?php

declare(strict_types=1);

/**
 * Services Loader
 *
 * Loads and initializes all FormFlow Pro services including Enterprise modules.
 *
 * @package FormFlowPro
 * @since 2.0.0
 * @since 2.4.0 Added Enterprise modules (SSO, Payments, PWA, Marketplace, Security, etc.)
 */

namespace FormFlowPro;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Services Class
 *
 * Central service locator and initializer for all FormFlow Pro modules.
 */
class Services
{
    /**
     * Module instances cache
     *
     * @var array<string, object>
     */
    private static array $instances = [];

    /**
     * Enterprise modules enabled flag
     *
     * @var bool
     */
    private static bool $enterprise_enabled = true;

    /**
     * Initialize all services
     *
     * @return void
     */
    public static function init(): void
    {
        self::load_services();
        self::initialize_services();

        // Load enterprise modules if enabled
        if (self::$enterprise_enabled) {
            self::load_enterprise_modules();
            self::initialize_enterprise_modules();
        }
    }

    /**
     * Load core service classes
     *
     * @return void
     */
    private static function load_services(): void
    {
        // Queue System
        require_once FORMFLOW_PATH . 'includes/queue/class-queue-manager.php';

        // PDF Generation
        require_once FORMFLOW_PATH . 'includes/pdf/class-pdf-generator.php';

        // Email Templates
        require_once FORMFLOW_PATH . 'includes/email/class-email-template.php';

        // Cache Layer
        require_once FORMFLOW_PATH . 'includes/cache/class-cache-manager.php';

        // Advanced Reporting Module
        require_once FORMFLOW_PATH . 'includes/Reporting/ReportGenerator.php';
        require_once FORMFLOW_PATH . 'includes/Reporting/D3Visualization.php';
        require_once FORMFLOW_PATH . 'includes/Reporting/ReportingManager.php';
    }

    /**
     * Load enterprise module classes
     *
     * @return void
     */
    private static function load_enterprise_modules(): void
    {
        // Automation Module
        require_once FORMFLOW_PATH . 'includes/Automation/ConditionEvaluator.php';
        require_once FORMFLOW_PATH . 'includes/Automation/ActionLibrary.php';
        require_once FORMFLOW_PATH . 'includes/Automation/TriggerManager.php';
        require_once FORMFLOW_PATH . 'includes/Automation/WorkflowEngine.php';
        require_once FORMFLOW_PATH . 'includes/Automation/AutomationManager.php';

        // UX Premium Module
        require_once FORMFLOW_PATH . 'includes/UX/UXManager.php';

        // SSO Enterprise Module
        require_once FORMFLOW_PATH . 'includes/SSO/SSOManager.php';

        // Payment Processing Module
        require_once FORMFLOW_PATH . 'includes/Payments/PaymentManager.php';
        require_once FORMFLOW_PATH . 'includes/Payments/StripeProvider.php';
        require_once FORMFLOW_PATH . 'includes/Payments/PayPalProvider.php';
        require_once FORMFLOW_PATH . 'includes/Payments/WooCommerceIntegration.php';

        // PWA Module
        require_once FORMFLOW_PATH . 'includes/PWA/PWAManager.php';
        require_once FORMFLOW_PATH . 'includes/PWA/ServiceWorkerManager.php';
        require_once FORMFLOW_PATH . 'includes/PWA/MobilePreview.php';

        // Marketplace & Extensions Module
        require_once FORMFLOW_PATH . 'includes/Marketplace/ExtensionManager.php';
        require_once FORMFLOW_PATH . 'includes/Marketplace/DeveloperSDK.php';

        // Security Module
        require_once FORMFLOW_PATH . 'includes/Security/SecurityManager.php';
        require_once FORMFLOW_PATH . 'includes/Security/TwoFactorAuth.php';
        require_once FORMFLOW_PATH . 'includes/Security/AuditLogger.php';
        require_once FORMFLOW_PATH . 'includes/Security/GDPRCompliance.php';
        require_once FORMFLOW_PATH . 'includes/Security/AccessControl.php';

        // AI Module
        require_once FORMFLOW_PATH . 'includes/AI/AIProviderInterface.php';
        require_once FORMFLOW_PATH . 'includes/AI/OpenAIProvider.php';
        require_once FORMFLOW_PATH . 'includes/AI/LocalAIProvider.php';
        require_once FORMFLOW_PATH . 'includes/AI/AIService.php';

        // Integrations Module
        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationInterface.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/AbstractIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationManager.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/SalesforceIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/HubSpotIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/GoogleSheetsIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/ZapierIntegration.php';

        // Notifications Module
        require_once FORMFLOW_PATH . 'includes/Notifications/NotificationManager.php';
        require_once FORMFLOW_PATH . 'includes/Notifications/EmailBuilder.php';
        require_once FORMFLOW_PATH . 'includes/Notifications/SMSProvider.php';
        require_once FORMFLOW_PATH . 'includes/Notifications/PushNotifications.php';
        require_once FORMFLOW_PATH . 'includes/Notifications/ChatIntegrations.php';

        // Multi-Site Module
        require_once FORMFLOW_PATH . 'includes/MultiSite/MultiSiteManager.php';
        require_once FORMFLOW_PATH . 'includes/MultiSite/DataPartitioner.php';

        // Form Builder Module
        require_once FORMFLOW_PATH . 'includes/FormBuilder/FormBuilderManager.php';
        require_once FORMFLOW_PATH . 'includes/FormBuilder/FieldTypes.php';
        require_once FORMFLOW_PATH . 'includes/FormBuilder/DragDropBuilder.php';
        require_once FORMFLOW_PATH . 'includes/FormBuilder/FormVersioning.php';
        require_once FORMFLOW_PATH . 'includes/FormBuilder/ABTesting.php';
    }

    /**
     * Initialize core services
     *
     * @return void
     */
    private static function initialize_services(): void
    {
        // Initialize Queue Manager
        Queue\Queue_Manager::get_instance();

        // Initialize Cache Manager
        Cache\Cache_Manager::get_instance();

        // Initialize Advanced Reporting Module
        Reporting\ReportGenerator::getInstance();
        Reporting\D3Visualization::getInstance();
        Reporting\ReportingManager::getInstance();

        // Hook into submission processing
        add_action('formflow_form_submitted', [__CLASS__, 'handle_submission'], 10, 3);
    }

    /**
     * Initialize enterprise modules
     *
     * @return void
     */
    private static function initialize_enterprise_modules(): void
    {
        // Initialize Automation Module
        self::$instances['automation'] = Automation\AutomationManager::getInstance();

        // Initialize UX Premium Module
        self::$instances['ux'] = UX\UXManager::getInstance();

        // Initialize SSO Enterprise Module (if settings exist)
        if (get_option('formflow_sso_enabled', false)) {
            self::$instances['sso'] = SSO\SSOManager::getInstance();
        }

        // Initialize Payment Processing Module (if configured)
        if (get_option('formflow_payments_enabled', false)) {
            self::$instances['payments'] = Payments\PaymentManager::getInstance();
        }

        // Initialize PWA Module (if enabled)
        if (get_option('formflow_pwa_enabled', false)) {
            self::$instances['pwa'] = PWA\PWAManager::getInstance();
        }

        // Initialize Marketplace Module
        self::$instances['marketplace'] = Marketplace\ExtensionManager::getInstance();

        // Initialize Security Module
        self::$instances['security'] = Security\SecurityManager::getInstance();

        // Initialize AI Module (if API key configured)
        if (get_option('formflow_ai_enabled', false)) {
            self::$instances['ai'] = AI\AIService::getInstance();
        }

        // Initialize Integrations Module
        self::$instances['integrations'] = Integrations\IntegrationManager::getInstance();

        // Initialize Notifications Module
        self::$instances['notifications'] = Notifications\NotificationManager::getInstance();

        // Initialize Multi-Site Module (if multisite)
        if (is_multisite()) {
            self::$instances['multisite'] = MultiSite\MultiSiteManager::getInstance();
        }

        // Initialize Form Builder Module
        self::$instances['formbuilder'] = FormBuilder\FormBuilderManager::getInstance();

        // Register enterprise admin menu items
        add_action('admin_menu', [__CLASS__, 'register_enterprise_menus'], 20);

        // Register REST API routes for enterprise modules
        add_action('rest_api_init', [__CLASS__, 'register_enterprise_rest_routes']);

        // Fire action for extensions to hook into
        do_action('formflow_enterprise_modules_loaded', self::$instances);
    }

    /**
     * Register enterprise admin menu items
     *
     * @return void
     */
    public static function register_enterprise_menus(): void
    {
        // Automation submenu
        add_submenu_page(
            'formflow-pro',
            __('Automação', 'formflow-pro'),
            __('Automação', 'formflow-pro'),
            'manage_options',
            'formflow-automation',
            [__CLASS__, 'render_automation_page']
        );

        // SSO submenu (if enabled)
        if (get_option('formflow_sso_enabled', false) || current_user_can('manage_options')) {
            add_submenu_page(
                'formflow-pro',
                __('SSO Enterprise', 'formflow-pro'),
                __('SSO', 'formflow-pro'),
                'manage_options',
                'formflow-sso',
                [__CLASS__, 'render_sso_page']
            );
        }

        // Payments submenu (if enabled)
        if (get_option('formflow_payments_enabled', false) || current_user_can('manage_options')) {
            add_submenu_page(
                'formflow-pro',
                __('Pagamentos', 'formflow-pro'),
                __('Pagamentos', 'formflow-pro'),
                'manage_options',
                'formflow-payments',
                [__CLASS__, 'render_payments_page']
            );
        }

        // Marketplace submenu
        add_submenu_page(
            'formflow-pro',
            __('Marketplace', 'formflow-pro'),
            __('Marketplace', 'formflow-pro'),
            'manage_options',
            'formflow-marketplace',
            [__CLASS__, 'render_marketplace_page']
        );

        // Security submenu
        add_submenu_page(
            'formflow-pro',
            __('Segurança', 'formflow-pro'),
            __('Segurança', 'formflow-pro'),
            'manage_options',
            'formflow-security',
            [__CLASS__, 'render_security_page']
        );

        // Integrations submenu
        add_submenu_page(
            'formflow-pro',
            __('Integrações', 'formflow-pro'),
            __('Integrações', 'formflow-pro'),
            'manage_options',
            'formflow-integrations',
            [__CLASS__, 'render_integrations_page']
        );
    }

    /**
     * Register REST API routes for enterprise modules
     *
     * @return void
     */
    public static function register_enterprise_rest_routes(): void
    {
        // Automation routes
        if (isset(self::$instances['automation'])) {
            self::$instances['automation']->registerRestRoutes();
        }

        // SSO routes
        if (isset(self::$instances['sso'])) {
            self::$instances['sso']->registerRestRoutes();
        }

        // Payments routes
        if (isset(self::$instances['payments'])) {
            self::$instances['payments']->registerRestRoutes();
        }

        // Marketplace routes
        if (isset(self::$instances['marketplace'])) {
            self::$instances['marketplace']->registerRestRoutes();
        }

        // Security routes
        if (isset(self::$instances['security'])) {
            self::$instances['security']->registerRestRoutes();
        }
    }

    /**
     * Render automation admin page
     *
     * @return void
     */
    public static function render_automation_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/automation.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/automation.php';
        } else {
            self::render_coming_soon_page(__('Automação', 'formflow-pro'));
        }
    }

    /**
     * Render SSO admin page
     *
     * @return void
     */
    public static function render_sso_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/sso.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/sso.php';
        } else {
            self::render_coming_soon_page(__('SSO Enterprise', 'formflow-pro'));
        }
    }

    /**
     * Render payments admin page
     *
     * @return void
     */
    public static function render_payments_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/payments.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/payments.php';
        } else {
            self::render_coming_soon_page(__('Pagamentos', 'formflow-pro'));
        }
    }

    /**
     * Render marketplace admin page
     *
     * @return void
     */
    public static function render_marketplace_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/marketplace.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/marketplace.php';
        } else {
            self::render_coming_soon_page(__('Marketplace', 'formflow-pro'));
        }
    }

    /**
     * Render security admin page
     *
     * @return void
     */
    public static function render_security_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/security.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/security.php';
        } else {
            self::render_coming_soon_page(__('Segurança', 'formflow-pro'));
        }
    }

    /**
     * Render integrations admin page
     *
     * @return void
     */
    public static function render_integrations_page(): void
    {
        if (file_exists(FORMFLOW_PATH . 'includes/admin/views/integrations.php')) {
            include FORMFLOW_PATH . 'includes/admin/views/integrations.php';
        } else {
            self::render_coming_soon_page(__('Integrações', 'formflow-pro'));
        }
    }

    /**
     * Render coming soon placeholder page
     *
     * @param string $title Page title.
     * @return void
     */
    private static function render_coming_soon_page(string $title): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e('Módulo Enterprise', 'formflow-pro'); ?></strong><br>
                    <?php esc_html_e('Este módulo está ativo. A interface administrativa completa estará disponível em breve.', 'formflow-pro'); ?>
                </p>
            </div>
            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2><?php esc_html_e('Status do Módulo', 'formflow-pro'); ?></h2>
                <p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('Módulo carregado e inicializado', 'formflow-pro'); ?></p>
                <p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> <?php esc_html_e('API REST disponível', 'formflow-pro'); ?></p>
                <p><span class="dashicons dashicons-clock" style="color: orange;"></span> <?php esc_html_e('Interface administrativa em desenvolvimento', 'formflow-pro'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Get module instance
     *
     * @param string $module Module name.
     * @return object|null
     */
    public static function get(string $module): ?object
    {
        return self::$instances[$module] ?? null;
    }

    /**
     * Handle form submission
     *
     * @param int   $submission_id Submission ID.
     * @param int   $form_id Form ID.
     * @param array $form_data Form data.
     * @return void
     */
    public static function handle_submission(int $submission_id, int $form_id, array $form_data): void
    {
        $queue = Queue\Queue_Manager::get_instance();

        // Queue PDF generation
        $queue->add_job('generate_pdf', [
            'submission_id' => $submission_id,
        ], 5);

        // Queue email notification
        $queue->add_job('send_notification', [
            'submission_id' => $submission_id,
            'form_id' => $form_id,
            'form_data' => $form_data,
        ], 10);
    }

    /**
     * Get cache instance
     *
     * @return Cache\Cache_Manager
     */
    public static function cache(): Cache\Cache_Manager
    {
        return Cache\Cache_Manager::get_instance();
    }

    /**
     * Get queue instance
     *
     * @return Queue\Queue_Manager
     */
    public static function queue(): Queue\Queue_Manager
    {
        return Queue\Queue_Manager::get_instance();
    }

    /**
     * Get PDF generator instance
     *
     * @return PDF\PDF_Generator
     */
    public static function pdf(): PDF\PDF_Generator
    {
        return new PDF\PDF_Generator();
    }

    /**
     * Get email template instance
     *
     * @return Email\Email_Template
     */
    public static function email(): Email\Email_Template
    {
        return new Email\Email_Template();
    }
}

// Register queue processors
add_action('formflow_process_generate_pdf', function ($data, $job_id) {
    try {
        $pdf = Services::pdf();
        $pdf_url = $pdf->generate_submission_pdf($data['submission_id']);

        // Save PDF URL to submission metadata
        global $wpdb;
        $metadata = $wpdb->get_var($wpdb->prepare(
            "SELECT metadata FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $data['submission_id']
        ));

        $metadata = $metadata ? json_decode($metadata, true) : [];
        $metadata['pdf_url'] = $pdf_url;

        $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            ['metadata' => wp_json_encode($metadata)],
            ['id' => $data['submission_id']],
            ['%s'],
            ['%d']
        );
    } catch (\Exception $e) {
        error_log('FormFlow PDF Generation Error: ' . $e->getMessage());
    }
}, 10, 2);

add_action('formflow_process_send_notification', function ($data, $job_id) {
    try {
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $data['form_id']
        ));

        if (!$form) {
            return;
        }

        $email = Services::email();
        $admin_email = get_option('formflow_company_email', get_option('admin_email'));

        $form_data_html = '<table>';
        foreach ($data['form_data'] as $key => $value) {
            $form_data_html .= '<tr><td><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong></td>';
            $form_data_html .= '<td>' . esc_html($value) . '</td></tr>';
        }
        $form_data_html .= '</table>';

        $email->send('submission_notification', $admin_email, [
            'form_name' => $form->name,
            'form_data' => $form_data_html,
            'admin_url' => admin_url('admin.php?page=formflow-submissions&submission_id=' . $data['submission_id']),
            'site_name' => get_bloginfo('name'),
        ]);

        // Send confirmation to user if email field exists
        if (isset($data['form_data']['email'])) {
            $email->send('submission_confirmation', $data['form_data']['email'], [
                'site_name' => get_bloginfo('name'),
            ]);
        }
    } catch (\Exception $e) {
        error_log('FormFlow Email Notification Error: ' . $e->getMessage());
    }
}, 10, 2);

/**
 * Process check_signature_status queue job
 *
 * Checks the current status of an Autentique document and updates
 * the submission accordingly.
 *
 * @since 2.0.0
 */
add_action('formflow_process_check_signature_status', function ($data, $job_id) {
    try {
        $submission_id = $data['submission_id'] ?? null;
        if (!$submission_id) {
            error_log('FormFlow: check_signature_status - Missing submission_id');
            return;
        }

        global $wpdb;

        // Get submission to find document_id
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT id, signature_document_id, signature_status FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $submission_id
        ));

        if (!$submission) {
            error_log("FormFlow: Submission #{$submission_id} not found");
            return;
        }

        // If no document ID yet, try to get from autentique_documents table
        $document_id = $submission->signature_document_id;
        if (!$document_id) {
            $doc = $wpdb->get_row($wpdb->prepare(
                "SELECT document_id FROM {$wpdb->prefix}formflow_autentique_documents WHERE submission_id = %d ORDER BY created_at DESC LIMIT 1",
                $submission_id
            ));
            $document_id = $doc->document_id ?? null;
        }

        if (!$document_id) {
            error_log("FormFlow: No document_id found for submission #{$submission_id}");
            return;
        }

        // Initialize Autentique service
        require_once FORMFLOW_PATH . 'includes/autentique/class-autentique-service.php';
        $autentique = new \FormFlowPro\Autentique\Autentique_Service();

        // Get current document status from Autentique API
        $document_status = $autentique->get_document_status($document_id);

        if (empty($document_status)) {
            error_log("FormFlow: Could not get status for document {$document_id}");
            return;
        }

        // Check signature status
        $all_signed = true;
        $any_refused = false;
        $signed_at = null;

        if (!empty($document_status['signatures'])) {
            foreach ($document_status['signatures'] as $signature) {
                // Check if refused
                if (!empty($signature['refused'])) {
                    $any_refused = true;
                    break;
                }

                // Check if not signed yet
                if (empty($signature['signed'])) {
                    $all_signed = false;
                } else {
                    // Use the latest signature timestamp
                    $signature_time = $signature['signed']['created_at'] ?? null;
                    if ($signature_time && (!$signed_at || $signature_time > $signed_at)) {
                        $signed_at = $signature_time;
                    }
                }
            }
        }

        // Determine new status
        $new_status = 'pending';
        if ($any_refused) {
            $new_status = 'refused';
        } elseif ($all_signed) {
            $new_status = 'signed';
        }

        // Update submission if status changed
        if ($new_status !== $submission->signature_status) {
            $update_data = [
                'signature_status' => $new_status,
                'updated_at' => current_time('mysql'),
            ];

            if ($signed_at) {
                $update_data['signed_at'] = $signed_at;
            }

            $wpdb->update(
                $wpdb->prefix . 'formflow_submissions',
                $update_data,
                ['id' => $submission_id],
                ['%s', '%s', '%s'],
                ['%d']
            );

            // Also update the autentique_documents table
            $wpdb->update(
                $wpdb->prefix . 'formflow_autentique_documents',
                [
                    'status' => $new_status,
                    'signed_at' => $signed_at,
                    'updated_at' => current_time('mysql'),
                ],
                ['document_id' => $document_id],
                ['%s', '%s', '%s'],
                ['%s']
            );

            // Fire action for status change
            do_action('formflow_signature_status_changed', $submission_id, $new_status, $document_id);

            // Log the status change
            require_once FORMFLOW_PATH . 'includes/logs/class-log-manager.php';
            $log = \FormFlowPro\Logs\Log_Manager::get_instance();
            $log->info('Signature status updated', [
                'submission_id' => $submission_id,
                'document_id' => $document_id,
                'old_status' => $submission->signature_status,
                'new_status' => $new_status,
            ]);

            error_log("FormFlow: Signature status updated for submission #{$submission_id}: {$new_status}");
        }

        // If still pending, re-queue for later check (max 24 hours)
        if ($new_status === 'pending') {
            $check_count = ($data['check_count'] ?? 0) + 1;

            // Check up to 288 times (24 hours at 5-minute intervals)
            if ($check_count < 288) {
                $queue = \FormFlowPro\Queue\Queue_Manager::get_instance();
                $queue->add_job('check_signature_status', [
                    'submission_id' => $submission_id,
                    'check_count' => $check_count,
                ], 5);
            } else {
                error_log("FormFlow: Max signature checks reached for submission #{$submission_id}");
            }
        }
    } catch (\Exception $e) {
        error_log('FormFlow Signature Status Check Error: ' . $e->getMessage());

        require_once FORMFLOW_PATH . 'includes/logs/class-log-manager.php';
        $log = \FormFlowPro\Logs\Log_Manager::get_instance();
        $log->error('Signature status check failed', [
            'submission_id' => $data['submission_id'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}, 10, 2);
