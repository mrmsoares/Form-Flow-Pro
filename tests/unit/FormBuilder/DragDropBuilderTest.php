<?php
/**
 * Tests for DragDropBuilder class.
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\DragDropBuilder;
use FormFlowPro\FormBuilder\FormStructure;
use FormFlowPro\FormBuilder\ConditionalRule;

class DragDropBuilderTest extends TestCase
{
    private $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';

        $this->builder = DragDropBuilder::getInstance();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);

        parent::tearDown();
    }

    public function test_getInstance_returns_singleton()
    {
        $instance1 = DragDropBuilder::getInstance();
        $instance2 = DragDropBuilder::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_createForm_creates_new_form()
    {
        global $wpdb;

        $data = [
            'title' => 'Contact Form',
            'description' => 'Get in touch',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
                ['id' => 'field_2', 'type' => 'email', 'label' => 'Email'],
            ],
        ];

        $wpdb->set_mock_result('insert_id', 123);

        $form = $this->builder->createForm($data);

        $this->assertInstanceOf(FormStructure::class, $form);
        $this->assertEquals('Contact Form', $form->title);
        $this->assertEquals(123, $form->id);
    }

    public function test_createForm_sets_default_title_if_empty()
    {
        global $wpdb;

        $data = [
            'title' => '',
            'fields' => [],
        ];

        $wpdb->set_mock_result('insert_id', 456);

        $form = $this->builder->createForm($data);

        $this->assertStringContainsString('Untitled Form', $form->title);
    }

    public function test_getForm_returns_form()
    {
        global $wpdb;

        $mockRow = [
            'id' => 123,
            'title' => 'Test Form',
            'description' => 'Test Description',
            'form_data' => json_encode([
                'fields' => [
                    ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
                ],
            ]),
            'settings' => json_encode(['ajax_submit' => true]),
            'styles' => json_encode(['primary_color' => '#3b82f6']),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'published',
            'version' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockRow);

        $form = $this->builder->getForm(123);

        $this->assertInstanceOf(FormStructure::class, $form);
        $this->assertEquals('Test Form', $form->title);
        $this->assertEquals('published', $form->status);
    }

    public function test_getForm_returns_null_when_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $form = $this->builder->getForm(999);

        $this->assertNull($form);
    }

    public function test_updateForm_updates_existing_form()
    {
        global $wpdb;

        $existingForm = [
            'id' => 123,
            'title' => 'Old Title',
            'description' => '',
            'form_data' => json_encode(['fields' => []]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'draft',
            'version' => 1,
        ];

        $wpdb->set_mock_result('get_row', $existingForm);
        $wpdb->set_mock_result('update', 1);

        $updateData = [
            'title' => 'New Title',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
            ],
        ];

        $form = $this->builder->updateForm(123, $updateData);

        $this->assertInstanceOf(FormStructure::class, $form);
        $this->assertEquals('New Title', $form->title);
        $this->assertEquals(2, $form->version);
    }

    public function test_updateForm_returns_null_when_form_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $form = $this->builder->updateForm(999, ['title' => 'Test']);

        $this->assertNull($form);
    }

    public function test_deleteForm_deletes_form()
    {
        global $wpdb;

        $mockForm = [
            'id' => 123,
            'title' => 'Test Form',
            'form_data' => json_encode(['fields' => []]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'draft',
            'version' => 1,
            'description' => '',
        ];

        $wpdb->set_mock_result('get_row', $mockForm);
        $wpdb->set_mock_result('delete', 1);

        $result = $this->builder->deleteForm(123);

        $this->assertTrue($result);
    }

    public function test_deleteForm_returns_false_when_not_found()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $result = $this->builder->deleteForm(999);

        $this->assertFalse($result);
    }

    public function test_duplicateForm_creates_copy()
    {
        global $wpdb;

        $originalForm = [
            'id' => 123,
            'title' => 'Original Form',
            'description' => 'Original description',
            'form_data' => json_encode([
                'fields' => [
                    ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
                ],
            ]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'published',
            'version' => 3,
        ];

        $wpdb->set_mock_result('get_row', $originalForm);
        $wpdb->set_mock_result('insert_id', 456);

        $duplicate = $this->builder->duplicateForm(123);

        $this->assertInstanceOf(FormStructure::class, $duplicate);
        $this->assertStringContainsString('(Copy)', $duplicate->title);
        $this->assertEquals('draft', $duplicate->status);
    }

    public function test_duplicateForm_generates_new_field_ids()
    {
        global $wpdb;

        $originalForm = [
            'id' => 123,
            'title' => 'Form',
            'description' => '',
            'form_data' => json_encode([
                'fields' => [
                    ['id' => 'field_old1', 'type' => 'text', 'label' => 'Field 1'],
                    ['id' => 'field_old2', 'type' => 'email', 'label' => 'Field 2'],
                ],
            ]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'published',
            'version' => 1,
        ];

        $wpdb->set_mock_result('get_row', $originalForm);
        $wpdb->set_mock_result('insert_id', 456);

        $duplicate = $this->builder->duplicateForm(123);

        // Check that new IDs were generated
        foreach ($duplicate->fields as $field) {
            $this->assertStringNotContainsString('field_old', $field['id']);
            $this->assertStringContainsString('field_', $field['id']);
        }
    }

    public function test_getForms_returns_array_of_forms()
    {
        global $wpdb;

        $mockRows = [
            [
                'id' => 1,
                'title' => 'Form 1',
                'description' => '',
                'form_data' => json_encode(['fields' => []]),
                'settings' => json_encode([]),
                'styles' => json_encode([]),
                'logic' => json_encode([]),
                'notifications' => json_encode([]),
                'status' => 'published',
                'version' => 1,
            ],
            [
                'id' => 2,
                'title' => 'Form 2',
                'description' => '',
                'form_data' => json_encode(['fields' => []]),
                'settings' => json_encode([]),
                'styles' => json_encode([]),
                'logic' => json_encode([]),
                'notifications' => json_encode([]),
                'status' => 'draft',
                'version' => 1,
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockRows);

        $forms = $this->builder->getForms();

        $this->assertIsArray($forms);
        $this->assertCount(2, $forms);
        $this->assertInstanceOf(FormStructure::class, $forms[0]);
    }

    public function test_getForms_filters_by_status()
    {
        global $wpdb;

        $mockRows = [
            [
                'id' => 1,
                'title' => 'Published Form',
                'description' => '',
                'form_data' => json_encode(['fields' => []]),
                'settings' => json_encode([]),
                'styles' => json_encode([]),
                'logic' => json_encode([]),
                'notifications' => json_encode([]),
                'status' => 'published',
                'version' => 1,
            ],
        ];

        $wpdb->set_mock_result('get_results', $mockRows);

        $forms = $this->builder->getForms(['status' => 'published']);

        $this->assertIsArray($forms);
    }

    public function test_countForms_returns_total_count()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_var', 42);

        $count = $this->builder->countForms();

        $this->assertEquals(42, $count);
    }

    public function test_countForms_filters_by_status()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_var', 10);

        $count = $this->builder->countForms('published');

        $this->assertEquals(10, $count);
    }

    public function test_renderFormShortcode_with_missing_id_returns_error()
    {
        $html = $this->builder->renderFormShortcode(['id' => 0]);

        $this->assertStringContainsString('Form ID is required', $html);
        $this->assertStringContainsString('ffp-error', $html);
    }

    public function test_renderFormShortcode_with_invalid_id_returns_error()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null);

        $html = $this->builder->renderFormShortcode(['id' => 999]);

        $this->assertStringContainsString('Form not found', $html);
    }

    public function test_renderFormShortcode_renders_published_form()
    {
        global $wpdb;

        $mockForm = [
            'id' => 123,
            'title' => 'Contact Form',
            'description' => 'Get in touch',
            'form_data' => json_encode([
                'id' => 123,
                'title' => 'Contact Form',
                'description' => 'Get in touch',
                'fields' => [
                    ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true],
                ],
                'settings' => [],
                'styles' => [],
            ]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'published',
            'version' => 1,
        ];

        $wpdb->set_mock_result('get_row', $mockForm);

        $html = $this->builder->renderFormShortcode(['id' => 123]);

        $this->assertStringContainsString('ffp-form', $html);
        $this->assertStringContainsString('Contact Form', $html);
    }

    public function test_renderForm_includes_title_when_enabled()
    {
        $formData = [
            'id' => 123,
            'title' => 'My Form',
            'description' => '',
            'fields' => [],
            'settings' => [],
            'styles' => [],
        ];

        $html = $this->builder->renderForm($formData, ['title' => true]);

        $this->assertStringContainsString('My Form', $html);
        $this->assertStringContainsString('ffp-form-title', $html);
    }

    public function test_renderForm_includes_description_when_enabled()
    {
        $formData = [
            'id' => 123,
            'title' => 'Form',
            'description' => 'This is a test form',
            'fields' => [],
            'settings' => [],
            'styles' => [],
        ];

        $html = $this->builder->renderForm($formData, ['description' => true]);

        $this->assertStringContainsString('This is a test form', $html);
        $this->assertStringContainsString('ffp-form-description', $html);
    }

    public function test_renderForm_includes_honeypot_when_enabled()
    {
        $formData = [
            'id' => 123,
            'title' => 'Form',
            'fields' => [],
            'settings' => [
                'honeypot' => true,
            ],
            'styles' => [],
        ];

        $html = $this->builder->renderForm($formData);

        $this->assertStringContainsString('ffp-hp', $html);
        $this->assertStringContainsString('ffp_hp_123', $html);
    }

    public function test_renderForm_includes_multi_step_indicator()
    {
        $formData = [
            'id' => 123,
            'title' => 'Multi-Step Form',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'label' => 'Field 1', 'step' => 0, 'name' => 'field1'],
                ['id' => 'f2', 'type' => 'text', 'label' => 'Field 2', 'step' => 1, 'name' => 'field2'],
            ],
            'steps' => [
                ['title' => 'Step 1', 'description' => ''],
                ['title' => 'Step 2', 'description' => ''],
            ],
            'settings' => [
                'multi_step' => true,
                'step_indicator' => 'progress',
            ],
            'styles' => [],
        ];

        $html = $this->builder->renderForm($formData);

        $this->assertStringContainsString('ffp-step-indicator', $html);
        $this->assertStringContainsString('ffp-progress-bar', $html);
    }

    public function test_renderForm_includes_navigation_buttons_for_multi_step()
    {
        $formData = [
            'id' => 123,
            'title' => 'Form',
            'fields' => [],
            'steps' => [
                ['title' => 'Step 1'],
                ['title' => 'Step 2'],
            ],
            'settings' => [
                'multi_step' => true,
            ],
            'styles' => [],
        ];

        $html = $this->builder->renderForm($formData);

        $this->assertStringContainsString('ffp-btn-prev', $html);
        $this->assertStringContainsString('ffp-btn-next', $html);
        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('Next', $html);
    }

    public function test_renderForm_applies_custom_styles()
    {
        $formData = [
            'id' => 123,
            'title' => 'Form',
            'fields' => [],
            'settings' => [],
            'styles' => [
                'primary_color' => '#ff0000',
                'border_radius' => '12px',
            ],
        ];

        $html = $this->builder->renderForm($formData);

        $this->assertStringContainsString('--ffp-primary: #ff0000', $html);
        $this->assertStringContainsString('--ffp-radius: 12px', $html);
    }

    public function test_conditional_rule_evaluates_equals()
    {
        $rule = new ConditionalRule([
            'target_field' => 'field_2',
            'action' => 'show',
            'conditions' => [
                [
                    'field' => 'field_1',
                    'operator' => 'equals',
                    'value' => 'yes',
                ],
            ],
            'logic' => 'all',
        ]);

        $formData = [
            'field_1' => 'yes',
        ];

        $result = $rule->evaluate($formData);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_evaluates_not_equals()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                [
                    'field' => 'status',
                    'operator' => 'not_equals',
                    'value' => 'inactive',
                ],
            ],
        ]);

        $result = $rule->evaluate(['status' => 'active']);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_evaluates_contains()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                [
                    'field' => 'message',
                    'operator' => 'contains',
                    'value' => 'urgent',
                ],
            ],
        ]);

        $result = $rule->evaluate(['message' => 'This is an urgent message']);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_evaluates_greater_than()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                [
                    'field' => 'age',
                    'operator' => 'greater_than',
                    'value' => 18,
                ],
            ],
        ]);

        $result = $rule->evaluate(['age' => 25]);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_evaluates_empty()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                [
                    'field' => 'optional_field',
                    'operator' => 'empty',
                ],
            ],
        ]);

        $result = $rule->evaluate(['optional_field' => '']);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_evaluates_checked()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                [
                    'field' => 'agree',
                    'operator' => 'checked',
                ],
            ],
        ]);

        $result = $rule->evaluate(['agree' => true]);

        $this->assertTrue($result);
    }

    public function test_conditional_rule_logic_all_requires_all_conditions()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                ['field' => 'field_1', 'operator' => 'equals', 'value' => 'yes'],
                ['field' => 'field_2', 'operator' => 'equals', 'value' => 'yes'],
            ],
            'logic' => 'all',
        ]);

        // Both conditions true
        $this->assertTrue($rule->evaluate(['field_1' => 'yes', 'field_2' => 'yes']));

        // One condition false
        $this->assertFalse($rule->evaluate(['field_1' => 'yes', 'field_2' => 'no']));
    }

    public function test_conditional_rule_logic_any_requires_one_condition()
    {
        $rule = new ConditionalRule([
            'conditions' => [
                ['field' => 'field_1', 'operator' => 'equals', 'value' => 'yes'],
                ['field' => 'field_2', 'operator' => 'equals', 'value' => 'yes'],
            ],
            'logic' => 'any',
        ]);

        // One condition true
        $this->assertTrue($rule->evaluate(['field_1' => 'yes', 'field_2' => 'no']));

        // Both false
        $this->assertFalse($rule->evaluate(['field_1' => 'no', 'field_2' => 'no']));
    }

    public function test_form_structure_has_default_settings()
    {
        $form = new FormStructure();

        $this->assertIsArray($form->settings);
        $this->assertArrayHasKey('submit_button_text', $form->settings);
        $this->assertArrayHasKey('success_message', $form->settings);
        $this->assertArrayHasKey('ajax_submit', $form->settings);
    }

    public function test_form_structure_has_default_styles()
    {
        $form = new FormStructure();

        $this->assertIsArray($form->styles);
        $this->assertArrayHasKey('primary_color', $form->styles);
        $this->assertArrayHasKey('border_radius', $form->styles);
        $this->assertArrayHasKey('field_spacing', $form->styles);
    }

    public function test_form_structure_toArray_returns_complete_data()
    {
        $form = new FormStructure([
            'id' => 123,
            'title' => 'Test Form',
            'fields' => [
                ['id' => 'field_1', 'type' => 'text'],
            ],
        ]);

        $array = $form->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('fields', $array);
        $this->assertArrayHasKey('settings', $array);
        $this->assertArrayHasKey('styles', $array);
    }

    public function test_ajaxSaveForm_creates_new_form()
    {
        global $wpdb;

        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'form_id' => 0,
            'form_data' => json_encode([
                'title' => 'New Form',
                'fields' => [],
            ]),
        ];

        set_nonce_verified(true);
        set_current_user_can('edit_posts', true);

        $wpdb->set_mock_result('insert_id', 789);

        $output = $this->callAjaxEndpoint(function() {
            $this->builder->ajaxSaveForm();
        });

        $this->assertTrue($output['success']);
        $this->assertArrayHasKey('data', $output);
    }

    public function test_ajaxSaveForm_updates_existing_form()
    {
        global $wpdb;

        $existingForm = [
            'id' => 123,
            'title' => 'Existing',
            'description' => '',
            'form_data' => json_encode(['fields' => []]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'draft',
            'version' => 1,
        ];

        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'form_id' => 123,
            'form_data' => json_encode([
                'title' => 'Updated Form',
                'fields' => [],
            ]),
        ];

        set_nonce_verified(true);
        set_current_user_can('edit_posts', true);

        $wpdb->set_mock_result('get_row', $existingForm);
        $wpdb->set_mock_result('update', 1);

        $output = $this->callAjaxEndpoint(function() {
            $this->builder->ajaxSaveForm();
        });

        $this->assertTrue($output['success']);
    }

    public function test_ajaxDuplicateForm_duplicates_form()
    {
        global $wpdb;

        $originalForm = [
            'id' => 123,
            'title' => 'Original',
            'description' => '',
            'form_data' => json_encode(['fields' => []]),
            'settings' => json_encode([]),
            'styles' => json_encode([]),
            'logic' => json_encode([]),
            'notifications' => json_encode([]),
            'status' => 'published',
            'version' => 1,
        ];

        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'form_id' => 123,
        ];

        set_nonce_verified(true);
        set_current_user_can('edit_posts', true);

        $wpdb->set_mock_result('get_row', $originalForm);
        $wpdb->set_mock_result('insert_id', 456);

        $output = $this->callAjaxEndpoint(function() {
            $this->builder->ajaxDuplicateForm();
        });

        $this->assertTrue($output['success']);
    }

    public function test_ajaxPreviewForm_generates_preview_html()
    {
        $_POST = [
            'nonce' => wp_create_nonce('ffp_nonce'),
            'form_data' => json_encode([
                'id' => 0,
                'title' => 'Preview Form',
                'description' => 'Preview description',
                'fields' => [
                    ['id' => 'f1', 'type' => 'text', 'label' => 'Name', 'name' => 'name'],
                ],
                'settings' => [],
                'styles' => [],
            ]),
        ];

        set_nonce_verified(true);

        $output = $this->callAjaxEndpoint(function() {
            $this->builder->ajaxPreviewForm();
        });

        $this->assertTrue($output['success']);
        $this->assertArrayHasKey('data', $output);
        $this->assertArrayHasKey('html', $output['data']);
        $this->assertStringContainsString('Preview Form', $output['data']['html']);
    }
}
