<?php

/**
 * Payment Form Frontend Template
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Variables passed to this template:
// $form_id - Form ID
// $amount - Payment amount
// $currency - Currency code
// $description - Payment description
// $submission_id - Related submission ID

$form_id = $form_id ?? 0;
$amount = $amount ?? 0;
$currency = $currency ?? get_option('formflow_payments_currency', 'BRL');
$description = $description ?? '';
$submission_id = $submission_id ?? 0;

// Get payment settings
$stripe_enabled = get_option('formflow_stripe_enabled', 0);
$paypal_enabled = get_option('formflow_paypal_enabled', 0);
$stripe_key = get_option('formflow_stripe_publishable_key', '');
$test_mode = get_option('formflow_payments_test_mode', 1);

// Currency symbols
$currency_symbols = [
    'BRL' => 'R$',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
];
$symbol = $currency_symbols[$currency] ?? $currency;

?>

<div class="formflow-payment-form" id="formflow-payment-<?php echo esc_attr($form_id); ?>">
    <?php if ($test_mode) : ?>
        <div class="formflow-test-mode-banner" style="background: #fff3cd; color: #856404; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px; font-size: 13px;">
            <strong><?php esc_html_e('Test Mode', 'formflow-pro'); ?></strong> — <?php esc_html_e('No real charges will be made.', 'formflow-pro'); ?>
        </div>
    <?php endif; ?>

    <!-- Order Summary -->
    <div class="formflow-order-summary" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; font-size: 16px;"><?php esc_html_e('Order Summary', 'formflow-pro'); ?></h3>

        <?php if ($description) : ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #dee2e6;">
                <span><?php echo esc_html($description); ?></span>
                <span><?php echo esc_html($symbol . ' ' . number_format($amount, 2, ',', '.')); ?></span>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 18px;">
            <span><?php esc_html_e('Total', 'formflow-pro'); ?></span>
            <span class="formflow-total-amount"><?php echo esc_html($symbol . ' ' . number_format($amount, 2, ',', '.')); ?></span>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="formflow-payment-methods">
        <h3 style="margin: 0 0 15px 0; font-size: 16px;"><?php esc_html_e('Payment Method', 'formflow-pro'); ?></h3>

        <?php if ($stripe_enabled) : ?>
            <!-- Stripe Payment -->
            <div class="formflow-payment-method" data-method="stripe" style="border: 2px solid #635bff; border-radius: 8px; padding: 20px; margin-bottom: 15px; cursor: pointer;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="payment_method" value="stripe" checked style="margin-right: 10px;">
                    <span style="display: flex; align-items: center; gap: 10px;">
                        <svg width="50" height="21" viewBox="0 0 50 21" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M50 10.5C50 7.42 48.52 4.74 46.16 3.16C43.8 1.58 40.64 1.58 38.28 3.16V0.78H34.22V20.22H38.28V17.84C40.64 19.42 43.8 19.42 46.16 17.84C48.52 16.26 50 13.58 50 10.5ZM45.94 10.5C45.94 12.88 44.04 14.78 41.66 14.78C39.28 14.78 37.38 12.88 37.38 10.5C37.38 8.12 39.28 6.22 41.66 6.22C44.04 6.22 45.94 8.12 45.94 10.5Z" fill="#635BFF"/>
                            <path d="M27.82 6.22C25.44 6.22 23.54 8.12 23.54 10.5C23.54 12.88 25.44 14.78 27.82 14.78C30.2 14.78 32.1 12.88 32.1 10.5C32.1 8.12 30.2 6.22 27.82 6.22ZM27.82 18.84C23.2 18.84 19.48 15.12 19.48 10.5C19.48 5.88 23.2 2.16 27.82 2.16C32.44 2.16 36.16 5.88 36.16 10.5C36.16 15.12 32.44 18.84 27.82 18.84Z" fill="#635BFF"/>
                            <path d="M14.28 6.22V2.16H10.22V20.22H14.28V10.5C14.28 8.12 16.18 6.22 18.56 6.22H19.48V2.16H18.56C16.88 2.16 15.32 2.98 14.28 4.26V6.22Z" fill="#635BFF"/>
                            <path d="M0 10.5C0 5.88 3.72 2.16 8.34 2.16V6.22C5.96 6.22 4.06 8.12 4.06 10.5C4.06 12.88 5.96 14.78 8.34 14.78V18.84C3.72 18.84 0 15.12 0 10.5Z" fill="#635BFF"/>
                        </svg>
                        <span style="font-weight: 500;"><?php esc_html_e('Credit Card', 'formflow-pro'); ?></span>
                    </span>
                </label>

                <div class="stripe-card-element" style="margin-top: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                    <div id="card-element-<?php echo esc_attr($form_id); ?>">
                        <!-- Stripe Card Element will be mounted here -->
                    </div>
                    <div id="card-errors-<?php echo esc_attr($form_id); ?>" role="alert" style="color: #dc3545; font-size: 13px; margin-top: 10px;"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($paypal_enabled) : ?>
            <!-- PayPal Payment -->
            <div class="formflow-payment-method" data-method="paypal" style="border: 2px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 15px; cursor: pointer;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                    <input type="radio" name="payment_method" value="paypal" style="margin-right: 10px;">
                    <span style="display: flex; align-items: center; gap: 10px;">
                        <svg width="80" height="20" viewBox="0 0 80 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M28.7 4.2H24.4C24.1 4.2 23.9 4.4 23.9 4.7L22.2 14.8C22.2 15 22.3 15.2 22.5 15.2H24.5C24.8 15.2 25 15 25 14.7L25.5 11.8C25.5 11.5 25.8 11.3 26.1 11.3H27.4C30.1 11.3 31.7 10 32.1 7.4C32.3 6.2 32.1 5.3 31.6 4.6C31 3.9 30 4.2 28.7 4.2Z" fill="#003087"/>
                            <path d="M39.6 11.3C39.4 12.5 38.4 13.3 37.2 13.3C36.6 13.3 36.1 13.1 35.8 12.7C35.5 12.3 35.4 11.8 35.5 11.2C35.6 10 36.7 9.2 37.9 9.2C38.5 9.2 39 9.4 39.3 9.8C39.6 10.2 39.7 10.7 39.6 11.3ZM42.4 7.4H40.4C40.2 7.4 40 7.5 40 7.7L39.9 8.2L39.8 8C39.4 7.4 38.4 7.2 37.5 7.2C35.2 7.2 33.3 8.9 32.9 11.2C32.7 12.3 32.9 13.4 33.5 14.2C34.1 14.9 34.9 15.2 35.9 15.2C37.5 15.2 38.4 14.3 38.4 14.3L38.3 14.8C38.3 15 38.4 15.2 38.6 15.2H40.4C40.7 15.2 40.9 15 40.9 14.7L42 7.8C42 7.6 41.9 7.4 42.4 7.4Z" fill="#003087"/>
                            <path d="M52.4 7.4H50.3C50.1 7.4 49.9 7.5 49.8 7.7L47.4 11.2L46.4 7.9C46.3 7.6 46.1 7.4 45.8 7.4H43.9C43.7 7.4 43.5 7.6 43.6 7.9L45.4 14.5L43.6 17C43.5 17.2 43.6 17.5 43.9 17.5H45.9C46.1 17.5 46.3 17.4 46.4 17.2L52.7 7.9C52.9 7.6 52.7 7.4 52.4 7.4Z" fill="#003087"/>
                            <path d="M60.1 4.2H55.8C55.5 4.2 55.3 4.4 55.3 4.7L53.6 14.8C53.6 15 53.7 15.2 53.9 15.2H56.1C56.3 15.2 56.4 15 56.5 14.9L57 11.8C57 11.5 57.3 11.3 57.6 11.3H58.9C61.6 11.3 63.2 10 63.6 7.4C63.8 6.2 63.6 5.3 63.1 4.6C62.4 3.9 61.4 4.2 60.1 4.2Z" fill="#0070BA"/>
                            <path d="M70.9 11.3C70.7 12.5 69.7 13.3 68.5 13.3C67.9 13.3 67.4 13.1 67.1 12.7C66.8 12.3 66.7 11.8 66.8 11.2C66.9 10 68 9.2 69.2 9.2C69.8 9.2 70.3 9.4 70.6 9.8C70.9 10.2 71 10.7 70.9 11.3ZM73.7 7.4H71.7C71.5 7.4 71.3 7.5 71.3 7.7L71.2 8.2L71.1 8C70.7 7.4 69.7 7.2 68.8 7.2C66.5 7.2 64.6 8.9 64.2 11.2C64 12.3 64.2 13.4 64.8 14.2C65.4 14.9 66.2 15.2 67.2 15.2C68.8 15.2 69.7 14.3 69.7 14.3L69.6 14.8C69.6 15 69.7 15.2 69.9 15.2H71.7C72 15.2 72.2 15 72.2 14.7L73.3 7.8C73.3 7.6 73.2 7.4 73.7 7.4Z" fill="#0070BA"/>
                            <path d="M76.2 4.5L74.5 14.8C74.5 15 74.6 15.2 74.8 15.2H76.5C76.8 15.2 77 15 77 14.7L78.7 4.6C78.7 4.4 78.6 4.2 78.4 4.2H76.5C76.3 4.2 76.2 4.3 76.2 4.5Z" fill="#0070BA"/>
                        </svg>
                        <span style="font-weight: 500;">PayPal</span>
                    </span>
                </label>

                <div class="paypal-buttons" id="paypal-buttons-<?php echo esc_attr($form_id); ?>" style="margin-top: 15px; display: none;">
                    <!-- PayPal buttons will be rendered here -->
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Submit Button -->
    <button type="button" id="formflow-pay-btn-<?php echo esc_attr($form_id); ?>" class="formflow-pay-button" style="width: 100%; padding: 15px; background: linear-gradient(135deg, #27ae60, #2ecc71); color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 20px;">
        <?php printf(esc_html__('Pay %s', 'formflow-pro'), $symbol . ' ' . number_format($amount, 2, ',', '.')); ?>
    </button>

    <!-- Hidden Form Data -->
    <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
    <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission_id); ?>">
    <input type="hidden" name="amount" value="<?php echo esc_attr($amount); ?>">
    <input type="hidden" name="currency" value="<?php echo esc_attr($currency); ?>">
    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('formflow_payment'); ?>">

    <!-- Processing Overlay -->
    <div class="formflow-processing-overlay" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.9); z-index: 100; justify-content: center; align-items: center; flex-direction: column;">
        <div class="formflow-spinner" style="width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #27ae60; border-radius: 50%; animation: formflow-spin 1s linear infinite;"></div>
        <p style="margin-top: 15px; color: #666;"><?php esc_html_e('Processing payment...', 'formflow-pro'); ?></p>
    </div>
</div>

<style>
@keyframes formflow-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.formflow-payment-form {
    position: relative;
    max-width: 500px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.formflow-payment-method {
    transition: border-color 0.2s ease;
}
.formflow-payment-method:hover,
.formflow-payment-method.selected {
    border-color: #635bff !important;
}
.formflow-pay-button:hover {
    opacity: 0.9;
}
.formflow-pay-button:disabled {
    background: #ccc;
    cursor: not-allowed;
}
</style>

<?php if ($stripe_enabled && $stripe_key) : ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    var stripe = Stripe('<?php echo esc_js($stripe_key); ?>');
    var elements = stripe.elements();
    var card = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                '::placeholder': { color: '#aab7c4' }
            }
        }
    });
    card.mount('#card-element-<?php echo esc_js($form_id); ?>');

    card.on('change', function(event) {
        var displayError = document.getElementById('card-errors-<?php echo esc_js($form_id); ?>');
        displayError.textContent = event.error ? event.error.message : '';
    });

    // Payment method selection
    document.querySelectorAll('.formflow-payment-method').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.formflow-payment-method').forEach(function(m) {
                m.style.borderColor = '#ddd';
                m.classList.remove('selected');
            });
            this.style.borderColor = '#635bff';
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
})();
</script>
<?php endif; ?>
