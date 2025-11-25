<?php

declare(strict_types=1);

/**
 * Salesforce Integration
 *
 * Handles integration with Salesforce CRM.
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
 * Salesforce Integration Class
 */
class SalesforceIntegration extends AbstractIntegration
{
    /**
     * Salesforce API version
     */
    private const API_VERSION = 'v58.0';

    /**
     * OAuth token endpoint
     */
    private const TOKEN_ENDPOINT = '/services/oauth2/token';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'salesforce';
        $this->name = __('Salesforce', 'formflow-pro');
        $this->description = __('Sync form submissions with Salesforce CRM as Leads or Contacts.', 'formflow-pro');
        $this->icon = FORMFLOW_URL . 'assets/images/integrations/salesforce.svg';
        $this->optionName = 'formflow_integration_salesforce';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        return !empty($config['client_id'])
            && !empty($config['client_secret'])
            && !empty($config['instance_url']);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'client_id',
                'label' => __('Consumer Key', 'formflow-pro'),
                'type' => 'text',
                'description' => __('From your Salesforce Connected App.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'client_secret',
                'label' => __('Consumer Secret', 'formflow-pro'),
                'type' => 'password',
                'description' => __('From your Salesforce Connected App.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'instance_url',
                'label' => __('Instance URL', 'formflow-pro'),
                'type' => 'url',
                'description' => __('Your Salesforce instance URL (e.g., https://yourcompany.salesforce.com).', 'formflow-pro'),
                'required' => true,
                'placeholder' => 'https://yourcompany.salesforce.com',
            ],
            [
                'name' => 'username',
                'label' => __('Username', 'formflow-pro'),
                'type' => 'email',
                'description' => __('Salesforce user email for API access.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'password',
                'label' => __('Password + Security Token', 'formflow-pro'),
                'type' => 'password',
                'description' => __('Your password concatenated with security token.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'sandbox',
                'label' => __('Sandbox Environment', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Check if using a sandbox environment.', 'formflow-pro'),
                'default' => false,
            ],
            [
                'name' => 'default_object',
                'label' => __('Default Object', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Default Salesforce object to create.', 'formflow-pro'),
                'options' => [
                    ['value' => 'Lead', 'label' => __('Lead', 'formflow-pro')],
                    ['value' => 'Contact', 'label' => __('Contact', 'formflow-pro')],
                    ['value' => 'Case', 'label' => __('Case', 'formflow-pro')],
                    ['value' => 'Account', 'label' => __('Account', 'formflow-pro')],
                    ['value' => 'Opportunity', 'label' => __('Opportunity', 'formflow-pro')],
                ],
                'default' => 'Lead',
            ],
            [
                'name' => 'lead_source',
                'label' => __('Lead Source', 'formflow-pro'),
                'type' => 'text',
                'description' => __('Value for LeadSource field.', 'formflow-pro'),
                'default' => 'Web Form',
            ],
            [
                'name' => 'duplicate_check',
                'label' => __('Check for Duplicates', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Check for existing records by email before creating.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'duplicate_action',
                'label' => __('Duplicate Action', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Action when duplicate is found.', 'formflow-pro'),
                'options' => [
                    ['value' => 'skip', 'label' => __('Skip (don\'t create)', 'formflow-pro')],
                    ['value' => 'update', 'label' => __('Update existing', 'formflow-pro')],
                    ['value' => 'create', 'label' => __('Create anyway', 'formflow-pro')],
                ],
                'default' => 'update',
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('Please configure all required fields first.', 'formflow-pro'),
            ];
        }

        // Try to get access token
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => __('Failed to authenticate with Salesforce. Please check your credentials.', 'formflow-pro'),
            ];
        }

        // Try to get user info
        $config = $this->getConfig();
        $userInfoUrl = rtrim($config['instance_url'], '/') . '/services/oauth2/userinfo';

        $response = $this->makeRequest($userInfoUrl, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Connection failed: %s', 'formflow-pro'),
                    $response['error'] ?? __('Unknown error', 'formflow-pro')
                ),
            ];
        }

        $this->logEvent('connection_test_success', [
            'user' => $response['data']['email'] ?? 'unknown',
            'org_id' => $response['data']['organization_id'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                __('Connected successfully as %s', 'formflow-pro'),
                $response['data']['email'] ?? $response['data']['name'] ?? 'Unknown'
            ),
            'data' => [
                'user' => $response['data']['email'] ?? null,
                'organization_id' => $response['data']['organization_id'] ?? null,
            ],
        ];
    }

    /**
     * Get OAuth access token
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        // Check for cached token
        $cachedToken = get_transient('formflow_sf_access_token');
        if ($cachedToken) {
            return $cachedToken;
        }

        $config = $this->getConfig();

        // Determine login URL based on sandbox setting
        $loginUrl = !empty($config['sandbox'])
            ? 'https://test.salesforce.com'
            : 'https://login.salesforce.com';

        $response = wp_remote_post($loginUrl . self::TOKEN_ENDPOINT, [
            'timeout' => 30,
            'body' => [
                'grant_type' => 'password',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'username' => $config['username'],
                'password' => $config['password'],
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logError('OAuth token request failed', [
                'error' => $response->get_error_message(),
            ]);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            $this->logError('OAuth token not received', [
                'response' => $body,
            ]);
            return null;
        }

        // Cache token (typically valid for 2 hours, we cache for 1.5 hours)
        set_transient('formflow_sf_access_token', $body['access_token'], 5400);

        // Store instance URL if returned
        if (!empty($body['instance_url']) && $body['instance_url'] !== $config['instance_url']) {
            $config['instance_url'] = $body['instance_url'];
            update_option($this->optionName, $config);
            $this->config = null;
        }

        return $body['access_token'];
    }

    /**
     * {@inheritdoc}
     */
    public function sendSubmission(array $submission, array $mapping): array
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('Salesforce integration is not configured or enabled.', 'formflow-pro'),
            ];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('Failed to authenticate with Salesforce.', 'formflow-pro'),
            ];
        }

        $config = $this->getConfig();
        $objectType = $mapping['_object_type'] ?? $config['default_object'] ?? 'Lead';
        unset($mapping['_object_type']);

        // Map fields
        $data = $this->mapFields($submission, $mapping);

        // Add default values
        if ($objectType === 'Lead' && !empty($config['lead_source'])) {
            $data['LeadSource'] = $config['lead_source'];
        }

        // Check for duplicates if enabled
        if (!empty($config['duplicate_check']) && !empty($data['Email'])) {
            $existing = $this->findByEmail($data['Email'], $objectType, $token);

            if ($existing) {
                $action = $config['duplicate_action'] ?? 'update';

                if ($action === 'skip') {
                    $this->recordSync(
                        $submission['id'] ?? 0,
                        'skipped',
                        $existing['Id'],
                        'Duplicate found'
                    );

                    return [
                        'success' => true,
                        'message' => __('Record already exists, skipped.', 'formflow-pro'),
                        'external_id' => $existing['Id'],
                        'action' => 'skipped',
                    ];
                }

                if ($action === 'update') {
                    return $this->updateRecord($objectType, $existing['Id'], $data, $token, $submission['id'] ?? 0);
                }
            }
        }

        // Create new record
        return $this->createRecord($objectType, $data, $token, $submission['id'] ?? 0);
    }

    /**
     * Create Salesforce record
     *
     * @param string $objectType Object type (Lead, Contact, etc.)
     * @param array $data Record data
     * @param string $token Access token
     * @param int $submissionId Submission ID
     * @return array
     */
    private function createRecord(string $objectType, array $data, string $token, int $submissionId): array
    {
        $config = $this->getConfig();
        $url = rtrim($config['instance_url'], '/') . '/services/data/' . self::API_VERSION . '/sobjects/' . $objectType;

        $response = $this->makeRequest($url, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($data),
        ]);

        if (!$response['success']) {
            $error = $response['error'] ?? __('Unknown error', 'formflow-pro');

            // Handle specific Salesforce errors
            if (isset($response['data'][0]['errorCode'])) {
                $error = $response['data'][0]['message'] ?? $response['data'][0]['errorCode'];
            }

            $this->recordSync($submissionId, 'failed', null, $error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $externalId = $response['data']['id'] ?? null;

        $this->recordSync($submissionId, 'success', $externalId);

        $this->logEvent('record_created', [
            'object_type' => $objectType,
            'external_id' => $externalId,
            'submission_id' => $submissionId,
        ]);

        return [
            'success' => true,
            'message' => sprintf(__('%s created successfully.', 'formflow-pro'), $objectType),
            'external_id' => $externalId,
            'action' => 'created',
        ];
    }

    /**
     * Update Salesforce record
     *
     * @param string $objectType Object type
     * @param string $recordId Salesforce record ID
     * @param array $data Record data
     * @param string $token Access token
     * @param int $submissionId Submission ID
     * @return array
     */
    private function updateRecord(string $objectType, string $recordId, array $data, string $token, int $submissionId): array
    {
        $config = $this->getConfig();
        $url = rtrim($config['instance_url'], '/') . '/services/data/' . self::API_VERSION . '/sobjects/' . $objectType . '/' . $recordId;

        $response = $this->makeRequest($url, [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($data),
        ]);

        // PATCH returns 204 No Content on success
        if (!$response['success'] && $response['code'] !== 204) {
            $error = $response['error'] ?? __('Unknown error', 'formflow-pro');

            $this->recordSync($submissionId, 'failed', $recordId, $error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $this->recordSync($submissionId, 'success', $recordId);

        $this->logEvent('record_updated', [
            'object_type' => $objectType,
            'external_id' => $recordId,
            'submission_id' => $submissionId,
        ]);

        return [
            'success' => true,
            'message' => sprintf(__('%s updated successfully.', 'formflow-pro'), $objectType),
            'external_id' => $recordId,
            'action' => 'updated',
        ];
    }

    /**
     * Find record by email
     *
     * @param string $email Email address
     * @param string $objectType Object type
     * @param string $token Access token
     * @return array|null
     */
    private function findByEmail(string $email, string $objectType, string $token): ?array
    {
        $config = $this->getConfig();
        $query = sprintf(
            "SELECT Id, Email FROM %s WHERE Email = '%s' LIMIT 1",
            $objectType,
            esc_sql($email)
        );

        $url = rtrim($config['instance_url'], '/') . '/services/data/' . self::API_VERSION . '/query?q=' . rawurlencode($query);

        $response = $this->makeRequest($url, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (!$response['success'] || empty($response['data']['records'])) {
            return null;
        }

        return $response['data']['records'][0];
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableFields(): array
    {
        if (!$this->isConfigured()) {
            return $this->getDefaultFields();
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return $this->getDefaultFields();
        }

        $config = $this->getConfig();
        $objectType = $config['default_object'] ?? 'Lead';

        // Check cache
        $cacheKey = 'formflow_sf_fields_' . $objectType;
        $cached = get_transient($cacheKey);
        if ($cached) {
            return $cached;
        }

        $url = rtrim($config['instance_url'], '/') . '/services/data/' . self::API_VERSION . '/sobjects/' . $objectType . '/describe';

        $response = $this->makeRequest($url, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (!$response['success'] || empty($response['data']['fields'])) {
            return $this->getDefaultFields();
        }

        $fields = [];
        foreach ($response['data']['fields'] as $field) {
            // Only include creatable/updateable fields
            if (!$field['createable'] && !$field['updateable']) {
                continue;
            }

            $fields[] = [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $this->mapSalesforceType($field['type']),
                'required' => !$field['nillable'] && $field['createable'],
            ];
        }

        // Cache for 1 hour
        set_transient($cacheKey, $fields, 3600);

        return $fields;
    }

    /**
     * Get default fields when API not available
     *
     * @return array
     */
    private function getDefaultFields(): array
    {
        return [
            ['name' => 'FirstName', 'label' => __('First Name', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'LastName', 'label' => __('Last Name', 'formflow-pro'), 'type' => 'text', 'required' => true],
            ['name' => 'Email', 'label' => __('Email', 'formflow-pro'), 'type' => 'email', 'required' => false],
            ['name' => 'Phone', 'label' => __('Phone', 'formflow-pro'), 'type' => 'phone', 'required' => false],
            ['name' => 'Company', 'label' => __('Company', 'formflow-pro'), 'type' => 'text', 'required' => true],
            ['name' => 'Title', 'label' => __('Title', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'Street', 'label' => __('Street', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'City', 'label' => __('City', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'State', 'label' => __('State', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'PostalCode', 'label' => __('Postal Code', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'Country', 'label' => __('Country', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'Website', 'label' => __('Website', 'formflow-pro'), 'type' => 'url', 'required' => false],
            ['name' => 'Description', 'label' => __('Description', 'formflow-pro'), 'type' => 'textarea', 'required' => false],
            ['name' => 'LeadSource', 'label' => __('Lead Source', 'formflow-pro'), 'type' => 'text', 'required' => false],
        ];
    }

    /**
     * Map Salesforce field type to standard type
     *
     * @param string $sfType Salesforce type
     * @return string
     */
    private function mapSalesforceType(string $sfType): string
    {
        $mapping = [
            'string' => 'text',
            'textarea' => 'textarea',
            'email' => 'email',
            'phone' => 'phone',
            'url' => 'url',
            'boolean' => 'checkbox',
            'int' => 'number',
            'double' => 'number',
            'currency' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            'picklist' => 'select',
            'multipicklist' => 'multiselect',
        ];

        return $mapping[$sfType] ?? 'text';
    }
}
