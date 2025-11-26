<?php
/**
 * Automation Manager - Central automation system manager
 *
 * Coordinates workflow engine, triggers, actions, and provides
 * the visual builder API and admin interface.
 *
 * @package FormFlowPro
 * @subpackage Automation
 * @since 3.0.0
 */

namespace FormFlowPro\Automation;

use FormFlowPro\Core\SingletonTrait;

/**
 * Workflow template model
 */
class WorkflowTemplate
{
    public string $id;
    public string $name;
    public string $description;
    public string $category;
    public string $icon;
    public array $workflow_data;
    public bool $is_premium;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->category = $data['category'] ?? 'general';
        $this->icon = $data['icon'] ?? 'workflow';
        $this->workflow_data = $data['workflow_data'] ?? [];
        $this->is_premium = $data['is_premium'] ?? false;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'workflow_data' => $this->workflow_data,
            'is_premium' => $this->is_premium
        ];
    }
}

/**
 * Automation Manager
 */
class AutomationManager
{
    use SingletonTrait;

    private WorkflowEngine $engine;
    private TriggerManager $trigger_manager;
    private ActionLibrary $action_library;
    private ConditionEvaluator $condition_evaluator;
    private array $templates = [];

    /**
     * Initialize automation manager
     */
    protected function init(): void
    {
        $this->engine = WorkflowEngine::getInstance();
        $this->trigger_manager = TriggerManager::getInstance();
        $this->action_library = ActionLibrary::getInstance();
        $this->condition_evaluator = ConditionEvaluator::getInstance();

        $this->registerDefaultTemplates();
        $this->registerHooks();
        $this->createDatabaseTables();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // AJAX handlers
        add_action('wp_ajax_ffp_save_workflow', [$this, 'ajaxSaveWorkflow']);
        add_action('wp_ajax_ffp_delete_workflow', [$this, 'ajaxDeleteWorkflow']);
        add_action('wp_ajax_ffp_duplicate_workflow', [$this, 'ajaxDuplicateWorkflow']);
        add_action('wp_ajax_ffp_test_workflow', [$this, 'ajaxTestWorkflow']);
        add_action('wp_ajax_ffp_get_workflow_logs', [$this, 'ajaxGetWorkflowLogs']);
        add_action('wp_ajax_ffp_export_workflow', [$this, 'ajaxExportWorkflow']);
        add_action('wp_ajax_ffp_import_workflow', [$this, 'ajaxImportWorkflow']);
    }

