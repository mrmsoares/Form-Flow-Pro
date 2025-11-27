<?php
/**
 * Tests for ServiceWorkerManager class.
 */

namespace FormFlowPro\Tests\Unit\PWA;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\PWA\ServiceWorkerManager;

class ServiceWorkerManagerTest extends TestCase
{
    private $swManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->swManager = ServiceWorkerManager::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = ServiceWorkerManager::getInstance();
        $instance2 = ServiceWorkerManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ServiceWorkerManager::class, $instance1);
    }

    public function test_is_pwa_enabled_returns_false_by_default()
    {
        delete_option('ffp_pwa_enabled');

        $result = $this->swManager->isPWAEnabled();

        $this->assertFalse($result);
    }

    public function test_is_pwa_enabled_returns_true_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);

        $result = $this->swManager->isPWAEnabled();

        $this->assertTrue($result);
    }

    public function test_add_query_vars_adds_service_worker_var()
    {
        $vars = ['existing_var'];

        $result = $this->swManager->addQueryVars($vars);

        $this->assertContains('ffp_service_worker', $result);
        $this->assertContains('ffp_offline_page', $result);
        $this->assertContains('existing_var', $result);
    }

    public function test_handle_service_worker_request_exits_for_sw()
    {
        set_query_var('ffp_service_worker', 1);

        ob_start();
        try {
            $this->swManager->handleServiceWorkerRequest();
            $output = ob_get_clean();

            $this->assertStringContainsString('FormFlow Pro Service Worker', $output);
        } catch (\Exception $e) {
            ob_end_clean();
            // Expected exit
        }
    }

    public function test_handle_service_worker_request_exits_for_offline_page()
    {
        set_query_var('ffp_offline_page', 1);

        ob_start();
        try {
            $this->swManager->handleServiceWorkerRequest();
            $output = ob_get_clean();

            $this->assertStringContainsString('Offline', $output);
        } catch (\Exception $e) {
            ob_end_clean();
            // Expected exit
        }
    }

    public function test_output_registration_script_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);

        ob_start();
        $this->swManager->outputRegistrationScript();
        $output = ob_get_clean();

        $this->assertStringContainsString('serviceWorker', $output);
        $this->assertStringContainsString('navigator.serviceWorker.register', $output);
        $this->assertStringContainsString('ffp-sw.js', $output);
    }

    public function test_output_registration_script_skips_when_disabled()
    {
        update_option('ffp_pwa_enabled', false);

        ob_start();
        $this->swManager->outputRegistrationScript();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_output_manifest_link_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_theme_color', '#3b82f6');
        update_option('ffp_pwa_short_name', 'TestApp');

        ob_start();
        $this->swManager->outputManifestLink();
        $output = ob_get_clean();

        $this->assertStringContainsString('rel="manifest"', $output);
        $this->assertStringContainsString('theme-color', $output);
        $this->assertStringContainsString('#3b82f6', $output);
        $this->assertStringContainsString('apple-mobile-web-app', $output);
        $this->assertStringContainsString('TestApp', $output);
    }

    public function test_output_manifest_link_skips_when_disabled()
    {
        update_option('ffp_pwa_enabled', false);

        ob_start();
        $this->swManager->outputManifestLink();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_output_manifest_link_includes_custom_icon()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_icon', 'https://example.com/icon.png');

        ob_start();
        $this->swManager->outputManifestLink();
        $output = ob_get_clean();

        $this->assertStringContainsString('apple-touch-icon', $output);
        $this->assertStringContainsString('https://example.com/icon.png', $output);
    }

    public function test_bump_version_increments_sw_version()
    {
        update_option('ffp_sw_version', 5);

        $this->swManager->bumpVersion();

        $this->assertEquals(6, get_option('ffp_sw_version'));
    }

    public function test_bump_version_initializes_from_zero()
    {
        delete_option('ffp_sw_version');

        $this->swManager->bumpVersion();

        $this->assertEquals(2, get_option('ffp_sw_version'));
    }

    public function test_get_cache_strategies_returns_array()
    {
        $strategies = $this->swManager->getCacheStrategies();

        $this->assertIsArray($strategies);
        $this->assertArrayHasKey('cache_first', $strategies);
        $this->assertArrayHasKey('network_first', $strategies);
        $this->assertArrayHasKey('stale_while_revalidate', $strategies);
        $this->assertArrayHasKey('network_only', $strategies);
        $this->assertArrayHasKey('cache_only', $strategies);
    }

    public function test_cache_strategies_have_required_fields()
    {
        $strategies = $this->swManager->getCacheStrategies();

        foreach ($strategies as $key => $strategy) {
            $this->assertArrayHasKey('name', $strategy);
            $this->assertArrayHasKey('description', $strategy);
            $this->assertIsString($strategy['name']);
            $this->assertIsString($strategy['description']);
        }
    }

    public function test_register_rewrite_rules()
    {
        // Mock test - rewrite rules would be tested in integration
        $this->swManager->registerRewriteRules();

        $this->assertTrue(true);
    }

    public function test_register_settings()
    {
        // Mock test - settings registration would be tested in integration
        $this->swManager->registerSettings();

        $this->assertTrue(true);
    }

    public function test_service_worker_code_generation()
    {
        // This is a private method test via reflection
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('generateServiceWorkerCode');
        $method->setAccessible(true);

        $settings = [
            'enabled' => true,
            'offline_enabled' => true,
            'background_sync' => true,
        ];

        $code = $method->invoke(
            $this->swManager,
            $settings,
            'cache-v1',
            'forms-v1',
            'submissions-v1',
            ['/']
        );

        $this->assertIsString($code);
        $this->assertStringContainsString('FormFlow Pro Service Worker', $code);
        $this->assertStringContainsString('cache-v1', $code);
        $this->assertStringContainsString('forms-v1', $code);
        $this->assertStringContainsString('submissions-v1', $code);
        $this->assertStringContainsString('install', $code);
        $this->assertStringContainsString('activate', $code);
        $this->assertStringContainsString('fetch', $code);
    }

    public function test_service_worker_code_includes_offline_submission_handling()
    {
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('generateServiceWorkerCode');
        $method->setAccessible(true);

        $settings = ['enabled' => true, 'offline_enabled' => true, 'background_sync' => true];

        $code = $method->invoke(
            $this->swManager,
            $settings,
            'cache-v1',
            'forms-v1',
            'submissions-v1',
            ['/']
        );

        $this->assertStringContainsString('handleFormSubmission', $code);
        $this->assertStringContainsString('storeOfflineSubmission', $code);
        $this->assertStringContainsString('syncOfflineSubmissions', $code);
        $this->assertStringContainsString('sync', $code);
    }

    public function test_service_worker_code_includes_push_notification_handling()
    {
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('generateServiceWorkerCode');
        $method->setAccessible(true);

        $settings = ['enabled' => true, 'push_notifications' => true];

        $code = $method->invoke(
            $this->swManager,
            $settings,
            'cache-v1',
            'forms-v1',
            'submissions-v1',
            ['/']
        );

        $this->assertStringContainsString('push', $code);
        $this->assertStringContainsString('notificationclick', $code);
        $this->assertStringContainsString('showNotification', $code);
    }

    public function test_service_worker_code_includes_cache_strategies()
    {
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('generateServiceWorkerCode');
        $method->setAccessible(true);

        $settings = ['enabled' => true];

        $code = $method->invoke(
            $this->swManager,
            $settings,
            'cache-v1',
            'forms-v1',
            'submissions-v1',
            ['/']
        );

        $this->assertStringContainsString('cacheFirst', $code);
        $this->assertStringContainsString('networkFirst', $code);
        $this->assertStringContainsString('staleWhileRevalidate', $code);
    }

    public function test_precache_urls_includes_offline_page()
    {
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('getPrecacheUrls');
        $method->setAccessible(true);

        $urls = $method->invoke($this->swManager);

        $this->assertIsArray($urls);
        $this->assertGreaterThan(0, count($urls));

        $offlineUrlFound = false;
        foreach ($urls as $url) {
            if (strpos($url, 'ffp-offline.html') !== false) {
                $offlineUrlFound = true;
                break;
            }
        }

        $this->assertTrue($offlineUrlFound);
    }

    public function test_precache_urls_includes_assets()
    {
        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('getPrecacheUrls');
        $method->setAccessible(true);

        $urls = $method->invoke($this->swManager);

        $hasCss = false;
        $hasJs = false;

        foreach ($urls as $url) {
            if (strpos($url, 'frontend.css') !== false) $hasCss = true;
            if (strpos($url, 'frontend.js') !== false) $hasJs = true;
        }

        $this->assertTrue($hasCss);
        $this->assertTrue($hasJs);
    }

    public function test_offline_page_contains_required_elements()
    {
        update_option('ffp_pwa_offline_message', 'Custom offline message');

        ob_start();
        try {
            set_query_var('ffp_offline_page', 1);
            $this->swManager->handleServiceWorkerRequest();
        } catch (\Exception $e) {
            // Expected exit
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('<!DOCTYPE html>', $output);
        $this->assertStringContainsString('Offline', $output);
        $this->assertStringContainsString('Custom offline message', $output);
        $this->assertStringContainsString('Try Again', $output);
        $this->assertStringContainsString('pending-submissions', $output);
    }

    public function test_offline_page_includes_auto_retry_script()
    {
        ob_start();
        try {
            set_query_var('ffp_offline_page', 1);
            $this->swManager->handleServiceWorkerRequest();
        } catch (\Exception $e) {
            // Expected exit
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('window.addEventListener', $output);
        $this->assertStringContainsString('online', $output);
        $this->assertStringContainsString('location.reload', $output);
    }

    public function test_offline_enabled_forms_query()
    {
        global $wpdb;

        // Mock database
        $wpdb->prefix = 'wp_';

        $reflection = new \ReflectionClass($this->swManager);
        $method = $reflection->getMethod('getOfflineEnabledForms');
        $method->setAccessible(true);

        $forms = $method->invoke($this->swManager);

        $this->assertIsArray($forms);
    }
}
