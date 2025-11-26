<?php
/**
 * Tests for ReportGenerator class.
 */

namespace FormFlowPro\Tests\Unit\Reporting;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Reporting\ReportGenerator;

class ReportGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset singleton
        $reflection = new \ReflectionClass(ReportGenerator::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $reflection = new \ReflectionClass(ReportGenerator::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        parent::tearDown();
    }

    public function test_singleton_instance()
    {
        $instance1 = ReportGenerator::getInstance();
        $instance2 = ReportGenerator::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(ReportGenerator::class, $instance1);
    }

    public function test_get_templates_returns_array()
    {
        $generator = ReportGenerator::getInstance();
        $templates = $generator->getTemplates();

        $this->assertIsArray($templates);
    }

    public function test_get_template_returns_null_for_nonexistent()
    {
        $generator = ReportGenerator::getInstance();
        $template = $generator->getTemplate('nonexistent_template');

        $this->assertNull($template);
    }

    public function test_register_template()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerTemplate('test_template', [
            'name' => 'Test Template',
            'description' => 'A test template',
            'sections' => ['summary'],
        ]);

        $template = $generator->getTemplate('test_template');

        $this->assertNotNull($template);
        $this->assertEquals('test_template', $template['id']);
        $this->assertEquals('Test Template', $template['name']);
        $this->assertEquals('A test template', $template['description']);
    }

    public function test_get_data_sources_returns_array()
    {
        $generator = ReportGenerator::getInstance();
        $sources = $generator->getDataSources();

        $this->assertIsArray($sources);
    }

    public function test_register_data_source()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerDataSource('test_source', [
            'label' => 'Test Data Source',
            'callback' => function () {
                return ['data' => 'test'];
            },
            'metrics' => ['count', 'total'],
        ]);

        $sources = $generator->getDataSources();

        $this->assertArrayHasKey('test_source', $sources);
        $this->assertEquals('Test Data Source', $sources['test_source']['label']);
    }

    public function test_register_formatter()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerFormatter('uppercase', function ($value) {
            return strtoupper($value);
        });

        $result = $generator->format('hello', 'uppercase');

        $this->assertEquals('HELLO', $result);
    }

    public function test_format_returns_string_for_unknown_formatter()
    {
        $generator = ReportGenerator::getInstance();

        $result = $generator->format('test value', 'nonexistent_formatter');

        $this->assertEquals('test value', $result);
    }

    public function test_format_with_options()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerFormatter('prefix', function ($value, $options) {
            $prefix = $options['prefix'] ?? '';
            return $prefix . $value;
        });

        $result = $generator->format('World', 'prefix', ['prefix' => 'Hello ']);

        $this->assertEquals('Hello World', $result);
    }

    public function test_register_multiple_templates()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerTemplate('template_1', ['name' => 'Template 1']);
        $generator->registerTemplate('template_2', ['name' => 'Template 2']);
        $generator->registerTemplate('template_3', ['name' => 'Template 3']);

        $templates = $generator->getTemplates();

        $this->assertArrayHasKey('template_1', $templates);
        $this->assertArrayHasKey('template_2', $templates);
        $this->assertArrayHasKey('template_3', $templates);
    }

    public function test_template_default_values()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerTemplate('minimal_template', [
            'name' => 'Minimal',
        ]);

        $template = $generator->getTemplate('minimal_template');

        $this->assertEquals('minimal_template', $template['id']);
        $this->assertEquals('Minimal', $template['name']);
        $this->assertEquals('', $template['description']);
        $this->assertIsArray($template['sections']);
        $this->assertIsArray($template['settings']);
    }

    public function test_data_source_default_values()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerDataSource('minimal_source', [
            'label' => 'Minimal',
        ]);

        $sources = $generator->getDataSources();
        $source = $sources['minimal_source'];

        $this->assertEquals('minimal_source', $source['id']);
        $this->assertEquals('Minimal', $source['label']);
        $this->assertNull($source['callback']);
        $this->assertIsArray($source['metrics']);
    }

    public function test_formatter_chain()
    {
        $generator = ReportGenerator::getInstance();

        $generator->registerFormatter('trim', function ($value) {
            return trim($value);
        });

        $generator->registerFormatter('lower', function ($value) {
            return strtolower($value);
        });

        $value = '  HELLO WORLD  ';
        $value = $generator->format($value, 'trim');
        $value = $generator->format($value, 'lower');

        $this->assertEquals('hello world', $value);
    }
}
