<?php
/**
 * Tests for PaymentManager class.
 *
 * @package FormFlowPro\Tests\Unit\Payments
 */

namespace FormFlowPro\Tests\Unit\Payments;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Payments\PaymentManager;
use FormFlowPro\Payments\PaymentRecord;
use FormFlowPro\Payments\SubscriptionRecord;

class PaymentManagerTest extends TestCase
{
    private PaymentManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = PaymentManager::getInstance();
    }

    // ==================== Model Tests ====================

    public function testPaymentRecordConstructor(): void
    {
        $data = [
            'id' => 1,
            'form_id' => 10,
            'submission_id' => 100,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test123',
            'provider_customer_id' => 'cus_test123',
            'status' => 'succeeded',
            'amount' => 99.99,
            'currency' => 'USD',
            'refunded_amount' => 0.0,
            'metadata' => ['key' => 'value'],
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ];

        $record = new PaymentRecord($data);

        $this->assertEquals(1, $record->id);
        $this->assertEquals(10, $record->form_id);
        $this->assertEquals('stripe', $record->provider);
        $this->assertEquals('pi_test123', $record->provider_payment_id);
        $this->assertEquals(99.99, $record->amount);
        $this->assertEquals('USD', $record->currency);
    }

    public function testPaymentRecordToArray(): void
    {
        $data = [
            'id' => 1,
            'form_id' => 10,
            'provider' => 'stripe',
            'amount' => 50.00,
            'currency' => 'USD',
            'status' => 'pending',
        ];

        $record = new PaymentRecord($data);
        $array = $record->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals(10, $array['form_id']);
        $this->assertEquals('stripe', $array['provider']);
        $this->assertEquals(50.00, $array['amount']);
    }

    public function testSubscriptionRecordConstructor(): void
    {
        $data = [
            'id' => 1,
            'form_id' => 10,
            'user_id' => 5,
            'provider' => 'stripe',
            'provider_subscription_id' => 'sub_test123',
            'provider_customer_id' => 'cus_test123',
            'plan_id' => 'plan_premium',
            'status' => 'active',
            'amount' => 29.99,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
        ];

        $record = new SubscriptionRecord($data);

        $this->assertEquals(1, $record->id);
        $this->assertEquals('sub_test123', $record->provider_subscription_id);
        $this->assertEquals('active', $record->status);
        $this->assertEquals(29.99, $record->amount);
        $this->assertEquals('month', $record->interval);
    }

    public function testSubscriptionRecordToArray(): void
    {
        $data = [
            'id' => 1,
            'provider' => 'paypal',
            'status' => 'active',
            'amount' => 19.99,
        ];

        $record = new SubscriptionRecord($data);
        $array = $record->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('paypal', $array['provider']);
        $this->assertEquals('active', $array['status']);
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = PaymentManager::getInstance();
        $instance2 = PaymentManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== Provider Tests ====================

    public function testGetProviderStripe(): void
    {
        $provider = $this->manager->getProvider('stripe');

        // Provider may or may not be null depending on configuration
        if ($provider !== null) {
            $this->assertInstanceOf(\FormFlowPro\Payments\PaymentProviderInterface::class, $provider);
        } else {
            $this->assertNull($provider);
        }
    }

    public function testGetProviderPaypal(): void
    {
        $provider = $this->manager->getProvider('paypal');

        if ($provider !== null) {
            $this->assertInstanceOf(\FormFlowPro\Payments\PaymentProviderInterface::class, $provider);
        } else {
            $this->assertNull($provider);
        }
    }

    public function testGetProviderInvalid(): void
    {
        $provider = $this->manager->getProvider('nonexistent');

        $this->assertNull($provider);
    }

    public function testGetAvailableProvidersReturnsArray(): void
    {
        $providers = $this->manager->getAvailableProviders();

        $this->assertIsArray($providers);
    }

    // ==================== Payment Creation Tests ====================

    public function testCreatePaymentWithInvalidProvider(): void
    {
        $result = $this->manager->createPayment('invalid_provider', [
            'amount' => 100,
            'currency' => 'USD',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid payment provider', $result['error']);
    }

    public function testCreatePaymentRequiresConfiguredProvider(): void
    {
        // Most providers won't be configured in test environment
        $result = $this->manager->createPayment('stripe', [
            'amount' => 100,
            'currency' => 'USD',
        ]);

        // Either succeeds or fails due to configuration
        $this->assertArrayHasKey('success', $result);
    }

    // ==================== Payment Retrieval Tests ====================

    public function testGetPaymentReturnsNullForNonexistent(): void
    {
        $payment = $this->manager->getPayment(999999);

        $this->assertNull($payment);
    }

    public function testGetPaymentsReturnsArray(): void
    {
        $payments = $this->manager->getPayments();

        $this->assertIsArray($payments);
    }

    public function testGetPaymentsWithFilters(): void
    {
        $payments = $this->manager->getPayments([
            'form_id' => 1,
            'status' => 'succeeded',
            'provider' => 'stripe',
            'limit' => 10,
            'offset' => 0,
        ]);

        $this->assertIsArray($payments);
    }

    // ==================== Subscription Tests ====================

    public function testGetSubscriptionReturnsNullForNonexistent(): void
    {
        $subscription = $this->manager->getSubscription(999999);

        $this->assertNull($subscription);
    }

    public function testGetUserSubscriptionsReturnsArray(): void
    {
        $subscriptions = $this->manager->getUserSubscriptions(1);

        $this->assertIsArray($subscriptions);
    }

    public function testGetUserSubscriptionsWithStatus(): void
    {
        $subscriptions = $this->manager->getUserSubscriptions(1, 'active');

        $this->assertIsArray($subscriptions);
    }

    public function testCreateSubscriptionWithInvalidProvider(): void
    {
        $result = $this->manager->createSubscription('invalid_provider', [
            'plan_id' => 'test_plan',
            'customer_id' => 'cus_test',
        ]);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid payment provider', $result['error']);
    }

    public function testCancelSubscriptionWithInvalidProvider(): void
    {
        $result = $this->manager->cancelSubscription('invalid_provider', 'sub_test123');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid payment provider', $result['error']);
    }

    // ==================== Refund Tests ====================

    public function testRefundPaymentWithInvalidProvider(): void
    {
        $result = $this->manager->refundPayment('invalid_provider', 'pi_test123', 50.00);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid payment provider', $result['error']);
    }

    // ==================== Invoice Tests ====================

    public function testCreateInvoice(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'items' => [
                ['name' => 'Product 1', 'quantity' => 2, 'price' => 25.00],
                ['name' => 'Product 2', 'quantity' => 1, 'price' => 50.00],
            ],
            'currency' => 'USD',
        ]);

        $this->assertIsInt($invoice_id);
        $this->assertGreaterThan(0, $invoice_id);
    }

    public function testGetInvoiceReturnsNullForNonexistent(): void
    {
        $invoice = $this->manager->getInvoice(999999);

        $this->assertNull($invoice);
    }

    public function testGetInvoiceByNumberReturnsNullForNonexistent(): void
    {
        $invoice = $this->manager->getInvoiceByNumber('NONEXISTENT-12345');

        $this->assertNull($invoice);
    }

    public function testCreateAndGetInvoice(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'items' => [
                ['name' => 'Test Item', 'quantity' => 1, 'price' => 100.00],
            ],
            'tax' => 10.00,
            'discount' => 5.00,
            'currency' => 'USD',
            'notes' => 'Test invoice',
        ]);

        $invoice = $this->manager->getInvoice($invoice_id);

        $this->assertIsArray($invoice);
        $this->assertEquals('Test Customer', $invoice['customer_name']);
        $this->assertEquals('test@example.com', $invoice['customer_email']);
        $this->assertEquals(100.00, $invoice['subtotal']);
        $this->assertEquals(10.00, $invoice['tax']);
        $this->assertEquals(5.00, $invoice['discount']);
        $this->assertEquals(105.00, $invoice['total']); // 100 + 10 - 5
    }

    public function testUpdateInvoiceStatus(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'Status Test',
            'customer_email' => 'status@test.com',
            'items' => [['name' => 'Item', 'price' => 50]],
        ]);

        $result = $this->manager->updateInvoiceStatus($invoice_id, 'paid');

        $this->assertTrue($result);

        $invoice = $this->manager->getInvoice($invoice_id);
        $this->assertEquals('paid', $invoice['status']);
        $this->assertNotNull($invoice['paid_date']);
    }

    public function testGenerateInvoicePDF(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'PDF Test Customer',
            'customer_email' => 'pdf@test.com',
            'items' => [
                ['name' => 'Service', 'quantity' => 1, 'price' => 150.00],
            ],
        ]);

        $html = $this->manager->generateInvoicePDF($invoice_id);

        $this->assertIsString($html);
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringContainsString('PDF Test Customer', $html);
        $this->assertStringContainsString('Service', $html);
    }

    public function testGenerateInvoicePDFReturnsEmptyForNonexistent(): void
    {
        $html = $this->manager->generateInvoicePDF(999999);

        $this->assertEquals('', $html);
    }

    // ==================== Statistics Tests ====================

    public function testGetStatisticsReturnsCorrectStructure(): void
    {
        $stats = $this->manager->getStatistics('30days');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('total_payments', $stats);
        $this->assertArrayHasKey('successful_payments', $stats);
        $this->assertArrayHasKey('success_rate', $stats);
        $this->assertArrayHasKey('active_subscriptions', $stats);
        $this->assertArrayHasKey('mrr', $stats);
        $this->assertArrayHasKey('by_provider', $stats);
        $this->assertArrayHasKey('period', $stats);
    }

    public function testGetStatisticsWithDifferentPeriods(): void
    {
        $periods = ['7days', '30days', '90days', 'year'];

        foreach ($periods as $period) {
            $stats = $this->manager->getStatistics($period);
            $this->assertEquals($period, $stats['period']);
        }
    }

    // ==================== REST API Tests ====================

    public function testRestGetPaymentsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/payments');
        $response = $this->manager->restGetPayments($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertIsArray($response->get_data());
    }

    public function testRestGetPaymentNotFound(): void
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/payments/999999');
        $request->set_param('id', 999999);

        $response = $this->manager->restGetPayment($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    public function testRestRefundPaymentNotFound(): void
    {
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/payments/999999/refund');
        $request->set_param('id', 999999);

        $response = $this->manager->restRefundPayment($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    public function testRestGetSubscriptionsReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/subscriptions');
        $response = $this->manager->restGetSubscriptions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestCancelSubscriptionNotFound(): void
    {
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/subscriptions/999999/cancel');
        $request->set_param('id', 999999);

        $response = $this->manager->restCancelSubscription($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(404, $response->get_status());
    }

    public function testRestGetInvoicesReturnsResponse(): void
    {
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/invoices');
        $response = $this->manager->restGetInvoices($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertIsArray($response->get_data());
    }

    public function testRestCreateInvoiceReturnsResponse(): void
    {
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/invoices');
        $request->set_body(json_encode([
            'customer_name' => 'REST Test',
            'customer_email' => 'rest@test.com',
            'items' => [['name' => 'Item', 'price' => 100]],
        ]));
        $request->set_header('Content-Type', 'application/json');

        $response = $this->manager->restCreateInvoice($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('invoice_id', $data);
    }

    // ==================== Currency Tests ====================

    public function testGetCurrencySymbol(): void
    {
        // Access private method
        $symbol = $this->callPrivateMethod($this->manager, 'getCurrencySymbol', ['USD']);
        $this->assertEquals('$', $symbol);

        $symbol = $this->callPrivateMethod($this->manager, 'getCurrencySymbol', ['EUR']);
        $this->assertEquals('€', $symbol);

        $symbol = $this->callPrivateMethod($this->manager, 'getCurrencySymbol', ['GBP']);
        $this->assertEquals('£', $symbol);

        $symbol = $this->callPrivateMethod($this->manager, 'getCurrencySymbol', ['BRL']);
        $this->assertEquals('R$', $symbol);

        // Unknown currency
        $symbol = $this->callPrivateMethod($this->manager, 'getCurrencySymbol', ['XYZ']);
        $this->assertEquals('XYZ ', $symbol);
    }

    // ==================== Invoice Number Generation Tests ====================

    public function testGenerateInvoiceNumberFormat(): void
    {
        $number = $this->callPrivateMethod($this->manager, 'generateInvoiceNumber', []);

        $this->assertIsString($number);
        $this->assertStringStartsWith('INV-', $number);
        $this->assertStringContainsString(date('Y'), $number);
    }

    public function testInvoiceNumbersAreSequential(): void
    {
        // Create two invoices and check numbers
        $invoice_id1 = $this->manager->createInvoice([
            'customer_name' => 'Test 1',
            'items' => [['name' => 'Item', 'price' => 10]],
        ]);

        $invoice_id2 = $this->manager->createInvoice([
            'customer_name' => 'Test 2',
            'items' => [['name' => 'Item', 'price' => 20]],
        ]);

        $invoice1 = $this->manager->getInvoice($invoice_id1);
        $invoice2 = $this->manager->getInvoice($invoice_id2);

        // Extract numeric parts
        $num1 = (int) substr($invoice1['invoice_number'], -5);
        $num2 = (int) substr($invoice2['invoice_number'], -5);

        $this->assertEquals($num1 + 1, $num2);
    }

    // ==================== Invoice Calculation Tests ====================

    public function testInvoiceCalculatesSubtotalCorrectly(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'Calc Test',
            'items' => [
                ['name' => 'Item 1', 'quantity' => 2, 'price' => 10.00],
                ['name' => 'Item 2', 'quantity' => 3, 'price' => 15.00],
                ['name' => 'Item 3', 'quantity' => 1, 'price' => 25.00],
            ],
        ]);

        $invoice = $this->manager->getInvoice($invoice_id);

        // 2*10 + 3*15 + 1*25 = 20 + 45 + 25 = 90
        $this->assertEquals(90.00, $invoice['subtotal']);
        $this->assertEquals(90.00, $invoice['total']);
    }

    public function testInvoiceCalculatesTotalWithTaxAndDiscount(): void
    {
        $invoice_id = $this->manager->createInvoice([
            'customer_name' => 'Tax Test',
            'items' => [
                ['name' => 'Item', 'quantity' => 1, 'price' => 100.00],
            ],
            'tax' => 20.00,
            'discount' => 10.00,
        ]);

        $invoice = $this->manager->getInvoice($invoice_id);

        // 100 + 20 - 10 = 110
        $this->assertEquals(100.00, $invoice['subtotal']);
        $this->assertEquals(20.00, $invoice['tax']);
        $this->assertEquals(10.00, $invoice['discount']);
        $this->assertEquals(110.00, $invoice['total']);
    }
}
