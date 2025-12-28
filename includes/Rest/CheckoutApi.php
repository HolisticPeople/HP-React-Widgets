<?php
namespace HP_RW\Rest;

use HP_RW\Services\StripeService;
use HP_RW\Services\ShippingService;
use HP_RW\Services\CheckoutService;
use HP_RW\Services\PointsService;
use HP_RW\Services\FunnelConfigLoader;
use HP_RW\Util\Resolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for checkout operations.
 * 
 * Provides endpoints for:
 * - Shipping rates calculation
 * - Cart totals calculation
 * - Payment intent creation
 * - Order completion
 * - Customer lookup
 * - Product catalog/prices
 */
class CheckoutApi
{
    /**
     * Register REST routes.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register all checkout REST routes.
     */
    public function register_routes(): void
    {
        $namespace = 'hp-rw/v1';

        // Customer lookup
        register_rest_route($namespace, '/checkout/customer', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_customer_lookup'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email' => [
                    'required'          => true,
                    'validate_callback' => fn($v) => is_email($v),
                ],
            ],
        ]);

        // Shipping rates
        register_rest_route($namespace, '/checkout/shipping-rates', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_shipping_rates'],
            'permission_callback' => '__return_true',
        ]);

        // Totals calculation
        register_rest_route($namespace, '/checkout/totals', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_totals'],
            'permission_callback' => '__return_true',
        ]);

        // Create payment intent
        register_rest_route($namespace, '/checkout/create-intent', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_create_intent'],
            'permission_callback' => '__return_true',
        ]);

        // Complete order after payment
        register_rest_route($namespace, '/checkout/complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_complete'],
            'permission_callback' => '__return_true',
        ]);

        // Get order summary
        register_rest_route($namespace, '/checkout/order-summary', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_order_summary'],
            'permission_callback' => '__return_true',
        ]);

        // Catalog prices
        register_rest_route($namespace, '/catalog/prices', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_catalog_prices'],
            'permission_callback' => '__return_true',
        ]);

        // Funnel status
        register_rest_route($namespace, '/funnel/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_funnel_status'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Look up a customer by email.
     * Returns user info, addresses (including all from HP-Multi-Address), and points balance if the user exists.
     */
    public function handle_customer_lookup(WP_REST_Request $request): WP_REST_Response
    {
        $email = sanitize_email($request->get_param('email'));
        error_log('[HP-RW] Customer lookup for: ' . $email);
        $user = get_user_by('email', $email);

        if (!$user) {
            error_log('[HP-RW] Customer lookup: User not found for ' . $email);
            return new WP_REST_Response([
                'user_id'         => 0,
                'points_balance'  => 0,
                'billing'         => null,
                'shipping'        => null,
                'all_addresses'   => ['billing' => [], 'shipping' => []],
            ]);
        }

        error_log('[HP-RW] Customer lookup: Found user ID ' . $user->ID);
        $pointsService = new PointsService();
        $customer = new \WC_Customer($user->ID);

        // Helper to normalize country to 2-letter code
        $normalizeCountry = function($country) {
            if (empty($country)) return '';
            // If already a 2-letter code, return as-is
            if (strlen($country) === 2) return strtoupper($country);
            // Try to convert full name to code using WooCommerce
            $countries = WC()->countries->get_countries();
            $code = array_search($country, $countries);
            return $code !== false ? $code : $country;
        };

        // Get primary addresses
        $primaryBilling = [
            'id'         => 'billing_primary',
            'first_name' => $customer->get_billing_first_name(),
            'last_name'  => $customer->get_billing_last_name(),
            'company'    => $customer->get_billing_company(),
            'address_1'  => $customer->get_billing_address_1(),
            'address_2'  => $customer->get_billing_address_2(),
            'city'       => $customer->get_billing_city(),
            'state'      => $customer->get_billing_state(),
            'postcode'   => $customer->get_billing_postcode(),
            'country'    => $normalizeCountry($customer->get_billing_country()),
            'phone'      => $customer->get_billing_phone(),
            'email'      => $customer->get_billing_email(),
            'is_default' => true,
        ];

        $primaryShipping = [
            'id'         => 'shipping_primary',
            'first_name' => $customer->get_shipping_first_name(),
            'last_name'  => $customer->get_shipping_last_name(),
            'company'    => $customer->get_shipping_company(),
            'address_1'  => $customer->get_shipping_address_1(),
            'address_2'  => $customer->get_shipping_address_2(),
            'city'       => $customer->get_shipping_city(),
            'state'      => $customer->get_shipping_state(),
            'postcode'   => $customer->get_shipping_postcode(),
            'country'    => $normalizeCountry($customer->get_shipping_country()),
            'phone'      => $customer->get_shipping_phone(),
            'is_default' => true,
        ];

        // Get all addresses from HP-Multi-Address if available
        $allAddresses = ['billing' => [], 'shipping' => []];
        
        // Add primary addresses first
        if (!empty($primaryBilling['address_1'])) {
            $allAddresses['billing'][] = $primaryBilling;
        }
        if (!empty($primaryShipping['address_1'])) {
            $allAddresses['shipping'][] = $primaryShipping;
        }

        // Try to get additional addresses from HP-Multi-Address
        if (class_exists('\\HP_MA\\AddressManager')) {
            $additionalBilling = \HP_MA\AddressManager::get_addresses($user->ID, 'billing');
            $additionalShipping = \HP_MA\AddressManager::get_addresses($user->ID, 'shipping');

            // Helper to extract address field with or without prefix
            $getField = function($addr, $type, $field) {
                $prefixed = $type . '_' . $field;
                return $addr[$prefixed] ?? $addr[$field] ?? '';
            };

            if (is_array($additionalBilling)) {
                foreach ($additionalBilling as $key => $addr) {
                    if (!is_array($addr)) continue;
                    $allAddresses['billing'][] = [
                        'id'         => 'billing_' . $key,
                        'first_name' => $getField($addr, 'billing', 'first_name'),
                        'last_name'  => $getField($addr, 'billing', 'last_name'),
                        'company'    => $getField($addr, 'billing', 'company'),
                        'address_1'  => $getField($addr, 'billing', 'address_1'),
                        'address_2'  => $getField($addr, 'billing', 'address_2'),
                        'city'       => $getField($addr, 'billing', 'city'),
                        'state'      => $getField($addr, 'billing', 'state'),
                        'postcode'   => $getField($addr, 'billing', 'postcode'),
                        'country'    => $normalizeCountry($getField($addr, 'billing', 'country')),
                        'phone'      => $getField($addr, 'billing', 'phone'),
                        'email'      => $getField($addr, 'billing', 'email'),
                        'is_default' => false,
                    ];
                }
            }

            if (is_array($additionalShipping)) {
                foreach ($additionalShipping as $key => $addr) {
                    if (!is_array($addr)) continue;
                    $allAddresses['shipping'][] = [
                        'id'         => 'shipping_' . $key,
                        'first_name' => $getField($addr, 'shipping', 'first_name'),
                        'last_name'  => $getField($addr, 'shipping', 'last_name'),
                        'company'    => $getField($addr, 'shipping', 'company'),
                        'address_1'  => $getField($addr, 'shipping', 'address_1'),
                        'address_2'  => $getField($addr, 'shipping', 'address_2'),
                        'city'       => $getField($addr, 'shipping', 'city'),
                        'state'      => $getField($addr, 'shipping', 'state'),
                        'postcode'   => $getField($addr, 'shipping', 'postcode'),
                        'country'    => $normalizeCountry($getField($addr, 'shipping', 'country')),
                        'phone'      => $getField($addr, 'shipping', 'phone'),
                        'is_default' => false,
                    ];
                }
            }
        }

        return new WP_REST_Response([
            'user_id'        => $user->ID,
            'points_balance' => $pointsService->getCustomerPoints($user->ID),
            'billing'        => $primaryBilling,
            'shipping'       => $primaryShipping,
            'all_addresses'  => $allAddresses,
        ]);
    }

    /**
     * Get shipping rates for given address and items.
     */
    public function handle_shipping_rates(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $address = (array) $request->get_param('address');
            $items = (array) $request->get_param('items');

            if (empty($items)) {
                return new WP_Error('bad_request', 'Items required', ['status' => 400]);
            }

            $shippingService = new ShippingService();
            $result = $shippingService->getRates($address, $items);

            if (!$result['success']) {
                error_log('[HP-RW CheckoutApi] Shipping rates failed: ' . ($result['error'] ?? 'Unknown error'));
                return new WP_Error('shipping_error', $result['error'] ?? 'Failed to get rates', ['status' => 502]);
            }

            return new WP_REST_Response(['rates' => $result['rates']]);
        } catch (\Throwable $e) {
            error_log('[HP-RW CheckoutApi] Shipping rates exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('shipping_error', 'Shipping calculation failed: ' . $e->getMessage(), ['status' => 502]);
        }
    }

    /**
     * Calculate totals for a cart.
     */
    public function handle_totals(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $items = (array) $request->get_param('items');
        $address = (array) $request->get_param('address');
        $selectedRate = $request->get_param('selected_rate');
        $pointsToRedeem = (int) ($request->get_param('points_to_redeem') ?? 0);
        $funnelId = (string) ($request->get_param('funnel_id') ?? 'default');
        $offerTotal = $request->get_param('offer_total');  // Admin-set total price

        error_log('[HP-RW] handle_totals: items=' . count($items) . ' points=' . $pointsToRedeem . ' funnel=' . $funnelId);

        if (empty($items)) {
            return new WP_Error('bad_request', 'Items required', ['status' => 400]);
        }

        // Get funnel config for global discount
        $globalDiscountPercent = $this->getFunnelGlobalDiscount($funnelId);

        $checkoutService = new CheckoutService();
        $totals = $checkoutService->calculateTotals(
            $items,
            $address,
            $selectedRate,
            $pointsToRedeem,
            $globalDiscountPercent,
            [],
            $offerTotal !== null ? (float) $offerTotal : null  // Pass offer total
        );

        return new WP_REST_Response($totals);
    }

    /**
     * Create a Stripe PaymentIntent for checkout.
     */
    public function handle_create_intent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $funnelId = (string) ($request->get_param('funnel_id') ?? 'default');
        $funnelName = (string) ($request->get_param('funnel_name') ?? 'Funnel');
        $customer = (array) $request->get_param('customer');
        $address = (array) $request->get_param('shipping_address');
        $items = (array) $request->get_param('items');
        $selectedRate = $request->get_param('selected_rate');
        $pointsToRedeem = (int) ($request->get_param('points_to_redeem') ?? 0);
        $analytics = (array) ($request->get_param('analytics') ?? []);
        $offerTotal = $request->get_param('offer_total');  // Admin-set total price

        if (empty($items)) {
            return new WP_Error('bad_request', 'Items required', ['status' => 400]);
        }

        $email = isset($customer['email']) ? (string) $customer['email'] : '';
        if ($email === '' || !is_email($email)) {
            return new WP_Error('bad_request', 'Valid customer email required', ['status' => 400]);
        }

        // Check funnel status (legacy gate)
        $funnelMode = $this->getFunnelMode($funnelId);
        if ($funnelMode === 'off') {
            return new WP_Error('funnel_off', 'Funnel is currently disabled', [
                'status'   => 409,
                'redirect' => home_url('/'),
            ]);
        }

        // Initialize Stripe with funnel stripe_mode (from funnel config), fallback to legacy mode
        $stripeMode = $this->getStripeModeForFunnel($funnelId);
        $stripe = new StripeService($stripeMode);

        if (!$stripe->isConfigured()) {
            return new WP_Error('stripe_not_configured', 'Stripe keys are missing', ['status' => 500]);
        }

        // Get global discount for this funnel
        $globalDiscountPercent = $this->getFunnelGlobalDiscount($funnelId);

        // Calculate totals
        $checkoutService = new CheckoutService();
        $totals = $checkoutService->calculateTotals(
            $items,
            $address,
            $selectedRate,
            $pointsToRedeem,
            $globalDiscountPercent,
            [],
            $offerTotal !== null ? (float) $offerTotal : null  // Pass offer total
        );

        $grandTotal = $totals['grand_total'];
        $amountCents = (int) round($grandTotal * 100);

        if ($amountCents <= 0) {
            return new WP_Error('bad_amount', 'Amount must be greater than zero', ['status' => 400]);
        }

        // Create or get Stripe customer
        $name = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        $user = get_user_by('email', $email);
        $stripeCustomerId = $stripe->createOrGetCustomer($email, $name, $user ? (int) $user->ID : 0);

        if (!$stripeCustomerId) {
            return new WP_Error('stripe_customer', 'Could not create Stripe customer', ['status' => 502]);
        }

        // Create draft order
        $draftData = [
            'funnel_id'               => $funnelId,
            'funnel_name'             => $funnelName,
            'stripe_mode'             => $stripeMode,
            'customer'                => ['email' => $email, 'name' => $name, 'user_id' => $user ? (int) $user->ID : 0],
            'shipping_address'        => $address,
            'items'                   => $items,
            'selected_rate'           => $selectedRate,
            'points_to_redeem'        => $pointsToRedeem,
            'global_discount_percent' => $globalDiscountPercent,
            'offer_total'             => $offerTotal !== null ? (float) $offerTotal : null,
            'analytics'               => $analytics,
            'currency'                => get_woocommerce_currency() ?: 'USD',
            'amount'                  => $grandTotal,
            'stripe_customer'         => $stripeCustomerId,
        ];

        $draftId = $checkoutService->createDraft($draftData);

        // Create PaymentIntent
        $params = [
            'amount'                                               => $amountCents,
            'currency'                                             => strtolower(get_woocommerce_currency() ?: 'usd'),
            'customer'                                             => $stripeCustomerId,
            'payment_method_types[]'                               => 'card',
            'payment_method_options[card][setup_future_usage]'     => 'off_session',
            'description'                                          => 'HolisticPeople - ' . $funnelName,
            'metadata[order_draft_id]'                             => $draftId,
            'metadata[funnel_id]'                                  => $funnelId,
            'metadata[funnel_name]'                                => $funnelName,
        ];

        $pi = $stripe->createPaymentIntent($params);

        if (!$pi || empty($pi['client_secret'])) {
            return new WP_Error('stripe_pi', 'Could not create PaymentIntent', ['status' => 502]);
        }

        return new WP_REST_Response([
            'client_secret'  => (string) $pi['client_secret'],
            'publishable'    => $stripe->publishable,
            'order_draft_id' => $draftId,
            'amount_cents'   => $amountCents,
            'pi_id'          => $pi['id'] ?? '',
        ]);
    }

    /**
     * Complete an order after successful payment.
     */
    public function handle_complete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $draftId = (string) $request->get_param('order_draft_id');
        $piId = (string) $request->get_param('pi_id');

        error_log('[HP-RW] handle_complete hit with draft: ' . $draftId . ' and PI: ' . $piId);

        if ($draftId === '' || $piId === '') {
            return new WP_Error('bad_request', 'Draft ID and PaymentIntent ID required', ['status' => 400]);
        }

        $checkoutService = new CheckoutService();
        $draftData = $checkoutService->getDraft($draftId);

        if (!$draftData) {
            return new WP_Error('not_found', 'Order draft not found', ['status' => 404]);
        }

        // Verify payment with Stripe
        $funnelId = (string) ($draftData['funnel_id'] ?? 'default');
        $stripeMode = (string) ($draftData['stripe_mode'] ?? $this->getStripeModeForFunnel($funnelId));
        $stripe = new StripeService($stripeMode);

        $pi = $stripe->retrievePaymentIntent($piId);
        if (!$pi || ($pi['status'] ?? '') !== 'succeeded') {
            return new WP_Error('payment_failed', 'Payment has not succeeded', ['status' => 400]);
        }

        // Extract payment details
        $stripeCustomerId = $pi['customer'] ?? $draftData['stripe_customer'] ?? '';
        $chargeId = '';
        $paymentMethodId = $pi['payment_method'] ?? '';

        if (!empty($pi['latest_charge'])) {
            $chargeId = $pi['latest_charge'];
        }

        // Create the order
        $order = $checkoutService->createOrderFromDraft(
            $draftData,
            $stripeCustomerId,
            $piId,
            $chargeId,
            $paymentMethodId
        );

        if (!$order) {
            return new WP_Error('order_failed', 'Failed to create order', ['status' => 500]);
        }

        // Clean up draft
        $checkoutService->deleteDraft($draftId);

        // Note: Points deduction is handled by YITH based on the order meta fields
        // (_ywpar_coupon_points and _ywpar_coupon_amount) set during order creation.
        // We no longer manually deduct points here to avoid conflicts and "refunds" on status change.

        return new WP_REST_Response([
            'success'      => true,
            'order_id'     => $order->get_id(),
            'order_number' => $order->get_order_number(),
        ]);
    }

    /**
     * Get order summary for thank you page.
     */
    public function handle_order_summary(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $orderId = (int) $request->get_param('order_id');
        $piId = (string) $request->get_param('pi_id');

        $order = null;
        if ($orderId > 0) {
            $order = wc_get_order($orderId);
        } elseif ($piId !== '') {
            // Try to find order by PI ID meta
            $orders = wc_get_orders([
                'limit'      => 1,
                'meta_key'   => '_hp_rw_stripe_pi_id',
                'meta_value' => $piId,
            ]);
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }

        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        // Verify pi_id matches for security (if provided)
        $storedPiId = $order->get_meta('_hp_rw_stripe_pi_id');
        if ($piId !== '' && $storedPiId !== $piId) {
            return new WP_Error('unauthorized', 'Invalid authorization', ['status' => 403]);
        }

        $items = [];
        $itemsDiscount = 0.0;
        foreach ($order->get_items() as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            $imageUrl = '';

            if ($product) {
                $imageId = $product->get_image_id();
                if ($imageId) {
                    $imageData = wp_get_attachment_image_src($imageId, 'woocommerce_thumbnail');
                    if ($imageData && isset($imageData[0])) {
                        $imageUrl = $imageData[0];
                    }
                }
            }

            // Get the price intended by the funnel (before coupons)
            $funnelPrice = (float) $item->get_meta('_hp_rw_funnel_price');
            if ($funnelPrice <= 0) {
                // Fallback: if meta is missing, use item subtotal which is MSRP
                $funnelPrice = (float) ($item->get_subtotal() / max(1, $item->get_quantity()));
            }

            $items[] = [
                'name'     => $item->get_name(),
                'qty'      => $item->get_quantity(),
                'price'    => $funnelPrice,
                'subtotal' => (float) $item->get_subtotal(),
                'total'    => $funnelPrice * $item->get_quantity(),
                'image'    => $imageUrl,
                'sku'      => $product ? $product->get_sku() : '',
            ];

            // Item discount is MSRP - Intended Price
            $itemsDiscount += ((float) $item->get_subtotal() - ($funnelPrice * $item->get_quantity()));
        }

        // Calculate fees and points breakdown
        $feesTotal = 0.0;
        $pointsRedeemed = ['points' => 0, 'value' => 0.0];
        $extraDiscounts = 0.0;

        // 1. Points from meta (our robust coupon flow)
        $metaPointsValue = (float) $order->get_meta('_ywpar_coupon_amount');
        if ($metaPointsValue > 0) {
            $pointsRedeemed['value'] = $metaPointsValue;
            $pointsRedeemed['points'] = (int) $order->get_meta('_ywpar_coupon_points');
        }

        // 2. Scan fees for "Savings" or other discounts
        foreach ($order->get_fees() as $fee) {
            $feeTotal = (float) $fee->get_total();
            $feesTotal += $feeTotal;

            // If it's a discount fee (negative) and NOT points, count it as a general discount
            if ($feeTotal < 0 && stripos($fee->get_name(), 'points') === false) {
                $extraDiscounts += abs($feeTotal);
            }

            // Legacy points fee fallback
            if ($pointsRedeemed['value'] <= 0 && stripos($fee->get_name(), 'points') !== false) {
                $pointsService = new PointsService();
                $pointsRedeemed['value'] = abs($feeTotal);
                $pointsRedeemed['points'] = $pointsService->moneyToPoints(abs($feeTotal));
            }
        }

        // 3. Scan coupons for other discounts
        $couponDiscount = (float) $order->get_discount_total();
        // total_discount includes our points coupon, so we must subtract it to avoid double counting
        $otherCouponDiscount = max(0.0, $couponDiscount - $pointsRedeemed['value']);
        $itemsDiscount += $extraDiscounts + $otherCouponDiscount;

        return new WP_REST_Response([
            'order_id'        => $order->get_id(),
            'order_number'    => $order->get_order_number(),
            'items'           => $items,
            'shipping_total'  => (float) $order->get_shipping_total(),
            'fees_total'      => $feesTotal,
            'points_redeemed' => $pointsRedeemed,
            'items_discount'  => $itemsDiscount,
            'grand_total'     => (float) $order->get_total(),
            'status'          => $order->get_status(),
        ]);
    }

    /**
     * Get prices for catalog products.
     */
    public function handle_catalog_prices(WP_REST_Request $request): WP_REST_Response
    {
        $skusParam = $request->get_param('skus');
        $skus = is_string($skusParam) ? explode(',', $skusParam) : [];
        $skus = array_filter(array_map('trim', $skus));

        if (empty($skus)) {
            return new WP_REST_Response(['prices' => []]);
        }

        $prices = Resolver::getPricesForSkus($skus);

        return new WP_REST_Response([
            'ok'     => true,
            'prices' => $prices,
        ]);
    }

    /**
     * Get funnel status.
     */
    public function handle_funnel_status(WP_REST_Request $request): WP_REST_Response
    {
        $funnelId = (string) ($request->get_param('funnel_id') ?? 'default');
        $mode = $this->getFunnelMode($funnelId);

        return new WP_REST_Response([
            'funnel_id' => $funnelId,
            'mode'      => $mode,
        ]);
    }

    /**
     * Get funnel mode (test/live/off).
     */
    private function getFunnelMode(string $funnelId): string
    {
        $opts = get_option('hp_rw_settings', []);
        $env = isset($opts['env']) && $opts['env'] === 'production' ? 'production' : 'staging';

        // Check funnel-specific config
        if (!empty($opts['funnels']) && is_array($opts['funnels'])) {
            foreach ($opts['funnels'] as $f) {
                if (is_array($f) && !empty($f['id']) && (string) $f['id'] === $funnelId) {
                    if ($env === 'staging') {
                        return $f['mode_staging'] ?? 'test';
                    } else {
                        return $f['mode_production'] ?? 'live';
                    }
                }
            }
        }

        // Default based on environment
        return $env === 'production' ? 'live' : 'test';
    }

    /**
     * Resolve Stripe mode for a funnel using the funnel CPT config (`stripe_mode`),
     * falling back to the legacy env-driven funnel mode.
     *
     * @return string 'test' | 'live'
     */
    private function getStripeModeForFunnel(string $funnelId): string
    {
        $postId = absint($funnelId);
        if ($postId > 0) {
            $config = FunnelConfigLoader::getById($postId);
            if (is_array($config)) {
                $mode = strtolower(trim((string) ($config['stripe_mode'] ?? 'auto')));
                if ($mode === 'test' || $mode === 'live') {
                    return $mode;
                }
            }
        }

        $legacy = $this->getFunnelMode($funnelId);
        return ($legacy === 'live') ? 'live' : 'test';
    }

    /**
     * Get global discount percentage for a funnel.
     */
    private function getFunnelGlobalDiscount(string $funnelId): float
    {
        $opts = get_option('hp_rw_settings', []);

        // Check funnel-specific config
        if (!empty($opts['funnel_configs']) && is_array($opts['funnel_configs'])) {
            if (isset($opts['funnel_configs'][$funnelId]['global_discount_percent'])) {
                return (float) $opts['funnel_configs'][$funnelId]['global_discount_percent'];
            }
        }

        // Default global discount
        return (float) ($opts['default_global_discount'] ?? 0);
    }
}















