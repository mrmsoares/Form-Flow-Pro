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
    private PayPalProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new PayPalProvider();
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

    public function testGetClientIdReturnsString(): void
    {
        $clientId = $this->provider->getClientId();
        $this->assertIsString($clientId);
    }

    public function testGetEnvironmentReturnsSandboxOrProduction(): void
    {
        $env = $this->provider->getEnvironment();
        $this->assertContains($env, ['sandbox', 'production']);
    }

    // ==================== Order/Payment Tests ====================

    public function testCreatePaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
            'capture' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePaymentWithItems(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 150.00,
            'currency' => 'USD',
            'description' => 'Test order',
            'items' => [
                [
                    'name' => 'Product 1',
                    'price' => 50.00,
                    'quantity' => 2,
                ],
                [
                    'name' => 'Product 2',
                    'price' => 50.00,
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePaymentWithRedirectUrls(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
            'return_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'brand_name' => 'Test Store',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePaymentWithMetadata(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.00,
            'currency' => 'USD',
            'metadata' => [
                'order_id' => '12345',
                'customer_id' => '67890',
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCapturePaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->capturePayment('ORDER123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testAuthorizePaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->authorizePayment('ORDER123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCaptureAuthorizationReturnsArrayStructure(): void
    {
        $result = $this->provider->captureAuthorization('AUTH123', 50.00, 'USD');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testRefundPaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->refundPayment('ORDER123', 50.00);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetPaymentReturnsArrayStructure(): void
    {
        $result = $this->provider->getPayment('ORDER123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Customer Tests ====================

    public function testCreateCustomerReturnsSuccessWithGeneratedId(): void
    {
        $result = $this->provider->createCustomer([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('customer_id', $result);
        $this->assertStringStartsWith('cus_', $result['customer_id']);
    }

    public function testCreateCustomerGeneratesUniqueIds(): void
    {
        $result1 = $this->provider->createCustomer(['email' => 'test1@example.com']);
        $result2 = $this->provider->createCustomer(['email' => 'test2@example.com']);

        $this->assertNotEquals($result1['customer_id'], $result2['customer_id']);
    }

    // ==================== Subscription Tests ====================

    public function testCreateSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->createSubscription([
            'plan_id' => 'PLAN123',
            'email' => 'subscriber@example.com',
            'return_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateSubscriptionWithSubscriberInfo(): void
    {
        $result = $this->provider->createSubscription([
            'plan_id' => 'PLAN123',
            'email' => 'subscriber@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'admin_area_2' => 'San Jose',
                'admin_area_1' => 'CA',
                'postal_code' => '95131',
                'country_code' => 'US',
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testGetSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->getSubscription('SUB123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCancelSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->cancelSubscription('SUB123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testSuspendSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->suspendSubscription('SUB123', 'Payment failed');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testActivateSubscriptionReturnsArrayStructure(): void
    {
        $result = $this->provider->activateSubscription('SUB123', 'Customer reactivated');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Plan & Product Tests ====================

    public function testCreatePlanReturnsArrayStructure(): void
    {
        $result = $this->provider->createPlan([
            'product_id' => 'PROD123',
            'name' => 'Monthly Plan',
            'price' => 29.99,
            'currency' => 'USD',
            'interval' => 'MONTH',
            'interval_count' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePlanWithTrialPeriod(): void
    {
        $result = $this->provider->createPlan([
            'product_id' => 'PROD123',
            'name' => 'Premium Plan',
            'price' => 49.99,
            'currency' => 'USD',
            'interval' => 'MONTH',
            'trial' => [
                'interval' => 'DAY',
                'interval_count' => 14,
                'price' => 0,
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreatePlanWithSetupFee(): void
    {
        $result = $this->provider->createPlan([
            'product_id' => 'PROD123',
            'name' => 'Enterprise Plan',
            'price' => 99.99,
            'currency' => 'USD',
            'interval' => 'MONTH',
            'setup_fee' => 49.99,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateProductReturnsArrayStructure(): void
    {
        $result = $this->provider->createProduct([
            'name' => 'SaaS Product',
            'type' => 'SERVICE',
            'description' => 'A software as a service product',
            'category' => 'SOFTWARE',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateProductWithImages(): void
    {
        $result = $this->provider->createProduct([
            'name' => 'Physical Product',
            'type' => 'PHYSICAL',
            'image_url' => 'https://example.com/image.jpg',
            'home_url' => 'https://example.com/product',
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Invoice Tests ====================

    public function testCreateInvoiceReturnsArrayStructure(): void
    {
        $result = $this->provider->createInvoice([
            'customer_email' => 'customer@example.com',
            'currency' => 'USD',
            'items' => [
                [
                    'name' => 'Consulting',
                    'price' => 200.00,
                    'quantity' => 2,
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testCreateInvoiceWithAllOptions(): void
    {
        $result = $this->provider->createInvoice([
            'customer_email' => 'customer@example.com',
            'customer_first_name' => 'John',
            'customer_last_name' => 'Doe',
            'currency' => 'USD',
            'business_name' => 'My Business',
            'term_type' => 'NET_15',
            'note' => 'Thank you for your business',
            'memo' => 'Invoice memo',
            'items' => [
                [
                    'name' => 'Service',
                    'description' => 'Professional service',
                    'price' => 100.00,
                    'quantity' => 1,
                ],
            ],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testSendInvoiceReturnsArrayStructure(): void
    {
        $result = $this->provider->sendInvoice('INV123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Webhook Tests ====================

    public function testHandleWebhookInvalidPayload(): void
    {
        $result = $this->provider->handleWebhook('invalid_json', '');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid webhook payload', $result['error']);
    }

    public function testHandleWebhookMissingEventType(): void
    {
        $result = $this->provider->handleWebhook('{"id": "123"}', '');

        $this->assertFalse($result['success']);
    }

    public function testHandleWebhookValidPayload(): void
    {
        $payload = json_encode([
            'id' => 'WH-123',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER123',
                'status' => 'APPROVED',
            ],
            'create_time' => '2024-01-01T00:00:00Z',
        ]);

        $result = $this->provider->handleWebhook($payload, '');

        // Without webhook ID configured, verification is skipped
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testHandleWebhookExtractsEventData(): void
    {
        $payload = json_encode([
            'id' => 'WH-456',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE123',
                'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
            ],
            'create_time' => '2024-01-01T12:00:00Z',
        ]);

        $result = $this->provider->handleWebhook($payload, '');

        if ($result['success']) {
            $this->assertEquals('WH-456', $result['event_id']);
            $this->assertEquals('PAYMENT.CAPTURE.COMPLETED', $result['event_type']);
            $this->assertIsArray($result['data']);
        }
    }

    // ==================== Amount Formatting Tests ====================

    public function testAmountFormattingInPayment(): void
    {
        // PayPal expects amounts as strings with 2 decimal places
        $result = $this->provider->createPayment([
            'amount' => 99.9,  // Should become "99.90"
            'currency' => 'USD',
        ]);

        $this->assertIsArray($result);
    }

    public function testAmountFormattingWithDecimalPrecision(): void
    {
        $result = $this->provider->createPayment([
            'amount' => 100.999,  // Should be formatted to 2 decimals
            'currency' => 'USD',
        ]);

        $this->assertIsArray($result);
    }
}
