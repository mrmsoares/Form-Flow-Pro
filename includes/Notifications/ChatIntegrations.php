<?php

declare(strict_types=1);

namespace FormFlowPro\Notifications;

/**
 * Chat Platform Integrations
 *
 * Integrations for team communication platforms:
 * - Slack (Webhooks, Bot API, Interactive Messages)
 * - Microsoft Teams (Webhooks, Adaptive Cards)
 * - Discord (Webhooks, Rich Embeds)
 *
 * @package FormFlowPro\Notifications
 * @since 2.4.0
 */

interface ChatProviderInterface
{
    public function send(string $channel, string $message, array $options = []): array;
    public function sendCard(string $channel, array $card): array;
    public function isConfigured(): bool;
    public function testConnection(): bool;
}

/**
 * Slack Integration
 */
class SlackIntegration implements ChatProviderInterface
{
    private string $webhookUrl;
    private string $botToken;
    private string $defaultChannel;

    public function __construct()
    {
        $this->webhookUrl = get_option('formflow_slack_webhook_url', '');
        $this->botToken = get_option('formflow_slack_bot_token', '');
        $this->defaultChannel = get_option('formflow_slack_default_channel', '#general');
    }

    public function isConfigured(): bool
    {
        return !empty($this->webhookUrl) || !empty($this->botToken);
    }

    public function testConnection(): bool
    {
        $result = $this->send($this->defaultChannel, 'FormFlow Pro connection test');
        return $result['success'];
    }

    public function send(string $channel, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Slack not configured'];
        }

        // Use Bot API if available, otherwise webhook
        if (!empty($this->botToken)) {
            return $this->sendViaBot($channel, $message, $options);
        }

