<?php
/**
 * FormFlow Pro - Drag & Drop Form Builder
 *
 * Modern visual form builder with real-time preview, conditional logic,
 * multi-step support, and responsive design.
 *
 * @package FormFlowPro
 * @subpackage FormBuilder
 * @since 2.4.0
 */

namespace FormFlowPro\FormBuilder;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Structure Model
 */
class FormStructure
{
    public int $id;
    public string $title;
    public string $description;
    public array $fields;
    public array $steps;
    public array $settings;
    public array $styles;
    public array $logic;
    public array $notifications;
    public string $status;
    public int $version;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->title = $data['title'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->fields = $data['fields'] ?? [];
        $this->steps = $data['steps'] ?? [];
        $this->settings = $data['settings'] ?? $this->getDefaultSettings();
        $this->styles = $data['styles'] ?? $this->getDefaultStyles();
        $this->logic = $data['logic'] ?? [];
        $this->notifications = $data['notifications'] ?? [];
        $this->status = $data['status'] ?? 'draft';
        $this->version = $data['version'] ?? 1;
    }

    public function getDefaultSettings(): array
    {
        return [
            'submit_button_text' => __('Submit', 'form-flow-pro'),
            'success_message' => __('Thank you for your submission!', 'form-flow-pro'),
            'error_message' => __('Please fix the errors below.', 'form-flow-pro'),
            'redirect_url' => '',
            'redirect_enabled' => false,
            'ajax_submit' => true,
            'save_draft' => true,
            'honeypot' => true,
            'captcha' => 'none',
            'limit_submissions' => false,
            'submission_limit' => 0,
            'schedule_enabled' => false,
            'schedule_start' => '',
            'schedule_end' => '',
            'require_login' => false,
            'allowed_roles' => [],
            'multi_step' => false,
            'step_indicator' => 'progress', // progress, steps, dots
            'animation' => 'fade', // fade, slide, none
        ];
    }

    public function getDefaultStyles(): array
    {
        return [
            'theme' => 'default',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#64748b',
            'error_color' => '#ef4444',
            'success_color' => '#22c55e',
            'background_color' => '#ffffff',
            'text_color' => '#1e293b',
            'border_radius' => '8px',
            'field_spacing' => '20px',
            'font_family' => 'inherit',
            'font_size' => '16px',
            'label_position' => 'top', // top, left, floating
            'custom_css' => '',
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'fields' => $this->fields,
            'steps' => $this->steps,
            'settings' => $this->settings,
            'styles' => $this->styles,
            'logic' => $this->logic,
            'notifications' => $this->notifications,
            'status' => $this->status,
            'version' => $this->version,
        ];
    }
}

/**
 * Conditional Logic Rule
 */
