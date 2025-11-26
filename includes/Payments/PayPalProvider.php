<?php
/**
 * FormFlow Pro - PayPal Payment Provider
 *
 * Complete PayPal integration with Orders API, Subscriptions,
 * and Smart Payment Buttons.
 *
 * @package FormFlowPro
 * @subpackage Payments
 * @since 2.4.0
 */

namespace FormFlowPro\Payments;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PayPal Payment Provider
 */
class PayPalProvider implements PaymentProviderInterface
{
    private string $client_id;
    private string $client_secret;
    private string $webhook_id;
    private bool $sandbox;
    private string $api_base;
    private ?string $access_token = null;
    private ?int $token_expiry = null;

    public function __construct()
    {
        $this->sandbox = get_option('ffp_paypal_sandbox', true);

        if ($this->sandbox) {
            $this->client_id = get_option('ffp_paypal_sandbox_client_id', '');
            $this->client_secret = get_option('ffp_paypal_sandbox_client_secret', '');
            $this->webhook_id = get_option('ffp_paypal_sandbox_webhook_id', '');
            $this->api_base = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->client_id = get_option('ffp_paypal_live_client_id', '');
            $this->client_secret = get_option('ffp_paypal_live_client_secret', '');
            $this->webhook_id = get_option('ffp_paypal_live_webhook_id', '');
            $this->api_base = 'https://api-m.paypal.com';
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * Get client ID for frontend
     */
    public function getClientId(): string
    {
        return $this->client_id;
    }

    /**
     * Get environment
     */
    public function getEnvironment(): string
    {
        return $this->sandbox ? 'sandbox' : 'production';
    }

    /**
     * Get access token
     */
    private function getAccessToken(): ?string
    {
        // Return cached token if valid
        if ($this->access_token && $this->token_expiry && time() < $this->token_expiry) {
            return $this->access_token;
        }

        $response = wp_remote_post($this->api_base . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['access_token'])) {
            return null;
        }

        $this->access_token = $body['access_token'];
        $this->token_expiry = time() + ($body['expires_in'] ?? 3600) - 60; // 1 minute buffer

        return $this->access_token;
    }

    /**
     * Create Order (Payment Intent equivalent)
     */
    public function createPayment(array $data): array
    {
        $order_data = [
            'intent' => $data['capture'] === false ? 'AUTHORIZE' : 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $data['reference_id'] ?? uniqid('ffp_'),
                'description' => $data['description'] ?? '',
                'amount' => [
                    'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                    'value' => number_format($data['amount'], 2, '.', ''),
                ],
            ]],
        ];

        // Breakdown
        if (!empty($data['breakdown'])) {
            $order_data['purchase_units'][0]['amount']['breakdown'] = $data['breakdown'];
        }

        // Items
        if (!empty($data['items'])) {
            $order_data['purchase_units'][0]['items'] = array_map(function($item) use ($data) {
                return [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? '',
                    'unit_amount' => [
                        'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                        'value' => number_format($item['price'], 2, '.', ''),
                    ],
                    'quantity' => (string) ($item['quantity'] ?? 1),
                    'category' => $item['category'] ?? 'DIGITAL_GOODS', // PHYSICAL_GOODS, DIGITAL_GOODS, DONATION
                ];
            }, $data['items']);
        }

        // Shipping
        if (!empty($data['shipping'])) {
            $order_data['purchase_units'][0]['shipping'] = $data['shipping'];
        }

        // Payee
        if (!empty($data['payee_email'])) {
            $order_data['purchase_units'][0]['payee'] = [
                'email_address' => $data['payee_email'],
            ];
        }

        // Platform fee
        if (!empty($data['platform_fee'])) {
            $order_data['purchase_units'][0]['payment_instruction'] = [
                'platform_fees' => [[
                    'amount' => [
                        'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                        'value' => number_format($data['platform_fee'], 2, '.', ''),
                    ],
                ]],
            ];
        }

        // Application context for redirects
        if (!empty($data['return_url']) || !empty($data['cancel_url'])) {
            $order_data['application_context'] = [
                'return_url' => $data['return_url'] ?? '',
                'cancel_url' => $data['cancel_url'] ?? '',
                'brand_name' => $data['brand_name'] ?? get_bloginfo('name'),
                'locale' => $data['locale'] ?? 'en-US',
                'landing_page' => $data['landing_page'] ?? 'NO_PREFERENCE', // LOGIN, BILLING, NO_PREFERENCE
                'shipping_preference' => $data['shipping_preference'] ?? 'NO_SHIPPING', // GET_FROM_FILE, NO_SHIPPING, SET_PROVIDED_ADDRESS
                'user_action' => $data['user_action'] ?? 'PAY_NOW', // CONTINUE, PAY_NOW
            ];
        }

