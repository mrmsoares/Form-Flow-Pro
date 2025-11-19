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
}
