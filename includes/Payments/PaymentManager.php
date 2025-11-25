<?php
/**
 * FormFlow Pro - Payment Manager
 *
 * Central payment hub managing all payment providers, subscriptions,
 * invoices, and payment form integration.
 *
 * @package FormFlowPro
 * @subpackage Payments
 * @since 2.4.0
 */

namespace FormFlowPro\Payments;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Record Model
 */
class PaymentRecord
{
    public int $id;
    public int $form_id;
    public int $submission_id;
    public string $provider;
    public string $provider_payment_id;
    public string $provider_customer_id;
    public string $status;
    public float $amount;
    public string $currency;
    public ?float $refunded_amount;
    public array $metadata;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * Subscription Record Model
 */
class SubscriptionRecord
{
    public int $id;
    public int $form_id;
    public int $user_id;
    public string $provider;
    public string $provider_subscription_id;
    public string $provider_customer_id;
    public string $plan_id;
    public string $status;
    public float $amount;
    public string $currency;
    public string $interval;
    public int $interval_count;
    public ?string $trial_end;
    public ?string $current_period_start;
    public ?string $current_period_end;
    public ?string $canceled_at;
    public array $metadata;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * Payment Manager
 */
class PaymentManager
{
    use SingletonTrait;

    private string $payments_table;
    private string $subscriptions_table;
    private string $invoices_table;
    private array $providers = [];

    protected function init(): void
    {
        global $wpdb;

        $this->payments_table = $wpdb->prefix . 'ffp_payments';
        $this->subscriptions_table = $wpdb->prefix . 'ffp_subscriptions';
        $this->invoices_table = $wpdb->prefix . 'ffp_invoices';

        $this->createTables();
        $this->initializeProviders();
        $this->registerHooks();
    }

    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->payments_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            submission_id BIGINT UNSIGNED DEFAULT 0,
            user_id BIGINT UNSIGNED DEFAULT 0,
            provider VARCHAR(50) NOT NULL,
            provider_payment_id VARCHAR(255) NOT NULL,
            provider_customer_id VARCHAR(255) DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            refunded_amount DECIMAL(10,2) DEFAULT 0,
            metadata LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY provider (provider),
            KEY provider_payment_id (provider_payment_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->subscriptions_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT 0,
            provider VARCHAR(50) NOT NULL,
            provider_subscription_id VARCHAR(255) NOT NULL,
            provider_customer_id VARCHAR(255) NOT NULL,
            plan_id VARCHAR(255) DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            interval_type VARCHAR(20) NOT NULL DEFAULT 'month',
            interval_count INT UNSIGNED DEFAULT 1,
            trial_end DATETIME DEFAULT NULL,
            current_period_start DATETIME DEFAULT NULL,
            current_period_end DATETIME DEFAULT NULL,
            canceled_at DATETIME DEFAULT NULL,
            metadata LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY user_id (user_id),
            KEY provider (provider),
            KEY provider_subscription_id (provider_subscription_id),
            KEY status (status)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->invoices_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_number VARCHAR(50) NOT NULL,
            form_id BIGINT UNSIGNED DEFAULT 0,
            payment_id BIGINT UNSIGNED DEFAULT 0,
            subscription_id BIGINT UNSIGNED DEFAULT 0,
            user_id BIGINT UNSIGNED DEFAULT 0,
            provider VARCHAR(50) DEFAULT '',
            provider_invoice_id VARCHAR(255) DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'draft',
            customer_name VARCHAR(255) DEFAULT '',
            customer_email VARCHAR(255) DEFAULT '',
            customer_address TEXT,
            items LONGTEXT,
            subtotal DECIMAL(10,2) DEFAULT 0,
            tax DECIMAL(10,2) DEFAULT 0,
            discount DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            due_date DATE DEFAULT NULL,
            paid_date DATE DEFAULT NULL,
            notes TEXT,
            metadata LONGTEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY form_id (form_id),
            KEY status (status),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function initializeProviders(): void
    {
        $this->providers['stripe'] = new StripeProvider();
        $this->providers['paypal'] = new PayPalProvider();
    }

    private function registerHooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Webhooks
        add_action('wp_ajax_nopriv_ffp_stripe_webhook', [$this, 'handleStripeWebhook']);
        add_action('wp_ajax_ffp_stripe_webhook', [$this, 'handleStripeWebhook']);
        add_action('wp_ajax_nopriv_ffp_paypal_webhook', [$this, 'handlePayPalWebhook']);
        add_action('wp_ajax_ffp_paypal_webhook', [$this, 'handlePayPalWebhook']);

        // AJAX
        add_action('wp_ajax_ffp_create_payment_intent', [$this, 'ajaxCreatePaymentIntent']);
        add_action('wp_ajax_nopriv_ffp_create_payment_intent', [$this, 'ajaxCreatePaymentIntent']);
        add_action('wp_ajax_ffp_confirm_payment', [$this, 'ajaxConfirmPayment']);
        add_action('wp_ajax_nopriv_ffp_confirm_payment', [$this, 'ajaxConfirmPayment']);

        // Form integration
        add_action('ffp_form_submission', [$this, 'handleFormPayment'], 10, 2);

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Shortcodes
        add_shortcode('ffp_payment_form', [$this, 'renderPaymentFormShortcode']);
        add_shortcode('ffp_subscription_manage', [$this, 'renderSubscriptionManageShortcode']);
    }

