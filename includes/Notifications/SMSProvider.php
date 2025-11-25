<?php

declare(strict_types=1);

namespace FormFlowPro\Notifications;

/**
 * SMS Provider Interface and Implementations
 *
 * Supports multiple SMS providers:
 * - Twilio (global leader)
 * - Vonage/Nexmo (international coverage)
 * - AWS SNS (scalable)
 * - MessageBird (European focus)
 *
 * @package FormFlowPro\Notifications
 * @since 2.4.0
 */

interface SMSProviderInterface
{
    public function send(string $to, string $message, array $options = []): array;
    public function sendBulk(array $recipients, string $message, array $options = []): array;
    public function getDeliveryStatus(string $messageId): array;
    public function validatePhoneNumber(string $phone): bool;
    public function getBalance(): ?float;
    public function isConfigured(): bool;
}

/**
 * Twilio SMS Provider
 */
class TwilioProvider implements SMSProviderInterface
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;
    private string $messagingServiceSid;
    private string $apiUrl = 'https://api.twilio.com/2010-04-01';

    public function __construct()
    {
        $this->accountSid = get_option('formflow_twilio_account_sid', '');
        $this->authToken = get_option('formflow_twilio_auth_token', '');
        $this->fromNumber = get_option('formflow_twilio_from_number', '');
        $this->messagingServiceSid = get_option('formflow_twilio_messaging_service_sid', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accountSid) && !empty($this->authToken) &&
               (!empty($this->fromNumber) || !empty($this->messagingServiceSid));
    }

    public function send(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Twilio not configured'];
        }

        $to = $this->normalizePhoneNumber($to);

        $params = [
            'To' => $to,
            'Body' => $message,
        ];

        // Use Messaging Service or From Number
        if (!empty($this->messagingServiceSid)) {
            $params['MessagingServiceSid'] = $this->messagingServiceSid;
        } else {
            $params['From'] = $this->fromNumber;
        }

        // Optional parameters
        if (!empty($options['status_callback'])) {
            $params['StatusCallback'] = $options['status_callback'];
        }

        if (!empty($options['media_url'])) {
            $params['MediaUrl'] = $options['media_url'];
        }

        $response = $this->makeRequest(
            "/Accounts/{$this->accountSid}/Messages.json",
            'POST',
            $params
        );

        if (isset($response['sid'])) {
            return [
                'success' => true,
                'message_id' => $response['sid'],
                'status' => $response['status'],
                'to' => $response['to'],
                'provider' => 'twilio',
            ];
        }

        return [
            'success' => false,
            'error' => $response['message'] ?? 'Unknown error',
            'code' => $response['code'] ?? null,
        ];
    }

    public function sendBulk(array $recipients, string $message, array $options = []): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
            $personalizedMessage = $message;

            // Personalize message with recipient data
            if (is_array($recipient) && isset($recipient['data'])) {
                foreach ($recipient['data'] as $key => $value) {
                    $personalizedMessage = str_replace("{{$key}}", $value, $personalizedMessage);
                }
            }

            $results[] = $this->send($phone, $personalizedMessage, $options);

            // Rate limiting: Twilio allows ~100 messages/second
            usleep(15000); // 15ms delay
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ];
    }

    public function getDeliveryStatus(string $messageId): array
    {
        $response = $this->makeRequest(
            "/Accounts/{$this->accountSid}/Messages/{$messageId}.json",
            'GET'
        );

        if (isset($response['status'])) {
            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => $response['status'],
                'error_code' => $response['error_code'] ?? null,
                'error_message' => $response['error_message'] ?? null,
                'date_sent' => $response['date_sent'] ?? null,
                'date_updated' => $response['date_updated'] ?? null,
            ];
        }

        return ['success' => false, 'error' => $response['message'] ?? 'Unknown error'];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        $response = $this->makeRequest(
            "/Accounts/{$this->accountSid}/IncomingPhoneNumbers.json",
            'GET',
            ['PhoneNumber' => $phone]
        );

        // Use Lookup API for validation
        $lookupResponse = wp_remote_get(
            "https://lookups.twilio.com/v1/PhoneNumbers/{$phone}",
            [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$this->accountSid}:{$this->authToken}"),
                ],
                'timeout' => 10,
            ]
        );

        if (!is_wp_error($lookupResponse)) {
            $body = json_decode(wp_remote_retrieve_body($lookupResponse), true);
            return isset($body['phone_number']);
        }

        return false;
    }

    public function getBalance(): ?float
    {
        $response = $this->makeRequest(
            "/Accounts/{$this->accountSid}/Balance.json",
            'GET'
        );

        return isset($response['balance']) ? (float) $response['balance'] : null;
    }

    private function makeRequest(string $endpoint, string $method, array $params = []): array
    {
        $url = $this->apiUrl . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$this->accountSid}:{$this->authToken}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ];

        if ($method === 'POST' && !empty($params)) {
            $args['body'] = $params;
        } elseif ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (!str_starts_with($phone, '+')) {
            // Assume US number if no country code
            $phone = '+1' . ltrim($phone, '1');
        }

        return $phone;
    }
}

