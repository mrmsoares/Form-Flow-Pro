/**
 * FormFlow Pro - Forms Management Tests
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

import { screen, fireEvent, waitFor } from '@testing-library/dom';
import '@testing-library/jest-dom';

describe('FormBuilder', () => {
  let FormBuilder;
  let consoleLogSpy;
  let mockAjax;

  beforeEach(() => {
    // Clear the document body
    document.body.innerHTML = '';

    // Reset all mocks
    jest.clearAllMocks();

    // Spy on console.log
    consoleLogSpy = jest.spyOn(console, 'log').mockImplementation();

    // Mock jQuery AJAX
    mockAjax = jest.fn();
    global.jQuery.ajax = mockAjax;
    global.$.ajax = mockAjax;

    // Mock formflowData
    global.formflowData = {
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      strings: {
        error: 'An error occurred',
        processing: 'Processing...'
      }
    };

    // Mock window functions
    window.alert = jest.fn();
    window.location = { reload: jest.fn() };

    // Mock jQuery sortable
    global.jQuery.fn = {
      sortable: jest.fn(),
      checkValidity: jest.fn(() => true),
      reportValidity: jest.fn()
    };

    // Define FormBuilder object
    FormBuilder = {
      currentFormId: null,
      fieldCounter: 0,

      init() {
        this.setupEventListeners();
        console.log('FormFlow Forms Management initialized');
      },

      setupEventListeners() {
        $(document).on('click', '#add-new-form, #create-first-form', (e) => {
          e.preventDefault();
          this.openFormBuilder();
        });

        $(document).on('click', '.edit-form', (e) => {
          e.preventDefault();
          const formId = $(e.currentTarget).data('form-id');
          this.openFormBuilder(formId);
        });

        $(document).on('click', '.formflow-modal-close, #cancel-form-builder, .formflow-modal-overlay', () => {
          this.closeFormBuilder();
        });

        $(document).on('click', '#save-form-builder', () => {
          this.saveForm();
        });

        $(document).on('click', '#add-form-field', () => {
          this.addField();
        });

        $(document).on('click', '.remove-field', (e) => {
          $(e.currentTarget).closest('.form-field-item').remove();
        });

        $(document).on('change', '#enable-autentique', (e) => {
          if ($(e.currentTarget).is(':checked')) {
            $('#autentique-settings').slideDown();
            this.loadTemplates();
          } else {
            $('#autentique-settings').slideUp();
          }
        });

        $(document).on('change', '#cb-select-all', function() {
          $('.formflow-forms-table input[type="checkbox"]').prop('checked', $(this).prop('checked'));
        });
      },

      openFormBuilder(formId = null) {
        this.currentFormId = formId;
        this.fieldCounter = 0;

        if (formId) {
          this.loadForm(formId);
          $('#form-builder-title').text('Edit Form');
        } else {
          this.resetForm();
          $('#form-builder-title').text('Create New Form');
        }

        $('#form-builder-modal').fadeIn(200);
        $('body').addClass('modal-open');
      },

      closeFormBuilder() {
        $('#form-builder-modal').fadeOut(200);
        $('body').removeClass('modal-open');
        this.currentFormId = null;
        this.resetForm();
      },

      resetForm() {
        $('#form-builder-form')[0].reset();
        $('#form-id').val('');
        $('#form-fields-container').empty();
        $('#autentique-settings').hide();
        this.fieldCounter = 0;
        this.addField();
      },

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

      populateForm(data) {
        $('#form-id').val(data.id);
        $('#form-name').val(data.name);
        $('#form-description').val(data.description);
        $('#form-status').val(data.status);

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
        this.makeFieldsSortable();
      },

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

      saveForm() {
        const form = $('#form-builder-form');

        if (!form[0].checkValidity()) {
          form[0].reportValidity();
          return;
        }

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

        const $saveBtn = $('#save-form-builder');
        const originalText = $saveBtn.text();
        $saveBtn.prop('disabled', true).text(formflowData.strings.processing);

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

      collectSettings() {
        const settings = {
          autentique: {
            enabled: $('#enable-autentique').is(':checked'),
            template_id: $('#document-template').val()
          }
        };

        return JSON.stringify(settings);
      },

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
  });

  afterEach(() => {
    consoleLogSpy.mockRestore();
  });

  describe('Initialization', () => {
    test('should initialize FormBuilder object', () => {
      expect(FormBuilder).toBeDefined();
      expect(typeof FormBuilder.init).toBe('function');
    });

    test('should log initialization message', () => {
      FormBuilder.init();
      expect(consoleLogSpy).toHaveBeenCalledWith('FormFlow Forms Management initialized');
    });

    test('should call setupEventListeners on init', () => {
      const setupSpy = jest.spyOn(FormBuilder, 'setupEventListeners');
      FormBuilder.init();
      expect(setupSpy).toHaveBeenCalled();
    });

    test('should initialize with null currentFormId', () => {
      expect(FormBuilder.currentFormId).toBeNull();
    });

    test('should initialize with zero fieldCounter', () => {
      expect(FormBuilder.fieldCounter).toBe(0);
    });
  });

  describe('Open Form Builder', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="form-builder-modal" style="display: none;"></div>
        <h2 id="form-builder-title"></h2>
      `;
    });

    test('should open form builder for new form', () => {
      const mockFadeIn = jest.fn();
      const mockAddClass = jest.fn();
      const mockText = jest.fn();
      const resetSpy = jest.spyOn(FormBuilder, 'resetForm');

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-modal') {
          return { fadeIn: mockFadeIn };
        }
        if (selector === 'body') {
          return { addClass: mockAddClass };
        }
        if (selector === '#form-builder-title') {
          return { text: mockText };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.openFormBuilder();

      expect(mockFadeIn).toHaveBeenCalledWith(200);
      expect(mockAddClass).toHaveBeenCalledWith('modal-open');
      expect(mockText).toHaveBeenCalledWith('Create New Form');
      expect(resetSpy).toHaveBeenCalled();
    });

    test('should open form builder for editing', () => {
      const mockText = jest.fn();
      const loadSpy = jest.spyOn(FormBuilder, 'loadForm').mockImplementation();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-title') {
          return { text: mockText };
        }
        return { fadeIn: jest.fn(), addClass: jest.fn() };
      });
      global.$ = global.jQuery;

      FormBuilder.openFormBuilder(123);

      expect(FormBuilder.currentFormId).toBe(123);
      expect(mockText).toHaveBeenCalledWith('Edit Form');
      expect(loadSpy).toHaveBeenCalledWith(123);
    });

    test('should reset fieldCounter when opening', () => {
      FormBuilder.fieldCounter = 10;

      global.jQuery.mockImplementation(() => ({
        fadeIn: jest.fn(),
        addClass: jest.fn(),
        text: jest.fn()
      }));
      global.$ = global.jQuery;

      jest.spyOn(FormBuilder, 'resetForm').mockImplementation();

      FormBuilder.openFormBuilder();

      expect(FormBuilder.fieldCounter).toBe(0);
    });
  });

  describe('Close Form Builder', () => {
    test('should close modal and reset', () => {
      const mockFadeOut = jest.fn();
      const mockRemoveClass = jest.fn();
      const resetSpy = jest.spyOn(FormBuilder, 'resetForm').mockImplementation();

      FormBuilder.currentFormId = 123;

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-modal') {
          return { fadeOut: mockFadeOut };
        }
        if (selector === 'body') {
          return { removeClass: mockRemoveClass };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.closeFormBuilder();

      expect(mockFadeOut).toHaveBeenCalledWith(200);
      expect(mockRemoveClass).toHaveBeenCalledWith('modal-open');
      expect(FormBuilder.currentFormId).toBeNull();
      expect(resetSpy).toHaveBeenCalled();
    });
  });

  describe('Reset Form', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form id="form-builder-form">
          <input id="form-id">
          <div id="form-fields-container"></div>
          <div id="autentique-settings"></div>
        </form>
      `;
    });

    test('should reset all form fields', () => {
      const mockReset = jest.fn();
      const mockVal = jest.fn();
      const mockEmpty = jest.fn();
      const mockHide = jest.fn();
      const addFieldSpy = jest.spyOn(FormBuilder, 'addField').mockImplementation();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-form') {
          return [{ reset: mockReset }];
        }
        if (selector === '#form-id') {
          return { val: mockVal };
        }
        if (selector === '#form-fields-container') {
          return { empty: mockEmpty };
        }
        if (selector === '#autentique-settings') {
          return { hide: mockHide };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.resetForm();

      expect(mockReset).toHaveBeenCalled();
      expect(mockVal).toHaveBeenCalledWith('');
      expect(mockEmpty).toHaveBeenCalled();
      expect(mockHide).toHaveBeenCalled();
      expect(FormBuilder.fieldCounter).toBe(0);
      expect(addFieldSpy).toHaveBeenCalled();
    });
  });

  describe('Load Form', () => {
    test('should make AJAX request to load form', () => {
      global.$.ajax = mockAjax;

      FormBuilder.loadForm(456);

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_get_form',
          form_id: 456,
          nonce: 'test-nonce'
        })
      }));
    });

    test('should populate form on success', () => {
      const populateSpy = jest.spyOn(FormBuilder, 'populateForm');

      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: { id: 456, name: 'Test Form' } });
      });

      FormBuilder.loadForm(456);

      expect(populateSpy).toHaveBeenCalledWith({ id: 456, name: 'Test Form' });
    });

    test('should show error on failure', () => {
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false, data: { message: 'Not found' } });
      });

      FormBuilder.loadForm(999);

      expect(window.alert).toHaveBeenCalledWith('Not found');
    });

    test('should handle AJAX error', () => {
      global.$.ajax = jest.fn((options) => {
        options.error();
      });

      FormBuilder.loadForm(456);

      expect(window.alert).toHaveBeenCalledWith(formflowData.strings.error);
    });
  });

  describe('Populate Form', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input id="form-id">
        <input id="form-name">
        <textarea id="form-description"></textarea>
        <select id="form-status"></select>
        <div id="form-fields-container"></div>
        <input type="checkbox" id="enable-autentique">
        <div id="autentique-settings"></div>
        <select id="document-template"></select>
      `;
    });

    test('should populate basic form data', () => {
      const mockVal = jest.fn();
      const mockEmpty = jest.fn();
      const addFieldSpy = jest.spyOn(FormBuilder, 'addField').mockImplementation();

      global.jQuery.mockImplementation((selector) => {
        if (selector.includes('#form-')) {
          return { val: mockVal };
        }
        if (selector === '#form-fields-container') {
          return { empty: mockEmpty };
        }
        return { val: jest.fn(), prop: jest.fn(), show: jest.fn() };
      });
      global.$ = global.jQuery;

      const data = {
        id: '123',
        name: 'Contact Form',
        description: 'A contact form',
        status: 'active',
        fields: '[]',
        settings: '{}'
      };

      FormBuilder.populateForm(data);

      expect(mockVal).toHaveBeenCalledWith('123');
      expect(mockVal).toHaveBeenCalledWith('Contact Form');
      expect(mockVal).toHaveBeenCalledWith('A contact form');
      expect(mockVal).toHaveBeenCalledWith('active');
    });

    test('should parse and add fields', () => {
      const addFieldSpy = jest.spyOn(FormBuilder, 'addField').mockImplementation();

      global.jQuery.mockImplementation(() => ({
        val: jest.fn(),
        empty: jest.fn(),
        prop: jest.fn(),
        show: jest.fn()
      }));
      global.$ = global.jQuery;

      const data = {
        id: '123',
        name: 'Form',
        fields: JSON.stringify([
          { label: 'Name', type: 'text' },
          { label: 'Email', type: 'email' }
        ]),
        settings: '{}'
      };

      FormBuilder.populateForm(data);

      expect(addFieldSpy).toHaveBeenCalledTimes(2);
    });

    test('should handle invalid JSON in fields', () => {
      const consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
      const addFieldSpy = jest.spyOn(FormBuilder, 'addField').mockImplementation();

      global.jQuery.mockImplementation(() => ({
        val: jest.fn(),
        empty: jest.fn(),
        prop: jest.fn(),
        show: jest.fn()
      }));
      global.$ = global.jQuery;

      const data = {
        id: '123',
        name: 'Form',
        fields: 'invalid json',
        settings: '{}'
      };

      FormBuilder.populateForm(data);

      expect(consoleErrorSpy).toHaveBeenCalled();
      expect(addFieldSpy).toHaveBeenCalled();

      consoleErrorSpy.mockRestore();
    });

    test('should populate Autentique settings', () => {
      const mockProp = jest.fn();
      const mockShow = jest.fn();
      const mockVal = jest.fn();
      const loadTemplatesSpy = jest.spyOn(FormBuilder, 'loadTemplates').mockImplementation();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#enable-autentique') {
          return { prop: mockProp };
        }
        if (selector === '#autentique-settings') {
          return { show: mockShow };
        }
        if (selector === '#document-template') {
          return { val: mockVal };
        }
        return { val: jest.fn(), empty: jest.fn() };
      });
      global.$ = global.jQuery;

      jest.spyOn(FormBuilder, 'addField').mockImplementation();

      const data = {
        id: '123',
        name: 'Form',
        fields: '[]',
        settings: JSON.stringify({
          autentique: {
            enabled: true,
            template_id: 'template-123'
          }
        })
      };

      FormBuilder.populateForm(data);

      expect(mockProp).toHaveBeenCalledWith('checked', true);
      expect(mockShow).toHaveBeenCalled();
      expect(mockVal).toHaveBeenCalledWith('template-123');
      expect(loadTemplatesSpy).toHaveBeenCalled();
    });
  });

  describe('Add Field', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="form-fields-container"></div>
      `;
    });

    test('should increment fieldCounter', () => {
      FormBuilder.fieldCounter = 5;

      const mockAppend = jest.fn();
      const makeSortableSpy = jest.spyOn(FormBuilder, 'makeFieldsSortable').mockImplementation();

      global.jQuery.mockImplementation(() => ({
        append: mockAppend
      }));
      global.$ = global.jQuery;

      FormBuilder.addField();

      expect(FormBuilder.fieldCounter).toBe(6);
      expect(mockAppend).toHaveBeenCalled();
      expect(makeSortableSpy).toHaveBeenCalled();
    });

    test('should add field HTML to container', () => {
      const mockAppend = jest.fn();

      global.jQuery.mockImplementation(() => ({
        append: mockAppend
      }));
      global.$ = global.jQuery;

      jest.spyOn(FormBuilder, 'makeFieldsSortable').mockImplementation();

      FormBuilder.addField();

      expect(mockAppend).toHaveBeenCalledWith(expect.stringContaining('form-field-item'));
    });

    test('should populate field with data', () => {
      const mockAppend = jest.fn();

      global.jQuery.mockImplementation(() => ({
        append: mockAppend
      }));
      global.$ = global.jQuery;

      jest.spyOn(FormBuilder, 'makeFieldsSortable').mockImplementation();

      const fieldData = {
        label: 'Email Address',
        type: 'email',
        name: 'email',
        required: true,
        placeholder: 'Enter your email'
      };

      FormBuilder.addField(fieldData);

      const appendedHtml = mockAppend.mock.calls[0][0];
      expect(appendedHtml).toContain('Email Address');
      expect(appendedHtml).toContain('email');
      expect(appendedHtml).toContain('checked');
    });

    test('should include all field types', () => {
      const mockAppend = jest.fn();

      global.jQuery.mockImplementation(() => ({
        append: mockAppend
      }));
      global.$ = global.jQuery;

      jest.spyOn(FormBuilder, 'makeFieldsSortable').mockImplementation();

      FormBuilder.addField();

      const appendedHtml = mockAppend.mock.calls[0][0];
      expect(appendedHtml).toContain('Text');
      expect(appendedHtml).toContain('Email');
      expect(appendedHtml).toContain('Phone');
      expect(appendedHtml).toContain('File Upload');
    });
  });

  describe('Save Form', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <form id="form-builder-form">
          <input id="form-id" value="123">
          <input id="form-name" value="Contact Form">
          <textarea id="form-description">Description</textarea>
          <select id="form-status">
            <option value="active" selected>Active</option>
          </select>
        </form>
        <button id="save-form-builder">Save Form</button>
      `;
    });

    test('should validate form before saving', () => {
      const mockForm = [{ checkValidity: jest.fn(() => false), reportValidity: jest.fn() }];

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-form') {
          return mockForm;
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.saveForm();

      expect(mockForm[0].checkValidity).toHaveBeenCalled();
      expect(mockForm[0].reportValidity).toHaveBeenCalled();
    });

    test('should make AJAX request to save form', () => {
      const mockForm = [{ checkValidity: jest.fn(() => true) }];
      const collectFieldsSpy = jest.spyOn(FormBuilder, 'collectFields').mockReturnValue('[]');
      const collectSettingsSpy = jest.spyOn(FormBuilder, 'collectSettings').mockReturnValue('{}');

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-form') {
          return mockForm;
        }
        if (selector === '#save-form-builder') {
          return {
            prop: jest.fn(() => ({ text: jest.fn() })),
            text: jest.fn(() => 'Save Form')
          };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;
      global.$.ajax = mockAjax;

      FormBuilder.saveForm();

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_save_form',
          nonce: 'test-nonce'
        })
      }));
    });

    test('should reload page on success', () => {
      const mockForm = [{ checkValidity: jest.fn(() => true) }];

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-form') {
          return mockForm;
        }
        if (selector === '#save-form-builder') {
          return {
            prop: jest.fn(() => ({ text: jest.fn() })),
            text: jest.fn(() => 'Save Form')
          };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true });
        options.complete();
      });

      jest.spyOn(FormBuilder, 'collectFields').mockReturnValue('[]');
      jest.spyOn(FormBuilder, 'collectSettings').mockReturnValue('{}');

      FormBuilder.saveForm();

      expect(window.alert).toHaveBeenCalledWith('Form saved successfully!');
      expect(window.location.reload).toHaveBeenCalled();
    });

    test('should show error on failure', () => {
      const mockForm = [{ checkValidity: jest.fn(() => true) }];

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-builder-form') {
          return mockForm;
        }
        if (selector === '#save-form-builder') {
          return {
            prop: jest.fn(() => ({ text: jest.fn() })),
            text: jest.fn(() => 'Save Form')
          };
        }
        return { val: jest.fn(() => '') };
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false, data: { message: 'Save failed' } });
        options.complete();
      });

      jest.spyOn(FormBuilder, 'collectFields').mockReturnValue('[]');
      jest.spyOn(FormBuilder, 'collectSettings').mockReturnValue('{}');

      FormBuilder.saveForm();

      expect(window.alert).toHaveBeenCalledWith('Save failed');
    });
  });

  describe('Collect Fields', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="form-fields-container">
          <div class="form-field-item">
            <input name="field_label[]" value="Name">
            <select name="field_type[]"><option value="text" selected>Text</option></select>
            <input name="field_name[]" value="name">
            <input type="checkbox" name="field_required_1" checked>
            <input name="field_placeholder[]" value="Enter your name">
          </div>
        </div>
      `;
    });

    test('should collect fields data as JSON', () => {
      const mockEach = jest.fn((callback) => {
        const mockField = {
          index: jest.fn(() => 0),
          find: jest.fn((selector) => ({
            val: jest.fn(() => {
              if (selector.includes('label')) return 'Name';
              if (selector.includes('type')) return 'text';
              if (selector.includes('name')) return 'name';
              if (selector.includes('placeholder')) return 'Enter your name';
              return '';
            }),
            is: jest.fn(() => true)
          }))
        };

        callback.call(mockField, 0, mockField);
      });

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#form-fields-container .form-field-item') {
          return { each: mockEach };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      const result = FormBuilder.collectFields();
      const parsed = JSON.parse(result);

      expect(Array.isArray(parsed)).toBe(true);
      expect(parsed).toHaveLength(1);
      expect(parsed[0]).toHaveProperty('label', 'Name');
      expect(parsed[0]).toHaveProperty('type', 'text');
    });
  });

  describe('Collect Settings', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input type="checkbox" id="enable-autentique" checked>
        <select id="document-template"><option value="tpl-123" selected>Template</option></select>
      `;
    });

    test('should collect settings as JSON', () => {
      global.jQuery.mockImplementation((selector) => {
        if (selector === '#enable-autentique') {
          return { is: jest.fn(() => true) };
        }
        if (selector === '#document-template') {
          return { val: jest.fn(() => 'tpl-123') };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      const result = FormBuilder.collectSettings();
      const parsed = JSON.parse(result);

      expect(parsed.autentique.enabled).toBe(true);
      expect(parsed.autentique.template_id).toBe('tpl-123');
    });
  });

  describe('Load Templates', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <select id="document-template"></select>
      `;
    });

    test('should load templates via AJAX', () => {
      global.$.ajax = mockAjax;

      FormBuilder.loadTemplates();

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_get_templates',
          nonce: 'test-nonce'
        })
      }));
    });

    test('should populate select with templates', () => {
      const mockEmpty = jest.fn(() => ({ append: jest.fn() }));
      const mockAppend = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#document-template') {
          return {
            empty: mockEmpty,
            append: mockAppend
          };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({
          success: true,
          data: [
            { id: '1', name: 'Template 1' },
            { id: '2', name: 'Template 2' }
          ]
        });
      });

      FormBuilder.loadTemplates();

      expect(mockAppend).toHaveBeenCalledTimes(2);
    });

    test('should handle empty template list', () => {
      const mockEmpty = jest.fn(() => ({ append: jest.fn() }));

      global.jQuery.mockImplementation(() => ({
        empty: mockEmpty,
        append: jest.fn()
      }));
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: [] });
      });

      FormBuilder.loadTemplates();

      expect(mockEmpty).toHaveBeenCalled();
    });
  });

  describe('Make Fields Sortable', () => {
    test('should initialize sortable if available', () => {
      const mockSortable = jest.fn();

      global.jQuery.fn.sortable = mockSortable;
      global.jQuery.mockImplementation(() => ({
        sortable: mockSortable
      }));
      global.$ = global.jQuery;

      FormBuilder.makeFieldsSortable();

      expect(mockSortable).toHaveBeenCalledWith(expect.objectContaining({
        handle: '.field-drag-handle',
        axis: 'y'
      }));
    });

    test('should not fail if sortable is unavailable', () => {
      global.jQuery.fn.sortable = undefined;

      expect(() => FormBuilder.makeFieldsSortable()).not.toThrow();
    });
  });

  describe('Event Listeners Setup', () => {
    test('should setup new form listener', () => {
      const mockOn = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === document) {
          return { on: mockOn };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('click', '#add-new-form, #create-first-form', expect.any(Function));
    });

    test('should setup edit form listener', () => {
      const mockOn = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === document) {
          return { on: mockOn };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('click', '.edit-form', expect.any(Function));
    });

    test('should setup save form listener', () => {
      const mockOn = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === document) {
          return { on: mockOn };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      FormBuilder.setupEventListeners();

      expect(mockOn).toHaveBeenCalledWith('click', '#save-form-builder', expect.any(Function));
    });
  });
});