    /**
     * Create database tables
     */
    private function createDatabaseTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Workflows table
        $sql_workflows = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            triggers LONGTEXT,
            nodes LONGTEXT,
            connections LONGTEXT,
            variables LONGTEXT,
            settings LONGTEXT,
            version INT UNSIGNED NOT NULL DEFAULT 1,
            author_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            KEY status (status),
            KEY author_id (author_id)
        ) {$charset_collate};";

        // Workflow executions table
        $sql_executions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_workflow_executions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            execution_id VARCHAR(36) NOT NULL,
            workflow_id BIGINT UNSIGNED NOT NULL,
            workflow_uuid VARCHAR(36) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            trigger_type VARCHAR(50),
            trigger_data LONGTEXT,
            context LONGTEXT,
            variables LONGTEXT,
            node_states LONGTEXT,
            logs LONGTEXT,
            error_message TEXT,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME,
            execution_time_ms INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY execution_id (execution_id),
            KEY workflow_id (workflow_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset_collate};";

        // Workflow versions table (for history)
        $sql_versions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_workflow_versions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id BIGINT UNSIGNED NOT NULL,
            version INT UNSIGNED NOT NULL,
            workflow_data LONGTEXT NOT NULL,
            change_summary TEXT,
            author_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id_version (workflow_id, version)
        ) {$charset_collate};";

        // Only require the WordPress file if dbDelta is not already defined (allows testing)
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        dbDelta($sql_workflows);
        dbDelta($sql_executions);
        dbDelta($sql_versions);
    }

    /**
     * Register default workflow templates
     */
    private function registerDefaultTemplates(): void
    {
        // Welcome Email Template
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'welcome_email',
            'name' => __('Welcome Email', 'form-flow-pro'),
            'description' => __('Send a welcome email when a user registers', 'form-flow-pro'),
            'category' => 'user_management',
            'icon' => 'mail',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'user_action', 'config' => ['action_type' => 'register']]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 100, 'y' => 100]],
                    ['id' => 'send_email', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 100, 'y' => 250], 'config' => [
                        'to' => '{{user_email}}',
                        'subject' => __('Welcome to {{site_name}}!', 'form-flow-pro'),
                        'body' => __('<h1>Welcome {{first_name}}!</h1><p>Thank you for registering.</p>', 'form-flow-pro')
                    ]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 100, 'y' => 400]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'send_email'],
                    ['source' => 'send_email', 'target' => 'end']
                ]
            ]
        ]));

        // Form Submission Notification
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'form_notification',
            'name' => __('Form Submission Notification', 'form-flow-pro'),
            'description' => __('Send notification when a form is submitted', 'form-flow-pro'),
            'category' => 'forms',
            'icon' => 'bell',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'form_submission', 'config' => ['form_id' => 0]]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 100, 'y' => 100]],
                    ['id' => 'notify_admin', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 100, 'y' => 250], 'config' => [
                        'to' => '{{admin_email}}',
                        'subject' => __('New Form Submission', 'form-flow-pro'),
                        'body' => __('<h2>New submission received</h2><p>Form: {{form_name}}</p>', 'form-flow-pro')
                    ]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 100, 'y' => 400]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'notify_admin'],
                    ['source' => 'notify_admin', 'target' => 'end']
                ]
            ]
        ]));

        // Lead Scoring Template
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'lead_scoring',
            'name' => __('Lead Scoring', 'form-flow-pro'),
            'description' => __('Score leads based on form data and route accordingly', 'form-flow-pro'),
            'category' => 'marketing',
            'icon' => 'target',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'form_submission', 'config' => []]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50]],
                    ['id' => 'calculate_score', 'type' => 'set_variable', 'position' => ['x' => 250, 'y' => 150], 'config' => [
                        'variables' => [
                            ['name' => 'score', 'value' => '0']
                        ]
                    ]],
                    ['id' => 'check_company', 'type' => 'condition', 'position' => ['x' => 250, 'y' => 250], 'config' => [
                        'conditions' => [
                            ['expression' => '{{company_size}} > 100', 'label' => 'Large Company', 'next_node' => 'high_priority']
                        ],
                        'default_next' => 'normal_priority'
                    ]],
                    ['id' => 'high_priority', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 100, 'y' => 400], 'config' => [
                        'to' => 'sales@company.com',
                        'subject' => __('High Priority Lead!', 'form-flow-pro'),
                        'body' => __('High value lead received.', 'form-flow-pro')
                    ]],
                    ['id' => 'normal_priority', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 400, 'y' => 400], 'config' => [
                        'to' => 'marketing@company.com',
                        'subject' => __('New Lead', 'form-flow-pro'),
                        'body' => __('New lead received.', 'form-flow-pro')
                    ]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 250, 'y' => 550]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'calculate_score'],
                    ['source' => 'calculate_score', 'target' => 'check_company'],
                    ['source' => 'high_priority', 'target' => 'end'],
                    ['source' => 'normal_priority', 'target' => 'end']
                ]
            ],
            'is_premium' => true
        ]));

        // Scheduled Report Template
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'scheduled_report',
            'name' => __('Scheduled Report', 'form-flow-pro'),
            'description' => __('Generate and send a report on a schedule', 'form-flow-pro'),
            'category' => 'reporting',
            'icon' => 'calendar',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'scheduled', 'config' => ['schedule_type' => 'daily', 'time' => '09:00']]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 100, 'y' => 100]],
                    ['id' => 'fetch_data', 'type' => 'action', 'action_type' => 'database_query', 'position' => ['x' => 100, 'y' => 200], 'config' => [
                        'operation' => 'select',
                        'table' => 'submissions',
                        'output_variable' => 'report_data'
                    ]],
                    ['id' => 'send_report', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 100, 'y' => 350], 'config' => [
                        'to' => '{{admin_email}}',
                        'subject' => __('Daily Report - {{today}}', 'form-flow-pro'),
                        'body' => __('Report attached.', 'form-flow-pro')
                    ]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 100, 'y' => 500]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'fetch_data'],
                    ['source' => 'fetch_data', 'target' => 'send_report'],
                    ['source' => 'send_report', 'target' => 'end']
                ]
            ]
        ]));

        // CRM Sync Template
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'crm_sync',
            'name' => __('CRM Sync', 'form-flow-pro'),
            'description' => __('Sync form submissions to your CRM', 'form-flow-pro'),
            'category' => 'integrations',
            'icon' => 'refresh-cw',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'form_submission', 'config' => []]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 100, 'y' => 100]],
                    ['id' => 'transform', 'type' => 'transform', 'position' => ['x' => 100, 'y' => 200], 'config' => [
                        'input' => '{{submission}}',
                        'transformations' => [
                            ['type' => 'map', 'expression' => '{"email": "{{item.email}}", "name": "{{item.name}}"}']
                        ],
                        'output_variable' => 'crm_data'
                    ]],
                    ['id' => 'send_to_crm', 'type' => 'action', 'action_type' => 'http_request', 'position' => ['x' => 100, 'y' => 350], 'config' => [
                        'url' => 'https://api.crm.com/contacts',
                        'method' => 'POST',
                        'body' => '{{crm_data}}'
                    ]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 100, 'y' => 500]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'transform'],
                    ['source' => 'transform', 'target' => 'send_to_crm'],
                    ['source' => 'send_to_crm', 'target' => 'end']
                ]
            ],
            'is_premium' => true
        ]));

        // Multi-step Approval Template
        $this->registerTemplate(new WorkflowTemplate([
            'id' => 'approval_workflow',
            'name' => __('Multi-step Approval', 'form-flow-pro'),
            'description' => __('Route submissions through an approval process', 'form-flow-pro'),
            'category' => 'business',
            'icon' => 'check-circle',
            'workflow_data' => [
                'triggers' => [
                    ['type' => 'form_submission', 'config' => []]
                ],
                'nodes' => [
                    ['id' => 'start', 'type' => 'start', 'position' => ['x' => 250, 'y' => 50]],
                    ['id' => 'check_amount', 'type' => 'condition', 'position' => ['x' => 250, 'y' => 150], 'config' => [
                        'conditions' => [
                            ['expression' => '{{amount}} > 10000', 'label' => 'High Value', 'next_node' => 'manager_approval'],
                            ['expression' => '{{amount}} > 1000', 'label' => 'Medium Value', 'next_node' => 'supervisor_approval']
                        ],
                        'default_next' => 'auto_approve'
                    ]],
                    ['id' => 'manager_approval', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 100, 'y' => 300]],
                    ['id' => 'supervisor_approval', 'type' => 'action', 'action_type' => 'send_email', 'position' => ['x' => 250, 'y' => 300]],
                    ['id' => 'auto_approve', 'type' => 'action', 'action_type' => 'database_query', 'position' => ['x' => 400, 'y' => 300]],
                    ['id' => 'end', 'type' => 'end', 'position' => ['x' => 250, 'y' => 450]]
                ],
                'connections' => [
                    ['source' => 'start', 'target' => 'check_amount'],
                    ['source' => 'manager_approval', 'target' => 'end'],
                    ['source' => 'supervisor_approval', 'target' => 'end'],
                    ['source' => 'auto_approve', 'target' => 'end']
                ]
            ],
            'is_premium' => true
        ]));

        do_action('ffp_register_workflow_templates', $this);
    }

    /**
     * Register a workflow template
     */
    public function registerTemplate(WorkflowTemplate $template): void
    {
        $this->templates[$template->id] = $template;
    }

    /**
     * Get all templates
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Get templates by category
     */
    public function getTemplatesByCategory(): array
    {
        $categories = [];

        foreach ($this->templates as $template) {
            $category = $template->category;
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $template->toArray();
        }

        return $categories;
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Automations', 'form-flow-pro'),
            __('Automations', 'form-flow-pro'),
            'manage_options',
            'ffp-automations',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'ffp-automations') === false) {
            return;
        }

        wp_enqueue_style(
            'ffp-automation-builder',
            FORMFLOW_PRO_URL . 'assets/css/automation-builder.css',
            [],
            FORMFLOW_PRO_VERSION
        );

        wp_enqueue_script(
            'ffp-automation-builder',
            FORMFLOW_PRO_URL . 'assets/js/automation-builder.js',
            ['jquery', 'wp-element', 'wp-components'],
            FORMFLOW_PRO_VERSION,
            true
        );

        wp_localize_script('ffp-automation-builder', 'ffpAutomation', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('formflow/v1/automation/'),
            'nonce' => wp_create_nonce('ffp_automation'),
            'triggers' => $this->trigger_manager->getTriggersByCategory(),
            'actions' => $this->action_library->getActionsByCategory(),
            'operators' => $this->condition_evaluator->getOperatorInfo(),
            'templates' => $this->getTemplatesByCategory(),
            'strings' => [
                'save' => __('Save', 'form-flow-pro'),
                'cancel' => __('Cancel', 'form-flow-pro'),
                'delete' => __('Delete', 'form-flow-pro'),
                'duplicate' => __('Duplicate', 'form-flow-pro'),
                'test' => __('Test', 'form-flow-pro'),
                'confirm_delete' => __('Are you sure you want to delete this workflow?', 'form-flow-pro'),
                'workflow_saved' => __('Workflow saved successfully', 'form-flow-pro'),
                'workflow_deleted' => __('Workflow deleted', 'form-flow-pro')
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function renderAdminPage(): void
    {
        $workflows = $this->getWorkflows();
        $statistics = $this->engine->getStatistics();

        ?>
        <div class="wrap ffp-automations-wrap">
            <div class="ffp-automations-header">
                <h1><?php esc_html_e('Automations', 'form-flow-pro'); ?></h1>
                <div class="ffp-header-actions">
                    <button type="button" class="button button-secondary" id="ffp-import-workflow">
                        <?php esc_html_e('Import', 'form-flow-pro'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="ffp-new-workflow">
                        <?php esc_html_e('Create Workflow', 'form-flow-pro'); ?>
                    </button>
                </div>
            </div>

            <div class="ffp-automations-stats">
                <div class="stat-card">
                    <span class="stat-value"><?php echo esc_html($statistics['total']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Executions (24h)', 'form-flow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo esc_html($statistics['completed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Completed', 'form-flow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo esc_html($statistics['failed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Failed', 'form-flow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo esc_html($statistics['success_rate']); ?>%</span>
                    <span class="stat-label"><?php esc_html_e('Success Rate', 'form-flow-pro'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?php echo esc_html($statistics['avg_time_ms']); ?>ms</span>
                    <span class="stat-label"><?php esc_html_e('Avg Time', 'form-flow-pro'); ?></span>
                </div>
            </div>

            <div class="ffp-automations-content">
                <div class="ffp-workflows-list">
                    <div class="ffp-list-header">
                        <div class="ffp-search-box">
                            <input type="text" id="ffp-workflow-search" placeholder="<?php esc_attr_e('Search workflows...', 'form-flow-pro'); ?>">
                        </div>
                        <div class="ffp-filter-box">
                            <select id="ffp-workflow-status-filter">
                                <option value=""><?php esc_html_e('All Status', 'form-flow-pro'); ?></option>
                                <option value="active"><?php esc_html_e('Active', 'form-flow-pro'); ?></option>
                                <option value="draft"><?php esc_html_e('Draft', 'form-flow-pro'); ?></option>
                                <option value="paused"><?php esc_html_e('Paused', 'form-flow-pro'); ?></option>
                            </select>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Status', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Triggers', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Executions', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Last Run', 'form-flow-pro'); ?></th>
                                <th><?php esc_html_e('Actions', 'form-flow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ffp-workflows-tbody">
                            <?php if (empty($workflows)): ?>
                                <tr class="no-items">
                                    <td colspan="6">
                                        <div class="ffp-empty-state">
                                            <span class="dashicons dashicons-admin-generic"></span>
                                            <p><?php esc_html_e('No workflows yet. Create your first automation!', 'form-flow-pro'); ?></p>
                                            <button type="button" class="button button-primary" id="ffp-create-first-workflow">
                                                <?php esc_html_e('Create Workflow', 'form-flow-pro'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($workflows as $workflow): ?>
                                    <?php $this->renderWorkflowRow($workflow); ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Workflow Builder Modal -->
            <div id="ffp-workflow-builder-modal" class="ffp-modal" style="display: none;">
                <div class="ffp-modal-content ffp-workflow-builder">
                    <div class="ffp-modal-header">
                        <h2 id="ffp-builder-title"><?php esc_html_e('Create Workflow', 'form-flow-pro'); ?></h2>
                        <button type="button" class="ffp-modal-close">&times;</button>
                    </div>
                    <div class="ffp-modal-body">
                        <div id="ffp-workflow-builder-root"></div>
                    </div>
                </div>
            </div>

            <!-- Template Selector Modal -->
            <div id="ffp-template-modal" class="ffp-modal" style="display: none;">
                <div class="ffp-modal-content">
                    <div class="ffp-modal-header">
                        <h2><?php esc_html_e('Choose a Template', 'form-flow-pro'); ?></h2>
                        <button type="button" class="ffp-modal-close">&times;</button>
                    </div>
                    <div class="ffp-modal-body">
                        <div class="ffp-template-grid">
                            <div class="ffp-template-card" data-template="blank">
                                <div class="ffp-template-icon">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                </div>
                                <h3><?php esc_html_e('Blank Workflow', 'form-flow-pro'); ?></h3>
                                <p><?php esc_html_e('Start from scratch', 'form-flow-pro'); ?></p>
                            </div>
                            <?php foreach ($this->templates as $template): ?>
                                <div class="ffp-template-card<?php echo $template->is_premium ? ' premium' : ''; ?>" data-template="<?php echo esc_attr($template->id); ?>">
                                    <?php if ($template->is_premium): ?>
                                        <span class="ffp-premium-badge"><?php esc_html_e('Pro', 'form-flow-pro'); ?></span>
                                    <?php endif; ?>
                                    <div class="ffp-template-icon">
                                        <span class="dashicons dashicons-<?php echo esc_attr($template->icon); ?>"></span>
                                    </div>
                                    <h3><?php echo esc_html($template->name); ?></h3>
                                    <p><?php echo esc_html($template->description); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .ffp-automations-wrap {
                margin: 20px 20px 20px 0;
            }

            .ffp-automations-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .ffp-header-actions {
                display: flex;
                gap: 10px;
            }

            .ffp-automations-stats {
                display: grid;
                grid-template-columns: repeat(5, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .stat-card {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                text-align: center;
            }

            .stat-value {
                display: block;
                font-size: 28px;
                font-weight: 600;
                color: #1d2327;
            }

            .stat-label {
                display: block;
                font-size: 13px;
                color: #646970;
                margin-top: 4px;
            }

            .ffp-list-header {
                display: flex;
                gap: 12px;
                margin-bottom: 16px;
            }

            .ffp-search-box input {
                width: 300px;
            }

            .ffp-empty-state {
                text-align: center;
                padding: 60px 20px;
            }

            .ffp-empty-state .dashicons {
                font-size: 48px;
                width: 48px;
                height: 48px;
                color: #c3c4c7;
            }

            .ffp-empty-state p {
                margin: 16px 0;
                color: #646970;
            }

            .ffp-status-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
            }

            .ffp-status-active {
                background: #d4edda;
                color: #155724;
            }

            .ffp-status-draft {
                background: #fff3cd;
                color: #856404;
            }

            .ffp-status-paused {
                background: #e2e3e5;
                color: #383d41;
            }

            .ffp-row-actions {
                display: flex;
                gap: 8px;
            }

            .ffp-modal {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.7);
                z-index: 100000;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .ffp-modal-content {
                background: #fff;
                border-radius: 12px;
                max-width: 90vw;
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
            }

            .ffp-workflow-builder {
                width: 95vw;
                height: 90vh;
            }

            .ffp-modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 24px;
                border-bottom: 1px solid #dcdcde;
            }

            .ffp-modal-header h2 {
                margin: 0;
            }

            .ffp-modal-close {
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: #646970;
            }

            .ffp-modal-body {
                flex: 1;
                overflow: auto;
                padding: 24px;
            }

            .ffp-template-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
                gap: 16px;
            }

            .ffp-template-card {
                position: relative;
                background: #f6f7f7;
                border: 2px solid transparent;
                border-radius: 8px;
                padding: 24px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s;
            }

            .ffp-template-card:hover {
                border-color: #2271b1;
                background: #fff;
            }

            .ffp-template-card.premium {
                background: linear-gradient(135deg, #f0f6fc 0%, #fff 100%);
            }

            .ffp-premium-badge {
                position: absolute;
                top: 8px;
                right: 8px;
                background: #2271b1;
                color: #fff;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: 600;
            }

            .ffp-template-icon {
                margin-bottom: 12px;
            }

            .ffp-template-icon .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: #2271b1;
            }

            .ffp-template-card h3 {
                margin: 0 0 8px 0;
                font-size: 14px;
            }

            .ffp-template-card p {
                margin: 0;
                font-size: 12px;
                color: #646970;
            }
        </style>
        <?php
    }

    /**
     * Render workflow table row
     */
    private function renderWorkflowRow(array $workflow): void
    {
        $triggers = json_decode($workflow['triggers'] ?? '[]', true);
        $trigger_types = array_column($triggers, 'type');
        $last_execution = $this->getLastExecution($workflow['id']);

        ?>
        <tr data-workflow-id="<?php echo esc_attr($workflow['id']); ?>">
            <td>
                <strong>
                    <a href="#" class="ffp-edit-workflow" data-id="<?php echo esc_attr($workflow['id']); ?>">
                        <?php echo esc_html($workflow['name']); ?>
                    </a>
                </strong>
                <?php if (!empty($workflow['description'])): ?>
                    <p class="description"><?php echo esc_html(wp_trim_words($workflow['description'], 10)); ?></p>
                <?php endif; ?>
            </td>
            <td>
                <span class="ffp-status-badge ffp-status-<?php echo esc_attr($workflow['status']); ?>">
                    <?php echo esc_html(ucfirst($workflow['status'])); ?>
                </span>
            </td>
            <td>
                <?php if (!empty($trigger_types)): ?>
                    <?php echo esc_html(implode(', ', $trigger_types)); ?>
                <?php else: ?>
                    <em><?php esc_html_e('No triggers', 'form-flow-pro'); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <?php echo esc_html($this->getExecutionCount($workflow['id'])); ?>
            </td>
            <td>
                <?php if ($last_execution): ?>
                    <?php echo esc_html(human_time_diff(strtotime($last_execution), time())); ?>
                    <?php esc_html_e('ago', 'form-flow-pro'); ?>
                <?php else: ?>
                    <em><?php esc_html_e('Never', 'form-flow-pro'); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <div class="ffp-row-actions">
                    <button type="button" class="button button-small ffp-edit-workflow" data-id="<?php echo esc_attr($workflow['id']); ?>">
                        <?php esc_html_e('Edit', 'form-flow-pro'); ?>
                    </button>
                    <?php if ($workflow['status'] === 'active'): ?>
                        <button type="button" class="button button-small ffp-pause-workflow" data-id="<?php echo esc_attr($workflow['id']); ?>">
                            <?php esc_html_e('Pause', 'form-flow-pro'); ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="button button-small ffp-activate-workflow" data-id="<?php echo esc_attr($workflow['id']); ?>">
                            <?php esc_html_e('Activate', 'form-flow-pro'); ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="button button-small ffp-delete-workflow" data-id="<?php echo esc_attr($workflow['id']); ?>">
                        <?php esc_html_e('Delete', 'form-flow-pro'); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/automation/workflows', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetWorkflows'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateWorkflow'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ]
        ]);

        register_rest_route('formflow/v1', '/automation/workflows/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetWorkflow'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'restUpdateWorkflow'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteWorkflow'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                }
            ]
        ]);

        register_rest_route('formflow/v1', '/automation/workflows/(?P<id>\d+)/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'restExecuteWorkflow'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/automation/workflows/(?P<id>\d+)/executions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetExecutions'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/automation/triggers', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTriggers'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/automation/actions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetActions'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/automation/templates', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetTemplates'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);
    }

    // ==================== REST Handlers ====================

    public function restGetWorkflows(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['workflows' => $this->getWorkflows()]);
    }

    public function restGetWorkflow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $workflow = $this->getWorkflow($id);

        if (!$workflow) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        return new \WP_REST_Response(['workflow' => $workflow]);
    }

    public function restCreateWorkflow(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $id = $this->saveWorkflow($data);

        return new \WP_REST_Response(['id' => $id, 'success' => true], 201);
    }

    public function restUpdateWorkflow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = $request->get_json_params();
        $data['id'] = $id;

        $this->saveWorkflow($data);

        return new \WP_REST_Response(['success' => true]);
    }

    public function restDeleteWorkflow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $this->deleteWorkflow($id);

        return new \WP_REST_Response(['success' => true]);
    }

    public function restExecuteWorkflow(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $data = $request->get_json_params();

        $workflow = $this->engine->loadWorkflow($id);

        if (!$workflow) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        try {
            $execution = $this->engine->execute($workflow, $data['variables'] ?? [], [
                'trigger_type' => 'manual'
            ]);

            return new \WP_REST_Response([
                'success' => $execution->status === 'completed',
                'execution_id' => $execution->execution_id,
                'status' => $execution->status,
                'output' => $execution->variables
            ]);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function restGetExecutions(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int)$request->get_param('id');
        $executions = $this->getExecutions($id);

        return new \WP_REST_Response(['executions' => $executions]);
    }

    public function restGetTriggers(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'triggers' => $this->trigger_manager->getTriggersByCategory()
        ]);
    }

    public function restGetActions(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'actions' => $this->action_library->getActionsByCategory()
        ]);
    }

    public function restGetTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'templates' => $this->getTemplatesByCategory()
        ]);
    }

    // ==================== Data Methods ====================

    /**
     * Get all workflows
     */
    public function getWorkflows(array $args = []): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';
        $where = [];

        if (!empty($args['status'])) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        return $wpdb->get_results(
            "SELECT * FROM {$table} {$where_clause} ORDER BY updated_at DESC",
            ARRAY_A
        );
    }

    /**
     * Get single workflow
     */
    public function getWorkflow(int $id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Save workflow
     */
    public function saveWorkflow(array $data): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';
        $id = $data['id'] ?? 0;

        $row = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => sanitize_key($data['status'] ?? 'draft'),
            'triggers' => wp_json_encode($data['triggers'] ?? []),
            'nodes' => wp_json_encode($data['nodes'] ?? []),
            'connections' => wp_json_encode($data['connections'] ?? []),
            'variables' => wp_json_encode($data['variables'] ?? []),
            'settings' => wp_json_encode($data['settings'] ?? []),
            'author_id' => get_current_user_id()
        ];

        if ($id > 0) {
            // Update
            $row['version'] = ($data['version'] ?? 1) + 1;
            $wpdb->update($table, $row, ['id' => $id]);

            // Save version history
            $this->saveWorkflowVersion($id, $data);
        } else {
            // Insert
            $row['uuid'] = wp_generate_uuid4();
            $row['version'] = 1;
            $wpdb->insert($table, $row);
            $id = $wpdb->insert_id;
        }

        // Register triggers
        if (!empty($data['triggers'])) {
            $this->trigger_manager->unregisterWorkflowTriggers($id);

            foreach ($data['triggers'] as $trigger) {
                $this->trigger_manager->registerWorkflowTrigger(
                    $id,
                    $trigger['type'],
                    $trigger['config'] ?? []
                );
            }
        }

        return $id;
    }

    /**
     * Save workflow version
     */
    private function saveWorkflowVersion(int $workflow_id, array $data): void
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'ffp_workflow_versions', [
            'workflow_id' => $workflow_id,
            'version' => $data['version'] ?? 1,
            'workflow_data' => wp_json_encode($data),
            'change_summary' => $data['change_summary'] ?? '',
            'author_id' => get_current_user_id()
        ]);
    }

    /**
     * Delete workflow
     */
    public function deleteWorkflow(int $id): void
    {
        global $wpdb;

        // Unregister triggers
        $this->trigger_manager->unregisterWorkflowTriggers($id);

        // Delete workflow
        $wpdb->delete($wpdb->prefix . 'ffp_workflows', ['id' => $id]);

        // Delete executions
        $wpdb->delete($wpdb->prefix . 'ffp_workflow_executions', ['workflow_id' => $id]);

        // Delete versions
        $wpdb->delete($wpdb->prefix . 'ffp_workflow_versions', ['workflow_id' => $id]);
    }

    /**
     * Get executions for workflow
     */
    public function getExecutions(int $workflow_id, int $limit = 50): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE workflow_id = %d ORDER BY started_at DESC LIMIT %d",
            $workflow_id,
            $limit
        ), ARRAY_A);
    }

    /**
     * Get execution count for workflow
     */
    private function getExecutionCount(int $workflow_id): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE workflow_id = %d",
            $workflow_id
        ));
    }

    /**
     * Get last execution time for workflow
     */
    private function getLastExecution(int $workflow_id): ?string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflow_executions';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT started_at FROM {$table} WHERE workflow_id = %d ORDER BY started_at DESC LIMIT 1",
            $workflow_id
        ));
    }

    // ==================== AJAX Handlers ====================

    public function ajaxSaveWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $data = json_decode(stripslashes($_POST['workflow'] ?? '{}'), true);
        $id = $this->saveWorkflow($data);

        wp_send_json_success(['id' => $id]);
    }

    public function ajaxDeleteWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['workflow_id'] ?? 0);
        $this->deleteWorkflow($id);

        wp_send_json_success();
    }

    public function ajaxDuplicateWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['workflow_id'] ?? 0);
        $workflow = $this->getWorkflow($id);

        if (!$workflow) {
            wp_send_json_error(['message' => 'Workflow not found']);
        }

        unset($workflow['id']);
        $workflow['name'] .= ' (Copy)';
        $workflow['status'] = 'draft';
        $workflow['triggers'] = json_decode($workflow['triggers'], true);
        $workflow['nodes'] = json_decode($workflow['nodes'], true);
        $workflow['connections'] = json_decode($workflow['connections'], true);
        $workflow['variables'] = json_decode($workflow['variables'], true);
        $workflow['settings'] = json_decode($workflow['settings'], true);

        $new_id = $this->saveWorkflow($workflow);

        wp_send_json_success(['id' => $new_id]);
    }

    public function ajaxTestWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['workflow_id'] ?? 0);
        $test_data = json_decode(stripslashes($_POST['test_data'] ?? '{}'), true);

        $workflow = $this->engine->loadWorkflow($id);

        if (!$workflow) {
            wp_send_json_error(['message' => 'Workflow not found']);
        }

        try {
            // Temporarily activate for testing
            $original_status = $workflow->status;
            $workflow->status = 'active';

            $execution = $this->engine->execute($workflow, $test_data, [
                'trigger_type' => 'test'
            ]);

            wp_send_json_success([
                'execution_id' => $execution->execution_id,
                'status' => $execution->status,
                'logs' => $execution->logs,
                'output' => $execution->variables,
                'error' => $execution->error_message
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajaxGetWorkflowLogs(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['workflow_id'] ?? 0);
        $executions = $this->getExecutions($id, 100);

        wp_send_json_success(['executions' => $executions]);
    }

    public function ajaxExportWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $id = intval($_POST['workflow_id'] ?? 0);
        $workflow = $this->getWorkflow($id);

        if (!$workflow) {
            wp_send_json_error(['message' => 'Workflow not found']);
        }

        // Prepare export data
        $export = [
            'name' => $workflow['name'],
            'description' => $workflow['description'],
            'triggers' => json_decode($workflow['triggers'], true),
            'nodes' => json_decode($workflow['nodes'], true),
            'connections' => json_decode($workflow['connections'], true),
            'variables' => json_decode($workflow['variables'], true),
            'settings' => json_decode($workflow['settings'], true),
            'version' => $workflow['version'],
            'exported_at' => current_time('c'),
            'plugin_version' => FORMFLOW_PRO_VERSION
        ];

        wp_send_json_success(['export' => $export]);
    }

    public function ajaxImportWorkflow(): void
    {
        check_ajax_referer('ffp_automation', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $import_data = json_decode(stripslashes($_POST['import_data'] ?? '{}'), true);

        if (empty($import_data['name'])) {
            wp_send_json_error(['message' => 'Invalid import data']);
        }

        $import_data['status'] = 'draft';
        $id = $this->saveWorkflow($import_data);

        wp_send_json_success(['id' => $id]);
    }
}
