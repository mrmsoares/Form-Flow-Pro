<?php
/**
 * Base Test Case for FormFlow Pro tests.
 */

namespace FormFlowPro\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_wp_mocks();
    }

    protected function tearDown(): void
    {
        reset_wp_mocks();
        parent::tearDown();
    }

    protected function getPrivateProperty($object, $property)
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    protected function callPrivateMethod($object, $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    /**
     * Helper method to capture AJAX response output
     *
     * Properly handles output buffering when testing AJAX methods that throw WPAjaxDieException
     *
     * @param callable $callback The AJAX method to call
     * @return string The captured output
     * @throws WPAjaxDieException
     */
    protected function captureAjaxOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();
            $output = ob_get_clean();
            return $output;
        } catch (\WPAjaxDieException $e) {
            $output = ob_get_clean();
            // Re-throw to allow expectException checks
            throw $e;
        } finally {
            // Ensure buffers are always cleaned up
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }
    }

    /**
     * Helper method to call AJAX endpoint and get response
     *
     * @param callable $callback The AJAX method to call
     * @return array The decoded JSON response
     */
    protected function callAjaxEndpoint(callable $callback): array
    {
        $level = ob_get_level();
        ob_start();

        try {
            $callback();
            $output = ob_get_clean();
        } catch (\WPAjaxDieException $e) {
            $output = ob_get_clean();
        } catch (\Exception $e) {
            // Clean buffer on any exception
            if (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        // Safety check: ensure we're back to original buffer level
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return json_decode($output ?? '', true) ?? [];
    }
}