class ConditionalRule
{
    public string $id;
    public string $target_field;
    public string $action; // show, hide, enable, disable, set_value, require
    public array $conditions;
    public string $logic; // all, any

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? uniqid('rule_');
        $this->target_field = $data['target_field'] ?? '';
        $this->action = $data['action'] ?? 'show';
        $this->conditions = $data['conditions'] ?? [];
        $this->logic = $data['logic'] ?? 'all';
    }

    public function evaluate(array $form_data): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        $results = [];

        foreach ($this->conditions as $condition) {
            $field_value = $form_data[$condition['field']] ?? null;
            $compare_value = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? 'equals';

            $results[] = $this->evaluateCondition($field_value, $operator, $compare_value);
        }

        return $this->logic === 'all'
            ? !in_array(false, $results, true)
            : in_array(true, $results, true);
    }

    private function evaluateCondition($field_value, string $operator, $compare_value): bool
    {
        switch ($operator) {
            case 'equals':
            case 'is':
                return $field_value == $compare_value;

            case 'not_equals':
            case 'is_not':
                return $field_value != $compare_value;

            case 'contains':
                return is_string($field_value) && strpos($field_value, $compare_value) !== false;

            case 'not_contains':
                return is_string($field_value) && strpos($field_value, $compare_value) === false;

            case 'starts_with':
                return is_string($field_value) && strpos($field_value, $compare_value) === 0;

            case 'ends_with':
                return is_string($field_value) && substr($field_value, -strlen($compare_value)) === $compare_value;

            case 'greater_than':
                return is_numeric($field_value) && $field_value > $compare_value;

            case 'less_than':
                return is_numeric($field_value) && $field_value < $compare_value;

            case 'greater_or_equal':
                return is_numeric($field_value) && $field_value >= $compare_value;

            case 'less_or_equal':
                return is_numeric($field_value) && $field_value <= $compare_value;

            case 'between':
                $range = explode(',', $compare_value);
                return is_numeric($field_value) && count($range) === 2
                    && $field_value >= floatval($range[0])
                    && $field_value <= floatval($range[1]);

            case 'empty':
            case 'is_empty':
                return empty($field_value);

            case 'not_empty':
            case 'is_not_empty':
                return !empty($field_value);

            case 'checked':
                return $field_value === true || $field_value === '1' || $field_value === 'on';

            case 'unchecked':
                return $field_value === false || $field_value === '0' || $field_value === '' || $field_value === null;

            case 'in':
                $values = is_array($compare_value) ? $compare_value : explode(',', $compare_value);
                return in_array($field_value, array_map('trim', $values));

            case 'not_in':
                $values = is_array($compare_value) ? $compare_value : explode(',', $compare_value);
                return !in_array($field_value, array_map('trim', $values));

            case 'matches':
                return is_string($field_value) && preg_match('/' . $compare_value . '/', $field_value);

            default:
                return false;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target_field' => $this->target_field,
            'action' => $this->action,
            'conditions' => $this->conditions,
            'logic' => $this->logic,
        ];
    }
}

/**
 * Drag & Drop Form Builder
 */
class DragDropBuilder
{
    use SingletonTrait;

    private string $forms_table;
    private FieldTypesRegistry $field_registry;

    protected function init(): void
    {
        global $wpdb;

        $this->forms_table = $wpdb->prefix . 'ffp_forms';
        $this->field_registry = FieldTypesRegistry::getInstance();

        $this->createTables();
        $this->registerHooks();
    }

    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->forms_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            form_data LONGTEXT NOT NULL,
            settings LONGTEXT,
            styles LONGTEXT,
            logic LONGTEXT,
            notifications LONGTEXT,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            version INT UNSIGNED DEFAULT 1,
            views INT UNSIGNED DEFAULT 0,
            submissions INT UNSIGNED DEFAULT 0,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_shortcode('formflow', [$this, 'renderFormShortcode']);
        add_action('wp_ajax_ffp_save_form', [$this, 'ajaxSaveForm']);
        add_action('wp_ajax_ffp_duplicate_form', [$this, 'ajaxDuplicateForm']);
        add_action('wp_ajax_ffp_preview_form', [$this, 'ajaxPreviewForm']);
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('FormFlow Pro', 'form-flow-pro'),
            __('FormFlow Pro', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro',
            [$this, 'renderFormsList'],
            'dashicons-feedback',
            30
        );

