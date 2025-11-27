<?php
/**
 * Tests for FieldTypes and FieldTypesRegistry classes.
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\FieldTypesRegistry;
use FormFlowPro\FormBuilder\TextField;
use FormFlowPro\FormBuilder\EmailField;
use FormFlowPro\FormBuilder\PhoneField;
use FormFlowPro\FormBuilder\SelectField;
use FormFlowPro\FormBuilder\CheckboxField;
use FormFlowPro\FormBuilder\RadioField;
use FormFlowPro\FormBuilder\FileUploadField;
use FormFlowPro\FormBuilder\DateField;
use FormFlowPro\FormBuilder\NumberField;
use FormFlowPro\FormBuilder\TextareaField;
use FormFlowPro\FormBuilder\RatingField;
use FormFlowPro\FormBuilder\SliderField;
use FormFlowPro\FormBuilder\AddressField;
use FormFlowPro\FormBuilder\NameField;

class FieldTypesTest extends TestCase
{
    private $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = FieldTypesRegistry::getInstance();
    }

    public function test_registry_is_singleton()
    {
        $instance1 = FieldTypesRegistry::getInstance();
        $instance2 = FieldTypesRegistry::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_registry_has_all_core_field_types()
    {
        $fieldTypes = $this->registry->getAll();

        $this->assertArrayHasKey('text', $fieldTypes);
        $this->assertArrayHasKey('email', $fieldTypes);
        $this->assertArrayHasKey('phone', $fieldTypes);
        $this->assertArrayHasKey('textarea', $fieldTypes);
        $this->assertArrayHasKey('number', $fieldTypes);
        $this->assertArrayHasKey('select', $fieldTypes);
        $this->assertArrayHasKey('radio', $fieldTypes);
        $this->assertArrayHasKey('checkbox', $fieldTypes);
        $this->assertArrayHasKey('date', $fieldTypes);
        $this->assertArrayHasKey('file', $fieldTypes);
    }

    public function test_text_field_renders_correctly()
    {
        $textField = new TextField();

        $field = [
            'id' => 'test_field',
            'name' => 'test_name',
            'label' => 'Test Label',
            'placeholder' => 'Enter text',
            'required' => true,
        ];

        $html = $textField->render($field);

        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('name="test_name"', $html);
        $this->assertStringContainsString('Test Label', $html);
        $this->assertStringContainsString('placeholder="Enter text"', $html);
        $this->assertStringContainsString('required', $html);
    }

    public function test_text_field_validates_min_length()
    {
        $textField = new TextField();

        $field = [
            'label' => 'Name',
            'min_length' => 3,
        ];

        $errors = $textField->validate('ab', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 3 characters', $errors[0]);
    }

    public function test_text_field_validates_max_length()
    {
        $textField = new TextField();

        $field = [
            'label' => 'Code',
            'max_length' => 5,
        ];

        $errors = $textField->validate('123456', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not exceed 5 characters', $errors[0]);
    }

    public function test_text_field_validates_pattern()
    {
        $textField = new TextField();

        $field = [
            'label' => 'Username',
            'pattern' => '^[a-zA-Z0-9_]+$',
        ];

        $errors = $textField->validate('user@name!', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('format is invalid', $errors[0]);
    }

    public function test_email_field_validates_email_format()
    {
        $emailField = new EmailField();

        $field = [
            'label' => 'Email',
            'required' => true,
        ];

        $errors = $emailField->validate('invalid-email', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('valid email address', $errors[0]);
    }

    public function test_email_field_validates_allowed_domains()
    {
        $emailField = new EmailField();

        $field = [
            'label' => 'Email',
            'allowed_domains' => ['example.com', 'test.com'],
        ];

        $errors = $emailField->validate('user@notallowed.com', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    public function test_email_field_validates_blocked_domains()
    {
        $emailField = new EmailField();

        $field = [
            'label' => 'Email',
            'blocked_domains' => ['spam.com', 'trash.com'],
        ];

        $errors = $emailField->validate('user@spam.com', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not allowed', $errors[0]);
    }

    public function test_email_field_renders_with_confirmation()
    {
        $emailField = new EmailField();

        $field = [
            'id' => 'email_field',
            'name' => 'user_email',
            'label' => 'Email',
            'confirm_email' => true,
        ];

        $html = $emailField->render($field);

        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Confirm Email', $html);
        $this->assertStringContainsString('user_email_confirm', $html);
    }

    public function test_phone_field_validates_format()
    {
        $phoneField = new PhoneField();

        $field = [
            'label' => 'Phone',
            'validate_format' => true,
        ];

        // Too short
        $errors = $phoneField->validate('123', $field);
        $this->assertNotEmpty($errors);

        // Too long
        $errors = $phoneField->validate('12345678901234567890', $field);
        $this->assertNotEmpty($errors);

        // Valid
        $errors = $phoneField->validate('1234567890', $field);
        $this->assertEmpty($errors);
    }

    public function test_number_field_validates_min_value()
    {
        $numberField = new NumberField();

        $field = [
            'label' => 'Age',
            'min' => 18,
        ];

        $errors = $numberField->validate('15', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 18', $errors[0]);
    }

    public function test_number_field_validates_max_value()
    {
        $numberField = new NumberField();

        $field = [
            'label' => 'Percentage',
            'max' => 100,
        ];

        $errors = $numberField->validate('150', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('not exceed 100', $errors[0]);
    }

    public function test_number_field_sanitizes_to_float()
    {
        $numberField = new NumberField();

        $field = [];

        $result = $numberField->sanitize('42.5', $field);

        $this->assertIsFloat($result);
        $this->assertEquals(42.5, $result);
    }

    public function test_select_field_renders_options()
    {
        $selectField = new SelectField();

        $field = [
            'id' => 'country',
            'name' => 'country',
            'label' => 'Country',
            'options' => [
                ['value' => 'us', 'label' => 'United States'],
                ['value' => 'uk', 'label' => 'United Kingdom'],
                ['value' => 'ca', 'label' => 'Canada'],
            ],
        ];

        $html = $selectField->render($field);

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('United States', $html);
        $this->assertStringContainsString('United Kingdom', $html);
        $this->assertStringContainsString('Canada', $html);
        $this->assertStringContainsString('value="us"', $html);
    }

    public function test_select_field_renders_multiple_select()
    {
        $selectField = new SelectField();

        $field = [
            'id' => 'skills',
            'name' => 'skills',
            'label' => 'Skills',
            'multiple' => true,
            'options' => [
                ['value' => 'php', 'label' => 'PHP'],
                ['value' => 'js', 'label' => 'JavaScript'],
            ],
        ];

        $html = $selectField->render($field);

        $this->assertStringContainsString('multiple', $html);
        $this->assertStringContainsString('name="skills[]"', $html);
    }

    public function test_checkbox_field_validates_min_selections()
    {
        $checkboxField = new CheckboxField();

        $field = [
            'label' => 'Interests',
            'min_selections' => 2,
        ];

        $errors = $checkboxField->validate(['option1'], $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 2 options', $errors[0]);
    }

    public function test_checkbox_field_validates_max_selections()
    {
        $checkboxField = new CheckboxField();

        $field = [
            'label' => 'Interests',
            'max_selections' => 3,
        ];

        $errors = $checkboxField->validate(['opt1', 'opt2', 'opt3', 'opt4'], $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('no more than 3 options', $errors[0]);
    }

    public function test_radio_field_renders_options()
    {
        $radioField = new RadioField();

        $field = [
            'id' => 'gender',
            'name' => 'gender',
            'label' => 'Gender',
            'options' => [
                ['value' => 'male', 'label' => 'Male'],
                ['value' => 'female', 'label' => 'Female'],
                ['value' => 'other', 'label' => 'Other'],
            ],
        ];

        $html = $radioField->render($field);

        $this->assertStringContainsString('type="radio"', $html);
        $this->assertStringContainsString('Male', $html);
        $this->assertStringContainsString('Female', $html);
        $this->assertStringContainsString('value="male"', $html);
    }

    public function test_radio_field_renders_with_other_option()
    {
        $radioField = new RadioField();

        $field = [
            'id' => 'choice',
            'name' => 'choice',
            'label' => 'Choice',
            'options' => [
                ['value' => 'a', 'label' => 'Option A'],
            ],
            'other_option' => true,
        ];

        $html = $radioField->render($field);

        $this->assertStringContainsString('Other', $html);
        $this->assertStringContainsString('value="__other__"', $html);
        $this->assertStringContainsString('Please specify', $html);
    }

    public function test_date_field_validates_min_date()
    {
        $dateField = new DateField();

        $field = [
            'label' => 'Event Date',
            'min_date' => '2024-01-01',
        ];

        $errors = $dateField->validate('2023-12-31', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot be before', $errors[0]);
    }

    public function test_date_field_validates_max_date()
    {
        $dateField = new DateField();

        $field = [
            'label' => 'Birthday',
            'max_date' => '2023-12-31',
        ];

        $errors = $dateField->validate('2024-01-01', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('cannot be after', $errors[0]);
    }

    public function test_file_upload_field_renders_with_drag_drop()
    {
        $fileField = new FileUploadField();

        $field = [
            'id' => 'resume',
            'name' => 'resume',
            'label' => 'Resume',
            'allowed_types' => ['pdf', 'doc', 'docx'],
            'max_size' => 5 * 1024 * 1024,
            'drag_drop' => true,
        ];

        $html = $fileField->render($field);

        $this->assertStringContainsString('ffp-file-dropzone', $html);
        $this->assertStringContainsString('Drag and drop', $html);
        $this->assertStringContainsString('accept=".pdf,.doc,.docx"', $html);
    }

    public function test_rating_field_validates_max_rating()
    {
        $ratingField = new RatingField();

        $field = [
            'label' => 'Rating',
            'max_rating' => 5,
        ];

        $errors = $ratingField->validate('6', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('between 0 and 5', $errors[0]);
    }

    public function test_rating_field_sanitizes_to_float()
    {
        $ratingField = new RatingField();

        $field = [];

        $result = $ratingField->sanitize('4.5', $field);

        $this->assertIsFloat($result);
        $this->assertEquals(4.5, $result);
    }

    public function test_slider_field_renders_with_value_display()
    {
        $sliderField = new SliderField();

        $field = [
            'id' => 'volume',
            'name' => 'volume',
            'label' => 'Volume',
            'min' => 0,
            'max' => 100,
            'step' => 5,
            'show_value' => true,
            'prefix' => '',
            'suffix' => '%',
        ];

        $html = $sliderField->render($field);

        $this->assertStringContainsString('type="range"', $html);
        $this->assertStringContainsString('min="0"', $html);
        $this->assertStringContainsString('max="100"', $html);
        $this->assertStringContainsString('step="5"', $html);
        $this->assertStringContainsString('ffp-slider-value', $html);
    }

    public function test_address_field_renders_all_components()
    {
        $addressField = new AddressField();

        $field = [
            'id' => 'address',
            'name' => 'address',
            'label' => 'Address',
            'show_line2' => true,
            'show_country' => true,
        ];

        $html = $addressField->render($field);

        $this->assertStringContainsString('Street Address', $html);
        $this->assertStringContainsString('Address Line 2', $html);
        $this->assertStringContainsString('City', $html);
        $this->assertStringContainsString('State/Province', $html);
        $this->assertStringContainsString('ZIP/Postal Code', $html);
        $this->assertStringContainsString('Country', $html);
    }

    public function test_name_field_renders_simple_format()
    {
        $nameField = new NameField();

        $field = [
            'id' => 'name',
            'name' => 'name',
            'label' => 'Name',
            'format' => 'simple',
        ];

        $html = $nameField->render($field);

        $this->assertStringContainsString('Full Name', $html);
        $this->assertStringContainsString('type="text"', $html);
    }

    public function test_name_field_renders_full_format()
    {
        $nameField = new NameField();

        $field = [
            'id' => 'name',
            'name' => 'name',
            'label' => 'Name',
            'format' => 'first_last',
            'show_prefix' => true,
            'show_middle' => true,
            'show_suffix' => true,
        ];

        $html = $nameField->render($field);

        $this->assertStringContainsString('Prefix', $html);
        $this->assertStringContainsString('First Name', $html);
        $this->assertStringContainsString('Middle Name', $html);
        $this->assertStringContainsString('Last Name', $html);
        $this->assertStringContainsString('Suffix', $html);
    }

    public function test_textarea_field_validates_min_length()
    {
        $textareaField = new TextareaField();

        $field = [
            'label' => 'Message',
            'min_length' => 10,
        ];

        $errors = $textareaField->validate('Short', $field);

        $this->assertNotEmpty($errors);
    }

    public function test_textarea_field_sanitizes_with_rich_editor()
    {
        $textareaField = new TextareaField();

        $field = [
            'rich_editor' => true,
        ];

        $value = '<p>Test</p><script>alert("xss")</script>';
        $result = $textareaField->sanitize($value, $field);

        $this->assertStringContainsString('<p>Test</p>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_registry_render_method()
    {
        $field = [
            'id' => 'test',
            'name' => 'test',
            'label' => 'Test',
        ];

        $html = $this->registry->render('text', $field);

        $this->assertStringContainsString('type="text"', $html);
    }

    public function test_registry_validate_method()
    {
        $field = [
            'label' => 'Email',
            'required' => true,
        ];

        $errors = $this->registry->validate('email', 'invalid', $field);

        $this->assertNotEmpty($errors);
    }

    public function test_registry_sanitize_method()
    {
        $field = [];

        $result = $this->registry->sanitize('text', 'Test Value', $field);

        $this->assertEquals('Test Value', $result);
    }

    public function test_registry_get_categories()
    {
        $categories = $this->registry->getCategories();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('basic', $categories);
        $this->assertArrayHasKey('choice', $categories);
        $this->assertArrayHasKey('datetime', $categories);
        $this->assertArrayHasKey('upload', $categories);
        $this->assertArrayHasKey('advanced', $categories);
    }

    public function test_registry_get_by_category()
    {
        $basicFields = $this->registry->getByCategory('basic');

        $this->assertNotEmpty($basicFields);
        $this->assertArrayHasKey('text', $basicFields);
        $this->assertArrayHasKey('email', $basicFields);
    }

    public function test_registry_get_schemas()
    {
        $schemas = $this->registry->getSchemas();

        $this->assertIsArray($schemas);
        $this->assertArrayHasKey('text', $schemas);

        $textSchema = $schemas['text'];
        $this->assertArrayHasKey('type', $textSchema);
        $this->assertArrayHasKey('label', $textSchema);
        $this->assertArrayHasKey('category', $textSchema);
        $this->assertArrayHasKey('settings', $textSchema);
    }

    public function test_required_field_validation()
    {
        $textField = new TextField();

        $field = [
            'label' => 'Name',
            'required' => true,
        ];

        $errors = $textField->validate('', $field);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('required', $errors[0]);
    }

    public function test_optional_field_validation_passes_empty()
    {
        $textField = new TextField();

        $field = [
            'label' => 'Name',
            'required' => false,
        ];

        $errors = $textField->validate('', $field);

        $this->assertEmpty($errors);
    }
}
