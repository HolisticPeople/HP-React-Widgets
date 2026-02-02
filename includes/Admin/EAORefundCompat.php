<?php
/**
 * EAO Refund Compatibility for HP-React-Widgets funnel orders.
 * Intercepts the EAO refund AJAX calls and handles Stripe refunds
 * for orders created through HP-React-Widgets funnels.
 * 
 * @package HP_RW\Admin
 * @version 2.43.80
 * @author Amnon Manneberg
 */

namespace HP_RW\Admin;

if (!defined('ABSPATH')) { exit; }

class EAORefundCompat {
    public function register(): void {
        if (!is_admin()) { return; }
        // Run before EAO's own handler; we only handle HP-RW funnel orders.
        add_action('wp_ajax_eao_payment_get_refund_data', [$this, 'maybeHandleRefundData'], 1);
        add_action('wp_ajax_eao_payment_process_refund', [$this, 'maybeProcessRefund'], 1);
    }

    /**
     * Check if an order was created by HP-React-Widgets funnel checkout.
     */
    private function isHpRwOrder(\WC_Order $order): bool {
        return (
            (string) $order->get_meta('_hp_rw_stripe_pi_id', true) !== '' ||
            (string) $order->get_meta('_hp_rw_stripe_charge_id', true) !== '' ||
            (string) $order->get_payment_method() === 'hp_stripe_express'
        );
    }

    /**
     * Get the Stripe charge ID from the order (may need to fetch from PaymentIntent).
     */
    private function getChargeId(\WC_Order $order, string $secret): string {
        // First try the stored charge ID
        $charge_id = (string) $order->get_meta('_hp_rw_stripe_charge_id', true);
        if ($charge_id !== '') {
            return $charge_id;
        }

        // Try to derive charge ID from PaymentIntent
        $pi_id = (string) $order->get_meta('_hp_rw_stripe_pi_id', true);
        if ($pi_id !== '' && !empty($secret)) {
            $headers = ['Authorization' => 'Bearer ' . $secret];
            $pi_resp = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . rawurlencode($pi_id), [
                'headers' => $headers,
                'timeout' => 15
            ]);
            if (!is_wp_error($pi_resp)) {
                $pi_body = json_decode(wp_remote_retrieve_body($pi_resp), true);
                if (!empty($pi_body['latest_charge'])) {
                    $charge_id = $pi_body['latest_charge'];
                } elseif (!empty($pi_body['charges']['data'][0]['id'])) {
                    $charge_id = $pi_body['charges']['data'][0]['id'];
                }
                // Store for future use
                if ($charge_id !== '') {
                    $order->update_meta_data('_hp_rw_stripe_charge_id', $charge_id);
                    $order->save();
                }
            }
        }

