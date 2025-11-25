<?php

declare(strict_types=1);

/**
 * Google Sheets Integration
 *
 * Handles integration with Google Sheets API.
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
 * Google Sheets Integration Class
 */
class GoogleSheetsIntegration extends AbstractIntegration
{
    /**
     * Google Sheets API base URL
     */
    private const SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets';

    /**
     * Google OAuth token URL
     */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id = 'google_sheets';
        $this->name = __('Google Sheets', 'formflow-pro');
        $this->description = __('Automatically export form submissions to Google Sheets.', 'formflow-pro');
        $this->icon = FORMFLOW_URL . 'assets/images/integrations/google-sheets.svg';
        $this->optionName = 'formflow_integration_google_sheets';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        $config = $this->getConfig();
        return !empty($config['client_id'])
            && !empty($config['client_secret'])
            && (!empty($config['refresh_token']) || !empty($config['service_account_json']));
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
                'description' => __('Choose how to authenticate with Google.', 'formflow-pro'),
                'options' => [
                    ['value' => 'oauth', 'label' => __('OAuth 2.0 (User consent)', 'formflow-pro')],
                    ['value' => 'service_account', 'label' => __('Service Account (Server-to-server)', 'formflow-pro')],
                ],
                'default' => 'oauth',
            ],
            [
                'name' => 'client_id',
                'label' => __('Client ID', 'formflow-pro'),
                'type' => 'text',
                'description' => __('Google Cloud Console Client ID.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'client_secret',
                'label' => __('Client Secret', 'formflow-pro'),
                'type' => 'password',
                'description' => __('Google Cloud Console Client Secret.', 'formflow-pro'),
                'required' => true,
            ],
            [
                'name' => 'service_account_json',
                'label' => __('Service Account JSON', 'formflow-pro'),
                'type' => 'textarea',
                'description' => __('Paste your service account JSON key (for service account auth).', 'formflow-pro'),
                'required' => false,
            ],
            [
                'name' => 'default_spreadsheet_id',
                'label' => __('Default Spreadsheet ID', 'formflow-pro'),
                'type' => 'text',
                'description' => __('The spreadsheet ID from the Google Sheets URL.', 'formflow-pro'),
                'placeholder' => '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms',
                'required' => false,
            ],
            [
                'name' => 'default_sheet_name',
                'label' => __('Default Sheet Name', 'formflow-pro'),
                'type' => 'text',
                'description' => __('Default sheet/tab name within the spreadsheet.', 'formflow-pro'),
                'default' => 'Sheet1',
            ],
            [
                'name' => 'auto_create_headers',
                'label' => __('Auto-create Headers', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Automatically create column headers from form fields.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'include_timestamp',
                'label' => __('Include Timestamp', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Add submission timestamp as first column.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'include_submission_id',
                'label' => __('Include Submission ID', 'formflow-pro'),
                'type' => 'checkbox',
                'description' => __('Add submission ID column.', 'formflow-pro'),
                'default' => true,
            ],
            [
                'name' => 'date_format',
                'label' => __('Date Format', 'formflow-pro'),
                'type' => 'text',
                'description' => __('PHP date format for timestamps.', 'formflow-pro'),
                'default' => 'Y-m-d H:i:s',
            ],
        ];
    }

    /**
     * Get OAuth authorization URL
     *
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        $config = $this->getConfig();
        $redirectUri = admin_url('admin.php?page=formflow-settings&tab=integrations&integration=google_sheets&action=oauth_callback');

        $params = [
            'client_id' => $config['client_id'] ?? '',
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => wp_create_nonce('formflow_google_oauth'),
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Handle OAuth callback
     *
     * @param string $code Authorization code
     * @return array{success: bool, message: string}
     */
    public function handleOAuthCallback(string $code): array
    {
        $config = $this->getConfig();
        $redirectUri = admin_url('admin.php?page=formflow-settings&tab=integrations&integration=google_sheets&action=oauth_callback');

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['error'])) {
            return [
                'success' => false,
                'message' => $body['error_description'] ?? $body['error'],
            ];
        }

        if (empty($body['access_token'])) {
            return [
                'success' => false,
                'message' => __('No access token received.', 'formflow-pro'),
            ];
        }

        // Save tokens
        $config['access_token'] = $body['access_token'];
        $config['refresh_token'] = $body['refresh_token'] ?? $config['refresh_token'] ?? '';
        $config['token_expires'] = time() + ($body['expires_in'] ?? 3600);

        update_option($this->optionName, $config);
        $this->config = null;

        $this->logEvent('oauth_success');

        return [
            'success' => true,
            'message' => __('Successfully connected to Google Sheets!', 'formflow-pro'),
        ];
    }

    /**
     * Get access token (refresh if needed)
     *
     * @return string|null
     */
    private function getAccessToken(): ?string
    {
        $config = $this->getConfig();

        // Service account auth
        if (($config['auth_type'] ?? 'oauth') === 'service_account') {
            return $this->getServiceAccountToken();
        }

        // OAuth - check if token is valid
        if (!empty($config['access_token']) && ($config['token_expires'] ?? 0) > time() + 60) {
            return $config['access_token'];
        }

        // Refresh token
        if (empty($config['refresh_token'])) {
            $this->logError('No refresh token available');
            return null;
        }

        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'refresh_token' => $config['refresh_token'],
                'grant_type' => 'refresh_token',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logError('Token refresh failed', ['error' => $response->get_error_message()]);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            $this->logError('Token refresh failed', ['response' => $body]);
            return null;
        }

        // Update stored token
        $config['access_token'] = $body['access_token'];
        $config['token_expires'] = time() + ($body['expires_in'] ?? 3600);

        if (!empty($body['refresh_token'])) {
            $config['refresh_token'] = $body['refresh_token'];
        }

        update_option($this->optionName, $config);
        $this->config = null;

        return $body['access_token'];
    }

    /**
     * Get service account token
     *
     * @return string|null
     */
    private function getServiceAccountToken(): ?string
    {
        $config = $this->getConfig();

        if (empty($config['service_account_json'])) {
            return null;
        }

        $serviceAccount = json_decode($config['service_account_json'], true);
        if (!$serviceAccount) {
            $this->logError('Invalid service account JSON');
            return null;
        }

        // Check cached token
        $cached = get_transient('formflow_gs_sa_token');
        if ($cached) {
            return $cached;
        }

        // Create JWT
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $now = time();
        $claims = [
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $headerEncoded = $this->base64UrlEncode(wp_json_encode($header));
        $claimsEncoded = $this->base64UrlEncode(wp_json_encode($claims));

        $signatureInput = "{$headerEncoded}.{$claimsEncoded}";

        // Sign with private key
        $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
        if (!$privateKey) {
            $this->logError('Invalid private key in service account');
            return null;
        }

        openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureEncoded = $this->base64UrlEncode($signature);

        $jwt = "{$signatureInput}.{$signatureEncoded}";

        // Exchange JWT for access token
        $response = wp_remote_post(self::TOKEN_URL, [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ],
        ]);

        if (is_wp_error($response)) {
            $this->logError('Service account token request failed', ['error' => $response->get_error_message()]);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            $this->logError('No access token from service account', ['response' => $body]);
            return null;
        }

        // Cache token
        set_transient('formflow_gs_sa_token', $body['access_token'], 3500);

        return $body['access_token'];
    }

    /**
     * Base64 URL encode
     *
     * @param string $data Data to encode
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('Failed to get access token. Please re-authorize.', 'formflow-pro'),
            ];
        }

        $config = $this->getConfig();
        $spreadsheetId = $config['default_spreadsheet_id'] ?? '';

        if (empty($spreadsheetId)) {
            // Just verify token works
            return [
                'success' => true,
                'message' => __('Authentication successful! Configure a spreadsheet ID to complete setup.', 'formflow-pro'),
            ];
        }

        // Try to get spreadsheet metadata
        $url = self::SHEETS_API . '/' . $spreadsheetId;
        $response = $this->makeRequest($url, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Failed to access spreadsheet: %s', 'formflow-pro'),
                    $response['error'] ?? __('Unknown error', 'formflow-pro')
                ),
            ];
        }

        $this->logEvent('connection_test_success', [
            'spreadsheet' => $response['data']['properties']['title'] ?? 'Unknown',
        ]);

        return [
            'success' => true,
            'message' => sprintf(
                __('Connected to spreadsheet: %s', 'formflow-pro'),
                $response['data']['properties']['title'] ?? 'Unknown'
            ),
            'data' => [
                'title' => $response['data']['properties']['title'] ?? null,
                'sheets' => array_column($response['data']['sheets'] ?? [], 'properties'),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function sendSubmission(array $submission, array $mapping): array
    {
        if (!$this->isEnabled() || !$this->isConfigured()) {
            return [
                'success' => false,
                'message' => __('Google Sheets integration is not configured or enabled.', 'formflow-pro'),
            ];
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('Failed to authenticate with Google.', 'formflow-pro'),
            ];
        }

        $config = $this->getConfig();
        $spreadsheetId = $mapping['_spreadsheet_id'] ?? $config['default_spreadsheet_id'] ?? '';
        $sheetName = $mapping['_sheet_name'] ?? $config['default_sheet_name'] ?? 'Sheet1';
        unset($mapping['_spreadsheet_id'], $mapping['_sheet_name']);

        if (empty($spreadsheetId)) {
            return [
                'success' => false,
                'message' => __('No spreadsheet ID configured.', 'formflow-pro'),
            ];
        }

        // Build row data
        $rowData = $this->buildRowData($submission, $mapping, $config);

        // Check if we need to create headers
        if (!empty($config['auto_create_headers'])) {
            $this->ensureHeaders($spreadsheetId, $sheetName, array_keys($rowData), $token);
        }

        // Append row
        $result = $this->appendRow($spreadsheetId, $sheetName, array_values($rowData), $token);

        if ($result['success']) {
            $this->recordSync(
                $submission['id'] ?? 0,
                'success',
                $result['updated_range'] ?? null
            );

            return [
                'success' => true,
                'message' => __('Data exported to Google Sheets successfully.', 'formflow-pro'),
                'external_id' => $result['updated_range'] ?? null,
                'action' => 'appended',
            ];
        }

        $this->recordSync(
            $submission['id'] ?? 0,
            'failed',
            null,
            $result['error'] ?? 'Unknown error'
        );

        return [
            'success' => false,
            'message' => $result['error'] ?? __('Failed to export to Google Sheets.', 'formflow-pro'),
        ];
    }

    /**
     * Build row data from submission
     *
     * @param array $submission Submission data
     * @param array $mapping Field mapping
     * @param array $config Integration config
     * @return array
     */
    private function buildRowData(array $submission, array $mapping, array $config): array
    {
        $row = [];

        // Add timestamp if enabled
        if (!empty($config['include_timestamp'])) {
            $dateFormat = $config['date_format'] ?? 'Y-m-d H:i:s';
            $timestamp = $submission['created_at'] ?? current_time('mysql');
            $row['Timestamp'] = date($dateFormat, strtotime($timestamp));
        }

        // Add submission ID if enabled
        if (!empty($config['include_submission_id'])) {
            $row['Submission ID'] = $submission['id'] ?? '';
        }

        // Get form data
        $formData = $submission['form_data'] ?? $submission['data'] ?? [];

        // Map fields
        if (!empty($mapping)) {
            foreach ($mapping as $columnName => $fieldPath) {
                if (strpos($columnName, '_') === 0) {
                    continue;
                }
                $row[$columnName] = $this->getNestedValue($formData, $fieldPath) ?? '';
            }
        } else {
            // No mapping - use all form data
            foreach ($formData as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $row[$key] = $value;
            }
        }

        return $row;
    }

    /**
     * Ensure headers exist in sheet
     *
     * @param string $spreadsheetId Spreadsheet ID
     * @param string $sheetName Sheet name
     * @param array $headers Header names
     * @param string $token Access token
     * @return void
     */
    private function ensureHeaders(string $spreadsheetId, string $sheetName, array $headers, string $token): void
    {
        // Check if first row has data
        $range = "{$sheetName}!A1:" . $this->columnLetter(count($headers)) . "1";
        $url = self::SHEETS_API . "/{$spreadsheetId}/values/{$range}";

        $response = $this->makeRequest($url, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if ($response['success'] && !empty($response['data']['values'])) {
            // Headers already exist
            return;
        }

        // Create headers
        $url = self::SHEETS_API . "/{$spreadsheetId}/values/{$range}?valueInputOption=RAW";

        $this->makeRequest($url, [
            'method' => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'values' => [$headers],
            ]),
        ]);
    }

    /**
     * Append row to sheet
     *
     * @param string $spreadsheetId Spreadsheet ID
     * @param string $sheetName Sheet name
     * @param array $values Row values
     * @param string $token Access token
     * @return array
     */
    private function appendRow(string $spreadsheetId, string $sheetName, array $values, string $token): array
    {
        $range = "{$sheetName}!A:A";
        $url = self::SHEETS_API . "/{$spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

        $response = $this->makeRequest($url, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'values' => [$values],
            ]),
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'] ?? __('Unknown error', 'formflow-pro'),
            ];
        }

        $this->logEvent('row_appended', [
            'spreadsheet_id' => $spreadsheetId,
            'sheet' => $sheetName,
            'range' => $response['data']['updates']['updatedRange'] ?? null,
        ]);

        return [
            'success' => true,
            'updated_range' => $response['data']['updates']['updatedRange'] ?? null,
            'updated_rows' => $response['data']['updates']['updatedRows'] ?? 1,
        ];
    }

    /**
     * Convert column number to letter
     *
     * @param int $num Column number (1-based)
     * @return string
     */
    private function columnLetter(int $num): string
    {
        $letter = '';
        while ($num > 0) {
            $num--;
            $letter = chr(65 + ($num % 26)) . $letter;
            $num = (int) ($num / 26);
        }
        return $letter;
    }

    /**
     * {@inheritdoc}
     */
    public function getAvailableFields(): array
    {
        // Google Sheets is flexible - returns common fields
        return [
            ['name' => 'email', 'label' => __('Email', 'formflow-pro'), 'type' => 'email', 'required' => false],
            ['name' => 'name', 'label' => __('Name', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'phone', 'label' => __('Phone', 'formflow-pro'), 'type' => 'phone', 'required' => false],
            ['name' => 'message', 'label' => __('Message', 'formflow-pro'), 'type' => 'textarea', 'required' => false],
            ['name' => 'company', 'label' => __('Company', 'formflow-pro'), 'type' => 'text', 'required' => false],
            ['name' => 'address', 'label' => __('Address', 'formflow-pro'), 'type' => 'text', 'required' => false],
        ];
    }

    /**
     * Get list of sheets in a spreadsheet
     *
     * @param string $spreadsheetId Spreadsheet ID
     * @return array
     */
    public function getSheets(string $spreadsheetId): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $url = self::SHEETS_API . '/' . $spreadsheetId . '?fields=sheets.properties';
        $response = $this->makeRequest($url, [
            'method' => 'GET',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        if (!$response['success']) {
            return [];
        }

        return array_map(function ($sheet) {
            return [
                'id' => $sheet['properties']['sheetId'],
                'title' => $sheet['properties']['title'],
                'index' => $sheet['properties']['index'],
            ];
        }, $response['data']['sheets'] ?? []);
    }

    /**
     * Create new sheet in spreadsheet
     *
     * @param string $spreadsheetId Spreadsheet ID
     * @param string $title Sheet title
     * @return array
     */
    public function createSheet(string $spreadsheetId, string $title): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('Not authenticated.', 'formflow-pro'),
            ];
        }

        $url = self::SHEETS_API . '/' . $spreadsheetId . ':batchUpdate';
        $response = $this->makeRequest($url, [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'requests' => [
                    [
                        'addSheet' => [
                            'properties' => [
                                'title' => $title,
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => $response['error'] ?? __('Failed to create sheet.', 'formflow-pro'),
            ];
        }

        return [
            'success' => true,
            'sheet_id' => $response['data']['replies'][0]['addSheet']['properties']['sheetId'] ?? null,
            'title' => $title,
        ];
    }
}
