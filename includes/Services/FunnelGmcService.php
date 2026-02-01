<?php
namespace HP_RW\Services;

use HP_RW\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for managing funnel Google Merchant Center integration.
 * 
 * Provides methods to retrieve GMC-specific data from funnels,
 * calculate prices, and determine sync eligibility.
 */
class FunnelGmcService
{
    /**
     * Default Google Product Category for health supplements.
     */
    public const DEFAULT_CATEGORY = 469;

    /**
     * Get all GMC data for a funnel.
     *
     * @param int $funnelId Funnel post ID
     * @return array|null GMC data array or null if funnel not found
     */
    public static function getFunnelGmcData(int $funnelId): ?array
    {
        $post = get_post($funnelId);
        if (!$post || $post->post_type !== Plugin::FUNNEL_POST_TYPE) {
            return null;
        }

        $config = FunnelConfigLoader::get($funnelId);
        if (!$config) {
            return null;
        }

        // Get GMC-specific fields
        $gmcEnabled = (bool) get_field('funnel_gmc_enabled', $funnelId);
        $titleOverride = get_field('funnel_gmc_title_override', $funnelId) ?: '';
        $descriptionOverride = get_field('funnel_gmc_description_override', $funnelId) ?: '';
        $imageOverride = get_field('funnel_gmc_image_override', $funnelId);
        $category = (int) (get_field('funnel_gmc_category', $funnelId) ?: self::DEFAULT_CATEGORY);
        $brandOverride = get_field('funnel_gmc_brand', $funnelId) ?: '';

        // Get custom labels from group field
        $customLabelsGroup = get_field('field_funnel_gmc_custom_labels_group', $funnelId);
        $customLabels = [];
        for ($i = 0; $i < 5; $i++) {
            $label = '';
            if (is_array($customLabelsGroup)) {
                $label = $customLabelsGroup["funnel_gmc_custom_label_{$i}"] ?? '';
            } else {
                $label = get_field("funnel_gmc_custom_label_{$i}", $funnelId) ?: '';
            }
            $customLabels[] = $label;
        }

        // Calculate derived values
        $lowestPrice = self::getLowestOfferPrice($funnelId);
        $brand = $brandOverride ?: self::detectBrandFromProducts($config);
        $availability = self::calculateAvailability($config);

        // Resolve image URL
        $imageUrl = '';
        if (!empty($imageOverride)) {
            $imageUrl = is_array($imageOverride) ? ($imageOverride['url'] ?? '') : $imageOverride;
        }
        if (empty($imageUrl) && !empty($config['hero']['image'])) {
            $imageUrl = $config['hero']['image'];
        }

        return [
            'funnel_id' => $funnelId,
            'slug' => $config['slug'] ?? $post->post_name,
            'enabled' => $gmcEnabled,
            
            // Core product data
            'title' => $titleOverride ?: ($config['hero']['title'] ?? $post->post_title),
            'description' => $descriptionOverride ?: ($config['hero']['description'] ?? ''),
            'image_link' => $imageUrl,
            'link' => get_permalink($funnelId),
            
            // Pricing
            'price' => $lowestPrice,
            'price_formatted' => number_format($lowestPrice, 2) . ' USD',
            
            // Attributes
            'brand' => $brand,
            'condition' => 'new',
            'availability' => $availability,
            'google_product_category' => $category,
            
            // Custom labels
            'custom_label_0' => $customLabels[0] ?? '',
            'custom_label_1' => $customLabels[1] ?? '',
            'custom_label_2' => $customLabels[2] ?? '',
            'custom_label_3' => $customLabels[3] ?? '',
            'custom_label_4' => $customLabels[4] ?? '',
            
            // Item group for variants
            'item_group_id' => 'funnel_' . $funnelId,
            
            // Raw overrides for reference
            'overrides' => [
                'title' => $titleOverride,
                'description' => $descriptionOverride,
                'image' => $imageUrl,
                'brand' => $brandOverride,
            ],
        ];
    }

    /**
     * Get the lowest offer price from a funnel.
     *
     * @param int $funnelId Funnel post ID
     * @return float Lowest price or 0 if no valid offers
     */
    public static function getLowestOfferPrice(int $funnelId): float
    {
        $config = FunnelConfigLoader::get($funnelId);
        $offers = $config['offers'] ?? [];
        
        if (empty($offers)) {
            return 0.0;
        }

        $lowestPrice = PHP_FLOAT_MAX;

        foreach ($offers as $offer) {
            // Use explicit offerPrice if set (camelCase from FunnelConfigLoader)
            if (isset($offer['offerPrice']) && $offer['offerPrice'] !== null && $offer['offerPrice'] !== '') {
                $price = (float) $offer['offerPrice'];
            } else {
                // Calculate from products if no explicit price
                $price = self::calculateOfferPrice($offer, $config);
            }

            if ($price > 0 && $price < $lowestPrice) {
                $lowestPrice = $price;
            }
        }

        return $lowestPrice === PHP_FLOAT_MAX ? 0.0 : $lowestPrice;
    }

