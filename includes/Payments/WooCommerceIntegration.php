<?php
/**
 * FormFlow Pro - WooCommerce Integration
 *
 * Deep integration with WooCommerce for products, orders,
 * subscriptions (with WooCommerce Subscriptions), and checkout.
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
 * WooCommerce Integration
 */
class WooCommerceIntegration
{
    use SingletonTrait;

    private bool $wc_active = false;
    private bool $wc_subscriptions_active = false;

    protected function init(): void
    {
        $this->checkDependencies();

        if ($this->wc_active) {
            $this->registerHooks();
        }
    }

    private function checkDependencies(): void
    {
        $this->wc_active = class_exists('WooCommerce');
        $this->wc_subscriptions_active = class_exists('WC_Subscriptions');
    }

    private function registerHooks(): void
    {
        // Form submission to order
        add_action('ffp_form_submission', [$this, 'handleFormSubmission'], 20, 2);

        // Custom product type for form products
        add_filter('product_type_selector', [$this, 'addFormProductType']);
        add_filter('woocommerce_product_class', [$this, 'filterProductClass'], 10, 2);

        // Cart handling
        add_filter('woocommerce_add_cart_item_data', [$this, 'addFormDataToCart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'displayFormDataInCart'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveFormDataToOrder'], 10, 4);

        // Order meta display
        add_action('woocommerce_order_item_meta_end', [$this, 'displayFormDataInOrderMeta'], 10, 3);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Admin
        add_action('add_meta_boxes', [$this, 'addOrderMetaBoxes']);

        // AJAX
        add_action('wp_ajax_ffp_wc_create_product', [$this, 'ajaxCreateProduct']);
        add_action('wp_ajax_ffp_wc_add_to_cart', [$this, 'ajaxAddToCart']);
    }

    /**
     * Check if WooCommerce is active
     */
    public function isActive(): bool
    {
        return $this->wc_active;
    }

    /**
     * Check if WooCommerce Subscriptions is active
     */
    public function isSubscriptionsActive(): bool
    {
        return $this->wc_subscriptions_active;
    }

    /**
     * Handle form submission to create order
     */
    public function handleFormSubmission(int $form_id, array $submission_data): void
    {
        if (!$this->wc_active) {
            return;
        }

        $form = \FormFlowPro\FormBuilder\DragDropBuilder::getInstance()->getForm($form_id);

        if (!$form) {
            return;
        }

        // Check if form has WooCommerce integration enabled
        $wc_settings = $form->settings['woocommerce'] ?? [];

        if (empty($wc_settings['enabled'])) {
            return;
        }

        $data = $submission_data['data'] ?? [];

        switch ($wc_settings['action'] ?? 'create_order') {
            case 'create_order':
                $this->createOrderFromSubmission($form, $data, $wc_settings);
                break;

            case 'add_to_cart':
                $this->addToCartFromSubmission($form, $data, $wc_settings);
                break;

            case 'create_subscription':
                if ($this->wc_subscriptions_active) {
                    $this->createSubscriptionFromSubmission($form, $data, $wc_settings);
                }
                break;
        }
    }

