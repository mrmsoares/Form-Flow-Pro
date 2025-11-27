<?php
/**
 * FormFlow Pro - Form Versioning System
 *
 * Provides complete version control for forms including snapshots,
 * comparisons, rollback, and branching capabilities.
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
 * Form Version Model
 */
class FormVersion
{
    public int $id;
    public int $form_id;
    public int $version_number;
    public string $version_label;
    public array $form_data;
    public array $settings;
    public array $fields;
    public string $change_summary;
    public int $created_by;
    public string $created_at;
    public bool $is_published;
    public ?int $parent_version_id;
    public string $branch_name;
    public string $checksum;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id ?? 0,
            'form_id' => $this->form_id ?? 0,
            'version_number' => $this->version_number ?? 1,
            'version_label' => $this->version_label ?? '',
            'form_data' => $this->form_data ?? [],
            'settings' => $this->settings ?? [],
            'fields' => $this->fields ?? [],
            'change_summary' => $this->change_summary ?? '',
            'created_by' => $this->created_by ?? 0,
            'created_at' => $this->created_at ?? '',
            'is_published' => $this->is_published ?? false,
            'parent_version_id' => $this->parent_version_id ?? null,
            'branch_name' => $this->branch_name ?? 'main',
            'checksum' => $this->checksum ?? '',
        ];
    }
}

/**
 * Form Versioning Manager
 */
class FormVersioning
{
    use SingletonTrait;

    private string $versions_table;
    private string $changes_table;

