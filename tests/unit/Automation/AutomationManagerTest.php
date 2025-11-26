<?php
/**
 * Tests for AutomationManager class.
 */

namespace FormFlowPro\Tests\Unit\Automation;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Automation\AutomationManager;
use FormFlowPro\Automation\WorkflowTemplate;

class AutomationManagerTest extends TestCase
{
    /**
     * Classes that need singleton reset for AutomationManager tests
     */
    private array $singletonClasses = [
        AutomationManager::class,
        \FormFlowPro\Automation\TriggerManager::class,
        \FormFlowPro\Automation\WorkflowEngine::class,
        \FormFlowPro\Automation\ActionLibrary::class,
        \FormFlowPro\Automation\ConditionEvaluator::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Reset all related singletons for clean state
        $this->resetAllSingletons();
    }

    protected function tearDown(): void
    {
        // Reset all singletons
        $this->resetAllSingletons();
        parent::tearDown();
    }

    /**
     * Reset all singleton instances to ensure clean test state
     */
    private function resetAllSingletons(): void
    {
        foreach ($this->singletonClasses as $class) {
            if (class_exists($class)) {
                $reflection = new \ReflectionClass($class);
                if ($reflection->hasProperty('instance')) {
                    $instance = $reflection->getProperty('instance');
                    $instance->setAccessible(true);
                    $instance->setValue(null, null);
                }
            }
        }
    }

    // ==========================================================================
    // Singleton Tests
    // ==========================================================================

    public function test_singleton_instance()
    {
        $instance1 = AutomationManager::getInstance();
        $instance2 = AutomationManager::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(AutomationManager::class, $instance1);
    }

    // ==========================================================================
    // Template Tests
    // ==========================================================================

    public function test_get_templates_returns_array()
    {
        $manager = AutomationManager::getInstance();
        $templates = $manager->getTemplates();

        $this->assertIsArray($templates);
    }

    public function test_register_template()
    {
        $manager = AutomationManager::getInstance();

        $template = new WorkflowTemplate([
            'id' => 'custom_template',
            'name' => 'Custom Template',
            'description' => 'A custom workflow template',
            'category' => 'testing',
            'icon' => 'settings',
            'workflow_data' => [
                'triggers' => [],
                'nodes' => [],
                'connections' => []
            ]
        ]);

        $manager->registerTemplate($template);

        $templates = $manager->getTemplates();
        $this->assertArrayHasKey('custom_template', $templates);
        $this->assertEquals('Custom Template', $templates['custom_template']->name);
    }

    public function test_get_templates_by_category()
    {
        $manager = AutomationManager::getInstance();

        // Register templates in different categories
        $manager->registerTemplate(new WorkflowTemplate([
            'id' => 'template_a',
            'name' => 'Template A',
            'category' => 'cat_one'
        ]));

        $manager->registerTemplate(new WorkflowTemplate([
            'id' => 'template_b',
            'name' => 'Template B',
            'category' => 'cat_two'
        ]));

        $byCategory = $manager->getTemplatesByCategory();

        $this->assertIsArray($byCategory);
        $this->assertArrayHasKey('cat_one', $byCategory);
        $this->assertArrayHasKey('cat_two', $byCategory);
    }

    // ==========================================================================
    // Workflow CRUD Tests
    // ==========================================================================