        // Custom metadata (stored in custom_id)
        if (!empty($data['metadata'])) {
            $order_data['purchase_units'][0]['custom_id'] = json_encode($data['metadata']);
        }

        $response = $this->request('POST', '/v2/checkout/orders', $order_data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        $approve_link = null;
        foreach ($response['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approve_link = $link['href'];
                break;
            }
        }

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'status' => $response['status'],
            'approve_url' => $approve_link,
        ];
    }

    /**
     * Capture authorized payment
     */
    public function capturePayment(string $payment_id): array
    {
        $response = $this->request('POST', "/v2/checkout/orders/{$payment_id}/capture");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? [];

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'capture_id' => $capture['id'] ?? null,
            'status' => $response['status'],
            'amount' => isset($capture['amount'])
                ? floatval($capture['amount']['value'])
                : null,
        ];
    }

    /**
     * Authorize payment (for two-step capture)
     */
    public function authorizePayment(string $payment_id): array
    {
        $response = $this->request('POST', "/v2/checkout/orders/{$payment_id}/authorize");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        $authorization = $response['purchase_units'][0]['payments']['authorizations'][0] ?? [];

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'authorization_id' => $authorization['id'] ?? null,
            'status' => $response['status'],
        ];
    }

    /**
     * Capture authorized payment
     */
    public function captureAuthorization(string $authorization_id, ?float $amount = null, string $currency = 'USD'): array
    {
        $data = [];

        if ($amount !== null) {
            $data['amount'] = [
                'currency_code' => strtoupper($currency),
                'value' => number_format($amount, 2, '.', ''),
            ];
        }

        $response = $this->request('POST', "/v2/payments/authorizations/{$authorization_id}/capture", $data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'capture_id' => $response['id'],
            'status' => $response['status'],
            'amount' => floatval($response['amount']['value'] ?? 0),
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $payment_id, ?float $amount = null): array
    {
        // First get the capture ID
        $payment = $this->getPayment($payment_id);

        if (!$payment['success']) {
            return $payment;
        }

        $capture_id = $payment['capture_id'] ?? null;

        if (!$capture_id) {
            return [
                'success' => false,
                'error' => 'No capture found for this payment',
            ];
        }

        $data = [];

        if ($amount !== null) {
            $data['amount'] = [
                'currency_code' => strtoupper($payment['currency'] ?? 'USD'),
                'value' => number_format($amount, 2, '.', ''),
            ];
        }

        $response = $this->request('POST', "/v2/payments/captures/{$capture_id}/refund", $data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'refund_id' => $response['id'],
            'status' => $response['status'],
            'amount' => floatval($response['amount']['value'] ?? 0),
        ];
    }

    /**
     * Get payment/order details
     */
    public function getPayment(string $payment_id): array
    {
        $response = $this->request('GET', "/v2/checkout/orders/{$payment_id}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        $purchase_unit = $response['purchase_units'][0] ?? [];
        $payer = $response['payer'] ?? [];
        $capture = $purchase_unit['payments']['captures'][0] ?? [];
        $authorization = $purchase_unit['payments']['authorizations'][0] ?? [];

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'status' => $response['status'],
            'amount' => floatval($purchase_unit['amount']['value'] ?? 0),
            'currency' => $purchase_unit['amount']['currency_code'] ?? 'USD',
            'capture_id' => $capture['id'] ?? null,
            'authorization_id' => $authorization['id'] ?? null,
            'payer_email' => $payer['email_address'] ?? null,
            'payer_id' => $payer['payer_id'] ?? null,
            'metadata' => !empty($purchase_unit['custom_id'])
                ? json_decode($purchase_unit['custom_id'], true)
                : [],
            'created' => $response['create_time'] ?? null,
        ];
    }

    /**
     * Create customer (PayPal Vault)
     */
    public function createCustomer(array $data): array
    {
        // PayPal doesn't have a traditional customer object
        // Use vault tokens instead
        $vault_data = [
            'customer' => [
                'email_address' => $data['email'] ?? '',
            ],
        ];

        if (!empty($data['name'])) {
            $vault_data['customer']['name'] = [
                'given_name' => $data['first_name'] ?? '',
                'surname' => $data['last_name'] ?? '',
            ];
        }

        // For PayPal, customers are created implicitly
        // Return a generated ID based on email for reference
        return [
            'success' => true,
            'customer_id' => 'cus_' . md5($data['email'] ?? uniqid()),
            'email' => $data['email'] ?? null,
        ];
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $data): array
    {
        $subscription_data = [
            'plan_id' => $data['plan_id'],
        ];

        // Subscriber
        if (!empty($data['email']) || !empty($data['name'])) {
            $subscription_data['subscriber'] = [];

            if (!empty($data['email'])) {
                $subscription_data['subscriber']['email_address'] = $data['email'];
            }

            if (!empty($data['name'])) {
                $subscription_data['subscriber']['name'] = [
                    'given_name' => $data['first_name'] ?? '',
                    'surname' => $data['last_name'] ?? '',
                ];
            }

            if (!empty($data['shipping_address'])) {
                $subscription_data['subscriber']['shipping_address'] = $data['shipping_address'];
            }
        }

        // Start time
        if (!empty($data['start_time'])) {
            $subscription_data['start_time'] = $data['start_time'];
        }

        // Quantity
        if (!empty($data['quantity'])) {
            $subscription_data['quantity'] = (string) $data['quantity'];
        }

        // Custom ID (metadata)
        if (!empty($data['metadata'])) {
            $subscription_data['custom_id'] = json_encode($data['metadata']);
        }

        // Application context
        $subscription_data['application_context'] = [
            'brand_name' => $data['brand_name'] ?? get_bloginfo('name'),
            'locale' => $data['locale'] ?? 'en-US',
            'shipping_preference' => $data['shipping_preference'] ?? 'NO_SHIPPING',
            'user_action' => 'SUBSCRIBE_NOW',
            'return_url' => $data['return_url'] ?? home_url('/paypal/subscription/success'),
            'cancel_url' => $data['cancel_url'] ?? home_url('/paypal/subscription/cancel'),
        ];

        $response = $this->request('POST', '/v1/billing/subscriptions', $subscription_data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        $approve_link = null;
        foreach ($response['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approve_link = $link['href'];
                break;
            }
        }

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'],
            'approve_url' => $approve_link,
        ];
    }

    /**
     * Get subscription details
     */
    public function getSubscription(string $subscription_id): array
    {
        $response = $this->request('GET', "/v1/billing/subscriptions/{$subscription_id}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'],
            'plan_id' => $response['plan_id'] ?? null,
            'subscriber' => $response['subscriber'] ?? [],
            'start_time' => $response['start_time'] ?? null,
            'billing_info' => $response['billing_info'] ?? [],
            'metadata' => !empty($response['custom_id'])
                ? json_decode($response['custom_id'], true)
                : [],
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscription_id): array
    {
        $response = $this->request('POST', "/v1/billing/subscriptions/{$subscription_id}/cancel", [
            'reason' => 'Customer requested cancellation',
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $subscription_id,
            'status' => 'CANCELLED',
        ];
    }

    /**
     * Suspend subscription
     */
    public function suspendSubscription(string $subscription_id, string $reason = 'Suspended by admin'): array
    {
        $response = $this->request('POST', "/v1/billing/subscriptions/{$subscription_id}/suspend", [
            'reason' => $reason,
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $subscription_id,
            'status' => 'SUSPENDED',
        ];
    }

    /**
     * Activate/Resume subscription
     */
    public function activateSubscription(string $subscription_id, string $reason = 'Reactivated'): array
    {
        $response = $this->request('POST', "/v1/billing/subscriptions/{$subscription_id}/activate", [
            'reason' => $reason,
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $subscription_id,
            'status' => 'ACTIVE',
        ];
    }

    /**
     * Create billing plan
     */
    public function createPlan(array $data): array
    {
        $plan_data = [
            'product_id' => $data['product_id'],
            'name' => $data['name'],
            'status' => $data['status'] ?? 'ACTIVE',
            'billing_cycles' => [],
        ];

        if (!empty($data['description'])) {
            $plan_data['description'] = $data['description'];
        }

        // Trial period
        if (!empty($data['trial'])) {
            $plan_data['billing_cycles'][] = [
                'frequency' => [
                    'interval_unit' => strtoupper($data['trial']['interval'] ?? 'DAY'),
                    'interval_count' => $data['trial']['interval_count'] ?? 7,
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => 1,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => number_format($data['trial']['price'] ?? 0, 2, '.', ''),
                        'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                    ],
                ],
            ];
        }

        // Regular billing cycle
        $plan_data['billing_cycles'][] = [
            'frequency' => [
                'interval_unit' => strtoupper($data['interval'] ?? 'MONTH'),
                'interval_count' => $data['interval_count'] ?? 1,
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => empty($data['trial']) ? 1 : 2,
            'total_cycles' => $data['total_cycles'] ?? 0, // 0 = infinite
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => number_format($data['price'], 2, '.', ''),
                    'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                ],
            ],
        ];

        // Payment preferences
        $plan_data['payment_preferences'] = [
            'auto_bill_outstanding' => $data['auto_bill'] ?? true,
            'setup_fee_failure_action' => 'CONTINUE',
            'payment_failure_threshold' => $data['failure_threshold'] ?? 3,
        ];

        if (!empty($data['setup_fee'])) {
            $plan_data['payment_preferences']['setup_fee'] = [
                'value' => number_format($data['setup_fee'], 2, '.', ''),
                'currency_code' => strtoupper($data['currency'] ?? 'USD'),
            ];
        }

        // Taxes
        if (!empty($data['taxes'])) {
            $plan_data['taxes'] = [
                'percentage' => (string) $data['taxes']['percentage'],
                'inclusive' => $data['taxes']['inclusive'] ?? false,
            ];
        }

        $response = $this->request('POST', '/v1/billing/plans', $plan_data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'plan_id' => $response['id'],
            'status' => $response['status'],
        ];
    }

    /**
     * Create product (for plans)
     */
    public function createProduct(array $data): array
    {
        $product_data = [
            'name' => $data['name'],
            'type' => $data['type'] ?? 'SERVICE', // PHYSICAL, DIGITAL, SERVICE
        ];

        if (!empty($data['description'])) {
            $product_data['description'] = $data['description'];
        }

        if (!empty($data['category'])) {
            $product_data['category'] = $data['category'];
        }

        if (!empty($data['image_url'])) {
            $product_data['image_url'] = $data['image_url'];
        }

        if (!empty($data['home_url'])) {
            $product_data['home_url'] = $data['home_url'];
        }

        $response = $this->request('POST', '/v1/catalogs/products', $product_data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'product_id' => $response['id'],
        ];
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $data): array
    {
        $invoice_data = [
            'detail' => [
                'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                'invoice_date' => $data['invoice_date'] ?? date('Y-m-d'),
                'payment_term' => [
                    'term_type' => $data['term_type'] ?? 'NET_30',
                ],
            ],
            'invoicer' => [
                'business_name' => $data['business_name'] ?? get_bloginfo('name'),
            ],
            'primary_recipients' => [[
                'billing_info' => [
                    'email_address' => $data['customer_email'],
                ],
            ]],
            'items' => array_map(function($item) use ($data) {
                return [
                    'name' => $item['name'],
                    'description' => $item['description'] ?? '',
                    'quantity' => (string) ($item['quantity'] ?? 1),
                    'unit_amount' => [
                        'currency_code' => strtoupper($data['currency'] ?? 'USD'),
                        'value' => number_format($item['price'], 2, '.', ''),
                    ],
                ];
            }, $data['items'] ?? []),
        ];

        if (!empty($data['note'])) {
            $invoice_data['detail']['note'] = $data['note'];
        }

        if (!empty($data['memo'])) {
            $invoice_data['detail']['memo'] = $data['memo'];
        }

        if (!empty($data['customer_name'])) {
            $invoice_data['primary_recipients'][0]['billing_info']['name'] = [
                'given_name' => $data['customer_first_name'] ?? '',
                'surname' => $data['customer_last_name'] ?? '',
            ];
        }

        $response = $this->request('POST', '/v2/invoicing/invoices', $invoice_data);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'invoice_id' => $response['id'],
            'status' => $response['status'],
        ];
    }

    /**
     * Send invoice
     */
    public function sendInvoice(string $invoice_id): array
    {
        $response = $this->request('POST', "/v2/invoicing/invoices/{$invoice_id}/send", [
            'send_to_invoicer' => true,
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error'],
            ];
        }

        return [
            'success' => true,
            'invoice_id' => $invoice_id,
        ];
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // PayPal webhook verification
        $event = json_decode($payload, true);

        if (!$event || !isset($event['event_type'])) {
            return [
                'success' => false,
                'error' => 'Invalid webhook payload',
            ];
        }

        // Verify webhook signature
        if (!empty($this->webhook_id)) {
            $headers = getallheaders();

            $verification_data = [
                'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
                'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id' => $this->webhook_id,
                'webhook_event' => $event,
            ];

            $verify_response = $this->request('POST', '/v1/notifications/verify-webhook-signature', $verification_data);

            if (($verify_response['verification_status'] ?? '') !== 'SUCCESS') {
                return [
                    'success' => false,
                    'error' => 'Webhook signature verification failed',
                ];
            }
        }

        return [
            'success' => true,
            'event_id' => $event['id'],
            'event_type' => $event['event_type'],
            'data' => $event['resource'] ?? [],
            'created' => $event['create_time'] ?? null,
        ];
    }

    /**
     * Make API request
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'error' => 'Failed to obtain access token',
            ];
        }

        $url = $this->api_base . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => uniqid('ffp_', true),
            ],
            'timeout' => 60,
        ];

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif (!empty($data)) {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'error' => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle empty responses (204 No Content)
        if ($code === 204) {
            return ['success' => true];
        }

        $result = json_decode($body, true) ?? [];

        // Check for errors
        if ($code >= 400) {
            $error_message = $result['message']
                ?? $result['error_description']
                ?? $result['details'][0]['description']
                ?? 'Unknown error';

            return [
                'error' => $error_message,
                'code' => $code,
            ];
        }

        return $result;
    }
}
