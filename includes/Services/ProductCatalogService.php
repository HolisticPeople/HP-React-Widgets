<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for accessing product catalog data with serving information
 * for AI-powered kit building and calculations.
 */
class ProductCatalogService
{
    /**
     * Meta keys for product serving information.
     */
    private const META_SERVINGS_PER_CONTAINER = '_hp_servings_per_container';
    private const META_SERVING_SIZE = '_hp_serving_size';
    private const META_SERVING_UNIT = '_hp_serving_unit';
    private const META_PRODUCT_COST = '_hp_product_cost';

    /**
     * Get product details including serving info for AI calculations.
     *
     * @param string $sku Product SKU
     * @return array|null Product details or null if not found
     */
    public static function getProductDetails(string $sku): ?array
    {
        $product = self::getProductBySku($sku);
        
        if (!$product) {
            return null;
        }

        return self::formatProductData($product);
    }

    /**
     * Get product economics data (cost, margin calculations).
     *
     * @param string $sku Product SKU
     * @return array|null Economics data or null if not found
     */
    public static function getProductEconomics(string $sku): ?array
    {
        $product = self::getProductBySku($sku);
        
        if (!$product) {
            return null;
        }

        $price = (float) $product->get_price();
        $cost = self::getProductCost($product);
        $marginDollars = $price - $cost;
        $marginPercent = $price > 0 ? ($marginDollars / $price) * 100 : 0;

        return [
            'sku' => $sku,
            'name' => $product->get_name(),
            'price' => $price,
            'cost' => $cost,
            'weight_oz' => self::getWeightInOz($product),
            'margin_percent' => round($marginPercent, 1),
            'margin_dollars' => round($marginDollars, 2),
        ];
    }

    /**
     * Calculate quantity needed for X days supply.
     *
     * @param string $sku Product SKU
     * @param int $days Number of days
     * @param int $servingsPerDay Servings consumed per day
     * @return array|null Calculation result or null if product not found
     */
    public static function calculateSupply(string $sku, int $days, int $servingsPerDay = 1): ?array
    {
        $product = self::getProductBySku($sku);
        
        if (!$product) {
            return null;
        }

        $servingsPerContainer = self::getServingsPerContainer($product);
        
        if ($servingsPerContainer <= 0) {
            return [
                'error' => 'Product does not have serving information configured',
                'sku' => $sku,
            ];
        }

        $totalServingsNeeded = $days * $servingsPerDay;
        $bottlesNeeded = (int) ceil($totalServingsNeeded / $servingsPerContainer);
        $totalServings = $bottlesNeeded * $servingsPerContainer;
        $coversDays = (int) floor($totalServings / $servingsPerDay);

        return [
            'sku' => $sku,
            'name' => $product->get_name(),
            'days_requested' => $days,
            'servings_per_day' => $servingsPerDay,
            'servings_per_container' => $servingsPerContainer,
            'bottles_needed' => $bottlesNeeded,
            'total_servings' => $totalServings,
            'covers_days' => $coversDays,
            'price_per_bottle' => (float) $product->get_price(),
            'total_price' => (float) $product->get_price() * $bottlesNeeded,
        ];
    }

    /**
     * Search products by category, name, or other filters.
     *
     * @param array $filters Search filters
     * @return array Matching products
     */
    public static function searchProducts(array $filters): array
    {
        $args = [
            'status' => 'publish',
            'limit' => $filters['limit'] ?? 50,
            'return' => 'objects',
        ];

        // Category filter
        if (!empty($filters['category'])) {
            $args['category'] = [$filters['category']];
        }

        // SKU filter (partial match)
        if (!empty($filters['sku'])) {
            $args['sku'] = $filters['sku'];
        }

        // Tag filter
        if (!empty($filters['tag'])) {
            $args['tag'] = [$filters['tag']];
        }

        // Search by name
        if (!empty($filters['search'])) {
            $args['s'] = $filters['search'];
        }

        // Only include products with serving info if requested
        if (!empty($filters['has_serving_info'])) {
            $args['meta_query'] = [
                [
                    'key' => self::META_SERVINGS_PER_CONTAINER,
                    'compare' => 'EXISTS',
                ],
            ];
        }

        $products = wc_get_products($args);
        $results = [];

        foreach ($products as $product) {
            $results[] = self::formatProductData($product);
        }

        return $results;
    }

    /**
     * Get all products with their economics data for AI analysis.
     *
     * @param array $filters Optional filters
     * @return array Products with economics
     */
    public static function getProductsWithEconomics(array $filters = []): array
    {
        $products = self::searchProducts($filters);
        $results = [];

        foreach ($products as $productData) {
            $economics = self::getProductEconomics($productData['sku']);
            if ($economics) {
                $results[] = array_merge($productData, [
                    'cost' => $economics['cost'],
                    'margin_percent' => $economics['margin_percent'],
                    'margin_dollars' => $economics['margin_dollars'],
                ]);
            }
        }

        return $results;
    }

