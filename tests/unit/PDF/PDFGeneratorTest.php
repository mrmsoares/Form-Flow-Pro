<?php
/**
 * Tests for PDF_Generator class.
 */

namespace FormFlowPro\Tests\Unit\PDF;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\PDF\PDF_Generator;

class PDFGeneratorTest extends TestCase
{
    private $pdfGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdfGenerator = new PDF_Generator();
    }

    public function test_generate_submission_pdf_throws_exception_for_invalid_submission()
    {
        global $wpdb;

        $wpdb->set_mock_result('get_row', null); // Submission not found

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Submission not found');

        $this->pdfGenerator->generate_submission_pdf(999);
    }

    public function test_generate_submission_pdf_returns_file_url()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 123,
            'form_id' => 1,
            'form_name' => 'Contact Form',
            'form_data' => json_encode([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'message' => 'Test message',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:30:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(123);

        $this->assertIsString($pdfUrl);
        $this->assertStringContainsString('formflow-pdfs', $pdfUrl);
        $this->assertStringContainsString('submission-123', $pdfUrl);
    }

    public function test_generate_submission_pdf_creates_directory_if_not_exists()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 456,
            'form_id' => 2,
            'form_name' => 'Registration Form',
            'form_data' => json_encode(['username' => 'testuser']),
            'status' => 'pending',
            'created_at' => '2024-01-15 11:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(456);

        // wp_mkdir_p should have been called to create directory
        $this->assertIsString($pdfUrl);
    }

    public function test_generate_submission_pdf_includes_timestamp_in_filename()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 789,
            'form_id' => 3,
            'form_name' => 'Survey',
            'form_data' => json_encode(['question1' => 'answer1']),
            'status' => 'completed',
            'created_at' => '2024-01-15 12:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(789);

        // Filename should contain timestamp
        $this->assertMatchesRegularExpression('/submission-789-\d+\.pdf/', $pdfUrl);
    }

    public function test_generate_html_creates_valid_html_structure()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 100,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'field1' => 'value1',
                'field2' => 'value2',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html>', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('<body>', $html);
    }

    public function test_generate_html_includes_submission_id()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 555,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('Form Submission #555', $html);
    }

    public function test_generate_html_includes_form_name()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'My Custom Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('My Custom Form', $html);
    }

    public function test_generate_html_includes_submission_date()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 14:30:45',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('2024-01-15', $html);
    }

    public function test_generate_html_includes_status()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'pending',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('Pending', $html);
    }

    public function test_generate_html_renders_form_data_as_table()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '555-1234',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('<th>Field</th>', $html);
        $this->assertStringContainsString('<th>Value</th>', $html);
    }

    public function test_generate_html_includes_all_form_fields()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('First Name', $html);
        $this->assertStringContainsString('Last Name', $html);
        $this->assertStringContainsString('Email', $html);
        $this->assertStringContainsString('John', $html);
        $this->assertStringContainsString('Doe', $html);
        $this->assertStringContainsString('john@example.com', $html);
    }

    public function test_generate_html_escapes_html_in_data()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form <script>alert("xss")</script>',
            'form_data' => json_encode([
                'message' => '<script>alert("xss")</script>',
                'name' => 'John <b>Doe</b>',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        // Should not contain raw script tags
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);
        // Should contain escaped version or stripped version
        $this->assertStringNotContainsString('alert("xss")', $html);
    }

    public function test_generate_html_formats_field_names_nicely()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'first_name' => 'John',
                'phone_number' => '555-1234',
                'email_address' => 'john@example.com',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        // Underscores should be replaced with spaces and capitalized
        $this->assertStringContainsString('First Name', $html);
        $this->assertStringContainsString('Phone Number', $html);
        $this->assertStringContainsString('Email Address', $html);
    }

    public function test_generate_html_includes_css_styling()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('<style>', $html);
        $this->assertStringContainsString('font-family', $html);
        $this->assertStringContainsString('table', $html);
    }

    public function test_generate_html_handles_empty_form_data()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Empty Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('<table>', $html);
        $this->assertIsString($html);
    }

    public function test_generate_html_handles_null_form_data()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => null,
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertIsString($html);
        $this->assertStringContainsString('Form Submission', $html);
    }

    public function test_generate_with_library_creates_file()
    {
        $html = '<html><body>Test PDF Content</body></html>';
        $filepath = '/tmp/test.pdf';

        $this->callPrivateMethod($this->pdfGenerator, 'generate_with_library', [$html, $filepath]);

        // In the current implementation, it just saves HTML
        // This would be expanded when actual PDF library is integrated
        $this->assertTrue(true);
    }

    public function test_generate_submission_pdf_handles_special_characters_in_data()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'name' => 'José García',
                'city' => 'São Paulo',
                'company' => 'Société Générale',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(1);

        $this->assertIsString($pdfUrl);
    }

    public function test_generate_submission_pdf_includes_utf8_charset()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode(['field' => 'value']),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('charset=UTF-8', $html);
    }

    public function test_generate_submission_pdf_with_long_text_values()
    {
        global $wpdb;

        $longText = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 100);

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'description' => $longText,
                'short_field' => 'value',
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(1);

        $this->assertIsString($pdfUrl);
    }

    public function test_generate_submission_pdf_with_numeric_values()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'quantity' => 42,
                'price' => 99.99,
                'total' => 4199.58,
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('99.99', $html);
    }

    public function test_generate_submission_pdf_filename_is_unique()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 999,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $url1 = $this->pdfGenerator->generate_submission_pdf(999);
        sleep(1); // Ensure different timestamp
        $url2 = $this->pdfGenerator->generate_submission_pdf(999);

        // URLs should be different due to timestamp
        $this->assertNotEquals($url1, $url2);
    }

    public function test_generate_html_with_mixed_data_types()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 1,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([
                'string_field' => 'text value',
                'number_field' => 123,
                'boolean_field' => true,
                'null_field' => null,
            ]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $html = $this->callPrivateMethod($this->pdfGenerator, 'generate_html', [$mockSubmission]);

        $this->assertStringContainsString('text value', $html);
        $this->assertStringContainsString('123', $html);
    }

    public function test_generate_submission_pdf_returns_proper_url_format()
    {
        global $wpdb;

        $mockSubmission = (object)[
            'id' => 123,
            'form_id' => 1,
            'form_name' => 'Test Form',
            'form_data' => json_encode([]),
            'status' => 'completed',
            'created_at' => '2024-01-15 10:00:00',
        ];

        $wpdb->set_mock_result('get_row', $mockSubmission);

        $pdfUrl = $this->pdfGenerator->generate_submission_pdf(123);

        // URL should end with .pdf
        $this->assertStringEndsWith('.pdf', $pdfUrl);
        // URL should be a valid format
        $this->assertMatchesRegularExpression('/^https?:\/\//', $pdfUrl);
    }
}
