<?php
/**
 * Trigger Manager - Event-based workflow trigger system
 *
 * Manages workflow triggers including form submissions, webhooks,
 * scheduled events, database changes, and custom triggers.
 *
 * @package FormFlowPro
 * @subpackage Automation
 * @since 3.0.0
 */

namespace FormFlowPro\Automation;

use FormFlowPro\Core\SingletonTrait;

/**
 * Trigger definition interface
 */
interface TriggerInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getCategory(): string;
    public function getConfigSchema(): array;
    public function validateConfig(array $config): bool;
    public function register(int $workflow_id, array $config): void;
    public function unregister(int $workflow_id): void;
}

/**
 * Abstract base trigger
 */
abstract class AbstractTrigger implements TriggerInterface
{
    protected string $id;
    protected string $name;
    protected string $description;
    protected string $category;
    protected string $icon;

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'icon' => $this->icon,
            'config_schema' => $this->getConfigSchema()
        ];
    }

    public function validateConfig(array $config): bool
    {
        return true;
    }
}

/**
 * Form submission trigger
 */
class FormSubmissionTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'form_submission';
        $this->name = __('Form Submission', 'form-flow-pro');
        $this->description = __('Triggers when a form is submitted', 'form-flow-pro');
        $this->category = 'forms';
        $this->icon = 'file-text';
    }

    public function getConfigSchema(): array
    {
        return [
            'form_id' => [
                'type' => 'select',
                'label' => __('Form', 'form-flow-pro'),
                'required' => true,
                'options_source' => 'forms',
                'description' => __('Select the form to monitor', 'form-flow-pro')
            ],
            'conditions' => [
                'type' => 'conditions',
                'label' => __('Conditions', 'form-flow-pro'),
                'required' => false,
                'description' => __('Optional conditions to filter submissions', 'form-flow-pro')
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $form_id = $config['form_id'] ?? 0;

        // Store workflow association
        $associations = get_option('ffp_form_workflow_triggers', []);
        if (!isset($associations[$form_id])) {
            $associations[$form_id] = [];
        }
        $associations[$form_id][$workflow_id] = $config;
        update_option('ffp_form_workflow_triggers', $associations);
    }

    public function unregister(int $workflow_id): void
    {
        $associations = get_option('ffp_form_workflow_triggers', []);

        foreach ($associations as $form_id => $workflows) {
            if (isset($workflows[$workflow_id])) {
                unset($associations[$form_id][$workflow_id]);
            }
        }

        update_option('ffp_form_workflow_triggers', $associations);
    }
}

/**
 * Scheduled trigger (cron-based)
 */
class ScheduledTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'scheduled';
        $this->name = __('Scheduled', 'form-flow-pro');
        $this->description = __('Triggers on a schedule (hourly, daily, weekly, etc.)', 'form-flow-pro');
        $this->category = 'time';
        $this->icon = 'clock';
    }

    public function getConfigSchema(): array
    {
        return [
            'schedule_type' => [
                'type' => 'select',
                'label' => __('Schedule Type', 'form-flow-pro'),
                'required' => true,
                'options' => [
                    'interval' => __('Interval', 'form-flow-pro'),
                    'daily' => __('Daily at specific time', 'form-flow-pro'),
                    'weekly' => __('Weekly on specific day', 'form-flow-pro'),
                    'monthly' => __('Monthly on specific date', 'form-flow-pro'),
                    'cron' => __('Custom cron expression', 'form-flow-pro')
                ]
            ],
            'interval_minutes' => [
                'type' => 'number',
                'label' => __('Interval (minutes)', 'form-flow-pro'),
                'required' => false,
                'min' => 5,
                'max' => 10080,
                'condition' => ['schedule_type' => 'interval']
            ],
            'time' => [
                'type' => 'time',
                'label' => __('Time', 'form-flow-pro'),
                'required' => false,
                'condition' => ['schedule_type' => ['daily', 'weekly', 'monthly']]
            ],
            'day_of_week' => [
                'type' => 'select',
                'label' => __('Day of Week', 'form-flow-pro'),
                'required' => false,
                'options' => [
                    '1' => __('Monday', 'form-flow-pro'),
                    '2' => __('Tuesday', 'form-flow-pro'),
                    '3' => __('Wednesday', 'form-flow-pro'),
                    '4' => __('Thursday', 'form-flow-pro'),
                    '5' => __('Friday', 'form-flow-pro'),
                    '6' => __('Saturday', 'form-flow-pro'),
                    '0' => __('Sunday', 'form-flow-pro')
                ],
                'condition' => ['schedule_type' => 'weekly']
            ],
            'day_of_month' => [
                'type' => 'number',
                'label' => __('Day of Month', 'form-flow-pro'),
                'required' => false,
                'min' => 1,
                'max' => 31,
                'condition' => ['schedule_type' => 'monthly']
            ],
            'cron_expression' => [
                'type' => 'text',
                'label' => __('Cron Expression', 'form-flow-pro'),
                'required' => false,
                'placeholder' => '0 */6 * * *',
                'condition' => ['schedule_type' => 'cron']
            ],
            'timezone' => [
                'type' => 'select',
                'label' => __('Timezone', 'form-flow-pro'),
                'required' => false,
                'options_source' => 'timezones',
                'default' => wp_timezone_string()
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $schedule_type = $config['schedule_type'] ?? 'daily';
        $hook = "ffp_scheduled_workflow_{$workflow_id}";

        // Clear existing schedule
        wp_clear_scheduled_hook($hook);

        $timestamp = $this->calculateNextRun($config);

        if ($schedule_type === 'interval') {
            $interval = ($config['interval_minutes'] ?? 60) * 60;
            wp_schedule_event($timestamp, 'ffp_custom_interval', $hook);

            // Register custom interval
            add_filter('cron_schedules', function ($schedules) use ($interval, $workflow_id) {
                $schedules["ffp_interval_{$workflow_id}"] = [
                    'interval' => $interval,
                    'display' => "Every {$interval} seconds"
                ];
                return $schedules;
            });
        } else {
            // Single event that reschedules itself
            wp_schedule_single_event($timestamp, $hook);
        }

        // Store schedule config
        update_option("ffp_schedule_config_{$workflow_id}", $config);
    }

    public function unregister(int $workflow_id): void
    {
        $hook = "ffp_scheduled_workflow_{$workflow_id}";
        wp_clear_scheduled_hook($hook);
        delete_option("ffp_schedule_config_{$workflow_id}");
    }

    private function calculateNextRun(array $config): int
    {
        $schedule_type = $config['schedule_type'] ?? 'daily';
        $timezone = new \DateTimeZone($config['timezone'] ?? wp_timezone_string());
        $now = new \DateTime('now', $timezone);

        switch ($schedule_type) {
            case 'interval':
                return time() + (($config['interval_minutes'] ?? 60) * 60);

            case 'daily':
                $time = $config['time'] ?? '09:00';
                $target = \DateTime::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $time, $timezone);
                if ($target <= $now) {
                    $target->modify('+1 day');
                }
                return $target->getTimestamp();

            case 'weekly':
                $time = $config['time'] ?? '09:00';
                $day = $config['day_of_week'] ?? 1;
                $target = new \DateTime('now', $timezone);
                $timeParts = explode(':', $time);
                $target->setTime((int) ($timeParts[0] ?? 0), (int) ($timeParts[1] ?? 0));

                while ($target->format('w') != $day || $target <= $now) {
                    $target->modify('+1 day');
                }
                return $target->getTimestamp();

            case 'monthly':
                $time = $config['time'] ?? '09:00';
                $day = min($config['day_of_month'] ?? 1, 28);
                $target = new \DateTime($now->format('Y-m-') . sprintf('%02d', $day) . ' ' . $time, $timezone);

                if ($target <= $now) {
                    $target->modify('+1 month');
                }
                return $target->getTimestamp();

            case 'cron':
                // Parse cron expression and calculate next run
                return $this->getNextCronRun($config['cron_expression'] ?? '0 0 * * *', $timezone);

            default:
                return time() + 3600;
        }
    }

    private function getNextCronRun(string $expression, \DateTimeZone $timezone): int
    {
        // Simple cron parser for common expressions
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            return time() + 3600;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $now = new \DateTime('now', $timezone);
        $target = clone $now;

        // Simple implementation - advance until we match
        for ($i = 0; $i < 527040; $i++) { // Max 1 year of minutes
            $target->modify('+1 minute');

            if ($this->matchesCronField($minute, (int)$target->format('i')) &&
                $this->matchesCronField($hour, (int)$target->format('G')) &&
                $this->matchesCronField($day, (int)$target->format('j')) &&
                $this->matchesCronField($month, (int)$target->format('n')) &&
                $this->matchesCronField($weekday, (int)$target->format('w'))) {
                return $target->getTimestamp();
            }
        }

        return time() + 3600;
    }

    private function matchesCronField(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        if (strpos($field, '/') !== false) {
            [$range, $step] = explode('/', $field);
            $step = (int)$step;
            return $value % $step === 0;
        }

        if (strpos($field, ',') !== false) {
            $values = array_map('intval', explode(',', $field));
            return in_array($value, $values);
        }

        if (strpos($field, '-') !== false) {
            [$min, $max] = array_map('intval', explode('-', $field));
            return $value >= $min && $value <= $max;
        }

        return (int)$field === $value;
    }
}

