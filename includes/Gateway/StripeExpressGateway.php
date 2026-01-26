<?php
/**
 * Stripe Express Shop Gateway
 * 
 * Internal-only gateway for orders created via Express Shop funnel checkout.
 * Supports refunds via Stripe API using stored credentials.
 * 
 * @package HP_RW
 * @since 2.43.16
 */

namespace HP_RW\Gateway;

if (!defined('ABSPATH')) {
    exit;
}

class StripeExpressGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id                 = 'hp_stripe_express';
        $this->method_title       = __('Stripe (Express Shop)', 'hp-react-widgets');
        $this->method_description = __('Internal gateway for Express Shop funnel orders. Not available for regular checkout.', 'hp-react-widgets');
        $this->has_fields         = false;
        $this->supports           = ['refunds'];

        // This gateway is internal only - never show on frontend
        $this->enabled = 'no';

        $this->title       = __('Stripe (Express Shop)', 'hp-react-widgets');
        $this->description = '';
    }

    /**
     * This gateway is internal only - never available for checkout.
     */
    public function is_available(): bool
    {
        return false;
    }

    /**
     * Process refund via Stripe API.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount   Refund amount.
     * @param string $reason   Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return new \WP_Error('invalid_order', __('Order not found.', 'hp-react-widgets'));
        }

        // Get Stripe Payment Intent ID
        $paymentIntentId = $order->get_meta('_hp_rw_stripe_pi_id');
        if (empty($paymentIntentId)) {
            return new \WP_Error('missing_pi_id', __('Stripe Payment Intent ID not found. Cannot process refund.', 'hp-react-widgets'));
        }

        // Get Stripe mode from order (or default to live)
        $stripeMode = $order->get_meta('_hp_rw_stripe_mode');
        if (empty($stripeMode)) {
            $stripeMode = 'live';
        }

        // Get secret key
        $secretKey = $this->getStripeSecretKey($stripeMode);
        if (empty($secretKey)) {
            return new \WP_Error('missing_credentials', __('Stripe credentials not configured.', 'hp-react-widgets'));
        }

        // Build refund request
        $refundArgs = [
            'payment_intent' => $paymentIntentId,
        ];

        if ($amount !== null && $amount > 0) {
            // Stripe expects amount in cents
            $refundArgs['amount'] = (int) round($amount * 100);
        }

        if (!empty($reason)) {
            $refundArgs['reason'] = 'requested_by_customer';
            $refundArgs['metadata'] = ['reason' => substr($reason, 0, 500)];
        }

        // Call Stripe API
        $response = wp_remote_post('https://api.stripe.com/v1/refunds', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($refundArgs),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 200 && $statusCode < 300 && isset($body['id'])) {
            $refundId = $body['id'];
            $refundStatus = $body['status'] ?? 'succeeded';

            // Add order note
            $order->add_order_note(sprintf(
                __('Stripe refund processed. Refund ID: %s, Status: %s, Amount: %s', 'hp-react-widgets'),
                $refundId,
                $refundStatus,
                wc_price($amount, ['currency' => $order->get_currency()])
            ));

            // Store refund ID
            $existingRefunds = $order->get_meta('_hp_rw_stripe_refund_ids') ?: [];
            $existingRefunds[] = $refundId;
            $order->update_meta_data('_hp_rw_stripe_refund_ids', $existingRefunds);
            $order->save();

            return true;
        }

        // Handle error
        $errorMsg = $body['error']['message'] ?? 'Unknown Stripe error';
        return new \WP_Error('refund_failed', $errorMsg);
    }

    /**
     * Get Stripe secret key based on mode.
     * Uses WooCommerce Stripe Gateway settings (same as StripeService).
     */
    private function getStripeSecretKey(string $mode): string
    {
        $stripeSettings = get_option('woocommerce_stripe_settings', []);
        $stripeApiSettings = get_option('woocommerce_stripe_api_settings', []);

        if ($mode === 'live') {
            return (string) ($stripeSettings['secret_key'] ?: ($stripeApiSettings['secret_key_live'] ?? ''));
        }

        return (string) ($stripeSettings['test_secret_key'] ?: ($stripeApiSettings['secret_key_test'] ?? ''));
    }
}
