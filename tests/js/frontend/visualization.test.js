/**
 * @jest-environment jsdom
 */

import '@testing-library/jest-dom';
import { screen, waitFor } from '@testing-library/dom';

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Mock D3.js
const mockD3 = {
    select: jest.fn().mockReturnThis(),
    selectAll: jest.fn().mockReturnThis(),
    append: jest.fn().mockReturnThis(),
    attr: jest.fn().mockReturnThis(),
    style: jest.fn().mockReturnThis(),
    text: jest.fn().mockReturnThis(),
    html: jest.fn().mockReturnThis(),
    data: jest.fn().mockReturnThis(),
    enter: jest.fn().mockReturnThis(),
    transition: jest.fn().mockReturnThis(),
    duration: jest.fn().mockReturnThis(),
    ease: jest.fn().mockReturnThis(),
    delay: jest.fn().mockReturnThis(),
    on: jest.fn().mockReturnThis(),
    call: jest.fn().mockReturnThis(),
    remove: jest.fn().mockReturnThis(),
    empty: jest.fn().mockReturnValue(false),
    node: jest.fn().mockReturnValue({
        getTotalLength: () => 100,
        getBoundingClientRect: () => ({ width: 800, height: 600 })
    }),
    scaleLinear: jest.fn(() => ({
        domain: jest.fn().mockReturnThis(),
        range: jest.fn().mockReturnThis()
    })),
    scaleTime: jest.fn(() => ({
        domain: jest.fn().mockReturnThis(),
        range: jest.fn().mockReturnThis()
    })),
    scaleBand: jest.fn(() => ({
        domain: jest.fn().mockReturnThis(),
        range: jest.fn().mockReturnThis(),
        padding: jest.fn().mockReturnThis(),
        bandwidth: jest.fn().mockReturnValue(50)
    })),
    scaleSequential: jest.fn(() => ({
        domain: jest.fn().mockReturnThis(),
        interpolator: jest.fn().mockReturnThis()
    })),
    axisBottom: jest.fn(() => ({
        ticks: jest.fn().mockReturnThis(),
        tickFormat: jest.fn().mockReturnThis(),
        tickSize: jest.fn().mockReturnThis()
    })),
    axisLeft: jest.fn(() => ({
        ticks: jest.fn().mockReturnThis(),
        tickFormat: jest.fn().mockReturnThis(),
        tickSize: jest.fn().mockReturnThis()
    })),
    line: jest.fn(() => ({
        x: jest.fn().mockReturnThis(),
        y: jest.fn().mockReturnThis(),
        curve: jest.fn().mockReturnThis()
    })),
    area: jest.fn(() => ({
        x: jest.fn().mockReturnThis(),
        y0: jest.fn().mockReturnThis(),
        y1: jest.fn().mockReturnThis(),
        curve: jest.fn().mockReturnThis()
    })),
    arc: jest.fn(() => ({
        innerRadius: jest.fn().mockReturnThis(),
        outerRadius: jest.fn().mockReturnThis(),
        startAngle: jest.fn().mockReturnThis(),
        endAngle: jest.fn().mockReturnThis(),
        cornerRadius: jest.fn().mockReturnThis(),
        centroid: jest.fn().mockReturnValue([100, 100])
    })),
    pie: jest.fn(() => ({
        value: jest.fn().mockReturnThis(),
        sort: jest.fn().mockReturnThis(),
        padAngle: jest.fn().mockReturnThis()
    })),
    extent: jest.fn(() => [0, 100]),
    max: jest.fn(() => 100),
    min: jest.fn(() => 0),
    sum: jest.fn(() => 500),
    format: jest.fn(() => (value) => value.toString()),
    timeParse: jest.fn(() => (str) => new Date(str)),
    timeFormat: jest.fn(() => (date) => date.toISOString()),
    easeCubicOut: 'easeCubicOut',
    curveMonotoneX: 'curveMonotoneX',
    interpolate: jest.fn(() => (t) => t),
    interpolateRgbBasis: jest.fn(() => (t) => '#000000')
};

global.d3 = mockD3;