    /**
     * Create WooCommerce order from form submission
     */
    public function createOrderFromSubmission($form, array $data, array $settings): ?int
    {
        // Create order
        $order = wc_create_order([
            'status' => $settings['initial_status'] ?? 'pending',
        ]);

        if (is_wp_error($order)) {
            return null;
        }

        // Add products
        $products = $this->getProductsFromFormData($form, $data, $settings);

        foreach ($products as $product_data) {
            $product = wc_get_product($product_data['id']);

            if (!$product) {
                continue;
            }

            $order->add_product(
                $product,
                $product_data['quantity'] ?? 1,
                [
                    'subtotal' => $product_data['custom_price'] ?? $product->get_price() * ($product_data['quantity'] ?? 1),
                    'total' => $product_data['custom_price'] ?? $product->get_price() * ($product_data['quantity'] ?? 1),
                ]
            );
        }

        // Set customer
        $customer_data = $this->extractCustomerData($data, $settings);

        if (!empty($customer_data['email'])) {
            // Try to find existing customer
            $user_id = email_exists($customer_data['email']);

            if ($user_id) {
                $order->set_customer_id($user_id);
            }
        }

        // Billing address
        $order->set_billing_first_name($customer_data['first_name'] ?? '');
        $order->set_billing_last_name($customer_data['last_name'] ?? '');
        $order->set_billing_email($customer_data['email'] ?? '');
        $order->set_billing_phone($customer_data['phone'] ?? '');
        $order->set_billing_address_1($customer_data['address_1'] ?? '');
        $order->set_billing_address_2($customer_data['address_2'] ?? '');
        $order->set_billing_city($customer_data['city'] ?? '');
        $order->set_billing_state($customer_data['state'] ?? '');
        $order->set_billing_postcode($customer_data['postcode'] ?? '');
        $order->set_billing_country($customer_data['country'] ?? '');

        // Shipping address (if different)
        if (!empty($settings['shipping_same_as_billing'])) {
            $order->set_shipping_first_name($customer_data['first_name'] ?? '');
            $order->set_shipping_last_name($customer_data['last_name'] ?? '');
            $order->set_shipping_address_1($customer_data['address_1'] ?? '');
            $order->set_shipping_address_2($customer_data['address_2'] ?? '');
            $order->set_shipping_city($customer_data['city'] ?? '');
            $order->set_shipping_state($customer_data['state'] ?? '');
            $order->set_shipping_postcode($customer_data['postcode'] ?? '');
            $order->set_shipping_country($customer_data['country'] ?? '');
        }

        // Add custom fees
        if (!empty($settings['fees'])) {
            foreach ($settings['fees'] as $fee) {
                $fee_amount = $this->calculateDynamicValue($fee['amount'], $data);
                if ($fee_amount > 0) {
                    $item_fee = new \WC_Order_Item_Fee();
                    $item_fee->set_name($fee['name']);
                    $item_fee->set_amount($fee_amount);
                    $item_fee->set_total($fee_amount);
                    $order->add_item($item_fee);
                }
            }
        }

        // Add shipping
        if (!empty($settings['shipping_method'])) {
            $shipping_item = new \WC_Order_Item_Shipping();
            $shipping_item->set_method_title($settings['shipping_method']['title']);
            $shipping_item->set_method_id($settings['shipping_method']['id']);
            $shipping_item->set_total($settings['shipping_method']['cost'] ?? 0);
            $order->add_item($shipping_item);
        }

        // Apply coupon
        if (!empty($data[$settings['coupon_field'] ?? 'coupon'])) {
            $coupon_code = sanitize_text_field($data[$settings['coupon_field']]);
            $order->apply_coupon($coupon_code);
        }

        // Store form submission reference
        $order->update_meta_data('_ffp_form_id', $form->id);
        $order->update_meta_data('_ffp_submission_id', $submission_data['submission_id'] ?? 0);
        $order->update_meta_data('_ffp_form_data', $data);

        // Custom meta from form fields
        if (!empty($settings['meta_mapping'])) {
            foreach ($settings['meta_mapping'] as $field => $meta_key) {
                if (isset($data[$field])) {
                    $order->update_meta_data($meta_key, $data[$field]);
                }
            }
        }

        // Calculate totals
        $order->calculate_totals();

        // Save
        $order->save();

        // Trigger payment if payment method specified
        if (!empty($settings['payment_gateway'])) {
            $payment_gateways = WC()->payment_gateways()->payment_gateways();

            if (isset($payment_gateways[$settings['payment_gateway']])) {
                $order->set_payment_method($payment_gateways[$settings['payment_gateway']]);
                $order->save();
            }
        }

        // Send notifications
        if (!empty($settings['send_new_order_email'])) {
            WC()->mailer()->get_emails()['WC_Email_New_Order']->trigger($order->get_id());
        }

        do_action('ffp_wc_order_created', $order->get_id(), $form->id, $data);

        return $order->get_id();
    }

    /**
     * Add products to cart from form submission
     */
    public function addToCartFromSubmission($form, array $data, array $settings): void
    {
        if (!WC()->cart) {
            return;
        }

        $products = $this->getProductsFromFormData($form, $data, $settings);

        foreach ($products as $product_data) {
            $cart_item_data = [
                'ffp_form_id' => $form->id,
                'ffp_form_data' => $data,
            ];

            // Custom price
            if (!empty($product_data['custom_price'])) {
                $cart_item_data['ffp_custom_price'] = $product_data['custom_price'];
            }

            WC()->cart->add_to_cart(
                $product_data['id'],
                $product_data['quantity'] ?? 1,
                $product_data['variation_id'] ?? 0,
                $product_data['variation'] ?? [],
                $cart_item_data
            );
        }

        // Redirect to cart/checkout
        if (!empty($settings['redirect_to_checkout'])) {
            wp_redirect(wc_get_checkout_url());
            exit;
        }
    }

