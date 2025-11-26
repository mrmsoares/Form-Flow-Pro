<?php
/**
 * D3 Visualization - Interactive D3.js Chart System
 *
 * Provides advanced interactive data visualizations using D3.js
 * including line charts, bar charts, pie charts, heatmaps, and funnel charts.
 *
 * @package FormFlowPro
 * @subpackage Reporting
 * @since 2.3.0
 */

namespace FormFlowPro\Reporting;

use FormFlowPro\Core\SingletonTrait;

/**
 * Chart configuration model
 */
class ChartConfig
{
    public string $id;
    public string $type;
    public string $title;
    public string $subtitle;
    public array $data;
    public array $options;
    public array $dimensions;
    public array $colors;
    public array $axes;
    public array $legend;
    public array $tooltip;
    public array $animation;
    public bool $responsive;
    public bool $interactive;

    public function __construct(array $config = [])
    {
        $this->id = $config['id'] ?? 'chart-' . wp_generate_uuid4();
        $this->type = $config['type'] ?? 'line';
        $this->title = $config['title'] ?? '';
        $this->subtitle = $config['subtitle'] ?? '';
        $this->data = $config['data'] ?? [];
        $this->options = $config['options'] ?? [];
        $this->dimensions = $config['dimensions'] ?? ['width' => 600, 'height' => 400];
        $this->colors = $config['colors'] ?? ['#2271b1', '#72aee6', '#135e96', '#9ec2e6', '#c5d9ed'];
        $this->axes = $config['axes'] ?? [];
        $this->legend = $config['legend'] ?? ['show' => true, 'position' => 'bottom'];
        $this->tooltip = $config['tooltip'] ?? ['enabled' => true];
        $this->animation = $config['animation'] ?? ['enabled' => true, 'duration' => 750];
        $this->responsive = $config['responsive'] ?? true;
        $this->interactive = $config['interactive'] ?? true;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'data' => $this->data,
            'options' => $this->options,
            'dimensions' => $this->dimensions,
            'colors' => $this->colors,
            'axes' => $this->axes,
            'legend' => $this->legend,
            'tooltip' => $this->tooltip,
            'animation' => $this->animation,
            'responsive' => $this->responsive,
            'interactive' => $this->interactive
        ];
    }

    public function toJSON(): string
    {
        return wp_json_encode($this->toArray());
    }
}

/**
 * Dashboard Widget model
 */
class DashboardWidget
{
    public string $id;
    public string $title;
    public string $type;
    public int $width;
    public int $height;
    public int $order;
    public array $chart_config;
    public array $data_source;
    public array $refresh;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? wp_generate_uuid4();
        $this->title = $data['title'] ?? '';
        $this->type = $data['type'] ?? 'chart';
        $this->width = $data['width'] ?? 6;
        $this->height = $data['height'] ?? 300;
        $this->order = $data['order'] ?? 0;
        $this->chart_config = $data['chart_config'] ?? [];
        $this->data_source = $data['data_source'] ?? [];
        $this->refresh = $data['refresh'] ?? ['enabled' => false, 'interval' => 60];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'width' => $this->width,
            'height' => $this->height,
            'order' => $this->order,
            'chart_config' => $this->chart_config,
            'data_source' => $this->data_source,
            'refresh' => $this->refresh
        ];
    }
}

/**
 * D3 Visualization Manager
 */
class D3Visualization
{
    use SingletonTrait;

    private string $d3_version = '7.8.5';
    private array $chart_types = [];
    private array $color_schemes = [];
    private array $registered_charts = [];

    /**
     * Initialize visualization manager
     */
    protected function init(): void
    {
        $this->registerChartTypes();
        $this->registerColorSchemes();
        $this->registerHooks();
    }

    /**
     * Register hooks
     */
    private function registerHooks(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueuePublicAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // AJAX handlers
        add_action('wp_ajax_ffp_get_chart_data', [$this, 'ajaxGetChartData']);
        add_action('wp_ajax_ffp_save_dashboard_layout', [$this, 'ajaxSaveDashboardLayout']);
        add_action('wp_ajax_ffp_export_chart', [$this, 'ajaxExportChart']);
    }