describe('FFPVisualization Module', () => {
    let FFPVisualization;

    beforeEach(() => {
        // Setup DOM
        document.body.innerHTML = `
            <div id="chart-container"></div>
            <div class="ffp-chart-wrapper" data-chart-id="test-chart">
                <div id="ffp-chart-test-chart"></div>
            </div>
        `;

        // Mock window.FFPCharts
        global.window.FFPCharts = {
            'test-chart': {
                container: 'ffp-chart-test-chart',
                rendered: false,
                config: {
                    type: 'line',
                    data: [
                        { date: '2024-01-01', value: 10 },
                        { date: '2024-01-02', value: 20 },
                        { date: '2024-01-03', value: 15 }
                    ],
                    dimensions: { height: 400 },
                    colors: ['#2271b1'],
                    options: { margin: { top: 20, right: 30, bottom: 40, left: 50 } },
                    responsive: true
                }
            }
        };

        // Load the visualization module
        require('../../../src/js/visualization.js');
        FFPVisualization = window.FFPVisualization;
    });

    afterEach(() => {
        jest.clearAllMocks();
        delete global.window.FFPCharts;
    });

    describe('Initialization', () => {
        test('should initialize visualization system', () => {
            expect(FFPVisualization).toBeDefined();
            expect(FFPVisualization.defaults).toBeDefined();
        });

        test('should have default configuration', () => {
            expect(FFPVisualization.defaults.margin).toEqual({
                top: 20,
                right: 30,
                bottom: 40,
                left: 50
            });
            expect(FFPVisualization.defaults.animation.duration).toBe(750);
            expect(FFPVisualization.defaults.responsive).toBe(true);
        });

        test('should initialize chart instances storage', () => {
            expect(FFPVisualization.instances).toBeDefined();
            expect(FFPVisualization.charts).toBeDefined();
        });
    });

    describe('Chart Rendering', () => {
        test('should render chart by ID', () => {
            FFPVisualization.render('test-chart');

            expect(mockD3.select).toHaveBeenCalled();
            expect(window.FFPCharts['test-chart'].rendered).toBe(true);
        });

        test('should not render already rendered chart', () => {
            window.FFPCharts['test-chart'].rendered = true;
            const selectSpy = jest.spyOn(mockD3, 'select');

            FFPVisualization.render('test-chart');

            expect(selectSpy).not.toHaveBeenCalled();
        });

        test('should warn if container not found', () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
            window.FFPCharts['missing-chart'] = {
                container: 'non-existent',
                rendered: false,
                config: { type: 'line' }
            };

            FFPVisualization.render('missing-chart');

            expect(consoleSpy).toHaveBeenCalledWith(
                'FFPVisualization: Container not found for chart',
                'missing-chart'
            );

            consoleSpy.mockRestore();
        });

        test('should warn for unknown chart type', () => {
            const consoleSpy = jest.spyOn(console, 'warn').mockImplementation();
            window.FFPCharts['test-chart'].config.type = 'unknown-type';

            FFPVisualization.render('test-chart');

            expect(consoleSpy).toHaveBeenCalledWith(
                'FFPVisualization: Unknown chart type',
                'unknown-type'
            );

            consoleSpy.mockRestore();
        });
    });

    describe('Line Chart', () => {
        test('should render line chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = window.FFPCharts['test-chart'].config;

            const result = FFPVisualization.renderLine(container, config);

            expect(mockD3.select).toHaveBeenCalledWith(container);
            expect(result).toBeDefined();
        });

        test('should parse dates correctly', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = window.FFPCharts['test-chart'].config;

            FFPVisualization.renderLine(container, config);

            expect(mockD3.timeParse).toHaveBeenCalledWith('%Y-%m-%d');
        });

        test('should create scales for line chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = window.FFPCharts['test-chart'].config;

            FFPVisualization.renderLine(container, config);

            expect(mockD3.scaleTime).toHaveBeenCalled();
            expect(mockD3.scaleLinear).toHaveBeenCalled();
        });

        test('should add area fill if areaOpacity > 0', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                ...window.FFPCharts['test-chart'].config,
                options: { ...window.FFPCharts['test-chart'].config.options, areaOpacity: 0.3 }
            };

            FFPVisualization.renderLine(container, config);

            expect(mockD3.area).toHaveBeenCalled();
        });
    });

    describe('Bar Chart', () => {
        test('should render vertical bar chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'bar',
                data: [
                    { label: 'A', value: 10 },
                    { label: 'B', value: 20 }
                ],
                dimensions: { height: 400 },
                options: { margin: { top: 20, right: 30, bottom: 40, left: 50 } }
            };

            FFPVisualization.renderBar(container, config);

            expect(mockD3.scaleBand).toHaveBeenCalled();
            expect(mockD3.scaleLinear).toHaveBeenCalled();
        });

        test('should render horizontal bar chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'bar',
                data: [{ label: 'A', value: 10 }],
                dimensions: { height: 400 },
                options: {
                    margin: { top: 20, right: 30, bottom: 40, left: 50 },
                    orientation: 'horizontal'
                }
            };

            FFPVisualization.renderBar(container, config);

            expect(mockD3.scaleBand).toHaveBeenCalled();
        });
    });

    describe('Pie/Donut Chart', () => {
        test('should render pie chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'pie',
                data: [
                    { label: 'A', value: 30 },
                    { label: 'B', value: 70 }
                ],
                dimensions: { height: 400 }
            };

            FFPVisualization.renderPie(container, config);

            expect(mockD3.pie).toHaveBeenCalled();
            expect(mockD3.arc).toHaveBeenCalled();
        });

        test('should render donut chart with inner radius', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'donut',
                data: [{ label: 'A', value: 100 }],
                dimensions: { height: 400 },
                options: { innerRadius: 60 }
            };

            FFPVisualization.renderDonut(container, config);

            expect(mockD3.pie).toHaveBeenCalled();
        });
    });

    describe('Heatmap', () => {
        test('should render heatmap', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'heatmap',
                data: [
                    { x: 'Mon', y: '9am', value: 10 },
                    { x: 'Tue', y: '10am', value: 20 }
                ],
                dimensions: { height: 400 },
                options: { margin: { top: 20, right: 30, bottom: 60, left: 100 } }
            };

            FFPVisualization.renderHeatmap(container, config);

            expect(mockD3.scaleBand).toHaveBeenCalled();
            expect(mockD3.scaleSequential).toHaveBeenCalled();
        });
    });

    describe('Funnel Chart', () => {
        test('should render funnel chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'funnel',
                data: [
                    { label: 'Stage 1', value: 100 },
                    { label: 'Stage 2', value: 75 },
                    { label: 'Stage 3', value: 50 }
                ],
                dimensions: { height: 400 },
                options: { margin: { top: 20, right: 120, bottom: 40, left: 120 } }
            };

            FFPVisualization.renderFunnel(container, config);

            expect(mockD3.select).toHaveBeenCalled();
        });
    });

    describe('Gauge Chart', () => {
        test('should render gauge chart', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'gauge',
                data: [{ value: 75, label: 'Completion' }],
                dimensions: { height: 300 },
                options: { min: 0, max: 100 }
            };

            FFPVisualization.renderGauge(container, config);

            expect(mockD3.arc).toHaveBeenCalled();
            expect(mockD3.scaleLinear).toHaveBeenCalled();
        });

        test('should apply color based on thresholds', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'gauge',
                data: [{ value: 25, label: 'Low' }],
                dimensions: { height: 300 },
                options: {
                    min: 0,
                    max: 100,
                    thresholds: [
                        { value: 30, color: '#dc3545' },
                        { value: 70, color: '#ffc107' },
                        { value: 100, color: '#28a745' }
                    ]
                }
            };

            FFPVisualization.renderGauge(container, config);

            expect(mockD3.arc).toHaveBeenCalled();
        });
    });

    describe('Scatter Plot', () => {
        test('should render scatter plot', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = {
                type: 'scatter',
                data: [
                    { x: 10, y: 20, label: 'Point 1' },
                    { x: 30, y: 40, label: 'Point 2' }
                ],
                dimensions: { height: 400 },
                options: { margin: { top: 20, right: 30, bottom: 40, left: 50 } }
            };

            FFPVisualization.renderScatter(container, config);

            expect(mockD3.scaleLinear).toHaveBeenCalled();
        });
    });

    describe('Tooltip Management', () => {
        test('should create tooltip', () => {
            const tooltip = FFPVisualization.createTooltip();

            expect(mockD3.select).toHaveBeenCalledWith('body');
            expect(tooltip).toBeDefined();
        });

        test('should show tooltip', () => {
            const tooltip = mockD3.select('body').append('div');
            const event = { pageX: 100, pageY: 200 };

            FFPVisualization.showTooltip(tooltip, 'Test content', event);

            expect(tooltip.transition).toHaveBeenCalled();
        });

        test('should hide tooltip', () => {
            const tooltip = mockD3.select('body').append('div');

            FFPVisualization.hideTooltip(tooltip);

            expect(tooltip.transition).toHaveBeenCalled();
        });
    });

    describe('Utility Functions', () => {
        test('should get dimensions accounting for margins', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            container.style.width = '800px';
            const config = {
                dimensions: { height: 400 },
                options: { margin: { top: 20, right: 30, bottom: 40, left: 50 } }
            };

            const dims = FFPVisualization.getDimensions(container, config);

            expect(dims.margin).toEqual({ top: 20, right: 30, bottom: 40, left: 50 });
            expect(dims.height).toBe(360); // 400 - 20 - 40
        });

        test('should format numbers correctly', () => {
            expect(FFPVisualization.formatNumber(1234.56, 'currency')).toContain('$');
            expect(FFPVisualization.formatNumber(75, 'percent')).toContain('%');
            expect(FFPVisualization.formatNumber(1000000, 'compact')).toBeDefined();
        });
    });

    describe('Toolbar Actions', () => {
        test('should zoom in', () => {
            const chartId = 'test-chart';
            FFPVisualization.instances[chartId] = mockD3.select('svg');

            FFPVisualization.zoom(chartId, 1.2);

            expect(mockD3.transition).toHaveBeenCalled();
        });

        test('should reset chart', () => {
            window.FFPCharts['test-chart'].rendered = true;

            FFPVisualization.reset('test-chart');

            expect(window.FFPCharts['test-chart'].rendered).toBe(false);
        });

        test('should download chart as SVG', () => {
            document.getElementById('ffp-chart-test-chart').innerHTML = '<svg></svg>';
            const createElementSpy = jest.spyOn(document, 'createElement');

            FFPVisualization.download('test-chart');

            expect(createElementSpy).toHaveBeenCalledWith('a');
        });
    });

    describe('Data Loading', () => {
        test('should load chart data via AJAX', async () => {
            const mockAjax = jest.spyOn($, 'ajax').mockResolvedValue({
                success: true,
                data: { data: [{ value: 10 }] }
            });

            global.ffpVisualization = {
                ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
                nonce: 'test-nonce'
            };

            const data = await FFPVisualization.loadData('test-chart', {
                period: 'last_7_days',
                form_id: 1
            });

            expect(mockAjax).toHaveBeenCalledWith(
                expect.objectContaining({
                    data: expect.objectContaining({
                        action: 'ffp_get_chart_data',
                        chart_id: 'test-chart',
                        period: 'last_7_days',
                        form_id: 1
                    })
                })
            );

            mockAjax.mockRestore();
        });

        test('should refresh chart with new data', async () => {
            const mockAjax = jest.spyOn($, 'ajax').mockResolvedValue({
                success: true,
                data: { data: [{ value: 20 }] }
            });

            global.ffpVisualization = {
                ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
                nonce: 'test-nonce'
            };

            await FFPVisualization.refresh('test-chart', { period: 'today' });

            await waitFor(() => {
                expect(window.FFPCharts['test-chart'].rendered).toBe(false);
            });

            mockAjax.mockRestore();
        });
    });

    describe('Responsive Behavior', () => {
        test('should setup resize handler', () => {
            const addEventListenerSpy = jest.spyOn(window, 'addEventListener');

            FFPVisualization.setupResizeHandler();

            expect(addEventListenerSpy).toHaveBeenCalledWith('resize', expect.any(Function));

            addEventListenerSpy.mockRestore();
        });

        test('should re-render responsive charts on resize', (done) => {
            jest.useFakeTimers();

            FFPVisualization.setupResizeHandler();

            window.dispatchEvent(new Event('resize'));

            jest.advanceTimersByTime(250);

            setTimeout(() => {
                expect(window.FFPCharts['test-chart'].rendered).toBe(false);
                jest.useRealTimers();
                done();
            }, 100);
        });
    });

    describe('Legend Rendering', () => {
        test('should render legend if enabled', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            container.innerHTML = '<div class="ffp-chart-legend"></div>';
            const data = [
                { label: 'Item 1', value: 10 },
                { label: 'Item 2', value: 20 }
            ];
            const colors = ['#2271b1', '#72aee6'];
            const config = { legend: { show: true } };

            FFPVisualization.renderLegend(container, data, colors, config);

            expect(mockD3.select).toHaveBeenCalled();
        });

        test('should not render legend if disabled', () => {
            const container = document.getElementById('ffp-chart-test-chart');
            const config = { legend: { show: false } };

            FFPVisualization.renderLegend(container, [], [], config);

            expect(mockD3.select).toHaveBeenCalled();
        });
    });
});