/**
 * Vonage (Nexmo) SMS Provider
 */
class VonageProvider implements SMSProviderInterface
{
    private string $apiKey;
    private string $apiSecret;
    private string $fromNumber;
    private string $apiUrl = 'https://rest.nexmo.com';

    public function __construct()
    {
        $this->apiKey = get_option('formflow_vonage_api_key', '');
        $this->apiSecret = get_option('formflow_vonage_api_secret', '');
        $this->fromNumber = get_option('formflow_vonage_from_number', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->fromNumber);
    }

    public function send(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Vonage not configured'];
        }

        $to = $this->normalizePhoneNumber($to);

        $params = [
            'api_key' => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'from' => $this->fromNumber,
            'to' => $to,
            'text' => $message,
        ];

        // Unicode detection
        if (preg_match('/[^\x00-\x7F]/', $message)) {
            $params['type'] = 'unicode';
        }

        // Callback URL
        if (!empty($options['status_callback'])) {
            $params['callback'] = $options['status_callback'];
        }

        $response = wp_remote_post(
            "{$this->apiUrl}/sms/json",
            [
                'body' => $params,
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['messages'][0])) {
            $msg = $body['messages'][0];

            if ($msg['status'] === '0') {
                return [
                    'success' => true,
                    'message_id' => $msg['message-id'],
                    'status' => 'sent',
                    'to' => $msg['to'],
                    'remaining_balance' => $msg['remaining-balance'] ?? null,
                    'message_price' => $msg['message-price'] ?? null,
                    'provider' => 'vonage',
                ];
            }

            return [
                'success' => false,
                'error' => $msg['error-text'] ?? 'Unknown error',
                'code' => $msg['status'],
            ];
        }

        return ['success' => false, 'error' => 'Invalid response from Vonage'];
    }

    public function sendBulk(array $recipients, string $message, array $options = []): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
            $personalizedMessage = $message;

            if (is_array($recipient) && isset($recipient['data'])) {
                foreach ($recipient['data'] as $key => $value) {
                    $personalizedMessage = str_replace("{{$key}}", $value, $personalizedMessage);
                }
            }

            $results[] = $this->send($phone, $personalizedMessage, $options);

            usleep(50000); // 50ms delay for rate limiting
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ];
    }

    public function getDeliveryStatus(string $messageId): array
    {
        // Vonage uses webhooks for delivery reports
        // This queries the search API
        $response = wp_remote_get(
            "{$this->apiUrl}/search/message?api_key={$this->apiKey}&api_secret={$this->apiSecret}&id={$messageId}",
            ['timeout' => 30]
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['message-id'])) {
            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => $body['status'] ?? 'unknown',
                'date_received' => $body['date-received'] ?? null,
            ];
        }

        return ['success' => false, 'error' => 'Message not found'];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        $phone = $this->normalizePhoneNumber($phone);

        $response = wp_remote_get(
            "https://api.nexmo.com/ni/basic/json?api_key={$this->apiKey}&api_secret={$this->apiSecret}&number={$phone}",
            ['timeout' => 10]
        );

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return isset($body['status']) && $body['status'] === 0;
        }

        return false;
    }

    public function getBalance(): ?float
    {
        $response = wp_remote_get(
            "{$this->apiUrl}/account/get-balance?api_key={$this->apiKey}&api_secret={$this->apiSecret}",
            ['timeout' => 10]
        );

        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            return isset($body['value']) ? (float) $body['value'] : null;
        }

        return null;
    }

    private function normalizePhoneNumber(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }
}