    /**
     * Register default chart types
     */
    private function registerChartTypes(): void
    {
        // Line Chart
        $this->registerChartType('line', [
            'label' => __('Line Chart', 'form-flow-pro'),
            'description' => __('Show trends over time', 'form-flow-pro'),
            'icon' => 'chart-line',
            'supports' => ['multiple_series', 'area_fill', 'annotations', 'zoom'],
            'defaults' => [
                'curve' => 'monotone',
                'showPoints' => true,
                'pointRadius' => 4,
                'lineWidth' => 2,
                'areaOpacity' => 0.2
            ]
        ]);

        // Bar Chart
        $this->registerChartType('bar', [
            'label' => __('Bar Chart', 'form-flow-pro'),
            'description' => __('Compare values across categories', 'form-flow-pro'),
            'icon' => 'chart-bar',
            'supports' => ['horizontal', 'stacked', 'grouped', 'labels'],
            'defaults' => [
                'barPadding' => 0.2,
                'groupPadding' => 0.1,
                'borderRadius' => 4,
                'showValues' => false
            ]
        ]);

        // Pie Chart
        $this->registerChartType('pie', [
            'label' => __('Pie Chart', 'form-flow-pro'),
            'description' => __('Show part-to-whole relationships', 'form-flow-pro'),
            'icon' => 'chart-pie',
            'supports' => ['donut', 'labels', 'explode'],
            'defaults' => [
                'innerRadius' => 0,
                'outerRadius' => 150,
                'padAngle' => 0.02,
                'cornerRadius' => 4,
                'showLabels' => true,
                'labelType' => 'percent'
            ]
        ]);

        // Donut Chart
        $this->registerChartType('donut', [
            'label' => __('Donut Chart', 'form-flow-pro'),
            'description' => __('Pie chart with center hole', 'form-flow-pro'),
            'icon' => 'chart-pie',
            'supports' => ['center_label', 'labels'],
            'defaults' => [
                'innerRadius' => 80,
                'outerRadius' => 150,
                'padAngle' => 0.02,
                'cornerRadius' => 4,
                'showLabels' => true,
                'centerLabel' => ''
            ]
        ]);

        // Area Chart
        $this->registerChartType('area', [
            'label' => __('Area Chart', 'form-flow-pro'),
            'description' => __('Filled line chart for volume', 'form-flow-pro'),
            'icon' => 'chart-area',
            'supports' => ['stacked', 'gradient', 'multiple_series'],
            'defaults' => [
                'curve' => 'monotone',
                'opacity' => 0.6,
                'gradient' => true,
                'showLine' => true
            ]
        ]);

        // Scatter Plot
        $this->registerChartType('scatter', [
            'label' => __('Scatter Plot', 'form-flow-pro'),
            'description' => __('Show correlation between variables', 'form-flow-pro'),
            'icon' => 'admin-generic',
            'supports' => ['size_encoding', 'color_encoding', 'regression_line'],
            'defaults' => [
                'pointRadius' => 6,
                'opacity' => 0.7,
                'jitter' => false
            ]
        ]);

        // Heatmap
        $this->registerChartType('heatmap', [
            'label' => __('Heatmap', 'form-flow-pro'),
            'description' => __('Matrix visualization with color intensity', 'form-flow-pro'),
            'icon' => 'grid-view',
            'supports' => ['color_scale', 'labels', 'annotations'],
            'defaults' => [
                'colorScale' => ['#f7fbff', '#2271b1'],
                'cellPadding' => 2,
                'cellRadius' => 2,
                'showValues' => true
            ]
        ]);

        // Funnel Chart
        $this->registerChartType('funnel', [
            'label' => __('Funnel Chart', 'form-flow-pro'),
            'description' => __('Visualize conversion processes', 'form-flow-pro'),
            'icon' => 'filter',
            'supports' => ['labels', 'percentages', 'comparison'],
            'defaults' => [
                'funnelWidth' => 0.8,
                'neckWidth' => 0.3,
                'neckHeight' => 0.25,
                'showLabels' => true,
                'showPercentages' => true,
                'direction' => 'vertical'
            ]
        ]);

        // Gauge Chart
        $this->registerChartType('gauge', [
            'label' => __('Gauge Chart', 'form-flow-pro'),
            'description' => __('Show progress towards a goal', 'form-flow-pro'),
            'icon' => 'performance',
            'supports' => ['thresholds', 'labels', 'target'],
            'defaults' => [
                'min' => 0,
                'max' => 100,
                'startAngle' => -120,
                'endAngle' => 120,
                'thresholds' => [
                    ['value' => 30, 'color' => '#dc3545'],
                    ['value' => 70, 'color' => '#ffc107'],
                    ['value' => 100, 'color' => '#28a745']
                ]
            ]
        ]);

        // Radial Bar Chart
        $this->registerChartType('radial_bar', [
            'label' => __('Radial Bar', 'form-flow-pro'),
            'description' => __('Circular bar chart', 'form-flow-pro'),
            'icon' => 'marker',
            'supports' => ['labels', 'track'],
            'defaults' => [
                'innerRadius' => 40,
                'barWidth' => 20,
                'showTrack' => true,
                'trackColor' => '#e9ecef',
                'cornerRadius' => 10
            ]
        ]);

        // Treemap
        $this->registerChartType('treemap', [
            'label' => __('Treemap', 'form-flow-pro'),
            'description' => __('Hierarchical data as nested rectangles', 'form-flow-pro'),
            'icon' => 'screenoptions',
            'supports' => ['drill_down', 'labels', 'breadcrumb'],
            'defaults' => [
                'padding' => 2,
                'labelThreshold' => 50,
                'tile' => 'squarify'
            ]
        ]);

        // Sankey Diagram
        $this->registerChartType('sankey', [
            'label' => __('Sankey Diagram', 'form-flow-pro'),
            'description' => __('Flow and relationship visualization', 'form-flow-pro'),
            'icon' => 'randomize',
            'supports' => ['labels', 'gradients'],
            'defaults' => [
                'nodeWidth' => 20,
                'nodePadding' => 10,
                'linkOpacity' => 0.5
            ]
        ]);

        do_action('ffp_register_chart_types', $this);
    }

