<?php
/**
 * Developer SDK - API Marketplace Development Kit
 *
 * Provides tools and APIs for developers to create
 * extensions and integrations for FormFlow Pro.
 *
 * @package FormFlowPro
 * @subpackage Marketplace
 * @since 3.0.0
 */

namespace FormFlowPro\Marketplace;

use FormFlowPro\Core\SingletonTrait;

/**
 * Extension manifest model
 */
class ExtensionManifest
{
    public string $id;
    public string $name;
    public string $slug;
    public string $version;
    public string $description;
    public string $author;
    public string $author_uri;
    public string $extension_uri;
    public string $license;
    public string $requires_php;
    public string $requires_wp;
    public string $requires_ffp;
    public string $main_file;
    public string $namespace;
    public array $hooks;
    public array $filters;
    public array $actions;
    public array $shortcodes;
    public array $widgets;
    public array $rest_endpoints;
    public array $settings;
    public array $dependencies;
    public array $assets;
    public array $capabilities;
    public string $icon;
    public array $screenshots;
    public array $tags;
    public string $category;
    public bool $is_premium;
    public float $price;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->description = $data['description'] ?? '';
        $this->author = $data['author'] ?? '';
        $this->author_uri = $data['author_uri'] ?? '';
        $this->extension_uri = $data['extension_uri'] ?? '';
        $this->license = $data['license'] ?? 'GPL-2.0+';
        $this->requires_php = $data['requires_php'] ?? '8.1';
        $this->requires_wp = $data['requires_wp'] ?? '6.0';
        $this->requires_ffp = $data['requires_ffp'] ?? '2.0.0';
        $this->main_file = $data['main_file'] ?? '';
        $this->namespace = $data['namespace'] ?? '';
        $this->hooks = $data['hooks'] ?? [];
        $this->filters = $data['filters'] ?? [];
        $this->actions = $data['actions'] ?? [];
        $this->shortcodes = $data['shortcodes'] ?? [];
        $this->widgets = $data['widgets'] ?? [];
        $this->rest_endpoints = $data['rest_endpoints'] ?? [];
        $this->settings = $data['settings'] ?? [];
        $this->dependencies = $data['dependencies'] ?? [];
        $this->assets = $data['assets'] ?? [];
        $this->capabilities = $data['capabilities'] ?? [];
        $this->icon = $data['icon'] ?? '';
        $this->screenshots = $data['screenshots'] ?? [];
        $this->tags = $data['tags'] ?? [];
        $this->category = $data['category'] ?? 'general';
        $this->is_premium = $data['is_premium'] ?? false;
        $this->price = $data['price'] ?? 0.0;
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors[] = __('Extension name is required', 'formflow-pro');
        }

        if (empty($this->slug) || !preg_match('/^[a-z0-9-]+$/', $this->slug)) {
            $errors[] = __('Extension slug must contain only lowercase letters, numbers, and hyphens', 'formflow-pro');
        }

        if (!preg_match('/^\d+\.\d+\.\d+$/', $this->version)) {
            $errors[] = __('Version must be in semver format (e.g., 1.0.0)', 'formflow-pro');
        }

        if (empty($this->main_file)) {
            $errors[] = __('Main file is required', 'formflow-pro');
        }

        return $errors;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public function toJSON(): string
    {
        return wp_json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
}

/**
 * API Endpoint definition
 */
class APIEndpoint
{
    public string $route;
    public string $method;
    public string $callback;
    public string $permission_callback;
    public array $args;
    public string $description;
    public bool $require_auth;
    public array $rate_limit;

    public function __construct(array $data = [])
    {
        $this->route = $data['route'] ?? '';
        $this->method = strtoupper($data['method'] ?? 'GET');
        $this->callback = $data['callback'] ?? '';
        $this->permission_callback = $data['permission_callback'] ?? '__return_true';
        $this->args = $data['args'] ?? [];
        $this->description = $data['description'] ?? '';
        $this->require_auth = $data['require_auth'] ?? false;
        $this->rate_limit = $data['rate_limit'] ?? ['requests' => 100, 'window' => 3600];
    }
}

/**
 * Hook definition
 */
class HookDefinition
{
    public string $name;
    public string $type; // action or filter
    public string $description;
    public array $parameters;
    public string $return_type;
    public string $since;
    public array $examples;

    public function __construct(array $data = [])
    {
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? 'action';
        $this->description = $data['description'] ?? '';
        $this->parameters = $data['parameters'] ?? [];
        $this->return_type = $data['return_type'] ?? 'void';
        $this->since = $data['since'] ?? '1.0.0';
        $this->examples = $data['examples'] ?? [];
    }
}

/**
 * Developer SDK - Main class
 */
class DeveloperSDK
{
    use SingletonTrait;

    private const API_NAMESPACE = 'formflow-pro/v1';
    private const SDK_VERSION = '1.0.0';

    private array $registered_hooks = [];
    private array $registered_filters = [];
    private array $api_endpoints = [];
    private array $extension_points = [];

    /**
     * Initialize SDK
     */
    public function init(): void
    {
        $this->registerCoreHooks();
        $this->registerCoreFilters();
        $this->registerExtensionPoints();
        $this->registerDeveloperAPI();
        $this->registerDocumentation();
    }

    /**
     * Register core hooks for extensions
     */
    private function registerCoreHooks(): void
    {
        // Form Processing Hooks
        $this->registerHook([
            'name' => 'formflow_before_process_submission',
            'type' => 'action',
            'description' => 'Fires before a form submission is processed',
            'parameters' => [
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'form_data', 'type' => 'array', 'description' => 'The submitted form data'],
                ['name' => 'form_id', 'type' => 'int', 'description' => 'The form ID']
            ],
            'since' => '2.0.0',
            'examples' => [
                "add_action('formflow_before_process_submission', function(\$submission_id, \$form_data, \$form_id) {\n    // Your code here\n}, 10, 3);"
            ]
        ]);

