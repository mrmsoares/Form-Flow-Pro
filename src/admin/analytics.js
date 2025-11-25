/**
 * FormFlow Pro - Analytics Dashboard V2.2.0
 *
 * Advanced analytics with real-time stats, period comparison,
 * and export capabilities.
 *
 * @package FormFlowPro
 * @since 2.0.0
 * @updated 2.2.0 Added advanced analytics features
 */

(function ($) {
    'use strict';

    /**
     * Analytics Dashboard
     */
    const FormFlowAnalytics = {
        charts: {},
        config: {},
        realtimeInterval: null,
        REALTIME_INTERVAL_MS: 30000, // 30 seconds

        /**
         * Initialize
         */
        init() {
            this.setupEventListeners();
            console.log('FormFlow Analytics V2.2.0 initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Export dropdown
            $('#export-dropdown').on('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                $('#export-menu').toggleClass('show');
            });

            // Export actions
            $('[data-export]').on('click', (e) => {
                e.preventDefault();
                const format = $(e.currentTarget).data('export');
                this.exportReport(format);
                $('#export-menu').removeClass('show');
            });

            // Close dropdown on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.dropdown').length) {
                    $('#export-menu').removeClass('show');
                }
            });

            // Refresh real-time stats
            $('#refresh-realtime').on('click', () => {
                this.loadRealtimeStats();
            });

            // Date preset buttons
            $('.preset-btn').on('click', (e) => {
                const days = $(e.currentTarget).data('days');
                this.setDatePreset(days);
            });

            // Compare periods button
            $('#compare-periods').on('click', () => {
                this.comparePeriods();
            });
        },

        /**
         * Initialize all charts with config
         */
        initCharts(data, config = {}) {
            this.config = config;

            if (config.viewMode === 'overview' || !config.viewMode) {
                // Submissions Trend Chart (Line)
                this.createTrendChart(data.trend);

                // Status Distribution Chart (Doughnut)
                this.createStatusChart(data.status);

                // Hourly Distribution Chart (Bar)
                this.createHourlyChart(data.hourly);

                // Top Forms Chart (Horizontal Bar)
                this.createTopFormsChart(data.forms);
            } else if (config.viewMode === 'performance') {
                this.createPerformanceChart(data);
            } else if (config.viewMode === 'compare') {
                this.createComparisonChart(data);
            }
        },

        /**
         * Initialize advanced features
         */
        initAdvancedFeatures(config) {
            this.config = config;

            // Start real-time stats polling
            this.loadRealtimeStats();
            this.startRealtimePolling();

            // Load queue metrics if on performance view
            if (config.viewMode === 'performance') {
                this.loadQueueMetrics();
                this.checkSystemHealth();
            }
        },

        /**
         * Start real-time stats polling
         */
        startRealtimePolling() {
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
            }

            this.realtimeInterval = setInterval(() => {
                this.loadRealtimeStats();
            }, this.REALTIME_INTERVAL_MS);
        },

        /**
         * Load real-time statistics
         */
        loadRealtimeStats() {
            const $refreshBtn = $('#refresh-realtime');
            $refreshBtn.find('.dashicons').addClass('spin');

            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_get_realtime_stats',
                    nonce: formflowData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateRealtimeDisplay(response.data);
                    }
                },
                complete: () => {
                    $refreshBtn.find('.dashicons').removeClass('spin');
                }
            });
        },

        /**
         * Update real-time display
         */
        updateRealtimeDisplay(stats) {
            $('#rt-submissions-today').text(stats.submissions_today || 0);
            $('#rt-completed-today').text(stats.completed_today || 0);
            $('#rt-pending-signatures').text(stats.pending_signatures || 0);
            $('#rt-queue-pending').text(stats.queue_pending || 0);

            if (stats.last_submission) {
                const lastTime = this.formatRelativeTime(stats.last_submission);
                $('#rt-last-submission').text(lastTime);
            } else {
                $('#rt-last-submission').text('-');
            }

            // Update queue metrics if on performance page
            if (this.config.viewMode === 'performance') {
                $('#queue-pending-count').text(stats.queue_pending || 0);
                $('#queue-processing-count').text(stats.queue_processing || 0);
            }
        },

        /**
         * Format relative time
         */
        formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);

            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        },

        /**
         * Load queue metrics
         */
        loadQueueMetrics() {
            // Queue metrics come from real-time stats
            // Additional detailed metrics could be loaded here
        },

        /**
         * Check system health
         */
        checkSystemHealth() {
            const $healthStatus = $('#health-status');
            const $healthDot = $healthStatus.find('.health-dot');
            const $healthText = $healthStatus.find('.health-text');

            // Check basic system status
            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_get_realtime_stats',
                    nonce: formflowData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        const queueBacklog = data.queue_pending || 0;

                        if (queueBacklog > 100) {
                            $healthDot.css('background', '#dc3232');
                            $healthText.text('Warning: High queue backlog');
                        } else if (queueBacklog > 50) {
                            $healthDot.css('background', '#ffb900');
                            $healthText.text('Notice: Queue processing');
                        } else {
                            $healthDot.css('background', '#46b450');
                            $healthText.text('All systems operational');
                        }
                    }
                },
                error: () => {
                    $healthDot.css('background', '#dc3232');
                    $healthText.text('Connection error');
                }
            });
        },

        /**
         * Set date preset
         */
        setDatePreset(days) {
            const today = new Date();
            const from = new Date(today);
            from.setDate(from.getDate() - days);

            $('#date-from').val(this.formatDate(from));
            $('#date-to').val(this.formatDate(today));

            // Highlight active preset
            $('.preset-btn').removeClass('active');
            $(`.preset-btn[data-days="${days}"]`).addClass('active');
        },

        /**
         * Format date to YYYY-MM-DD
         */
        formatDate(date) {
            return date.toISOString().split('T')[0];
        },

        /**
         * Compare periods
         */
        comparePeriods() {
            const currentFrom = $('#current-from').val();
            const currentTo = $('#current-to').val();
            const previousFrom = $('#previous-from').val();
            const previousTo = $('#previous-to').val();
            const formId = this.config.formId || '';

            const $btn = $('#compare-periods');
            $btn.prop('disabled', true).text('Comparing...');

            $.ajax({
                url: formflowData.ajax_url,
                type: 'POST',
                data: {
                    action: 'formflow_compare_periods',
                    nonce: formflowData.nonce,
                    current_from: currentFrom,
                    current_to: currentTo,
                    previous_from: previousFrom,
                    previous_to: previousTo,
                    form_id: formId
                },
                success: (response) => {
                    if (response.success) {
                        this.updateComparisonDisplay(response.data);
                    }
                },
                complete: () => {
                    $btn.prop('disabled', false).text('Compare');
                }
            });
        },

        /**
         * Update comparison display
         */
        updateComparisonDisplay(data) {
            // Update values
            $('#cmp-current-total').text(data.current.total.toLocaleString());
            $('#cmp-previous-total').text(data.previous.total.toLocaleString());
            this.updateChangeValue('#cmp-change-total', data.changes.total, '%');

            $('#cmp-current-completed').text(data.current.completed.toLocaleString());
            $('#cmp-previous-completed').text(data.previous.completed.toLocaleString());
            this.updateChangeValue('#cmp-change-completed', data.changes.completed, '%');

            $('#cmp-current-rate').text(data.current.conversion_rate + '%');
            $('#cmp-previous-rate').text(data.previous.conversion_rate + '%');
            this.updateChangeValue('#cmp-change-rate', data.changes.conversion_rate, 'pp');
        },

        /**
         * Update change value with styling
         */
        updateChangeValue(selector, value, suffix) {
            const $el = $(selector);
            const $parent = $el.closest('.change-value');
            const formatted = (value >= 0 ? '+' : '') + value.toFixed(1) + suffix;

            $el.text(formatted);
            $parent.removeClass('positive negative').addClass(value >= 0 ? 'positive' : 'negative');
        },

        /**
         * Create submissions trend chart
         */
        createTrendChart(data) {
            const ctx = document.getElementById('submissions-trend-chart');
            if (!ctx) return;

            this.charts.trend = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Submissions',
                        data: data.data,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#0073aa',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    return 'Submissions: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        },

        /**
         * Create status distribution chart
         */
        createStatusChart(data) {
            const ctx = document.getElementById('status-distribution-chart');
            if (!ctx) return;

            const colors = this.getStatusColors(data.labels);

            this.charts.status = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels.map(label => this.formatStatus(label)),
                    datasets: [{
                        data: data.data,
                        backgroundColor: colors.backgrounds,
                        borderColor: colors.borders,
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        },

        /**
         * Create hourly distribution chart
         */
        createHourlyChart(data) {
            const ctx = document.getElementById('hourly-distribution-chart');
            if (!ctx) return;

            this.charts.hourly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Submissions',
                        data: data.data,
                        backgroundColor: 'rgba(0, 166, 210, 0.7)',
                        borderColor: '#00a0d2',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: 'rgba(0, 166, 210, 0.9)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                title: function(context) {
                                    return 'Hour: ' + context[0].label;
                                },
                                label: function(context) {
                                    return 'Submissions: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: 10
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create top forms chart
         */
        createTopFormsChart(data) {
            const ctx = document.getElementById('top-forms-chart');
            if (!ctx) return;

            this.charts.topForms = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Submissions',
                        data: data.data,
                        backgroundColor: 'rgba(70, 180, 80, 0.7)',
                        borderColor: '#46b450',
                        borderWidth: 1,
                        borderRadius: 4,
                        hoverBackgroundColor: 'rgba(70, 180, 80, 0.9)'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return 'Submissions: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                font: {
                                    size: 11
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create performance chart
         */
        createPerformanceChart(data) {
            const ctx = document.getElementById('performance-chart');
            if (!ctx) return;

            // Create a placeholder performance chart
            this.charts.performance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.trend?.labels || [],
                    datasets: [{
                        label: 'Avg Processing Time (ms)',
                        data: [], // Would need performance data from backend
                        borderColor: '#9b59b6',
                        backgroundColor: 'rgba(155, 89, 182, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Milliseconds'
                            }
                        }
                    }
                }
            });
        },

        /**
         * Create comparison chart
         */
        createComparisonChart(data) {
            const ctx = document.getElementById('comparison-chart');
            if (!ctx) return;

            this.charts.comparison = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Total Submissions', 'Completed', 'Pending', 'Failed'],
                    datasets: [
                        {
                            label: 'Current Period',
                            data: [0, 0, 0, 0], // Placeholder
                            backgroundColor: 'rgba(0, 115, 170, 0.7)',
                            borderColor: '#0073aa',
                            borderWidth: 1
                        },
                        {
                            label: 'Previous Period',
                            data: [0, 0, 0, 0], // Placeholder
                            backgroundColor: 'rgba(150, 150, 150, 0.7)',
                            borderColor: '#969696',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        },

        /**
         * Get colors for status chart
         */
        getStatusColors(statuses) {
            const colorMap = {
                'completed': {
                    background: 'rgba(70, 180, 80, 0.7)',
                    border: '#46b450'
                },
                'pending': {
                    background: 'rgba(255, 185, 0, 0.7)',
                    border: '#ffb900'
                },
                'pending_signature': {
                    background: 'rgba(0, 166, 210, 0.7)',
                    border: '#00a0d2'
                },
                'processing': {
                    background: 'rgba(155, 89, 182, 0.7)',
                    border: '#9b59b6'
                },
                'failed': {
                    background: 'rgba(220, 50, 50, 0.7)',
                    border: '#dc3232'
                },
                'draft': {
                    background: 'rgba(130, 130, 130, 0.7)',
                    border: '#828282'
                }
            };

            const backgrounds = [];
            const borders = [];

            statuses.forEach(status => {
                const colors = colorMap[status] || {
                    background: 'rgba(150, 150, 150, 0.7)',
                    border: '#969696'
                };
                backgrounds.push(colors.background);
                borders.push(colors.border);
            });

            return { backgrounds, borders };
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
         * Export analytics report
         */
        exportReport(format = 'csv') {
            const formId = this.config.formId || $('#form-filter').val() || '';
            const dateFrom = this.config.dateFrom || $('#date-from').val();
            const dateTo = this.config.dateTo || $('#date-to').val();

            if (format === 'csv') {
                // Use the new CSV export endpoint
                const params = new URLSearchParams({
                    action: 'formflow_export_analytics_csv',
                    nonce: formflowData.nonce,
                    date_from: dateFrom,
                    date_to: dateTo,
                    form_id: formId
                });

                window.location.href = formflowData.ajax_url + '?' + params.toString();
            } else if (format === 'pdf') {
                // PDF export would require additional backend implementation
                alert('PDF export coming soon!');
            }
        },

        /**
         * Destroy all charts
         */
        destroyCharts() {
            Object.keys(this.charts).forEach(key => {
                if (this.charts[key]) {
                    this.charts[key].destroy();
                }
            });
            this.charts = {};
        },

        /**
         * Cleanup on page unload
         */
        cleanup() {
            if (this.realtimeInterval) {
                clearInterval(this.realtimeInterval);
            }
            this.destroyCharts();
        }
    };

    // Make available globally
    window.FormFlowAnalytics = FormFlowAnalytics;

    // Initialize on document ready
    $(document).ready(() => {
        FormFlowAnalytics.init();
    });

    // Cleanup on page unload
    $(window).on('beforeunload', () => {
        FormFlowAnalytics.cleanup();
    });

})(jQuery);
