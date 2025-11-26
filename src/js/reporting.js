/**
 * FormFlow Pro - Reporting Module
 *
 * Admin interface for report generation, scheduling, and management
 *
 * @package FormFlowPro
 * @since 2.3.0
 */

(function($) {
    'use strict';

    /**
     * Reporting Manager
     */
    const FFPReporting = {
        /**
         * Configuration
         */
        config: {
            ajaxUrl: typeof ffpReporting !== 'undefined' ? ffpReporting.ajaxUrl : ajaxurl,
            nonce: typeof ffpReporting !== 'undefined' ? ffpReporting.nonce : '',
            restUrl: typeof ffpReporting !== 'undefined' ? ffpReporting.restUrl : '/wp-json/formflow/v1/',
            dateFormat: 'YYYY-MM-DD',
            maxFileSize: 10 * 1024 * 1024 // 10MB
        },

        /**
         * State
         */
        state: {
            currentTab: 'generate',
            reportType: 'executive_summary',
            dateRange: 'last_30_days',
            customStartDate: null,
            customEndDate: null,
            selectedForms: [],
            exportFormat: 'pdf',
            isGenerating: false,
            schedules: [],
            history: [],
            currentPage: 1,
            totalPages: 1
        },

        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initDatePickers();
            this.loadSchedules();
            this.loadHistory();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            const self = this;

            // Tab navigation
            $(document).on('click', '.ffp-reporting-tab', function(e) {
                e.preventDefault();
                self.switchTab($(this).data('tab'));
            });

            // Report type selection
            $(document).on('change', '#ffp-report-type', function() {
                self.state.reportType = $(this).val();
                self.updateReportOptions();
            });

            // Date range selection
            $(document).on('change', '#ffp-date-range', function() {
                self.state.dateRange = $(this).val();
                self.toggleCustomDateRange();
            });

            // Form selection
            $(document).on('change', '.ffp-form-checkbox', function() {
                self.updateSelectedForms();
            });

            // Select all forms
            $(document).on('click', '#ffp-select-all-forms', function() {
                const isChecked = $(this).prop('checked');
                $('.ffp-form-checkbox').prop('checked', isChecked);
                self.updateSelectedForms();
            });

            // Export format selection
            $(document).on('change', 'input[name="export_format"]', function() {
                self.state.exportFormat = $(this).val();
            });

            // Generate report
            $(document).on('click', '#ffp-generate-report', function(e) {
                e.preventDefault();
                self.generateReport();
            });

            // Preview report
            $(document).on('click', '#ffp-preview-report', function(e) {
                e.preventDefault();
                self.previewReport();
            });

            // Schedule modal
            $(document).on('click', '#ffp-add-schedule', function(e) {
                e.preventDefault();
                self.openScheduleModal();
            });

            // Save schedule
            $(document).on('click', '#ffp-save-schedule', function(e) {
                e.preventDefault();
                self.saveSchedule();
            });

            // Delete schedule
            $(document).on('click', '.ffp-delete-schedule', function(e) {
                e.preventDefault();
                const scheduleId = $(this).closest('.ffp-schedule-item').data('id');
                self.deleteSchedule(scheduleId);
            });

            // Toggle schedule
            $(document).on('change', '.ffp-schedule-toggle', function() {
                const scheduleId = $(this).closest('.ffp-schedule-item').data('id');
                const enabled = $(this).prop('checked');
                self.toggleSchedule(scheduleId, enabled);
            });

            // Run schedule now
            $(document).on('click', '.ffp-run-schedule', function(e) {
                e.preventDefault();
                const scheduleId = $(this).closest('.ffp-schedule-item').data('id');
                self.runScheduleNow(scheduleId);
            });

            // Download report
            $(document).on('click', '.ffp-download-report', function(e) {
                e.preventDefault();
                const reportId = $(this).closest('.ffp-history-item').data('id');
                self.downloadReport(reportId);
            });

            // Delete report
            $(document).on('click', '.ffp-delete-report', function(e) {
                e.preventDefault();
                const reportId = $(this).closest('.ffp-history-item').data('id');
                self.deleteReport(reportId);
            });

            // Pagination
            $(document).on('click', '.ffp-history-pagination a', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                self.loadHistory(page);
            });

            // Modal close
            $(document).on('click', '.ffp-modal-close, .ffp-modal-overlay', function() {
                self.closeModal();
            });

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });
        },

        /**
         * Initialize date pickers
         */
        initDatePickers: function() {
            if ($.fn.datepicker) {
                $('#ffp-start-date, #ffp-end-date').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: new Date(),
                    onSelect: function(dateText, inst) {
                        const field = $(this).attr('id');
                        if (field === 'ffp-start-date') {
                            FFPReporting.state.customStartDate = dateText;
                        } else {
                            FFPReporting.state.customEndDate = dateText;
                        }
                    }
                });
            }
        },

        /**
         * Switch tab
         */
        switchTab: function(tab) {
            this.state.currentTab = tab;

            // Update tab buttons
            $('.ffp-reporting-tab').removeClass('active');
            $(`.ffp-reporting-tab[data-tab="${tab}"]`).addClass('active');

            // Update tab content
            $('.ffp-reporting-panel').removeClass('active');
            $(`#ffp-panel-${tab}`).addClass('active');

            // Load data if needed
            if (tab === 'schedules') {
                this.loadSchedules();
            } else if (tab === 'history') {
                this.loadHistory();
            }
        },

        /**
         * Toggle custom date range fields
         */
        toggleCustomDateRange: function() {
            const isCustom = this.state.dateRange === 'custom';
            $('.ffp-custom-date-range').toggle(isCustom);
        },

        /**
         * Update selected forms
         */
        updateSelectedForms: function() {
            this.state.selectedForms = [];
            $('.ffp-form-checkbox:checked').each(function() {
                FFPReporting.state.selectedForms.push($(this).val());
            });

            // Update select all checkbox
            const allChecked = $('.ffp-form-checkbox').length === $('.ffp-form-checkbox:checked').length;
            $('#ffp-select-all-forms').prop('checked', allChecked);
        },

        /**
         * Update report options based on type
         */
        updateReportOptions: function() {
            const type = this.state.reportType;
            const $options = $('.ffp-report-type-options');

            // Show/hide options based on report type
            $options.find('.ffp-option-group').hide();
            $options.find(`.ffp-option-group[data-types*="${type}"]`).show();

            // Update format availability
            this.updateFormatAvailability();
        },

        /**
         * Update export format availability
         */
        updateFormatAvailability: function() {
            const type = this.state.reportType;
            const formats = this.getAvailableFormats(type);

            $('input[name="export_format"]').each(function() {
                const format = $(this).val();
                const isAvailable = formats.includes(format);
                $(this).prop('disabled', !isAvailable);
                $(this).closest('.ffp-format-option').toggleClass('disabled', !isAvailable);
            });

            // Select first available if current is disabled
            if (!formats.includes(this.state.exportFormat)) {
                this.state.exportFormat = formats[0];
                $(`input[name="export_format"][value="${formats[0]}"]`).prop('checked', true);
            }
        },

        /**
         * Get available formats for report type
         */
        getAvailableFormats: function(type) {
            const formatMap = {
                'executive_summary': ['pdf', 'html'],
                'detailed_analytics': ['pdf', 'excel', 'html'],
                'form_performance': ['pdf', 'excel', 'csv'],
                'submission_export': ['excel', 'csv', 'json'],
                'signature_status': ['pdf', 'excel']
            };
            return formatMap[type] || ['pdf'];
        },

        /**
         * Generate report
         */
        generateReport: function() {
            if (this.state.isGenerating) {
                return;
            }

            // Validate
            if (!this.validateReportForm()) {
                return;
            }

            this.state.isGenerating = true;
            const $btn = $('#ffp-generate-report');
            const originalText = $btn.text();
            $btn.prop('disabled', true).html('<span class="ffp-spinner"></span> Generating...');

            const data = this.getReportData();

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_generate_report',
                    nonce: this.config.nonce,
                    ...data
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', 'Report generated successfully!');

                        // Download the file
                        if (response.data.download_url) {
                            window.location.href = response.data.download_url;
                        }

                        // Refresh history
                        FFPReporting.loadHistory();
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to generate report.');
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    FFPReporting.state.isGenerating = false;
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Preview report
         */
        previewReport: function() {
            if (!this.validateReportForm()) {
                return;
            }

            const data = this.getReportData();
            data.preview = true;

            // Open preview in modal
            this.openPreviewModal();
            $('#ffp-preview-content').html('<div class="ffp-loading"><span class="ffp-spinner"></span> Loading preview...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_preview_report',
                    nonce: this.config.nonce,
                    ...data
                },
                success: function(response) {
                    if (response.success) {
                        $('#ffp-preview-content').html(response.data.html);
                    } else {
                        $('#ffp-preview-content').html('<p class="ffp-error">' + (response.data.message || 'Failed to load preview.') + '</p>');
                    }
                },
                error: function() {
                    $('#ffp-preview-content').html('<p class="ffp-error">An error occurred. Please try again.</p>');
                }
            });
        },

        /**
         * Get report form data
         */
        getReportData: function() {
            const data = {
                report_type: this.state.reportType,
                date_range: this.state.dateRange,
                export_format: this.state.exportFormat,
                forms: this.state.selectedForms
            };

            if (this.state.dateRange === 'custom') {
                data.start_date = this.state.customStartDate;
                data.end_date = this.state.customEndDate;
            }

            // Add type-specific options
            $('.ffp-report-type-options .ffp-option-field:visible').each(function() {
                const name = $(this).attr('name');
                const value = $(this).is(':checkbox') ? $(this).prop('checked') : $(this).val();
                data[name] = value;
            });

            return data;
        },

        /**
         * Validate report form
         */
        validateReportForm: function() {
            let isValid = true;
            const errors = [];

            // Check date range
            if (this.state.dateRange === 'custom') {
                if (!this.state.customStartDate) {
                    errors.push('Please select a start date.');
                    isValid = false;
                }
                if (!this.state.customEndDate) {
                    errors.push('Please select an end date.');
                    isValid = false;
                }
                if (this.state.customStartDate && this.state.customEndDate) {
                    if (new Date(this.state.customStartDate) > new Date(this.state.customEndDate)) {
                        errors.push('Start date must be before end date.');
                        isValid = false;
                    }
                }
            }

            // Check form selection for certain report types
            const requiresForms = ['form_performance', 'submission_export'];
            if (requiresForms.includes(this.state.reportType) && this.state.selectedForms.length === 0) {
                errors.push('Please select at least one form.');
                isValid = false;
            }

            if (!isValid) {
                this.showNotice('error', errors.join('<br>'));
            }

            return isValid;
        },

        /**
         * Load schedules
         */
        loadSchedules: function() {
            const $container = $('#ffp-schedules-list');
            $container.html('<div class="ffp-loading"><span class="ffp-spinner"></span> Loading schedules...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_get_report_schedules',
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.state.schedules = response.data.schedules;
                        FFPReporting.renderSchedules();
                    } else {
                        $container.html('<p class="ffp-error">Failed to load schedules.</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="ffp-error">An error occurred. Please try again.</p>');
                }
            });
        },

        /**
         * Render schedules
         */
        renderSchedules: function() {
            const $container = $('#ffp-schedules-list');

            if (this.state.schedules.length === 0) {
                $container.html(`
                    <div class="ffp-empty-state">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <h3>No Scheduled Reports</h3>
                        <p>Create a schedule to automatically generate and send reports.</p>
                    </div>
                `);
                return;
            }

            let html = '';
            this.state.schedules.forEach(function(schedule) {
                html += FFPReporting.renderScheduleItem(schedule);
            });

            $container.html(html);
        },

        /**
         * Render single schedule item
         */
        renderScheduleItem: function(schedule) {
            const frequencyLabels = {
                'daily': 'Daily',
                'weekly': 'Weekly',
                'monthly': 'Monthly',
                'quarterly': 'Quarterly'
            };

            const statusClass = schedule.enabled ? 'active' : 'inactive';
            const nextRun = schedule.next_run ? this.formatDate(schedule.next_run) : 'Not scheduled';

            return `
                <div class="ffp-schedule-item" data-id="${schedule.id}">
                    <div class="ffp-schedule-header">
                        <div class="ffp-schedule-info">
                            <h4 class="ffp-schedule-name">${this.escapeHtml(schedule.name)}</h4>
                            <span class="ffp-schedule-frequency">${frequencyLabels[schedule.frequency] || schedule.frequency}</span>
                        </div>
                        <div class="ffp-schedule-toggle-wrapper">
                            <label class="ffp-toggle">
                                <input type="checkbox" class="ffp-schedule-toggle" ${schedule.enabled ? 'checked' : ''}>
                                <span class="ffp-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="ffp-schedule-details">
                        <div class="ffp-schedule-detail">
                            <span class="dashicons dashicons-media-document"></span>
                            <span>${this.getReportTypeLabel(schedule.report_type)}</span>
                        </div>
                        <div class="ffp-schedule-detail">
                            <span class="dashicons dashicons-clock"></span>
                            <span>Next run: ${nextRun}</span>
                        </div>
                        <div class="ffp-schedule-detail">
                            <span class="dashicons dashicons-email"></span>
                            <span>${this.escapeHtml(schedule.recipients.join(', '))}</span>
                        </div>
                    </div>
                    <div class="ffp-schedule-actions">
                        <button class="ffp-btn ffp-btn-small ffp-run-schedule" title="Run Now">
                            <span class="dashicons dashicons-controls-play"></span>
                        </button>
                        <button class="ffp-btn ffp-btn-small ffp-edit-schedule" title="Edit">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="ffp-btn ffp-btn-small ffp-btn-danger ffp-delete-schedule" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Open schedule modal
         */
        openScheduleModal: function(scheduleId) {
            const isEdit = !!scheduleId;
            const title = isEdit ? 'Edit Schedule' : 'Create Schedule';

            let schedule = {
                name: '',
                report_type: 'executive_summary',
                frequency: 'weekly',
                recipients: '',
                enabled: true
            };

            if (isEdit) {
                schedule = this.state.schedules.find(s => s.id === scheduleId) || schedule;
                schedule.recipients = schedule.recipients.join(', ');
            }

            const html = `
                <div class="ffp-modal-overlay"></div>
                <div class="ffp-modal ffp-schedule-modal">
                    <div class="ffp-modal-header">
                        <h3>${title}</h3>
                        <button class="ffp-modal-close">&times;</button>
                    </div>
                    <div class="ffp-modal-body">
                        <input type="hidden" id="ffp-schedule-id" value="${scheduleId || ''}">

                        <div class="ffp-form-group">
                            <label for="ffp-schedule-name">Schedule Name <span class="required">*</span></label>
                            <input type="text" id="ffp-schedule-name" value="${this.escapeHtml(schedule.name)}" placeholder="e.g., Weekly Executive Report">
                        </div>

                        <div class="ffp-form-group">
                            <label for="ffp-schedule-type">Report Type</label>
                            <select id="ffp-schedule-type">
                                <option value="executive_summary" ${schedule.report_type === 'executive_summary' ? 'selected' : ''}>Executive Summary</option>
                                <option value="detailed_analytics" ${schedule.report_type === 'detailed_analytics' ? 'selected' : ''}>Detailed Analytics</option>
                                <option value="form_performance" ${schedule.report_type === 'form_performance' ? 'selected' : ''}>Form Performance</option>
                                <option value="submission_export" ${schedule.report_type === 'submission_export' ? 'selected' : ''}>Submission Export</option>
                                <option value="signature_status" ${schedule.report_type === 'signature_status' ? 'selected' : ''}>Signature Status</option>
                            </select>
                        </div>

                        <div class="ffp-form-group">
                            <label for="ffp-schedule-frequency">Frequency</label>
                            <select id="ffp-schedule-frequency">
                                <option value="daily" ${schedule.frequency === 'daily' ? 'selected' : ''}>Daily</option>
                                <option value="weekly" ${schedule.frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
                                <option value="monthly" ${schedule.frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
                                <option value="quarterly" ${schedule.frequency === 'quarterly' ? 'selected' : ''}>Quarterly</option>
                            </select>
                        </div>

                        <div class="ffp-form-group">
                            <label for="ffp-schedule-recipients">Recipients <span class="required">*</span></label>
                            <input type="text" id="ffp-schedule-recipients" value="${this.escapeHtml(schedule.recipients)}" placeholder="email@example.com, another@example.com">
                            <span class="ffp-field-hint">Separate multiple emails with commas</span>
                        </div>

                        <div class="ffp-form-group">
                            <label class="ffp-checkbox-label">
                                <input type="checkbox" id="ffp-schedule-enabled" ${schedule.enabled ? 'checked' : ''}>
                                Enable this schedule
                            </label>
                        </div>
                    </div>
                    <div class="ffp-modal-footer">
                        <button class="ffp-btn ffp-btn-secondary ffp-modal-close">Cancel</button>
                        <button class="ffp-btn ffp-btn-primary" id="ffp-save-schedule">${isEdit ? 'Update' : 'Create'} Schedule</button>
                    </div>
                </div>
            `;

            $('body').append(html);
        },

        /**
         * Save schedule
         */
        saveSchedule: function() {
            const scheduleId = $('#ffp-schedule-id').val();
            const isEdit = !!scheduleId;

            const data = {
                id: scheduleId,
                name: $('#ffp-schedule-name').val().trim(),
                report_type: $('#ffp-schedule-type').val(),
                frequency: $('#ffp-schedule-frequency').val(),
                recipients: $('#ffp-schedule-recipients').val().split(',').map(e => e.trim()).filter(e => e),
                enabled: $('#ffp-schedule-enabled').prop('checked')
            };

            // Validate
            if (!data.name) {
                this.showNotice('error', 'Please enter a schedule name.');
                return;
            }

            if (data.recipients.length === 0) {
                this.showNotice('error', 'Please enter at least one recipient email.');
                return;
            }

            const $btn = $('#ffp-save-schedule');
            const originalText = $btn.text();
            $btn.prop('disabled', true).html('<span class="ffp-spinner"></span> Saving...');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: isEdit ? 'ffp_update_report_schedule' : 'ffp_create_report_schedule',
                    nonce: this.config.nonce,
                    schedule: data
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', isEdit ? 'Schedule updated!' : 'Schedule created!');
                        FFPReporting.closeModal();
                        FFPReporting.loadSchedules();
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to save schedule.');
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Delete schedule
         */
        deleteSchedule: function(scheduleId) {
            if (!confirm('Are you sure you want to delete this schedule?')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_delete_report_schedule',
                    nonce: this.config.nonce,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', 'Schedule deleted!');
                        FFPReporting.loadSchedules();
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to delete schedule.');
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                }
            });
        },

        /**
         * Toggle schedule enabled/disabled
         */
        toggleSchedule: function(scheduleId, enabled) {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_toggle_report_schedule',
                    nonce: this.config.nonce,
                    schedule_id: scheduleId,
                    enabled: enabled
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', enabled ? 'Schedule enabled!' : 'Schedule disabled!');
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to update schedule.');
                        // Revert toggle
                        $(`.ffp-schedule-item[data-id="${scheduleId}"] .ffp-schedule-toggle`).prop('checked', !enabled);
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                    $(`.ffp-schedule-item[data-id="${scheduleId}"] .ffp-schedule-toggle`).prop('checked', !enabled);
                }
            });
        },

        /**
         * Run schedule now
         */
        runScheduleNow: function(scheduleId) {
            const $btn = $(`.ffp-schedule-item[data-id="${scheduleId}"] .ffp-run-schedule`);
            $btn.prop('disabled', true).html('<span class="ffp-spinner-small"></span>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_run_report_schedule',
                    nonce: this.config.nonce,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', 'Report generated and sent!');
                        FFPReporting.loadHistory();
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to run schedule.');
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span>');
                }
            });
        },

        /**
         * Load history
         */
        loadHistory: function(page) {
            page = page || 1;
            this.state.currentPage = page;

            const $container = $('#ffp-history-list');
            $container.html('<div class="ffp-loading"><span class="ffp-spinner"></span> Loading history...</div>');

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_get_report_history',
                    nonce: this.config.nonce,
                    page: page,
                    per_page: 20
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.state.history = response.data.reports;
                        FFPReporting.state.totalPages = response.data.total_pages;
                        FFPReporting.renderHistory();
                    } else {
                        $container.html('<p class="ffp-error">Failed to load history.</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="ffp-error">An error occurred. Please try again.</p>');
                }
            });
        },

        /**
         * Render history
         */
        renderHistory: function() {
            const $container = $('#ffp-history-list');

            if (this.state.history.length === 0) {
                $container.html(`
                    <div class="ffp-empty-state">
                        <span class="dashicons dashicons-media-document"></span>
                        <h3>No Reports Yet</h3>
                        <p>Generate your first report to see it here.</p>
                    </div>
                `);
                return;
            }

            let html = '<div class="ffp-history-table">';
            html += `
                <div class="ffp-history-header">
                    <div class="ffp-history-col ffp-col-name">Report</div>
                    <div class="ffp-history-col ffp-col-type">Type</div>
                    <div class="ffp-history-col ffp-col-format">Format</div>
                    <div class="ffp-history-col ffp-col-size">Size</div>
                    <div class="ffp-history-col ffp-col-date">Generated</div>
                    <div class="ffp-history-col ffp-col-actions">Actions</div>
                </div>
            `;

            this.state.history.forEach(function(report) {
                html += FFPReporting.renderHistoryItem(report);
            });

            html += '</div>';

            // Pagination
            if (this.state.totalPages > 1) {
                html += this.renderPagination();
            }

            $container.html(html);
        },

        /**
         * Render single history item
         */
        renderHistoryItem: function(report) {
            const formatIcons = {
                'pdf': 'media-document',
                'excel': 'media-spreadsheet',
                'csv': 'media-text',
                'json': 'editor-code',
                'html': 'admin-site'
            };

            const icon = formatIcons[report.format] || 'media-document';
            const size = this.formatFileSize(report.file_size);

            return `
                <div class="ffp-history-item" data-id="${report.id}">
                    <div class="ffp-history-col ffp-col-name">
                        <span class="dashicons dashicons-${icon}"></span>
                        <span class="ffp-report-name">${this.escapeHtml(report.name)}</span>
                    </div>
                    <div class="ffp-history-col ffp-col-type">${this.getReportTypeLabel(report.report_type)}</div>
                    <div class="ffp-history-col ffp-col-format">${report.format.toUpperCase()}</div>
                    <div class="ffp-history-col ffp-col-size">${size}</div>
                    <div class="ffp-history-col ffp-col-date">${this.formatDate(report.created_at)}</div>
                    <div class="ffp-history-col ffp-col-actions">
                        <button class="ffp-btn ffp-btn-small ffp-download-report" title="Download">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        <button class="ffp-btn ffp-btn-small ffp-btn-danger ffp-delete-report" title="Delete">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Render pagination
         */
        renderPagination: function() {
            let html = '<div class="ffp-history-pagination">';

            if (this.state.currentPage > 1) {
                html += `<a href="#" data-page="${this.state.currentPage - 1}">&laquo; Previous</a>`;
            }

            for (let i = 1; i <= this.state.totalPages; i++) {
                if (i === this.state.currentPage) {
                    html += `<span class="current">${i}</span>`;
                } else {
                    html += `<a href="#" data-page="${i}">${i}</a>`;
                }
            }

            if (this.state.currentPage < this.state.totalPages) {
                html += `<a href="#" data-page="${this.state.currentPage + 1}">Next &raquo;</a>`;
            }

            html += '</div>';
            return html;
        },

        /**
         * Download report
         */
        downloadReport: function(reportId) {
            window.location.href = this.config.ajaxUrl +
                '?action=ffp_download_report&nonce=' + this.config.nonce +
                '&report_id=' + reportId;
        },

        /**
         * Delete report
         */
        deleteReport: function(reportId) {
            if (!confirm('Are you sure you want to delete this report?')) {
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_delete_report',
                    nonce: this.config.nonce,
                    report_id: reportId
                },
                success: function(response) {
                    if (response.success) {
                        FFPReporting.showNotice('success', 'Report deleted!');
                        FFPReporting.loadHistory(FFPReporting.state.currentPage);
                    } else {
                        FFPReporting.showNotice('error', response.data.message || 'Failed to delete report.');
                    }
                },
                error: function() {
                    FFPReporting.showNotice('error', 'An error occurred. Please try again.');
                }
            });
        },

        /**
         * Open preview modal
         */
        openPreviewModal: function() {
            const html = `
                <div class="ffp-modal-overlay"></div>
                <div class="ffp-modal ffp-preview-modal">
                    <div class="ffp-modal-header">
                        <h3>Report Preview</h3>
                        <button class="ffp-modal-close">&times;</button>
                    </div>
                    <div class="ffp-modal-body">
                        <div id="ffp-preview-content"></div>
                    </div>
                    <div class="ffp-modal-footer">
                        <button class="ffp-btn ffp-btn-secondary ffp-modal-close">Close</button>
                        <button class="ffp-btn ffp-btn-primary" id="ffp-generate-from-preview">Generate Report</button>
                    </div>
                </div>
            `;

            $('body').append(html);

            $('#ffp-generate-from-preview').on('click', function() {
                FFPReporting.closeModal();
                FFPReporting.generateReport();
            });
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('.ffp-modal, .ffp-modal-overlay').remove();
        },

        /**
         * Show notice
         */
        showNotice: function(type, message) {
            const $notice = $(`
                <div class="ffp-notice ffp-notice-${type}">
                    <p>${message}</p>
                    <button class="ffp-notice-close">&times;</button>
                </div>
            `);

            $('.ffp-reporting-container').prepend($notice);

            // Auto dismiss
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual close
            $notice.find('.ffp-notice-close').on('click', function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Get report type label
         */
        getReportTypeLabel: function(type) {
            const labels = {
                'executive_summary': 'Executive Summary',
                'detailed_analytics': 'Detailed Analytics',
                'form_performance': 'Form Performance',
                'submission_export': 'Submission Export',
                'signature_status': 'Signature Status'
            };
            return labels[type] || type;
        },

        /**
         * Format date
         */
        formatDate: function(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let unitIndex = 0;
            let size = bytes;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }

            return size.toFixed(unitIndex > 0 ? 1 : 0) + ' ' + units[unitIndex];
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    /**
     * Dashboard Widget - Quick Stats
     */
    const FFPDashboardStats = {
        /**
         * Initialize
         */
        init: function() {
            this.loadStats();
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            $(document).on('click', '.ffp-refresh-stats', function(e) {
                e.preventDefault();
                FFPDashboardStats.loadStats(true);
            });

            $(document).on('change', '#ffp-stats-period', function() {
                FFPDashboardStats.loadStats();
            });
        },

        /**
         * Load stats
         */
        loadStats: function(forceRefresh) {
            const $container = $('.ffp-dashboard-stats');
            if (!$container.length) return;

            const period = $('#ffp-stats-period').val() || 'last_30_days';

            $container.addClass('loading');

            $.ajax({
                url: FFPReporting.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ffp_get_dashboard_stats',
                    nonce: FFPReporting.config.nonce,
                    period: period,
                    force_refresh: forceRefresh ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        FFPDashboardStats.renderStats(response.data);
                    }
                },
                complete: function() {
                    $container.removeClass('loading');
                }
            });
        },

        /**
         * Render stats
         */
        renderStats: function(data) {
            // Update KPI values
            $('.ffp-kpi-submissions .ffp-kpi-value').text(data.total_submissions || 0);
            $('.ffp-kpi-conversion .ffp-kpi-value').text((data.conversion_rate || 0) + '%');
            $('.ffp-kpi-signatures .ffp-kpi-value').text(data.signatures_completed || 0);
            $('.ffp-kpi-revenue .ffp-kpi-value').text(this.formatCurrency(data.total_revenue || 0));

            // Update trends
            this.updateTrend('.ffp-kpi-submissions', data.submissions_trend);
            this.updateTrend('.ffp-kpi-conversion', data.conversion_trend);
            this.updateTrend('.ffp-kpi-signatures', data.signatures_trend);
            this.updateTrend('.ffp-kpi-revenue', data.revenue_trend);

            // Update charts if D3 is available
            if (typeof FFPVisualization !== 'undefined' && data.charts) {
                if (data.charts.submissions_over_time) {
                    FFPVisualization.renderChart('submissions-chart', 'line', data.charts.submissions_over_time);
                }
                if (data.charts.form_distribution) {
                    FFPVisualization.renderChart('forms-chart', 'donut', data.charts.form_distribution);
                }
            }
        },

        /**
         * Update trend indicator
         */
        updateTrend: function(selector, trend) {
            const $trend = $(selector + ' .ffp-kpi-trend');
            if (!$trend.length || trend === undefined) return;

            const isPositive = trend >= 0;
            const icon = isPositive ? 'arrow-up-alt' : 'arrow-down-alt';
            const trendClass = isPositive ? 'ffp-trend-up' : 'ffp-trend-down';

            $trend.removeClass('ffp-trend-up ffp-trend-down ffp-trend-neutral')
                .addClass(trendClass)
                .html(`<span class="dashicons dashicons-${icon}"></span> ${Math.abs(trend)}%`);
        },

        /**
         * Format currency
         */
        formatCurrency: function(amount) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(amount);
        }
    };

    /**
     * Export functionality
     */
    const FFPExport = {
        /**
         * Export data
         */
        export: function(type, format, options) {
            const data = {
                action: 'ffp_export_data',
                nonce: FFPReporting.config.nonce,
                type: type,
                format: format,
                ...options
            };

            // Create form and submit
            const $form = $('<form>', {
                method: 'POST',
                action: FFPReporting.config.ajaxUrl
            });

            Object.keys(data).forEach(function(key) {
                $form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: typeof data[key] === 'object' ? JSON.stringify(data[key]) : data[key]
                }));
            });

            $form.appendTo('body').submit().remove();
        },

        /**
         * Export submissions
         */
        exportSubmissions: function(formId, format) {
            this.export('submissions', format, { form_id: formId });
        },

        /**
         * Export analytics
         */
        exportAnalytics: function(dateRange, format) {
            this.export('analytics', format, { date_range: dateRange });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.ffp-reporting-container').length) {
            FFPReporting.init();
        }

        if ($('.ffp-dashboard-stats').length) {
            FFPDashboardStats.init();
        }
    });

    // Expose to global scope
    window.FFPReporting = FFPReporting;
    window.FFPDashboardStats = FFPDashboardStats;
    window.FFPExport = FFPExport;

})(jQuery);
