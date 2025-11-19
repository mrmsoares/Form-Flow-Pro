<?php

declare(strict_types=1);

namespace FormFlowPro\PDF;

if (!defined('ABSPATH')) exit;

/**
 * PDF Generator - Document Creation System
 */
class PDF_Generator
{
    public function generate_submission_pdf(int $submission_id): string
    {
        global $wpdb;

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, f.name as form_name
             FROM {$wpdb->prefix}formflow_submissions s
             LEFT JOIN {$wpdb->prefix}formflow_forms f ON s.form_id = f.id
             WHERE s.id = %d",
            $submission_id
        ));

        if (!$submission) {
            throw new \Exception('Submission not found');
        }

        $html = $this->generate_html($submission);
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/formflow-pdfs';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = "submission-{$submission_id}-" . time() . '.pdf';
        $filepath = $pdf_dir . '/' . $filename;

        // Use WP's built-in HTML to PDF if available, otherwise simple HTML file
        if (class_exists('TCPDF') || class_exists('Dompdf\\Dompdf')) {
            $this->generate_with_library($html, $filepath);
        } else {
            // Fallback: Save as HTML (can be printed as PDF)
            file_put_contents($filepath, $html);
        }

        return $upload_dir['baseurl'] . '/formflow-pdfs/' . $filename;
    }

    private function generate_html(object $submission): string
    {
        $form_data = json_decode($submission->form_data, true);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>body{font-family:Arial,sans-serif;padding:20px;}';
        $html .= 'h1{color:#0073aa;}table{width:100%;border-collapse:collapse;}';
        $html .= 'th,td{padding:10px;border:1px solid #ddd;text-align:left;}';
        $html .= 'th{background:#f5f5f5;}</style></head><body>';

        $html .= '<h1>Form Submission #' . $submission->id . '</h1>';
        $html .= '<p><strong>Form:</strong> ' . esc_html($submission->form_name) . '</p>';
        $html .= '<p><strong>Date:</strong> ' . date('Y-m-d H:i:s', strtotime($submission->created_at)) . '</p>';
        $html .= '<p><strong>Status:</strong> ' . ucfirst($submission->status) . '</p>';

        $html .= '<h2>Submission Data</h2><table><tr><th>Field</th><th>Value</th></tr>';
        foreach ($form_data as $key => $value) {
            $html .= '<tr><td>' . esc_html(ucwords(str_replace('_', ' ', $key))) . '</td>';
            $html .= '<td>' . esc_html($value) . '</td></tr>';
        }
        $html .= '</table></body></html>';

        return $html;
    }

    private function generate_with_library(string $html, string $filepath): void
    {
        // Placeholder for TCPDF/Dompdf integration
        // This would be implemented with actual PDF library
        file_put_contents($filepath, $html);
    }
}
