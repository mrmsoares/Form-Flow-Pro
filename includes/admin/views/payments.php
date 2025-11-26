<?php

/**
 * Payment Processing Settings Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle form submission
if (isset($_POST['formflow_payments_submit'])) {
    check_admin_referer('formflow_payments', 'formflow_payments_nonce');

    // General settings
    update_option('formflow_payments_enabled', isset($_POST['payments_enabled']) ? 1 : 0);
    update_option('formflow_payments_currency', sanitize_text_field($_POST['currency'] ?? 'BRL'));
    update_option('formflow_payments_test_mode', isset($_POST['test_mode']) ? 1 : 0);

    // Stripe settings
    update_option('formflow_stripe_enabled', isset($_POST['stripe_enabled']) ? 1 : 0);
    update_option('formflow_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key'] ?? ''));
    update_option('formflow_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key'] ?? ''));
    update_option('formflow_stripe_webhook_secret', sanitize_text_field($_POST['stripe_webhook_secret'] ?? ''));

    // PayPal settings
    update_option('formflow_paypal_enabled', isset($_POST['paypal_enabled']) ? 1 : 0);
    update_option('formflow_paypal_client_id', sanitize_text_field($_POST['paypal_client_id'] ?? ''));
    update_option('formflow_paypal_client_secret', sanitize_text_field($_POST['paypal_client_secret'] ?? ''));

    // WooCommerce settings
    update_option('formflow_woocommerce_enabled', isset($_POST['woocommerce_enabled']) ? 1 : 0);

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Payment settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current settings
$settings = [
    'enabled' => get_option('formflow_payments_enabled', 0),
    'currency' => get_option('formflow_payments_currency', 'BRL'),
    'test_mode' => get_option('formflow_payments_test_mode', 1),
    'stripe' => [
        'enabled' => get_option('formflow_stripe_enabled', 0),
        'publishable_key' => get_option('formflow_stripe_publishable_key', ''),
        'secret_key' => get_option('formflow_stripe_secret_key', ''),
        'webhook_secret' => get_option('formflow_stripe_webhook_secret', ''),
    ],
    'paypal' => [
        'enabled' => get_option('formflow_paypal_enabled', 0),
        'client_id' => get_option('formflow_paypal_client_id', ''),
        'client_secret' => get_option('formflow_paypal_client_secret', ''),
    ],
    'woocommerce' => [
        'enabled' => get_option('formflow_woocommerce_enabled', 0),
    ],
];

// Webhook URLs
$stripe_webhook_url = home_url('/formflow/payments/stripe/webhook');
$paypal_webhook_url = home_url('/formflow/payments/paypal/webhook');

// Get payment statistics
$stats = [
    'total_transactions' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_transactions"),
    'total_revenue' => (float) $wpdb->get_var("SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}formflow_transactions WHERE status = 'completed'"),
    'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_transactions WHERE status = 'pending'"),
    'today_revenue' => (float) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}formflow_transactions WHERE status = 'completed' AND DATE(created_at) = %s",
        current_time('Y-m-d')
    )),
];

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

// Currency list
$currencies = [
    'BRL' => 'R$ - Brazilian Real',
    'USD' => '$ - US Dollar',
    'EUR' => '€ - Euro',
    'GBP' => '£ - British Pound',
    'ARS' => '$ - Argentine Peso',
    'CLP' => '$ - Chilean Peso',
    'COP' => '$ - Colombian Peso',
    'MXN' => '$ - Mexican Peso',
    'PEN' => 'S/ - Peruvian Sol',
];

?>

<div class="wrap formflow-admin formflow-payments">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-money-alt"></span>
        <?php esc_html_e('Payment Processing', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <hr class="wp-header-end">

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #27ae60;">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['total_transactions']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Total Transactions', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #0073aa;">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;">
                <?php echo esc_html(number_format($stats['total_revenue'], 2, ',', '.')); ?>
            </div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Total Revenue', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #f0ad4e;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['pending']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Pending', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #9b59b6;">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;">
                <?php echo esc_html(number_format($stats['today_revenue'], 2, ',', '.')); ?>
            </div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Today', 'formflow-pro'); ?></div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?page=formflow-payments&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Overview', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-payments&tab=stripe" class="nav-tab <?php echo $active_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('Stripe', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-payments&tab=paypal" class="nav-tab <?php echo $active_tab === 'paypal' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-money-alt"></span>
            <?php esc_html_e('PayPal', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-payments&tab=woocommerce" class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-cart"></span>
            <?php esc_html_e('WooCommerce', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-payments&tab=transactions" class="nav-tab <?php echo $active_tab === 'transactions' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Transactions', 'formflow-pro'); ?>
        </a>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('formflow_payments', 'formflow_payments_nonce'); ?>

        <!-- Overview Tab -->
        <?php if ($active_tab === 'overview') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-admin-settings" style="color: #0073aa;"></span>
                        <?php esc_html_e('General Payment Settings', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Payments', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="payments_enabled"
                                           value="1"
                                           <?php checked($settings['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable payment processing for forms', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="currency"><?php esc_html_e('Currency', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <select id="currency" name="currency" class="regular-text">
                                    <?php foreach ($currencies as $code => $label) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['currency'], $code); ?>>
                                            <?php echo esc_html($code . ' - ' . $label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Test Mode', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="test_mode"
                                           value="1"
                                           <?php checked($settings['test_mode'], 1); ?>>
                                    <?php esc_html_e('Enable test/sandbox mode', 'formflow-pro'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Use test credentials for all payment gateways. No real charges will be made.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Gateway Status Cards -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                    <!-- Stripe -->
                    <div class="card" style="padding: 20px; <?php echo $settings['stripe']['enabled'] ? 'border-left: 4px solid #635bff;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span style="color: #635bff; font-weight: bold;">Stripe</span>
                        </h3>
                        <p style="color: #666;"><?php esc_html_e('Accept credit cards worldwide', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['stripe']['enabled'] ? '#635bff22' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['stripe']['enabled'] ? '#635bff' : '#666'; ?>;">
                                <?php echo $settings['stripe']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-payments&tab=stripe" class="button"><?php esc_html_e('Configure', 'formflow-pro'); ?></a>
                    </div>

                    <!-- PayPal -->
                    <div class="card" style="padding: 20px; <?php echo $settings['paypal']['enabled'] ? 'border-left: 4px solid #003087;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span style="color: #003087; font-weight: bold;">PayPal</span>
                        </h3>
                        <p style="color: #666;"><?php esc_html_e('Accept PayPal and credit cards', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['paypal']['enabled'] ? '#00308722' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['paypal']['enabled'] ? '#003087' : '#666'; ?>;">
                                <?php echo $settings['paypal']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-payments&tab=paypal" class="button"><?php esc_html_e('Configure', 'formflow-pro'); ?></a>
                    </div>

                    <!-- WooCommerce -->
                    <div class="card" style="padding: 20px; <?php echo $settings['woocommerce']['enabled'] ? 'border-left: 4px solid #96588a;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span style="color: #96588a; font-weight: bold;">WooCommerce</span>
                        </h3>
                        <p style="color: #666;"><?php esc_html_e('Use WooCommerce payment gateways', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['woocommerce']['enabled'] ? '#96588a22' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['woocommerce']['enabled'] ? '#96588a' : '#666'; ?>;">
                                <?php echo $settings['woocommerce']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-payments&tab=woocommerce" class="button"><?php esc_html_e('Configure', 'formflow-pro'); ?></a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stripe Tab -->
        <?php if ($active_tab === 'stripe') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #635bff; font-weight: bold; font-size: 24px;">Stripe</span>
                        <?php if ($settings['test_mode']) : ?>
                            <span style="background: #f0ad4e; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px;">TEST MODE</span>
                        <?php endif; ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Stripe', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="stripe_enabled"
                                           value="1"
                                           <?php checked($settings['stripe']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable Stripe payment gateway', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="stripe_publishable_key"><?php esc_html_e('Publishable Key', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="stripe_publishable_key"
                                       name="stripe_publishable_key"
                                       value="<?php echo esc_attr($settings['stripe']['publishable_key']); ?>"
                                       class="regular-text"
                                       placeholder="pk_test_...">
                                <p class="description"><?php esc_html_e('Starts with pk_test_ (test) or pk_live_ (production)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="stripe_secret_key"><?php esc_html_e('Secret Key', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="stripe_secret_key"
                                       name="stripe_secret_key"
                                       value="<?php echo esc_attr($settings['stripe']['secret_key']); ?>"
                                       class="regular-text"
                                       placeholder="sk_test_..."
                                       autocomplete="new-password">
                                <p class="description"><?php esc_html_e('Starts with sk_test_ (test) or sk_live_ (production)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="stripe_webhook_secret"><?php esc_html_e('Webhook Secret', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="stripe_webhook_secret"
                                       name="stripe_webhook_secret"
                                       value="<?php echo esc_attr($settings['stripe']['webhook_secret']); ?>"
                                       class="regular-text"
                                       placeholder="whsec_..."
                                       autocomplete="new-password">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Webhook URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #f5f5f5;"><?php echo esc_html($stripe_webhook_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($stripe_webhook_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                                <p class="description"><?php esc_html_e('Add this URL to your Stripe webhook settings.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('Setup Instructions', 'formflow-pro'); ?></h4>
                        <ol style="margin-left: 20px; line-height: 1.8;">
                            <li><?php printf(esc_html__('Go to %s', 'formflow-pro'), '<a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard > API Keys</a>'); ?></li>
                            <li><?php esc_html_e('Copy your Publishable and Secret keys', 'formflow-pro'); ?></li>
                            <li><?php printf(esc_html__('Go to %s and add the webhook URL above', 'formflow-pro'), '<a href="https://dashboard.stripe.com/webhooks" target="_blank">Webhooks</a>'); ?></li>
                            <li><?php esc_html_e('Copy the Webhook Signing Secret', 'formflow-pro'); ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PayPal Tab -->
        <?php if ($active_tab === 'paypal') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #003087; font-weight: bold; font-size: 24px;">PayPal</span>
                        <?php if ($settings['test_mode']) : ?>
                            <span style="background: #f0ad4e; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px;">SANDBOX</span>
                        <?php endif; ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable PayPal', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="paypal_enabled"
                                           value="1"
                                           <?php checked($settings['paypal']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable PayPal payment gateway', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="paypal_client_id"><?php esc_html_e('Client ID', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="paypal_client_id"
                                       name="paypal_client_id"
                                       value="<?php echo esc_attr($settings['paypal']['client_id']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="paypal_client_secret"><?php esc_html_e('Client Secret', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="paypal_client_secret"
                                       name="paypal_client_secret"
                                       value="<?php echo esc_attr($settings['paypal']['client_secret']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Webhook URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #f5f5f5;"><?php echo esc_html($paypal_webhook_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($paypal_webhook_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- WooCommerce Tab -->
        <?php if ($active_tab === 'woocommerce') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="color: #96588a; font-weight: bold; font-size: 24px;">WooCommerce</span>
                    </h2>

                    <?php if (!class_exists('WooCommerce')) : ?>
                        <div class="notice notice-warning inline" style="margin: 0 0 20px 0;">
                            <p>
                                <strong><?php esc_html_e('WooCommerce not detected!', 'formflow-pro'); ?></strong>
                                <?php esc_html_e('Please install and activate WooCommerce to use this integration.', 'formflow-pro'); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable WooCommerce', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="woocommerce_enabled"
                                           value="1"
                                           <?php checked($settings['woocommerce']['enabled'], 1); ?>
                                           <?php disabled(!class_exists('WooCommerce')); ?>>
                                    <?php esc_html_e('Use WooCommerce payment gateways', 'formflow-pro'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('When enabled, form payments will use all active WooCommerce payment methods.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php if (class_exists('WooCommerce')) : ?>
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                            <h4 style="margin-top: 0;"><?php esc_html_e('Active Payment Gateways', 'formflow-pro'); ?></h4>
                            <?php
                            $gateways = WC()->payment_gateways()->get_available_payment_gateways();
                            if (!empty($gateways)) :
                            ?>
                                <ul style="list-style: none; padding: 0; margin: 10px 0 0 0;">
                                    <?php foreach ($gateways as $gateway) : ?>
                                        <li style="padding: 8px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                            <?php echo esc_html($gateway->get_title()); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <p style="color: #666;"><?php esc_html_e('No payment gateways configured in WooCommerce.', 'formflow-pro'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Transactions Tab -->
        <?php if ($active_tab === 'transactions') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 0;">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('ID', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Submission', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Gateway', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Amount', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Date', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $transactions = $wpdb->get_results(
                                "SELECT * FROM {$wpdb->prefix}formflow_transactions ORDER BY created_at DESC LIMIT 50"
                            );

                            if (empty($transactions)) :
                            ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                                        <?php esc_html_e('No transactions yet.', 'formflow-pro'); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($transactions as $transaction) : ?>
                                    <tr>
                                        <td>#<?php echo esc_html($transaction->id); ?></td>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-submissions&action=view&id=' . $transaction->submission_id)); ?>">
                                                #<?php echo esc_html($transaction->submission_id); ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html(ucfirst($transaction->gateway)); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($transaction->currency . ' ' . number_format($transaction->amount, 2)); ?></strong>
                                        </td>
                                        <td>
                                            <?php
                                            $status_colors = [
                                                'completed' => '#46b450',
                                                'pending' => '#f0ad4e',
                                                'failed' => '#dc3545',
                                                'refunded' => '#6c757d',
                                            ];
                                            $color = $status_colors[$transaction->status] ?? '#666';
                                            ?>
                                            <span style="color: <?php echo esc_attr($color); ?>; font-weight: 500;">
                                                <?php echo esc_html(ucfirst($transaction->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab !== 'transactions') : ?>
            <p class="submit">
                <button type="submit" name="formflow_payments_submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Save Payment Settings', 'formflow-pro'); ?>
                </button>
            </p>
        <?php endif; ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.copy-url').on('click', function() {
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function() {
            alert('<?php esc_html_e('Copied to clipboard!', 'formflow-pro'); ?>');
        });
    });
});
</script>