    /**
     * Register default color schemes
     */
    private function registerColorSchemes(): void
    {
        $this->registerColorScheme('default', [
            'name' => __('Default', 'form-flow-pro'),
            'colors' => ['#2271b1', '#72aee6', '#135e96', '#9ec2e6', '#c5d9ed', '#1d4e89', '#4a90d9', '#6ba3e0']
        ]);

        $this->registerColorScheme('vibrant', [
            'name' => __('Vibrant', 'form-flow-pro'),
            'colors' => ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e']
        ]);

        $this->registerColorScheme('pastel', [
            'name' => __('Pastel', 'form-flow-pro'),
            'colors' => ['#a8dadc', '#ffd6a5', '#caffbf', '#ffc8dd', '#bde0fe', '#e2eafc', '#ffccd5', '#d8e2dc']
        ]);

        $this->registerColorScheme('corporate', [
            'name' => __('Corporate', 'form-flow-pro'),
            'colors' => ['#1e3a5f', '#3d5a80', '#5c7ea8', '#7b9ec9', '#98bee5', '#0066cc', '#004999', '#003366']
        ]);

        $this->registerColorScheme('nature', [
            'name' => __('Nature', 'form-flow-pro'),
            'colors' => ['#2d6a4f', '#40916c', '#52b788', '#74c69d', '#95d5b2', '#1b4332', '#081c15', '#b7e4c7']
        ]);

        $this->registerColorScheme('sunset', [
            'name' => __('Sunset', 'form-flow-pro'),
            'colors' => ['#ff6b35', '#f7c59f', '#efa42b', '#c4740f', '#5c4742', '#2b2d42', '#8d99ae', '#edf2f4']
        ]);

        $this->registerColorScheme('monochrome', [
            'name' => __('Monochrome', 'form-flow-pro'),
            'colors' => ['#1d2327', '#3c434a', '#646970', '#8c8f94', '#a7aaad', '#c3c4c7', '#dcdcde', '#f0f0f1']
        ]);

        $this->registerColorScheme('categorical', [
            'name' => __('Categorical', 'form-flow-pro'),
            'colors' => ['#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd', '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf']
        ]);

        do_action('ffp_register_color_schemes', $this);
    }

    /**
     * Register a chart type
     */
    public function registerChartType(string $type, array $config): void
    {
        $this->chart_types[$type] = array_merge([
            'type' => $type,
            'label' => '',
            'description' => '',
            'icon' => 'chart-bar',
            'supports' => [],
            'defaults' => []
        ], $config);
    }

    /**
     * Get all chart types
     */
    public function getChartTypes(): array
    {
        return $this->chart_types;
    }

    /**
     * Get chart type config
     */
    public function getChartType(string $type): ?array
    {
        return $this->chart_types[$type] ?? null;
    }

    /**
     * Register a color scheme
     */
    public function registerColorScheme(string $id, array $config): void
    {
        $this->color_schemes[$id] = array_merge([
            'id' => $id,
            'name' => '',
            'colors' => []
        ], $config);
    }

    /**
     * Get all color schemes
     */
    public function getColorSchemes(): array
    {
        return $this->color_schemes;
    }

    /**
     * Get color scheme
     */
    public function getColorScheme(string $id): array
    {
        return $this->color_schemes[$id]['colors'] ?? $this->color_schemes['default']['colors'];
    }

    /**
     * Create a chart configuration
     */
    public function createChart(array $config): ChartConfig
    {
        $type = $config['type'] ?? 'line';
        $type_config = $this->getChartType($type);

        // Merge with defaults
        if ($type_config) {
            $config['options'] = array_merge($type_config['defaults'], $config['options'] ?? []);
        }

        // Apply color scheme
        if (isset($config['colorScheme'])) {
            $config['colors'] = $this->getColorScheme($config['colorScheme']);
        }

        $chart = new ChartConfig($config);
        $this->registered_charts[$chart->id] = $chart;

        return $chart;
    }