    /**
     * Calculate offer price from products.
     *
     * @param array $offer Offer data
     * @param array $config Full funnel config
     * @return float Calculated price
     */
    private static function calculateOfferPrice(array $offer, array $config): float
    {
        $total = 0.0;
        $offerType = $offer['type'] ?? 'single';

        // Get products from the 'products' array populated by FunnelConfigLoader
        $products = $offer['products'] ?? [];

        if (!empty($products)) {
            // Use the products array directly (already enriched by FunnelConfigLoader)
            foreach ($products as $product) {
                $sku = $product['sku'] ?? '';
                $qty = (int) ($product['qty'] ?? 1);
                $productPrice = self::getProductPriceBySku($sku);
                $total += $productPrice * $qty;
            }
        } elseif ($offerType === 'single') {
            // Fallback to legacy single product fields
            $sku = $offer['productSku'] ?? $offer['product_sku'] ?? '';
            $qty = (int) ($offer['quantity'] ?? 1);
            $productPrice = self::getProductPriceBySku($sku);
            $total = $productPrice * $qty;
        } elseif ($offerType === 'fixed_bundle') {
            // Fallback to legacy bundle_items
            foreach ($offer['bundleItems'] ?? $offer['bundle_items'] ?? [] as $item) {
                $sku = $item['sku'] ?? '';
                $qty = (int) ($item['qty'] ?? 1);
                $productPrice = self::getProductPriceBySku($sku);
                $total += $productPrice * $qty;
            }
        } elseif ($offerType === 'customizable_kit') {
            // Fallback to legacy kit_products
            foreach ($offer['kitProducts'] ?? $offer['kit_products'] ?? [] as $item) {
                $sku = $item['sku'] ?? '';
                $qty = (int) ($item['qty'] ?? 1);
                $productPrice = self::getProductPriceBySku($sku);
                $total += $productPrice * $qty;
            }
        }

        // Apply discount if configured (camelCase from FunnelConfigLoader)
        $discountType = $offer['discountType'] ?? $offer['discount_type'] ?? 'none';
        $discountValue = (float) ($offer['discountValue'] ?? $offer['discount_value'] ?? 0);

        if ($discountType === 'percent_off' && $discountValue > 0) {
            $total = $total * (1 - ($discountValue / 100));
        } elseif ($discountType === 'fixed_discount' && $discountValue > 0) {
            $total = max(0, $total - $discountValue);
        }

        return round($total, 2);
    }

    /**
     * Get product price by SKU.
     *
     * @param string $sku Product SKU
     * @return float Product price or 0 if not found
     */
    private static function getProductPriceBySku(string $sku): float
    {
        if (empty($sku)) {
            return 0.0;
        }

        $productId = wc_get_product_id_by_sku($sku);
        if (!$productId) {
            return 0.0;
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return 0.0;
        }

        return (float) $product->get_price();
    }

    /**
     * Detect brand from funnel products.
     *
     * @param array $config Funnel config
     * @return string Brand name
     */
    private static function detectBrandFromProducts(array $config): string
    {
        $offers = $config['offers'] ?? [];
        
        foreach ($offers as $offer) {
            $sku = '';
            $offerType = $offer['type'] ?? 'single';
            
            if ($offerType === 'single') {
                $sku = $offer['product_sku'] ?? '';
            } elseif (!empty($offer['bundle_items'][0]['sku'])) {
                $sku = $offer['bundle_items'][0]['sku'];
            } elseif (!empty($offer['kit_products'][0]['sku'])) {
                $sku = $offer['kit_products'][0]['sku'];
            }

            if ($sku) {
                $productId = wc_get_product_id_by_sku($sku);
                if ($productId) {
                    // Try to get brand from product meta or taxonomy
                    $brand = get_post_meta($productId, '_brand', true);
                    if (!$brand) {
                        $brand = get_post_meta($productId, 'brand', true);
                    }
                    if (!$brand) {
                        // Try WooCommerce Brands taxonomy
                        $terms = get_the_terms($productId, 'product_brand');
                        if (!empty($terms) && !is_wp_error($terms)) {
                            $brand = $terms[0]->name;
                        }
                    }
                    if ($brand) {
                        return $brand;
                    }
                }
            }
        }

        // Fallback to site name
        return get_bloginfo('name');
    }

