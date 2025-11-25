<?php

declare(strict_types=1);

/**
 * Integration Manager
 *
 * Central manager for all third-party integrations.
 *
 * @package FormFlowPro\Integrations
 * @since 2.3.0
 */

namespace FormFlowPro\Integrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Manager Class
 */
class IntegrationManager
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Registered integrations
     *
     * @var array<string, IntegrationInterface>
     */
    private array $integrations = [];

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
        $this->registerDefaultIntegrations();
        $this->setupHooks();
    }

    /**
     * Register default integrations
     *
     * @return void
     */
    private function registerDefaultIntegrations(): void
    {
        // Load integration classes
        require_once FORMFLOW_PATH . 'includes/Integrations/IntegrationInterface.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/AbstractIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/SalesforceIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/HubSpotIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/ZapierIntegration.php';
        require_once FORMFLOW_PATH . 'includes/Integrations/GoogleSheetsIntegration.php';

        // Register integrations
        $this->register(new SalesforceIntegration());
        $this->register(new HubSpotIntegration());
        $this->register(new ZapierIntegration());
        $this->register(new GoogleSheetsIntegration());

        // Allow plugins to register custom integrations
        do_action('formflow_register_integrations', $this);
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        // Process integrations after form submission
        add_action('formflow_submission_processed', [$this, 'processSubmission'], 10, 2);

        // Zapier retry hook
        add_action('formflow_zapier_retry', [$this, 'handleZapierRetry'], 10, 3);

        // Admin notices for disconnected integrations
        add_action('admin_notices', [$this, 'displayConnectionWarnings']);

        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register an integration
     *
     * @param IntegrationInterface $integration Integration instance
     * @return self
     */
    public function register(IntegrationInterface $integration): self
    {
        $this->integrations[$integration->getId()] = $integration;
        return $this;
    }

    /**
     * Get integration by ID
     *
     * @param string $id Integration ID
     * @return IntegrationInterface|null
     */
    public function get(string $id): ?IntegrationInterface
    {
        return $this->integrations[$id] ?? null;
    }

    /**
     * Get all registered integrations
     *
     * @return array<string, IntegrationInterface>
     */
    public function getAll(): array
    {
        return $this->integrations;
    }

    /**
     * Get all enabled integrations
     *
     * @return array<string, IntegrationInterface>
     */
    public function getEnabled(): array
    {
        return array_filter($this->integrations, function (IntegrationInterface $integration) {
            return $integration->isEnabled() && $integration->isConfigured();
        });
    }

    /**
     * Get integrations list for display
     *
     * @return array
     */
    public function getIntegrationsList(): array
    {
        $list = [];

        foreach ($this->integrations as $integration) {
            $list[] = [
                'id' => $integration->getId(),
                'name' => $integration->getName(),
                'description' => $integration->getDescription(),
                'icon' => $integration->getIcon(),
                'configured' => $integration->isConfigured(),
                'enabled' => $integration->isEnabled(),
            ];
        }

        return $list;
    }

    /**
     * Process submission through enabled integrations
     *
     * @param array $submission Submission data
     * @param int $formId Form ID
     * @return array Results from each integration
     */
    public function processSubmission(array $submission, int $formId): array
    {
        $results = [];
        $formMappings = $this->getFormMappings($formId);

        foreach ($this->getEnabled() as $id => $integration) {
            // Skip if no mapping for this form and integration
            if (!isset($formMappings[$id])) {
                continue;
            }

            $mapping = $formMappings[$id];

            try {
                $result = $integration->sendSubmission($submission, $mapping);
                $results[$id] = $result;

                // Log result
                if ($result['success']) {
                    $this->logSync($submission['id'] ?? 0, $id, 'success', $result);
                } else {
                    $this->logSync($submission['id'] ?? 0, $id, 'failed', $result);
                }
            } catch (\Throwable $e) {
                $results[$id] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                ];

                $this->logSync($submission['id'] ?? 0, $id, 'error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Get field mappings for a form
     *
     * @param int $formId Form ID
     * @return array
     */
    public function getFormMappings(int $formId): array
    {
        $option = get_option('formflow_integration_mappings', []);
        return $option[$formId] ?? [];
    }

    /**
     * Save field mappings for a form
     *
     * @param int $formId Form ID
     * @param string $integrationId Integration ID
     * @param array $mapping Field mapping
     * @return bool
     */
    public function saveFormMapping(int $formId, string $integrationId, array $mapping): bool
    {
        $option = get_option('formflow_integration_mappings', []);

        if (!isset($option[$formId])) {
            $option[$formId] = [];
        }

        $option[$formId][$integrationId] = $mapping;

        return update_option('formflow_integration_mappings', $option);
    }

    /**
     * Remove field mapping for a form
     *
     * @param int $formId Form ID
     * @param string $integrationId Integration ID
     * @return bool
     */
    public function removeFormMapping(int $formId, string $integrationId): bool
    {
        $option = get_option('formflow_integration_mappings', []);

        if (isset($option[$formId][$integrationId])) {
            unset($option[$formId][$integrationId]);
        }

        return update_option('formflow_integration_mappings', $option);
    }

    /**
     * Log integration sync
     *
     * @param int $submissionId Submission ID
     * @param string $integrationId Integration ID
     * @param string $status Status
     * @param array $data Additional data
     * @return void
     */
    private function logSync(int $submissionId, string $integrationId, string $status, array $data): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_integration_sync',
            [
                'submission_id' => $submissionId,
                'integration_id' => $integrationId,
                'status' => $status,
                'external_id' => $data['external_id'] ?? null,
                'error_message' => $data['message'] ?? ($data['error'] ?? null),
                'synced_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Handle Zapier retry event
     *
     * @param string $webhookUrl Webhook URL
     * @param array $payload Payload data
     * @param int $submissionId Submission ID
     * @return void
     */
    public function handleZapierRetry(string $webhookUrl, array $payload, int $submissionId): void
    {
        $zapier = $this->get('zapier');
        if (!$zapier || !$zapier->isEnabled()) {
            return;
        }

        /** @var ZapierIntegration $zapier */
        $result = $zapier->sendSubmission(
            ['id' => $submissionId, 'form_data' => $payload['data'] ?? []],
            ['_webhook_url' => $webhookUrl]
        );

        // Log result
        $this->logSync($submissionId, 'zapier', $result['success'] ? 'success' : 'failed', $result);
    }

    /**
     * Display admin notices for disconnected integrations
     *
     * @return void
     */
    public function displayConnectionWarnings(): void
    {
        // Only on FormFlow pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'formflow') === false) {
            return;
        }

        foreach ($this->integrations as $integration) {
            if ($integration->isEnabled() && !$integration->isConfigured()) {
                printf(
                    '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                    sprintf(
                        /* translators: %s: integration name */
                        esc_html__('%s integration is enabled but not properly configured.', 'formflow-pro'),
                        esc_html($integration->getName())
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
        register_rest_route('formflow/v1', '/integrations', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetIntegrations'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('formflow/v1', '/integrations/(?P<id>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetIntegration'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('formflow/v1', '/integrations/(?P<id>[a-z_]+)/test', [
            'methods' => 'POST',
            'callback' => [$this, 'restTestConnection'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('formflow/v1', '/integrations/(?P<id>[a-z_]+)/fields', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetFields'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    /**
     * REST: Get all integrations
     *
     * @return \WP_REST_Response
     */
    public function restGetIntegrations(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->getIntegrationsList());
    }

    /**
     * REST: Get single integration
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function restGetIntegration(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $integration = $this->get($id);

        if (!$integration) {
            return new \WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        return new \WP_REST_Response([
            'id' => $integration->getId(),
            'name' => $integration->getName(),
            'description' => $integration->getDescription(),
            'icon' => $integration->getIcon(),
            'configured' => $integration->isConfigured(),
            'enabled' => $integration->isEnabled(),
            'config_fields' => $integration->getConfigFields(),
        ]);
    }

    /**
     * REST: Test integration connection
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function restTestConnection(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $integration = $this->get($id);

        if (!$integration) {
            return new \WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        $result = $integration->testConnection();

        return new \WP_REST_Response($result);
    }

    /**
     * REST: Get integration fields
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function restGetFields(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = $request->get_param('id');
        $integration = $this->get($id);

        if (!$integration) {
            return new \WP_REST_Response(['error' => 'Integration not found'], 404);
        }

        return new \WP_REST_Response($integration->getAvailableFields());
    }

    /**
     * Get sync history for a submission
     *
     * @param int $submissionId Submission ID
     * @return array
     */
    public function getSyncHistory(int $submissionId): array
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_integration_sync
             WHERE submission_id = %d
             ORDER BY synced_at DESC",
            $submissionId
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * Get sync statistics
     *
     * @param string|null $integrationId Optional integration ID filter
     * @param string $period Period (today, week, month, all)
     * @return array
     */
    public function getSyncStats(?string $integrationId = null, string $period = 'all'): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_integration_sync';

        $where = '1=1';
        $params = [];

        if ($integrationId) {
            $where .= ' AND integration_id = %s';
            $params[] = $integrationId;
        }

        switch ($period) {
            case 'today':
                $where .= ' AND DATE(synced_at) = CURDATE()';
                break;
            case 'week':
                $where .= ' AND synced_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case 'month':
                $where .= ' AND synced_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
        }

        $query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped
            FROM {$table}
            WHERE {$where}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }

        $result = $wpdb->get_row($query, ARRAY_A);

        return [
            'total' => (int) ($result['total'] ?? 0),
            'success' => (int) ($result['success'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'success_rate' => $result['total'] > 0
                ? round(($result['success'] / $result['total']) * 100, 1)
                : 0,
        ];
    }
}
