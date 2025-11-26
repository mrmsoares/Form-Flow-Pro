<?php
/**
 * Tests for ReportGenerator class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\ReportGenerator;

class ReportGeneratorTest extends TestCase
{
    private $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = ReportGenerator::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = ReportGenerator::getInstance();
        $instance2 = ReportGenerator::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_available_report_types()
    {
        $types = $this->generator->getAvailableReportTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('executive_summary', $types);
        $this->assertArrayHasKey('detailed_analytics', $types);
        $this->assertArrayHasKey('form_performance', $types);
        $this->assertArrayHasKey('submission_export', $types);
        $this->assertArrayHasKey('signature_status', $types);
    }

    public function test_get_supported_formats()
    {
        $formats = $this->generator->getSupportedFormats();

        $this->assertIsArray($formats);
        $this->assertContains('pdf', $formats);
        $this->assertContains('excel', $formats);
        $this->assertContains('csv', $formats);
        $this->assertContains('json', $formats);
        $this->assertContains('html', $formats);
    }

    public function test_generate_report_returns_array()
    {
        global $wpdb;

        // Mock submissions data
        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'form_id' => 'form-1',
                'status' => 'completed',
                'created_at' => '2024-01-15 10:00:00',
            ],
            (object)[
                'id' => '2',
                'form_id' => 'form-1',
                'status' => 'pending',
                'created_at' => '2024-01-16 11:00:00',
            ],
        ]);

        $config = [
            'type' => 'executive_summary',
            'date_range' => 'last_30_days',
            'format' => 'json',
        ];

        $result = $this->generator->generateReport($config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_generate_report_with_invalid_type_returns_error()
    {
        $config = [
            'type' => 'invalid_report_type',
            'date_range' => 'last_30_days',
            'format' => 'pdf',
        ];

        $result = $this->generator->generateReport($config);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_calculate_date_range_last_7_days()
    {
        $range = $this->callPrivateMethod($this->generator, 'calculateDateRange', ['last_7_days']);

        $this->assertIsArray($range);
        $this->assertArrayHasKey('start', $range);
        $this->assertArrayHasKey('end', $range);

        $start = new \DateTime($range['start']);
        $end = new \DateTime($range['end']);
        $diff = $start->diff($end);

        $this->assertEquals(7, $diff->days);
    }

    public function test_calculate_date_range_last_30_days()
    {
        $range = $this->callPrivateMethod($this->generator, 'calculateDateRange', ['last_30_days']);

        $start = new \DateTime($range['start']);
        $end = new \DateTime($range['end']);
        $diff = $start->diff($end);

        $this->assertEquals(30, $diff->days);
    }

    public function test_calculate_date_range_custom()
    {
        $range = $this->callPrivateMethod($this->generator, 'calculateDateRange', [
            'custom',
            '2024-01-01',
            '2024-01-31'
        ]);

        $this->assertEquals('2024-01-01', $range['start']);
        $this->assertEquals('2024-01-31', $range['end']);
    }

    public function test_get_kpi_data_returns_array()
    {
        global $wpdb;

        // Mock count queries
        $wpdb->set_mock_result('get_var', 100);

        $kpiData = $this->callPrivateMethod($this->generator, 'getKPIData', [
            '2024-01-01',
            '2024-01-31'
        ]);

        $this->assertIsArray($kpiData);
    }

    public function test_format_currency()
    {
        $formatted = $this->callPrivateMethod($this->generator, 'formatCurrency', [1234.56]);

        $this->assertIsString($formatted);
        $this->assertStringContainsString('1', $formatted);
    }

    public function test_format_percentage()
    {
        $formatted = $this->callPrivateMethod($this->generator, 'formatPercentage', [0.75]);

        $this->assertIsString($formatted);
        $this->assertStringContainsString('75', $formatted);
    }

    public function test_get_report_history()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Test Report',
                'report_type' => 'executive_summary',
                'format' => 'pdf',
                'file_path' => '/path/to/report.pdf',
                'file_size' => 1024,
                'created_at' => '2024-01-15 10:00:00',
            ],
        ]);

        $history = $this->generator->getReportHistory(1, 10);

        $this->assertIsArray($history);
        $this->assertArrayHasKey('reports', $history);
        $this->assertArrayHasKey('total', $history);
    }

    public function test_delete_report()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'file_path' => '/tmp/test-report.pdf',
        ]);

        $result = $this->generator->deleteReport('1');

        // Should return true or handle gracefully
        $this->assertIsBool($result);
    }

    public function test_validate_report_config()
    {
        $validConfig = [
            'type' => 'executive_summary',
            'date_range' => 'last_30_days',
            'format' => 'pdf',
        ];

        $isValid = $this->callPrivateMethod($this->generator, 'validateConfig', [$validConfig]);

        $this->assertTrue($isValid);
    }

    public function test_validate_report_config_missing_type()
    {
        $invalidConfig = [
            'date_range' => 'last_30_days',
            'format' => 'pdf',
        ];

        $isValid = $this->callPrivateMethod($this->generator, 'validateConfig', [$invalidConfig]);

        $this->assertFalse($isValid);
    }
}
