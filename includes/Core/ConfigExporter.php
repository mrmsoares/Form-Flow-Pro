<?php

/**
 * Configuration Exporter
 *
 * Handles export and import of FormFlow Pro configurations.
 * Supports forms, templates, settings, and webhooks.
 *
 * @package FormFlowPro
 * @since 2.2.0
 */

namespace FormFlowPro\Core;

/**
 * Configuration Exporter class.
 *
 * @since 2.2.0
 */
class ConfigExporter
{
    /**
     * WordPress database object.
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Export format version.
     *
     * @var string
     */
    private const EXPORT_VERSION = '2.2.0';

    /**
     * Constructor.
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Export configurations to JSON.
     *
     * @param array $options Export options.
     * @return array Export data with metadata.
     */
    public function export(array $options = []): array
    {
        $defaults = [
            'include_forms' => true,
            'include_templates' => true,
            'include_settings' => true,
            'include_webhooks' => true,
            'form_ids' => [], // Empty means all
        ];

        $options = array_merge($defaults, $options);

        $export_data = [
            'meta' => [
                'version' => self::EXPORT_VERSION,
                'plugin_version' => FORMFLOW_VERSION,
                'exported_at' => current_time('c'),
                'site_url' => get_site_url(),
                'options' => $options,
            ],
            'data' => [],
        ];

        if ($options['include_forms']) {
            $export_data['data']['forms'] = $this->export_forms($options['form_ids']);
        }

        if ($options['include_templates']) {
            $export_data['data']['templates'] = $this->export_templates();
        }

        if ($options['include_settings']) {
            $export_data['data']['settings'] = $this->export_settings();
        }

        if ($options['include_webhooks']) {
            $export_data['data']['webhooks'] = $this->export_webhooks();
        }

        return $export_data;
    }

    /**
     * Export forms.
     *
     * @param array $form_ids Specific form IDs to export.
     * @return array Forms data.
     */
    private function export_forms(array $form_ids = []): array
    {
        $table = $this->wpdb->prefix . 'formflow_forms';

        if (!empty($form_ids)) {
            $placeholders = implode(',', array_fill(0, count($form_ids), '%s'));
            $query = $this->wpdb->prepare(
                "SELECT * FROM {$table} WHERE id IN ({$placeholders})",
                $form_ids
            );
        } else {
            $query = "SELECT * FROM {$table}";
        }

        $forms = $this->wpdb->get_results($query, ARRAY_A);

        // Remove sensitive/internal data
        foreach ($forms as &$form) {
            unset($form['created_by']);
            // Decode settings for readability
            if (!empty($form['settings'])) {
                $form['settings'] = json_decode($form['settings'], true);
            }
        }

        return $forms;
    }

    /**
     * Export templates.
     *
     * @return array Templates data.
     */
    private function export_templates(): array
    {
        $table = $this->wpdb->prefix . 'formflow_templates';
        $templates = $this->wpdb->get_results(
            "SELECT * FROM {$table} WHERE status != 'draft'",
            ARRAY_A
        );

        foreach ($templates as &$template) {
            unset($template['created_by']);
            if (!empty($template['settings'])) {
                $template['settings'] = json_decode($template['settings'], true);
            }
        }

        return $templates;
    }

    /**
     * Export settings.
     *
     * @return array Settings data.
     */
    private function export_settings(): array
    {
        $table = $this->wpdb->prefix . 'formflow_settings';
        $settings = $this->wpdb->get_results(
            "SELECT setting_key, setting_value, autoload FROM {$table}",
            ARRAY_A
        );

        // Also include WordPress options
        $wp_options = [
            'formflow_autentique_api_key' => get_option('formflow_autentique_api_key', ''),
            'formflow_autentique_sandbox' => get_option('formflow_autentique_sandbox', false),
        ];

        // Mask API keys for security
        if (!empty($wp_options['formflow_autentique_api_key'])) {
            $wp_options['formflow_autentique_api_key'] = $this->mask_api_key(
                $wp_options['formflow_autentique_api_key']
            );
        }

        return [
            'plugin_settings' => $settings,
            'wp_options' => $wp_options,
        ];
    }

    /**
     * Export webhooks.
     *
     * @return array Webhooks data.
     */
    private function export_webhooks(): array
    {
        $table = $this->wpdb->prefix . 'formflow_webhooks';
        $webhooks = $this->wpdb->get_results(
            "SELECT name, event, url, method, headers, enabled FROM {$table}",
            ARRAY_A
        );

        foreach ($webhooks as &$webhook) {
            if (!empty($webhook['headers'])) {
                $webhook['headers'] = json_decode($webhook['headers'], true);
            }
        }

        return $webhooks;
    }

