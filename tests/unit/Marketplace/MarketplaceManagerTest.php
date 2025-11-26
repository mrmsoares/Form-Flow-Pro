<?php
/**
 * Tests for ExtensionManager class (Marketplace module).
 *
 * @package FormFlowPro\Tests\Unit\Marketplace
 */

namespace FormFlowPro\Tests\Unit\Marketplace;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Marketplace\ExtensionManager;
use FormFlowPro\Marketplace\InstalledExtension;
use FormFlowPro\Marketplace\MarketplaceExtension;
use FormFlowPro\Marketplace\ExtensionStatus;

class MarketplaceManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for each test
        $reflection = new \ReflectionClass(ExtensionManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $reflection = new \ReflectionClass(ExtensionManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        parent::tearDown();
    }

    // ==========================================================================
    // Singleton Tests
    // ==========================================================================

    public function test_singleton_instance()
    {
        $instance1 = ExtensionManager::getInstance();
        $instance2 = ExtensionManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ExtensionManager::class, $instance1);
    }

    // ==========================================================================
    // ExtensionStatus Constants Tests
    // ==========================================================================

    public function test_extension_status_constants()
    {
        $this->assertEquals('active', ExtensionStatus::ACTIVE);
        $this->assertEquals('inactive', ExtensionStatus::INACTIVE);
        $this->assertEquals('installed', ExtensionStatus::INSTALLED);
        $this->assertEquals('update_available', ExtensionStatus::UPDATE_AVAILABLE);
        $this->assertEquals('not_installed', ExtensionStatus::NOT_INSTALLED);
    }

    // ==========================================================================
    // InstalledExtension Model Tests
    // ==========================================================================

    public function test_installed_extension_constructor()
    {
        $extension = new InstalledExtension([
            'id' => 'ext-1',
            'slug' => 'my-extension',
            'name' => 'My Extension',
            'version' => '1.2.0',
            'description' => 'Test extension description',
            'author' => 'Test Author',
            'author_uri' => 'https://example.com',
            'category' => 'integrations',
            'status' => ExtensionStatus::ACTIVE,
            'path' => '/path/to/extension',
            'main_file' => 'my-extension.php',
            'icon' => 'https://example.com/icon.png',
            'is_premium' => true,
            'license_key' => 'LICENSE-KEY-123',
            'license_expires' => '2025-12-31'
        ]);

        $this->assertEquals('ext-1', $extension->id);
        $this->assertEquals('my-extension', $extension->slug);
        $this->assertEquals('My Extension', $extension->name);
        $this->assertEquals('1.2.0', $extension->version);
        $this->assertEquals('Test Author', $extension->author);
        $this->assertEquals('integrations', $extension->category);
        $this->assertEquals(ExtensionStatus::ACTIVE, $extension->status);
        $this->assertTrue($extension->is_premium);
        $this->assertEquals('LICENSE-KEY-123', $extension->license_key);
    }

    public function test_installed_extension_defaults()
    {
        $extension = new InstalledExtension([]);

        $this->assertEquals('', $extension->id);
        $this->assertEquals('', $extension->slug);
        $this->assertEquals('1.0.0', $extension->version);
        $this->assertEquals('general', $extension->category);
        $this->assertEquals(ExtensionStatus::INACTIVE, $extension->status);
        $this->assertFalse($extension->is_premium);
        $this->assertNull($extension->license_key);
        $this->assertNull($extension->latest_version);
    }

    public function test_installed_extension_has_update_true()
    {
        $extension = new InstalledExtension([
            'version' => '1.0.0',
            'latest_version' => '2.0.0'
        ]);

        $this->assertTrue($extension->hasUpdate());
    }

    public function test_installed_extension_has_update_false()
    {
        $extension = new InstalledExtension([
            'version' => '2.0.0',
            'latest_version' => '2.0.0'
        ]);

        $this->assertFalse($extension->hasUpdate());
    }

    public function test_installed_extension_has_update_no_latest()
    {
        $extension = new InstalledExtension([
            'version' => '1.0.0',
            'latest_version' => null
        ]);

        $this->assertFalse($extension->hasUpdate());
    }

    public function test_installed_extension_is_active()
    {
        $active = new InstalledExtension(['status' => ExtensionStatus::ACTIVE]);
        $inactive = new InstalledExtension(['status' => ExtensionStatus::INACTIVE]);

        $this->assertTrue($active->isActive());
        $this->assertFalse($inactive->isActive());
    }

    public function test_installed_extension_is_license_valid_free()
    {
        $extension = new InstalledExtension([
            'is_premium' => false
        ]);

        $this->assertTrue($extension->isLicenseValid());
    }

    public function test_installed_extension_is_license_valid_premium_valid()
    {
        $extension = new InstalledExtension([
            'is_premium' => true,
            'license_key' => 'VALID-KEY',
            'license_expires' => date('Y-m-d', strtotime('+1 year'))
        ]);

        $this->assertTrue($extension->isLicenseValid());
    }

    public function test_installed_extension_is_license_valid_premium_expired()
    {
        $extension = new InstalledExtension([
            'is_premium' => true,
            'license_key' => 'EXPIRED-KEY',
            'license_expires' => date('Y-m-d', strtotime('-1 year'))
        ]);

        $this->assertFalse($extension->isLicenseValid());
    }

    public function test_installed_extension_is_license_valid_premium_no_key()
    {
        $extension = new InstalledExtension([
            'is_premium' => true,
            'license_key' => null
        ]);

        $this->assertFalse($extension->isLicenseValid());
    }

    public function test_installed_extension_to_array()
    {
        $extension = new InstalledExtension([
            'id' => 'test-ext',
            'slug' => 'test',
            'name' => 'Test'
        ]);

        $array = $extension->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('status', $array);
    }

    // ==========================================================================
    // MarketplaceExtension Model Tests
    // ==========================================================================

    public function test_marketplace_extension_constructor()
    {
        $extension = new MarketplaceExtension([
            'id' => 'mkt-1',
            'slug' => 'marketplace-extension',
            'name' => 'Marketplace Extension',
            'version' => '3.0.0',
            'description' => 'Short description',
            'long_description' => 'Longer description with more details',
            'author' => 'Extension Author',
            'category' => 'analytics',
            'tags' => ['analytics', 'reporting'],
            'icon' => 'https://example.com/icon.png',
            'screenshots' => ['https://example.com/screen1.png'],
            'download_url' => 'https://example.com/download',
            'rating' => 4.5,
            'rating_count' => 100,
            'active_installs' => 5000,
            'is_premium' => true,
            'price' => 49.99,
            'currency' => 'USD'
        ]);

        $this->assertEquals('mkt-1', $extension->id);
        $this->assertEquals('marketplace-extension', $extension->slug);
        $this->assertEquals('3.0.0', $extension->version);
        $this->assertEquals('analytics', $extension->category);
        $this->assertContains('analytics', $extension->tags);
        $this->assertEquals(4.5, $extension->rating);
        $this->assertEquals(5000, $extension->active_installs);
        $this->assertTrue($extension->is_premium);
        $this->assertEquals(49.99, $extension->price);
    }

    public function test_marketplace_extension_defaults()
    {
        $extension = new MarketplaceExtension([]);

        $this->assertEquals('', $extension->id);
        $this->assertEquals('1.0.0', $extension->version);
        $this->assertEquals('general', $extension->category);
        $this->assertEquals(0.0, $extension->rating);
        $this->assertEquals(0, $extension->rating_count);
        $this->assertEquals(0, $extension->active_installs);
        $this->assertFalse($extension->is_premium);
        $this->assertEquals(0.0, $extension->price);
        $this->assertEquals('USD', $extension->currency);
        $this->assertEquals('8.1', $extension->requires_php);
        $this->assertEquals('6.0', $extension->requires_wp);
        $this->assertEquals('2.0.0', $extension->requires_ffp);
    }

    public function test_marketplace_extension_to_array()
    {
        $extension = new MarketplaceExtension([
            'id' => 'test',
            'name' => 'Test Extension',
            'price' => 29.99
        ]);

        $array = $extension->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('price', $array);
        $this->assertArrayHasKey('rating', $array);
        $this->assertArrayHasKey('is_premium', $array);
    }

    // ==========================================================================
    // ExtensionManager Basic Methods Tests
    // ==========================================================================

    public function test_get_categories()
    {
        $manager = ExtensionManager::getInstance();
        $categories = $manager->getCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('field-types', $categories);
        $this->assertArrayHasKey('integrations', $categories);
        $this->assertArrayHasKey('workflow-actions', $categories);
        $this->assertArrayHasKey('notifications', $categories);
        $this->assertArrayHasKey('payments', $categories);
        $this->assertArrayHasKey('analytics', $categories);
    }

    public function test_get_installed_extensions_returns_array()
    {
        $manager = ExtensionManager::getInstance();
        $installed = $manager->getInstalledExtensions();

        $this->assertIsArray($installed);
    }

    public function test_get_active_extensions_returns_array()
    {
        $manager = ExtensionManager::getInstance();
        $active = $manager->getActiveExtensions();

        $this->assertIsArray($active);
    }

    public function test_is_installed_returns_false_for_unknown()
    {
        $manager = ExtensionManager::getInstance();

        $this->assertFalse($manager->isInstalled('unknown-extension'));
    }

    public function test_is_active_returns_false_for_unknown()
    {
        $manager = ExtensionManager::getInstance();

        $this->assertFalse($manager->isActive('unknown-extension'));
    }

    // ==========================================================================
    // REST API Tests
    // ==========================================================================

    public function test_rest_permission_check()
    {
        $manager = ExtensionManager::getInstance();

        // In test environment, current_user_can returns true
        $result = $manager->restPermissionCheck();

        $this->assertTrue($result);
    }

    public function test_rest_search_extensions_returns_response()
    {
        global $wp_http_mock_response;

        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'extensions' => [],
                'total' => 0
            ])
        ];

        $manager = ExtensionManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow-pro/v1/marketplace/extensions');
        $response = $manager->restSearchExtensions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_installed_extensions_returns_response()
    {
        $manager = ExtensionManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow-pro/v1/extensions');
        $response = $manager->restGetInstalledExtensions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    // ==========================================================================
    // Extension Lifecycle Tests
    // ==========================================================================

    public function test_activate_extension_not_installed()
    {
        $manager = ExtensionManager::getInstance();

        $result = $manager->activateExtension('nonexistent-extension');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_deactivate_extension_not_installed()
    {
        $manager = ExtensionManager::getInstance();

        $result = $manager->deactivateExtension('nonexistent-extension');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    public function test_uninstall_extension_not_installed()
    {
        $manager = ExtensionManager::getInstance();

        $result = $manager->uninstallExtension('nonexistent-extension');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    public function test_update_extension_not_installed()
    {
        $manager = ExtensionManager::getInstance();

        $result = $manager->updateExtension('nonexistent-extension');

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // Marketplace Search Tests
    // ==========================================================================

    public function test_search_marketplace_returns_array()
    {
        global $wp_http_mock_response;

        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'extensions' => [
                    ['id' => '1', 'name' => 'Test Extension', 'slug' => 'test']
                ],
                'total' => 1,
                'pages' => 1
            ])
        ];

        $manager = ExtensionManager::getInstance();

        $result = $manager->searchMarketplace([
            'query' => 'test',
            'category' => 'integrations'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('extensions', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function test_search_marketplace_handles_error()
    {
        global $wp_http_mock_error;

        $wp_http_mock_error = 'Connection failed';

        $manager = ExtensionManager::getInstance();

        $result = $manager->searchMarketplace(['query' => 'test']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('extensions', $result);
        $this->assertEmpty($result['extensions']);
    }

    public function test_get_featured_extensions_returns_array()
    {
        global $wp_http_mock_response;

        $wp_http_mock_response = [
            'response' => ['code' => 200],
            'body' => json_encode([])
        ];

        $manager = ExtensionManager::getInstance();

        $result = $manager->getFeaturedExtensions();

        $this->assertIsArray($result);
    }

    // ==========================================================================
    // Event Callbacks Tests
    // ==========================================================================

    public function test_on_extension_activated()
    {
        $manager = ExtensionManager::getInstance();

        // Should not throw
        $manager->onExtensionActivated('test-extension', ['name' => 'Test']);

        $this->assertTrue(true);
    }

    public function test_on_extension_deactivated()
    {
        $manager = ExtensionManager::getInstance();

        // Should not throw
        $manager->onExtensionDeactivated('test-extension');

        $this->assertTrue(true);
    }

    // ==========================================================================
    // Integration Tests
    // ==========================================================================

    public function test_installed_extension_workflow()
    {
        $extension = new InstalledExtension([
            'id' => 'workflow-test',
            'slug' => 'workflow-test',
            'name' => 'Workflow Test',
            'version' => '1.0.0',
            'status' => ExtensionStatus::INACTIVE,
            'is_premium' => false
        ]);

        // Initially inactive
        $this->assertFalse($extension->isActive());
        $this->assertTrue($extension->isLicenseValid()); // Free extension

        // Convert to array and back
        $array = $extension->toArray();
        $restored = new InstalledExtension($array);

        $this->assertEquals($extension->slug, $restored->slug);
        $this->assertEquals($extension->version, $restored->version);
    }

    public function test_marketplace_extension_pricing()
    {
        $free = new MarketplaceExtension([
            'name' => 'Free Extension',
            'is_premium' => false,
            'price' => 0
        ]);

        $premium = new MarketplaceExtension([
            'name' => 'Premium Extension',
            'is_premium' => true,
            'price' => 99.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($free->is_premium);
        $this->assertEquals(0, $free->price);

        $this->assertTrue($premium->is_premium);
        $this->assertEquals(99.99, $premium->price);
        $this->assertEquals('USD', $premium->currency);
    }
}
