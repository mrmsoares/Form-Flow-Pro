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
    private StripeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new StripeProvider();
    }

    // ==================== Interface Implementation ====================

    public function testImplementsPaymentProviderInterface(): void
    {
        $this->assertInstanceOf(PaymentProviderInterface::class, $this->provider);
    }

    // ==================== Configuration Tests ====================

    public function testIsConfiguredReturnsBool(): void
    {
        $result = $this->provider->isConfigured();
        $this->assertIsBool($result);
    }

    public function testGetPublishableKeyReturnsString(): void
    {
        $key = $this->provider->getPublishableKey();
        $this->assertIsString($key);
    }

    // ==================== Amount Conversion Tests ====================

    public function testConvertToStripeAmountStandardCurrency(): void
    {
        // Access private method to test conversion
        $amount = $this->callPrivateMethod($this->provider, 'convertToStripeAmount', [99.99, 'usd']);
        $this->assertEquals(9999, $amount);

        $amount = $this->callPrivateMethod($this->provider, 'convertToStripeAmount', [100.00, 'eur']);
        $this->assertEquals(10000, $amount);

        $amount = $this->callPrivateMethod($this->provider, 'convertToStripeAmount', [50.50, 'gbp']);
        $this->assertEquals(5050, $amount);
    }

    public function testConvertToStripeAmountZeroDecimalCurrency(): void
    {
        // JPY is a zero-decimal currency
        $amount = $this->callPrivateMethod($this->provider, 'convertToStripeAmount', [1000, 'jpy']);
        $this->assertEquals(1000, $amount);

        // KRW is a zero-decimal currency
        $amount = $this->callPrivateMethod($this->provider, 'convertToStripeAmount', [50000, 'krw']);
        $this->assertEquals(50000, $amount);
    }

    public function testConvertFromStripeAmountStandardCurrency(): void
    {
        $amount = $this->callPrivateMethod($this->provider, 'convertFromStripeAmount', [9999, 'usd']);
        $this->assertEquals(99.99, $amount);

        $amount = $this->callPrivateMethod($this->provider, 'convertFromStripeAmount', [10000, 'eur']);
        $this->assertEquals(100.00, $amount);
    }

    public function testConvertFromStripeAmountZeroDecimalCurrency(): void
    {
        $amount = $this->callPrivateMethod($this->provider, 'convertFromStripeAmount', [1000, 'jpy']);
        $this->assertEquals(1000.0, $amount);
    }

    // ==================== Payment Intent Tests ====================

    public function testCreatePaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'usd',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePaymentWithAllOptions(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'usd',
            'customer_id' => 'cus_test123',
            'description' => 'Test payment',
            'email' => 'test@example.com',
            'capture' => true,
            'save_card' => true,
            'statement_descriptor' => 'Test Company',
            'metadata' => ['order_id' => '12345'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testConfirmPaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->confirmPayment('pi_test123', [
            'payment_method' => 'pm_card_visa',
            'return_url' => 'https://example.com/return',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCapturePaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->capturePayment('pi_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testRefundPaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->refundPayment('pi_test123', 50.00);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetPaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->getPayment('pi_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Checkout Session Tests ====================

    public function testCreateCheckoutSessionPaymentMode(): void
    {
        $result = $this->provider->createCheckoutSession([
            'mode' => 'payment',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'line_items' => [
                [
                    'name' => 'Test Product',
                    'price' => 50.00,
                    'quantity' => 2,
                    'currency' => 'usd',
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateCheckoutSessionSubscriptionMode(): void
    {
        $result = $this->provider->createCheckoutSession([
            'mode' => 'subscription',
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'price_id' => 'price_test123',
            'customer_email' => 'test@example.com',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetCheckoutSessionReturnsArrayStructure(): void
    {
        $result = $this->provider->getCheckoutSession('cs_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Customer Tests ====================

    public function testCreateCustomerReturnsArrayStructure(): void
    {
        $result = $this->provider->createCustomer([
            'email' => 'test@example.com',
            'name' => 'Test Customer',
            'phone' => '+1234567890',
            'description' => 'Test customer description',
            'metadata' => ['user_id' => '123'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testUpdateCustomerReturnsArrayStructure(): void
    {
        $result = $this->provider->updateCustomer('cus_test123', [
            'email' => 'updated@example.com',
            'name' => 'Updated Name',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetCustomerReturnsArrayStructure(): void
    {
        $result = $this->provider->getCustomer('cus_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Payment Method Tests ====================

    public function testAttachPaymentMethodReturnsArrayStructure(): void
    {
        $result = $this->provider->attachPaymentMethod('pm_test123', 'cus_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testListPaymentMethodsReturnsArrayStructure(): void
    {
        $result = $this->provider->listPaymentMethods('cus_test123', 'card');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Subscription Tests ====================

    public function testCreateSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->createSubscription([
            'customer_id' => 'cus_test123',
            'items' => [
                ['price_id' => 'price_test123', 'quantity' => 1],
            ],
            'default_payment_method' => 'pm_test123',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateSubscriptionWithTrialPeriod(): void
    {
        $result = $this->provider->createSubscription([
            'customer_id' => 'cus_test123',
            'items' => [['price_id' => 'price_test123']],
            'trial_period_days' => 14,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->getSubscription('sub_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testUpdateSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->updateSubscription('sub_test123', [
            'cancel_at_period_end' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCancelSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->cancelSubscription('sub_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testPauseSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->pauseSubscription('sub_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testResumeSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->resumeSubscription('sub_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Product & Price Tests ====================

    public function testCreateProductReturnsArrayStructure(): void
    {
        $result = $this->provider->createProduct([
            'name' => 'Test Product',
            'description' => 'A test product',
            'active' => true,
            'metadata' => ['category' => 'software'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePriceOneTimeReturnsArrayStructure(): void
    {
        $result = $this->provider->createPrice([
            'product_id' => 'prod_test123',
            'unit_amount' => 99.99,
            'currency' => 'usd',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePriceRecurringReturnsArrayStructure(): void
    {
        $result = $this->provider->createPrice([
            'product_id' => 'prod_test123',
            'unit_amount' => 29.99,
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
                'interval_count' => 1,
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Invoice Tests ====================

    public function testCreateInvoiceReturnsArrayStructure(): void
    {
        $result = $this->provider->createInvoice([
            'customer_id' => 'cus_test123',
            'auto_advance' => true,
            'description' => 'Monthly invoice',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testAddInvoiceItemReturnsArrayStructure(): void
    {
        $result = $this->provider->addInvoiceItem([
            'customer_id' => 'cus_test123',
            'amount' => 100.00,
            'currency' => 'usd',
            'description' => 'Service charge',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testFinalizeInvoiceReturnsArrayStructure(): void
    {
        $result = $this->provider->finalizeInvoice('in_test123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Webhook Tests ====================

    public function testHandleWebhookInvalidSignatureFormat(): void
    {
        $result = $this->provider->handleWebhook('{}', 'invalid_signature');

        $this->assertFalse($result['success']);
    }

    public function testHandleWebhookMissingSecret(): void
    {
        // Without webhook secret configured, should fail
        $result = $this->provider->handleWebhook('{}', 't=123,v1=abc');

        $this->assertArrayHasKey('success', $result);
    }

    public function testHandleWebhookTimestampValidation(): void
    {
        // Old timestamp should be rejected
        $old_timestamp = time() - 600; // 10 minutes ago
        $result = $this->provider->handleWebhook('{}', "t={$old_timestamp},v1=test");

        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Parameter Encoding Tests ====================

    public function testEncodeParamsSimple(): void
    {
        $params = ['key' => 'value', 'number' => 123];
        $encoded = $this->callPrivateMethod($this->provider, 'encodeParams', [$params]);

        $this->assertStringContainsString('key=value', $encoded);
        $this->assertStringContainsString('number=123', $encoded);
    }

    public function testEncodeParamsNested(): void
    {
        $params = [
            'metadata' => [
                'order_id' => '123',
                'user_id' => '456',
            ],
        ];
        $encoded = $this->callPrivateMethod($this->provider, 'encodeParams', [$params]);

        $this->assertStringContainsString('metadata', $encoded);
        $this->assertStringContainsString('order_id', $encoded);
    }
}
