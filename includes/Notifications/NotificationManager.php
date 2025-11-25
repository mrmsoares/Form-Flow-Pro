<?php

declare(strict_types=1);

namespace FormFlowPro\Notifications;

/**
 * Notification Manager
 *
 * Central hub for all notification channels:
 * - SMS (Twilio, Vonage, AWS SNS)
 * - Chat (Slack, Teams, Discord)
 * - Push Notifications (Web Push)
 * - Email (Template Builder)
 *
 * Features:
 * - Unified notification API
 * - Scheduling and queuing
 * - Template management
 * - Analytics and logging
 * - Preference management
 *
 * @package FormFlowPro\Notifications
 * @since 2.4.0
 */
class NotificationManager
{
    private static ?NotificationManager $instance = null;

    private SMSManager $smsManager;
    private ChatManager $chatManager;
    private PushNotifications $pushNotifications;
    private EmailBuilder $emailBuilder;

    private string $tableNotificationLogs;
    private string $tableNotificationPreferences;
    private string $tableScheduledNotifications;

    private function __construct()
    {
        global $wpdb;
        $this->tableNotificationLogs = $wpdb->prefix . 'formflow_notification_logs';
        $this->tableNotificationPreferences = $wpdb->prefix . 'formflow_notification_preferences';
        $this->tableScheduledNotifications = $wpdb->prefix . 'formflow_scheduled_notifications';

        $this->initComponents();
        $this->initHooks();
    }

    public static function getInstance(): NotificationManager
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initComponents(): void
    {
        $this->smsManager = SMSManager::getInstance();
        $this->chatManager = ChatManager::getInstance();
        $this->pushNotifications = PushNotifications::getInstance();
        $this->emailBuilder = EmailBuilder::getInstance();
    }

    private function initHooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Scheduled notifications cron
        add_action('formflow_process_scheduled_notifications', [$this, 'processScheduledNotifications']);
        if (!wp_next_scheduled('formflow_process_scheduled_notifications')) {
            wp_schedule_event(time(), 'every_minute', 'formflow_process_scheduled_notifications');
        }

        // Register custom cron schedule
        add_filter('cron_schedules', function ($schedules) {
            $schedules['every_minute'] = [
                'interval' => 60,
                'display' => __('Every Minute', 'formflow-pro'),
            ];
            return $schedules;
        });