    public function test_get_workflows_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'uuid' => 'uuid-123',
                'name' => 'Test Workflow',
                'status' => 'active',
                'triggers' => '[]',
                'nodes' => '[]',
                'connections' => '[]'
            ]
        ]);

        $manager = AutomationManager::getInstance();
        $workflows = $manager->getWorkflows();

        $this->assertIsArray($workflows);
    }

    public function test_get_workflows_with_status_filter()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'name' => 'Active Workflow',
                'status' => 'active'
            ]
        ]);

        $manager = AutomationManager::getInstance();
        $workflows = $manager->getWorkflows(['status' => 'active']);

        $this->assertIsArray($workflows);
    }

    public function test_get_workflow_returns_array_on_success()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'id' => 1,
            'uuid' => 'uuid-456',
            'name' => 'Single Workflow',
            'status' => 'draft',
            'triggers' => '[]',
            'nodes' => '[]'
        ]);

        $manager = AutomationManager::getInstance();
        $workflow = $manager->getWorkflow(1);

        $this->assertIsArray($workflow);
        $this->assertEquals('Single Workflow', $workflow['name']);
    }

    public function test_get_workflow_returns_null_when_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $manager = AutomationManager::getInstance();
        $workflow = $manager->getWorkflow(999);

        $this->assertNull($workflow);
    }

    public function test_save_workflow_creates_new_workflow()
    {
        global $wpdb;

        $wpdb->insert_id = 1;

        $manager = AutomationManager::getInstance();

        $data = [
            'name' => 'New Workflow',
            'description' => 'Test workflow description',
            'status' => 'draft',
            'triggers' => [
                ['type' => 'form_submission', 'config' => []]
            ],
            'nodes' => [
                ['id' => 'start', 'type' => 'start'],
                ['id' => 'end', 'type' => 'end']
            ],
            'connections' => [
                ['source' => 'start', 'target' => 'end']
            ]
        ];

        $id = $manager->saveWorkflow($data);

        $this->assertEquals(1, $id);
    }

    public function test_save_workflow_updates_existing_workflow()
    {
        global $wpdb;

        $manager = AutomationManager::getInstance();

        $data = [
            'id' => 1,
            'name' => 'Updated Workflow',
            'description' => 'Updated description',
            'status' => 'active',
            'triggers' => [],
            'nodes' => [],
            'connections' => [],
            'version' => 1
        ];

        $id = $manager->saveWorkflow($data);

        $this->assertEquals(1, $id);
    }

    public function test_delete_workflow()
    {
        global $wpdb;

        $manager = AutomationManager::getInstance();

        // Should not throw
        $manager->deleteWorkflow(1);

        $this->assertTrue(true);
    }

    // ==========================================================================
    // Execution Tests
    // ==========================================================================

    public function test_get_executions_returns_array()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            [
                'id' => 1,
                'execution_id' => 'exec-123',
                'workflow_id' => 1,
                'status' => 'completed',
                'started_at' => '2024-01-01 10:00:00'
            ]
        ]);

        $manager = AutomationManager::getInstance();
        $executions = $manager->getExecutions(1);

        $this->assertIsArray($executions);
    }

    public function test_get_executions_with_limit()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $manager = AutomationManager::getInstance();
        $executions = $manager->getExecutions(1, 10);

        $this->assertIsArray($executions);
    }

    // ==========================================================================
    // REST API Tests
    // ==========================================================================

    public function test_rest_get_workflows_returns_response()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow/v1/automation/workflows');
        $response = $manager->restGetWorkflows($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_workflow_returns_response()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', [
            'id' => 1,
            'name' => 'Test',
            'status' => 'active'
        ]);

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow/v1/automation/workflows/1');
        $request->set_param('id', 1);
        $response = $manager->restGetWorkflow($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_workflow_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow/v1/automation/workflows/999');
        $request->set_param('id', 999);
        $response = $manager->restGetWorkflow($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    public function test_rest_create_workflow_returns_response()
    {
        global $wpdb;

        $wpdb->insert_id = 1;

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('POST', '/formflow/v1/automation/workflows');
        $response = $manager->restCreateWorkflow($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());
    }

    public function test_rest_update_workflow_returns_response()
    {
        global $wpdb;

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('PUT', '/formflow/v1/automation/workflows/1');
        $request->set_param('id', 1);
        $response = $manager->restUpdateWorkflow($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_delete_workflow_returns_response()
    {
        global $wpdb;

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('DELETE', '/formflow/v1/automation/workflows/1');
        $request->set_param('id', 1);
        $response = $manager->restDeleteWorkflow($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_executions_returns_response()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', []);

        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow/v1/automation/workflows/1/executions');
        $request->set_param('id', 1);
        $response = $manager->restGetExecutions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_templates_returns_response()
    {
        $manager = AutomationManager::getInstance();

        $request = new \WP_REST_Request('GET', '/formflow/v1/automation/templates');
        $response = $manager->restGetTemplates($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    // ==========================================================================
    // WorkflowTemplate Model Tests
    // ==========================================================================

    public function test_workflow_template_constructor()
    {
        $template = new WorkflowTemplate([
            'id' => 'test_template',
            'name' => 'Test Template',
            'description' => 'A test template',
            'category' => 'testing',
            'icon' => 'gear',
            'workflow_data' => ['key' => 'value'],
            'is_premium' => true
        ]);

        $this->assertEquals('test_template', $template->id);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals('A test template', $template->description);
        $this->assertEquals('testing', $template->category);
        $this->assertEquals('gear', $template->icon);
        $this->assertTrue($template->is_premium);
    }

    public function test_workflow_template_defaults()
    {
        $template = new WorkflowTemplate([]);

        $this->assertEquals('', $template->id);
        $this->assertEquals('', $template->name);
        $this->assertEquals('general', $template->category);
        $this->assertEquals('workflow', $template->icon);
        $this->assertFalse($template->is_premium);
    }

    public function test_workflow_template_to_array()
    {
        $template = new WorkflowTemplate([
            'id' => 'array_test',
            'name' => 'Array Test'
        ]);

        $array = $template->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertEquals('array_test', $array['id']);
    }
}