    /**
     * Get payment provider
     */
    public function getProvider(string $name): ?PaymentProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Get available providers
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->providers as $name => $provider) {
            if ($provider->isConfigured()) {
                $available[$name] = $provider;
            }
        }

        return $available;
    }

    /**
     * Create payment
     */
    public function createPayment(string $provider, array $data): array
    {
        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            return ['success' => false, 'error' => 'Invalid payment provider'];
        }

        if (!$payment_provider->isConfigured()) {
            return ['success' => false, 'error' => 'Payment provider not configured'];
        }

        // Create payment with provider
        $result = $payment_provider->createPayment($data);

        if (!$result['success']) {
            return $result;
        }

        // Store payment record
        $payment_id = $this->storePayment([
            'form_id' => $data['form_id'] ?? 0,
            'submission_id' => $data['submission_id'] ?? 0,
            'user_id' => get_current_user_id(),
            'provider' => $provider,
            'provider_payment_id' => $result['payment_id'],
            'provider_customer_id' => $data['customer_id'] ?? '',
            'status' => $result['status'] ?? 'pending',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'metadata' => $data['metadata'] ?? [],
        ]);

        $result['local_payment_id'] = $payment_id;

        return $result;
    }

    /**
     * Capture payment
     */
    public function capturePayment(string $provider, string $payment_id): array
    {
        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            return ['success' => false, 'error' => 'Invalid payment provider'];
        }

        $result = $payment_provider->capturePayment($payment_id);

        if ($result['success']) {
            $this->updatePaymentByProviderId($provider, $payment_id, [
                'status' => 'captured',
            ]);
        }

        return $result;
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $provider, string $payment_id, float $amount = null): array
    {
        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            return ['success' => false, 'error' => 'Invalid payment provider'];
        }

        $result = $payment_provider->refundPayment($payment_id, $amount);

        if ($result['success']) {
            global $wpdb;

            // Update refunded amount
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->payments_table}
                 SET refunded_amount = refunded_amount + %f, status = 'refunded', updated_at = NOW()
                 WHERE provider = %s AND provider_payment_id = %s",
                $result['amount'] ?? $amount,
                $provider,
                $payment_id
            ));
        }

        return $result;
    }

    /**
     * Create subscription
     */
    public function createSubscription(string $provider, array $data): array
    {
        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            return ['success' => false, 'error' => 'Invalid payment provider'];
        }

        if (!$payment_provider->isConfigured()) {
            return ['success' => false, 'error' => 'Payment provider not configured'];
        }

        $result = $payment_provider->createSubscription($data);

        if (!$result['success']) {
            return $result;
        }

        // Store subscription record
        $subscription_id = $this->storeSubscription([
            'form_id' => $data['form_id'] ?? 0,
            'user_id' => get_current_user_id(),
            'provider' => $provider,
            'provider_subscription_id' => $result['subscription_id'],
            'provider_customer_id' => $data['customer_id'] ?? '',
            'plan_id' => $data['plan_id'] ?? $data['items'][0]['price_id'] ?? '',
            'status' => $result['status'] ?? 'pending',
            'amount' => $data['amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'interval_type' => $data['interval'] ?? 'month',
            'interval_count' => $data['interval_count'] ?? 1,
            'trial_end' => $result['trial_end'] ?? null,
            'current_period_start' => isset($result['current_period_start'])
                ? date('Y-m-d H:i:s', $result['current_period_start'])
                : null,
            'current_period_end' => isset($result['current_period_end'])
                ? date('Y-m-d H:i:s', $result['current_period_end'])
                : null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        $result['local_subscription_id'] = $subscription_id;

        do_action('ffp_subscription_created', $subscription_id, $provider, $result);

        return $result;
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $provider, string $subscription_id): array
    {
        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            return ['success' => false, 'error' => 'Invalid payment provider'];
        }

        $result = $payment_provider->cancelSubscription($subscription_id);

        if ($result['success']) {
            $this->updateSubscriptionByProviderId($provider, $subscription_id, [
                'status' => 'canceled',
                'canceled_at' => current_time('mysql'),
            ]);

            do_action('ffp_subscription_canceled', $subscription_id, $provider);
        }

        return $result;
    }

    /**
     * Store payment record
     */
    private function storePayment(array $data): int
    {
        global $wpdb;

        $wpdb->insert($this->payments_table, [
            'form_id' => $data['form_id'],
            'submission_id' => $data['submission_id'],
            'user_id' => $data['user_id'],
            'provider' => $data['provider'],
            'provider_payment_id' => $data['provider_payment_id'],
            'provider_customer_id' => $data['provider_customer_id'] ?? '',
            'status' => $data['status'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Store subscription record
     */
    private function storeSubscription(array $data): int
    {
        global $wpdb;

        $wpdb->insert($this->subscriptions_table, [
            'form_id' => $data['form_id'],
            'user_id' => $data['user_id'],
            'provider' => $data['provider'],
            'provider_subscription_id' => $data['provider_subscription_id'],
            'provider_customer_id' => $data['provider_customer_id'] ?? '',
            'plan_id' => $data['plan_id'] ?? '',
            'status' => $data['status'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'interval_type' => $data['interval_type'],
            'interval_count' => $data['interval_count'],
            'trial_end' => $data['trial_end'],
            'current_period_start' => $data['current_period_start'],
            'current_period_end' => $data['current_period_end'],
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Update payment by provider ID
     */
    private function updatePaymentByProviderId(string $provider, string $provider_payment_id, array $data): void
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $wpdb->update(
            $this->payments_table,
            $data,
            [
                'provider' => $provider,
                'provider_payment_id' => $provider_payment_id,
            ]
        );
    }

    /**
     * Update subscription by provider ID
     */
    private function updateSubscriptionByProviderId(string $provider, string $provider_subscription_id, array $data): void
    {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $wpdb->update(
            $this->subscriptions_table,
            $data,
            [
                'provider' => $provider,
                'provider_subscription_id' => $provider_subscription_id,
            ]
        );
    }

    /**
     * Get payment by ID
     */
    public function getPayment(int $id): ?PaymentRecord
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->payments_table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToPayment($row);
    }

    /**
     * Get payments
     */
    public function getPayments(array $args = []): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->payments_table} WHERE 1=1";
        $params = [];

        if (!empty($args['form_id'])) {
            $sql .= " AND form_id = %d";
            $params[] = $args['form_id'];
        }

        if (!empty($args['user_id'])) {
            $sql .= " AND user_id = %d";
            $params[] = $args['user_id'];
        }

        if (!empty($args['provider'])) {
            $sql .= " AND provider = %s";
            $params[] = $args['provider'];
        }

        if (!empty($args['status'])) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($args['limit'])) {
            $sql .= " LIMIT %d";
            $params[] = $args['limit'];

            if (!empty($args['offset'])) {
                $sql .= " OFFSET %d";
                $params[] = $args['offset'];
            }
        }

        $rows = $wpdb->get_results(
            !empty($params) ? $wpdb->prepare($sql, ...$params) : $sql,
            ARRAY_A
        );

        return array_map([$this, 'rowToPayment'], $rows);
    }

    /**
     * Get subscription by ID
     */
    public function getSubscription(int $id): ?SubscriptionRecord
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->subscriptions_table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToSubscription($row);
    }

    /**
     * Get user subscriptions
     */
    public function getUserSubscriptions(int $user_id, string $status = null): array
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->subscriptions_table} WHERE user_id = %d";
        $params = [$user_id];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map([$this, 'rowToSubscription'], $rows);
    }

    /**
     * Convert row to PaymentRecord
     */
    private function rowToPayment(array $row): PaymentRecord
    {
        return new PaymentRecord([
            'id' => (int) $row['id'],
            'form_id' => (int) $row['form_id'],
            'submission_id' => (int) $row['submission_id'],
            'provider' => $row['provider'],
            'provider_payment_id' => $row['provider_payment_id'],
            'provider_customer_id' => $row['provider_customer_id'],
            'status' => $row['status'],
            'amount' => (float) $row['amount'],
            'currency' => $row['currency'],
            'refunded_amount' => (float) $row['refunded_amount'],
            'metadata' => json_decode($row['metadata'] ?? '{}', true) ?? [],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ]);
    }

    /**
     * Convert row to SubscriptionRecord
     */
    private function rowToSubscription(array $row): SubscriptionRecord
    {
        return new SubscriptionRecord([
            'id' => (int) $row['id'],
            'form_id' => (int) $row['form_id'],
            'user_id' => (int) $row['user_id'],
            'provider' => $row['provider'],
            'provider_subscription_id' => $row['provider_subscription_id'],
            'provider_customer_id' => $row['provider_customer_id'],
            'plan_id' => $row['plan_id'],
            'status' => $row['status'],
            'amount' => (float) $row['amount'],
            'currency' => $row['currency'],
            'interval' => $row['interval_type'],
            'interval_count' => (int) $row['interval_count'],
            'trial_end' => $row['trial_end'],
            'current_period_start' => $row['current_period_start'],
            'current_period_end' => $row['current_period_end'],
            'canceled_at' => $row['canceled_at'],
            'metadata' => json_decode($row['metadata'] ?? '{}', true) ?? [],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ]);
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $data): int
    {
        global $wpdb;

        // Generate invoice number
        $invoice_number = $this->generateInvoiceNumber();

        // Calculate totals
        $subtotal = 0;
        $items = $data['items'] ?? [];

        foreach ($items as $item) {
            $subtotal += ($item['quantity'] ?? 1) * ($item['price'] ?? 0);
        }

        $tax = $data['tax'] ?? 0;
        $discount = $data['discount'] ?? 0;
        $total = $subtotal + $tax - $discount;

        $wpdb->insert($this->invoices_table, [
            'invoice_number' => $invoice_number,
            'form_id' => $data['form_id'] ?? 0,
            'payment_id' => $data['payment_id'] ?? 0,
            'subscription_id' => $data['subscription_id'] ?? 0,
            'user_id' => $data['user_id'] ?? get_current_user_id(),
            'provider' => $data['provider'] ?? '',
            'provider_invoice_id' => $data['provider_invoice_id'] ?? '',
            'status' => $data['status'] ?? 'draft',
            'customer_name' => $data['customer_name'] ?? '',
            'customer_email' => $data['customer_email'] ?? '',
            'customer_address' => $data['customer_address'] ?? '',
            'items' => json_encode($items),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'currency' => $data['currency'] ?? 'USD',
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? '',
            'metadata' => json_encode($data['metadata'] ?? []),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Generate invoice number
     */
    private function generateInvoiceNumber(): string
    {
        global $wpdb;

        $prefix = get_option('ffp_invoice_prefix', 'INV-');
        $year = date('Y');

        $last_number = $wpdb->get_var($wpdb->prepare(
            "SELECT invoice_number FROM {$this->invoices_table}
             WHERE invoice_number LIKE %s
             ORDER BY id DESC LIMIT 1",
            $prefix . $year . '%'
        ));

        if ($last_number) {
            $number = (int) substr($last_number, -5) + 1;
        } else {
            $number = 1;
        }

        return $prefix . $year . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get invoice
     */
    public function getInvoice(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->invoices_table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['items'] = json_decode($row['items'], true) ?? [];
        $row['metadata'] = json_decode($row['metadata'], true) ?? [];

        return $row;
    }

    /**
     * Get invoice by number
     */
    public function getInvoiceByNumber(string $invoice_number): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->invoices_table} WHERE invoice_number = %s",
            $invoice_number
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['items'] = json_decode($row['items'], true) ?? [];
        $row['metadata'] = json_decode($row['metadata'], true) ?? [];

        return $row;
    }

    /**
     * Update invoice status
     */
    public function updateInvoiceStatus(int $id, string $status): bool
    {
        global $wpdb;

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ($status === 'paid') {
            $data['paid_date'] = current_time('Y-m-d');
        }

        return $wpdb->update($this->invoices_table, $data, ['id' => $id]) !== false;
    }

    /**
     * Generate invoice PDF
     */
    public function generateInvoicePDF(int $invoice_id): string
    {
        $invoice = $this->getInvoice($invoice_id);

        if (!$invoice) {
            return '';
        }

        // Simple HTML to PDF (would use a library like TCPDF or DOMPDF in production)
        $html = $this->buildInvoiceHTML($invoice);

        // For now, return HTML that can be printed/saved as PDF
        return $html;
    }

    /**
     * Build invoice HTML
     */
    private function buildInvoiceHTML(array $invoice): string
    {
        $currency_symbol = $this->getCurrencySymbol($invoice['currency']);

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice ' . esc_html($invoice['invoice_number']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
                .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
                .invoice-title { font-size: 32px; color: #2563eb; }
                .invoice-number { font-size: 14px; color: #666; }
                .company-info, .customer-info { margin-bottom: 30px; }
                .info-label { font-weight: bold; color: #666; font-size: 12px; text-transform: uppercase; }
                table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                th { background: #f8fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; }
                td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
                .text-right { text-align: right; }
                .totals { margin-top: 30px; }
                .totals table { width: 300px; margin-left: auto; }
                .totals td { border: none; padding: 8px 12px; }
                .total-row { font-weight: bold; font-size: 18px; background: #f8fafc; }
                .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #666; font-size: 12px; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; text-transform: uppercase; }
                .status-paid { background: #dcfce7; color: #166534; }
                .status-pending { background: #fef9c3; color: #854d0e; }
                .status-draft { background: #f1f5f9; color: #475569; }
            </style>
        </head>
        <body>
            <div class="header">
                <div>
                    <div class="invoice-title">INVOICE</div>
                    <div class="invoice-number">' . esc_html($invoice['invoice_number']) . '</div>
                </div>
                <div class="text-right">
                    <span class="status-badge status-' . esc_attr($invoice['status']) . '">' . esc_html(ucfirst($invoice['status'])) . '</span>
                </div>
            </div>

            <div class="company-info">
                <div class="info-label">From</div>
                <div><strong>' . esc_html(get_bloginfo('name')) . '</strong></div>
            </div>

            <div class="customer-info">
                <div class="info-label">Bill To</div>
                <div><strong>' . esc_html($invoice['customer_name']) . '</strong></div>
                <div>' . esc_html($invoice['customer_email']) . '</div>
                <div>' . nl2br(esc_html($invoice['customer_address'])) . '</div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($invoice['items'] as $item) {
            $amount = ($item['quantity'] ?? 1) * ($item['price'] ?? 0);
            $html .= '<tr>
                <td>' . esc_html($item['name'] ?? $item['description'] ?? '') . '</td>
                <td class="text-right">' . esc_html($item['quantity'] ?? 1) . '</td>
                <td class="text-right">' . $currency_symbol . number_format($item['price'] ?? 0, 2) . '</td>
                <td class="text-right">' . $currency_symbol . number_format($amount, 2) . '</td>
            </tr>';
        }

        $html .= '</tbody>
            </table>

            <div class="totals">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td class="text-right">' . $currency_symbol . number_format($invoice['subtotal'], 2) . '</td>
                    </tr>';

        if ($invoice['tax'] > 0) {
            $html .= '<tr>
                <td>Tax</td>
                <td class="text-right">' . $currency_symbol . number_format($invoice['tax'], 2) . '</td>
            </tr>';
        }

        if ($invoice['discount'] > 0) {
            $html .= '<tr>
                <td>Discount</td>
                <td class="text-right">-' . $currency_symbol . number_format($invoice['discount'], 2) . '</td>
            </tr>';
        }

        $html .= '<tr class="total-row">
                        <td>Total</td>
                        <td class="text-right">' . $currency_symbol . number_format($invoice['total'], 2) . '</td>
                    </tr>
                </table>
            </div>';

        if (!empty($invoice['notes'])) {
            $html .= '<div class="footer">
                <div class="info-label">Notes</div>
                <div>' . nl2br(esc_html($invoice['notes'])) . '</div>
            </div>';
        }

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get currency symbol
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
            'BRL' => 'R$', 'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF',
        ];

        return $symbols[strtoupper($currency)] ?? $currency . ' ';
    }

    /**
     * Handle Stripe webhook
     */
    public function handleStripeWebhook(): void
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $stripe = $this->getProvider('stripe');
        $result = $stripe->handleWebhook($payload, $signature);

        if (!$result['success']) {
            wp_send_json_error($result, 400);
        }

        $this->processWebhookEvent('stripe', $result);

        wp_send_json_success(['received' => true]);
    }

    /**
     * Handle PayPal webhook
     */
    public function handlePayPalWebhook(): void
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '';

        $paypal = $this->getProvider('paypal');
        $result = $paypal->handleWebhook($payload, $signature);

        if (!$result['success']) {
            wp_send_json_error($result, 400);
        }

        $this->processWebhookEvent('paypal', $result);

        wp_send_json_success(['received' => true]);
    }

    /**
     * Process webhook event
     */
    private function processWebhookEvent(string $provider, array $event): void
    {
        $event_type = $event['event_type'];
        $data = $event['data'];

        // Map common event types
        $event_handlers = [
            // Stripe events
            'payment_intent.succeeded' => 'handlePaymentSucceeded',
            'payment_intent.payment_failed' => 'handlePaymentFailed',
            'customer.subscription.created' => 'handleSubscriptionCreated',
            'customer.subscription.updated' => 'handleSubscriptionUpdated',
            'customer.subscription.deleted' => 'handleSubscriptionDeleted',
            'invoice.paid' => 'handleInvoicePaid',
            'invoice.payment_failed' => 'handleInvoicePaymentFailed',

            // PayPal events
            'CHECKOUT.ORDER.APPROVED' => 'handlePaymentSucceeded',
            'PAYMENT.CAPTURE.COMPLETED' => 'handlePaymentSucceeded',
            'PAYMENT.CAPTURE.DENIED' => 'handlePaymentFailed',
            'BILLING.SUBSCRIPTION.ACTIVATED' => 'handleSubscriptionCreated',
            'BILLING.SUBSCRIPTION.UPDATED' => 'handleSubscriptionUpdated',
            'BILLING.SUBSCRIPTION.CANCELLED' => 'handleSubscriptionDeleted',
        ];

        if (isset($event_handlers[$event_type])) {
            $handler = $event_handlers[$event_type];
            $this->$handler($provider, $data);
        }

        do_action('ffp_webhook_received', $provider, $event_type, $data);
    }

    private function handlePaymentSucceeded(string $provider, array $data): void
    {
        $payment_id = $data['id'] ?? '';

        $this->updatePaymentByProviderId($provider, $payment_id, [
            'status' => 'succeeded',
        ]);

        do_action('ffp_payment_succeeded', $provider, $payment_id, $data);
    }

    private function handlePaymentFailed(string $provider, array $data): void
    {
        $payment_id = $data['id'] ?? '';

        $this->updatePaymentByProviderId($provider, $payment_id, [
            'status' => 'failed',
        ]);

        do_action('ffp_payment_failed', $provider, $payment_id, $data);
    }

    private function handleSubscriptionCreated(string $provider, array $data): void
    {
        do_action('ffp_subscription_webhook_created', $provider, $data);
    }

    private function handleSubscriptionUpdated(string $provider, array $data): void
    {
        $subscription_id = $data['id'] ?? '';
        $status = $data['status'] ?? '';

        $update_data = ['status' => $status];

        if (isset($data['current_period_start'])) {
            $update_data['current_period_start'] = date('Y-m-d H:i:s', $data['current_period_start']);
        }

        if (isset($data['current_period_end'])) {
            $update_data['current_period_end'] = date('Y-m-d H:i:s', $data['current_period_end']);
        }

        $this->updateSubscriptionByProviderId($provider, $subscription_id, $update_data);

        do_action('ffp_subscription_webhook_updated', $provider, $subscription_id, $data);
    }

    private function handleSubscriptionDeleted(string $provider, array $data): void
    {
        $subscription_id = $data['id'] ?? '';

        $this->updateSubscriptionByProviderId($provider, $subscription_id, [
            'status' => 'canceled',
            'canceled_at' => current_time('mysql'),
        ]);

        do_action('ffp_subscription_webhook_canceled', $provider, $subscription_id, $data);
    }

    private function handleInvoicePaid(string $provider, array $data): void
    {
        do_action('ffp_invoice_paid', $provider, $data);
    }

    private function handleInvoicePaymentFailed(string $provider, array $data): void
    {
        do_action('ffp_invoice_payment_failed', $provider, $data);
    }

    /**
     * AJAX create payment intent
     */
    public function ajaxCreatePaymentIntent(): void
    {
        check_ajax_referer('ffp_payment_nonce', 'nonce');

        $provider = sanitize_text_field($_POST['provider'] ?? 'stripe');
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = sanitize_text_field($_POST['currency'] ?? 'USD');
        $form_id = (int) ($_POST['form_id'] ?? 0);

        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
        }

        $result = $this->createPayment($provider, [
            'amount' => $amount,
            'currency' => $currency,
            'form_id' => $form_id,
            'metadata' => [
                'form_id' => $form_id,
            ],
        ]);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX confirm payment
     */
    public function ajaxConfirmPayment(): void
    {
        check_ajax_referer('ffp_payment_nonce', 'nonce');

        $provider = sanitize_text_field($_POST['provider'] ?? 'stripe');
        $payment_id = sanitize_text_field($_POST['payment_id'] ?? '');

        if (empty($payment_id)) {
            wp_send_json_error(['message' => 'Payment ID required']);
        }

        $payment_provider = $this->getProvider($provider);

        if (!$payment_provider) {
            wp_send_json_error(['message' => 'Invalid provider']);
        }

        $result = $payment_provider->getPayment($payment_id);

        if ($result['success'] && $result['status'] === 'succeeded') {
            $this->updatePaymentByProviderId($provider, $payment_id, [
                'status' => 'succeeded',
            ]);

            do_action('ffp_payment_confirmed', $payment_id, $provider);
        }

        wp_send_json_success($result);
    }

    /**
     * Handle form payment
     */
    public function handleFormPayment(int $form_id, array $submission_data): void
    {
        $form = \FormFlowPro\FormBuilder\DragDropBuilder::getInstance()->getForm($form_id);

        if (!$form) {
            return;
        }

        $payment_settings = $form->settings['payment'] ?? [];

        if (empty($payment_settings['enabled'])) {
            return;
        }

        // Payment already handled by frontend
        if (!empty($submission_data['payment_intent_id'])) {
            return;
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        // Check if Stripe is configured
        $stripe = $this->getProvider('stripe');
        if ($stripe && $stripe->isConfigured()) {
            wp_register_script(
                'stripe-js',
                'https://js.stripe.com/v3/',
                [],
                null,
                true
            );
        }

        // Check if PayPal is configured
        $paypal = $this->getProvider('paypal');
        if ($paypal && $paypal->isConfigured()) {
            $client_id = $paypal->getClientId();
            wp_register_script(
                'paypal-js',
                "https://www.paypal.com/sdk/js?client-id={$client_id}&currency=USD",
                [],
                null,
                true
            );
        }

        // Payment form script
        wp_register_script(
            'ffp-payments',
            plugins_url('assets/js/payments.js', dirname(__DIR__)),
            ['jquery'],
            JEFORM_VERSION,
            true
        );

        wp_localize_script('ffp-payments', 'ffpPayments', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ffp_payment_nonce'),
            'stripe' => $stripe && $stripe->isConfigured() ? [
                'publishableKey' => $stripe->getPublishableKey(),
            ] : null,
            'paypal' => $paypal && $paypal->isConfigured() ? [
                'clientId' => $paypal->getClientId(),
                'environment' => $paypal->getEnvironment(),
            ] : null,
        ]);
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Payments', 'form-flow-pro'),
            __('Payments', 'form-flow-pro'),
            'manage_options',
            'formflow-pro-payments',
            [$this, 'renderPaymentsPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Subscriptions', 'form-flow-pro'),
            __('Subscriptions', 'form-flow-pro'),
            'manage_options',
            'formflow-pro-subscriptions',
            [$this, 'renderSubscriptionsPage']
        );

        add_submenu_page(
            'formflow-pro',
            __('Invoices', 'form-flow-pro'),
            __('Invoices', 'form-flow-pro'),
            'manage_options',
            'formflow-pro-invoices',
            [$this, 'renderInvoicesPage']
        );
    }

    /**
     * Render payments page
     */
    public function renderPaymentsPage(): void
    {
        $payments = $this->getPayments(['limit' => 50]);
        include JEFORM_PLUGIN_DIR . 'templates/admin/payments.php';
    }

    /**
     * Render subscriptions page
     */
    public function renderSubscriptionsPage(): void
    {
        include JEFORM_PLUGIN_DIR . 'templates/admin/subscriptions.php';
    }

    /**
     * Render invoices page
     */
    public function renderInvoicesPage(): void
    {
        include JEFORM_PLUGIN_DIR . 'templates/admin/invoices.php';
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('form-flow-pro/v1', '/payments', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPayments'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/payments/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPayment'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/payments/(?P<id>\d+)/refund', [
            'methods' => 'POST',
            'callback' => [$this, 'restRefundPayment'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/subscriptions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSubscriptions'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/subscriptions/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'restCancelSubscription'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/invoices', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetInvoices'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateInvoice'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ],
        ]);
    }

    public function restGetPayments(\WP_REST_Request $request): \WP_REST_Response
    {
        $payments = $this->getPayments([
            'form_id' => $request->get_param('form_id'),
            'status' => $request->get_param('status'),
            'limit' => $request->get_param('per_page') ?? 20,
            'offset' => (($request->get_param('page') ?? 1) - 1) * ($request->get_param('per_page') ?? 20),
        ]);

        return new \WP_REST_Response(array_map(function($p) { return $p->toArray(); }, $payments));
    }

    public function restGetPayment(\WP_REST_Request $request): \WP_REST_Response
    {
        $payment = $this->getPayment((int) $request->get_param('id'));

        if (!$payment) {
            return new \WP_REST_Response(['error' => 'Payment not found'], 404);
        }

        return new \WP_REST_Response($payment->toArray());
    }

    public function restRefundPayment(\WP_REST_Request $request): \WP_REST_Response
    {
        $payment = $this->getPayment((int) $request->get_param('id'));

        if (!$payment) {
            return new \WP_REST_Response(['error' => 'Payment not found'], 404);
        }

        $amount = $request->get_param('amount');
        $result = $this->refundPayment($payment->provider, $payment->provider_payment_id, $amount);

        if ($result['success']) {
            return new \WP_REST_Response($result);
        }

        return new \WP_REST_Response($result, 400);
    }

    public function restGetSubscriptions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->subscriptions_table} WHERE 1=1";
        $params = [];

        if ($request->get_param('status')) {
            $sql .= " AND status = %s";
            $params[] = $request->get_param('status');
        }

        $sql .= " ORDER BY created_at DESC LIMIT 50";

        $rows = $wpdb->get_results(
            !empty($params) ? $wpdb->prepare($sql, ...$params) : $sql,
            ARRAY_A
        );

        return new \WP_REST_Response(array_map([$this, 'rowToSubscription'], $rows));
    }

    public function restCancelSubscription(\WP_REST_Request $request): \WP_REST_Response
    {
        $subscription = $this->getSubscription((int) $request->get_param('id'));

        if (!$subscription) {
            return new \WP_REST_Response(['error' => 'Subscription not found'], 404);
        }

        $result = $this->cancelSubscription($subscription->provider, $subscription->provider_subscription_id);

        if ($result['success']) {
            return new \WP_REST_Response($result);
        }

        return new \WP_REST_Response($result, 400);
    }

    public function restGetInvoices(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $sql = "SELECT * FROM {$this->invoices_table} ORDER BY created_at DESC LIMIT 50";
        $rows = $wpdb->get_results($sql, ARRAY_A);

        foreach ($rows as &$row) {
            $row['items'] = json_decode($row['items'], true) ?? [];
        }

        return new \WP_REST_Response($rows);
    }

    public function restCreateInvoice(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();
        $invoice_id = $this->createInvoice($data);

        if ($invoice_id) {
            return new \WP_REST_Response(['invoice_id' => $invoice_id], 201);
        }

        return new \WP_REST_Response(['error' => 'Failed to create invoice'], 500);
    }

    /**
     * Render payment form shortcode
     */
    public function renderPaymentFormShortcode($atts): string
    {
        $atts = shortcode_atts([
            'amount' => 0,
            'currency' => 'USD',
            'provider' => 'stripe',
            'description' => '',
            'button_text' => __('Pay Now', 'form-flow-pro'),
        ], $atts);

        wp_enqueue_script('stripe-js');
        wp_enqueue_script('ffp-payments');

        ob_start();
        include JEFORM_PLUGIN_DIR . 'templates/frontend/payment-form.php';
        return ob_get_clean();
    }

    /**
     * Render subscription management shortcode
     */
    public function renderSubscriptionManageShortcode($atts): string
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to manage your subscriptions.', 'form-flow-pro') . '</p>';
        }

        $subscriptions = $this->getUserSubscriptions(get_current_user_id(), 'active');

        ob_start();
        include JEFORM_PLUGIN_DIR . 'templates/frontend/subscription-manage.php';
        return ob_get_clean();
    }

    /**
     * Get payment statistics
     */
    public function getStatistics(string $period = '30days'): array
    {
        global $wpdb;

        $start_date = match ($period) {
            '7days' => date('Y-m-d', strtotime('-7 days')),
            '30days' => date('Y-m-d', strtotime('-30 days')),
            '90days' => date('Y-m-d', strtotime('-90 days')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            default => date('Y-m-d', strtotime('-30 days')),
        };

        // Total revenue
        $total_revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount - refunded_amount) FROM {$this->payments_table}
             WHERE status = 'succeeded' AND DATE(created_at) >= %s",
            $start_date
        ));

        // Total payments
        $total_payments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->payments_table}
             WHERE DATE(created_at) >= %s",
            $start_date
        ));

        // Successful payments
        $successful_payments = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->payments_table}
             WHERE status = 'succeeded' AND DATE(created_at) >= %s",
            $start_date
        ));

        // Active subscriptions
        $active_subscriptions = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->subscriptions_table} WHERE status = 'active'"
        );

        // Monthly recurring revenue (MRR)
        $mrr = (float) $wpdb->get_var(
            "SELECT SUM(amount) FROM {$this->subscriptions_table}
             WHERE status = 'active' AND interval_type = 'month'"
        );

        // Add annual subscriptions to MRR (divided by 12)
        $annual_revenue = (float) $wpdb->get_var(
            "SELECT SUM(amount / 12) FROM {$this->subscriptions_table}
             WHERE status = 'active' AND interval_type = 'year'"
        );

        $mrr += $annual_revenue;

        // Revenue by provider
        $by_provider = $wpdb->get_results($wpdb->prepare(
            "SELECT provider, SUM(amount - refunded_amount) as revenue, COUNT(*) as count
             FROM {$this->payments_table}
             WHERE status = 'succeeded' AND DATE(created_at) >= %s
             GROUP BY provider",
            $start_date
        ), ARRAY_A);

        return [
            'total_revenue' => $total_revenue,
            'total_payments' => $total_payments,
            'successful_payments' => $successful_payments,
            'success_rate' => $total_payments > 0 ? round(($successful_payments / $total_payments) * 100, 1) : 0,
            'active_subscriptions' => $active_subscriptions,
            'mrr' => $mrr,
            'by_provider' => $by_provider,
            'period' => $period,
        ];
    }
}
