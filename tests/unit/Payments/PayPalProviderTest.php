<?php
/**
 * Tests for PayPalProvider class.
 *
 * @package FormFlowPro\Tests\Unit\Payments
 */

namespace FormFlowPro\Tests\Unit\Payments;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Payments\PayPalProvider;
use FormFlowPro\Payments\PaymentProviderInterface;

class PayPalProviderTest extends TestCase
{
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up sandbox credentials
        set_option('ffp_paypal_sandbox', true);
        set_option('ffp_paypal_sandbox_client_id', 'AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS');
        set_option('ffp_paypal_sandbox_client_secret', 'EGnHDxD_qRPdaLdZz8iCr8N7_MzF-YHPTkjs6NKYQvQSBngp4PTTVWkPZRbL');
        set_option('ffp_paypal_sandbox_webhook_id', 'WH-12345678');

        $this->provider = new PayPalProvider();
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
        set_option('ffp_paypal_sandbox_client_id', '');
        set_option('ffp_paypal_sandbox_client_secret', '');

        $provider = new PayPalProvider();
        $this->assertFalse($provider->isConfigured());
    }

    public function test_get_client_id()
    {
        $clientId = $this->provider->getClientId();
        $this->assertEquals('AYSq3RDGsmBLJE-otTkBtM-jBRd1TCQwFf9RGfwddNXWz0uFU9ztymylOhRS', $clientId);
    }

    public function test_get_environment_sandbox()
    {
        $env = $this->provider->getEnvironment();
        $this->assertEquals('sandbox', $env);
    }

    public function test_get_environment_production()
    {
        set_option('ffp_paypal_sandbox', false);
        $provider = new PayPalProvider();
        $env = $provider->getEnvironment();
        $this->assertEquals('production', $env);
    }

    // ==========================================================================
    // Access Token Tests
    // ==========================================================================

    public function test_get_access_token_success()
    {
        set_wp_remote_post_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'A21AAK9zVHbxj_test_token',
                        'token_type' => 'Bearer',
                        'expires_in' => 32400,
                    ]),
                ];
            }
            return ['response' => ['code' => 400], 'body' => '{}'];
        });

        // Create payment to trigger token fetch
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'test_token',
                        'expires_in' => 32400,
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'ORDER-123',
                    'status' => 'CREATED',
                    'links' => [
                        ['rel' => 'approve', 'href' => 'https://paypal.com/approve'],
                    ],
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->assertTrue($result['success']);
    }

    // ==========================================================================
    // Order/Payment Tests
    // ==========================================================================

    public function test_create_payment_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'access_token' => 'test_token',
                        'expires_in' => 32400,
                    ]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => '5O190127TN364715T',
                    'status' => 'CREATED',
                    'links' => [
                        ['rel' => 'approve', 'href' => 'https://www.paypal.com/checkoutnow?token=5O190127TN364715T'],
                        ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/checkout/orders/5O190127TN364715T'],
                    ],
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
            'description' => 'Test order',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('5O190127TN364715T', $result['payment_id']);
        $this->assertEquals('CREATED', $result['status']);
        $this->assertArrayHasKey('approve_url', $result);
    }

    public function test_create_payment_with_items()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            $body = json_decode($args['body'], true);
            $this->assertArrayHasKey('purchase_units', $body);
            $this->assertArrayHasKey('items', $body['purchase_units'][0]);

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'ORDER-123',
                    'status' => 'CREATED',
                    'links' => [['rel' => 'approve', 'href' => 'https://paypal.com/approve']],
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 150.00,
            'currency' => 'USD',
            'items' => [
                ['name' => 'Product A', 'price' => 50.00, 'quantity' => 2],
                ['name' => 'Product B', 'price' => 50.00, 'quantity' => 1],
            ],
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_create_payment_handles_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 400],
                'body' => json_encode([
                    'name' => 'INVALID_REQUEST',
                    'message' => 'Request is not well-formed',
                    'details' => [
                        ['description' => 'Amount is required'],
                    ],
                ]),
            ];
        });

        $result = $this->provider->createPayment([
            'amount' => 0,
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
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => '5O190127TN364715T',
                    'status' => 'COMPLETED',
                    'purchase_units' => [
                        [
                            'payments' => [
                                'captures' => [
                                    [
                                        'id' => '3C679366HH908993F',
                                        'status' => 'COMPLETED',
                                        'amount' => [
                                            'value' => '100.00',
                                            'currency_code' => 'USD',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ];
        });

        $result = $this->provider->capturePayment('5O190127TN364715T');

        $this->assertTrue($result['success']);
        $this->assertEquals('5O190127TN364715T', $result['payment_id']);
        $this->assertEquals('3C679366HH908993F', $result['capture_id']);
        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals(100.00, $result['amount']);
    }

    // ==========================================================================
    // Refund Tests
    // ==========================================================================

    public function test_refund_payment_success()
    {
        // First call: getPayment
        // Second call: refund
        $callCount = 0;
        set_wp_remote_request_handler(function($url, $args) use (&$callCount) {
            $callCount++;

            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            // First call - getPayment
            if ($callCount <= 2 && strpos($url, '/checkout/orders/') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode([
                        'id' => 'ORDER-123',
                        'status' => 'COMPLETED',
                        'purchase_units' => [
                            [
                                'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                                'payments' => [
                                    'captures' => [
                                        ['id' => 'CAPTURE-123'],
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ];
            }

            // Second call - refund
            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'REFUND-123',
                    'status' => 'COMPLETED',
                    'amount' => [
                        'value' => '50.00',
                        'currency_code' => 'USD',
                    ],
                ]),
            ];
        });

        $result = $this->provider->refundPayment('ORDER-123', 50.00);

        $this->assertTrue($result['success']);
        $this->assertEquals('REFUND-123', $result['refund_id']);
        $this->assertEquals(50.00, $result['amount']);
    }

    public function test_refund_payment_no_capture_found()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'ORDER-123',
                    'status' => 'CREATED',
                    'purchase_units' => [
                        [
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                            'payments' => [],
                        ],
                    ],
                ]),
            ];
        });

        $result = $this->provider->refundPayment('ORDER-123');

        $this->assertFalse($result['success']);
        $this->assertEquals('No capture found for this payment', $result['error']);
    }

    // ==========================================================================
    // Payment Retrieval Tests
    // ==========================================================================

    public function test_get_payment_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => '5O190127TN364715T',
                    'status' => 'COMPLETED',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'value' => '100.00',
                                'currency_code' => 'USD',
                            ],
                            'custom_id' => '{"order_id":"123"}',
                            'payments' => [
                                'captures' => [
                                    ['id' => 'CAPTURE-123'],
                                ],
                            ],
                        ],
                    ],
                    'payer' => [
                        'email_address' => 'buyer@example.com',
                        'payer_id' => 'PAYER123',
                    ],
                    'create_time' => '2024-01-01T12:00:00Z',
                ]),
            ];
        });

        $result = $this->provider->getPayment('5O190127TN364715T');

        $this->assertTrue($result['success']);
        $this->assertEquals('5O190127TN364715T', $result['payment_id']);
        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals(100.00, $result['amount']);
        $this->assertEquals('buyer@example.com', $result['payer_email']);
    }

    // ==========================================================================
    // Customer Tests
    // ==========================================================================

    public function test_create_customer_success()
    {
        // PayPal doesn't have traditional customers, so this generates an ID
        $result = $this->provider->createCustomer([
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('customer_id', $result);
        $this->assertEquals('customer@example.com', $result['email']);
    }

    // ==========================================================================
    // Subscription Tests
    // ==========================================================================

    public function test_create_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'I-BW452GLLEP1G',
                    'status' => 'APPROVAL_PENDING',
                    'links' => [
                        ['rel' => 'approve', 'href' => 'https://www.paypal.com/webapps/billing/subscriptions?ba_token=BA-123'],
                    ],
                ]),
            ];
        });

        $result = $this->provider->createSubscription([
            'plan_id' => 'P-5ML4271244454362WXNWU5NQ',
            'email' => 'subscriber@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('I-BW452GLLEP1G', $result['subscription_id']);
        $this->assertEquals('APPROVAL_PENDING', $result['status']);
        $this->assertArrayHasKey('approve_url', $result);
    }

    public function test_get_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'I-BW452GLLEP1G',
                    'status' => 'ACTIVE',
                    'plan_id' => 'P-5ML4271244454362WXNWU5NQ',
                    'subscriber' => [
                        'email_address' => 'subscriber@example.com',
                    ],
                    'start_time' => '2024-01-01T00:00:00Z',
                    'billing_info' => [],
                    'custom_id' => '{"user_id":"123"}',
                ]),
            ];
        });

        $result = $this->provider->getSubscription('I-BW452GLLEP1G');

        $this->assertTrue($result['success']);
        $this->assertEquals('I-BW452GLLEP1G', $result['subscription_id']);
        $this->assertEquals('ACTIVE', $result['status']);
    }

    public function test_cancel_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return ['response' => ['code' => 204], 'body' => ''];
        });

        $result = $this->provider->cancelSubscription('I-BW452GLLEP1G');

        $this->assertTrue($result['success']);
        $this->assertEquals('I-BW452GLLEP1G', $result['subscription_id']);
        $this->assertEquals('CANCELLED', $result['status']);
    }

    public function test_suspend_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return ['response' => ['code' => 204], 'body' => ''];
        });

        $result = $this->provider->suspendSubscription('I-BW452GLLEP1G', 'Customer request');

        $this->assertTrue($result['success']);
        $this->assertEquals('SUSPENDED', $result['status']);
    }

    public function test_activate_subscription_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return ['response' => ['code' => 204], 'body' => ''];
        });

        $result = $this->provider->activateSubscription('I-BW452GLLEP1G');

        $this->assertTrue($result['success']);
        $this->assertEquals('ACTIVE', $result['status']);
    }

    // ==========================================================================
    // Plan Tests
    // ==========================================================================

    public function test_create_plan_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'P-5ML4271244454362WXNWU5NQ',
                    'status' => 'ACTIVE',
                ]),
            ];
        });

        $result = $this->provider->createPlan([
            'product_id' => 'PROD-123',
            'name' => 'Monthly Plan',
            'price' => 29.99,
            'currency' => 'USD',
            'interval' => 'month',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('P-5ML4271244454362WXNWU5NQ', $result['plan_id']);
        $this->assertEquals('ACTIVE', $result['status']);
    }

    // ==========================================================================
    // Product Tests
    // ==========================================================================

    public function test_create_product_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'PROD-6XB24663H4094933M',
                ]),
            ];
        });

        $result = $this->provider->createProduct([
            'name' => 'Test Product',
            'description' => 'A test product',
            'type' => 'SERVICE',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('PROD-6XB24663H4094933M', $result['product_id']);
    }

    // ==========================================================================
    // Invoice Tests
    // ==========================================================================

    public function test_create_invoice_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'INV2-Z56S-5LLA-Q52L-CPZ5',
                    'status' => 'DRAFT',
                ]),
            ];
        });

        $result = $this->provider->createInvoice([
            'customer_email' => 'customer@example.com',
            'items' => [
                ['name' => 'Item 1', 'price' => 100.00, 'quantity' => 1],
            ],
            'currency' => 'USD',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('INV2-Z56S-5LLA-Q52L-CPZ5', $result['invoice_id']);
        $this->assertEquals('DRAFT', $result['status']);
    }

    public function test_send_invoice_success()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            return ['response' => ['code' => 204], 'body' => ''];
        });

        $result = $this->provider->sendInvoice('INV2-Z56S-5LLA-Q52L-CPZ5');

        $this->assertTrue($result['success']);
        $this->assertEquals('INV2-Z56S-5LLA-Q52L-CPZ5', $result['invoice_id']);
    }

    // ==========================================================================
    // Webhook Tests
    // ==========================================================================

    public function test_handle_webhook_invalid_payload()
    {
        $result = $this->provider->handleWebhook('invalid json', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid webhook payload', $result['error']);
    }

    public function test_handle_webhook_success()
    {
        $payload = json_encode([
            'id' => 'WH-2WR32451HC0233532-67976317FL4543714',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => '3C679366HH908993F',
                'status' => 'COMPLETED',
            ],
            'create_time' => '2024-01-01T12:00:00Z',
        ]);

        // Mock webhook verification to succeed
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            if (strpos($url, '/verify-webhook-signature') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['verification_status' => 'SUCCESS']),
                ];
            }

            return ['response' => ['code' => 400], 'body' => '{}'];
        });

        $result = $this->provider->handleWebhook($payload, 'test-signature');

        $this->assertTrue($result['success']);
        $this->assertEquals('WH-2WR32451HC0233532-67976317FL4543714', $result['event_id']);
        $this->assertEquals('PAYMENT.CAPTURE.COMPLETED', $result['event_type']);
    }

    public function test_handle_webhook_verification_failure()
    {
        $payload = json_encode([
            'id' => 'WH-123',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [],
        ]);

        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['access_token' => 'test_token', 'expires_in' => 32400]),
                ];
            }

            if (strpos($url, '/verify-webhook-signature') !== false) {
                return [
                    'response' => ['code' => 200],
                    'body' => json_encode(['verification_status' => 'FAILURE']),
                ];
            }

            return ['response' => ['code' => 400], 'body' => '{}'];
        });

        $result = $this->provider->handleWebhook($payload, 'invalid-signature');

        $this->assertFalse($result['success']);
        $this->assertEquals('Webhook signature verification failed', $result['error']);
    }

    // ==========================================================================
    // Error Handling Tests
    // ==========================================================================

    public function test_request_handles_network_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return new \WP_Error('http_request_failed', 'Connection timeout');
            }
            return new \WP_Error('http_request_failed', 'Connection timeout');
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_request_handles_401_error()
    {
        set_wp_remote_request_handler(function($url, $args) {
            if (strpos($url, '/oauth2/token') !== false) {
                return [
                    'response' => ['code' => 401],
                    'body' => json_encode([
                        'error' => 'invalid_client',
                        'error_description' => 'Client Authentication failed',
                    ]),
                ];
            }

            return ['response' => ['code' => 401], 'body' => '{}'];
        });

        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to obtain access token', $result['error']);
    }
}
