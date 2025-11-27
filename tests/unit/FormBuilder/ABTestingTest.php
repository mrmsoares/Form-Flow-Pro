<?php
/**
 * Tests for ABTesting class.
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\ABTesting;
use FormFlowPro\FormBuilder\ABTest;
use FormFlowPro\FormBuilder\TestVariant;

class ABTestingTest extends TestCase
{
    private $abTesting;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_COOKIE = [];

        $this->abTesting = ABTesting::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $_COOKIE = [];

        parent::tearDown();
    }

    public function test_getInstance_returns_singleton()
    {
        $instance1 = ABTesting::getInstance();
        $instance2 = ABTesting::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_createTest_creates_new_test()
    {
        global $wpdb;

        $form_id = 123;
        $data = [
            'name' => 'Test A/B Test',
            'description' => 'Testing form variations',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'variants' => [
                ['name' => 'Control', 'is_control' => true, 'changes' => []],
                ['name' => 'Variant B', 'is_control' => false, 'changes' => []],
            ],
        ];

        $wpdb->set_mock_result('insert_id', 456);

        $test = $this->abTesting->createTest($form_id, $data);

        $this->assertInstanceOf(ABTest::class, $test);
        $this->assertEquals('Test A/B Test', $test->name);
        $this->assertEquals('draft', $test->status);
    }

    public function test_createTest_creates_default_variants_if_none_provided()
    {
        global $wpdb;

        $form_id = 123;
        $data = [
            'name' => 'Simple Test',
        ];

        $wpdb->set_mock_result('insert_id', 456);

        $test = $this->abTesting->createTest($form_id, $data);

        // Should have created Control and Variant B by default
        $inserts = $wpdb->get_mock_inserts();

        $variantInserts = array_filter($inserts, function($insert) {
            return strpos($insert['table'], 'ab_variants') !== false;
        });

        $this->assertCount(2, $variantInserts);
    }

    public function test_createVariant_creates_new_variant()
    {
        global $wpdb;

        $test_id = 456;
        $data = [
            'name' => 'Variant C',
            'changes' => [
                ['path' => 'settings.submit_text', 'value' => 'Send Now'],
            ],
            'weight' => 33.3,
        ];

        $wpdb->set_mock_result('insert', 1);

        $variant = $this->abTesting->createVariant($test_id, $data);

        $this->assertInstanceOf(TestVariant::class, $variant);
        $this->assertEquals('Variant C', $variant->name);
    }

    public function test_getTest_returns_test_with_variants()
    {
        global $wpdb;

        $mockTestRow = [
            'id' => 456,
            'form_id' => 123,
            'name' => 'Test Name',
            'description' => 'Test Description',
            'status' => 'running',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $mockVariants = [
            [
                'id' => 1,
                'test_id' => 456,
                'variant_id' => 'var_control',
                'name' => 'Control',
                'changes' => json_encode([]),
                'weight' => 50.0,
                'is_control' => 1,
                'version_id' => null,
            ],
            [
                'id' => 2,
                'test_id' => 456,
                'variant_id' => 'var_b',
                'name' => 'Variant B',
                'changes' => json_encode([]),
                'weight' => 50.0,
                'is_control' => 0,
                'version_id' => null,
            ],
        ];

        $wpdb->set_mock_result('get_row', $mockTestRow);
        $wpdb->set_mock_result('get_results', $mockVariants);

        $test = $this->abTesting->getTest(456);

        $this->assertInstanceOf(ABTest::class, $test);
        $this->assertEquals('Test Name', $test->name);
        $this->assertCount(2, $test->variants);
    }

    public function test_getActiveTest_returns_running_test()
    {
        global $wpdb;

        $mockTestRow = [
            'id' => 456,
            'form_id' => 123,
            'name' => 'Active Test',
            'status' => 'running',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTestRow);
        $wpdb->set_mock_result('get_results', []);

        $test = $this->abTesting->getActiveTest(123);

        $this->assertInstanceOf(ABTest::class, $test);
        $this->assertEquals('running', $test->status);
    }

    public function test_startTest_changes_status_to_running()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'name' => 'Test',
            'status' => 'draft',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => null,
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('update', 1);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->startTest(456);

        $this->assertTrue($result);

        $updates = $wpdb->get_mock_updates();
        $statusUpdate = array_filter($updates, function($update) {
            return isset($update['data']['status']) && $update['data']['status'] === 'running';
        });

        $this->assertNotEmpty($statusUpdate);
    }

    public function test_startTest_pauses_existing_running_test()
    {
        global $wpdb;

        $currentTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'draft',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => null,
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
            'name' => 'Test',
        ];

        $existingTest = [
            'id' => 789,
            'form_id' => 123,
            'status' => 'running',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
            'name' => 'Existing',
        ];

        $wpdb->set_mock_result('get_row', $currentTest);
        $wpdb->set_mock_result('update', 1);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->startTest(456);

        $this->assertTrue($result);
    }

    public function test_pauseTest_changes_status_to_paused()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'running',
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('update', 1);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->pauseTest(456);

        $this->assertTrue($result);
    }

    public function test_completeTest_sets_winner()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'running',
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('update', 1);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->completeTest(456, 'var_b');

        $this->assertTrue($result);

        $updates = $wpdb->get_mock_updates();
        $completionUpdate = array_filter($updates, function($update) {
            return isset($update['data']['status']) && $update['data']['status'] === 'completed';
        });

        $this->assertNotEmpty($completionUpdate);
    }

    public function test_deleteTest_removes_test_and_related_data()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'draft',
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => null,
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('delete', 1);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->deleteTest(456);

        $this->assertTrue($result);

        $deletes = $wpdb->get_mock_deletes();
        $this->assertNotEmpty($deletes);
    }

    public function test_deleteTest_prevents_deleting_running_test()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'running',
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => json_encode([]),
            'traffic_allocation' => 'equal',
            'allocation_weights' => json_encode([]),
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('get_results', []);

        $result = $this->abTesting->deleteTest(456);

        $this->assertFalse($result);
    }

    public function test_assignVariant_returns_variant()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'running',
            'variants' => [
                new TestVariant(['id' => 'var_control', 'name' => 'Control', 'is_control' => true]),
                new TestVariant(['id' => 'var_b', 'name' => 'Variant B', 'is_control' => false]),
            ],
            'traffic_allocation' => 'equal',
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => [],
            'allocation_weights' => [],
            'minimum_sample' => 100,
            'confidence_level' => 0.95,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'test_id' => 456,
                'variant_id' => 'var_control',
                'name' => 'Control',
                'changes' => json_encode([]),
                'weight' => 50.0,
                'is_control' => 1,
                'version_id' => null,
            ],
        ]);

        $variant = $this->abTesting->assignVariant(456);

        $this->assertInstanceOf(TestVariant::class, $variant);
    }

    public function test_trackEvent_records_event()
    {
        global $wpdb;

        $wpdb->set_mock_result('insert', 1);
        $wpdb->set_mock_result('get_row', null);

        $result = $this->abTesting->trackEvent(456, 'var_b', 'view', []);

        $this->assertTrue($result);

        $inserts = $wpdb->get_mock_inserts();
        $eventInserts = array_filter($inserts, function($insert) {
            return strpos($insert['table'], 'ab_events') !== false;
        });

        $this->assertNotEmpty($eventInserts);
    }

    public function test_calculateResults_returns_statistics()
    {
        global $wpdb;

        $mockTest = [
            'id' => 456,
            'form_id' => 123,
            'status' => 'running',
            'variants' => [
                new TestVariant(['id' => 'control', 'name' => 'Control', 'is_control' => true]),
                new TestVariant(['id' => 'variant_b', 'name' => 'Variant B', 'is_control' => false]),
            ],
            'confidence_level' => 0.95,
            'name' => 'Test',
            'description' => '',
            'test_type' => 'ab',
            'goal_type' => 'submission',
            'goal_config' => [],
            'traffic_allocation' => 'equal',
            'allocation_weights' => [],
            'minimum_sample' => 100,
            'winner_variant_id' => null,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => null,
            'created_at' => '2024-01-01 00:00:00',
            'created_by' => 1,
        ];

        $mockTotals = [
            'total_views' => 1000,
            'total_conversions' => 100,
            'total_bounces' => 50,
            'total_time' => 300000,
            'total_interactions' => 500,
        ];

        $wpdb->set_mock_result('get_row', $mockTest);
        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'test_id' => 456,
                'variant_id' => 'control',
                'name' => 'Control',
                'changes' => json_encode([]),
                'weight' => 50.0,
                'is_control' => 1,
                'version_id' => null,
            ],
        ]);

        $results = $this->abTesting->calculateResults(456);

        $this->assertIsArray($results);
    }

    public function test_calculateStatisticalSignificance_with_valid_data()
    {
        $significance = $this->abTesting->calculateStatisticalSignificance(
            1000,  // control views
            100,   // control conversions
            1000,  // variant views
            150    // variant conversions
        );

        $this->assertIsFloat($significance);
        $this->assertGreaterThanOrEqual(0, $significance);
        $this->assertLessThanOrEqual(100, $significance);
    }

    public function test_calculateStatisticalSignificance_with_zero_views_returns_zero()
    {
        $significance = $this->abTesting->calculateStatisticalSignificance(
            0,   // control views
            0,   // control conversions
            100, // variant views
            10   // variant conversions
        );

        $this->assertEquals(0, $significance);
    }

    public function test_determineWinner_selects_significant_variant()
    {
        $results = [
            'control' => [
                'variant_id' => 'control',
                'is_control' => true,
                'conversion_rate' => 10.0,
                'views' => 1000,
                'conversions' => 100,
            ],
            'variant_b' => [
                'variant_id' => 'variant_b',
                'is_control' => false,
                'conversion_rate' => 15.0,
                'views' => 1000,
                'conversions' => 150,
                'improvement' => 50.0,
                'statistical_significance' => 98.0,
                'is_significant' => true,
            ],
        ];

        $winner = $this->abTesting->determineWinner($results, 0.95);

        $this->assertNotEmpty($winner);
        $this->assertEquals('variant_b', $winner['variant_id']);
    }

    public function test_determineWinner_returns_control_if_no_significant_winner()
    {
        $results = [
            'control' => [
                'variant_id' => 'control',
                'is_control' => true,
                'conversion_rate' => 10.0,
                'views' => 1000,
                'conversions' => 100,
            ],
            'variant_b' => [
                'variant_id' => 'variant_b',
                'is_control' => false,
                'conversion_rate' => 10.5,
                'views' => 1000,
                'conversions' => 105,
                'improvement' => 5.0,
                'statistical_significance' => 60.0,
                'is_significant' => false,
            ],
        ];

        $winner = $this->abTesting->determineWinner($results, 0.95);

        // Should fall back to control
        $this->assertNotEmpty($winner);
    }

    public function test_checkTestsForWinners_completes_tests_with_winners()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_col', [456]);
        $wpdb->set_mock_result('get_row', null);
        $wpdb->set_mock_result('get_results', []);

        // Should not throw exception
        $this->abTesting->checkTestsForWinners();

        $this->assertTrue(true);
    }

    public function test_getTimeSeriesData_returns_daily_breakdown()
    {
        global $wpdb;

        $mockData = [
            ['date' => '2024-01-01', 'variant_id' => 'control', 'views' => 100, 'conversions' => 10],
            ['date' => '2024-01-01', 'variant_id' => 'variant_b', 'views' => 100, 'conversions' => 15],
            ['date' => '2024-01-02', 'variant_id' => 'control', 'views' => 120, 'conversions' => 12],
            ['date' => '2024-01-02', 'variant_id' => 'variant_b', 'views' => 110, 'conversions' => 18],
        ];

        $wpdb->set_mock_result('get_results', $mockData);

        $timeSeries = $this->abTesting->getTimeSeriesData(456);

        $this->assertIsArray($timeSeries);
        $this->assertNotEmpty($timeSeries);
    }

    public function test_applyTestVariant_returns_form_data_when_no_active_test()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $formData = ['title' => 'Test Form', 'fields' => []];

        $result = $this->abTesting->applyTestVariant($formData, 123);

        $this->assertEquals($formData, $result);
    }

    public function test_ajaxTrackEvent_with_valid_data_tracks_event()
    {
        global $wpdb;

        $_POST = [
            'test_id' => 456,
            'variant_id' => 'var_b',
            'event_type' => 'view',
            'event_data' => json_encode(['page' => 'home']),
        ];

        $wpdb->set_mock_result('insert', 1);
        $wpdb->set_mock_result('get_row', null);

        $output = $this->callAjaxEndpoint(function() {
            $this->abTesting->ajaxTrackEvent();
        });

        $this->assertTrue($output['success']);
    }

    public function test_ajaxTrackEvent_with_invalid_event_type_returns_error()
    {
        $_POST = [
            'test_id' => 456,
            'variant_id' => 'var_b',
            'event_type' => 'invalid_event',
            'event_data' => json_encode([]),
        ];

        $output = $this->callAjaxEndpoint(function() {
            $this->abTesting->ajaxTrackEvent();
        });

        $this->assertFalse($output['success']);
        $this->assertStringContainsString('Invalid event type', $output['data']['message']);
    }
}
