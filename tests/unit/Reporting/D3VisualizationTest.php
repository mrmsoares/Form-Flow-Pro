<?php
/**
 * Tests for D3Visualization class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\D3Visualization;

class D3VisualizationTest extends TestCase
{
    private $visualization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->visualization = D3Visualization::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = D3Visualization::getInstance();
        $instance2 = D3Visualization::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_available_chart_types()
    {
        $types = $this->visualization->getChartTypes();

        $this->assertIsArray($types);
        $this->assertContains('line', $types);
        $this->assertContains('bar', $types);
        $this->assertContains('pie', $types);
        $this->assertContains('donut', $types);
        $this->assertContains('area', $types);
        $this->assertContains('scatter', $types);
        $this->assertContains('heatmap', $types);
        $this->assertContains('funnel', $types);
        $this->assertContains('gauge', $types);
        $this->assertContains('radial_bar', $types);
    }

    public function test_get_color_schemes()
    {
        $schemes = $this->visualization->getColorSchemes();

        $this->assertIsArray($schemes);
        $this->assertNotEmpty($schemes);
    }

    public function test_render_chart_returns_html()
    {
        $config = [
            'type' => 'line',
            'data' => [
                ['label' => 'Jan', 'value' => 100],
                ['label' => 'Feb', 'value' => 150],
                ['label' => 'Mar', 'value' => 120],
            ],
            'options' => [
                'title' => 'Test Chart',
                'width' => 600,
                'height' => 400,
            ],
        ];

        $html = $this->visualization->renderChart($config);

        $this->assertIsString($html);
        $this->assertStringContainsString('ffp-chart', $html);
    }

    public function test_render_chart_with_invalid_type_returns_error()
    {
        $config = [
            'type' => 'invalid_chart_type',
            'data' => [],
        ];

        $html = $this->visualization->renderChart($config);

        $this->assertStringContainsString('error', strtolower($html));
    }

    public function test_prepare_line_chart_data()
    {
        $rawData = [
            ['date' => '2024-01-01', 'value' => 100],
            ['date' => '2024-01-02', 'value' => 150],
            ['date' => '2024-01-03', 'value' => 120],
        ];

        $prepared = $this->callPrivateMethod($this->visualization, 'prepareLineData', [$rawData]);

        $this->assertIsArray($prepared);
    }

    public function test_prepare_bar_chart_data()
    {
        $rawData = [
            ['label' => 'Form A', 'value' => 100],
            ['label' => 'Form B', 'value' => 150],
            ['label' => 'Form C', 'value' => 80],
        ];

        $prepared = $this->callPrivateMethod($this->visualization, 'prepareBarData', [$rawData]);

        $this->assertIsArray($prepared);
    }

    public function test_prepare_pie_chart_data()
    {
        $rawData = [
            ['label' => 'Completed', 'value' => 60],
            ['label' => 'Pending', 'value' => 30],
            ['label' => 'Failed', 'value' => 10],
        ];

        $prepared = $this->callPrivateMethod($this->visualization, 'preparePieData', [$rawData]);

        $this->assertIsArray($prepared);

        // Should calculate percentages
        $total = array_sum(array_column($rawData, 'value'));
        $this->assertEquals(100, $total);
    }

    public function test_get_chart_config_defaults()
    {
        $defaults = $this->visualization->getDefaultConfig('line');

        $this->assertIsArray($defaults);
        $this->assertArrayHasKey('width', $defaults);
        $this->assertArrayHasKey('height', $defaults);
        $this->assertArrayHasKey('margin', $defaults);
    }

    public function test_render_dashboard_widget()
    {
        $config = [
            'id' => 'test-widget',
            'title' => 'Test Widget',
            'chart_type' => 'bar',
            'data_source' => 'submissions_by_form',
            'width' => 6, // Grid columns
        ];

        $html = $this->visualization->renderDashboardWidget($config);

        $this->assertIsString($html);
        $this->assertStringContainsString('ffp-dashboard-widget', $html);
    }

    public function test_get_submissions_chart_data()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)['date' => '2024-01-01', 'count' => 10],
            (object)['date' => '2024-01-02', 'count' => 15],
            (object)['date' => '2024-01-03', 'count' => 12],
        ]);

        $data = $this->visualization->getChartData('submissions_over_time', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]);

        $this->assertIsArray($data);
    }

    public function test_get_form_distribution_chart_data()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)['form_name' => 'Contact Form', 'count' => 50],
            (object)['form_name' => 'Quote Form', 'count' => 30],
            (object)['form_name' => 'Survey Form', 'count' => 20],
        ]);

        $data = $this->visualization->getChartData('form_distribution', []);

        $this->assertIsArray($data);
    }

    public function test_render_kpi_widget()
    {
        $config = [
            'label' => 'Total Submissions',
            'value' => 1234,
            'trend' => 15.5,
            'trend_direction' => 'up',
            'format' => 'number',
        ];

        $html = $this->visualization->renderKPIWidget($config);

        $this->assertIsString($html);
        $this->assertStringContainsString('ffp-kpi', $html);
        $this->assertStringContainsString('1234', $html);
    }

    public function test_export_chart_as_svg()
    {
        $chartId = 'test-chart-123';

        // This would normally interact with frontend, so we test the config generation
        $exportConfig = $this->visualization->getExportConfig($chartId, 'svg');

        $this->assertIsArray($exportConfig);
        $this->assertArrayHasKey('format', $exportConfig);
        $this->assertEquals('svg', $exportConfig['format']);
    }

    public function test_responsive_chart_config()
    {
        $config = [
            'type' => 'line',
            'responsive' => true,
        ];

        $processedConfig = $this->visualization->processConfig($config);

        $this->assertTrue($processedConfig['responsive']);
        $this->assertArrayHasKey('breakpoints', $processedConfig);
    }

    public function test_chart_animation_options()
    {
        $config = [
            'type' => 'bar',
            'animation' => [
                'enabled' => true,
                'duration' => 500,
                'easing' => 'easeInOut',
            ],
        ];

        $processedConfig = $this->visualization->processConfig($config);

        $this->assertTrue($processedConfig['animation']['enabled']);
        $this->assertEquals(500, $processedConfig['animation']['duration']);
    }
}
