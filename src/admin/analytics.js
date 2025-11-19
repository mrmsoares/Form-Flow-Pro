/**
 * FormFlow Pro - Analytics Dashboard
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

(function ($) {
    'use strict';

    /**
     * Analytics Dashboard
     */
    const FormFlowAnalytics = {
        charts: {},

        /**
         * Initialize
         */
        init() {
            this.setupEventListeners();
            console.log('FormFlow Analytics initialized');
        },

        /**
         * Setup event listeners
         */
        setupEventListeners() {
            // Export report
            $('#export-analytics').on('click', (e) => {
                e.preventDefault();
                this.exportReport();
            });
        },

        /**
         * Initialize all charts
         */
        initCharts(data) {
            // Submissions Trend Chart (Line)
            this.createTrendChart(data.trend);

            // Status Distribution Chart (Doughnut)
            this.createStatusChart(data.status);

            // Hourly Distribution Chart (Bar)
            this.createHourlyChart(data.hourly);

            // Top Forms Chart (Horizontal Bar)
            this.createTopFormsChart(data.forms);
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
        exportReport() {
            // Get current filter values
            const formId = $('#form-filter').val();
            const dateFrom = $('#date-from').val();
            const dateTo = $('#date-to').val();

            // Create export form
            const form = $('<form>', {
                method: 'POST',
                action: formflowData.ajax_url
            });

            // Add fields
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'formflow_export_analytics'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: formflowData.nonce
            }));

            if (formId) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'form_id',
                    value: formId
                }));
            }

            if (dateFrom) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'date_from',
                    value: dateFrom
                }));
            }

            if (dateTo) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: 'date_to',
                    value: dateTo
                }));
            }

            // Submit form
            $('body').append(form);
            form.submit();
            form.remove();
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
        }
    };

    // Make available globally
    window.FormFlowAnalytics = FormFlowAnalytics;

    // Initialize on document ready
    $(document).ready(() => {
        FormFlowAnalytics.init();
    });

})(jQuery);