        add_submenu_page(
            'formflow-pro',
            __('All Forms', 'form-flow-pro'),
            __('All Forms', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro',
            [$this, 'renderFormsList']
        );

        add_submenu_page(
            'formflow-pro',
            __('Add New', 'form-flow-pro'),
            __('Add New', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro-new',
            [$this, 'renderFormBuilder']
        );

        add_submenu_page(
            'formflow-pro',
            __('Entries', 'form-flow-pro'),
            __('Entries', 'form-flow-pro'),
            'edit_posts',
            'formflow-pro-entries',
            [$this, 'renderEntries']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook): void
    {
        if (strpos($hook, 'formflow-pro') === false) {
            return;
        }

        // Enqueue builder CSS
        wp_enqueue_style(
            'ffp-builder',
            plugins_url('assets/css/builder.css', dirname(__DIR__)),
            [],
            JEFORM_VERSION
        );

        // Enqueue builder JS
        wp_enqueue_script(
            'ffp-builder',
            plugins_url('assets/js/builder.js', dirname(__DIR__)),
            ['jquery', 'wp-element', 'wp-components', 'wp-i18n'],
            JEFORM_VERSION,
            true
        );

        // Localize script
        wp_localize_script('ffp-builder', 'ffpBuilder', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('form-flow-pro/v1'),
            'nonce' => wp_create_nonce('ffp_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'fieldTypes' => $this->field_registry->getSchemas(),
            'categories' => $this->field_registry->getCategories(),
            'strings' => $this->getLocalizedStrings(),
            'formId' => isset($_GET['form_id']) ? intval($_GET['form_id']) : 0,
        ]);

        // Additional libraries
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');
        wp_enqueue_script('jquery-ui-droppable');
    }

    /**
     * Get localized strings
     */
    private function getLocalizedStrings(): array
    {
        return [
            'addField' => __('Add Field', 'form-flow-pro'),
            'fieldSettings' => __('Field Settings', 'form-flow-pro'),
            'formSettings' => __('Form Settings', 'form-flow-pro'),
            'preview' => __('Preview', 'form-flow-pro'),
            'save' => __('Save', 'form-flow-pro'),
            'publish' => __('Publish', 'form-flow-pro'),
            'duplicate' => __('Duplicate', 'form-flow-pro'),
            'delete' => __('Delete', 'form-flow-pro'),
            'confirmDelete' => __('Are you sure you want to delete this field?', 'form-flow-pro'),
            'unsavedChanges' => __('You have unsaved changes. Are you sure you want to leave?', 'form-flow-pro'),
            'saving' => __('Saving...', 'form-flow-pro'),
            'saved' => __('Saved', 'form-flow-pro'),
            'error' => __('Error', 'form-flow-pro'),
            'dragHere' => __('Drag fields here to build your form', 'form-flow-pro'),
            'required' => __('Required', 'form-flow-pro'),
            'optional' => __('Optional', 'form-flow-pro'),
            'conditionalLogic' => __('Conditional Logic', 'form-flow-pro'),
            'addCondition' => __('Add Condition', 'form-flow-pro'),
            'addStep' => __('Add Step', 'form-flow-pro'),
            'step' => __('Step', 'form-flow-pro'),
        ];
    }

    /**
     * Create a new form
     */
    public function createForm(array $data): ?FormStructure
    {
        global $wpdb;

        $form = new FormStructure($data);

        $form_data = [
            'title' => sanitize_text_field($form->title ?: __('Untitled Form', 'form-flow-pro')),
            'description' => sanitize_textarea_field($form->description),
            'form_data' => json_encode($form->toArray()),
            'settings' => json_encode($form->settings),
            'styles' => json_encode($form->styles),
            'logic' => json_encode($form->logic),
            'notifications' => json_encode($form->notifications),
            'status' => $form->status,
            'version' => 1,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $inserted = $wpdb->insert($this->forms_table, $form_data);

        if (!$inserted) {
            return null;
        }

        $form->id = $wpdb->insert_id;

        // Create initial version
        $versioning = FormVersioning::getInstance();
        $versioning->createVersion($form->id, $form->toArray(), [
            'label' => 'v1 - Initial',
            'summary' => __('Form created', 'form-flow-pro'),
        ]);

        do_action('ffp_form_created', $form->id, $form);

        return $form;
    }

    /**
     * Get form by ID
     */
    public function getForm(int $form_id): ?FormStructure
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->forms_table} WHERE id = %d",
            $form_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToForm($row);
    }

    /**
     * Update form
     */
    public function updateForm(int $form_id, array $data): ?FormStructure
    {
        global $wpdb;

        $existing = $this->getForm($form_id);

        if (!$existing) {
            return null;
        }

        // Merge with existing data
        $form = new FormStructure(array_merge($existing->toArray(), $data));
        $form->id = $form_id;
        $form->version = $existing->version + 1;

        $update_data = [
            'title' => sanitize_text_field($form->title),
            'description' => sanitize_textarea_field($form->description),
            'form_data' => json_encode($form->toArray()),
            'settings' => json_encode($form->settings),
            'styles' => json_encode($form->styles),
            'logic' => json_encode($form->logic),
            'notifications' => json_encode($form->notifications),
            'status' => $form->status,
            'version' => $form->version,
            'updated_at' => current_time('mysql'),
        ];

        if ($form->status === 'published' && $existing->status !== 'published') {
            $update_data['published_at'] = current_time('mysql');
        }

        $wpdb->update($this->forms_table, $update_data, ['id' => $form_id]);

        // Create version snapshot
        $versioning = FormVersioning::getInstance();
        $versioning->createVersion($form_id, $form->toArray(), [
            'label' => sprintf('v%d', $form->version),
            'publish' => $form->status === 'published',
        ]);

        do_action('ffp_form_updated', $form_id, $form);

        return $form;
    }

    /**
     * Delete form
     */
    public function deleteForm(int $form_id): bool
    {
        global $wpdb;

        $form = $this->getForm($form_id);

        if (!$form) {
            return false;
        }

        $deleted = $wpdb->delete($this->forms_table, ['id' => $form_id]);

        if ($deleted) {
            do_action('ffp_form_deleted', $form_id, $form);
            return true;
        }

        return false;
    }

    /**
     * Duplicate form
     */
    public function duplicateForm(int $form_id): ?FormStructure
    {
        $original = $this->getForm($form_id);

        if (!$original) {
            return null;
        }

        $data = $original->toArray();
        $data['title'] = sprintf(__('%s (Copy)', 'form-flow-pro'), $original->title);
        $data['status'] = 'draft';
        unset($data['id']);

        // Generate new IDs for fields
        foreach ($data['fields'] as &$field) {
            $field['id'] = uniqid('field_');
        }

        return $this->createForm($data);
    }

    /**
     * Get forms list
     */
    public function getForms(array $options = []): array
    {
        global $wpdb;

        $status = $options['status'] ?? null;
        $search = $options['search'] ?? '';
        $orderby = $options['orderby'] ?? 'created_at';
        $order = $options['order'] ?? 'DESC';
        $limit = $options['limit'] ?? 20;
        $offset = $options['offset'] ?? 0;

        $sql = "SELECT * FROM {$this->forms_table} WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        if ($search) {
            $sql .= " AND (title LIKE %s OR description LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $sql .= " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map([$this, 'rowToForm'], $rows);
    }

    /**
     * Count forms
     */
    public function countForms(string $status = null): int
    {
        global $wpdb;

        if ($status) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->forms_table} WHERE status = %s",
                $status
            ));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->forms_table}");
    }

    /**
     * Convert database row to FormStructure
     */
    private function rowToForm(array $row): FormStructure
    {
        $form_data = json_decode($row['form_data'] ?? '{}', true) ?? [];

        return new FormStructure([
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'fields' => $form_data['fields'] ?? [],
            'steps' => $form_data['steps'] ?? [],
            'settings' => json_decode($row['settings'] ?? '{}', true) ?? [],
            'styles' => json_decode($row['styles'] ?? '{}', true) ?? [],
            'logic' => json_decode($row['logic'] ?? '[]', true) ?? [],
            'notifications' => json_decode($row['notifications'] ?? '[]', true) ?? [],
            'status' => $row['status'],
            'version' => (int) $row['version'],
        ]);
    }

    /**
     * Render form shortcode
     */
    public function renderFormShortcode($atts): string
    {
        $atts = shortcode_atts([
            'id' => 0,
            'title' => true,
            'description' => true,
            'ajax' => true,
        ], $atts);

        $form_id = intval($atts['id']);

        if (!$form_id) {
            return '<p class="ffp-error">' . __('Form ID is required.', 'form-flow-pro') . '</p>';
        }

        $form = $this->getForm($form_id);

        if (!$form) {
            return '<p class="ffp-error">' . __('Form not found.', 'form-flow-pro') . '</p>';
        }

        if ($form->status !== 'published') {
            if (!current_user_can('edit_posts')) {
                return '<p class="ffp-error">' . __('This form is not available.', 'form-flow-pro') . '</p>';
            }
        }

        // Apply A/B testing variant
        $form_data = apply_filters('ffp_render_form', $form->toArray(), $form_id);

        return $this->renderForm($form_data, $atts);
    }

    /**
     * Render form HTML
     */
    public function renderForm(array $form_data, array $options = []): string
    {
        $form_id = $form_data['id'] ?? 0;
        $settings = $form_data['settings'] ?? [];
        $styles = $form_data['styles'] ?? [];
        $fields = $form_data['fields'] ?? [];
        $steps = $form_data['steps'] ?? [];
        $logic = $form_data['logic'] ?? [];

        // Check access restrictions
        if (!$this->checkFormAccess($settings)) {
            return '<p class="ffp-error">' . __('You do not have permission to view this form.', 'form-flow-pro') . '</p>';
        }

        // Check schedule
        if (!$this->checkFormSchedule($settings)) {
            return '<p class="ffp-error">' . __('This form is not currently available.', 'form-flow-pro') . '</p>';
        }

        // Check submission limit
        if (!$this->checkSubmissionLimit($form_id, $settings)) {
            return '<p class="ffp-error">' . __('This form has reached its submission limit.', 'form-flow-pro') . '</p>';
        }

        // Enqueue frontend assets
        $this->enqueueFrontendAssets();

        // Build form HTML
        $html = $this->buildFormHTML($form_data, $options);

        // Add inline styles
        $html .= $this->buildFormStyles($form_id, $styles);

        // Add conditional logic script
        if (!empty($logic)) {
            $html .= $this->buildConditionalLogicScript($form_id, $logic);
        }

        return $html;
    }

    /**
     * Build form HTML structure
     */
    private function buildFormHTML(array $form_data, array $options): string
    {
        $form_id = $form_data['id'] ?? 0;
        $settings = $form_data['settings'] ?? [];
        $fields = $form_data['fields'] ?? [];
        $steps = $form_data['steps'] ?? [];
        $is_multi_step = !empty($settings['multi_step']) && !empty($steps);

        $form_class = 'ffp-form ffp-form-' . $form_id;
        $form_class .= ' ffp-label-' . ($settings['label_position'] ?? 'top');

        if ($is_multi_step) {
            $form_class .= ' ffp-multi-step';
        }

        $html = '<div class="ffp-form-wrapper" id="ffp-form-wrapper-' . $form_id . '">';

        // Title
        if (!empty($options['title']) && !empty($form_data['title'])) {
            $html .= '<h2 class="ffp-form-title">' . esc_html($form_data['title']) . '</h2>';
        }

        // Description
        if (!empty($options['description']) && !empty($form_data['description'])) {
            $html .= '<p class="ffp-form-description">' . esc_html($form_data['description']) . '</p>';
        }

        // Success/Error messages container
        $html .= '<div class="ffp-messages"></div>';

        // Form start
        $html .= '<form class="' . esc_attr($form_class) . '" id="ffp-form-' . $form_id . '" method="post"';
        $html .= ' data-form-id="' . $form_id . '"';
        $html .= ' data-ajax="' . (!empty($settings['ajax_submit']) ? 'true' : 'false') . '"';
        $html .= ' enctype="multipart/form-data">';

        // Security fields
        $html .= wp_nonce_field('ffp_submit_form_' . $form_id, 'ffp_nonce', true, false);
        $html .= '<input type="hidden" name="ffp_form_id" value="' . $form_id . '">';

        // Honeypot
        if (!empty($settings['honeypot'])) {
            $html .= '<div class="ffp-hp" style="position:absolute;left:-9999px;" aria-hidden="true">';
            $html .= '<input type="text" name="ffp_hp_' . $form_id . '" tabindex="-1" autocomplete="off">';
            $html .= '</div>';
        }

        // Multi-step progress
        if ($is_multi_step) {
            $html .= $this->buildStepIndicator($steps, $settings['step_indicator'] ?? 'progress');
        }

        // Form fields
        if ($is_multi_step) {
            $html .= $this->buildMultiStepFields($fields, $steps);
        } else {
            $html .= '<div class="ffp-form-fields">';
            $html .= $this->buildFields($fields);
            $html .= '</div>';
        }

        // Submit button
        $html .= '<div class="ffp-form-actions">';

        if ($is_multi_step) {
            $html .= '<button type="button" class="ffp-btn ffp-btn-prev" style="display:none;">';
            $html .= esc_html__('Previous', 'form-flow-pro');
            $html .= '</button>';

            $html .= '<button type="button" class="ffp-btn ffp-btn-next">';
            $html .= esc_html__('Next', 'form-flow-pro');
            $html .= '</button>';
        }

        $html .= '<button type="submit" class="ffp-btn ffp-btn-submit"';
        if ($is_multi_step) $html .= ' style="display:none;"';
        $html .= '>';
        $html .= '<span class="ffp-btn-text">' . esc_html($settings['submit_button_text'] ?? __('Submit', 'form-flow-pro')) . '</span>';
        $html .= '<span class="ffp-btn-loading" style="display:none;"></span>';
        $html .= '</button>';

        $html .= '</div>';

        $html .= '</form>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Build step indicator
     */
    private function buildStepIndicator(array $steps, string $type): string
    {
        $html = '<div class="ffp-step-indicator ffp-step-indicator-' . esc_attr($type) . '">';

        switch ($type) {
            case 'progress':
                $html .= '<div class="ffp-progress-bar">';
                $html .= '<div class="ffp-progress-fill" style="width: ' . (100 / count($steps)) . '%"></div>';
                $html .= '</div>';
                $html .= '<div class="ffp-progress-text">';
                $html .= sprintf(__('Step %d of %d', 'form-flow-pro'), 1, count($steps));
                $html .= '</div>';
                break;

            case 'steps':
                foreach ($steps as $index => $step) {
                    $class = $index === 0 ? 'ffp-step active' : 'ffp-step';
                    $html .= '<div class="' . $class . '" data-step="' . $index . '">';
                    $html .= '<span class="ffp-step-number">' . ($index + 1) . '</span>';
                    $html .= '<span class="ffp-step-title">' . esc_html($step['title'] ?? '') . '</span>';
                    $html .= '</div>';
                }
                break;

            case 'dots':
                foreach ($steps as $index => $step) {
                    $class = $index === 0 ? 'ffp-dot active' : 'ffp-dot';
                    $html .= '<span class="' . $class . '" data-step="' . $index . '"></span>';
                }
                break;
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Build multi-step fields
     */
    private function buildMultiStepFields(array $fields, array $steps): string
    {
        $html = '';
        $step_fields = $this->organizeFieldsByStep($fields, $steps);

        foreach ($steps as $index => $step) {
            $class = $index === 0 ? 'ffp-step-content active' : 'ffp-step-content';
            $html .= '<div class="' . $class . '" data-step="' . $index . '">';

            if (!empty($step['title'])) {
                $html .= '<h3 class="ffp-step-title">' . esc_html($step['title']) . '</h3>';
            }

            if (!empty($step['description'])) {
                $html .= '<p class="ffp-step-description">' . esc_html($step['description']) . '</p>';
            }

            $html .= '<div class="ffp-form-fields">';
            $html .= $this->buildFields($step_fields[$index] ?? []);
            $html .= '</div>';

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Organize fields by step
     */
    private function organizeFieldsByStep(array $fields, array $steps): array
    {
        $organized = [];

        foreach ($steps as $index => $step) {
            $organized[$index] = [];
        }

        foreach ($fields as $field) {
            $step_index = $field['step'] ?? 0;
            $organized[$step_index][] = $field;
        }

        return $organized;
    }

    /**
     * Build fields HTML
     */
    private function buildFields(array $fields): string
    {
        $html = '';

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $html .= $this->field_registry->render($type, $field);
        }

        return $html;
    }

    /**
     * Build form styles
     */
    private function buildFormStyles(int $form_id, array $styles): string
    {
        $css = '
        #ffp-form-wrapper-' . $form_id . ' {
            --ffp-primary: ' . esc_attr($styles['primary_color'] ?? '#3b82f6') . ';
            --ffp-secondary: ' . esc_attr($styles['secondary_color'] ?? '#64748b') . ';
            --ffp-error: ' . esc_attr($styles['error_color'] ?? '#ef4444') . ';
            --ffp-success: ' . esc_attr($styles['success_color'] ?? '#22c55e') . ';
            --ffp-background: ' . esc_attr($styles['background_color'] ?? '#ffffff') . ';
            --ffp-text: ' . esc_attr($styles['text_color'] ?? '#1e293b') . ';
            --ffp-radius: ' . esc_attr($styles['border_radius'] ?? '8px') . ';
            --ffp-spacing: ' . esc_attr($styles['field_spacing'] ?? '20px') . ';
            --ffp-font: ' . esc_attr($styles['font_family'] ?? 'inherit') . ';
            --ffp-font-size: ' . esc_attr($styles['font_size'] ?? '16px') . ';
        }';

        // Custom CSS
        if (!empty($styles['custom_css'])) {
            $css .= "\n" . $styles['custom_css'];
        }

        return '<style>' . $css . '</style>';
    }

    /**
     * Build conditional logic script
     */
    private function buildConditionalLogicScript(int $form_id, array $logic): string
    {
        $rules = array_map(function($rule) {
            return (new ConditionalRule($rule))->toArray();
        }, $logic);

        return '<script>
            if (typeof ffpConditionalLogic === "undefined") { var ffpConditionalLogic = {}; }
            ffpConditionalLogic[' . $form_id . '] = ' . json_encode($rules) . ';
        </script>';
    }

    /**
     * Check form access
     */
    private function checkFormAccess(array $settings): bool
    {
        if (empty($settings['require_login'])) {
            return true;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        if (empty($settings['allowed_roles'])) {
            return true;
        }

        $user = wp_get_current_user();
        return !empty(array_intersect($settings['allowed_roles'], $user->roles));
    }

    /**
     * Check form schedule
     */
    private function checkFormSchedule(array $settings): bool
    {
        if (empty($settings['schedule_enabled'])) {
            return true;
        }

        $now = current_time('timestamp');

        if (!empty($settings['schedule_start'])) {
            $start = strtotime($settings['schedule_start']);
            if ($now < $start) {
                return false;
            }
        }

        if (!empty($settings['schedule_end'])) {
            $end = strtotime($settings['schedule_end']);
            if ($now > $end) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check submission limit
     */
    private function checkSubmissionLimit(int $form_id, array $settings): bool
    {
        if (empty($settings['limit_submissions']) || empty($settings['submission_limit'])) {
            return true;
        }

        global $wpdb;

        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT submissions FROM {$this->forms_table} WHERE id = %d",
            $form_id
        ));

        return $count < $settings['submission_limit'];
    }

    /**
     * Enqueue frontend assets
     */
    private function enqueueFrontendAssets(): void
    {
        wp_enqueue_style(
            'ffp-frontend',
            plugins_url('assets/css/frontend.css', dirname(__DIR__)),
            [],
            JEFORM_VERSION
        );

        wp_enqueue_script(
            'ffp-frontend',
            plugins_url('assets/js/frontend.js', dirname(__DIR__)),
            ['jquery'],
            JEFORM_VERSION,
            true
        );

        wp_localize_script('ffp-frontend', 'ffpFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ffp_submit'),
        ]);
    }

    /**
     * Render forms list page
     */
    public function renderFormsList(): void
    {
        $forms = $this->getForms();
        $counts = [
            'all' => $this->countForms(),
            'published' => $this->countForms('published'),
            'draft' => $this->countForms('draft'),
        ];

        include JEFORM_PLUGIN_DIR . 'templates/admin/forms-list.php';
    }

    /**
     * Render form builder page
     */
    public function renderFormBuilder(): void
    {
        $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        $form = $form_id ? $this->getForm($form_id) : null;

        include JEFORM_PLUGIN_DIR . 'templates/admin/form-builder.php';
    }

    /**
     * Render entries page
     */
    public function renderEntries(): void
    {
        include JEFORM_PLUGIN_DIR . 'templates/admin/entries.php';
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('form-flow-pro/v1', '/forms', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetForms'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/forms/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'restUpdateForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteForm'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/forms/(?P<id>\d+)/duplicate', [
            'methods' => 'POST',
            'callback' => [$this, 'restDuplicateForm'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restGetForms(\WP_REST_Request $request): \WP_REST_Response
    {
        $forms = $this->getForms([
            'status' => $request->get_param('status'),
            'search' => $request->get_param('search'),
            'limit' => $request->get_param('per_page') ?? 20,
            'offset' => (($request->get_param('page') ?? 1) - 1) * ($request->get_param('per_page') ?? 20),
        ]);

        return new \WP_REST_Response(array_map(function($f) { return $f->toArray(); }, $forms));
    }

    public function restCreateForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $form = $this->createForm($data);

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Failed to create form'], 500);
        }

        return new \WP_REST_Response($form->toArray(), 201);
    }

    public function restGetForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $form = $this->getForm((int) $request->get_param('id'));

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Form not found'], 404);
        }

        return new \WP_REST_Response($form->toArray());
    }

    public function restUpdateForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('id');
        $data = $request->get_json_params();

        $form = $this->updateForm($form_id, $data);

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Failed to update form'], 500);
        }

        return new \WP_REST_Response($form->toArray());
    }

    public function restDeleteForm(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($this->deleteForm((int) $request->get_param('id'))) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Failed to delete form'], 500);
    }

    public function restDuplicateForm(\WP_REST_Request $request): \WP_REST_Response
    {
        $form = $this->duplicateForm((int) $request->get_param('id'));

        if (!$form) {
            return new \WP_REST_Response(['error' => 'Failed to duplicate form'], 500);
        }

        return new \WP_REST_Response($form->toArray(), 201);
    }

    /**
     * AJAX save form
     */
    public function ajaxSaveForm(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $form_data = json_decode(stripslashes($_POST['form_data'] ?? '{}'), true);

        if ($form_id) {
            $form = $this->updateForm($form_id, $form_data);
        } else {
            $form = $this->createForm($form_data);
        }

        if ($form) {
            wp_send_json_success($form->toArray());
        } else {
            wp_send_json_error(['message' => 'Failed to save form']);
        }
    }

    /**
     * AJAX duplicate form
     */
    public function ajaxDuplicateForm(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $form = $this->duplicateForm($form_id);

        if ($form) {
            wp_send_json_success($form->toArray());
        } else {
            wp_send_json_error(['message' => 'Failed to duplicate form']);
        }
    }

    /**
     * AJAX preview form
     */
    public function ajaxPreviewForm(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        $form_data = json_decode(stripslashes($_POST['form_data'] ?? '{}'), true);

        $html = $this->renderForm($form_data, [
            'title' => true,
            'description' => true,
        ]);

        wp_send_json_success(['html' => $html]);
    }
}
