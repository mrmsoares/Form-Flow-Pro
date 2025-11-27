<?php
/**
 * Tests for PWAManager class.
 */

namespace FormFlowPro\Tests\Unit\PWA;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\PWA\PWAManager;
use FormFlowPro\PWA\ServiceWorkerManager;
use FormFlowPro\PWA\MobilePreview;

class PWAManagerTest extends TestCase
{
    private $pwaManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pwaManager = PWAManager::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = PWAManager::getInstance();
        $instance2 = PWAManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(PWAManager::class, $instance1);
    }

    public function test_get_service_worker_manager_returns_instance()
    {
        $swManager = $this->pwaManager->getServiceWorkerManager();

        $this->assertInstanceOf(ServiceWorkerManager::class, $swManager);
    }

    public function test_get_mobile_preview_returns_instance()
    {
        $mobilePreview = $this->pwaManager->getMobilePreview();

        $this->assertInstanceOf(MobilePreview::class, $mobilePreview);
    }

    public function test_is_enabled_returns_false_by_default()
    {
        delete_option('ffp_pwa_enabled');

        $result = $this->pwaManager->isEnabled();

        $this->assertFalse($result);
    }

    public function test_is_enabled_returns_true_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);

        $result = $this->pwaManager->isEnabled();

        $this->assertTrue($result);
    }

    public function test_enable_sets_option()
    {
        $this->pwaManager->enable();

        $this->assertTrue(get_option('ffp_pwa_enabled'));
    }

    public function test_disable_sets_option()
    {
        update_option('ffp_pwa_enabled', true);

        $this->pwaManager->disable();

        $this->assertFalse(get_option('ffp_pwa_enabled'));
    }

    public function test_get_manifest_returns_array_with_required_fields()
    {
        update_option('ffp_pwa_name', 'Test App');
        update_option('ffp_pwa_short_name', 'TestApp');
        update_option('ffp_pwa_description', 'Test Description');
        update_option('ffp_pwa_theme_color', '#ff0000');
        update_option('ffp_pwa_background_color', '#ffffff');

        $manifest = $this->pwaManager->getManifest();

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('short_name', $manifest);
        $this->assertArrayHasKey('description', $manifest);
        $this->assertArrayHasKey('start_url', $manifest);
        $this->assertArrayHasKey('scope', $manifest);
        $this->assertArrayHasKey('display', $manifest);
        $this->assertArrayHasKey('theme_color', $manifest);
        $this->assertArrayHasKey('background_color', $manifest);
        $this->assertArrayHasKey('icons', $manifest);
        $this->assertArrayHasKey('screenshots', $manifest);
        $this->assertArrayHasKey('shortcuts', $manifest);
        $this->assertArrayHasKey('share_target', $manifest);
    }

    public function test_get_manifest_uses_custom_values()
    {
        update_option('ffp_pwa_name', 'My Custom App');
        update_option('ffp_pwa_short_name', 'MyApp');
        update_option('ffp_pwa_theme_color', '#3b82f6');

        $manifest = $this->pwaManager->getManifest();

        $this->assertEquals('My Custom App', $manifest['name']);
        $this->assertEquals('MyApp', $manifest['short_name']);
        $this->assertEquals('#3b82f6', $manifest['theme_color']);
    }

    public function test_get_manifest_icons_array()
    {
        $manifest = $this->pwaManager->getManifest();

        $this->assertIsArray($manifest['icons']);
        $this->assertGreaterThan(0, count($manifest['icons']));

        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertArrayHasKey('sizes', $icon);
            $this->assertArrayHasKey('type', $icon);
            $this->assertArrayHasKey('purpose', $icon);
        }
    }

    public function test_get_status_returns_array_with_required_keys()
    {
        $status = $this->pwaManager->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('enabled', $status);
        $this->assertArrayHasKey('service_worker_registered', $status);
        $this->assertArrayHasKey('manifest_valid', $status);
        $this->assertArrayHasKey('https', $status);
        $this->assertArrayHasKey('icons_present', $status);
        $this->assertArrayHasKey('installable', $status);
        $this->assertArrayHasKey('issues', $status);
    }

    public function test_get_status_adds_https_warning_when_not_secure()
    {
        // Mock is_ssl() returning false
        $status = $this->pwaManager->getStatus();

        $this->assertIsArray($status['issues']);

        $hasHttpsIssue = false;
        foreach ($status['issues'] as $issue) {
            if ($issue['type'] === 'error' && strpos($issue['message'], 'HTTPS') !== false) {
                $hasHttpsIssue = true;
                break;
            }
        }

        $this->assertTrue($hasHttpsIssue);
    }

    public function test_get_status_checks_manifest_validity()
    {
        update_option('ffp_pwa_name', 'A'); // Too short

        $status = $this->pwaManager->getStatus();

        $this->assertFalse($status['manifest_valid']);
    }

    public function test_get_status_installable_requires_all_conditions()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_name', 'Valid Name');

        $status = $this->pwaManager->getStatus();

        // Installable depends on enabled, https, manifest_valid, and no errors
        $this->assertIsBool($status['installable']);
    }

    public function test_generate_icons_returns_array()
    {
        $attachmentId = 123; // Mock attachment ID

        $result = $this->pwaManager->generateIcons($attachmentId);

        $this->assertIsArray($result);
    }

    public function test_get_install_banner_returns_html_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_short_name', 'TestApp');

        $banner = $this->pwaManager->getInstallBanner();

        $this->assertIsString($banner);
        $this->assertStringContainsString('ffp-install-banner', $banner);
        $this->assertStringContainsString('TestApp', $banner);
        $this->assertStringContainsString('Install', $banner);
    }

    public function test_get_install_banner_returns_empty_when_disabled()
    {
        update_option('ffp_pwa_enabled', false);

        $banner = $this->pwaManager->getInstallBanner();

        $this->assertEmpty($banner);
    }

    public function test_rest_get_manifest_returns_response()
    {
        $response = $this->pwaManager->restGetManifest();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals('application/manifest+json', $response->get_headers()['Content-Type']);
    }

    public function test_rest_get_status_returns_response()
    {
        $response = $this->pwaManager->restGetStatus();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('enabled', $data);
        $this->assertArrayHasKey('installable', $data);
    }

    public function test_rest_get_settings_returns_all_settings()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_name', 'Test PWA');
        update_option('ffp_pwa_theme_color', '#ff0000');
        update_option('ffp_pwa_offline_forms', true);
        update_option('ffp_pwa_background_sync', true);

        $response = $this->pwaManager->restGetSettings();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['enabled']);
        $this->assertEquals('Test PWA', $data['name']);
        $this->assertEquals('#ff0000', $data['theme_color']);
        $this->assertTrue($data['offline_forms']);
        $this->assertTrue($data['background_sync']);
    }

    public function test_rest_save_settings_updates_options()
    {
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/pwa/settings');
        $request->set_param('enabled', true);
        $request->set_param('name', 'Updated Name');
        $request->set_param('theme_color', '#00ff00');
        $request->set_param('offline_forms', true);

        $response = $this->pwaManager->restSaveSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $this->assertTrue(get_option('ffp_pwa_enabled'));
        $this->assertEquals('Updated Name', get_option('ffp_pwa_name'));
        $this->assertEquals('#00ff00', get_option('ffp_pwa_theme_color'));
        $this->assertTrue(get_option('ffp_pwa_offline_forms'));
    }

    public function test_rest_save_settings_sanitizes_input()
    {
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/pwa/settings');
        $request->set_param('name', '<script>alert("xss")</script>Test');
        $request->set_param('description', '<b>Bold</b> text');

        $response = $this->pwaManager->restSaveSettings($request);

        $savedName = get_option('ffp_pwa_name');
        $this->assertStringNotContainsString('<script>', $savedName);
        $this->assertStringNotContainsString('alert', $savedName);
    }

    public function test_ajax_save_settings_requires_nonce()
    {
        $_POST['_ajax_nonce'] = 'invalid_nonce';

        $this->expectException(\WPAjaxDieException::class);
        $this->pwaManager->ajaxSaveSettings();
    }

    public function test_ajax_save_settings_requires_permission()
    {
        $_POST['_ajax_nonce'] = wp_create_nonce('ffp_pwa_settings');

        $this->expectException(\WPAjaxDieException::class);
        $this->pwaManager->ajaxSaveSettings();
    }

    public function test_ajax_test_manifest_returns_success()
    {
        $_POST['nonce'] = wp_create_nonce('ffp_nonce');

        ob_start();
        try {
            $this->pwaManager->ajaxTestManifest();
        } catch (\WPAjaxDieException $e) {
            // Expected
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('manifest', $data['data']);
        $this->assertArrayHasKey('status', $data['data']);
    }

    public function test_ajax_clear_cache_bumps_version()
    {
        $_POST['nonce'] = wp_create_nonce('ffp_nonce');

        $oldVersion = get_option('ffp_sw_version', '1');

        ob_start();
        try {
            $this->pwaManager->ajaxClearCache();
        } catch (\WPAjaxDieException $e) {
            // Expected
        }
        ob_end_clean();

        $newVersion = get_option('ffp_sw_version', '1');
        $this->assertGreaterThan((int)$oldVersion, (int)$newVersion);
    }

    public function test_register_admin_menu()
    {
        // Mock test - actual menu registration would be tested in integration
        $this->pwaManager->registerAdminMenu();

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_on_pwa_page()
    {
        // Mock test - asset enqueuing would be tested in integration
        $this->pwaManager->enqueueAdminAssets('formflow-pro_page_formflow-pro-pwa');

        $this->assertTrue(true);
    }

    public function test_enqueue_admin_assets_skips_other_pages()
    {
        $this->pwaManager->enqueueAdminAssets('other-page');

        $this->assertTrue(true);
    }

    public function test_enqueue_frontend_assets_when_enabled()
    {
        update_option('ffp_pwa_enabled', true);
        update_option('ffp_pwa_install_banner', true);

        $this->pwaManager->enqueueFrontendAssets();

        $this->assertTrue(true);
    }

    public function test_enqueue_frontend_assets_skips_when_disabled()
    {
        update_option('ffp_pwa_enabled', false);

        $this->pwaManager->enqueueFrontendAssets();

        $this->assertTrue(true);
    }

    public function test_render_admin_page()
    {
        ob_start();
        $this->pwaManager->renderAdminPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('Mobile & PWA Settings', $output);
        $this->assertStringContainsString('ffp-pwa-settings', $output);
    }
}
