/**
 * FormFlow Pro - Forms Management Script
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Form Builder
     */
    const FormBuilder = {
        currentFormId: null,
        fieldCounter: 0,

        /**
         * Initialize
         */
        init() {
            this.setupEventListeners();
            console.log('FormFlow Forms Management initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Open form builder for new form
            $(document).on('click', '#add-new-form, #create-first-form', (e) => {
                e.preventDefault();
                this.openFormBuilder();
            });

            // Open form builder for editing
            $(document).on('click', '.edit-form', (e) => {
                e.preventDefault();
                const formId = $(e.currentTarget).data('form-id');
                this.openFormBuilder(formId);
            });

            // Close modal
            $(document).on('click', '.formflow-modal-close, #cancel-form-builder, .formflow-modal-overlay', () => {
                this.closeFormBuilder();
            });

            // Save form
            $(document).on('click', '#save-form-builder', () => {
                this.saveForm();
            });

            // Add field
            $(document).on('click', '#add-form-field', () => {
                this.addField();
            });

            // Remove field
            $(document).on('click', '.remove-field', (e) => {
                $(e.currentTarget).closest('.form-field-item').remove();
            });

            // Toggle Autentique settings
            $(document).on('change', '#enable-autentique', (e) => {
                if ($(e.currentTarget).is(':checked')) {
                    $('#autentique-settings').slideDown();
                    this.loadTemplates();
                } else {
                    $('#autentique-settings').slideUp();
                }
            });

            // Select all checkbox
            $(document).on('change', '#cb-select-all', function() {
                $('.formflow-forms-table input[type="checkbox"]').prop('checked', $(this).prop('checked'));
            });
        },

        /**
         * Open form builder modal
         */
        openFormBuilder(formId = null) {
            this.currentFormId = formId;
            this.fieldCounter = 0;

            if (formId) {
                // Load existing form
                this.loadForm(formId);
                $('#form-builder-title').text('Edit Form');
            } else {
                // Reset form for new form
                this.resetForm();
                $('#form-builder-title').text('Create New Form');
            }

            $('#form-builder-modal').fadeIn(200);
            $('body').addClass('modal-open');
        },

        /**
         * Close form builder modal
         */
        closeFormBuilder() {
            $('#form-builder-modal').fadeOut(200);
            $('body').removeClass('modal-open');
            this.currentFormId = null;
            this.resetForm();
        },

        /**
         * Reset form
         */
        resetForm() {
            $('#form-builder-form')[0].reset();
            $('#form-id').val('');
            $('#form-fields-container').empty();
            $('#autentique-settings').hide();
            this.fieldCounter = 0;

            // Add one default field
            this.addField();
        },

        /**
         * Load form data
         */
        loadForm(formId) {
            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_get_form',
                    form_id: formId,
                    nonce: formflowData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.populateForm(response.data);
                    } else {
                        alert(response.data.message || formflowData.strings.error);
                    }
                },
                error: () => {
                    alert(formflowData.strings.error);
                }
            });
        },

        /**
         * Populate form with data
         */
        populateForm(data) {
            $('#form-id').val(data.id);
            $('#form-name').val(data.name);
            $('#form-description').val(data.description);
            $('#form-status').val(data.status);

            // Parse and populate fields
            let fields = [];
            try {
                fields = JSON.parse(data.fields || '[]');
            } catch (e) {
                console.error('Error parsing form fields:', e);
            }

            $('#form-fields-container').empty();
            this.fieldCounter = 0;

            if (fields.length > 0) {
                fields.forEach((field) => {
                    this.addField(field);
                });
            } else {
                this.addField();
            }

            // Parse and populate settings
            let settings = {};
            try {
                settings = JSON.parse(data.settings || '{}');
            } catch (e) {
                console.error('Error parsing form settings:', e);
            }

            if (settings.autentique && settings.autentique.enabled) {
                $('#enable-autentique').prop('checked', true);
                $('#autentique-settings').show();
                $('#document-template').val(settings.autentique.template_id || '');
                this.loadTemplates();
            }
        },

        /**
         * Add field to form builder
         */
        addField(fieldData = null) {
            this.fieldCounter++;
            const fieldId = 'field-' + this.fieldCounter;

            const fieldTypes = [
                { value: 'text', label: 'Text' },
                { value: 'email', label: 'Email' },
                { value: 'tel', label: 'Phone' },
                { value: 'number', label: 'Number' },
                { value: 'textarea', label: 'Textarea' },
                { value: 'select', label: 'Select' },
                { value: 'checkbox', label: 'Checkbox' },
                { value: 'radio', label: 'Radio' },
                { value: 'date', label: 'Date' },
                { value: 'file', label: 'File Upload' }
            ];

            let fieldTypeOptions = '';
            fieldTypes.forEach((type) => {
                const selected = fieldData && fieldData.type === type.value ? 'selected' : '';
                fieldTypeOptions += `<option value="${type.value}" ${selected}>${type.label}</option>`;
            });

            const fieldHtml = `
                <div class="form-field-item" data-field-id="${fieldId}">
                    <div class="field-header">
                        <span class="dashicons dashicons-menu field-drag-handle"></span>
                        <input type="text"
                               class="field-label"
                               name="field_label[]"
                               value="${fieldData ? fieldData.label : ''}"
                               placeholder="Field Label"
                               required>
                        <button type="button" class="button-link-delete remove-field">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="field-settings">
                        <div class="field-setting">
                            <label>Field Type</label>
                            <select name="field_type[]" class="field-type">
                                ${fieldTypeOptions}
                            </select>
                        </div>
                        <div class="field-setting">
                            <label>Field Name</label>
                            <input type="text"
                                   name="field_name[]"
                                   value="${fieldData ? fieldData.name : ''}"
                                   placeholder="field_name"
                                   class="small-text">
                        </div>
                        <div class="field-setting">
                            <label>
                                <input type="checkbox"
                                       name="field_required_${this.fieldCounter}"
                                       value="1"
                                       ${fieldData && fieldData.required ? 'checked' : ''}>
                                Required
                            </label>
                        </div>
                        <div class="field-setting">
                            <label>Placeholder</label>
                            <input type="text"
                                   name="field_placeholder[]"
                                   value="${fieldData ? fieldData.placeholder || '' : ''}"
                                   placeholder="Optional placeholder">
                        </div>
                    </div>
                </div>
            `;

            $('#form-fields-container').append(fieldHtml);

            // Make fields sortable
            this.makeFieldsSortable();
        },

        /**
         * Make fields sortable
         */
        makeFieldsSortable() {
            if (typeof $.fn.sortable !== 'undefined') {
                $('#form-fields-container').sortable({
                    handle: '.field-drag-handle',
                    axis: 'y',
                    cursor: 'move',
                    opacity: 0.7
                });
            }
        },

        /**
         * Save form
         */
        saveForm() {
            const form = $('#form-builder-form');

            // Validate
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }

            // Collect form data
            const formData = {
                action: 'formflow_save_form',
                nonce: formflowData.nonce,
                form_id: $('#form-id').val(),
                form_name: $('#form-name').val(),
                form_description: $('#form-description').val(),
                form_status: $('#form-status').val(),
                fields: this.collectFields(),
                settings: this.collectSettings()
            };

            // Show loading
            const $saveBtn = $('#save-form-builder');
            const originalText = $saveBtn.text();
            $saveBtn.prop('disabled', true).text(formflowData.strings.processing);

            // Save via AJAX
            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        alert('Form saved successfully!');
                        location.reload();
                    } else {
                        alert(response.data.message || formflowData.strings.error);
                    }
                },
                error: () => {
                    alert(formflowData.strings.error);
                },
                complete: () => {
                    $saveBtn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Collect fields data
         */
        collectFields() {
            const fields = [];

            $('#form-fields-container .form-field-item').each(function() {
                const $field = $(this);
                const fieldIndex = $field.index();

                fields.push({
                    label: $field.find('[name="field_label[]"]').val(),
                    type: $field.find('[name="field_type[]"]').val(),
                    name: $field.find('[name="field_name[]"]').val(),
                    required: $field.find(`[name="field_required_${fieldIndex + 1}"]`).is(':checked'),
                    placeholder: $field.find('[name="field_placeholder[]"]').val()
                });
            });

            return JSON.stringify(fields);
        },

        /**
         * Collect settings
         */
        collectSettings() {
            const settings = {
                autentique: {
                    enabled: $('#enable-autentique').is(':checked'),
                    template_id: $('#document-template').val()
                }
            };

            return JSON.stringify(settings);
        },

        /**
         * Load Autentique templates
         */
        loadTemplates() {
            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_get_templates',
                    nonce: formflowData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const $select = $('#document-template');
                        $select.empty().append('<option value="">Select template...</option>');

                        if (response.data && response.data.length > 0) {
                            response.data.forEach((template) => {
                                $select.append(`<option value="${template.id}">${template.name}</option>`);
                            });
                        }
                    }
                },
                error: () => {
                    console.error('Failed to load templates');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(() => {
        FormBuilder.init();
    });

})(jQuery);
