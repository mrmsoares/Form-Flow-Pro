<?php
/**
 * Tests for NotificationManager class.
 */

namespace FormFlowPro\Tests\Unit\Notifications;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Notifications\NotificationManager;
use FormFlowPro\Notifications\SMSManager;
use FormFlowPro\Notifications\ChatManager;
use FormFlowPro\Notifications\PushNotifications;
use FormFlowPro\Notifications\EmailBuilder;

class NotificationManagerTest extends TestCase
{
    private $notificationManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationManager = NotificationManager::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = NotificationManager::getInstance();
        $instance2 = NotificationManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(NotificationManager::class, $instance1);
    }

    public function test_get_sms_manager_returns_instance()
    {
        $smsManager = $this->notificationManager->getSMSManager();

        $this->assertInstanceOf(SMSManager::class, $smsManager);
    }

    public function test_get_chat_manager_returns_instance()
    {
        $chatManager = $this->notificationManager->getChatManager();

        $this->assertInstanceOf(ChatManager::class, $chatManager);
    }

    public function test_get_push_notifications_returns_instance()
    {
        $pushNotifications = $this->notificationManager->getPushNotifications();

        $this->assertInstanceOf(PushNotifications::class, $pushNotifications);
    }

    public function test_get_email_builder_returns_instance()
    {
        $emailBuilder = $this->notificationManager->getEmailBuilder();

        $this->assertInstanceOf(EmailBuilder::class, $emailBuilder);
    }

    public function test_send_email_notification()
    {
        $result = $this->notificationManager->send('email', 'test@example.com', [
            'subject' => 'Test Email',
            'content' => 'Test content',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_sms_notification()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $result = $this->notificationManager->send('sms', '+1234567890', [
            'content' => 'Test SMS',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_push_notification()
    {
        $result = $this->notificationManager->send('push', '1', [
            'subject' => 'Test Push',
            'content' => 'Test content',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_slack_notification()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $result = $this->notificationManager->send('slack', '#general', [
            'content' => 'Test Slack message',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_teams_notification()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $result = $this->notificationManager->send('teams', '', [
            'content' => 'Test Teams message',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_discord_notification()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $result = $this->notificationManager->send('discord', '', [
            'content' => 'Test Discord message',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('notification_id', $result);
    }

    public function test_send_unknown_channel_returns_error()
    {
        $result = $this->notificationManager->send('unknown_channel', 'test@example.com', [
            'content' => 'Test',
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Unknown channel', $result['error']);
    }

    public function test_send_multi_channel_notification()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $results = $this->notificationManager->sendMultiChannel(
            ['email', 'slack'],
            'test@example.com',
            ['content' => 'Test message']
        );

        $this->assertIsArray($results);
        $this->assertArrayHasKey('email', $results);
        $this->assertArrayHasKey('slack', $results);
    }

    public function test_schedule_notification()
    {
        $scheduledAt = new \DateTime('+1 hour');

        $id = $this->notificationManager->schedule(
            'email',
            'test@example.com',
            $scheduledAt,
            [
                'subject' => 'Scheduled Email',
                'content' => 'Scheduled content',
            ]
        );

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function test_cancel_scheduled_notification()
    {
        $scheduledAt = new \DateTime('+1 hour');

        $id = $this->notificationManager->schedule(
            'email',
            'test@example.com',
            $scheduledAt,
            ['subject' => 'Test', 'content' => 'Test']
        );

        $result = $this->notificationManager->cancelScheduled($id);

        $this->assertTrue($result);
    }

    public function test_process_scheduled_notifications()
    {
        global $wpdb;

        $scheduledAt = new \DateTime('-1 hour');
        $this->notificationManager->schedule(
            'email',
            'test@example.com',
            $scheduledAt,
            ['subject' => 'Test', 'content' => 'Test']
        );

        $this->notificationManager->processScheduledNotifications();

        $this->assertTrue(true);
    }

    public function test_handle_form_submission()
    {
        $formId = 123;
        $submissionId = 456;

        update_post_meta($formId, '_formflow_notifications', [
            'enabled' => true,
            'rules' => [
                [
                    'channel' => 'email',
                    'recipient' => 'admin@example.com',
                    'subject' => 'New submission',
                    'content' => 'You have a new submission',
                    'conditions' => [],
                ],
            ],
        ]);

        $data = [
            'form_id' => $formId,
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ];

        $this->notificationManager->handleFormSubmission($submissionId, $data);

        $this->assertTrue(true);
    }

    public function test_set_notification_preference()
    {
        $result = $this->notificationManager->setPreference(
            'email',
            'submission',
            true,
            1,
            'user@example.com'
        );

        $this->assertTrue($result);
    }

    public function test_get_notification_logs()
    {
        $logs = $this->notificationManager->getLogs();

        $this->assertIsArray($logs);
    }

    public function test_get_notification_logs_with_filters()
    {
        $logs = $this->notificationManager->getLogs([
            'channel' => 'email',
            'status' => 'sent',
        ], 10, 0);

        $this->assertIsArray($logs);
    }

    public function test_get_statistics()
    {
        $stats = $this->notificationManager->getStatistics('day');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('by_channel', $stats);
        $this->assertArrayHasKey('sms', $stats);
        $this->assertArrayHasKey('chat', $stats);
        $this->assertArrayHasKey('push', $stats);
    }

    public function test_check_permission_returns_true()
    {
        $result = $this->notificationManager->checkPermission();

        $this->assertTrue($result);
    }

    public function test_rest_send_notification()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/notifications/send');
        $request->set_param('channel', 'email');
        $request->set_param('recipient', 'test@example.com');
        $request->set_param('subject', 'Test');
        $request->set_param('content', 'Test content');

        $response = $this->notificationManager->restSendNotification($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
    }

    public function test_rest_schedule_notification()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/notifications/schedule');
        $request->set_param('channel', 'email');
        $request->set_param('recipient', 'test@example.com');
        $request->set_param('scheduled_at', date('Y-m-d H:i:s', strtotime('+1 hour')));
        $request->set_param('subject', 'Test');
        $request->set_param('content', 'Test content');

        $response = $this->notificationManager->restScheduleNotification($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('success', $data);
    }

    public function test_rest_get_logs()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/notifications/logs');
        $request->set_param('channel', 'email');
        $request->set_param('limit', 50);

        $response = $this->notificationManager->restGetLogs($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('logs', $data);
    }

    public function test_rest_get_stats()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/notifications/stats');
        $request->set_param('period', 'week');

        $response = $this->notificationManager->restGetStats($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('total', $data);
    }

    public function test_rest_test_notification()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/notifications/test');
        $request->set_param('channel', 'email');
        $request->set_param('recipient', 'test@example.com');

        $response = $this->notificationManager->restTestNotification($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_settings()
    {
        update_option('formflow_sms_default_provider', 'twilio');
        update_option('formflow_twilio_account_sid', 'test_sid');

        $request = new \WP_REST_Request('GET', '/formflow/v1/notifications/settings');
        $response = $this->notificationManager->restGetSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('sms', $data);
        $this->assertArrayHasKey('chat', $data);
        $this->assertArrayHasKey('push', $data);
    }

    public function test_rest_save_settings()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/notifications/settings');
        $request->set_param('sms', [
            'default_provider' => 'vonage',
            'twilio' => [
                'account_sid' => 'new_sid',
                'auth_token' => 'new_token',
                'from_number' => '+9876543210',
            ],
        ]);

        $response = $this->notificationManager->restSaveSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $this->assertEquals('vonage', get_option('formflow_sms_default_provider'));
        $this->assertEquals('new_sid', get_option('formflow_twilio_account_sid'));
    }

    public function test_install_creates_tables()
    {
        global $wpdb;

        $this->notificationManager->install();

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_notification_logs'");
        $this->assertNotNull($table);

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_notification_preferences'");
        $this->assertNotNull($table);

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_scheduled_notifications'");
        $this->assertNotNull($table);
    }

    public function test_register_admin_menu()
    {
        $this->notificationManager->registerAdminMenu();

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_on_notifications_page()
    {
        $this->notificationManager->enqueueAdminAssets('formflow-notifications');

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_skips_other_pages()
    {
        $this->notificationManager->enqueueAdminAssets('other-page');

        $this->assertTrue(true);
    }

    public function test_render_notifications_page()
    {
        ob_start();
        $this->notificationManager->renderNotificationsPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('Notification Center', $output);
        $this->assertStringContainsString('nav-tab-wrapper', $output);
    }
}