        return $this->sendViaWebhook($message, $options);
    }

    private function sendViaWebhook(string $message, array $options = []): array
    {
        $payload = [
            'text' => $message,
        ];

        // Rich formatting with blocks
        if (!empty($options['blocks'])) {
            $payload['blocks'] = $options['blocks'];
        }

        // Attachments for legacy formatting
        if (!empty($options['attachments'])) {
            $payload['attachments'] = $options['attachments'];
        }

        // Username and icon
        if (!empty($options['username'])) {
            $payload['username'] = $options['username'];
        }
        if (!empty($options['icon_emoji'])) {
            $payload['icon_emoji'] = $options['icon_emoji'];
        }
        if (!empty($options['icon_url'])) {
            $payload['icon_url'] = $options['icon_url'];
        }

        $response = wp_remote_post($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code === 200 && $body === 'ok',
            'response' => $body,
            'provider' => 'slack',
        ];
    }

    private function sendViaBot(string $channel, string $message, array $options = []): array
    {
        $payload = [
            'channel' => $channel,
            'text' => $message,
        ];

        if (!empty($options['blocks'])) {
            $payload['blocks'] = $options['blocks'];
        }
        if (!empty($options['attachments'])) {
            $payload['attachments'] = $options['attachments'];
        }
        if (!empty($options['thread_ts'])) {
            $payload['thread_ts'] = $options['thread_ts'];
        }
        if (!empty($options['reply_broadcast'])) {
            $payload['reply_broadcast'] = true;
        }

        $response = wp_remote_post('https://slack.com/api/chat.postMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->botToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $body['ok'] ?? false,
            'message_ts' => $body['ts'] ?? null,
            'channel' => $body['channel'] ?? null,
            'error' => $body['error'] ?? null,
            'provider' => 'slack',
        ];
    }

    public function sendCard(string $channel, array $card): array
    {
        // Convert card to Slack Block Kit format
        $blocks = $this->convertToBlocks($card);

        return $this->send($channel, $card['fallback'] ?? 'New notification', [
            'blocks' => $blocks,
        ]);
    }

    /**
     * Create form submission notification
     */
    public function createSubmissionNotification(array $submission, array $form): array
    {
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '📝 New Form Submission',
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Form:*\n{$form['title']}",
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Submitted:*\n" . date('M j, Y g:i A'),
                    ],
                ],
            ],
            ['type' => 'divider'],
        ];

        // Add submission fields
        $fields = [];
        foreach ($submission as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $fields[] = [
                'type' => 'mrkdwn',
                'text' => "*{$key}:*\n{$value}",
            ];

            // Slack limits 10 fields per section
            if (count($fields) === 10) {
                $blocks[] = ['type' => 'section', 'fields' => $fields];
                $fields = [];
            }
        }

        if (!empty($fields)) {
            $blocks[] = ['type' => 'section', 'fields' => $fields];
        }

        // Action buttons
        $blocks[] = [
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => 'View Submission'],
                    'url' => admin_url("admin.php?page=formflow-submissions&id={$submission['id']}"),
                    'style' => 'primary',
                ],
            ],
        ];

        return $blocks;
    }

    private function convertToBlocks(array $card): array
    {
        $blocks = [];

        if (!empty($card['title'])) {
            $blocks[] = [
                'type' => 'header',
                'text' => ['type' => 'plain_text', 'text' => $card['title']],
            ];
        }

        if (!empty($card['description'])) {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => $card['description']],
            ];
        }

        if (!empty($card['fields'])) {
            $fields = [];
            foreach ($card['fields'] as $field) {
                $fields[] = [
                    'type' => 'mrkdwn',
                    'text' => "*{$field['label']}:*\n{$field['value']}",
                ];
            }
            $blocks[] = ['type' => 'section', 'fields' => $fields];
        }

        if (!empty($card['buttons'])) {
            $elements = [];
            foreach ($card['buttons'] as $button) {
                $elements[] = [
                    'type' => 'button',
                    'text' => ['type' => 'plain_text', 'text' => $button['text']],
                    'url' => $button['url'],
                    'style' => $button['style'] ?? 'primary',
                ];
            }
            $blocks[] = ['type' => 'actions', 'elements' => $elements];
        }

        return $blocks;
    }

    /**
     * Upload file to Slack
     */
    public function uploadFile(string $channel, string $filePath, array $options = []): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'error' => 'Bot token required for file uploads'];
        }

        $boundary = wp_generate_password(24, false);

        $body = '';
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"channels\"\r\n\r\n{$channel}\r\n";

        if (!empty($options['filename'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"filename\"\r\n\r\n{$options['filename']}\r\n";
        }

        if (!empty($options['title'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"title\"\r\n\r\n{$options['title']}\r\n";
        }

        if (!empty($options['initial_comment'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"initial_comment\"\r\n\r\n{$options['initial_comment']}\r\n";
        }

        $filename = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://slack.com/api/files.upload', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->botToken,
                'Content-Type' => "multipart/form-data; boundary={$boundary}",
            ],
            'body' => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $body['ok'] ?? false,
            'file' => $body['file'] ?? null,
            'error' => $body['error'] ?? null,
        ];
    }
}

/**
 * Microsoft Teams Integration
 */
class TeamsIntegration implements ChatProviderInterface
{
    private string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = get_option('formflow_teams_webhook_url', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->webhookUrl);
    }

    public function testConnection(): bool
    {
        $result = $this->send('', 'FormFlow Pro connection test');
        return $result['success'];
    }

    public function send(string $channel, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Teams not configured'];
        }

        // Simple message card format
        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => $message,
            'themeColor' => $options['color'] ?? '0076D7',
            'text' => $message,
        ];

        // Title
        if (!empty($options['title'])) {
            $payload['title'] = $options['title'];
        }

        // Sections for more complex messages
        if (!empty($options['sections'])) {
            $payload['sections'] = $options['sections'];
        }

        // Action buttons
        if (!empty($options['actions'])) {
            $payload['potentialAction'] = $options['actions'];
        }

        $response = wp_remote_post($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code === 200,
            'response_code' => $code,
            'provider' => 'teams',
        ];
    }

    public function sendCard(string $channel, array $card): array
    {
        return $this->sendAdaptiveCard($card);
    }

    /**
     * Send Adaptive Card (Teams modern format)
     */
    public function sendAdaptiveCard(array $card): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Teams not configured'];
        }

        $payload = [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => $this->buildAdaptiveCard($card),
                ],
            ],
        ];

        $response = wp_remote_post($this->webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code === 200,
            'response_code' => $code,
            'provider' => 'teams',
        ];
    }

    /**
     * Build Adaptive Card structure
     */
    private function buildAdaptiveCard(array $card): array
    {
        $adaptiveCard = [
            '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
            'type' => 'AdaptiveCard',
            'version' => '1.4',
            'body' => [],
        ];

        // Header
        if (!empty($card['title'])) {
            $adaptiveCard['body'][] = [
                'type' => 'TextBlock',
                'size' => 'Large',
                'weight' => 'Bolder',
                'text' => $card['title'],
                'wrap' => true,
            ];
        }

        // Subtitle/Description
        if (!empty($card['description'])) {
            $adaptiveCard['body'][] = [
                'type' => 'TextBlock',
                'text' => $card['description'],
                'wrap' => true,
            ];
        }

        // Fact set for key-value pairs
        if (!empty($card['fields'])) {
            $facts = [];
            foreach ($card['fields'] as $field) {
                $facts[] = [
                    'title' => $field['label'],
                    'value' => (string) $field['value'],
                ];
            }
            $adaptiveCard['body'][] = [
                'type' => 'FactSet',
                'facts' => $facts,
            ];
        }

        // Action buttons
        if (!empty($card['buttons'])) {
            $adaptiveCard['actions'] = [];
            foreach ($card['buttons'] as $button) {
                $adaptiveCard['actions'][] = [
                    'type' => 'Action.OpenUrl',
                    'title' => $button['text'],
                    'url' => $button['url'],
                ];
            }
        }

        return $adaptiveCard;
    }

    /**
     * Create form submission notification
     */
    public function createSubmissionNotification(array $submission, array $form): array
    {
        return [
            'title' => '📝 New Form Submission',
            'description' => "A new submission was received for **{$form['title']}**",
            'fields' => array_map(fn($key, $value) => [
                'label' => $key,
                'value' => is_array($value) ? implode(', ', $value) : $value,
            ], array_keys($submission), array_values($submission)),
            'buttons' => [
                [
                    'text' => 'View Submission',
                    'url' => admin_url("admin.php?page=formflow-submissions&id={$submission['id']}"),
                ],
            ],
        ];
    }
}

