<?php
namespace HP_RW\Util;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utility helpers to resolve WooCommerce products from incoming item payloads.
 *
 * Supported payload shape per item:
 * - { product_id?: number, sku?: string, variation_id?: number, qty?: number }
 */
class Resolver
{
    /** @var array<string, \WC_Product> Static cache for resolved products during this request */
    private static $resolvedProducts = [];

    /**
     * Resolve a WC_Product (or variation) from an incoming item payload.
     *
     * @param array $item Item data with optional product_id, sku, or variation_id
     * @return \WC_Product|null The resolved product or null if not found
     */
    public static function resolveProductFromItem(array $item): ?\WC_Product
    {
        $product_id = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        $variation_id = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
        $sku = isset($item['sku']) ? (string) $item['sku'] : '';

        // Build cache key
        $cacheKey = "p_{$product_id}_v_{$variation_id}_s_{$sku}";
        if (isset(self::$resolvedProducts[$cacheKey])) {
            return self::$resolvedProducts[$cacheKey];
        }

        // If no product_id but we have a SKU, try to resolve by SKU
        if ($product_id <= 0 && $sku !== '') {
            $pid = wc_get_product_id_by_sku($sku);
            if ($pid && is_numeric($pid)) {
                $product_id = (int) $pid;
            }
        }

        $p = null;

        // If we have a variation_id, prefer that
        if ($variation_id > 0) {
            $p = wc_get_product($variation_id);
        }

        // Otherwise use product_id
        if (!$p && $product_id > 0) {
            $p = wc_get_product($product_id);
        }

        if ($p) {
            self::$resolvedProducts[$cacheKey] = $p;
        }

        return $p;
    }

    /**
     * Get product data for display.
     *
     * @param \WC_Product $product The WooCommerce product
     * @return array Product data for frontend display
     */
    public static function getProductDisplayData(\WC_Product $product): array
    {
        $image_id = $product->get_image_id();
        $image_url = '';
        
        if ($image_id) {
            $image_data = wp_get_attachment_image_src($image_id, 'woocommerce_thumbnail');
            if ($image_data && isset($image_data[0])) {
                $image_url = $image_data[0];
            }
        }

        // Fallback to placeholder
        if (!$image_url) {
            $image_url = wc_placeholder_img_src('woocommerce_thumbnail');
        }

        return [
            'id'          => $product->get_id(),
            'name'        => $product->get_name(),
            'sku'         => $product->get_sku(),
            'price'       => (float) $product->get_price(),
            'regular_price' => (float) $product->get_regular_price(),
            'sale_price'  => $product->get_sale_price() ? (float) $product->get_sale_price() : null,
            'on_sale'     => $product->is_on_sale(),
            'in_stock'    => $product->is_in_stock(),
            'stock_qty'   => $product->get_stock_quantity(),
            'image'       => $image_url,
            'short_description' => $product->get_short_description(),
        ];
    }

    /**
     * Resolve multiple products from SKUs.
     *
     * @param array $skus Array of SKU strings
     * @return array<string, \WC_Product> Map of SKU => Product
     */
    public static function resolveProductsBySku(array $skus): array
    {
        $products = [];

        foreach ($skus as $sku) {
            $sku = (string) $sku;
            if ($sku === '') {
                continue;
            }

            $product = self::resolveProductFromItem(['sku' => $sku]);
            if ($product) {
                $products[$sku] = $product;
            }
        }

        return $products;
    }

    /**
     * Get prices for multiple SKUs.
     *
     * @param array $skus Array of SKU strings
     * @return array<string, float> Map of SKU => price
     */
    public static function getPricesForSkus(array $skus): array
    {
        $prices = [];
        $products = self::resolveProductsBySku($skus);

        foreach ($products as $sku => $product) {
            $prices[$sku] = (float) $product->get_price();
        }

        return $prices;
    }
}















