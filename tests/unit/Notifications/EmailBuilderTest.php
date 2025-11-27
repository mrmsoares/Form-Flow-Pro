<?php
/**
 * Tests for EmailBuilder class.
 */

namespace FormFlowPro\Tests\Unit\Notifications;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Notifications\EmailBuilder;

class EmailBuilderTest extends TestCase
{
    private $emailBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailBuilder = EmailBuilder::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = EmailBuilder::getInstance();
        $instance2 = EmailBuilder::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(EmailBuilder::class, $instance1);
    }

    public function test_create_table()
    {
        global $wpdb;

        $this->emailBuilder->createTable();

        $table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_email_templates'");
        $this->assertNotNull($table);
    }

    public function test_create_table_installs_default_templates()
    {
        global $wpdb;

        $this->emailBuilder->createTable();

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_email_templates WHERE is_system = 1"
        );

        $this->assertGreaterThan(0, $count);
    }

    public function test_get_template_by_slug()
    {
        $this->emailBuilder->createTable();

        $template = $this->emailBuilder->getTemplateBySlug('submission-notification');

        $this->assertIsArray($template);
        $this->assertArrayHasKey('name', $template);
        $this->assertArrayHasKey('slug', $template);
        $this->assertArrayHasKey('content_json', $template);
    }

    public function test_get_template_by_slug_returns_null_for_invalid()
    {
        $template = $this->emailBuilder->getTemplateBySlug('nonexistent-template');

        $this->assertNull($template);
    }

    public function test_render_template()
    {
        $template = [
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
                                    'content' => '<h1>Test Email</h1>',
                                ],
                            ],
                        ],
                    ],
                    'styles' => [
                        'backgroundColor' => '#ffffff',
                        'padding' => '20px',
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertIsString($html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Test Email', $html);
        $this->assertStringContainsString('background-color:#f4f4f4', $html);
    }

    public function test_replace_variables()
    {
        $content = 'Hello {{name}}, welcome to {{site_name}}!';
        $variables = [
            'name' => 'John Doe',
            'site_name' => 'FormFlow Pro',
        ];

        $result = $this->emailBuilder->replaceVariables($content, $variables);

        $this->assertEquals('Hello John Doe, welcome to FormFlow Pro!', $result);
    }

    public function test_send_with_template()
    {
        $this->emailBuilder->createTable();

        $result = $this->emailBuilder->sendWithTemplate(
            'submission-notification',
            'test@example.com',
            [
                'form_name' => 'Contact Form',
                'submission_date' => 'January 1, 2024',
                'site_name' => 'Test Site',
            ]
        );

        $this->assertIsBool($result);
    }

    public function test_send_with_invalid_template_returns_false()
    {
        $result = $this->emailBuilder->sendWithTemplate(
            'nonexistent-template',
            'test@example.com',
            []
        );

        $this->assertFalse($result);
    }

    public function test_rest_get_templates()
    {
        $this->emailBuilder->createTable();

        $request = new \WP_REST_Request('GET', '/formflow/v1/email-templates');
        $response = $this->emailBuilder->restGetTemplates($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_get_templates_with_category_filter()
    {
        $this->emailBuilder->createTable();

        $request = new \WP_REST_Request('GET', '/formflow/v1/email-templates');
        $request->set_param('category', 'notifications');

        $response = $this->emailBuilder->restGetTemplates($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_get_template()
    {
        global $wpdb;

        $this->emailBuilder->createTable();

        $id = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}formflow_email_templates LIMIT 1"
        );

        $request = new \WP_REST_Request('GET', '/formflow/v1/email-templates/' . $id);
        $request->set_param('id', $id);

        $response = $this->emailBuilder->restGetTemplate($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('content_json', $data);
    }

    public function test_rest_get_template_returns_404_for_invalid_id()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/email-templates/99999');
        $request->set_param('id', 99999);

        $response = $this->emailBuilder->restGetTemplate($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function test_rest_create_template()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/email-templates');
        $request->set_param('name', 'Test Template');
        $request->set_param('slug', 'test-template');
        $request->set_param('subject', 'Test Subject');
        $request->set_param('content_json', [
            'settings' => [],
            'rows' => [],
        ]);

        $response = $this->emailBuilder->restCreateTemplate($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('slug', $data);
    }

    public function test_rest_update_template()
    {
        global $wpdb;

        $this->emailBuilder->createTable();

        $id = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}formflow_email_templates WHERE is_system = 0 LIMIT 1"
        );

        if (!$id) {
            $wpdb->insert(
                $wpdb->prefix . 'formflow_email_templates',
                [
                    'name' => 'Test',
                    'slug' => 'test-update',
                    'content_json' => json_encode(['settings' => [], 'rows' => []]),
                ]
            );
            $id = $wpdb->insert_id;
        }

        $request = new \WP_REST_Request('PUT', '/formflow/v1/email-templates/' . $id);
        $request->set_param('id', $id);
        $request->set_param('name', 'Updated Template');

        $response = $this->emailBuilder->restUpdateTemplate($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
    }

    public function test_rest_delete_template()
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_email_templates',
            [
                'name' => 'To Delete',
                'slug' => 'to-delete',
                'content_json' => json_encode(['settings' => [], 'rows' => []]),
                'is_system' => 0,
            ]
        );
        $id = $wpdb->insert_id;

        $request = new \WP_REST_Request('DELETE', '/formflow/v1/email-templates/' . $id);
        $request->set_param('id', $id);

        $response = $this->emailBuilder->restDeleteTemplate($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function test_rest_delete_system_template_returns_403()
    {
        global $wpdb;

        $this->emailBuilder->createTable();

        $id = $wpdb->get_var(
            "SELECT id FROM {$wpdb->prefix}formflow_email_templates WHERE is_system = 1 LIMIT 1"
        );

        $request = new \WP_REST_Request('DELETE', '/formflow/v1/email-templates/' . $id);
        $request->set_param('id', $id);

        $response = $this->emailBuilder->restDeleteTemplate($request);

        $this->assertEquals(403, $response->get_status());
    }

    public function test_rest_render_template()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/email-templates/render');
        $request->set_param('content_json', [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<p>Hello {{name}}</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $request->set_param('variables', ['name' => 'John']);

        $response = $this->emailBuilder->restRenderTemplate($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('html', $data);
        $this->assertArrayHasKey('text', $data);
        $this->assertStringContainsString('Hello John', $data['html']);
    }

    public function test_rest_send_test_email()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/email-templates/send-test');
        $request->set_param('email', 'test@example.com');
        $request->set_param('subject', 'Test Email');
        $request->set_param('content_json', [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<p>Test content</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->emailBuilder->restSendTest($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('email', $data);
    }

    public function test_check_permission_returns_true()
    {
        $result = $this->emailBuilder->checkPermission();

        $this->assertTrue($result);
    }

    public function test_register_admin_menu()
    {
        $this->emailBuilder->registerAdminMenu();

        $this->assertTrue(true);
    }

    public function test_enqueue_builder_assets_on_builder_page()
    {
        $this->emailBuilder->enqueueBuilderAssets('formflow-email-builder');

        $this->assertTrue(true);
    }

    public function test_enqueue_builder_assets_skips_other_pages()
    {
        $this->emailBuilder->enqueueBuilderAssets('other-page');

        $this->assertTrue(true);
    }

    public function test_render_builder_page()
    {
        ob_start();
        $this->emailBuilder->renderBuilderPage();
        $output = ob_get_clean();

        $this->assertStringContainsString('Email Template Builder', $output);
        $this->assertStringContainsString('email-builder-app', $output);
    }

    public function test_render_text_component()
    {
        $template = [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'text',
                                    'content' => '<p>Test text</p>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertStringContainsString('Test text', $html);
    }

    public function test_render_button_component()
    {
        $template = [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'button',
                                    'text' => 'Click Me',
                                    'url' => 'https://example.com',
                                    'backgroundColor' => '#0073aa',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertStringContainsString('Click Me', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }

    public function test_render_image_component()
    {
        $template = [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'image',
                                    'src' => 'https://example.com/image.png',
                                    'alt' => 'Test Image',
                                    'width' => 200,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertStringContainsString('https://example.com/image.png', $html);
        $this->assertStringContainsString('Test Image', $html);
    }

    public function test_render_divider_component()
    {
        $template = [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'divider',
                                    'color' => '#cccccc',
                                    'thickness' => 2,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertStringContainsString('<hr', $html);
    }

    public function test_render_spacer_component()
    {
        $template = [
            'settings' => [],
            'rows' => [
                [
                    'type' => 'content',
                    'columns' => [
                        [
                            'width' => '100%',
                            'components' => [
                                [
                                    'type' => 'spacer',
                                    'height' => 30,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $html = $this->emailBuilder->renderTemplate($template);

        $this->assertStringContainsString('height:30px', $html);
    }
}
