<?php
/**
 * Tests for UXManager class.
 *
 * @package FormFlowPro\Tests\Unit\UX
 */

namespace FormFlowPro\Tests\Unit\UX;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\UX\UXManager;

class UXManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton for each test
        $reflection = new \ReflectionClass(UXManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $reflection = new \ReflectionClass(UXManager::class);
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
        $instance1 = UXManager::getInstance();
        $instance2 = UXManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(UXManager::class, $instance1);
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_get_config_returns_array()
    {
        $manager = UXManager::getInstance();
        $config = $manager->getConfig();

        $this->assertIsArray($config);
    }

    public function test_get_config_has_default_values()
    {
        $manager = UXManager::getInstance();
        $config = $manager->getConfig();

        $this->assertArrayHasKey('keyboard_shortcuts', $config);
        $this->assertArrayHasKey('bulk_operations', $config);
        $this->assertArrayHasKey('inline_editing', $config);
        $this->assertArrayHasKey('dark_mode', $config);
        $this->assertArrayHasKey('autosave', $config);
    }

    public function test_get_config_default_values()
    {
        $manager = UXManager::getInstance();
        $config = $manager->getConfig();

        // Check default values
        $this->assertTrue($config['keyboard_shortcuts']);
        $this->assertTrue($config['bulk_operations']);
        $this->assertTrue($config['inline_editing']);
        $this->assertFalse($config['dark_mode']);
        $this->assertTrue($config['autosave']);
        $this->assertEquals(30, $config['autosave_interval']);
    }

    public function test_save_config()
    {
        $manager = UXManager::getInstance();

        $newConfig = [
            'keyboard_shortcuts' => false,
            'dark_mode' => true,
            'autosave_interval' => 60,
        ];

        $result = $manager->saveConfig($newConfig);
        $this->assertTrue($result);

        $savedConfig = $manager->getConfig();
        $this->assertFalse($savedConfig['keyboard_shortcuts']);
        $this->assertTrue($savedConfig['dark_mode']);
        $this->assertEquals(60, $savedConfig['autosave_interval']);
    }

    public function test_save_config_sanitizes_boolean_values()
    {
        $manager = UXManager::getInstance();

        $newConfig = [
            'keyboard_shortcuts' => 'yes', // String should be converted to bool
            'dark_mode' => 1, // Int should be converted to bool
        ];

        $manager->saveConfig($newConfig);
        $savedConfig = $manager->getConfig();

        $this->assertTrue($savedConfig['keyboard_shortcuts']);
        $this->assertTrue($savedConfig['dark_mode']);
    }

    public function test_save_config_sanitizes_integer_values()
    {
        $manager = UXManager::getInstance();

        $newConfig = [
            'autosave_interval' => '45', // String should be converted to int
            'max_undo_steps' => 15.5, // Float should be converted to int
        ];

        $manager->saveConfig($newConfig);
        $savedConfig = $manager->getConfig();

        $this->assertIsInt($savedConfig['autosave_interval']);
        $this->assertEquals(45, $savedConfig['autosave_interval']);
        $this->assertIsInt($savedConfig['max_undo_steps']);
        $this->assertEquals(15, $savedConfig['max_undo_steps']);
    }

    // ==========================================================================
    // Body Classes Tests
    // ==========================================================================

    public function test_add_body_classes_default()
    {
        $manager = UXManager::getInstance();
        $classes = $manager->addBodyClasses('existing-class');

        $this->assertStringContainsString('existing-class', $classes);
    }

    public function test_add_body_classes_dark_mode()
    {
        $manager = UXManager::getInstance();
        $manager->saveConfig(['dark_mode' => true]);

        $classes = $manager->addBodyClasses('');
        $this->assertStringContainsString('formflow-dark-mode', $classes);
    }

    public function test_add_body_classes_compact_mode()
    {
        $manager = UXManager::getInstance();
        $manager->saveConfig(['compact_mode' => true]);

        $classes = $manager->addBodyClasses('');
        $this->assertStringContainsString('formflow-compact-mode', $classes);
    }

    public function test_add_body_classes_no_animations()
    {
        $manager = UXManager::getInstance();
        $manager->saveConfig(['animations' => false]);

        $classes = $manager->addBodyClasses('');
        $this->assertStringContainsString('formflow-no-animations', $classes);
    }

    // ==========================================================================
    // Shortcuts Tests
    // ==========================================================================

    public function test_get_shortcuts_list()
    {
        $manager = UXManager::getInstance();
        $shortcuts = $manager->getShortcutsList();

        $this->assertIsArray($shortcuts);
        $this->assertNotEmpty($shortcuts);
    }

    public function test_get_shortcuts_list_structure()
    {
        $manager = UXManager::getInstance();
        $shortcuts = $manager->getShortcutsList();

        foreach ($shortcuts as $shortcut) {
            $this->assertIsArray($shortcut);
            $this->assertArrayHasKey('key', $shortcut);
            $this->assertArrayHasKey('action', $shortcut);
        }
    }

    public function test_get_shortcuts_list_contains_expected_shortcuts()
    {
        $manager = UXManager::getInstance();
        $shortcuts = $manager->getShortcutsList();

        $keys = array_column($shortcuts, 'key');

        $this->assertContains('Ctrl+S', $keys);
        $this->assertContains('Ctrl+Z', $keys);
        $this->assertContains('Ctrl+Y', $keys);
        $this->assertContains('Ctrl+F', $keys);
        $this->assertContains('Esc', $keys);
    }

    // ==========================================================================
    // Saved Views Tests
    // ==========================================================================

    public function test_get_saved_views_empty_by_default()
    {
        $manager = UXManager::getInstance();
        $views = $manager->getSavedViews();

        $this->assertIsArray($views);
    }

    public function test_get_saved_views_returns_stored_views()
    {
        // Pre-populate saved views
        update_option('formflow_saved_views', [
            'view_1' => ['name' => 'Test View', 'filters' => []],
        ]);

        $manager = UXManager::getInstance();
        $views = $manager->getSavedViews();

        $this->assertArrayHasKey('view_1', $views);
        $this->assertEquals('Test View', $views['view_1']['name']);
    }

    public function test_delete_saved_view_success()
    {
        // Pre-populate saved views
        update_option('formflow_saved_views', [
            'view_1' => ['name' => 'Test View', 'filters' => []],
            'view_2' => ['name' => 'Another View', 'filters' => []],
        ]);

        $manager = UXManager::getInstance();
        $result = $manager->deleteSavedView('view_1');

        $this->assertTrue($result);

        $views = $manager->getSavedViews();
        $this->assertArrayNotHasKey('view_1', $views);
        $this->assertArrayHasKey('view_2', $views);
    }

    public function test_delete_saved_view_nonexistent()
    {
        update_option('formflow_saved_views', []);

        $manager = UXManager::getInstance();
        $result = $manager->deleteSavedView('nonexistent_view');

        $this->assertFalse($result);
    }

    // ==========================================================================
    // Autosave Tests
    // ==========================================================================

    public function test_get_autosave_returns_null_when_empty()
    {
        $manager = UXManager::getInstance();
        $autosave = $manager->getAutosave('form', 1);

        $this->assertNull($autosave);
    }

    public function test_get_autosave_returns_stored_data()
    {
        // Pre-populate autosave
        $key = 'formflow_autosave_form_1_1'; // type_id_userid
        set_transient($key, ['title' => 'Test', 'content' => 'Draft content']);

        $manager = UXManager::getInstance();
        $autosave = $manager->getAutosave('form', 1);

        $this->assertIsArray($autosave);
        $this->assertEquals('Test', $autosave['title']);
        $this->assertEquals('Draft content', $autosave['content']);
    }

    public function test_clear_autosave()
    {
        // Pre-populate autosave
        $key = 'formflow_autosave_form_1_1';
        set_transient($key, ['data' => 'test']);

        $manager = UXManager::getInstance();
        $result = $manager->clearAutosave('form', 1);

        $this->assertTrue($result);

        $autosave = $manager->getAutosave('form', 1);
        $this->assertNull($autosave);
    }

    // ==========================================================================
    // AJAX Handler Tests
    // ==========================================================================

    public function test_handle_bulk_action_success()
    {
        global $wpdb;

        $_POST['nonce'] = 'valid';
        $_POST['bulk_action'] = 'export';
        $_POST['ids'] = [1, 2, 3];

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleBulkAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    public function test_handle_inline_edit_missing_params()
    {
        $_POST['nonce'] = 'valid';
        $_POST['id'] = 0; // Invalid ID
        $_POST['field'] = '';

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleInlineEdit();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_handle_save_view_missing_name()
    {
        $_POST['nonce'] = 'valid';
        $_POST['name'] = ''; // Empty name

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleSaveView();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_handle_save_view_success()
    {
        $_POST['nonce'] = 'valid';
        $_POST['name'] = 'My Test View';
        $_POST['filters'] = ['status' => 'active'];
        $_POST['columns'] = ['title', 'status', 'date'];

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleSaveView();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('view_id', $response['data']);

        // Verify view was saved
        $views = $manager->getSavedViews();
        $this->assertNotEmpty($views);
    }

    public function test_handle_autosave_missing_data()
    {
        $_POST['nonce'] = 'valid';
        $_POST['type'] = '';
        $_POST['data'] = [];

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleAutosave();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertFalse($response['success']);
    }

    public function test_handle_autosave_success()
    {
        $_POST['nonce'] = 'valid';
        $_POST['type'] = 'form';
        $_POST['id'] = 1;
        $_POST['data'] = ['title' => 'Autosaved Title'];

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleAutosave();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('timestamp', $response['data']);
    }

    public function test_handle_ux_settings_get()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST['nonce'] = 'valid';

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleUXSettings();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('keyboard_shortcuts', $response['data']);
    }

    public function test_handle_ux_settings_post()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['nonce'] = 'valid';
        $_POST['config'] = ['dark_mode' => true];

        $manager = UXManager::getInstance();

        ob_start();
        $manager->handleUXSettings();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    // ==========================================================================
    // Integration Tests
    // ==========================================================================

    public function test_full_view_workflow()
    {
        $manager = UXManager::getInstance();

        // 1. Initially no views
        $views = $manager->getSavedViews();
        $this->assertEmpty($views);

        // 2. Create a view via AJAX
        $_POST['nonce'] = 'valid';
        $_POST['name'] = 'Active Items';
        $_POST['filters'] = ['status' => 'active'];
        $_POST['columns'] = ['title', 'status'];

        ob_start();
        $manager->handleSaveView();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $viewId = $response['data']['view_id'];

        // 3. Verify view exists
        $views = $manager->getSavedViews();
        $this->assertArrayHasKey($viewId, $views);
        $this->assertEquals('Active Items', $views[$viewId]['name']);

        // 4. Delete the view
        $result = $manager->deleteSavedView($viewId);
        $this->assertTrue($result);

        // 5. Verify view is gone
        $views = $manager->getSavedViews();
        $this->assertArrayNotHasKey($viewId, $views);
    }

    public function test_full_autosave_workflow()
    {
        $manager = UXManager::getInstance();

        // 1. Initially no autosave
        $autosave = $manager->getAutosave('form', 99);
        $this->assertNull($autosave);

        // 2. Create autosave via AJAX
        $_POST['nonce'] = 'valid';
        $_POST['type'] = 'form';
        $_POST['id'] = 99;
        $_POST['data'] = ['title' => 'Draft Form', 'fields' => ['name', 'email']];

        ob_start();
        $manager->handleAutosave();
        ob_get_clean();

        // 3. Verify autosave exists
        $autosave = $manager->getAutosave('form', 99);
        $this->assertIsArray($autosave);
        $this->assertEquals('Draft Form', $autosave['title']);

        // 4. Clear autosave
        $result = $manager->clearAutosave('form', 99);
        $this->assertTrue($result);

        // 5. Verify autosave is gone
        $autosave = $manager->getAutosave('form', 99);
        $this->assertNull($autosave);
    }

    public function test_config_persistence()
    {
        // Create first instance and change config
        $manager1 = UXManager::getInstance();
        $manager1->saveConfig([
            'dark_mode' => true,
            'compact_mode' => true,
            'autosave_interval' => 120,
        ]);

        // Reset singleton
        $reflection = new \ReflectionClass(UXManager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        // Create new instance and verify config persisted
        $manager2 = UXManager::getInstance();
        $config = $manager2->getConfig();

        $this->assertTrue($config['dark_mode']);
        $this->assertTrue($config['compact_mode']);
        $this->assertEquals(120, $config['autosave_interval']);
    }
}
