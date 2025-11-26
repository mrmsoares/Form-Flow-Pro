<?php

/**
 * Subscriptions Management Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle subscription actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : 0;

if ($action && $subscription_id && check_admin_referer('subscription_action_' . $subscription_id)) {
    switch ($action) {
        case 'cancel':
            $wpdb->update(
                $wpdb->prefix . 'formflow_subscriptions',
                ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
                ['id' => $subscription_id],
                ['%s', '%s'],
                ['%d']
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Subscription cancelled.', 'formflow-pro') . '</p></div>';
            break;

        case 'pause':
            $wpdb->update(
                $wpdb->prefix . 'formflow_subscriptions',
                ['status' => 'paused', 'updated_at' => current_time('mysql')],
                ['id' => $subscription_id],
                ['%s', '%s'],
                ['%d']
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Subscription paused.', 'formflow-pro') . '</p></div>';
            break;

        case 'resume':
            $wpdb->update(
                $wpdb->prefix . 'formflow_subscriptions',
                ['status' => 'active', 'updated_at' => current_time('mysql')],
                ['id' => $subscription_id],
                ['%s', '%s'],
                ['%d']
            );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Subscription resumed.', 'formflow-pro') . '</p></div>';
            break;
    }
}

// Get subscription statistics
$stats = [
    'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_subscriptions WHERE status = 'active'"),
    'paused' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_subscriptions WHERE status = 'paused'"),
    'cancelled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_subscriptions WHERE status = 'cancelled'"),
    'mrr' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}formflow_subscriptions WHERE status = 'active' AND billing_period = 'monthly'"),
];

// Get subscriptions with pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where_clause = $status_filter ? $wpdb->prepare(" WHERE status = %s", $status_filter) : '';

$total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_subscriptions" . $where_clause);
$total_pages = ceil($total_items / $per_page);

$subscriptions = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}formflow_subscriptions" . $where_clause . " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"
);

$currency = get_option('formflow_payments_currency', 'BRL');

?>

<div class="wrap formflow-admin formflow-subscriptions">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-update-alt"></span>
        <?php esc_html_e('Subscriptions', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #27ae60;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['active']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Active', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #f0ad4e;">
                <span class="dashicons dashicons-controls-pause"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['paused']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Paused', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #dc3545;">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['cancelled']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Cancelled', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #0073aa;">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;">
                <?php echo esc_html(number_format($stats['mrr'], 2, ',', '.')); ?>
            </div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('MRR', 'formflow-pro'); ?></div>
        </div>
    </div>

    <!-- Status Filter -->
    <ul class="subsubsub" style="margin-bottom: 10px;">
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-subscriptions')); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
                <?php esc_html_e('All', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-subscriptions&status=active')); ?>" class="<?php echo $status_filter === 'active' ? 'current' : ''; ?>">
                <?php esc_html_e('Active', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-subscriptions&status=paused')); ?>" class="<?php echo $status_filter === 'paused' ? 'current' : ''; ?>">
                <?php esc_html_e('Paused', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-subscriptions&status=cancelled')); ?>" class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
                <?php esc_html_e('Cancelled', 'formflow-pro'); ?>
            </a>
        </li>
    </ul>

    <!-- Subscriptions Table -->
    <div class="card" style="margin-top: 20px; padding: 0;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;"><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Customer', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Plan', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Amount', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Gateway', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Next Billing', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subscriptions)) : ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            <?php esc_html_e('No subscriptions found.', 'formflow-pro'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($subscriptions as $subscription) : ?>
                        <tr>
                            <td>#<?php echo esc_html($subscription->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($subscription->customer_email ?? '—'); ?></strong>
                                <?php if (!empty($subscription->customer_name)) : ?>
                                    <br><small style="color: #666;"><?php echo esc_html($subscription->customer_name); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($subscription->plan_name ?? __('Custom', 'formflow-pro')); ?></td>
                            <td>
                                <strong><?php echo esc_html($currency . ' ' . number_format($subscription->amount ?? 0, 2)); ?></strong>
                                <br><small style="color: #666;">/ <?php echo esc_html($subscription->billing_period ?? 'monthly'); ?></small>
                            </td>
                            <td><?php echo esc_html(ucfirst($subscription->gateway ?? '—')); ?></td>
                            <td>
                                <?php
                                $status_colors = [
                                    'active' => '#27ae60',
                                    'paused' => '#f0ad4e',
                                    'cancelled' => '#dc3545',
                                    'past_due' => '#e74c3c',
                                ];
                                $color = $status_colors[$subscription->status] ?? '#666';
                                ?>
                                <span style="color: <?php echo esc_attr($color); ?>; font-weight: 500;">
                                    <?php echo esc_html(ucfirst($subscription->status)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($subscription->next_billing_date)) : ?>
                                    <?php echo esc_html(wp_date(get_option('date_format'), strtotime($subscription->next_billing_date))); ?>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($subscription->status === 'active') : ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-subscriptions&action=pause&subscription_id=' . $subscription->id), 'subscription_action_' . $subscription->id); ?>" class="button button-small">
                                        <?php esc_html_e('Pause', 'formflow-pro'); ?>
                                    </a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-subscriptions&action=cancel&subscription_id=' . $subscription->id), 'subscription_action_' . $subscription->id); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to cancel this subscription?', 'formflow-pro')); ?>');">
                                        <?php esc_html_e('Cancel', 'formflow-pro'); ?>
                                    </a>
                                <?php elseif ($subscription->status === 'paused') : ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=formflow-subscriptions&action=resume&subscription_id=' . $subscription->id), 'subscription_action_' . $subscription->id); ?>" class="button button-small button-primary">
                                        <?php esc_html_e('Resume', 'formflow-pro'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'total' => $total_pages,
                    'current' => $current_page,
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
