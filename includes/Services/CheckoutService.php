<?php
namespace HP_RW\Services;

use HP_RW\Util\Resolver;
use HP_RW\Services\StripeService;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;
use WC_Order_Item_Fee;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout service for managing order drafts and creating WooCommerce orders.
 * 
 * Handles the complete checkout flow including:
 * - Order draft storage (for multi-step checkout)
 * - Totals calculation with discounts and points
 * - Order creation from completed payments
 */
class CheckoutService
{
    private const TRANSIENT_PREFIX = 'hp_rw_draft_';
    private const TTL = 2 * HOUR_IN_SECONDS;

    /**
     * Create a new order draft.
     * 
     * @param array $payload Draft data including items, customer, address, etc.
     * @return string Draft ID
     */
    public function createDraft(array $payload): string
    {
        $id = uniqid('hprw_', true);
        set_transient(self::TRANSIENT_PREFIX . $id, wp_json_encode($payload), self::TTL);
        error_log('[HP-RW] Draft created: ' . $id . ' for email: ' . ($payload['customer']['email'] ?? 'unknown'));
        return $id;
    }

    /**
     * Get an order draft by ID.
     * 
     * @param string $id Draft ID
     * @return array|null Draft data or null if not found
     */
    public function getDraft(string $id): ?array
    {
        $raw = get_transient(self::TRANSIENT_PREFIX . $id);
        if (!$raw) {
            return null;
        }
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Delete an order draft.
     * 
     * @param string $id Draft ID
     */
    public function deleteDraft(string $id): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $id);
    }

    /**
     * Calculate totals for a cart.
     * 
     * @param array $items Array of items with sku, qty
     * @param array $address Shipping address
     * @param array|null $selectedRate Selected shipping rate with serviceName, amount
     * @param int $pointsToRedeem Points to redeem
     * @param float $globalDiscountPercent Global discount percentage
     * @param array $funnelConfig Optional funnel-specific config for per-item discounts
     * @return array Totals breakdown
     */
    public function calculateTotals(
        array $items,
        array $address,
        ?array $selectedRate = null,
        int $pointsToRedeem = 0,
        float $globalDiscountPercent = 0.0,
        array $funnelConfig = [],
        ?float $offerTotal = null  // Admin-set total price for entire offer
    ): array {
        error_log('[HP-RW] calculateTotals: items=' . count($items) . ' points=' . $pointsToRedeem . ' rate=' . ($selectedRate['amount'] ?? 'null'));
        $order_id = 0;
        
        try {
            $order = wc_create_order(['status' => 'auto-draft']);
            $order_id = $order->get_id();

        // Add items
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) {
                continue;
            }

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);
            
            // Apply label suffix if provided (e.g., "(Kit Included)")
            if (!empty($it['label'])) {
                $productName = $product->get_name() . ' ' . $it['label'];
                $item->set_name($productName);
            }

            // Standard WC practice: subtotal is regular price, total is what they pay
            $subtotal = $regularPrice * $qty;
            
            // Base total is price from frontend/product (already discounted if sale price)
            $total = $price * $qty;

            // Check for per-item discount overrides
            $excludeGd = !empty($it['exclude_global_discount']);
            $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : null;

            if ($itemPct !== null && $itemPct > 0) {
                // Percentage is off regular price
                $total = max(0.0, $regularPrice * (1 - ($itemPct / 100.0)) * $qty);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            } else if ($regularPrice > 0 && abs($price - $regularPrice) > 0.01) {
                // Implied percentage for EAO
                $itemPct = round((1 - ($price / $regularPrice)) * 100, 2);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            }
            
            if ($excludeGd) {
                $item->add_meta_data('_hp_rw_exclude_global_discount', '1', true);
            }

            // Store pre-coupon price for summary
            $item->add_meta_data('_hp_rw_funnel_price', ($total / $qty), true);

            $item->set_subtotal($subtotal);
            $item->set_total($total);
            $order->add_item($item);
        }

            // Set addresses
            $this->applyAddress($order, 'billing', $address);
            $this->applyAddress($order, 'shipping', $address);

            // Add shipping
            if ($selectedRate && isset($selectedRate['amount'])) {
                $ship = new WC_Order_Item_Shipping();
                $ship->set_method_title($selectedRate['serviceName'] ?? 'Shipping');
                $ship->set_total((float) $selectedRate['amount']);
                $order->add_item($ship);
            }

            // First calculation
            $order->calculate_totals(false);

            // Calculate global discount on eligible items
            $productsGross = 0.0;
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                if ($item->get_meta('_hp_rw_exclude_global_discount')) {
                    continue;
                }
                $productsGross += (float) $item->get_subtotal();
            }

            $discountTotal = (float) $order->get_discount_total();
            $globalDiscount = 0.0;
            
            if ($globalDiscountPercent > 0.0 && $productsGross > 0.0) {
                $globalDiscount = round($productsGross * ($globalDiscountPercent / 100.0), 2);
                if ($globalDiscount > 0.0) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('Global discount (' . $globalDiscountPercent . '%)');
                    $fee->set_amount(-1 * $globalDiscount);
                    $fee->set_total(-1 * $globalDiscount);
                    $order->add_item($fee);
                }
            }

            $allProductsSubtotal = (float) $order->get_subtotal();
            $productsNet = max(0.0, $allProductsSubtotal - $discountTotal - $globalDiscount);

            // Points redemption
            $pointsService = new PointsService();
            $pointsDiscount = 0.0;
            
            if ($pointsToRedeem > 0 && $productsNet > 0) {
                $pointsDiscount = min($pointsService->pointsToMoney($pointsToRedeem), $productsNet);
                if ($pointsDiscount > 0) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('Points redemption');
                    $fee->set_amount(-1 * $pointsDiscount);
                    $fee->set_total(-1 * $pointsDiscount);
                    $order->add_item($fee);
                }
            }

            // Final calculation
            $order->calculate_totals(false);

            // Build totals manually
            $itemsTotalAfterDiscounts = 0.0;
            foreach ($order->get_items() as $it) {
                if ($it instanceof WC_Order_Item_Product) {
                    $itemsTotalAfterDiscounts += (float) $it->get_total();
                }
            }

            $feesTotal = 0.0;
            foreach ($order->get_fees() as $fee) {
                $feesTotal += (float) $fee->get_total();
            }

            $shippingTotal = (float) $order->get_shipping_total();
            $taxTotal = (float) $order->get_total_tax();
            
            // Use admin-set offer total if provided, otherwise calculate from items
            if ($offerTotal !== null) {
                // Use the exact offer total set by admin, plus shipping and any fees
                $grandTotal = max(0.0, $offerTotal + $shippingTotal - $pointsDiscount);
            } else {
                $grandTotal = max(0.0, $itemsTotalAfterDiscounts + $feesTotal + $shippingTotal + $taxTotal);
            }

            error_log('[HP-RW] calculateTotals returning: grand_total=' . $grandTotal . ' shipping=' . $shippingTotal . ' points_discount=' . $pointsDiscount . ' offer_total=' . ($offerTotal ?? 'null'));

            return [
                'subtotal'            => $allProductsSubtotal,
                'discount_total'      => $discountTotal,
                'shipping_total'      => $shippingTotal,
                'tax_total'           => $taxTotal,
                'fees_total'          => $feesTotal,
                'global_discount'     => $globalDiscount,
                'points_discount'     => $pointsDiscount,
                'discounted_subtotal' => $productsNet,
                'grand_total'         => $grandTotal,
            ];
        } finally {
            if ($order_id > 0) {
                wp_delete_post($order_id, true);
            }
        }
    }

    /**
     * Create a WooCommerce order from a completed checkout.
     * 
     * @param array $draftData Draft data from getDraft()
     * @param string $stripeCustomerId Stripe customer ID
     * @param string $stripePaymentIntentId Stripe PaymentIntent ID
     * @param string|null $stripeChargeId Stripe Charge ID (optional)
     * @param string|null $paymentMethodId Payment method ID (optional)
     * @return WC_Order|null Created order or null on failure
     */
    public function createOrderFromDraft(
        array $draftData,
        string $stripeCustomerId,
        string $stripePaymentIntentId,
        ?string $stripeChargeId = null,
        ?string $paymentMethodId = null
    ): ?WC_Order {
        error_log('[HP-RW] createOrderFromDraft starting for PI: ' . $stripePaymentIntentId);
        $items = $draftData['items'] ?? [];
        $customer = $draftData['customer'] ?? [];
        $shippingAddress = $draftData['shipping_address'] ?? [];
        $selectedRate = $draftData['selected_rate'] ?? null;
        $pointsToRedeem = (int) ($draftData['points_to_redeem'] ?? 0);
        $offerTotal = isset($draftData['offer_total']) ? (float) $draftData['offer_total'] : null;
        $funnelId = $draftData['funnel_id'] ?? 'default';
        $funnelName = $draftData['funnel_name'] ?? 'Funnel';
        $analytics = $draftData['analytics'] ?? [];

        if (empty($items)) {
            return null;
        }

        $order = wc_create_order(['status' => 'pending']);
        if (!$order) {
            return null;
        }

        // Link to existing customer if found by email
        $email = $customer['email'] ?? '';
        if ($email) {
            $user = get_user_by('email', $email);
            if ($user) {
                $order->set_customer_id($user->ID);
                error_log('[HP-RW] Linked order to existing customer ID: ' . $user->ID);
            }
        }

        // Add items
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) {
                continue;
            }

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);
            
            // Apply label suffix if provided (e.g., "(Kit Included)")
            if (!empty($it['label'])) {
                $productName = $product->get_name() . ' ' . $it['label'];
                $item->set_name($productName);
            }

            // Use admin-set sale price if provided, otherwise use WC price
            $regularPrice = (float) $product->get_regular_price();
            if ($regularPrice <= 0) {
                $regularPrice = (float) $product->get_price();
            }
            
            $salePrice = isset($it['salePrice']) ? (float) $it['salePrice'] : null;
            $price = ($salePrice !== null) ? $salePrice : (float) $product->get_price();
            
            // Standard WC practice: subtotal is regular price, total is what they pay
            $subtotal = $regularPrice * $qty;
            
            // Base total is price from frontend/product (already discounted if sale price)
            $total = $price * $qty;

            // Check for per-item discount overrides from funnel config
            $excludeGd = !empty($it['exclude_global_discount']);
            $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : null;

            if ($itemPct !== null && $itemPct > 0) {
                // If a percentage is given, it's always off the regular price
                $total = max(0.0, $regularPrice * (1 - ($itemPct / 100.0)) * $qty);
            } else if ($regularPrice > 0 && abs($price - $regularPrice) > 0.01) {
                // No percentage given, but price differs from regular. 
                // Calculate implied percentage for EAO display.
                $itemPct = round((1 - ($price / $regularPrice)) * 100, 2);
            }

            if ($itemPct !== null && $itemPct > 0) {
                // Use EAO's meta key for visibility in admin
                $item->add_meta_data('_eao_item_discount_percent', $itemPct, true);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            }
            
            if ($excludeGd) {
                $item->add_meta_data('_eao_exclude_global_discount', '1', true);
                $item->add_meta_data('_hp_rw_exclude_global_discount', '1', true);
            }

            // Store the "Funnel Price" (pre-coupon price) for summary display
            $item->add_meta_data('_hp_rw_funnel_price', ($total / $qty), true);

            $item->set_subtotal($subtotal);
            $item->set_total($total);
            $order->add_item($item);
            error_log('[HP-RW TRACE] Added item: ' . $product->get_sku() . ' Price: ' . ($total / $qty) . ' Total: ' . $total);
        }

        // Set addresses
        $email = $customer['email'] ?? '';
        $billingAddress = array_merge($shippingAddress, ['email' => $email]);
        $this->applyAddress($order, 'billing', $billingAddress);
        $this->applyAddress($order, 'shipping', $shippingAddress);

        // Add shipping
        if ($selectedRate && isset($selectedRate['amount'])) {
            $amount = (float) $selectedRate['amount'];
            if ($amount > 0) {
                $ship = new WC_Order_Item_Shipping();
                $ship->set_method_title($selectedRate['serviceName'] ?? 'Shipping');
                $ship->set_method_id('hp_rw_shipping:1');
                $ship->set_total($amount);
                $order->add_item($ship);
                error_log('[HP-RW TRACE] Added shipping: ' . $amount);
            }
        }

        $order->calculate_totals(false);
        error_log('[HP-RW TRACE] Total after items+shipping: ' . $order->get_total());

        // Apply global discount if configured
        $globalDiscountPercent = (float) ($draftData['global_discount_percent'] ?? 0);
        if ($globalDiscountPercent > 0) {
            $productsGross = 0.0;
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) {
                    continue;
                }
                if ($item->get_meta('_hp_rw_exclude_global_discount')) {
                    continue;
                }
                $productsGross += (float) $item->get_subtotal();
            }

            $globalDiscount = round($productsGross * ($globalDiscountPercent / 100.0), 2);
            if ($globalDiscount > 0.0) {
                $fee = new \WC_Order_Item_Fee();
                $fee->set_name('Global discount (' . $globalDiscountPercent . '%)');
                $fee->set_amount(-1 * $globalDiscount);
                $fee->set_total(-1 * $globalDiscount);
                $order->add_item($fee);
                error_log('[HP-RW TRACE] Added global discount: ' . $globalDiscount);
            }
        }

        // Points redemption - Use EAO/YITH style coupon for robustness
        if ($pointsToRedeem > 0) {
            $this->applyPointsRedemption($order, $pointsToRedeem);
            error_log('[HP-RW TRACE] Points redemption triggered: ' . $pointsToRedeem);
        }

        $order->calculate_totals(false);
        error_log('[HP-RW TRACE] Total after discounts/points: ' . $order->get_total());

        // Link to existing user if found
        $user = $email ? get_user_by('email', $email) : null;
        if ($user) {
            $order->set_customer_id($user->ID);
        }

        // Set payment method (reflect the funnel's stripe mode if available)
        $draftStripeMode = isset($draftData['stripe_mode']) ? (string) $draftData['stripe_mode'] : null;
        $stripeService = new StripeService($draftStripeMode);
        
        $modeLabel = ($stripeService->mode === 'test') ? ' (Test)' : '';
        $paymentMethodTitle = 'HP Express Shop' . $modeLabel;
        
        $order->set_payment_method('hp_rw_stripe');
        $order->set_payment_method_title($paymentMethodTitle);

        // --- CUSTOM TOTALS ADJUSTMENT ---
        // Calculate the current subtotal of products and fees added so far
        $order->calculate_totals(false);
        $currentItemsTotal = 0.0;
        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $currentItemsTotal += (float) $item->get_total();
            }
        }

        // If an explicit offer total was set by admin, and it differs from our calculated items total,
        // add a "Savings" fee to bridge the gap. This ensures the grand total matches the payment.
        if ($offerTotal !== null && $offerTotal > 0) {
            $diff = $offerTotal - $currentItemsTotal;
            // Allow a small margin for rounding
            if (abs($diff) > 0.01) {
                $savingsFee = new \WC_Order_Item_Fee();
                $savingsFee->set_name($diff < 0 ? 'Offer Savings' : 'Package Adjustment');
                $savingsFee->set_amount($diff);
                $savingsFee->set_total($diff);
                $order->add_item($savingsFee);
                error_log('[HP-RW TRACE] Added adjustment fee to match offer total: ' . $diff . ' (Offer: ' . $offerTotal . ', Items: ' . $currentItemsTotal . ')');
            }
        }

        // Store Stripe metadata
        $order->update_meta_data('_hp_rw_stripe_customer_id', $stripeCustomerId);
        $order->update_meta_data('_hp_rw_stripe_pi_id', $stripePaymentIntentId);
        
        $order->calculate_totals(false);
        error_log('[HP-RW TRACE] Total before final save: ' . $order->get_total());

        if ($stripeChargeId) {
            $order->update_meta_data('_hp_rw_stripe_charge_id', $stripeChargeId);
        }
        if ($paymentMethodId) {
            $order->update_meta_data('_hp_rw_stripe_pm_id', $paymentMethodId);
        }

        // Store funnel metadata
        $order->update_meta_data('_hp_rw_funnel_id', $funnelId);
        $order->update_meta_data('_hp_rw_funnel_name', $funnelName);

        // Store analytics
        if (!empty($analytics)) {
            if (!empty($analytics['campaign'])) {
                $order->update_meta_data('_hp_rw_campaign', $analytics['campaign']);
            }
            if (!empty($analytics['source'])) {
                $order->update_meta_data('_hp_rw_source', $analytics['source']);
            }
            if (!empty($analytics['utm']) && is_array($analytics['utm'])) {
                $order->update_meta_data('_hp_rw_utm', wp_json_encode($analytics['utm']));
            }
        }

        // Add order note
        $order->add_order_note(sprintf('Funnel: %s', $funnelName));

        // Set status to processing
        $order->set_status('processing');
        
        // Final total calculation before save.
        // Use FALSE to prevent resetting manual overrides/coupons that may not yet be in the DB context.
        $order->calculate_totals(false);
        $order->save();

        error_log('[HP-RW TRACE] Final saved total: ' . $order->get_total());

        return $order;
    }

    /**
     * Apply points redemption using a YITH-compatible coupon.
     */
    private function applyPointsRedemption(WC_Order $order, int $points): void
    {
        $pointsService = new PointsService();
        $discountAmount = $pointsService->pointsToMoney($points);

        if ($discountAmount <= 0) {
            return;
        }

        // Ensure we don't exceed order total
        $order->calculate_totals(false);
        $maxDiscount = (float) $order->get_total();
        if ($discountAmount > $maxDiscount) {
            $discountAmount = $maxDiscount;
            $points = (int) ($discountAmount * 10); // Assuming 10 points = $1
        }

        // Create a unique coupon code following YITH's pattern
        $couponCode = 'ywpar_discount_' . time() . '_' . rand(100, 999);

        // Create a transient coupon
        $coupon = new \WC_Coupon();
        $coupon->set_code($couponCode);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($discountAmount);
        $coupon->set_description(sprintf('Points discount: %d points redeemed via Funnel', $points));
        $coupon->add_meta_data('ywpar_coupon', 1, true);
        $coupon->set_usage_limit(1);
        $coupon->save();

        // Apply to order
        $order->apply_coupon($couponCode);

        // Standard YITH meta
        $order->update_meta_data('_ywpar_coupon_points', $points);
        $order->update_meta_data('_ywpar_coupon_amount', $discountAmount);

        error_log('[HP-RW] Applied points redemption coupon: ' . $couponCode . ' for $' . $discountAmount);
    }

    /**
     * Add items to an existing order (for upsells).
     * 
     * @param WC_Order $order Existing order
     * @param array $items Items to add
     * @return float Total amount added
     */
    public function addItemsToOrder(WC_Order $order, array $items): float
    {
        $addedTotal = 0.0;

        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) {
                continue;
            }

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);

            $regularPrice = (float) $product->get_regular_price();
            if ($regularPrice <= 0) {
                $regularPrice = (float) $product->get_price();
            }
            
            $price = (float) $product->get_price();
            $subtotal = $regularPrice * $qty;
            $total = $price * $qty;

            // Check for item-specific discount
            $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : null;
            if ($itemPct !== null && $itemPct > 0) {
                $total = max(0.0, $regularPrice * (1 - ($itemPct / 100.0)) * $qty);
                $item->add_meta_data('_eao_item_discount_percent', $itemPct, true);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            } else if ($regularPrice > 0 && abs($price - $regularPrice) > 0.01) {
                $itemPct = round((1 - ($price / $regularPrice)) * 100, 2);
                $item->add_meta_data('_eao_item_discount_percent', $itemPct, true);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            }

            // Store pre-coupon price for summary
            $item->add_meta_data('_hp_rw_funnel_price', ($total / $qty), true);

            $item->set_subtotal($subtotal);
            $item->set_total($total);
            $item->add_meta_data('_hp_rw_upsell_item', '1', true);
            $order->add_item($item);

            $addedTotal += $total;
        }

        $order->calculate_totals(false);
        $order->save();

        return $addedTotal;
    }

    /**
     * Apply address fields to a WooCommerce order.
     */
    private function applyAddress($order, string $type, array $addr): void
    {
        $map = [
            'first_name', 'last_name', 'company', 'address_1', 'address_2',
            'city', 'state', 'postcode', 'country', 'phone', 'email'
        ];

        foreach ($map as $key) {
            $method = "set_{$type}_{$key}";
            if (method_exists($order, $method) && isset($addr[$key])) {
                $order->{$method}((string) $addr[$key]);
            }
        }
    }
}















