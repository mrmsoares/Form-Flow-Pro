<?php
/**
 * Tests for Chat Integrations (Slack, Teams, Discord).
 */

namespace FormFlowPro\Tests\Unit\Notifications;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Notifications\ChatManager;
use FormFlowPro\Notifications\SlackIntegration;
use FormFlowPro\Notifications\TeamsIntegration;
use FormFlowPro\Notifications\DiscordIntegration;

class ChatIntegrationsTest extends TestCase
{
    private $chatManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chatManager = ChatManager::getInstance();
    }

    public function test_chat_manager_get_instance_returns_singleton()
    {
        $instance1 = ChatManager::getInstance();
        $instance2 = ChatManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ChatManager::class, $instance1);
    }

    public function test_get_provider_returns_slack()
    {
        $provider = $this->chatManager->getProvider('slack');

        $this->assertInstanceOf(SlackIntegration::class, $provider);
    }

    public function test_get_provider_returns_teams()
    {
        $provider = $this->chatManager->getProvider('teams');

        $this->assertInstanceOf(TeamsIntegration::class, $provider);
    }

    public function test_get_provider_returns_discord()
    {
        $provider = $this->chatManager->getProvider('discord');

        $this->assertInstanceOf(DiscordIntegration::class, $provider);
    }

    public function test_get_provider_returns_null_for_invalid()
    {
        $provider = $this->chatManager->getProvider('invalid_provider');

        $this->assertNull($provider);
    }

    public function test_get_configured_providers()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $providers = $this->chatManager->getConfiguredProviders();

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('slack', $providers);
    }

    public function test_broadcast_to_multiple_platforms()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $results = $this->chatManager->broadcast('Test broadcast message', [
            'providers' => ['slack', 'teams'],
        ]);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('slack', $results);
        $this->assertArrayHasKey('teams', $results);
    }

    public function test_trigger_chat_notification_on_form_submission()
    {
        $formId = 123;
        $submissionId = 456;

        update_post_meta($formId, '_formflow_chat_notifications', [
            'enabled' => true,
            'platforms' => [
                'slack' => [
                    'enabled' => true,
                    'channel' => '#general',
                ],
            ],
        ]);

        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $data = [
            'form_id' => $formId,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $this->chatManager->triggerChatNotification($submissionId, $data);

        $this->assertTrue(true);
    }

    public function test_get_chat_logs()
    {
        $logs = $this->chatManager->getLogs();

        $this->assertIsArray($logs);
    }

    public function test_get_chat_logs_with_filters()
    {
        $logs = $this->chatManager->getLogs([
            'provider' => 'slack',
            'status' => 'sent',
        ], 10);

        $this->assertIsArray($logs);
    }

    public function test_get_chat_statistics()
    {
        $stats = $this->chatManager->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_sent', $stats);
        $this->assertArrayHasKey('total_failed', $stats);
        $this->assertArrayHasKey('by_provider', $stats);
    }

    public function test_create_table()
    {
        global $wpdb;

        $this->chatManager->createTable();

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_chat_logs'");
        $this->assertNotNull($table);
    }

    public function test_slack_integration_is_configured_with_webhook()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $slack = new SlackIntegration();

        $this->assertTrue($slack->isConfigured());
    }

    public function test_slack_integration_is_configured_with_bot_token()
    {
        update_option('formflow_slack_bot_token', 'xoxb-test-token');

        $slack = new SlackIntegration();

        $this->assertTrue($slack->isConfigured());
    }

    public function test_slack_integration_is_not_configured()
    {
        delete_option('formflow_slack_webhook_url');
        delete_option('formflow_slack_bot_token');

        $slack = new SlackIntegration();

        $this->assertFalse($slack->isConfigured());
    }

    public function test_slack_send_message_via_webhook()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $slack = new SlackIntegration();
        $result = $slack->send('#general', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('slack', $result['provider']);
    }

    public function test_slack_send_message_via_bot()
    {
        update_option('formflow_slack_bot_token', 'xoxb-test-token');

        $slack = new SlackIntegration();
        $result = $slack->send('#general', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_slack_send_card()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');

        $slack = new SlackIntegration();
        $card = [
            'title' => 'Test Card',
            'description' => 'This is a test card',
            'fallback' => 'Test Card',
            'fields' => [
                ['label' => 'Field 1', 'value' => 'Value 1'],
                ['label' => 'Field 2', 'value' => 'Value 2'],
            ],
        ];

        $result = $slack->sendCard('#general', $card);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_slack_create_submission_notification()
    {
        $slack = new SlackIntegration();

        $submission = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $form = [
            'title' => 'Contact Form',
            'id' => 456,
        ];

        $blocks = $slack->createSubmissionNotification($submission, $form);

        $this->assertIsArray($blocks);
        $this->assertNotEmpty($blocks);
    }

    public function test_slack_test_connection()
    {
        update_option('formflow_slack_webhook_url', 'https://hooks.slack.com/test');
        update_option('formflow_slack_default_channel', '#general');

        $slack = new SlackIntegration();
        $result = $slack->testConnection();

        $this->assertIsBool($result);
    }

    public function test_slack_upload_file()
    {
        update_option('formflow_slack_bot_token', 'xoxb-test-token');

        $slack = new SlackIntegration();

        $testFile = '/tmp/test.txt';
        file_put_contents($testFile, 'Test content');

        $result = $slack->uploadFile('#general', $testFile);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function test_teams_integration_is_configured()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();

        $this->assertTrue($teams->isConfigured());
    }

    public function test_teams_integration_is_not_configured()
    {
        delete_option('formflow_teams_webhook_url');

        $teams = new TeamsIntegration();

        $this->assertFalse($teams->isConfigured());
    }

    public function test_teams_send_message()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();
        $result = $teams->send('', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('teams', $result['provider']);
    }

    public function test_teams_send_with_options()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();
        $result = $teams->send('', 'Test message', [
            'title' => 'Test Title',
            'color' => 'FF0000',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_teams_send_adaptive_card()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();
        $card = [
            'title' => 'Test Card',
            'description' => 'This is a test card',
            'fields' => [
                ['label' => 'Field 1', 'value' => 'Value 1'],
            ],
        ];

        $result = $teams->sendAdaptiveCard($card);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_teams_send_card()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();
        $card = [
            'title' => 'Test Card',
            'description' => 'Description',
        ];

        $result = $teams->sendCard('', $card);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_teams_create_submission_notification()
    {
        $teams = new TeamsIntegration();

        $submission = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $form = [
            'title' => 'Contact Form',
            'id' => 456,
        ];

        $card = $teams->createSubmissionNotification($submission, $form);

        $this->assertIsArray($card);
        $this->assertArrayHasKey('title', $card);
        $this->assertArrayHasKey('description', $card);
        $this->assertArrayHasKey('fields', $card);
        $this->assertArrayHasKey('buttons', $card);
    }

    public function test_teams_test_connection()
    {
        update_option('formflow_teams_webhook_url', 'https://outlook.office.com/webhook/test');

        $teams = new TeamsIntegration();
        $result = $teams->testConnection();

        $this->assertIsBool($result);
    }

    public function test_discord_integration_is_configured_with_webhook()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $discord = new DiscordIntegration();

        $this->assertTrue($discord->isConfigured());
    }

    public function test_discord_integration_is_configured_with_bot_token()
    {
        update_option('formflow_discord_bot_token', 'test_token');

        $discord = new DiscordIntegration();

        $this->assertTrue($discord->isConfigured());
    }

    public function test_discord_integration_is_not_configured()
    {
        delete_option('formflow_discord_webhook_url');
        delete_option('formflow_discord_bot_token');

        $discord = new DiscordIntegration();

        $this->assertFalse($discord->isConfigured());
    }

    public function test_discord_send_message()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $discord = new DiscordIntegration();
        $result = $discord->send('', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('provider', $result);
        $this->assertEquals('discord', $result['provider']);
    }

    public function test_discord_send_with_embeds()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $discord = new DiscordIntegration();
        $result = $discord->send('', 'Test message', [
            'embeds' => [
                [
                    'title' => 'Test Embed',
                    'description' => 'This is a test',
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_discord_send_card()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $discord = new DiscordIntegration();
        $card = [
            'title' => 'Test Card',
            'description' => 'This is a test card',
            'color' => '5865F2',
        ];

        $result = $discord->sendCard('', $card);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_discord_create_submission_notification()
    {
        $discord = new DiscordIntegration();

        $submission = [
            'id' => 123,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $form = [
            'title' => 'Contact Form',
            'id' => 456,
        ];

        $card = $discord->createSubmissionNotification($submission, $form);

        $this->assertIsArray($card);
        $this->assertArrayHasKey('title', $card);
        $this->assertArrayHasKey('description', $card);
        $this->assertArrayHasKey('color', $card);
        $this->assertArrayHasKey('fields', $card);
    }

    public function test_discord_send_file()
    {
        update_option('formflow_discord_bot_token', 'test_token');

        $discord = new DiscordIntegration();

        $testFile = '/tmp/test.txt';
        file_put_contents($testFile, 'Test content');

        $result = $discord->sendFile('12345', $testFile);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);

        if (file_exists($testFile)) {
            unlink($testFile);
        }
    }

    public function test_discord_test_connection()
    {
        update_option('formflow_discord_webhook_url', 'https://discord.com/api/webhooks/test');

        $discord = new DiscordIntegration();
        $result = $discord->testConnection();

        $this->assertIsBool($result);
    }
}
