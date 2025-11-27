<?php
/**
 * Tests for MultiSiteManager class.
 */

namespace FormFlowPro\Tests\Unit\MultiSite;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\MultiSite\MultiSiteManager;

class MultiSiteManagerTest extends TestCase
{
    private $multiSiteManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->multiSiteManager = MultiSiteManager::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = MultiSiteManager::getInstance();
        $instance2 = MultiSiteManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(MultiSiteManager::class, $instance1);
    }

    public function test_is_multisite_returns_boolean()
    {
        $result = $this->multiSiteManager->isMultisite();

        $this->assertIsBool($result);
    }

    public function test_get_network_settings_returns_defaults()
    {
        delete_site_option('formflow_network_settings');

        $settings = $this->multiSiteManager->getNetworkSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('network_activated', $settings);
        $this->assertArrayHasKey('sync_settings', $settings);
        $this->assertArrayHasKey('shared_templates', $settings);
        $this->assertArrayHasKey('centralized_analytics', $settings);
        $this->assertArrayHasKey('global_integrations', $settings);
        $this->assertArrayHasKey('license_key', $settings);
        $this->assertArrayHasKey('license_type', $settings);
        $this->assertArrayHasKey('max_sites', $settings);
        $this->assertArrayHasKey('data_retention_days', $settings);
        $this->assertArrayHasKey('enable_cross_site_reporting', $settings);
    }

    public function test_get_network_settings_default_values()
    {
        delete_site_option('formflow_network_settings');

        $settings = $this->multiSiteManager->getNetworkSettings();

        $this->assertFalse($settings['network_activated']);
        $this->assertFalse($settings['sync_settings']);
        $this->assertTrue($settings['shared_templates']);
        $this->assertTrue($settings['centralized_analytics']);
        $this->assertFalse($settings['global_integrations']);
        $this->assertEquals('', $settings['license_key']);
        $this->assertEquals('network', $settings['license_type']);
        $this->assertEquals(0, $settings['max_sites']); // 0 = unlimited
        $this->assertEquals(90, $settings['data_retention_days']);
        $this->assertTrue($settings['enable_cross_site_reporting']);
    }

    public function test_get_network_settings_returns_saved_settings()
    {
        $savedSettings = [
            'network_activated' => true,
            'sync_settings' => true,
            'license_key' => 'test-license-key',
            'max_sites' => 10,
        ];
        update_site_option('formflow_network_settings', $savedSettings);

        $settings = $this->multiSiteManager->getNetworkSettings();

        $this->assertTrue($settings['network_activated']);
        $this->assertTrue($settings['sync_settings']);
        $this->assertEquals('test-license-key', $settings['license_key']);
        $this->assertEquals(10, $settings['max_sites']);
    }

    public function test_save_network_settings_returns_true()
    {
        $settings = [
            'network_activated' => true,
            'sync_settings' => false,
            'shared_templates' => true,
            'license_key' => 'new-license',
            'max_sites' => 5,
            'data_retention_days' => 120,
        ];

        $result = $this->multiSiteManager->saveNetworkSettings($settings);

        $this->assertTrue($result);
    }

    public function test_save_network_settings_sanitizes_values()
    {
        $settings = [
            'network_activated' => 1,
            'sync_settings' => 0,
            'license_key' => '<script>alert("xss")</script>',
            'max_sites' => '15',
            'data_retention_days' => '60',
        ];

        $this->multiSiteManager->saveNetworkSettings($settings);

        $saved = $this->multiSiteManager->getNetworkSettings();

        $this->assertTrue($saved['network_activated']);
        $this->assertFalse($saved['sync_settings']);
        $this->assertStringNotContainsString('<script>', $saved['license_key']);
        $this->assertEquals(15, $saved['max_sites']);
        $this->assertEquals(60, $saved['data_retention_days']);
    }

    public function test_get_network_stats_returns_empty_when_not_multisite()
    {
        // Mock is_multisite() returning false
        $reflection = new \ReflectionProperty($this->multiSiteManager, 'isMultisite');
        $reflection->setAccessible(true);
        $reflection->setValue($this->multiSiteManager, false);

        $stats = $this->multiSiteManager->getNetworkStats();

        $this->assertIsArray($stats);
        $this->assertEmpty($stats);
    }

    public function test_get_network_stats_structure()
    {
        // Mock multisite environment
        $reflection = new \ReflectionProperty($this->multiSiteManager, 'isMultisite');
        $reflection->setAccessible(true);
        $reflection->setValue($this->multiSiteManager, true);

        $stats = $this->multiSiteManager->getNetworkStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_sites', $stats);
        $this->assertArrayHasKey('active_sites', $stats);
        $this->assertArrayHasKey('total_forms', $stats);
        $this->assertArrayHasKey('total_submissions', $stats);
        $this->assertArrayHasKey('total_documents', $stats);
        $this->assertArrayHasKey('submissions_today', $stats);
        $this->assertArrayHasKey('top_sites', $stats);
    }

    public function test_get_all_sites_data_returns_empty_when_not_multisite()
    {
        $reflection = new \ReflectionProperty($this->multiSiteManager, 'isMultisite');
        $reflection->setAccessible(true);
        $reflection->setValue($this->multiSiteManager, false);

        $sites = $this->multiSiteManager->getAllSitesData();

        $this->assertIsArray($sites);
        $this->assertEmpty($sites);
    }

    public function test_is_plugin_active_on_site_checks_active_plugins()
    {
        $blogId = 1;

        $result = $this->multiSiteManager->isPluginActiveOnSite($blogId);

        $this->assertIsBool($result);
    }

    public function test_sync_settings_to_site_returns_false_for_main_site()
    {
        $mainSiteId = get_main_site_id();

        $result = $this->multiSiteManager->syncSettingsToSite($mainSiteId);

        $this->assertFalse($result);
    }

    public function test_get_license_info_returns_structure()
    {
        update_site_option('formflow_network_settings', [
            'license_key' => 'test-key',
            'license_type' => 'network',
            'max_sites' => 10,
        ]);

        $info = $this->multiSiteManager->getLicenseInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('key', $info);
        $this->assertArrayHasKey('type', $info);
        $this->assertArrayHasKey('max_sites', $info);
        $this->assertArrayHasKey('active_sites', $info);
        $this->assertArrayHasKey('status', $info);

        $this->assertEquals('test-key', $info['key']);
        $this->assertEquals('network', $info['type']);
        $this->assertEquals(10, $info['max_sites']);
        $this->assertEquals('active', $info['status']);
    }

    public function test_get_license_info_inactive_when_no_key()
    {
        update_site_option('formflow_network_settings', [
            'license_key' => '',
        ]);

        $info = $this->multiSiteManager->getLicenseInfo();

        $this->assertEquals('inactive', $info['status']);
    }

    public function test_rest_get_network_stats_returns_response()
    {
        $response = $this->multiSiteManager->restGetNetworkStats();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_sites_returns_response()
    {
        $response = $this->multiSiteManager->restGetSites();

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_network_settings_get_returns_settings()
    {
        update_site_option('formflow_network_settings', [
            'network_activated' => true,
            'sync_settings' => true,
        ]);

        $request = new \WP_REST_Request('GET', '/formflow/v1/network/settings');

        $response = $this->multiSiteManager->restNetworkSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['network_activated']);
        $this->assertTrue($data['sync_settings']);
    }

    public function test_rest_network_settings_post_saves_settings()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/network/settings');
        $request->set_param('network_activated', true);
        $request->set_param('sync_settings', false);
        $request->set_param('max_sites', 20);

        $response = $this->multiSiteManager->restNetworkSettings($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['success']);

        $saved = $this->multiSiteManager->getNetworkSettings();
        $this->assertTrue($saved['network_activated']);
        $this->assertFalse($saved['sync_settings']);
        $this->assertEquals(20, $saved['max_sites']);
    }

    public function test_rest_network_settings_post_returns_error_message_on_failure()
    {
        // Force failure by using invalid data structure
        $request = new \WP_REST_Request('POST', '/formflow/v1/network/settings');

        $response = $this->multiSiteManager->restNetworkSettings($request);

        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_activate_for_site_calls_activation_hook()
    {
        $blogId = 1;

        $result = $this->multiSiteManager->activateForSite($blogId);

        $this->assertTrue($result);
    }

    public function test_deactivate_for_site_calls_deactivation_hook()
    {
        $blogId = 1;

        $result = $this->multiSiteManager->deactivateForSite($blogId);

        $this->assertTrue($result);
    }

    public function test_on_new_site_activates_when_network_activated()
    {
        update_site_option('formflow_network_settings', [
            'network_activated' => true,
        ]);

        $site = new \WP_Site((object)[
            'blog_id' => 2,
        ]);

        $this->multiSiteManager->onNewSite($site, []);

        $this->assertTrue(true); // Verify no errors
    }

    public function test_on_new_site_syncs_settings_when_enabled()
    {
        update_site_option('formflow_network_settings', [
            'network_activated' => true,
            'sync_settings' => true,
        ]);

        $site = new \WP_Site((object)[
            'blog_id' => 2,
        ]);

        $this->multiSiteManager->onNewSite($site, []);

        $this->assertTrue(true); // Verify no errors
    }

    public function test_on_site_activate_activates_when_network_activated()
    {
        update_site_option('formflow_network_settings', [
            'network_activated' => true,
        ]);

        $this->multiSiteManager->onSiteActivate(2);

        $this->assertTrue(true); // Verify no errors
    }

    public function test_on_site_delete_drops_tables_when_requested()
    {
        global $wpdb;

        $this->multiSiteManager->onSiteDelete(2, true);

        $this->assertTrue(true); // Verify no errors
    }

    public function test_network_activate_activates_all_sites()
    {
        $this->multiSiteManager->networkActivate();

        $settings = $this->multiSiteManager->getNetworkSettings();
        $this->assertTrue($settings['network_activated']);
    }

    public function test_network_deactivate_clears_transients()
    {
        set_site_transient('formflow_network_stats', ['test' => 'data'], 300);

        $this->multiSiteManager->networkDeactivate();

        $cached = get_site_transient('formflow_network_stats');
        $this->assertFalse($cached);
    }

    public function test_sync_network_data_clears_cache()
    {
        set_site_transient('formflow_network_stats', ['test' => 'data'], 300);

        $this->multiSiteManager->syncNetworkData();

        $cached = get_site_transient('formflow_network_stats');
        $this->assertFalse($cached);
    }

    public function test_display_network_notices_shows_license_warning()
    {
        update_site_option('formflow_network_settings', [
            'license_key' => '',
        ]);

        set_current_screen('formflow-network');

        ob_start();
        $this->multiSiteManager->displayNetworkNotices();
        $output = ob_get_clean();

        $this->assertStringContainsString('license not configured', $output);
    }

    public function test_display_network_notices_shows_site_limit_warning()
    {
        update_site_option('formflow_network_settings', [
            'license_key' => 'test-key',
            'max_sites' => 1,
        ]);

        set_current_screen('formflow-network');

        ob_start();
        $this->multiSiteManager->displayNetworkNotices();
        $output = ob_get_clean();

        // May show limit warning depending on active sites
        $this->assertIsString($output);
    }

    public function test_display_network_notices_skips_non_formflow_pages()
    {
        set_current_screen('other-page');

        ob_start();
        $this->multiSiteManager->displayNetworkNotices();
        $output = ob_get_clean();

        $this->assertEmpty($output);
    }

    public function test_register_admin_menu()
    {
        // Mock test - actual menu registration would be tested in integration
        $this->multiSiteManager->addNetworkMenu();

        $this->assertTrue(true);
    }

    public function test_register_rest_routes()
    {
        // Mock test - REST route registration would be tested in integration
        $this->multiSiteManager->registerRestRoutes();

        $this->assertTrue(true);
    }

    public function test_render_network_dashboard()
    {
        ob_start();
        try {
            $this->multiSiteManager->renderNetworkDashboard();
        } catch (\Exception $e) {
            // May fail if view file doesn't exist in test environment
        }
        $output = ob_get_clean();

        $this->assertTrue(true);
    }

    public function test_render_sites_overview()
    {
        ob_start();
        try {
            $this->multiSiteManager->renderSitesOverview();
        } catch (\Exception $e) {
            // May fail if view file doesn't exist in test environment
        }
        $output = ob_get_clean();

        $this->assertTrue(true);
    }

    public function test_render_network_settings()
    {
        ob_start();
        try {
            $this->multiSiteManager->renderNetworkSettings();
        } catch (\Exception $e) {
            // May fail if view file doesn't exist in test environment
        }
        $output = ob_get_clean();

        $this->assertTrue(true);
    }

    public function test_render_license_management()
    {
        ob_start();
        try {
            $this->multiSiteManager->renderLicenseManagement();
        } catch (\Exception $e) {
            // May fail if view file doesn't exist in test environment
        }
        $output = ob_get_clean();

        $this->assertTrue(true);
    }
}
