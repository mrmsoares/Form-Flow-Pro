<?php

declare(strict_types=1);

namespace FormFlowPro\Notifications;

/**
 * Visual Email Template Builder
 *
 * Drag-and-drop email builder with:
 * - Pre-built components (text, image, button, divider, spacer)
 * - Responsive templates
 * - Dynamic variables
 * - Template library
 * - Preview and testing
 * - MJML support for responsive emails
 *
 * @package FormFlowPro\Notifications
 * @since 2.4.0
 */
class EmailBuilder
{
    private static ?EmailBuilder $instance = null;
    private string $tableTemplates;

    private function __construct()
    {
        global $wpdb;
        $this->tableTemplates = $wpdb->prefix . 'formflow_email_templates';

        $this->initHooks();
    }

    public static function getInstance(): EmailBuilder
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initHooks(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueBuilderAssets']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Create database table
     */
    public function createTable(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableTemplates} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            subject VARCHAR(255) NULL,
            preview_text VARCHAR(255) NULL,
            content_json LONGTEXT NOT NULL,
            content_html LONGTEXT NULL,
            content_text TEXT NULL,
            category VARCHAR(100) DEFAULT 'general',
            is_system TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            variables JSON NULL,
            settings JSON NULL,
            thumbnail VARCHAR(255) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_category (category),
            INDEX idx_active (is_active)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Insert default templates
        $this->installDefaultTemplates();
    }

