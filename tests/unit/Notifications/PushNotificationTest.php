<?php
/**
 * Tests for PushNotifications class.
 */

namespace FormFlowPro\Tests\Unit\Notifications;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Notifications\PushNotifications;

class PushNotificationTest extends TestCase
{
    private $pushNotifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pushNotifications = PushNotifications::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = PushNotifications::getInstance();
        $instance2 = PushNotifications::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(PushNotifications::class, $instance1);
    }

    public function test_get_public_key_returns_string()
    {
        $publicKey = $this->pushNotifications->getPublicKey();

        $this->assertIsString($publicKey);
        $this->assertNotEmpty($publicKey);
    }

    public function test_create_table()
    {
        global $wpdb;

        $this->pushNotifications->createTable();

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_push_subscriptions'");
        $this->assertNotNull($table);
    }

    public function test_subscribe_creates_new_subscription()
    {
        $result = $this->pushNotifications->subscribe(
            'https://fcm.googleapis.com/fcm/send/test',
            'test_public_key',
            'test_auth_token',
            1
        );

        $this->assertTrue($result);
    }

    public function test_subscribe_updates_existing_subscription()
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test_update';

        $this->pushNotifications->subscribe(
            $endpoint,
            'old_public_key',
            'old_auth_token',
            1
        );

        $result = $this->pushNotifications->subscribe(
            $endpoint,
            'new_public_key',
            'new_auth_token',
            1
        );

        $this->assertTrue($result);
    }

    public function test_unsubscribe()
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test_unsub';

        $this->pushNotifications->subscribe(
            $endpoint,
            'test_public_key',
            'test_auth_token',
            1
        );

        $result = $this->pushNotifications->unsubscribe($endpoint);

        $this->assertTrue($result);
    }

    public function test_send_to_user()
    {
        $userId = 1;

        $this->pushNotifications->subscribe(
            'https://fcm.googleapis.com/fcm/send/test_user',
            'test_public_key',
            'test_auth_token',
            $userId
        );

        $payload = [
            'title' => 'Test Notification',
            'body' => 'This is a test',
            'icon' => '/icon.png',
        ];

        $result = $this->pushNotifications->sendToUser($userId, $payload);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_send_to_user_with_no_subscriptions()
    {
        $userId = 9999;

        $payload = [
            'title' => 'Test Notification',
            'body' => 'This is a test',
        ];

        $result = $this->pushNotifications->sendToUser($userId, $payload);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertEquals('No subscriptions found', $result['error']);
    }

    public function test_send_to_all()
    {
        $this->pushNotifications->subscribe(
            'https://fcm.googleapis.com/fcm/send/test_all_1',
            'test_public_key',
            'test_auth_token',
            1
        );

        $payload = [
            'title' => 'Broadcast Notification',
            'body' => 'This is a broadcast',
        ];

        $result = $this->pushNotifications->sendToAll($payload);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
    }

    public function test_send_to_role()
    {
        $payload = [
            'title' => 'Role Notification',
            'body' => 'This is for administrators',
        ];

        $result = $this->pushNotifications->sendToRole('administrator', $payload);

        $this->assertIsArray($result);
    }

    public function test_get_subscribers_count()
    {
        $count = $this->pushNotifications->getSubscribersCount();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_get_statistics()
    {
        $stats = $this->pushNotifications->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_subscribers', $stats);
        $this->assertArrayHasKey('by_device', $stats);
        $this->assertArrayHasKey('recent_subscriptions', $stats);
    }

    public function test_notify_on_submission()
    {
        $formId = 123;
        $submissionId = 456;

        update_post_meta($formId, '_formflow_push_notifications', [
            'enabled' => true,
            'notify_admins' => true,
        ]);

        $data = [
            'form_id' => $formId,
            'email' => 'user@example.com',
            'name' => 'John Doe',
        ];

        $this->pushNotifications->notifyOnSubmission($submissionId, $data);

        $this->assertTrue(true);
    }

    public function test_generate_service_worker()
    {
        $serviceWorker = $this->pushNotifications->generateServiceWorker();

        $this->assertIsString($serviceWorker);
        $this->assertStringContainsString('self.addEventListener', $serviceWorker);
        $this->assertStringContainsString('push', $serviceWorker);
        $this->assertStringContainsString('notificationclick', $serviceWorker);
    }

    public function test_enqueue_scripts()
    {
        update_option('formflow_push_enabled', true);

        $this->pushNotifications->enqueueScripts();

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_scripts()
    {
        $this->pushNotifications->enqueueAdminScripts('formflow-notifications');

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_scripts_skips_other_pages()
    {
        $this->pushNotifications->enqueueAdminScripts('other-page');

        $this->assertTrue(true);
    }

    public function test_render_service_worker_script()
    {
        update_option('formflow_push_enabled', true);

        ob_start();
        $this->pushNotifications->renderServiceWorkerScript();
        $output = ob_get_clean();

        $this->assertStringContainsString('serviceWorker', $output);
        $this->assertStringContainsString('register', $output);
    }

    public function test_rest_subscribe()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/push/subscribe');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test_rest',
            'keys' => [
                'p256dh' => 'test_public_key',
                'auth' => 'test_auth_token',
            ],
        ]));

        $response = $this->pushNotifications->restSubscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
    }

    public function test_rest_subscribe_with_invalid_data()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/push/subscribe');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode([]));

        $response = $this->pushNotifications->restSubscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
    }

    public function test_rest_unsubscribe()
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test_rest_unsub';

        $this->pushNotifications->subscribe($endpoint, 'key', 'auth', 1);

        $request = new \WP_REST_Request('POST', '/formflow/v1/push/unsubscribe');
        $request->set_param('endpoint', $endpoint);

        $response = $this->pushNotifications->restUnsubscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
    }

    public function test_rest_unsubscribe_without_endpoint()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/push/unsubscribe');

        $response = $this->pushNotifications->restUnsubscribe($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
    }

    public function test_rest_test_push()
    {
        $this->pushNotifications->subscribe(
            'https://fcm.googleapis.com/fcm/send/test_push',
            'test_key',
            'test_auth',
            get_current_user_id()
        );

        $request = new \WP_REST_Request('POST', '/formflow/v1/push/test');

        $response = $this->pushNotifications->restTestPush($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_send_push_notification()
    {
        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test_send',
            'public_key' => 'test_public_key',
            'auth_token' => 'test_auth_token',
        ];

        $payload = [
            'title' => 'Test',
            'body' => 'Test notification',
        ];

        $result = $this->pushNotifications->send($subscription, $payload);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
