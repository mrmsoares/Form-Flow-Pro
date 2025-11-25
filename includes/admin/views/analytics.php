<?php

/**
 * Analytics Dashboard Page - V2.2.0 Enterprise Edition
 *
 * Advanced analytics with real-time stats, period comparison,
 * performance metrics, and export capabilities.
 *
 * @package FormFlowPro
 * @since 2.0.0
 * @updated 2.2.0 Added advanced analytics features
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get date range parameters
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$form_filter = isset($_GET['form_id']) ? sanitize_text_field($_GET['form_id']) : '';
$view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'overview';

// Get all forms for filter
$forms = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

// Build WHERE clause for filtering
$where_clauses = ["DATE(created_at) BETWEEN %s AND %s"];
$where_values = [$date_from, $date_to];

if ($form_filter) {
    $where_clauses[] = "form_id = %s";
    $where_values[] = $form_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Get overview stats
$total_submissions = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE $where_sql",
    $where_values
));

$completed_submissions = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'completed' AND $where_sql",
    $where_values
));

$pending_submissions = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status IN ('pending', 'pending_signature') AND $where_sql",
    $where_values
));

$failed_submissions = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'failed' AND $where_sql",
    $where_values
));

$conversion_rate = $total_submissions > 0 ? round(($completed_submissions / $total_submissions) * 100, 2) : 0;

// Get average processing time
$avg_processing_time = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(processing_time_ms) FROM {$wpdb->prefix}formflow_submissions WHERE processing_time_ms IS NOT NULL AND $where_sql",
    $where_values
));

// Get submissions by form
$submissions_by_form = $wpdb->get_results($wpdb->prepare(
    "SELECT f.name as form_name, f.id as form_id, COUNT(s.id) as count
    FROM {$wpdb->prefix}formflow_forms f
    LEFT JOIN {$wpdb->prefix}formflow_submissions s ON f.id = s.form_id AND $where_sql
    GROUP BY f.id, f.name
    ORDER BY count DESC
    LIMIT 10",
    $where_values
));

// Get submissions by status
$submissions_by_status = $wpdb->get_results($wpdb->prepare(
    "SELECT status, COUNT(*) as count
    FROM {$wpdb->prefix}formflow_submissions
    WHERE $where_sql
    GROUP BY status
    ORDER BY count DESC",
    $where_values
));

// Get daily submissions for trend chart
$daily_submissions = $wpdb->get_results($wpdb->prepare(
    "SELECT DATE(created_at) as date, COUNT(*) as count
    FROM {$wpdb->prefix}formflow_submissions
    WHERE $where_sql
    GROUP BY DATE(created_at)
    ORDER BY date ASC",
    $where_values
));

// Fill in missing dates with zeros
$date_range = [];
$counts = [];
$current_date = strtotime($date_from);
$end_date = strtotime($date_to);

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $date_range[] = date('M d', $current_date);

    // Find count for this date
    $count = 0;
    foreach ($daily_submissions as $submission) {
        if ($submission->date === $date_str) {
            $count = (int) $submission->count;
            break;
        }
    }
    $counts[] = $count;

    $current_date = strtotime('+1 day', $current_date);
}

// Get hourly distribution
$hourly_distribution = $wpdb->get_results($wpdb->prepare(
    "SELECT HOUR(created_at) as hour, COUNT(*) as count
    FROM {$wpdb->prefix}formflow_submissions
    WHERE $where_sql
    GROUP BY HOUR(created_at)
    ORDER BY hour ASC",
    $where_values
));

$hours = range(0, 23);
$hourly_counts = array_fill(0, 24, 0);
foreach ($hourly_distribution as $hour_data) {
    $hourly_counts[(int)$hour_data->hour] = (int)$hour_data->count;
}

// Calculate period comparison (vs previous period)
$days_in_period = max(1, (strtotime($date_to) - strtotime($date_from)) / 86400);
$prev_date_from = date('Y-m-d', strtotime($date_from . ' - ' . $days_in_period . ' days'));
$prev_date_to = date('Y-m-d', strtotime($date_from . ' - 1 day'));

$prev_where_values = [$prev_date_from, $prev_date_to];
if ($form_filter) {
    $prev_where_values[] = $form_filter;
}

$prev_total = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE $where_sql",
    $prev_where_values
));

$prev_completed = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'completed' AND $where_sql",
    $prev_where_values
));

// Calculate changes
$total_change = $prev_total > 0 ? round((($total_submissions - $prev_total) / $prev_total) * 100, 1) : ($total_submissions > 0 ? 100 : 0);
$completed_change = $prev_completed > 0 ? round((($completed_submissions - $prev_completed) / $prev_completed) * 100, 1) : ($completed_submissions > 0 ? 100 : 0);

?>

<div class="wrap formflow-admin formflow-analytics">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Analytics Dashboard', 'formflow-pro'); ?>
        <span class="analytics-version-badge">V2.2.0</span>
    </h1>

    <div class="analytics-actions">
        <button type="button" class="button" id="refresh-realtime">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Refresh', 'formflow-pro'); ?>
        </button>

        <div class="dropdown">
            <button type="button" class="button button-primary dropdown-toggle" id="export-dropdown">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export', 'formflow-pro'); ?>
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <div class="dropdown-menu" id="export-menu">
                <a href="#" class="dropdown-item" data-export="csv">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e('Export CSV', 'formflow-pro'); ?>
                </a>
                <a href="#" class="dropdown-item" data-export="pdf">
                    <span class="dashicons dashicons-pdf"></span>
                    <?php esc_html_e('Export PDF', 'formflow-pro'); ?>
                </a>
            </div>
        </div>
    </div>

    <hr class="wp-header-end">

    <!-- Real-time Stats Bar -->
    <div class="realtime-stats-bar card" id="realtime-stats">
        <div class="realtime-indicator">
            <span class="pulse-dot"></span>
            <span class="realtime-label"><?php esc_html_e('Real-time', 'formflow-pro'); ?></span>
        </div>

        <div class="realtime-stats-grid">
            <div class="realtime-stat">
                <span class="stat-value" id="rt-submissions-today">-</span>
                <span class="stat-label"><?php esc_html_e('Today', 'formflow-pro'); ?></span>
            </div>
            <div class="realtime-stat">
                <span class="stat-value" id="rt-completed-today">-</span>
                <span class="stat-label"><?php esc_html_e('Completed', 'formflow-pro'); ?></span>
            </div>
            <div class="realtime-stat">
                <span class="stat-value" id="rt-pending-signatures">-</span>
                <span class="stat-label"><?php esc_html_e('Pending Signatures', 'formflow-pro'); ?></span>
            </div>
            <div class="realtime-stat">
                <span class="stat-value" id="rt-queue-pending">-</span>
                <span class="stat-label"><?php esc_html_e('Queue', 'formflow-pro'); ?></span>
            </div>
            <div class="realtime-stat last-update">
                <span class="stat-value" id="rt-last-submission">-</span>
                <span class="stat-label"><?php esc_html_e('Last Submission', 'formflow-pro'); ?></span>
            </div>
        </div>
    </div>

    <!-- View Tabs -->
    <nav class="analytics-tabs">
        <a href="?page=formflow-analytics&view=overview&date_from=<?php echo esc_attr($date_from); ?>&date_to=<?php echo esc_attr($date_to); ?>&form_id=<?php echo esc_attr($form_filter); ?>"
           class="tab <?php echo $view_mode === 'overview' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-chart-area"></span>
            <?php esc_html_e('Overview', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-analytics&view=performance&date_from=<?php echo esc_attr($date_from); ?>&date_to=<?php echo esc_attr($date_to); ?>&form_id=<?php echo esc_attr($form_filter); ?>"
           class="tab <?php echo $view_mode === 'performance' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-performance"></span>
            <?php esc_html_e('Performance', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-analytics&view=compare&date_from=<?php echo esc_attr($date_from); ?>&date_to=<?php echo esc_attr($date_to); ?>&form_id=<?php echo esc_attr($form_filter); ?>"
           class="tab <?php echo $view_mode === 'compare' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e('Compare', 'formflow-pro'); ?>
        </a>
    </nav>

    <!-- Filters -->
    <div class="analytics-filters card">
        <form method="get" action="">
            <input type="hidden" name="page" value="formflow-analytics">
            <input type="hidden" name="view" value="<?php echo esc_attr($view_mode); ?>">

            <div class="filter-row">
                <div class="filter-group">
                    <label for="form-filter"><?php esc_html_e('Form:', 'formflow-pro'); ?></label>
                    <select name="form_id" id="form-filter">
                        <option value=""><?php esc_html_e('All Forms', 'formflow-pro'); ?></option>
                        <?php foreach ($forms as $form) : ?>
                            <option value="<?php echo esc_attr($form->id); ?>" <?php selected($form_filter, $form->id); ?>>
                                <?php echo esc_html($form->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date-from"><?php esc_html_e('From:', 'formflow-pro'); ?></label>
                    <input type="date" name="date_from" id="date-from" value="<?php echo esc_attr($date_from); ?>">
                </div>

                <div class="filter-group">
                    <label for="date-to"><?php esc_html_e('To:', 'formflow-pro'); ?></label>
                    <input type="date" name="date_to" id="date-to" value="<?php echo esc_attr($date_to); ?>">
                </div>

                <div class="filter-group preset-buttons">
                    <button type="button" class="button button-small preset-btn" data-days="7">7D</button>
                    <button type="button" class="button button-small preset-btn" data-days="30">30D</button>
                    <button type="button" class="button button-small preset-btn" data-days="90">90D</button>
                    <button type="button" class="button button-small preset-btn" data-days="365">1Y</button>
                </div>

                <div class="filter-group filter-actions">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Apply', 'formflow-pro'); ?>
                    </button>
                    <a href="?page=formflow-analytics&view=<?php echo esc_attr($view_mode); ?>" class="button">
                        <?php esc_html_e('Reset', 'formflow-pro'); ?>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($view_mode === 'overview') : ?>
    <!-- Overview Stats Grid -->
    <div class="analytics-stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <span class="dashicons dashicons-forms"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Total Submissions', 'formflow-pro'); ?></div>
                <div class="stat-change <?php echo $total_change >= 0 ? 'positive' : 'negative'; ?>">
                    <span class="dashicons dashicons-arrow-<?php echo $total_change >= 0 ? 'up' : 'down'; ?>-alt"></span>
                    <?php echo abs($total_change); ?>% <?php esc_html_e('vs previous', 'formflow-pro'); ?>
                </div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($completed_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Completed', 'formflow-pro'); ?></div>
                <div class="stat-change <?php echo $completed_change >= 0 ? 'positive' : 'negative'; ?>">
                    <span class="dashicons dashicons-arrow-<?php echo $completed_change >= 0 ? 'up' : 'down'; ?>-alt"></span>
                    <?php echo abs($completed_change); ?>% <?php esc_html_e('vs previous', 'formflow-pro'); ?>
                </div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($pending_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Pending', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <span class="dashicons dashicons-chart-area"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $conversion_rate; ?>%</div>
                <div class="stat-label"><?php esc_html_e('Conversion Rate', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-secondary">
            <div class="stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $avg_processing_time ? number_format($avg_processing_time, 0) . 'ms' : '-'; ?></div>
                <div class="stat-label"><?php esc_html_e('Avg Processing Time', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-danger">
            <div class="stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($failed_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Failed', 'formflow-pro'); ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="analytics-charts-grid">
        <!-- Submissions Trend -->
        <div class="chart-card chart-large">
            <div class="chart-header">
                <h3><?php esc_html_e('Submissions Trend', 'formflow-pro'); ?></h3>
                <p class="chart-description"><?php esc_html_e('Daily submission volume over selected period', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="submissions-trend-chart"></canvas>
            </div>
        </div>

        <!-- Submissions by Status -->
        <div class="chart-card chart-small">
            <div class="chart-header">
                <h3><?php esc_html_e('By Status', 'formflow-pro'); ?></h3>
                <p class="chart-description"><?php esc_html_e('Distribution by status', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="status-distribution-chart"></canvas>
            </div>
        </div>

        <!-- Hourly Distribution -->
        <div class="chart-card chart-medium">
            <div class="chart-header">
                <h3><?php esc_html_e('Hourly Distribution', 'formflow-pro'); ?></h3>
                <p class="chart-description"><?php esc_html_e('Peak hours for submissions', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="hourly-distribution-chart"></canvas>
            </div>
        </div>

        <!-- Top Forms -->
        <div class="chart-card chart-medium">
            <div class="chart-header">
                <h3><?php esc_html_e('Top Forms', 'formflow-pro'); ?></h3>
                <p class="chart-description"><?php esc_html_e('Most active forms', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="top-forms-chart"></canvas>
            </div>
        </div>
    </div>

    <?php elseif ($view_mode === 'performance') : ?>
    <!-- Performance View -->
    <div class="performance-metrics">
        <div class="metrics-row">
            <div class="metric-card">
                <div class="metric-header">
                    <h3><?php esc_html_e('Processing Performance', 'formflow-pro'); ?></h3>
                </div>
                <div class="metric-body">
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Average Processing Time', 'formflow-pro'); ?></span>
                        <span class="metric-value"><?php echo $avg_processing_time ? number_format($avg_processing_time, 0) . 'ms' : 'N/A'; ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Success Rate', 'formflow-pro'); ?></span>
                        <span class="metric-value"><?php echo $conversion_rate; ?>%</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Failed Submissions', 'formflow-pro'); ?></span>
                        <span class="metric-value"><?php echo number_format($failed_submissions); ?></span>
                    </div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <h3><?php esc_html_e('Queue Status', 'formflow-pro'); ?></h3>
                </div>
                <div class="metric-body" id="queue-metrics">
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Pending Jobs', 'formflow-pro'); ?></span>
                        <span class="metric-value" id="queue-pending-count">-</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Processing', 'formflow-pro'); ?></span>
                        <span class="metric-value" id="queue-processing-count">-</span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label"><?php esc_html_e('Failed Jobs', 'formflow-pro'); ?></span>
                        <span class="metric-value" id="queue-failed-count">-</span>
                    </div>
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-header">
                    <h3><?php esc_html_e('System Health', 'formflow-pro'); ?></h3>
                </div>
                <div class="metric-body" id="system-health">
                    <div class="health-indicator" id="health-status">
                        <span class="health-dot"></span>
                        <span class="health-text"><?php esc_html_e('Checking...', 'formflow-pro'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="chart-card chart-full">
            <div class="chart-header">
                <h3><?php esc_html_e('Processing Time Over Time', 'formflow-pro'); ?></h3>
            </div>
            <div class="chart-body">
                <canvas id="performance-chart"></canvas>
            </div>
        </div>
    </div>

    <?php elseif ($view_mode === 'compare') : ?>
    <!-- Period Comparison View -->
    <div class="comparison-view">
        <div class="comparison-config card">
            <h3><?php esc_html_e('Period Comparison', 'formflow-pro'); ?></h3>
            <p class="description"><?php esc_html_e('Compare metrics between two time periods', 'formflow-pro'); ?></p>

            <div class="comparison-periods">
                <div class="period-group">
                    <label><?php esc_html_e('Current Period', 'formflow-pro'); ?></label>
                    <div class="period-dates">
                        <input type="date" id="current-from" value="<?php echo esc_attr($date_from); ?>">
                        <span>-</span>
                        <input type="date" id="current-to" value="<?php echo esc_attr($date_to); ?>">
                    </div>
                </div>

                <div class="period-group">
                    <label><?php esc_html_e('Previous Period', 'formflow-pro'); ?></label>
                    <div class="period-dates">
                        <input type="date" id="previous-from" value="<?php echo esc_attr($prev_date_from); ?>">
                        <span>-</span>
                        <input type="date" id="previous-to" value="<?php echo esc_attr($prev_date_to); ?>">
                    </div>
                </div>

                <button type="button" class="button button-primary" id="compare-periods">
                    <?php esc_html_e('Compare', 'formflow-pro'); ?>
                </button>
            </div>
        </div>

        <div class="comparison-results" id="comparison-results">
            <div class="comparison-grid">
                <div class="comparison-card">
                    <h4><?php esc_html_e('Total Submissions', 'formflow-pro'); ?></h4>
                    <div class="comparison-values">
                        <div class="current-value">
                            <span class="label"><?php esc_html_e('Current', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-current-total"><?php echo number_format($total_submissions); ?></span>
                        </div>
                        <div class="previous-value">
                            <span class="label"><?php esc_html_e('Previous', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-previous-total"><?php echo number_format($prev_total); ?></span>
                        </div>
                        <div class="change-value <?php echo $total_change >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="label"><?php esc_html_e('Change', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-change-total"><?php echo ($total_change >= 0 ? '+' : '') . $total_change; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="comparison-card">
                    <h4><?php esc_html_e('Completed', 'formflow-pro'); ?></h4>
                    <div class="comparison-values">
                        <div class="current-value">
                            <span class="label"><?php esc_html_e('Current', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-current-completed"><?php echo number_format($completed_submissions); ?></span>
                        </div>
                        <div class="previous-value">
                            <span class="label"><?php esc_html_e('Previous', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-previous-completed"><?php echo number_format($prev_completed); ?></span>
                        </div>
                        <div class="change-value <?php echo $completed_change >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="label"><?php esc_html_e('Change', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-change-completed"><?php echo ($completed_change >= 0 ? '+' : '') . $completed_change; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="comparison-card">
                    <h4><?php esc_html_e('Conversion Rate', 'formflow-pro'); ?></h4>
                    <div class="comparison-values">
                        <div class="current-value">
                            <span class="label"><?php esc_html_e('Current', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-current-rate"><?php echo $conversion_rate; ?>%</span>
                        </div>
                        <div class="previous-value">
                            <span class="label"><?php esc_html_e('Previous', 'formflow-pro'); ?></span>
                            <?php
                            $prev_rate = $prev_total > 0 ? round(($prev_completed / $prev_total) * 100, 2) : 0;
                            ?>
                            <span class="value" id="cmp-previous-rate"><?php echo $prev_rate; ?>%</span>
                        </div>
                        <div class="change-value <?php echo ($conversion_rate - $prev_rate) >= 0 ? 'positive' : 'negative'; ?>">
                            <span class="label"><?php esc_html_e('Change', 'formflow-pro'); ?></span>
                            <span class="value" id="cmp-change-rate"><?php echo (($conversion_rate - $prev_rate) >= 0 ? '+' : '') . round($conversion_rate - $prev_rate, 2); ?>pp</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Chart -->
        <div class="chart-card chart-full">
            <div class="chart-header">
                <h3><?php esc_html_e('Period Comparison Chart', 'formflow-pro'); ?></h3>
            </div>
            <div class="chart-body">
                <canvas id="comparison-chart"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Chart Data
const chartData = {
    trend: {
        labels: <?php echo wp_json_encode($date_range); ?>,
        data: <?php echo wp_json_encode($counts); ?>
    },
    status: {
        labels: <?php echo wp_json_encode(array_column($submissions_by_status, 'status')); ?>,
        data: <?php echo wp_json_encode(array_map('intval', array_column($submissions_by_status, 'count'))); ?>
    },
    hourly: {
        labels: <?php echo wp_json_encode(array_map(function($h) { return sprintf('%02d:00', $h); }, $hours)); ?>,
        data: <?php echo wp_json_encode($hourly_counts); ?>
    },
    forms: {
        labels: <?php echo wp_json_encode(array_column($submissions_by_form, 'form_name')); ?>,
        data: <?php echo wp_json_encode(array_map('intval', array_column($submissions_by_form, 'count'))); ?>
    }
};

const analyticsConfig = {
    viewMode: '<?php echo esc_js($view_mode); ?>',
    dateFrom: '<?php echo esc_js($date_from); ?>',
    dateTo: '<?php echo esc_js($date_to); ?>',
    formId: '<?php echo esc_js($form_filter); ?>',
    prevDateFrom: '<?php echo esc_js($prev_date_from); ?>',
    prevDateTo: '<?php echo esc_js($prev_date_to); ?>'
};

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.FormFlowAnalytics !== 'undefined') {
        window.FormFlowAnalytics.initCharts(chartData, analyticsConfig);
        window.FormFlowAnalytics.initAdvancedFeatures(analyticsConfig);
    }
});
</script>
