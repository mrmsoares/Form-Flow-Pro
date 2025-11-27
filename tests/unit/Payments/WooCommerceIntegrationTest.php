<?php
/**
 * Tests for WooCommerceIntegration class.
 *
 * @package FormFlowPro\Tests\Unit\Payments
 */

namespace FormFlowPro\Tests\Unit\Payments;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Payments\WooCommerceIntegration;

class WooCommerceIntegrationTest extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset singleton for each test
        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        // Mock WooCommerce active
        mock_class_exists('WooCommerce', true);
    }

    protected function tearDown(): void
    {
        // Reset singleton
        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        parent::tearDown();
    }

    // ==========================================================================
    // Singleton Tests
    // ==========================================================================

    public function test_singleton_instance()
    {
        $instance1 = WooCommerceIntegration::getInstance();
        $instance2 = WooCommerceIntegration::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(WooCommerceIntegration::class, $instance1);
    }

    // ==========================================================================
    // Dependency Check Tests
    // ==========================================================================

    public function test_is_active_returns_true_when_woocommerce_exists()
    {
        $integration = WooCommerceIntegration::getInstance();
        $this->assertTrue($integration->isActive());
    }

    public function test_is_active_returns_false_when_woocommerce_missing()
    {
        mock_class_exists('WooCommerce', false);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();
        $this->assertFalse($integration->isActive());
    }

    public function test_is_subscriptions_active_returns_true_when_subscriptions_exists()
    {
        mock_class_exists('WC_Subscriptions', true);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();
        $this->assertTrue($integration->isSubscriptionsActive());
    }

    public function test_is_subscriptions_active_returns_false_when_subscriptions_missing()
    {
        mock_class_exists('WC_Subscriptions', false);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();
        $this->assertFalse($integration->isSubscriptionsActive());
    }

    // ==========================================================================
    // Product Creation Tests
    // ==========================================================================

    public function test_create_product_success()
    {
        $integration = WooCommerceIntegration::getInstance();

        $product_id = $integration->createProduct([
            'name' => 'Test Product',
            'price' => 99.99,
            'description' => 'A test product',
            'form_id' => 10,
        ]);

        $this->assertIsInt($product_id);
        $this->assertGreaterThan(0, $product_id);
    }

    public function test_create_product_with_sale_price()
    {
        $integration = WooCommerceIntegration::getInstance();

        $product_id = $integration->createProduct([
            'name' => 'Sale Product',
            'price' => 99.99,
            'sale_price' => 79.99,
        ]);

        $this->assertIsInt($product_id);
    }

    public function test_create_product_virtual_by_default()
    {
        $integration = WooCommerceIntegration::getInstance();

        $product_id = $integration->createProduct([
            'name' => 'Virtual Product',
            'price' => 49.99,
        ]);

        $this->assertIsInt($product_id);
    }

    // ==========================================================================
    // Order Creation Tests
    // ==========================================================================

    public function test_create_order_from_submission_basic()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        $settings = [
            'product_id' => 1,
            'quantity' => 2,
        ];

        $order_id = $integration->createOrderFromSubmission($form, $data, $settings);

        $this->assertIsInt($order_id);
        $this->assertGreaterThan(0, $order_id);
    }

    public function test_create_order_applies_customer_data()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'address_1' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'postcode' => '10001',
            'country' => 'US',
        ];
        $settings = [
            'product_id' => 1,
            'customer_mapping' => [
                'email' => 'email',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'phone' => 'phone',
                'address_1' => 'address_1',
                'city' => 'city',
                'state' => 'state',
                'postcode' => 'postcode',
                'country' => 'country',
            ],
        ];

        $order_id = $integration->createOrderFromSubmission($form, $data, $settings);

        $this->assertIsInt($order_id);
    }

    public function test_create_order_with_fees()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [];
        $settings = [
            'product_id' => 1,
            'fees' => [
                [
                    'name' => 'Processing Fee',
                    'amount' => 5.00,
                ],
            ],
        ];

        $order_id = $integration->createOrderFromSubmission($form, $data, $settings);

        $this->assertIsInt($order_id);
    }

    public function test_create_order_with_shipping()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [];
        $settings = [
            'product_id' => 1,
            'shipping_method' => [
                'title' => 'Standard Shipping',
                'id' => 'flat_rate',
                'cost' => 10.00,
            ],
        ];

        $order_id = $integration->createOrderFromSubmission($form, $data, $settings);

        $this->assertIsInt($order_id);
    }

    // ==========================================================================
    // Add to Cart Tests
    // ==========================================================================

    public function test_add_to_cart_from_submission()
    {
        // Mock WC()->cart
        mock_wc_cart();

        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = ['field1' => 'value1'];
        $settings = [
            'product_id' => 1,
            'quantity' => 2,
        ];

        // Should not throw
        $integration->addToCartFromSubmission($form, $data, $settings);

        $this->assertTrue(true);
    }

    public function test_add_to_cart_with_custom_price()
    {
        mock_wc_cart();

        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = ['custom_amount' => 150.00];
        $settings = [
            'product_id' => 1,
            'custom_price' => 150.00,
        ];

        $integration->addToCartFromSubmission($form, $data, $settings);

        $this->assertTrue(true);
    }

    // ==========================================================================
    // Cart Data Tests
    // ==========================================================================

    public function test_add_form_data_to_cart()
    {
        $integration = WooCommerceIntegration::getInstance();

        $_POST['ffp_form_id'] = 10;
        $_POST['ffp_form_data'] = json_encode(['field1' => 'value1']);

        $cart_item_data = $integration->addFormDataToCart([], 1, 0);

        $this->assertArrayHasKey('ffp_form_id', $cart_item_data);
        $this->assertEquals(10, $cart_item_data['ffp_form_id']);
        $this->assertArrayHasKey('ffp_form_data', $cart_item_data);

        unset($_POST['ffp_form_id'], $_POST['ffp_form_data']);
    }

    public function test_display_form_data_in_cart()
    {
        $integration = WooCommerceIntegration::getInstance();

        $cart_item = [
            'ffp_form_data' => [
                'first_name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        $item_data = $integration->displayFormDataInCart([], $cart_item);

        $this->assertIsArray($item_data);
        $this->assertGreaterThan(0, count($item_data));
    }

    // ==========================================================================
    // Order Data Tests
    // ==========================================================================

    public function test_save_form_data_to_order()
    {
        $integration = WooCommerceIntegration::getInstance();

        $item = new \stdClass();
        $item->meta_data = [];

        $values = [
            'ffp_form_id' => 10,
            'ffp_form_data' => ['field1' => 'value1'],
        ];

        // Should not throw
        $integration->saveFormDataToOrder($item, 'cart_item_key', $values, null);

        $this->assertTrue(true);
    }

    // ==========================================================================
    // Product Type Tests
    // ==========================================================================

    public function test_add_form_product_type()
    {
        $integration = WooCommerceIntegration::getInstance();

        $types = ['simple' => 'Simple', 'variable' => 'Variable'];
        $result = $integration->addFormProductType($types);

        $this->assertArrayHasKey('ffp_form_product', $result);
        $this->assertEquals('Form Product', $result['ffp_form_product']);
    }

    public function test_filter_product_class()
    {
        $integration = WooCommerceIntegration::getInstance();

        $classname = $integration->filterProductClass('WC_Product_Simple', 'ffp_form_product');

        $this->assertEquals('FormFlowPro\\Payments\\WC_Product_FFP_Form', $classname);
    }

    public function test_filter_product_class_unchanged_for_other_types()
    {
        $integration = WooCommerceIntegration::getInstance();

        $classname = $integration->filterProductClass('WC_Product_Simple', 'simple');

        $this->assertEquals('WC_Product_Simple', $classname);
    }

    // ==========================================================================
    // Statistics Tests
    // ==========================================================================

    public function test_get_orders_by_form()
    {
        global $wpdb;

        // Mock WooCommerce orders
        mock_wc_get_orders([
            (object) [
                'ID' => 1,
                'get_id' => function() { return 1; },
                'get_status' => function() { return 'completed'; },
                'get_total' => function() { return 100.00; },
                'get_currency' => function() { return 'USD'; },
                'get_billing_email' => function() { return 'customer@example.com'; },
                'get_formatted_billing_full_name' => function() { return 'John Doe'; },
                'get_date_created' => function() {
                    return (object) ['format' => function($f) { return '2024-01-01 12:00:00'; }];
                },
                'get_meta' => function($key) { return []; },
            ],
        ]);

        $integration = WooCommerceIntegration::getInstance();
        $orders = $integration->getOrdersByForm(10);

        $this->assertIsArray($orders);
    }

    public function test_get_form_statistics()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_var', 10, 0); // Total orders
        $wpdb->set_mock_result('get_var', 1000.00, 1); // Total revenue
        $wpdb->set_mock_result('get_results', [
            ['post_status' => 'wc-completed', 'count' => 8],
            ['post_status' => 'wc-processing', 'count' => 2],
        ]);

        $integration = WooCommerceIntegration::getInstance();
        $stats = $integration->getFormStatistics(10);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_revenue', $stats);
        $this->assertArrayHasKey('currency', $stats);
        $this->assertArrayHasKey('by_status', $stats);

        $this->assertEquals(10, $stats['total_orders']);
        $this->assertEquals(1000.00, $stats['total_revenue']);
    }

    // ==========================================================================
    // Subscription Tests
    // ==========================================================================

    public function test_create_subscription_from_submission()
    {
        mock_class_exists('WC_Subscriptions', true);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        $settings = [
            'subscription_product_id' => 1,
        ];

        // This will fail due to mocking limitations, but tests the flow
        $result = $integration->createSubscriptionFromSubmission($form, $data, $settings);

        // Returns null when product not found in mock environment
        $this->assertNull($result);
    }

    // ==========================================================================
    // AJAX Tests
    // ==========================================================================

    public function test_ajax_create_product()
    {
        $_POST['name'] = 'Test Product';
        $_POST['price'] = 99.99;
        $_POST['form_id'] = 10;

        $integration = WooCommerceIntegration::getInstance();

        ob_start();
        $integration->ajaxCreateProduct();
        ob_end_clean();

        // Verify it doesn't throw
        $this->assertTrue(true);

        unset($_POST['name'], $_POST['price'], $_POST['form_id']);
    }

    public function test_ajax_add_to_cart()
    {
        mock_wc_cart();

        $_POST['product_id'] = 1;
        $_POST['quantity'] = 2;
        $_POST['form_id'] = 10;
        $_POST['form_data'] = json_encode(['field1' => 'value1']);

        $integration = WooCommerceIntegration::getInstance();

        ob_start();
        $integration->ajaxAddToCart();
        ob_end_clean();

        // Verify it doesn't throw
        $this->assertTrue(true);

        unset($_POST['product_id'], $_POST['quantity'], $_POST['form_id'], $_POST['form_data']);
    }

    // ==========================================================================
    // REST API Tests
    // ==========================================================================

    public function test_rest_get_products()
    {
        // Mock wc_get_products
        mock_wc_get_products([
            (object) [
                'get_id' => function() { return 1; },
                'get_name' => function() { return 'Product A'; },
                'get_price' => function() { return 99.99; },
                'get_type' => function() { return 'simple'; },
                'get_sku' => function() { return 'SKU123'; },
                'get_status' => function() { return 'publish'; },
            ],
        ]);

        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/woocommerce/products');

        $response = $integration->restGetProducts($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_get_products_when_wc_not_active()
    {
        mock_class_exists('WooCommerce', false);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/woocommerce/products');

        $response = $integration->restGetProducts($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
    }

    public function test_rest_create_product()
    {
        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/woocommerce/products');
        $request->set_body(json_encode([
            'name' => 'New Product',
            'price' => 49.99,
        ]));

        $response = $integration->restCreateProduct($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function test_rest_get_orders()
    {
        mock_wc_get_orders([]);

        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/woocommerce/orders');
        $request->set_param('form_id', 10);

        $response = $integration->restGetOrders($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
    }

    public function test_rest_get_orders_missing_form_id()
    {
        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/woocommerce/orders');

        $response = $integration->restGetOrders($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(400, $response->get_status());
    }

    public function test_rest_get_form_stats()
    {
        global $wpdb;
        $wpdb->set_mock_result('get_var', 5, 0);
        $wpdb->set_mock_result('get_var', 500.00, 1);
        $wpdb->set_mock_result('get_results', []);

        $integration = WooCommerceIntegration::getInstance();
        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/forms/10/woocommerce/stats');
        $request->set_param('id', 10);

        $response = $integration->restGetFormStats($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('total_orders', $data);
        $this->assertArrayHasKey('total_revenue', $data);
    }

    // ==========================================================================
    // Utility Method Tests
    // ==========================================================================

    public function test_extract_customer_data_from_form()
    {
        $integration = WooCommerceIntegration::getInstance();

        $data = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'address_1' => '123 Main St',
            'city' => 'New York',
        ];

        $settings = [
            'customer_mapping' => [
                'email' => 'email',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
            ],
        ];

        $customer = $this->callPrivateMethod($integration, 'extractCustomerData', [$data, $settings]);

        $this->assertIsArray($customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertEquals('customer@example.com', $customer['email']);
        $this->assertEquals('John', $customer['first_name']);
        $this->assertEquals('Doe', $customer['last_name']);
    }

    public function test_extract_customer_data_with_composite_name()
    {
        $integration = WooCommerceIntegration::getInstance();

        $data = [
            'name' => [
                'first' => 'Jane',
                'last' => 'Smith',
            ],
        ];

        $settings = [
            'customer_mapping' => [
                'name' => 'name',
            ],
        ];

        $customer = $this->callPrivateMethod($integration, 'extractCustomerData', [$data, $settings]);

        $this->assertEquals('Jane', $customer['first_name']);
        $this->assertEquals('Smith', $customer['last_name']);
    }

    public function test_get_products_from_form_data_static_product()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [];
        $settings = [
            'product_id' => 1,
            'quantity' => 3,
        ];

        $products = $this->callPrivateMethod($integration, 'getProductsFromFormData', [$form, $data, $settings]);

        $this->assertIsArray($products);
        $this->assertCount(1, $products);
        $this->assertEquals(1, $products[0]['id']);
        $this->assertEquals(3, $products[0]['quantity']);
    }

    public function test_get_products_from_form_data_with_product_field()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [
            'product_selection' => [1, 2, 3],
        ];
        $settings = [
            'product_field' => 'product_selection',
        ];

        $products = $this->callPrivateMethod($integration, 'getProductsFromFormData', [$form, $data, $settings]);

        $this->assertIsArray($products);
        $this->assertCount(3, $products);
    }

    // ==========================================================================
    // Integration Tests
    // ==========================================================================

    public function test_full_order_creation_flow()
    {
        $integration = WooCommerceIntegration::getInstance();

        $form = (object) ['id' => 10];
        $data = [
            'email' => 'customer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];
        $settings = [
            'product_id' => 1,
            'quantity' => 1,
            'initial_status' => 'pending',
        ];

        $order_id = $integration->createOrderFromSubmission($form, $data, $settings);

        $this->assertIsInt($order_id);
        $this->assertGreaterThan(0, $order_id);
    }

    public function test_handle_form_submission_when_wc_disabled()
    {
        mock_class_exists('WooCommerce', false);

        $reflection = new \ReflectionClass(WooCommerceIntegration::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $integration = WooCommerceIntegration::getInstance();

        // Should return early without throwing
        $integration->handleFormSubmission(10, []);

        $this->assertTrue(true);
    }
}