    /**
     * Get product categories for filtering.
     *
     * @return array Categories with counts
     */
    public static function getCategories(): array
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
        ]);

        if (is_wp_error($categories)) {
            return [];
        }

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'id' => $category->term_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'count' => $category->count,
            ];
        }

        return $result;
    }

    /**
     * Format product data for API response.
     *
     * @param \WC_Product $product WooCommerce product
     * @return array Formatted product data
     */
    private static function formatProductData(\WC_Product $product): array
    {
        $imageId = $product->get_image_id();
        $imageUrl = $imageId ? wp_get_attachment_image_url($imageId, 'medium') : '';

        $categories = [];
        $categoryTerms = wp_get_post_terms($product->get_id(), 'product_cat');
        foreach ($categoryTerms as $term) {
            $categories[] = $term->slug;
        }

        return [
            'id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'name' => $product->get_name(),
            'price' => (float) $product->get_price(),
            'regular_price' => (float) $product->get_regular_price(),
            'sale_price' => $product->get_sale_price() ? (float) $product->get_sale_price() : null,
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'weight' => $product->get_weight(),
            'weight_oz' => self::getWeightInOz($product),
            'categories' => $categories,
            'image_url' => $imageUrl,
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            // Serving information
            'servings_per_container' => self::getServingsPerContainer($product),
            'serving_size' => self::getServingSize($product),
            'serving_unit' => self::getServingUnit($product),
        ];
    }

    /**
     * Get product by SKU.
     *
     * @param string $sku Product SKU
     * @return \WC_Product|null
     */
    private static function getProductBySku(string $sku): ?\WC_Product
    {
        $productId = wc_get_product_id_by_sku($sku);
        
        if (!$productId) {
            return null;
        }

        $product = wc_get_product($productId);
        
        return $product instanceof \WC_Product ? $product : null;
    }

    /**
     * Get servings per container from product meta.
     *
     * @param \WC_Product $product
     * @return int
     */
    private static function getServingsPerContainer(\WC_Product $product): int
    {
        $value = $product->get_meta(self::META_SERVINGS_PER_CONTAINER);
        
        // Fallback to ACF field if available
        if (empty($value) && function_exists('get_field')) {
            $value = get_field('servings_per_container', $product->get_id());
        }
        
        return (int) $value;
    }

    /**
     * Get serving size from product meta.
     *
     * @param \WC_Product $product
     * @return string
     */
    private static function getServingSize(\WC_Product $product): string
    {
        $value = $product->get_meta(self::META_SERVING_SIZE);
        
        if (empty($value) && function_exists('get_field')) {
            $value = get_field('serving_size', $product->get_id());
        }
        
        return (string) $value;
    }

    /**
     * Get serving unit from product meta.
     *
     * @param \WC_Product $product
     * @return string
     */
    private static function getServingUnit(\WC_Product $product): string
    {
        $value = $product->get_meta(self::META_SERVING_UNIT);
        
        if (empty($value) && function_exists('get_field')) {
            $value = get_field('serving_unit', $product->get_id());
        }
        
        return (string) $value ?: 'serving';
    }

    /**
     * Get product cost (COGS) from meta.
     *
     * @param \WC_Product $product
     * @return float
     */
    private static function getProductCost(\WC_Product $product): float
    {
        $candidate_ids = [$product->get_id()];
        if ($product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $candidate_ids[] = $parent_id;
            }
        }

        foreach ($candidate_ids as $id) {
            $p = ($id === $product->get_id()) ? $product : wc_get_product($id);
            if (!$p) continue;

            // 1) Primary: Custom HP field
            $value = $p->get_meta(self::META_PRODUCT_COST);
            if (!empty($value) && is_numeric($value)) return (float) $value;
            
            // 2) Fallback: EAO / Cost of Goods Sold plugin key
            $value = $p->get_meta('_cogs_total_value');
            if (!empty($value) && is_numeric($value)) return (float) $value;

            // 3) Fallback: ACF field if available
            if (function_exists('get_field')) {
                $value = get_field('product_cost', $id);
                if (!empty($value) && is_numeric($value)) return (float) $value;
            }
            
            // 4) Fallback: WooCommerce Cost of Goods plugin
            $value = $p->get_meta('_wc_cog_cost');
            if (!empty($value) && is_numeric($value)) return (float) $value;
        }
        
        return 0.0;
    }

    /**
     * Get product weight in ounces.
     *
     * @param \WC_Product $product
     * @return float
     */
    private static function getWeightInOz(\WC_Product $product): float
    {
        $weight = $product->get_weight();
        
        if (empty($weight)) {
            return 0;
        }

        $weightUnit = get_option('woocommerce_weight_unit', 'lbs');
        
        // Convert to ounces
        switch ($weightUnit) {
            case 'kg':
                return $weight * 35.274;
            case 'g':
                return $weight * 0.035274;
            case 'lbs':
                return $weight * 16;
            case 'oz':
            default:
                return (float) $weight;
        }
    }
}















