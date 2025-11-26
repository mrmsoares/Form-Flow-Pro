<?php

/**
 * Invoices Management Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get invoice statistics
$stats = [
    'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_invoices"),
    'paid' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_invoices WHERE status = 'paid'"),
    'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_invoices WHERE status = 'pending'"),
    'overdue' => (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_invoices WHERE status = 'pending' AND due_date < %s",
        current_time('Y-m-d')
    )),
    'total_revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(total), 0) FROM {$wpdb->prefix}formflow_invoices WHERE status = 'paid'"),
];

// Get invoices with pagination
$per_page = 20;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$where_clause = $status_filter ? $wpdb->prepare(" WHERE status = %s", $status_filter) : '';

$total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_invoices" . $where_clause);
$total_pages = ceil($total_items / $per_page);

$invoices = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}formflow_invoices" . $where_clause . " ORDER BY created_at DESC LIMIT $per_page OFFSET $offset"
);

$currency = get_option('formflow_payments_currency', 'BRL');

?>

<div class="wrap formflow-admin formflow-invoices">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-media-text"></span>
        <?php esc_html_e('Invoices', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <a href="#" class="page-title-action" id="create-invoice">
        <?php esc_html_e('Create Invoice', 'formflow-pro'); ?>
    </a>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['total']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Total', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0; border-left: 4px solid #27ae60;">
            <div style="font-size: 28px; font-weight: bold; color: #27ae60;"><?php echo esc_html($stats['paid']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Paid', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0; border-left: 4px solid #f0ad4e;">
            <div style="font-size: 28px; font-weight: bold; color: #f0ad4e;"><?php echo esc_html($stats['pending']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Pending', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0; border-left: 4px solid #dc3545;">
            <div style="font-size: 28px; font-weight: bold; color: #dc3545;"><?php echo esc_html($stats['overdue']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Overdue', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0; border-left: 4px solid #0073aa;">
            <div style="font-size: 22px; font-weight: bold; color: #0073aa;"><?php echo esc_html(number_format($stats['total_revenue'], 2, ',', '.')); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Revenue', 'formflow-pro'); ?></div>
        </div>
    </div>

    <!-- Status Filter -->
    <ul class="subsubsub" style="margin-bottom: 10px;">
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-invoices')); ?>" class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
                <?php esc_html_e('All', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-invoices&status=paid')); ?>" class="<?php echo $status_filter === 'paid' ? 'current' : ''; ?>">
                <?php esc_html_e('Paid', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-invoices&status=pending')); ?>" class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                <?php esc_html_e('Pending', 'formflow-pro'); ?>
            </a> |
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-invoices&status=cancelled')); ?>" class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
                <?php esc_html_e('Cancelled', 'formflow-pro'); ?>
            </a>
        </li>
    </ul>

    <!-- Invoices Table -->
    <div class="card" style="margin-top: 20px; padding: 0;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 100px;"><?php esc_html_e('Invoice #', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Customer', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Amount', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Issue Date', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Due Date', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)) : ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                            <?php esc_html_e('No invoices found.', 'formflow-pro'); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($invoices as $invoice) : ?>
                        <?php
                        $is_overdue = $invoice->status === 'pending' && !empty($invoice->due_date) && strtotime($invoice->due_date) < time();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($invoice->invoice_number ?? '#' . $invoice->id); ?></strong>
                            </td>
                            <td>
                                <strong><?php echo esc_html($invoice->customer_name ?? '—'); ?></strong>
                                <?php if (!empty($invoice->customer_email)) : ?>
                                    <br><small style="color: #666;"><?php echo esc_html($invoice->customer_email); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html($currency . ' ' . number_format($invoice->total ?? 0, 2)); ?></strong>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'paid' => '#27ae60',
                                    'pending' => '#f0ad4e',
                                    'cancelled' => '#6c757d',
                                    'refunded' => '#dc3545',
                                ];
                                $color = $is_overdue ? '#dc3545' : ($status_colors[$invoice->status] ?? '#666');
                                $label = $is_overdue ? __('Overdue', 'formflow-pro') : ucfirst($invoice->status);
                                ?>
                                <span style="color: <?php echo esc_attr($color); ?>; font-weight: 500;">
                                    <?php echo esc_html($label); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($invoice->created_at) ? esc_html(wp_date(get_option('date_format'), strtotime($invoice->created_at))) : '—'; ?>
                            </td>
                            <td>
                                <?php if (!empty($invoice->due_date)) : ?>
                                    <span style="<?php echo $is_overdue ? 'color: #dc3545;' : ''; ?>">
                                        <?php echo esc_html(wp_date(get_option('date_format'), strtotime($invoice->due_date))); ?>
                                    </span>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="#" class="button button-small view-invoice" data-invoice-id="<?php echo esc_attr($invoice->id); ?>">
                                    <span class="dashicons dashicons-visibility" style="margin-top: 3px;"></span>
                                    <?php esc_html_e('View', 'formflow-pro'); ?>
                                </a>
                                <a href="#" class="button button-small download-invoice" data-invoice-id="<?php echo esc_attr($invoice->id); ?>">
                                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                                    <?php esc_html_e('PDF', 'formflow-pro'); ?>
                                </a>
                                <?php if ($invoice->status === 'pending') : ?>
                                    <a href="#" class="button button-small send-reminder" data-invoice-id="<?php echo esc_attr($invoice->id); ?>">
                                        <span class="dashicons dashicons-email-alt" style="margin-top: 3px;"></span>
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

<!-- Create Invoice Modal -->
<div id="create-invoice-modal" class="formflow-modal" style="display: none;">
    <div class="formflow-modal-overlay"></div>
    <div class="formflow-modal-content" style="max-width: 700px;">
        <div class="formflow-modal-header">
            <h2><?php esc_html_e('Create Invoice', 'formflow-pro'); ?></h2>
            <button type="button" class="formflow-modal-close">
                <span class="dashicons dashicons-no"></span>
            </button>
        </div>

        <div class="formflow-modal-body">
            <form id="create-invoice-form">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="formflow-form-group">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Customer Name', 'formflow-pro'); ?> <span style="color: red;">*</span>
                        </label>
                        <input type="text" name="customer_name" class="regular-text" style="width: 100%;" required>
                    </div>

                    <div class="formflow-form-group">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Customer Email', 'formflow-pro'); ?> <span style="color: red;">*</span>
                        </label>
                        <input type="email" name="customer_email" class="regular-text" style="width: 100%;" required>
                    </div>
                </div>

                <div class="formflow-form-group" style="margin-top: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                        <?php esc_html_e('Line Items', 'formflow-pro'); ?>
                    </label>
                    <table class="wp-list-table widefat" id="invoice-items">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                                <th style="width: 80px;"><?php esc_html_e('Qty', 'formflow-pro'); ?></th>
                                <th style="width: 120px;"><?php esc_html_e('Price', 'formflow-pro'); ?></th>
                                <th style="width: 120px;"><?php esc_html_e('Total', 'formflow-pro'); ?></th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="invoice-item-row">
                                <td><input type="text" name="items[0][description]" class="regular-text" style="width: 100%;"></td>
                                <td><input type="number" name="items[0][qty]" value="1" min="1" class="small-text item-qty" style="width: 100%;"></td>
                                <td><input type="number" name="items[0][price]" step="0.01" min="0" class="small-text item-price" style="width: 100%;"></td>
                                <td class="item-total">0.00</td>
                                <td><button type="button" class="button remove-item"><span class="dashicons dashicons-trash"></span></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5">
                                    <button type="button" class="button" id="add-invoice-item">
                                        <span class="dashicons dashicons-plus"></span>
                                        <?php esc_html_e('Add Item', 'formflow-pro'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: 600;"><?php esc_html_e('Total:', 'formflow-pro'); ?></td>
                                <td id="invoice-total" style="font-weight: 600;"><?php echo esc_html($currency); ?> 0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div class="formflow-form-group">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Due Date', 'formflow-pro'); ?>
                        </label>
                        <input type="date" name="due_date" class="regular-text" style="width: 100%;">
                    </div>

                    <div class="formflow-form-group">
                        <label style="font-weight: 600; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Notes', 'formflow-pro'); ?>
                        </label>
                        <textarea name="notes" rows="3" class="large-text" style="width: 100%;"></textarea>
                    </div>
                </div>
            </form>
        </div>

        <div class="formflow-modal-footer">
            <button type="button" class="button" id="cancel-invoice">
                <?php esc_html_e('Cancel', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button button-primary" id="save-invoice">
                <?php esc_html_e('Create Invoice', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>