/**
 * Webhook trigger
 */
class WebhookTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'webhook';
        $this->name = __('Incoming Webhook', 'form-flow-pro');
        $this->description = __('Triggers when a webhook is received', 'form-flow-pro');
        $this->category = 'integrations';
        $this->icon = 'link';
    }

    public function getConfigSchema(): array
    {
        return [
            'webhook_url' => [
                'type' => 'readonly',
                'label' => __('Webhook URL', 'form-flow-pro'),
                'description' => __('Send POST requests to this URL to trigger the workflow', 'form-flow-pro'),
                'generator' => 'webhook_url'
            ],
            'secret_key' => [
                'type' => 'password',
                'label' => __('Secret Key', 'form-flow-pro'),
                'required' => false,
                'description' => __('Optional secret for signature verification', 'form-flow-pro'),
                'generator' => 'random_key'
            ],
            'allowed_ips' => [
                'type' => 'textarea',
                'label' => __('Allowed IPs', 'form-flow-pro'),
                'required' => false,
                'placeholder' => '192.168.1.1\n10.0.0.0/8',
                'description' => __('One IP or CIDR range per line (empty = allow all)', 'form-flow-pro')
            ],
            'required_headers' => [
                'type' => 'keyvalue',
                'label' => __('Required Headers', 'form-flow-pro'),
                'required' => false,
                'description' => __('Headers that must be present in the request', 'form-flow-pro')
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $webhook_key = $config['webhook_key'] ?? wp_generate_password(32, false);

        // Store webhook config
        $webhooks = get_option('ffp_workflow_webhooks', []);
        $webhooks[$webhook_key] = [
            'workflow_id' => $workflow_id,
            'secret_key' => $config['secret_key'] ?? '',
            'allowed_ips' => $config['allowed_ips'] ?? '',
            'required_headers' => $config['required_headers'] ?? [],
            'created_at' => current_time('mysql')
        ];
        update_option('ffp_workflow_webhooks', $webhooks);

        // Store key reference for workflow
        update_option("ffp_workflow_webhook_key_{$workflow_id}", $webhook_key);
    }

    public function unregister(int $workflow_id): void
    {
        $webhook_key = get_option("ffp_workflow_webhook_key_{$workflow_id}");

        if ($webhook_key) {
            $webhooks = get_option('ffp_workflow_webhooks', []);
            unset($webhooks[$webhook_key]);
            update_option('ffp_workflow_webhooks', $webhooks);
            delete_option("ffp_workflow_webhook_key_{$workflow_id}");
        }
    }

    public function getWebhookUrl(int $workflow_id): string
    {
        $webhook_key = get_option("ffp_workflow_webhook_key_{$workflow_id}");
        return rest_url("formflow/v1/webhook/{$webhook_key}");
    }
}

/**
 * Database change trigger
 */
class DatabaseChangeTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'database_change';
        $this->name = __('Database Change', 'form-flow-pro');
        $this->description = __('Triggers when database records are created, updated, or deleted', 'form-flow-pro');
        $this->category = 'data';
        $this->icon = 'database';
    }

    public function getConfigSchema(): array
    {
        return [
            'table' => [
                'type' => 'select',
                'label' => __('Table', 'form-flow-pro'),
                'required' => true,
                'options' => [
                    'submissions' => __('Form Submissions', 'form-flow-pro'),
                    'users' => __('WordPress Users', 'form-flow-pro'),
                    'posts' => __('Posts', 'form-flow-pro'),
                    'comments' => __('Comments', 'form-flow-pro'),
                    'custom' => __('Custom Table', 'form-flow-pro')
                ]
            ],
            'custom_table' => [
                'type' => 'text',
                'label' => __('Custom Table Name', 'form-flow-pro'),
                'required' => false,
                'condition' => ['table' => 'custom']
            ],
            'event_type' => [
                'type' => 'multiselect',
                'label' => __('Event Types', 'form-flow-pro'),
                'required' => true,
                'options' => [
                    'insert' => __('Insert (new record)', 'form-flow-pro'),
                    'update' => __('Update (record modified)', 'form-flow-pro'),
                    'delete' => __('Delete (record removed)', 'form-flow-pro')
                ]
            ],
            'conditions' => [
                'type' => 'conditions',
                'label' => __('Conditions', 'form-flow-pro'),
                'required' => false,
                'description' => __('Only trigger when these conditions are met', 'form-flow-pro')
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $triggers = get_option('ffp_db_change_triggers', []);
        $triggers[$workflow_id] = $config;
        update_option('ffp_db_change_triggers', $triggers);
    }

    public function unregister(int $workflow_id): void
    {
        $triggers = get_option('ffp_db_change_triggers', []);
        unset($triggers[$workflow_id]);
        update_option('ffp_db_change_triggers', $triggers);
    }
}

/**
 * WordPress hook trigger
 */
class WordPressHookTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'wordpress_hook';
        $this->name = __('WordPress Hook', 'form-flow-pro');
        $this->description = __('Triggers on WordPress actions or filters', 'form-flow-pro');
        $this->category = 'wordpress';
        $this->icon = 'code';
    }

    public function getConfigSchema(): array
    {
        return [
            'hook_type' => [
                'type' => 'select',
                'label' => __('Hook Type', 'form-flow-pro'),
                'required' => true,
                'options' => [
                    'action' => __('Action', 'form-flow-pro'),
                    'filter' => __('Filter', 'form-flow-pro')
                ]
            ],
            'hook_name' => [
                'type' => 'text',
                'label' => __('Hook Name', 'form-flow-pro'),
                'required' => true,
                'placeholder' => 'save_post'
            ],
            'priority' => [
                'type' => 'number',
                'label' => __('Priority', 'form-flow-pro'),
                'required' => false,
                'default' => 10
            ],
            'accepted_args' => [
                'type' => 'number',
                'label' => __('Number of Arguments', 'form-flow-pro'),
                'required' => false,
                'default' => 1
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        // Store for runtime registration
        $hooks = get_option('ffp_wp_hook_triggers', []);
        $hooks[$workflow_id] = $config;
        update_option('ffp_wp_hook_triggers', $hooks);
    }

    public function unregister(int $workflow_id): void
    {
        $hooks = get_option('ffp_wp_hook_triggers', []);
        unset($hooks[$workflow_id]);
        update_option('ffp_wp_hook_triggers', $hooks);
    }
}

/**
 * User action trigger
 */
class UserActionTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'user_action';
        $this->name = __('User Action', 'form-flow-pro');
        $this->description = __('Triggers on user login, registration, role change, etc.', 'form-flow-pro');
        $this->category = 'users';
        $this->icon = 'user';
    }

    public function getConfigSchema(): array
    {
        return [
            'action_type' => [
                'type' => 'select',
                'label' => __('Action', 'form-flow-pro'),
                'required' => true,
                'options' => [
                    'login' => __('User Login', 'form-flow-pro'),
                    'logout' => __('User Logout', 'form-flow-pro'),
                    'register' => __('User Registration', 'form-flow-pro'),
                    'profile_update' => __('Profile Update', 'form-flow-pro'),
                    'role_change' => __('Role Change', 'form-flow-pro'),
                    'password_reset' => __('Password Reset', 'form-flow-pro'),
                    'delete' => __('User Deleted', 'form-flow-pro')
                ]
            ],
            'user_roles' => [
                'type' => 'multiselect',
                'label' => __('User Roles', 'form-flow-pro'),
                'required' => false,
                'options_source' => 'user_roles',
                'description' => __('Only trigger for users with these roles (empty = all)', 'form-flow-pro')
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $triggers = get_option('ffp_user_action_triggers', []);
        $triggers[$workflow_id] = $config;
        update_option('ffp_user_action_triggers', $triggers);
    }

    public function unregister(int $workflow_id): void
    {
        $triggers = get_option('ffp_user_action_triggers', []);
        unset($triggers[$workflow_id]);
        update_option('ffp_user_action_triggers', $triggers);
    }
}

/**
 * Manual trigger (button/API call)
 */
class ManualTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'manual';
        $this->name = __('Manual / API', 'form-flow-pro');
        $this->description = __('Trigger manually via button or API call', 'form-flow-pro');
        $this->category = 'other';
        $this->icon = 'play';
    }

    public function getConfigSchema(): array
    {
        return [
            'input_fields' => [
                'type' => 'repeater',
                'label' => __('Input Fields', 'form-flow-pro'),
                'required' => false,
                'fields' => [
                    'name' => ['type' => 'text', 'label' => __('Field Name', 'form-flow-pro')],
                    'type' => [
                        'type' => 'select',
                        'label' => __('Type', 'form-flow-pro'),
                        'options' => ['text', 'number', 'email', 'date', 'select', 'boolean']
                    ],
                    'required' => ['type' => 'checkbox', 'label' => __('Required', 'form-flow-pro')],
                    'default' => ['type' => 'text', 'label' => __('Default Value', 'form-flow-pro')]
                ]
            ],
            'require_confirmation' => [
                'type' => 'checkbox',
                'label' => __('Require Confirmation', 'form-flow-pro'),
                'default' => true
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        // Manual triggers don't need registration
        update_option("ffp_manual_trigger_config_{$workflow_id}", $config);
    }

    public function unregister(int $workflow_id): void
    {
        delete_option("ffp_manual_trigger_config_{$workflow_id}");
    }
}

/**
 * Email received trigger
 */
class EmailReceivedTrigger extends AbstractTrigger
{
    public function __construct()
    {
        $this->id = 'email_received';
        $this->name = __('Email Received', 'form-flow-pro');
        $this->description = __('Triggers when an email is received at a specific address', 'form-flow-pro');
        $this->category = 'integrations';
        $this->icon = 'mail';
    }

    public function getConfigSchema(): array
    {
        return [
            'email_address' => [
                'type' => 'readonly',
                'label' => __('Dedicated Email', 'form-flow-pro'),
                'generator' => 'workflow_email',
                'description' => __('Forward emails to this address to trigger the workflow', 'form-flow-pro')
            ],
            'from_filter' => [
                'type' => 'text',
                'label' => __('From Filter', 'form-flow-pro'),
                'required' => false,
                'placeholder' => '*@example.com',
                'description' => __('Only process emails from matching addresses', 'form-flow-pro')
            ],
            'subject_filter' => [
                'type' => 'text',
                'label' => __('Subject Filter', 'form-flow-pro'),
                'required' => false,
                'placeholder' => '[TICKET]*',
                'description' => __('Only process emails with matching subject', 'form-flow-pro')
            ],
            'parse_attachments' => [
                'type' => 'checkbox',
                'label' => __('Parse Attachments', 'form-flow-pro'),
                'default' => true
            ]
        ];
    }

    public function register(int $workflow_id, array $config): void
    {
        $triggers = get_option('ffp_email_triggers', []);
        $triggers[$workflow_id] = $config;
        update_option('ffp_email_triggers', $triggers);
    }

    public function unregister(int $workflow_id): void
    {
        $triggers = get_option('ffp_email_triggers', []);
        unset($triggers[$workflow_id]);
        update_option('ffp_email_triggers', $triggers);
    }
}

/**
 * Trigger Manager - Central trigger management
 */
class TriggerManager
{
    use SingletonTrait;

    private array $triggers = [];
    private WorkflowEngine $engine;

    /**
     * Initialize trigger manager
     */
    protected function init(): void
    {
        $this->engine = WorkflowEngine::getInstance();

        $this->registerCoreTriggers();
        $this->registerHooks();
    }

    /**
     * Register core triggers
     */
    private function registerCoreTriggers(): void
    {
        $this->registerTrigger(new FormSubmissionTrigger());
        $this->registerTrigger(new ScheduledTrigger());
        $this->registerTrigger(new WebhookTrigger());
        $this->registerTrigger(new DatabaseChangeTrigger());
        $this->registerTrigger(new WordPressHookTrigger());
        $this->registerTrigger(new UserActionTrigger());
        $this->registerTrigger(new ManualTrigger());
        $this->registerTrigger(new EmailReceivedTrigger());

        do_action('ffp_register_triggers', $this);
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Form submission
        add_action('ffp_form_submitted', [$this, 'handleFormSubmission'], 10, 2);

        // User actions
        add_action('wp_login', [$this, 'handleUserLogin'], 10, 2);
        add_action('user_register', [$this, 'handleUserRegister']);
        add_action('profile_update', [$this, 'handleProfileUpdate'], 10, 2);
        add_action('set_user_role', [$this, 'handleRoleChange'], 10, 3);
        add_action('delete_user', [$this, 'handleUserDelete']);
        add_action('wp_logout', [$this, 'handleUserLogout']);
        add_action('after_password_reset', [$this, 'handlePasswordReset']);

        // Database changes
        add_action('ffp_submission_created', [$this, 'handleSubmissionCreated']);
        add_action('ffp_submission_updated', [$this, 'handleSubmissionUpdated']);
        add_action('ffp_submission_deleted', [$this, 'handleSubmissionDeleted']);

        // Scheduled triggers
        add_action('init', [$this, 'registerScheduledTriggers']);

        // WordPress hook triggers
        add_action('init', [$this, 'registerWordPressHookTriggers']);

        // REST API for webhooks
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register a trigger type
     */
    public function registerTrigger(TriggerInterface $trigger): void
    {
        $this->triggers[$trigger->getId()] = $trigger;
    }

    /**
     * Get all registered triggers
     */
    public function getTriggers(): array
    {
        return $this->triggers;
    }

    /**
     * Get trigger by ID
     */
    public function getTrigger(string $id): ?TriggerInterface
    {
        return $this->triggers[$id] ?? null;
    }

    /**
     * Get triggers by category
     */
    public function getTriggersByCategory(): array
    {
        $categories = [];

        foreach ($this->triggers as $trigger) {
            $category = $trigger->getCategory();
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $trigger->toArray();
        }

        return $categories;
    }

    /**
     * Register trigger for a workflow
     */
    public function registerWorkflowTrigger(int $workflow_id, string $trigger_id, array $config): bool
    {
        $trigger = $this->getTrigger($trigger_id);

        if (!$trigger) {
            return false;
        }

        if (!$trigger->validateConfig($config)) {
            return false;
        }

        $trigger->register($workflow_id, $config);

        return true;
    }

    /**
     * Unregister all triggers for a workflow
     */
    public function unregisterWorkflowTriggers(int $workflow_id): void
    {
        foreach ($this->triggers as $trigger) {
            $trigger->unregister($workflow_id);
        }
    }

    /**
     * Fire trigger and execute associated workflows
     */
    public function fireTrigger(string $trigger_id, array $data = [], array $context = []): array
    {
        $executions = [];

        // Find workflows associated with this trigger
        $workflows = $this->getWorkflowsForTrigger($trigger_id, $data);

        foreach ($workflows as $workflow_id => $trigger_config) {
            // Check trigger conditions
            if (!$this->evaluateTriggerConditions($trigger_config, $data)) {
                continue;
            }

            try {
                $workflow = $this->engine->loadWorkflow($workflow_id);

                if ($workflow && $workflow->isActive()) {
                    $execution = $this->engine->execute($workflow, $data, array_merge($context, [
                        'trigger_type' => $trigger_id,
                        'trigger_config' => $trigger_config
                    ]));

                    $executions[$workflow_id] = $execution;
                }
            } catch (\Exception $e) {
                error_log("FormFlow Pro: Failed to execute workflow {$workflow_id}: " . $e->getMessage());
            }
        }

        return $executions;
    }

    /**
     * Get workflows for a trigger
     */
    private function getWorkflowsForTrigger(string $trigger_id, array $data): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';
        $workflows = [];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, triggers FROM {$table} WHERE status = 'active' AND triggers LIKE %s",
                '%"' . $wpdb->esc_like($trigger_id) . '"%'
            ),
            ARRAY_A
        );

        foreach ($rows as $row) {
            $triggers = json_decode($row['triggers'], true) ?: [];

            foreach ($triggers as $trigger) {
                if (($trigger['type'] ?? '') === $trigger_id) {
                    $workflows[(int)$row['id']] = $trigger['config'] ?? [];
                    break;
                }
            }
        }

        return $workflows;
    }

    /**
     * Evaluate trigger conditions
     */
    private function evaluateTriggerConditions(array $config, array $data): bool
    {
        $conditions = $config['conditions'] ?? [];

        if (empty($conditions)) {
            return true;
        }

        $evaluator = ConditionEvaluator::getInstance();
        return $evaluator->evaluateGroup($conditions, $data);
    }

    // ==================== Event Handlers ====================

    /**
     * Handle form submission
     */
    public function handleFormSubmission(int $submission_id, array $data): void
    {
        $form_id = $data['form_id'] ?? 0;

        $this->fireTrigger('form_submission', array_merge($data, [
            'submission_id' => $submission_id,
            'form_id' => $form_id
        ]), [
            'source' => 'form_submission'
        ]);
    }

    /**
     * Handle user login
     *
     * @param string $user_login User login name.
     * @param \WP_User $user WordPress user object.
     */
    public function handleUserLogin(string $user_login, $user): void
    {
        $this->fireTrigger('user_action', [
            'action_type' => 'login',
            'user_id' => $user->ID,
            'user_login' => $user_login,
            'user_email' => $user->user_email,
            'user_roles' => $user->roles
        ], [
            'source' => 'user_login'
        ]);
    }

    /**
     * Handle user registration
     */
    public function handleUserRegister(int $user_id): void
    {
        $user = get_userdata($user_id);

        if ($user) {
            $this->fireTrigger('user_action', [
                'action_type' => 'register',
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'user_roles' => $user->roles
            ], [
                'source' => 'user_register'
            ]);
        }
    }

    /**
     * Handle profile update
     *
     * @param int $user_id User ID.
     * @param \WP_User $old_user_data Old user data object.
     */
    public function handleProfileUpdate(int $user_id, $old_user_data): void
    {
        $user = get_userdata($user_id);

        if ($user) {
            $this->fireTrigger('user_action', [
                'action_type' => 'profile_update',
                'user_id' => $user_id,
                'user_data' => [
                    'login' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name
                ],
                'old_user_data' => [
                    'login' => $old_user_data->user_login,
                    'email' => $old_user_data->user_email,
                    'display_name' => $old_user_data->display_name
                ]
            ], [
                'source' => 'profile_update'
            ]);
        }
    }

    /**
     * Handle role change
     */
    public function handleRoleChange(int $user_id, string $role, array $old_roles): void
    {
        $user = get_userdata($user_id);

        if ($user) {
            $this->fireTrigger('user_action', [
                'action_type' => 'role_change',
                'user_id' => $user_id,
                'user_email' => $user->user_email,
                'new_role' => $role,
                'old_roles' => $old_roles
            ], [
                'source' => 'role_change'
            ]);
        }
    }

    /**
     * Handle user delete
     */
    public function handleUserDelete(int $user_id): void
    {
        $this->fireTrigger('user_action', [
            'action_type' => 'delete',
            'user_id' => $user_id
        ], [
            'source' => 'user_delete'
        ]);
    }

    /**
     * Handle user logout
     */
    public function handleUserLogout(): void
    {
        $user = wp_get_current_user();

        if ($user->ID) {
            $this->fireTrigger('user_action', [
                'action_type' => 'logout',
                'user_id' => $user->ID,
                'user_email' => $user->user_email
            ], [
                'source' => 'user_logout'
            ]);
        }
    }

    /**
     * Handle password reset
     *
     * @param \WP_User $user WordPress user object.
     */
    public function handlePasswordReset($user): void
    {
        $this->fireTrigger('user_action', [
            'action_type' => 'password_reset',
            'user_id' => $user->ID,
            'user_email' => $user->user_email
        ], [
            'source' => 'password_reset'
        ]);
    }

    /**
     * Handle submission created
     */
    public function handleSubmissionCreated(array $submission): void
    {
        $this->fireTrigger('database_change', [
            'table' => 'submissions',
            'event_type' => 'insert',
            'record' => $submission
        ], [
            'source' => 'database_change'
        ]);
    }

    /**
     * Handle submission updated
     */
    public function handleSubmissionUpdated(array $submission, array $old_submission): void
    {
        $this->fireTrigger('database_change', [
            'table' => 'submissions',
            'event_type' => 'update',
            'record' => $submission,
            'old_record' => $old_submission
        ], [
            'source' => 'database_change'
        ]);
    }

    /**
     * Handle submission deleted
     */
    public function handleSubmissionDeleted(int $submission_id): void
    {
        $this->fireTrigger('database_change', [
            'table' => 'submissions',
            'event_type' => 'delete',
            'record_id' => $submission_id
        ], [
            'source' => 'database_change'
        ]);
    }

    /**
     * Register scheduled triggers at runtime
     */
    public function registerScheduledTriggers(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_workflows';

        $workflows = $wpdb->get_results(
            "SELECT id FROM {$table} WHERE status = 'active' AND triggers LIKE '%scheduled%'",
            ARRAY_A
        );

        foreach ($workflows as $row) {
            $workflow_id = (int)$row['id'];
            $hook = "ffp_scheduled_workflow_{$workflow_id}";

            add_action($hook, function () use ($workflow_id) {
                $workflow = $this->engine->loadWorkflow($workflow_id);

                if ($workflow && $workflow->isActive()) {
                    $this->engine->execute($workflow, [], [
                        'trigger_type' => 'scheduled'
                    ]);

                    // Reschedule for next run
                    $config = get_option("ffp_schedule_config_{$workflow_id}", []);
                    if (!empty($config) && $config['schedule_type'] !== 'interval') {
                        $trigger = new ScheduledTrigger();
                        $trigger->register($workflow_id, $config);
                    }
                }
            });
        }
    }

    /**
     * Register WordPress hook triggers at runtime
     */
    public function registerWordPressHookTriggers(): void
    {
        $hooks = get_option('ffp_wp_hook_triggers', []);

        foreach ($hooks as $workflow_id => $config) {
            $hook_name = $config['hook_name'] ?? '';
            $hook_type = $config['hook_type'] ?? 'action';
            $priority = $config['priority'] ?? 10;
            $accepted_args = $config['accepted_args'] ?? 1;

            if (empty($hook_name)) {
                continue;
            }

            $callback = function (...$args) use ($workflow_id, $hook_name) {
                $workflow = $this->engine->loadWorkflow($workflow_id);

                if ($workflow && $workflow->isActive()) {
                    $this->engine->execute($workflow, [
                        'hook_name' => $hook_name,
                        'hook_args' => $args
                    ], [
                        'trigger_type' => 'wordpress_hook'
                    ]);
                }

                // Return first arg for filters
                return $args[0] ?? null;
            };

            if ($hook_type === 'filter') {
                add_filter($hook_name, $callback, $priority, $accepted_args);
            } else {
                add_action($hook_name, $callback, $priority, $accepted_args);
            }
        }
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/webhook/(?P<key>[a-zA-Z0-9]+)', [
            'methods' => ['POST', 'GET'],
            'callback' => [$this, 'handleWebhookRequest'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('formflow/v1', '/trigger/(?P<workflow_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handleManualTrigger'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    /**
     * Handle webhook request
     */
    public function handleWebhookRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $webhook_key = $request->get_param('key');
        $webhooks = get_option('ffp_workflow_webhooks', []);

        if (!isset($webhooks[$webhook_key])) {
            return new \WP_REST_Response(['error' => 'Invalid webhook'], 404);
        }

        $config = $webhooks[$webhook_key];
        $workflow_id = $config['workflow_id'];

        // Verify secret if configured
        if (!empty($config['secret_key'])) {
            $signature = $request->get_header('X-Webhook-Signature');
            $payload = $request->get_body();
            $expected = hash_hmac('sha256', $payload, $config['secret_key']);

            if (!hash_equals($expected, $signature ?? '')) {
                return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
            }
        }

        // Check IP restrictions
        if (!empty($config['allowed_ips'])) {
            $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $allowed = array_filter(array_map('trim', explode("\n", $config['allowed_ips'])));

            if (!$this->isIpAllowed($client_ip, $allowed)) {
                return new \WP_REST_Response(['error' => 'IP not allowed'], 403);
            }
        }

        // Check required headers
        $required_headers = $config['required_headers'] ?? [];
        foreach ($required_headers as $header => $value) {
            if ($request->get_header($header) !== $value) {
                return new \WP_REST_Response(['error' => "Missing or invalid header: {$header}"], 400);
            }
        }

        // Execute workflow
        $workflow = $this->engine->loadWorkflow($workflow_id);

        if (!$workflow || !$workflow->isActive()) {
            return new \WP_REST_Response(['error' => 'Workflow not active'], 400);
        }

        $data = $request->get_json_params() ?: $request->get_body_params();

        $execution = $this->engine->execute($workflow, $data, [
            'trigger_type' => 'webhook',
            'webhook_key' => $webhook_key,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);

        return new \WP_REST_Response([
            'success' => $execution->status === 'completed',
            'execution_id' => $execution->execution_id,
            'status' => $execution->status
        ]);
    }

    /**
     * Handle manual trigger request
     */
    public function handleManualTrigger(\WP_REST_Request $request): \WP_REST_Response
    {
        $workflow_id = (int)$request->get_param('workflow_id');
        $data = $request->get_json_params() ?: [];

        $workflow = $this->engine->loadWorkflow($workflow_id);

        if (!$workflow) {
            return new \WP_REST_Response(['error' => 'Workflow not found'], 404);
        }

        if (!$workflow->isActive()) {
            return new \WP_REST_Response(['error' => 'Workflow not active'], 400);
        }

        // Validate manual trigger config
        $manual_config = get_option("ffp_manual_trigger_config_{$workflow_id}", []);
        $input_fields = $manual_config['input_fields'] ?? [];

        foreach ($input_fields as $field) {
            $name = $field['name'] ?? '';
            $required = $field['required'] ?? false;

            if ($required && empty($data[$name])) {
                return new \WP_REST_Response([
                    'error' => "Missing required field: {$name}"
                ], 400);
            }
        }

        $execution = $this->engine->execute($workflow, $data, [
            'trigger_type' => 'manual',
            'triggered_by' => get_current_user_id()
        ]);

        return new \WP_REST_Response([
            'success' => $execution->status === 'completed',
            'execution_id' => $execution->execution_id,
            'status' => $execution->status,
            'output' => $execution->variables
        ]);
    }

    /**
     * Check if IP is in allowed list
     */
    private function isIpAllowed(string $ip, array $allowed): bool
    {
        foreach ($allowed as $rule) {
            if (strpos($rule, '/') !== false) {
                // CIDR notation
                if ($this->ipInCidr($ip, $rule)) {
                    return true;
                }
            } elseif ($ip === $rule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int)$mask;

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);

        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
}
