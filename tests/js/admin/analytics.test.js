/**
 * FormFlow Pro - Analytics Dashboard Tests
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

import { screen, fireEvent, waitFor } from '@testing-library/dom';
import '@testing-library/jest-dom';

// Mock Chart.js
global.Chart = jest.fn().mockImplementation((ctx, config) => {
  return {
    destroy: jest.fn(),
    update: jest.fn(),
    data: config.data,
    options: config.options,
    type: config.type
  };
});

describe('FormFlowAnalytics', () => {
  let FormFlowAnalytics;
  let consoleLogSpy;
  let mockAjax;

  beforeEach(() => {
    // Clear the document body
    document.body.innerHTML = '';

    // Reset all mocks
    jest.clearAllMocks();
    jest.useFakeTimers();

    // Spy on console.log
    consoleLogSpy = jest.spyOn(console, 'log').mockImplementation();

    // Mock jQuery AJAX
    mockAjax = jest.fn();
    global.jQuery.ajax = mockAjax;
    global.$.ajax = mockAjax;

    // Mock formflowData
    global.formflowData = {
      ajax_url: '/wp-admin/admin-ajax.php',
      nonce: 'test-nonce'
    };

    // Mock window.location
    delete window.location;
    window.location = { href: '' };

    // Define FormFlowAnalytics object
    FormFlowAnalytics = {
      charts: {},
      config: {},
      realtimeInterval: null,
      REALTIME_INTERVAL_MS: 30000,

      init() {
        this.setupEventListeners();
        console.log('FormFlow Analytics V2.2.0 initialized');
      },

      setupEventListeners() {
        $('#export-dropdown').on('click', (e) => {
          e.preventDefault();
          e.stopPropagation();
          $('#export-menu').toggleClass('show');
        });

        $('[data-export]').on('click', (e) => {
          e.preventDefault();
          const format = $(e.currentTarget).data('export');
          this.exportReport(format);
          $('#export-menu').removeClass('show');
        });

        $(document).on('click', (e) => {
          if (!$(e.target).closest('.dropdown').length) {
            $('#export-menu').removeClass('show');
          }
        });

        $('#refresh-realtime').on('click', () => {
          this.loadRealtimeStats();
        });

        $('.preset-btn').on('click', (e) => {
          const days = $(e.currentTarget).data('days');
          this.setDatePreset(days);
        });

        $('#compare-periods').on('click', () => {
          this.comparePeriods();
        });
      },

      initCharts(data, config = {}) {
        this.config = config;

        if (config.viewMode === 'overview' || !config.viewMode) {
          this.createTrendChart(data.trend);
          this.createStatusChart(data.status);
          this.createHourlyChart(data.hourly);
          this.createTopFormsChart(data.forms);
        } else if (config.viewMode === 'performance') {
          this.createPerformanceChart(data);
        } else if (config.viewMode === 'compare') {
          this.createComparisonChart(data);
        }
      },

      initAdvancedFeatures(config) {
        this.config = config;
        this.loadRealtimeStats();
        this.startRealtimePolling();

        if (config.viewMode === 'performance') {
          this.loadQueueMetrics();
          this.checkSystemHealth();
        }
      },

      startRealtimePolling() {
        if (this.realtimeInterval) {
          clearInterval(this.realtimeInterval);
        }

        this.realtimeInterval = setInterval(() => {
          this.loadRealtimeStats();
        }, this.REALTIME_INTERVAL_MS);
      },

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

        if (this.config.viewMode === 'performance') {
          $('#queue-pending-count').text(stats.queue_pending || 0);
          $('#queue-processing-count').text(stats.queue_processing || 0);
        }
      },

      formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
      },

      loadQueueMetrics() {
        // Queue metrics loaded via real-time stats
      },

      checkSystemHealth() {
        const $healthStatus = $('#health-status');
        const $healthDot = $healthStatus.find('.health-dot');
        const $healthText = $healthStatus.find('.health-text');

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

      setDatePreset(days) {
        const today = new Date();
        const from = new Date(today);
        from.setDate(from.getDate() - days);

        $('#date-from').val(this.formatDate(from));
        $('#date-to').val(this.formatDate(today));

        $('.preset-btn').removeClass('active');
        $(`.preset-btn[data-days="${days}"]`).addClass('active');
      },

      formatDate(date) {
        return date.toISOString().split('T')[0];
      },

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

      updateComparisonDisplay(data) {
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

      updateChangeValue(selector, value, suffix) {
        const $el = $(selector);
        const $parent = $el.closest('.change-value');
        const formatted = (value >= 0 ? '+' : '') + value.toFixed(1) + suffix;

        $el.text(formatted);
        $parent.removeClass('positive negative').addClass(value >= 0 ? 'positive' : 'negative');
      },

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
              backgroundColor: 'rgba(0, 115, 170, 0.1)'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

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
              borderColor: colors.borders
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

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
              backgroundColor: 'rgba(0, 166, 210, 0.7)'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

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
              backgroundColor: 'rgba(70, 180, 80, 0.7)'
            }]
          },
          options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

      createPerformanceChart(data) {
        const ctx = document.getElementById('performance-chart');
        if (!ctx) return;

        this.charts.performance = new Chart(ctx, {
          type: 'line',
          data: {
            labels: data.trend?.labels || [],
            datasets: [{
              label: 'Avg Processing Time (ms)',
              data: [],
              borderColor: '#9b59b6'
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

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
                data: [0, 0, 0, 0],
                backgroundColor: 'rgba(0, 115, 170, 0.7)'
              },
              {
                label: 'Previous Period',
                data: [0, 0, 0, 0],
                backgroundColor: 'rgba(150, 150, 150, 0.7)'
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false
          }
        });
      },

      getStatusColors(statuses) {
        const colorMap = {
          'completed': { background: 'rgba(70, 180, 80, 0.7)', border: '#46b450' },
          'pending': { background: 'rgba(255, 185, 0, 0.7)', border: '#ffb900' },
          'pending_signature': { background: 'rgba(0, 166, 210, 0.7)', border: '#00a0d2' },
          'processing': { background: 'rgba(155, 89, 182, 0.7)', border: '#9b59b6' },
          'failed': { background: 'rgba(220, 50, 50, 0.7)', border: '#dc3232' },
          'draft': { background: 'rgba(130, 130, 130, 0.7)', border: '#828282' }
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

      formatStatus(status) {
        return status.split('_').map(word =>
          word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
      },

      exportReport(format = 'csv') {
        const formId = this.config.formId || $('#form-filter').val() || '';
        const dateFrom = this.config.dateFrom || $('#date-from').val();
        const dateTo = this.config.dateTo || $('#date-to').val();

        if (format === 'csv') {
          const params = new URLSearchParams({
            action: 'formflow_export_analytics_csv',
            nonce: formflowData.nonce,
            date_from: dateFrom,
            date_to: dateTo,
            form_id: formId
          });

          window.location.href = formflowData.ajax_url + '?' + params.toString();
        } else if (format === 'pdf') {
          alert('PDF export coming soon!');
        }
      },

      destroyCharts() {
        Object.keys(this.charts).forEach(key => {
          if (this.charts[key]) {
            this.charts[key].destroy();
          }
        });
        this.charts = {};
      },

      cleanup() {
        if (this.realtimeInterval) {
          clearInterval(this.realtimeInterval);
        }
        this.destroyCharts();
      }
    };

    // Make available globally
    window.FormFlowAnalytics = FormFlowAnalytics;
  });

  afterEach(() => {
    consoleLogSpy.mockRestore();
    jest.clearAllTimers();
    jest.useRealTimers();
  });

  describe('Initialization', () => {
    test('should initialize FormFlowAnalytics object', () => {
      expect(FormFlowAnalytics).toBeDefined();
      expect(typeof FormFlowAnalytics.init).toBe('function');
    });

    test('should log initialization message', () => {
      FormFlowAnalytics.init();
      expect(consoleLogSpy).toHaveBeenCalledWith('FormFlow Analytics V2.2.0 initialized');
    });

    test('should call setupEventListeners on init', () => {
      const setupSpy = jest.spyOn(FormFlowAnalytics, 'setupEventListeners');
      FormFlowAnalytics.init();
      expect(setupSpy).toHaveBeenCalled();
    });

    test('should initialize with empty charts object', () => {
      expect(FormFlowAnalytics.charts).toEqual({});
    });

    test('should initialize with null realtimeInterval', () => {
      expect(FormFlowAnalytics.realtimeInterval).toBeNull();
    });

    test('should be available globally', () => {
      expect(window.FormFlowAnalytics).toBe(FormFlowAnalytics);
    });
  });

  describe('Chart Creation', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <canvas id="submissions-trend-chart"></canvas>
        <canvas id="status-distribution-chart"></canvas>
        <canvas id="hourly-distribution-chart"></canvas>
        <canvas id="top-forms-chart"></canvas>
        <canvas id="performance-chart"></canvas>
        <canvas id="comparison-chart"></canvas>
      `;
    });

    test('should create trend chart', () => {
      const data = { labels: ['Mon', 'Tue', 'Wed'], data: [10, 20, 15] };
      FormFlowAnalytics.createTrendChart(data);

      expect(Chart).toHaveBeenCalledWith(
        expect.any(HTMLCanvasElement),
        expect.objectContaining({ type: 'line' })
      );
      expect(FormFlowAnalytics.charts.trend).toBeDefined();
    });

    test('should create status chart', () => {
      const data = { labels: ['completed', 'pending'], data: [50, 30] };
      FormFlowAnalytics.createStatusChart(data);

      expect(Chart).toHaveBeenCalledWith(
        expect.any(HTMLCanvasElement),
        expect.objectContaining({ type: 'doughnut' })
      );
      expect(FormFlowAnalytics.charts.status).toBeDefined();
    });

    test('should create hourly chart', () => {
      const data = { labels: ['00:00', '01:00'], data: [5, 8] };
      FormFlowAnalytics.createHourlyChart(data);

      expect(Chart).toHaveBeenCalledWith(
        expect.any(HTMLCanvasElement),
        expect.objectContaining({ type: 'bar' })
      );
      expect(FormFlowAnalytics.charts.hourly).toBeDefined();
    });

    test('should create top forms chart', () => {
      const data = { labels: ['Contact Form', 'Signup Form'], data: [100, 75] };
      FormFlowAnalytics.createTopFormsChart(data);

      expect(Chart).toHaveBeenCalledWith(
        expect.any(HTMLCanvasElement),
        expect.objectContaining({ type: 'bar' })
      );
      expect(FormFlowAnalytics.charts.topForms).toBeDefined();
    });

    test('should not create chart if canvas element missing', () => {
      document.body.innerHTML = '';
      FormFlowAnalytics.createTrendChart({ labels: [], data: [] });

      expect(FormFlowAnalytics.charts.trend).toBeUndefined();
    });

    test('should initialize all charts with overview mode', () => {
      const mockData = {
        trend: { labels: ['Mon'], data: [10] },
        status: { labels: ['completed'], data: [50] },
        hourly: { labels: ['00:00'], data: [5] },
        forms: { labels: ['Form 1'], data: [100] }
      };

      const createTrendSpy = jest.spyOn(FormFlowAnalytics, 'createTrendChart');
      const createStatusSpy = jest.spyOn(FormFlowAnalytics, 'createStatusChart');
      const createHourlySpy = jest.spyOn(FormFlowAnalytics, 'createHourlyChart');
      const createFormsSpy = jest.spyOn(FormFlowAnalytics, 'createTopFormsChart');

      FormFlowAnalytics.initCharts(mockData, { viewMode: 'overview' });

      expect(createTrendSpy).toHaveBeenCalled();
      expect(createStatusSpy).toHaveBeenCalled();
      expect(createHourlySpy).toHaveBeenCalled();
      expect(createFormsSpy).toHaveBeenCalled();
    });
  });

  describe('Real-time Statistics', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <button id="refresh-realtime"><span class="dashicons"></span></button>
        <span id="rt-submissions-today">0</span>
        <span id="rt-completed-today">0</span>
        <span id="rt-pending-signatures">0</span>
        <span id="rt-queue-pending">0</span>
        <span id="rt-last-submission">-</span>
      `;
    });

    test('should load real-time stats', () => {
      const mockFind = jest.fn(() => ({
        addClass: jest.fn(() => ({ removeClass: jest.fn() })),
        removeClass: jest.fn()
      }));

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#refresh-realtime') {
          return { find: mockFind };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = mockAjax;

      FormFlowAnalytics.loadRealtimeStats();

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_get_realtime_stats'
        })
      }));
    });

    test('should update real-time display', () => {
      const mockText = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        return { text: mockText };
      });
      global.$ = global.jQuery;

      const stats = {
        submissions_today: 42,
        completed_today: 38,
        pending_signatures: 4,
        queue_pending: 2,
        last_submission: new Date().toISOString()
      };

      FormFlowAnalytics.updateRealtimeDisplay(stats);

      expect(mockText).toHaveBeenCalledWith(42);
      expect(mockText).toHaveBeenCalledWith(38);
      expect(mockText).toHaveBeenCalledWith(4);
      expect(mockText).toHaveBeenCalledWith(2);
    });

    test('should start real-time polling', () => {
      const loadSpy = jest.spyOn(FormFlowAnalytics, 'loadRealtimeStats');

      FormFlowAnalytics.startRealtimePolling();

      expect(FormFlowAnalytics.realtimeInterval).not.toBeNull();

      jest.advanceTimersByTime(30000);
      expect(loadSpy).toHaveBeenCalled();
    });

    test('should clear existing interval before starting new one', () => {
      FormFlowAnalytics.realtimeInterval = setInterval(() => {}, 1000);
      const oldInterval = FormFlowAnalytics.realtimeInterval;

      FormFlowAnalytics.startRealtimePolling();

      expect(FormFlowAnalytics.realtimeInterval).not.toBe(oldInterval);
    });
  });

  describe('Date Formatting', () => {
    test('should format relative time - just now', () => {
      const now = new Date();
      const result = FormFlowAnalytics.formatRelativeTime(now.toISOString());
      expect(result).toBe('Just now');
    });

    test('should format relative time - minutes ago', () => {
      const date = new Date();
      date.setMinutes(date.getMinutes() - 5);
      const result = FormFlowAnalytics.formatRelativeTime(date.toISOString());
      expect(result).toBe('5m ago');
    });

    test('should format relative time - hours ago', () => {
      const date = new Date();
      date.setHours(date.getHours() - 3);
      const result = FormFlowAnalytics.formatRelativeTime(date.toISOString());
      expect(result).toBe('3h ago');
    });

    test('should format relative time - days ago', () => {
      const date = new Date();
      date.setDate(date.getDate() - 2);
      const result = FormFlowAnalytics.formatRelativeTime(date.toISOString());
      expect(result).toBe('2d ago');
    });

    test('should format date to YYYY-MM-DD', () => {
      const date = new Date('2024-03-15T10:30:00');
      const result = FormFlowAnalytics.formatDate(date);
      expect(result).toBe('2024-03-15');
    });
  });

  describe('Date Presets', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input id="date-from">
        <input id="date-to">
        <button class="preset-btn" data-days="7">7 Days</button>
        <button class="preset-btn" data-days="30">30 Days</button>
      `;
    });

    test('should set date preset for 7 days', () => {
      const mockVal = jest.fn();
      const mockRemoveClass = jest.fn(() => ({ addClass: jest.fn() }));

      global.jQuery.mockImplementation((selector) => {
        if (selector.includes('date-')) {
          return { val: mockVal };
        }
        if (selector === '.preset-btn') {
          return { removeClass: mockRemoveClass };
        }
        return { addClass: jest.fn() };
      });
      global.$ = global.jQuery;

      FormFlowAnalytics.setDatePreset(7);

      expect(mockVal).toHaveBeenCalledTimes(2);
      expect(mockRemoveClass).toHaveBeenCalledWith('active');
    });

    test('should set date preset for 30 days', () => {
      const mockVal = jest.fn();

      global.jQuery.mockImplementation((selector) => {
        if (selector.includes('date-')) {
          return { val: mockVal };
        }
        return { removeClass: jest.fn(() => ({ addClass: jest.fn() })) };
      });
      global.$ = global.jQuery;

      FormFlowAnalytics.setDatePreset(30);

      expect(mockVal).toHaveBeenCalledTimes(2);
    });
  });

  describe('Period Comparison', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <input id="current-from" value="2024-01-01">
        <input id="current-to" value="2024-01-31">
        <input id="previous-from" value="2023-12-01">
        <input id="previous-to" value="2023-12-31">
        <button id="compare-periods">Compare</button>
      `;
    });

    test('should make AJAX request to compare periods', () => {
      const mockProp = jest.fn(() => ({ text: jest.fn() }));
      const mockVal = jest.fn((selector) => {
        if (selector === '#current-from') return '2024-01-01';
        if (selector === '#current-to') return '2024-01-31';
        if (selector === '#previous-from') return '2023-12-01';
        if (selector === '#previous-to') return '2023-12-31';
        return '';
      });

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#compare-periods') {
          return { prop: mockProp, text: jest.fn() };
        }
        return { val: jest.fn(() => mockVal(selector)) };
      });
      global.$ = global.jQuery;
      global.$.ajax = mockAjax;

      FormFlowAnalytics.comparePeriods();

      expect(mockAjax).toHaveBeenCalledWith(expect.objectContaining({
        data: expect.objectContaining({
          action: 'formflow_compare_periods'
        })
      }));
    });

    test('should update comparison display', () => {
      const mockText = jest.fn();
      const updateChangeSpy = jest.spyOn(FormFlowAnalytics, 'updateChangeValue');

      global.jQuery.mockImplementation(() => ({
        text: mockText,
        toLocaleString: jest.fn(() => '1,000')
      }));
      global.$ = global.jQuery;

      const data = {
        current: { total: 1000, completed: 800, conversion_rate: 80 },
        previous: { total: 800, completed: 600, conversion_rate: 75 },
        changes: { total: 25, completed: 33.3, conversion_rate: 5 }
      };

      FormFlowAnalytics.updateComparisonDisplay(data);

      expect(updateChangeSpy).toHaveBeenCalledTimes(3);
    });

    test('should update change value with positive change', () => {
      const mockText = jest.fn();
      const mockClosest = jest.fn(() => ({
        removeClass: jest.fn(() => ({ addClass: jest.fn() }))
      }));

      global.jQuery.mockImplementation(() => ({
        text: mockText,
        closest: mockClosest
      }));
      global.$ = global.jQuery;

      FormFlowAnalytics.updateChangeValue('#test', 15.5, '%');

      expect(mockText).toHaveBeenCalledWith('+15.5%');
    });

    test('should update change value with negative change', () => {
      const mockText = jest.fn();
      const mockClosest = jest.fn(() => ({
        removeClass: jest.fn(() => ({ addClass: jest.fn() }))
      }));

      global.jQuery.mockImplementation(() => ({
        text: mockText,
        closest: mockClosest
      }));
      global.$ = global.jQuery;

      FormFlowAnalytics.updateChangeValue('#test', -10.2, '%');

      expect(mockText).toHaveBeenCalledWith('-10.2%');
    });
  });

  describe('System Health Check', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <div id="health-status">
          <span class="health-dot"></span>
          <span class="health-text"></span>
        </div>
      `;
    });

    test('should check system health', () => {
      const mockFind = jest.fn(() => ({
        css: jest.fn(),
        text: jest.fn()
      }));

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#health-status') {
          return { find: mockFind };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = mockAjax;

      FormFlowAnalytics.checkSystemHealth();

      expect(mockAjax).toHaveBeenCalled();
    });

    test('should show healthy status for low queue', () => {
      const mockCss = jest.fn();
      const mockText = jest.fn();
      const mockFind = jest.fn(() => ({
        css: mockCss,
        text: mockText
      }));

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#health-status') {
          return { find: mockFind };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: { queue_pending: 10 } });
      });

      FormFlowAnalytics.checkSystemHealth();

      expect(mockCss).toHaveBeenCalledWith('background', '#46b450');
      expect(mockText).toHaveBeenCalledWith('All systems operational');
    });

    test('should show warning for high queue', () => {
      const mockCss = jest.fn();
      const mockText = jest.fn();
      const mockFind = jest.fn(() => ({
        css: mockCss,
        text: mockText
      }));

      global.jQuery.mockImplementation((selector) => {
        if (selector === '#health-status') {
          return { find: mockFind };
        }
        return global.jQuery;
      });
      global.$ = global.jQuery;
      global.$.ajax = jest.fn((options) => {
        options.success({ success: true, data: { queue_pending: 150 } });
      });

      FormFlowAnalytics.checkSystemHealth();

      expect(mockCss).toHaveBeenCalledWith('background', '#dc3232');
      expect(mockText).toHaveBeenCalledWith('Warning: High queue backlog');
    });
  });

  describe('Export Report', () => {
    beforeEach(() => {
      document.body.innerHTML = `
        <select id="form-filter"><option value="1">Form 1</option></select>
        <input id="date-from" value="2024-01-01">
        <input id="date-to" value="2024-01-31">
      `;

      window.alert = jest.fn();
    });

    test('should export CSV report', () => {
      global.jQuery.mockImplementation((selector) => ({
        val: jest.fn(() => selector === '#form-filter' ? '1' : '2024-01-01')
      }));
      global.$ = global.jQuery;

      FormFlowAnalytics.exportReport('csv');

      expect(window.location.href).toContain('formflow_export_analytics_csv');
    });

    test('should show alert for PDF export', () => {
      FormFlowAnalytics.exportReport('pdf');

      expect(window.alert).toHaveBeenCalledWith('PDF export coming soon!');
    });
  });

  describe('Utility Methods', () => {
    test('should format status text', () => {
      expect(FormFlowAnalytics.formatStatus('pending_signature')).toBe('Pending Signature');
      expect(FormFlowAnalytics.formatStatus('completed')).toBe('Completed');
      expect(FormFlowAnalytics.formatStatus('failed')).toBe('Failed');
    });

    test('should get status colors', () => {
      const statuses = ['completed', 'pending', 'failed'];
      const colors = FormFlowAnalytics.getStatusColors(statuses);

      expect(colors.backgrounds).toHaveLength(3);
      expect(colors.borders).toHaveLength(3);
      expect(colors.backgrounds[0]).toContain('70, 180, 80');
      expect(colors.backgrounds[1]).toContain('255, 185, 0');
      expect(colors.backgrounds[2]).toContain('220, 50, 50');
    });

    test('should return default colors for unknown status', () => {
      const statuses = ['unknown_status'];
      const colors = FormFlowAnalytics.getStatusColors(statuses);

      expect(colors.backgrounds[0]).toContain('150, 150, 150');
      expect(colors.borders[0]).toBe('#969696');
    });
  });

  describe('Cleanup', () => {
    test('should clear interval on cleanup', () => {
      FormFlowAnalytics.realtimeInterval = setInterval(() => {}, 1000);

      FormFlowAnalytics.cleanup();

      expect(FormFlowAnalytics.realtimeInterval).toBe(null);
    });

    test('should destroy all charts on cleanup', () => {
      const mockDestroy = jest.fn();
      FormFlowAnalytics.charts = {
        trend: { destroy: mockDestroy },
        status: { destroy: mockDestroy }
      };

      FormFlowAnalytics.cleanup();

      expect(mockDestroy).toHaveBeenCalledTimes(2);
      expect(FormFlowAnalytics.charts).toEqual({});
    });

    test('should destroy charts independently', () => {
      const mockDestroy = jest.fn();
      FormFlowAnalytics.charts = {
        trend: { destroy: mockDestroy }
      };

      FormFlowAnalytics.destroyCharts();

      expect(mockDestroy).toHaveBeenCalled();
      expect(FormFlowAnalytics.charts).toEqual({});
    });
  });
});
