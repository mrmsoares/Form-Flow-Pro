<?php

declare(strict_types=1);

/**
 * HubSpot Integration
 *
 * Handles integration with HubSpot CRM.
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
 * HubSpot Integration Class
 */
class HubSpotIntegration extends AbstractIntegration
{
    /**
     * HubSpot API base URL
     */
    private const API_BASE = 'https://api.hubapi.com';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'hubspot';
        $this->name = __('HubSpot', 'formflow-pro');
        $this->description = __('Sync form submissions with HubSpot CRM as Contacts or Deals.', 'formflow-pro');
        $this->icon = FORMFLOW_URL . 'assets/images/integrations/hubspot.svg';
        $this->optionName = 'formflow_integration_hubspot';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        return !empty($config['access_token']) || !empty($config['api_key']);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'auth_type',
                'label' => __('Authentication Type', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Choose how to authenticate with HubSpot.', 'formflow-pro'),
                'options' => [
                    ['value' => 'private_app', 'label' => __('Private App (Recommended)', 'formflow-pro')],
                    ['value' => 'api_key', 'label' => __('API Key (Legacy)', 'formflow-pro')],
                ],
                'default' => 'private_app',
            ],
            [
                'name' => 'access_token',
                'label' => __('Access Token', 'formflow-pro'),
                'type' => 'password',
                'description' => __('Private App access token from HubSpot.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'api_key',
                'label' => __('API Key (Legacy)', 'formflow-pro'),
                'type' => 'password',
                'description' => __('Legacy API Key. Use Private App instead.', 'formflow-pro'),
                'required' => false,
            ],
            [
                'name' => 'portal_id',
                'label' => __('Portal ID', 'formflow-pro'),
                'type' => 'text',
                'description' => __('Your HubSpot Portal ID (optional, auto-detected).', 'formflow-pro'),
                'required' => false,
            ],
            [
                'name' => 'default_object',
                'label' => __('Default Object', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Default HubSpot object to create.', 'formflow-pro'),
                'options' => [
                    ['value' => 'contacts', 'label' => __('Contact', 'formflow-pro')],
                    ['value' => 'deals', 'label' => __('Deal', 'formflow-pro')],
                    ['value' => 'tickets', 'label' => __('Ticket', 'formflow-pro')],
                    ['value' => 'companies', 'label' => __('Company', 'formflow-pro')],
                ],
                'default' => 'contacts',
            ],
            [
                'name' => 'lifecycle_stage',
                'label' => __('Lifecycle Stage', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Default lifecycle stage for new contacts.', 'formflow-pro'),
                'options' => [
                    ['value' => 'subscriber', 'label' => __('Subscriber', 'formflow-pro')],
                    ['value' => 'lead', 'label' => __('Lead', 'formflow-pro')],
                    ['value' => 'marketingqualifiedlead', 'label' => __('Marketing Qualified Lead', 'formflow-pro')],
                    ['value' => 'salesqualifiedlead', 'label' => __('Sales Qualified Lead', 'formflow-pro')],
                    ['value' => 'opportunity', 'label' => __('Opportunity', 'formflow-pro')],
                    ['value' => 'customer', 'label' => __('Customer', 'formflow-pro')],
                ],
                'default' => 'lead',
            ],
            [
                'name' => 'lead_status',
                'label' => __('Lead Status', 'formflow-pro'),
                'type' => 'select',
                'description' => __('Default lead status for new contacts.', 'formflow-pro'),
                'options' => [
                    ['value' => 'NEW', 'label' => __('New', 'formflow-pro')],
                    ['value' => 'OPEN', 'label' => __('Open', 'formflow-pro')],
                    ['value' => 'IN_PROGRESS', 'label' => __('In Progress', 'formflow-pro')],
                    ['value' => 'OPEN_DEAL', 'label' => __('Open Deal', 'formflow-pro')],
                    ['value' => 'UNQUALIFIED', 'label' => __('Unqualified', 'formflow-pro')],
                    ['value' => 'ATTEMPTED_TO_CONTACT', 'label' => __('Attempted to Contact', 'formflow-pro')],
                    ['value' => 'CONNECTED', 'label' => __('Connected', 'formflow-pro')],
                ],
                'default' => 'NEW',
            ],
            [
                'name' => 'update_existing',
                'label' => __('Update Existing Contacts', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Update contact if email already exists.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'create_timeline',
                'label' => __('Create Timeline Event', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Create timeline event for form submission.', 'formflow-pro'),
                'default' => true,
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
                'message' => __('Please configure authentication first.', 'formflow-pro'),
            ];
        }

        // Test with account info endpoint
        $response = $this->apiRequest('/account-info/v3/details');

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Connection failed: %s', 'formflow-pro'),
                    $response['error'] ?? __('Unknown error', 'formflow-pro')
                ),
            ];
        }

        // Save portal ID if not set
        $config = $this->getConfig();
        if (empty($config['portal_id']) && !empty($response['data']['portalId'])) {
            $config['portal_id'] = (string) $response['data']['portalId'];
            update_option($this->optionName, $config);
            $this->config = null;
        }

        $this->logEvent('connection_test_success', [
            'portal_id' => $response['data']['portalId'] ?? 'unknown',
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                __('Connected successfully. Portal ID: %s', 'formflow-pro'),
                $response['data']['portalId'] ?? 'Unknown'
            ),
            'data' => [
                'portal_id' => $response['data']['portalId'] ?? null,
                'hub_domain' => $response['data']['uiDomain'] ?? null,
            ],
        ];
    }