/**
 * AWS SNS SMS Provider
 */
class AWSSNSProvider implements SMSProviderInterface
{
    private string $accessKeyId;
    private string $secretAccessKey;
    private string $region;
    private string $senderId;

    public function __construct()
    {
        $this->accessKeyId = get_option('formflow_aws_access_key_id', '');
        $this->secretAccessKey = get_option('formflow_aws_secret_access_key', '');
        $this->region = get_option('formflow_aws_region', 'us-east-1');
        $this->senderId = get_option('formflow_aws_sender_id', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessKeyId) && !empty($this->secretAccessKey);
    }

    public function send(string $to, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'AWS SNS not configured'];
        }

        $to = $this->normalizePhoneNumber($to);

        $params = [
            'Action' => 'Publish',
            'Message' => $message,
            'PhoneNumber' => $to,
            'Version' => '2010-03-31',
        ];

        if (!empty($this->senderId)) {
            $params['MessageAttributes.entry.1.Name'] = 'AWS.SNS.SMS.SenderID';
            $params['MessageAttributes.entry.1.Value.StringValue'] = $this->senderId;
            $params['MessageAttributes.entry.1.Value.DataType'] = 'String';
        }

        // SMS Type: Promotional or Transactional
        $smsType = $options['sms_type'] ?? 'Transactional';
        $params['MessageAttributes.entry.2.Name'] = 'AWS.SNS.SMS.SMSType';
        $params['MessageAttributes.entry.2.Value.StringValue'] = $smsType;
        $params['MessageAttributes.entry.2.Value.DataType'] = 'String';

        $response = $this->makeRequest($params);

        if (isset($response['PublishResponse']['PublishResult']['MessageId'])) {
            return [
                'success' => true,
                'message_id' => $response['PublishResponse']['PublishResult']['MessageId'],
                'status' => 'sent',
                'to' => $to,
                'provider' => 'aws_sns',
            ];
        }

        return [
            'success' => false,
            'error' => $response['Error']['Message'] ?? 'Unknown error',
        ];
    }

    public function sendBulk(array $recipients, string $message, array $options = []): array
    {
        $results = [];

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
            $results[] = $this->send($phone, $message, $options);
            usleep(20000); // 20ms delay
        }

        return [
            'success' => true,
            'total' => count($recipients),
            'sent' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
        ];
    }

    public function getDeliveryStatus(string $messageId): array
    {
        // AWS SNS uses CloudWatch for delivery logs
        return [
            'success' => true,
            'message_id' => $messageId,
            'status' => 'check_cloudwatch',
            'note' => 'Use CloudWatch Logs for delivery status',
        ];
    }

    public function validatePhoneNumber(string $phone): bool
    {
        $phone = $this->normalizePhoneNumber($phone);
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    public function getBalance(): ?float
    {
        // AWS SNS doesn't have a balance API - it's pay per use
        return null;
    }

    private function makeRequest(array $params): array
    {
        $host = "sns.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/";
        $timestamp = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');

        $queryString = http_build_query($params);

        // AWS Signature Version 4
        $canonicalRequest = "POST\n/\n{$queryString}\nhost:{$host}\nx-amz-date:{$timestamp}\n\nhost;x-amz-date\n" .
            hash('sha256', '');

        $credentialScope = "{$datestamp}/{$this->region}/sns/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n" .
            hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey($datestamp);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKeyId}/{$credentialScope}, " .
            "SignedHeaders=host;x-amz-date, Signature={$signature}";

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Host' => $host,
                'X-Amz-Date' => $timestamp,
                'Authorization' => $authHeader,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $queryString,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['Error' => ['Message' => $response->get_error_message()]];
        }

        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);

        return json_decode(json_encode($xml), true) ?: [];
    }

    private function getSignatureKey(string $datestamp): string
    {
        $kDate = hash_hmac('sha256', $datestamp, 'AWS4' . $this->secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 'sns', $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            $phone = '+1' . ltrim($phone, '1');
        }
        return $phone;
    }
}

