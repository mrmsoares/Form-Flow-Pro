<?php
/**
 * Tests for StripeProvider class.
 *
 * @package FormFlowPro\Tests\Unit\Payments
 */

namespace FormFlowPro\Tests\Unit\Payments;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Payments\StripeProvider;
use FormFlowPro\Payments\PaymentProviderInterface;

class StripeProviderTest extends TestCase
{
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test mode credentials
        set_option('ffp_stripe_test_mode', true);
        set_option('ffp_stripe_test_secret_key', 'sk_test_123456789');
        set_option('ffp_stripe_test_publishable_key', 'pk_test_123456789');
        set_option('ffp_stripe_test_webhook_secret', 'whsec_test_123456789');

        $this->provider = new StripeProvider();
    }

    // ==========================================================================
    // Interface Implementation Tests
    // ==========================================================================

    public function test_implements_payment_provider_interface()
    {
        $this->assertInstanceOf(PaymentProviderInterface::class, $this->provider);
    }

    // ==========================================================================
    // Configuration Tests
    // ==========================================================================

    public function test_is_configured_returns_true_when_credentials_set()
    {
        $this->assertTrue($this->provider->isConfigured());
    }

    public function test_is_configured_returns_false_when_credentials_missing()
    {
        set_option('ffp_stripe_test_secret_key', '');
        set_option('ffp_stripe_test_publishable_key', '');

        $provider = new StripeProvider();
        $this->assertFalse($provider->isConfigured());
    }

    public function test_get_publishable_key()
    {
        $key = $this->provider->getPublishableKey();
        $this->assertEquals('pk_test_123456789', $key);
    }

    // ==========================================================================
    // Payment Intent Tests
    // ==========================================================================

    public function test_create_payment_builds_correct_params()
    {
        // Mock wp_remote_request to capture the request
        global $wp_remote_request_args;
        $wp_remote_request_args = null;

        set_wp_remote_request_handler(function($url, $args) use (&$wp_remote_request_args) {
            $wp_remote_request_args = $args;
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'client_secret' => 'pi_test123_secret',
                    'status' => 'requires_payment_method',
                    'amount' => 10000,
                    'currency' => 'usd',
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
            'description' => 'Test payment',
            'email' => 'customer@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['payment_id']);
        $this->assertArrayHasKey('client_secret', $result);
    }

    public function test_create_payment_converts_amount_to_cents()
    {
        set_wp_remote_request_handler(function($url, $args) {
            $body = $args['body'];
            parse_str($body, $params);

            // Verify amount is in cents
            $this->assertEquals('10000', $params['amount']);

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'client_secret' => 'secret',
                    'status' => 'requires_payment_method',
                    'amount' => 10000,
                    'currency' => 'usd',
                ]),
            ];
        });

        $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
        ]);
    }

    public function test_create_payment_handles_zero_decimal_currencies()
    {
        set_wp_remote_request_handler(function($url, $args) {
            $body = $args['body'];
            parse_str($body, $params);

            // JPY should not be multiplied by 100
            $this->assertEquals('1000', $params['amount']);

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'client_secret' => 'secret',
                    'status' => 'requires_payment_method',
                    'amount' => 1000,
                    'currency' => 'jpy',
                ]),
            ];
        });

        $this->provider->createPayment([
            'amount' => 1000,
            'currency' => 'JPY',
        ]);
    }

    public function test_create_payment_handles_api_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'error' => [
                        'message' => 'Invalid amount',
                        'code' => 'amount_too_small',
                    ],
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 0.01,
            'currency' => 'USD',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid amount', $result['error']);
        $this->assertEquals('amount_too_small', $result['code']);
    }

    public function test_create_payment_handles_network_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return new \WP_Error('http_request_failed', 'Connection timeout');
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // ==========================================================================
    // Capture Tests
    // ==========================================================================

    public function test_capture_payment_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'amount_received' => 10000,
                    'currency' => 'usd',
                ]),
            ];
        });

        $result = $this->provider->capturePayment('pi_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['payment_id']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertEquals(100.00, $result['amount_captured']);
    }

    public function test_capture_payment_failure()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'error' => [
                        'message' => 'This payment intent cannot be captured',
                    ],
                ]),
            ];
        });

        $result = $this->provider->capturePayment('pi_test123');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    // ==========================================================================
    // Refund Tests
    // ==========================================================================

    public function test_refund_payment_full_refund()
    {
        // Mock getPayment first
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/payment_intents/') !== false && $args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'id' => 'pi_test123',
                        'status' => 'succeeded',
                        'amount' => 10000,
                        'currency' => 'usd',
                    ]),
                ];
            }

            // Refund request
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 're_test123',
                    'status' => 'succeeded',
                    'amount' => 5000,
                    'currency' => 'usd',
                ]),
            ];
        });

        $result = $this->provider->refundPayment('pi_test123', 50.00);

        $this->assertTrue($result['success']);
        $this->assertEquals('re_test123', $result['refund_id']);
        $this->assertEquals(50.00, $result['amount']);
    }

    public function test_refund_payment_partial_refund()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/payment_intents/') !== false && $args['method'] === 'GET') {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'id' => 'pi_test123',
                        'currency' => 'usd',
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 're_test123',
                    'status' => 'succeeded',
                    'amount' => 2500,
                    'currency' => 'usd',
                ]),
            ];
        });

        $result = $this->provider->refundPayment('pi_test123', 25.00);

        $this->assertTrue($result['success']);
        $this->assertEquals(25.00, $result['amount']);
    }

    // ==========================================================================
    // Payment Retrieval Tests
    // ==========================================================================

    public function test_get_payment_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'amount' => 10000,
                    'currency' => 'usd',
                    'customer' => 'cus_test123',
                    'payment_method' => 'pm_test123',
                    'metadata' => ['order_id' => '123'],
                    'created' => 1234567890,
                ]),
            ];
        });

        $result = $this->provider->getPayment('pi_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['payment_id']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertEquals(100.00, $result['amount']);
        $this->assertEquals('cus_test123', $result['customer_id']);
    }

    // ==========================================================================
    // Customer Tests
    // ==========================================================================

    public function test_create_customer_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'cus_test123',
                    'email' => 'customer@example.com',
                ]),
            ];
        });

        $result = $this->provider->createCustomer([
            'email' => 'customer@example.com',
            'name' => 'John Doe',
            'phone' => '+1234567890',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('cus_test123', $result['customer_id']);
        $this->assertEquals('customer@example.com', $result['email']);
    }

    public function test_get_customer_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'cus_test123',
                    'email' => 'customer@example.com',
                    'name' => 'John Doe',
                    'phone' => '+1234567890',
                    'metadata' => [],
                    'default_source' => 'card_123',
                ]),
            ];
        });

        $result = $this->provider->getCustomer('cus_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('cus_test123', $result['customer_id']);
        $this->assertEquals('customer@example.com', $result['email']);
    }

    // ==========================================================================
    // Subscription Tests
    // ==========================================================================

    public function test_create_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'sub_test123',
                    'status' => 'active',
                    'current_period_start' => 1234567890,
                    'current_period_end' => 1237159890,
                    'trial_start' => null,
                    'trial_end' => null,
                    'latest_invoice' => [
                        'payment_intent' => [
                            'client_secret' => 'secret_123',
                        ],
                    ],
                ]),
            ];
        });

        $result = $this->provider->createSubscription([
            'customer_id' => 'cus_test123',
            'items' => [
                ['price_id' => 'price_monthly'],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('sub_test123', $result['subscription_id']);
        $this->assertEquals('active', $result['status']);
    }

    public function test_cancel_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'sub_test123',
                    'status' => 'canceled',
                ]),
            ];
        });

        $result = $this->provider->cancelSubscription('sub_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('sub_test123', $result['subscription_id']);
        $this->assertEquals('canceled', $result['status']);
    }

    public function test_get_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'sub_test123',
                    'status' => 'active',
                    'customer' => 'cus_test123',
                    'current_period_start' => 1234567890,
                    'current_period_end' => 1237159890,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'trial_start' => null,
                    'trial_end' => null,
                    'items' => ['data' => []],
                    'latest_invoice' => null,
                    'metadata' => [],
                ]),
            ];
        });

        $result = $this->provider->getSubscription('sub_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('sub_test123', $result['subscription_id']);
        $this->assertEquals('active', $result['status']);
    }

    // ==========================================================================
    // Checkout Session Tests
    // ==========================================================================

    public function test_create_checkout_session_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'cs_test123',
                    'url' => 'https://checkout.stripe.com/pay/cs_test123',
                    'expires_at' => 1234567890,
                ]),
            ];
        });

        $result = $this->provider->createCheckoutSession([
            'mode' => 'payment',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'line_items' => [
                [
                    'name' => 'Product A',
                    'price' => 100.00,
                    'currency' => 'USD',
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('cs_test123', $result['session_id']);
        $this->assertArrayHasKey('url', $result);
    }

    // ==========================================================================
    // Webhook Tests
    // ==========================================================================

    public function test_handle_webhook_with_invalid_signature()
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ]);

        $signature = 't=1234567890,v1=invalidsignature';

        $result = $this->provider->handleWebhook($payload, $signature);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_handle_webhook_with_old_timestamp()
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ]);

        $old_timestamp = time() - 400; // 6 minutes ago
        $signature = "t={$old_timestamp},v1=test";

        $result = $this->provider->handleWebhook($payload, $signature);

        $this->assertFalse($result['success']);
        $this->assertEquals('Webhook timestamp too old', $result['error']);
    }

    public function test_handle_webhook_with_valid_signature()
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_123']],
            'created' => 1234567890,
        ]);

        $timestamp = time();
        $signed_payload = "{$timestamp}.{$payload}";
        $signature_value = hash_hmac('sha256', $signed_payload, 'whsec_test_123456789');
        $signature = "t={$timestamp},v1={$signature_value}";

        $result = $this->provider->handleWebhook($payload, $signature);

        $this->assertTrue($result['success']);
        $this->assertEquals('evt_test123', $result['event_id']);
        $this->assertEquals('payment_intent.succeeded', $result['event_type']);
    }

    public function test_handle_webhook_without_secret()
    {
        set_option('ffp_stripe_test_webhook_secret', '');
        $provider = new StripeProvider();

        $result = $provider->handleWebhook('payload', 'signature');

        $this->assertFalse($result['success']);
        $this->assertEquals('Webhook secret not configured', $result['error']);
    }

    // ==========================================================================
    // Amount Conversion Tests
    // ==========================================================================

    public function test_convert_to_stripe_amount_standard_currency()
    {
        // This is tested indirectly through createPayment
        set_wp_remote_request_handler(function($url, $args) {
            parse_str($args['body'], $params);
            $this->assertEquals('12345', $params['amount']); // 123.45 * 100

            return [
                'response' => ['code' => 200],
                'body' => json_encode(['id' => 'pi_test', 'status' => 'requires_payment_method', 'amount' => 12345, 'currency' => 'usd', 'client_secret' => 'secret']),
            ];
        });

        $this->provider->createPayment(['amount' => 123.45, 'currency' => 'USD']);
    }

    public function test_convert_from_stripe_amount_standard_currency()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'amount' => 12345,
                    'currency' => 'usd',
                    'customer' => null,
                    'payment_method' => null,
                    'metadata' => [],
                    'created' => 1234567890,
                ]),
            ];
        });

        $result = $this->provider->getPayment('pi_test123');
        $this->assertEquals(123.45, $result['amount']);
    }

    // ==========================================================================
    // Product and Price Tests
    // ==========================================================================

    public function test_create_product_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'prod_test123',
                ]),
            ];
        });

        $result = $this->provider->createProduct([
            'name' => 'Test Product',
            'description' => 'A test product',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('prod_test123', $result['product_id']);
    }

    public function test_create_price_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'price_test123',
                ]),
            ];
        });

        $result = $this->provider->createPrice([
            'product_id' => 'prod_test123',
            'unit_amount' => 29.99,
            'currency' => 'USD',
            'recurring' => [
                'interval' => 'month',
                'interval_count' => 1,
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('price_test123', $result['price_id']);
    }

    // ==========================================================================
    // Invoice Tests
    // ==========================================================================

    public function test_create_invoice_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'in_test123',
                    'status' => 'draft',
                    'hosted_invoice_url' => 'https://invoice.stripe.com/i/test123',
                ]),
            ];
        });

        $result = $this->provider->createInvoice([
            'customer_id' => 'cus_test123',
            'description' => 'Test invoice',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('in_test123', $result['invoice_id']);
        $this->assertEquals('draft', $result['status']);
    }

    public function test_add_invoice_item_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'ii_test123',
                ]),
            ];
        });

        $result = $this->provider->addInvoiceItem([
            'customer_id' => 'cus_test123',
            'amount' => 50.00,
            'currency' => 'USD',
            'description' => 'Service fee',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('ii_test123', $result['invoice_item_id']);
    }

    public function test_finalize_invoice_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'in_test123',
                    'status' => 'open',
                    'hosted_invoice_url' => 'https://invoice.stripe.com/i/test123',
                    'invoice_pdf' => 'https://invoice.stripe.com/i/test123/pdf',
                ]),
            ];
        });

        $result = $this->provider->finalizeInvoice('in_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('in_test123', $result['invoice_id']);
        $this->assertEquals('open', $result['status']);
    }
}
