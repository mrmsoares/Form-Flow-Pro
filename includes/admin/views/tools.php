<?php

/**
 * Tools Page - Export/Import Configurations
 *
 * Admin page for exporting and importing FormFlow Pro configurations.
 *
 * @package FormFlowPro
 * @since 2.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get counts for display
$forms_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms");
$templates_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_templates");
$webhooks_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_webhooks");

// Get forms for selective export
$forms = $wpdb->get_results("SELECT id, name, elementor_form_id FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

?>

<div class="wrap formflow-admin formflow-tools">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Tools', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <div class="tools-grid">
        <!-- Export Section -->
        <div class="tool-card">
            <div class="tool-header">
                <span class="dashicons dashicons-download"></span>
                <h2><?php esc_html_e('Export Configuration', 'formflow-pro'); ?></h2>
            </div>

            <div class="tool-body">
                <p class="description">
                    <?php esc_html_e('Export your FormFlow Pro configuration to a JSON file. This includes forms, templates, settings, and webhooks.', 'formflow-pro'); ?>
                </p>

                <div class="export-stats">
                    <div class="stat-item">
                        <span class="dashicons dashicons-forms"></span>
                        <span class="stat-count"><?php echo esc_html($forms_count); ?></span>
                        <span class="stat-label"><?php esc_html_e('Forms', 'formflow-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="dashicons dashicons-media-document"></span>
                        <span class="stat-count"><?php echo esc_html($templates_count); ?></span>
                        <span class="stat-label"><?php esc_html_e('Templates', 'formflow-pro'); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="dashicons dashicons-admin-links"></span>
                        <span class="stat-count"><?php echo esc_html($webhooks_count); ?></span>
                        <span class="stat-label"><?php esc_html_e('Webhooks', 'formflow-pro'); ?></span>
                    </div>
                </div>

                <form id="export-config-form" class="export-form">
                    <div class="export-options">
                        <h3><?php esc_html_e('Export Options', 'formflow-pro'); ?></h3>

                        <label class="checkbox-option">
                            <input type="checkbox" name="include_forms" value="1" checked>
                            <?php esc_html_e('Include Forms', 'formflow-pro'); ?>
                            <span class="option-count">(<?php echo esc_html($forms_count); ?>)</span>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="include_templates" value="1" checked>
                            <?php esc_html_e('Include Templates', 'formflow-pro'); ?>
                            <span class="option-count">(<?php echo esc_html($templates_count); ?>)</span>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="include_settings" value="1" checked>
                            <?php esc_html_e('Include Settings', 'formflow-pro'); ?>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="include_webhooks" value="1" checked>
                            <?php esc_html_e('Include Webhooks', 'formflow-pro'); ?>
                            <span class="option-count">(<?php echo esc_html($webhooks_count); ?>)</span>
                        </label>
                    </div>

                    <?php if (!empty($forms)) : ?>
                    <div class="form-selection" style="display: none;">
                        <h4><?php esc_html_e('Select Specific Forms (optional)', 'formflow-pro'); ?></h4>
                        <div class="forms-list">
                            <?php foreach ($forms as $form) : ?>
                            <label class="checkbox-option">
                                <input type="checkbox" name="form_ids[]" value="<?php echo esc_attr($form->id); ?>">
                                <?php echo esc_html($form->name); ?>
                                <span class="form-id">(<?php echo esc_html($form->elementor_form_id); ?>)</span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Leave all unchecked to export all forms.', 'formflow-pro'); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="button button-primary button-large" id="export-btn">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Configuration', 'formflow-pro'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Import Section -->
        <div class="tool-card">
            <div class="tool-header">
                <span class="dashicons dashicons-upload"></span>
                <h2><?php esc_html_e('Import Configuration', 'formflow-pro'); ?></h2>
            </div>

            <div class="tool-body">
                <p class="description">
                    <?php esc_html_e('Import a FormFlow Pro configuration from a JSON file. This will add or update your forms, templates, settings, and webhooks.', 'formflow-pro'); ?>
                </p>

                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php esc_html_e('Warning:', 'formflow-pro'); ?></strong>
                        <?php esc_html_e('API keys and sensitive credentials are not included in exports for security. You will need to reconfigure these after import.', 'formflow-pro'); ?>
                    </p>
                </div>

                <form id="import-config-form" class="import-form">
                    <div class="file-upload-area" id="file-drop-zone">
                        <input type="file" name="config_file" id="config-file" accept=".json" hidden>
                        <div class="upload-placeholder">
                            <span class="dashicons dashicons-cloud-upload"></span>
                            <p><?php esc_html_e('Drag and drop your JSON file here', 'formflow-pro'); ?></p>
                            <p class="or"><?php esc_html_e('or', 'formflow-pro'); ?></p>
                            <button type="button" class="button" id="browse-file">
                                <?php esc_html_e('Browse Files', 'formflow-pro'); ?>
                            </button>
                        </div>
                        <div class="file-selected" style="display: none;">
                            <span class="dashicons dashicons-media-text"></span>
                            <span class="file-name"></span>
                            <button type="button" class="remove-file">&times;</button>
                        </div>
                    </div>

                    <div class="import-preview" id="import-preview" style="display: none;">
                        <h3><?php esc_html_e('Import Preview', 'formflow-pro'); ?></h3>
                        <div class="preview-meta">
                            <p><strong><?php esc_html_e('Exported from:', 'formflow-pro'); ?></strong> <span id="preview-site"></span></p>
                            <p><strong><?php esc_html_e('Export date:', 'formflow-pro'); ?></strong> <span id="preview-date"></span></p>
                            <p><strong><?php esc_html_e('Version:', 'formflow-pro'); ?></strong> <span id="preview-version"></span></p>
                        </div>

                        <div class="preview-summary">
                            <div class="summary-item" id="preview-forms">
                                <span class="dashicons dashicons-forms"></span>
                                <span class="count">0</span>
                                <span class="label"><?php esc_html_e('Forms', 'formflow-pro'); ?></span>
                            </div>
                            <div class="summary-item" id="preview-templates">
                                <span class="dashicons dashicons-media-document"></span>
                                <span class="count">0</span>
                                <span class="label"><?php esc_html_e('Templates', 'formflow-pro'); ?></span>
                            </div>
                            <div class="summary-item" id="preview-settings">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <span class="count">0</span>
                                <span class="label"><?php esc_html_e('Settings', 'formflow-pro'); ?></span>
                            </div>
                            <div class="summary-item" id="preview-webhooks">
                                <span class="dashicons dashicons-admin-links"></span>
                                <span class="count">0</span>
                                <span class="label"><?php esc_html_e('Webhooks', 'formflow-pro'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="import-options" id="import-options" style="display: none;">
                        <h3><?php esc_html_e('Import Options', 'formflow-pro'); ?></h3>

                        <label class="checkbox-option">
                            <input type="checkbox" name="import_forms" value="1" checked>
                            <?php esc_html_e('Import Forms', 'formflow-pro'); ?>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="import_templates" value="1" checked>
                            <?php esc_html_e('Import Templates', 'formflow-pro'); ?>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="import_settings" value="1" checked>
                            <?php esc_html_e('Import Settings', 'formflow-pro'); ?>
                        </label>

                        <label class="checkbox-option">
                            <input type="checkbox" name="import_webhooks" value="1" checked>
                            <?php esc_html_e('Import Webhooks', 'formflow-pro'); ?>
                        </label>

                        <div class="overwrite-option">
                            <label class="checkbox-option warning">
                                <input type="checkbox" name="overwrite_existing" value="1">
                                <?php esc_html_e('Overwrite existing items', 'formflow-pro'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('If checked, existing forms, templates, and settings with the same ID will be overwritten.', 'formflow-pro'); ?>
                            </p>
                        </div>
                    </div>

                    <button type="submit" class="button button-primary button-large" id="import-btn" disabled>
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Import Configuration', 'formflow-pro'); ?>
                    </button>
                </form>

                <div class="import-results" id="import-results" style="display: none;">
                    <h3><?php esc_html_e('Import Results', 'formflow-pro'); ?></h3>
                    <div class="results-content"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Tools -->
    <div class="additional-tools">
        <h2><?php esc_html_e('Additional Tools', 'formflow-pro'); ?></h2>

        <div class="tools-grid small">
            <div class="tool-card mini">
                <h3>
                    <span class="dashicons dashicons-database"></span>
                    <?php esc_html_e('Database Optimization', 'formflow-pro'); ?>
                </h3>
                <p><?php esc_html_e('Optimize database tables and clean up old data.', 'formflow-pro'); ?></p>
                <button type="button" class="button" id="optimize-db">
                    <?php esc_html_e('Optimize', 'formflow-pro'); ?>
                </button>
            </div>

            <div class="tool-card mini">
                <h3>
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Clear Cache', 'formflow-pro'); ?>
                </h3>
                <p><?php esc_html_e('Clear all FormFlow Pro cached data.', 'formflow-pro'); ?></p>
                <button type="button" class="button" id="clear-cache">
                    <?php esc_html_e('Clear Cache', 'formflow-pro'); ?>
                </button>
            </div>

            <div class="tool-card mini">
                <h3>
                    <span class="dashicons dashicons-backup"></span>
                    <?php esc_html_e('System Info', 'formflow-pro'); ?>
                </h3>
                <p><?php esc_html_e('View system information and debug data.', 'formflow-pro'); ?></p>
                <button type="button" class="button" id="system-info">
                    <?php esc_html_e('View Info', 'formflow-pro'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.formflow-tools .tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.formflow-tools .tools-grid.small {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

.formflow-tools .tool-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    overflow: hidden;
}

.formflow-tools .tool-card.mini {
    padding: 20px;
}

.formflow-tools .tool-card.mini h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 10px;
    font-size: 14px;
}

.formflow-tools .tool-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa, #fff);
    border-bottom: 1px solid #dcdcde;
}

.formflow-tools .tool-header .dashicons {
    font-size: 28px;
    width: 28px;
    height: 28px;
    color: #0073aa;
}

.formflow-tools .tool-header h2 {
    margin: 0;
    font-size: 18px;
}

.formflow-tools .tool-body {
    padding: 20px;
}

.formflow-tools .export-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    padding: 15px;
    background: #f6f7f7;
    border-radius: 6px;
}

.formflow-tools .stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.formflow-tools .stat-item .dashicons {
    color: #646970;
}

.formflow-tools .stat-count {
    font-size: 20px;
    font-weight: 700;
    color: #1d2327;
}

.formflow-tools .stat-label {
    color: #646970;
}

.formflow-tools .export-options,
.formflow-tools .import-options {
    margin: 20px 0;
}

.formflow-tools .export-options h3,
.formflow-tools .import-options h3 {
    font-size: 14px;
    margin: 0 0 15px;
}

.formflow-tools .checkbox-option {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    cursor: pointer;
}

.formflow-tools .checkbox-option.warning {
    color: #d63638;
}

.formflow-tools .option-count,
.formflow-tools .form-id {
    color: #787c82;
    font-size: 12px;
}

.formflow-tools .file-upload-area {
    border: 2px dashed #c3c4c7;
    border-radius: 8px;
    padding: 40px;
    text-align: center;
    margin: 20px 0;
    transition: all 0.2s;
}

.formflow-tools .file-upload-area.drag-over {
    border-color: #0073aa;
    background: rgba(0, 115, 170, 0.05);
}

.formflow-tools .upload-placeholder .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #c3c4c7;
}

.formflow-tools .upload-placeholder p {
    margin: 10px 0;
    color: #646970;
}

.formflow-tools .upload-placeholder .or {
    color: #787c82;
    font-size: 12px;
}

.formflow-tools .file-selected {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.formflow-tools .file-selected .dashicons {
    color: #46b450;
}

.formflow-tools .remove-file {
    background: none;
    border: none;
    color: #dc3232;
    font-size: 20px;
    cursor: pointer;
    padding: 0 5px;
}

.formflow-tools .import-preview {
    background: #f6f7f7;
    border-radius: 6px;
    padding: 15px;
    margin: 20px 0;
}

.formflow-tools .import-preview h3 {
    margin: 0 0 10px;
    font-size: 14px;
}

.formflow-tools .preview-meta {
    font-size: 13px;
    margin-bottom: 15px;
}

.formflow-tools .preview-meta p {
    margin: 5px 0;
}

.formflow-tools .preview-summary {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.formflow-tools .summary-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: #fff;
    border-radius: 6px;
    border: 1px solid #dcdcde;
}

.formflow-tools .summary-item .count {
    font-size: 18px;
    font-weight: 700;
}

.formflow-tools .import-results {
    margin-top: 20px;
    padding: 15px;
    border-radius: 6px;
}

.formflow-tools .import-results.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.formflow-tools .import-results.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.formflow-tools .additional-tools {
    margin-top: 40px;
}

.formflow-tools .additional-tools h2 {
    font-size: 16px;
    margin-bottom: 15px;
}

.formflow-tools .button-large {
    padding: 8px 20px;
    height: auto;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.formflow-tools .button-large .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // File drop zone
    const $dropZone = $('#file-drop-zone');
    const $fileInput = $('#config-file');
    let importData = null;

    // Browse button click
    $('#browse-file').on('click', function() {
        $fileInput.click();
    });

    // File input change
    $fileInput.on('change', function() {
        if (this.files.length > 0) {
            handleFile(this.files[0]);
        }
    });

    // Drag and drop
    $dropZone.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });

    $dropZone.on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });

    $dropZone.on('drop', function(e) {
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    // Remove file
    $dropZone.on('click', '.remove-file', function() {
        $fileInput.val('');
        importData = null;
        $('.upload-placeholder').show();
        $('.file-selected').hide();
        $('#import-preview, #import-options').hide();
        $('#import-btn').prop('disabled', true);
    });

    // Handle file selection
    function handleFile(file) {
        if (file.type !== 'application/json' && !file.name.endsWith('.json')) {
            alert('<?php esc_html_e('Please select a valid JSON file.', 'formflow-pro'); ?>');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                importData = JSON.parse(e.target.result);
                showFileSelected(file.name);
                previewImport(importData);
            } catch (error) {
                alert('<?php esc_html_e('Invalid JSON file.', 'formflow-pro'); ?>');
            }
        };
        reader.readAsText(file);
    }

    // Show selected file
    function showFileSelected(filename) {
        $('.upload-placeholder').hide();
        $('.file-selected').show().find('.file-name').text(filename);
    }

    // Preview import
    function previewImport(data) {
        const meta = data.meta || {};
        const summary = {
            forms: data.data?.forms?.length || 0,
            templates: data.data?.templates?.length || 0,
            settings: data.data?.settings?.plugin_settings?.length || 0,
            webhooks: data.data?.webhooks?.length || 0
        };

        $('#preview-site').text(meta.site_url || 'Unknown');
        $('#preview-date').text(meta.exported_at ? new Date(meta.exported_at).toLocaleString() : 'Unknown');
        $('#preview-version').text(meta.version || 'Unknown');

        $('#preview-forms .count').text(summary.forms);
        $('#preview-templates .count').text(summary.templates);
        $('#preview-settings .count').text(summary.settings);
        $('#preview-webhooks .count').text(summary.webhooks);

        $('#import-preview, #import-options').show();
        $('#import-btn').prop('disabled', false);
    }

    // Export form submit
    $('#export-config-form').on('submit', function(e) {
        e.preventDefault();

        const $btn = $('#export-btn');
        $btn.prop('disabled', true).text('<?php esc_html_e('Exporting...', 'formflow-pro'); ?>');

        const formData = {
            action: 'formflow_export_config',
            nonce: formflowData.nonce,
            include_forms: $('input[name="include_forms"]').is(':checked') ? 1 : 0,
            include_templates: $('input[name="include_templates"]').is(':checked') ? 1 : 0,
            include_settings: $('input[name="include_settings"]').is(':checked') ? 1 : 0,
            include_webhooks: $('input[name="include_webhooks"]').is(':checked') ? 1 : 0,
            form_ids: $('input[name="form_ids[]"]:checked').map(function() {
                return $(this).val();
            }).get()
        };

        $.post(formflowData.ajax_url, formData, function(response) {
            if (response.success) {
                // Download JSON file
                const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = response.data.filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            } else {
                alert(response.data?.message || '<?php esc_html_e('Export failed.', 'formflow-pro'); ?>');
            }
        }).fail(function() {
            alert('<?php esc_html_e('Export request failed.', 'formflow-pro'); ?>');
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php esc_html_e('Export Configuration', 'formflow-pro'); ?>');
        });
    });

    // Import form submit
    $('#import-config-form').on('submit', function(e) {
        e.preventDefault();

        if (!importData) {
            alert('<?php esc_html_e('Please select a file first.', 'formflow-pro'); ?>');
            return;
        }

        if (!confirm('<?php esc_html_e('Are you sure you want to import this configuration?', 'formflow-pro'); ?>')) {
            return;
        }

        const $btn = $('#import-btn');
        $btn.prop('disabled', true).text('<?php esc_html_e('Importing...', 'formflow-pro'); ?>');

        const formData = {
            action: 'formflow_import_config',
            nonce: formflowData.nonce,
            import_data: JSON.stringify(importData),
            import_forms: $('input[name="import_forms"]').is(':checked') ? 1 : 0,
            import_templates: $('input[name="import_templates"]').is(':checked') ? 1 : 0,
            import_settings: $('input[name="import_settings"]').is(':checked') ? 1 : 0,
            import_webhooks: $('input[name="import_webhooks"]').is(':checked') ? 1 : 0,
            overwrite_existing: $('input[name="overwrite_existing"]').is(':checked') ? 1 : 0
        };

        $.post(formflowData.ajax_url, formData, function(response) {
            const $results = $('#import-results');

            if (response.success) {
                $results.removeClass('error').addClass('success');

                let html = '<p><strong><?php esc_html_e('Import completed successfully!', 'formflow-pro'); ?></strong></p>';
                html += '<ul>';

                if (response.data.imported.forms) {
                    html += '<li><?php esc_html_e('Forms imported:', 'formflow-pro'); ?> ' + response.data.imported.forms + '</li>';
                }
                if (response.data.imported.templates) {
                    html += '<li><?php esc_html_e('Templates imported:', 'formflow-pro'); ?> ' + response.data.imported.templates + '</li>';
                }
                if (response.data.imported.settings) {
                    html += '<li><?php esc_html_e('Settings imported:', 'formflow-pro'); ?> ' + response.data.imported.settings + '</li>';
                }
                if (response.data.imported.webhooks) {
                    html += '<li><?php esc_html_e('Webhooks imported:', 'formflow-pro'); ?> ' + response.data.imported.webhooks + '</li>';
                }

                html += '</ul>';

                if (response.data.skipped && Object.values(response.data.skipped).some(v => v > 0)) {
                    html += '<p><em><?php esc_html_e('Some items were skipped (already exist).', 'formflow-pro'); ?></em></p>';
                }

                $results.find('.results-content').html(html);
            } else {
                $results.removeClass('success').addClass('error');
                $results.find('.results-content').html(
                    '<p><strong><?php esc_html_e('Import failed:', 'formflow-pro'); ?></strong> ' +
                    (response.data?.message || '<?php esc_html_e('Unknown error', 'formflow-pro'); ?>') +
                    '</p>'
                );
            }

            $results.show();
        }).fail(function() {
            $('#import-results')
                .removeClass('success').addClass('error')
                .find('.results-content')
                .html('<p><?php esc_html_e('Import request failed.', 'formflow-pro'); ?></p>')
                .end().show();
        }).always(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import Configuration', 'formflow-pro'); ?>');
        });
    });

    // Toggle form selection
    $('input[name="include_forms"]').on('change', function() {
        $('.form-selection').toggle($(this).is(':checked'));
    });
});
</script>