/**
 * SMS Manager - Factory and Manager for SMS Providers
 */
class SMSManager
{
    private static ?SMSManager $instance = null;
    private array $providers = [];
    private string $defaultProvider;
    private string $tableSMSLogs;

    private function __construct()
    {
        global $wpdb;
        $this->tableSMSLogs = $wpdb->prefix . 'formflow_sms_logs';
        $this->defaultProvider = get_option('formflow_sms_default_provider', 'twilio');

        $this->registerProviders();
        $this->initHooks();
    }

    public static function getInstance(): SMSManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function registerProviders(): void
    {
        $this->providers['twilio'] = new TwilioProvider();
        $this->providers['vonage'] = new VonageProvider();
        $this->providers['aws_sns'] = new AWSSNSProvider();
    }

    private function initHooks(): void
    {
        add_action('formflow_send_sms', [$this, 'scheduledSend'], 10, 3);
        add_action('formflow_submission_created', [$this, 'triggerSMSNotification'], 10, 2);
    }

    /**
     * Create database table
     */
    public function createTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableSMSLogs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(100) NULL,
            provider VARCHAR(50) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'delivered', 'failed', 'undelivered') DEFAULT 'pending',
            error_message TEXT NULL,
            form_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            cost DECIMAL(10,4) NULL,
            segments INT UNSIGNED DEFAULT 1,
            metadata JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_provider (provider),
            INDEX idx_status (status),
            INDEX idx_phone (phone_number),
            INDEX idx_form (form_id),
            INDEX idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get provider instance
     */
    public function getProvider(string $name = null): ?SMSProviderInterface
    {
        $name = $name ?? $this->defaultProvider;
        return $this->providers[$name] ?? null;
    }

    /**
     * Get all configured providers
     */
    public function getConfiguredProviders(): array
    {
        return array_filter(
            $this->providers,
            fn($provider) => $provider->isConfigured()
        );
    }

    /**
     * Send SMS
     */
    public function send(
        string $to,
        string $message,
        array $options = [],
        ?string $provider = null
    ): array {
        $providerInstance = $this->getProvider($provider);

        if (!$providerInstance || !$providerInstance->isConfigured()) {
            // Try fallback providers
            foreach ($this->providers as $name => $fallback) {
                if ($fallback->isConfigured()) {
                    $providerInstance = $fallback;
                    $provider = $name;
                    break;
                }
            }

            if (!$providerInstance) {
                return ['success' => false, 'error' => 'No SMS provider configured'];
            }
        }

        // Log the attempt
        $logId = $this->logSMS($to, $message, $provider ?? $this->defaultProvider, $options);

        // Send the message
        $result = $providerInstance->send($to, $message, $options);

        // Update log with result
        $this->updateLog($logId, $result);

        // Fire action for hooks
        do_action('formflow_sms_sent', $result, $to, $message, $options);

        return $result;
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(
        array $recipients,
        string $message,
        array $options = [],
        ?string $provider = null
    ): array {
        $providerInstance = $this->getProvider($provider);

        if (!$providerInstance || !$providerInstance->isConfigured()) {
            return ['success' => false, 'error' => 'No SMS provider configured'];
        }

        return $providerInstance->sendBulk($recipients, $message, $options);
    }

    /**
     * Schedule SMS for later
     */
    public function schedule(
        string $to,
        string $message,
        int $timestamp,
        array $options = [],
        ?string $provider = null
    ): bool {
        return (bool) wp_schedule_single_event(
            $timestamp,
            'formflow_send_sms',
            [$to, $message, array_merge($options, ['provider' => $provider])]
        );
    }

    /**
     * Scheduled send handler
     */
    public function scheduledSend(string $to, string $message, array $options = []): void
    {
        $provider = $options['provider'] ?? null;
        unset($options['provider']);

        $this->send($to, $message, $options, $provider);
    }

    /**
     * Trigger SMS notification on form submission
     */
    public function triggerSMSNotification(int $submissionId, array $data): void
    {
        $formId = $data['form_id'] ?? 0;

        // Get form SMS settings
        $smsSettings = get_post_meta($formId, '_formflow_sms_notifications', true);

        if (empty($smsSettings) || empty($smsSettings['enabled'])) {
            return;
        }

        // Admin notification
        if (!empty($smsSettings['admin_phone']) && !empty($smsSettings['admin_message'])) {
            $message = $this->parseTemplate($smsSettings['admin_message'], $data);
            $this->send($smsSettings['admin_phone'], $message, [
                'form_id' => $formId,
                'submission_id' => $submissionId,
            ]);
        }

        // User notification (if phone field exists)
        if (!empty($smsSettings['user_phone_field']) && !empty($smsSettings['user_message'])) {
            $phoneField = $smsSettings['user_phone_field'];
            $userPhone = $data[$phoneField] ?? null;

            if ($userPhone) {
                $message = $this->parseTemplate($smsSettings['user_message'], $data);
                $this->send($userPhone, $message, [
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                ]);
            }
        }
    }

    /**
     * Parse message template with submission data
     */
    private function parseTemplate(string $template, array $data): string
    {
        // Replace {{field_name}} with values
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn($matches) => $data[$matches[1]] ?? '',
            $template
        );
    }

    /**
     * Log SMS to database
     */
    private function logSMS(string $to, string $message, string $provider, array $options = []): int
    {
        global $wpdb;

        $wpdb->insert(
            $this->tableSMSLogs,
            [
                'provider' => $provider,
                'phone_number' => $to,
                'message' => $message,
                'status' => 'pending',
                'form_id' => $options['form_id'] ?? null,
                'submission_id' => $options['submission_id'] ?? null,
                'user_id' => get_current_user_id() ?: null,
                'segments' => $this->calculateSegments($message),
                'metadata' => json_encode($options),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Update SMS log with result
     */
    private function updateLog(int $logId, array $result): void
    {
        global $wpdb;

        $status = $result['success'] ? 'sent' : 'failed';
        if (isset($result['status'])) {
            $status = match ($result['status']) {
                'delivered' => 'delivered',
                'undelivered', 'failed' => 'undelivered',
                default => $result['success'] ? 'sent' : 'failed',
            };
        }

        $wpdb->update(
            $this->tableSMSLogs,
            [
                'message_id' => $result['message_id'] ?? null,
                'status' => $status,
                'error_message' => $result['error'] ?? null,
                'cost' => $result['message_price'] ?? null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $logId],
            ['%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    /**
     * Calculate message segments
     */
    private function calculateSegments(string $message): int
    {
        $length = strlen($message);

        // GSM-7 encoding: 160 chars per segment, 153 for multipart
        // Unicode: 70 chars per segment, 67 for multipart
        $isUnicode = preg_match('/[^\x00-\x7F]/', $message);

        if ($isUnicode) {
            $perSegment = $length > 70 ? 67 : 70;
        } else {
            $perSegment = $length > 160 ? 153 : 160;
        }

        return (int) ceil($length / $perSegment);
    }

    /**
     * Get SMS logs
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['provider'])) {
            $where[] = 'provider = %s';
            $params[] = $filters['provider'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['phone'])) {
            $where[] = 'phone_number LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['phone']) . '%';
        }

        if (!empty($filters['form_id'])) {
            $where[] = 'form_id = %d';
            $params[] = $filters['form_id'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableSMSLogs}
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get statistics
     */
    public function getStatistics(string $period = 'day'): array
    {
        global $wpdb;

        $intervals = [
            'hour' => 'INTERVAL 1 HOUR',
            'day' => 'INTERVAL 1 DAY',
            'week' => 'INTERVAL 1 WEEK',
            'month' => 'INTERVAL 1 MONTH',
        ];

        $interval = $intervals[$period] ?? $intervals['day'];

        return [
            'total' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableSMSLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'sent' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableSMSLogs}
                WHERE status IN ('sent', 'delivered')
                AND created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'failed' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableSMSLogs}
                WHERE status IN ('failed', 'undelivered')
                AND created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'total_cost' => (float) $wpdb->get_var(
                "SELECT COALESCE(SUM(cost), 0) FROM {$this->tableSMSLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'total_segments' => (int) $wpdb->get_var(
                "SELECT COALESCE(SUM(segments), 0) FROM {$this->tableSMSLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})"
            ),
        ];
    }
}
