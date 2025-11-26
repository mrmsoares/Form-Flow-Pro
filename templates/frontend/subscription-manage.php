<?php

/**
 * Subscription Management Frontend Template
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables passed to this template:
// $subscription - Subscription object
// $user - Current user object

$subscription = $subscription ?? null;
$user = $user ?? wp_get_current_user();

if (!$subscription) {
    echo '<div class="formflow-notice formflow-notice-error">' . esc_html__('Subscription not found.', 'formflow-pro') . '</div>';
    return;
}

$currency = get_option('formflow_payments_currency', 'BRL');
$currency_symbols = [
    'BRL' => 'R$',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
];
$symbol = $currency_symbols[$currency] ?? $currency;

// Status colors and labels
$status_info = [
    'active' => ['color' => '#27ae60', 'label' => __('Active', 'formflow-pro')],
    'paused' => ['color' => '#f0ad4e', 'label' => __('Paused', 'formflow-pro')],
    'cancelled' => ['color' => '#dc3545', 'label' => __('Cancelled', 'formflow-pro')],
    'past_due' => ['color' => '#e74c3c', 'label' => __('Past Due', 'formflow-pro')],
];

$current_status = $status_info[$subscription->status] ?? $status_info['active'];

// Billing period labels
$billing_labels = [
    'monthly' => __('month', 'formflow-pro'),
    'yearly' => __('year', 'formflow-pro'),
    'weekly' => __('week', 'formflow-pro'),
];
$billing_label = $billing_labels[$subscription->billing_period ?? 'monthly'] ?? __('month', 'formflow-pro');

?>

<div class="formflow-subscription-manage" style="max-width: 600px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

    <!-- Subscription Header -->
    <div class="subscription-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px;"><?php echo esc_html($subscription->plan_name ?? __('Subscription', 'formflow-pro')); ?></h2>
        <div style="font-size: 36px; font-weight: 700; margin: 15px 0;">
            <?php echo esc_html($symbol . ' ' . number_format($subscription->amount ?? 0, 2, ',', '.')); ?>
            <span style="font-size: 16px; font-weight: 400; opacity: 0.8;">/ <?php echo esc_html($billing_label); ?></span>
        </div>
        <span class="subscription-status" style="display: inline-block; padding: 5px 15px; background: rgba(255,255,255,0.2); border-radius: 20px; font-size: 14px;">
            <?php echo esc_html($current_status['label']); ?>
        </span>
    </div>

    <!-- Subscription Details -->
    <div class="subscription-details" style="background: #fff; border: 1px solid #e5e5e5; border-top: none; padding: 30px;">

        <!-- Next Billing -->
        <?php if ($subscription->status === 'active' && !empty($subscription->next_billing_date)) : ?>
            <div class="detail-row" style="display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
                <span style="color: #666;"><?php esc_html_e('Next billing date', 'formflow-pro'); ?></span>
                <span style="font-weight: 500;"><?php echo esc_html(wp_date(get_option('date_format'), strtotime($subscription->next_billing_date))); ?></span>
            </div>
        <?php endif; ?>

        <!-- Start Date -->
        <div class="detail-row" style="display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
            <span style="color: #666;"><?php esc_html_e('Started', 'formflow-pro'); ?></span>
            <span style="font-weight: 500;"><?php echo esc_html(wp_date(get_option('date_format'), strtotime($subscription->created_at ?? 'now'))); ?></span>
        </div>

        <!-- Payment Method -->
        <div class="detail-row" style="display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
            <span style="color: #666;"><?php esc_html_e('Payment method', 'formflow-pro'); ?></span>
            <span style="font-weight: 500;">
                <?php if ($subscription->gateway === 'stripe') : ?>
                    <span style="display: inline-flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-credit-card" style="font-size: 16px;"></span>
                        <?php echo esc_html('•••• ' . ($subscription->card_last4 ?? '****')); ?>
                    </span>
                <?php elseif ($subscription->gateway === 'paypal') : ?>
                    PayPal
                <?php else : ?>
                    <?php echo esc_html(ucfirst($subscription->gateway ?? '—')); ?>
                <?php endif; ?>
            </span>
        </div>

        <!-- Subscription ID -->
        <div class="detail-row" style="display: flex; justify-content: space-between; padding: 15px 0;">
            <span style="color: #666;"><?php esc_html_e('Subscription ID', 'formflow-pro'); ?></span>
            <span style="font-weight: 500; font-family: monospace; font-size: 12px;"><?php echo esc_html($subscription->external_id ?? 'sub_' . $subscription->id); ?></span>
        </div>
    </div>

    <!-- Actions -->
    <div class="subscription-actions" style="background: #f8f9fa; border: 1px solid #e5e5e5; border-top: none; padding: 20px 30px; border-radius: 0 0 12px 12px;">
        <?php if ($subscription->status === 'active') : ?>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="formflow-btn formflow-btn-secondary" id="pause-subscription" style="flex: 1; padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    <span class="dashicons dashicons-controls-pause" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Pause', 'formflow-pro'); ?>
                </button>
                <button type="button" class="formflow-btn formflow-btn-secondary" id="update-payment" style="flex: 1; padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 6px; cursor: pointer; font-size: 14px;">
                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                    <?php esc_html_e('Update Payment', 'formflow-pro'); ?>
                </button>
            </div>
            <button type="button" class="formflow-btn formflow-btn-danger" id="cancel-subscription" style="width: 100%; padding: 12px; margin-top: 10px; border: none; background: transparent; color: #dc3545; border-radius: 6px; cursor: pointer; font-size: 14px;">
                <?php esc_html_e('Cancel Subscription', 'formflow-pro'); ?>
            </button>
        <?php elseif ($subscription->status === 'paused') : ?>
            <button type="button" class="formflow-btn formflow-btn-primary" id="resume-subscription" style="width: 100%; padding: 15px; border: none; background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600;">
                <span class="dashicons dashicons-controls-play" style="vertical-align: middle;"></span>
                <?php esc_html_e('Resume Subscription', 'formflow-pro'); ?>
            </button>
        <?php elseif ($subscription->status === 'cancelled') : ?>
            <div style="text-align: center; color: #666; padding: 10px;">
                <p style="margin: 0;"><?php esc_html_e('This subscription has been cancelled.', 'formflow-pro'); ?></p>
                <a href="#" class="formflow-resubscribe" style="color: #667eea; text-decoration: none; font-weight: 500;">
                    <?php esc_html_e('Resubscribe', 'formflow-pro'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Billing History -->
    <div class="billing-history" style="margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0; font-size: 18px;"><?php esc_html_e('Billing History', 'formflow-pro'); ?></h3>

        <?php
        global $wpdb;
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_invoices WHERE subscription_id = %d ORDER BY created_at DESC LIMIT 5",
            $subscription->id
        ));
        ?>

        <?php if (empty($invoices)) : ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #666;">
                <?php esc_html_e('No billing history yet.', 'formflow-pro'); ?>
            </div>
        <?php else : ?>
            <div style="background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;">
                <?php foreach ($invoices as $index => $invoice) : ?>
                    <div class="invoice-row" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; <?php echo $index > 0 ? 'border-top: 1px solid #f0f0f0;' : ''; ?>">
                        <div>
                            <div style="font-weight: 500;"><?php echo esc_html(wp_date(get_option('date_format'), strtotime($invoice->created_at))); ?></div>
                            <div style="font-size: 12px; color: #666;"><?php echo esc_html($invoice->invoice_number ?? '#' . $invoice->id); ?></div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-weight: 500;"><?php echo esc_html($symbol . ' ' . number_format($invoice->total, 2)); ?></span>
                            <span style="color: <?php echo $invoice->status === 'paid' ? '#27ae60' : '#f0ad4e'; ?>; font-size: 12px;">
                                <?php echo esc_html(ucfirst($invoice->status)); ?>
                            </span>
                            <a href="#" class="download-invoice" data-invoice-id="<?php echo esc_attr($invoice->id); ?>" style="color: #667eea; text-decoration: none;">
                                <span class="dashicons dashicons-download"></span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Hidden Data -->
    <input type="hidden" id="subscription-id" value="<?php echo esc_attr($subscription->id); ?>">
    <input type="hidden" id="subscription-nonce" value="<?php echo wp_create_nonce('formflow_subscription_' . $subscription->id); ?>">
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancel-modal" class="formflow-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div class="formflow-modal-content" style="background: #fff; padding: 30px; border-radius: 12px; max-width: 400px; text-align: center;">
        <span class="dashicons dashicons-warning" style="font-size: 48px; width: 48px; height: 48px; color: #f0ad4e;"></span>
        <h3 style="margin: 15px 0;"><?php esc_html_e('Cancel Subscription?', 'formflow-pro'); ?></h3>
        <p style="color: #666; margin-bottom: 20px;">
            <?php esc_html_e('Your subscription will remain active until the end of the current billing period.', 'formflow-pro'); ?>
        </p>
        <div style="display: flex; gap: 10px;">
            <button type="button" class="close-modal" style="flex: 1; padding: 12px; border: 1px solid #ddd; background: #fff; border-radius: 6px; cursor: pointer;">
                <?php esc_html_e('Keep Subscription', 'formflow-pro'); ?>
            </button>
            <button type="button" id="confirm-cancel" style="flex: 1; padding: 12px; border: none; background: #dc3545; color: #fff; border-radius: 6px; cursor: pointer;">
                <?php esc_html_e('Yes, Cancel', 'formflow-pro'); ?>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    // Cancel subscription
    var cancelBtn = document.getElementById('cancel-subscription');
    var cancelModal = document.getElementById('cancel-modal');

    if (cancelBtn && cancelModal) {
        cancelBtn.addEventListener('click', function() {
            cancelModal.style.display = 'flex';
        });

        cancelModal.querySelector('.close-modal').addEventListener('click', function() {
            cancelModal.style.display = 'none';
        });

        document.getElementById('confirm-cancel').addEventListener('click', function() {
            // AJAX call to cancel subscription
            var formData = new FormData();
            formData.append('action', 'formflow_cancel_subscription');
            formData.append('subscription_id', document.getElementById('subscription-id').value);
            formData.append('nonce', document.getElementById('subscription-nonce').value);

            fetch(formflow_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.data.message || '<?php echo esc_js(__('Error cancelling subscription.', 'formflow-pro')); ?>');
                }
            });
        });
    }

    // Pause subscription
    var pauseBtn = document.getElementById('pause-subscription');
    if (pauseBtn) {
        pauseBtn.addEventListener('click', function() {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to pause your subscription?', 'formflow-pro')); ?>')) return;

            var formData = new FormData();
            formData.append('action', 'formflow_pause_subscription');
            formData.append('subscription_id', document.getElementById('subscription-id').value);
            formData.append('nonce', document.getElementById('subscription-nonce').value);

            fetch(formflow_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.data.message || '<?php echo esc_js(__('Error pausing subscription.', 'formflow-pro')); ?>');
                }
            });
        });
    }

    // Resume subscription
    var resumeBtn = document.getElementById('resume-subscription');
    if (resumeBtn) {
        resumeBtn.addEventListener('click', function() {
            var formData = new FormData();
            formData.append('action', 'formflow_resume_subscription');
            formData.append('subscription_id', document.getElementById('subscription-id').value);
            formData.append('nonce', document.getElementById('subscription-nonce').value);

            fetch(formflow_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.data.message || '<?php echo esc_js(__('Error resuming subscription.', 'formflow-pro')); ?>');
                }
            });
        });
    }
})();
</script>
