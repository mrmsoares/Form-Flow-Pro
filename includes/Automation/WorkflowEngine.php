<?php
/**
 * Workflow Engine - Core execution engine for automation workflows
 *
 * Handles workflow execution, state management, branching,
 * parallel execution, and error recovery.
 *
 * @package FormFlowPro
 * @subpackage Automation
 * @since 3.0.0
 */

namespace FormFlowPro\Automation;

use FormFlowPro\Core\SingletonTrait;

/**
 * Workflow definition model
 */
class Workflow
{
    public int $id;
    public string $uuid;
    public string $name;
    public string $description;
    public string $status; // draft, active, paused, archived
    public array $triggers;
    public array $nodes;
    public array $connections;
    public array $variables;
    public array $settings;
    public int $version;
    public int $author_id;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->uuid = $data['uuid'] ?? wp_generate_uuid4();
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->status = $data['status'] ?? 'draft';
        $this->triggers = $data['triggers'] ?? [];
        $this->nodes = $data['nodes'] ?? [];
        $this->connections = $data['connections'] ?? [];
        $this->variables = $data['variables'] ?? [];
        $this->settings = $data['settings'] ?? $this->getDefaultSettings();
        $this->version = $data['version'] ?? 1;
        $this->author_id = $data['author_id'] ?? get_current_user_id();
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
    }

    private function getDefaultSettings(): array
    {
        return [
            'max_executions_per_hour' => 1000,
            'timeout_seconds' => 300,
            'retry_on_failure' => true,
            'max_retries' => 3,
            'retry_delay_seconds' => 60,
            'log_level' => 'info',
            'notification_on_failure' => true,
            'notification_email' => '',
            'parallel_execution' => true,
            'max_parallel_branches' => 5
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'triggers' => $this->triggers,
            'nodes' => $this->nodes,
            'connections' => $this->connections,
            'variables' => $this->variables,
            'settings' => $this->settings,
            'version' => $this->version,
            'author_id' => $this->author_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getNode(string $node_id): ?array
    {
        foreach ($this->nodes as $node) {
            if ($node['id'] === $node_id) {
                return $node;
            }
        }
        return null;
    }

    public function getNextNodes(string $node_id): array
    {
        $next_nodes = [];
        foreach ($this->connections as $connection) {
            if ($connection['source'] === $node_id) {
                $next_nodes[] = [
                    'node_id' => $connection['target'],
                    'condition' => $connection['condition'] ?? null,
                    'label' => $connection['label'] ?? ''
                ];
            }
        }
        return $next_nodes;
    }

    public function getStartNodes(): array
    {
        $target_nodes = array_column($this->connections, 'target');
        return array_filter($this->nodes, function ($node) use ($target_nodes) {
            return !in_array($node['id'], $target_nodes) && $node['type'] !== 'trigger';
        });
    }
}

/**
 * Workflow execution instance
 */
class WorkflowExecution
{
    public string $execution_id;
    public int $workflow_id;
    public string $workflow_uuid;
    public string $status; // pending, running, completed, failed, cancelled, paused
    public string $trigger_type;
    public array $trigger_data;
    public array $context;
    public array $variables;
    public array $node_states;
    public array $logs;
    public ?string $error_message;
    public string $started_at;
    public ?string $completed_at;
    public int $execution_time_ms;

    public function __construct(array $data = [])
    {
        $this->execution_id = $data['execution_id'] ?? wp_generate_uuid4();
        $this->workflow_id = $data['workflow_id'] ?? 0;
        $this->workflow_uuid = $data['workflow_uuid'] ?? '';
        $this->status = $data['status'] ?? 'pending';
        $this->trigger_type = $data['trigger_type'] ?? '';
        $this->trigger_data = $data['trigger_data'] ?? [];
        $this->context = $data['context'] ?? [];
        $this->variables = $data['variables'] ?? [];
        $this->node_states = $data['node_states'] ?? [];
        $this->logs = $data['logs'] ?? [];
        $this->error_message = $data['error_message'] ?? null;
        $this->started_at = $data['started_at'] ?? current_time('mysql');
        $this->completed_at = $data['completed_at'] ?? null;
        $this->execution_time_ms = $data['execution_time_ms'] ?? 0;
    }

    public function setNodeState(string $node_id, string $status, array $output = []): void
    {
        $this->node_states[$node_id] = [
            'status' => $status,
            'output' => $output,
            'timestamp' => microtime(true)
        ];
    }

    public function getNodeState(string $node_id): ?array
    {
        return $this->node_states[$node_id] ?? null;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true)
        ];
    }

    public function setVariable(string $key, $value): void
    {
        $this->variables[$key] = $value;
    }

    public function getVariable(string $key, $default = null)
    {
        return $this->variables[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'execution_id' => $this->execution_id,
            'workflow_id' => $this->workflow_id,
            'workflow_uuid' => $this->workflow_uuid,
            'status' => $this->status,
            'trigger_type' => $this->trigger_type,
            'trigger_data' => $this->trigger_data,
            'context' => $this->context,
            'variables' => $this->variables,
            'node_states' => $this->node_states,
            'logs' => $this->logs,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'execution_time_ms' => $this->execution_time_ms
        ];
    }
}

