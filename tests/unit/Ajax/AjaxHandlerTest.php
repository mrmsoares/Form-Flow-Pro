<?php
/**
 * Tests for Ajax_Handler class.
 */

namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Ajax_Handler;

class AjaxHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/tmp/wordpress/');
        }

        // Require the Ajax_Handler class
        require_once FORMFLOW_PATH . 'includes/ajax/class-ajax-handler.php';
    }

    public function test_class_exists()
    {
        $this->assertTrue(class_exists('FormFlowPro\Ajax\Ajax_Handler'));
    }

    public function test_init_method_exists()
    {
        $this->assertTrue(method_exists('FormFlowPro\Ajax\Ajax_Handler', 'init'));
    }

    public function test_load_handlers_method_exists()
    {
        $reflection = new \ReflectionClass('FormFlowPro\Ajax\Ajax_Handler');
        $this->assertTrue($reflection->hasMethod('load_handlers'));
    }

    public function test_load_handlers_is_private()
    {
        $reflection = new \ReflectionClass('FormFlowPro\Ajax\Ajax_Handler');
        $method = $reflection->getMethod('load_handlers');
        $this->assertTrue($method->isPrivate());
    }

    public function test_init_is_static()
    {
        $reflection = new \ReflectionClass('FormFlowPro\Ajax\Ajax_Handler');
        $method = $reflection->getMethod('init');
        $this->assertTrue($method->isStatic());
    }
}
