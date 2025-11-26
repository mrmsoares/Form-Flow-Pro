<?php
/**
 * FormFlow Pro - Stripe Payment Provider
 *
 * Complete Stripe integration with Payment Intents, Subscriptions,
 * Checkout Sessions, and Webhooks.
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
 * Payment Provider Interface
 */
interface PaymentProviderInterface
{
    public function createPayment(array $data): array;
    public function capturePayment(string $payment_id): array;
    public function refundPayment(string $payment_id, float $amount = null): array;
    public function getPayment(string $payment_id): array;
    public function createCustomer(array $data): array;
    public function createSubscription(array $data): array;
    public function cancelSubscription(string $subscription_id): array;
    public function handleWebhook(string $payload, string $signature): array;
    public function isConfigured(): bool;
}

/**
 * Stripe Payment Provider
 */
class StripeProvider implements PaymentProviderInterface
{
    private string $api_key;
    private string $webhook_secret;
    private string $publishable_key;
    private bool $test_mode;
    private string $api_version = '2023-10-16';
    private string $api_base = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->test_mode = get_option('ffp_stripe_test_mode', true);

        if ($this->test_mode) {
            $this->api_key = get_option('ffp_stripe_test_secret_key', '');
            $this->publishable_key = get_option('ffp_stripe_test_publishable_key', '');
            $this->webhook_secret = get_option('ffp_stripe_test_webhook_secret', '');
        } else {
            $this->api_key = get_option('ffp_stripe_live_secret_key', '');
            $this->publishable_key = get_option('ffp_stripe_live_publishable_key', '');
            $this->webhook_secret = get_option('ffp_stripe_live_webhook_secret', '');
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->api_key) && !empty($this->publishable_key);
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->publishable_key;
    }

    /**
     * Create a Payment Intent
     */
    public function createPayment(array $data): array
    {
        $params = [
            'amount' => $this->convertToStripeAmount($data['amount'], $data['currency'] ?? 'usd'),
            'currency' => strtolower($data['currency'] ?? 'usd'),
            'payment_method_types' => $data['payment_methods'] ?? ['card'],
            'metadata' => $data['metadata'] ?? [],
        ];

        // Customer
        if (!empty($data['customer_id'])) {
            $params['customer'] = $data['customer_id'];
        }

        // Description
        if (!empty($data['description'])) {
            $params['description'] = $data['description'];
        }

        // Receipt email
        if (!empty($data['email'])) {
            $params['receipt_email'] = $data['email'];
        }

        // Automatic capture
        if (isset($data['capture'])) {
            $params['capture_method'] = $data['capture'] ? 'automatic' : 'manual';
        }

        // Setup future usage for saving card
        if (!empty($data['save_card'])) {
            $params['setup_future_usage'] = 'off_session';
        }

        // Statement descriptor
        if (!empty($data['statement_descriptor'])) {
            $params['statement_descriptor'] = substr($data['statement_descriptor'], 0, 22);
        }

        // Application fee for connected accounts
        if (!empty($data['application_fee'])) {
            $params['application_fee_amount'] = $this->convertToStripeAmount(
                $data['application_fee'],
                $data['currency'] ?? 'usd'
            );
        }

        // Transfer to connected account
        if (!empty($data['transfer_destination'])) {
            $params['transfer_data'] = [
                'destination' => $data['transfer_destination'],
            ];

            if (!empty($data['transfer_amount'])) {
                $params['transfer_data']['amount'] = $this->convertToStripeAmount(
                    $data['transfer_amount'],
                    $data['currency'] ?? 'usd'
                );
            }
        }

        $response = $this->request('POST', '/payment_intents', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Payment creation failed',
                'code' => $response['error']['code'] ?? 'unknown',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
                'code' => 'invalid_response',
            ];
        }

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'client_secret' => $response['client_secret'] ?? null,
            'status' => $response['status'] ?? 'unknown',
            'amount' => isset($response['amount'], $response['currency'])
                ? $this->convertFromStripeAmount($response['amount'], $response['currency'])
                : 0,
            'currency' => $response['currency'] ?? 'usd',
        ];
    }

    /**
     * Confirm Payment Intent
     */
    public function confirmPayment(string $payment_id, array $data = []): array
    {
        $params = [];

        if (!empty($data['payment_method'])) {
            $params['payment_method'] = $data['payment_method'];
        }

        if (!empty($data['return_url'])) {
            $params['return_url'] = $data['return_url'];
        }

        $response = $this->request('POST', "/payment_intents/{$payment_id}/confirm", $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Confirmation failed',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'requires_action' => ($response['status'] ?? '') === 'requires_action',
            'client_secret' => $response['client_secret'] ?? null,
        ];
    }

    /**
     * Capture authorized payment
     */
    public function capturePayment(string $payment_id): array
    {
        $response = $this->request('POST', "/payment_intents/{$payment_id}/capture");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Capture failed',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'amount_captured' => isset($response['amount_received'], $response['currency'])
                ? $this->convertFromStripeAmount($response['amount_received'], $response['currency'])
                : 0,
        ];
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $payment_id, float $amount = null): array
    {
        $params = [
            'payment_intent' => $payment_id,
        ];

        if ($amount !== null) {
            // Get payment to determine currency
            $payment = $this->getPayment($payment_id);
            if (!empty($payment['currency'])) {
                $params['amount'] = $this->convertToStripeAmount($amount, $payment['currency']);
            }
        }

        $response = $this->request('POST', '/refunds', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Refund failed',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'refund_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'amount' => isset($response['amount'], $response['currency'])
                ? $this->convertFromStripeAmount($response['amount'], $response['currency'])
                : 0,
        ];
    }

    /**
     * Get payment details
     */
    public function getPayment(string $payment_id): array
    {
        $response = $this->request('GET', "/payment_intents/{$payment_id}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to retrieve payment',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'payment_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'amount' => isset($response['amount'], $response['currency'])
                ? $this->convertFromStripeAmount($response['amount'], $response['currency'])
                : 0,
            'currency' => $response['currency'] ?? 'usd',
            'customer_id' => $response['customer'] ?? null,
            'payment_method' => $response['payment_method'] ?? null,
            'metadata' => $response['metadata'] ?? [],
            'created' => $response['created'] ?? null,
        ];
    }

    /**
     * Create Checkout Session
     */
    public function createCheckoutSession(array $data): array
    {
        $params = [
            'mode' => $data['mode'] ?? 'payment', // payment, subscription, setup
            'success_url' => $data['success_url'],
            'cancel_url' => $data['cancel_url'],
        ];

        // Line items
        if (!empty($data['line_items'])) {
            $params['line_items'] = array_map(function($item) {
                return [
                    'price_data' => [
                        'currency' => strtolower($item['currency'] ?? 'usd'),
                        'unit_amount' => $this->convertToStripeAmount(
                            $item['price'],
                            $item['currency'] ?? 'usd'
                        ),
                        'product_data' => [
                            'name' => $item['name'],
                            'description' => $item['description'] ?? '',
                            'images' => $item['images'] ?? [],
                        ],
                    ],
                    'quantity' => $item['quantity'] ?? 1,
                ];
            }, $data['line_items']);
        }

        // Use existing price ID
        if (!empty($data['price_id'])) {
            $params['line_items'] = [
                [
                    'price' => $data['price_id'],
                    'quantity' => $data['quantity'] ?? 1,
                ],
            ];
        }

        // Customer
        if (!empty($data['customer_id'])) {
            $params['customer'] = $data['customer_id'];
        } elseif (!empty($data['customer_email'])) {
            $params['customer_email'] = $data['customer_email'];
        }

        // Metadata
        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        // Client reference ID
        if (!empty($data['client_reference_id'])) {
            $params['client_reference_id'] = $data['client_reference_id'];
        }

        // Shipping
        if (!empty($data['shipping_enabled'])) {
            $params['shipping_address_collection'] = [
                'allowed_countries' => $data['shipping_countries'] ?? ['US', 'CA', 'GB'],
            ];
        }

        // Tax collection
        if (!empty($data['automatic_tax'])) {
            $params['automatic_tax'] = ['enabled' => true];
        }

        // Allow promotion codes
        if (!empty($data['allow_promotion_codes'])) {
            $params['allow_promotion_codes'] = true;
        }

        // Phone number collection
        if (!empty($data['phone_number_collection'])) {
            $params['phone_number_collection'] = ['enabled' => true];
        }

        // Consent collection (terms, etc.)
        if (!empty($data['consent_collection'])) {
            $params['consent_collection'] = [
                'terms_of_service' => 'required',
            ];
        }

        // Subscription data
        if ($params['mode'] === 'subscription' && !empty($data['subscription_data'])) {
            $params['subscription_data'] = $data['subscription_data'];
        }

        // Invoice creation for one-time payments
        if ($params['mode'] === 'payment' && !empty($data['invoice_creation'])) {
            $params['invoice_creation'] = ['enabled' => true];
        }

        // Custom text
        if (!empty($data['custom_text'])) {
            $params['custom_text'] = $data['custom_text'];
        }

        // Expiration
        if (!empty($data['expires_at'])) {
            $params['expires_at'] = $data['expires_at'];
        }

        $response = $this->request('POST', '/checkout/sessions', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create checkout session',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'session_id' => $response['id'],
            'url' => $response['url'] ?? null,
            'expires_at' => $response['expires_at'] ?? null,
        ];
    }

    /**
     * Retrieve Checkout Session
     */
    public function getCheckoutSession(string $session_id): array
    {
        $response = $this->request('GET', "/checkout/sessions/{$session_id}?expand[]=line_items&expand[]=customer&expand[]=payment_intent");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to retrieve session',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'session_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'payment_status' => $response['payment_status'] ?? 'unknown',
            'customer' => $response['customer'] ?? null,
            'payment_intent' => $response['payment_intent'] ?? null,
            'subscription' => $response['subscription'] ?? null,
            'amount_total' => isset($response['amount_total'], $response['currency'])
                ? $this->convertFromStripeAmount($response['amount_total'], $response['currency'])
                : null,
            'metadata' => $response['metadata'] ?? [],
        ];
    }

    /**
     * Create customer
     */
    public function createCustomer(array $data): array
    {
        $params = [];

        if (!empty($data['email'])) {
            $params['email'] = $data['email'];
        }

        if (!empty($data['name'])) {
            $params['name'] = $data['name'];
        }

        if (!empty($data['phone'])) {
            $params['phone'] = $data['phone'];
        }

        if (!empty($data['description'])) {
            $params['description'] = $data['description'];
        }

        if (!empty($data['address'])) {
            $params['address'] = $data['address'];
        }

        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        if (!empty($data['payment_method'])) {
            $params['payment_method'] = $data['payment_method'];
            $params['invoice_settings'] = [
                'default_payment_method' => $data['payment_method'],
            ];
        }

        $response = $this->request('POST', '/customers', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create customer',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'customer_id' => $response['id'],
            'email' => $response['email'] ?? null,
        ];
    }

    /**
     * Update customer
     */
    public function updateCustomer(string $customer_id, array $data): array
    {
        $params = [];

        if (isset($data['email'])) $params['email'] = $data['email'];
        if (isset($data['name'])) $params['name'] = $data['name'];
        if (isset($data['phone'])) $params['phone'] = $data['phone'];
        if (isset($data['description'])) $params['description'] = $data['description'];
        if (isset($data['address'])) $params['address'] = $data['address'];
        if (isset($data['metadata'])) $params['metadata'] = $data['metadata'];

        if (!empty($data['default_payment_method'])) {
            $params['invoice_settings'] = [
                'default_payment_method' => $data['default_payment_method'],
            ];
        }

        $response = $this->request('POST', "/customers/{$customer_id}", $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to update customer',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'customer_id' => $response['id'],
        ];
    }

    /**
     * Get customer
     */
    public function getCustomer(string $customer_id): array
    {
        $response = $this->request('GET', "/customers/{$customer_id}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Customer not found',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'customer_id' => $response['id'],
            'email' => $response['email'] ?? null,
            'name' => $response['name'] ?? null,
            'phone' => $response['phone'] ?? null,
            'metadata' => $response['metadata'] ?? [],
            'default_source' => $response['default_source'] ?? null,
        ];
    }

    /**
     * Attach payment method to customer
     */
    public function attachPaymentMethod(string $payment_method_id, string $customer_id): array
    {
        $response = $this->request('POST', "/payment_methods/{$payment_method_id}/attach", [
            'customer' => $customer_id,
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to attach payment method',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'payment_method_id' => $response['id'],
        ];
    }

    /**
     * List customer payment methods
     */
    public function listPaymentMethods(string $customer_id, string $type = 'card'): array
    {
        $response = $this->request('GET', "/payment_methods?customer={$customer_id}&type={$type}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to list payment methods',
            ];
        }

        return [
            'success' => true,
            'payment_methods' => array_map(function($pm) {
                return [
                    'id' => $pm['id'],
                    'type' => $pm['type'],
                    'card' => $pm['card'] ?? null,
                    'created' => $pm['created'],
                ];
            }, $response['data'] ?? []),
        ];
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $data): array
    {
        $params = [
            'customer' => $data['customer_id'],
            'items' => array_map(function($item) {
                $subscription_item = ['price' => $item['price_id']];
                if (!empty($item['quantity'])) {
                    $subscription_item['quantity'] = $item['quantity'];
                }
                return $subscription_item;
            }, $data['items'] ?? []),
        ];

        // Default payment method
        if (!empty($data['default_payment_method'])) {
            $params['default_payment_method'] = $data['default_payment_method'];
        }

        // Collection method
        $params['collection_method'] = $data['collection_method'] ?? 'charge_automatically';

        // Trial period
        if (!empty($data['trial_period_days'])) {
            $params['trial_period_days'] = $data['trial_period_days'];
        } elseif (!empty($data['trial_end'])) {
            $params['trial_end'] = $data['trial_end'];
        }

        // Billing cycle anchor
        if (!empty($data['billing_cycle_anchor'])) {
            $params['billing_cycle_anchor'] = $data['billing_cycle_anchor'];
        }

        // Cancel at period end
        if (isset($data['cancel_at_period_end'])) {
            $params['cancel_at_period_end'] = $data['cancel_at_period_end'];
        }

        // Proration behavior
        if (!empty($data['proration_behavior'])) {
            $params['proration_behavior'] = $data['proration_behavior'];
        }

        // Coupon
        if (!empty($data['coupon'])) {
            $params['coupon'] = $data['coupon'];
        }

        // Promotion code
        if (!empty($data['promotion_code'])) {
            $params['promotion_code'] = $data['promotion_code'];
        }

        // Metadata
        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        // Payment behavior
        $params['payment_behavior'] = $data['payment_behavior'] ?? 'default_incomplete';

        // Expand latest_invoice for client secret
        $params['expand'] = ['latest_invoice.payment_intent'];

        $response = $this->request('POST', '/subscriptions', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create subscription',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        $result = [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'current_period_start' => $response['current_period_start'] ?? null,
            'current_period_end' => $response['current_period_end'] ?? null,
            'trial_start' => $response['trial_start'] ?? null,
            'trial_end' => $response['trial_end'] ?? null,
        ];

        // Include client secret for incomplete subscriptions
        if (!empty($response['latest_invoice']['payment_intent']['client_secret'])) {
            $result['client_secret'] = $response['latest_invoice']['payment_intent']['client_secret'];
        }

        return $result;
    }

    /**
     * Get subscription
     */
    public function getSubscription(string $subscription_id): array
    {
        $response = $this->request('GET', "/subscriptions/{$subscription_id}?expand[]=latest_invoice&expand[]=customer");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Subscription not found',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        $customer = $response['customer'] ?? null;
        $customer_id = is_array($customer) ? ($customer['id'] ?? null) : $customer;

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
            'customer_id' => $customer_id,
            'current_period_start' => $response['current_period_start'] ?? null,
            'current_period_end' => $response['current_period_end'] ?? null,
            'cancel_at_period_end' => $response['cancel_at_period_end'] ?? false,
            'canceled_at' => $response['canceled_at'] ?? null,
            'trial_start' => $response['trial_start'] ?? null,
            'trial_end' => $response['trial_end'] ?? null,
            'items' => $response['items']['data'] ?? [],
            'latest_invoice' => $response['latest_invoice'] ?? null,
            'metadata' => $response['metadata'] ?? [],
        ];
    }

    /**
     * Update subscription
     */
    public function updateSubscription(string $subscription_id, array $data): array
    {
        $params = [];

        if (!empty($data['items'])) {
            $params['items'] = array_map(function($item) {
                $subscription_item = ['price' => $item['price_id']];
                if (!empty($item['id'])) {
                    $subscription_item['id'] = $item['id'];
                }
                if (!empty($item['quantity'])) {
                    $subscription_item['quantity'] = $item['quantity'];
                }
                if (!empty($item['deleted'])) {
                    $subscription_item['deleted'] = true;
                }
                return $subscription_item;
            }, $data['items']);
        }

        if (isset($data['cancel_at_period_end'])) {
            $params['cancel_at_period_end'] = $data['cancel_at_period_end'];
        }

        if (!empty($data['default_payment_method'])) {
            $params['default_payment_method'] = $data['default_payment_method'];
        }

        if (!empty($data['proration_behavior'])) {
            $params['proration_behavior'] = $data['proration_behavior'];
        }

        if (!empty($data['coupon'])) {
            $params['coupon'] = $data['coupon'];
        }

        if (!empty($data['trial_end'])) {
            $params['trial_end'] = $data['trial_end'];
        }

        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        $response = $this->request('POST', "/subscriptions/{$subscription_id}", $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to update subscription',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
        ];
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(string $subscription_id): array
    {
        $response = $this->request('DELETE', "/subscriptions/{$subscription_id}");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to cancel subscription',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'] ?? 'canceled',
        ];
    }

    /**
     * Pause subscription (by ending trial)
     */
    public function pauseSubscription(string $subscription_id): array
    {
        return $this->updateSubscription($subscription_id, [
            'pause_collection' => [
                'behavior' => 'mark_uncollectible',
            ],
        ]);
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(string $subscription_id): array
    {
        $response = $this->request('POST', "/subscriptions/{$subscription_id}", [
            'pause_collection' => '',
        ]);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to resume subscription',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'subscription_id' => $response['id'],
            'status' => $response['status'] ?? 'unknown',
        ];
    }

    /**
     * Create product
     */
    public function createProduct(array $data): array
    {
        $params = [
            'name' => $data['name'],
        ];

        if (!empty($data['description'])) {
            $params['description'] = $data['description'];
        }

        if (!empty($data['images'])) {
            $params['images'] = $data['images'];
        }

        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        $params['active'] = $data['active'] ?? true;

        $response = $this->request('POST', '/products', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create product',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'product_id' => $response['id'],
        ];
    }

    /**
     * Create price
     */
    public function createPrice(array $data): array
    {
        $params = [
            'product' => $data['product_id'],
            'currency' => strtolower($data['currency'] ?? 'usd'),
        ];

        if (!empty($data['unit_amount'])) {
            $params['unit_amount'] = $this->convertToStripeAmount($data['unit_amount'], $params['currency']);
        }

        // Recurring for subscriptions
        if (!empty($data['recurring'])) {
            $params['recurring'] = [
                'interval' => $data['recurring']['interval'] ?? 'month',
                'interval_count' => $data['recurring']['interval_count'] ?? 1,
            ];

            if (!empty($data['recurring']['trial_period_days'])) {
                $params['recurring']['trial_period_days'] = $data['recurring']['trial_period_days'];
            }
        }

        // Billing scheme
        if (!empty($data['billing_scheme'])) {
            $params['billing_scheme'] = $data['billing_scheme'];
        }

        // Tiers for graduated pricing
        if (!empty($data['tiers'])) {
            $params['tiers'] = $data['tiers'];
            $params['tiers_mode'] = $data['tiers_mode'] ?? 'graduated';
        }

        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        $response = $this->request('POST', '/prices', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create price',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'price_id' => $response['id'],
        ];
    }

    /**
     * Create invoice
     */
    public function createInvoice(array $data): array
    {
        $params = [
            'customer' => $data['customer_id'],
        ];

        if (isset($data['auto_advance'])) {
            $params['auto_advance'] = $data['auto_advance'];
        }

        if (!empty($data['collection_method'])) {
            $params['collection_method'] = $data['collection_method'];
        }

        if (!empty($data['description'])) {
            $params['description'] = $data['description'];
        }

        if (!empty($data['due_date'])) {
            $params['due_date'] = $data['due_date'];
        }

        if (!empty($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        $response = $this->request('POST', '/invoices', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create invoice',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'invoice_id' => $response['id'],
            'status' => $response['status'] ?? 'draft',
            'invoice_url' => $response['hosted_invoice_url'] ?? null,
        ];
    }

    /**
     * Add invoice item
     */
    public function addInvoiceItem(array $data): array
    {
        $params = [
            'customer' => $data['customer_id'],
        ];

        if (!empty($data['invoice'])) {
            $params['invoice'] = $data['invoice'];
        }

        if (!empty($data['price'])) {
            $params['price'] = $data['price'];
        } else {
            $params['unit_amount'] = $this->convertToStripeAmount(
                $data['amount'],
                $data['currency'] ?? 'usd'
            );
            $params['currency'] = strtolower($data['currency'] ?? 'usd');
        }

        if (!empty($data['quantity'])) {
            $params['quantity'] = $data['quantity'];
        }

        if (!empty($data['description'])) {
            $params['description'] = $data['description'];
        }

        $response = $this->request('POST', '/invoiceitems', $params);

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to add invoice item',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'invoice_item_id' => $response['id'],
        ];
    }

    /**
     * Finalize and send invoice
     */
    public function finalizeInvoice(string $invoice_id): array
    {
        $response = $this->request('POST', "/invoices/{$invoice_id}/finalize");

        if (!empty($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to finalize invoice',
            ];
        }

        if (!isset($response['id'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response',
            ];
        }

        return [
            'success' => true,
            'invoice_id' => $response['id'],
            'status' => $response['status'] ?? 'finalized',
            'invoice_url' => $response['hosted_invoice_url'] ?? null,
            'invoice_pdf' => $response['invoice_pdf'] ?? null,
        ];
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        if (empty($this->webhook_secret)) {
            return [
                'success' => false,
                'error' => 'Webhook secret not configured',
            ];
        }

        // Verify signature
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = [];

        foreach ($elements as $element) {
            [$prefix, $value] = explode('=', $element, 2);
            if ($prefix === 't') {
                $timestamp = $value;
            } elseif ($prefix === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            return [
                'success' => false,
                'error' => 'Invalid signature format',
            ];
        }

        // Check timestamp (5 minute tolerance)
        if (abs(time() - $timestamp) > 300) {
            return [
                'success' => false,
                'error' => 'Webhook timestamp too old',
            ];
        }

        // Compute expected signature
        $signed_payload = "{$timestamp}.{$payload}";
        $expected_signature = hash_hmac('sha256', $signed_payload, $this->webhook_secret);

        // Compare signatures
        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expected_signature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            return [
                'success' => false,
                'error' => 'Invalid signature',
            ];
        }

        // Parse event
        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            return [
                'success' => false,
                'error' => 'Invalid event payload',
            ];
        }

        return [
            'success' => true,
            'event_id' => $event['id'],
            'event_type' => $event['type'],
            'data' => $event['data']['object'] ?? [],
            'created' => $event['created'],
        ];
    }

    /**
     * Make API request
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->api_base . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Stripe-Version' => $this->api_version,
            ],
            'timeout' => 60,
        ];

        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif (!empty($data)) {
            $args['body'] = $this->encodeParams($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'error' => [
                    'message' => $response->get_error_message(),
                    'code' => 'network_error',
                ],
            ];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true) ?? [];
    }

    /**
     * Encode parameters for Stripe API
     */
    private function encodeParams(array $params, string $prefix = ''): string
    {
        $result = [];

        foreach ($params as $key => $value) {
            $name = $prefix ? "{$prefix}[{$key}]" : $key;

            if (is_array($value)) {
                $result[] = $this->encodeParams($value, $name);
            } else {
                $result[] = urlencode($name) . '=' . urlencode($value);
            }
        }

        return implode('&', $result);
    }

    /**
     * Convert amount to Stripe format (cents)
     */
    private function convertToStripeAmount(float $amount, string $currency): int
    {
        $zero_decimal_currencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
            'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        if (in_array(strtolower($currency), $zero_decimal_currencies)) {
            return (int) $amount;
        }

        return (int) round($amount * 100);
    }

    /**
     * Convert amount from Stripe format
     */
    private function convertFromStripeAmount(int $amount, string $currency): float
    {
        $zero_decimal_currencies = [
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga',
            'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];

        if (in_array(strtolower($currency), $zero_decimal_currencies)) {
            return (float) $amount;
        }

        return $amount / 100;
    }
}
