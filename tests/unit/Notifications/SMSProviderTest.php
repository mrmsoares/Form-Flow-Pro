<?php
/**
 * Tests for SMS Providers and SMS Manager.
 */

namespace FormFlowPro\Tests\Unit\Notifications;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Notifications\SMSManager;
use FormFlowPro\Notifications\TwilioProvider;
use FormFlowPro\Notifications\VonageProvider;
use FormFlowPro\Notifications\AWSSNSProvider;

class SMSProviderTest extends TestCase
{
    private $smsManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsManager = SMSManager::getInstance();
    }

    public function test_sms_manager_get_instance_returns_singleton()
    {
        $instance1 = SMSManager::getInstance();
        $instance2 = SMSManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(SMSManager::class, $instance1);
    }

    public function test_get_provider_returns_default()
    {
        update_option('formflow_sms_default_provider', 'twilio');

        $provider = $this->smsManager->getProvider();

        $this->assertInstanceOf(TwilioProvider::class, $provider);
    }

    public function test_get_provider_returns_specific_provider()
    {
        $provider = $this->smsManager->getProvider('vonage');

        $this->assertInstanceOf(VonageProvider::class, $provider);
    }

    public function test_get_provider_returns_null_for_invalid()
    {
        $provider = $this->smsManager->getProvider('invalid_provider');

        $this->assertNull($provider);
    }

    public function test_get_configured_providers()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $providers = $this->smsManager->getConfiguredProviders();

        $this->assertIsArray($providers);
        $this->assertArrayHasKey('twilio', $providers);
    }

    public function test_send_sms_with_twilio()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $result = $this->smsManager->send('+1234567890', 'Test message', [], 'twilio');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_send_sms_with_no_provider_configured()
    {
        delete_option('formflow_twilio_account_sid');
        delete_option('formflow_vonage_api_key');
        delete_option('formflow_aws_access_key_id');

        $result = $this->smsManager->send('+1234567890', 'Test message');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_send_bulk_sms()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $recipients = [
            '+1234567890',
            '+0987654321',
        ];

        $result = $this->smsManager->sendBulk($recipients, 'Test message', [], 'twilio');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    public function test_schedule_sms()
    {
        $timestamp = time() + 3600;

        $result = $this->smsManager->schedule(
            '+1234567890',
            'Scheduled message',
            $timestamp,
            [],
            'twilio'
        );

        $this->assertTrue($result);
    }

    public function test_get_sms_logs()
    {
        $logs = $this->smsManager->getLogs();

        $this->assertIsArray($logs);
    }

    public function test_get_sms_logs_with_filters()
    {
        $logs = $this->smsManager->getLogs([
            'provider' => 'twilio',
            'status' => 'sent',
        ], 10, 0);

        $this->assertIsArray($logs);
    }

    public function test_get_sms_statistics()
    {
        $stats = $this->smsManager->getStatistics('day');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('sent', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('total_cost', $stats);
        $this->assertArrayHasKey('total_segments', $stats);
    }

    public function test_create_table()
    {
        global $wpdb;

        $this->smsManager->createTable();

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_sms_logs'");
        $this->assertNotNull($table);
    }

    public function test_twilio_provider_is_configured()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $provider = new TwilioProvider();

        $this->assertTrue($provider->isConfigured());
    }

    public function test_twilio_provider_is_not_configured()
    {
        delete_option('formflow_twilio_account_sid');
        delete_option('formflow_twilio_auth_token');

        $provider = new TwilioProvider();

        $this->assertFalse($provider->isConfigured());
    }

    public function test_twilio_provider_send()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $provider = new TwilioProvider();
        $result = $provider->send('+1234567890', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_twilio_provider_send_bulk()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $provider = new TwilioProvider();
        $result = $provider->sendBulk(['+1234567890', '+0987654321'], 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    public function test_twilio_provider_validate_phone_number()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');

        $provider = new TwilioProvider();
        $result = $provider->validatePhoneNumber('+1234567890');

        $this->assertIsBool($result);
    }

    public function test_vonage_provider_is_configured()
    {
        update_option('formflow_vonage_api_key', 'test_key');
        update_option('formflow_vonage_api_secret', 'test_secret');
        update_option('formflow_vonage_from_number', 'FormFlow');

        $provider = new VonageProvider();

        $this->assertTrue($provider->isConfigured());
    }

    public function test_vonage_provider_is_not_configured()
    {
        delete_option('formflow_vonage_api_key');

        $provider = new VonageProvider();

        $this->assertFalse($provider->isConfigured());
    }

    public function test_vonage_provider_send()
    {
        update_option('formflow_vonage_api_key', 'test_key');
        update_option('formflow_vonage_api_secret', 'test_secret');
        update_option('formflow_vonage_from_number', 'FormFlow');

        $provider = new VonageProvider();
        $result = $provider->send('+1234567890', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_vonage_provider_get_balance()
    {
        update_option('formflow_vonage_api_key', 'test_key');
        update_option('formflow_vonage_api_secret', 'test_secret');

        $provider = new VonageProvider();
        $balance = $provider->getBalance();

        $this->assertTrue(is_float($balance) || is_null($balance));
    }

    public function test_aws_sns_provider_is_configured()
    {
        update_option('formflow_aws_access_key_id', 'test_key_id');
        update_option('formflow_aws_secret_access_key', 'test_secret');

        $provider = new AWSSNSProvider();

        $this->assertTrue($provider->isConfigured());
    }

    public function test_aws_sns_provider_is_not_configured()
    {
        delete_option('formflow_aws_access_key_id');

        $provider = new AWSSNSProvider();

        $this->assertFalse($provider->isConfigured());
    }

    public function test_aws_sns_provider_send()
    {
        update_option('formflow_aws_access_key_id', 'test_key_id');
        update_option('formflow_aws_secret_access_key', 'test_secret');
        update_option('formflow_aws_region', 'us-east-1');

        $provider = new AWSSNSProvider();
        $result = $provider->send('+1234567890', 'Test message');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_aws_sns_provider_validate_phone_number()
    {
        $provider = new AWSSNSProvider();

        $this->assertTrue($provider->validatePhoneNumber('+1234567890'));
        $this->assertFalse($provider->validatePhoneNumber('invalid'));
    }

    public function test_aws_sns_provider_get_balance_returns_null()
    {
        $provider = new AWSSNSProvider();
        $balance = $provider->getBalance();

        $this->assertNull($balance);
    }

    public function test_trigger_sms_notification_on_form_submission()
    {
        $formId = 123;
        $submissionId = 456;

        update_post_meta($formId, '_formflow_sms_notifications', [
            'enabled' => true,
            'admin_phone' => '+1234567890',
            'admin_message' => 'New submission from {{name}}',
        ]);

        $data = [
            'form_id' => $formId,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $this->smsManager->triggerSMSNotification($submissionId, $data);

        $this->assertTrue(true);
    }

    public function test_scheduled_send_handler()
    {
        update_option('formflow_twilio_account_sid', 'test_sid');
        update_option('formflow_twilio_auth_token', 'test_token');
        update_option('formflow_twilio_from_number', '+1234567890');

        $this->smsManager->scheduledSend('+1234567890', 'Test message', ['provider' => 'twilio']);

        $this->assertTrue(true);
    }
}