    /**
     * Create subscription from form submission
     */
    public function createSubscriptionFromSubmission($form, array $data, array $settings): ?int
    {
        if (!$this->wc_subscriptions_active) {
            return null;
        }

        // Get subscription product
        $product_id = $settings['subscription_product_id'] ?? 0;

        if (!$product_id && !empty($settings['product_field'])) {
            $product_id = (int) ($data[$settings['product_field']] ?? 0);
        }

        if (!$product_id) {
            return null;
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type(['subscription', 'variable-subscription'])) {
            return null;
        }

        // Create subscription
        $customer_data = $this->extractCustomerData($data, $settings);

        $subscription = wcs_create_subscription([
            'order_type' => 'subscription',
            'status' => 'pending',
            'billing_period' => \WC_Subscriptions_Product::get_period($product),
            'billing_interval' => \WC_Subscriptions_Product::get_interval($product),
        ]);

        if (is_wp_error($subscription)) {
            return null;
        }

        // Add product
        $subscription->add_product($product, $settings['quantity'] ?? 1);

        // Set billing details
        $subscription->set_billing_first_name($customer_data['first_name'] ?? '');
        $subscription->set_billing_last_name($customer_data['last_name'] ?? '');
        $subscription->set_billing_email($customer_data['email'] ?? '');
        $subscription->set_billing_phone($customer_data['phone'] ?? '');
        $subscription->set_billing_address_1($customer_data['address_1'] ?? '');
        $subscription->set_billing_city($customer_data['city'] ?? '');
        $subscription->set_billing_state($customer_data['state'] ?? '');
        $subscription->set_billing_postcode($customer_data['postcode'] ?? '');
        $subscription->set_billing_country($customer_data['country'] ?? '');

        // Store form data
        $subscription->update_meta_data('_ffp_form_id', $form->id);
        $subscription->update_meta_data('_ffp_form_data', $data);

        // Calculate and save
        $subscription->calculate_totals();
        $subscription->save();

        // Create initial order
        $parent_order = wcs_create_order_from_subscription($subscription, 'parent');

        if ($parent_order && !is_wp_error($parent_order)) {
            $subscription->set_parent_id($parent_order->get_id());
            $subscription->save();
        }

        do_action('ffp_wc_subscription_created', $subscription->get_id(), $form->id, $data);

        return $subscription->get_id();
    }

    /**
     * Get products from form data
     */
    private function getProductsFromFormData($form, array $data, array $settings): array
    {
        $products = [];

        // Static product
        if (!empty($settings['product_id'])) {
            $products[] = [
                'id' => $settings['product_id'],
                'quantity' => $this->calculateDynamicValue($settings['quantity'] ?? 1, $data),
                'custom_price' => !empty($settings['price_field'])
                    ? floatval($data[$settings['price_field']] ?? 0)
                    : null,
            ];
        }

        // Product from field selection
        if (!empty($settings['product_field'])) {
            $selected = $data[$settings['product_field']] ?? [];
            $selected = is_array($selected) ? $selected : [$selected];

            foreach ($selected as $product_id) {
                $products[] = [
                    'id' => (int) $product_id,
                    'quantity' => 1,
                ];
            }
        }

        // Multiple products mapping
        if (!empty($settings['product_mapping'])) {
            foreach ($settings['product_mapping'] as $mapping) {
                $quantity = 0;

                if (!empty($mapping['quantity_field'])) {
                    $quantity = (int) ($data[$mapping['quantity_field']] ?? 0);
                } elseif (!empty($mapping['checkbox_field'])) {
                    $quantity = !empty($data[$mapping['checkbox_field']]) ? 1 : 0;
                } else {
                    $quantity = $mapping['quantity'] ?? 1;
                }

                if ($quantity > 0) {
                    $products[] = [
                        'id' => $mapping['product_id'],
                        'quantity' => $quantity,
                        'custom_price' => !empty($mapping['price_field'])
                            ? floatval($data[$mapping['price_field']] ?? 0)
                            : null,
                    ];
                }
            }
        }

        return $products;
    }

