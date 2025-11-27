<?php
/**
 * Report Generator - Executive PDF Report Generation System
 *
 * Generates professional PDF reports with charts, KPIs, data tables,
 * and executive summaries for form submissions and analytics.
 *
 * @package FormFlowPro
 * @subpackage Reporting
 * @since 2.3.0
 */

namespace FormFlowPro\Reporting;

use FormFlowPro\Core\SingletonTrait;

/**
 * Report data model
 */
class ReportData
{
    public string $id;
    public string $title;
    public string $subtitle;
    public string $period_start;
    public string $period_end;
    public array $metrics;
    public array $charts;
    public array $tables;
    public array $sections;
    public array $metadata;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? wp_generate_uuid4();
        $this->title = $data['title'] ?? '';
        $this->subtitle = $data['subtitle'] ?? '';
        $this->period_start = $data['period_start'] ?? '';
        $this->period_end = $data['period_end'] ?? '';
        $this->metrics = $data['metrics'] ?? [];
        $this->charts = $data['charts'] ?? [];
        $this->tables = $data['tables'] ?? [];
        $this->sections = $data['sections'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'period_start' => $this->period_start,
            'period_end' => $this->period_end,
            'metrics' => $this->metrics,
            'charts' => $this->charts,
            'tables' => $this->tables,
            'sections' => $this->sections,
            'metadata' => $this->metadata
        ];
    }
}

/**
 * KPI Metric model
 */
class KPIMetric
{
    public string $id;
    public string $label;
    public $value;
    public $previous_value;
    public string $format;
    public string $trend;
    public float $change_percent;
    public string $icon;
    public string $color;
    public string $description;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->label = $data['label'] ?? '';
        $this->value = $data['value'] ?? 0;
        $this->previous_value = $data['previous_value'] ?? null;
        $this->format = $data['format'] ?? 'number';
        $this->trend = $data['trend'] ?? 'neutral';
        $this->change_percent = $data['change_percent'] ?? 0.0;
        $this->icon = $data['icon'] ?? '';
        $this->color = $data['color'] ?? '#2271b1';
        $this->description = $data['description'] ?? '';
    }

    /**
     * Calculate trend from current and previous values
     */
    public function calculateTrend(): void
    {
        if ($this->previous_value === null || $this->previous_value == 0) {
            $this->trend = 'neutral';
            $this->change_percent = 0;
            return;
        }

        $change = $this->value - $this->previous_value;
        $this->change_percent = round(($change / $this->previous_value) * 100, 2);

        if ($change > 0) {
            $this->trend = 'up';
        } elseif ($change < 0) {
            $this->trend = 'down';
        } else {
            $this->trend = 'neutral';
        }
    }

    /**
     * Format value based on format type
     */
    public function getFormattedValue(): string
    {
        switch ($this->format) {
            case 'currency':
                return '$' . number_format($this->value, 2);
            case 'percent':
                return number_format($this->value, 1) . '%';
            case 'duration':
                return $this->formatDuration($this->value);
            case 'number':
            default:
                return number_format($this->value);
        }
    }

    /**
     * Format duration in seconds to human readable
     */
    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . 'm';
        } else {
            return round($seconds / 3600, 1) . 'h';
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'value' => $this->value,
            'formatted_value' => $this->getFormattedValue(),
            'previous_value' => $this->previous_value,
            'format' => $this->format,
            'trend' => $this->trend,
            'change_percent' => $this->change_percent,
            'icon' => $this->icon,
            'color' => $this->color,
            'description' => $this->description
        ];
    }
}

/**
 * Report Section model
 */
class ReportSection
{
    public string $id;
    public string $title;
    public string $type;
    public string $content;
    public array $data;
    public array $options;
    public int $order;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? wp_generate_uuid4();
        $this->title = $data['title'] ?? '';
        $this->type = $data['type'] ?? 'text';
        $this->content = $data['content'] ?? '';
        $this->data = $data['data'] ?? [];
        $this->options = $data['options'] ?? [];
        $this->order = $data['order'] ?? 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'content' => $this->content,
            'data' => $this->data,
            'options' => $this->options,
            'order' => $this->order
        ];
    }
}

/**
 * Report Generator class
 */
class ReportGenerator
{
    use SingletonTrait;

    private array $templates = [];
    private array $data_sources = [];
    private array $formatters = [];
    private string $default_template = 'executive';

    /**
     * Initialize generator
     */
    protected function init(): void
    {
        $this->registerDefaultTemplates();
        $this->registerDefaultDataSources();
        $this->registerDefaultFormatters();
    }

