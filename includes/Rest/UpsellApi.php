<?php
namespace HP_RW\Rest;

use HP_RW\Services\StripeService;
use HP_RW\Services\CheckoutService;
use HP_RW\Services\FunnelConfigLoader;
use HP_RW\Util\Resolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for one-click upsell operations.
 * 
 * Handles post-purchase upsells by charging the customer's saved
 * payment method without requiring re-entry of card details.
 */
class UpsellApi
{
    /**
     * Register REST routes.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register upsell REST routes.
     */
    public function register_routes(): void
    {
        $namespace = 'hp-rw/v1';

        // One-click upsell charge
        register_rest_route($namespace, '/upsell/charge', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_charge'],
            'permission_callback' => '__return_true',
        ]);

        // Get available upsell offers for an order
        register_rest_route($namespace, '/upsell/offers', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handle_get_offers'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Process a one-click upsell charge.
     * 
     * Charges the customer's saved payment method from the parent order
     * and adds the upsell items to that order.
     */
    public function handle_charge(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $parentOrderId = (int) $request->get_param('parent_order_id');
        $parentPiId = (string) $request->get_param('parent_pi_id');
        $items = (array) $request->get_param('items');
        $funnelName = (string) ($request->get_param('funnel_name') ?? '');

        if ($parentOrderId <= 0) {
            return new WP_Error('bad_request', 'Parent order ID required', ['status' => 400]);
        }

        if (empty($items)) {
            return new WP_Error('bad_request', 'Items required', ['status' => 400]);
        }

        // Get parent order
        $parentOrder = wc_get_order($parentOrderId);
        if (!$parentOrder) {
            return new WP_Error('not_found', 'Parent order not found', ['status' => 404]);
        }

        // Verify pi_id for security (acts as authorization token)
        $storedPiId = $parentOrder->get_meta('_hp_rw_stripe_pi_id');
        if ($parentPiId !== '' && $storedPiId !== $parentPiId) {
            return new WP_Error('unauthorized', 'Invalid authorization', ['status' => 403]);
        }

        // Get saved payment details from parent order
        $stripeCustomerId = $parentOrder->get_meta('_hp_rw_stripe_customer_id');
        $paymentMethodId = $parentOrder->get_meta('_hp_rw_stripe_pm_id');

        if (!$stripeCustomerId) {
            return new WP_Error('no_customer', 'No Stripe customer ID found on order', ['status' => 400]);
        }

        // Determine Stripe mode from funnel
        $funnelId = $parentOrder->get_meta('_hp_rw_funnel_id') ?: 'default';
        $stripeMode = $this->getFunnelStripeMode($funnelId);
        $stripe = new StripeService($stripeMode);

        if (!$stripe->isConfigured()) {
            return new WP_Error('stripe_not_configured', 'Stripe not configured', ['status' => 500]);
        }

        // If no specific payment method, try to get default from customer
        if (!$paymentMethodId) {
            $customerData = $stripe->retrieveCustomer($stripeCustomerId);
            if ($customerData && !empty($customerData['invoice_settings']['default_payment_method'])) {
                $paymentMethodId = $customerData['invoice_settings']['default_payment_method'];
            } else {
                // Try to list payment methods
                $paymentMethods = $stripe->listPaymentMethods($stripeCustomerId);
                if (!empty($paymentMethods)) {
                    $paymentMethodId = $paymentMethods[0]['id'] ?? '';
                }
            }
        }

        if (!$paymentMethodId) {
            return new WP_Error('no_payment_method', 'No saved payment method found', ['status' => 400]);
        }

        // Calculate upsell amount
        $upsellTotal = 0.0;
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) {
                continue;
            }

            $price = (float) $product->get_price();
            $itemTotal = $price * $qty;

            // Check for item-specific discount
            $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : null;
            if ($itemPct !== null && $itemPct >= 0) {
                $discounted = $price * (1 - ($itemPct / 100.0));
                $itemTotal = max(0.0, $discounted * $qty);
            }

            $upsellTotal += $itemTotal;
        }

        if ($upsellTotal <= 0) {
            return new WP_Error('invalid_amount', 'Upsell amount must be greater than zero', ['status' => 400]);
        }

        $amountCents = (int) round($upsellTotal * 100);

        // Create description
        $orderFunnelName = $funnelName ?: ($parentOrder->get_meta('_hp_rw_funnel_name') ?: 'Funnel');
        $description = sprintf(
            'HolisticPeople - %s (Upsell for Order #%s)',
            $orderFunnelName,
            $parentOrder->get_order_number()
        );

        // Charge off-session
        $pi = $stripe->chargeOffSession(
            $stripeCustomerId,
            $paymentMethodId,
            $amountCents,
            strtolower(get_woocommerce_currency() ?: 'usd'),
            [
                'parent_order_id' => (string) $parentOrderId,
                'upsell'          => 'true',
                'funnel_id'       => $funnelId,
            ]
        );

        if (!$pi) {
            return new WP_Error('charge_failed', 'Failed to create charge', ['status' => 502]);
        }

        // Check for charge errors
        if (!empty($pi['error'])) {
            $errorMessage = $pi['error']['message'] ?? 'Payment failed';
            return new WP_Error('charge_declined', $errorMessage, ['status' => 402]);
        }

        // Check status
        $status = $pi['status'] ?? '';
        if ($status !== 'succeeded') {
            // May require authentication - can't do one-click
            if ($status === 'requires_action' || $status === 'requires_confirmation') {
                return new WP_Error('requires_action', 'Additional authentication required', [
                    'status'        => 402,
                    'client_secret' => $pi['client_secret'] ?? '',
                ]);
            }
            return new WP_Error('charge_failed', 'Payment did not succeed: ' . $status, ['status' => 502]);
        }

