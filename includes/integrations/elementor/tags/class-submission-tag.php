<?php

declare(strict_types=1);

/**
 * Submission Count Dynamic Tag for Elementor
 *
 * Dynamic tag to display submission statistics.
 *
 * @package FormFlowPro\Integrations\Elementor\Tags
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor\Tags;

use Elementor\Core\DynamicTags\Tag;
use Elementor\Controls_Manager;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Submission Tag Class
 */
class Submission_Tag extends Tag
{
    /**
     * Get tag name
     *
     * @return string Tag name.
     */
    public function get_name(): string
    {
        return 'formflow-submission-count';
    }

    /**
     * Get tag title
     *
     * @return string Tag title.
     */
    public function get_title(): string
    {
        return __('FormFlow Submission Count', 'formflow-pro');
    }

    /**
     * Get tag group
     *
     * @return string|array Tag group.
     */
    public function get_group(): string
    {
        return 'formflow';
    }

    /**
     * Get tag categories
     *
     * @return array Tag categories.
     */
    public function get_categories(): array
    {
        return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
    }

    /**
     * Register tag controls
     *
     * @return void
     */
    protected function register_controls(): void
    {
        $this->add_control(
            'form_id',
            [
                'label' => __('Form', 'formflow-pro'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_formflow_forms(),
                'default' => '',
                'description' => __('Select a form to count submissions (leave empty for all forms)', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'status',
            [
                'label' => __('Status', 'formflow-pro'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __('All Statuses', 'formflow-pro'),
                    'pending' => __('Pending', 'formflow-pro'),
                    'completed' => __('Completed', 'formflow-pro'),
                    'failed' => __('Failed', 'formflow-pro'),
                    'pending_signature' => __('Pending Signature', 'formflow-pro'),
                ],
                'default' => '',
            ]
        );

        $this->add_control(
            'date_range',
            [
                'label' => __('Date Range', 'formflow-pro'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    '' => __('All Time', 'formflow-pro'),
                    'today' => __('Today', 'formflow-pro'),
                    'week' => __('This Week', 'formflow-pro'),
                    'month' => __('This Month', 'formflow-pro'),
                    'year' => __('This Year', 'formflow-pro'),
                ],
                'default' => '',
            ]
        );

        $this->add_control(
            'format',
            [
                'label' => __('Number Format', 'formflow-pro'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'plain' => __('Plain (1234)', 'formflow-pro'),
                    'formatted' => __('Formatted (1,234)', 'formflow-pro'),
                    'short' => __('Short (1.2K)', 'formflow-pro'),
                ],
                'default' => 'plain',
            ]
        );
    }

    /**
     * Render tag output
     *
     * @return void
     */
    public function render(): void
    {
        $settings = $this->get_settings();
        $count = $this->get_submission_count($settings);

        echo $this->format_number($count, $settings['format'] ?? 'plain');
    }

    /**
     * Get submission count
     *
     * @param array $settings Tag settings.
     * @return int Submission count.
     */
    private function get_submission_count(array $settings): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'formflow_submissions';
        $where_clauses = ['1=1'];
        $where_values = [];

        // Filter by form
        if (!empty($settings['form_id'])) {
            $where_clauses[] = 'form_id = %d';
            $where_values[] = intval($settings['form_id']);
        }

        // Filter by status
        if (!empty($settings['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = sanitize_text_field($settings['status']);
        }

        // Filter by date range
        if (!empty($settings['date_range'])) {
            $date_clause = $this->get_date_clause($settings['date_range']);
            if ($date_clause) {
                $where_clauses[] = $date_clause;
            }
        }

        $where_sql = implode(' AND ', $where_clauses);

        if (!empty($where_values)) {
            $sql = $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where_sql", $where_values);
        } else {
            $sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get date SQL clause
     *
     * @param string $range Date range.
     * @return string SQL clause.
     */
    private function get_date_clause(string $range): string
    {
        switch ($range) {
            case 'today':
                return "DATE(created_at) = CURDATE()";

            case 'week':
                return "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";

            case 'month':
                return "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";

            case 'year':
                return "YEAR(created_at) = YEAR(CURDATE())";

            default:
                return '';
        }
    }

    /**
     * Format number
     *
     * @param int    $number Number to format.
     * @param string $format Format type.
     * @return string Formatted number.
     */
    private function format_number(int $number, string $format): string
    {
        switch ($format) {
            case 'formatted':
                return number_format($number);

            case 'short':
                if ($number >= 1000000) {
                    return round($number / 1000000, 1) . 'M';
                } elseif ($number >= 1000) {
                    return round($number / 1000, 1) . 'K';
                }
                return (string) $number;

            default:
                return (string) $number;
        }
    }

    /**
     * Get FormFlow forms
     *
     * @return array Available forms.
     */
    private function get_formflow_forms(): array
    {
        global $wpdb;

        $forms = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}formflow_forms WHERE status = 'active' ORDER BY name ASC"
        );

        $options = ['' => __('All Forms', 'formflow-pro')];

        if ($forms) {
            foreach ($forms as $form) {
                $options[$form->id] = $form->name;
            }
        }

        return $options;
    }
}