    /**
     * Install default templates
     */
    private function installDefaultTemplates(): void
    {
        $defaults = [
            [
                'name' => 'Form Submission Notification',
                'slug' => 'submission-notification',
                'subject' => 'New Form Submission: {{form_name}}',
                'category' => 'notifications',
                'content_json' => json_encode($this->getSubmissionNotificationTemplate()),
                'is_system' => 1,
                'variables' => json_encode([
                    'form_name', 'submission_date', 'submission_data',
                    'submission_url', 'site_name', 'site_url',
                ]),
            ],
            [
                'name' => 'Welcome Email',
                'slug' => 'welcome-email',
                'subject' => 'Welcome to {{site_name}}!',
                'category' => 'onboarding',
                'content_json' => json_encode($this->getWelcomeTemplate()),
                'is_system' => 1,
                'variables' => json_encode([
                    'user_name', 'user_email', 'site_name', 'site_url', 'login_url',
                ]),
            ],
            [
                'name' => 'Document Signed',
                'slug' => 'document-signed',
                'subject' => 'Document Signed: {{document_name}}',
                'category' => 'autentique',
                'content_json' => json_encode($this->getDocumentSignedTemplate()),
                'is_system' => 1,
                'variables' => json_encode([
                    'document_name', 'signer_name', 'signed_date', 'download_url',
                ]),
            ],
        ];

        global $wpdb;

        foreach ($defaults as $template) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$this->tableTemplates} WHERE slug = %s",
                    $template['slug']
                )
            );

            if (!$exists) {
                $template['content_html'] = $this->renderTemplate(
                    json_decode($template['content_json'], true)
                );
                $template['created_at'] = current_time('mysql');

                $wpdb->insert($this->tableTemplates, $template);
            }
        }
    }

    /**
     * Get submission notification template structure
     */
    private function getSubmissionNotificationTemplate(): array
    {
        return [
            'settings' => [
                'backgroundColor' => '#f4f4f4',
                'contentWidth' => 600,
                'fontFamily' => 'Arial, sans-serif',
            ],
            'rows' => [
                [
                    'type' => 'header',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'image',
                                    'src' => '{{logo_url}}',
                                    'alt' => '{{site_name}}',
                                    'width' => 150,
                                    'align' => 'center',
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#ffffff',
                        'padding' => '20px',
                    ],
                ],
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<h1 style="color:#333;margin:0;">New Form Submission</h1>',
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '<p style="color:#666;font-size:16px;">You have received a new submission for <strong>{{form_name}}</strong>.</p>',
                                ],
                                [
                                    'type' => 'divider',
                                    'color' => '#eeeeee',
                                    'thickness' => 1,
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '{{submission_data}}',
                                ],
                                [
                                    'type' => 'button',
                                    'text' => 'View Submission',
                                    'url' => '{{submission_url}}',
                                    'backgroundColor' => '#0073aa',
                                    'textColor' => '#ffffff',
                                    'align' => 'center',
                                    'borderRadius' => 4,
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#ffffff',
                        'padding' => '30px',
                    ],
                ],
                [
                    'type' => 'footer',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<p style="color:#999;font-size:12px;text-align:center;">© {{year}} {{site_name}}</p>',
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#f4f4f4',
                        'padding' => '20px',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get welcome email template structure
     */
    private function getWelcomeTemplate(): array
    {
        return [
            'settings' => [
                'backgroundColor' => '#f0f4f8',
                'contentWidth' => 600,
                'fontFamily' => 'Arial, sans-serif',
            ],
            'rows' => [
                [
                    'type' => 'header',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'image',
                                    'src' => '{{logo_url}}',
                                    'alt' => '{{site_name}}',
                                    'width' => 150,
                                    'align' => 'center',
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#667eea',
                        'padding' => '30px',
                    ],
                ],
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<h1 style="color:#333;margin:0;text-align:center;">Welcome, {{user_name}}!</h1>',
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '<p style="color:#666;font-size:16px;text-align:center;">Thank you for joining {{site_name}}. We\'re excited to have you on board!</p>',
                                ],
                                [
                                    'type' => 'spacer',
                                    'height' => 20,
                                ],
                                [
                                    'type' => 'button',
                                    'text' => 'Get Started',
                                    'url' => '{{login_url}}',
                                    'backgroundColor' => '#667eea',
                                    'textColor' => '#ffffff',
                                    'align' => 'center',
                                    'borderRadius' => 25,
                                    'padding' => '15px 40px',
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#ffffff',
                        'padding' => '40px 30px',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get document signed template structure
     */
    private function getDocumentSignedTemplate(): array
    {
        return [
            'settings' => [
                'backgroundColor' => '#f4f4f4',
                'contentWidth' => 600,
                'fontFamily' => 'Arial, sans-serif',
            ],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<div style="text-align:center;padding:20px 0;"><span style="font-size:48px;">✅</span></div>',
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '<h1 style="color:#28a745;margin:0;text-align:center;">Document Signed!</h1>',
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '<p style="color:#666;font-size:16px;text-align:center;"><strong>{{signer_name}}</strong> has signed <strong>{{document_name}}</strong>.</p>',
                                ],
                                [
                                    'type' => 'text',
                                    'content' => '<p style="color:#999;font-size:14px;text-align:center;">Signed on {{signed_date}}</p>',
                                ],
                                [
                                    'type' => 'button',
                                    'text' => 'Download Document',
                                    'url' => '{{download_url}}',
                                    'backgroundColor' => '#28a745',
                                    'textColor' => '#ffffff',
                                    'align' => 'center',
                                    'borderRadius' => 4,
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#ffffff',
                        'padding' => '40px 30px',
                    ],
                ],
            ],
        ];
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Email Builder', 'formflow-pro'),
            __('Email Builder', 'formflow-pro'),
            'manage_options',
            'formflow-email-builder',
            [$this, 'renderBuilderPage']
        );
    }

    /**
     * Enqueue builder assets
     */
    public function enqueueBuilderAssets(string $hook): void
    {
        if (strpos($hook, 'formflow-email-builder') === false) {
            return;
        }

        wp_enqueue_style(
            'formflow-email-builder',
            FORMFLOW_URL . 'assets/css/email-builder.css',
            [],
            FORMFLOW_VERSION
        );

        wp_enqueue_script(
            'formflow-email-builder',
            FORMFLOW_URL . 'assets/js/email-builder.js',
            ['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'wp-api-fetch'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('formflow-email-builder', 'formflowEmailBuilder', [
            'restUrl' => rest_url('formflow/v1/email-templates'),
            'nonce' => wp_create_nonce('wp_rest'),
            'previewUrl' => admin_url('admin-ajax.php?action=formflow_preview_email'),
            'i18n' => [
                'save' => __('Save Template', 'formflow-pro'),
                'preview' => __('Preview', 'formflow-pro'),
                'sendTest' => __('Send Test', 'formflow-pro'),
                'delete' => __('Delete', 'formflow-pro'),
                'confirmDelete' => __('Are you sure you want to delete this template?', 'formflow-pro'),
            ],
            'components' => $this->getAvailableComponents(),
        ]);
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        $namespace = 'formflow/v1';

        register_rest_route($namespace, '/email-templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetTemplates'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateTemplate'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route($namespace, '/email-templates/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetTemplate'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'restUpdateTemplate'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteTemplate'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route($namespace, '/email-templates/render', [
            'methods' => 'POST',
            'callback' => [$this, 'restRenderTemplate'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route($namespace, '/email-templates/send-test', [
            'methods' => 'POST',
            'callback' => [$this, 'restSendTest'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * REST: Get templates
     */
    public function restGetTemplates(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $category = $request->get_param('category');
        $where = $category ? $wpdb->prepare('WHERE category = %s', $category) : '';

        $templates = $wpdb->get_results(
            "SELECT id, name, slug, subject, category, is_system, is_active,
                    thumbnail, created_at, updated_at
            FROM {$this->tableTemplates}
            {$where}
            ORDER BY name ASC",
            ARRAY_A
        );

        return new \WP_REST_Response($templates);
    }

    /**
     * REST: Get single template
     */
    public function restGetTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableTemplates} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$template) {
            return new \WP_REST_Response(['error' => 'Template not found'], 404);
        }

        $template['content_json'] = json_decode($template['content_json'], true);
        $template['variables'] = json_decode($template['variables'] ?? '[]', true);
        $template['settings'] = json_decode($template['settings'] ?? '{}', true);

        return new \WP_REST_Response($template);
    }

    /**
     * REST: Create template
     */
    public function restCreateTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $name = sanitize_text_field($request->get_param('name'));
        $slug = sanitize_title($request->get_param('slug') ?: $name);
        $contentJson = $request->get_param('content_json');

        // Ensure unique slug
        $slug = $this->ensureUniqueSlug($slug);

        $contentHtml = $this->renderTemplate($contentJson);

        $inserted = $wpdb->insert(
            $this->tableTemplates,
            [
                'name' => $name,
                'slug' => $slug,
                'subject' => sanitize_text_field($request->get_param('subject') ?? ''),
                'preview_text' => sanitize_text_field($request->get_param('preview_text') ?? ''),
                'content_json' => json_encode($contentJson),
                'content_html' => $contentHtml,
                'content_text' => $this->htmlToText($contentHtml),
                'category' => sanitize_text_field($request->get_param('category') ?? 'general'),
                'variables' => json_encode($request->get_param('variables') ?? []),
                'settings' => json_encode($request->get_param('settings') ?? []),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if (!$inserted) {
            return new \WP_REST_Response(['error' => 'Failed to create template'], 500);
        }

        return new \WP_REST_Response([
            'id' => $wpdb->insert_id,
            'slug' => $slug,
        ], 201);
    }

    /**
     * REST: Update template
     */
    public function restUpdateTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');
        $contentJson = $request->get_param('content_json');

        $data = [
            'name' => sanitize_text_field($request->get_param('name')),
            'subject' => sanitize_text_field($request->get_param('subject') ?? ''),
            'preview_text' => sanitize_text_field($request->get_param('preview_text') ?? ''),
            'category' => sanitize_text_field($request->get_param('category') ?? 'general'),
            'updated_at' => current_time('mysql'),
        ];

        if ($contentJson) {
            $data['content_json'] = json_encode($contentJson);
            $data['content_html'] = $this->renderTemplate($contentJson);
            $data['content_text'] = $this->htmlToText($data['content_html']);
        }

        if ($request->get_param('variables')) {
            $data['variables'] = json_encode($request->get_param('variables'));
        }

        if ($request->get_param('settings')) {
            $data['settings'] = json_encode($request->get_param('settings'));
        }

        $updated = $wpdb->update(
            $this->tableTemplates,
            $data,
            ['id' => $id]
        );

        return new \WP_REST_Response(['success' => $updated !== false]);
    }

    /**
     * REST: Delete template
     */
    public function restDeleteTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $id = $request->get_param('id');

        // Don't delete system templates
        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT is_system FROM {$this->tableTemplates} WHERE id = %d",
                $id
            )
        );

        if ($template && $template->is_system) {
            return new \WP_REST_Response(['error' => 'Cannot delete system template'], 403);
        }

        $deleted = $wpdb->delete($this->tableTemplates, ['id' => $id], ['%d']);

        return new \WP_REST_Response(['success' => (bool) $deleted]);
    }

    /**
     * REST: Render template
     */
    public function restRenderTemplate(\WP_REST_Request $request): \WP_REST_Response
    {
        $contentJson = $request->get_param('content_json');
        $variables = $request->get_param('variables') ?? [];

        $html = $this->renderTemplate($contentJson);
        $html = $this->replaceVariables($html, $variables);

        return new \WP_REST_Response([
            'html' => $html,
            'text' => $this->htmlToText($html),
        ]);
    }

    /**
     * REST: Send test email
     */
    public function restSendTest(\WP_REST_Request $request): \WP_REST_Response
    {
        $email = sanitize_email($request->get_param('email') ?: wp_get_current_user()->user_email);
        $subject = sanitize_text_field($request->get_param('subject') ?: 'Test Email');
        $contentJson = $request->get_param('content_json');

        $html = $this->renderTemplate($contentJson);

        // Replace variables with test data
        $testData = [
            'user_name' => 'John Doe',
            'user_email' => $email,
            'form_name' => 'Contact Form',
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'logo_url' => FORMFLOW_URL . 'assets/images/logo.png',
            'year' => date('Y'),
            'submission_url' => admin_url('admin.php?page=formflow-submissions'),
            'submission_data' => '<p><strong>Name:</strong> John Doe</p><p><strong>Email:</strong> john@example.com</p>',
        ];

        $html = $this->replaceVariables($html, $testData);

        $sent = wp_mail(
            $email,
            $subject,
            $html,
            ['Content-Type: text/html; charset=UTF-8']
        );

        return new \WP_REST_Response([
            'success' => $sent,
            'email' => $email,
        ]);
    }

    /**
     * Render template JSON to HTML
     */
    public function renderTemplate(array $template): string
    {
        $settings = $template['settings'] ?? [];
        $rows = $template['rows'] ?? [];

        $bgColor = $settings['backgroundColor'] ?? '#f4f4f4';
        $contentWidth = $settings['contentWidth'] ?? 600;
        $fontFamily = $settings['fontFamily'] ?? 'Arial, sans-serif';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title></title>
    <style>
        body { margin: 0; padding: 0; background-color: {$bgColor}; font-family: {$fontFamily}; }
        .email-wrapper { width: 100%; background-color: {$bgColor}; padding: 20px 0; }
        .email-content { max-width: {$contentWidth}px; margin: 0 auto; }
        .email-row { width: 100%; }
        .email-column { display: inline-block; vertical-align: top; }
        img { max-width: 100%; height: auto; }
        .button { display: inline-block; text-decoration: none; }
        @media only screen and (max-width: 600px) {
            .email-content { width: 100% !important; }
            .email-column { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-content">
HTML;

        foreach ($rows as $row) {
            $html .= $this->renderRow($row);
        }

        $html .= <<<HTML
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Render a row
     */
    private function renderRow(array $row): string
    {
        $styles = $this->buildInlineStyles($row['styles'] ?? []);
        $columns = $row['columns'] ?? [];

        $html = "<div class=\"email-row\" style=\"{$styles}\">";
        $html .= "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>";

        foreach ($columns as $column) {
            $width = $column['width'] ?? '100%';
            $html .= "<td class=\"email-column\" style=\"width:{$width};vertical-align:top;\">";

            foreach ($column['components'] ?? [] as $component) {
                $html .= $this->renderComponent($component);
            }

            $html .= "</td>";
        }

        $html .= "</tr></table></div>";

        return $html;
    }

    /**
     * Render a component
     */
    private function renderComponent(array $component): string
    {
        $type = $component['type'] ?? 'text';

        return match ($type) {
            'text' => $this->renderTextComponent($component),
            'image' => $this->renderImageComponent($component),
            'button' => $this->renderButtonComponent($component),
            'divider' => $this->renderDividerComponent($component),
            'spacer' => $this->renderSpacerComponent($component),
            'social' => $this->renderSocialComponent($component),
            'video' => $this->renderVideoComponent($component),
            'columns' => $this->renderColumnsComponent($component),
            default => '',
        };
    }

    private function renderTextComponent(array $component): string
    {
        $content = $component['content'] ?? '';
        $styles = $this->buildInlineStyles($component['styles'] ?? []);

        return "<div style=\"{$styles}\">{$content}</div>";
    }

    private function renderImageComponent(array $component): string
    {
        $src = esc_url($component['src'] ?? '');
        $alt = esc_attr($component['alt'] ?? '');
        $width = $component['width'] ?? 'auto';
        $align = $component['align'] ?? 'left';
        $link = $component['link'] ?? '';

        $imgStyle = "width:{$width}px;max-width:100%;height:auto;";
        $containerStyle = "text-align:{$align};";

        $img = "<img src=\"{$src}\" alt=\"{$alt}\" style=\"{$imgStyle}\">";

        if ($link) {
            $img = "<a href=\"" . esc_url($link) . "\" target=\"_blank\">{$img}</a>";
        }

        return "<div style=\"{$containerStyle}\">{$img}</div>";
    }

    private function renderButtonComponent(array $component): string
    {
        $text = esc_html($component['text'] ?? 'Button');
        $url = esc_url($component['url'] ?? '#');
        $bgColor = $component['backgroundColor'] ?? '#0073aa';
        $textColor = $component['textColor'] ?? '#ffffff';
        $align = $component['align'] ?? 'center';
        $borderRadius = $component['borderRadius'] ?? 4;
        $padding = $component['padding'] ?? '12px 24px';

        $buttonStyle = "display:inline-block;background-color:{$bgColor};color:{$textColor};" .
            "text-decoration:none;padding:{$padding};border-radius:{$borderRadius}px;" .
            "font-weight:bold;font-size:16px;";

        return "<div style=\"text-align:{$align};padding:10px 0;\">" .
            "<a href=\"{$url}\" class=\"button\" style=\"{$buttonStyle}\">{$text}</a></div>";
    }

    private function renderDividerComponent(array $component): string
    {
        $color = $component['color'] ?? '#eeeeee';
        $thickness = $component['thickness'] ?? 1;
        $margin = $component['margin'] ?? '20px 0';

        return "<hr style=\"border:none;border-top:{$thickness}px solid {$color};margin:{$margin};\">";
    }

    private function renderSpacerComponent(array $component): string
    {
        $height = $component['height'] ?? 20;

        return "<div style=\"height:{$height}px;line-height:{$height}px;\">&nbsp;</div>";
    }

    private function renderSocialComponent(array $component): string
    {
        $icons = $component['icons'] ?? [];
        $align = $component['align'] ?? 'center';
        $iconSize = $component['iconSize'] ?? 32;

        $html = "<div style=\"text-align:{$align};padding:10px 0;\">";

        foreach ($icons as $icon) {
            $url = esc_url($icon['url'] ?? '#');
            $src = esc_url($icon['icon'] ?? '');
            $alt = esc_attr($icon['name'] ?? '');

            $html .= "<a href=\"{$url}\" target=\"_blank\" style=\"display:inline-block;margin:0 5px;\">";
            $html .= "<img src=\"{$src}\" alt=\"{$alt}\" style=\"width:{$iconSize}px;height:{$iconSize}px;\">";
            $html .= "</a>";
        }

        $html .= "</div>";

        return $html;
    }

    private function renderVideoComponent(array $component): string
    {
        $thumbnailUrl = esc_url($component['thumbnail'] ?? '');
        $videoUrl = esc_url($component['url'] ?? '');
        $align = $component['align'] ?? 'center';

        return "<div style=\"text-align:{$align};\">" .
            "<a href=\"{$videoUrl}\" target=\"_blank\">" .
            "<img src=\"{$thumbnailUrl}\" alt=\"Play Video\" style=\"max-width:100%;\">" .
            "</a></div>";
    }

    private function renderColumnsComponent(array $component): string
    {
        $columns = $component['columns'] ?? [];

        $html = "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr>";

        foreach ($columns as $column) {
            $width = $column['width'] ?? floor(100 / count($columns)) . '%';
            $html .= "<td style=\"width:{$width};vertical-align:top;padding:0 10px;\">";

            foreach ($column['components'] ?? [] as $comp) {
                $html .= $this->renderComponent($comp);
            }

            $html .= "</td>";
        }

        $html .= "</tr></table>";

        return $html;
    }

    /**
     * Build inline styles from array
     */
    private function buildInlineStyles(array $styles): string
    {
        $styleString = '';

        foreach ($styles as $property => $value) {
            $cssProperty = $this->camelToKebab($property);
            $styleString .= "{$cssProperty}:{$value};";
        }

        return $styleString;
    }

    /**
     * Convert camelCase to kebab-case
     */
    private function camelToKebab(string $string): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }

    /**
     * Replace variables in template
     */
    public function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Convert HTML to plain text
     */
    private function htmlToText(string $html): string
    {
        // Remove style and script tags
        $text = preg_replace('/<(style|script)[^>]*>.*?<\/\1>/si', '', $html);

        // Convert common elements
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);

        // Strip remaining tags
        $text = strip_tags($text);

        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Get available components
     */
    private function getAvailableComponents(): array
    {
        return [
            [
                'type' => 'text',
                'name' => __('Text', 'formflow-pro'),
                'icon' => 'dashicons-text',
                'defaults' => ['content' => '<p>Enter your text here</p>'],
            ],
            [
                'type' => 'image',
                'name' => __('Image', 'formflow-pro'),
                'icon' => 'dashicons-format-image',
                'defaults' => ['src' => '', 'alt' => '', 'width' => 'auto'],
            ],
            [
                'type' => 'button',
                'name' => __('Button', 'formflow-pro'),
                'icon' => 'dashicons-button',
                'defaults' => [
                    'text' => 'Click Here',
                    'url' => '#',
                    'backgroundColor' => '#0073aa',
                    'textColor' => '#ffffff',
                ],
            ],
            [
                'type' => 'divider',
                'name' => __('Divider', 'formflow-pro'),
                'icon' => 'dashicons-minus',
                'defaults' => ['color' => '#eeeeee', 'thickness' => 1],
            ],
            [
                'type' => 'spacer',
                'name' => __('Spacer', 'formflow-pro'),
                'icon' => 'dashicons-arrow-up-alt2',
                'defaults' => ['height' => 20],
            ],
            [
                'type' => 'social',
                'name' => __('Social Icons', 'formflow-pro'),
                'icon' => 'dashicons-share',
                'defaults' => ['icons' => [], 'iconSize' => 32],
            ],
            [
                'type' => 'video',
                'name' => __('Video', 'formflow-pro'),
                'icon' => 'dashicons-video-alt3',
                'defaults' => ['url' => '', 'thumbnail' => ''],
            ],
            [
                'type' => 'columns',
                'name' => __('Columns', 'formflow-pro'),
                'icon' => 'dashicons-columns',
                'defaults' => ['columns' => [['width' => '50%'], ['width' => '50%']]],
            ],
        ];
    }

    /**
     * Ensure unique slug
     */
    private function ensureUniqueSlug(string $slug): string
    {
        global $wpdb;

        $originalSlug = $slug;
        $counter = 1;

        while ($wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->tableTemplates} WHERE slug = %s",
                $slug
            )
        )) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get template by slug
     */
    public function getTemplateBySlug(string $slug): ?array
    {
        global $wpdb;

        $template = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->tableTemplates} WHERE slug = %s AND is_active = 1",
                $slug
            ),
            ARRAY_A
        );

        if ($template) {
            $template['content_json'] = json_decode($template['content_json'], true);
        }

        return $template;
    }

    /**
     * Send email using template
     */
    public function sendWithTemplate(
        string $templateSlug,
        string $to,
        array $variables = [],
        array $options = []
    ): bool {
        $template = $this->getTemplateBySlug($templateSlug);

        if (!$template) {
            return false;
        }

        $subject = $this->replaceVariables($template['subject'], $variables);
        $html = $this->replaceVariables($template['content_html'], $variables);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        if (!empty($options['from_name']) && !empty($options['from_email'])) {
            $headers[] = "From: {$options['from_name']} <{$options['from_email']}>";
        }

        if (!empty($options['reply_to'])) {
            $headers[] = "Reply-To: {$options['reply_to']}";
        }

        return wp_mail($to, $subject, $html, $headers);
    }

    /**
     * Render builder page
     */
    public function renderBuilderPage(): void
    {
        ?>
        <div class="wrap formflow-email-builder-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e('Email Template Builder', 'formflow-pro'); ?>
            </h1>
            <a href="#" class="page-title-action" id="new-template-btn">
                <?php esc_html_e('Add New Template', 'formflow-pro'); ?>
            </a>

            <div id="email-builder-app">
                <!-- React/Vue app will mount here -->
                <div class="builder-loading">
                    <span class="spinner is-active"></span>
                    <?php esc_html_e('Loading builder...', 'formflow-pro'); ?>
                </div>
            </div>
        </div>
        <?php
    }
}
