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
 */
class CheckoutService
{
    private const TRANSIENT_PREFIX = 'hp_rw_draft_';
    private const TTL = 2 * HOUR_IN_SECONDS;

    public function createDraft(array $payload): string
    {
        $id = uniqid('hprw_', true);
        set_transient(self::TRANSIENT_PREFIX . $id, wp_json_encode($payload), self::TTL);
        return $id;
    }

    public function getDraft(string $id): ?array
    {
        $raw = get_transient(self::TRANSIENT_PREFIX . $id);
        if (!$raw) return null;
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    public function deleteDraft(string $id): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $id);
    }

    public function calculateTotals(
        array $items,
        array $address,
        ?array $selectedRate = null,
        int $pointsToRedeem = 0,
        float $globalDiscountPercent = 0.0,
        array $funnelConfig = [],
        ?float $offerTotal = null
    ): array {
        $order_id = 0;
        try {
            $order = wc_create_order(['status' => 'auto-draft']);
            $order_id = $order->get_id();

            $sumRegularPrice = 0.0;
            $resolvedItems = [];
            foreach ($items as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $product = Resolver::resolveProductFromItem((array) $it);
                if (!$product) continue;

                $regPrice = (float) $product->get_regular_price();
                if ($regPrice <= 0) $regPrice = (float) $product->get_price();
                
                $sumRegularPrice += ($regPrice * $qty);
                $resolvedItems[] = ['product' => $product, 'qty' => $qty, 'regPrice' => $regPrice, 'originalItem' => $it];
            }

            $runningTotal = 0.0;
            $count = count($resolvedItems);
            foreach ($resolvedItems as $idx => $ri) {
                $product = $ri['product'];
                $qty = $ri['qty'];
                $regPrice = $ri['regPrice'];
                $it = $ri['originalItem'];

                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity($qty);
                if (!empty($it['label'])) $item->set_name($product->get_name() . ' ' . $it['label']);

                $subtotal = $regPrice * $qty;
                $total = $subtotal;

                if ($offerTotal !== null && $offerTotal > 0 && $sumRegularPrice > 0) {
                    if ($idx === $count - 1) {
                        $total = $offerTotal - $runningTotal;
                    } else {
                        $total = round(($subtotal / $sumRegularPrice) * $offerTotal, 2);
                        $runningTotal += $total;
                    }
                } else {
                    $salePrice = isset($it['salePrice']) ? (float) $it['salePrice'] : (isset($it['sale_price']) ? (float) $it['sale_price'] : null);
                    $price = ($salePrice !== null) ? $salePrice : (float) $product->get_price();
                    $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : (isset($it['itemDiscountPercent']) ? (float) $it['itemDiscountPercent'] : null);
                    
                    if ($itemPct !== null && $itemPct > 0) {
                        $total = max(0.0, $regPrice * (1 - ($itemPct / 100.0)) * $qty);
                    } else if ($regPrice > 0 && abs($price - $regPrice) > 0.01) {
                        $total = $price * $qty;
                    }
                }

                $item->set_subtotal($subtotal);
                $item->set_total($total);
                $item->set_total_tax(0);
                $item->set_subtotal_tax(0);
                $order->add_item($item);
            }

            $this->applyAddress($order, 'billing', $address);
            $this->applyAddress($order, 'shipping', $address);

            $shippingTotal = 0.0;
            if ($selectedRate && isset($selectedRate['amount'])) {
                $shippingTotal = (float) $selectedRate['amount'];
                $ship = new WC_Order_Item_Shipping();
                $ship->set_method_title($selectedRate['serviceName'] ?? 'Shipping');
                $ship->set_total($shippingTotal);
                $order->add_item($ship);
            }

            $order->calculate_totals(false);

            // Global discount (only if not explicit offer)
            $globalDiscount = 0.0;
            if ($offerTotal === null && $globalDiscountPercent > 0.0) {
                $productsNet = (float) $order->get_total(); // already includes line-level discounts
                $globalDiscount = round($productsNet * ($globalDiscountPercent / 100.0), 2);
                if ($globalDiscount > 0.0) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('Global discount (' . $globalDiscountPercent . '%)');
                    $fee->set_total(-1 * $globalDiscount);
                    $order->add_item($fee);
                }
            }

            $order->calculate_totals(false);
            $finalNet = (float) $order->get_total() - $shippingTotal;
            
            $pointsService = new PointsService();
            $pointsDiscount = 0.0;
            if ($pointsToRedeem > 0 && $finalNet > 0) {
                $pointsDiscount = min($pointsService->pointsToMoney($pointsToRedeem), $finalNet);
                if ($pointsDiscount > 0) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name('Points redemption');
                    $fee->set_total(-1 * $pointsDiscount);
                    $order->add_item($fee);
                }
            }

            $order->calculate_totals(false);

            return [
                'subtotal'            => $sumRegularPrice,
                'discount_total'      => $sumRegularPrice - $finalNet,
                'shipping_total'      => $shippingTotal,
                'tax_total'           => (float) $order->get_total_tax(),
                'fees_total'          => (float) $order->get_total_fees(),
                'global_discount'     => $globalDiscount,
                'points_discount'     => $pointsDiscount,
                'discounted_subtotal' => $finalNet,
                'grand_total'         => (float) $order->get_total(),
            ];
        } finally {
            if ($order_id > 0) wp_delete_post($order_id, true);
        }
    }

    public function createOrderFromDraft(
        array $draftData,
        string $stripeCustomerId,
        string $stripePaymentIntentId,
        ?string $stripeChargeId = null,
        ?string $paymentMethodId = null
    ): ?WC_Order {
        $items = $draftData['items'] ?? [];
        $customer = $draftData['customer'] ?? [];
        $shippingAddress = $draftData['shipping_address'] ?? [];
        $selectedRate = $draftData['selected_rate'] ?? null;
        $pointsToRedeem = (int) ($draftData['points_to_redeem'] ?? 0);
        $offerTotal = isset($draftData['offer_total']) ? (float) $draftData['offer_total'] : null;
        $globalDiscountPercent = (float) ($draftData['global_discount_percent'] ?? 0);
        $funnelName = $draftData['funnel_name'] ?? 'Funnel';

        if (empty($items)) return null;

        $order = wc_create_order(['status' => 'pending']);
        if (!$order) return null;

        $email = $customer['email'] ?? '';
        if ($email) {
            $user = get_user_by('email', $email);
            if ($user) $order->set_customer_id($user->ID);
        }

        $sumRegularPrice = 0.0;
        $resolvedItems = [];
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) continue;

            $regPrice = (float) $product->get_regular_price();
            if ($regPrice <= 0) $regPrice = (float) $product->get_price();
            $sumRegularPrice += ($regPrice * $qty);
            $resolvedItems[] = ['product' => $product, 'qty' => $qty, 'regPrice' => $regPrice, 'originalItem' => $it];
        }

        // Add items at FULL PRICE with per-item discount metadata
        // Each item has its own discount based on salePrice vs regularPrice
        // The total discount is applied as a "Offer Savings" fee to prevent coupon conflicts
        $totalOfferDiscount = 0.0;
        
        foreach ($resolvedItems as $ri) {
            $product = $ri['product'];
            $qty = $ri['qty'];
            $regPrice = $ri['regPrice'];
            $it = $ri['originalItem'];

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);
            if (!empty($it['label'])) $item->set_name($product->get_name() . ' ' . $it['label']);

            // Full price for this item
            $lineSubtotal = $regPrice * $qty;
            
            // Get the item's individual sale price (0 = free, null = no discount)
            $salePrice = null;
            if (isset($it['salePrice'])) {
                $salePrice = (float) $it['salePrice'];
            } elseif (isset($it['sale_price'])) {
                $salePrice = (float) $it['sale_price'];
            }
            
            // Calculate item-level discount
            $itemDiscount = 0.0;
            $itemDiscountPercent = 0.0;
            if ($salePrice !== null && $regPrice > 0) {
                // Use the explicit sale price
                $itemDiscount = ($regPrice - $salePrice) * $qty;
                $itemDiscountPercent = round((($regPrice - $salePrice) / $regPrice) * 100, 2);
            }
            
            $totalOfferDiscount += $itemDiscount;

            // Set subtotal and total to FULL PRICE
            // The discount is tracked via meta and applied as a fee
            $item->set_subtotal($lineSubtotal);
            $item->set_total($lineSubtotal);
            $item->set_total_tax(0);
            $item->set_subtotal_tax(0);
            
            // Set per-item discount meta for EAO (each item has its own discount, not global)
            if ($itemDiscountPercent > 0) {
                $item->add_meta_data('_eao_exclude_global_discount', '1', true);
                $item->add_meta_data('_eao_item_discount_percent', $itemDiscountPercent, true);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemDiscountPercent, true);
                $item->add_meta_data('_hp_rw_exclude_global_discount', '1', true);
            }

            $order->add_item($item);
        }

        // Use calculated per-item discounts, or fall back to offer total difference
        $offerDiscount = $totalOfferDiscount;
        if ($offerDiscount <= 0 && $offerTotal !== null && $offerTotal > 0 && $sumRegularPrice > $offerTotal) {
            $offerDiscount = round($sumRegularPrice - $offerTotal, 2);
        }
        
        // Store the total discount amount on the order (but NOT as a global discount %)
        if ($offerDiscount > 0) {
            $order->update_meta_data('_hp_rw_offer_discount_amount', $offerDiscount);
        }

        $this->applyAddress($order, 'billing', array_merge($shippingAddress, ['email' => $email]));
        $this->applyAddress($order, 'shipping', $shippingAddress);

        if ($selectedRate && isset($selectedRate['amount'])) {
            $ship = new WC_Order_Item_Shipping();
            $ship->set_method_title($selectedRate['serviceName'] ?? 'Shipping');
            $ship->set_method_id('hp_rw_shipping:1');
            $ship->set_total((float) $selectedRate['amount']);
            $order->add_item($ship);
        }

        // Apply offer discount as a fee (before coupons)
        // This prevents WC coupons from overwriting item-level discounts
        if ($offerDiscount > 0) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Offer Savings');
            $fee->set_total(-1 * $offerDiscount);
            $fee->set_tax_status('none');
            $order->add_item($fee);
        }

        $order->calculate_totals(false);

        // Global discount (only if no offer total - mutually exclusive)
        if ($offerTotal === null && $globalDiscountPercent > 0) {
            $productsNet = 0.0;
            foreach ($order->get_items() as $item) {
                if (!$item instanceof WC_Order_Item_Product) continue;
                if ($item->get_meta('_hp_rw_exclude_global_discount')) continue;
                $productsNet += (float) $item->get_total();
            }
            $globalDiscount = round($productsNet * ($globalDiscountPercent / 100.0), 2);
            if ($globalDiscount > 0.0) {
                $fee = new \WC_Order_Item_Fee();
                $fee->set_name('Global discount (' . $globalDiscountPercent . '%)');
                $fee->set_total(-1 * $globalDiscount);
                $order->add_item($fee);
            }
        }

        if ($pointsToRedeem > 0) $this->applyPointsRedemption($order, $pointsToRedeem);

        $order->update_meta_data('_hp_rw_stripe_customer_id', $stripeCustomerId);
        $order->update_meta_data('_hp_rw_stripe_pi_id', $stripePaymentIntentId);
        if ($stripeChargeId) $order->update_meta_data('_hp_rw_stripe_charge_id', $stripeChargeId);
        if ($paymentMethodId) $order->update_meta_data('_hp_rw_stripe_pm_id', $paymentMethodId);
        $order->update_meta_data('_hp_rw_funnel_id', $draftData['funnel_id'] ?? 'default');
        $order->update_meta_data('_hp_rw_funnel_name', $funnelName);

        $order->add_order_note(sprintf('Funnel: %s', $funnelName));
        $order->set_status('processing');
        $order->calculate_totals(false);
        $order->save();

        return $order;
    }

    private function applyPointsRedemption(WC_Order $order, int $points): void
    {
        $pointsService = new PointsService();
        $discountAmount = $pointsService->pointsToMoney($points);
        if ($discountAmount <= 0) return;

        $order->calculate_totals(false);
        $maxDiscount = (float) $order->get_total();
        if ($discountAmount > $maxDiscount) {
            $discountAmount = $maxDiscount;
            $points = (int) ($discountAmount * 10);
        }

        $couponCode = 'ywpar_discount_' . time() . '_' . rand(100, 999);
        $coupon = new \WC_Coupon();
        $coupon->set_code($couponCode);
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount($discountAmount);
        $coupon->set_description(sprintf('Points discount: %d points redeemed via Funnel', $points));
        $coupon->add_meta_data('ywpar_coupon', 1, true);
        $coupon->set_usage_limit(1);
        $coupon->save();

        $order->apply_coupon($couponCode);
        $order->update_meta_data('_ywpar_coupon_points', $points);
        $order->update_meta_data('_ywpar_coupon_amount', $discountAmount);
    }

    public function addItemsToOrder(WC_Order $order, array $items): float
    {
        $addedTotal = 0.0;
        foreach ($items as $it) {
            $qty = max(1, (int) ($it['qty'] ?? 1));
            $product = Resolver::resolveProductFromItem((array) $it);
            if (!$product) continue;

            $item = new WC_Order_Item_Product();
            $item->set_product($product);
            $item->set_quantity($qty);

            $regPrice = (float) $product->get_regular_price();
            if ($regPrice <= 0) $regPrice = (float) $product->get_price();
            $salePrice = isset($it['salePrice']) ? (float) $it['salePrice'] : (isset($it['sale_price']) ? (float) $it['sale_price'] : (float) $product->get_price());
            
            $itemPct = isset($it['item_discount_percent']) ? (float) $it['item_discount_percent'] : (isset($it['itemDiscountPercent']) ? (float) $it['itemDiscountPercent'] : null);
            if ($itemPct === null && $regPrice > 0 && abs($salePrice - $regPrice) > 0.01) {
                $itemPct = round((1 - ($salePrice / $regPrice)) * 100, 2);
            }

            $finalPrice = ($itemPct > 0) ? $regPrice * (1 - ($itemPct / 100)) : $salePrice;
            
            $item->set_subtotal($regPrice * $qty);
            $item->set_total($finalPrice * $qty);
            if ($itemPct > 0) {
                $item->add_meta_data('_eao_item_discount_percent', $itemPct, true);
                $item->add_meta_data('_hp_rw_item_discount_percent', $itemPct, true);
            }
            $item->add_meta_data('_hp_rw_upsell_item', '1', true);
            $order->add_item($item);
            $addedTotal += ($finalPrice * $qty);
        }
        $order->calculate_totals(false);
        $order->save();
        return $addedTotal;
    }

    private function applyAddress($order, string $type, array $addr): void
    {
        $map = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email'];
        foreach ($map as $key) {
            $method = "set_{$type}_{$key}";
            if (method_exists($order, $method) && isset($addr[$key])) {
                $order->{$method}((string) $addr[$key]);
            }
        }
    }
}