        return $charge_id;
    }

    /**
     * Handle refund processing for HP-RW funnel orders.
     */
    public function maybeProcessRefund(): void {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) { return; }
        $order = wc_get_order($order_id);
        if (!$order) { return; }

        if (!$this->isHpRwOrder($order)) { return; } // let EAO handle

        // Parse lines payload from UI
        $lines_json = isset($_POST['lines']) ? wp_unslash($_POST['lines']) : '[]';
        $lines = json_decode($lines_json, true);
        if (!is_array($lines)) { $lines = []; }
        $user_reason = isset($_POST['reason']) ? sanitize_text_field(wp_unslash($_POST['reason'])) : '';

        // Calculate totals
        $amount_total = 0.0;
        $points_total = 0;
        $points_map = [];
        $line_items = [];

        foreach ($lines as $row) {
            $item_id = isset($row['item_id']) ? absint($row['item_id']) : 0;
            $money = isset($row['money']) ? floatval($row['money']) : 0.0;
            $points = isset($row['points']) ? intval($row['points']) : 0;
            
            if ($points > 0) { 
                $points_total += $points; 
                $points_map[$item_id] = $points; 
            }
            
            if ($item_id > 0 && $money > 0) {
                $line_items[$item_id] = ['qty' => 0, 'refund_total' => wc_format_decimal($money, 2)];
                $amount_total += $money;
            }
        }

        if ($amount_total <= 0 && $points_total <= 0) { return; }

        // Stripe credentials from EAO settings
        $stripe_mode = (string) $order->get_meta('_hp_rw_stripe_mode', true);
        if ($stripe_mode !== 'test') { $stripe_mode = 'live'; }
        $opts = get_option('eao_stripe_settings', []);
        $secret = ($stripe_mode === 'live') ? ($opts['live_secret'] ?? '') : ($opts['test_secret'] ?? '');
        
        if ($amount_total > 0 && empty($secret)) {
            wp_send_json_error(['message' => 'Stripe API key missing for ' . (($stripe_mode === 'live') ? 'Live' : 'Test') . ' mode.']);
        }

        // Get charge ID
        $charge_id = $this->getChargeId($order, $secret);
        if ($amount_total > 0 && empty($charge_id)) {
            wp_send_json_error(['message' => 'Stripe charge reference not found for this order.']);
        }

        // Check remaining refundable amount on the charge
        $remaining_cents = PHP_INT_MAX;
        if ($amount_total > 0 && !empty($charge_id)) {
            $chk = wp_remote_get('https://api.stripe.com/v1/charges/' . rawurlencode($charge_id), [
                'headers' => ['Authorization' => 'Bearer ' . $secret],
                'timeout' => 15
            ]);
            if (!is_wp_error($chk)) {
                $body = json_decode(wp_remote_retrieve_body($chk), true);
                if (is_array($body) && isset($body['amount'])) {
                    $amount_cents_all = (int) ($body['amount'] ?? 0);
                    $amount_refunded_cents = (int) ($body['amount_refunded'] ?? 0);
                    $remaining_cents = max(0, $amount_cents_all - $amount_refunded_cents);
                }
            }
        }

        // Issue Stripe refund
        $refund_ids = [];
        if ($amount_total > 0) {
            $want_cents = (int) round($amount_total * 100);
            $cap_cents = min($want_cents, $remaining_cents);
            
            if ($cap_cents <= 0) {
                wp_send_json_error(['message' => 'No refundable amount remaining on this charge.']);
            }

            $headers = ['Authorization' => 'Bearer ' . $secret, 'Content-Type' => 'application/x-www-form-urlencoded'];
            $rf = wp_remote_post('https://api.stripe.com/v1/refunds', [
                'headers' => $headers,
                'body' => [
                    'charge' => $charge_id,
                    'amount' => $cap_cents,
                    'reason' => 'requested_by_customer',
                    'metadata[order_id]' => $order_id
                ],
                'timeout' => 25
            ]);
            
            if (is_wp_error($rf)) {
                wp_send_json_error(['message' => 'Stripe refund error: ' . $rf->get_error_message()]);
            }
            
            $rf_body = json_decode(wp_remote_retrieve_body($rf), true);
            if (empty($rf_body['id'])) {
                $error_msg = 'Stripe refund failed';
                if (!empty($rf_body['error']['message'])) {
                    $error_msg .= ': ' . $rf_body['error']['message'];
                }
                wp_send_json_error(['message' => $error_msg, 'stripe' => $rf_body]);
            }
            $refund_ids[] = (string) $rf_body['id'];
        }

        // Create a single WooCommerce refund to record the operation (no gateway call)
        $reason = 'Refund via EAO (HP-RW Funnel)';
        if ($points_total > 0) { $reason .= ' | Points to refund: ' . (int) $points_total; }
        if (!empty($user_reason)) { $reason .= ' | Reason: ' . $user_reason; }

        $refund = wc_create_refund([
            'amount' => wc_format_decimal($amount_total, 2),
            'reason' => $reason,
            'order_id' => $order_id,
            'line_items' => $line_items,
            'refund_payment' => false,
            'restock_items' => false
        ]);
        
        if (is_wp_error($refund)) {
            wp_send_json_error(['message' => $refund->get_error_message()]);
        }
        
        if ($amount_total > 0 && !empty($refund_ids)) {
            update_post_meta($refund->get_id(), '_hp_rw_stripe_refunds', wp_json_encode(array_values($refund_ids)));
            update_post_meta($refund->get_id(), '_eao_refund_reference', implode(',', $refund_ids));
            update_post_meta($refund->get_id(), '_eao_refunded_via_gateway', 'Stripe (HP-RW ' . (($stripe_mode === 'live') ? 'Live' : 'Test') . ')');
        }

        // Handle points restore
        if ($points_total > 0) {
            if (function_exists('ywpar_increase_points')) {
                ywpar_increase_points($order->get_customer_id(), $points_total, sprintf(__('Redeemed points returned for Order #%d', 'hp-react-widgets'), $order_id), $order_id);
            } elseif (function_exists('ywpar_get_customer')) {
                $cust = ywpar_get_customer($order->get_customer_id());
                if ($cust && method_exists($cust, 'update_points')) {
                    $cust->update_points($points_total, 'order_points_return', ['order_id' => $order_id, 'description' => 'Redeemed points returned']);
                }
            }
            update_post_meta($refund->get_id(), '_eao_points_refunded', (int) $points_total);
            if (!empty($points_map)) { 
                update_post_meta($refund->get_id(), '_eao_points_refunded_map', wp_json_encode($points_map)); 
            }
        }

        $note = 'EAO Refund: Refund of $' . wc_format_decimal($amount_total, 2) . ' processed through Stripe (HP-RW Funnel).';
        if (!empty($refund_ids)) { $note .= ' Stripe refunds: ' . implode(', ', $refund_ids) . '.'; }
        $order->add_order_note($note, false, false);

        // Clean buffer and return JSON to UI
        if (function_exists('ob_get_level')) { 
            while (ob_get_level() > 0) { @ob_end_clean(); } 
        }
        wp_send_json_success([
            'refund_id' => $refund->get_id(), 
            'amount' => wc_format_decimal($amount_total, 2), 
            'points' => (int) $points_total
        ]);
    }

    /**
     * Handle refund data retrieval for HP-RW funnel orders.
     */
    public function maybeHandleRefundData(): void {
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) { return; }
        $order = wc_get_order($order_id);
        if (!$order) { return; }

        if (!$this->isHpRwOrder($order)) { return; }

        $items_resp = [];
        $order_items = $order->get_items('line_item');
        
        $total_charged = (float) $order->get_total();
        $shipping_paid_total = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();

        // Calculate line item shares
        $products_total = 0.0;
        $per_item_base = [];
        
        foreach ($order_items as $iid => $it) {
            $base = (float) $it->get_total() + (float) $it->get_total_tax();
            if ($base <= 0.0001) { 
                $base = (float) $it->get_subtotal() + (float) $it->get_subtotal_tax(); 
            }
            $per_item_base[$iid] = $base;
            $products_total += $base;
        }

        // Points distribution
        $points_redeemed_total = (int) $order->get_meta('_ywpar_coupon_points', true);
        $per_item_points_initial = [];
        
        if ($points_redeemed_total > 0 && $products_total > 0) {
            $acc_pts = 0;
            $items_keys = array_keys($per_item_base);
            $last_idx = count($items_keys) - 1;
            
            foreach ($items_keys as $i => $iid) {
                $base = $per_item_base[$iid];
                $portion = $base / $products_total;
                $alloc = ($i === $last_idx) ? max(0, $points_redeemed_total - $acc_pts) : (int) round($points_redeemed_total * $portion);
                $per_item_points_initial[$iid] = $alloc;
                $acc_pts += $alloc;
            }
        }

        // Get points already refunded
        $per_item_points_refunded = [];
        foreach ($order->get_refunds() as $r) {
            $map_json = (string) get_post_meta($r->get_id(), '_eao_points_refunded_map', true);
            if ($map_json) {
                $map = json_decode($map_json, true);
                if (is_array($map)) {
                    foreach ($map as $iid => $pts) {
                        $iid = absint($iid);
                        $per_item_points_refunded[$iid] = ($per_item_points_refunded[$iid] ?? 0) + (int) $pts;
                    }
                }
            }
        }

        // Build product rows
        foreach ($order_items as $item_id => $item) {
            $product = $item->get_product();
            $sku = $product ? $product->get_sku() : '';
            $image = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '';
            $qty = (int) $item->get_quantity();
            
            $base = isset($per_item_base[$item_id]) ? $per_item_base[$item_id] : 0.0;
            
            // Calculate refunded amounts
            $refunded_item = method_exists($order, 'get_total_refunded_for_item') ? (float) $order->get_total_refunded_for_item($item_id) : 0.0;
            $refunded_tax = method_exists($order, 'get_total_tax_refunded_for_item') ? (float) $order->get_total_tax_refunded_for_item($item_id) : 0.0;
            $refunded_line = $refunded_item + $refunded_tax;
            $remaining = max(0.0, $base - $refunded_line);
            
            $points_initial = (int) ($per_item_points_initial[$item_id] ?? 0);
            $points_refunded = (int) ($per_item_points_refunded[$item_id] ?? 0);
            $points_remaining = max(0, $points_initial - $points_refunded);
            
            $items_resp[] = [
                'item_id' => $item_id,
                'name' => $item->get_name(),
                'sku' => $sku,
                'image' => $image,
                'qty' => $qty,
                'points_initial' => $points_initial,
                'points' => $points_remaining,
                'paid' => wc_format_decimal($base, 2),
                'remaining' => wc_format_decimal($remaining, 2)
            ];
        }

        // Add shipping rows
        $shipping_items = $order->get_items('shipping');
        if (!empty($shipping_items)) {
            foreach ($shipping_items as $sh_id => $sh_item) {
                $sh_paid = (float) $sh_item->get_total() + (float) $sh_item->get_total_tax();
                $sh_refunded = 0.0;
                if (method_exists($order, 'get_total_refunded_for_item')) { 
                    $sh_refunded += (float) $order->get_total_refunded_for_item($sh_id, 'shipping'); 
                }
                if (method_exists($order, 'get_total_tax_refunded_for_item')) { 
                    $sh_refunded += (float) $order->get_total_tax_refunded_for_item($sh_id, 'shipping'); 
                }
                $sh_remaining = max(0.0, $sh_paid - $sh_refunded);
                
                $items_resp[] = [
                    'item_id' => $sh_id,
                    'name' => 'Shipping: ' . $sh_item->get_name(),
                    'sku' => '',
                    'image' => '',
                    'qty' => '',
                    'points_initial' => 0,
                    'points' => 0,
                    'paid' => wc_format_decimal($sh_paid, 2),
                    'remaining' => wc_format_decimal($sh_remaining, 2)
                ];
            }
        }

        // Existing refunds snapshot
        $existing = [];
        foreach ($order->get_refunds() as $refund) {
            $existing[] = [
                'id' => $refund->get_id(),
                'amount' => wc_format_decimal($refund->get_amount(), 2),
                'reason' => $refund->get_reason(),
                'date' => $refund->get_date_created() ? $refund->get_date_created()->date_i18n('Y-m-d H:i') : '',
                'points' => (int) get_post_meta($refund->get_id(), '_eao_points_refunded', true)
            ];
        }

        // Gateway description
        $stripe_mode = (string) $order->get_meta('_hp_rw_stripe_mode', true);
        $mode_label = ($stripe_mode === 'test') ? 'Test' : 'Live';
        $gateway_info = ['label' => 'Stripe (HP-RW Funnel ' . $mode_label . ')'];

        // Ensure clean JSON
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }
        
        wp_send_json_success([
            'items' => $items_resp,
            'refunds' => $existing,
            'gateway' => $gateway_info
        ]);
    }
}