    /**
     * Extract customer data from form data
     */
    private function extractCustomerData(array $data, array $settings): array
    {
        $mapping = $settings['customer_mapping'] ?? [];

        $customer = [
            'email' => $data[$mapping['email'] ?? 'email'] ?? '',
            'first_name' => $data[$mapping['first_name'] ?? 'first_name'] ?? '',
            'last_name' => $data[$mapping['last_name'] ?? 'last_name'] ?? '',
            'phone' => $data[$mapping['phone'] ?? 'phone'] ?? '',
            'address_1' => $data[$mapping['address_1'] ?? 'address_1'] ?? '',
            'address_2' => $data[$mapping['address_2'] ?? 'address_2'] ?? '',
            'city' => $data[$mapping['city'] ?? 'city'] ?? '',
            'state' => $data[$mapping['state'] ?? 'state'] ?? '',
            'postcode' => $data[$mapping['postcode'] ?? 'postcode'] ?? '',
            'country' => $data[$mapping['country'] ?? 'country'] ?? '',
        ];

        // Handle name field that combines first/last
        if (isset($data[$mapping['name'] ?? 'name'])) {
            $name_data = $data[$mapping['name']];
            if (is_array($name_data)) {
                $customer['first_name'] = $name_data['first'] ?? '';
                $customer['last_name'] = $name_data['last'] ?? '';
            } else {
                $parts = explode(' ', $name_data, 2);
                $customer['first_name'] = $parts[0] ?? '';
                $customer['last_name'] = $parts[1] ?? '';
            }
        }

        // Handle address field that is composite
        if (isset($data[$mapping['address'] ?? 'address'])) {
            $address_data = $data[$mapping['address']];
            if (is_array($address_data)) {
                $customer['address_1'] = $address_data['line1'] ?? '';
                $customer['address_2'] = $address_data['line2'] ?? '';
                $customer['city'] = $address_data['city'] ?? '';
                $customer['state'] = $address_data['state'] ?? '';
                $customer['postcode'] = $address_data['postal'] ?? '';
                $customer['country'] = $address_data['country'] ?? '';
            }
        }

        return $customer;
    }

    /**
     * Calculate dynamic value from field or expression
     */
    private function calculateDynamicValue($value, array $data)
    {
        if (is_numeric($value)) {
            return $value;
        }

        // Field reference: {field:field_name}
        if (preg_match('/\{field:([^}]+)\}/', $value, $matches)) {
            return $data[$matches[1]] ?? 0;
        }

        return $value;
    }

    /**
     * Add form data to cart item
     */
    public function addFormDataToCart(array $cart_item_data, int $product_id, int $variation_id): array
    {
        if (isset($_POST['ffp_form_id'])) {
            $cart_item_data['ffp_form_id'] = (int) $_POST['ffp_form_id'];
            $cart_item_data['ffp_form_data'] = json_decode(
                stripslashes($_POST['ffp_form_data'] ?? '{}'),
                true
            );
        }

        if (isset($_POST['ffp_custom_price'])) {
            $cart_item_data['ffp_custom_price'] = floatval($_POST['ffp_custom_price']);
        }

        return $cart_item_data;
    }

    /**
     * Display form data in cart
     */
    public function displayFormDataInCart(array $item_data, array $cart_item): array
    {
        if (!empty($cart_item['ffp_form_data'])) {
            $form_data = $cart_item['ffp_form_data'];

            // Add visible form fields to cart display
            foreach ($form_data as $key => $value) {
                if (is_string($value) && !empty($value)) {
                    $item_data[] = [
                        'key' => ucwords(str_replace('_', ' ', $key)),
                        'value' => $value,
                    ];
                }
            }
        }

        return $item_data;
    }

    /**
     * Save form data to order line item
     */
    public function saveFormDataToOrder($item, $cart_item_key, array $values, $order): void
    {
        if (!empty($values['ffp_form_id'])) {
            $item->add_meta_data('_ffp_form_id', $values['ffp_form_id']);
        }

        if (!empty($values['ffp_form_data'])) {
            $item->add_meta_data('_ffp_form_data', $values['ffp_form_data']);
        }

        if (!empty($values['ffp_custom_price'])) {
            $item->add_meta_data('_ffp_custom_price', $values['ffp_custom_price']);
        }
    }

