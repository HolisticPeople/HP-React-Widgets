<?php
namespace HP_RW\Rest;

use HP_RW\Services\CheckoutService;
use HP_RW\Services\FunnelConfigLoader;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for PayPal checkout operations.
 * 
 * @package HP_RW
 * @since 2.39.0
 */
class PayPalApi
{
    private const PAYPAL_SANDBOX_API = 'https://api-m.sandbox.paypal.com';
    private const PAYPAL_LIVE_API = 'https://api-m.paypal.com';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        $namespace = 'hp-rw/v1';

        register_rest_route($namespace, '/paypal/create-order', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_create_order'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/paypal/capture-order', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_capture_order'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Create a PayPal order.
     */
    public function handle_create_order(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $funnelId = (string) ($request->get_param('funnel_id') ?? 'default');
        $funnelName = (string) ($request->get_param('funnel_name') ?? 'Funnel');
        $customer = (array) $request->get_param('customer');
        $address = (array) $request->get_param('shipping_address');
        $items = (array) $request->get_param('items');
        $selectedRate = $request->get_param('selected_rate');
        $pointsToRedeem = (int) ($request->get_param('points_to_redeem') ?? 0);
        $offerTotal = $request->get_param('offer_total');

        if (empty($items)) {
            return new WP_Error('bad_request', 'Items required', ['status' => 400]);
        }

        $email = isset($customer['email']) ? (string) $customer['email'] : '';
        if ($email === '' || !is_email($email)) {
            return new WP_Error('bad_request', 'Valid customer email required', ['status' => 400]);
        }

        // Get PayPal credentials
        $paypalMode = $this->getPayPalModeForFunnel($funnelId);
        $credentials = $this->getPayPalCredentials($paypalMode);
        
        if (!$credentials['client_id'] || !$credentials['secret']) {
            return new WP_Error('paypal_not_configured', 'PayPal credentials not configured', ['status' => 500]);
        }

        // Calculate totals
        $globalDiscountPercent = $this->getFunnelGlobalDiscount($funnelId);
        $checkoutService = new CheckoutService();
        $totals = $checkoutService->calculateTotals(
            $items,
            $address,
            $selectedRate,
            $pointsToRedeem,
            $globalDiscountPercent,
            [],
            $offerTotal !== null ? (float) $offerTotal : null
        );

        $grandTotal = $totals['grand_total'];
        if ($grandTotal <= 0) {
            return new WP_Error('bad_amount', 'Amount must be greater than zero', ['status' => 400]);
        }

        $currency = strtoupper(get_woocommerce_currency() ?: 'USD');
        $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        $user = get_user_by('email', $email);

        // Create draft order data
        $draftData = [
            'funnel_id'               => $funnelId,
            'funnel_name'             => $funnelName,
            'payment_method'          => 'paypal',
            'paypal_mode'             => $paypalMode,
            'customer'                => ['email' => $email, 'name' => $name, 'user_id' => $user ? (int) $user->ID : 0],
            'shipping_address'        => $address,
            'items'                   => $items,
            'selected_rate'           => $selectedRate,
            'points_to_redeem'        => $pointsToRedeem,
            'global_discount_percent' => $globalDiscountPercent,
            'offer_total'             => $offerTotal !== null ? (float) $offerTotal : null,
            'currency'                => $currency,
            'amount'                  => $grandTotal,
        ];

        $draftId = $checkoutService->createDraft($draftData);

        // Get access token
        $accessToken = $this->getAccessToken($credentials, $paypalMode);
        if (!$accessToken) {
            return new WP_Error('paypal_auth', 'Failed to authenticate with PayPal', ['status' => 502]);
        }

        // Create PayPal order
        $apiBase = $paypalMode === 'live' ? self::PAYPAL_LIVE_API : self::PAYPAL_SANDBOX_API;
        
        $orderPayload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $draftId,
                    'description' => 'HolisticPeople - ' . $funnelName,
                    'custom_id' => $draftId,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($grandTotal, 2, '.', ''),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'brand_name' => 'HolisticPeople',
                        'locale' => 'en-US',
                        'landing_page' => 'LOGIN',
                        'user_action' => 'PAY_NOW',
                        'return_url' => home_url('/checkout/paypal-return'),
                        'cancel_url' => home_url('/checkout/paypal-cancel'),
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($apiBase . '/v2/checkout/orders', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => $draftId,
            ],
            'body' => wp_json_encode($orderPayload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $checkoutService->deleteDraft($draftId);
            return new WP_Error('paypal_api', 'PayPal API error: ' . $response->get_error_message(), ['status' => 502]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 && $statusCode !== 201) {
            $checkoutService->deleteDraft($draftId);
            $errorMsg = $body['message'] ?? $body['details'][0]['description'] ?? 'Unknown PayPal error';
            return new WP_Error('paypal_order', 'Failed to create PayPal order: ' . $errorMsg, ['status' => 502]);
        }

        $paypalOrderId = $body['id'] ?? '';
        if (!$paypalOrderId) {
            $checkoutService->deleteDraft($draftId);
            return new WP_Error('paypal_order', 'PayPal order ID not returned', ['status' => 502]);
        }

        // Store PayPal order ID in draft
        $draftData['paypal_order_id'] = $paypalOrderId;
        $checkoutService->updateDraft($draftId, $draftData);

        return new WP_REST_Response([
            'success' => true,
            'paypal_order_id' => $paypalOrderId,
            'draft_id' => $draftId,
        ]);
    }

    /**
     * Capture a PayPal order after buyer approval.
     */
    public function handle_capture_order(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $paypalOrderId = (string) $request->get_param('paypal_order_id');
        
        if (empty($paypalOrderId)) {
            return new WP_Error('bad_request', 'PayPal order ID required', ['status' => 400]);
        }

        $checkoutService = new CheckoutService();
        
        // Find draft by PayPal order ID
        $draftData = $checkoutService->findDraftByPayPalOrderId($paypalOrderId);
        if (!$draftData) {
            return new WP_Error('not_found', 'Order draft not found', ['status' => 404]);
        }

        $draftId = $draftData['draft_id'] ?? '';
        $paypalMode = $draftData['paypal_mode'] ?? 'sandbox';
        $credentials = $this->getPayPalCredentials($paypalMode);

        if (!$credentials['client_id'] || !$credentials['secret']) {
            return new WP_Error('paypal_not_configured', 'PayPal credentials not configured', ['status' => 500]);
        }

        $accessToken = $this->getAccessToken($credentials, $paypalMode);
        if (!$accessToken) {
            return new WP_Error('paypal_auth', 'Failed to authenticate with PayPal', ['status' => 502]);
        }

        // Capture the order
        $apiBase = $paypalMode === 'live' ? self::PAYPAL_LIVE_API : self::PAYPAL_SANDBOX_API;
        
        $response = wp_remote_post($apiBase . '/v2/checkout/orders/' . $paypalOrderId . '/capture', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'body' => '{}',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('paypal_api', 'PayPal API error: ' . $response->get_error_message(), ['status' => 502]);
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200 && $statusCode !== 201) {
            $errorMsg = $body['message'] ?? $body['details'][0]['description'] ?? 'Unknown PayPal error';
            return new WP_Error('paypal_capture', 'Failed to capture payment: ' . $errorMsg, ['status' => 502]);
        }

        $captureStatus = $body['status'] ?? '';
        if ($captureStatus !== 'COMPLETED') {
            return new WP_Error('payment_failed', 'Payment was not completed. Status: ' . $captureStatus, ['status' => 400]);
        }

        // Extract capture details
        $captureId = '';
        $payerId = $body['payer']['payer_id'] ?? '';
        $payerEmail = $body['payer']['email_address'] ?? '';
        
        if (!empty($body['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = $body['purchase_units'][0]['payments']['captures'][0]['id'];
        }

        // Create WooCommerce order from draft
        $order = $checkoutService->createOrderFromDraft(
            $draftData,
            '', // No Stripe customer
            '', // No Stripe PI ID
            '', // No Stripe charge ID
            ''  // No Stripe payment method
        );

        if (!$order) {
            return new WP_Error('order_failed', 'Failed to create order', ['status' => 500]);
        }

        // Store PayPal metadata
        $order->update_meta_data('_hp_rw_payment_method', 'paypal');
        $order->update_meta_data('_hp_rw_paypal_order_id', $paypalOrderId);
        $order->update_meta_data('_hp_rw_paypal_capture_id', $captureId);
        $order->update_meta_data('_hp_rw_paypal_payer_id', $payerId);
        $order->update_meta_data('_hp_rw_paypal_payer_email', $payerEmail);
        $order->update_meta_data('_hp_rw_paypal_mode', $paypalMode);
        
        // Set payment method title
        $order->set_payment_method('paypal_funnel');
        $order->set_payment_method_title('PayPal (Funnel Checkout)');
        $order->set_transaction_id($captureId ?: $paypalOrderId);
        
        // Add order note
        $order->add_order_note(sprintf(
            'PayPal payment captured. Order ID: %s, Capture ID: %s, Payer: %s',
            $paypalOrderId,
            $captureId,
            $payerEmail
        ));
        
        $order->save();

        // Delete draft
        $checkoutService->deleteDraft($draftId);

        return new WP_REST_Response([
            'success' => true,
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
        ]);
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
            // Cache for slightly less than expiry
            set_transient($cacheKey, $token, max(60, $expiresIn - 300));
        }

        return $token;
    }

    /**
     * Get PayPal credentials based on mode.
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
     * Determine PayPal mode for a funnel (sandbox or live).
     */
    private function getPayPalModeForFunnel(string $funnelId): string
    {
        $postId = absint($funnelId);
        if ($postId > 0) {
            $config = FunnelConfigLoader::getById($postId);
            if (is_array($config)) {
                // Use same mode as Stripe
                $mode = strtolower(trim((string) ($config['stripe_mode'] ?? 'auto')));
                if ($mode === 'test' || $mode === 'sandbox') return 'sandbox';
                if ($mode === 'live') return 'live';
            }
        }

        // Fallback to environment setting
        $opts = get_option('hp_rw_settings', []);
        $env = isset($opts['env']) && $opts['env'] === 'production' ? 'production' : 'staging';
        return $env === 'production' ? 'live' : 'sandbox';
    }

    /**
     * Get global discount percentage for a funnel.
     */
    private function getFunnelGlobalDiscount(string $funnelId): float
    {
        $opts = get_option('hp_rw_settings', []);
        if (!empty($opts['funnel_configs']) && is_array($opts['funnel_configs'])) {
            if (isset($opts['funnel_configs'][$funnelId]['global_discount_percent'])) {
                return (float) $opts['funnel_configs'][$funnelId]['global_discount_percent'];
            }
        }
        return (float) ($opts['default_global_discount'] ?? 0);
    }
}