    /**
     * Make API request to HubSpot
     *
     * @param string $endpoint API endpoint
     * @param array $options Request options
     * @return array
     */
    private function apiRequest(string $endpoint, array $options = []): array
    {
        $config = $this->getConfig();
        $url = self::API_BASE . $endpoint;

        // Add authentication
        $headers = $options['headers'] ?? [];

        if ($config['auth_type'] === 'api_key' && !empty($config['api_key'])) {
            // Legacy API key authentication
            $separator = strpos($endpoint, '?') !== false ? '&' : '?';
            $url .= $separator . 'hapikey=' . $config['api_key'];
        } else {
            // Private App token authentication
            $headers['Authorization'] = 'Bearer ' . ($config['access_token'] ?? '');
        }

        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';

        $args = [
            'method' => $options['method'] ?? 'GET',
            'headers' => $headers,
            'timeout' => 30,
        ];

        if (!empty($options['body'])) {
            $args['body'] = is_array($options['body']) ? wp_json_encode($options['body']) : $options['body'];
        }

        return $this->makeRequest($url, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function sendSubmission(array $submission, array $mapping): array
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('HubSpot integration is not configured or enabled.', 'formflow-pro'),
            ];
        }

        $config = $this->getConfig();
        $objectType = $mapping['_object_type'] ?? $config['default_object'] ?? 'contacts';
        unset($mapping['_object_type']);

        // Map fields
        $data = $this->mapFields($submission, $mapping);

        // Add default properties for contacts
        if ($objectType === 'contacts') {
            if (!empty($config['lifecycle_stage'])) {
                $data['lifecyclestage'] = $config['lifecycle_stage'];
            }
            if (!empty($config['lead_status'])) {
                $data['hs_lead_status'] = $config['lead_status'];
            }
        }

        // Check for existing contact
        $existingId = null;
        if ($objectType === 'contacts' && !empty($data['email']) && !empty($config['update_existing'])) {
            $existing = $this->findContactByEmail($data['email']);
            if ($existing) {
                $existingId = $existing['id'];
            }
        }

        // Format properties for HubSpot API
        $properties = [];
        foreach ($data as $key => $value) {
            $properties[$key] = $value;
        }

        if ($existingId) {
            return $this->updateContact($existingId, $properties, $submission['id'] ?? 0);
        }