        // Form submission trigger
        add_action('formflow_submission_created', [$this, 'handleFormSubmission'], 5, 2);
    }

    /**
     * Install database tables
     */
    public function install(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Notification logs
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableNotificationLogs} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            notification_id VARCHAR(64) NOT NULL UNIQUE,
            channel ENUM('email', 'sms', 'push', 'slack', 'teams', 'discord') NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            content TEXT NULL,
            template_id BIGINT UNSIGNED NULL,
            status ENUM('pending', 'sent', 'delivered', 'failed', 'bounced') DEFAULT 'pending',
            error_message TEXT NULL,
            metadata JSON NULL,
            form_id BIGINT UNSIGNED NULL,
            submission_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NULL,
            sent_at DATETIME NULL,
            delivered_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_channel (channel),
            INDEX idx_status (status),
            INDEX idx_recipient (recipient),
            INDEX idx_form (form_id),
            INDEX idx_created (created_at)
        ) {$charset};";

        // User notification preferences
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableNotificationPreferences} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(255) NULL,
            channel VARCHAR(50) NOT NULL,
            notification_type VARCHAR(100) NOT NULL,
            is_enabled TINYINT(1) DEFAULT 1,
            settings JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_pref (user_id, email, channel, notification_type),
            INDEX idx_user (user_id),
            INDEX idx_email (email),
            INDEX idx_channel (channel)
        ) {$charset};";

        // Scheduled notifications
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->tableScheduledNotifications} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            channel VARCHAR(50) NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            content TEXT NULL,
            template_slug VARCHAR(255) NULL,
            variables JSON NULL,
            scheduled_at DATETIME NOT NULL,
            status ENUM('scheduled', 'processing', 'sent', 'failed', 'cancelled') DEFAULT 'scheduled',
            attempts INT UNSIGNED DEFAULT 0,
            max_attempts INT UNSIGNED DEFAULT 3,
            last_error TEXT NULL,
            metadata JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_scheduled (scheduled_at),
            INDEX idx_channel (channel)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        // Create component tables
        $this->smsManager->createTable();
        $this->chatManager->createTable();
        $this->pushNotifications->createTable();
        $this->emailBuilder->createTable();

        update_option('formflow_notifications_version', '2.4.0');
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Notifications', 'formflow-pro'),
            __('Notifications', 'formflow-pro'),
            'manage_options',
            'formflow-notifications',
            [$this, 'renderNotificationsPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'formflow-notifications') === false) {
            return;
        }

        wp_enqueue_style(
            'formflow-notifications',
            FORMFLOW_URL . 'assets/css/admin-notifications.css',
            [],
            FORMFLOW_VERSION
        );

        wp_enqueue_script(
            'formflow-notifications',
            FORMFLOW_URL . 'assets/js/admin-notifications.js',
            ['jquery', 'wp-api-fetch'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('formflow-notifications', 'formflowNotifications', [
            'nonce' => wp_create_nonce('formflow_notifications_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formflow/v1/notifications'),
            'i18n' => [
                'testSent' => __('Test notification sent!', 'formflow-pro'),
                'error' => __('An error occurred', 'formflow-pro'),
                'saved' => __('Settings saved', 'formflow-pro'),
            ],
        ]);
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        $namespace = 'formflow/v1';

        // Send notification
        register_rest_route($namespace, '/notifications/send', [
            'methods' => 'POST',
            'callback' => [$this, 'restSendNotification'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Schedule notification
        register_rest_route($namespace, '/notifications/schedule', [
            'methods' => 'POST',
            'callback' => [$this, 'restScheduleNotification'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get logs
        register_rest_route($namespace, '/notifications/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetLogs'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Get statistics
        register_rest_route($namespace, '/notifications/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStats'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Test notification
        register_rest_route($namespace, '/notifications/test', [
            'methods' => 'POST',
            'callback' => [$this, 'restTestNotification'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // Settings
        register_rest_route($namespace, '/notifications/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restSaveSettings'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Send notification through any channel
     */
    public function send(string $channel, string $recipient, array $options = []): array
    {
        $notificationId = $this->generateNotificationId();

        // Check user preferences
        if (!$this->shouldSendNotification($recipient, $channel, $options['type'] ?? 'general')) {
            return [
                'success' => false,
                'error' => 'User has disabled this notification type',
                'notification_id' => $notificationId,
            ];
        }

        $result = match ($channel) {
            'email' => $this->sendEmail($recipient, $options),
            'sms' => $this->sendSMS($recipient, $options),
            'push' => $this->sendPush($recipient, $options),
            'slack' => $this->sendSlack($recipient, $options),
            'teams' => $this->sendTeams($recipient, $options),
            'discord' => $this->sendDiscord($recipient, $options),
            default => ['success' => false, 'error' => 'Unknown channel'],
        };

        // Log notification
        $this->logNotification($notificationId, $channel, $recipient, $options, $result);

        $result['notification_id'] = $notificationId;

        return $result;
    }

    /**
     * Send to multiple channels
     */
    public function sendMultiChannel(array $channels, string $recipient, array $options = []): array
    {
        $results = [];

        foreach ($channels as $channel) {
            $results[$channel] = $this->send($channel, $recipient, $options);
        }

        return $results;
    }

    /**
     * Schedule notification for later
     */
    public function schedule(
        string $channel,
        string $recipient,
        \DateTime $scheduledAt,
        array $options = []
    ): int {
        global $wpdb;

        $wpdb->insert(
            $this->tableScheduledNotifications,
            [
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $options['subject'] ?? null,
                'content' => $options['content'] ?? null,
                'template_slug' => $options['template'] ?? null,
                'variables' => json_encode($options['variables'] ?? []),
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'metadata' => json_encode($options['metadata'] ?? []),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Cancel scheduled notification
     */
    public function cancelScheduled(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->update(
            $this->tableScheduledNotifications,
            ['status' => 'cancelled'],
            ['id' => $id, 'status' => 'scheduled'],
            ['%s'],
            ['%d', '%s']
        );
    }

    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications(): void
    {
        global $wpdb;

        $notifications = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableScheduledNotifications}
                WHERE status = 'scheduled'
                AND scheduled_at <= %s
                AND attempts < max_attempts
                LIMIT 50",
                current_time('mysql')
            ),
            ARRAY_A
        );

        foreach ($notifications as $notification) {
            // Mark as processing
            $wpdb->update(
                $this->tableScheduledNotifications,
                ['status' => 'processing', 'attempts' => $notification['attempts'] + 1],
                ['id' => $notification['id']],
                ['%s', '%d'],
                ['%d']
            );

            $options = [
                'subject' => $notification['subject'],
                'content' => $notification['content'],
                'template' => $notification['template_slug'],
                'variables' => json_decode($notification['variables'], true),
            ];

            $result = $this->send($notification['channel'], $notification['recipient'], $options);

            $wpdb->update(
                $this->tableScheduledNotifications,
                [
                    'status' => $result['success'] ? 'sent' : 'failed',
                    'last_error' => $result['error'] ?? null,
                ],
                ['id' => $notification['id']],
                ['%s', '%s'],
                ['%d']
            );
        }
    }

    /**
     * Send email
     */
    private function sendEmail(string $to, array $options): array
    {
        if (!empty($options['template'])) {
            $sent = $this->emailBuilder->sendWithTemplate(
                $options['template'],
                $to,
                $options['variables'] ?? []
            );
        } else {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $sent = wp_mail(
                $to,
                $options['subject'] ?? '',
                $options['content'] ?? '',
                $headers
            );
        }

        return ['success' => $sent];
    }

    /**
     * Send SMS
     */
    private function sendSMS(string $to, array $options): array
    {
        return $this->smsManager->send(
            $to,
            $options['content'] ?? '',
            $options
        );
    }

    /**
     * Send push notification
     */
    private function sendPush(string $userId, array $options): array
    {
        return $this->pushNotifications->sendToUser((int) $userId, [
            'title' => $options['subject'] ?? '',
            'body' => $options['content'] ?? '',
            'icon' => $options['icon'] ?? null,
            'data' => $options['data'] ?? [],
        ]);
    }

    /**
     * Send Slack notification
     */
    private function sendSlack(string $channel, array $options): array
    {
        $slack = $this->chatManager->getProvider('slack');
        return $slack->send($channel, $options['content'] ?? '', $options);
    }

    /**
     * Send Teams notification
     */
    private function sendTeams(string $channel, array $options): array
    {
        $teams = $this->chatManager->getProvider('teams');
        return $teams->send($channel, $options['content'] ?? '', $options);
    }

    /**
     * Send Discord notification
     */
    private function sendDiscord(string $channel, array $options): array
    {
        $discord = $this->chatManager->getProvider('discord');
        return $discord->send($channel, $options['content'] ?? '', $options);
    }

    /**
     * Handle form submission
     */
    public function handleFormSubmission(int $submissionId, array $data): void
    {
        $formId = $data['form_id'] ?? 0;

        // Get form notification settings
        $settings = get_post_meta($formId, '_formflow_notifications', true);

        if (empty($settings) || empty($settings['enabled'])) {
            return;
        }

        $form = get_post($formId);
        $variables = array_merge($data, [
            'form_name' => $form->post_title,
            'form_id' => $formId,
            'submission_id' => $submissionId,
            'submission_url' => admin_url("admin.php?page=formflow-submissions&id={$submissionId}"),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'submission_date' => current_time('F j, Y g:i A'),
        ]);

        // Process each notification rule
        foreach ($settings['rules'] ?? [] as $rule) {
            if (!$this->evaluateConditions($rule['conditions'] ?? [], $data)) {
                continue;
            }

            $this->send(
                $rule['channel'],
                $this->parseRecipient($rule['recipient'], $data),
                [
                    'subject' => $this->parseTemplate($rule['subject'] ?? '', $variables),
                    'content' => $this->parseTemplate($rule['content'] ?? '', $variables),
                    'template' => $rule['template'] ?? null,
                    'variables' => $variables,
                    'form_id' => $formId,
                    'submission_id' => $submissionId,
                ]
            );
        }
    }

    /**
     * Evaluate notification conditions
     */
    private function evaluateConditions(array $conditions, array $data): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';
            $dataValue = $data[$field] ?? '';

            $result = match ($operator) {
                'equals' => $dataValue == $value,
                'not_equals' => $dataValue != $value,
                'contains' => stripos($dataValue, $value) !== false,
                'not_contains' => stripos($dataValue, $value) === false,
                'is_empty' => empty($dataValue),
                'not_empty' => !empty($dataValue),
                'greater_than' => (float) $dataValue > (float) $value,
                'less_than' => (float) $dataValue < (float) $value,
                default => true,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse recipient field
     */
    private function parseRecipient(string $recipient, array $data): string
    {
        // Check if it's a field reference
        if (preg_match('/^\{\{(\w+)\}\}$/', $recipient, $matches)) {
            return $data[$matches[1]] ?? $recipient;
        }

        return $recipient;
    }

    /**
     * Parse template variables
     */
    private function parseTemplate(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn($matches) => $variables[$matches[1]] ?? '',
            $template
        );
    }

    /**
     * Log notification
     */
    private function logNotification(
        string $notificationId,
        string $channel,
        string $recipient,
        array $options,
        array $result
    ): void {
        global $wpdb;

        $wpdb->insert(
            $this->tableNotificationLogs,
            [
                'notification_id' => $notificationId,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $options['subject'] ?? null,
                'content' => $options['content'] ?? null,
                'template_id' => $options['template_id'] ?? null,
                'status' => $result['success'] ? 'sent' : 'failed',
                'error_message' => $result['error'] ?? null,
                'metadata' => json_encode($options['metadata'] ?? []),
                'form_id' => $options['form_id'] ?? null,
                'submission_id' => $options['submission_id'] ?? null,
                'user_id' => get_current_user_id() ?: null,
                'sent_at' => $result['success'] ? current_time('mysql') : null,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );
    }

    /**
     * Check if notification should be sent based on preferences
     */
    private function shouldSendNotification(string $recipient, string $channel, string $type): bool
    {
        global $wpdb;

        $preference = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT is_enabled FROM {$this->tableNotificationPreferences}
                WHERE (email = %s OR user_id = %d)
                AND channel = %s AND notification_type = %s",
                $recipient,
                get_current_user_id(),
                $channel,
                $type
            )
        );

        // Default to enabled if no preference set
        return $preference ? (bool) $preference->is_enabled : true;
    }

    /**
     * Set user notification preference
     */
    public function setPreference(
        string $channel,
        string $type,
        bool $enabled,
        ?int $userId = null,
        ?string $email = null
    ): bool {
        global $wpdb;

        return (bool) $wpdb->replace(
            $this->tableNotificationPreferences,
            [
                'user_id' => $userId,
                'email' => $email,
                'channel' => $channel,
                'notification_type' => $type,
                'is_enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Get notification logs
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['channel'])) {
            $where[] = 'channel = %s';
            $params[] = $filters['channel'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $params[] = $filters['status'];
        }

        if (!empty($filters['form_id'])) {
            $where[] = 'form_id = %d';
            $params[] = $filters['form_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableNotificationLogs}
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
                "SELECT COUNT(*) FROM {$this->tableNotificationLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'sent' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableNotificationLogs}
                WHERE status = 'sent' AND created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'failed' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->tableNotificationLogs}
                WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), {$interval})"
            ),
            'by_channel' => $wpdb->get_results(
                "SELECT channel, COUNT(*) as count, status
                FROM {$this->tableNotificationLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})
                GROUP BY channel, status",
                ARRAY_A
            ),
            'timeline' => $wpdb->get_results(
                "SELECT DATE(created_at) as date, channel, COUNT(*) as count
                FROM {$this->tableNotificationLogs}
                WHERE created_at >= DATE_SUB(NOW(), {$interval})
                GROUP BY DATE(created_at), channel
                ORDER BY date ASC",
                ARRAY_A
            ),
            'sms' => $this->smsManager->getStatistics($period),
            'chat' => $this->chatManager->getStatistics(),
            'push' => $this->pushNotifications->getStatistics(),
        ];
    }

    /**
     * REST: Send notification
     */
    public function restSendNotification(\WP_REST_Request $request): \WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $recipient = $request->get_param('recipient');
        $options = $request->get_params();

        $result = $this->send($channel, $recipient, $options);

        return new \WP_REST_Response($result);
    }

    /**
     * REST: Schedule notification
     */
    public function restScheduleNotification(\WP_REST_Request $request): \WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $recipient = $request->get_param('recipient');
        $scheduledAt = new \DateTime($request->get_param('scheduled_at'));
        $options = $request->get_params();

        $id = $this->schedule($channel, $recipient, $scheduledAt, $options);

        return new \WP_REST_Response(['id' => $id, 'success' => (bool) $id]);
    }

    /**
     * REST: Get logs
     */
    public function restGetLogs(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [
            'channel' => $request->get_param('channel'),
            'status' => $request->get_param('status'),
            'form_id' => $request->get_param('form_id'),
            'date_from' => $request->get_param('date_from'),
            'date_to' => $request->get_param('date_to'),
        ];

        $limit = (int) ($request->get_param('limit') ?? 50);
        $offset = (int) ($request->get_param('offset') ?? 0);

        return new \WP_REST_Response([
            'logs' => $this->getLogs(array_filter($filters), $limit, $offset),
        ]);
    }

    /**
     * REST: Get statistics
     */
    public function restGetStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $period = $request->get_param('period') ?? 'day';

        return new \WP_REST_Response($this->getStatistics($period));
    }

    /**
     * REST: Test notification
     */
    public function restTestNotification(\WP_REST_Request $request): \WP_REST_Response
    {
        $channel = $request->get_param('channel');
        $recipient = $request->get_param('recipient');

        $testOptions = [
            'subject' => 'FormFlow Pro Test Notification',
            'content' => 'This is a test notification from FormFlow Pro. If you receive this, your notification settings are working correctly!',
        ];

        $result = $this->send($channel, $recipient, $testOptions);

        return new \WP_REST_Response($result);
    }

    /**
     * REST: Get settings
     */
    public function restGetSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'sms' => [
                'default_provider' => get_option('formflow_sms_default_provider', 'twilio'),
                'twilio' => [
                    'account_sid' => get_option('formflow_twilio_account_sid', ''),
                    'from_number' => get_option('formflow_twilio_from_number', ''),
                ],
                'vonage' => [
                    'api_key' => get_option('formflow_vonage_api_key', ''),
                    'from_number' => get_option('formflow_vonage_from_number', ''),
                ],
            ],
            'chat' => [
                'slack_webhook' => get_option('formflow_slack_webhook_url', ''),
                'teams_webhook' => get_option('formflow_teams_webhook_url', ''),
                'discord_webhook' => get_option('formflow_discord_webhook_url', ''),
            ],
            'push' => [
                'enabled' => get_option('formflow_push_enabled', false),
                'vapid_public_key' => get_option('formflow_vapid_public_key', ''),
            ],
        ]);
    }

    /**
     * REST: Save settings
     */
    public function restSaveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $settings = $request->get_params();

        // SMS settings
        if (isset($settings['sms'])) {
            if (isset($settings['sms']['default_provider'])) {
                update_option('formflow_sms_default_provider', $settings['sms']['default_provider']);
            }
            if (isset($settings['sms']['twilio'])) {
                update_option('formflow_twilio_account_sid', $settings['sms']['twilio']['account_sid'] ?? '');
                update_option('formflow_twilio_auth_token', $settings['sms']['twilio']['auth_token'] ?? '');
                update_option('formflow_twilio_from_number', $settings['sms']['twilio']['from_number'] ?? '');
            }
            if (isset($settings['sms']['vonage'])) {
                update_option('formflow_vonage_api_key', $settings['sms']['vonage']['api_key'] ?? '');
                update_option('formflow_vonage_api_secret', $settings['sms']['vonage']['api_secret'] ?? '');
                update_option('formflow_vonage_from_number', $settings['sms']['vonage']['from_number'] ?? '');
            }
        }

        // Chat settings
        if (isset($settings['chat'])) {
            update_option('formflow_slack_webhook_url', $settings['chat']['slack_webhook'] ?? '');
            update_option('formflow_teams_webhook_url', $settings['chat']['teams_webhook'] ?? '');
            update_option('formflow_discord_webhook_url', $settings['chat']['discord_webhook'] ?? '');
        }

        // Push settings
        if (isset($settings['push'])) {
            update_option('formflow_push_enabled', !empty($settings['push']['enabled']));
        }

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Generate notification ID
     */
    private function generateNotificationId(): string
    {
        return 'NTF-' . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Get component instances
     */
    public function getSMSManager(): SMSManager
    {
        return $this->smsManager;
    }

    public function getChatManager(): ChatManager
    {
        return $this->chatManager;
    }

    public function getPushNotifications(): PushNotifications
    {
        return $this->pushNotifications;
    }

    public function getEmailBuilder(): EmailBuilder
    {
        return $this->emailBuilder;
    }

    /**
     * Render notifications page
     */
    public function renderNotificationsPage(): void
    {
        $activeTab = sanitize_text_field($_GET['tab'] ?? 'overview');
        ?>
        <div class="wrap formflow-notifications-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-email-alt"></span>
                <?php esc_html_e('Notification Center', 'formflow-pro'); ?>
            </h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=formflow-notifications&tab=overview"
                   class="nav-tab <?php echo $activeTab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Overview', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-notifications&tab=sms"
                   class="nav-tab <?php echo $activeTab === 'sms' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('SMS', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-notifications&tab=chat"
                   class="nav-tab <?php echo $activeTab === 'chat' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Chat', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-notifications&tab=push"
                   class="nav-tab <?php echo $activeTab === 'push' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Push', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-email-builder"
                   class="nav-tab">
                    <?php esc_html_e('Email Builder', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-notifications&tab=logs"
                   class="nav-tab <?php echo $activeTab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Logs', 'formflow-pro'); ?>
                </a>
            </nav>

            <div class="tab-content" id="formflow-notifications-content">
                <!-- Content loaded via JavaScript -->
            </div>
        </div>
        <?php
    }
}
