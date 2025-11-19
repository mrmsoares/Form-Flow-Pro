<?php
/**
 * Admin Dashboard View
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get stats
$stats = [
    'total_submissions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions"),
    'total_forms' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_forms WHERE status = 'active'"),
    'pending_signatures' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions WHERE status = 'pending_signature'"),
    'completed_today' => (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_submissions
            WHERE status = 'completed' AND DATE(created_at) = %s",
            current_time('Y-m-d')
        )
    ),
];

// Get recent submissions
$recent_submissions = $wpdb->get_results(
    "SELECT s.*, f.name as form_name
    FROM {$wpdb->prefix}formflow_submissions s
    LEFT JOIN {$wpdb->prefix}formflow_forms f ON s.form_id = f.id
    ORDER BY s.created_at DESC
    LIMIT 10"
);

// Get chart data (last 30 days)
$chart_data = $wpdb->get_results(
    "SELECT DATE(created_at) as date, COUNT(*) as count
    FROM {$wpdb->prefix}formflow_submissions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC",
    ARRAY_A
);

// Fill missing dates with zeros
$dates = [];
$counts = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('M d', strtotime($date));

    $found = false;
    foreach ($chart_data as $data) {
        if ($data['date'] === $date) {
            $counts[] = (int) $data['count'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $counts[] = 0;
    }
}
?>

<div class="wrap formflow-admin formflow-dashboard">
    <h1 class="wp-heading-inline">
        <?php echo esc_html(get_admin_page_title()); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div class="formflow-stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <span class="dashicons dashicons-forms"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_submissions']); ?></div>
                <div class="stat-label"><?php _e('Total Submissions', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['total_forms']); ?></div>
                <div class="stat-label"><?php _e('Active Forms', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <span class="dashicons dashicons-edit"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['pending_signatures']); ?></div>
                <div class="stat-label"><?php _e('Pending Signatures', 'formflow-pro'); ?></div>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($stats['completed_today']); ?></div>
                <div class="stat-label"><?php _e('Completed Today', 'formflow-pro'); ?></div>
            </div>
        </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="formflow-dashboard-grid">
        <!-- Chart Section -->
        <div class="dashboard-section chart-section">
            <div class="card">
                <h2><?php _e('Submissions (Last 30 Days)', 'formflow-pro'); ?></h2>
                <div class="chart-container">
                    <canvas id="submissionsChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Submissions -->
        <div class="dashboard-section recent-section">
            <div class="card">
                <h2>
                    <?php _e('Recent Submissions', 'formflow-pro'); ?>
                    <a href="<?php echo admin_url('admin.php?page=formflow-submissions'); ?>" class="button button-small">
                        <?php _e('View All', 'formflow-pro'); ?>
                    </a>
                </h2>

                <?php if (empty($recent_submissions)) : ?>
                    <p class="no-items"><?php _e('No submissions yet.', 'formflow-pro'); ?></p>
                <?php else : ?>
                    <div class="recent-submissions-list">
                        <?php foreach ($recent_submissions as $submission) : ?>
                            <div class="submission-item">
                                <div class="submission-info">
                                    <strong><?php echo esc_html($submission->form_name ?: __('Unknown Form', 'formflow-pro')); ?></strong>
                                    <span class="submission-date">
                                        <?php echo human_time_diff(strtotime($submission->created_at), current_time('timestamp')); ?>
                                        <?php _e('ago', 'formflow-pro'); ?>
                                    </span>
                                </div>
                                <div class="submission-status">
                                    <?php
                                    $status_classes = [
                                        'completed' => 'success',
                                        'pending' => 'warning',
                                        'pending_signature' => 'info',
                                        'failed' => 'error',
                                    ];
                                    $status_class = $status_classes[$submission->status] ?? 'default';
                                    ?>
                                    <span class="status-badge status-<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $submission->status))); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-section actions-section">
            <div class="card">
                <h2><?php _e('Quick Actions', 'formflow-pro'); ?></h2>
                <div class="quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=formflow-submissions&status=pending_signature'); ?>" class="action-button">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Review Pending Signatures', 'formflow-pro'); ?>
                        <?php if ($stats['pending_signatures'] > 0) : ?>
                            <span class="badge"><?php echo $stats['pending_signatures']; ?></span>
                        <?php endif; ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=formflow-analytics'); ?>" class="action-button">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php _e('View Analytics', 'formflow-pro'); ?>
                    </a>

                    <a href="<?php echo admin_url('admin.php?page=formflow-settings'); ?>" class="action-button">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Plugin Settings', 'formflow-pro'); ?>
                    </a>

                    <a href="#" class="action-button" id="clear-cache-btn">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Clear Cache', 'formflow-pro'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="dashboard-section status-section">
            <div class="card">
                <h2><?php _e('System Status', 'formflow-pro'); ?></h2>
                <div class="system-status">
                    <?php
                    $autentique_key = get_option('formflow_autentique_api_key');
                    $cache_enabled = wp_using_ext_object_cache();
                    $queue_running = wp_next_scheduled('formflow_process_queue');
                    ?>

                    <div class="status-item">
                        <span class="status-label"><?php _e('Autentique API', 'formflow-pro'); ?>:</span>
                        <span class="status-value <?php echo $autentique_key ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $autentique_key ? __('Connected', 'formflow-pro') : __('Not Configured', 'formflow-pro'); ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label"><?php _e('Object Cache', 'formflow-pro'); ?>:</span>
                        <span class="status-value <?php echo $cache_enabled ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $cache_enabled ? __('Enabled', 'formflow-pro') : __('Disabled', 'formflow-pro'); ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label"><?php _e('Queue Processor', 'formflow-pro'); ?>:</span>
                        <span class="status-value <?php echo $queue_running ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $queue_running ? __('Running', 'formflow-pro') : __('Idle', 'formflow-pro'); ?>
                        </span>
                    </div>

                    <div class="status-item">
                        <span class="status-label"><?php _e('Plugin Version', 'formflow-pro'); ?>:</span>
                        <span class="status-value"><?php echo FORMFLOW_VERSION; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
jQuery(document).ready(function($) {
    // Chart.js initialization
    if (typeof Chart !== 'undefined') {
        const ctx = document.getElementById('submissionsChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: '<?php _e('Submissions', 'formflow-pro'); ?>',
                        data: <?php echo json_encode($counts); ?>,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }

    // Clear cache action
    $('#clear-cache-btn').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e('Are you sure you want to clear all cache?', 'formflow-pro'); ?>')) {
            $.post(ajaxurl, {
                action: 'formflow_clear_cache',
                nonce: formflowData.nonce
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Cache cleared successfully!', 'formflow-pro'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Failed to clear cache.', 'formflow-pro'); ?>');
                }
            });
        }
    });
});
</script>
