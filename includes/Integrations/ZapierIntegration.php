<?php

declare(strict_types=1);

/**
 * Zapier Integration
 *
 * Handles integration with Zapier via webhooks.
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
 * Zapier Integration Class
 */
class ZapierIntegration extends AbstractIntegration
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'zapier';
        $this->name = __('Zapier', 'formflow-pro');
        $this->description = __('Connect FormFlow Pro to 5,000+ apps via Zapier webhooks.', 'formflow-pro');
        $this->icon = FORMFLOW_URL . 'assets/images/integrations/zapier.svg';
        $this->optionName = 'formflow_integration_zapier';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        $webhooks = $config['webhooks'] ?? [];
        return !empty($webhooks);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'default_webhook_url',
                'label' => __('Default Webhook URL', 'formflow-pro'),
                'type' => 'url',
                'description' => __('Default Zapier webhook URL for all forms.', 'formflow-pro'),
                'placeholder' => 'https://hooks.zapier.com/hooks/catch/...',
                'required' => false,
            ],
            [
                'name' => 'include_metadata',
                'label' => __('Include Metadata', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Include form and submission metadata in webhook payload.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'include_form_fields',
                'label' => __('Include Form Fields Info', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Include field names and types in the payload.', 'formflow-pro'),
                'default' => false,
            ],
            [
                'name' => 'flatten_data',
                'label' => __('Flatten Data', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Flatten nested data for easier mapping in Zapier.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'retry_failed',
                'label' => __('Retry Failed Webhooks', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Automatically retry failed webhook deliveries.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'max_retries',
                'label' => __('Max Retries', 'formflow-pro'),
                'type' => 'number',
                'description' => __('Maximum number of retry attempts.', 'formflow-pro'),
                'default' => 3,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        $config = $this->getConfig();
        $webhookUrl = $config['default_webhook_url'] ?? '';

        if (empty($webhookUrl)) {
            return [
                'success' => false,
                'message' => __('Please configure a webhook URL first.', 'formflow-pro'),
            ];
        }

        // Send test payload
        $testPayload = [
            '_test' => true,
            '_source' => 'formflow_pro',
            '_timestamp' => current_time('mysql'),
            'form_name' => 'Test Form',
            'submission_id' => 0,
            'data' => [
                'email' => 'test@example.com',
                'name' => 'Test User',
                'message' => 'This is a test submission from FormFlow Pro.',
            ],
        ];

        $result = $this->sendWebhook($webhookUrl, $testPayload);

        if ($result['success']) {
            $this->logEvent('connection_test_success', [
                'webhook_url' => $this->maskUrl($webhookUrl),
            ]);

            return [
                'success' => true,
                'message' => __('Test webhook sent successfully! Check your Zapier dashboard.', 'formflow-pro'),
            ];
        }

        return [
            'success' => false,
            'message' => sprintf(
                __('Webhook test failed: %s', 'formflow-pro'),
                $result['error'] ?? __('Unknown error', 'formflow-pro')
            ),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sendSubmission(array $submission, array $mapping): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => __('Zapier integration is not enabled.', 'formflow-pro'),
            ];
        }

        $config = $this->getConfig();

        // Get webhook URL (from mapping or default)
        $webhookUrl = $mapping['_webhook_url'] ?? $config['default_webhook_url'] ?? '';
        unset($mapping['_webhook_url']);

        if (empty($webhookUrl)) {
            return [
                'success' => false,
                'message' => __('No webhook URL configured.', 'formflow-pro'),
            ];
        }

        // Build payload
        $payload = $this->buildPayload($submission, $mapping, $config);

        // Send webhook
        $result = $this->sendWebhook($webhookUrl, $payload);

        if ($result['success']) {
            $this->recordSync(
                $submission['id'] ?? 0,
                'success',
                $result['zap_id'] ?? null
            );

            return [
                'success' => true,
                'message' => __('Data sent to Zapier successfully.', 'formflow-pro'),
                'external_id' => $result['zap_id'] ?? null,
                'action' => 'sent',
            ];
        }

        // Handle retry if enabled
        if (!empty($config['retry_failed'])) {
            $this->scheduleRetry($submission, $webhookUrl, $payload, $config);
        }

        $this->recordSync(
            $submission['id'] ?? 0,
            'failed',
            null,
            $result['error'] ?? 'Unknown error'
        );

        return [
            'success' => false,
            'message' => $result['error'] ?? __('Failed to send data to Zapier.', 'formflow-pro'),
        ];
    }

    /**
     * Build webhook payload
     *
     * @param array $submission Submission data
     * @param array $mapping Field mapping
     * @param array $config Integration config
     * @return array
     */
    private function buildPayload(array $submission, array $mapping, array $config): array
    {
        $payload = [
            '_source' => 'formflow_pro',
            '_version' => '2.3.0',
            '_timestamp' => current_time('c'),
        ];

        // Add metadata if enabled
        if (!empty($config['include_metadata'])) {
            $payload['_metadata'] = [
                'submission_id' => $submission['id'] ?? null,
                'form_id' => $submission['form_id'] ?? null,
                'form_name' => $submission['form_name'] ?? null,
                'submitted_at' => $submission['created_at'] ?? current_time('mysql'),
                'ip_address' => $submission['ip_address'] ?? null,
                'user_agent' => $submission['user_agent'] ?? null,
                'page_url' => $submission['page_url'] ?? null,
            ];
        }

        // Map and include form data
        $formData = $submission['form_data'] ?? $submission['data'] ?? [];

        if (!empty($mapping)) {
            // Use custom mapping
            $mappedData = [];
            foreach ($mapping as $targetField => $sourceField) {
                if (strpos($targetField, '_') === 0) {
                    continue; // Skip meta fields
                }
                $mappedData[$targetField] = $this->getNestedValue($formData, $sourceField);
            }
            $formData = $mappedData;
        }

        // Flatten data if enabled
        if (!empty($config['flatten_data'])) {
            $formData = $this->flattenArray($formData);
        }

        $payload['data'] = $formData;

        // Add form fields info if enabled
        if (!empty($config['include_form_fields'])) {
            $payload['_fields'] = $this->getFieldsInfo($formData);
        }

        return $payload;
    }

    /**
     * Send webhook request
     *
     * @param string $url Webhook URL
     * @param array $payload Payload data
     * @return array
     */
    private function sendWebhook(string $url, array $payload): array
    {
        $response = wp_remote_post($url, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'FormFlowPro/2.3.0',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            $this->logError('Webhook request failed', [
                'url' => $this->maskUrl($url),
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

        // Zapier returns various success codes
        if ($code >= 200 && $code < 300) {
            $this->logEvent('webhook_sent', [
                'url' => $this->maskUrl($url),
                'code' => $code,
            ]);

            return [
                'success' => true,
                'zap_id' => $data['id'] ?? $data['request_id'] ?? null,
            ];
        }

        $this->logError('Webhook returned error', [
            'url' => $this->maskUrl($url),
            'code' => $code,
            'response' => $data,
        ]);

        return [
            'success' => false,
            'error' => $data['message'] ?? "HTTP {$code}",
            'code' => $code,
        ];
    }

    /**
     * Schedule retry for failed webhook
     *
     * @param array $submission Submission data
     * @param string $webhookUrl Webhook URL
     * @param array $payload Payload data
     * @param array $config Integration config
     * @return void
     */
    private function scheduleRetry(array $submission, string $webhookUrl, array $payload, array $config): void
    {
        $maxRetries = (int) ($config['max_retries'] ?? 3);

        // Get current retry count from transient
        $retryKey = 'formflow_zapier_retry_' . ($submission['id'] ?? 0);
        $currentRetry = (int) get_transient($retryKey);

        if ($currentRetry >= $maxRetries) {
            $this->logError('Max retries reached', [
                'submission_id' => $submission['id'] ?? 0,
                'retries' => $currentRetry,
            ]);
            delete_transient($retryKey);
            return;
        }

        // Increment retry count
        set_transient($retryKey, $currentRetry + 1, 3600);

        // Schedule retry with exponential backoff
        $delay = pow(2, $currentRetry) * 60; // 1min, 2min, 4min, etc.

        wp_schedule_single_event(
            time() + $delay,
            'formflow_zapier_retry',
            [
                'webhook_url' => $webhookUrl,
                'payload' => $payload,
                'submission_id' => $submission['id'] ?? 0,
            ]
        );

        $this->logEvent('retry_scheduled', [
            'submission_id' => $submission['id'] ?? 0,
            'retry_number' => $currentRetry + 1,
            'delay' => $delay,
        ]);
    }

    /**
     * Flatten nested array
     *
     * @param array $array Input array
     * @param string $prefix Key prefix
     * @return array
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}_{$key}" : $key;

            if (is_array($value) && !$this->isIndexedArray($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Check if array is indexed (not associative)
     *
     * @param array $array Input array
     * @return bool
     */
    private function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Get fields info from data
     *
     * @param array $data Form data
     * @return array
     */
    private function getFieldsInfo(array $data): array
    {
        $fields = [];

        foreach ($data as $key => $value) {
            $fields[$key] = [
                'type' => gettype($value),
                'empty' => empty($value),
            ];

            if (is_string($value)) {
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $fields[$key]['type'] = 'email';
                } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
                    $fields[$key]['type'] = 'url';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                    $fields[$key]['type'] = 'date';
                }
            }
        }

        return $fields;
    }

    /**
     * Mask URL for logging (hide sensitive parts)
     *
     * @param string $url URL to mask
     * @return string
     */
    private function maskUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';

        // Mask the last segment of the path (usually the unique ID)
        $segments = explode('/', trim($path, '/'));
        if (count($segments) > 1) {
            $segments[count($segments) - 1] = '****';
        }

        return ($parts['scheme'] ?? 'https') . '://' .
               ($parts['host'] ?? '') .
               '/' . implode('/', $segments);
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableFields(): array
    {
        // Zapier is flexible - it accepts any fields
        return [
            ['name' => 'email', 'label' => __('Email', 'formflow-pro'), 'type' => 'email', 'required' => false],
            ['name' => 'name', 'label' => __('Name', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'phone', 'label' => __('Phone', 'formflow-pro'), 'type' => 'phone', 'required' => false],
            ['name' => 'message', 'label' => __('Message', 'formflow-pro'), 'type' => 'textarea', 'required' => false],
            ['name' => 'custom_1', 'label' => __('Custom Field 1', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'custom_2', 'label' => __('Custom Field 2', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'custom_3', 'label' => __('Custom Field 3', 'formflow-pro'), 'type' => 'text', 'required' => false],
        ];
    }

    /**
     * Add webhook URL for specific form
     *
     * @param int $formId Form ID
     * @param string $webhookUrl Webhook URL
     * @param string $name Webhook name
     * @return bool
     */
    public function addFormWebhook(int $formId, string $webhookUrl, string $name = ''): bool
    {
        $config = $this->getConfig();
        $webhooks = $config['webhooks'] ?? [];

        $webhooks[$formId] = [
            'url' => esc_url_raw($webhookUrl),
            'name' => sanitize_text_field($name) ?: "Form {$formId}",
            'enabled' => true,
            'created_at' => current_time('mysql'),
        ];

        $config['webhooks'] = $webhooks;
        return update_option($this->optionName, $config);
    }

    /**
     * Remove webhook for specific form
     *
     * @param int $formId Form ID
     * @return bool
     */
    public function removeFormWebhook(int $formId): bool
    {
        $config = $this->getConfig();
        $webhooks = $config['webhooks'] ?? [];

        unset($webhooks[$formId]);

        $config['webhooks'] = $webhooks;
        return update_option($this->optionName, $config);
    }

    /**
     * Get webhook URL for form
     *
     * @param int $formId Form ID
     * @return string|null
     */
    public function getFormWebhookUrl(int $formId): ?string
    {
        $config = $this->getConfig();
        $webhooks = $config['webhooks'] ?? [];

        if (isset($webhooks[$formId]) && !empty($webhooks[$formId]['enabled'])) {
            return $webhooks[$formId]['url'] ?? null;
        }

        return $config['default_webhook_url'] ?? null;
    }
}
