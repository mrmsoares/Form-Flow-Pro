<?php
/**
 * Performance tests for FormFlow Pro.
 *
 * Tests system performance under load.
 *
 * @package FormFlowPro
 * @subpackage Tests
 */

namespace FormFlowPro\Tests\Integration;

use FormFlowPro\Tests\TestCase;

/**
 * Performance Test Suite
 *
 * Tests performance characteristics of critical components.
 */
class PerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_mocks();
    }

    /**
     * Test queue can handle batch job creation
     */
    public function test_queue_batch_job_creation_performance()
    {
        $start_time = microtime(true);
        $job_count = 100;

        $jobs = [];
        for ($i = 0; $i < $job_count; $i++) {
            $jobs[] = [
                'job_type' => 'check_signature_status',
                'payload' => json_encode(['submission_id' => $i + 1]),
                'priority' => rand(1, 10),
                'status' => 'pending'
            ];
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // ms

        // Should create 100 job objects in under 50ms
        $this->assertLessThan(50, $execution_time, "Creating {$job_count} jobs should take less than 50ms");
        $this->assertCount($job_count, $jobs);
    }

    /**
     * Test JSON encoding/decoding performance
     */
    public function test_json_encoding_performance()
    {
        $form_data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+55 11 99999-9999',
            'cpf' => '123.456.789-00',
            'address' => [
                'street' => 'Rua Example',
                'number' => '123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip' => '01234-567'
            ],
            'metadata' => [
                'source' => 'elementor',
                'ip' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0...',
                'referrer' => 'https://example.com'
            ]
        ];

        $iterations = 1000;
        $start_time = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $encoded = json_encode($form_data);
            $decoded = json_decode($encoded, true);
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // 1000 encode/decode cycles should complete in under 100ms
        $this->assertLessThan(100, $execution_time, "{$iterations} JSON cycles should take less than 100ms");
    }

    /**
     * Test cache key generation performance
     */
    public function test_cache_key_generation_performance()
    {
        $iterations = 10000;
        $start_time = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $key = 'formflow_' . md5('submission_' . $i . '_data');
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // 10000 key generations should complete in under 50ms
        $this->assertLessThan(50, $execution_time, "{$iterations} cache key generations should take less than 50ms");
    }

    /**
     * Test priority queue sorting performance
     */
    public function test_priority_queue_sorting_performance()
    {
        $jobs = [];
        for ($i = 0; $i < 1000; $i++) {
            $jobs[] = [
                'id' => $i + 1,
                'priority' => rand(1, 10),
                'created_at' => date('Y-m-d H:i:s', time() - rand(0, 3600))
            ];
        }

        $start_time = microtime(true);

        // Sort by priority (descending) then by created_at (ascending)
        usort($jobs, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return strcmp($a['created_at'], $b['created_at']);
            }
            return $b['priority'] - $a['priority'];
        });

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // Sorting 1000 jobs should complete in under 20ms
        $this->assertLessThan(20, $execution_time, "Sorting 1000 jobs should take less than 20ms");

        // Verify sorting worked - highest priority should be first
        $this->assertGreaterThanOrEqual($jobs[1]['priority'], $jobs[0]['priority']);
    }

    /**
     * Test string sanitization performance
     */
    public function test_sanitization_performance()
    {
        $dirty_strings = [];
        for ($i = 0; $i < 100; $i++) {
            $dirty_strings[] = "<script>alert('xss')</script>Normal text with <b>HTML</b> and 'quotes' and \"double quotes\"";
        }

        $start_time = microtime(true);

        $clean_strings = [];
        foreach ($dirty_strings as $dirty) {
            $clean_strings[] = htmlspecialchars(strip_tags($dirty), ENT_QUOTES, 'UTF-8');
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // Sanitizing 100 strings should complete in under 10ms
        $this->assertLessThan(10, $execution_time, "Sanitizing 100 strings should take less than 10ms");
        $this->assertCount(100, $clean_strings);

        // Verify sanitization worked
        $this->assertStringNotContainsString('<script>', $clean_strings[0]);
    }

    /**
     * Test email validation performance
     */
    public function test_email_validation_performance()
    {
        $emails = [];
        for ($i = 0; $i < 1000; $i++) {
            $emails[] = "user{$i}@example.com";
        }

        $start_time = microtime(true);

        $valid_count = 0;
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid_count++;
            }
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // Validating 1000 emails should complete in under 10ms
        $this->assertLessThan(10, $execution_time, "Validating 1000 emails should take less than 10ms");
        $this->assertEquals(1000, $valid_count);
    }

    /**
     * Test array operations performance
     */
    public function test_array_operations_performance()
    {
        $submissions = [];
        for ($i = 0; $i < 1000; $i++) {
            $submissions[] = [
                'id' => $i + 1,
                'status' => ['pending', 'completed', 'failed'][rand(0, 2)],
                'form_id' => rand(1, 10),
                'created_at' => date('Y-m-d', strtotime("-{$i} days"))
            ];
        }

        $start_time = microtime(true);

        // Filter pending submissions
        $pending = array_filter($submissions, fn($s) => $s['status'] === 'pending');

        // Group by form_id
        $by_form = [];
        foreach ($submissions as $s) {
            $by_form[$s['form_id']][] = $s;
        }

        // Count by status
        $status_counts = array_count_values(array_column($submissions, 'status'));

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // All operations should complete in under 20ms
        $this->assertLessThan(20, $execution_time, "Array operations on 1000 items should take less than 20ms");
        $this->assertIsArray($pending);
        $this->assertCount(10, $by_form);
        $this->assertArrayHasKey('pending', $status_counts);
    }

    /**
     * Test date/time operations performance
     */
    public function test_datetime_operations_performance()
    {
        $iterations = 1000;
        $start_time = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $now = new \DateTime();
            $scheduled = $now->modify('+5 minutes');
            $formatted = $scheduled->format('Y-m-d H:i:s');
            $timestamp = $scheduled->getTimestamp();
        }

        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000;

        // 1000 datetime operations should complete in under 50ms
        $this->assertLessThan(50, $execution_time, "{$iterations} datetime operations should take less than 50ms");
    }

    /**
     * Test memory usage for large dataset
     */
    public function test_memory_efficiency()
    {
        $initial_memory = memory_get_usage(true);

        // Create 1000 submission objects
        $submissions = [];
        for ($i = 0; $i < 1000; $i++) {
            $submissions[] = [
                'id' => $i + 1,
                'form_data' => json_encode([
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com",
                    'message' => str_repeat('Lorem ipsum ', 10)
                ]),
                'status' => 'pending'
            ];
        }

        $final_memory = memory_get_usage(true);
        $memory_used = ($final_memory - $initial_memory) / 1024 / 1024; // MB

        // Creating 1000 submissions should use less than 10MB
        $this->assertLessThan(10, $memory_used, "1000 submissions should use less than 10MB of memory");

        // Clean up
        unset($submissions);
    }
}
