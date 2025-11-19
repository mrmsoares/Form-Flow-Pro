<?php

declare(strict_types=1);

namespace FormFlowPro\Email;

if (!defined('ABSPATH')) exit;

/**
 * Email Template System
 */
class Email_Template
{
    private array $templates = [];

    public function __construct()
    {
        $this->load_templates();
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }

    private function load_templates(): void
    {
        $this->templates = [
            'submission_notification' => [
                'subject' => 'New Form Submission: {form_name}',
                'body' => $this->get_submission_template(),
            ],
            'submission_confirmation' => [
                'subject' => 'Thank you for your submission',
                'body' => $this->get_confirmation_template(),
            ],
            'signature_request' => [
                'subject' => 'Please sign your document',
                'body' => $this->get_signature_template(),
            ],
        ];
    }

    public function send(string $template, string $to, array $data): bool
    {
        if (!isset($this->templates[$template])) {
            return false;
        }

        $template_data = $this->templates[$template];
        $subject = $this->parse_placeholders($template_data['subject'], $data);
        $body = $this->parse_placeholders($template_data['body'], $data);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_option('formflow_email_from_name', get_bloginfo('name')) .
            ' <' . get_option('formflow_email_from_address', get_option('admin_email')) . '>',
        ];

        return wp_mail($to, $subject, $body, $headers);
    }

    private function parse_placeholders(string $content, array $data): string
    {
        foreach ($data as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }

    private function get_submission_template(): string
    {
        return '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;}
        .container{max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;}
        h1{color:#0073aa;margin-top:0;}
        .data{background:#f9f9f9;padding:15px;border-radius:4px;margin:20px 0;}
        .footer{text-align:center;color:#666;font-size:12px;margin-top:30px;}
        </style></head><body>
        <div class="container">
            <h1>New Form Submission</h1>
            <p>You have received a new submission for <strong>{form_name}</strong>.</p>
            <div class="data">{form_data}</div>
            <p><a href="{admin_url}" style="background:#0073aa;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">View Submission</a></p>
            <div class="footer">
                <p>FormFlow Pro | {site_name}</p>
            </div>
        </div>
        </body></html>';
    }

    private function get_confirmation_template(): string
    {
        return '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;">
        <div style="max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">
            <h1 style="color:#0073aa;">Thank You!</h1>
            <p>Your submission has been received successfully.</p>
            <p>We will review your information and get back to you soon.</p>
            <p style="color:#666;font-size:14px;margin-top:30px;">Best regards,<br>{site_name}</p>
        </div>
        </body></html>';
    }

    private function get_signature_template(): string
    {
        return '
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px;">
        <div style="max-width:600px;margin:0 auto;background:#fff;padding:30px;border-radius:8px;">
            <h1 style="color:#0073aa;">Signature Required</h1>
            <p>Please sign the document by clicking the link below:</p>
            <p><a href="{signature_url}" style="background:#0073aa;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;display:inline-block;">Sign Document</a></p>
            <p style="color:#666;font-size:14px;margin-top:30px;">This link will expire in 30 days.</p>
        </div>
        </body></html>';
    }

    public function set_html_content_type(): string
    {
        return 'text/html';
    }
}