    /**
     * Register default report templates
     */
    private function registerDefaultTemplates(): void
    {
        // Executive Summary Template
        $this->registerTemplate('executive', [
            'name' => __('Executive Summary', 'form-flow-pro'),
            'description' => __('High-level overview with KPIs and trend analysis', 'form-flow-pro'),
            'sections' => [
                ['type' => 'header', 'order' => 1],
                ['type' => 'kpi_grid', 'order' => 2],
                ['type' => 'trend_chart', 'order' => 3],
                ['type' => 'executive_summary', 'order' => 4],
                ['type' => 'top_forms', 'order' => 5],
                ['type' => 'conversion_funnel', 'order' => 6],
                ['type' => 'footer', 'order' => 99]
            ],
            'settings' => [
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
                'header_logo' => true,
                'footer_page_numbers' => true,
                'color_scheme' => 'professional'
            ]
        ]);

        // Detailed Analytics Template
        $this->registerTemplate('detailed', [
            'name' => __('Detailed Analytics', 'form-flow-pro'),
            'description' => __('Comprehensive report with all metrics and data tables', 'form-flow-pro'),
            'sections' => [
                ['type' => 'header', 'order' => 1],
                ['type' => 'table_of_contents', 'order' => 2],
                ['type' => 'kpi_grid', 'order' => 3],
                ['type' => 'submissions_over_time', 'order' => 4],
                ['type' => 'form_performance', 'order' => 5],
                ['type' => 'field_analytics', 'order' => 6],
                ['type' => 'geographic_distribution', 'order' => 7],
                ['type' => 'device_breakdown', 'order' => 8],
                ['type' => 'conversion_analysis', 'order' => 9],
                ['type' => 'data_table', 'order' => 10],
                ['type' => 'footer', 'order' => 99]
            ],
            'settings' => [
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
                'header_logo' => true,
                'footer_page_numbers' => true,
                'color_scheme' => 'professional'
            ]
        ]);

        // Form Performance Template
        $this->registerTemplate('form_performance', [
            'name' => __('Form Performance', 'form-flow-pro'),
            'description' => __('Focused analysis of individual form performance', 'form-flow-pro'),
            'sections' => [
                ['type' => 'header', 'order' => 1],
                ['type' => 'form_summary', 'order' => 2],
                ['type' => 'submission_timeline', 'order' => 3],
                ['type' => 'field_completion_rates', 'order' => 4],
                ['type' => 'abandonment_analysis', 'order' => 5],
                ['type' => 'ab_test_results', 'order' => 6],
                ['type' => 'recommendations', 'order' => 7],
                ['type' => 'footer', 'order' => 99]
            ],
            'settings' => [
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
                'header_logo' => true,
                'footer_page_numbers' => true,
                'color_scheme' => 'professional'
            ]
        ]);

        // Compliance Report Template
        $this->registerTemplate('compliance', [
            'name' => __('Compliance Report', 'form-flow-pro'),
            'description' => __('GDPR/CCPA compliance and data handling report', 'form-flow-pro'),
            'sections' => [
                ['type' => 'header', 'order' => 1],
                ['type' => 'compliance_summary', 'order' => 2],
                ['type' => 'data_collection_overview', 'order' => 3],
                ['type' => 'consent_tracking', 'order' => 4],
                ['type' => 'data_retention', 'order' => 5],
                ['type' => 'access_requests', 'order' => 6],
                ['type' => 'audit_log', 'order' => 7],
                ['type' => 'footer', 'order' => 99]
            ],
            'settings' => [
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'right' => 15, 'bottom' => 20, 'left' => 15],
                'header_logo' => true,
                'footer_page_numbers' => true,
                'color_scheme' => 'formal'
            ]
        ]);

        // Quick Snapshot Template
        $this->registerTemplate('snapshot', [
            'name' => __('Quick Snapshot', 'form-flow-pro'),
            'description' => __('One-page summary of key metrics', 'form-flow-pro'),
            'sections' => [
                ['type' => 'header_compact', 'order' => 1],
                ['type' => 'kpi_row', 'order' => 2],
                ['type' => 'mini_chart', 'order' => 3],
                ['type' => 'top_5_list', 'order' => 4]
            ],
            'settings' => [
                'page_size' => 'A4',
                'orientation' => 'landscape',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
                'header_logo' => true,
                'footer_page_numbers' => false,
                'color_scheme' => 'modern'
            ]
        ]);

