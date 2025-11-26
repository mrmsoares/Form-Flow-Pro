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
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = UXManager::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = UXManager::getInstance();
        $instance2 = UXManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==========================================================================
    // Keyboard Shortcuts Tests
    // ==========================================================================

    public function test_get_keyboard_shortcuts()
    {
        $shortcuts = $this->manager->getKeyboardShortcuts();

        $this->assertIsArray($shortcuts);
        $this->assertArrayHasKey('save', $shortcuts);
        $this->assertArrayHasKey('search', $shortcuts);
    }

    public function test_register_keyboard_shortcut()
    {
        $shortcut = [
            'key' => 'k',
            'modifiers' => ['ctrl', 'shift'],
            'action' => 'custom_action',
            'description' => 'Custom shortcut action',
        ];

        $result = $this->manager->registerShortcut('custom', $shortcut);

        $this->assertTrue($result);

        $shortcuts = $this->manager->getKeyboardShortcuts();
        $this->assertArrayHasKey('custom', $shortcuts);
    }

    public function test_register_keyboard_shortcut_validation_fails()
    {
        // Missing required fields
        $shortcut = [
            'key' => 'x',
        ];

        $result = $this->manager->registerShortcut('invalid', $shortcut);

        $this->assertFalse($result);
    }

    public function test_unregister_keyboard_shortcut()
    {
        // First register a shortcut
        $this->manager->registerShortcut('temp', [
            'key' => 't',
            'modifiers' => ['ctrl'],
            'action' => 'temp_action',
        ]);

        $result = $this->manager->unregisterShortcut('temp');

        $this->assertTrue($result);

        $shortcuts = $this->manager->getKeyboardShortcuts();
        $this->assertArrayNotHasKey('temp', $shortcuts);
    }

    // ==========================================================================
    // Bulk Operations Tests
    // ==========================================================================

    public function test_register_bulk_action()
    {
        $action = [
            'label' => 'Archive Selected',
            'callback' => 'archive_items',
            'confirm' => true,
            'confirm_message' => 'Are you sure you want to archive {count} items?',
        ];

        $result = $this->manager->registerBulkAction('archive', $action);

        $this->assertTrue($result);
    }

    public function test_get_bulk_actions()
    {
        $actions = $this->manager->getBulkActions();

        $this->assertIsArray($actions);
        $this->assertArrayHasKey('delete', $actions);
    }

    public function test_execute_bulk_action()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)['id' => '1', 'status' => 'active'],
            (object)['id' => '2', 'status' => 'active'],
            (object)['id' => '3', 'status' => 'active'],
        ]);

        $result = $this->manager->executeBulkAction('delete', ['1', '2', '3']);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['affected']);
    }

    public function test_execute_bulk_action_invalid_action()
    {
        $result = $this->manager->executeBulkAction('nonexistent', ['1', '2']);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_execute_bulk_action_empty_items()
    {
        $result = $this->manager->executeBulkAction('delete', []);

        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // Inline Editing Tests
    // ==========================================================================

    public function test_enable_inline_editing()
    {
        $config = [
            'entity' => 'forms',
            'fields' => ['title', 'description', 'status'],
            'validation' => [
                'title' => ['required' => true, 'maxlength' => 100],
            ],
        ];

        $result = $this->manager->enableInlineEditing($config);

        $this->assertTrue($result);
    }

    public function test_inline_edit_save()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'title' => 'Old Title',
            'description' => 'Old Description',
        ]);

        $result = $this->manager->saveInlineEdit('forms', '1', 'title', 'New Title');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }

    public function test_inline_edit_save_validation_fails()
    {
        $this->manager->enableInlineEditing([
            'entity' => 'forms',
            'fields' => ['title'],
            'validation' => [
                'title' => ['required' => true, 'minlength' => 5],
            ],
        ]);

        $result = $this->manager->saveInlineEdit('forms', '1', 'title', 'Ab');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_inline_edit_unauthorized_field()
    {
        $this->manager->enableInlineEditing([
            'entity' => 'forms',
            'fields' => ['title', 'description'],
        ]);

        $result = $this->manager->saveInlineEdit('forms', '1', 'secret_field', 'value');

        $this->assertFalse($result['success']);
    }

    // ==========================================================================
    // View Management Tests
    // ==========================================================================

    public function test_save_user_view()
    {
        global $wpdb;

        $view = [
            'name' => 'My Custom View',
            'entity' => 'forms',
            'columns' => ['title', 'status', 'created_at'],
            'sort' => ['column' => 'created_at', 'direction' => 'desc'],
            'filters' => ['status' => 'active'],
        ];

        $result = $this->manager->saveView($view);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('view_id', $result);
    }

    public function test_get_user_views()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Active Forms',
                'config' => json_encode(['filters' => ['status' => 'active']]),
            ],
            (object)[
                'id' => '2',
                'name' => 'Recent Forms',
                'config' => json_encode(['sort' => ['column' => 'created_at', 'direction' => 'desc']]),
            ],
        ]);

        $views = $this->manager->getViews('forms');

        $this->assertIsArray($views);
        $this->assertCount(2, $views);
    }

    public function test_delete_view()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'user_id' => get_current_user_id(),
        ]);

        $result = $this->manager->deleteView('1');

        $this->assertTrue($result);
    }

    public function test_delete_view_unauthorized()
    {
        global $wpdb;

        // View belongs to another user
        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'user_id' => 999, // Different user
        ]);

        $result = $this->manager->deleteView('1');

        $this->assertFalse($result);
    }

    // ==========================================================================
    // Auto-save Tests
    // ==========================================================================

    public function test_autosave_draft()
    {
        global $wpdb;

        $data = [
            'entity' => 'forms',
            'entity_id' => '1',
            'field' => 'description',
            'value' => 'Auto-saved content...',
        ];

        $result = $this->manager->autosave($data);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('draft_id', $result);
    }

    public function test_get_autosave_draft()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'entity' => 'forms',
            'entity_id' => '1',
            'data' => json_encode(['description' => 'Auto-saved content']),
            'updated_at' => '2024-01-15 10:30:00',
        ]);

        $draft = $this->manager->getAutosaveDraft('forms', '1');

        $this->assertIsObject($draft);
        $this->assertObjectHasProperty('data', $draft);
    }

    public function test_restore_autosave_draft()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'entity' => 'forms',
            'entity_id' => '1',
            'data' => json_encode(['title' => 'Draft Title', 'description' => 'Draft Desc']),
        ]);

        $result = $this->manager->restoreAutosaveDraft('forms', '1');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_discard_autosave_draft()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'entity' => 'forms',
            'entity_id' => '1',
        ]);

        $result = $this->manager->discardAutosaveDraft('forms', '1');

        $this->assertTrue($result);
    }

    // ==========================================================================
    // UX Settings Tests
    // ==========================================================================

    public function test_get_ux_settings()
    {
        $settings = $this->manager->getUXSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('dark_mode', $settings);
        $this->assertArrayHasKey('keyboard_shortcuts_enabled', $settings);
        $this->assertArrayHasKey('reduced_motion', $settings);
    }

    public function test_save_ux_settings()
    {
        $settings = [
            'dark_mode' => true,
            'keyboard_shortcuts_enabled' => true,
            'reduced_motion' => false,
            'high_contrast' => false,
            'sidebar_collapsed' => true,
        ];

        $result = $this->manager->saveUXSettings($settings);

        $this->assertTrue($result);
    }

    public function test_get_single_ux_setting()
    {
        $this->manager->saveUXSettings(['dark_mode' => true]);

        $darkMode = $this->manager->getUXSetting('dark_mode');

        $this->assertTrue($darkMode);
    }

    public function test_get_ux_setting_default()
    {
        $value = $this->manager->getUXSetting('nonexistent_setting', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    // ==========================================================================
    // Toast Notifications Tests
    // ==========================================================================

    public function test_queue_toast_notification()
    {
        $result = $this->manager->queueToast([
            'type' => 'success',
            'message' => 'Item saved successfully!',
            'duration' => 3000,
        ]);

        $this->assertTrue($result);
    }

    public function test_get_queued_toasts()
    {
        $this->manager->queueToast(['type' => 'success', 'message' => 'Success 1']);
        $this->manager->queueToast(['type' => 'error', 'message' => 'Error 1']);

        $toasts = $this->manager->getQueuedToasts();

        $this->assertIsArray($toasts);
        $this->assertGreaterThanOrEqual(2, count($toasts));
    }

    public function test_clear_toast_queue()
    {
        $this->manager->queueToast(['type' => 'info', 'message' => 'Info message']);

        $this->manager->clearToastQueue();

        $toasts = $this->manager->getQueuedToasts();
        $this->assertEmpty($toasts);
    }

    // ==========================================================================
    // Command Palette Tests
    // ==========================================================================

    public function test_register_command()
    {
        $command = [
            'id' => 'create_form',
            'title' => 'Create New Form',
            'description' => 'Start creating a new form',
            'icon' => 'plus',
            'action' => 'navigate',
            'url' => '/forms/new',
            'shortcut' => ['ctrl', 'n'],
            'category' => 'forms',
        ];

        $result = $this->manager->registerCommand($command);

        $this->assertTrue($result);
    }

    public function test_get_commands()
    {
        $commands = $this->manager->getCommands();

        $this->assertIsArray($commands);
    }

    public function test_search_commands()
    {
        $this->manager->registerCommand([
            'id' => 'search_test',
            'title' => 'Search Test Command',
            'action' => 'test',
        ]);

        $results = $this->manager->searchCommands('search');

        $this->assertIsArray($results);
    }

    public function test_unregister_command()
    {
        $this->manager->registerCommand([
            'id' => 'temp_command',
            'title' => 'Temporary Command',
            'action' => 'temp',
        ]);

        $result = $this->manager->unregisterCommand('temp_command');

        $this->assertTrue($result);
    }

    // ==========================================================================
    // Recent Items Tests
    // ==========================================================================

    public function test_add_recent_item()
    {
        $result = $this->manager->addRecentItem([
            'type' => 'form',
            'id' => 'form-123',
            'title' => 'Contact Form',
            'url' => '/forms/edit/form-123',
        ]);

        $this->assertTrue($result);
    }

    public function test_get_recent_items()
    {
        $this->manager->addRecentItem(['type' => 'form', 'id' => '1', 'title' => 'Form 1']);
        $this->manager->addRecentItem(['type' => 'form', 'id' => '2', 'title' => 'Form 2']);

        $recent = $this->manager->getRecentItems('form', 10);

        $this->assertIsArray($recent);
    }

    public function test_clear_recent_items()
    {
        $this->manager->addRecentItem(['type' => 'form', 'id' => '1', 'title' => 'Form']);

        $result = $this->manager->clearRecentItems('form');

        $this->assertTrue($result);

        $recent = $this->manager->getRecentItems('form');
        $this->assertEmpty($recent);
    }

    // ==========================================================================
    // Theme/Dark Mode Tests
    // ==========================================================================

    public function test_set_theme()
    {
        $result = $this->manager->setTheme('dark');

        $this->assertTrue($result);

        $theme = $this->manager->getTheme();
        $this->assertEquals('dark', $theme);
    }

    public function test_set_invalid_theme()
    {
        $result = $this->manager->setTheme('invalid_theme');

        $this->assertFalse($result);
    }

    public function test_toggle_theme()
    {
        $this->manager->setTheme('light');

        $newTheme = $this->manager->toggleTheme();

        $this->assertEquals('dark', $newTheme);

        $newTheme = $this->manager->toggleTheme();
        $this->assertEquals('light', $newTheme);
    }

    // ==========================================================================
    // Accessibility Tests
    // ==========================================================================

    public function test_set_reduced_motion()
    {
        $result = $this->manager->setReducedMotion(true);

        $this->assertTrue($result);

        $reducedMotion = $this->manager->getUXSetting('reduced_motion');
        $this->assertTrue($reducedMotion);
    }

    public function test_set_high_contrast()
    {
        $result = $this->manager->setHighContrast(true);

        $this->assertTrue($result);

        $highContrast = $this->manager->getUXSetting('high_contrast');
        $this->assertTrue($highContrast);
    }

    public function test_get_accessibility_settings()
    {
        $settings = $this->manager->getAccessibilitySettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('reduced_motion', $settings);
        $this->assertArrayHasKey('high_contrast', $settings);
        $this->assertArrayHasKey('focus_visible', $settings);
    }

    // ==========================================================================
    // Table Column Configuration Tests
    // ==========================================================================

    public function test_save_table_columns()
    {
        $columns = [
            ['id' => 'title', 'visible' => true, 'width' => 200, 'order' => 0],
            ['id' => 'status', 'visible' => true, 'width' => 100, 'order' => 1],
            ['id' => 'created_at', 'visible' => true, 'width' => 150, 'order' => 2],
            ['id' => 'author', 'visible' => false, 'width' => 120, 'order' => 3],
        ];

        $result = $this->manager->saveTableColumns('forms', $columns);

        $this->assertTrue($result);
    }

    public function test_get_table_columns()
    {
        $this->manager->saveTableColumns('forms', [
            ['id' => 'title', 'visible' => true, 'order' => 0],
        ]);

        $columns = $this->manager->getTableColumns('forms');

        $this->assertIsArray($columns);
    }

    public function test_reset_table_columns()
    {
        $this->manager->saveTableColumns('forms', [
            ['id' => 'custom', 'visible' => true],
        ]);

        $result = $this->manager->resetTableColumns('forms');

        $this->assertTrue($result);
    }

    // ==========================================================================
    // Sidebar State Tests
    // ==========================================================================

    public function test_set_sidebar_collapsed()
    {
        $result = $this->manager->setSidebarCollapsed(true);

        $this->assertTrue($result);

        $collapsed = $this->manager->isSidebarCollapsed();
        $this->assertTrue($collapsed);
    }

    public function test_toggle_sidebar()
    {
        $this->manager->setSidebarCollapsed(false);

        $newState = $this->manager->toggleSidebar();

        $this->assertTrue($newState);

        $newState = $this->manager->toggleSidebar();
        $this->assertFalse($newState);
    }

    // ==========================================================================
    // Form Persistence Tests
    // ==========================================================================

    public function test_save_form_state()
    {
        $formData = [
            'title' => 'Draft Form',
            'description' => 'Work in progress...',
            'fields' => [
                ['type' => 'text', 'label' => 'Name'],
            ],
        ];

        $result = $this->manager->saveFormState('form_editor', $formData);

        $this->assertTrue($result);
    }

    public function test_get_form_state()
    {
        $this->manager->saveFormState('form_editor', ['title' => 'Saved Title']);

        $state = $this->manager->getFormState('form_editor');

        $this->assertIsArray($state);
        $this->assertEquals('Saved Title', $state['title']);
    }

    public function test_clear_form_state()
    {
        $this->manager->saveFormState('form_editor', ['data' => 'value']);

        $result = $this->manager->clearFormState('form_editor');

        $this->assertTrue($result);

        $state = $this->manager->getFormState('form_editor');
        $this->assertNull($state);
    }

    // ==========================================================================
    // Notification Preferences Tests
    // ==========================================================================

    public function test_set_notification_preferences()
    {
        $prefs = [
            'toast_position' => 'top-right',
            'toast_duration' => 5000,
            'sound_enabled' => false,
        ];

        $result = $this->manager->setNotificationPreferences($prefs);

        $this->assertTrue($result);
    }

    public function test_get_notification_preferences()
    {
        $prefs = $this->manager->getNotificationPreferences();

        $this->assertIsArray($prefs);
        $this->assertArrayHasKey('toast_position', $prefs);
        $this->assertArrayHasKey('toast_duration', $prefs);
    }

    // ==========================================================================
    // Lazy Load Configuration Tests
    // ==========================================================================

    public function test_configure_lazy_loading()
    {
        $config = [
            'threshold' => 0.1,
            'root_margin' => '100px',
            'enabled' => true,
        ];

        $result = $this->manager->configureLazyLoading($config);

        $this->assertTrue($result);
    }

    public function test_get_lazy_loading_config()
    {
        $config = $this->manager->getLazyLoadingConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('threshold', $config);
        $this->assertArrayHasKey('enabled', $config);
    }

    // ==========================================================================
    // Validation Helper Tests
    // ==========================================================================

    public function test_validate_field_required()
    {
        $rules = ['required' => true];

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['', $rules]);

        $this->assertFalse($isValid);

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['value', $rules]);
        $this->assertTrue($isValid);
    }

    public function test_validate_field_minlength()
    {
        $rules = ['minlength' => 5];

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['abc', $rules]);
        $this->assertFalse($isValid);

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['abcdef', $rules]);
        $this->assertTrue($isValid);
    }

    public function test_validate_field_maxlength()
    {
        $rules = ['maxlength' => 10];

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['this is a very long string', $rules]);
        $this->assertFalse($isValid);

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['short', $rules]);
        $this->assertTrue($isValid);
    }

    public function test_validate_field_pattern()
    {
        $rules = ['pattern' => '/^[a-z]+$/'];

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['abc123', $rules]);
        $this->assertFalse($isValid);

        $isValid = $this->callPrivateMethod($this->manager, 'validateField', ['abc', $rules]);
        $this->assertTrue($isValid);
    }

    // ==========================================================================
    // Asset Enqueueing Tests
    // ==========================================================================

    public function test_get_required_assets()
    {
        $assets = $this->manager->getRequiredAssets();

        $this->assertIsArray($assets);
        $this->assertArrayHasKey('js', $assets);
        $this->assertArrayHasKey('css', $assets);
        $this->assertContains('ux-premium', $assets['js']);
        $this->assertContains('ux-premium', $assets['css']);
    }

    public function test_get_localized_script_data()
    {
        $data = $this->manager->getLocalizedScriptData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('ajaxUrl', $data);
        $this->assertArrayHasKey('nonce', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('shortcuts', $data);
    }

    // ==========================================================================
    // AJAX Handler Tests
    // ==========================================================================

    public function test_ajax_bulk_action_handler()
    {
        global $wpdb;

        $_POST['action'] = 'ffp_bulk_action';
        $_POST['nonce'] = wp_create_nonce('ffp_ux_nonce');
        $_POST['bulk_action'] = 'delete';
        $_POST['items'] = ['1', '2', '3'];

        $wpdb->set_mock_result('get_results', [
            (object)['id' => '1'],
            (object)['id' => '2'],
            (object)['id' => '3'],
        ]);

        ob_start();
        $this->manager->handleBulkAction();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    public function test_ajax_inline_edit_handler()
    {
        global $wpdb;

        $_POST['action'] = 'ffp_inline_edit';
        $_POST['nonce'] = wp_create_nonce('ffp_ux_nonce');
        $_POST['entity'] = 'forms';
        $_POST['entity_id'] = '1';
        $_POST['field'] = 'title';
        $_POST['value'] = 'New Title';

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'title' => 'Old Title',
        ]);

        ob_start();
        $this->manager->handleInlineEdit();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    public function test_ajax_save_view_handler()
    {
        $_POST['action'] = 'ffp_save_view';
        $_POST['nonce'] = wp_create_nonce('ffp_ux_nonce');
        $_POST['view'] = json_encode([
            'name' => 'My View',
            'entity' => 'forms',
            'columns' => ['title', 'status'],
        ]);

        ob_start();
        $this->manager->handleSaveView();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    public function test_ajax_autosave_handler()
    {
        $_POST['action'] = 'ffp_autosave';
        $_POST['nonce'] = wp_create_nonce('ffp_ux_nonce');
        $_POST['entity'] = 'forms';
        $_POST['entity_id'] = '1';
        $_POST['data'] = json_encode(['title' => 'Auto-saved title']);

        ob_start();
        $this->manager->handleAutosave();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    public function test_ajax_save_ux_settings_handler()
    {
        $_POST['action'] = 'ffp_save_ux_settings';
        $_POST['nonce'] = wp_create_nonce('ffp_ux_nonce');
        $_POST['settings'] = json_encode([
            'dark_mode' => true,
            'keyboard_shortcuts_enabled' => true,
        ]);

        ob_start();
        $this->manager->handleSaveUXSettings();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
    }

    // ==========================================================================
    // Performance Optimization Tests
    // ==========================================================================

    public function test_get_cache_config()
    {
        $config = $this->manager->getCacheConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('ttl', $config);
    }

    public function test_set_cache_enabled()
    {
        $result = $this->manager->setCacheEnabled(true);

        $this->assertTrue($result);
    }

    public function test_clear_ux_cache()
    {
        $result = $this->manager->clearCache();

        $this->assertTrue($result);
    }

    // ==========================================================================
    // Feature Flags Tests
    // ==========================================================================

    public function test_get_feature_flags()
    {
        $flags = $this->manager->getFeatureFlags();

        $this->assertIsArray($flags);
        $this->assertArrayHasKey('command_palette', $flags);
        $this->assertArrayHasKey('dark_mode', $flags);
        $this->assertArrayHasKey('keyboard_shortcuts', $flags);
    }

    public function test_is_feature_enabled()
    {
        $isEnabled = $this->manager->isFeatureEnabled('dark_mode');

        $this->assertIsBool($isEnabled);
    }

    public function test_enable_feature()
    {
        $result = $this->manager->enableFeature('command_palette');

        $this->assertTrue($result);
        $this->assertTrue($this->manager->isFeatureEnabled('command_palette'));
    }

    public function test_disable_feature()
    {
        $result = $this->manager->disableFeature('command_palette');

        $this->assertTrue($result);
        $this->assertFalse($this->manager->isFeatureEnabled('command_palette'));
    }

    // ==========================================================================
    // Contextual Help Tests
    // ==========================================================================

    public function test_register_help_topic()
    {
        $topic = [
            'id' => 'form_builder',
            'title' => 'Form Builder Help',
            'content' => 'Learn how to use the form builder...',
            'context' => 'forms/edit',
        ];

        $result = $this->manager->registerHelpTopic($topic);

        $this->assertTrue($result);
    }

    public function test_get_help_topics_for_context()
    {
        $this->manager->registerHelpTopic([
            'id' => 'test_topic',
            'title' => 'Test Topic',
            'content' => 'Test content',
            'context' => 'test_page',
        ]);

        $topics = $this->manager->getHelpTopicsForContext('test_page');

        $this->assertIsArray($topics);
    }

    // ==========================================================================
    // Session State Tests
    // ==========================================================================

    public function test_save_session_state()
    {
        $state = [
            'current_page' => '/forms',
            'scroll_position' => 500,
            'open_panels' => ['settings', 'preview'],
        ];

        $result = $this->manager->saveSessionState($state);

        $this->assertTrue($result);
    }

    public function test_get_session_state()
    {
        $this->manager->saveSessionState(['key' => 'value']);

        $state = $this->manager->getSessionState();

        $this->assertIsArray($state);
    }

    public function test_clear_session_state()
    {
        $this->manager->saveSessionState(['data' => 'test']);

        $result = $this->manager->clearSessionState();

        $this->assertTrue($result);

        $state = $this->manager->getSessionState();
        $this->assertEmpty($state);
    }
}