    /**
     * Import configurations from JSON data.
     *
     * @param array $import_data Import data.
     * @param array $options Import options.
     * @return array Import results.
     */
    public function import(array $import_data, array $options = []): array
    {
        $defaults = [
            'overwrite_existing' => false,
            'import_forms' => true,
            'import_templates' => true,
            'import_settings' => true,
            'import_webhooks' => true,
        ];

        $options = array_merge($defaults, $options);

        // Validate import data
        $validation = $this->validate_import_data($import_data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
            ];
        }

        $results = [
            'success' => true,
            'imported' => [],
            'skipped' => [],
            'errors' => [],
        ];

        try {
            $this->wpdb->query('START TRANSACTION');

            if ($options['import_forms'] && !empty($import_data['data']['forms'])) {
                $form_results = $this->import_forms(
                    $import_data['data']['forms'],
                    $options['overwrite_existing']
                );
                $results['imported']['forms'] = $form_results['imported'];
                $results['skipped']['forms'] = $form_results['skipped'];
                $results['errors'] = array_merge($results['errors'], $form_results['errors']);
            }

            if ($options['import_templates'] && !empty($import_data['data']['templates'])) {
                $template_results = $this->import_templates(
                    $import_data['data']['templates'],
                    $options['overwrite_existing']
                );
                $results['imported']['templates'] = $template_results['imported'];
                $results['skipped']['templates'] = $template_results['skipped'];
                $results['errors'] = array_merge($results['errors'], $template_results['errors']);
            }

            if ($options['import_settings'] && !empty($import_data['data']['settings'])) {
                $settings_results = $this->import_settings(
                    $import_data['data']['settings'],
                    $options['overwrite_existing']
                );
                $results['imported']['settings'] = $settings_results['imported'];
            }

            if ($options['import_webhooks'] && !empty($import_data['data']['webhooks'])) {
                $webhook_results = $this->import_webhooks(
                    $import_data['data']['webhooks'],
                    $options['overwrite_existing']
                );
                $results['imported']['webhooks'] = $webhook_results['imported'];
                $results['skipped']['webhooks'] = $webhook_results['skipped'];
            }

            $this->wpdb->query('COMMIT');

        } catch (\Exception $e) {
            $this->wpdb->query('ROLLBACK');
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate import data structure.
     *
     * @param array $data Import data.
     * @return array Validation result.
     */
    private function validate_import_data(array $data): array
    {
        if (empty($data['meta'])) {
            return [
                'valid' => false,
                'error' => __('Invalid import file: missing metadata', 'formflow-pro'),
            ];
        }

        if (empty($data['meta']['version'])) {
            return [
                'valid' => false,
                'error' => __('Invalid import file: missing version', 'formflow-pro'),
            ];
        }

        // Check version compatibility
        if (version_compare($data['meta']['version'], '2.0.0', '<')) {
            return [
                'valid' => false,
                'error' => __('Import file version too old. Please export from a newer version.', 'formflow-pro'),
            ];
        }

        if (empty($data['data'])) {
            return [
                'valid' => false,
                'error' => __('Invalid import file: no data to import', 'formflow-pro'),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Import forms.
     *
     * @param array $forms Forms data.
     * @param bool $overwrite Overwrite existing.
     * @return array Import results.
     */
    private function import_forms(array $forms, bool $overwrite): array
    {
        $table = $this->wpdb->prefix . 'formflow_forms';
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($forms as $form) {
            // Check if form exists
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %s OR elementor_form_id = %s",
                $form['id'],
                $form['elementor_form_id']
            ));

            if ($existing && !$overwrite) {
                $results['skipped']++;
                continue;
            }

            // Prepare form data
            $form_data = [
                'id' => $form['id'],
                'name' => sanitize_text_field($form['name']),
                'elementor_form_id' => sanitize_text_field($form['elementor_form_id']),
                'status' => $form['status'] ?? 'active',
                'settings' => is_array($form['settings'])
                    ? wp_json_encode($form['settings'])
                    : $form['settings'],
                'pdf_template_id' => $form['pdf_template_id'] ?? null,
                'email_template_id' => $form['email_template_id'] ?? null,
                'autentique_enabled' => (int) ($form['autentique_enabled'] ?? 0),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];

            if ($existing && $overwrite) {
                $this->wpdb->update(
                    $table,
                    $form_data,
                    ['id' => $form['id']]
                );
            } else {
                $this->wpdb->insert($table, $form_data);
            }

            if ($this->wpdb->last_error) {
                $results['errors'][] = sprintf(
                    __('Error importing form "%s": %s', 'formflow-pro'),
                    $form['name'],
                    $this->wpdb->last_error
                );
            } else {
                $results['imported']++;
            }
        }

        return $results;
    }

    /**
     * Import templates.
     *
     * @param array $templates Templates data.
     * @param bool $overwrite Overwrite existing.
     * @return array Import results.
     */
    private function import_templates(array $templates, bool $overwrite): array
    {
        $table = $this->wpdb->prefix . 'formflow_templates';
        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($templates as $template) {
            // Check if template exists
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE id = %s",
                $template['id']
            ));

            if ($existing && !$overwrite) {
                $results['skipped']++;
                continue;
            }

            // Prepare template data
            $template_data = [
                'id' => $template['id'],
                'name' => sanitize_text_field($template['name']),
                'type' => $template['type'],
                'content' => wp_kses_post($template['content']),
                'settings' => is_array($template['settings'])
                    ? wp_json_encode($template['settings'])
                    : ($template['settings'] ?? null),
                'pdf_orientation' => $template['pdf_orientation'] ?? 'portrait',
                'pdf_page_size' => $template['pdf_page_size'] ?? 'A4',
                'email_subject' => sanitize_text_field($template['email_subject'] ?? ''),
                'email_from_name' => sanitize_text_field($template['email_from_name'] ?? ''),
                'email_from_email' => sanitize_email($template['email_from_email'] ?? ''),
                'is_default' => (int) ($template['is_default'] ?? 0),
                'status' => $template['status'] ?? 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];

            if ($existing && $overwrite) {
                $this->wpdb->update(
                    $table,
                    $template_data,
                    ['id' => $template['id']]
                );
            } else {
                $this->wpdb->insert($table, $template_data);
            }

            if ($this->wpdb->last_error) {
                $results['errors'][] = sprintf(
                    __('Error importing template "%s": %s', 'formflow-pro'),
                    $template['name'],
                    $this->wpdb->last_error
                );
            } else {
                $results['imported']++;
            }
        }

        return $results;
    }