        $this->registerHook([
            'name' => 'formflow_after_process_submission',
            'type' => 'action',
            'description' => 'Fires after a form submission is processed',
            'parameters' => [
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'result', 'type' => 'array', 'description' => 'The processing result'],
                ['name' => 'form_id', 'type' => 'int', 'description' => 'The form ID']
            ],
            'since' => '2.0.0'
        ]);

        // PDF Generation Hooks
        $this->registerHook([
            'name' => 'formflow_before_generate_pdf',
            'type' => 'action',
            'description' => 'Fires before PDF generation starts',
            'parameters' => [
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'template_id', 'type' => 'int', 'description' => 'The PDF template ID']
            ],
            'since' => '2.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_after_generate_pdf',
            'type' => 'action',
            'description' => 'Fires after PDF is generated',
            'parameters' => [
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'pdf_path', 'type' => 'string', 'description' => 'The generated PDF path'],
                ['name' => 'pdf_url', 'type' => 'string', 'description' => 'The PDF URL']
            ],
            'since' => '2.0.0'
        ]);

        // Email Hooks
        $this->registerHook([
            'name' => 'formflow_before_send_email',
            'type' => 'action',
            'description' => 'Fires before an email is sent',
            'parameters' => [
                ['name' => 'email_data', 'type' => 'array', 'description' => 'Email data (to, subject, body, etc.)'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'since' => '2.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_after_send_email',
            'type' => 'action',
            'description' => 'Fires after an email is sent',
            'parameters' => [
                ['name' => 'success', 'type' => 'bool', 'description' => 'Whether the email was sent'],
                ['name' => 'email_data', 'type' => 'array', 'description' => 'Email data'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'since' => '2.0.0'
        ]);

        // Autentique/Signature Hooks
        $this->registerHook([
            'name' => 'formflow_before_send_to_autentique',
            'type' => 'action',
            'description' => 'Fires before sending document to Autentique',
            'parameters' => [
                ['name' => 'document_data', 'type' => 'array', 'description' => 'Document data'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'since' => '2.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_document_signed',
            'type' => 'action',
            'description' => 'Fires when a document is fully signed',
            'parameters' => [
                ['name' => 'document_id', 'type' => 'string', 'description' => 'The Autentique document ID'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'signed_url', 'type' => 'string', 'description' => 'URL to signed document']
            ],
            'since' => '2.0.0'
        ]);

        // Workflow Hooks
        $this->registerHook([
            'name' => 'formflow_workflow_started',
            'type' => 'action',
            'description' => 'Fires when a workflow execution starts',
            'parameters' => [
                ['name' => 'workflow_id', 'type' => 'int', 'description' => 'The workflow ID'],
                ['name' => 'execution_id', 'type' => 'string', 'description' => 'The execution ID'],
                ['name' => 'trigger_data', 'type' => 'array', 'description' => 'Trigger data']
            ],
            'since' => '3.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_workflow_completed',
            'type' => 'action',
            'description' => 'Fires when a workflow execution completes',
            'parameters' => [
                ['name' => 'workflow_id', 'type' => 'int', 'description' => 'The workflow ID'],
                ['name' => 'execution_id', 'type' => 'string', 'description' => 'The execution ID'],
                ['name' => 'result', 'type' => 'array', 'description' => 'Execution result']
            ],
            'since' => '3.0.0'
        ]);

        // SSO Hooks
        $this->registerHook([
            'name' => 'formflow_sso_user_authenticated',
            'type' => 'action',
            'description' => 'Fires when a user authenticates via SSO',
            'parameters' => [
                ['name' => 'user', 'type' => 'WP_User', 'description' => 'The authenticated user'],
                ['name' => 'provider', 'type' => 'string', 'description' => 'The SSO provider'],
                ['name' => 'external_data', 'type' => 'array', 'description' => 'External user data']
            ],
            'since' => '3.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_sso_user_provisioned',
            'type' => 'action',
            'description' => 'Fires when a new user is created via SSO',
            'parameters' => [
                ['name' => 'user_id', 'type' => 'int', 'description' => 'The new user ID'],
                ['name' => 'external_data', 'type' => 'array', 'description' => 'External user data'],
                ['name' => 'provider', 'type' => 'string', 'description' => 'The SSO provider']
            ],
            'since' => '3.0.0'
        ]);

        // Extension Hooks
        $this->registerHook([
            'name' => 'formflow_extension_activated',
            'type' => 'action',
            'description' => 'Fires when an extension is activated',
            'parameters' => [
                ['name' => 'extension_slug', 'type' => 'string', 'description' => 'The extension slug'],
                ['name' => 'extension_data', 'type' => 'array', 'description' => 'Extension manifest data']
            ],
            'since' => '3.0.0'
        ]);

        $this->registerHook([
            'name' => 'formflow_extension_deactivated',
            'type' => 'action',
            'description' => 'Fires when an extension is deactivated',
            'parameters' => [
                ['name' => 'extension_slug', 'type' => 'string', 'description' => 'The extension slug']
            ],
            'since' => '3.0.0'
        ]);
    }

    /**
     * Register core filters for extensions
     */
    private function registerCoreFilters(): void
    {
        // Form Data Filters
        $this->registerFilter([
            'name' => 'formflow_submission_data',
            'description' => 'Filters the submission data before processing',
            'parameters' => [
                ['name' => 'data', 'type' => 'array', 'description' => 'The submission data'],
                ['name' => 'form_id', 'type' => 'int', 'description' => 'The form ID']
            ],
            'return_type' => 'array',
            'since' => '2.0.0',
            'examples' => [
                "add_filter('formflow_submission_data', function(\$data, \$form_id) {\n    \$data['custom_field'] = 'value';\n    return \$data;\n}, 10, 2);"
            ]
        ]);

        $this->registerFilter([
            'name' => 'formflow_field_validation',
            'description' => 'Filters field validation rules',
            'parameters' => [
                ['name' => 'rules', 'type' => 'array', 'description' => 'Validation rules'],
                ['name' => 'field_name', 'type' => 'string', 'description' => 'Field name'],
                ['name' => 'field_value', 'type' => 'mixed', 'description' => 'Field value']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        // PDF Filters
        $this->registerFilter([
            'name' => 'formflow_pdf_content',
            'description' => 'Filters the PDF HTML content before rendering',
            'parameters' => [
                ['name' => 'html', 'type' => 'string', 'description' => 'The HTML content'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'template_id', 'type' => 'int', 'description' => 'The template ID']
            ],
            'return_type' => 'string',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_pdf_filename',
            'description' => 'Filters the generated PDF filename',
            'parameters' => [
                ['name' => 'filename', 'type' => 'string', 'description' => 'The filename'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'return_type' => 'string',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_pdf_options',
            'description' => 'Filters PDF generation options',
            'parameters' => [
                ['name' => 'options', 'type' => 'array', 'description' => 'PDF options (size, orientation, etc.)'],
                ['name' => 'template_id', 'type' => 'int', 'description' => 'The template ID']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        // Email Filters
        $this->registerFilter([
            'name' => 'formflow_email_recipients',
            'description' => 'Filters the email recipients',
            'parameters' => [
                ['name' => 'recipients', 'type' => 'array', 'description' => 'Array of email addresses'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'email_type', 'type' => 'string', 'description' => 'Type of email (notification, confirmation, etc.)']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_email_subject',
            'description' => 'Filters the email subject',
            'parameters' => [
                ['name' => 'subject', 'type' => 'string', 'description' => 'The email subject'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'return_type' => 'string',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_email_body',
            'description' => 'Filters the email body content',
            'parameters' => [
                ['name' => 'body', 'type' => 'string', 'description' => 'The email body HTML'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID'],
                ['name' => 'template_vars', 'type' => 'array', 'description' => 'Template variables']
            ],
            'return_type' => 'string',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_email_attachments',
            'description' => 'Filters email attachments',
            'parameters' => [
                ['name' => 'attachments', 'type' => 'array', 'description' => 'Array of attachment paths'],
                ['name' => 'submission_id', 'type' => 'int', 'description' => 'The submission ID']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        // Workflow Filters
        $this->registerFilter([
            'name' => 'formflow_workflow_conditions',
            'description' => 'Filters workflow condition evaluation',
            'parameters' => [
                ['name' => 'result', 'type' => 'bool', 'description' => 'Condition result'],
                ['name' => 'condition', 'type' => 'array', 'description' => 'Condition definition'],
                ['name' => 'context', 'type' => 'array', 'description' => 'Execution context']
            ],
            'return_type' => 'bool',
            'since' => '3.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_workflow_action_result',
            'description' => 'Filters workflow action execution result',
            'parameters' => [
                ['name' => 'result', 'type' => 'array', 'description' => 'Action result'],
                ['name' => 'action', 'type' => 'string', 'description' => 'Action type'],
                ['name' => 'params', 'type' => 'array', 'description' => 'Action parameters']
            ],
            'return_type' => 'array',
            'since' => '3.0.0'
        ]);

        // API Filters
        $this->registerFilter([
            'name' => 'formflow_api_response',
            'description' => 'Filters REST API response data',
            'parameters' => [
                ['name' => 'response', 'type' => 'array', 'description' => 'Response data'],
                ['name' => 'request', 'type' => 'WP_REST_Request', 'description' => 'The request object'],
                ['name' => 'endpoint', 'type' => 'string', 'description' => 'The endpoint route']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_api_rate_limit',
            'description' => 'Filters API rate limiting',
            'parameters' => [
                ['name' => 'limit', 'type' => 'array', 'description' => 'Rate limit settings'],
                ['name' => 'endpoint', 'type' => 'string', 'description' => 'The endpoint route'],
                ['name' => 'user_id', 'type' => 'int', 'description' => 'The user ID']
            ],
            'return_type' => 'array',
            'since' => '3.0.0'
        ]);

        // Admin Filters
        $this->registerFilter([
            'name' => 'formflow_admin_menu_items',
            'description' => 'Filters admin menu items',
            'parameters' => [
                ['name' => 'items', 'type' => 'array', 'description' => 'Menu items array']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_dashboard_widgets',
            'description' => 'Filters dashboard widgets',
            'parameters' => [
                ['name' => 'widgets', 'type' => 'array', 'description' => 'Dashboard widgets array']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        // Analytics Filters
        $this->registerFilter([
            'name' => 'formflow_analytics_metrics',
            'description' => 'Filters available analytics metrics',
            'parameters' => [
                ['name' => 'metrics', 'type' => 'array', 'description' => 'Available metrics']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);

        $this->registerFilter([
            'name' => 'formflow_analytics_data',
            'description' => 'Filters analytics data before display',
            'parameters' => [
                ['name' => 'data', 'type' => 'array', 'description' => 'Analytics data'],
                ['name' => 'metric', 'type' => 'string', 'description' => 'The metric name'],
                ['name' => 'date_range', 'type' => 'array', 'description' => 'Date range']
            ],
            'return_type' => 'array',
            'since' => '2.0.0'
        ]);
    }

    /**
     * Register extension points
     */
    private function registerExtensionPoints(): void
    {
        // Field Types
        $this->extension_points['field_types'] = [
            'description' => __('Register custom form field types', 'formflow-pro'),
            'callback' => 'formflow_register_field_type',
            'example' => "formflow_register_field_type('my_field', [\n    'label' => 'My Custom Field',\n    'icon' => 'dashicons-edit',\n    'render_callback' => 'my_field_render',\n    'validate_callback' => 'my_field_validate'\n]);"
        ];

        // Actions (Workflow)
        $this->extension_points['workflow_actions'] = [
            'description' => __('Register custom workflow actions', 'formflow-pro'),
            'callback' => 'formflow_register_workflow_action',
            'example' => "formflow_register_workflow_action('my_action', [\n    'label' => 'My Action',\n    'icon' => 'dashicons-admin-generic',\n    'execute_callback' => 'my_action_execute',\n    'config_schema' => [...]\n]);"
        ];

        // Triggers (Workflow)
        $this->extension_points['workflow_triggers'] = [
            'description' => __('Register custom workflow triggers', 'formflow-pro'),
            'callback' => 'formflow_register_workflow_trigger',
            'example' => "formflow_register_workflow_trigger('my_trigger', [\n    'label' => 'My Trigger',\n    'icon' => 'dashicons-flag',\n    'setup_callback' => 'my_trigger_setup',\n    'config_schema' => [...]\n]);"
        ];

        // Integrations
        $this->extension_points['integrations'] = [
            'description' => __('Register custom integrations', 'formflow-pro'),
            'callback' => 'formflow_register_integration',
            'example' => "formflow_register_integration('my_crm', [\n    'label' => 'My CRM',\n    'icon' => 'my-crm-icon.svg',\n    'connect_callback' => 'my_crm_connect',\n    'sync_callback' => 'my_crm_sync'\n]);"
        ];

        // PDF Templates
        $this->extension_points['pdf_templates'] = [
            'description' => __('Register custom PDF templates', 'formflow-pro'),
            'callback' => 'formflow_register_pdf_template',
            'example' => "formflow_register_pdf_template('my_template', [\n    'label' => 'My Template',\n    'thumbnail' => 'template-thumb.png',\n    'render_callback' => 'my_template_render'\n]);"
        ];

        // Email Templates
        $this->extension_points['email_templates'] = [
            'description' => __('Register custom email templates', 'formflow-pro'),
            'callback' => 'formflow_register_email_template',
            'example' => "formflow_register_email_template('my_email', [\n    'label' => 'My Email Template',\n    'html_path' => 'templates/my-email.html',\n    'variables' => ['name', 'email', 'message']\n]);"
        ];

        // Notification Channels
        $this->extension_points['notification_channels'] = [
            'description' => __('Register custom notification channels', 'formflow-pro'),
            'callback' => 'formflow_register_notification_channel',
            'example' => "formflow_register_notification_channel('my_channel', [\n    'label' => 'My Channel',\n    'send_callback' => 'my_channel_send',\n    'config_schema' => [...]\n]);"
        ];

        // Analytics Widgets
        $this->extension_points['analytics_widgets'] = [
            'description' => __('Register custom analytics widgets', 'formflow-pro'),
            'callback' => 'formflow_register_analytics_widget',
            'example' => "formflow_register_analytics_widget('my_widget', [\n    'label' => 'My Widget',\n    'size' => 'medium',\n    'render_callback' => 'my_widget_render'\n]);"
        ];

        // Payment Gateways
        $this->extension_points['payment_gateways'] = [
            'description' => __('Register custom payment gateways', 'formflow-pro'),
            'callback' => 'formflow_register_payment_gateway',
            'example' => "formflow_register_payment_gateway('my_gateway', [\n    'label' => 'My Payment Gateway',\n    'process_callback' => 'my_gateway_process',\n    'webhook_callback' => 'my_gateway_webhook'\n]);"
        ];

        // SSO Providers
        $this->extension_points['sso_providers'] = [
            'description' => __('Register custom SSO providers', 'formflow-pro'),
            'callback' => 'formflow_register_sso_provider',
            'example' => "formflow_register_sso_provider('my_sso', [\n    'label' => 'My SSO Provider',\n    'authenticate_callback' => 'my_sso_authenticate',\n    'config_schema' => [...]\n]);"
        ];
    }

    /**
     * Register developer API endpoints
     */
    private function registerDeveloperAPI(): void
    {
        add_action('rest_api_init', function () {
            // SDK Info
            register_rest_route(self::API_NAMESPACE, '/sdk/info', [
                'methods' => 'GET',
                'callback' => [$this, 'apiGetSDKInfo'],
                'permission_callback' => '__return_true'
            ]);

            // Available Hooks
            register_rest_route(self::API_NAMESPACE, '/sdk/hooks', [
                'methods' => 'GET',
                'callback' => [$this, 'apiGetHooks'],
                'permission_callback' => '__return_true'
            ]);

            // Available Filters
            register_rest_route(self::API_NAMESPACE, '/sdk/filters', [
                'methods' => 'GET',
                'callback' => [$this, 'apiGetFilters'],
                'permission_callback' => '__return_true'
            ]);

            // Extension Points
            register_rest_route(self::API_NAMESPACE, '/sdk/extension-points', [
                'methods' => 'GET',
                'callback' => [$this, 'apiGetExtensionPoints'],
                'permission_callback' => '__return_true'
            ]);

            // Validate Extension
            register_rest_route(self::API_NAMESPACE, '/sdk/validate-extension', [
                'methods' => 'POST',
                'callback' => [$this, 'apiValidateExtension'],
                'permission_callback' => [$this, 'checkDeveloperPermission']
            ]);

            // Generate Extension Scaffold
            register_rest_route(self::API_NAMESPACE, '/sdk/scaffold', [
                'methods' => 'POST',
                'callback' => [$this, 'apiGenerateScaffold'],
                'permission_callback' => [$this, 'checkDeveloperPermission']
            ]);
        });
    }

    /**
     * Register documentation
     */
    private function registerDocumentation(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'formflow-pro',
                __('Developer Docs', 'formflow-pro'),
                __('Developers', 'formflow-pro'),
                'manage_options',
                'formflow-developers',
                [$this, 'renderDeveloperPage']
            );
        });
    }

    /**
     * Register a hook
     */
    public function registerHook(array $data): void
    {
        $hook = new HookDefinition($data);
        $this->registered_hooks[$hook->name] = $hook;
    }

    /**
     * Register a filter
     */
    public function registerFilter(array $data): void
    {
        $data['type'] = 'filter';
        $filter = new HookDefinition($data);
        $this->registered_filters[$filter->name] = $filter;
    }

    /**
     * Get all registered hooks
     */
    public function getHooks(): array
    {
        return $this->registered_hooks;
    }

    /**
     * Get all registered filters
     */
    public function getFilters(): array
    {
        return $this->registered_filters;
    }

    /**
     * Get extension points
     */
    public function getExtensionPoints(): array
    {
        return $this->extension_points;
    }

    /**
     * API: Get SDK info
     */
    public function apiGetSDKInfo(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'version' => self::SDK_VERSION,
            'api_namespace' => self::API_NAMESPACE,
            'hooks_count' => count($this->registered_hooks),
            'filters_count' => count($this->registered_filters),
            'extension_points_count' => count($this->extension_points),
            'documentation_url' => admin_url('admin.php?page=formflow-developers'),
            'support_url' => 'https://formflowpro.com/developer-support',
            'marketplace_url' => admin_url('admin.php?page=formflow-marketplace')
        ]);
    }

    /**
     * API: Get hooks
     */
    public function apiGetHooks(\WP_REST_Request $request): \WP_REST_Response
    {
        $hooks = [];
        foreach ($this->registered_hooks as $name => $hook) {
            $hooks[$name] = [
                'name' => $hook->name,
                'description' => $hook->description,
                'parameters' => $hook->parameters,
                'since' => $hook->since,
                'examples' => $hook->examples
            ];
        }
        return new \WP_REST_Response($hooks);
    }

    /**
     * API: Get filters
     */
    public function apiGetFilters(\WP_REST_Request $request): \WP_REST_Response
    {
        $filters = [];
        foreach ($this->registered_filters as $name => $filter) {
            $filters[$name] = [
                'name' => $filter->name,
                'description' => $filter->description,
                'parameters' => $filter->parameters,
                'return_type' => $filter->return_type,
                'since' => $filter->since,
                'examples' => $filter->examples
            ];
        }
        return new \WP_REST_Response($filters);
    }

    /**
     * API: Get extension points
     */
    public function apiGetExtensionPoints(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response($this->extension_points);
    }

    /**
     * API: Validate extension
     */
    public function apiValidateExtension(\WP_REST_Request $request): \WP_REST_Response
    {
        $manifest_data = $request->get_json_params();
        $manifest = new ExtensionManifest($manifest_data);
        $errors = $manifest->validate();

        if (!empty($errors)) {
            return new \WP_REST_Response([
                'valid' => false,
                'errors' => $errors
            ], 400);
        }

        return new \WP_REST_Response([
            'valid' => true,
            'manifest' => $manifest->toArray()
        ]);
    }

    /**
     * API: Generate scaffold
     */
    public function apiGenerateScaffold(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $type = $data['type'] ?? 'basic';
        $name = sanitize_title($data['name'] ?? 'my-extension');

        $scaffold = $this->generateExtensionScaffold($type, $name, $data);

        return new \WP_REST_Response([
            'success' => true,
            'files' => $scaffold
        ]);
    }

    /**
     * Generate extension scaffold
     */
    private function generateExtensionScaffold(string $type, string $slug, array $data): array
    {
        $name = $data['name'] ?? ucwords(str_replace('-', ' ', $slug));
        $namespace = $data['namespace'] ?? 'FFPExtension\\' . str_replace('-', '', ucwords($slug, '-'));
        $author = $data['author'] ?? '';

        $files = [];

        // Main plugin file
        $files["{$slug}.php"] = $this->generateMainFile($slug, $name, $namespace, $author);

        // Manifest file
        $files['manifest.json'] = $this->generateManifestFile($slug, $name, $namespace, $author, $type);

        // Main class file
        $files["includes/{$slug}-extension.php"] = $this->generateExtensionClass($slug, $name, $namespace);

        // README
        $files['README.md'] = $this->generateReadme($name, $slug);

        // Type-specific files
        switch ($type) {
            case 'field-type':
                $files['includes/field-type.php'] = $this->generateFieldTypeFile($namespace);
                break;
            case 'integration':
                $files['includes/integration.php'] = $this->generateIntegrationFile($namespace);
                break;
            case 'workflow-action':
                $files['includes/workflow-action.php'] = $this->generateWorkflowActionFile($namespace);
                break;
        }

        return $files;
    }

    /**
     * Generate main plugin file content
     */
    private function generateMainFile(string $slug, string $name, string $namespace, string $author): string
    {
        return <<<PHP
<?php
/**
 * Plugin Name: {$name}
 * Plugin URI: https://example.com/{$slug}
 * Description: A FormFlow Pro extension
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: {$author}
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: {$slug}
 * Domain Path: /languages
 *
 * @package {$namespace}
 */

// Abort if WordPress is not loaded
if (!defined('ABSPATH')) {
    exit;
}

// Check if FormFlow Pro is active
if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('formflow-pro/formflow-pro.php')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p>';
        echo esc_html__('{$name} requires FormFlow Pro to be installed and activated.', '{$slug}');
        echo '</p></div>';
    });
    return;
}

// Define constants
define('{$this->slugToConstant($slug)}_VERSION', '1.0.0');
define('{$this->slugToConstant($slug)}_PATH', plugin_dir_path(__FILE__));
define('{$this->slugToConstant($slug)}_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function (\$class) {
    \$prefix = '{$namespace}\\\\';
    \$base_dir = {$this->slugToConstant($slug)}_PATH . 'includes/';

    \$len = strlen(\$prefix);
    if (strncmp(\$prefix, \$class, \$len) !== 0) {
        return;
    }

    \$relative_class = substr(\$class, \$len);
    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';

    if (file_exists(\$file)) {
        require \$file;
    }
});

// Initialize the extension
add_action('formflow_pro_loaded', function () {
    {$namespace}\\Extension::getInstance()->init();
});
PHP;
    }

    /**
     * Generate manifest file content
     */
    private function generateManifestFile(string $slug, string $name, string $namespace, string $author, string $type): string
    {
        $manifest = [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug,
            'version' => '1.0.0',
            'description' => "A FormFlow Pro {$type} extension",
            'author' => $author,
            'author_uri' => '',
            'extension_uri' => '',
            'license' => 'GPL-2.0+',
            'requires_php' => '8.1',
            'requires_wp' => '6.0',
            'requires_ffp' => '3.0.0',
            'main_file' => "{$slug}.php",
            'namespace' => $namespace,
            'category' => $type,
            'tags' => [$type],
            'is_premium' => false
        ];

        return wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate extension class file
     */
    private function generateExtensionClass(string $slug, string $name, string $namespace): string
    {
        return <<<PHP
<?php
namespace {$namespace};

class Extension
{
    private static ?self \$instance = null;

    public static function getInstance(): self
    {
        if (self::\$instance === null) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }

    public function init(): void
    {
        // Register hooks
        add_action('formflow_after_process_submission', [\$this, 'onSubmissionProcessed'], 10, 3);

        // Register filters
        add_filter('formflow_submission_data', [\$this, 'filterSubmissionData'], 10, 2);

        // Register admin menu
        add_action('admin_menu', [\$this, 'registerAdminMenu']);
    }

    public function onSubmissionProcessed(int \$submission_id, array \$result, int \$form_id): void
    {
        // Handle submission processed event
    }

    public function filterSubmissionData(array \$data, int \$form_id): array
    {
        // Modify submission data if needed
        return \$data;
    }

    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('{$name}', '{$slug}'),
            __('{$name}', '{$slug}'),
            'manage_options',
            '{$slug}',
            [\$this, 'renderAdminPage']
        );
    }

    public function renderAdminPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('{$name}', '{$slug}') . '</h1>';
        echo '<p>' . esc_html__('Extension settings page.', '{$slug}') . '</p>';
        echo '</div>';
    }
}
PHP;
    }

    /**
     * Generate README content
     */
    private function generateReadme(string $name, string $slug): string
    {
        return <<<MD
# {$name}

A FormFlow Pro extension.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- FormFlow Pro 3.0.0+

## Installation

1. Upload the plugin files to `/wp-content/plugins/{$slug}/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the extension in FormFlow Pro settings

## Usage

[Describe how to use your extension]

## Hooks

### Actions

- `{$slug}_action_name` - Description

### Filters

- `{$slug}_filter_name` - Description

## Changelog

### 1.0.0
- Initial release

## License

GPL-2.0+
MD;
    }

    /**
     * Generate field type file
     */
    private function generateFieldTypeFile(string $namespace): string
    {
        return <<<PHP
<?php
namespace {$namespace};

class CustomFieldType
{
    public function register(): void
    {
        formflow_register_field_type('custom_field', [
            'label' => __('Custom Field', 'formflow-pro'),
            'icon' => 'dashicons-edit',
            'category' => 'advanced',
            'render_callback' => [\$this, 'render'],
            'validate_callback' => [\$this, 'validate'],
            'sanitize_callback' => [\$this, 'sanitize'],
            'config_schema' => \$this->getConfigSchema()
        ]);
    }

    public function render(array \$field, \$value = null): string
    {
        \$html = sprintf(
            '<input type="text" name="%s" id="%s" value="%s" class="formflow-field" />',
            esc_attr(\$field['name']),
            esc_attr(\$field['id']),
            esc_attr(\$value)
        );
        return \$html;
    }

    public function validate(\$value, array \$field): bool
    {
        if (\$field['required'] && empty(\$value)) {
            return false;
        }
        return true;
    }

    public function sanitize(\$value): string
    {
        return sanitize_text_field(\$value);
    }

    private function getConfigSchema(): array
    {
        return [
            'placeholder' => [
                'type' => 'text',
                'label' => __('Placeholder', 'formflow-pro'),
                'default' => ''
            ],
            'maxlength' => [
                'type' => 'number',
                'label' => __('Max Length', 'formflow-pro'),
                'default' => 255
            ]
        ];
    }
}
PHP;
    }

    /**
     * Generate integration file
     */
    private function generateIntegrationFile(string $namespace): string
    {
        return <<<PHP
<?php
namespace {$namespace};

use FormFlowPro\\Integrations\\AbstractIntegration;

class CustomIntegration extends AbstractIntegration
{
    protected string \$id = 'custom_integration';
    protected string \$name = 'Custom Integration';
    protected string \$description = 'Connect with Custom Service';

    public function connect(array \$credentials): bool
    {
        // Implement connection logic
        \$api_key = \$credentials['api_key'] ?? '';

        if (empty(\$api_key)) {
            \$this->setError(__('API key is required', 'formflow-pro'));
            return false;
        }

        // Test connection
        \$response = wp_remote_get('https://api.example.com/test', [
            'headers' => ['Authorization' => 'Bearer ' . \$api_key]
        ]);

        if (is_wp_error(\$response)) {
            \$this->setError(\$response->get_error_message());
            return false;
        }

        \$this->saveCredentials(\$credentials);
        return true;
    }

    public function disconnect(): bool
    {
        \$this->deleteCredentials();
        return true;
    }

    public function sync(array \$data): array
    {
        // Implement data sync logic
        return ['success' => true];
    }

    public function getSettingsSchema(): array
    {
        return [
            'api_key' => [
                'type' => 'password',
                'label' => __('API Key', 'formflow-pro'),
                'required' => true
            ],
            'webhook_url' => [
                'type' => 'url',
                'label' => __('Webhook URL', 'formflow-pro'),
                'required' => false
            ]
        ];
    }
}
PHP;
    }

    /**
     * Generate workflow action file
     */
    private function generateWorkflowActionFile(string $namespace): string
    {
        return <<<PHP
<?php
namespace {$namespace};

class CustomWorkflowAction
{
    public function register(): void
    {
        formflow_register_workflow_action('custom_action', [
            'label' => __('Custom Action', 'formflow-pro'),
            'description' => __('Performs a custom action', 'formflow-pro'),
            'icon' => 'dashicons-admin-generic',
            'category' => 'custom',
            'execute_callback' => [\$this, 'execute'],
            'config_schema' => \$this->getConfigSchema()
        ]);
    }

    public function execute(array \$config, array \$context): array
    {
        try {
            // Get configuration values
            \$param1 = \$config['param1'] ?? '';
            \$param2 = \$config['param2'] ?? '';

            // Access context data
            \$submission_data = \$context['data'] ?? [];
            \$workflow_id = \$context['workflow_id'] ?? 0;

            // Perform your action
            \$result = \$this->performAction(\$param1, \$param2, \$submission_data);

            return [
                'success' => true,
                'message' => __('Action completed successfully', 'formflow-pro'),
                'output' => \$result
            ];

        } catch (\\Exception \$e) {
            return [
                'success' => false,
                'message' => \$e->getMessage()
            ];
        }
    }

    private function performAction(string \$param1, string \$param2, array \$data): array
    {
        // Your custom logic here
        return ['processed' => true];
    }

    private function getConfigSchema(): array
    {
        return [
            'param1' => [
                'type' => 'text',
                'label' => __('Parameter 1', 'formflow-pro'),
                'description' => __('First parameter', 'formflow-pro'),
                'required' => true
            ],
            'param2' => [
                'type' => 'select',
                'label' => __('Parameter 2', 'formflow-pro'),
                'options' => [
                    'option1' => __('Option 1', 'formflow-pro'),
                    'option2' => __('Option 2', 'formflow-pro')
                ],
                'default' => 'option1'
            ]
        ];
    }
}
PHP;
    }

    /**
     * Convert slug to constant name
     */
    private function slugToConstant(string $slug): string
    {
        return strtoupper(str_replace('-', '_', $slug));
    }

    /**
     * Check developer permission
     */
    public function checkDeveloperPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Render developer documentation page
     */
    public function renderDeveloperPage(): void
    {
        $active_tab = $_GET['tab'] ?? 'overview';
        ?>
        <div class="wrap ffp-developer-docs">
            <h1><?php esc_html_e('Developer Documentation', 'formflow-pro'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=formflow-developers&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Overview', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-developers&tab=hooks" class="nav-tab <?php echo $active_tab === 'hooks' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Hooks', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-developers&tab=filters" class="nav-tab <?php echo $active_tab === 'filters' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Filters', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-developers&tab=extensions" class="nav-tab <?php echo $active_tab === 'extensions' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Extensions', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-developers&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('REST API', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-developers&tab=scaffold" class="nav-tab <?php echo $active_tab === 'scaffold' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Scaffold', 'formflow-pro'); ?>
                </a>
            </nav>

            <div class="tab-content" style="margin-top: 20px;">
                <?php
                switch ($active_tab) {
                    case 'hooks':
                        $this->renderHooksTab();
                        break;
                    case 'filters':
                        $this->renderFiltersTab();
                        break;
                    case 'extensions':
                        $this->renderExtensionsTab();
                        break;
                    case 'api':
                        $this->renderAPITab();
                        break;
                    case 'scaffold':
                        $this->renderScaffoldTab();
                        break;
                    default:
                        $this->renderOverviewTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render overview tab
     */
    private function renderOverviewTab(): void
    {
        ?>
        <div class="ffp-dev-overview">
            <div class="ffp-dev-card">
                <h2><?php esc_html_e('Welcome to FormFlow Pro SDK', 'formflow-pro'); ?></h2>
                <p><?php esc_html_e('Build powerful extensions and integrations for FormFlow Pro.', 'formflow-pro'); ?></p>

                <div class="ffp-dev-stats">
                    <div class="stat">
                        <span class="number"><?php echo count($this->registered_hooks); ?></span>
                        <span class="label"><?php esc_html_e('Action Hooks', 'formflow-pro'); ?></span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo count($this->registered_filters); ?></span>
                        <span class="label"><?php esc_html_e('Filters', 'formflow-pro'); ?></span>
                    </div>
                    <div class="stat">
                        <span class="number"><?php echo count($this->extension_points); ?></span>
                        <span class="label"><?php esc_html_e('Extension Points', 'formflow-pro'); ?></span>
                    </div>
                </div>
            </div>

            <div class="ffp-dev-grid">
                <div class="ffp-dev-card">
                    <h3><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e('Quick Start', 'formflow-pro'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Use the Scaffold tool to generate extension boilerplate', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Implement your custom functionality using hooks and filters', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Test your extension thoroughly', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Submit to the marketplace for review', 'formflow-pro'); ?></li>
                    </ol>
                </div>

                <div class="ffp-dev-card">
                    <h3><span class="dashicons dashicons-book"></span> <?php esc_html_e('Resources', 'formflow-pro'); ?></h3>
                    <ul>
                        <li><a href="https://formflowpro.com/docs/developers" target="_blank"><?php esc_html_e('Full Documentation', 'formflow-pro'); ?></a></li>
                        <li><a href="https://github.com/formflowpro/examples" target="_blank"><?php esc_html_e('Example Extensions', 'formflow-pro'); ?></a></li>
                        <li><a href="https://formflowpro.com/community" target="_blank"><?php esc_html_e('Developer Community', 'formflow-pro'); ?></a></li>
                        <li><a href="https://formflowpro.com/support" target="_blank"><?php esc_html_e('Support', 'formflow-pro'); ?></a></li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
            .ffp-dev-overview { max-width: 1200px; }
            .ffp-dev-card { background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 20px; }
            .ffp-dev-card h2, .ffp-dev-card h3 { margin-top: 0; }
            .ffp-dev-card h3 .dashicons { margin-right: 8px; color: #2271b1; }
            .ffp-dev-stats { display: flex; gap: 30px; margin-top: 20px; }
            .ffp-dev-stats .stat { text-align: center; }
            .ffp-dev-stats .number { display: block; font-size: 36px; font-weight: bold; color: #2271b1; }
            .ffp-dev-stats .label { display: block; color: #666; }
            .ffp-dev-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        </style>
        <?php
    }

    /**
     * Render hooks tab
     */
    private function renderHooksTab(): void
    {
        ?>
        <h2><?php esc_html_e('Available Action Hooks', 'formflow-pro'); ?></h2>
        <p><?php esc_html_e('Use these hooks to execute code at specific points in FormFlow Pro.', 'formflow-pro'); ?></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 250px;"><?php esc_html_e('Hook Name', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Since', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->registered_hooks as $hook) : ?>
                    <tr>
                        <td><code><?php echo esc_html($hook->name); ?></code></td>
                        <td>
                            <?php echo esc_html($hook->description); ?>
                            <?php if (!empty($hook->parameters)) : ?>
                                <details style="margin-top: 5px;">
                                    <summary><?php esc_html_e('Parameters', 'formflow-pro'); ?></summary>
                                    <ul style="margin: 5px 0 0 20px;">
                                        <?php foreach ($hook->parameters as $param) : ?>
                                            <li><code>$<?php echo esc_html($param['name']); ?></code> (<?php echo esc_html($param['type']); ?>) - <?php echo esc_html($param['description']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($hook->since); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render filters tab
     */
    private function renderFiltersTab(): void
    {
        ?>
        <h2><?php esc_html_e('Available Filters', 'formflow-pro'); ?></h2>
        <p><?php esc_html_e('Use these filters to modify data at specific points in FormFlow Pro.', 'formflow-pro'); ?></p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 250px;"><?php esc_html_e('Filter Name', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Returns', 'formflow-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Since', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->registered_filters as $filter) : ?>
                    <tr>
                        <td><code><?php echo esc_html($filter->name); ?></code></td>
                        <td>
                            <?php echo esc_html($filter->description); ?>
                            <?php if (!empty($filter->parameters)) : ?>
                                <details style="margin-top: 5px;">
                                    <summary><?php esc_html_e('Parameters', 'formflow-pro'); ?></summary>
                                    <ul style="margin: 5px 0 0 20px;">
                                        <?php foreach ($filter->parameters as $param) : ?>
                                            <li><code>$<?php echo esc_html($param['name']); ?></code> (<?php echo esc_html($param['type']); ?>) - <?php echo esc_html($param['description']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo esc_html($filter->return_type); ?></code></td>
                        <td><?php echo esc_html($filter->since); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render extensions tab
     */
    private function renderExtensionsTab(): void
    {
        ?>
        <h2><?php esc_html_e('Extension Points', 'formflow-pro'); ?></h2>
        <p><?php esc_html_e('Register custom components using these extension points.', 'formflow-pro'); ?></p>

        <div class="ffp-extension-points">
            <?php foreach ($this->extension_points as $key => $point) : ?>
                <div class="ffp-dev-card">
                    <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></h3>
                    <p><?php echo esc_html($point['description']); ?></p>
                    <h4><?php esc_html_e('Registration Function:', 'formflow-pro'); ?></h4>
                    <code><?php echo esc_html($point['callback']); ?></code>
                    <h4><?php esc_html_e('Example:', 'formflow-pro'); ?></h4>
                    <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;"><code><?php echo esc_html($point['example']); ?></code></pre>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render API tab
     */
    private function renderAPITab(): void
    {
        ?>
        <h2><?php esc_html_e('REST API Reference', 'formflow-pro'); ?></h2>
        <p><?php esc_html_e('FormFlow Pro provides a comprehensive REST API for integration.', 'formflow-pro'); ?></p>

        <div class="ffp-dev-card">
            <h3><?php esc_html_e('Base URL', 'formflow-pro'); ?></h3>
            <code><?php echo esc_html(rest_url(self::API_NAMESPACE)); ?></code>
        </div>

        <div class="ffp-dev-card">
            <h3><?php esc_html_e('Authentication', 'formflow-pro'); ?></h3>
            <p><?php esc_html_e('Use Application Passwords or JWT for authentication.', 'formflow-pro'); ?></p>
            <pre style="background: #f5f5f5; padding: 10px;">Authorization: Basic {base64_encoded_credentials}
// or
Authorization: Bearer {jwt_token}</pre>
        </div>

        <div class="ffp-dev-card">
            <h3><?php esc_html_e('SDK Endpoints', 'formflow-pro'); ?></h3>
            <table class="wp-list-table widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Endpoint', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Method', 'formflow-pro'); ?></th>
                        <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>/sdk/info</code></td>
                        <td>GET</td>
                        <td><?php esc_html_e('Get SDK information', 'formflow-pro'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/sdk/hooks</code></td>
                        <td>GET</td>
                        <td><?php esc_html_e('List all available hooks', 'formflow-pro'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/sdk/filters</code></td>
                        <td>GET</td>
                        <td><?php esc_html_e('List all available filters', 'formflow-pro'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/sdk/extension-points</code></td>
                        <td>GET</td>
                        <td><?php esc_html_e('List extension points', 'formflow-pro'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/sdk/validate-extension</code></td>
                        <td>POST</td>
                        <td><?php esc_html_e('Validate extension manifest', 'formflow-pro'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/sdk/scaffold</code></td>
                        <td>POST</td>
                        <td><?php esc_html_e('Generate extension scaffold', 'formflow-pro'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render scaffold tab
     */
    private function renderScaffoldTab(): void
    {
        ?>
        <h2><?php esc_html_e('Extension Scaffold Generator', 'formflow-pro'); ?></h2>
        <p><?php esc_html_e('Generate boilerplate code for your extension.', 'formflow-pro'); ?></p>

        <div class="ffp-dev-card">
            <form id="ffp-scaffold-form">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ext-name"><?php esc_html_e('Extension Name', 'formflow-pro'); ?></label></th>
                        <td><input type="text" id="ext-name" name="name" class="regular-text" required placeholder="My Extension" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ext-slug"><?php esc_html_e('Slug', 'formflow-pro'); ?></label></th>
                        <td><input type="text" id="ext-slug" name="slug" class="regular-text" required placeholder="my-extension" pattern="[a-z0-9-]+" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ext-type"><?php esc_html_e('Extension Type', 'formflow-pro'); ?></label></th>
                        <td>
                            <select id="ext-type" name="type">
                                <option value="basic"><?php esc_html_e('Basic Extension', 'formflow-pro'); ?></option>
                                <option value="field-type"><?php esc_html_e('Custom Field Type', 'formflow-pro'); ?></option>
                                <option value="integration"><?php esc_html_e('Integration', 'formflow-pro'); ?></option>
                                <option value="workflow-action"><?php esc_html_e('Workflow Action', 'formflow-pro'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ext-author"><?php esc_html_e('Author', 'formflow-pro'); ?></label></th>
                        <td><input type="text" id="ext-author" name="author" class="regular-text" placeholder="Your Name" /></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Generate Scaffold', 'formflow-pro'); ?></button>
                </p>
            </form>

            <div id="ffp-scaffold-result" style="display: none;">
                <h3><?php esc_html_e('Generated Files', 'formflow-pro'); ?></h3>
                <div id="ffp-scaffold-files"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#ext-name').on('input', function() {
                var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                $('#ext-slug').val(slug);
            });

            $('#ffp-scaffold-form').on('submit', function(e) {
                e.preventDefault();

                var data = {
                    name: $('#ext-name').val(),
                    slug: $('#ext-slug').val(),
                    type: $('#ext-type').val(),
                    author: $('#ext-author').val()
                };

                $.ajax({
                    url: '<?php echo esc_url(rest_url(self::API_NAMESPACE . '/sdk/scaffold')); ?>',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
                    },
                    success: function(response) {
                        var html = '';
                        $.each(response.files, function(filename, content) {
                            html += '<div class="ffp-scaffold-file">';
                            html += '<h4><span class="dashicons dashicons-media-code"></span> ' + filename + '</h4>';
                            html += '<pre><code>' + $('<div>').text(content).html() + '</code></pre>';
                            html += '</div>';
                        });
                        $('#ffp-scaffold-files').html(html);
                        $('#ffp-scaffold-result').show();
                    },
                    error: function(xhr) {
                        alert('Error generating scaffold: ' + xhr.responseJSON.message);
                    }
                });
            });
        });
        </script>

        <style>
            .ffp-scaffold-file { margin-bottom: 20px; }
            .ffp-scaffold-file h4 { margin: 0 0 5px; background: #f5f5f5; padding: 8px; border-radius: 4px 4px 0 0; }
            .ffp-scaffold-file h4 .dashicons { margin-right: 5px; }
            .ffp-scaffold-file pre { margin: 0; background: #282c34; color: #abb2bf; padding: 15px; border-radius: 0 0 4px 4px; overflow-x: auto; max-height: 400px; }
            .ffp-scaffold-file code { font-family: 'Fira Code', 'Consolas', monospace; font-size: 13px; }
        </style>
        <?php
    }
}
