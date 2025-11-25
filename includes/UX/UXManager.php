<?php

declare(strict_types=1);

/**
 * UX Manager
 *
 * Central manager for premium UX features.
 *
 * @package FormFlowPro\UX
 * @since 2.2.1
 */

namespace FormFlowPro\UX;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * UX Manager Class
 */
class UXManager
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Configuration
     *
     * @var array
     */
    private array $config;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->loadConfig();
        $this->setupHooks();
    }

    /**
     * Load configuration
     *
     * @return void
     */
    private function loadConfig(): void
    {
        $defaults = [
            'keyboard_shortcuts' => true,
            'bulk_operations' => true,
            'quick_actions' => true,
            'inline_editing' => true,
            'advanced_filters' => true,
            'saved_views' => true,
            'column_customization' => true,
            'dark_mode' => false,
            'compact_mode' => false,
            'animations' => true,
            'tooltips' => true,
            'autosave' => true,
            'autosave_interval' => 30,
            'undo_redo' => true,
            'max_undo_steps' => 20,
        ];

        $this->config = wp_parse_args(
            get_option('formflow_ux_settings', []),
            $defaults
        );
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        // Enqueue UX scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

        // Add body classes for UX features
        add_filter('admin_body_class', [$this, 'addBodyClasses']);

        // Register AJAX handlers
        add_action('wp_ajax_formflow_bulk_action', [$this, 'handleBulkAction']);
        add_action('wp_ajax_formflow_inline_edit', [$this, 'handleInlineEdit']);
        add_action('wp_ajax_formflow_save_view', [$this, 'handleSaveView']);
        add_action('wp_ajax_formflow_autosave', [$this, 'handleAutosave']);
        add_action('wp_ajax_formflow_ux_settings', [$this, 'handleUXSettings']);
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Save configuration
     *
     * @param array $config Configuration
     * @return bool
     */
    public function saveConfig(array $config): bool
    {
        $sanitized = [];

        foreach ($this->config as $key => $default) {
            if (is_bool($default)) {
                $sanitized[$key] = !empty($config[$key]);
            } elseif (is_int($default)) {
                $sanitized[$key] = (int) ($config[$key] ?? $default);
            } else {
                $sanitized[$key] = sanitize_text_field($config[$key] ?? $default);
            }
        }

        $result = update_option('formflow_ux_settings', $sanitized);

        if ($result) {
            $this->config = $sanitized;
        }

        return $result;
    }

    /**
     * Enqueue assets
     *
     * @param string $hook Current admin page
     * @return void
     */
    public function enqueueAssets(string $hook): void
    {
        // Only on FormFlow pages
        if (strpos($hook, 'formflow') === false) {
            return;
        }

        // Keyboard shortcuts
        if ($this->config['keyboard_shortcuts']) {
            wp_add_inline_script('jquery', $this->getKeyboardShortcutsScript());
        }

        // Inline styles for UX features
        wp_add_inline_style('formflow-pro', $this->getUXStyles());

        // Localize UX config
        wp_localize_script('formflow-pro', 'formflowUX', [
            'config' => $this->config,
            'shortcuts' => $this->getShortcutsList(),
            'strings' => [
                'undo' => __('Undo', 'formflow-pro'),
                'redo' => __('Redo', 'formflow-pro'),
                'saved' => __('Saved', 'formflow-pro'),
                'saving' => __('Saving...', 'formflow-pro'),
                'bulk_confirm' => __('Are you sure you want to perform this action on %d items?', 'formflow-pro'),
            ],
        ]);
    }

    /**
     * Add body classes
     *
     * @param string $classes Current body classes
     * @return string
     */
    public function addBodyClasses(string $classes): string
    {
        if ($this->config['dark_mode']) {
            $classes .= ' formflow-dark-mode';
        }

        if ($this->config['compact_mode']) {
            $classes .= ' formflow-compact-mode';
        }

        if (!$this->config['animations']) {
            $classes .= ' formflow-no-animations';
        }

        return $classes;
    }

    /**
     * Get keyboard shortcuts script
     *
     * @return string
     */
    private function getKeyboardShortcutsScript(): string
    {
        return <<<'JS'
(function($) {
    'use strict';

    var FormFlowShortcuts = {
        init: function() {
            $(document).on('keydown', this.handleKeydown.bind(this));
        },

        handleKeydown: function(e) {
            // Ignore if typing in input
            if ($(e.target).is('input, textarea, select')) {
                return;
            }

            var key = e.key.toLowerCase();
            var ctrl = e.ctrlKey || e.metaKey;
            var shift = e.shiftKey;

            // Ctrl+S - Save
            if (ctrl && key === 's') {
                e.preventDefault();
                this.triggerSave();
                return;
            }

            // Ctrl+Z - Undo
            if (ctrl && !shift && key === 'z') {
                e.preventDefault();
                this.triggerUndo();
                return;
            }

            // Ctrl+Shift+Z or Ctrl+Y - Redo
            if ((ctrl && shift && key === 'z') || (ctrl && key === 'y')) {
                e.preventDefault();
                this.triggerRedo();
                return;
            }

            // Ctrl+F - Focus search
            if (ctrl && key === 'f') {
                e.preventDefault();
                this.focusSearch();
                return;
            }

            // N - New item
            if (key === 'n' && !ctrl) {
                this.triggerNew();
                return;
            }

            // E - Edit selected
            if (key === 'e' && !ctrl) {
                this.triggerEdit();
                return;
            }

            // Delete/Backspace - Delete selected
            if ((key === 'delete' || key === 'backspace') && !ctrl) {
                this.triggerDelete();
                return;
            }

            // Escape - Close modal/cancel
            if (key === 'escape') {
                this.triggerCancel();
                return;
            }

            // ? - Show shortcuts help
            if (shift && key === '/') {
                this.showShortcutsHelp();
                return;
            }
        },

        triggerSave: function() {
            var $saveBtn = $('.formflow-save-btn, #publish, .button-primary[type="submit"]').first();
            if ($saveBtn.length) {
                $saveBtn.click();
            }
        },

        triggerUndo: function() {
            $(document).trigger('formflow:undo');
        },

        triggerRedo: function() {
            $(document).trigger('formflow:redo');
        },

        focusSearch: function() {
            var $search = $('#formflow-search, .search-box input, [name="s"]').first();
            if ($search.length) {
                $search.focus().select();
            }
        },

        triggerNew: function() {
            var $newBtn = $('.formflow-new-btn, .page-title-action').first();
            if ($newBtn.length) {
                $newBtn.click();
            }
        },

        triggerEdit: function() {
            var $selected = $('.formflow-selected, .check-column input:checked').first();
            if ($selected.length) {
                var $editLink = $selected.closest('tr').find('.row-actions .edit a');
                if ($editLink.length) {
                    window.location.href = $editLink.attr('href');
                }
            }
        },

        triggerDelete: function() {
            var $selected = $('.formflow-selected, .check-column input:checked');
            if ($selected.length) {
                if (confirm('Delete ' + $selected.length + ' selected item(s)?')) {
                    $('#bulk-action-selector-top').val('delete');
                    $('#doaction').click();
                }
            }
        },

        triggerCancel: function() {
            var $modal = $('.formflow-modal.active, .media-modal').first();
            if ($modal.length) {
                $modal.find('.close, .media-modal-close').click();
            }
        },

        showShortcutsHelp: function() {
            var html = '<div class="formflow-shortcuts-modal">' +
                '<div class="shortcuts-content">' +
                '<h2>Keyboard Shortcuts</h2>' +
                '<div class="shortcuts-list">' +
                '<div class="shortcut"><kbd>Ctrl</kbd>+<kbd>S</kbd> Save</div>' +
                '<div class="shortcut"><kbd>Ctrl</kbd>+<kbd>Z</kbd> Undo</div>' +
                '<div class="shortcut"><kbd>Ctrl</kbd>+<kbd>Y</kbd> Redo</div>' +
                '<div class="shortcut"><kbd>Ctrl</kbd>+<kbd>F</kbd> Search</div>' +
                '<div class="shortcut"><kbd>N</kbd> New item</div>' +
                '<div class="shortcut"><kbd>E</kbd> Edit selected</div>' +
                '<div class="shortcut"><kbd>Del</kbd> Delete selected</div>' +
                '<div class="shortcut"><kbd>Esc</kbd> Close/Cancel</div>' +
                '<div class="shortcut"><kbd>?</kbd> Show this help</div>' +
                '</div>' +
                '<button class="button close-shortcuts">Close</button>' +
                '</div></div>';

            $(html).appendTo('body').on('click', '.close-shortcuts, .formflow-shortcuts-modal', function(e) {
                if (e.target === this || $(e.target).hasClass('close-shortcuts')) {
                    $('.formflow-shortcuts-modal').remove();
                }
            });
        }
    };

    $(document).ready(function() {
        FormFlowShortcuts.init();
    });
})(jQuery);
JS;
    }

    /**
     * Get UX styles
     *
     * @return string
     */
    private function getUXStyles(): string
    {
        return <<<'CSS'
/* Keyboard shortcuts modal */
.formflow-shortcuts-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
}

.formflow-shortcuts-modal .shortcuts-content {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    max-width: 400px;
    width: 100%;
}

.formflow-shortcuts-modal h2 {
    margin-top: 0;
}

.formflow-shortcuts-modal .shortcuts-list {
    margin: 20px 0;
}

.formflow-shortcuts-modal .shortcut {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.formflow-shortcuts-modal kbd {
    background: #f0f0f0;
    border: 1px solid #ccc;
    border-radius: 3px;
    padding: 2px 6px;
    font-size: 12px;
}

/* Inline editing */
.formflow-inline-edit {
    cursor: pointer;
    border-bottom: 1px dashed #0073aa;
}

.formflow-inline-edit:hover {
    background: #f0f7fc;
}

.formflow-inline-edit-active {
    padding: 5px;
    border: 1px solid #0073aa;
    border-radius: 3px;
    background: #fff;
}

/* Bulk actions */
.formflow-bulk-bar {
    background: #0073aa;
    color: #fff;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    position: sticky;
    top: 32px;
    z-index: 100;
}

.formflow-bulk-bar .selected-count {
    font-weight: 600;
}

.formflow-bulk-bar .bulk-actions {
    display: flex;
    gap: 10px;
}

.formflow-bulk-bar .button {
    background: rgba(255,255,255,0.2);
    border-color: rgba(255,255,255,0.3);
    color: #fff;
}

.formflow-bulk-bar .button:hover {
    background: rgba(255,255,255,0.3);
}

/* Quick actions */
.formflow-quick-action {
    opacity: 0;
    transition: opacity 0.2s;
}

tr:hover .formflow-quick-action {
    opacity: 1;
}

/* Saved views */
.formflow-saved-views {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.formflow-saved-view {
    background: #f0f0f0;
    border: none;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
}

.formflow-saved-view.active {
    background: #0073aa;
    color: #fff;
}

/* Dark mode */
.formflow-dark-mode .wrap {
    background: #1e1e1e;
    color: #e0e0e0;
}

.formflow-dark-mode .card,
.formflow-dark-mode .postbox {
    background: #2d2d2d;
    border-color: #3d3d3d;
}

/* Compact mode */
.formflow-compact-mode .wp-list-table td,
.formflow-compact-mode .wp-list-table th {
    padding: 4px 8px;
}

.formflow-compact-mode .card {
    padding: 10px;
}

/* No animations */
.formflow-no-animations * {
    transition: none !important;
    animation: none !important;
}

/* Autosave indicator */
.formflow-autosave-indicator {
    position: fixed;
    bottom: 20px;
    right: 20px;
    background: #46b450;
    color: #fff;
    padding: 10px 20px;
    border-radius: 4px;
    opacity: 0;
    transition: opacity 0.3s;
}

.formflow-autosave-indicator.visible {
    opacity: 1;
}

/* Tooltips */
[data-tooltip] {
    position: relative;
}

[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: #fff;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}
CSS;
    }

    /**
     * Get shortcuts list
     *
     * @return array
     */
    public function getShortcutsList(): array
    {
        return [
            ['key' => 'Ctrl+S', 'action' => __('Save', 'formflow-pro')],
            ['key' => 'Ctrl+Z', 'action' => __('Undo', 'formflow-pro')],
            ['key' => 'Ctrl+Y', 'action' => __('Redo', 'formflow-pro')],
            ['key' => 'Ctrl+F', 'action' => __('Search', 'formflow-pro')],
            ['key' => 'N', 'action' => __('New item', 'formflow-pro')],
            ['key' => 'E', 'action' => __('Edit selected', 'formflow-pro')],
            ['key' => 'Del', 'action' => __('Delete selected', 'formflow-pro')],
            ['key' => 'Esc', 'action' => __('Close/Cancel', 'formflow-pro')],
            ['key' => '?', 'action' => __('Show shortcuts', 'formflow-pro')],
        ];
    }

    /**
     * Handle bulk action AJAX
     *
     * @return void
     */
    public function handleBulkAction(): void
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));

        if (empty($action) || empty($ids)) {
            wp_send_json_error(['message' => __('Invalid request.', 'formflow-pro')], 400);
        }

        $results = $this->processBulkAction($action, $ids);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %d: number of items */
                __('Action completed on %d items.', 'formflow-pro'),
                $results['processed']
            ),
            'results' => $results,
        ]);
    }

    /**
     * Process bulk action
     *
     * @param string $action Action name
     * @param array $ids Item IDs
     * @return array Results
     */
    private function processBulkAction(string $action, array $ids): array
    {
        global $wpdb;

        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $deleted = $wpdb->delete(
                        $wpdb->prefix . 'formflow_submissions',
                        ['id' => $id],
                        ['%d']
                    );
                    if ($deleted) {
                        $results['processed']++;
                    } else {
                        $results['failed']++;
                    }
                }
                break;

            case 'export':
                // Handle export
                $results['processed'] = count($ids);
                break;

            case 'mark_read':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}formflow_submissions
                     SET status = 'read'
                     WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                    ...$ids
                ));
                $results['processed'] = count($ids);
                break;

            case 'archive':
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}formflow_submissions
                     SET status = 'archived'
                     WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ")",
                    ...$ids
                ));
                $results['processed'] = count($ids);
                break;

            default:
                $results = apply_filters('formflow_bulk_action_' . $action, $results, $ids);
        }

        return $results;
    }

    /**
     * Handle inline edit AJAX
     *
     * @return void
     */
    public function handleInlineEdit(): void
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');
        $table = sanitize_text_field($_POST['table'] ?? 'submissions');

        if (!$id || !$field) {
            wp_send_json_error(['message' => __('Invalid request.', 'formflow-pro')], 400);
        }

        global $wpdb;

        $tableName = $wpdb->prefix . 'formflow_' . $table;

        // Validate table exists
        $tableExists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tableName
        ));

        if (!$tableExists) {
            wp_send_json_error(['message' => __('Invalid table.', 'formflow-pro')], 400);
        }

        // Update field
        $result = $wpdb->update(
            $tableName,
            [$field => $value],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success([
                'message' => __('Updated successfully.', 'formflow-pro'),
                'value' => $value,
            ]);
        }

        wp_send_json_error(['message' => __('Update failed.', 'formflow-pro')], 500);
    }

    /**
     * Handle save view AJAX
     *
     * @return void
     */
    public function handleSaveView(): void
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $filters = isset($_POST['filters']) ? (array) $_POST['filters'] : [];
        $columns = isset($_POST['columns']) ? (array) $_POST['columns'] : [];

        if (empty($name)) {
            wp_send_json_error(['message' => __('View name required.', 'formflow-pro')], 400);
        }

        $views = get_option('formflow_saved_views', []);

        $viewId = sanitize_title($name) . '_' . time();

        $views[$viewId] = [
            'name' => $name,
            'filters' => array_map('sanitize_text_field', $filters),
            'columns' => array_map('sanitize_text_field', $columns),
            'created' => current_time('mysql'),
            'user_id' => get_current_user_id(),
        ];

        update_option('formflow_saved_views', $views);

        wp_send_json_success([
            'message' => __('View saved successfully.', 'formflow-pro'),
            'view_id' => $viewId,
        ]);
    }

    /**
     * Handle autosave AJAX
     *
     * @return void
     */
    public function handleAutosave(): void
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        $type = sanitize_text_field($_POST['type'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $data = isset($_POST['data']) ? (array) $_POST['data'] : [];

        if (empty($type) || empty($data)) {
            wp_send_json_error(['message' => __('Invalid request.', 'formflow-pro')], 400);
        }

        // Store autosave
        $autosaveKey = "formflow_autosave_{$type}_{$id}_" . get_current_user_id();
        set_transient($autosaveKey, $data, 3600);

        wp_send_json_success([
            'message' => __('Autosaved.', 'formflow-pro'),
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Handle UX settings AJAX
     *
     * @return void
     */
    public function handleUXSettings(): void
    {
        check_ajax_referer('formflow_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'formflow-pro')], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            wp_send_json_success($this->config);
            return;
        }

        $config = isset($_POST['config']) ? (array) $_POST['config'] : [];

        if ($this->saveConfig($config)) {
            wp_send_json_success([
                'message' => __('Settings saved.', 'formflow-pro'),
            ]);
        }

        wp_send_json_error(['message' => __('Failed to save settings.', 'formflow-pro')], 500);
    }

    /**
     * Get saved views
     *
     * @return array
     */
    public function getSavedViews(): array
    {
        return get_option('formflow_saved_views', []);
    }

    /**
     * Delete saved view
     *
     * @param string $viewId View ID
     * @return bool
     */
    public function deleteSavedView(string $viewId): bool
    {
        $views = $this->getSavedViews();

        if (!isset($views[$viewId])) {
            return false;
        }

        unset($views[$viewId]);
        return update_option('formflow_saved_views', $views);
    }

    /**
     * Get autosave
     *
     * @param string $type Content type
     * @param int $id Content ID
     * @return array|null
     */
    public function getAutosave(string $type, int $id): ?array
    {
        $autosaveKey = "formflow_autosave_{$type}_{$id}_" . get_current_user_id();
        $data = get_transient($autosaveKey);

        return $data ?: null;
    }

    /**
     * Clear autosave
     *
     * @param string $type Content type
     * @param int $id Content ID
     * @return bool
     */
    public function clearAutosave(string $type, int $id): bool
    {
        $autosaveKey = "formflow_autosave_{$type}_{$id}_" . get_current_user_id();
        return delete_transient($autosaveKey);
    }
}