    /**
     * Calculate availability based on product stock.
     *
     * @param array $config Funnel config
     * @return string Availability status (in_stock, out_of_stock, preorder)
     */
    private static function calculateAvailability(array $config): string
    {
        $offers = $config['offers'] ?? [];
        
        foreach ($offers as $offer) {
            $skus = self::getOfferSkus($offer);
            
            foreach ($skus as $sku) {
                $productId = wc_get_product_id_by_sku($sku);
                if (!$productId) {
                    continue;
                }

                $product = wc_get_product($productId);
                if (!$product) {
                    continue;
                }

                if (!$product->is_in_stock()) {
                    return 'out_of_stock';
                }

                // Check for backorder status
                if ($product->is_on_backorder()) {
                    return 'preorder';
                }
            }
        }

        return 'in_stock';
    }

    /**
     * Get all SKUs from an offer.
     *
     * @param array $offer Offer data
     * @return array Array of SKUs
     */
    private static function getOfferSkus(array $offer): array
    {
        $skus = [];
        $offerType = $offer['type'] ?? 'single';

        if ($offerType === 'single' && !empty($offer['product_sku'])) {
            $skus[] = $offer['product_sku'];
        } elseif ($offerType === 'fixed_bundle') {
            foreach ($offer['bundle_items'] ?? [] as $item) {
                if (!empty($item['sku'])) {
                    $skus[] = $item['sku'];
                }
            }
        } elseif ($offerType === 'customizable_kit') {
            foreach ($offer['kit_products'] ?? [] as $item) {
                if (!empty($item['sku'])) {
                    $skus[] = $item['sku'];
                }
            }
        }

        return array_unique($skus);
    }

    /**
     * Get the brand for a funnel.
     *
     * @param int $funnelId Funnel post ID
     * @return string Brand name
     */
    public static function getFunnelBrand(int $funnelId): string
    {
        $brandOverride = get_field('funnel_gmc_brand', $funnelId);
        if ($brandOverride) {
            return $brandOverride;
        }

        $config = FunnelConfigLoader::get($funnelId);
        if ($config) {
            return self::detectBrandFromProducts($config);
        }

        return get_bloginfo('name');
    }

    /**
     * Check if a funnel is enabled for GMC sync.
     *
     * @param int $funnelId Funnel post ID
     * @return bool True if enabled
     */
    public static function isFunnelGmcEnabled(int $funnelId): bool
    {
        return (bool) get_field('funnel_gmc_enabled', $funnelId);
    }

    /**
     * Get all funnels that are enabled for GMC sync.
     *
     * @return array Array of funnel data
     */
    public static function getAllGmcEnabledFunnels(): array
    {
        $posts = get_posts([
            'post_type' => Plugin::FUNNEL_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'meta_query' => [
                [
                    'key' => 'funnel_gmc_enabled',
                    'value' => '1',
                    'compare' => '=',
                ],
            ],
        ]);

        $funnels = [];
        foreach ($posts as $post) {
            $data = self::getFunnelGmcData($post->ID);
            if ($data && $data['enabled'] && $data['price'] > 0) {
                $funnels[] = $data;
            }
        }

        return $funnels;
    }

    /**
     * Validate funnel for GMC eligibility.
     *
     * @param int $funnelId Funnel post ID
     * @return array Validation result with 'valid' bool and 'errors' array
     */
    public static function validateForGmc(int $funnelId): array
    {
        $errors = [];
        $data = self::getFunnelGmcData($funnelId);

        if (!$data) {
            return ['valid' => false, 'errors' => ['Funnel not found']];
        }

        // Required fields
        if (empty($data['title'])) {
            $errors[] = 'Title is required';
        }

        if (empty($data['description'])) {
            $errors[] = 'Description is required';
        }

        if (empty($data['image_link'])) {
            $errors[] = 'Image is required';
        }

        if ($data['price'] <= 0) {
            $errors[] = 'Price must be greater than 0';
        }

        if (empty($data['link'])) {
            $errors[] = 'Funnel URL is required';
        }

        // Recommendations (warnings, not errors)
        $warnings = [];

        if (strlen($data['title']) > 150) {
            $warnings[] = 'Title exceeds 150 characters';
        }

        if (strlen($data['description']) < 100) {
            $warnings[] = 'Description is short (less than 100 characters)';
        }

        if (strlen($data['description']) > 5000) {
            $warnings[] = 'Description exceeds 5000 characters';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => $data,
        ];
    }
}
