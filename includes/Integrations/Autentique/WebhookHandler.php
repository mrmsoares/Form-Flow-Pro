<?php

/**
 * Autentique Webhook Handler.
 *
 * REST API endpoint for receiving Autentique webhooks.
 * Handles signature status updates and document events.
 *
 * @package FormFlowPro
 * @subpackage Integrations\Autentique
 * @since 2.1.0
 */

namespace FormFlowPro\Integrations\Autentique;

/**
 * Webhook Handler class.
 *
 * Registers REST API endpoint and processes incoming webhooks from Autentique.
 *
 * @since 2.1.0
 */
class WebhookHandler
{
    /**
     * REST API namespace.
     *
     * @since 2.1.0
     * @var string
     */
    private const API_NAMESPACE = 'formflow/v1';

    /**
     * Webhook endpoint route.
     *
     * @since 2.1.0
     * @var string
     */
    private const WEBHOOK_ROUTE = '/autentique/webhook';

    /**
     * Autentique service instance.
     *
     * @since 2.1.0
     * @var AutentiqueService
     */
    private $service;

    /**
     * WordPress database instance.
     *
     * @since 2.1.0
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor.
     *
     * @since 2.1.0
     * @param AutentiqueService|null $service Service instance.
     */
    public function __construct($service = null)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->service = $service ?? new AutentiqueService();
    }

    /**
     * Initialize webhook handler.
     *
     * Registers REST API routes.
     *
     * @since 2.1.0
     * @return void
     */
    public function init()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @since 2.1.0
     * @return void
     */
    public function register_routes()
    {
        register_rest_route(
            self::API_NAMESPACE,
            self::WEBHOOK_ROUTE,
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => [$this, 'verify_webhook_permission'],
            ]
        );

        // Optional: GET endpoint for testing webhook configuration
        register_rest_route(
            self::API_NAMESPACE,
            self::WEBHOOK_ROUTE . '/test',
            [
                'methods' => 'GET',
                'callback' => [$this, 'test_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Handle incoming webhook.
     *
     * @since 2.1.0
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response object.
     */
    public function handle_webhook($request)
    {
        $start_time = microtime(true);

        try {
            // Get webhook data
            $body = $request->get_body();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON payload');
            }

            // Log incoming webhook
            $this->logWebhook('received', $data);

            // Validate webhook signature
            if (!$this->validateSignature($request, $body)) {
                $this->logWebhook('rejected', $data, 'Invalid signature');

                return new \WP_REST_Response(
                    ['error' => 'Invalid signature'],
                    401
                );
            }

            // Process webhook through service
            $result = $this->service->processSignatureWebhook($data);

            // Calculate processing time
            $processing_time = round((microtime(true) - $start_time) * 1000, 2);

            // Log processing result
            $this->logWebhook('processed', $data, null, [
                'processing_time_ms' => $processing_time,
                'result' => $result,
            ]);

            // Return success response
            return new \WP_REST_Response(
                [
                    'success' => true,
                    'message' => 'Webhook processed',
                    'processing_time_ms' => $processing_time,
                ],
                200
            );
        } catch (\Exception $e) {
            // Log error
            $this->logWebhook('error', $data ?? [], $e->getMessage());

            // Return error response
            return new \WP_REST_Response(
                [
                    'success' => false,
                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }

    /**
     * Test webhook endpoint.
     *
     * @since 2.1.0
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response object.
     */
    public function test_webhook($request)
    {
        return new \WP_REST_Response(
            [
                'success' => true,
                'message' => 'Webhook endpoint is active',
                'endpoint' => rest_url(self::API_NAMESPACE . self::WEBHOOK_ROUTE),
                'timestamp' => current_time('mysql'),
                'version' => FORMFLOW_VERSION,
            ],
            200
        );
    }

    /**
     * Verify webhook permission.
     *
     * No authentication required - signature validation is done in handle_webhook.
     *
     * @since 2.1.0
     * @param \WP_REST_Request $request Request object.
     * @return bool Always true.
     */
    public function verify_webhook_permission($request)
    {
        // Webhooks are public endpoints
        // Authentication is done via signature validation
        return true;
    }

    /**
     * Validate webhook signature.
     *
     * @since 2.1.0
     * @param \WP_REST_Request $request Request object.
     * @param string $body Raw request body.
     * @return bool True if valid.
     */
    private function validateSignature($request, $body)
    {
        // Get signature from header
        $signature = $request->get_header('X-Autentique-Signature');

        if (empty($signature)) {
            // Check if signature validation is required
            $require_signature = get_option('formflow_autentique_require_signature', true);

            if (!$require_signature) {
                // Allow webhooks without signature in development
                return true;
            }

            return false;
        }

        // Get webhook secret
        $secret = get_option('formflow_autentique_webhook_secret');

        if (empty($secret)) {
            // No secret configured - allow or deny based on settings
            return !get_option('formflow_autentique_require_signature', true);
        }

        // Validate HMAC signature
        $expected_signature = hash_hmac('sha256', $body, $secret);

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Log webhook activity.
     *
     * @since 2.1.0
     * @param string $status Webhook status (received, processed, rejected, error).
     * @param array $data Webhook data.
     * @param string|null $error Error message if applicable.
     * @param array $context Additional context.
     * @return void
     */
    private function logWebhook($status, array $data, $error = null, array $context = [])
    {
        $log_data = [
            'id' => $this->generateUUID(),
            'integration' => 'autentique',
            'webhook_type' => $data['event'] ?? 'unknown',
            'status' => $status,
            'payload' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ];

        // Add error if present
        if ($error !== null) {
            $log_data['error'] = $error;
        }

        // Add context
        if (!empty($context)) {
            $log_data['context'] = wp_json_encode($context);
        }

        // Store in webhooks table
        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_webhooks',
            $log_data
        );

        // Also log to main logs table for monitoring
        $level = ($status === 'error' || $status === 'rejected') ? 'error' : 'info';
        $message = sprintf('Autentique webhook %s: %s', $status, $data['event'] ?? 'unknown');

        $this->wpdb->insert(
            $this->wpdb->prefix . 'formflow_logs',
            [
                'id' => $this->generateUUID(),
                'submission_id' => $data['submission_id'] ?? null,
                'level' => $level,
                'message' => $message,
                'context' => wp_json_encode(array_merge($data, $context)),
                'created_at' => current_time('mysql'),
            ]
        );
    }

    /**
     * Get webhook URL.
     *
     * @since 2.1.0
     * @return string Webhook URL.
     */
    public static function get_webhook_url()
    {
        return rest_url(self::API_NAMESPACE . self::WEBHOOK_ROUTE);
    }

    /**
     * Get webhook statistics.
     *
     * @since 2.1.0
     * @param int $days Number of days to analyze (default: 7).
     * @return array Statistics.
     */
    public function get_webhook_stats($days = 7)
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $stats = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    status,
                    webhook_type,
                    COUNT(*) as count
                FROM {$this->wpdb->prefix}formflow_webhooks
                WHERE integration = 'autentique'
                AND created_at >= %s
                GROUP BY status, webhook_type
                ORDER BY count DESC",
                $since
            ),
            ARRAY_A
        );

        // Get total count
        $total = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}formflow_webhooks
                WHERE integration = 'autentique' AND created_at >= %s",
                $since
            )
        );

        // Get error count
        $errors = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}formflow_webhooks
                WHERE integration = 'autentique'
                AND status IN ('error', 'rejected')
                AND created_at >= %s",
                $since
            )
        );

        // Calculate success rate
        $success_rate = $total > 0 ? round((($total - $errors) / $total) * 100, 2) : 100;

        // Get recent webhooks
        $recent = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}formflow_webhooks
                WHERE integration = 'autentique'
                ORDER BY created_at DESC
                LIMIT 10"
            ),
            ARRAY_A
        );

        return [
            'period_days' => $days,
            'total_webhooks' => (int) $total,
            'errors' => (int) $errors,
            'success_rate' => $success_rate,
            'by_status' => $stats,
            'recent_webhooks' => $recent,
        ];
    }

    /**
     * Retry failed webhook.
     *
     * @since 2.1.0
     * @param string $webhook_id Webhook ID from logs.
     * @return array Result.
     */
    public function retry_webhook($webhook_id)
    {
        $webhook = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}formflow_webhooks WHERE id = %s",
                $webhook_id
            )
        );

        if (!$webhook) {
            return [
                'success' => false,
                'error' => 'Webhook not found',
            ];
        }

        if ($webhook->status !== 'error' && $webhook->status !== 'rejected') {
            return [
                'success' => false,
                'error' => 'Can only retry failed webhooks',
            ];
        }

        try {
            $payload = json_decode($webhook->payload, true);
            $result = $this->service->processSignatureWebhook($payload);

            // Update webhook status
            $this->wpdb->update(
                $this->wpdb->prefix . 'formflow_webhooks',
                [
                    'status' => 'processed',
                    'context' => wp_json_encode(['retried_at' => current_time('mysql')]),
                ],
                ['id' => $webhook_id]
            );

            return [
                'success' => true,
                'result' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clean old webhook logs.
     *
     * @since 2.1.0
     * @param int $days Keep logs newer than X days (default: 30).
     * @return int Number of deleted records.
     */
    public function clean_old_logs($days = 30)
    {
        $before = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $deleted = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->wpdb->prefix}formflow_webhooks
                WHERE integration = 'autentique' AND created_at < %s",
                $before
            )
        );

        return (int) $deleted;
    }

    /**
     * Generate UUID v4.
     *
     * @since 2.1.0
     * @return string UUID.
     */
    private function generateUUID()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
