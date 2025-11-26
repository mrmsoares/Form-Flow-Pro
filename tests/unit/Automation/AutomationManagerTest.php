<?php
/**
 * Tests for AutomationManager class.
 */

namespace FormFlowPro\Tests\Unit\Automation;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Automation\AutomationManager;

class AutomationManagerTest extends TestCase
{
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = AutomationManager::getInstance();
    }

    public function test_singleton_instance()
    {
        $instance1 = AutomationManager::getInstance();
        $instance2 = AutomationManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_create_workflow()
    {
        global $wpdb;

        $workflowData = [
            'name' => 'New Submission Workflow',
            'trigger' => 'form_submitted',
            'trigger_config' => ['form_id' => 'form-123'],
            'nodes' => [
                [
                    'id' => 'start-1',
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 100],
                ],
                [
                    'id' => 'email-1',
                    'type' => 'send_email',
                    'config' => [
                        'to' => '{{submission.email}}',
                        'subject' => 'Thank you!',
                        'body' => 'We received your submission.',
                    ],
                    'position' => ['x' => 100, 'y' => 200],
                ],
                [
                    'id' => 'end-1',
                    'type' => 'end',
                    'position' => ['x' => 100, 'y' => 300],
                ],
            ],
            'connections' => [
                ['from' => 'start-1', 'to' => 'email-1'],
                ['from' => 'email-1', 'to' => 'end-1'],
            ],
        ];

        $result = $this->manager->createWorkflow($workflowData);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('workflow_id', $result);
    }

    public function test_create_workflow_validation_fails_without_name()
    {
        $workflowData = [
            'trigger' => 'form_submitted',
            'nodes' => [],
        ];

        $result = $this->manager->createWorkflow($workflowData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_get_workflow()
    {
        global $wpdb;

        $mockWorkflow = (object)[
            'id' => '1',
            'name' => 'Test Workflow',
            'trigger' => 'form_submitted',
            'trigger_config' => json_encode(['form_id' => 'form-123']),
            'nodes' => json_encode([]),
            'connections' => json_encode([]),
            'status' => 'active',
            'created_at' => '2024-01-01 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockWorkflow);

        $workflow = $this->manager->getWorkflow('1');

        $this->assertIsObject($workflow);
        $this->assertEquals('Test Workflow', $workflow->name);
    }

    public function test_update_workflow()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Old Name',
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Updated Workflow',
            'nodes' => [
                ['id' => 'start-1', 'type' => 'start'],
            ],
        ];

        $result = $this->manager->updateWorkflow('1', $updateData);

        $this->assertTrue($result['success']);
    }

    public function test_delete_workflow()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Test Workflow',
        ]);

        $result = $this->manager->deleteWorkflow('1');

        $this->assertTrue($result);
    }

    public function test_toggle_workflow_status()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'status' => 'active',
        ]);

        $result = $this->manager->toggleWorkflowStatus('1', 'paused');

        $this->assertTrue($result['success']);
    }

    public function test_get_all_workflows()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'name' => 'Workflow 1',
                'status' => 'active',
            ],
            (object)[
                'id' => '2',
                'name' => 'Workflow 2',
                'status' => 'paused',
            ],
        ]);

        $workflows = $this->manager->getWorkflows();

        $this->assertIsArray($workflows);
        $this->assertCount(2, $workflows);
    }

    public function test_get_available_triggers()
    {
        $triggers = $this->manager->getAvailableTriggers();

        $this->assertIsArray($triggers);
        $this->assertArrayHasKey('form_submitted', $triggers);
        $this->assertArrayHasKey('signature_completed', $triggers);
        $this->assertArrayHasKey('payment_received', $triggers);
    }

    public function test_get_available_actions()
    {
        $actions = $this->manager->getAvailableActions();

        $this->assertIsArray($actions);
        $this->assertArrayHasKey('send_email', $actions);
        $this->assertArrayHasKey('send_sms', $actions);
        $this->assertArrayHasKey('http_request', $actions);
        $this->assertArrayHasKey('create_pdf', $actions);
        $this->assertArrayHasKey('send_signature', $actions);
        $this->assertArrayHasKey('database_query', $actions);
    }

    public function test_execute_workflow()
    {
        global $wpdb;

        $mockWorkflow = (object)[
            'id' => '1',
            'name' => 'Test Workflow',
            'status' => 'active',
            'nodes' => json_encode([
                ['id' => 'start-1', 'type' => 'start'],
                ['id' => 'set-var-1', 'type' => 'set_variable', 'config' => ['name' => 'test', 'value' => 'hello']],
                ['id' => 'end-1', 'type' => 'end'],
            ]),
            'connections' => json_encode([
                ['from' => 'start-1', 'to' => 'set-var-1'],
                ['from' => 'set-var-1', 'to' => 'end-1'],
            ]),
        ];

        $wpdb->set_mock_result('get_row', $mockWorkflow);

        $context = [
            'submission_id' => '123',
            'form_id' => 'form-456',
            'data' => ['name' => 'John', 'email' => 'john@example.com'],
        ];

        $result = $this->manager->executeWorkflow('1', $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function test_execute_workflow_paused_returns_error()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'status' => 'paused',
        ]);

        $result = $this->manager->executeWorkflow('1', []);

        $this->assertFalse($result['success']);
    }

    public function test_validate_workflow_structure()
    {
        $validWorkflow = [
            'nodes' => [
                ['id' => 'start-1', 'type' => 'start'],
                ['id' => 'end-1', 'type' => 'end'],
            ],
            'connections' => [
                ['from' => 'start-1', 'to' => 'end-1'],
            ],
        ];

        $isValid = $this->callPrivateMethod($this->manager, 'validateWorkflowStructure', [$validWorkflow]);

        $this->assertTrue($isValid);
    }

    public function test_validate_workflow_requires_start_node()
    {
        $invalidWorkflow = [
            'nodes' => [
                ['id' => 'email-1', 'type' => 'send_email'],
                ['id' => 'end-1', 'type' => 'end'],
            ],
            'connections' => [],
        ];

        $isValid = $this->callPrivateMethod($this->manager, 'validateWorkflowStructure', [$invalidWorkflow]);

        $this->assertFalse($isValid);
    }

    public function test_validate_workflow_requires_end_node()
    {
        $invalidWorkflow = [
            'nodes' => [
                ['id' => 'start-1', 'type' => 'start'],
                ['id' => 'email-1', 'type' => 'send_email'],
            ],
            'connections' => [],
        ];

        $isValid = $this->callPrivateMethod($this->manager, 'validateWorkflowStructure', [$invalidWorkflow]);

        $this->assertFalse($isValid);
    }

    public function test_process_condition_node()
    {
        $node = [
            'id' => 'condition-1',
            'type' => 'condition',
            'config' => [
                'field' => 'status',
                'operator' => 'equals',
                'value' => 'approved',
            ],
        ];

        $context = [
            'data' => ['status' => 'approved'],
        ];

        $result = $this->callPrivateMethod($this->manager, 'processConditionNode', [$node, $context]);

        $this->assertTrue($result);
    }

    public function test_process_condition_node_not_equals()
    {
        $node = [
            'id' => 'condition-1',
            'type' => 'condition',
            'config' => [
                'field' => 'status',
                'operator' => 'not_equals',
                'value' => 'rejected',
            ],
        ];

        $context = [
            'data' => ['status' => 'approved'],
        ];

        $result = $this->callPrivateMethod($this->manager, 'processConditionNode', [$node, $context]);

        $this->assertTrue($result);
    }

    public function test_replace_variables_in_string()
    {
        $template = 'Hello {{name}}, your email is {{email}}';
        $context = [
            'data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
        ];

        $result = $this->callPrivateMethod($this->manager, 'replaceVariables', [$template, $context]);

        $this->assertEquals('Hello John Doe, your email is john@example.com', $result);
    }

    public function test_get_workflow_execution_history()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_results', [
            (object)[
                'id' => '1',
                'workflow_id' => '1',
                'status' => 'completed',
                'started_at' => '2024-01-15 10:00:00',
                'completed_at' => '2024-01-15 10:00:05',
            ],
        ]);

        $history = $this->manager->getWorkflowExecutionHistory('1');

        $this->assertIsArray($history);
    }

    public function test_duplicate_workflow()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', (object)[
            'id' => '1',
            'name' => 'Original Workflow',
            'trigger' => 'form_submitted',
            'trigger_config' => json_encode([]),
            'nodes' => json_encode([]),
            'connections' => json_encode([]),
            'status' => 'active',
        ]);

        $result = $this->manager->duplicateWorkflow('1');

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
    }
}
