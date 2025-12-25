<?php
namespace HP_RW\Services;

use HP_RW\Util\Resolver;
use WC_Order_Item_Product;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipping service for fetching real-time shipping rates.
 * 
 * Integrates with EAO's ShipStation utilities for rate fetching.
 * Respects HP ShipStation Rates plugin allow-list if available.
 */
class ShippingService
{
    /**
     * Get shipping rates for given items and address.
     * 
     * @param array $address Shipping address with keys: first_name, last_name, address_1, address_2, city, state, postcode, country
     * @param array $items Array of items with keys: sku, qty, product_id (optional), variation_id (optional)
     * @return array{success: bool, rates?: array, error?: string}
     */
    public function getRates(array $address, array $items): array
    {
        // Ensure EAO ShipStation helpers are loaded
        if (!function_exists('eao_build_shipstation_rates_request') || !function_exists('eao_get_shipstation_carrier_rates')) {
            $this->tryLoadEaoShipStation();
            if (!function_exists('eao_build_shipstation_rates_request') || !function_exists('eao_get_shipstation_carrier_rates')) {
                return [
                    'success' => false,
                    'error'   => 'EAO ShipStation utilities not available',
                ];
            }
        }

        if (empty($items)) {
            return [
                'success' => false,
                'error'   => 'Items required',
            ];
        }

        $order_id = 0;
        $order = null;
        
        try {
            $order = wc_create_order(['status' => 'auto-draft']);
            $order_id = $order->get_id();
            
            foreach ($items as $it) {
                $qty = max(1, (int) ($it['qty'] ?? 1));
                $product = Resolver::resolveProductFromItem((array) $it);
                if (!$product) {
                    continue;
                }
                
                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity($qty);
                $item->set_total($product->get_price() * $qty);
                $order->add_item($item);
            }
            
            // Set shipping address
            $this->applyAddress($order, 'shipping', $address);
            $order->save();

            // Build base request via EAO helper
            $base_request = \eao_build_shipstation_rates_request($order);
            if (!$base_request || !is_array($base_request)) {
                return [
                    'success' => false,
                    'error'   => 'Could not prepare ShipStation request',
                ];
            }

            // Iterate carriers
            $default_carriers = ['stamps_com', 'ups_walleted'];
            $carriers_to_try = apply_filters('eao_shipstation_carriers_to_query', $default_carriers);
            if (!is_array($carriers_to_try)) {
                $carriers_to_try = $default_carriers;
            }

            $all_rates = [];
            $carrier_errors = [];

            foreach ($carriers_to_try as $carrier_code) {
                if (!is_string($carrier_code) || $carrier_code === '') {
                    continue;
                }
                
                $request_data = $base_request;
                $request_data['carrierCode'] = $carrier_code;
                
                if (($carrier_code === 'ups_walleted' || $carrier_code === 'ups') && function_exists('eao_customize_ups_request')) {
                    $request_data = \eao_customize_ups_request($request_data);
                }
                
                $carrier_rates_result = \eao_get_shipstation_carrier_rates($request_data);
                
                if (isset($carrier_rates_result['success']) && $carrier_rates_result['success'] 
                    && isset($carrier_rates_result['rates']) && is_array($carrier_rates_result['rates']) 
                    && !empty($carrier_rates_result['rates'])) {
                    foreach ($carrier_rates_result['rates'] as &$rate) {
                        if (is_array($rate) && !isset($rate['carrierCode'])) {
                            $rate['carrierCode'] = $carrier_code;
                        }
                    }
                    $all_rates = array_merge($all_rates, $carrier_rates_result['rates']);
                } else {
                    $carrier_errors[$carrier_code] = isset($carrier_rates_result['message']) 
                        ? (string) $carrier_rates_result['message'] 
                        : 'Unknown carrier error';
                }
            }

            if (empty($all_rates)) {
                $err = !empty($carrier_errors) 
                    ? 'HTTP Error: ' . implode('; ', array_map(
                        fn($k, $v) => $k . ': ' . $v,
                        array_keys($carrier_errors),
                        array_values($carrier_errors)
                    ))
                    : 'No rates available';
                return [
                    'success' => false,
                    'error'   => $err,
                ];
            }

            $rates = $all_rates;
            if (function_exists('eao_format_shipstation_rates_response')) {
                $fmt = \eao_format_shipstation_rates_response($rates);
                if (is_array($fmt) && isset($fmt['rates'])) {
                    $rates = $fmt['rates'];
                }
            }

            // Respect HP ShipStation Rates plugin allow-list
            $allowed = $this->getAllowedServiceCodes();
            if (!empty($allowed)) {
                $rates = array_values(array_filter($rates, function ($r) use ($allowed) {
                    if (!is_array($r)) {
                        return false;
                    }
                    $code = '';
                    if (isset($r['serviceCode'])) {
                        $code = (string) $r['serviceCode'];
                    } elseif (isset($r['service_code'])) {
                        $code = (string) $r['service_code'];
                    } elseif (isset($r['code'])) {
                        $code = (string) $r['code'];
                    }
                    $code = strtolower(trim($code));
                    return $code !== '' ? in_array($code, $allowed, true) : true;
                }));
            }

            return [
                'success' => true,
                'rates'   => $rates,
            ];
        } finally {
            // Cleanup the transient order
            if ($order_id > 0) {
                wp_delete_post($order_id, true);
            }
        }
    }

    /**
     * Attempt to include EAO ShipStation helper files.
     * Tries multiple possible plugin folder names.
     */
    private function tryLoadEaoShipStation(): void
    {
        // Try multiple possible plugin folder names
        $possible_folders = [
            'enhanced-admin-order-plugin',
            'HP-enhanced-admin-order',
            'enhanced-admin-order',
        ];
        
        foreach ($possible_folders as $folder) {
            $base = trailingslashit(WP_PLUGIN_DIR) . $folder . '/';
            $utils = $base . 'eao-shipstation-utils.php';
            $core = $base . 'eao-shipstation-core.php';
            
            if (file_exists($utils) && file_exists($core)) {
                require_once $utils;
                require_once $core;
                return; // Found and loaded
            }
        }
        
        // Log if not found for debugging
        error_log('[HP-RW ShippingService] Could not find EAO ShipStation files in any expected location');
    }

    /**
     * Read allowed service codes from the HP ShipStation Rates plugin.
     * 
     * @return array<string> Lowercase ShipStation serviceCode values that are enabled.
     */
    private function getAllowedServiceCodes(): array
    {
        if (function_exists('hp_ss_get_enabled_service_codes')) {
            try {
                $list = \hp_ss_get_enabled_service_codes();
                if (is_array($list) && !empty($list)) {
                    return array_values(array_unique(array_map(
                        fn($s) => strtolower(trim((string) $s)),
                        $list
                    )));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return [];
    }

    /**
     * Apply address fields to a WooCommerce order.
     * 
     * @param \WC_Order $order
     * @param string $type 'billing' or 'shipping'
     * @param array $addr Address data
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