/**
 * Node execution result
 */
class NodeResult
{
    public bool $success;
    public string $status; // completed, failed, skipped, waiting
    public array $output;
    public ?string $error;
    public ?string $next_node;
    public array $branch_results;

    public function __construct(
        bool $success = true,
        string $status = 'completed',
        array $output = [],
        ?string $error = null,
        ?string $next_node = null,
        array $branch_results = []
    ) {
        $this->success = $success;
        $this->status = $status;
        $this->output = $output;
        $this->error = $error;
        $this->next_node = $next_node;
        $this->branch_results = $branch_results;
    }

    public static function success(array $output = [], ?string $next_node = null): self
    {
        return new self(true, 'completed', $output, null, $next_node);
    }

    public static function failure(string $error, array $output = []): self
    {
        return new self(false, 'failed', $output, $error);
    }

    public static function skipped(string $reason = ''): self
    {
        return new self(true, 'skipped', ['reason' => $reason]);
    }

    public static function waiting(string $reason = ''): self
    {
        return new self(true, 'waiting', ['reason' => $reason]);
    }
}

/**
 * Workflow Engine - Main execution engine
 */
class WorkflowEngine
{
    use SingletonTrait;

    private array $node_handlers = [];
    private array $running_executions = [];
    private ConditionEvaluator $condition_evaluator;
    private ActionLibrary $action_library;

    /**
     * Initialize the workflow engine
     */
    protected function init(): void
    {
        $this->condition_evaluator = ConditionEvaluator::getInstance();
        $this->action_library = ActionLibrary::getInstance();

        $this->registerCoreNodeHandlers();
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_action('ffp_execute_workflow', [$this, 'executeWorkflowAsync'], 10, 3);
        add_action('ffp_resume_execution', [$this, 'resumeExecution'], 10, 2);
        add_action('ffp_cleanup_executions', [$this, 'cleanupOldExecutions']);

        // Schedule cleanup
        if (!wp_next_scheduled('ffp_cleanup_executions')) {
            wp_schedule_event(time(), 'daily', 'ffp_cleanup_executions');
        }
    }

    /**
     * Register core node handlers
     */
    private function registerCoreNodeHandlers(): void
    {
        // Control flow nodes
        $this->registerNodeHandler('start', [$this, 'handleStartNode']);
        $this->registerNodeHandler('end', [$this, 'handleEndNode']);
        $this->registerNodeHandler('condition', [$this, 'handleConditionNode']);
        $this->registerNodeHandler('switch', [$this, 'handleSwitchNode']);
        $this->registerNodeHandler('loop', [$this, 'handleLoopNode']);
        $this->registerNodeHandler('parallel', [$this, 'handleParallelNode']);
        $this->registerNodeHandler('merge', [$this, 'handleMergeNode']);
        $this->registerNodeHandler('delay', [$this, 'handleDelayNode']);
        $this->registerNodeHandler('wait', [$this, 'handleWaitNode']);

        // Data nodes
        $this->registerNodeHandler('set_variable', [$this, 'handleSetVariableNode']);
        $this->registerNodeHandler('transform', [$this, 'handleTransformNode']);
        $this->registerNodeHandler('filter', [$this, 'handleFilterNode']);
        $this->registerNodeHandler('aggregate', [$this, 'handleAggregateNode']);

        // Action nodes - delegate to ActionLibrary
        $this->registerNodeHandler('action', [$this, 'handleActionNode']);
    }

    /**
     * Register a custom node handler
     */
    public function registerNodeHandler(string $type, callable $handler): void
    {
        $this->node_handlers[$type] = $handler;
    }