    /**
     * Display form data in order item meta
     */
    public function displayFormDataInOrderMeta(int $item_id, $item, $order): void
    {
        $form_data = $item->get_meta('_ffp_form_data');

        if (!$form_data || !is_array($form_data)) {
            return;
        }

        echo '<div class="ffp-order-item-form-data">';
        echo '<strong>' . __('Form Data:', 'form-flow-pro') . '</strong><br>';

        foreach ($form_data as $key => $value) {
            if (is_string($value) && !empty($value)) {
                echo '<small>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ': ';
                echo esc_html($value) . '</small><br>';
            }
        }

        echo '</div>';
    }

    /**
     * Add custom product type for form-generated products
     */
    public function addFormProductType(array $types): array
    {
        $types['ffp_form_product'] = __('Form Product', 'form-flow-pro');
        return $types;
    }

    /**
     * Filter product class
     */
    public function filterProductClass(string $classname, string $product_type): string
    {
        if ($product_type === 'ffp_form_product') {
            return 'FormFlowPro\\Payments\\WC_Product_FFP_Form';
        }
        return $classname;
    }

    /**
     * Create WooCommerce product
     */
    public function createProduct(array $data): ?int
    {
        $product = new \WC_Product_Simple();

        $product->set_name($data['name']);
        $product->set_status($data['status'] ?? 'publish');
        $product->set_catalog_visibility($data['visibility'] ?? 'visible');
        $product->set_description($data['description'] ?? '');
        $product->set_short_description($data['short_description'] ?? '');
        $product->set_sku($data['sku'] ?? '');
        $product->set_regular_price($data['price'] ?? '0');

        if (!empty($data['sale_price'])) {
            $product->set_sale_price($data['sale_price']);
        }

        $product->set_virtual($data['virtual'] ?? true);
        $product->set_downloadable($data['downloadable'] ?? false);

        if (!empty($data['categories'])) {
            $product->set_category_ids($data['categories']);
        }

        // Store form association
        if (!empty($data['form_id'])) {
            $product->update_meta_data('_ffp_form_id', $data['form_id']);
        }

        $product_id = $product->save();

        return $product_id ?: null;
    }

