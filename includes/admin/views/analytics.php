<?php

/**
 * Analytics Dashboard Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get date range parameters
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
$form_filter = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;

// Get all forms for filter
$forms = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}formflow_forms ORDER BY name ASC");

// Build WHERE clause for filtering
$where_clauses = ["DATE(created_at) BETWEEN %s AND %s"];
$where_values = [$date_from, $date_to];

if ($form_filter) {
    $where_clauses[] = "form_id = %d";
    $where_values[] = $form_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// Get overview stats
$total_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE $where_sql",
    $where_values
));

$completed_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'completed' AND $where_sql",
    $where_values
));

$pending_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status IN ('pending', 'pending_signature') AND $where_sql",
    $where_values
));

$failed_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'failed' AND $where_sql",
    $where_values
));

$conversion_rate = $total_submissions > 0 ? round(($completed_submissions / $total_submissions) * 100, 2) : 0;

// Get submissions by form
$submissions_by_form = $wpdb->get_results($wpdb->prepare(
    "SELECT f.name as form_name, COUNT(s.id) as count
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

// Get hourly distribution (all time for selected period)
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

?>

<div class="wrap formflow-admin formflow-analytics">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Analytics', 'formflow-pro'); ?>
    </h1>

    <a href="#" class="page-title-action" id="export-analytics">
        <span class="dashicons dashicons-download"></span>
        <?php esc_html_e('Export Report', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Filters -->
    <div class="analytics-filters card">
        <form method="get" action="">
            <input type="hidden" name="page" value="formflow-analytics">

            <div class="filter-group">
                <label for="form-filter"><?php esc_html_e('Form:', 'formflow-pro'); ?></label>
                <select name="form_id" id="form-filter">
                    <option value="0"><?php esc_html_e('All Forms', 'formflow-pro'); ?></option>
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

            <div class="filter-group">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Apply Filters', 'formflow-pro'); ?>
                </button>
                <a href="?page=formflow-analytics" class="button">
                    <?php esc_html_e('Reset', 'formflow-pro'); ?>
                </a>
            </div>
        </form>
    </div>

    <!-- Stats Grid -->
    <div class="analytics-stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <span class="dashicons dashicons-forms"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Total Submissions', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($completed_submissions); ?></div>
                <div class="stat-label"><?php esc_html_e('Completed', 'formflow-pro'); ?></div>
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
                <p class="chart-description"><?php esc_html_e('Submissions by hour of day', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="hourly-distribution-chart"></canvas>
            </div>
        </div>

        <!-- Top Forms -->
        <div class="chart-card chart-medium">
            <div class="chart-header">
                <h3><?php esc_html_e('Top Forms', 'formflow-pro'); ?></h3>
                <p class="chart-description"><?php esc_html_e('Forms with most submissions', 'formflow-pro'); ?></p>
            </div>
            <div class="chart-body">
                <canvas id="top-forms-chart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Chart Data
const chartData = {
    trend: {
        labels: <?php echo json_encode($date_range); ?>,
        data: <?php echo json_encode($counts); ?>
    },
    status: {
        labels: <?php echo json_encode(array_column($submissions_by_status, 'status')); ?>,
        data: <?php echo json_encode(array_map('intval', array_column($submissions_by_status, 'count'))); ?>
    },
    hourly: {
        labels: <?php echo json_encode(array_map(function($h) { return sprintf('%02d:00', $h); }, $hours)); ?>,
        data: <?php echo json_encode($hourly_counts); ?>
    },
    forms: {
        labels: <?php echo json_encode(array_column($submissions_by_form, 'form_name')); ?>,
        data: <?php echo json_encode(array_map('intval', array_column($submissions_by_form, 'count'))); ?>
    }
};

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.FormFlowAnalytics !== 'undefined') {
        window.FormFlowAnalytics.initCharts(chartData);
    }
});
</script>
