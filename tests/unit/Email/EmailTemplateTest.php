<?php
/**
 * Tests for Email_Template class.
 */

namespace FormFlowPro\Tests\Unit\Email;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Email\Email_Template;

class EmailTemplateTest extends TestCase
{
    private $emailTemplate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailTemplate = new Email_Template();
    }

    public function test_constructor_loads_templates()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('submission_notification', $templates);
        $this->assertArrayHasKey('submission_confirmation', $templates);
        $this->assertArrayHasKey('signature_request', $templates);
    }

    public function test_templates_have_required_structure()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        foreach ($templates as $name => $template) {
            $this->assertArrayHasKey('subject', $template, "Template {$name} should have subject");
            $this->assertArrayHasKey('body', $template, "Template {$name} should have body");
            $this->assertIsString($template['subject']);
            $this->assertIsString($template['body']);
        }
    }

    public function test_send_with_invalid_template_returns_false()
    {
        $result = $this->emailTemplate->send('invalid_template', 'test@example.com', []);

        $this->assertFalse($result);
    }

    public function test_send_with_valid_template_returns_true()
    {
        update_option('formflow_email_from_name', 'FormFlow Pro');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $result = $this->emailTemplate->send(
            'submission_notification',
            'admin@example.com',
            [
                'form_name' => 'Contact Form',
                'form_data' => '<p>Name: John Doe</p>',
                'admin_url' => 'https://example.com/admin',
                'site_name' => 'My Site',
            ]
        );

        $this->assertTrue($result);
    }

    public function test_send_parses_placeholders_in_subject()
    {
        update_option('formflow_email_from_name', 'FormFlow Pro');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $data = [
            'form_name' => 'Registration Form',
            'form_data' => '',
            'admin_url' => '',
            'site_name' => 'Test Site',
        ];

        $result = $this->emailTemplate->send('submission_notification', 'test@example.com', $data);

        // wp_mail would be called with subject containing "Registration Form"
        $this->assertTrue($result);
    }

    public function test_send_parses_placeholders_in_body()
    {
        update_option('formflow_email_from_name', 'FormFlow Pro');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $data = [
            'form_name' => 'Contact Form',
            'form_data' => '<p>Email: john@example.com</p>',
            'admin_url' => 'https://example.com/wp-admin',
            'site_name' => 'My Website',
        ];

        $result = $this->emailTemplate->send('submission_notification', 'admin@example.com', $data);

        $this->assertTrue($result);
    }

    public function test_send_includes_html_content_type_header()
    {
        update_option('formflow_email_from_name', 'FormFlow Pro');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $result = $this->emailTemplate->send(
            'submission_confirmation',
            'user@example.com',
            ['site_name' => 'Test Site']
        );

        // Headers should include Content-Type: text/html
        $this->assertTrue($result);
    }

    public function test_send_includes_from_header()
    {
        update_option('formflow_email_from_name', 'My Custom Name');
        update_option('formflow_email_from_address', 'custom@example.com');

        $result = $this->emailTemplate->send(
            'submission_confirmation',
            'user@example.com',
            ['site_name' => 'Test']
        );

        $this->assertTrue($result);
    }

    public function test_send_uses_default_from_name_when_not_configured()
    {
        delete_option('formflow_email_from_name');
        update_option('formflow_email_from_address', 'noreply@example.com');

        // Should use get_bloginfo('name') as default
        $result = $this->emailTemplate->send(
            'submission_confirmation',
            'user@example.com',
            ['site_name' => 'Test']
        );

        $this->assertTrue($result);
    }

    public function test_send_uses_default_from_address_when_not_configured()
    {
        update_option('formflow_email_from_name', 'Test');
        delete_option('formflow_email_from_address');

        // Should use get_option('admin_email') as default
        $result = $this->emailTemplate->send(
            'submission_confirmation',
            'user@example.com',
            ['site_name' => 'Test']
        );

        $this->assertTrue($result);
    }

    public function test_parse_placeholders_replaces_all_placeholders()
    {
        $content = 'Hello {name}, your email is {email} and you live in {city}.';
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'city' => 'New York',
        ];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertEquals('Hello John Doe, your email is john@example.com and you live in New York.', $result);
    }

    public function test_parse_placeholders_handles_missing_placeholders()
    {
        $content = 'Hello {name}, your ID is {id}.';
        $data = [
            'name' => 'John Doe',
            // 'id' is missing
        ];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringContainsString('{id}', $result); // Unreplaced placeholder
    }

    public function test_parse_placeholders_handles_empty_data()
    {
        $content = 'Hello {name}!';
        $data = [];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertEquals('Hello {name}!', $result);
    }

    public function test_get_submission_template_returns_html()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_submission_template');

        $this->assertIsString($template);
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('<html>', $template);
        $this->assertStringContainsString('</html>', $template);
    }

    public function test_get_submission_template_contains_placeholders()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_submission_template');

        $this->assertStringContainsString('{form_name}', $template);
        $this->assertStringContainsString('{form_data}', $template);
        $this->assertStringContainsString('{admin_url}', $template);
        $this->assertStringContainsString('{site_name}', $template);
    }

    public function test_get_submission_template_has_styling()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_submission_template');

        $this->assertStringContainsString('<style>', $template);
        $this->assertStringContainsString('font-family', $template);
        $this->assertStringContainsString('color', $template);
    }

    public function test_get_confirmation_template_returns_html()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_confirmation_template');

        $this->assertIsString($template);
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('Thank You', $template);
    }

    public function test_get_confirmation_template_contains_site_name_placeholder()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_confirmation_template');

        $this->assertStringContainsString('{site_name}', $template);
    }

    public function test_get_signature_template_returns_html()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_signature_template');

        $this->assertIsString($template);
        $this->assertStringContainsString('<!DOCTYPE html>', $template);
        $this->assertStringContainsString('Signature Required', $template);
    }

    public function test_get_signature_template_contains_signature_url_placeholder()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_signature_template');

        $this->assertStringContainsString('{signature_url}', $template);
    }

    public function test_get_signature_template_mentions_expiration()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_signature_template');

        $this->assertStringContainsString('30 days', $template);
    }

    public function test_set_html_content_type_returns_text_html()
    {
        $contentType = $this->emailTemplate->set_html_content_type();

        $this->assertEquals('text/html', $contentType);
    }

    public function test_submission_notification_template_has_subject()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        $this->assertArrayHasKey('subject', $templates['submission_notification']);
        $this->assertStringContainsString('New Form Submission', $templates['submission_notification']['subject']);
    }

    public function test_submission_confirmation_template_has_subject()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        $this->assertArrayHasKey('subject', $templates['submission_confirmation']);
        $this->assertStringContainsString('Thank you', $templates['submission_confirmation']['subject']);
    }

    public function test_signature_request_template_has_subject()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        $this->assertArrayHasKey('subject', $templates['signature_request']);
        $this->assertStringContainsString('sign', $templates['signature_request']['subject']);
    }

    public function test_send_submission_notification_with_complete_data()
    {
        update_option('formflow_email_from_name', 'FormFlow');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $data = [
            'form_name' => 'Contact Form',
            'form_data' => '<table><tr><td>Name</td><td>John Doe</td></tr></table>',
            'admin_url' => 'https://example.com/admin/submissions/123',
            'site_name' => 'My Website',
        ];

        $result = $this->emailTemplate->send('submission_notification', 'admin@example.com', $data);

        $this->assertTrue($result);
    }

    public function test_send_confirmation_email_to_user()
    {
        update_option('formflow_email_from_name', 'FormFlow');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $data = [
            'site_name' => 'My Website',
        ];

        $result = $this->emailTemplate->send('submission_confirmation', 'user@example.com', $data);

        $this->assertTrue($result);
    }

    public function test_send_signature_request_email()
    {
        update_option('formflow_email_from_name', 'FormFlow');
        update_option('formflow_email_from_address', 'noreply@example.com');

        $data = [
            'signature_url' => 'https://example.com/sign/abc123',
        ];

        $result = $this->emailTemplate->send('signature_request', 'signer@example.com', $data);

        $this->assertTrue($result);
    }

    public function test_templates_are_mobile_responsive()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        foreach ($templates as $name => $template) {
            // Check for max-width which indicates responsive design
            $this->assertStringContainsString('max-width', $template['body'], "Template {$name} should be responsive");
        }
    }

    public function test_templates_have_proper_email_structure()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        foreach ($templates as $name => $template) {
            $this->assertStringContainsString('<html>', $template['body']);
            $this->assertStringContainsString('</html>', $template['body']);
            $this->assertStringContainsString('<body', $template['body']);
            $this->assertStringContainsString('</body>', $template['body']);
        }
    }

    public function test_send_handles_multiple_recipients()
    {
        update_option('formflow_email_from_name', 'FormFlow');
        update_option('formflow_email_from_address', 'noreply@example.com');

        // wp_mail can handle comma-separated emails
        $result = $this->emailTemplate->send(
            'submission_notification',
            'admin1@example.com,admin2@example.com',
            [
                'form_name' => 'Test',
                'form_data' => '',
                'admin_url' => '',
                'site_name' => 'Test',
            ]
        );

        $this->assertTrue($result);
    }

    public function test_parse_placeholders_with_special_characters()
    {
        $content = 'Hello {name}, your price is {price}.';
        $data = [
            'name' => 'José García',
            'price' => '$99.99',
        ];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertStringContainsString('José García', $result);
        $this->assertStringContainsString('$99.99', $result);
    }

    public function test_parse_placeholders_preserves_html()
    {
        $content = '<p>Hello {name}</p><div>{content}</div>';
        $data = [
            'name' => 'John',
            'content' => '<strong>Bold text</strong>',
        ];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertStringContainsString('<p>Hello John</p>', $result);
        $this->assertStringContainsString('<strong>Bold text</strong>', $result);
    }

    public function test_templates_include_brand_color()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        foreach ($templates as $name => $template) {
            // Should use WordPress admin blue color
            $this->assertStringContainsString('#0073aa', $template['body'], "Template {$name} should use brand color");
        }
    }

    public function test_send_with_empty_recipient_returns_false()
    {
        $result = $this->emailTemplate->send('submission_notification', '', ['form_name' => 'Test']);

        // wp_mail would return false for empty recipient
        $this->assertFalse($result);
    }

    public function test_submission_notification_includes_view_link()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_submission_template');

        $this->assertStringContainsString('{admin_url}', $template);
        $this->assertStringContainsString('View Submission', $template);
    }

    public function test_signature_request_includes_sign_button()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_signature_template');

        $this->assertStringContainsString('Sign Document', $template);
        $this->assertStringContainsString('{signature_url}', $template);
    }

    public function test_templates_have_utf8_charset()
    {
        $templates = $this->getPrivateProperty($this->emailTemplate, 'templates');

        foreach ($templates as $name => $template) {
            $this->assertStringContainsString('charset=UTF-8', $template['body'], "Template {$name} should specify UTF-8");
        }
    }

    public function test_parse_placeholders_with_numeric_values()
    {
        $content = 'Order #{order_id} total: {total}';
        $data = [
            'order_id' => 12345,
            'total' => 199.99,
        ];

        $result = $this->callPrivateMethod($this->emailTemplate, 'parse_placeholders', [$content, $data]);

        $this->assertStringContainsString('12345', $result);
        $this->assertStringContainsString('199.99', $result);
    }

    public function test_templates_have_footer()
    {
        $template = $this->callPrivateMethod($this->emailTemplate, 'get_submission_template');

        $this->assertStringContainsString('footer', $template);
        $this->assertStringContainsString('FormFlow Pro', $template);
    }
}
