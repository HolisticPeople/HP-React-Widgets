<?php
/**
 * PayPal Express Shop Gateway
 * 
 * Internal-only gateway for orders created via Express Shop funnel checkout.
 * Supports refunds via PayPal REST API using stored credentials.
 * 
 * @package HP_RW
 * @since 2.43.16
 */

namespace HP_RW\Gateway;

if (!defined('ABSPATH')) {
    exit;
}

class PayPalExpressGateway extends \WC_Payment_Gateway
{
    private const PAYPAL_SANDBOX_API = 'https://api-m.sandbox.paypal.com';
    private const PAYPAL_LIVE_API = 'https://api-m.paypal.com';

    public function __construct()
    {
        $this->id                 = 'hp_paypal_express';
        $this->method_title       = __('PayPal (Express Shop)', 'hp-react-widgets');
        $this->method_description = __('Internal gateway for Express Shop funnel orders. Not available for regular checkout.', 'hp-react-widgets');
        $this->has_fields         = false;
        $this->supports           = ['refunds'];

        // This gateway is internal only - never show on frontend
        $this->enabled = 'no';

        $this->title       = __('PayPal (Express Shop)', 'hp-react-widgets');
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
     * Process refund via PayPal REST API.
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

        // Get PayPal capture ID (required for refunds)
        $captureId = $order->get_meta('_hp_rw_paypal_capture_id');
        if (empty($captureId)) {
            // Try transaction ID as fallback
            $captureId = $order->get_transaction_id();
        }

        if (empty($captureId)) {
            return new \WP_Error('missing_capture_id', __('PayPal capture ID not found. Cannot process refund.', 'hp-react-widgets'));
        }

        // Get PayPal mode from order
        $paypalMode = $order->get_meta('_hp_rw_paypal_mode');
        if (empty($paypalMode)) {
            // Default to live for production orders
            $paypalMode = 'live';
        }

        // Get credentials
        $credentials = $this->getPayPalCredentials($paypalMode);
        if (empty($credentials['client_id']) || empty($credentials['secret'])) {
            return new \WP_Error('missing_credentials', __('PayPal credentials not configured.', 'hp-react-widgets'));
        }

        // Get access token
        $accessToken = $this->getAccessToken($credentials, $paypalMode);
        if (!$accessToken) {
            return new \WP_Error('auth_failed', __('Failed to authenticate with PayPal.', 'hp-react-widgets'));
        }

        // Build refund request
        $apiBase = $paypalMode === 'live' ? self::PAYPAL_LIVE_API : self::PAYPAL_SANDBOX_API;
        $refundUrl = $apiBase . '/v2/payments/captures/' . $captureId . '/refund';

        $refundPayload = [];
        if ($amount !== null && $amount > 0) {
            $refundPayload['amount'] = [
                'currency_code' => $order->get_currency(),
                'value' => number_format((float) $amount, 2, '.', ''),
            ];
        }
        if (!empty($reason)) {
            $refundPayload['note_to_payer'] = substr($reason, 0, 255);
        }

        $response = wp_remote_post($refundUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => !empty($refundPayload) ? wp_json_encode($refundPayload) : '{}',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('api_error', $response->get_error_message());
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 200 && $statusCode < 300) {
            $refundId = $body['id'] ?? '';
            $refundStatus = $body['status'] ?? '';

            // Add order note
            $order->add_order_note(sprintf(
                __('PayPal refund processed. Refund ID: %s, Status: %s, Amount: %s', 'hp-react-widgets'),
                $refundId,
                $refundStatus,
                wc_price($amount, ['currency' => $order->get_currency()])
            ));

            // Store refund ID
            $existingRefunds = $order->get_meta('_hp_rw_paypal_refund_ids') ?: [];
            $existingRefunds[] = $refundId;
            $order->update_meta_data('_hp_rw_paypal_refund_ids', $existingRefunds);
            $order->save();

            return true;
        }

        // Handle error
        $errorMsg = $body['message'] ?? ($body['details'][0]['description'] ?? 'Unknown PayPal error');
        return new \WP_Error('refund_failed', $errorMsg);
    }

    /**
     * Get PayPal credentials.
     */
    private function getPayPalCredentials(string $mode): array
    {
        $opts = get_option('hp_rw_paypal_settings', []);

        if ($mode === 'live') {
            return [
                'client_id' => $opts['live_client_id'] ?? '',
                'secret' => $opts['live_secret'] ?? '',
            ];
        }

        return [
            'client_id' => $opts['sandbox_client_id'] ?? '',
            'secret' => $opts['sandbox_secret'] ?? '',
        ];
    }

    /**
     * Get PayPal OAuth access token.
     */
    private function getAccessToken(array $credentials, string $mode): ?string
    {
        $cacheKey = 'hp_rw_paypal_token_' . $mode;
        $cached = get_transient($cacheKey);
        if ($cached) {
            return $cached;
        }

        $apiBase = $mode === 'live' ? self::PAYPAL_LIVE_API : self::PAYPAL_SANDBOX_API;

        $response = wp_remote_post($apiBase . '/v1/oauth2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($credentials['client_id'] . ':' . $credentials['secret']),
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $token = $body['access_token'] ?? null;
        $expiresIn = $body['expires_in'] ?? 3600;

        if ($token) {
            set_transient($cacheKey, $token, max(60, $expiresIn - 300));
        }

        return $token;
    }
}