    /**
     * Execute a workflow
     */
    public function execute(Workflow $workflow, array $trigger_data = [], array $context = []): WorkflowExecution
    {
        if (!$workflow->isActive()) {
            throw new \Exception("Workflow '{$workflow->name}' is not active");
        }

        // Check rate limits
        if (!$this->checkRateLimits($workflow)) {
            throw new \Exception("Workflow rate limit exceeded");
        }

        // Create execution instance
        $execution = new WorkflowExecution([
            'workflow_id' => $workflow->id,
            'workflow_uuid' => $workflow->uuid,
            'trigger_type' => $context['trigger_type'] ?? 'manual',
            'trigger_data' => $trigger_data,
            'context' => $context,
            'variables' => array_merge($workflow->variables, $trigger_data)
        ]);

        $execution->status = 'running';
        $execution->log('info', 'Workflow execution started', [
            'workflow_name' => $workflow->name,
            'trigger_type' => $execution->trigger_type
        ]);

        $this->running_executions[$execution->execution_id] = $execution;

        // Save initial state
        $this->saveExecution($execution);

        try {
            // Find start nodes and execute
            $start_time = microtime(true);

            $this->executeFromNodes($workflow, $execution, $this->findStartNodes($workflow));

            $execution->execution_time_ms = (int)((microtime(true) - $start_time) * 1000);
            $execution->status = 'completed';
            $execution->completed_at = current_time('mysql');
            $execution->log('info', 'Workflow execution completed', [
                'execution_time_ms' => $execution->execution_time_ms
            ]);

        } catch (\Exception $e) {
            $execution->status = 'failed';
            $execution->error_message = $e->getMessage();
            $execution->completed_at = current_time('mysql');
            $execution->log('error', 'Workflow execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Handle failure notification
            if ($workflow->settings['notification_on_failure'] ?? false) {
                $this->sendFailureNotification($workflow, $execution);
            }

            // Handle retry
            if ($workflow->settings['retry_on_failure'] ?? false) {
                $this->scheduleRetry($workflow, $execution);
            }
        }

        // Save final state
        $this->saveExecution($execution);
        unset($this->running_executions[$execution->execution_id]);

        do_action('ffp_workflow_execution_completed', $execution, $workflow);

        return $execution;
    }

    /**
     * Execute workflow asynchronously
     */
    public function executeWorkflowAsync(int $workflow_id, array $trigger_data = [], array $context = []): void
    {
        $workflow = $this->loadWorkflow($workflow_id);
        if ($workflow) {
            $this->execute($workflow, $trigger_data, $context);
        }
    }

    /**
     * Find start nodes in workflow
     */
    private function findStartNodes(Workflow $workflow): array
    {
        $start_nodes = [];

        foreach ($workflow->nodes as $node) {
            if ($node['type'] === 'start' || $node['type'] === 'trigger') {
                $start_nodes[] = $node;
            }
        }

        // If no explicit start node, find nodes with no incoming connections
        if (empty($start_nodes)) {
            $target_ids = array_column($workflow->connections, 'target');
            foreach ($workflow->nodes as $node) {
                if (!in_array($node['id'], $target_ids)) {
                    $start_nodes[] = $node;
                }
            }
        }

        return $start_nodes;
    }

    /**
     * Execute workflow from specific nodes
     */
    private function executeFromNodes(Workflow $workflow, WorkflowExecution $execution, array $nodes): void
    {
        $queue = $nodes;
        $visited = [];
        $max_iterations = 10000; // Prevent infinite loops
        $iterations = 0;

        while (!empty($queue) && $iterations < $max_iterations) {
            $iterations++;
            $node = array_shift($queue);
            $node_id = $node['id'];

            // Skip if already visited (unless it's a merge node waiting for all inputs)
            if (isset($visited[$node_id]) && $node['type'] !== 'merge') {
                continue;
            }

            // Check if execution was cancelled
            if ($execution->status === 'cancelled') {
                break;
            }

            // Execute the node
            $result = $this->executeNode($workflow, $execution, $node);
            $visited[$node_id] = true;

            $execution->setNodeState($node_id, $result->status, $result->output);

            if (!$result->success && $result->status === 'failed') {
                throw new \Exception("Node '{$node_id}' failed: " . ($result->error ?? 'Unknown error'));
            }

            // Handle waiting status (async operations)
            if ($result->status === 'waiting') {
                $this->scheduleResume($execution, $node_id, $result->output);
                continue;
            }

            // Get next nodes
            $next_connections = $workflow->getNextNodes($node_id);

            foreach ($next_connections as $connection) {
                // Evaluate connection condition if present
                if (!empty($connection['condition'])) {
                    if (!$this->condition_evaluator->evaluate($connection['condition'], $execution->variables)) {
                        continue;
                    }
                }

                $next_node = $workflow->getNode($connection['node_id']);
                if ($next_node) {
                    $queue[] = $next_node;
                }
            }

            // Handle specific next node from result
            if ($result->next_node) {
                $specific_next = $workflow->getNode($result->next_node);
                if ($specific_next) {
                    array_unshift($queue, $specific_next);
                }
            }
        }

        if ($iterations >= $max_iterations) {
            throw new \Exception("Workflow execution exceeded maximum iterations (possible infinite loop)");
        }
    }

    /**
     * Execute a single node
     */
    private function executeNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $node_type = $node['type'];
        $node_id = $node['id'];

        $execution->log('debug', "Executing node: {$node_id}", [
            'type' => $node_type,
            'config' => $node['config'] ?? []
        ]);

        // Check timeout
        $elapsed = time() - strtotime($execution->started_at);
        $timeout = $workflow->settings['timeout_seconds'] ?? 300;
        if ($elapsed > $timeout) {
            throw new \Exception("Workflow execution timeout ({$timeout}s)");
        }

        // Get handler
        $handler = $this->node_handlers[$node_type] ?? null;

        if (!$handler) {
            // Try generic action handler
            if (isset($node['action_type'])) {
                return $this->handleActionNode($workflow, $execution, $node);
            }
            throw new \Exception("Unknown node type: {$node_type}");
        }

        try {
            return call_user_func($handler, $workflow, $execution, $node);
        } catch (\Exception $e) {
            $execution->log('error', "Node execution error: {$node_id}", [
                'error' => $e->getMessage()
            ]);
            return NodeResult::failure($e->getMessage());
        }
    }