    /**
     * Render a chart
     */
    public function render(ChartConfig $chart, array $options = []): string
    {
        $container_id = 'ffp-chart-' . $chart->id;
        $wrapper_classes = ['ffp-chart-wrapper'];

        if ($chart->responsive) {
            $wrapper_classes[] = 'ffp-chart-responsive';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
             data-chart-id="<?php echo esc_attr($chart->id); ?>">

            <?php if ($chart->title || $chart->subtitle): ?>
            <div class="ffp-chart-header">
                <?php if ($chart->title): ?>
                <h3 class="ffp-chart-title"><?php echo esc_html($chart->title); ?></h3>
                <?php endif; ?>
                <?php if ($chart->subtitle): ?>
                <p class="ffp-chart-subtitle"><?php echo esc_html($chart->subtitle); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div id="<?php echo esc_attr($container_id); ?>"
                 class="ffp-chart-container"
                 style="width: 100%; height: <?php echo esc_attr($chart->dimensions['height']); ?>px;">
            </div>

            <?php if ($chart->legend['show']): ?>
            <div class="ffp-chart-legend ffp-legend-<?php echo esc_attr($chart->legend['position']); ?>"
                 id="<?php echo esc_attr($container_id); ?>-legend">
            </div>
            <?php endif; ?>

            <?php if ($chart->interactive): ?>
            <div class="ffp-chart-toolbar">
                <button type="button" class="ffp-chart-action" data-action="zoom-in" title="<?php esc_attr_e('Zoom In', 'form-flow-pro'); ?>">
                    <span class="dashicons dashicons-plus"></span>
                </button>
                <button type="button" class="ffp-chart-action" data-action="zoom-out" title="<?php esc_attr_e('Zoom Out', 'form-flow-pro'); ?>">
                    <span class="dashicons dashicons-minus"></span>
                </button>
                <button type="button" class="ffp-chart-action" data-action="reset" title="<?php esc_attr_e('Reset', 'form-flow-pro'); ?>">
                    <span class="dashicons dashicons-image-rotate"></span>
                </button>
                <button type="button" class="ffp-chart-action" data-action="download" title="<?php esc_attr_e('Download', 'form-flow-pro'); ?>">
                    <span class="dashicons dashicons-download"></span>
                </button>
                <button type="button" class="ffp-chart-action" data-action="fullscreen" title="<?php esc_attr_e('Fullscreen', 'form-flow-pro'); ?>">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            </div>
            <?php endif; ?>

        </div>

        <script>
        (function() {
            if (typeof window.FFPCharts === 'undefined') {
                window.FFPCharts = {};
            }

            window.FFPCharts['<?php echo esc_js($chart->id); ?>'] = {
                config: <?php echo $chart->toJSON(); ?>,
                container: '<?php echo esc_js($container_id); ?>',
                rendered: false
            };

            // Initialize when D3 is ready
            if (typeof FFPVisualization !== 'undefined') {
                FFPVisualization.render('<?php echo esc_js($chart->id); ?>');
            }
        })();
        </script>
        <?php

        return ob_get_clean();
    }

    /**
     * Render multiple charts in a grid
     */
    public function renderGrid(array $charts, array $options = []): string
    {
        $columns = $options['columns'] ?? 2;
        $gap = $options['gap'] ?? 20;

        ob_start();
        ?>
        <div class="ffp-charts-grid" style="display: grid; grid-template-columns: repeat(<?php echo (int) $columns; ?>, 1fr); gap: <?php echo (int) $gap; ?>px;">
            <?php foreach ($charts as $chart): ?>
                <?php
                if (is_array($chart)) {
                    $chart = $this->createChart($chart);
                }
                echo $this->render($chart, $options);
                ?>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(string $hook): void
    {
        // Only load on our pages
        if (strpos($hook, 'ffp-') === false && strpos($hook, 'formflow') === false) {
            return;
        }

        $this->enqueueD3Scripts();
    }

    /**
     * Enqueue public assets
     */
    public function enqueuePublicAssets(): void
    {
        if (empty($this->registered_charts)) {
            return;
        }

        $this->enqueueD3Scripts();
    }

    /**
     * Enqueue D3.js scripts
     */
    private function enqueueD3Scripts(): void
    {
        // D3.js core
        wp_enqueue_script(
            'ffp-d3',
            'https://d3js.org/d3.v' . explode('.', $this->d3_version)[0] . '.min.js',
            [],
            $this->d3_version,
            true
        );

        // Custom visualization library
        wp_enqueue_script(
            'ffp-visualization',
            FORMFLOW_PRO_URL . 'assets/js/visualization.js',
            ['jquery', 'ffp-d3'],
            FORMFLOW_PRO_VERSION,
            true
        );

        wp_enqueue_style(
            'ffp-visualization',
            FORMFLOW_PRO_URL . 'assets/css/visualization.css',
            [],
            FORMFLOW_PRO_VERSION
        );

        wp_localize_script('ffp-visualization', 'ffpVisualization', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formflow/v1/visualization/'),
            'nonce' => wp_create_nonce('ffp_visualization'),
            'chartTypes' => $this->getChartTypes(),
            'colorSchemes' => $this->getColorSchemes(),
            'i18n' => [
                'loading' => __('Loading...', 'form-flow-pro'),
                'noData' => __('No data available', 'form-flow-pro'),
                'error' => __('Failed to load chart', 'form-flow-pro'),
                'download' => __('Download', 'form-flow-pro'),
                'exportPNG' => __('Export as PNG', 'form-flow-pro'),
                'exportSVG' => __('Export as SVG', 'form-flow-pro'),
                'exportCSV' => __('Export data as CSV', 'form-flow-pro')
            ]
        ]);
    }

    /**
     * Generate inline JavaScript for D3 charts
     */
    public function generateChartScript(ChartConfig $chart): string
    {
        $method = 'generate' . str_replace('_', '', ucwords($chart->type, '_')) . 'Script';

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $chart);
        }

