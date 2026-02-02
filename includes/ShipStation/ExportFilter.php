<?php
/**
 * ShipStation Export Filter
 * 
 * Filters the order data exported to ShipStation to match how orders
 * are displayed in EAO (Enhanced Admin Order):
 * - Items show at their discounted prices (not full price)
 * - "Offer Savings" fee is not exported
 * 
 * @package HP_RW
 * @since 2.43.81
 * @author Holistic People
 */

namespace HP_RW\ShipStation;

if (!defined('ABSPATH')) {
    exit;
}

class ExportFilter
{
    /**
     * Initialize the filter hooks.
     */
    public static function init(): void
    {
        // Hook into ShipStation export (available since ShipStation v4.1.39)
        add_filter('woocommerce_shipstation_export_order_xml', [self::class, 'filterOrderXml'], 10, 1);
    }

    /**
     * Filter the order XML before it's sent to ShipStation.
     * 
     * Modifies line items to show discounted prices and removes
     * fee-based discount items like "Offer Savings".
     * 
     * @param \DOMElement $order_xml The order XML element.
     * @return \DOMElement The filtered order XML element.
     */
    public static function filterOrderXml($order_xml): \DOMElement
    {
        try {
            // Convert DOMElement to SimpleXML for easier manipulation
            $xml = simplexml_import_dom($order_xml);
            if (!$xml) {
                return $order_xml;
            }

            // Get the order ID from the XML
            $orderId = (string) ($xml->OrderID ?? '');
            if (empty($orderId)) {
                return $order_xml;
            }

            // Load the WooCommerce order
            $order = wc_get_order($orderId);
            if (!$order) {
                return $order_xml;
            }

            // Check if this is an HP-RW funnel order
            $funnelId = $order->get_meta('_hp_rw_funnel_id');
            if (empty($funnelId)) {
                // Not a funnel order, don't modify
                return $order_xml;
            }

            // Build a map of product discounts from order items
            $itemDiscounts = [];
            foreach ($order->get_items() as $item) {
                $productId = $item->get_product_id();
                $variationId = $item->get_variation_id();
                $sku = '';
                
                $product = $item->get_product();
                if ($product) {
                    $sku = $product->get_sku();
                }
                
                // Get discount percentage from meta (stored by funnel checkout)
                $discountPercent = $item->get_meta('_hp_rw_item_discount_percent');
                if (!$discountPercent) {
                    $discountPercent = $item->get_meta('_eao_item_discount_percent');
                }
                
                if ($discountPercent) {
                    // Store by SKU, product ID, and variation ID for lookup
                    if ($sku) {
                        $itemDiscounts['sku:' . $sku] = (float) $discountPercent;
                    }
                    if ($variationId) {
                        $itemDiscounts['var:' . $variationId] = (float) $discountPercent;
                    }
                    $itemDiscounts['prod:' . $productId] = (float) $discountPercent;
                }
            }

            // Track items to remove (fees/discounts)
            $itemsToRemove = [];

            // Process XML items
            if (isset($xml->Items) && isset($xml->Items->Item)) {
                foreach ($xml->Items->Item as $key => $item) {
                    $sku = (string) ($item->SKU ?? '');
                    $name = (string) ($item->Name ?? '');
                    $unitPrice = (float) ($item->UnitPrice ?? 0);
                    
                    // Check if this is a fee item (Offer Savings, etc.)
                    // Fee items typically have no SKU and contain discount-related words
                    $isFeeItem = empty($sku) && (
                        stripos($name, 'Savings') !== false ||
                        stripos($name, 'Offer') !== false ||
                        stripos($name, 'Discount') !== false ||
                        stripos($name, 'Points') !== false
                    );
                    
                    if ($isFeeItem) {
                        $itemsToRemove[] = $key;
                        continue;
                    }
                    
                    // Look up discount for this item
                    $discountPercent = 0;
                    if ($sku && isset($itemDiscounts['sku:' . $sku])) {
                        $discountPercent = $itemDiscounts['sku:' . $sku];
                    }
                    
                    // Apply discount to unit price if found
                    if ($discountPercent > 0 && $unitPrice > 0) {
                        $discountedPrice = $unitPrice * (1 - ($discountPercent / 100));
                        $item->UnitPrice = number_format($discountedPrice, 2, '.', '');
                    }
                }
            }

            // Remove fee items (process in reverse to maintain indices)
            if (!empty($itemsToRemove) && isset($xml->Items)) {
                // Convert to DOM to remove elements
                $dom = dom_import_simplexml($xml);
                $itemsNode = null;
                
                foreach ($dom->childNodes as $child) {
                    if ($child->nodeName === 'Items') {
                        $itemsNode = $child;
                        break;
                    }
                }
                
                if ($itemsNode) {
                    $itemElements = [];
                    foreach ($itemsNode->childNodes as $child) {
                        if ($child->nodeName === 'Item') {
                            $itemElements[] = $child;
                        }
                    }
                    
                    // Remove items in reverse order
                    rsort($itemsToRemove);
                    foreach ($itemsToRemove as $index) {
                        if (isset($itemElements[$index])) {
                            $itemsNode->removeChild($itemElements[$index]);
                        }
                    }
                }
            }

            // Return the modified DOM element
            $dom = dom_import_simplexml($xml);
            return $dom instanceof \DOMElement ? $dom : $order_xml;
            
        } catch (\Exception $e) {
            error_log('[HP-RW ShipStation] Export filter error: ' . $e->getMessage());
            return $order_xml;
        }
    }
}