    /**
     * Import settings.
     *
     * @param array $settings Settings data.
     * @param bool $overwrite Overwrite existing.
     * @return array Import results.
     */
    private function import_settings(array $settings, bool $overwrite): array
    {
        $table = $this->wpdb->prefix . 'formflow_settings';
        $results = ['imported' => 0];

        // Import plugin settings
        if (!empty($settings['plugin_settings'])) {
            foreach ($settings['plugin_settings'] as $setting) {
                // Check if setting exists
                $existing = $this->wpdb->get_var($this->wpdb->prepare(
                    "SELECT setting_key FROM {$table} WHERE setting_key = %s",
                    $setting['setting_key']
                ));

                if ($existing && !$overwrite) {
                    continue;
                }

                if ($existing) {
                    $this->wpdb->update(
                        $table,
                        [
                            'setting_value' => sanitize_text_field($setting['setting_value']),
                            'autoload' => (int) ($setting['autoload'] ?? 1),
                            'updated_at' => current_time('mysql'),
                        ],
                        ['setting_key' => $setting['setting_key']]
                    );
                } else {
                    $this->wpdb->insert($table, [
                        'setting_key' => sanitize_key($setting['setting_key']),
                        'setting_value' => sanitize_text_field($setting['setting_value']),
                        'autoload' => (int) ($setting['autoload'] ?? 1),
                        'created_at' => current_time('mysql'),
                    ]);
                }

                $results['imported']++;
            }
        }

        // Note: API keys are not imported for security reasons
        // They must be manually entered

        return $results;
    }

    /**
     * Import webhooks.
     *
     * @param array $webhooks Webhooks data.
     * @param bool $overwrite Overwrite existing.
     * @return array Import results.
     */
    private function import_webhooks(array $webhooks, bool $overwrite): array
    {
        $table = $this->wpdb->prefix . 'formflow_webhooks';
        $results = ['imported' => 0, 'skipped' => 0];

        foreach ($webhooks as $webhook) {
            // Check if webhook exists by URL and event
            $existing = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$table} WHERE url = %s AND event = %s",
                $webhook['url'],
                $webhook['event']
            ));

            if ($existing && !$overwrite) {
                $results['skipped']++;
                continue;
            }

            $webhook_data = [
                'name' => sanitize_text_field($webhook['name']),
                'event' => sanitize_text_field($webhook['event']),
                'url' => esc_url_raw($webhook['url']),
                'method' => $webhook['method'] ?? 'POST',
                'headers' => is_array($webhook['headers'])
                    ? wp_json_encode($webhook['headers'])
                    : ($webhook['headers'] ?? null),
                'enabled' => (int) ($webhook['enabled'] ?? 1),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];

            if ($existing && $overwrite) {
                $this->wpdb->update($table, $webhook_data, ['id' => $existing]);
            } else {
                $this->wpdb->insert($table, $webhook_data);
            }

            $results['imported']++;
        }

        return $results;
    }

    /**
     * Mask API key for export.
     *
     * @param string $key API key.
     * @return string Masked key.
     */
    private function mask_api_key(string $key): string
    {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }

    /**
     * Generate export filename.
     *
     * @return string Filename.
     */
    public static function generate_filename(): string
    {
        return 'formflow-config-' . date('Y-m-d-His') . '.json';
    }
}