    /**
     * Get orders by form
     */
    public function getOrdersByForm(int $form_id, array $args = []): array
    {
        $default_args = [
            'limit' => 20,
            'page' => 1,
            'status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $default_args);

        $query_args = [
            'limit' => $args['limit'],
            'page' => $args['page'],
            'status' => $args['status'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'meta_key' => '_ffp_form_id',
            'meta_value' => $form_id,
        ];

        $orders = wc_get_orders($query_args);

        return array_map(function($order) {
            return [
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'customer_email' => $order->get_billing_email(),
                'customer_name' => $order->get_formatted_billing_full_name(),
                'date_created' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'form_data' => $order->get_meta('_ffp_form_data'),
            ];
        }, $orders);
    }

    /**
     * Get form statistics from orders
     */
    public function getFormStatistics(int $form_id): array
    {
        global $wpdb;

        $meta_key = '_ffp_form_id';

        // Total orders
        $total_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
            $meta_key,
            $form_id
        ));

        // Total revenue
        $total_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm2.meta_value)
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_order_total'
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc-completed', 'wc-processing')
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
            $meta_key,
            $form_id
        ));

        // Orders by status
        $status_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.post_status, COUNT(*) as count
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'shop_order'
             AND pm.meta_key = %s
             AND pm.meta_value = %s
             GROUP BY p.post_status",
            $meta_key,
            $form_id
        ), ARRAY_A);

        return [
            'total_orders' => (int) $total_orders,
            'total_revenue' => (float) $total_revenue,
            'currency' => get_woocommerce_currency(),
            'by_status' => array_column($status_counts, 'count', 'post_status'),
        ];
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('form-flow-pro/v1', '/woocommerce/products', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetProducts'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateProduct'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/woocommerce/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetOrders'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/forms/(?P<id>\d+)/woocommerce/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetFormStats'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restGetProducts(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->wc_active) {
            return new \WP_REST_Response(['error' => 'WooCommerce not active'], 400);
        }

        $args = [
            'limit' => $request->get_param('per_page') ?? 20,
            'page' => $request->get_param('page') ?? 1,
            'status' => $request->get_param('status') ?? 'publish',
        ];

        if ($request->get_param('type')) {
            $args['type'] = $request->get_param('type');
        }

        $products = wc_get_products($args);

        return new \WP_REST_Response(array_map(function($product) {
            return [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'type' => $product->get_type(),
                'sku' => $product->get_sku(),
                'status' => $product->get_status(),
            ];
        }, $products));
    }

    public function restCreateProduct(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->wc_active) {
            return new \WP_REST_Response(['error' => 'WooCommerce not active'], 400);
        }

        $data = $request->get_json_params();
        $product_id = $this->createProduct($data);

        if (!$product_id) {
            return new \WP_REST_Response(['error' => 'Failed to create product'], 500);
        }

        return new \WP_REST_Response(['product_id' => $product_id], 201);
    }

    public function restGetOrders(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->wc_active) {
            return new \WP_REST_Response(['error' => 'WooCommerce not active'], 400);
        }

        $form_id = $request->get_param('form_id');

        if (!$form_id) {
            return new \WP_REST_Response(['error' => 'Form ID required'], 400);
        }

        $orders = $this->getOrdersByForm((int) $form_id, [
            'limit' => $request->get_param('per_page') ?? 20,
            'page' => $request->get_param('page') ?? 1,
            'status' => $request->get_param('status') ?? 'any',
        ]);

        return new \WP_REST_Response($orders);
    }

    public function restGetFormStats(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!$this->wc_active) {
            return new \WP_REST_Response(['error' => 'WooCommerce not active'], 400);
        }

        $form_id = (int) $request->get_param('id');
        $stats = $this->getFormStatistics($form_id);

        return new \WP_REST_Response($stats);
    }

    /**
     * Add order meta boxes
     */
    public function addOrderMetaBoxes(): void
    {
        add_meta_box(
            'ffp_form_submission_data',
            __('Form Submission Data', 'form-flow-pro'),
            [$this, 'renderOrderMetaBox'],
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render order meta box
     */
    public function renderOrderMetaBox($post): void
    {
        $order = wc_get_order($post->ID);

        if (!$order) {
            return;
        }

        $form_id = $order->get_meta('_ffp_form_id');
        $submission_id = $order->get_meta('_ffp_submission_id');
        $form_data = $order->get_meta('_ffp_form_data');

        if (!$form_id) {
            echo '<p>' . __('This order was not created from a form submission.', 'form-flow-pro') . '</p>';
            return;
        }

        echo '<p><strong>' . __('Form ID:', 'form-flow-pro') . '</strong> ' . esc_html($form_id) . '</p>';

        if ($submission_id) {
            echo '<p><strong>' . __('Submission ID:', 'form-flow-pro') . '</strong> ' . esc_html($submission_id) . '</p>';
        }

        if ($form_data && is_array($form_data)) {
            echo '<hr>';
            echo '<h4>' . __('Form Data', 'form-flow-pro') . '</h4>';

            foreach ($form_data as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                echo '<p><strong>' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</strong><br>';
                echo esc_html($value) . '</p>';
            }
        }
    }

    /**
     * AJAX create product
     */
    public function ajaxCreateProduct(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'form_id' => (int) ($_POST['form_id'] ?? 0),
        ];

        $product_id = $this->createProduct($data);

        if ($product_id) {
            wp_send_json_success(['product_id' => $product_id]);
        } else {
            wp_send_json_error(['message' => 'Failed to create product']);
        }
    }

    /**
     * AJAX add to cart
     */
    public function ajaxAddToCart(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!$this->wc_active) {
            wp_send_json_error(['message' => 'WooCommerce not active']);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 1);
        $form_id = (int) ($_POST['form_id'] ?? 0);
        $form_data = json_decode(stripslashes($_POST['form_data'] ?? '{}'), true);

        $cart_item_data = [
            'ffp_form_id' => $form_id,
            'ffp_form_data' => $form_data,
        ];

        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, [], $cart_item_data);

        if ($cart_item_key) {
            wp_send_json_success([
                'cart_item_key' => $cart_item_key,
                'cart_url' => wc_get_cart_url(),
                'checkout_url' => wc_get_checkout_url(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add to cart']);
        }
    }
}

/**
 * Custom WooCommerce Product Type for Forms
 */
if (class_exists('WC_Product_Simple')) {
    class WC_Product_FFP_Form extends \WC_Product_Simple
    {
        public function __construct($product = 0)
        {
            $this->product_type = 'ffp_form_product';
            parent::__construct($product);
        }

        public function get_type()
        {
            return 'ffp_form_product';
        }

        public function is_virtual()
        {
            return true;
        }

        public function is_sold_individually()
        {
            return true;
        }
    }
}