        return $this->createRecord($objectType, $properties, $submission['id'] ?? 0);
    }

    /**
     * Create HubSpot record
     *
     * @param string $objectType Object type
     * @param array $properties Record properties
     * @param int $submissionId Submission ID
     * @return array
     */
    private function createRecord(string $objectType, array $properties, int $submissionId): array
    {
        $endpoint = "/crm/v3/objects/{$objectType}";

        $response = $this->apiRequest($endpoint, [
            'method' => 'POST',
            'body' => ['properties' => $properties],
        ]);

        if (!$response['success']) {
            $error = $response['error'] ?? __('Unknown error', 'formflow-pro');

            // Parse HubSpot error
            if (isset($response['data']['message'])) {
                $error = $response['data']['message'];
            }

            $this->recordSync($submissionId, 'failed', null, $error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $externalId = (string) ($response['data']['id'] ?? '');

        $this->recordSync($submissionId, 'success', $externalId);

        $this->logEvent('record_created', [
            'object_type' => $objectType,
            'external_id' => $externalId,
            'submission_id' => $submissionId,
        ]);

        // Create timeline event if enabled
        $config = $this->getConfig();
        if (!empty($config['create_timeline']) && $objectType === 'contacts' && $externalId) {
            $this->createTimelineEvent($externalId, $submissionId);
        }

        return [
            'success' => true,
            'message' => sprintf(__('%s created successfully.', 'formflow-pro'), ucfirst(rtrim($objectType, 's'))),
            'external_id' => $externalId,
            'action' => 'created',
        ];
    }

    /**
     * Update HubSpot contact
     *
     * @param string $contactId Contact ID
     * @param array $properties Contact properties
     * @param int $submissionId Submission ID
     * @return array
     */
    private function updateContact(string $contactId, array $properties, int $submissionId): array
    {
        $endpoint = "/crm/v3/objects/contacts/{$contactId}";

        $response = $this->apiRequest($endpoint, [
            'method' => 'PATCH',
            'body' => ['properties' => $properties],
        ]);

        if (!$response['success']) {
            $error = $response['error'] ?? __('Unknown error', 'formflow-pro');

            $this->recordSync($submissionId, 'failed', $contactId, $error);

            return [
                'success' => false,
                'message' => $error,
            ];
        }

        $this->recordSync($submissionId, 'success', $contactId);

        $this->logEvent('record_updated', [
            'object_type' => 'contacts',
            'external_id' => $contactId,
            'submission_id' => $submissionId,
        ]);

        // Create timeline event
        $config = $this->getConfig();
        if (!empty($config['create_timeline'])) {
            $this->createTimelineEvent($contactId, $submissionId);
        }

        return [
            'success' => true,
            'message' => __('Contact updated successfully.', 'formflow-pro'),
            'external_id' => $contactId,
            'action' => 'updated',
        ];
    }

    /**
     * Find contact by email
     *
     * @param string $email Email address
     * @return array|null
     */
    private function findContactByEmail(string $email): ?array
    {
        $endpoint = '/crm/v3/objects/contacts/search';

        $response = $this->apiRequest($endpoint, [
            'method' => 'POST',
            'body' => [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email,
                            ],
                        ],
                    ],
                ],
                'limit' => 1,
            ],
        ]);

        if (!$response['success'] || empty($response['data']['results'])) {
            return null;
        }

        return $response['data']['results'][0];
    }

    /**
     * Create timeline event
     *
     * @param string $contactId Contact ID
     * @param int $submissionId Submission ID
     * @return bool
     */
    private function createTimelineEvent(string $contactId, int $submissionId): bool
    {
        // Note: Timeline events require a custom timeline event type to be created in HubSpot
        // This is a simplified version that adds a note instead

        $endpoint = "/crm/v3/objects/notes";

        $response = $this->apiRequest($endpoint, [
            'method' => 'POST',
            'body' => [
                'properties' => [
                    'hs_note_body' => sprintf(
                        __('Form submission received via FormFlow Pro (ID: %d)', 'formflow-pro'),
                        $submissionId
                    ),
                    'hs_timestamp' => (string) (time() * 1000),
                ],
                'associations' => [
                    [
                        'to' => ['id' => $contactId],
                        'types' => [
                            [
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 202, // Note to Contact
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $response['success'];
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableFields(): array
    {
        if (!$this->isConfigured()) {
            return $this->getDefaultFields();
        }

        $config = $this->getConfig();
        $objectType = $config['default_object'] ?? 'contacts';

        // Check cache
        $cacheKey = 'formflow_hs_fields_' . $objectType;
        $cached = get_transient($cacheKey);
        if ($cached) {
            return $cached;
        }

        $endpoint = "/crm/v3/properties/{$objectType}";
        $response = $this->apiRequest($endpoint);

        if (!$response['success'] || empty($response['data']['results'])) {
            return $this->getDefaultFields();
        }

        $fields = [];
        foreach ($response['data']['results'] as $property) {
            // Skip read-only and calculated properties
            if ($property['modificationMetadata']['readOnlyValue'] ?? false) {
                continue;
            }
            if ($property['calculated'] ?? false) {
                continue;
            }

            $fields[] = [
                'name' => $property['name'],
                'label' => $property['label'],
                'type' => $this->mapHubSpotType($property['type']),
                'required' => !empty($property['required']),
                'group' => $property['groupName'] ?? 'other',
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
            ['name' => 'email', 'label' => __('Email', 'formflow-pro'), 'type' => 'email', 'required' => true],
            ['name' => 'firstname', 'label' => __('First Name', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'lastname', 'label' => __('Last Name', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'phone', 'label' => __('Phone', 'formflow-pro'), 'type' => 'phone', 'required' => false],
            ['name' => 'company', 'label' => __('Company', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'jobtitle', 'label' => __('Job Title', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'website', 'label' => __('Website', 'formflow-pro'), 'type' => 'url', 'required' => false],
            ['name' => 'address', 'label' => __('Street Address', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'city', 'label' => __('City', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'state', 'label' => __('State/Region', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'zip', 'label' => __('Postal Code', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'country', 'label' => __('Country', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'message', 'label' => __('Message', 'formflow-pro'), 'type' => 'textarea', 'required' => false],
        ];
    }

    /**
     * Map HubSpot field type to standard type
     *
     * @param string $hsType HubSpot type
     * @return string
     */
    private function mapHubSpotType(string $hsType): string
    {
        $mapping = [
            'string' => 'text',
            'number' => 'number',
            'date' => 'date',
            'datetime' => 'datetime',
            'enumeration' => 'select',
            'bool' => 'checkbox',
            'phone_number' => 'phone',
        ];

        return $mapping[$hsType] ?? 'text';
    }
}