    // ==================== Core Node Handlers ====================

    /**
     * Handle start node
     */
    public function handleStartNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        return NodeResult::success(['message' => 'Workflow started']);
    }

    /**
     * Handle end node
     */
    public function handleEndNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $output = $node['config']['output'] ?? [];

        // Process output variables
        $result = [];
        foreach ($output as $key => $value) {
            $result[$key] = $this->resolveValue($value, $execution);
        }

        return NodeResult::success($result);
    }

    /**
     * Handle condition (if/else) node
     */
    public function handleConditionNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $conditions = $config['conditions'] ?? [];

        foreach ($conditions as $condition) {
            $result = $this->condition_evaluator->evaluate($condition['expression'], $execution->variables);

            if ($result) {
                return NodeResult::success(
                    ['matched_condition' => $condition['label'] ?? 'true'],
                    $condition['next_node'] ?? null
                );
            }
        }

        // Default/else branch
        $default_next = $config['default_next'] ?? null;
        return NodeResult::success(['matched_condition' => 'default'], $default_next);
    }

    /**
     * Handle switch node (multiple branches)
     */
    public function handleSwitchNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $switch_value = $this->resolveValue($config['value'] ?? '', $execution);
        $cases = $config['cases'] ?? [];

        foreach ($cases as $case) {
            if ($case['value'] === $switch_value) {
                return NodeResult::success(
                    ['matched_case' => $case['value']],
                    $case['next_node'] ?? null
                );
            }
        }

        // Default case
        return NodeResult::success(
            ['matched_case' => 'default'],
            $config['default_next'] ?? null
        );
    }

    /**
     * Handle loop node
     */
    public function handleLoopNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $loop_type = $config['type'] ?? 'foreach';
        $max_iterations = $config['max_iterations'] ?? 1000;

        $results = [];

        if ($loop_type === 'foreach') {
            $items = $this->resolveValue($config['items'] ?? [], $execution);
            $item_var = $config['item_variable'] ?? 'item';
            $index_var = $config['index_variable'] ?? 'index';

            if (!is_array($items)) {
                return NodeResult::failure("Loop items must be an array");
            }

            $index = 0;
            foreach ($items as $key => $item) {
                if ($index >= $max_iterations) {
                    break;
                }

                $execution->setVariable($item_var, $item);
                $execution->setVariable($index_var, $index);
                $execution->setVariable('loop_key', $key);

                // Execute loop body (nodes connected to loop_body output)
                $body_node_id = $config['body_node'] ?? null;
                if ($body_node_id) {
                    $body_node = $workflow->getNode($body_node_id);
                    if ($body_node) {
                        $body_result = $this->executeNode($workflow, $execution, $body_node);
                        $results[] = $body_result->output;

                        if (!$body_result->success) {
                            return NodeResult::failure("Loop iteration {$index} failed: " . $body_result->error);
                        }
                    }
                }

                $index++;
            }
        } elseif ($loop_type === 'while') {
            $condition = $config['condition'] ?? 'false';
            $index = 0;

            while ($this->condition_evaluator->evaluate($condition, $execution->variables)) {
                if ($index >= $max_iterations) {
                    break;
                }

                $execution->setVariable('loop_index', $index);

                $body_node_id = $config['body_node'] ?? null;
                if ($body_node_id) {
                    $body_node = $workflow->getNode($body_node_id);
                    if ($body_node) {
                        $body_result = $this->executeNode($workflow, $execution, $body_node);
                        $results[] = $body_result->output;
                    }
                }

                $index++;
            }
        } elseif ($loop_type === 'times') {
            $times = min((int)$this->resolveValue($config['times'] ?? 1, $execution), $max_iterations);

            for ($i = 0; $i < $times; $i++) {
                $execution->setVariable('loop_index', $i);

                $body_node_id = $config['body_node'] ?? null;
                if ($body_node_id) {
                    $body_node = $workflow->getNode($body_node_id);
                    if ($body_node) {
                        $body_result = $this->executeNode($workflow, $execution, $body_node);
                        $results[] = $body_result->output;
                    }
                }
            }
        }

        return NodeResult::success(['iterations' => count($results), 'results' => $results]);
    }

    /**
     * Handle parallel execution node
     */
    public function handleParallelNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $branch_nodes = $config['branches'] ?? [];
        $wait_for_all = $config['wait_for_all'] ?? true;
        $max_parallel = $workflow->settings['max_parallel_branches'] ?? 5;

        $branch_results = [];
        $branches_to_execute = array_slice($branch_nodes, 0, $max_parallel);

        foreach ($branches_to_execute as $branch_node_id) {
            $branch_node = $workflow->getNode($branch_node_id);
            if ($branch_node) {
                $result = $this->executeNode($workflow, $execution, $branch_node);
                $branch_results[$branch_node_id] = $result;

                if (!$wait_for_all && $result->success) {
                    // Return on first success
                    break;
                }
            }
        }

        $all_success = array_reduce($branch_results, function ($carry, $result) {
            return $carry && $result->success;
        }, true);

        return new NodeResult(
            $all_success,
            $all_success ? 'completed' : 'failed',
            ['branch_results' => array_map(fn($r) => $r->output, $branch_results)],
            $all_success ? null : 'One or more branches failed'
        );
    }

    /**
     * Handle merge node (waits for all incoming branches)
     */
    public function handleMergeNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $required_inputs = $config['required_inputs'] ?? [];

        // Check if all required inputs are completed
        foreach ($required_inputs as $input_node_id) {
            $state = $execution->getNodeState($input_node_id);
            if (!$state || $state['status'] !== 'completed') {
                return NodeResult::waiting("Waiting for node: {$input_node_id}");
            }
        }

        // Merge all input outputs
        $merged_data = [];
        foreach ($required_inputs as $input_node_id) {
            $state = $execution->getNodeState($input_node_id);
            $merged_data[$input_node_id] = $state['output'] ?? [];
        }

        return NodeResult::success(['merged_data' => $merged_data]);
    }

    /**
     * Handle delay node
     */
    public function handleDelayNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $delay_type = $config['type'] ?? 'seconds';
        $value = (int)$this->resolveValue($config['value'] ?? 0, $execution);

        $delay_seconds = match ($delay_type) {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => $value
        };

        if ($delay_seconds > 0) {
            // For short delays, sleep. For longer, schedule resume
            if ($delay_seconds <= 30) {
                sleep($delay_seconds);
            } else {
                $this->scheduleResume($execution, $node['id'], [
                    'resume_at' => time() + $delay_seconds
                ]);
                return NodeResult::waiting("Waiting for {$delay_seconds} seconds");
            }
        }

        return NodeResult::success(['delayed_seconds' => $delay_seconds]);
    }

    /**
     * Handle wait node (waits for external event)
     */
    public function handleWaitNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $wait_type = $config['type'] ?? 'webhook';
        $timeout = $config['timeout'] ?? 3600;

        // Store wait state
        $wait_key = "ffp_wait_{$execution->execution_id}_{$node['id']}";

        $existing_result = get_transient($wait_key);
        if ($existing_result !== false) {
            delete_transient($wait_key);
            return NodeResult::success($existing_result);
        }

        // Schedule timeout check
        wp_schedule_single_event(time() + $timeout, 'ffp_wait_timeout', [
            $execution->execution_id,
            $node['id']
        ]);

        return NodeResult::waiting("Waiting for {$wait_type} event");
    }

    /**
     * Handle set variable node
     */
    public function handleSetVariableNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $variables = $config['variables'] ?? [];

        $set_vars = [];
        foreach ($variables as $var) {
            $name = $var['name'] ?? '';
            $value = $this->resolveValue($var['value'] ?? null, $execution);

            if (!empty($name)) {
                $execution->setVariable($name, $value);
                $set_vars[$name] = $value;
            }
        }

        return NodeResult::success(['variables_set' => $set_vars]);
    }

    /**
     * Handle transform node (data transformation)
     */
    public function handleTransformNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $input = $this->resolveValue($config['input'] ?? [], $execution);
        $transformations = $config['transformations'] ?? [];

        $result = $input;

        foreach ($transformations as $transform) {
            $type = $transform['type'] ?? '';

            $result = match ($type) {
                'map' => array_map(function ($item) use ($transform, $execution) {
                    $execution->setVariable('item', $item);
                    return $this->resolveValue($transform['expression'], $execution);
                }, (array)$result),

                'filter' => array_filter((array)$result, function ($item) use ($transform, $execution) {
                    $execution->setVariable('item', $item);
                    return $this->condition_evaluator->evaluate($transform['condition'], $execution->variables);
                }),

                'reduce' => array_reduce((array)$result, function ($carry, $item) use ($transform, $execution) {
                    $execution->setVariable('accumulator', $carry);
                    $execution->setVariable('item', $item);
                    return $this->resolveValue($transform['expression'], $execution);
                }, $transform['initial'] ?? null),

                'sort' => $this->sortArray((array)$result, $transform['key'] ?? null, $transform['direction'] ?? 'asc'),

                'unique' => array_unique((array)$result),

                'flatten' => $this->flattenArray((array)$result, $transform['depth'] ?? 1),

                'group' => $this->groupArray((array)$result, $transform['key']),

                'pluck' => array_column((array)$result, $transform['key']),

                'json_encode' => json_encode($result),
                'json_decode' => json_decode($result, true),

                'uppercase' => is_string($result) ? strtoupper($result) : $result,
                'lowercase' => is_string($result) ? strtolower($result) : $result,
                'trim' => is_string($result) ? trim($result) : $result,

                default => $result
            };
        }

        // Store result in variable if specified
        $output_var = $config['output_variable'] ?? null;
        if ($output_var) {
            $execution->setVariable($output_var, $result);
        }

        return NodeResult::success(['result' => $result]);
    }

    /**
     * Handle filter node
     */
    public function handleFilterNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $input = $this->resolveValue($config['input'] ?? [], $execution);
        $conditions = $config['conditions'] ?? [];
        $match_type = $config['match_type'] ?? 'all'; // all, any

        if (!is_array($input)) {
            return NodeResult::failure("Filter input must be an array");
        }

        $filtered = array_filter($input, function ($item) use ($conditions, $match_type, $execution) {
            $execution->setVariable('item', $item);

            $results = array_map(function ($condition) use ($execution) {
                return $this->condition_evaluator->evaluate($condition, $execution->variables);
            }, $conditions);

            return $match_type === 'all' ? !in_array(false, $results, true) : in_array(true, $results, true);
        });

        return NodeResult::success([
            'filtered' => array_values($filtered),
            'count' => count($filtered),
            'original_count' => count($input)
        ]);
    }

    /**
     * Handle aggregate node
     */
    public function handleAggregateNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $config = $node['config'] ?? [];
        $input = $this->resolveValue($config['input'] ?? [], $execution);
        $operations = $config['operations'] ?? [];

        if (!is_array($input)) {
            return NodeResult::failure("Aggregate input must be an array");
        }

        $results = [];

        foreach ($operations as $op) {
            $field = $op['field'] ?? null;
            $type = $op['type'] ?? 'count';
            $alias = $op['alias'] ?? $type;

            $values = $field ? array_column($input, $field) : $input;
            $numeric_values = array_filter($values, 'is_numeric');

            $results[$alias] = match ($type) {
                'count' => count($values),
                'sum' => array_sum($numeric_values),
                'avg' => count($numeric_values) > 0 ? array_sum($numeric_values) / count($numeric_values) : 0,
                'min' => count($numeric_values) > 0 ? min($numeric_values) : null,
                'max' => count($numeric_values) > 0 ? max($numeric_values) : null,
                'first' => $values[0] ?? null,
                'last' => end($values) ?: null,
                'distinct' => count(array_unique($values)),
                default => null
            };
        }

        return NodeResult::success($results);
    }

    /**
     * Handle action node (delegates to ActionLibrary)
     */
    public function handleActionNode(Workflow $workflow, WorkflowExecution $execution, array $node): NodeResult
    {
        $action_type = $node['action_type'] ?? $node['config']['action_type'] ?? '';
        $config = $node['config'] ?? [];

        // Resolve all config values
        $resolved_config = $this->resolveConfigValues($config, $execution);

        return $this->action_library->executeAction($action_type, $resolved_config, $execution);
    }

    // ==================== Helper Methods ====================

    /**
     * Resolve a value (could be literal, variable reference, or expression)
     */
    private function resolveValue($value, WorkflowExecution $execution)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Check for variable reference {{variable}}
        if (preg_match_all('/\{\{([^}]+)\}\}/', $value, $matches)) {
            $result = $value;
            foreach ($matches[0] as $i => $match) {
                $var_path = trim($matches[1][$i]);
                $var_value = $this->getNestedValue($execution->variables, $var_path);

                if ($result === $match) {
                    // Entire value is a variable reference
                    return $var_value;
                }

                $result = str_replace($match, (string)$var_value, $result);
            }
            return $result;
        }

        return $value;
    }

    /**
     * Get nested value from array using dot notation
     */
    private function getNestedValue(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Resolve all values in a config array
     */
    private function resolveConfigValues(array $config, WorkflowExecution $execution): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_array($value)) {
                $resolved[$key] = $this->resolveConfigValues($value, $execution);
            } else {
                $resolved[$key] = $this->resolveValue($value, $execution);
            }
        }

        return $resolved;
    }

    /**
     * Sort array by key
     */
    private function sortArray(array $array, ?string $key, string $direction): array
    {
        if ($key) {
            usort($array, function ($a, $b) use ($key, $direction) {
                $val_a = $a[$key] ?? null;
                $val_b = $b[$key] ?? null;
                $result = $val_a <=> $val_b;
                return $direction === 'desc' ? -$result : $result;
            });
        } else {
            $direction === 'desc' ? rsort($array) : sort($array);
        }
        return $array;
    }

    /**
     * Flatten array
     */
    private function flattenArray(array $array, int $depth): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && $depth > 0) {
                $result = array_merge($result, $this->flattenArray($item, $depth - 1));
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    /**
     * Group array by key
     */
    private function groupArray(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            $group_key = $item[$key] ?? 'undefined';
            $result[$group_key][] = $item;
        }
        return $result;
    }

    /**
     * Check rate limits for workflow
     */
    private function checkRateLimits(Workflow $workflow): bool
    {
        $max_per_hour = $workflow->settings['max_executions_per_hour'] ?? 1000;
        $cache_key = "ffp_workflow_rate_{$workflow->id}";

        $count = (int)get_transient($cache_key);
        if ($count >= $max_per_hour) {
            return false;
        }

        set_transient($cache_key, $count + 1, HOUR_IN_SECONDS);
        return true;
    }

    /**
     * Schedule execution resume
     */
    private function scheduleResume(WorkflowExecution $execution, string $node_id, array $data): void
    {
        $resume_at = $data['resume_at'] ?? time() + 60;

        wp_schedule_single_event($resume_at, 'ffp_resume_execution', [
            $execution->execution_id,
            $node_id
        ]);

        $this->saveExecution($execution);
    }

    /**
     * Resume execution
     */
    public function resumeExecution(string $execution_id, string $node_id): void
    {
        $execution = $this->loadExecution($execution_id);
        if (!$execution || $execution->status !== 'running') {
            return;
        }

        $workflow = $this->loadWorkflow($execution->workflow_id);
        if (!$workflow) {
            return;
        }

        // Continue from the waiting node
        $node = $workflow->getNode($node_id);
        if ($node) {
            try {
                $this->executeFromNodes($workflow, $execution, [$node]);
                $execution->status = 'completed';
                $execution->completed_at = current_time('mysql');
            } catch (\Exception $e) {
                $execution->status = 'failed';
                $execution->error_message = $e->getMessage();
                $execution->completed_at = current_time('mysql');
            }

            $this->saveExecution($execution);
        }
    }

    /**
     * Schedule retry for failed execution
     */
    private function scheduleRetry(Workflow $workflow, WorkflowExecution $execution): void
    {
        $max_retries = $workflow->settings['max_retries'] ?? 3;
        $retry_count = $execution->context['retry_count'] ?? 0;

        if ($retry_count >= $max_retries) {
            return;
        }

        $delay = ($workflow->settings['retry_delay_seconds'] ?? 60) * pow(2, $retry_count);

        wp_schedule_single_event(time() + $delay, 'ffp_execute_workflow', [
            $workflow->id,
            $execution->trigger_data,
            array_merge($execution->context, ['retry_count' => $retry_count + 1])
        ]);
    }

    /**
     * Send failure notification
     */
    private function sendFailureNotification(Workflow $workflow, WorkflowExecution $execution): void
    {
        $email = $workflow->settings['notification_email'] ?? get_option('admin_email');

        if (empty($email)) {
            return;
        }

        $subject = sprintf(
            __('[FormFlow Pro] Workflow "%s" failed', 'form-flow-pro'),
            $workflow->name
        );

        $message = sprintf(
            __("Workflow execution failed.\n\nWorkflow: %s\nExecution ID: %s\nError: %s\nTime: %s", 'form-flow-pro'),
            $workflow->name,
            $execution->execution_id,
            $execution->error_message ?? 'Unknown error',
            current_time('mysql')
        );

        wp_mail($email, $subject, $message);
    }

    /**
     * Save execution state to database
     */
    public function saveExecution(WorkflowExecution $execution): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        $data = [
            'execution_id' => $execution->execution_id,
            'workflow_id' => $execution->workflow_id,
            'workflow_uuid' => $execution->workflow_uuid,
            'status' => $execution->status,
            'trigger_type' => $execution->trigger_type,
            'trigger_data' => wp_json_encode($execution->trigger_data),
            'context' => wp_json_encode($execution->context),
            'variables' => wp_json_encode($execution->variables),
            'node_states' => wp_json_encode($execution->node_states),
            'logs' => wp_json_encode($execution->logs),
            'error_message' => $execution->error_message,
            'started_at' => $execution->started_at,
            'completed_at' => $execution->completed_at,
            'execution_time_ms' => $execution->execution_time_ms
        ];

        $wpdb->replace($table, $data);
    }

    /**
     * Load execution from database
     */
    public function loadExecution(string $execution_id): ?WorkflowExecution
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE execution_id = %s", $execution_id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new WorkflowExecution([
            'execution_id' => $row['execution_id'],
            'workflow_id' => (int)$row['workflow_id'],
            'workflow_uuid' => $row['workflow_uuid'],
            'status' => $row['status'],
            'trigger_type' => $row['trigger_type'],
            'trigger_data' => json_decode($row['trigger_data'], true) ?: [],
            'context' => json_decode($row['context'], true) ?: [],
            'variables' => json_decode($row['variables'], true) ?: [],
            'node_states' => json_decode($row['node_states'], true) ?: [],
            'logs' => json_decode($row['logs'], true) ?: [],
            'error_message' => $row['error_message'],
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'execution_time_ms' => (int)$row['execution_time_ms']
        ]);
    }

    /**
     * Load workflow from database
     */
    public function loadWorkflow(int $workflow_id): ?Workflow
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $workflow_id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return new Workflow([
            'id' => (int)$row['id'],
            'uuid' => $row['uuid'],
            'name' => $row['name'],
            'description' => $row['description'],
            'status' => $row['status'],
            'triggers' => json_decode($row['triggers'], true) ?: [],
            'nodes' => json_decode($row['nodes'], true) ?: [],
            'connections' => json_decode($row['connections'], true) ?: [],
            'variables' => json_decode($row['variables'], true) ?: [],
            'settings' => json_decode($row['settings'], true) ?: [],
            'version' => (int)$row['version'],
            'author_id' => (int)$row['author_id'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ]);
    }

    /**
     * Cleanup old executions
     */
    public function cleanupOldExecutions(): void
    {
        global $wpdb;

        $retention_days = get_option('ffp_execution_retention_days', 30);
        $table = $wpdb->prefix . 'ffp_workflow_executions';

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
    }

    /**
     * Get execution statistics
     */
    public function getStatistics(int $workflow_id = 0, string $period = 'day'): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        $where = $workflow_id > 0 ? $wpdb->prepare("AND workflow_id = %d", $workflow_id) : "";

        $interval = match ($period) {
            'hour' => 'INTERVAL 1 HOUR',
            'day' => 'INTERVAL 24 HOUR',
            'week' => 'INTERVAL 7 DAY',
            'month' => 'INTERVAL 30 DAY',
            default => 'INTERVAL 24 HOUR'
        };

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) as running,
                AVG(execution_time_ms) as avg_time_ms,
                MAX(execution_time_ms) as max_time_ms,
                MIN(execution_time_ms) as min_time_ms
            FROM {$table}
            WHERE started_at >= DATE_SUB(NOW(), {$interval})
            {$where}
        ", ARRAY_A);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'completed' => (int)($stats['completed'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
            'running' => (int)($stats['running'] ?? 0),
            'success_rate' => $stats['total'] > 0 ? round(($stats['completed'] / $stats['total']) * 100, 2) : 0,
            'avg_time_ms' => round((float)($stats['avg_time_ms'] ?? 0), 2),
            'max_time_ms' => (int)($stats['max_time_ms'] ?? 0),
            'min_time_ms' => (int)($stats['min_time_ms'] ?? 0)
        ];
    }
}