/**
 * Discord Integration
 */
class DiscordIntegration implements ChatProviderInterface
{
    private string $webhookUrl;
    private string $botToken;

    public function __construct()
    {
        $this->webhookUrl = get_option('formflow_discord_webhook_url', '');
        $this->botToken = get_option('formflow_discord_bot_token', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->webhookUrl) || !empty($this->botToken);
    }

    public function testConnection(): bool
    {
        $result = $this->send('', 'FormFlow Pro connection test');
        return $result['success'];
    }

    public function send(string $channel, string $message, array $options = []): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Discord not configured'];
        }

        $payload = [
            'content' => $message,
        ];

        // Username override
        if (!empty($options['username'])) {
            $payload['username'] = $options['username'];
        }

        // Avatar URL
        if (!empty($options['avatar_url'])) {
            $payload['avatar_url'] = $options['avatar_url'];
        }

        // Rich embeds
        if (!empty($options['embeds'])) {
            $payload['embeds'] = $options['embeds'];
        }

        // TTS (text-to-speech)
        if (!empty($options['tts'])) {
            $payload['tts'] = true;
        }

        // Use webhook or bot API
        $url = !empty($this->botToken) && !empty($channel)
            ? "https://discord.com/api/v10/channels/{$channel}/messages"
            : $this->webhookUrl;

        $headers = ['Content-Type' => 'application/json'];
        if (!empty($this->botToken) && !empty($channel)) {
            $headers['Authorization'] = 'Bot ' . $this->botToken;
        }

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $code >= 200 && $code < 300,
            'message_id' => $body['id'] ?? null,
            'response_code' => $code,
            'provider' => 'discord',
        ];
    }

    public function sendCard(string $channel, array $card): array
    {
        $embed = $this->buildEmbed($card);

        return $this->send($channel, '', [
            'embeds' => [$embed],
        ]);
    }

    /**
     * Build Discord embed structure
     */
    private function buildEmbed(array $card): array
    {
        $embed = [
            'type' => 'rich',
            'color' => hexdec(ltrim($card['color'] ?? '5865F2', '#')),
        ];

        if (!empty($card['title'])) {
            $embed['title'] = $card['title'];
        }

        if (!empty($card['description'])) {
            $embed['description'] = $card['description'];
        }

        if (!empty($card['url'])) {
            $embed['url'] = $card['url'];
        }

        if (!empty($card['thumbnail'])) {
            $embed['thumbnail'] = ['url' => $card['thumbnail']];
        }

        if (!empty($card['image'])) {
            $embed['image'] = ['url' => $card['image']];
        }

        // Fields
        if (!empty($card['fields'])) {
            $embed['fields'] = [];
            foreach ($card['fields'] as $field) {
                $embed['fields'][] = [
                    'name' => $field['label'],
                    'value' => (string) $field['value'],
                    'inline' => $field['inline'] ?? true,
                ];
            }
        }

        // Footer
        if (!empty($card['footer'])) {
            $embed['footer'] = [
                'text' => $card['footer'],
            ];
        }

        // Timestamp
        $embed['timestamp'] = date('c');

        // Author
        if (!empty($card['author'])) {
            $embed['author'] = [
                'name' => $card['author']['name'],
                'url' => $card['author']['url'] ?? null,
                'icon_url' => $card['author']['icon'] ?? null,
            ];
        }

        return $embed;
    }

    /**
     * Create form submission notification
     */
    public function createSubmissionNotification(array $submission, array $form): array
    {
        $fields = [];
        foreach ($submission as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $fields[] = [
                'label' => $key,
                'value' => substr((string) $value, 0, 1024), // Discord field value limit
                'inline' => strlen($value) < 50,
            ];
        }

        return [
            'title' => '📝 New Form Submission',
            'description' => "A new submission was received for **{$form['title']}**",
            'color' => '5865F2',
            'fields' => $fields,
            'footer' => 'FormFlow Pro',
            'url' => admin_url("admin.php?page=formflow-submissions&id={$submission['id']}"),
        ];
    }

    /**
     * Send file to Discord
     */
    public function sendFile(string $channel, string $filePath, array $options = []): array
    {
        if (empty($this->botToken)) {
            return ['success' => false, 'error' => 'Bot token required for file uploads'];
        }

        $boundary = wp_generate_password(24, false);

        $body = '';

        // JSON payload
        $payload = [];
        if (!empty($options['content'])) {
            $payload['content'] = $options['content'];
        }
        if (!empty($options['embeds'])) {
            $payload['embeds'] = $options['embeds'];
        }

        if (!empty($payload)) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"payload_json\"\r\n";
            $body .= "Content-Type: application/json\r\n\r\n";
            $body .= json_encode($payload) . "\r\n";
        }

        // File
        $filename = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post(
            "https://discord.com/api/v10/channels/{$channel}/messages",
            [
                'headers' => [
                    'Authorization' => 'Bot ' . $this->botToken,
                    'Content-Type' => "multipart/form-data; boundary={$boundary}",
                ],
                'body' => $body,
                'timeout' => 60,
            ]
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $code >= 200 && $code < 300,
            'message_id' => $body['id'] ?? null,
            'attachments' => $body['attachments'] ?? [],
        ];
    }
}

