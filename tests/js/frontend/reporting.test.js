/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor, fireEvent } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock WordPress globals
global.ajaxurl = 'https://example.com/wp-admin/admin-ajax.php';
global.ffpReporting = {
    ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
    nonce: 'test-nonce-123',
    restUrl: '/wp-json/formflow/v1/',
};

describe('FFPReporting Module', () => {
    let FFPReporting;
    let mockAjax;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div class="ffp-reporting-container">
                <div class="ffp-reporting-tabs">
                    <button class="ffp-reporting-tab active" data-tab="generate">Generate</button>
                    <button class="ffp-reporting-tab" data-tab="schedules">Schedules</button>
                    <button class="ffp-reporting-tab" data-tab="history">History</button>
                </div>
                <div class="ffp-reporting-panel active" id="ffp-panel-generate">
                    <select id="ffp-report-type">
                        <option value="executive_summary">Executive Summary</option>
                        <option value="detailed_analytics">Detailed Analytics</option>
                    </select>
                    <select id="ffp-date-range">
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="custom">Custom</option>
                    </select>
                    <div class="ffp-custom-date-range" style="display:none">
                        <input type="text" id="ffp-start-date" />
                        <input type="text" id="ffp-end-date" />
                    </div>
                    <input type="checkbox" class="ffp-form-checkbox" value="1" />
                    <input type="checkbox" class="ffp-form-checkbox" value="2" />
                    <input type="checkbox" id="ffp-select-all-forms" />
                    <input type="radio" name="export_format" value="pdf" checked />
                    <input type="radio" name="export_format" value="excel" />
                    <button id="ffp-generate-report">Generate Report</button>
                    <button id="ffp-preview-report">Preview</button>
                </div>
                <div class="ffp-reporting-panel" id="ffp-panel-schedules">
                    <button id="ffp-add-schedule">Add Schedule</button>
                    <div id="ffp-schedules-list"></div>
                </div>
                <div class="ffp-reporting-panel" id="ffp-panel-history">
                    <div id="ffp-history-list"></div>
                </div>
            </div>
        `;

        // Mock jQuery AJAX
        mockAjax = jest.spyOn($, 'ajax');

        // Load the reporting module
        require('../../../src/js/reporting.js');
        FFPReporting = window.FFPReporting;

        // Mock datepicker
        $.fn.datepicker = jest.fn(function() { return this; });
    });

    afterEach(() => {
        mockAjax.mockRestore();
        jest.clearAllMocks();
    });

    describe('Initialization', () => {
        test('should initialize with default state', () => {
            expect(FFPReporting.state.currentTab).toBe('generate');
            expect(FFPReporting.state.reportType).toBe('executive_summary');
            expect(FFPReporting.state.dateRange).toBe('last_30_days');
            expect(FFPReporting.state.selectedForms).toEqual([]);
            expect(FFPReporting.state.exportFormat).toBe('pdf');
        });

        test('should bind all event handlers', () => {
            FFPReporting.init();
            expect($.fn.datepicker).toHaveBeenCalled();
        });
    });

    describe('Tab Navigation', () => {
        test('should switch tabs correctly', () => {
            FFPReporting.init();

            const schedulesTab = $('.ffp-reporting-tab[data-tab="schedules"]');
            schedulesTab.trigger('click');

            expect(FFPReporting.state.currentTab).toBe('schedules');
            expect($('#ffp-panel-schedules').hasClass('active')).toBe(true);
            expect($('#ffp-panel-generate').hasClass('active')).toBe(false);
        });

        test('should load data when switching to schedules tab', () => {
            mockAjax.mockImplementation(() => Promise.resolve({
                success: true,
                data: { schedules: [] }
            }));

            FFPReporting.init();
            FFPReporting.switchTab('schedules');

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_get_report_schedules'
                    })
                })
            );
        });
    });

    describe('Report Generation', () => {
        test('should validate required fields before generating', () => {
            FFPReporting.init();
            FFPReporting.state.dateRange = 'custom';
            FFPReporting.state.customStartDate = null;
            FFPReporting.state.customEndDate = null;

            const result = FFPReporting.validateReportForm();
            expect(result).toBe(false);
        });

        test('should generate report with valid data', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        download_url: 'https://example.com/report.pdf'
                    }
                });
                return Promise.resolve();
            });

            FFPReporting.init();
            FFPReporting.state.reportType = 'executive_summary';
            FFPReporting.state.dateRange = 'last_30_days';
            FFPReporting.state.selectedForms = ['1', '2'];
            FFPReporting.state.exportFormat = 'pdf';

            $('#ffp-generate-report').trigger('click');

            await waitFor(() => {
                expect(mockAjax).toHaveBeenCalledWith(
                    expect.objectContaining({
                        data: expect.objectContaining({
                            action: 'ffp_generate_report',
                            report_type: 'executive_summary',
                            date_range: 'last_30_days',
                            export_format: 'pdf'
                        })
                    })
                );
            });
        });

        test('should handle generation errors', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: false,
                    data: { message: 'Failed to generate report' }
                });
                return Promise.resolve();
            });

            FFPReporting.init();
            $('#ffp-generate-report').trigger('click');

            await waitFor(() => {
                expect($('.ffp-notice-error').length).toBeGreaterThan(0);
            });
        });

        test('should disable button during generation', () => {
            mockAjax.mockImplementation(() => new Promise(() => {})); // Never resolves

            FFPReporting.init();
            $('#ffp-generate-report').trigger('click');

            const $btn = $('#ffp-generate-report');
            expect($btn.prop('disabled')).toBe(true);
        });
    });

    describe('Date Range Selection', () => {
        test('should toggle custom date range fields', () => {
            FFPReporting.init();

            $('#ffp-date-range').val('custom').trigger('change');

            expect($('.ffp-custom-date-range').is(':visible')).toBe(true);
        });

        test('should validate date range', () => {
            FFPReporting.init();
            FFPReporting.state.dateRange = 'custom';
            FFPReporting.state.customStartDate = '2024-01-15';
            FFPReporting.state.customEndDate = '2024-01-10';

            const result = FFPReporting.validateReportForm();
            expect(result).toBe(false);
        });
    });

    describe('Form Selection', () => {
        test('should update selected forms on checkbox change', () => {
            FFPReporting.init();

            $('.ffp-form-checkbox').first().prop('checked', true).trigger('change');

            expect(FFPReporting.state.selectedForms).toContain('1');
        });

        test('should select all forms', () => {
            FFPReporting.init();

            $('#ffp-select-all-forms').prop('checked', true).trigger('click');

            expect($('.ffp-form-checkbox:checked').length).toBe(2);
        });
    });

    describe('Export Format', () => {
        test('should get available formats for report type', () => {
            const formats = FFPReporting.getAvailableFormats('executive_summary');
            expect(formats).toContain('pdf');
            expect(formats).toContain('html');
        });

        test('should update format availability based on report type', () => {
            FFPReporting.init();
            FFPReporting.state.reportType = 'signature_status';

            FFPReporting.updateFormatAvailability();

            const csvDisabled = $('input[name="export_format"][value="csv"]').prop('disabled');
            expect(csvDisabled).toBe(true);
        });
    });

    describe('Report Preview', () => {
        test('should open preview modal', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { html: '<div>Preview content</div>' }
                });
                return Promise.resolve();
            });

            FFPReporting.init();
            $('#ffp-preview-report').trigger('click');

            await waitFor(() => {
                expect($('.ffp-preview-modal').length).toBeGreaterThan(0);
            });
        });
    });

    describe('Schedule Management', () => {
        test('should load schedules', async () => {
            const mockSchedules = [
                {
                    id: 1,
                    name: 'Weekly Report',
                    frequency: 'weekly',
                    enabled: true,
                    recipients: ['admin@example.com'],
                    report_type: 'executive_summary'
                }
            ];

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: { schedules: mockSchedules }
                });
                return Promise.resolve();
            });

            FFPReporting.init();
            await FFPReporting.loadSchedules();

            expect(FFPReporting.state.schedules).toEqual(mockSchedules);
        });

        test('should render schedule item', () => {
            const schedule = {
                id: 1,
                name: 'Test Schedule',
                frequency: 'daily',
                enabled: true,
                recipients: ['test@example.com'],
                report_type: 'executive_summary',
                next_run: '2024-01-20 10:00:00'
            };

            const html = FFPReporting.renderScheduleItem(schedule);
            expect(html).toContain('Test Schedule');
            expect(html).toContain('Daily');
        });

        test('should delete schedule', async () => {
            global.confirm = jest.fn(() => true);
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true });
                return Promise.resolve();
            });

            FFPReporting.init();
            await FFPReporting.deleteSchedule(1);

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_delete_report_schedule',
                        schedule_id: 1
                    })
                })
            );
        });

        test('should toggle schedule enabled state', async () => {
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true });
                return Promise.resolve();
            });

            FFPReporting.init();
            await FFPReporting.toggleSchedule(1, false);

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_toggle_report_schedule',
                        enabled: false
                    })
                })
            );
        });
    });

    describe('Report History', () => {
        test('should load report history', async () => {
            const mockHistory = [
                {
                    id: 1,
                    name: 'Executive Summary',
                    report_type: 'executive_summary',
                    format: 'pdf',
                    file_size: 1024000,
                    created_at: '2024-01-20 10:00:00'
                }
            ];

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        reports: mockHistory,
                        total_pages: 1
                    }
                });
                return Promise.resolve();
            });

            FFPReporting.init();
            await FFPReporting.loadHistory(1);

            expect(FFPReporting.state.history).toEqual(mockHistory);
        });

        test('should download report', () => {
            global.window.location.href = '';
            FFPReporting.downloadReport(123);

            expect(window.location.href).toContain('action=ffp_download_report');
            expect(window.location.href).toContain('report_id=123');
        });

        test('should delete report from history', async () => {
            global.confirm = jest.fn(() => true);
            mockAjax.mockImplementation(({ success }) => {
                success({ success: true });
                return Promise.resolve();
            });

            FFPReporting.init();
            await FFPReporting.deleteReport(1);

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_delete_report'
                    })
                })
            );
        });
    });

    describe('Utility Functions', () => {
        test('should format file size correctly', () => {
            expect(FFPReporting.formatFileSize(1024)).toBe('1.0 KB');
            expect(FFPReporting.formatFileSize(1048576)).toBe('1.0 MB');
            expect(FFPReporting.formatFileSize(0)).toBe('0 B');
        });

        test('should format dates correctly', () => {
            const formatted = FFPReporting.formatDate('2024-01-20 15:30:00');
            expect(formatted).toContain('2024');
        });

        test('should escape HTML', () => {
            const escaped = FFPReporting.escapeHtml('<script>alert("xss")</script>');
            expect(escaped).not.toContain('<script>');
        });

        test('should get report type label', () => {
            expect(FFPReporting.getReportTypeLabel('executive_summary')).toBe('Executive Summary');
            expect(FFPReporting.getReportTypeLabel('form_performance')).toBe('Form Performance');
        });
    });

    describe('Notifications', () => {
        test('should show success notice', () => {
            FFPReporting.showNotice('success', 'Operation successful!');

            expect($('.ffp-notice-success').length).toBeGreaterThan(0);
            expect($('.ffp-notice-success').text()).toContain('Operation successful!');
        });

        test('should show error notice', () => {
            FFPReporting.showNotice('error', 'Operation failed!');

            expect($('.ffp-notice-error').length).toBeGreaterThan(0);
        });

        test('should auto-dismiss notices', (done) => {
            jest.useFakeTimers();
            FFPReporting.showNotice('info', 'Test message');

            jest.advanceTimersByTime(5000);

            setTimeout(() => {
                expect($('.ffp-notice').is(':visible')).toBe(false);
                jest.useRealTimers();
                done();
            }, 100);
        });
    });

    describe('Modal Management', () => {
        test('should open schedule modal', () => {
            FFPReporting.openScheduleModal();
            expect($('.ffp-schedule-modal').length).toBeGreaterThan(0);
        });

        test('should close modal', () => {
            FFPReporting.openScheduleModal();
            FFPReporting.closeModal();
            expect($('.ffp-modal').length).toBe(0);
        });
    });

    describe('Dashboard Stats', () => {
        test('should load dashboard stats', async () => {
            const FFPDashboardStats = window.FFPDashboardStats;

            document.body.innerHTML += '<div class="ffp-dashboard-stats"></div>';

            mockAjax.mockImplementation(({ success }) => {
                success({
                    success: true,
                    data: {
                        total_submissions: 150,
                        conversion_rate: 45,
                        signatures_completed: 80,
                        total_revenue: 5000
                    }
                });
                return Promise.resolve();
            });

            await FFPDashboardStats.loadStats();

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_get_dashboard_stats'
                    })
                })
            );
        });
    });
});