    protected function init(): void
    {
        global $wpdb;

        $this->versions_table = $wpdb->prefix . 'ffp_form_versions';
        $this->changes_table = $wpdb->prefix . 'ffp_version_changes';

        $this->createTables();
        $this->registerHooks();
    }

    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->versions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL DEFAULT 1,
            version_label VARCHAR(100) DEFAULT '',
            form_data LONGTEXT NOT NULL,
            settings LONGTEXT,
            fields LONGTEXT,
            change_summary TEXT,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_published TINYINT(1) NOT NULL DEFAULT 0,
            parent_version_id BIGINT UNSIGNED DEFAULT NULL,
            branch_name VARCHAR(100) DEFAULT 'main',
            checksum VARCHAR(64) NOT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY version_number (form_id, version_number),
            KEY branch_name (form_id, branch_name),
            KEY is_published (form_id, is_published),
            KEY created_at (created_at)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->changes_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            version_id BIGINT UNSIGNED NOT NULL,
            change_type ENUM('add', 'modify', 'delete', 'reorder') NOT NULL,
            element_type ENUM('field', 'setting', 'style', 'logic', 'notification') NOT NULL,
            element_id VARCHAR(100) NOT NULL,
            element_name VARCHAR(255) DEFAULT '',
            old_value LONGTEXT,
            new_value LONGTEXT,
            path VARCHAR(500) DEFAULT '',
            PRIMARY KEY (id),
            KEY version_id (version_id),
            KEY change_type (change_type),
            KEY element_type (element_type)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_ffp_create_version', [$this, 'ajaxCreateVersion']);
        add_action('wp_ajax_ffp_rollback_version', [$this, 'ajaxRollbackVersion']);
        add_action('wp_ajax_ffp_compare_versions', [$this, 'ajaxCompareVersions']);
    }

    /**
     * Create a new version snapshot
     */
    public function createVersion(int $form_id, array $form_data, array $options = []): ?FormVersion
    {
        global $wpdb;

        // Get current version number
        $current_version = $this->getLatestVersionNumber($form_id, $options['branch'] ?? 'main');
        $new_version_number = $current_version + 1;

        // Calculate checksum for integrity
        $checksum = $this->calculateChecksum($form_data);

        // Get previous version for change detection
        $previous_version = $this->getVersion($form_id, $current_version, $options['branch'] ?? 'main');

        // Detect changes
        $changes = [];
        if ($previous_version) {
            $changes = $this->detectChanges($previous_version->form_data, $form_data);
        }

        // Auto-generate change summary if not provided
        $change_summary = $options['summary'] ?? $this->generateChangeSummary($changes);

        $version_data = [
            'form_id' => $form_id,
            'version_number' => $new_version_number,
            'version_label' => $options['label'] ?? sprintf('v%d', $new_version_number),
            'form_data' => json_encode($form_data),
            'settings' => json_encode($form_data['settings'] ?? []),
            'fields' => json_encode($form_data['fields'] ?? []),
            'change_summary' => $change_summary,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'is_published' => $options['publish'] ?? false,
            'parent_version_id' => $previous_version->id ?? null,
            'branch_name' => $options['branch'] ?? 'main',
            'checksum' => $checksum,
        ];

        $inserted = $wpdb->insert($this->versions_table, $version_data);

        if (!$inserted) {
            return null;
        }

        $version_id = $wpdb->insert_id;

        // Store detailed changes
        $this->storeChanges($version_id, $changes);

        // Trigger action
        do_action('ffp_version_created', $version_id, $form_id, $new_version_number);

        return $this->getVersionById($version_id);
    }

    /**
     * Get version by ID
     */
    public function getVersionById(int $version_id): ?FormVersion
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->versions_table} WHERE id = %d",
            $version_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToVersion($row);
    }

    /**
     * Get version by form ID and version number
     */
    public function getVersion(int $form_id, int $version_number, string $branch = 'main'): ?FormVersion
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->versions_table}
             WHERE form_id = %d AND version_number = %d AND branch_name = %s",
            $form_id,
            $version_number,
            $branch
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToVersion($row);
    }

    /**
     * Get latest version for a form
     */
    public function getLatestVersion(int $form_id, string $branch = 'main'): ?FormVersion
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->versions_table}
             WHERE form_id = %d AND branch_name = %s
             ORDER BY version_number DESC LIMIT 1",
            $form_id,
            $branch
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToVersion($row);
    }

    /**
     * Get published version
     */
    public function getPublishedVersion(int $form_id): ?FormVersion
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->versions_table}
             WHERE form_id = %d AND is_published = 1
             ORDER BY created_at DESC LIMIT 1",
            $form_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToVersion($row);
    }

    /**
     * Get all versions for a form
     */
    public function getVersionHistory(int $form_id, array $options = []): array
    {
        global $wpdb;

        $branch = $options['branch'] ?? null;
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        $sql = "SELECT * FROM {$this->versions_table} WHERE form_id = %d";
        $params = [$form_id];

        if ($branch) {
            $sql .= " AND branch_name = %s";
            $params[] = $branch;
        }

        $sql .= " ORDER BY version_number DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map([$this, 'rowToVersion'], $rows);
    }

    /**
     * Get version count
     */
    public function getVersionCount(int $form_id, ?string $branch = null): int
    {
        global $wpdb;

        if ($branch) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->versions_table} WHERE form_id = %d AND branch_name = %s",
                $form_id,
                $branch
            ));
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->versions_table} WHERE form_id = %d",
            $form_id
        ));
    }

    /**
     * Rollback to a specific version
     */
    public function rollback(int $form_id, int $version_id, array $options = []): ?FormVersion
    {
        $target_version = $this->getVersionById($version_id);

        if (!$target_version || $target_version->form_id !== $form_id) {
            return null;
        }

        // Create new version with rolled back data
        $new_version = $this->createVersion($form_id, $target_version->form_data, [
            'label' => $options['label'] ?? sprintf('Rollback to v%d', $target_version->version_number),
            'summary' => sprintf(__('Rolled back to version %d', 'form-flow-pro'), $target_version->version_number),
            'branch' => $options['branch'] ?? $target_version->branch_name,
            'publish' => $options['publish'] ?? false,
        ]);

        if ($new_version) {
            do_action('ffp_version_rollback', $form_id, $version_id, $new_version->id);
        }

        return $new_version;
    }

    /**
     * Publish a specific version
     */
    public function publish(int $version_id): bool
    {
        global $wpdb;

        $version = $this->getVersionById($version_id);

        if (!$version) {
            return false;
        }

        // Unpublish all other versions for this form
        $wpdb->update(
            $this->versions_table,
            ['is_published' => 0],
            ['form_id' => $version->form_id]
        );

        // Publish this version
        $updated = $wpdb->update(
            $this->versions_table,
            ['is_published' => 1],
            ['id' => $version_id]
        );

        if ($updated !== false) {
            do_action('ffp_version_published', $version_id, $version->form_id);
            return true;
        }

        return false;
    }

    /**
     * Create a branch from a version
     */
    public function createBranch(int $version_id, string $branch_name): ?FormVersion
    {
        $source_version = $this->getVersionById($version_id);

        if (!$source_version) {
            return null;
        }

        // Check if branch already exists
        $existing = $this->getLatestVersion($source_version->form_id, $branch_name);
        if ($existing) {
            return null; // Branch already exists
        }

        return $this->createVersion($source_version->form_id, $source_version->form_data, [
            'label' => sprintf('Branch: %s (from v%d)', $branch_name, $source_version->version_number),
            'summary' => sprintf(__('Created branch %s from version %d', 'form-flow-pro'), $branch_name, $source_version->version_number),
            'branch' => $branch_name,
        ]);
    }

    /**
     * Merge a branch into another
     */
    public function mergeBranch(int $form_id, string $source_branch, string $target_branch = 'main', array $options = []): ?FormVersion
    {
        $source_version = $this->getLatestVersion($form_id, $source_branch);
        $target_version = $this->getLatestVersion($form_id, $target_branch);

        if (!$source_version) {
            return null;
        }

        // Simple merge - take source data and create new version in target
        $merged_data = $source_version->form_data;

        // If strategy is 'combine', merge the fields
        if (($options['strategy'] ?? 'replace') === 'combine' && $target_version) {
            $merged_data = $this->mergeFormData($target_version->form_data, $source_version->form_data);
        }

        return $this->createVersion($form_id, $merged_data, [
            'label' => sprintf('Merge: %s → %s', $source_branch, $target_branch),
            'summary' => sprintf(__('Merged %s into %s', 'form-flow-pro'), $source_branch, $target_branch),
            'branch' => $target_branch,
        ]);
    }

    /**
     * Compare two versions
     */
    public function compareVersions(int $version_id_a, int $version_id_b): array
    {
        $version_a = $this->getVersionById($version_id_a);
        $version_b = $this->getVersionById($version_id_b);

        if (!$version_a || !$version_b) {
            return ['error' => 'Version not found'];
        }

        $changes = $this->detectChanges($version_a->form_data, $version_b->form_data);

        return [
            'version_a' => $version_a->toArray(),
            'version_b' => $version_b->toArray(),
            'changes' => $changes,
            'summary' => $this->generateChangeSummary($changes),
            'statistics' => $this->getChangeStatistics($changes),
        ];
    }

    /**
     * Get changes for a version
     */
    public function getVersionChanges(int $version_id): array
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->changes_table} WHERE version_id = %d ORDER BY id ASC",
            $version_id
        ), ARRAY_A);
    }

    /**
     * Detect changes between two form data arrays
     */
    public function detectChanges(array $old_data, array $new_data, string $path = ''): array
    {
        $changes = [];

        // Check fields
        $old_fields = $this->indexById($old_data['fields'] ?? []);
        $new_fields = $this->indexById($new_data['fields'] ?? []);

        // Added fields
        foreach ($new_fields as $id => $field) {
            if (!isset($old_fields[$id])) {
                $changes[] = [
                    'type' => 'add',
                    'element_type' => 'field',
                    'element_id' => $id,
                    'element_name' => $field['label'] ?? $field['name'] ?? '',
                    'old_value' => null,
                    'new_value' => $field,
                    'path' => 'fields.' . $id,
                ];
            }
        }

        // Deleted fields
        foreach ($old_fields as $id => $field) {
            if (!isset($new_fields[$id])) {
                $changes[] = [
                    'type' => 'delete',
                    'element_type' => 'field',
                    'element_id' => $id,
                    'element_name' => $field['label'] ?? $field['name'] ?? '',
                    'old_value' => $field,
                    'new_value' => null,
                    'path' => 'fields.' . $id,
                ];
            }
        }

        // Modified fields
        foreach ($new_fields as $id => $new_field) {
            if (isset($old_fields[$id])) {
                $old_field = $old_fields[$id];
                $field_changes = $this->compareArrays($old_field, $new_field, 'fields.' . $id);

                if (!empty($field_changes)) {
                    $changes[] = [
                        'type' => 'modify',
                        'element_type' => 'field',
                        'element_id' => $id,
                        'element_name' => $new_field['label'] ?? $new_field['name'] ?? '',
                        'old_value' => $old_field,
                        'new_value' => $new_field,
                        'path' => 'fields.' . $id,
                        'details' => $field_changes,
                    ];
                }
            }
        }

        // Check settings
        $old_settings = $old_data['settings'] ?? [];
        $new_settings = $new_data['settings'] ?? [];
        $setting_changes = $this->compareArrays($old_settings, $new_settings, 'settings');

        foreach ($setting_changes as $change) {
            $changes[] = array_merge($change, ['element_type' => 'setting']);
        }

        // Check field order
        $old_order = array_keys($old_fields);
        $new_order = array_keys($new_fields);

        if ($old_order !== $new_order) {
            // Filter to only existing fields in both
            $common = array_intersect($old_order, $new_order);
            $old_common = array_values(array_intersect($old_order, $common));
            $new_common = array_values(array_intersect($new_order, $common));

            if ($old_common !== $new_common) {
                $changes[] = [
                    'type' => 'reorder',
                    'element_type' => 'field',
                    'element_id' => 'all',
                    'element_name' => __('Field Order', 'form-flow-pro'),
                    'old_value' => $old_order,
                    'new_value' => $new_order,
                    'path' => 'fields._order',
                ];
            }
        }

        return $changes;
    }

    /**
     * Compare two arrays recursively
     */
    private function compareArrays(array $old, array $new, string $path): array
    {
        $changes = [];

        $all_keys = array_unique(array_merge(array_keys($old), array_keys($new)));

        foreach ($all_keys as $key) {
            $old_value = $old[$key] ?? null;
            $new_value = $new[$key] ?? null;
            $current_path = $path . '.' . $key;

            if ($old_value === null && $new_value !== null) {
                $changes[] = [
                    'type' => 'add',
                    'element_id' => $key,
                    'old_value' => null,
                    'new_value' => $new_value,
                    'path' => $current_path,
                ];
            } elseif ($old_value !== null && $new_value === null) {
                $changes[] = [
                    'type' => 'delete',
                    'element_id' => $key,
                    'old_value' => $old_value,
                    'new_value' => null,
                    'path' => $current_path,
                ];
            } elseif (is_array($old_value) && is_array($new_value)) {
                $sub_changes = $this->compareArrays($old_value, $new_value, $current_path);
                $changes = array_merge($changes, $sub_changes);
            } elseif ($old_value !== $new_value) {
                $changes[] = [
                    'type' => 'modify',
                    'element_id' => $key,
                    'old_value' => $old_value,
                    'new_value' => $new_value,
                    'path' => $current_path,
                ];
            }
        }

        return $changes;
    }

    /**
     * Generate human-readable change summary
     */
    private function generateChangeSummary(array $changes): string
    {
        if (empty($changes)) {
            return __('No changes', 'form-flow-pro');
        }

        $added = 0;
        $modified = 0;
        $deleted = 0;
        $reordered = false;

        foreach ($changes as $change) {
            switch ($change['type']) {
                case 'add':
                    $added++;
                    break;
                case 'modify':
                    $modified++;
                    break;
                case 'delete':
                    $deleted++;
                    break;
                case 'reorder':
                    $reordered = true;
                    break;
            }
        }

        $parts = [];

        if ($added > 0) {
            $parts[] = sprintf(_n('%d field added', '%d fields added', $added, 'form-flow-pro'), $added);
        }

        if ($modified > 0) {
            $parts[] = sprintf(_n('%d field modified', '%d fields modified', $modified, 'form-flow-pro'), $modified);
        }

        if ($deleted > 0) {
            $parts[] = sprintf(_n('%d field deleted', '%d fields deleted', $deleted, 'form-flow-pro'), $deleted);
        }

        if ($reordered) {
            $parts[] = __('fields reordered', 'form-flow-pro');
        }

        return implode(', ', $parts);
    }

    /**
     * Get change statistics
     */
    private function getChangeStatistics(array $changes): array
    {
        $stats = [
            'total' => count($changes),
            'by_type' => [
                'add' => 0,
                'modify' => 0,
                'delete' => 0,
                'reorder' => 0,
            ],
            'by_element' => [
                'field' => 0,
                'setting' => 0,
                'style' => 0,
                'logic' => 0,
            ],
        ];

        foreach ($changes as $change) {
            $stats['by_type'][$change['type']] = ($stats['by_type'][$change['type']] ?? 0) + 1;
            $stats['by_element'][$change['element_type']] = ($stats['by_element'][$change['element_type']] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Store detailed changes in database
     */
    private function storeChanges(int $version_id, array $changes): void
    {
        global $wpdb;

        foreach ($changes as $change) {
            $wpdb->insert($this->changes_table, [
                'version_id' => $version_id,
                'change_type' => $change['type'],
                'element_type' => $change['element_type'],
                'element_id' => $change['element_id'],
                'element_name' => $change['element_name'] ?? '',
                'old_value' => isset($change['old_value']) ? json_encode($change['old_value']) : null,
                'new_value' => isset($change['new_value']) ? json_encode($change['new_value']) : null,
                'path' => $change['path'] ?? '',
            ]);
        }
    }

    /**
     * Calculate checksum for form data
     */
    private function calculateChecksum(array $data): string
    {
        return hash('sha256', json_encode($data));
    }

    /**
     * Get latest version number
     */
    private function getLatestVersionNumber(int $form_id, string $branch = 'main'): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_number) FROM {$this->versions_table} WHERE form_id = %d AND branch_name = %s",
            $form_id,
            $branch
        ));
    }

    /**
     * Index array by ID
     */
    private function indexById(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $id = $item['id'] ?? $item['name'] ?? uniqid();
            $indexed[$id] = $item;
        }
        return $indexed;
    }

    /**
     * Convert database row to FormVersion object
     */
    private function rowToVersion(array $row): FormVersion
    {
        return new FormVersion([
            'id' => (int) $row['id'],
            'form_id' => (int) $row['form_id'],
            'version_number' => (int) $row['version_number'],
            'version_label' => $row['version_label'],
            'form_data' => json_decode($row['form_data'], true) ?? [],
            'settings' => json_decode($row['settings'], true) ?? [],
            'fields' => json_decode($row['fields'], true) ?? [],
            'change_summary' => $row['change_summary'],
            'created_by' => (int) $row['created_by'],
            'created_at' => $row['created_at'],
            'is_published' => (bool) $row['is_published'],
            'parent_version_id' => $row['parent_version_id'] ? (int) $row['parent_version_id'] : null,
            'branch_name' => $row['branch_name'],
            'checksum' => $row['checksum'],
        ]);
    }

    /**
     * Merge two form data arrays
     */
    private function mergeFormData(array $target, array $source): array
    {
        $merged = $target;

        // Merge fields - add new fields from source
        $target_fields = $this->indexById($target['fields'] ?? []);
        $source_fields = $this->indexById($source['fields'] ?? []);

        foreach ($source_fields as $id => $field) {
            if (!isset($target_fields[$id])) {
                $target_fields[$id] = $field;
            }
        }

        $merged['fields'] = array_values($target_fields);

        // Merge settings
        $merged['settings'] = array_merge($target['settings'] ?? [], $source['settings'] ?? []);

        return $merged;
    }

    /**
     * Delete version
     */
    public function deleteVersion(int $version_id): bool
    {
        global $wpdb;

        $version = $this->getVersionById($version_id);

        if (!$version) {
            return false;
        }

        // Don't delete published versions
        if ($version->is_published) {
            return false;
        }

        // Delete changes first
        $wpdb->delete($this->changes_table, ['version_id' => $version_id]);

        // Delete version
        $deleted = $wpdb->delete($this->versions_table, ['id' => $version_id]);

        if ($deleted) {
            do_action('ffp_version_deleted', $version_id, $version->form_id);
        }

        return $deleted !== false;
    }

    /**
     * Cleanup old versions (keep last N versions)
     */
    public function cleanupVersions(int $form_id, int $keep_count = 50): int
    {
        global $wpdb;

        $deleted_count = 0;

        // Get versions to delete (excluding published)
        $versions = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->versions_table}
             WHERE form_id = %d AND is_published = 0
             ORDER BY version_number DESC
             LIMIT 99999 OFFSET %d",
            $form_id,
            $keep_count
        ));

        foreach ($versions as $version_id) {
            if ($this->deleteVersion((int) $version_id)) {
                $deleted_count++;
            }
        }

        return $deleted_count;
    }

    /**
     * Export version history
     */
    public function exportHistory(int $form_id, string $format = 'json'): string
    {
        $versions = $this->getVersionHistory($form_id, ['limit' => 1000]);

        $export_data = [
            'form_id' => $form_id,
            'exported_at' => current_time('mysql'),
            'version_count' => count($versions),
            'versions' => array_map(function($v) { return $v->toArray(); }, $versions),
        ];

        if ($format === 'json') {
            return json_encode($export_data, JSON_PRETTY_PRINT);
        }

        return serialize($export_data);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('form-flow-pro/v1', '/forms/(?P<form_id>\d+)/versions', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetVersions'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateVersion'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/versions/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetVersion'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteVersion'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/versions/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'restPublishVersion'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/versions/(?P<id>\d+)/rollback', [
            'methods' => 'POST',
            'callback' => [$this, 'restRollback'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/versions/compare', [
            'methods' => 'GET',
            'callback' => [$this, 'restCompareVersions'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restGetVersions(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('form_id');
        $branch = $request->get_param('branch');
        $limit = (int) ($request->get_param('per_page') ?? 20);
        $page = (int) ($request->get_param('page') ?? 1);

        $versions = $this->getVersionHistory($form_id, [
            'branch' => $branch,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ]);

        $total = $this->getVersionCount($form_id, $branch);

        return new \WP_REST_Response([
            'versions' => array_map(function($v) { return $v->toArray(); }, $versions),
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    public function restCreateVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('form_id');
        $form_data = $request->get_json_params()['form_data'] ?? [];
        $options = $request->get_json_params()['options'] ?? [];

        $version = $this->createVersion($form_id, $form_data, $options);

        if (!$version) {
            return new \WP_REST_Response(['error' => 'Failed to create version'], 500);
        }

        return new \WP_REST_Response($version->toArray(), 201);
    }

    public function restGetVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $version_id = (int) $request->get_param('id');
        $version = $this->getVersionById($version_id);

        if (!$version) {
            return new \WP_REST_Response(['error' => 'Version not found'], 404);
        }

        $data = $version->toArray();
        $data['changes'] = $this->getVersionChanges($version_id);

        return new \WP_REST_Response($data);
    }

    public function restDeleteVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $version_id = (int) $request->get_param('id');

        if ($this->deleteVersion($version_id)) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Cannot delete version'], 400);
    }

    public function restPublishVersion(\WP_REST_Request $request): \WP_REST_Response
    {
        $version_id = (int) $request->get_param('id');

        if ($this->publish($version_id)) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Failed to publish version'], 500);
    }

    public function restRollback(\WP_REST_Request $request): \WP_REST_Response
    {
        $version_id = (int) $request->get_param('id');
        $version = $this->getVersionById($version_id);

        if (!$version) {
            return new \WP_REST_Response(['error' => 'Version not found'], 404);
        }

        $new_version = $this->rollback($version->form_id, $version_id);

        if (!$new_version) {
            return new \WP_REST_Response(['error' => 'Failed to rollback'], 500);
        }

        return new \WP_REST_Response($new_version->toArray());
    }

    public function restCompareVersions(\WP_REST_Request $request): \WP_REST_Response
    {
        $version_a = (int) $request->get_param('version_a');
        $version_b = (int) $request->get_param('version_b');

        $comparison = $this->compareVersions($version_a, $version_b);

        if (isset($comparison['error'])) {
            return new \WP_REST_Response($comparison, 404);
        }

        return new \WP_REST_Response($comparison);
    }

    /**
     * AJAX Handlers
     */
    public function ajaxCreateVersion(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $form_data = json_decode(stripslashes($_POST['form_data'] ?? '{}'), true);
        $label = sanitize_text_field($_POST['label'] ?? '');

        $version = $this->createVersion($form_id, $form_data, [
            'label' => $label,
        ]);

        if ($version) {
            wp_send_json_success($version->toArray());
        } else {
            wp_send_json_error(['message' => 'Failed to create version']);
        }
    }

    public function ajaxRollbackVersion(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $version_id = (int) ($_POST['version_id'] ?? 0);
        $version = $this->getVersionById($version_id);

        if (!$version) {
            wp_send_json_error(['message' => 'Version not found']);
        }

        $new_version = $this->rollback($version->form_id, $version_id);

        if ($new_version) {
            wp_send_json_success($new_version->toArray());
        } else {
            wp_send_json_error(['message' => 'Rollback failed']);
        }
    }

    public function ajaxCompareVersions(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $version_a = (int) ($_POST['version_a'] ?? 0);
        $version_b = (int) ($_POST['version_b'] ?? 0);

        $comparison = $this->compareVersions($version_a, $version_b);

        wp_send_json_success($comparison);
    }

    /**
     * Get branches for a form
     */
    public function getBranches(int $form_id): array
    {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT branch_name FROM {$this->versions_table} WHERE form_id = %d ORDER BY branch_name",
            $form_id
        ));
    }
}