        // Add items to the parent order
        $checkoutService = new CheckoutService();
        $addedAmount = $checkoutService->addItemsToOrder($parentOrder, $items);

        // Update order metadata
        $upsellPiId = $pi['id'] ?? '';
        $existingUpsellPis = $parentOrder->get_meta('_hp_rw_upsell_pi_ids');
        $upsellPis = $existingUpsellPis ? explode(',', $existingUpsellPis) : [];
        $upsellPis[] = $upsellPiId;
        $parentOrder->update_meta_data('_hp_rw_upsell_pi_ids', implode(',', $upsellPis));

        // Add order note
        $parentOrder->add_order_note(sprintf(
            'Upsell completed: %s item(s) added for $%.2f via one-click charge (PI: %s)',
            count($items),
            $addedAmount,
            $upsellPiId
        ));

        $parentOrder->save();

        // Update Stripe PI description
        $stripe->updatePaymentIntent($upsellPiId, ['description' => $description]);

        return new WP_REST_Response([
            'success'      => true,
            'order_id'     => $parentOrderId,
            'upsell_total' => $upsellTotal,
            'pi_id'        => $upsellPiId,
        ]);
    }

    /**
     * Get available upsell offers for an order.
     */
    public function handle_get_offers(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $orderId = (int) $request->get_param('order_id');
        $piId = (string) $request->get_param('pi_id');
        $funnelId = (string) ($request->get_param('funnel_id') ?? '');

        if ($orderId <= 0) {
            return new WP_Error('bad_request', 'Order ID required', ['status' => 400]);
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            return new WP_Error('not_found', 'Order not found', ['status' => 404]);
        }

        // Verify pi_id for security
        $storedPiId = $order->get_meta('_hp_rw_stripe_pi_id');
        if ($piId !== '' && $storedPiId !== $piId) {
            return new WP_Error('unauthorized', 'Invalid authorization', ['status' => 403]);
        }

        // Get funnel ID from order if not provided
        if ($funnelId === '') {
            $funnelId = $order->get_meta('_hp_rw_funnel_id') ?: 'default';
        }

        // Get upsell offers from funnel config
        $offers = $this->getUpsellOffers($funnelId);

        // Filter out products already in the order
        $orderSkus = [];
        foreach ($order->get_items() as $item) {
            if ($item instanceof \WC_Order_Item_Product) {
                $product = $item->get_product();
                if ($product) {
                    $orderSkus[] = $product->get_sku();
                }
            }
        }

        $filteredOffers = array_filter($offers, function ($offer) use ($orderSkus) {
            return !in_array($offer['sku'] ?? '', $orderSkus, true);
        });

        return new WP_REST_Response([
            'offers'    => array_values($filteredOffers),
            'order_id'  => $orderId,
            'funnel_id' => $funnelId,
        ]);
    }

    /**
     * Get Stripe mode for a funnel.
     */
    private function getFunnelStripeMode(string $funnelId): string
    {
        // Prefer funnel CPT configuration if funnelId is a post ID
        $postId = absint($funnelId);
        if ($postId > 0) {
            $config = FunnelConfigLoader::getById($postId);
            if (is_array($config)) {
                $mode = strtolower(trim((string) ($config['stripe_mode'] ?? 'auto')));
                if ($mode === 'live' || $mode === 'test') {
                    return $mode;
                }
            }
        }

        $opts = get_option('hp_rw_settings', []);
        $env = isset($opts['env']) && $opts['env'] === 'production' ? 'production' : 'staging';

        if (!empty($opts['funnels']) && is_array($opts['funnels'])) {
            foreach ($opts['funnels'] as $f) {
                if (is_array($f) && !empty($f['id']) && (string) $f['id'] === $funnelId) {
                    if ($env === 'staging') {
                        $mode = $f['mode_staging'] ?? 'test';
                    } else {
                        $mode = $f['mode_production'] ?? 'live';
                    }
                    return ($mode === 'live') ? 'live' : 'test';
                }
            }
        }

        return $env === 'production' ? 'live' : 'test';
    }

    /**
     * Get upsell offers configured for a funnel.
     */
    private function getUpsellOffers(string $funnelId): array
    {
        $opts = get_option('hp_rw_settings', []);

        if (!empty($opts['funnel_configs']) && is_array($opts['funnel_configs'])) {
            $config = $opts['funnel_configs'][$funnelId] ?? [];
            if (!empty($config['upsell_offers']) && is_array($config['upsell_offers'])) {
                $offers = [];
                foreach ($config['upsell_offers'] as $offer) {
                    if (!is_array($offer) || empty($offer['sku'])) {
                        continue;
                    }

                    $product = Resolver::resolveProductFromItem(['sku' => $offer['sku']]);
                    if (!$product) {
                        continue;
                    }

                    $productData = Resolver::getProductDisplayData($product);
                    $offers[] = array_merge($productData, [
                        'offer_title'       => $offer['title'] ?? $productData['name'],
                        'offer_description' => $offer['description'] ?? '',
                        'discount_percent'  => (float) ($offer['discount_percent'] ?? 0),
                        'offer_price'       => $productData['price'] * (1 - (($offer['discount_percent'] ?? 0) / 100)),
                    ]);
                }
                return $offers;
            }
        }

        return [];
    }
}















