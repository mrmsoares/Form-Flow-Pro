<?php
/**
 * Tests for ABTesting class.
 *
 * @package FormFlowPro\Tests\Unit\FormBuilder
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\ABTesting;
use FormFlowPro\FormBuilder\ABTest;
use FormFlowPro\FormBuilder\TestVariant;

class ABTestingTest extends TestCase
{
    private ?ABTesting $abTesting = null;
    private bool $databaseAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Try to get instance and check if database is available
        try {
            $this->abTesting = ABTesting::getInstance();

            // Check if tables are initialized by trying to access a property
            $reflection = new \ReflectionClass($this->abTesting);
            $property = $reflection->getProperty('tests_table');
            $property->setAccessible(true);

            if ($property->isInitialized($this->abTesting)) {
                $this->databaseAvailable = true;
            }
        } catch (\Throwable $e) {
            $this->databaseAvailable = false;
        }
    }

    /**
     * Skip test if database is not available
     */
    private function requireDatabase(): void
    {
        if (!$this->databaseAvailable) {
            $this->markTestSkipped('Database not available for this test');
        }
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = ABTesting::getInstance();
        $instance2 = ABTesting::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== ABTest Model Tests ====================

    public function testABTestModelConstruction(): void
    {
        $data = [
            'id' => 1,
            'form_id' => 123,
            'name' => 'Test AB',
            'description' => 'Test description',
            'status' => 'draft',
            'test_type' => 'ab',
            'goal_type' => 'submission',
        ];

        $test = new ABTest($data);

        $this->assertEquals(1, $test->id);
        $this->assertEquals(123, $test->form_id);
        $this->assertEquals('Test AB', $test->name);
        $this->assertEquals('draft', $test->status);
        $this->assertEquals('ab', $test->test_type);
    }

    public function testABTestToArray(): void
    {
        $test = new ABTest([
            'id' => 1,
            'name' => 'Test',
            'status' => 'running',
        ]);

        $array = $test->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('Test', $array['name']);
    }

    // ==================== TestVariant Model Tests ====================

    public function testTestVariantConstruction(): void
    {
        $variant = new TestVariant([
            'name' => 'Control',
            'is_control' => true,
            'weight' => 50.0,
        ]);

        $this->assertEquals('Control', $variant->name);
        $this->assertTrue($variant->is_control);
        $this->assertEquals(50.0, $variant->weight);
        $this->assertStringStartsWith('var_', $variant->id);
    }

    public function testTestVariantDefaultValues(): void
    {
        $variant = new TestVariant([]);

        $this->assertEquals('Variant', $variant->name);
        $this->assertEquals([], $variant->changes);
        $this->assertEquals(0, $variant->views);
        $this->assertEquals(0, $variant->conversions);
        $this->assertEquals(0.0, $variant->conversion_rate);
        $this->assertEquals(50.0, $variant->weight);
        $this->assertFalse($variant->is_control);
    }

    public function testTestVariantToArray(): void
    {
        $variant = new TestVariant(['name' => 'Variant A']);

        $array = $variant->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Variant A', $array['name']);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('views', $array);
        $this->assertArrayHasKey('conversions', $array);
    }

    // ==================== Test Creation Tests ====================

    public function testCreateTestReturnsABTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, [
            'name' => 'My A/B Test',
            'description' => 'Testing form variations',
            'goal_type' => 'submission',
        ]);

        $this->assertInstanceOf(ABTest::class, $test);
        $this->assertEquals('My A/B Test', $test->name);
        $this->assertEquals('draft', $test->status);
    }

    public function testCreateTestWithDefaultVariants(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, [
            'name' => 'Default Variants Test',
        ]);

        $this->assertInstanceOf(ABTest::class, $test);
        $this->assertCount(2, $test->variants);

        $controlFound = false;
        foreach ($test->variants as $variant) {
            if ($variant->is_control) {
                $controlFound = true;
            }
        }
        $this->assertTrue($controlFound);
    }

    public function testCreateTestWithCustomVariants(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, [
            'name' => 'Custom Variants Test',
            'variants' => [
                ['name' => 'Control', 'is_control' => true, 'weight' => 33.3],
                ['name' => 'Variant B', 'is_control' => false, 'weight' => 33.3],
                ['name' => 'Variant C', 'is_control' => false, 'weight' => 33.4],
            ],
        ]);

        $this->assertCount(3, $test->variants);
    }

    public function testCreateTestWithAllOptions(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, [
            'name' => 'Full Options Test',
            'description' => 'Testing all options',
            'test_type' => 'multivariate',
            'goal_type' => 'conversion',
            'goal_config' => ['target' => 'button_click'],
            'traffic_allocation' => 'weighted',
            'minimum_sample' => 500,
            'confidence_level' => 0.99,
            'auto_end_on_winner' => true,
        ]);

        $this->assertEquals('multivariate', $test->test_type);
        $this->assertEquals('conversion', $test->goal_type);
        $this->assertEquals('weighted', $test->traffic_allocation);
        $this->assertEquals(500, $test->minimum_sample);
        $this->assertEquals(0.99, $test->confidence_level);
    }

    // ==================== Test Retrieval Tests ====================

    public function testGetTest(): void
    {
        $this->requireDatabase();

        $created = $this->abTesting->createTest(1, ['name' => 'Retrieve Test']);
        $retrieved = $this->abTesting->getTest($created->id);

        $this->assertInstanceOf(ABTest::class, $retrieved);
        $this->assertEquals($created->id, $retrieved->id);
        $this->assertEquals('Retrieve Test', $retrieved->name);
    }

    public function testGetTestReturnsNullForInvalidId(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->getTest(999999);
        $this->assertNull($test);
    }

    public function testGetTests(): void
    {
        $this->requireDatabase();

        $this->abTesting->createTest(2, ['name' => 'Test 1']);
        $this->abTesting->createTest(2, ['name' => 'Test 2']);

        $tests = $this->abTesting->getTests(2);

        $this->assertIsArray($tests);
        $this->assertGreaterThanOrEqual(2, count($tests));
    }

    public function testGetTestsWithStatusFilter(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(3, ['name' => 'Status Test']);

        $draftTests = $this->abTesting->getTests(3, ['status' => 'draft']);

        $this->assertIsArray($draftTests);
    }

    public function testGetActiveTestReturnsNullWhenNoRunning(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->getActiveTest(999);
        $this->assertNull($test);
    }

    // ==================== Test Lifecycle Tests ====================

    public function testStartTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(4, ['name' => 'Start Test']);

        $result = $this->abTesting->startTest($test->id);

        $this->assertTrue($result);

        $updated = $this->abTesting->getTest($test->id);
        $this->assertEquals('running', $updated->status);
        $this->assertNotEmpty($updated->start_date);
    }

    public function testStartTestFailsForNonDraft(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(4, ['name' => 'Non-Draft Test']);
        $this->abTesting->startTest($test->id);

        // Try to start again
        $result = $this->abTesting->startTest($test->id);
        $this->assertFalse($result);
    }

    public function testPauseTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(5, ['name' => 'Pause Test']);
        $this->abTesting->startTest($test->id);

        $result = $this->abTesting->pauseTest($test->id);

        $this->assertTrue($result);

        $updated = $this->abTesting->getTest($test->id);
        $this->assertEquals('paused', $updated->status);
    }

    public function testPauseTestFailsForNonRunning(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(5, ['name' => 'Draft Test']);

        $result = $this->abTesting->pauseTest($test->id);
        $this->assertFalse($result);
    }

    public function testResumeTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(6, ['name' => 'Resume Test']);
        $this->abTesting->startTest($test->id);
        $this->abTesting->pauseTest($test->id);

        $result = $this->abTesting->resumeTest($test->id);

        $this->assertTrue($result);

        $updated = $this->abTesting->getTest($test->id);
        $this->assertEquals('running', $updated->status);
    }

    public function testResumeTestFailsForNonPaused(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(6, ['name' => 'Not Paused']);

        $result = $this->abTesting->resumeTest($test->id);
        $this->assertFalse($result);
    }

    public function testCompleteTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(7, ['name' => 'Complete Test']);
        $this->abTesting->startTest($test->id);

        $result = $this->abTesting->completeTest($test->id, 'var_winner');

        $this->assertTrue($result);

        $updated = $this->abTesting->getTest($test->id);
        $this->assertEquals('completed', $updated->status);
        $this->assertNotEmpty($updated->end_date);
    }

    public function testDeleteTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(8, ['name' => 'Delete Test']);

        $result = $this->abTesting->deleteTest($test->id);

        $this->assertTrue($result);
        $this->assertNull($this->abTesting->getTest($test->id));
    }

    public function testDeleteTestFailsForRunning(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(8, ['name' => 'Running Test']);
        $this->abTesting->startTest($test->id);

        $result = $this->abTesting->deleteTest($test->id);
        $this->assertFalse($result);
    }

    // ==================== Event Tracking Tests ====================

    public function testTrackEvent(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(9, ['name' => 'Track Test']);
        $this->abTesting->startTest($test->id);

        $variant = $test->variants[0];

        $result = $this->abTesting->trackEvent($test->id, $variant->id, 'view', []);

        $this->assertTrue($result);
    }

    public function testTrackEventWithData(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(9, ['name' => 'Track Data Test']);
        $this->abTesting->startTest($test->id);

        $variant = $test->variants[0];

        $result = $this->abTesting->trackEvent($test->id, $variant->id, 'convert', [
            'submission_id' => 12345,
        ]);

        $this->assertTrue($result);
    }

    // ==================== Results Calculation Tests ====================

    public function testCalculateResults(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(10, ['name' => 'Results Test']);

        $results = $this->abTesting->calculateResults($test->id);

        $this->assertIsArray($results);
    }

    public function testCalculateResultsEmptyTest(): void
    {
        $this->requireDatabase();

        $results = $this->abTesting->calculateResults(999999);
        $this->assertEmpty($results);
    }

    // ==================== Statistical Significance Tests ====================

    public function testCalculateStatisticalSignificance(): void
    {
        $significance = $this->abTesting->calculateStatisticalSignificance(
            1000, // control views
            100,  // control conversions
            1000, // variant views
            150   // variant conversions (50% improvement)
        );

        $this->assertIsFloat($significance);
        $this->assertGreaterThanOrEqual(0, $significance);
        $this->assertLessThanOrEqual(100, $significance);
    }

    public function testCalculateStatisticalSignificanceNoData(): void
    {
        $significance = $this->abTesting->calculateStatisticalSignificance(0, 0, 0, 0);
        $this->assertEquals(0, $significance);
    }

    public function testCalculateStatisticalSignificanceSameRates(): void
    {
        $significance = $this->abTesting->calculateStatisticalSignificance(
            1000, 100,
            1000, 100
        );

        // Same conversion rates should have low significance
        $this->assertLessThan(50, $significance);
    }

    // ==================== Winner Determination Tests ====================

    public function testDetermineWinner(): void
    {
        $results = [
            'var_1' => [
                'variant_id' => 'var_1',
                'is_control' => true,
                'conversion_rate' => 5.0,
            ],
            'var_2' => [
                'variant_id' => 'var_2',
                'is_control' => false,
                'conversion_rate' => 8.0,
                'is_significant' => true,
            ],
        ];

        $winner = $this->abTesting->determineWinner($results, 0.95);

        $this->assertIsArray($winner);
        $this->assertEquals('var_2', $winner['variant_id']);
    }

    public function testDetermineWinnerNoSignificant(): void
    {
        $results = [
            'var_1' => [
                'variant_id' => 'var_1',
                'is_control' => true,
                'conversion_rate' => 5.0,
            ],
            'var_2' => [
                'variant_id' => 'var_2',
                'is_control' => false,
                'conversion_rate' => 5.1,
                'is_significant' => false,
            ],
        ];

        $winner = $this->abTesting->determineWinner($results, 0.95);

        // Should return control or empty
        $this->assertIsArray($winner);
    }

    // ==================== Time Series Data Tests ====================

    public function testGetTimeSeriesData(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(11, ['name' => 'Time Series Test']);

        $data = $this->abTesting->getTimeSeriesData(
            $test->id,
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );

        $this->assertIsArray($data);
    }

    // ==================== REST API Tests ====================

    public function testRestGetTestsReturnsResponse(): void
    {
        $this->requireDatabase();

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/forms/1/tests');
        $request->set_param('form_id', 1);

        $response = $this->abTesting->restGetTests($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertIsArray($response->get_data());
    }

    public function testRestCreateTestReturnsResponse(): void
    {
        $this->requireDatabase();

        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/forms/1/tests');
        $request->set_param('form_id', 1);
        $request->set_body(json_encode([
            'name' => 'REST Created Test',
            'description' => 'Created via REST',
        ]));

        $response = $this->abTesting->restCreateTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestGetTestReturnsResponse(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'REST Get Test']);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/tests/' . $test->id);
        $request->set_param('id', $test->id);

        $response = $this->abTesting->restGetTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals('REST Get Test', $data['name']);
    }

    public function testRestGetTestReturns404ForInvalid(): void
    {
        $this->requireDatabase();

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/tests/999999');
        $request->set_param('id', 999999);

        $response = $this->abTesting->restGetTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    public function testRestUpdateTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'Update Test']);

        $request = new \WP_REST_Request('PUT', '/form-flow-pro/v1/tests/' . $test->id);
        $request->set_param('id', $test->id);
        $request->set_body(json_encode([
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]));

        $response = $this->abTesting->restUpdateTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestUpdateTestFailsForRunning(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'Running Update Test']);
        $this->abTesting->startTest($test->id);

        $request = new \WP_REST_Request('PUT', '/form-flow-pro/v1/tests/' . $test->id);
        $request->set_param('id', $test->id);
        $request->set_body(json_encode(['name' => 'Should Fail']));

        $response = $this->abTesting->restUpdateTest($request);

        $this->assertEquals(400, $response->get_status());
    }

    public function testRestDeleteTest(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'Delete Test']);

        $request = new \WP_REST_Request('DELETE', '/form-flow-pro/v1/tests/' . $test->id);
        $request->set_param('id', $test->id);

        $response = $this->abTesting->restDeleteTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testRestTestAction(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'Action Test']);

        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/tests/' . $test->id . '/start');
        $request->set_param('id', $test->id);
        $request->set_param('action', 'start');

        $response = $this->abTesting->restTestAction($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestGetResults(): void
    {
        $this->requireDatabase();

        $test = $this->abTesting->createTest(1, ['name' => 'Results Test']);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/tests/' . $test->id . '/results');
        $request->set_param('id', $test->id);

        $response = $this->abTesting->restGetResults($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('time_series', $data);
        $this->assertArrayHasKey('winner', $data);
        $this->assertArrayHasKey('has_winner', $data);
    }

    // ==================== Variant Selection Tests ====================

    public function testWeightedRandomSelectionUsesWeights(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'weightedRandomSelection');
        $method->setAccessible(true);

        $variants = [
            new TestVariant(['id' => 'var_1', 'weight' => 90.0]),
            new TestVariant(['id' => 'var_2', 'weight' => 10.0]),
        ];

        // Run multiple selections
        $selections = ['var_1' => 0, 'var_2' => 0];
        for ($i = 0; $i < 100; $i++) {
            $selected = $method->invoke($this->abTesting, $variants);
            $selections[$selected->id]++;
        }

        // The heavily weighted variant should be selected more often
        $this->assertGreaterThan($selections['var_2'], $selections['var_1']);
    }

    // ==================== Beta Sample Tests ====================

    public function testBetaSampleReturnsValueBetween0And1(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'betaSample');
        $method->setAccessible(true);

        for ($i = 0; $i < 100; $i++) {
            $sample = $method->invoke($this->abTesting, 10, 20);
            $this->assertGreaterThanOrEqual(0, $sample);
            $this->assertLessThanOrEqual(1, $sample);
        }
    }

    // ==================== Normal CDF Tests ====================

    public function testNormalCDFReturnsCorrectValues(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'normalCDF');
        $method->setAccessible(true);

        // Z = 0 should give 0.5
        $result = $method->invoke($this->abTesting, 0);
        $this->assertEqualsWithDelta(0.5, $result, 0.01);

        // Large positive Z should approach 1
        $result = $method->invoke($this->abTesting, 3);
        $this->assertGreaterThan(0.99, $result);

        // Large negative Z should approach 0
        $result = $method->invoke($this->abTesting, -3);
        $this->assertLessThan(0.01, $result);
    }

    // ==================== Form Data Application Tests ====================

    public function testApplyTestVariantWithNoActiveTest(): void
    {
        $formData = ['fields' => [['id' => 'f1', 'type' => 'text']]];

        $result = $this->abTesting->applyTestVariant($formData, 999999);

        // Should return unchanged form data
        $this->assertEquals($formData, $result);
    }

    // ==================== Nested Value Tests ====================

    public function testSetNestedValue(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'setNestedValue');
        $method->setAccessible(true);

        $array = ['a' => ['b' => ['c' => 1]]];
        $result = $method->invoke($this->abTesting, $array, 'a.b.c', 99);

        $this->assertEquals(99, $result['a']['b']['c']);
    }

    public function testRemoveNestedValue(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'removeNestedValue');
        $method->setAccessible(true);

        $array = ['a' => ['b' => 1, 'c' => 2]];
        $result = $method->invoke($this->abTesting, $array, 'a.b');

        $this->assertArrayNotHasKey('b', $result['a']);
        $this->assertArrayHasKey('c', $result['a']);
    }

    public function testReorderArray(): void
    {
        $method = new \ReflectionMethod($this->abTesting, 'reorderArray');
        $method->setAccessible(true);

        $array = [
            ['id' => 'a', 'name' => 'A'],
            ['id' => 'b', 'name' => 'B'],
            ['id' => 'c', 'name' => 'C'],
        ];

        $result = $method->invoke($this->abTesting, $array, ['c', 'a', 'b']);

        $this->assertEquals('c', $result[0]['id']);
        $this->assertEquals('a', $result[1]['id']);
        $this->assertEquals('b', $result[2]['id']);
    }
}
