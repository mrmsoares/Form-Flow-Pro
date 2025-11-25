<?php
/**
 * Action Library - Workflow action definitions and execution
 *
 * Provides a comprehensive library of actions that can be used
 * in workflow automation including email, HTTP, database, and integrations.
 *
 * @package FormFlowPro
 * @subpackage Automation
 * @since 3.0.0
 */

namespace FormFlowPro\Automation;

use FormFlowPro\Core\SingletonTrait;

/**
 * Action interface
 */
interface ActionInterface
{
    public function getId(): string;
    public function getName(): string;
    public function getDescription(): string;
    public function getCategory(): string;
    public function getConfigSchema(): array;
    public function execute(array $config, WorkflowExecution $execution): NodeResult;
}

/**
 * Abstract action base class
 */
abstract class AbstractAction implements ActionInterface
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
}

// ==================== Email Actions ====================

/**
 * Send Email action
 */
class SendEmailAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'send_email';
        $this->name = __('Send Email', 'form-flow-pro');
        $this->description = __('Send an email to one or more recipients', 'form-flow-pro');
        $this->category = 'communication';
        $this->icon = 'mail';
    }

    public function getConfigSchema(): array
    {
        return [
            'to' => [
                'type' => 'text',
                'label' => __('To', 'form-flow-pro'),
                'required' => true,
                'placeholder' => 'email@example.com, {{user.email}}',
                'description' => __('Comma-separated email addresses', 'form-flow-pro')
            ],
            'cc' => [
                'type' => 'text',
                'label' => __('CC', 'form-flow-pro'),
                'required' => false
            ],
            'bcc' => [
                'type' => 'text',
                'label' => __('BCC', 'form-flow-pro'),
                'required' => false
            ],
            'subject' => [
                'type' => 'text',
                'label' => __('Subject', 'form-flow-pro'),
                'required' => true
            ],
            'body' => [
                'type' => 'richtext',
                'label' => __('Body', 'form-flow-pro'),
                'required' => true
            ],
            'content_type' => [
                'type' => 'select',
                'label' => __('Content Type', 'form-flow-pro'),
                'options' => [
                    'html' => __('HTML', 'form-flow-pro'),
                    'text' => __('Plain Text', 'form-flow-pro')
                ],
                'default' => 'html'
            ],
            'from_name' => [
                'type' => 'text',
                'label' => __('From Name', 'form-flow-pro'),
                'required' => false
            ],
            'from_email' => [
                'type' => 'email',
                'label' => __('From Email', 'form-flow-pro'),
                'required' => false
            ],
            'reply_to' => [
                'type' => 'email',
                'label' => __('Reply-To', 'form-flow-pro'),
                'required' => false
            ],
            'attachments' => [
                'type' => 'repeater',
                'label' => __('Attachments', 'form-flow-pro'),
                'fields' => [
                    'type' => ['type' => 'select', 'options' => ['file', 'url', 'variable']],
                    'value' => ['type' => 'text']
                ]
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $to = $config['to'] ?? '';
        $subject = $config['subject'] ?? '';
        $body = $config['body'] ?? '';
        $content_type = $config['content_type'] ?? 'html';

        if (empty($to) || empty($subject)) {
            return NodeResult::failure('Missing required fields: to or subject');
        }

        $headers = [];

        if ($content_type === 'html') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        if (!empty($config['from_name']) || !empty($config['from_email'])) {
            $from_name = $config['from_name'] ?? get_bloginfo('name');
            $from_email = $config['from_email'] ?? get_option('admin_email');
            $headers[] = "From: {$from_name} <{$from_email}>";
        }

        if (!empty($config['reply_to'])) {
            $headers[] = "Reply-To: {$config['reply_to']}";
        }

        if (!empty($config['cc'])) {
            $headers[] = "Cc: {$config['cc']}";
        }

        if (!empty($config['bcc'])) {
            $headers[] = "Bcc: {$config['bcc']}";
        }

        // Handle attachments
        $attachments = [];
        foreach ($config['attachments'] ?? [] as $attachment) {
            if ($attachment['type'] === 'file' && file_exists($attachment['value'])) {
                $attachments[] = $attachment['value'];
            } elseif ($attachment['type'] === 'url') {
                // Download and attach
                $temp_file = download_url($attachment['value']);
                if (!is_wp_error($temp_file)) {
                    $attachments[] = $temp_file;
                }
            }
        }

        $result = wp_mail($to, $subject, $body, $headers, $attachments);

        // Cleanup temp files
        foreach ($attachments as $file) {
            if (strpos($file, sys_get_temp_dir()) === 0) {
                @unlink($file);
            }
        }

        if ($result) {
            return NodeResult::success([
                'sent_to' => $to,
                'subject' => $subject
            ]);
        }

        return NodeResult::failure('Failed to send email');
    }
}

/**
 * Send SMS action
 */
class SendSMSAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'send_sms';
        $this->name = __('Send SMS', 'form-flow-pro');
        $this->description = __('Send an SMS message via Twilio or other provider', 'form-flow-pro');
        $this->category = 'communication';
        $this->icon = 'smartphone';
    }

    public function getConfigSchema(): array
    {
        return [
            'to' => [
                'type' => 'text',
                'label' => __('To Phone Number', 'form-flow-pro'),
                'required' => true,
                'placeholder' => '+1234567890'
            ],
            'message' => [
                'type' => 'textarea',
                'label' => __('Message', 'form-flow-pro'),
                'required' => true,
                'maxlength' => 1600
            ],
            'provider' => [
                'type' => 'select',
                'label' => __('Provider', 'form-flow-pro'),
                'options' => [
                    'twilio' => 'Twilio',
                    'vonage' => 'Vonage (Nexmo)'
                ],
                'default' => 'twilio'
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $to = $config['to'] ?? '';
        $message = $config['message'] ?? '';
        $provider = $config['provider'] ?? 'twilio';

        if (empty($to) || empty($message)) {
            return NodeResult::failure('Missing required fields: to or message');
        }

        // Use the Notifications module
        if (class_exists('\FormFlowPro\Notifications\SMSProvider')) {
            $sms_provider = \FormFlowPro\Notifications\SMSProvider::getInstance();

            try {
                $result = $sms_provider->send($to, $message);

                if ($result['success']) {
                    return NodeResult::success([
                        'sent_to' => $to,
                        'message_id' => $result['message_id'] ?? null
                    ]);
                }

                return NodeResult::failure($result['error'] ?? 'Failed to send SMS');
            } catch (\Exception $e) {
                return NodeResult::failure('SMS error: ' . $e->getMessage());
            }
        }

        return NodeResult::failure('SMS provider not configured');
    }
}

// ==================== HTTP Actions ====================

/**
 * HTTP Request action
 */
class HTTPRequestAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'http_request';
        $this->name = __('HTTP Request', 'form-flow-pro');
        $this->description = __('Make an HTTP request to an external API', 'form-flow-pro');
        $this->category = 'integrations';
        $this->icon = 'globe';
    }

    public function getConfigSchema(): array
    {
        return [
            'url' => [
                'type' => 'text',
                'label' => __('URL', 'form-flow-pro'),
                'required' => true,
                'placeholder' => 'https://api.example.com/endpoint'
            ],
            'method' => [
                'type' => 'select',
                'label' => __('Method', 'form-flow-pro'),
                'options' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'default' => 'GET'
            ],
            'headers' => [
                'type' => 'keyvalue',
                'label' => __('Headers', 'form-flow-pro'),
                'required' => false
            ],
            'body_type' => [
                'type' => 'select',
                'label' => __('Body Type', 'form-flow-pro'),
                'options' => [
                    'json' => 'JSON',
                    'form' => 'Form Data',
                    'raw' => 'Raw'
                ],
                'default' => 'json'
            ],
            'body' => [
                'type' => 'code',
                'label' => __('Body', 'form-flow-pro'),
                'language' => 'json',
                'required' => false
            ],
            'timeout' => [
                'type' => 'number',
                'label' => __('Timeout (seconds)', 'form-flow-pro'),
                'default' => 30
            ],
            'output_variable' => [
                'type' => 'text',
                'label' => __('Store Response In Variable', 'form-flow-pro'),
                'required' => false
            ],
            'fail_on_error' => [
                'type' => 'checkbox',
                'label' => __('Fail workflow on HTTP error', 'form-flow-pro'),
                'default' => true
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'GET');

        if (empty($url)) {
            return NodeResult::failure('URL is required');
        }

        $args = [
            'method' => $method,
            'timeout' => $config['timeout'] ?? 30,
            'headers' => $config['headers'] ?? []
        ];

        // Handle body
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($config['body'])) {
            $body_type = $config['body_type'] ?? 'json';
            $body = $config['body'];

            if ($body_type === 'json') {
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = is_string($body) ? $body : wp_json_encode($body);
            } elseif ($body_type === 'form') {
                $args['body'] = is_array($body) ? $body : [];
            } else {
                $args['body'] = $body;
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return NodeResult::failure('HTTP error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);

        // Try to parse JSON response
        $parsed_body = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $parsed_body = $response_body;
        }

        // Store in variable if specified
        if (!empty($config['output_variable'])) {
            $execution->setVariable($config['output_variable'], [
                'status_code' => $status_code,
                'headers' => $response_headers->getAll(),
                'body' => $parsed_body
            ]);
        }

        // Check for HTTP errors
        $fail_on_error = $config['fail_on_error'] ?? true;
        if ($fail_on_error && $status_code >= 400) {
            return NodeResult::failure("HTTP {$status_code}: " . substr($response_body, 0, 500));
        }

        return NodeResult::success([
            'status_code' => $status_code,
            'headers' => $response_headers->getAll(),
            'body' => $parsed_body
        ]);
    }
}

/**
 * Webhook action
 */
class WebhookAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'webhook';
        $this->name = __('Call Webhook', 'form-flow-pro');
        $this->description = __('Send data to a webhook URL', 'form-flow-pro');
        $this->category = 'integrations';
        $this->icon = 'link';
    }

    public function getConfigSchema(): array
    {
        return [
            'url' => [
                'type' => 'text',
                'label' => __('Webhook URL', 'form-flow-pro'),
                'required' => true
            ],
            'payload' => [
                'type' => 'code',
                'label' => __('Payload', 'form-flow-pro'),
                'language' => 'json',
                'default' => '{{variables}}'
            ],
            'secret' => [
                'type' => 'password',
                'label' => __('Signing Secret', 'form-flow-pro'),
                'required' => false,
                'description' => __('If provided, adds X-Webhook-Signature header', 'form-flow-pro')
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $url = $config['url'] ?? '';
        $payload = $config['payload'] ?? $execution->variables;

        if (is_string($payload) && $payload === '{{variables}}') {
            $payload = $execution->variables;
        }

        $body = wp_json_encode($payload);
        $headers = ['Content-Type' => 'application/json'];

        // Add signature if secret provided
        if (!empty($config['secret'])) {
            $signature = hash_hmac('sha256', $body, $config['secret']);
            $headers['X-Webhook-Signature'] = $signature;
        }

        $response = wp_remote_post($url, [
            'body' => $body,
            'headers' => $headers,
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return NodeResult::failure('Webhook error: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 400) {
            return NodeResult::failure("Webhook returned {$status_code}");
        }

        return NodeResult::success([
            'status_code' => $status_code,
            'response' => wp_remote_retrieve_body($response)
        ]);
    }
}

// ==================== Database Actions ====================

/**
 * Database Query action
 */
class DatabaseQueryAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'database_query';
        $this->name = __('Database Query', 'form-flow-pro');
        $this->description = __('Execute a database query', 'form-flow-pro');
        $this->category = 'data';
        $this->icon = 'database';
    }

    public function getConfigSchema(): array
    {
        return [
            'operation' => [
                'type' => 'select',
                'label' => __('Operation', 'form-flow-pro'),
                'options' => [
                    'select' => __('Select (Read)', 'form-flow-pro'),
                    'insert' => __('Insert (Create)', 'form-flow-pro'),
                    'update' => __('Update', 'form-flow-pro'),
                    'delete' => __('Delete', 'form-flow-pro')
                ]
            ],
            'table' => [
                'type' => 'select',
                'label' => __('Table', 'form-flow-pro'),
                'options_source' => 'database_tables'
            ],
            'columns' => [
                'type' => 'multiselect',
                'label' => __('Columns', 'form-flow-pro'),
                'options_source' => 'table_columns',
                'condition' => ['operation' => 'select']
            ],
            'data' => [
                'type' => 'keyvalue',
                'label' => __('Data', 'form-flow-pro'),
                'condition' => ['operation' => ['insert', 'update']]
            ],
            'where' => [
                'type' => 'conditions',
                'label' => __('Where Conditions', 'form-flow-pro'),
                'condition' => ['operation' => ['select', 'update', 'delete']]
            ],
            'order_by' => [
                'type' => 'text',
                'label' => __('Order By', 'form-flow-pro'),
                'condition' => ['operation' => 'select']
            ],
            'limit' => [
                'type' => 'number',
                'label' => __('Limit', 'form-flow-pro'),
                'condition' => ['operation' => 'select']
            ],
            'output_variable' => [
                'type' => 'text',
                'label' => __('Store Result In', 'form-flow-pro')
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        global $wpdb;

        $operation = $config['operation'] ?? 'select';
        $table = $config['table'] ?? '';

        if (empty($table)) {
            return NodeResult::failure('Table is required');
        }

        // Ensure table name is safe
        $table = $wpdb->prefix . preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        try {
            switch ($operation) {
                case 'select':
                    $result = $this->executeSelect($wpdb, $table, $config);
                    break;
                case 'insert':
                    $result = $this->executeInsert($wpdb, $table, $config);
                    break;
                case 'update':
                    $result = $this->executeUpdate($wpdb, $table, $config);
                    break;
                case 'delete':
                    $result = $this->executeDelete($wpdb, $table, $config);
                    break;
                default:
                    return NodeResult::failure("Unknown operation: {$operation}");
            }

            // Store in variable
            if (!empty($config['output_variable'])) {
                $execution->setVariable($config['output_variable'], $result);
            }

            return NodeResult::success($result);
        } catch (\Exception $e) {
            return NodeResult::failure('Database error: ' . $e->getMessage());
        }
    }

    private function executeSelect(\wpdb $wpdb, string $table, array $config): array
    {
        $columns = $config['columns'] ?? ['*'];
        $columns_str = is_array($columns) ? implode(', ', $columns) : '*';

        $sql = "SELECT {$columns_str} FROM {$table}";

        // Build WHERE clause
        $where = $this->buildWhere($config['where'] ?? []);
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        // Order
        if (!empty($config['order_by'])) {
            $sql .= " ORDER BY " . sanitize_sql_orderby($config['order_by']);
        }

        // Limit
        if (!empty($config['limit'])) {
            $sql .= $wpdb->prepare(" LIMIT %d", $config['limit']);
        }

        $results = $wpdb->get_results($sql, ARRAY_A);

        return [
            'rows' => $results,
            'count' => count($results)
        ];
    }

    private function executeInsert(\wpdb $wpdb, string $table, array $config): array
    {
        $data = $config['data'] ?? [];

        if (empty($data)) {
            throw new \Exception('No data provided for insert');
        }

        $wpdb->insert($table, $data);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return [
            'inserted_id' => $wpdb->insert_id,
            'rows_affected' => $wpdb->rows_affected
        ];
    }

    private function executeUpdate(\wpdb $wpdb, string $table, array $config): array
    {
        $data = $config['data'] ?? [];
        $where_conditions = $config['where'] ?? [];

        if (empty($data)) {
            throw new \Exception('No data provided for update');
        }

        // Build simple where array
        $where = [];
        foreach ($where_conditions as $condition) {
            if (isset($condition['field']) && isset($condition['value'])) {
                $where[$condition['field']] = $condition['value'];
            }
        }

        $wpdb->update($table, $data, $where);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return [
            'rows_affected' => $wpdb->rows_affected
        ];
    }

    private function executeDelete(\wpdb $wpdb, string $table, array $config): array
    {
        $where_conditions = $config['where'] ?? [];

        // Build simple where array
        $where = [];
        foreach ($where_conditions as $condition) {
            if (isset($condition['field']) && isset($condition['value'])) {
                $where[$condition['field']] = $condition['value'];
            }
        }

        if (empty($where)) {
            throw new \Exception('WHERE conditions required for DELETE');
        }

        $wpdb->delete($table, $where);

        if ($wpdb->last_error) {
            throw new \Exception($wpdb->last_error);
        }

        return [
            'rows_affected' => $wpdb->rows_affected
        ];
    }

    private function buildWhere(array $conditions): string
    {
        if (empty($conditions)) {
            return '';
        }

        global $wpdb;
        $parts = [];

        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $value = $condition['value'] ?? '';

            if (empty($field)) {
                continue;
            }

            $field = sanitize_key($field);

            switch ($operator) {
                case 'equals':
                    $parts[] = $wpdb->prepare("{$field} = %s", $value);
                    break;
                case 'not_equals':
                    $parts[] = $wpdb->prepare("{$field} != %s", $value);
                    break;
                case 'greater_than':
                    $parts[] = $wpdb->prepare("{$field} > %s", $value);
                    break;
                case 'less_than':
                    $parts[] = $wpdb->prepare("{$field} < %s", $value);
                    break;
                case 'contains':
                    $parts[] = $wpdb->prepare("{$field} LIKE %s", '%' . $wpdb->esc_like($value) . '%');
                    break;
                case 'is_null':
                    $parts[] = "{$field} IS NULL";
                    break;
                case 'is_not_null':
                    $parts[] = "{$field} IS NOT NULL";
                    break;
            }
        }

        return implode(' AND ', $parts);
    }
}

// ==================== WordPress Actions ====================

/**
 * Create/Update Post action
 */
class PostAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'post_action';
        $this->name = __('Create/Update Post', 'form-flow-pro');
        $this->description = __('Create or update a WordPress post', 'form-flow-pro');
        $this->category = 'wordpress';
        $this->icon = 'file-text';
    }

    public function getConfigSchema(): array
    {
        return [
            'action' => [
                'type' => 'select',
                'label' => __('Action', 'form-flow-pro'),
                'options' => [
                    'create' => __('Create New', 'form-flow-pro'),
                    'update' => __('Update Existing', 'form-flow-pro')
                ]
            ],
            'post_id' => [
                'type' => 'text',
                'label' => __('Post ID', 'form-flow-pro'),
                'condition' => ['action' => 'update']
            ],
            'post_type' => [
                'type' => 'select',
                'label' => __('Post Type', 'form-flow-pro'),
                'options_source' => 'post_types',
                'default' => 'post'
            ],
            'post_title' => [
                'type' => 'text',
                'label' => __('Title', 'form-flow-pro')
            ],
            'post_content' => [
                'type' => 'richtext',
                'label' => __('Content', 'form-flow-pro')
            ],
            'post_status' => [
                'type' => 'select',
                'label' => __('Status', 'form-flow-pro'),
                'options' => [
                    'draft' => __('Draft', 'form-flow-pro'),
                    'publish' => __('Published', 'form-flow-pro'),
                    'pending' => __('Pending Review', 'form-flow-pro'),
                    'private' => __('Private', 'form-flow-pro')
                ]
            ],
            'post_author' => [
                'type' => 'text',
                'label' => __('Author ID', 'form-flow-pro')
            ],
            'meta_fields' => [
                'type' => 'keyvalue',
                'label' => __('Custom Fields', 'form-flow-pro')
            ],
            'taxonomies' => [
                'type' => 'keyvalue',
                'label' => __('Taxonomies', 'form-flow-pro'),
                'description' => __('Key: taxonomy name, Value: term IDs (comma-separated)', 'form-flow-pro')
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $action = $config['action'] ?? 'create';

        $post_data = [
            'post_type' => $config['post_type'] ?? 'post',
            'post_title' => $config['post_title'] ?? '',
            'post_content' => $config['post_content'] ?? '',
            'post_status' => $config['post_status'] ?? 'draft'
        ];

        if (!empty($config['post_author'])) {
            $post_data['post_author'] = (int)$config['post_author'];
        }

        if ($action === 'update' && !empty($config['post_id'])) {
            $post_data['ID'] = (int)$config['post_id'];
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }

        if (is_wp_error($post_id)) {
            return NodeResult::failure('Post error: ' . $post_id->get_error_message());
        }

        // Handle meta fields
        foreach ($config['meta_fields'] ?? [] as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }

        // Handle taxonomies
        foreach ($config['taxonomies'] ?? [] as $taxonomy => $terms) {
            $term_ids = array_map('intval', explode(',', $terms));
            wp_set_object_terms($post_id, $term_ids, $taxonomy);
        }

        return NodeResult::success([
            'post_id' => $post_id,
            'action' => $action
        ]);
    }
}

/**
 * Create/Update User action
 */
class UserAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'user_action';
        $this->name = __('Create/Update User', 'form-flow-pro');
        $this->description = __('Create or update a WordPress user', 'form-flow-pro');
        $this->category = 'wordpress';
        $this->icon = 'user';
    }

    public function getConfigSchema(): array
    {
        return [
            'action' => [
                'type' => 'select',
                'label' => __('Action', 'form-flow-pro'),
                'options' => [
                    'create' => __('Create New', 'form-flow-pro'),
                    'update' => __('Update Existing', 'form-flow-pro')
                ]
            ],
            'user_id' => [
                'type' => 'text',
                'label' => __('User ID', 'form-flow-pro'),
                'condition' => ['action' => 'update']
            ],
            'user_login' => [
                'type' => 'text',
                'label' => __('Username', 'form-flow-pro'),
                'condition' => ['action' => 'create']
            ],
            'user_email' => [
                'type' => 'email',
                'label' => __('Email', 'form-flow-pro')
            ],
            'user_pass' => [
                'type' => 'password',
                'label' => __('Password', 'form-flow-pro'),
                'description' => __('Leave empty to auto-generate', 'form-flow-pro')
            ],
            'first_name' => [
                'type' => 'text',
                'label' => __('First Name', 'form-flow-pro')
            ],
            'last_name' => [
                'type' => 'text',
                'label' => __('Last Name', 'form-flow-pro')
            ],
            'role' => [
                'type' => 'select',
                'label' => __('Role', 'form-flow-pro'),
                'options_source' => 'user_roles'
            ],
            'meta_fields' => [
                'type' => 'keyvalue',
                'label' => __('User Meta', 'form-flow-pro')
            ],
            'send_notification' => [
                'type' => 'checkbox',
                'label' => __('Send Welcome Email', 'form-flow-pro'),
                'default' => true
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $action = $config['action'] ?? 'create';

        $user_data = [];

        if (!empty($config['user_email'])) {
            $user_data['user_email'] = sanitize_email($config['user_email']);
        }
        if (!empty($config['first_name'])) {
            $user_data['first_name'] = sanitize_text_field($config['first_name']);
        }
        if (!empty($config['last_name'])) {
            $user_data['last_name'] = sanitize_text_field($config['last_name']);
        }
        if (!empty($config['role'])) {
            $user_data['role'] = sanitize_key($config['role']);
        }

        if ($action === 'create') {
            $user_data['user_login'] = sanitize_user($config['user_login'] ?? '');
            $user_data['user_pass'] = $config['user_pass'] ?? wp_generate_password();

            $user_id = wp_insert_user($user_data);

            if (!is_wp_error($user_id) && ($config['send_notification'] ?? true)) {
                wp_new_user_notification($user_id, null, 'both');
            }
        } else {
            $user_data['ID'] = (int)$config['user_id'];

            if (!empty($config['user_pass'])) {
                $user_data['user_pass'] = $config['user_pass'];
            }

            $user_id = wp_update_user($user_data);
        }

        if (is_wp_error($user_id)) {
            return NodeResult::failure('User error: ' . $user_id->get_error_message());
        }

        // Handle meta fields
        foreach ($config['meta_fields'] ?? [] as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }

        return NodeResult::success([
            'user_id' => $user_id,
            'action' => $action
        ]);
    }
}

// ==================== Utility Actions ====================

/**
 * Log action
 */
class LogAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'log';
        $this->name = __('Log Message', 'form-flow-pro');
        $this->description = __('Write a message to the workflow log', 'form-flow-pro');
        $this->category = 'utility';
        $this->icon = 'file-text';
    }

    public function getConfigSchema(): array
    {
        return [
            'level' => [
                'type' => 'select',
                'label' => __('Level', 'form-flow-pro'),
                'options' => [
                    'debug' => 'Debug',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error'
                ],
                'default' => 'info'
            ],
            'message' => [
                'type' => 'textarea',
                'label' => __('Message', 'form-flow-pro'),
                'required' => true
            ],
            'include_variables' => [
                'type' => 'checkbox',
                'label' => __('Include Current Variables', 'form-flow-pro'),
                'default' => false
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $level = $config['level'] ?? 'info';
        $message = $config['message'] ?? '';

        $context = [];
        if ($config['include_variables'] ?? false) {
            $context['variables'] = $execution->variables;
        }

        $execution->log($level, $message, $context);

        return NodeResult::success(['logged' => true]);
    }
}

/**
 * Sleep/Delay action
 */
class SleepAction extends AbstractAction
{
    public function __construct()
    {
        $this->id = 'sleep';
        $this->name = __('Wait/Delay', 'form-flow-pro');
        $this->description = __('Pause workflow execution for a specified duration', 'form-flow-pro');
        $this->category = 'utility';
        $this->icon = 'clock';
    }

    public function getConfigSchema(): array
    {
        return [
            'duration' => [
                'type' => 'number',
                'label' => __('Duration', 'form-flow-pro'),
                'required' => true,
                'min' => 1
            ],
            'unit' => [
                'type' => 'select',
                'label' => __('Unit', 'form-flow-pro'),
                'options' => [
                    'seconds' => __('Seconds', 'form-flow-pro'),
                    'minutes' => __('Minutes', 'form-flow-pro'),
                    'hours' => __('Hours', 'form-flow-pro')
                ],
                'default' => 'seconds'
            ]
        ];
    }

    public function execute(array $config, WorkflowExecution $execution): NodeResult
    {
        $duration = (int)($config['duration'] ?? 1);
        $unit = $config['unit'] ?? 'seconds';

        $seconds = match ($unit) {
            'minutes' => $duration * 60,
            'hours' => $duration * 3600,
            default => $duration
        };

        // For short delays, sleep directly
        if ($seconds <= 30) {
            sleep($seconds);
            return NodeResult::success(['waited_seconds' => $seconds]);
        }

        // For longer delays, return waiting status
        return NodeResult::waiting("Waiting for {$seconds} seconds");
    }
}

/**
 * Action Library - Central action management
 */
class ActionLibrary
{
    use SingletonTrait;

    private array $actions = [];

    /**
     * Initialize action library
     */
    protected function init(): void
    {
        $this->registerCoreActions();
    }

    /**
     * Register core actions
     */
    private function registerCoreActions(): void
    {
        // Communication
        $this->registerAction(new SendEmailAction());
        $this->registerAction(new SendSMSAction());

        // HTTP/Integrations
        $this->registerAction(new HTTPRequestAction());
        $this->registerAction(new WebhookAction());

        // Database
        $this->registerAction(new DatabaseQueryAction());

        // WordPress
        $this->registerAction(new PostAction());
        $this->registerAction(new UserAction());

        // Utility
        $this->registerAction(new LogAction());
        $this->registerAction(new SleepAction());

        do_action('ffp_register_actions', $this);
    }

    /**
     * Register an action
     */
    public function registerAction(ActionInterface $action): void
    {
        $this->actions[$action->getId()] = $action;
    }

    /**
     * Get all actions
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Get action by ID
     */
    public function getAction(string $id): ?ActionInterface
    {
        return $this->actions[$id] ?? null;
    }

    /**
     * Get actions by category
     */
    public function getActionsByCategory(): array
    {
        $categories = [];

        foreach ($this->actions as $action) {
            $category = $action->getCategory();
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $action->toArray();
        }

        return $categories;
    }

    /**
     * Execute an action
     */
    public function executeAction(string $action_id, array $config, WorkflowExecution $execution): NodeResult
    {
        $action = $this->getAction($action_id);

        if (!$action) {
            return NodeResult::failure("Unknown action: {$action_id}");
        }

        try {
            return $action->execute($config, $execution);
        } catch (\Exception $e) {
            return NodeResult::failure("Action error: " . $e->getMessage());
        }
    }
}