        return $this->generateGenericScript($chart);
    }

    /**
     * Generate line chart script
     */
    private function generateLineScript(ChartConfig $chart): string
    {
        return <<<SCRIPT
FFPVisualization.charts.line = function(config) {
    const container = d3.select('#' + config.container);
    const margin = {top: 20, right: 30, bottom: 40, left: 50};
    const width = container.node().clientWidth - margin.left - margin.right;
    const height = config.config.dimensions.height - margin.top - margin.bottom;

    const svg = container.append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

    const data = config.config.data;
    const colors = config.config.colors;
    const options = config.config.options;

    // Parse dates
    const parseDate = d3.timeParse('%Y-%m-%d');
    data.forEach(d => {
        if (typeof d.date === 'string') {
            d.date = parseDate(d.date) || new Date(d.date);
        }
    });

    // Scales
    const x = d3.scaleTime()
        .domain(d3.extent(data, d => d.date))
        .range([0, width]);

    const y = d3.scaleLinear()
        .domain([0, d3.max(data, d => d.value) * 1.1])
        .range([height, 0]);

    // Line generator
    const line = d3.line()
        .x(d => x(d.date))
        .y(d => y(d.value))
        .curve(d3.curveMonotoneX);

    // Area generator (optional)
    if (options.areaOpacity > 0) {
        const area = d3.area()
            .x(d => x(d.date))
            .y0(height)
            .y1(d => y(d.value))
            .curve(d3.curveMonotoneX);

        svg.append('path')
            .datum(data)
            .attr('fill', colors[0])
            .attr('fill-opacity', options.areaOpacity)
            .attr('d', area);
    }

    // Draw line
    svg.append('path')
        .datum(data)
        .attr('fill', 'none')
        .attr('stroke', colors[0])
        .attr('stroke-width', options.lineWidth || 2)
        .attr('d', line);

    // Points
    if (options.showPoints) {
        svg.selectAll('.dot')
            .data(data)
            .enter().append('circle')
            .attr('class', 'dot')
            .attr('cx', d => x(d.date))
            .attr('cy', d => y(d.value))
            .attr('r', options.pointRadius || 4)
            .attr('fill', colors[0]);
    }

    // Axes
    svg.append('g')
        .attr('transform', 'translate(0,' + height + ')')
        .call(d3.axisBottom(x).ticks(6));

    svg.append('g')
        .call(d3.axisLeft(y).ticks(5));

    // Tooltip
    if (config.config.tooltip.enabled) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'ffp-tooltip')
            .style('opacity', 0);

        svg.selectAll('.dot')
            .on('mouseover', function(event, d) {
                tooltip.transition()
                    .duration(200)
                    .style('opacity', .9);
                tooltip.html(d3.timeFormat('%b %d')(d.date) + ': ' + d.value)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function() {
                tooltip.transition()
                    .duration(500)
                    .style('opacity', 0);
            });
    }

    return svg;
};
SCRIPT;
    }

    /**
     * Generate bar chart script
     */
    private function generateBarScript(ChartConfig $chart): string
    {
        return <<<SCRIPT
FFPVisualization.charts.bar = function(config) {
    const container = d3.select('#' + config.container);
    const margin = {top: 20, right: 30, bottom: 60, left: 50};
    const width = container.node().clientWidth - margin.left - margin.right;
    const height = config.config.dimensions.height - margin.top - margin.bottom;

    const svg = container.append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

    const data = config.config.data;
    const colors = config.config.colors;
    const options = config.config.options;

    // Scales
    const x = d3.scaleBand()
        .domain(data.map(d => d.label))
        .range([0, width])
        .padding(options.barPadding || 0.2);

    const y = d3.scaleLinear()
        .domain([0, d3.max(data, d => d.value) * 1.1])
        .range([height, 0]);

    // Bars
    svg.selectAll('.bar')
        .data(data)
        .enter().append('rect')
        .attr('class', 'bar')
        .attr('x', d => x(d.label))
        .attr('width', x.bandwidth())
        .attr('y', height)
        .attr('height', 0)
        .attr('fill', (d, i) => colors[i % colors.length])
        .attr('rx', options.borderRadius || 0)
        .transition()
        .duration(750)
        .attr('y', d => y(d.value))
        .attr('height', d => height - y(d.value));

    // Values on bars
    if (options.showValues) {
        svg.selectAll('.bar-value')
            .data(data)
            .enter().append('text')
            .attr('class', 'bar-value')
            .attr('x', d => x(d.label) + x.bandwidth() / 2)
            .attr('y', d => y(d.value) - 5)
            .attr('text-anchor', 'middle')
            .attr('font-size', '12px')
            .text(d => d.value);
    }

    // Axes
    svg.append('g')
        .attr('transform', 'translate(0,' + height + ')')
        .call(d3.axisBottom(x))
        .selectAll('text')
        .attr('transform', 'rotate(-45)')
        .style('text-anchor', 'end');

    svg.append('g')
        .call(d3.axisLeft(y).ticks(5));

    // Tooltip
    if (config.config.tooltip.enabled) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'ffp-tooltip')
            .style('opacity', 0);

        svg.selectAll('.bar')
            .on('mouseover', function(event, d) {
                d3.select(this).attr('opacity', 0.8);
                tooltip.transition().duration(200).style('opacity', .9);
                tooltip.html('<strong>' + d.label + '</strong><br/>' + d.value)
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function() {
                d3.select(this).attr('opacity', 1);
                tooltip.transition().duration(500).style('opacity', 0);
            });
    }

    return svg;
};
SCRIPT;
    }

    /**
     * Generate pie/donut chart script
     */
    private function generatePieScript(ChartConfig $chart): string
    {
        return <<<SCRIPT
FFPVisualization.charts.pie = function(config) {
    const container = d3.select('#' + config.container);
    const width = container.node().clientWidth;
    const height = config.config.dimensions.height;
    const radius = Math.min(width, height) / 2 - 20;

    const svg = container.append('svg')
        .attr('width', width)
        .attr('height', height)
        .append('g')
        .attr('transform', 'translate(' + width / 2 + ',' + height / 2 + ')');

    const data = config.config.data;
    const colors = config.config.colors;
    const options = config.config.options;

    const innerRadius = options.innerRadius || 0;
    const outerRadius = Math.min(radius, options.outerRadius || radius);

    // Pie layout
    const pie = d3.pie()
        .value(d => d.value)
        .sort(null)
        .padAngle(options.padAngle || 0.02);

    // Arc generator
    const arc = d3.arc()
        .innerRadius(innerRadius)
        .outerRadius(outerRadius)
        .cornerRadius(options.cornerRadius || 0);

    // Hover arc
    const arcHover = d3.arc()
        .innerRadius(innerRadius)
        .outerRadius(outerRadius + 10);

    // Draw slices
    const slices = svg.selectAll('.slice')
        .data(pie(data))
        .enter().append('g')
        .attr('class', 'slice');

    slices.append('path')
        .attr('d', arc)
        .attr('fill', (d, i) => colors[i % colors.length])
        .attr('stroke', '#fff')
        .attr('stroke-width', 2)
        .transition()
        .duration(750)
        .attrTween('d', function(d) {
            const i = d3.interpolate({startAngle: 0, endAngle: 0}, d);
            return function(t) { return arc(i(t)); };
        });

    // Labels
    if (options.showLabels) {
        const labelArc = d3.arc()
            .innerRadius(outerRadius * 0.7)
            .outerRadius(outerRadius * 0.7);

        const total = d3.sum(data, d => d.value);

        slices.append('text')
            .attr('transform', d => 'translate(' + labelArc.centroid(d) + ')')
            .attr('text-anchor', 'middle')
            .attr('font-size', '12px')
            .attr('fill', '#fff')
            .text(d => {
                const percent = (d.data.value / total * 100).toFixed(1);
                return options.labelType === 'percent' ? percent + '%' : d.data.value;
            });
    }

    // Center label for donut
    if (innerRadius > 0 && options.centerLabel) {
        svg.append('text')
            .attr('text-anchor', 'middle')
            .attr('dy', '0.35em')
            .attr('font-size', '24px')
            .attr('font-weight', 'bold')
            .text(options.centerLabel);
    }

    // Tooltip
    if (config.config.tooltip.enabled) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'ffp-tooltip')
            .style('opacity', 0);

        const total = d3.sum(data, d => d.value);

        slices.selectAll('path')
            .on('mouseover', function(event, d) {
                d3.select(this).transition().duration(200).attr('d', arcHover);
                const percent = (d.data.value / total * 100).toFixed(1);
                tooltip.transition().duration(200).style('opacity', .9);
                tooltip.html('<strong>' + d.data.label + '</strong><br/>' + d.data.value + ' (' + percent + '%)')
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function() {
                d3.select(this).transition().duration(200).attr('d', arc);
                tooltip.transition().duration(500).style('opacity', 0);
            });
    }

    // Legend
    if (config.config.legend.show) {
        const legendContainer = d3.select('#' + config.container + '-legend');
        const legend = legendContainer.selectAll('.legend-item')
            .data(data)
            .enter().append('div')
            .attr('class', 'legend-item')
            .style('display', 'inline-flex')
            .style('align-items', 'center')
            .style('margin-right', '16px');

        legend.append('span')
            .style('width', '12px')
            .style('height', '12px')
            .style('background', (d, i) => colors[i % colors.length])
            .style('border-radius', '2px')
            .style('margin-right', '6px');

        legend.append('span')
            .text(d => d.label)
            .style('font-size', '12px');
    }

    return svg;
};
SCRIPT;
    }

    /**
     * Generate heatmap script
     */
    private function generateHeatmapScript(ChartConfig $chart): string
    {
        return <<<SCRIPT
FFPVisualization.charts.heatmap = function(config) {
    const container = d3.select('#' + config.container);
    const margin = {top: 30, right: 30, bottom: 60, left: 100};
    const width = container.node().clientWidth - margin.left - margin.right;
    const height = config.config.dimensions.height - margin.top - margin.bottom;

    const svg = container.append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

    const data = config.config.data;
    const options = config.config.options;

    // Get unique values for x and y
    const xLabels = [...new Set(data.map(d => d.x))];
    const yLabels = [...new Set(data.map(d => d.y))];

    // Scales
    const x = d3.scaleBand()
        .domain(xLabels)
        .range([0, width])
        .padding(0.05);

    const y = d3.scaleBand()
        .domain(yLabels)
        .range([0, height])
        .padding(0.05);

    const colorScale = options.colorScale || ['#f7fbff', '#2271b1'];
    const color = d3.scaleSequential()
        .domain([0, d3.max(data, d => d.value)])
        .interpolator(d3.interpolateRgbBasis(colorScale));

    // Cells
    svg.selectAll('.cell')
        .data(data)
        .enter().append('rect')
        .attr('class', 'cell')
        .attr('x', d => x(d.x))
        .attr('y', d => y(d.y))
        .attr('width', x.bandwidth())
        .attr('height', y.bandwidth())
        .attr('fill', d => color(d.value))
        .attr('rx', options.cellRadius || 2)
        .attr('opacity', 0)
        .transition()
        .duration(750)
        .attr('opacity', 1);

    // Values
    if (options.showValues) {
        svg.selectAll('.cell-value')
            .data(data)
            .enter().append('text')
            .attr('class', 'cell-value')
            .attr('x', d => x(d.x) + x.bandwidth() / 2)
            .attr('y', d => y(d.y) + y.bandwidth() / 2)
            .attr('text-anchor', 'middle')
            .attr('dominant-baseline', 'middle')
            .attr('font-size', '11px')
            .attr('fill', d => d.value > d3.max(data, d => d.value) / 2 ? '#fff' : '#333')
            .text(d => d.value);
    }

    // Axes
    svg.append('g')
        .attr('transform', 'translate(0,' + height + ')')
        .call(d3.axisBottom(x))
        .selectAll('text')
        .attr('transform', 'rotate(-45)')
        .style('text-anchor', 'end');

    svg.append('g')
        .call(d3.axisLeft(y));

    // Tooltip
    if (config.config.tooltip.enabled) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'ffp-tooltip')
            .style('opacity', 0);

        svg.selectAll('.cell')
            .on('mouseover', function(event, d) {
                d3.select(this).attr('stroke', '#333').attr('stroke-width', 2);
                tooltip.transition().duration(200).style('opacity', .9);
                tooltip.html(d.x + ' / ' + d.y + '<br/><strong>' + d.value + '</strong>')
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function() {
                d3.select(this).attr('stroke', 'none');
                tooltip.transition().duration(500).style('opacity', 0);
            });
    }

    return svg;
};
SCRIPT;
    }

    /**
     * Generate funnel chart script
     */
    private function generateFunnelScript(ChartConfig $chart): string
    {
        return <<<SCRIPT
FFPVisualization.charts.funnel = function(config) {
    const container = d3.select('#' + config.container);
    const margin = {top: 20, right: 100, bottom: 20, left: 100};
    const width = container.node().clientWidth - margin.left - margin.right;
    const height = config.config.dimensions.height - margin.top - margin.bottom;

    const svg = container.append('svg')
        .attr('width', width + margin.left + margin.right)
        .attr('height', height + margin.top + margin.bottom)
        .append('g')
        .attr('transform', 'translate(' + margin.left + ',' + margin.top + ')');

    const data = config.config.data;
    const colors = config.config.colors;
    const options = config.config.options;

    const maxValue = data[0].value;
    const stepHeight = height / data.length;

    // Generate funnel paths
    data.forEach((d, i) => {
        const topWidth = (d.value / maxValue) * width;
        const nextValue = data[i + 1] ? data[i + 1].value : d.value * 0.7;
        const bottomWidth = (nextValue / maxValue) * width;

        const topLeft = (width - topWidth) / 2;
        const topRight = topLeft + topWidth;
        const bottomLeft = (width - bottomWidth) / 2;
        const bottomRight = bottomLeft + bottomWidth;

        const y1 = i * stepHeight;
        const y2 = (i + 1) * stepHeight;

        // Trapezoid path
        const path = 'M' + topLeft + ',' + y1 +
                    ' L' + topRight + ',' + y1 +
                    ' L' + bottomRight + ',' + y2 +
                    ' L' + bottomLeft + ',' + y2 + ' Z';

        svg.append('path')
            .attr('d', path)
            .attr('fill', colors[i % colors.length])
            .attr('stroke', '#fff')
            .attr('stroke-width', 2)
            .attr('opacity', 0)
            .transition()
            .delay(i * 150)
            .duration(500)
            .attr('opacity', 1);

        // Labels
        if (options.showLabels) {
            svg.append('text')
                .attr('x', -10)
                .attr('y', y1 + stepHeight / 2)
                .attr('text-anchor', 'end')
                .attr('dominant-baseline', 'middle')
                .attr('font-size', '13px')
                .attr('font-weight', 'bold')
                .text(d.label);
        }

        // Values and percentages
        const percentage = ((d.value / maxValue) * 100).toFixed(1);
        svg.append('text')
            .attr('x', width + 10)
            .attr('y', y1 + stepHeight / 2)
            .attr('text-anchor', 'start')
            .attr('dominant-baseline', 'middle')
            .attr('font-size', '12px')
            .html(d.value.toLocaleString() + (options.showPercentages ? ' (' + percentage + '%)' : ''));
    });

    // Tooltip
    if (config.config.tooltip.enabled) {
        const tooltip = d3.select('body').append('div')
            .attr('class', 'ffp-tooltip')
            .style('opacity', 0);

        svg.selectAll('path')
            .on('mouseover', function(event, d, i) {
                d3.select(this).attr('opacity', 0.8);
                const item = data[i] || data[0];
                const percentage = ((item.value / maxValue) * 100).toFixed(1);
                tooltip.transition().duration(200).style('opacity', .9);
                tooltip.html('<strong>' + item.label + '</strong><br/>' + item.value.toLocaleString() + ' (' + percentage + '%)')
                    .style('left', (event.pageX + 10) + 'px')
                    .style('top', (event.pageY - 28) + 'px');
            })
            .on('mouseout', function() {
                d3.select(this).attr('opacity', 1);
                tooltip.transition().duration(500).style('opacity', 0);
            });
    }

    return svg;
};
SCRIPT;
    }

    /**
     * Generate generic chart script
     */
    private function generateGenericScript(ChartConfig $chart): string
    {
        return "console.log('Chart type " . esc_js($chart->type) . " rendering for ', config);";
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/visualization/chart-data', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetChartData'],
            'permission_callback' => function () {
                return current_user_can('read');
            },
            'args' => [
                'chart_id' => ['required' => true, 'type' => 'string'],
                'period' => ['type' => 'string', 'default' => 'last_30_days'],
                'form_id' => ['type' => 'integer']
            ]
        ]);

        register_rest_route('formflow/v1', '/visualization/export', [
            'methods' => 'POST',
            'callback' => [$this, 'restExportChart'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    /**
     * REST: Get chart data
     */
    public function restGetChartData(\WP_REST_Request $request): \WP_REST_Response
    {
        $chart_id = $request->get_param('chart_id');
        $period = $request->get_param('period');
        $form_id = $request->get_param('form_id');

        $data = $this->getChartData($chart_id, [
            'period' => $period,
            'form_id' => $form_id
        ]);

        return new \WP_REST_Response(['data' => $data]);
    }

    /**
     * Get chart data
     */
    public function getChartData(string $chart_id, array $params = []): array
    {
        $generator = ReportGenerator::getInstance();

        switch ($chart_id) {
            case 'submissions_timeline':
                $period = $generator->resolvePeriod(['period' => $params['period'] ?? 'last_30_days']);
                $data = $generator->getSubmissionsData($period, $params);
                return $data['timeline'] ?? [];

            case 'form_performance':
                $period = $generator->resolvePeriod(['period' => $params['period'] ?? 'last_30_days']);
                $data = $generator->getFormsData($period, $params);
                return $data['performance'] ?? [];

            case 'device_breakdown':
                $period = $generator->resolvePeriod(['period' => $params['period'] ?? 'last_30_days']);
                $data = $generator->getSubmissionsData($period, $params);
                return $data['devices'] ?? [];

            case 'conversion_funnel':
                $period = $generator->resolvePeriod(['period' => $params['period'] ?? 'last_30_days']);
                $data = $generator->getSubmissionsData($period, $params);
                return $data['funnel'] ?? [];

            default:
                return apply_filters('ffp_get_chart_data_' . $chart_id, [], $params);
        }
    }

    /**
     * REST: Export chart
     */
    public function restExportChart(\WP_REST_Request $request): \WP_REST_Response
    {
        $chart_id = $request->get_param('chart_id');
        $format = $request->get_param('format') ?? 'svg';

        // Generate export
        $export_data = $this->exportChart($chart_id, $format);

        return new \WP_REST_Response($export_data);
    }

    /**
     * Export chart
     */
    public function exportChart(string $chart_id, string $format = 'svg'): array
    {
        return [
            'format' => $format,
            'chart_id' => $chart_id,
            'timestamp' => current_time('c')
        ];
    }

    // ==================== AJAX Handlers ====================

    /**
     * AJAX: Get chart data
     */
    public function ajaxGetChartData(): void
    {
        check_ajax_referer('ffp_visualization', 'nonce');

        $chart_id = sanitize_key($_POST['chart_id'] ?? '');
        $params = [
            'period' => sanitize_key($_POST['period'] ?? 'last_30_days'),
            'form_id' => intval($_POST['form_id'] ?? 0)
        ];

        $data = $this->getChartData($chart_id, $params);

        wp_send_json_success(['data' => $data]);
    }

    /**
     * AJAX: Save dashboard layout
     */
    public function ajaxSaveDashboardLayout(): void
    {
        check_ajax_referer('ffp_visualization', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $layout = json_decode(stripslashes($_POST['layout'] ?? '[]'), true);

        update_option('ffp_dashboard_layout', $layout);

        wp_send_json_success();
    }

    /**
     * AJAX: Export chart
     */
    public function ajaxExportChart(): void
    {
        check_ajax_referer('ffp_visualization', 'nonce');

        $chart_id = sanitize_key($_POST['chart_id'] ?? '');
        $format = sanitize_key($_POST['format'] ?? 'svg');

        $export = $this->exportChart($chart_id, $format);

        wp_send_json_success($export);
    }

    /**
     * Create dashboard widget
     */
    public function createWidget(array $config): DashboardWidget
    {
        return new DashboardWidget($config);
    }

    /**
     * Render dashboard
     */
    public function renderDashboard(array $widgets, array $options = []): string
    {
        $columns = $options['columns'] ?? 12;

        ob_start();
        ?>
        <div class="ffp-dashboard" data-columns="<?php echo (int) $columns; ?>">
            <div class="ffp-dashboard-grid">
                <?php foreach ($widgets as $widget): ?>
                    <?php
                    if (is_array($widget)) {
                        $widget = $this->createWidget($widget);
                    }
                    ?>
                    <div class="ffp-dashboard-widget"
                         data-widget-id="<?php echo esc_attr($widget->id); ?>"
                         data-width="<?php echo (int) $widget->width; ?>"
                         style="grid-column: span <?php echo (int) $widget->width; ?>;">

                        <div class="ffp-widget-header">
                            <h3 class="ffp-widget-title"><?php echo esc_html($widget->title); ?></h3>
                            <div class="ffp-widget-actions">
                                <?php if ($widget->refresh['enabled']): ?>
                                <button type="button" class="ffp-widget-refresh" title="<?php esc_attr_e('Refresh', 'form-flow-pro'); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="ffp-widget-settings" title="<?php esc_attr_e('Settings', 'form-flow-pro'); ?>">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                </button>
                            </div>
                        </div>

                        <div class="ffp-widget-content" style="height: <?php echo (int) $widget->height; ?>px;">
                            <?php if ($widget->type === 'chart'): ?>
                                <?php
                                $chart = $this->createChart($widget->chart_config);
                                echo $this->render($chart);
                                ?>
                            <?php elseif ($widget->type === 'kpi'): ?>
                                <div class="ffp-kpi-widget">
                                    <?php echo $this->renderKPIWidget($widget); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .ffp-dashboard {
                padding: 20px;
            }
            .ffp-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(12, 1fr);
                gap: 20px;
            }
            .ffp-dashboard-widget {
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .ffp-widget-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 20px;
                border-bottom: 1px solid #dcdcde;
            }
            .ffp-widget-title {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
            }
            .ffp-widget-actions {
                display: flex;
                gap: 8px;
            }
            .ffp-widget-actions button {
                background: none;
                border: none;
                cursor: pointer;
                color: #646970;
                padding: 4px;
            }
            .ffp-widget-actions button:hover {
                color: #2271b1;
            }
            .ffp-widget-content {
                padding: 16px 20px;
            }
        </style>
        <?php

        return ob_get_clean();
    }

    /**
     * Render KPI widget
     */
    private function renderKPIWidget(DashboardWidget $widget): string
    {
        $data = $widget->chart_config['data'] ?? [];

        ob_start();
        ?>
        <div class="ffp-kpi-grid">
            <?php foreach ($data as $kpi): ?>
            <div class="ffp-kpi-item">
                <div class="ffp-kpi-value"><?php echo esc_html($kpi['value'] ?? 0); ?></div>
                <div class="ffp-kpi-label"><?php echo esc_html($kpi['label'] ?? ''); ?></div>
                <?php if (isset($kpi['trend'])): ?>
                <div class="ffp-kpi-trend ffp-trend-<?php echo esc_attr($kpi['trend']); ?>">
                    <?php echo $kpi['trend'] === 'up' ? '&#9650;' : ($kpi['trend'] === 'down' ? '&#9660;' : '-'); ?>
                    <?php echo esc_html($kpi['change'] ?? '0'); ?>%
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
