<?php

declare(strict_types=1);

/**
 * Abstract Integration Base Class
 *
 * Base class with common functionality for all integrations.
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
 * Abstract Integration Class
 */
abstract class AbstractIntegration implements IntegrationInterface
{
    /**
     * Integration ID
     *
     * @var string
     */
    protected string $id;

    /**
     * Integration name
     *
     * @var string
     */
    protected string $name;

    /**
     * Integration description
     *
     * @var string
     */
    protected string $description;

    /**
     * Integration icon
     *
     * @var string
     */
    protected string $icon;

    /**
     * Configuration option name
     *
     * @var string
     */
    protected string $optionName;

    /**
     * Cached configuration
     *
     * @var array|null
     */
    protected ?array $config = null;

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * {@inheritdoc}
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Get configuration
     *
     * @return array
     */
    protected function getConfig(): array
    {
        if ($this->config === null) {
            $this->config = get_option($this->optionName, []);
        }
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        $config = $this->getConfig();
        return !empty($config['enabled']);
    }

    /**
     * {@inheritdoc}
     */
    public function saveConfig(array $config): bool
    {
        // Sanitize configuration
        $sanitized = $this->sanitizeConfig($config);

        // Merge with existing config
        $existing = $this->getConfig();
        $merged = array_merge($existing, $sanitized);

        // Save to database
        $result = update_option($this->optionName, $merged);

        // Clear cache
        $this->config = null;

        // Log configuration change
        $this->logEvent('config_updated', [
            'fields_updated' => array_keys($sanitized),
        ]);

        return $result;
    }

    /**
     * Sanitize configuration data
     *
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    protected function sanitizeConfig(array $config): array
    {
        $sanitized = [];
        $fields = $this->getConfigFields();

        foreach ($fields as $field) {
            $key = $field['name'];
            if (!isset($config[$key])) {
                continue;
            }

            $value = $config[$key];

            switch ($field['type']) {
                case 'text':
                case 'password':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'checkbox':
                    $sanitized[$key] = (bool) $value;
                    break;
                case 'select':
                    $options = array_column($field['options'] ?? [], 'value');
                    $sanitized[$key] = in_array($value, $options) ? $value : ($field['default'] ?? '');
                    break;
                case 'number':
                    $sanitized[$key] = (int) $value;
                    break;
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }

        // Handle enabled flag separately
        if (isset($config['enabled'])) {
            $sanitized['enabled'] = (bool) $config['enabled'];
        }

        return $sanitized;
    }

    /**
     * {@inheritdoc}
     */
    public function getSyncStatus(int $submissionId): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_integration_sync';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE submission_id = %d
             AND integration_id = %s
             ORDER BY synced_at DESC
             LIMIT 1",
            $submissionId,
            $this->id
        ), ARRAY_A);

        if (!$result) {
            return [
                'synced' => false,
                'status' => 'pending',
                'external_id' => null,
                'synced_at' => null,
                'error' => null,
            ];
        }

        return [
            'synced' => $result['status'] === 'success',
            'status' => $result['status'],
            'external_id' => $result['external_id'],
            'synced_at' => $result['synced_at'],
            'error' => $result['error_message'],
        ];
    }

    /**
     * Record sync result
     *
     * @param int $submissionId Submission ID
     * @param string $status Sync status
     * @param string|null $externalId External record ID
     * @param string|null $error Error message
     * @return bool
     */
    protected function recordSync(
        int $submissionId,
        string $status,
        ?string $externalId = null,
        ?string $error = null
    ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_integration_sync';

        return (bool) $wpdb->insert($table, [
            'submission_id' => $submissionId,
            'integration_id' => $this->id,
            'status' => $status,
            'external_id' => $externalId,
            'error_message' => $error,
            'synced_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Log integration event
     *
     * @param string $event Event type
     * @param array $data Event data
     * @return void
     */
    protected function logEvent(string $event, array $data = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_logs';

        $wpdb->insert($table, [
            'level' => 'info',
            'context' => 'integration_' . $this->id,
            'message' => $event,
            'data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Log integration error
     *
     * @param string $message Error message
     * @param array $data Error data
     * @return void
     */
    protected function logError(string $message, array $data = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_logs';

        $wpdb->insert($table, [
            'level' => 'error',
            'context' => 'integration_' . $this->id,
            'message' => $message,
            'data' => wp_json_encode($data),
            'created_at' => current_time('mysql'),
        ], ['%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Make HTTP request
     *
     * @param string $url Request URL
     * @param array $args Request arguments
     * @return array{success: bool, data?: mixed, error?: string, code?: int}
     */
    protected function makeRequest(string $url, array $args = []): array
    {
        $defaults = [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ];

        $args = wp_parse_args($args, $defaults);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->logError('HTTP request failed', [
                'url' => $url,
                'error' => $response->get_error_message(),
            ]);

            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            $this->logError('HTTP request returned error', [
                'url' => $url,
                'code' => $code,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'error' => $data['message'] ?? $data['error'] ?? "HTTP {$code}",
                'code' => $code,
                'data' => $data,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'code' => $code,
        ];
    }

    /**
     * Map submission fields to integration fields
     *
     * @param array $submission Submission data
     * @param array $mapping Field mapping
     * @return array Mapped data
     */
    protected function mapFields(array $submission, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $integrationField => $formField) {
            if (empty($formField)) {
                continue;
            }

            // Handle nested fields (e.g., "form_data.email")
            $value = $this->getNestedValue($submission, $formField);

            if ($value !== null) {
                $mapped[$integrationField] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Get nested value from array
     *
     * @param array $array Source array
     * @param string $path Dot notation path
     * @return mixed|null
     */
    protected function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
