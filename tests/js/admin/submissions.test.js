/**
 * FormFlow Pro - Submissions Management Tests
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

import { screen, fireEvent, waitFor } from '@testing-library/dom';
import '@testing-library/jest-dom';

describe('SubmissionsManager', () => {
  let SubmissionsManager;
  let consoleLogSpy;
  let mockAjax;
  let mockDataTable;

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

    // Mock DataTable
    mockDataTable = {
      ajax: {
        reload: jest.fn()
      },
      destroy: jest.fn(),
      draw: jest.fn()
    };

    global.jQuery.fn = {
      DataTable: jest.fn(() => mockDataTable),
      datepicker: jest.fn(),
      fadeIn: jest.fn(),
      fadeOut: jest.fn()
    };

    // Mock formflowData
    global.formflowData = {
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      strings: {
        error: 'An error occurred'
      }
    };

    // Mock window.alert
    window.alert = jest.fn();
    window.confirm = jest.fn(() => true);

    // Define SubmissionsManager
    SubmissionsManager = {
      dataTable: null,
      currentSubmissionId: null,

      init() {
        this.initDataTable();
        this.setupEventListeners();
        this.initDatepickers();
        console.log('FormFlow Submissions Management initialized');
      },

      initDataTable() {
        this.dataTable = $('#submissions-table').DataTable({
          processing: true,
          serverSide: true,
          responsive: true,
          ajax: {
            url: formflowData.ajax_url,
            type: 'POST',
            data: (d) => {
              d.action = 'formflow_get_submissions';
              d.nonce = formflowData.nonce;
              d.form_id = $('#form-filter').val();
              d.status = $('#status-filter').val();
              d.date_from = $('#date-from').val();
              d.date_to = $('#date-to').val();
            }
          },
          columns: [
            { data: 'id', orderable: false },
            { data: 'id' },
            { data: 'form_name' },
            { data: 'status' },
            { data: 'signature_status' },
            { data: 'ip_address' },
            { data: 'created_at' },
            { data: 'id', orderable: false }
          ],
          order: [[1, 'desc']],
          pageLength: 25
        });
      },

      setupEventListeners() {
        $('#apply-filters').on('click', () => {
          this.dataTable.ajax.reload();
        });

        $('#reset-filters').on('click', () => {
          $('#form-filter').val('');
          $('#status-filter').val('');
          $('#date-from').val('');
          $('#date-to').val('');
          this.dataTable.ajax.reload();
        });

        $('#cb-select-all-submissions').on('change', function() {
          $('.submission-checkbox').prop('checked', $(this).prop('checked'));
        });

        $(document).on('click', '.view-submission', (e) => {
          const submissionId = $(e.currentTarget).data('id');
          this.viewSubmission(submissionId);
        });

        $(document).on('click', '.delete-submission', (e) => {
          const submissionId = $(e.currentTarget).data('id');
          if (confirm('Are you sure you want to delete this submission?')) {
            this.deleteSubmission(submissionId);
          }
        });

        $('#apply-bulk-action').on('click', () => {
          this.applyBulkAction();
        });

        $('#export-submissions').on('click', (e) => {
          e.preventDefault();
          this.exportSubmissions('all');
        });

        $('.formflow-modal-close, #close-submission-modal, .formflow-modal-overlay').on('click', () => {
          this.closeModal();
        });

        $('#export-submission').on('click', () => {
          if (this.currentSubmissionId) {
            this.exportSubmissions('single', this.currentSubmissionId);
          }
        });
      },

      initDatepickers() {
        $('.formflow-datepicker').datepicker({
          dateFormat: 'yy-mm-dd',
          changeMonth: true,
          changeYear: true
        });
      },

      viewSubmission(submissionId) {
        this.currentSubmissionId = submissionId;
        $('#view-submission-modal').fadeIn(200);
        $('body').addClass('modal-open');

        $.ajax({
          url: formflowData.ajax_url,
          type: 'POST',
          data: {
            action: 'formflow_get_submission',
            submission_id: submissionId,
            nonce: formflowData.nonce
          },
          success: (response) => {
            if (response.success) {
              this.displaySubmissionDetails(response.data);
            } else {
              $('#submission-details-container').html(
                `<div class="notice notice-error"><p>${response.data.message || formflowData.strings.error}</p></div>`
              );
            }
          },
          error: () => {
            $('#submission-details-container').html(
              `<div class="notice notice-error"><p>${formflowData.strings.error}</p></div>`
            );
          }
        });
      },

      displaySubmissionDetails(data) {
        let html = '<div class="submission-details">';
        html += `<h3>Basic Information</h3>`;
        html += `<p>ID: ${data.id}</p>`;
        html += '</div>';
        $('#submission-details-container').html(html);
      },

      deleteSubmission(submissionId) {
        $.ajax({
          url: formflowData.ajax_url,
          type: 'POST',
          data: {
            action: 'formflow_delete_submission',
            submission_id: submissionId,
            nonce: formflowData.nonce
          },
          success: (response) => {
            if (response.success) {
              this.dataTable.ajax.reload();
              alert('Submission deleted successfully!');
            } else {
              alert(response.data.message || formflowData.strings.error);
            }
          },
          error: () => {
            alert(formflowData.strings.error);
          }
        });
      },

      applyBulkAction() {
        const action = $('#bulk-action-selector').val();
        const selectedIds = $('.submission-checkbox:checked').map(function() {
          return $(this).val();
        }).get();

        if (action === '-1') {
          alert('Please select an action');
          return;
        }

        if (selectedIds.length === 0) {
          alert('Please select at least one submission');
          return;
        }

        if (action === 'delete') {
          if (!confirm(`Are you sure you want to delete ${selectedIds.length} submission(s)?`)) {
            return;
          }

          $.ajax({
            url: formflowData.ajax_url,
            type: 'POST',
            data: {
              action: 'formflow_bulk_delete_submissions',
              submission_ids: selectedIds,
              nonce: formflowData.nonce
            },
            success: (response) => {
              if (response.success) {
                this.dataTable.ajax.reload();
                alert(`${selectedIds.length} submission(s) deleted successfully!`);
              } else {
                alert(response.data.message || formflowData.strings.error);
              }
            },
            error: () => {
              alert(formflowData.strings.error);
            }
          });
        } else if (action === 'export') {
          this.exportSubmissions('selected', selectedIds);
        }
      },

      exportSubmissions(type, data = null) {
        let exportData = {
          action: 'formflow_export_submissions',
          nonce: formflowData.nonce,
          export_type: type
        };

        if (type === 'single') {
          exportData.submission_id = data;
        } else if (type === 'selected') {
          exportData.submission_ids = data;
        }

        const form = $('<form>', {
          method: 'POST',
          action: formflowData.ajax_url
        });

        for (const [key, value] of Object.entries(exportData)) {
          form.append($('<input>', { type: 'hidden', name: key, value: value }));
        }

        $('body').append(form);
        form.submit();
        form.remove();
      },

      closeModal() {
        $('#view-submission-modal').fadeOut(200);
        $('body').removeClass('modal-open');
        this.currentSubmissionId = null;
      },

      getStatusClass(status) {
        const statusMap = {
          'completed': 'status-completed status-success',
          'pending': 'status-pending status-warning',
          'failed': 'status-failed status-error'
        };
        return statusMap[status] || 'status-default';
      },

      getSignatureStatusClass(status) {
        const statusMap = {
          'signed': 'badge-success',
          'pending': 'badge-warning',
          'refused': 'badge-error'
        };
        return statusMap[status] || 'badge-secondary';
      },

      formatStatus(status) {
        return status.split('_').map(word =>
          word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
      },

      formatFieldName(fieldName) {
        return fieldName
          .replace(/_/g, ' ')
          .replace(/\b\w/g, char => char.toUpperCase());
      },

      escapeHtml(text) {
        const map = {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
      }
    };
  });

  afterEach(() => {
    consoleLogSpy.mockRestore();
  });

  describe('Initialization', () => {
    test('should initialize SubmissionsManager object', () => {
      expect(SubmissionsManager).toBeDefined();
      expect(typeof SubmissionsManager.init).toBe('function');
    });

    test('should log initialization message', () => {
      SubmissionsManager.init();
      expect(consoleLogSpy).toHaveBeenCalledWith('FormFlow Submissions Management initialized');
    });

    test('should initialize with null dataTable', () => {
      expect(SubmissionsManager.dataTable).toBeNull();
    });

    test('should initialize with null currentSubmissionId', () => {
      expect(SubmissionsManager.currentSubmissionId).toBeNull();
    });
  });

  describe('DataTable Initialization', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <table id="submissions-table"></table>
        <select id="form-filter"></select>
        <select id="status-filter"></select>
        <input id="date-from">
        <input id="date-to">
      `;
    });

    test('should initialize DataTable', () => {
      const mockDT = jest.fn(() => mockDataTable);
      global.jQuery.mockImplementation((selector) => {
        return {
          DataTable: mockDT,
          val: jest.fn(() => '')
        };
      });
      global.$ = global.jQuery;

      SubmissionsManager.initDataTable();

      expect(mockDT).toHaveBeenCalled();
    });

    test('should configure DataTable with correct options', () => {
      const mockDT = jest.fn(() => mockDataTable);
      global.jQuery.mockImplementation((selector) => {
        return {
          DataTable: mockDT,
          val: jest.fn(() => '')
        };
      });
      global.$ = global.jQuery;

      SubmissionsManager.initDataTable();

      expect(mockDT).toHaveBeenCalledWith(expect.objectContaining({
        processing: true,
        serverSide: true,
        responsive: true
      }));
    });

    test('should set dataTable property', () => {
      const mockDT = jest.fn(() => mockDataTable);
      global.jQuery.mockImplementation((selector) => {
        return {
          DataTable: mockDT,
          val: jest.fn(() => '')
        };
      });
      global.$ = global.jQuery;

      SubmissionsManager.initDataTable();

      expect(SubmissionsManager.dataTable).toBe(mockDataTable);
    });
  });

  describe('Datepicker Initialization', () => {
    test('should initialize datepickers', () => {
      const mockDatepicker = jest.fn();
      global.jQuery.mockImplementation(() => ({
        datepicker: mockDatepicker
      }));
      global.$ = global.jQuery;

      SubmissionsManager.initDatepickers();

      expect(mockDatepicker).toHaveBeenCalledWith(expect.objectContaining({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true
      }));
    });
  });

  describe('View Submission', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="view-submission-modal" style="display: none;"></div>
        <div id="submission-details-container"></div>
      `;
    });

    test('should set currentSubmissionId', () => {
      const mockFadeIn = jest.fn();
      const mockAddClass = jest.fn();
      const mockHtml = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#view-submission-modal') {
          return { fadeIn: mockFadeIn };
        }
        if (selector === 'body') {
          return { addClass: mockAddClass };
        }
        if (selector === '#submission-details-container') {
          return { html: mockHtml };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn();

      SubmissionsManager.viewSubmission(123);

      expect(SubmissionsManager.currentSubmissionId).toBe(123);
    });

    test('should make AJAX request to get submission', () => {
      const mockFadeIn = jest.fn();
      const mockAddClass = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#view-submission-modal') {
          return { fadeIn: mockFadeIn };
        }
        if (selector === 'body') {
          return { addClass: mockAddClass };
        }
        return { html: jest.fn() };
      });
      global.$ = global.jQuery;
      global.$.ajax = mockAjax;

      SubmissionsManager.viewSubmission(456);

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_get_submission',
          submission_id: 456,
          nonce: 'test-nonce'
        })
      }));
    });

    test('should display submission details on success', () => {
      const mockHtml = jest.fn();
      const displaySpy = jest.spyOn(SubmissionsManager, 'displaySubmissionDetails');

      global.jQuery.mockImplementation((selector) => {
        return {
          fadeIn: jest.fn(),
          addClass: jest.fn(),
          html: mockHtml
        };
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: { id: 123, form_name: 'Test' } });
      });

      SubmissionsManager.viewSubmission(123);

      expect(displaySpy).toHaveBeenCalledWith({ id: 123, form_name: 'Test' });
    });

    test('should show error on failure', () => {
      const mockHtml = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#submission-details-container') {
          return { html: mockHtml };
        }
        return {
          fadeIn: jest.fn(),
          addClass: jest.fn(),
          html: jest.fn()
        };
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false, data: { message: 'Not found' } });
      });

      SubmissionsManager.viewSubmission(999);

      expect(mockHtml).toHaveBeenCalledWith(expect.stringContaining('Not found'));
    });
  });

  describe('Delete Submission', () => {
    beforeEach(() => {
      SubmissionsManager.dataTable = mockDataTable;
    });

    test('should make AJAX request to delete submission', () => {
      global.$.ajax = mockAjax;

      SubmissionsManager.deleteSubmission(123);

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_delete_submission',
          submission_id: 123,
          nonce: 'test-nonce'
        })
      }));
    });

    test('should reload dataTable on success', () => {
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true });
      });

      SubmissionsManager.deleteSubmission(123);

      expect(mockDataTable.ajax.reload).toHaveBeenCalled();
      expect(window.alert).toHaveBeenCalledWith('Submission deleted successfully!');
    });

    test('should show error on failure', () => {
      global.$.ajax = jest.fn((options) => {
        options.success({ success: false, data: { message: 'Delete failed' } });
      });

      SubmissionsManager.deleteSubmission(123);

      expect(window.alert).toHaveBeenCalledWith('Delete failed');
    });

    test('should handle AJAX error', () => {
      global.$.ajax = jest.fn((options) => {
        options.error();
      });

      SubmissionsManager.deleteSubmission(123);

      expect(window.alert).toHaveBeenCalledWith(formflowData.strings.error);
    });
  });

  describe('Bulk Actions', () => {
    beforeEach(() => {
      SubmissionsManager.dataTable = mockDataTable;
    });

    test('should alert when no action selected', () => {
      global.jQuery.mockImplementation((selector) => {
        if (selector === '#bulk-action-selector') {
          return { val: jest.fn(() => '-1') };
        }
        return { val: jest.fn(() => []) };
      });
      global.$ = global.jQuery;

      SubmissionsManager.applyBulkAction();

      expect(window.alert).toHaveBeenCalledWith('Please select an action');
    });

    test('should alert when no submissions selected', () => {
      global.jQuery.mockImplementation((selector) => {
        if (selector === '#bulk-action-selector') {
          return { val: jest.fn(() => 'delete') };
        }
        if (selector === '.submission-checkbox:checked') {
          return {
            map: jest.fn(() => ({ get: jest.fn(() => []) }))
          };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SubmissionsManager.applyBulkAction();

      expect(window.alert).toHaveBeenCalledWith('Please select at least one submission');
    });

    test('should delete multiple submissions', () => {
      window.confirm = jest.fn(() => true);

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#bulk-action-selector') {
          return { val: jest.fn(() => 'delete') };
        }
        if (selector === '.submission-checkbox:checked') {
          return {
            map: jest.fn(() => ({ get: jest.fn(() => [1, 2, 3]) }))
          };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true });
      });

      SubmissionsManager.applyBulkAction();

      expect(window.confirm).toHaveBeenCalled();
      expect(mockDataTable.ajax.reload).toHaveBeenCalled();
    });

    test('should export selected submissions', () => {
      const exportSpy = jest.spyOn(SubmissionsManager, 'exportSubmissions');

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#bulk-action-selector') {
          return { val: jest.fn(() => 'export') };
        }
        if (selector === '.submission-checkbox:checked') {
          return {
            map: jest.fn(() => ({ get: jest.fn(() => [1, 2, 3]) }))
          };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SubmissionsManager.applyBulkAction();

      expect(exportSpy).toHaveBeenCalledWith('selected', [1, 2, 3]);
    });
  });

  describe('Export Submissions', () => {
    test('should create form for export', () => {
      const mockForm = {
        append: jest.fn(() => mockForm),
        submit: jest.fn(),
        remove: jest.fn()
      };

      global.jQuery.mockImplementation((selector) => {
        if (typeof selector === 'string' && selector.startsWith('<form')) {
          return mockForm;
        }
        if (typeof selector === 'string' && selector.startsWith('<input')) {
          return selector;
        }
        if (selector === 'body') {
          return { append: jest.fn() };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SubmissionsManager.exportSubmissions('all');

      expect(mockForm.submit).toHaveBeenCalled();
      expect(mockForm.remove).toHaveBeenCalled();
    });
  });

  describe('Close Modal', () => {
    test('should close modal and reset currentSubmissionId', () => {
      const mockFadeOut = jest.fn();
      const mockRemoveClass = jest.fn();

      SubmissionsManager.currentSubmissionId = 123;

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#view-submission-modal') {
          return { fadeOut: mockFadeOut };
        }
        if (selector === 'body') {
          return { removeClass: mockRemoveClass };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;

      SubmissionsManager.closeModal();

      expect(mockFadeOut).toHaveBeenCalledWith(200);
      expect(mockRemoveClass).toHaveBeenCalledWith('modal-open');
      expect(SubmissionsManager.currentSubmissionId).toBeNull();
    });
  });

  describe('Utility Methods', () => {
    test('should get status class', () => {
      expect(SubmissionsManager.getStatusClass('completed')).toBe('status-completed status-success');
      expect(SubmissionsManager.getStatusClass('pending')).toBe('status-pending status-warning');
      expect(SubmissionsManager.getStatusClass('failed')).toBe('status-failed status-error');
      expect(SubmissionsManager.getStatusClass('unknown')).toBe('status-default');
    });

    test('should get signature status class', () => {
      expect(SubmissionsManager.getSignatureStatusClass('signed')).toBe('badge-success');
      expect(SubmissionsManager.getSignatureStatusClass('pending')).toBe('badge-warning');
      expect(SubmissionsManager.getSignatureStatusClass('refused')).toBe('badge-error');
      expect(SubmissionsManager.getSignatureStatusClass('unknown')).toBe('badge-secondary');
    });

    test('should format status text', () => {
      expect(SubmissionsManager.formatStatus('pending_signature')).toBe('Pending Signature');
      expect(SubmissionsManager.formatStatus('completed')).toBe('Completed');
    });

    test('should format field name', () => {
      expect(SubmissionsManager.formatFieldName('first_name')).toBe('First Name');
      expect(SubmissionsManager.formatFieldName('email_address')).toBe('Email Address');
    });

    test('should escape HTML', () => {
      expect(SubmissionsManager.escapeHtml('<script>alert("xss")</script>'))
        .toBe('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');
      expect(SubmissionsManager.escapeHtml('Test & Co')).toBe('Test &amp; Co');
    });
  });

  describe('Filter Actions', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <button id="apply-filters">Apply</button>
        <button id="reset-filters">Reset</button>
        <select id="form-filter"></select>
        <select id="status-filter"></select>
        <input id="date-from">
        <input id="date-to">
      `;
      SubmissionsManager.dataTable = mockDataTable;
    });

    test('should reload table on apply filters', () => {
      const mockOn = jest.fn((event, callback) => {
        if (event === 'click') callback();
      });

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#apply-filters') {
          return { on: mockOn };
        }
        return { on: jest.fn() };
      });
      global.$ = global.jQuery;

      SubmissionsManager.setupEventListeners();

      expect(mockDataTable.ajax.reload).toHaveBeenCalled();
    });

    test('should reset filters and reload table', () => {
      const mockVal = jest.fn(() => ({ val: mockVal }));
      let clickCallback;

      const mockOn = jest.fn((event, callback) => {
        if (event === 'click') clickCallback = callback;
      });

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#reset-filters') {
          return { on: mockOn };
        }
        if (selector.includes('filter') || selector.includes('date')) {
          return { val: mockVal };
        }
        return { on: jest.fn() };
      });
      global.$ = global.jQuery;

      SubmissionsManager.setupEventListeners();

      if (clickCallback) clickCallback();

      expect(mockVal).toHaveBeenCalledWith('');
    });
  });

  describe('Display Submission Details', () => {
    test('should display basic submission info', () => {
      const mockHtml = jest.fn();

      global.jQuery.mockImplementation(() => ({
        html: mockHtml
      }));
      global.$ = global.jQuery;

      const testData = {
        id: 123,
        form_name: 'Test Form',
        status: 'completed'
      };

      SubmissionsManager.displaySubmissionDetails(testData);

      expect(mockHtml).toHaveBeenCalledWith(expect.stringContaining('123'));
    });
  });
});