        do_action('ffp_register_report_templates', $this);
    }

    /**
     * Register default data sources
     */
    private function registerDefaultDataSources(): void
    {
        // Submissions data source
        $this->registerDataSource('submissions', [
            'label' => __('Form Submissions', 'form-flow-pro'),
            'callback' => [$this, 'getSubmissionsData'],
            'metrics' => [
                'total_submissions' => [
                    'label' => __('Total Submissions', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'forms'
                ],
                'conversion_rate' => [
                    'label' => __('Conversion Rate', 'form-flow-pro'),
                    'format' => 'percent',
                    'icon' => 'chart-line'
                ],
                'avg_completion_time' => [
                    'label' => __('Avg. Completion Time', 'form-flow-pro'),
                    'format' => 'duration',
                    'icon' => 'clock'
                ],
                'abandonment_rate' => [
                    'label' => __('Abandonment Rate', 'form-flow-pro'),
                    'format' => 'percent',
                    'icon' => 'exit'
                ]
            ]
        ]);

        // Forms data source
        $this->registerDataSource('forms', [
            'label' => __('Forms', 'form-flow-pro'),
            'callback' => [$this, 'getFormsData'],
            'metrics' => [
                'total_forms' => [
                    'label' => __('Total Forms', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'forms'
                ],
                'active_forms' => [
                    'label' => __('Active Forms', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'yes'
                ]
            ]
        ]);

        // Signatures data source
        $this->registerDataSource('signatures', [
            'label' => __('Digital Signatures', 'form-flow-pro'),
            'callback' => [$this, 'getSignaturesData'],
            'metrics' => [
                'total_signatures' => [
                    'label' => __('Total Signatures', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'edit'
                ],
                'pending_signatures' => [
                    'label' => __('Pending', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'clock'
                ],
                'completed_signatures' => [
                    'label' => __('Completed', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'yes-alt'
                ],
                'signature_rate' => [
                    'label' => __('Completion Rate', 'form-flow-pro'),
                    'format' => 'percent',
                    'icon' => 'chart-bar'
                ]
            ]
        ]);

        // Revenue data source (for payment forms)
        $this->registerDataSource('revenue', [
            'label' => __('Revenue', 'form-flow-pro'),
            'callback' => [$this, 'getRevenueData'],
            'metrics' => [
                'total_revenue' => [
                    'label' => __('Total Revenue', 'form-flow-pro'),
                    'format' => 'currency',
                    'icon' => 'money-alt'
                ],
                'avg_order_value' => [
                    'label' => __('Avg. Order Value', 'form-flow-pro'),
                    'format' => 'currency',
                    'icon' => 'cart'
                ],
                'transactions' => [
                    'label' => __('Transactions', 'form-flow-pro'),
                    'format' => 'number',
                    'icon' => 'products'
                ]
            ]
        ]);

        do_action('ffp_register_report_data_sources', $this);
    }

    /**
     * Register default formatters
     */
    private function registerDefaultFormatters(): void
    {
        // Number formatter
        $this->registerFormatter('number', function ($value, $options = []) {
            $decimals = $options['decimals'] ?? 0;
            return number_format($value, $decimals);
        });

        // Currency formatter
        $this->registerFormatter('currency', function ($value, $options = []) {
            $symbol = $options['symbol'] ?? '$';
            $decimals = $options['decimals'] ?? 2;
            return $symbol . number_format($value, $decimals);
        });

        // Percent formatter
        $this->registerFormatter('percent', function ($value, $options = []) {
            $decimals = $options['decimals'] ?? 1;
            return number_format($value, $decimals) . '%';
        });

        // Duration formatter
        $this->registerFormatter('duration', function ($value, $options = []) {
            if ($value < 60) {
                return $value . 's';
            } elseif ($value < 3600) {
                return round($value / 60, 1) . 'm';
            } else {
                return round($value / 3600, 1) . 'h';
            }
        });

        // Date formatter
        $this->registerFormatter('date', function ($value, $options = []) {
            $format = $options['format'] ?? get_option('date_format');
            return date_i18n($format, strtotime($value));
        });

        // Datetime formatter
        $this->registerFormatter('datetime', function ($value, $options = []) {
            $format = $options['format'] ?? get_option('date_format') . ' ' . get_option('time_format');
            return date_i18n($format, strtotime($value));
        });
    }

    /**
     * Register a report template
     */
    public function registerTemplate(string $id, array $config): void
    {
        $this->templates[$id] = array_merge([
            'id' => $id,
            'name' => '',
            'description' => '',
            'sections' => [],
            'settings' => []
        ], $config);
    }

    /**
     * Get all templates
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Get template by ID
     */
    public function getTemplate(string $id): ?array
    {
        return $this->templates[$id] ?? null;
    }

    /**
     * Register a data source
     */
    public function registerDataSource(string $id, array $config): void
    {
        $this->data_sources[$id] = array_merge([
            'id' => $id,
            'label' => '',
            'callback' => null,
            'metrics' => []
        ], $config);
    }

    /**
     * Get all data sources
     */
    public function getDataSources(): array
    {
        return $this->data_sources;
    }

    /**
     * Register a formatter
     */
    public function registerFormatter(string $name, callable $callback): void
    {
        $this->formatters[$name] = $callback;
    }

    /**
     * Format a value
     */
    public function format($value, string $formatter, array $options = []): string
    {
        if (!isset($this->formatters[$formatter])) {
            return (string) $value;
        }
        return call_user_func($this->formatters[$formatter], $value, $options);
    }

    /**
     * Generate a report
     */
    public function generate(array $config): ReportData
    {
        $template_id = $config['template'] ?? $this->default_template;
        $template = $this->getTemplate($template_id);

        if (!$template) {
            $template = $this->getTemplate($this->default_template);
        }

        $period = $this->resolvePeriod($config);

        $report = new ReportData([
            'title' => $config['title'] ?? $template['name'],
            'subtitle' => $config['subtitle'] ?? sprintf(
                __('%s - %s', 'form-flow-pro'),
                date_i18n(get_option('date_format'), strtotime($period['start'])),
                date_i18n(get_option('date_format'), strtotime($period['end']))
            ),
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'metadata' => [
                'template' => $template_id,
                'generated_at' => current_time('c'),
                'generated_by' => get_current_user_id(),
                'config' => $config
            ]
        ]);

        // Collect data from sources
        $data = $this->collectData($config, $period);

        // Build KPIs
        $report->metrics = $this->buildKPIs($data, $config);

        // Build charts
        $report->charts = $this->buildCharts($data, $config);

        // Build tables
        $report->tables = $this->buildTables($data, $config);

        // Build sections
        $report->sections = $this->buildSections($template, $data, $config);

        return apply_filters('ffp_report_generated', $report, $config);
    }

    /**
     * Resolve period from config
     */
    private function resolvePeriod(array $config): array
    {
        if (!empty($config['period_start']) && !empty($config['period_end'])) {
            return [
                'start' => $config['period_start'],
                'end' => $config['period_end']
            ];
        }

        $preset = $config['period'] ?? 'last_30_days';

        switch ($preset) {
            case 'today':
                return [
                    'start' => date('Y-m-d 00:00:00'),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'yesterday':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('-1 day')),
                    'end' => date('Y-m-d 23:59:59', strtotime('-1 day'))
                ];
            case 'this_week':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('monday this week')),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_week':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('monday last week')),
                    'end' => date('Y-m-d 23:59:59', strtotime('sunday last week'))
                ];
            case 'this_month':
                return [
                    'start' => date('Y-m-01 00:00:00'),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_month':
                return [
                    'start' => date('Y-m-01 00:00:00', strtotime('first day of last month')),
                    'end' => date('Y-m-t 23:59:59', strtotime('last day of last month'))
                ];
            case 'this_quarter':
                $quarter = ceil(date('n') / 3);
                $start_month = ($quarter - 1) * 3 + 1;
                return [
                    'start' => date('Y-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00'),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_quarter':
                $quarter = ceil(date('n') / 3) - 1;
                if ($quarter < 1) {
                    $quarter = 4;
                    $year = date('Y') - 1;
                } else {
                    $year = date('Y');
                }
                $start_month = ($quarter - 1) * 3 + 1;
                $end_month = $quarter * 3;
                return [
                    'start' => date($year . '-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01 00:00:00'),
                    'end' => date($year . '-' . str_pad($end_month, 2, '0', STR_PAD_LEFT) . '-t 23:59:59', strtotime($year . '-' . $end_month . '-01'))
                ];
            case 'this_year':
                return [
                    'start' => date('Y-01-01 00:00:00'),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_year':
                $year = date('Y') - 1;
                return [
                    'start' => date($year . '-01-01 00:00:00'),
                    'end' => date($year . '-12-31 23:59:59')
                ];
            case 'last_7_days':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('-7 days')),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_90_days':
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('-90 days')),
                    'end' => date('Y-m-d 23:59:59')
                ];
            case 'last_30_days':
            default:
                return [
                    'start' => date('Y-m-d 00:00:00', strtotime('-30 days')),
                    'end' => date('Y-m-d 23:59:59')
                ];
        }
    }

    /**
     * Collect data from all sources
     */
    private function collectData(array $config, array $period): array
    {
        $data = [];
        $sources = $config['data_sources'] ?? array_keys($this->data_sources);

        foreach ($sources as $source_id) {
            if (!isset($this->data_sources[$source_id])) {
                continue;
            }

            $source = $this->data_sources[$source_id];
            if (is_callable($source['callback'])) {
                $data[$source_id] = call_user_func(
                    $source['callback'],
                    $period,
                    $config
                );
            }
        }

        return $data;
    }

    /**
     * Build KPIs from collected data
     */
    private function buildKPIs(array $data, array $config): array
    {
        $kpis = [];

        foreach ($data as $source_id => $source_data) {
            if (empty($source_data['metrics'])) {
                continue;
            }

            foreach ($source_data['metrics'] as $metric_id => $metric_data) {
                $source = $this->data_sources[$source_id] ?? null;
                $metric_config = $source['metrics'][$metric_id] ?? [];

                $kpi = new KPIMetric([
                    'id' => "{$source_id}_{$metric_id}",
                    'label' => $metric_config['label'] ?? $metric_id,
                    'value' => $metric_data['value'] ?? 0,
                    'previous_value' => $metric_data['previous'] ?? null,
                    'format' => $metric_config['format'] ?? 'number',
                    'icon' => $metric_config['icon'] ?? '',
                    'description' => $metric_data['description'] ?? ''
                ]);

                $kpi->calculateTrend();
                $kpis[] = $kpi->toArray();
            }
        }

        return $kpis;
    }

    /**
     * Build charts from collected data
     */
    private function buildCharts(array $data, array $config): array
    {
        $charts = [];

        // Submissions over time chart
        if (!empty($data['submissions']['timeline'])) {
            $charts[] = [
                'id' => 'submissions_timeline',
                'type' => 'line',
                'title' => __('Submissions Over Time', 'form-flow-pro'),
                'data' => $data['submissions']['timeline'],
                'options' => [
                    'xAxis' => ['type' => 'time'],
                    'yAxis' => ['label' => __('Submissions', 'form-flow-pro')],
                    'colors' => ['#2271b1']
                ]
            ];
        }

        // Conversion funnel
        if (!empty($data['submissions']['funnel'])) {
            $charts[] = [
                'id' => 'conversion_funnel',
                'type' => 'funnel',
                'title' => __('Conversion Funnel', 'form-flow-pro'),
                'data' => $data['submissions']['funnel'],
                'options' => [
                    'colors' => ['#2271b1', '#72aee6', '#9ec2e6', '#c5d9ed']
                ]
            ];
        }

        // Form performance comparison
        if (!empty($data['forms']['performance'])) {
            $charts[] = [
                'id' => 'form_performance',
                'type' => 'bar',
                'title' => __('Form Performance', 'form-flow-pro'),
                'data' => $data['forms']['performance'],
                'options' => [
                    'orientation' => 'horizontal',
                    'colors' => ['#2271b1']
                ]
            ];
        }

        // Device breakdown
        if (!empty($data['submissions']['devices'])) {
            $charts[] = [
                'id' => 'device_breakdown',
                'type' => 'pie',
                'title' => __('Device Breakdown', 'form-flow-pro'),
                'data' => $data['submissions']['devices'],
                'options' => [
                    'colors' => ['#2271b1', '#72aee6', '#c3c4c7']
                ]
            ];
        }

        // Geographic heatmap
        if (!empty($data['submissions']['geographic'])) {
            $charts[] = [
                'id' => 'geographic_heatmap',
                'type' => 'heatmap',
                'title' => __('Geographic Distribution', 'form-flow-pro'),
                'data' => $data['submissions']['geographic'],
                'options' => [
                    'colorScale' => ['#c5d9ed', '#2271b1']
                ]
            ];
        }

        return apply_filters('ffp_report_charts', $charts, $data, $config);
    }

    /**
     * Build data tables from collected data
     */
    private function buildTables(array $data, array $config): array
    {
        $tables = [];

        // Top forms table
        if (!empty($data['forms']['list'])) {
            $tables[] = [
                'id' => 'top_forms',
                'title' => __('Top Forms', 'form-flow-pro'),
                'columns' => [
                    ['key' => 'name', 'label' => __('Form Name', 'form-flow-pro')],
                    ['key' => 'submissions', 'label' => __('Submissions', 'form-flow-pro'), 'format' => 'number'],
                    ['key' => 'conversion_rate', 'label' => __('Conversion', 'form-flow-pro'), 'format' => 'percent'],
                    ['key' => 'avg_time', 'label' => __('Avg. Time', 'form-flow-pro'), 'format' => 'duration']
                ],
                'rows' => $data['forms']['list'],
                'options' => [
                    'sortable' => true,
                    'limit' => 10
                ]
            ];
        }

        // Recent submissions table
        if (!empty($data['submissions']['recent'])) {
            $tables[] = [
                'id' => 'recent_submissions',
                'title' => __('Recent Submissions', 'form-flow-pro'),
                'columns' => [
                    ['key' => 'date', 'label' => __('Date', 'form-flow-pro'), 'format' => 'datetime'],
                    ['key' => 'form', 'label' => __('Form', 'form-flow-pro')],
                    ['key' => 'email', 'label' => __('Email', 'form-flow-pro')],
                    ['key' => 'status', 'label' => __('Status', 'form-flow-pro')]
                ],
                'rows' => $data['submissions']['recent'],
                'options' => [
                    'limit' => 20
                ]
            ];
        }

        return apply_filters('ffp_report_tables', $tables, $data, $config);
    }

    /**
     * Build report sections
     */
    private function buildSections(array $template, array $data, array $config): array
    {
        $sections = [];

        foreach ($template['sections'] as $section_config) {
            $section = new ReportSection([
                'id' => $section_config['id'] ?? wp_generate_uuid4(),
                'type' => $section_config['type'],
                'order' => $section_config['order'] ?? 0,
                'options' => $section_config
            ]);

            // Generate section content based on type
            $section->content = $this->generateSectionContent($section, $data, $config);
            $section->data = $this->generateSectionData($section, $data, $config);

            $sections[] = $section->toArray();
        }

        // Sort by order
        usort($sections, function ($a, $b) {
            return $a['order'] - $b['order'];
        });

        return $sections;
    }

    /**
     * Generate section content
     */
    private function generateSectionContent(ReportSection $section, array $data, array $config): string
    {
        switch ($section->type) {
            case 'executive_summary':
                return $this->generateExecutiveSummary($data);

            case 'recommendations':
                return $this->generateRecommendations($data);

            case 'compliance_summary':
                return $this->generateComplianceSummary($data);

            default:
                return '';
        }
    }

    /**
     * Generate section data
     */
    private function generateSectionData(ReportSection $section, array $data, array $config): array
    {
        switch ($section->type) {
            case 'kpi_grid':
            case 'kpi_row':
                return $data['submissions']['metrics'] ?? [];

            case 'trend_chart':
            case 'submissions_over_time':
                return $data['submissions']['timeline'] ?? [];

            case 'conversion_funnel':
                return $data['submissions']['funnel'] ?? [];

            case 'top_forms':
            case 'form_performance':
                return $data['forms']['list'] ?? [];

            default:
                return [];
        }
    }

    /**
     * Generate executive summary text
     */
    private function generateExecutiveSummary(array $data): string
    {
        $submissions = $data['submissions']['metrics']['total_submissions']['value'] ?? 0;
        $previous = $data['submissions']['metrics']['total_submissions']['previous'] ?? 0;
        $change = $previous > 0 ? round((($submissions - $previous) / $previous) * 100, 1) : 0;

        $trend = $change > 0 ? __('increased', 'form-flow-pro') : ($change < 0 ? __('decreased', 'form-flow-pro') : __('remained stable', 'form-flow-pro'));

        $summary = sprintf(
            __('During this reporting period, form submissions %s by %s%% compared to the previous period. ', 'form-flow-pro'),
            $trend,
            abs($change)
        );

        $conversion = $data['submissions']['metrics']['conversion_rate']['value'] ?? 0;
        $summary .= sprintf(
            __('The overall conversion rate is %s%%. ', 'form-flow-pro'),
            number_format($conversion, 1)
        );

        if (isset($data['signatures'])) {
            $sig_rate = $data['signatures']['metrics']['signature_rate']['value'] ?? 0;
            $summary .= sprintf(
                __('Digital signature completion rate stands at %s%%.', 'form-flow-pro'),
                number_format($sig_rate, 1)
            );
        }

        return $summary;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(array $data): string
    {
        $recommendations = [];

        // Check conversion rate
        $conversion = $data['submissions']['metrics']['conversion_rate']['value'] ?? 0;
        if ($conversion < 30) {
            $recommendations[] = __('Consider simplifying your forms to improve conversion rates.', 'form-flow-pro');
        }

        // Check abandonment rate
        $abandonment = $data['submissions']['metrics']['abandonment_rate']['value'] ?? 0;
        if ($abandonment > 50) {
            $recommendations[] = __('High abandonment rate detected. Review form length and complexity.', 'form-flow-pro');
        }

        // Check completion time
        $avg_time = $data['submissions']['metrics']['avg_completion_time']['value'] ?? 0;
        if ($avg_time > 300) {
            $recommendations[] = __('Average completion time is high. Consider breaking long forms into steps.', 'form-flow-pro');
        }

        if (empty($recommendations)) {
            $recommendations[] = __('Your forms are performing well. Continue monitoring for optimization opportunities.', 'form-flow-pro');
        }

        return '<ul><li>' . implode('</li><li>', $recommendations) . '</li></ul>';
    }

    /**
     * Generate compliance summary
     */
    private function generateComplianceSummary(array $data): string
    {
        return __('This report provides an overview of data collection practices, consent management, and compliance status.', 'form-flow-pro');
    }

    // ==================== Data Source Callbacks ====================

    /**
     * Get submissions data
     */
    public function getSubmissionsData(array $period, array $config): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_submissions';
        $form_id = $config['form_id'] ?? null;

        // Current period submissions
        $where = ["created_at BETWEEN %s AND %s"];
        $params = [$period['start'], $period['end']];

        if ($form_id) {
            $where[] = "form_id = %d";
            $params[] = $form_id;
        }

        $where_clause = implode(' AND ', $where);

        $current_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
            ...$params
        ));

        // Previous period
        $period_length = strtotime($period['end']) - strtotime($period['start']);
        $previous_start = date('Y-m-d H:i:s', strtotime($period['start']) - $period_length);
        $previous_end = date('Y-m-d H:i:s', strtotime($period['start']) - 1);

        $previous_params = [$previous_start, $previous_end];
        if ($form_id) {
            $previous_params[] = $form_id;
        }

        $previous_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE " . str_replace($period['start'], $previous_start, str_replace($period['end'], $previous_end, $where_clause)),
            ...$previous_params
        ));

        // Timeline data
        $timeline = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
             FROM {$table}
             WHERE {$where_clause}
             GROUP BY DATE(created_at)
             ORDER BY date",
            ...$params
        ), ARRAY_A);

        // Device breakdown
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT
                CASE
                    WHEN user_agent LIKE '%Mobile%' THEN 'Mobile'
                    WHEN user_agent LIKE '%Tablet%' THEN 'Tablet'
                    ELSE 'Desktop'
                END as device,
                COUNT(*) as count
             FROM {$table}
             WHERE {$where_clause}
             GROUP BY device",
            ...$params
        ), ARRAY_A);

        // Recent submissions
        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, f.title as form_name
             FROM {$table} s
             LEFT JOIN {$wpdb->prefix}ffp_forms f ON s.form_id = f.id
             WHERE s.created_at BETWEEN %s AND %s
             ORDER BY s.created_at DESC
             LIMIT 20",
            $period['start'],
            $period['end']
        ), ARRAY_A);

        // Calculate metrics
        $views = $this->getFormViews($period, $form_id);
        $conversion_rate = $views > 0 ? ($current_count / $views) * 100 : 0;
        $previous_views = $this->getFormViews([
            'start' => $previous_start,
            'end' => $previous_end
        ], $form_id);
        $previous_conversion = $previous_views > 0 ? ($previous_count / $previous_views) * 100 : 0;

        // Abandonment rate (views - submissions)
        $abandonment_rate = $views > 0 ? (($views - $current_count) / $views) * 100 : 0;

        // Average completion time
        $avg_time = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, created_at))
             FROM {$table}
             WHERE {$where_clause} AND started_at IS NOT NULL",
            ...$params
        )) ?: 0;

        return [
            'metrics' => [
                'total_submissions' => [
                    'value' => $current_count,
                    'previous' => $previous_count
                ],
                'conversion_rate' => [
                    'value' => round($conversion_rate, 2),
                    'previous' => round($previous_conversion, 2)
                ],
                'avg_completion_time' => [
                    'value' => $avg_time,
                    'previous' => null
                ],
                'abandonment_rate' => [
                    'value' => round($abandonment_rate, 2),
                    'previous' => null
                ]
            ],
            'timeline' => array_map(function ($row) {
                return [
                    'date' => $row['date'],
                    'value' => (int) $row['count']
                ];
            }, $timeline),
            'devices' => array_map(function ($row) {
                return [
                    'label' => $row['device'],
                    'value' => (int) $row['count']
                ];
            }, $devices ?: []),
            'recent' => array_map(function ($row) {
                return [
                    'date' => $row['created_at'],
                    'form' => $row['form_name'] ?? __('Unknown', 'form-flow-pro'),
                    'email' => $row['email'] ?? '',
                    'status' => $row['status'] ?? 'completed'
                ];
            }, $recent ?: []),
            'funnel' => [
                ['label' => __('Views', 'form-flow-pro'), 'value' => $views],
                ['label' => __('Started', 'form-flow-pro'), 'value' => round($views * 0.7)],
                ['label' => __('Completed', 'form-flow-pro'), 'value' => $current_count]
            ]
        ];
    }

    /**
     * Get form views
     */
    private function getFormViews(array $period, ?int $form_id = null): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_form_views';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return 0;
        }

        $where = ["viewed_at BETWEEN %s AND %s"];
        $params = [$period['start'], $period['end']];

        if ($form_id) {
            $where[] = "form_id = %d";
            $params[] = $form_id;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where),
            ...$params
        ));
    }

    /**
     * Get forms data
     */
    public function getFormsData(array $period, array $config): array
    {
        global $wpdb;

        $forms_table = $wpdb->prefix . 'ffp_forms';
        $submissions_table = $wpdb->prefix . 'ffp_submissions';

        // Total and active forms
        $total_forms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$forms_table}");
        $active_forms = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$forms_table} WHERE status = 'active'");

        // Form performance
        $performance = $wpdb->get_results($wpdb->prepare(
            "SELECT f.id, f.title as name,
                    COUNT(s.id) as submissions,
                    AVG(TIMESTAMPDIFF(SECOND, s.started_at, s.created_at)) as avg_time
             FROM {$forms_table} f
             LEFT JOIN {$submissions_table} s ON f.id = s.form_id
                AND s.created_at BETWEEN %s AND %s
             GROUP BY f.id
             ORDER BY submissions DESC
             LIMIT 10",
            $period['start'],
            $period['end']
        ), ARRAY_A);

        return [
            'metrics' => [
                'total_forms' => ['value' => $total_forms],
                'active_forms' => ['value' => $active_forms]
            ],
            'list' => array_map(function ($row) use ($period) {
                $views = $this->getFormViews($period, (int) $row['id']);
                $conversion = $views > 0 ? ($row['submissions'] / $views) * 100 : 0;
                return [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'submissions' => (int) $row['submissions'],
                    'conversion_rate' => round($conversion, 2),
                    'avg_time' => (int) ($row['avg_time'] ?? 0)
                ];
            }, $performance ?: []),
            'performance' => array_map(function ($row) {
                return [
                    'label' => $row['name'],
                    'value' => (int) $row['submissions']
                ];
            }, $performance ?: [])
        ];
    }

    /**
     * Get signatures data
     */
    public function getSignaturesData(array $period, array $config): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_signatures';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [
                'metrics' => [
                    'total_signatures' => ['value' => 0],
                    'pending_signatures' => ['value' => 0],
                    'completed_signatures' => ['value' => 0],
                    'signature_rate' => ['value' => 0]
                ]
            ];
        }

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at BETWEEN %s AND %s",
            $period['start'],
            $period['end']
        ));

        $completed = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND created_at BETWEEN %s AND %s",
            $period['start'],
            $period['end']
        ));

        $pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND created_at BETWEEN %s AND %s",
            $period['start'],
            $period['end']
        ));

        $rate = $total > 0 ? ($completed / $total) * 100 : 0;

        return [
            'metrics' => [
                'total_signatures' => ['value' => $total],
                'pending_signatures' => ['value' => $pending],
                'completed_signatures' => ['value' => $completed],
                'signature_rate' => ['value' => round($rate, 2)]
            ]
        ];
    }

    /**
     * Get revenue data
     */
    public function getRevenueData(array $period, array $config): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ffp_payments';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [
                'metrics' => [
                    'total_revenue' => ['value' => 0],
                    'avg_order_value' => ['value' => 0],
                    'transactions' => ['value' => 0]
                ]
            ];
        }

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT SUM(amount) as total, AVG(amount) as avg_value, COUNT(*) as transactions
             FROM {$table}
             WHERE status = 'completed' AND created_at BETWEEN %s AND %s",
            $period['start'],
            $period['end']
        ));

        return [
            'metrics' => [
                'total_revenue' => ['value' => (float) ($result->total ?? 0)],
                'avg_order_value' => ['value' => (float) ($result->avg_value ?? 0)],
                'transactions' => ['value' => (int) ($result->transactions ?? 0)]
            ]
        ];
    }

    // ==================== Export Methods ====================

    /**
     * Export report to PDF
     */
    public function exportPDF(ReportData $report, array $options = []): string
    {
        $html = $this->renderReportHTML($report, $options);

        // Use mPDF or similar library
        if (class_exists('\\Mpdf\\Mpdf')) {
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => $options['page_size'] ?? 'A4',
                'orientation' => $options['orientation'] ?? 'P',
                'margin_top' => $options['margins']['top'] ?? 20,
                'margin_right' => $options['margins']['right'] ?? 15,
                'margin_bottom' => $options['margins']['bottom'] ?? 20,
                'margin_left' => $options['margins']['left'] ?? 15
            ]);

            $mpdf->SetTitle($report->title);
            $mpdf->SetAuthor(get_bloginfo('name'));
            $mpdf->SetCreator('FormFlow Pro');

            $mpdf->WriteHTML($html);

            $filename = sanitize_file_name($report->title . '-' . date('Y-m-d') . '.pdf');
            $upload_dir = wp_upload_dir();
            $filepath = $upload_dir['basedir'] . '/ffp-reports/' . $filename;

            wp_mkdir_p(dirname($filepath));
            $mpdf->Output($filepath, 'F');

            return $filepath;
        }

        // Fallback: Use TCPDF or other available library
        return $this->exportPDFFallback($report, $html, $options);
    }

    /**
     * Fallback PDF export
     */
    private function exportPDFFallback(ReportData $report, string $html, array $options): string
    {
        $filename = sanitize_file_name($report->title . '-' . date('Y-m-d') . '.html');
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/ffp-reports/' . $filename;

        wp_mkdir_p(dirname($filepath));
        file_put_contents($filepath, $html);

        return $filepath;
    }

    /**
     * Render report as HTML
     */
    public function renderReportHTML(ReportData $report, array $options = []): string
    {
        $color_scheme = $this->getColorScheme($options['color_scheme'] ?? 'professional');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html($report->title); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                    color: <?php echo $color_scheme['text']; ?>;
                    background: #fff;
                }
                .report-header {
                    padding: 30px 0;
                    border-bottom: 3px solid <?php echo $color_scheme['primary']; ?>;
                    margin-bottom: 30px;
                }
                .report-title {
                    font-size: 28px;
                    font-weight: 600;
                    color: <?php echo $color_scheme['primary']; ?>;
                    margin-bottom: 8px;
                }
                .report-subtitle {
                    font-size: 14px;
                    color: <?php echo $color_scheme['secondary']; ?>;
                }
                .kpi-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .kpi-card {
                    background: <?php echo $color_scheme['card_bg']; ?>;
                    border-radius: 8px;
                    padding: 20px;
                    text-align: center;
                }
                .kpi-value {
                    font-size: 32px;
                    font-weight: 700;
                    color: <?php echo $color_scheme['primary']; ?>;
                }
                .kpi-label {
                    font-size: 12px;
                    color: <?php echo $color_scheme['secondary']; ?>;
                    margin-top: 4px;
                }
                .kpi-trend {
                    font-size: 11px;
                    margin-top: 8px;
                }
                .kpi-trend.up { color: #28a745; }
                .kpi-trend.down { color: #dc3545; }
                .kpi-trend.neutral { color: #6c757d; }
                .section {
                    margin-bottom: 30px;
                    page-break-inside: avoid;
                }
                .section-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: <?php echo $color_scheme['primary']; ?>;
                    border-bottom: 2px solid <?php echo $color_scheme['border']; ?>;
                    padding-bottom: 8px;
                    margin-bottom: 16px;
                }
                .chart-container {
                    background: <?php echo $color_scheme['card_bg']; ?>;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .chart-title {
                    font-size: 14px;
                    font-weight: 600;
                    margin-bottom: 16px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th, td {
                    padding: 12px;
                    text-align: left;
                    border-bottom: 1px solid <?php echo $color_scheme['border']; ?>;
                }
                th {
                    background: <?php echo $color_scheme['card_bg']; ?>;
                    font-weight: 600;
                    color: <?php echo $color_scheme['primary']; ?>;
                }
                tr:hover td {
                    background: <?php echo $color_scheme['card_bg']; ?>;
                }
                .summary-text {
                    background: <?php echo $color_scheme['card_bg']; ?>;
                    border-left: 4px solid <?php echo $color_scheme['primary']; ?>;
                    padding: 16px 20px;
                    margin-bottom: 20px;
                }
                .report-footer {
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid <?php echo $color_scheme['border']; ?>;
                    font-size: 10px;
                    color: <?php echo $color_scheme['secondary']; ?>;
                    text-align: center;
                }
                @media print {
                    .kpi-grid {
                        grid-template-columns: repeat(4, 1fr);
                    }
                }
            </style>
        </head>
        <body>
            <div class="report-header">
                <?php if (!empty($options['logo_url'])): ?>
                    <img src="<?php echo esc_url($options['logo_url']); ?>" alt="" style="max-height: 50px; margin-bottom: 16px;">
                <?php endif; ?>
                <h1 class="report-title"><?php echo esc_html($report->title); ?></h1>
                <p class="report-subtitle"><?php echo esc_html($report->subtitle); ?></p>
            </div>

            <?php if (!empty($report->metrics)): ?>
            <div class="kpi-grid">
                <?php foreach (array_slice($report->metrics, 0, 4) as $metric): ?>
                <div class="kpi-card">
                    <div class="kpi-value"><?php echo esc_html($metric['formatted_value']); ?></div>
                    <div class="kpi-label"><?php echo esc_html($metric['label']); ?></div>
                    <?php if ($metric['trend'] !== 'neutral'): ?>
                    <div class="kpi-trend <?php echo esc_attr($metric['trend']); ?>">
                        <?php echo $metric['trend'] === 'up' ? '&#9650;' : '&#9660;'; ?>
                        <?php echo esc_html(abs($metric['change_percent'])); ?>%
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($report->sections as $section): ?>
            <div class="section">
                <?php if (!empty($section['title'])): ?>
                <h2 class="section-title"><?php echo esc_html($section['title']); ?></h2>
                <?php endif; ?>

                <?php if (!empty($section['content'])): ?>
                <div class="summary-text">
                    <?php echo wp_kses_post($section['content']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php foreach ($report->charts as $chart): ?>
            <div class="chart-container">
                <h3 class="chart-title"><?php echo esc_html($chart['title']); ?></h3>
                <div id="chart-<?php echo esc_attr($chart['id']); ?>" class="chart-placeholder">
                    <?php echo $this->renderChartPlaceholder($chart); ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($report->tables as $table): ?>
            <div class="section">
                <h2 class="section-title"><?php echo esc_html($table['title']); ?></h2>
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($table['columns'] as $col): ?>
                            <th><?php echo esc_html($col['label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($table['rows'], 0, $table['options']['limit'] ?? 10) as $row): ?>
                        <tr>
                            <?php foreach ($table['columns'] as $col): ?>
                            <td>
                                <?php
                                $value = $row[$col['key']] ?? '';
                                if (!empty($col['format'])) {
                                    $value = $this->format($value, $col['format']);
                                }
                                echo esc_html($value);
                                ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>

            <div class="report-footer">
                <?php echo sprintf(
                    __('Generated by %s on %s', 'form-flow-pro'),
                    'FormFlow Pro',
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'))
                ); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render chart placeholder for PDF
     */
    private function renderChartPlaceholder(array $chart): string
    {
        $html = '<table style="width:100%">';

        switch ($chart['type']) {
            case 'bar':
            case 'line':
                foreach ($chart['data'] as $item) {
                    $max = max(array_column($chart['data'], 'value'));
                    $width = $max > 0 ? ($item['value'] / $max) * 100 : 0;
                    $html .= sprintf(
                        '<tr><td style="width:30%%">%s</td><td><div style="background:%s;width:%s%%;height:20px;border-radius:4px;"></div></td><td style="width:15%%">%s</td></tr>',
                        esc_html($item['label'] ?? $item['date'] ?? ''),
                        $chart['options']['colors'][0] ?? '#2271b1',
                        $width,
                        number_format($item['value'])
                    );
                }
                break;

            case 'pie':
                $total = array_sum(array_column($chart['data'], 'value'));
                foreach ($chart['data'] as $i => $item) {
                    $percent = $total > 0 ? ($item['value'] / $total) * 100 : 0;
                    $color = $chart['options']['colors'][$i % count($chart['options']['colors'])] ?? '#2271b1';
                    $html .= sprintf(
                        '<tr><td><span style="display:inline-block;width:12px;height:12px;background:%s;border-radius:2px;margin-right:8px;"></span>%s</td><td>%s (%.1f%%)</td></tr>',
                        $color,
                        esc_html($item['label']),
                        number_format($item['value']),
                        $percent
                    );
                }
                break;

            case 'funnel':
                $max = !empty($chart['data']) ? $chart['data'][0]['value'] : 0;
                foreach ($chart['data'] as $i => $item) {
                    $width = $max > 0 ? ($item['value'] / $max) * 100 : 0;
                    $color = $chart['options']['colors'][$i % count($chart['options']['colors'])] ?? '#2271b1';
                    $html .= sprintf(
                        '<tr><td style="width:25%%">%s</td><td><div style="background:%s;width:%s%%;height:30px;margin:4px auto;border-radius:4px;"></div></td><td style="width:20%%">%s</td></tr>',
                        esc_html($item['label']),
                        $color,
                        $width,
                        number_format($item['value'])
                    );
                }
                break;
        }

        $html .= '</table>';
        return $html;
    }

    /**
     * Get color scheme
     */
    private function getColorScheme(string $name): array
    {
        $schemes = [
            'professional' => [
                'primary' => '#1d2327',
                'secondary' => '#646970',
                'accent' => '#2271b1',
                'text' => '#1d2327',
                'border' => '#dcdcde',
                'card_bg' => '#f6f7f7'
            ],
            'modern' => [
                'primary' => '#2271b1',
                'secondary' => '#72aee6',
                'accent' => '#135e96',
                'text' => '#1d2327',
                'border' => '#c5d9ed',
                'card_bg' => '#f0f6fc'
            ],
            'formal' => [
                'primary' => '#1e3a5f',
                'secondary' => '#4a6785',
                'accent' => '#0066cc',
                'text' => '#1d2327',
                'border' => '#ccd0d4',
                'card_bg' => '#f8f9fa'
            ]
        ];

        return $schemes[$name] ?? $schemes['professional'];
    }

    /**
     * Export report to Excel
     */
    public function exportExcel(ReportData $report, array $options = []): string
    {
        $filename = sanitize_file_name($report->title . '-' . date('Y-m-d') . '.xlsx');
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/ffp-reports/' . $filename;

        wp_mkdir_p(dirname($filepath));

        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Title
            $sheet->setCellValue('A1', $report->title);
            $sheet->setCellValue('A2', $report->subtitle);
            $sheet->mergeCells('A1:F1');
            $sheet->mergeCells('A2:F2');

            $row = 4;

            // KPIs
            $sheet->setCellValue('A' . $row, __('Key Metrics', 'form-flow-pro'));
            $row++;

            foreach ($report->metrics as $metric) {
                $sheet->setCellValue('A' . $row, $metric['label']);
                $sheet->setCellValue('B' . $row, $metric['value']);
                $sheet->setCellValue('C' . $row, $metric['change_percent'] . '%');
                $row++;
            }

            $row += 2;

            // Tables
            foreach ($report->tables as $table) {
                $sheet->setCellValue('A' . $row, $table['title']);
                $row++;

                $col = 'A';
                foreach ($table['columns'] as $column) {
                    $sheet->setCellValue($col . $row, $column['label']);
                    $col++;
                }
                $row++;

                foreach ($table['rows'] as $tableRow) {
                    $col = 'A';
                    foreach ($table['columns'] as $column) {
                        $sheet->setCellValue($col . $row, $tableRow[$column['key']] ?? '');
                        $col++;
                    }
                    $row++;
                }

                $row += 2;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($filepath);
        } else {
            // Fallback to CSV
            return $this->exportCSV($report, $options);
        }

        return $filepath;
    }

    /**
     * Export report to CSV
     */
    public function exportCSV(ReportData $report, array $options = []): string
    {
        $filename = sanitize_file_name($report->title . '-' . date('Y-m-d') . '.csv');
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/ffp-reports/' . $filename;

        wp_mkdir_p(dirname($filepath));

        $fp = fopen($filepath, 'w');

        // Header
        fputcsv($fp, [$report->title]);
        fputcsv($fp, [$report->subtitle]);
        fputcsv($fp, []);

        // KPIs
        fputcsv($fp, [__('Key Metrics', 'form-flow-pro')]);
        fputcsv($fp, [__('Metric', 'form-flow-pro'), __('Value', 'form-flow-pro'), __('Change', 'form-flow-pro')]);

        foreach ($report->metrics as $metric) {
            fputcsv($fp, [$metric['label'], $metric['value'], $metric['change_percent'] . '%']);
        }

        fputcsv($fp, []);

        // Tables
        foreach ($report->tables as $table) {
            fputcsv($fp, [$table['title']]);
            fputcsv($fp, array_column($table['columns'], 'label'));

            foreach ($table['rows'] as $row) {
                $values = [];
                foreach ($table['columns'] as $column) {
                    $values[] = $row[$column['key']] ?? '';
                }
                fputcsv($fp, $values);
            }

            fputcsv($fp, []);
        }

        fclose($fp);

        return $filepath;
    }

    /**
     * Export report to JSON
     */
    public function exportJSON(ReportData $report, array $options = []): string
    {
        $filename = sanitize_file_name($report->title . '-' . date('Y-m-d') . '.json');
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/ffp-reports/' . $filename;

        wp_mkdir_p(dirname($filepath));

        file_put_contents($filepath, wp_json_encode($report->toArray(), JSON_PRETTY_PRINT));

        return $filepath;
    }
}