/**
 * Chat Notifications Manager
 */
class ChatManager
{
    private static ?ChatManager $instance = null;
    private array $providers = [];
    private string $tableChatLogs;

    private function __construct()
    {
        global $wpdb;
        $this->tableChatLogs = $wpdb->prefix . 'formflow_chat_logs';

        $this->registerProviders();
        $this->initHooks();
    }

    public static function getInstance(): ChatManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function registerProviders(): void
    {
        $this->providers['slack'] = new SlackIntegration();
        $this->providers['teams'] = new TeamsIntegration();
        $this->providers['discord'] = new DiscordIntegration();
    }

    private function initHooks(): void
    {
        add_action('formflow_submission_created', [$this, 'triggerChatNotification'], 10, 2);
    }

    /**
     * Create database table
     */
    public function createTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableChatLogs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider VARCHAR(50) NOT NULL,
            channel VARCHAR(255) NULL,
            message_type ENUM('text', 'card', 'file') DEFAULT 'text',
            content TEXT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            error_message TEXT NULL,
            form_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            response_data JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_provider (provider),
            INDEX idx_status (status),
            INDEX idx_form (form_id),
            INDEX idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get provider instance
     */
    public function getProvider(string $name): ?ChatProviderInterface
    {
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
     * Send to multiple platforms
     */
    public function broadcast(string $message, array $options = []): array
    {
        $results = [];
        $providers = $options['providers'] ?? array_keys($this->getConfiguredProviders());

        foreach ($providers as $providerName) {
            $provider = $this->getProvider($providerName);
            if ($provider && $provider->isConfigured()) {
                $channel = $options['channels'][$providerName] ?? '';
                $results[$providerName] = $provider->send($channel, $message, $options);
            }
        }

        return $results;
    }

    /**
     * Trigger chat notification on form submission
     */
    public function triggerChatNotification(int $submissionId, array $data): void
    {
        $formId = $data['form_id'] ?? 0;

        // Get form chat notification settings
        $chatSettings = get_post_meta($formId, '_formflow_chat_notifications', true);

        if (empty($chatSettings) || empty($chatSettings['enabled'])) {
            return;
        }

        $form = ['title' => get_the_title($formId), 'id' => $formId];
        $submission = array_merge(['id' => $submissionId], $data);

        // Send to configured platforms
        foreach ($chatSettings['platforms'] ?? [] as $platform => $config) {
            if (empty($config['enabled'])) {
                continue;
            }

            $provider = $this->getProvider($platform);
            if (!$provider || !$provider->isConfigured()) {
                continue;
            }

            // Create notification card
            $card = method_exists($provider, 'createSubmissionNotification')
                ? $provider->createSubmissionNotification($submission, $form)
                : null;

            if ($card) {
                $result = $provider->sendCard($config['channel'] ?? '', $card);
            } else {
                $message = $this->formatSubmissionMessage($submission, $form);
                $result = $provider->send($config['channel'] ?? '', $message);
            }

            // Log the notification
            $this->logNotification($platform, $result, [
                'form_id' => $formId,
                'submission_id' => $submissionId,
            ]);
        }
    }

    /**
     * Format submission as text message
     */
    private function formatSubmissionMessage(array $submission, array $form): string
    {
        $lines = [
            "📝 **New Form Submission**",
            "Form: {$form['title']}",
            "---",
        ];

        foreach ($submission as $key => $value) {
            if ($key === 'id') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $lines[] = "{$key}: {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Log notification
     */
    private function logNotification(string $provider, array $result, array $context = []): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->tableChatLogs,
            [
                'provider' => $provider,
                'message_type' => 'card',
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'form_id' => $context['form_id'] ?? null,
                'submission_id' => $context['submission_id'] ?? null,
                'response_data' => json_encode($result),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Get notification logs
     */
    public function getLogs(array $filters = [], int $limit = 50): array
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

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableChatLogs}
                WHERE {$whereClause}
                ORDER BY created_at DESC
                LIMIT %d",
                ...$params
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get statistics
     */
    public function getStatistics(): array
    {
        global $wpdb;

        return [
            'total_sent' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableChatLogs} WHERE status = 'sent'"
            ),
            'total_failed' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableChatLogs} WHERE status = 'failed'"
            ),
            'by_provider' => $wpdb->get_results(
                "SELECT provider, COUNT(*) as count, status
                FROM {$this->tableChatLogs}
                GROUP BY provider, status",
                ARRAY_A
            ),
        ];
    }
}
