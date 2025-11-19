/**
 * FormFlow Pro - Submissions Management Script
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Submissions Manager
     */
    const SubmissionsManager = {
        dataTable: null,
        currentSubmissionId: null,

        /**
         * Initialize
         */
        init() {
            this.initDataTable();
            this.setupEventListeners();
            this.initDatepickers();
            console.log('FormFlow Submissions Management initialized');
        },

        /**
         * Initialize DataTables
         */
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
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: (data) => {
                            return `<input type="checkbox" class="submission-checkbox" value="${data}">`;
                        }
                    },
                    {
                        data: 'id',
                        render: (data) => {
                            return `<strong>#${data}</strong>`;
                        }
                    },
                    { data: 'form_name' },
                    {
                        data: 'status',
                        render: (data) => {
                            const statusClass = this.getStatusClass(data);
                            return `<span class="status-badge ${statusClass}">${this.formatStatus(data)}</span>`;
                        }
                    },
                    {
                        data: 'signature_status',
                        render: (data) => {
                            if (!data) {
                                return '<span class="badge badge-secondary">N/A</span>';
                            }
                            const statusClass = this.getSignatureStatusClass(data);
                            return `<span class="badge ${statusClass}">${this.formatStatus(data)}</span>`;
                        }
                    },
                    { data: 'ip_address' },
                    {
                        data: 'created_at',
                        render: (data) => {
                            const date = new Date(data);
                            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                        }
                    },
                    {
                        data: 'id',
                        orderable: false,
                        searchable: false,
                        render: (data) => {
                            return `
                                <button type="button" class="button button-small view-submission" data-id="${data}">
                                    <span class="dashicons dashicons-visibility"></span>
                                    View
                                </button>
                                <button type="button" class="button button-small delete-submission" data-id="${data}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            `;
                        }
                    }
                ],
                order: [[1, 'desc']],
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                language: {
                    emptyTable: 'No submissions found',
                    info: 'Showing _START_ to _END_ of _TOTAL_ submissions',
                    infoEmpty: 'Showing 0 to 0 of 0 submissions',
                    infoFiltered: '(filtered from _MAX_ total submissions)',
                    lengthMenu: 'Show _MENU_ submissions',
                    loadingRecords: 'Loading...',
                    processing: 'Processing...',
                    search: 'Search:',
                    zeroRecords: 'No matching submissions found'
                },
                dom: '<"top"lf>rt<"bottom"ip><"clear">'
            });
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Apply filters
            $('#apply-filters').on('click', () => {
                this.dataTable.ajax.reload();
            });

            // Reset filters
            $('#reset-filters').on('click', () => {
                $('#form-filter').val('');
                $('#status-filter').val('');
                $('#date-from').val('');
                $('#date-to').val('');
                this.dataTable.ajax.reload();
            });

            // Select all checkboxes
            $('#cb-select-all-submissions').on('change', function() {
                $('.submission-checkbox').prop('checked', $(this).prop('checked'));
            });

            // View submission
            $(document).on('click', '.view-submission', (e) => {
                const submissionId = $(e.currentTarget).data('id');
                this.viewSubmission(submissionId);
            });

            // Delete submission
            $(document).on('click', '.delete-submission', (e) => {
                const submissionId = $(e.currentTarget).data('id');
                if (confirm('Are you sure you want to delete this submission?')) {
                    this.deleteSubmission(submissionId);
                }
            });

            // Bulk actions
            $('#apply-bulk-action').on('click', () => {
                this.applyBulkAction();
            });

            // Export all submissions
            $('#export-submissions').on('click', (e) => {
                e.preventDefault();
                this.exportSubmissions('all');
            });

            // Close modal
            $('.formflow-modal-close, #close-submission-modal, .formflow-modal-overlay').on('click', () => {
                this.closeModal();
            });

            // Export single submission
            $('#export-submission').on('click', () => {
                if (this.currentSubmissionId) {
                    this.exportSubmissions('single', this.currentSubmissionId);
                }
            });
        },

        /**
         * Initialize datepickers
         */
        initDatepickers() {
            $('.formflow-datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                changeMonth: true,
                changeYear: true
            });
        },

        /**
         * View submission details
         */
        viewSubmission(submissionId) {
            this.currentSubmissionId = submissionId;

            $('#view-submission-modal').fadeIn(200);
            $('body').addClass('modal-open');

            // Load submission data
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

        /**
         * Display submission details
         */
        displaySubmissionDetails(data) {
            let html = '<div class="submission-details">';

            // Basic info
            html += '<div class="detail-section">';
            html += '<h3>Basic Information</h3>';
            html += '<table class="widefat">';
            html += `<tr><th>Submission ID</th><td>#${data.id}</td></tr>`;
            html += `<tr><th>Form</th><td>${data.form_name}</td></tr>`;
            html += `<tr><th>Status</th><td><span class="status-badge ${this.getStatusClass(data.status)}">${this.formatStatus(data.status)}</span></td></tr>`;
            html += `<tr><th>IP Address</th><td>${data.ip_address}</td></tr>`;
            html += `<tr><th>User Agent</th><td>${data.user_agent || 'N/A'}</td></tr>`;
            html += `<tr><th>Created</th><td>${new Date(data.created_at).toLocaleString()}</td></tr>`;
            html += `<tr><th>Updated</th><td>${new Date(data.updated_at).toLocaleString()}</td></tr>`;
            html += '</table>';
            html += '</div>';

            // Form data
            let formData = {};
            try {
                formData = JSON.parse(data.form_data);
            } catch (e) {
                formData = { error: 'Unable to parse form data' };
            }

            html += '<div class="detail-section">';
            html += '<h3>Form Data</h3>';
            html += '<table class="widefat">';
            for (const [key, value] of Object.entries(formData)) {
                html += `<tr><th>${this.formatFieldName(key)}</th><td>${this.escapeHtml(value)}</td></tr>`;
            }
            html += '</table>';
            html += '</div>';

            // Metadata
            if (data.metadata) {
                let metadata = {};
                try {
                    metadata = JSON.parse(data.metadata);
                } catch (e) {
                    metadata = {};
                }

                if (Object.keys(metadata).length > 0) {
                    html += '<div class="detail-section">';
                    html += '<h3>Metadata</h3>';
                    html += '<table class="widefat">';
                    for (const [key, value] of Object.entries(metadata)) {
                        html += `<tr><th>${this.formatFieldName(key)}</th><td>${this.escapeHtml(JSON.stringify(value))}</td></tr>`;
                    }
                    html += '</table>';
                    html += '</div>';
                }
            }

            // Signature info
            if (data.signature_document_id) {
                html += '<div class="detail-section">';
                html += '<h3>Digital Signature</h3>';
                html += '<table class="widefat">';
                html += `<tr><th>Document ID</th><td>${data.signature_document_id}</td></tr>`;
                html += `<tr><th>Status</th><td><span class="badge ${this.getSignatureStatusClass(data.signature_status)}">${this.formatStatus(data.signature_status)}</span></td></tr>`;
                if (data.signature_completed_at) {
                    html += `<tr><th>Completed At</th><td>${new Date(data.signature_completed_at).toLocaleString()}</td></tr>`;
                }
                html += '</table>';
                html += '</div>';
            }

            html += '</div>';

            $('#submission-details-container').html(html);
        },

        /**
         * Delete submission
         */
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

        /**
         * Apply bulk action
         */
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

        /**
         * Export submissions
         */
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
            } else {
                // Add current filters for 'all' export
                exportData.form_id = $('#form-filter').val();
                exportData.status = $('#status-filter').val();
                exportData.date_from = $('#date-from').val();
                exportData.date_to = $('#date-to').val();
            }

            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: formflowData.ajax_url
            });

            for (const [key, value] of Object.entries(exportData)) {
                if (Array.isArray(value)) {
                    value.forEach((v) => {
                        form.append($('<input>', { type: 'hidden', name: key + '[]', value: v }));
                    });
                } else {
                    form.append($('<input>', { type: 'hidden', name: key, value: value }));
                }
            }

            $('body').append(form);
            form.submit();
            form.remove();
        },

        /**
         * Close modal
         */
        closeModal() {
            $('#view-submission-modal').fadeOut(200);
            $('body').removeClass('modal-open');
            this.currentSubmissionId = null;
        },

        /**
         * Get status CSS class
         */
        getStatusClass(status) {
            const statusMap = {
                'completed': 'status-completed status-success',
                'pending': 'status-pending status-warning',
                'failed': 'status-failed status-error',
                'pending_signature': 'status-pending status-info',
                'draft': 'status-draft status-default'
            };
            return statusMap[status] || 'status-default';
        },

        /**
         * Get signature status CSS class
         */
        getSignatureStatusClass(status) {
            const statusMap = {
                'signed': 'badge-success',
                'pending': 'badge-warning',
                'refused': 'badge-error',
                'canceled': 'badge-secondary'
            };
            return statusMap[status] || 'badge-secondary';
        },

        /**
         * Format status text
         */
        formatStatus(status) {
            return status.split('_').map(word =>
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        },

        /**
         * Format field name
         */
        formatFieldName(fieldName) {
            return fieldName
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());
        },

        /**
         * Escape HTML
         */
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

    // Initialize on document ready
    $(document).ready(() => {
        SubmissionsManager.init();
    });

})(jQuery);
