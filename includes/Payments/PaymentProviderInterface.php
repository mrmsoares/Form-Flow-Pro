<?php
/**
 * FormFlow Pro - Payment Provider Interface
 *
 * Interface for all payment provider implementations.
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
 *
 * All payment providers must implement this interface to ensure
 * consistent behavior across different payment gateways.
 */
interface PaymentProviderInterface
{
    /**
     * Create a new payment
     *
     * @param array $data Payment data including amount, currency, etc.
     * @return array Result with success status and payment details.
     */
    public function createPayment(array $data): array;

    /**
     * Capture a previously authorized payment
     *
     * @param string $payment_id The payment/authorization ID.
     * @return array Result with success status.
     */
    public function capturePayment(string $payment_id): array;

    /**
     * Refund a payment
     *
     * @param string $payment_id The payment ID to refund.
     * @param float|null $amount Optional partial refund amount.
     * @return array Result with success status and refund details.
     */
    public function refundPayment(string $payment_id, ?float $amount = null): array;

    /**
     * Get payment details
     *
     * @param string $payment_id The payment ID.
     * @return array Payment details.
     */
    public function getPayment(string $payment_id): array;

    /**
     * Create a customer record
     *
     * @param array $data Customer data.
     * @return array Result with customer ID.
     */
    public function createCustomer(array $data): array;

    /**
     * Create a subscription
     *
     * @param array $data Subscription data.
     * @return array Result with subscription details.
     */
    public function createSubscription(array $data): array;

    /**
     * Cancel a subscription
     *
     * @param string $subscription_id The subscription ID.
     * @return array Result with success status.
     */
    public function cancelSubscription(string $subscription_id): array;

    /**
     * Handle webhook events
     *
     * @param string $payload Raw webhook payload.
     * @param string $signature Webhook signature for verification.
     * @return array Processed event data.
     */
    public function handleWebhook(string $payload, string $signature): array;

    /**
     * Check if the provider is properly configured
     *
     * @return bool True if configured, false otherwise.
     */
    public function isConfigured(): bool;
}
