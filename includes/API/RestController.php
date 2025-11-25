<?php

declare(strict_types=1);

/**
 * REST API Controller
 *
 * Full REST API for FormFlow Pro.
 *
 * @package FormFlowPro\API
 * @since 2.2.1
 */

namespace FormFlowPro\API;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST Controller Class
 */
class RestController
{
    /**
     * API namespace
     */
    private const NAMESPACE = 'formflow/v1';

    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

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
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function registerRoutes(): void
    {
        // Forms endpoints
        $this->registerFormsRoutes();

        // Submissions endpoints
        $this->registerSubmissionsRoutes();

        // Templates endpoints
        $this->registerTemplatesRoutes();

        // Analytics endpoints
        $this->registerAnalyticsRoutes();

        // Settings endpoints
        $this->registerSettingsRoutes();

        // Webhooks endpoints
        $this->registerWebhooksRoutes();

        // Authentication endpoints
        $this->registerAuthRoutes();
    }

    /**
     * Register forms routes
     *
     * @return void
     */
    private function registerFormsRoutes(): void
    {
        // GET /forms - List all forms
        register_rest_route(self::NAMESPACE, '/forms', [
            'methods' => 'GET',
            'callback' => [$this, 'getForms'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => $this->getPaginationArgs(),
        ]);

        // POST /forms - Create form
        register_rest_route(self::NAMESPACE, '/forms', [
            'methods' => 'POST',
            'callback' => [$this, 'createForm'],
            'permission_callback' => [$this, 'checkWritePermission'],
            'args' => [
                'name' => ['required' => true, 'type' => 'string'],
                'elementor_form_id' => ['required' => true, 'type' => 'string'],
                'settings' => ['type' => 'object'],
            ],
        ]);

        // GET /forms/{id} - Get single form
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getForm'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);

        // PUT /forms/{id} - Update form
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateForm'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        // DELETE /forms/{id} - Delete form
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteForm'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        // GET /forms/{id}/submissions - Get form submissions
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/submissions', [
            'methods' => 'GET',
            'callback' => [$this, 'getFormSubmissions'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => $this->getPaginationArgs(),
        ]);

        // GET /forms/{id}/stats - Get form statistics
        register_rest_route(self::NAMESPACE, '/forms/(?P<id>\d+)/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'getFormStats'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);
    }

    /**
     * Register submissions routes
     *
     * @return void
     */
    private function registerSubmissionsRoutes(): void
    {
        // GET /submissions - List all submissions
        register_rest_route(self::NAMESPACE, '/submissions', [
            'methods' => 'GET',
            'callback' => [$this, 'getSubmissions'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => array_merge($this->getPaginationArgs(), [
                'form_id' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'date_from' => ['type' => 'string'],
                'date_to' => ['type' => 'string'],
            ]),
        ]);

        // POST /submissions - Create submission (public with rate limiting)
        register_rest_route(self::NAMESPACE, '/submissions', [
            'methods' => 'POST',
            'callback' => [$this, 'createSubmission'],
            'permission_callback' => [$this, 'checkPublicSubmitPermission'],
            'args' => [
                'form_id' => ['required' => true, 'type' => 'integer'],
                'data' => ['required' => true, 'type' => 'object'],
            ],
        ]);

        // GET /submissions/{id} - Get single submission
        register_rest_route(self::NAMESPACE, '/submissions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSubmission'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);

        // PUT /submissions/{id} - Update submission
        register_rest_route(self::NAMESPACE, '/submissions/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateSubmission'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        // DELETE /submissions/{id} - Delete submission
        register_rest_route(self::NAMESPACE, '/submissions/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteSubmission'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        // POST /submissions/bulk - Bulk operations
        register_rest_route(self::NAMESPACE, '/submissions/bulk', [
            'methods' => 'POST',
            'callback' => [$this, 'bulkSubmissions'],
            'permission_callback' => [$this, 'checkWritePermission'],
            'args' => [
                'action' => ['required' => true, 'type' => 'string'],
                'ids' => ['required' => true, 'type' => 'array'],
            ],
        ]);

        // GET /submissions/export - Export submissions
        register_rest_route(self::NAMESPACE, '/submissions/export', [
            'methods' => 'GET',
            'callback' => [$this, 'exportSubmissions'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => [
                'format' => ['type' => 'string', 'default' => 'csv'],
                'form_id' => ['type' => 'integer'],
            ],
        ]);
    }

    /**
     * Register templates routes
     *
     * @return void
     */
    private function registerTemplatesRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'getTemplates'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/templates/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getTemplate'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/templates', [
            'methods' => 'POST',
            'callback' => [$this, 'createTemplate'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/templates/(?P<id>\d+)', [
            'methods' => ['PUT', 'PATCH'],
            'callback' => [$this, 'updateTemplate'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/templates/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteTemplate'],
            'permission_callback' => [$this, 'checkWritePermission'],
        ]);
    }

    /**
     * Register analytics routes
     *
     * @return void
     */
    private function registerAnalyticsRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/analytics/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'getAnalyticsOverview'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '30d'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/trends', [
            'methods' => 'GET',
            'callback' => [$this, 'getAnalyticsTrends'],
            'permission_callback' => [$this, 'checkReadPermission'],
            'args' => [
                'period' => ['type' => 'string', 'default' => '30d'],
                'granularity' => ['type' => 'string', 'default' => 'day'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/forms', [
            'methods' => 'GET',
            'callback' => [$this, 'getFormAnalytics'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/analytics/realtime', [
            'methods' => 'GET',
            'callback' => [$this, 'getRealtimeAnalytics'],
            'permission_callback' => [$this, 'checkReadPermission'],
        ]);
    }

    /**
     * Register settings routes
     *
     * @return void
     */
    private function registerSettingsRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/settings/(?P<group>[a-z_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettingsGroup'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    /**
     * Register webhooks routes
     *
     * @return void
     */
    private function registerWebhooksRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/webhooks', [
            'methods' => 'GET',
            'callback' => [$this, 'getWebhooks'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks', [
            'methods' => 'POST',
            'callback' => [$this, 'createWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/(?P<id>\d+)/test', [
            'methods' => 'POST',
            'callback' => [$this, 'testWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/(?P<id>\d+)/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'getWebhookLogs'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    /**
     * Register auth routes
     *
     * @return void
     */
    private function registerAuthRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/auth/keys', [
            'methods' => 'GET',
            'callback' => [$this, 'getApiKeys'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/keys', [
            'methods' => 'POST',
            'callback' => [$this, 'createApiKey'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/keys/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'deleteApiKey'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/verify', [
            'methods' => 'GET',
            'callback' => [$this, 'verifyAuth'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get pagination args
     *
     * @return array
     */
    private function getPaginationArgs(): array
    {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'orderby' => [
                'type' => 'string',
                'default' => 'created_at',
            ],
            'order' => [
                'type' => 'string',
                'default' => 'DESC',
                'enum' => ['ASC', 'DESC'],
            ],
        ];
    }

    /**
     * Check read permission
     *
     * @return bool
     */
    public function checkReadPermission(): bool
    {
        return $this->validateApiKey('read') || current_user_can('edit_posts');
    }

    /**
     * Check write permission
     *
     * @return bool
     */
    public function checkWritePermission(): bool
    {
        return $this->validateApiKey('write') || current_user_can('manage_options');
    }

    /**
     * Check admin permission
     *
     * @return bool
     */
    public function checkAdminPermission(): bool
    {
        return $this->validateApiKey('admin') || current_user_can('manage_options');
    }

    /**
     * Check public submit permission (with rate limiting)
     *
     * @return bool|\WP_Error
     */
    public function checkPublicSubmitPermission()
    {
        // Check rate limit
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rateKey = 'formflow_api_submit_' . md5($ip);
        $count = (int) get_transient($rateKey);

        $limit = (int) get_option('formflow_api_rate_limit', 60);

        if ($count >= $limit) {
            return new \WP_Error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'formflow-pro'),
                ['status' => 429]
            );
        }

        set_transient($rateKey, $count + 1, 60);

        return true;
    }

    /**
     * Validate API key
     *
     * @param string $requiredPermission Required permission level
     * @return bool
     */
    private function validateApiKey(string $requiredPermission): bool
    {
        $apiKey = $this->getApiKeyFromRequest();

        if (!$apiKey) {
            return false;
        }

        $keys = get_option('formflow_api_keys', []);

        foreach ($keys as $key) {
            if ($key['key'] === $apiKey && $key['active']) {
                // Check permission level
                $permissions = ['read' => 1, 'write' => 2, 'admin' => 3];
                $keyLevel = $permissions[$key['permission']] ?? 0;
                $requiredLevel = $permissions[$requiredPermission] ?? 0;

                if ($keyLevel >= $requiredLevel) {
                    // Update last used
                    $this->updateApiKeyLastUsed($apiKey);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get API key from request
     *
     * @return string|null
     */
    private function getApiKeyFromRequest(): ?string
    {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check X-API-Key header
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }

        // Check query parameter
        if (!empty($_GET['api_key'])) {
            return sanitize_text_field($_GET['api_key']);
        }

        return null;
    }

    /**
     * Update API key last used timestamp
     *
     * @param string $apiKey API key
     * @return void
     */
    private function updateApiKeyLastUsed(string $apiKey): void
    {
        $keys = get_option('formflow_api_keys', []);

        foreach ($keys as &$key) {
            if ($key['key'] === $apiKey) {
                $key['last_used'] = current_time('mysql');
                $key['request_count'] = ($key['request_count'] ?? 0) + 1;
                break;
            }
        }

        update_option('formflow_api_keys', $keys);
    }

    // === Forms Callbacks ===

    public function getForms(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $orderby = $request->get_param('orderby');
        $order = $request->get_param('order');

        $offset = ($page - 1) * $perPage;

        $forms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $perPage,
            $offset
        ), ARRAY_A);

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms");

        return new \WP_REST_Response([
            'data' => $forms,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    public function getForm(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Form not found'], 404);
        }

        $form['settings'] = json_decode($form['settings'] ?? '{}', true);

        return new \WP_REST_Response($form);
    }

    public function createForm(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $result = $wpdb->insert(
            $wpdb->prefix . 'formflow_forms',
            [
                'name' => sanitize_text_field($request->get_param('name')),
                'elementor_form_id' => sanitize_text_field($request->get_param('elementor_form_id')),
                'settings' => wp_json_encode($request->get_param('settings') ?? []),
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return new \WP_REST_Response(['error' => 'Failed to create form'], 500);
        }

        $this->triggerWebhook('form.created', ['form_id' => $wpdb->insert_id]);

        return new \WP_REST_Response([
            'id' => $wpdb->insert_id,
            'message' => 'Form created successfully',
        ], 201);
    }

    public function updateForm(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');
        $data = [];
        $format = [];

        if ($request->has_param('name')) {
            $data['name'] = sanitize_text_field($request->get_param('name'));
            $format[] = '%s';
        }

        if ($request->has_param('settings')) {
            $data['settings'] = wp_json_encode($request->get_param('settings'));
            $format[] = '%s';
        }

        if ($request->has_param('status')) {
            $data['status'] = sanitize_text_field($request->get_param('status'));
            $format[] = '%s';
        }

        if (empty($data)) {
            return new \WP_REST_Response(['error' => 'No data to update'], 400);
        }

        $data['updated_at'] = current_time('mysql');
        $format[] = '%s';

        $result = $wpdb->update(
            $wpdb->prefix . 'formflow_forms',
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Failed to update form'], 500);
        }

        $this->triggerWebhook('form.updated', ['form_id' => $id]);

        return new \WP_REST_Response(['message' => 'Form updated successfully']);
    }

    public function deleteForm(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        $result = $wpdb->delete(
            $wpdb->prefix . 'formflow_forms',
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return new \WP_REST_Response(['error' => 'Failed to delete form'], 500);
        }

        $this->triggerWebhook('form.deleted', ['form_id' => $id]);

        return new \WP_REST_Response(['message' => 'Form deleted successfully']);
    }

    public function getFormSubmissions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $formId = $request->get_param('id');
        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $offset = ($page - 1) * $perPage;

        $submissions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions
             WHERE form_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $formId,
            $perPage,
            $offset
        ), ARRAY_A);

        foreach ($submissions as &$submission) {
            $submission['form_data'] = json_decode($submission['form_data'] ?? '{}', true);
        }

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE form_id = %d",
            $formId
        ));

        return new \WP_REST_Response([
            'data' => $submissions,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function getFormStats(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $formId = $request->get_param('id');

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as month
             FROM {$wpdb->prefix}formflow_submissions
             WHERE form_id = %d",
            $formId
        ), ARRAY_A);

        return new \WP_REST_Response($stats);
    }

    // === Submissions Callbacks ===

    public function getSubmissions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $page = $request->get_param('page');
        $perPage = $request->get_param('per_page');
        $formId = $request->get_param('form_id');
        $status = $request->get_param('status');
        $dateFrom = $request->get_param('date_from');
        $dateTo = $request->get_param('date_to');

        $where = '1=1';
        $params = [];

        if ($formId) {
            $where .= ' AND form_id = %d';
            $params[] = $formId;
        }

        if ($status) {
            $where .= ' AND status = %s';
            $params[] = $status;
        }

        if ($dateFrom) {
            $where .= ' AND created_at >= %s';
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $where .= ' AND created_at <= %s';
            $params[] = $dateTo;
        }

        $offset = ($page - 1) * $perPage;

        $query = "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $perPage;
        $params[] = $offset;

        $submissions = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

        foreach ($submissions as &$submission) {
            $submission['form_data'] = json_decode($submission['form_data'] ?? '{}', true);
        }

        $countQuery = "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE {$where}";
        $total = $wpdb->get_var($wpdb->prepare($countQuery, ...array_slice($params, 0, -2)));

        return new \WP_REST_Response([
            'data' => $submissions,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function getSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$submission) {
            return new \WP_REST_Response(['error' => 'Submission not found'], 404);
        }

        $submission['form_data'] = json_decode($submission['form_data'] ?? '{}', true);

        return new \WP_REST_Response($submission);
    }

    public function createSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $formId = $request->get_param('form_id');
        $data = $request->get_param('data');

        // Verify form exists
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $formId
        ));

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Form not found'], 404);
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'formflow_submissions',
            [
                'form_id' => $formId,
                'form_data' => wp_json_encode($data),
                'status' => 'new',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return new \WP_REST_Response(['error' => 'Failed to create submission'], 500);
        }

        $submissionId = $wpdb->insert_id;

        $this->triggerWebhook('submission.created', [
            'submission_id' => $submissionId,
            'form_id' => $formId,
            'data' => $data,
        ]);

        return new \WP_REST_Response([
            'id' => $submissionId,
            'message' => 'Submission created successfully',
        ], 201);
    }

    public function updateSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');
        $data = [];
        $format = [];

        if ($request->has_param('status')) {
            $data['status'] = sanitize_text_field($request->get_param('status'));
            $format[] = '%s';
        }

        if ($request->has_param('data')) {
            $data['form_data'] = wp_json_encode($request->get_param('data'));
            $format[] = '%s';
        }

        if (empty($data)) {
            return new \WP_REST_Response(['error' => 'No data to update'], 400);
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'formflow_submissions',
            $data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Failed to update submission'], 500);
        }

        $this->triggerWebhook('submission.updated', ['submission_id' => $id]);

        return new \WP_REST_Response(['message' => 'Submission updated successfully']);
    }

    public function deleteSubmission(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        $result = $wpdb->delete(
            $wpdb->prefix . 'formflow_submissions',
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return new \WP_REST_Response(['error' => 'Failed to delete submission'], 500);
        }

        $this->triggerWebhook('submission.deleted', ['submission_id' => $id]);

        return new \WP_REST_Response(['message' => 'Submission deleted successfully']);
    }

    public function bulkSubmissions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $action = $request->get_param('action');
        $ids = $request->get_param('ids');

        $results = ['processed' => 0, 'failed' => 0];

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $result = $wpdb->delete(
                        $wpdb->prefix . 'formflow_submissions',
                        ['id' => $id],
                        ['%d']
                    );
                    if ($result) {
                        $results['processed']++;
                    } else {
                        $results['failed']++;
                    }
                }
                break;

            case 'mark_read':
            case 'archive':
                $status = $action === 'mark_read' ? 'read' : 'archived';
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}formflow_submissions SET status = %s WHERE id IN ({$placeholders})",
                    $status,
                    ...$ids
                ));
                $results['processed'] = count($ids);
                break;
        }

        return new \WP_REST_Response($results);
    }

    public function exportSubmissions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $format = $request->get_param('format');
        $formId = $request->get_param('form_id');

        $where = $formId ? $wpdb->prepare('WHERE form_id = %d', $formId) : '';
        $submissions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}formflow_submissions {$where} ORDER BY created_at DESC",
            ARRAY_A
        );

        foreach ($submissions as &$submission) {
            $submission['form_data'] = json_decode($submission['form_data'] ?? '{}', true);
        }

        if ($format === 'json') {
            return new \WP_REST_Response($submissions);
        }

        // CSV format
        $csv = '';
        if (!empty($submissions)) {
            $headers = array_keys($submissions[0]);
            $csv = implode(',', $headers) . "\n";

            foreach ($submissions as $row) {
                $values = array_map(function ($v) {
                    if (is_array($v)) {
                        $v = json_encode($v);
                    }
                    return '"' . str_replace('"', '""', $v) . '"';
                }, array_values($row));
                $csv .= implode(',', $values) . "\n";
            }
        }

        $response = new \WP_REST_Response($csv);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-Disposition', 'attachment; filename="submissions.csv"');

        return $response;
    }

    // === Other Callbacks (simplified) ===

    public function getTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}formflow_templates", ARRAY_A);
        return new \WP_REST_Response(['data' => $templates]);
    }

    public function getTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = $request->get_param('id');
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_templates WHERE id = %d",
            $id
        ), ARRAY_A);
        return $template ? new \WP_REST_Response($template) : new \WP_REST_Response(['error' => 'Not found'], 404);
    }

    public function createTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'formflow_templates', [
            'name' => sanitize_text_field($request->get_param('name')),
            'type' => sanitize_text_field($request->get_param('type')),
            'content' => wp_kses_post($request->get_param('content')),
            'created_at' => current_time('mysql'),
        ]);
        return new \WP_REST_Response(['id' => $wpdb->insert_id], 201);
    }

    public function updateTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'formflow_templates', [
            'name' => sanitize_text_field($request->get_param('name')),
            'content' => wp_kses_post($request->get_param('content')),
        ], ['id' => $request->get_param('id')]);
        return new \WP_REST_Response(['message' => 'Updated']);
    }

    public function deleteTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'formflow_templates', ['id' => $request->get_param('id')]);
        return new \WP_REST_Response(['message' => 'Deleted']);
    }

    public function getAnalyticsOverview(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $stats = $wpdb->get_row(
            "SELECT COUNT(*) as total, SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
             FROM {$wpdb->prefix}formflow_submissions",
            ARRAY_A
        );
        return new \WP_REST_Response($stats);
    }

    public function getAnalyticsTrends(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $trends = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$wpdb->prefix}formflow_submissions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date",
            ARRAY_A
        );
        return new \WP_REST_Response($trends);
    }

    public function getFormAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $stats = $wpdb->get_results(
            "SELECT f.id, f.name, COUNT(s.id) as submissions
             FROM {$wpdb->prefix}formflow_forms f
             LEFT JOIN {$wpdb->prefix}formflow_submissions s ON f.id = s.form_id
             GROUP BY f.id
             ORDER BY submissions DESC",
            ARRAY_A
        );
        return new \WP_REST_Response($stats);
    }

    public function getRealtimeAnalytics(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $stats = $wpdb->get_row(
            "SELECT COUNT(*) as last_hour
             FROM {$wpdb->prefix}formflow_submissions
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            ARRAY_A
        );
        return new \WP_REST_Response($stats);
    }

    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(get_option('formflow_settings', []));
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        update_option('formflow_settings', $request->get_json_params());
        return new \WP_REST_Response(['message' => 'Settings updated']);
    }

    public function getSettingsGroup(\WP_REST_Request $request): \WP_REST_Response
    {
        $group = $request->get_param('group');
        return new \WP_REST_Response(get_option("formflow_{$group}_settings", []));
    }

    // === Webhooks ===

    public function getWebhooks(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(get_option('formflow_webhooks', []));
    }

    public function createWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhooks = get_option('formflow_webhooks', []);
        $id = time();
        $webhooks[$id] = [
            'id' => $id,
            'name' => sanitize_text_field($request->get_param('name')),
            'url' => esc_url_raw($request->get_param('url')),
            'events' => (array) $request->get_param('events'),
            'active' => true,
            'secret' => wp_generate_password(32, false),
            'created_at' => current_time('mysql'),
        ];
        update_option('formflow_webhooks', $webhooks);
        return new \WP_REST_Response(['id' => $id], 201);
    }

    public function getWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhooks = get_option('formflow_webhooks', []);
        $id = $request->get_param('id');
        return isset($webhooks[$id])
            ? new \WP_REST_Response($webhooks[$id])
            : new \WP_REST_Response(['error' => 'Not found'], 404);
    }

    public function updateWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhooks = get_option('formflow_webhooks', []);
        $id = $request->get_param('id');
        if (!isset($webhooks[$id])) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }
        $webhooks[$id] = array_merge($webhooks[$id], [
            'name' => sanitize_text_field($request->get_param('name') ?? $webhooks[$id]['name']),
            'url' => esc_url_raw($request->get_param('url') ?? $webhooks[$id]['url']),
            'events' => $request->get_param('events') ?? $webhooks[$id]['events'],
            'active' => $request->get_param('active') ?? $webhooks[$id]['active'],
        ]);
        update_option('formflow_webhooks', $webhooks);
        return new \WP_REST_Response(['message' => 'Updated']);
    }

    public function deleteWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhooks = get_option('formflow_webhooks', []);
        $id = $request->get_param('id');
        unset($webhooks[$id]);
        update_option('formflow_webhooks', $webhooks);
        return new \WP_REST_Response(['message' => 'Deleted']);
    }

    public function testWebhook(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhooks = get_option('formflow_webhooks', []);
        $id = $request->get_param('id');
        if (!isset($webhooks[$id])) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }

        $response = wp_remote_post($webhooks[$id]['url'], [
            'body' => wp_json_encode(['test' => true, 'source' => 'formflow']),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;

        return new \WP_REST_Response([
            'success' => $success,
            'code' => is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response),
        ]);
    }

    public function getWebhookLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;
        $id = $request->get_param('id');
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_logs WHERE context = %s ORDER BY created_at DESC LIMIT 50",
            'webhook_' . $id
        ), ARRAY_A);
        return new \WP_REST_Response($logs);
    }

    // === Auth ===

    public function getApiKeys(\WP_REST_Request $request): \WP_REST_Response
    {
        $keys = get_option('formflow_api_keys', []);
        // Mask keys for security
        foreach ($keys as &$key) {
            $key['key'] = substr($key['key'], 0, 8) . '...' . substr($key['key'], -4);
        }
        return new \WP_REST_Response($keys);
    }

    public function createApiKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $keys = get_option('formflow_api_keys', []);
        $newKey = 'ffp_' . wp_generate_password(32, false);

        $keys[] = [
            'id' => time(),
            'name' => sanitize_text_field($request->get_param('name')),
            'key' => $newKey,
            'permission' => sanitize_text_field($request->get_param('permission') ?? 'read'),
            'active' => true,
            'created_at' => current_time('mysql'),
            'last_used' => null,
            'request_count' => 0,
        ];

        update_option('formflow_api_keys', $keys);

        return new \WP_REST_Response(['key' => $newKey], 201);
    }

    public function deleteApiKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $keys = get_option('formflow_api_keys', []);
        $id = $request->get_param('id');
        $keys = array_filter($keys, function ($k) use ($id) {
            return $k['id'] != $id;
        });
        update_option('formflow_api_keys', array_values($keys));
        return new \WP_REST_Response(['message' => 'Deleted']);
    }

    public function verifyAuth(\WP_REST_Request $request): \WP_REST_Response
    {
        $apiKey = $this->getApiKeyFromRequest();

        if (!$apiKey) {
            return new \WP_REST_Response([
                'authenticated' => false,
                'method' => 'none',
            ]);
        }

        $valid = $this->validateApiKey('read');

        return new \WP_REST_Response([
            'authenticated' => $valid,
            'method' => 'api_key',
        ]);
    }

    /**
     * Trigger webhook
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return void
     */
    private function triggerWebhook(string $event, array $data): void
    {
        $webhooks = get_option('formflow_webhooks', []);

        foreach ($webhooks as $webhook) {
            if (!$webhook['active']) {
                continue;
            }

            if (!in_array($event, $webhook['events']) && !in_array('*', $webhook['events'])) {
                continue;
            }

            $payload = [
                'event' => $event,
                'timestamp' => current_time('c'),
                'data' => $data,
            ];

            // Generate signature
            $signature = hash_hmac('sha256', wp_json_encode($payload), $webhook['secret']);

            wp_remote_post($webhook['url'], [
                'body' => wp_json_encode($payload),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-FormFlow-Signature' => $signature,
                    'X-FormFlow-Event' => $event,
                ],
                'timeout' => 30,
                'blocking' => false,
            ]);

            // Log webhook
            global $wpdb;
            $wpdb->insert($wpdb->prefix . 'formflow_logs', [
                'level' => 'info',
                'context' => 'webhook_' . $webhook['id'],
                'message' => "Webhook triggered: {$event}",
                'data' => wp_json_encode(['url' => $webhook['url'], 'event' => $event]),
                'created_at' => current_time('mysql'),
            ]);
        }
    }
}
