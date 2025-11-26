<?php
/**
 * Tests for D3Visualization class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\D3Visualization;

class D3VisualizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton
        $reflection = new \ReflectionClass(D3Visualization::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(D3Visualization::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        parent::tearDown();
    }

    public function test_singleton_instance()
    {
        $instance1 = D3Visualization::getInstance();
        $instance2 = D3Visualization::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(D3Visualization::class, $instance1);
    }

    public function test_get_chart_types_returns_array()
    {
        $visualization = D3Visualization::getInstance();
        $types = $visualization->getChartTypes();

        $this->assertIsArray($types);
    }

    public function test_get_chart_type_returns_null_for_nonexistent()
    {
        $visualization = D3Visualization::getInstance();
        $type = $visualization->getChartType('nonexistent_type');

        $this->assertNull($type);
    }

    public function test_register_chart_type()
    {
        $visualization = D3Visualization::getInstance();

        $visualization->registerChartType('custom_chart', [
            'name' => 'Custom Chart',
            'description' => 'A custom chart type',
            'renderer' => 'customRenderer',
        ]);

        $type = $visualization->getChartType('custom_chart');

        $this->assertNotNull($type);
        $this->assertEquals('custom_chart', $type['id']);
        $this->assertEquals('Custom Chart', $type['name']);
    }

    public function test_get_color_schemes_returns_array()
    {
        $visualization = D3Visualization::getInstance();
        $schemes = $visualization->getColorSchemes();

        $this->assertIsArray($schemes);
    }

    public function test_get_color_scheme_returns_array()
    {
        $visualization = D3Visualization::getInstance();

        // Register a scheme first
        $visualization->registerColorScheme('test_scheme', [
            'name' => 'Test Scheme',
            'colors' => ['#ff0000', '#00ff00', '#0000ff'],
        ]);

        $scheme = $visualization->getColorScheme('test_scheme');

        $this->assertIsArray($scheme);
        $this->assertEquals('Test Scheme', $scheme['name']);
    }

    public function test_register_color_scheme()
    {
        $visualization = D3Visualization::getInstance();

        $colors = ['#1a1a1a', '#2a2a2a', '#3a3a3a', '#4a4a4a'];

        $visualization->registerColorScheme('dark_scheme', [
            'name' => 'Dark Scheme',
            'colors' => $colors,
        ]);

        $schemes = $visualization->getColorSchemes();

        $this->assertArrayHasKey('dark_scheme', $schemes);
        $this->assertEquals('Dark Scheme', $schemes['dark_scheme']['name']);
    }

    public function test_create_chart_returns_chart_config()
    {
        $visualization = D3Visualization::getInstance();

        // Register a chart type first
        $visualization->registerChartType('line', [
            'name' => 'Line Chart',
            'renderer' => 'lineRenderer',
        ]);

        $chart = $visualization->createChart([
            'id' => 'test-chart',
            'type' => 'line',
            'data' => [
                ['x' => 1, 'y' => 10],
                ['x' => 2, 'y' => 20],
            ],
        ]);

        $this->assertIsObject($chart);
    }

    public function test_get_chart_data_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)['date' => '2024-01-01', 'count' => 10],
            (object)['date' => '2024-01-02', 'count' => 15],
        ]);

        $visualization = D3Visualization::getInstance();

        $data = $visualization->getChartData('submissions', [
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-31',
        ]);

        $this->assertIsArray($data);
    }

    public function test_export_chart_returns_array()
    {
        $visualization = D3Visualization::getInstance();

        $result = $visualization->exportChart('test-chart', 'svg');

        $this->assertIsArray($result);
    }

    public function test_export_chart_png_format()
    {
        $visualization = D3Visualization::getInstance();

        $result = $visualization->exportChart('test-chart', 'png');

        $this->assertIsArray($result);
    }

    public function test_register_multiple_chart_types()
    {
        $visualization = D3Visualization::getInstance();

        $visualization->registerChartType('type_a', ['name' => 'Type A']);
        $visualization->registerChartType('type_b', ['name' => 'Type B']);
        $visualization->registerChartType('type_c', ['name' => 'Type C']);

        $types = $visualization->getChartTypes();

        $this->assertArrayHasKey('type_a', $types);
        $this->assertArrayHasKey('type_b', $types);
        $this->assertArrayHasKey('type_c', $types);
    }

    public function test_chart_type_default_values()
    {
        $visualization = D3Visualization::getInstance();

        $visualization->registerChartType('minimal_type', [
            'name' => 'Minimal',
        ]);

        $type = $visualization->getChartType('minimal_type');

        $this->assertEquals('minimal_type', $type['id']);
        $this->assertEquals('Minimal', $type['name']);
    }

    public function test_color_scheme_structure()
    {
        $visualization = D3Visualization::getInstance();

        $visualization->registerColorScheme('structured', [
            'name' => 'Structured',
            'colors' => ['#111', '#222', '#333'],
            'category' => 'custom',
        ]);

        $scheme = $visualization->getColorScheme('structured');

        $this->assertArrayHasKey('name', $scheme);
        $this->assertArrayHasKey('colors', $scheme);
        $this->assertIsArray($scheme['colors']);
    }
}
