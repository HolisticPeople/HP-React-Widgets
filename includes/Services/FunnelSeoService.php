<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Smart Bridge: Funnel SEO, Analytics, and Traffic Control Service.
 * 
 * Bridges ACF Funnel Data -> Yoast SEO Schema, OpenGraph, FiboSearch, and Google Analytics.
 * 
 * Modules:
 * - A.1: Yoast Schema Filters (Product, AggregateOffer, Reviews)
 * - A.2: OpenGraph Fallback Chain
 * - A.3: FiboSearch Integration
 * - B.1: Analytics Data Injector
 * - B.2: Shadow Listener for React Buttons
 * - B.3: URL-Based Step Tracking
 * - C.1: Product Canonical Swap
 * - C.2: Category Canonical Swap
 * - C.3: Admin Columns Monitoring
 * 
 * @since 2.9.0
 */
class FunnelSeoService
{
    /**
     * Settings option key for SEO & Tracking configuration.
     */
    public const OPTION_KEY = 'hp_funnel_seo_tracking_settings';

    /**
     * Default settings.
     */
    private const DEFAULT_SETTINGS = [
        // Schema settings
        'enable_schema'              => true,
        'default_brand'              => 'HolisticPeople',
        'include_testimonials'       => true,
        'enable_fibosearch'          => true,
        'schema_debug_mode'          => false,
        
        // Analytics settings
        'enable_analytics'           => true,
        'push_to_gtm'                => true,
        'push_to_ga4'                => true,
        'custom_button_selectors'    => '.hp-funnel-cta-btn, [data-checkout-submit], .hp-checkout-submit-btn, .offer-card-select-btn',
        'track_view_item'            => true,
        'track_add_to_cart'          => true,
        'track_begin_checkout'       => true,
        'track_purchase'             => true,
        'console_debug_mode'         => false,
        
        // Canonical settings
        'enable_canonical_swaps'     => true,
        'type1_product_swap'         => true,
        'type2_category_swap'        => true,
        'show_canonical_column'      => true,
        
        // General settings
        'auto_calculate_price_range' => true,
        'min_price_threshold'        => 0.01,
        'price_display_format'       => 'range', // range, starting_at, from
    ];

    /**
     * Initialize the service hooks.
     */
    public static function init(): void
    {
        $settings = self::getSettings();

        // MODULE A.1: SEO BRIDGE (Schema & Pricing)
        if ($settings['enable_schema']) {
            add_filter('wpseo_schema_webpage_type', [self::class, 'forceItemPage']);
            add_filter('wpseo_schema_graph_pieces', [self::class, 'addProductSchemaPiece'], 11, 2);
            add_filter('wpseo_opengraph_image', [self::class, 'handleOpenGraphImage']);
            add_filter('wpseo_opengraph_desc', [self::class, 'handleOpenGraphDesc']);
        }

        // MODULE A.3: SEARCH BRIDGE (FiboSearch)
        if ($settings['enable_fibosearch']) {
            add_filter('dgwt/wcas/indexer/post_types', [self::class, 'registerFiboSearchPostType']);
            add_filter('dgwt/wcas/indexer/readable/post/content', [self::class, 'indexRepeaterSkus'], 10, 3);
        }

        // MODULE B: ANALYTICS BRIDGE
        if ($settings['enable_analytics']) {
            add_action('wp_head', [self::class, 'injectAnalyticsData']);
            add_action('wp_footer', [self::class, 'injectAnalyticsScripts']);
        }

        // MODULE C: TRAFFIC CONTROL (Canonicals & Admin Columns)
        if ($settings['enable_canonical_swaps']) {
            add_filter('wpseo_canonical', [self::class, 'handleCanonicalSwaps']);
        }
        if ($settings['show_canonical_column']) {
            add_filter('manage_edit-product_columns', [self::class, 'addProductColumns']);
            add_action('manage_product_posts_custom_column', [self::class, 'renderProductColumn'], 10, 2);
        }

        // PRICE RANGE CALCULATION on funnel save
        if ($settings['auto_calculate_price_range']) {
            add_action('acf/save_post', [self::class, 'onFunnelSave'], 20);
        }
    }

    /**
     * Get settings with defaults.
     */
    public static function getSettings(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        return array_merge(self::DEFAULT_SETTINGS, is_array($stored) ? $stored : []);
    }

    /**
     * Save settings.
     */
    public static function saveSettings(array $settings): bool
    {
        return update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Handle funnel save - calculate and store price range + brand.
     */
    public static function onFunnelSave($postId): void
    {
        if (get_post_type($postId) !== 'hp-funnel') {
            return;
        }

        // Skip autosaves
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Calculate price range
        $priceRange = self::calculateFunnelPriceRange($postId);
        update_post_meta($postId, 'funnel_min_price', $priceRange['min']);
        update_post_meta($postId, 'funnel_max_price', $priceRange['max']);

        // Detect and store brand
        $brand = self::detectFunnelBrand($postId);
        update_post_meta($postId, 'funnel_brand', $brand);

        // Calculate availability based on stock
        $availability = self::calculateFunnelAvailability($postId);
        update_post_meta($postId, 'funnel_availability', $availability);
    }

    /**
     * Calculate price range for all offers in a funnel.
     * CRITICAL: Min price must NEVER be $0.
     */
    public static function calculateFunnelPriceRange(int $postId): array
    {
        $offers = get_field('funnel_offers', $postId);
        if (empty($offers) || !is_array($offers)) {
            $settings = self::getSettings();
            return ['min' => $settings['min_price_threshold'], 'max' => 0];
        }

        $minPrice = PHP_FLOAT_MAX;
        $maxPrice = 0;

        foreach ($offers as $offer) {
            $range = self::calculateOfferPriceRange($offer);
            $minPrice = min($minPrice, $range['min']);
            $maxPrice = max($maxPrice, $range['max']);
        }

        // Ensure min is never $0
        $settings = self::getSettings();
        $minThreshold = (float) $settings['min_price_threshold'];
        $minPrice = max($minPrice, $minThreshold);

        // Handle edge case where maxPrice is still 0
        if ($maxPrice === 0 || $maxPrice < $minPrice) {
            $maxPrice = $minPrice;
        }

        return [
            'min' => round($minPrice, 2),
            'max' => round($maxPrice, 2),
        ];
    }

    /**
     * Calculate price range for a single offer.
     */
    public static function calculateOfferPriceRange(array $offer): array
    {
        $type = $offer['offer_type'] ?? 'single';
        $settings = self::getSettings();
        $minThreshold = (float) $settings['min_price_threshold'];

        // If offer_price is explicitly set, use it as both min and max
        if (isset($offer['offer_price']) && $offer['offer_price'] !== '' && (float) $offer['offer_price'] > 0) {
            $price = (float) $offer['offer_price'];
            return ['min' => max($price, $minThreshold), 'max' => $price];
        }

        // Parse products_data JSON
        $productsJson = $offer['products_data'] ?? '';
        $products = !empty($productsJson) ? json_decode($productsJson, true) : [];
        
        if (empty($products) || !is_array($products)) {
            return ['min' => $minThreshold, 'max' => 0];
        }

        switch ($type) {
            case 'single':
                return self::calculateSingleOfferRange($products, $minThreshold);
                
            case 'fixed_bundle':
                return self::calculateBundleOfferRange($products, $minThreshold);
                
            case 'customizable_kit':
                return self::calculateKitOfferRange($products, $offer, $minThreshold);
                
            default:
                return ['min' => $minThreshold, 'max' => 0];
        }
    }

    /**
     * Calculate price range for single product offer.
     */
    private static function calculateSingleOfferRange(array $products, float $minThreshold): array
    {
        $product = $products[0] ?? null;
        if (!$product) {
            return ['min' => $minThreshold, 'max' => 0];
        }

        $price = self::getProductFinalPrice($product);
        return ['min' => max($price, $minThreshold), 'max' => $price];
    }

    /**
     * Calculate price range for fixed bundle offer.
     */
    private static function calculateBundleOfferRange(array $products, float $minThreshold): array
    {
        $total = 0;
        foreach ($products as $product) {
            $qty = (int) ($product['qty'] ?? 1);
            $price = self::getProductFinalPrice($product);
            $total += $price * $qty;
        }

        return ['min' => max($total, $minThreshold), 'max' => $total];
    }

    /**
     * Calculate price range for customizable kit offer.
     */
    private static function calculateKitOfferRange(array $products, array $offer, float $minThreshold): array
    {
        $mustHaveTotal = 0;
        $allProductsTotal = 0;

        foreach ($products as $product) {
            $role = $product['role'] ?? 'optional';
            $qty = (int) ($product['qty'] ?? 1);
            $maxQty = (int) ($product['max_qty'] ?? $product['maxQty'] ?? 99);
            $price = self::getProductFinalPrice($product);

            if ($role === 'must' && $qty > 0) {
                // Must-have products contribute to minimum
                $mustHaveTotal += $price * $qty;
            }

            // All products contribute to maximum (at their max qty)
            $allProductsTotal += $price * $maxQty;
        }

        // If no must-haves, use cheapest product as minimum
        if ($mustHaveTotal === 0 && !empty($products)) {
            $prices = array_map(function($p) {
                return self::getProductFinalPrice($p);
            }, $products);
            $mustHaveTotal = min($prices);
        }

        return [
            'min' => max($mustHaveTotal, $minThreshold),
            'max' => $allProductsTotal,
        ];
    }

    /**
     * Get final price for a product from products_data.
     */
    private static function getProductFinalPrice(array $product): float
    {
        // Use salePrice if set (including 0 for FREE items)
        if (isset($product['salePrice']) && $product['salePrice'] !== '' && $product['salePrice'] !== null) {
            return (float) $product['salePrice'];
        }

        // Fall back to WC price
        $sku = $product['sku'] ?? '';
        if (!$sku) {
            return 0;
        }

        $productId = wc_get_product_id_by_sku($sku);
        if (!$productId) {
            return 0;
        }

        $wcProduct = wc_get_product($productId);
        return $wcProduct ? (float) $wcProduct->get_price() : 0;
    }

    /**
     * Detect brand for funnel based on products.
     * If all products share a single brand, use that; otherwise default.
     */
    public static function detectFunnelBrand(int $postId): string
    {
        $settings = self::getSettings();
        $defaultBrand = $settings['default_brand'] ?: 'HolisticPeople';

        // Check if admin has set a custom brand override
        $customBrand = get_post_meta($postId, 'funnel_brand_override', true);
        if (!empty($customBrand)) {
            return $customBrand;
        }

        $offers = get_field('funnel_offers', $postId);
        if (empty($offers)) {
            return $defaultBrand;
        }

        $brands = [];

        foreach ($offers as $offer) {
            $productsJson = $offer['products_data'] ?? '';
            $products = !empty($productsJson) ? json_decode($productsJson, true) : [];

            foreach ($products as $product) {
                $sku = $product['sku'] ?? '';
                if (!$sku) continue;

                $productId = wc_get_product_id_by_sku($sku);
                if (!$productId) continue;

                // Check for brand taxonomy or ACF field
                $productBrand = self::getProductBrand($productId);
                if ($productBrand) {
                    $brands[$productBrand] = true;
                }
            }
        }

        // If all products share one brand, use it
        if (count($brands) === 1) {
            return array_keys($brands)[0];
        }

        return $defaultBrand;
    }

    /**
     * Get brand for a product from taxonomy or ACF.
     */
    private static function getProductBrand(int $productId): string
    {
        // Try product_brand taxonomy (WooCommerce Brands plugin)
        $terms = wp_get_post_terms($productId, 'product_brand', ['fields' => 'names']);
        if (!is_wp_error($terms) && !empty($terms)) {
            return $terms[0];
        }

        // Try ACF brand field
        if (function_exists('get_field')) {
            $brand = get_field('product_brand', $productId);
            if ($brand) {
                return is_array($brand) ? ($brand['name'] ?? '') : (string) $brand;
            }
        }

        return '';
    }

    /**
     * Calculate availability based on all products' stock status.
     */
    public static function calculateFunnelAvailability(int $postId): string
    {
        $offers = get_field('funnel_offers', $postId);
        if (empty($offers)) {
            return 'InStock';
        }

        $allInStock = true;

        foreach ($offers as $offer) {
            $productsJson = $offer['products_data'] ?? '';
            $products = !empty($productsJson) ? json_decode($productsJson, true) : [];

            foreach ($products as $product) {
                $sku = $product['sku'] ?? '';
                if (!$sku) continue;

                $productId = wc_get_product_id_by_sku($sku);
                if (!$productId) continue;

                $wcProduct = wc_get_product($productId);
                if ($wcProduct && !$wcProduct->is_in_stock()) {
                    $allInStock = false;
                    break 2;
                }
            }
        }

        return $allInStock ? 'InStock' : 'OutOfStock';
    }

    /**
     * Force 'hp-funnel' to be treated as an ItemPage in Yoast Schema.
     */
    public static function forceItemPage($type)
    {
        if (is_singular('hp-funnel')) {
            return 'ItemPage';
        }
        return $type;
    }

    /**
     * Add Product schema piece with AggregateOffer for funnels.
     */
    public static function addProductSchemaPiece($pieces, $context)
    {
        if (!is_singular('hp-funnel')) {
            return $pieces;
        }

        // Remove any existing Product schema to avoid duplicates
        $pieces = array_filter($pieces, function($piece) {
            return !($piece instanceof \Yoast\WP\SEO\Generators\Schema\Product);
        });

        // Add our custom Product schema piece
        $pieces[] = new class($context) {
            private $context;

            public function __construct($context) {
                $this->context = $context;
            }

            public function is_needed(): bool {
                return true;
            }

            public function generate(): array {
                return FunnelSeoService::generateProductSchema();
            }
        };

        return $pieces;
    }

    /**
     * Generate Product schema with AggregateOffer for Google Shopping compliance.
     */
    public static function generateProductSchema(): array
    {
        $postId = get_the_ID();
        $settings = self::getSettings();

        // Get stored price range (calculated on save)
        $minPrice = get_post_meta($postId, 'funnel_min_price', true) ?: 0;
        $maxPrice = get_post_meta($postId, 'funnel_max_price', true) ?: $minPrice;
        $brand = get_post_meta($postId, 'funnel_brand', true) ?: $settings['default_brand'];
        $availability = get_post_meta($postId, 'funnel_availability', true) ?: 'InStock';

        // Get offer count
        $offers = get_field('funnel_offers', $postId);
        $offerCount = is_array($offers) ? count($offers) : 1;

        // Get primary SKU from first offer
        $primarySku = '';
        if (!empty($offers[0]['products_data'])) {
            $products = json_decode($offers[0]['products_data'], true);
            $primarySku = $products[0]['sku'] ?? '';
        }

        // Build base schema
        $schema = [
            '@type' => 'Product',
            '@id'   => get_permalink($postId) . '#product',
            'name'  => get_field('hero_title', $postId) ?: get_the_title($postId),
            'description' => wp_strip_all_tags(get_field('hero_subtitle', $postId) ?: get_the_excerpt($postId)),
            'url'   => get_permalink($postId),
            'sku'   => $primarySku,
            'brand' => [
                '@type' => 'Brand',
                'name'  => $brand,
            ],
            'countryOfOrigin' => [
                '@type' => 'Country',
                'name'  => 'US', // Hardcoded per user requirement
            ],
            'offers' => [
                '@type'         => 'AggregateOffer',
                'lowPrice'      => number_format((float) $minPrice, 2, '.', ''),
                'highPrice'     => number_format((float) $maxPrice, 2, '.', ''),
                'priceCurrency' => get_woocommerce_currency(),
                'availability'  => 'https://schema.org/' . $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
                'offerCount'    => $offerCount,
                'url'           => get_permalink($postId),
                'seller'        => [
                    '@type' => 'Organization',
                    'name'  => get_bloginfo('name'),
                ],
            ],
        ];

        // Add image
        $heroImage = get_field('hero_image', $postId);
        if ($heroImage) {
            $schema['image'] = is_array($heroImage) ? ($heroImage['url'] ?? '') : $heroImage;
        } else {
            // Fall back to first product image
            $firstImage = self::getFirstProductImage($postId);
            if ($firstImage) {
                $schema['image'] = $firstImage;
            }
        }

        // Add reviews from testimonials if enabled
        if ($settings['include_testimonials']) {
            $reviews = self::getSchemaReviews($postId);
            if (!empty($reviews)) {
                $schema['review'] = $reviews['items'];
                $schema['aggregateRating'] = $reviews['aggregate'];
            }
        }

        return $schema;
    }

    /**
     * Get first product image from offers.
     */
    private static function getFirstProductImage(int $postId): ?string
    {
        $offers = get_field('funnel_offers', $postId);
        if (empty($offers)) {
            return null;
        }

        foreach ($offers as $offer) {
            // Check offer image first
            if (!empty($offer['offer_image'])) {
                $img = $offer['offer_image'];
                return is_array($img) ? ($img['url'] ?? '') : $img;
            }

            // Then check products
            $productsJson = $offer['products_data'] ?? '';
            $products = !empty($productsJson) ? json_decode($productsJson, true) : [];

            foreach ($products as $product) {
                $sku = $product['sku'] ?? '';
                if (!$sku) continue;

                $productId = wc_get_product_id_by_sku($sku);
                if (!$productId) continue;

                $imageUrl = get_the_post_thumbnail_url($productId, 'large');
                if ($imageUrl) {
                    return $imageUrl;
                }
            }
        }

        return null;
    }

    /**
     * Get schema-ready reviews from testimonials_list repeater.
     */
    private static function getSchemaReviews(int $postId): array
    {
        $testimonials = get_field('testimonials_list', $postId);
        if (empty($testimonials) || !is_array($testimonials)) {
            return [];
        }

        $reviews = [];
        $totalRating = 0;

        foreach ($testimonials as $row) {
            if (empty($row['name']) || empty($row['quote'])) {
                continue;
            }

            $rating = (int) ($row['rating'] ?? 5);
            $totalRating += $rating;

            $reviews[] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => '5',
                ],
                'author' => [
                    '@type' => 'Person',
                    'name'  => $row['name'],
                ],
                'reviewBody' => wp_strip_all_tags($row['quote']),
            ];
        }

        if (empty($reviews)) {
            return [];
        }

        $avgRating = round($totalRating / count($reviews), 1);

        return [
            'items'     => $reviews,
            'aggregate' => [
                '@type'       => 'AggregateRating',
                'ratingValue' => $avgRating,
                'reviewCount' => count($reviews),
                'bestRating'  => '5',
                'worstRating' => '1',
            ],
        ];
    }

    /**
     * Handle OpenGraph image override with fallback chain.
     * Priority: Hero image -> First product image -> Site default
     */
    public static function handleOpenGraphImage($url)
    {
        if (!is_singular('hp-funnel')) {
            return $url;
        }

        $postId = get_the_ID();

        // Priority 1: Hero image
        $heroImage = get_field('hero_image', $postId);
        if ($heroImage) {
            return is_array($heroImage) ? ($heroImage['url'] ?? $url) : $heroImage;
        }

        // Priority 2: First offer image or product image
        $firstImage = self::getFirstProductImage($postId);
        if ($firstImage) {
            return $firstImage;
        }

        // Priority 3: Default (Yoast handles this)
        return $url;
    }

    /**
     * Handle OpenGraph description override.
     */
    public static function handleOpenGraphDesc($desc)
    {
        if (!is_singular('hp-funnel')) {
            return $desc;
        }

        $heroDesc = get_field('hero_subtitle', get_the_ID());
        if ($heroDesc) {
            return wp_strip_all_tags($heroDesc);
        }

        return $desc;
    }

    /**
     * Register 'hp-funnel' post type for FiboSearch indexing.
     */
    public static function registerFiboSearchPostType($postTypes)
    {
        if (!in_array('hp-funnel', $postTypes)) {
            $postTypes[] = 'hp-funnel';
        }
        return $postTypes;
    }

    /**
     * Index SKUs and product names inside the funnel_offers repeater for FiboSearch.
     */
    public static function indexRepeaterSkus($content, $postId, $post)
    {
        if ($post->post_type !== 'hp-funnel') {
            return $content;
        }

        $searchableData = [];

        // Add hero title and subtitle for searchability
        $heroTitle = get_field('hero_title', $postId);
        if ($heroTitle) {
            $searchableData[] = $heroTitle;
        }

        // Get offers from ACF
        $offers = get_field('funnel_offers', $postId);
        if (!empty($offers) && is_array($offers)) {
            foreach ($offers as $offer) {
                // Add offer name
                if (!empty($offer['offer_name'])) {
                    $searchableData[] = $offer['offer_name'];
                }

                // Parse products_data JSON
                $productsJson = $offer['products_data'] ?? '';
                $products = !empty($productsJson) ? json_decode($productsJson, true) : [];

                foreach ($products as $product) {
                    $sku = $product['sku'] ?? '';
                    if ($sku) {
                        $searchableData[] = $sku;

                        // Also add product name
                        $productId = wc_get_product_id_by_sku($sku);
                        if ($productId) {
                            $searchableData[] = get_the_title($productId);
                        }
                    }
                }
            }
        }

        return $content . ' ' . implode(' ', array_filter($searchableData));
    }

    /**
     * Inject analytics data object into page head.
     */
    public static function injectAnalyticsData(): void
    {
        if (!is_singular('hp-funnel')) {
            return;
        }

        $settings = self::getSettings();
        $postId = get_the_ID();

        // Get stored price range
        $minPrice = get_post_meta($postId, 'funnel_min_price', true) ?: 0;
        $brand = get_post_meta($postId, 'funnel_brand', true) ?: $settings['default_brand'];

        // Get primary SKU
        $primarySku = '';
        $offers = get_field('funnel_offers', $postId);
        if (!empty($offers[0]['products_data'])) {
            $products = json_decode($offers[0]['products_data'], true);
            $primarySku = $products[0]['sku'] ?? '';
        }

        $funnelData = [
            'funnelId'   => $postId,
            'funnelName' => get_the_title($postId),
            'currency'   => get_woocommerce_currency(),
            'value'      => (float) $minPrice,
            'brand'      => $brand,
            'items'      => [[
                'item_id'   => $primarySku,
                'item_name' => get_the_title($postId),
                'item_brand' => $brand,
                'price'     => (float) $minPrice,
                'quantity'  => 1,
            ]],
        ];

        ?>
        <script>
        window.hpFunnelData = <?php echo wp_json_encode($funnelData); ?>;
        window.hpFunnelSettings = {
            trackViewItem: <?php echo $settings['track_view_item'] ? 'true' : 'false'; ?>,
            trackAddToCart: <?php echo $settings['track_add_to_cart'] ? 'true' : 'false'; ?>,
            trackBeginCheckout: <?php echo $settings['track_begin_checkout'] ? 'true' : 'false'; ?>,
            trackPurchase: <?php echo $settings['track_purchase'] ? 'true' : 'false'; ?>,
            pushToGtm: <?php echo $settings['push_to_gtm'] ? 'true' : 'false'; ?>,
            pushToGa4: <?php echo $settings['push_to_ga4'] ? 'true' : 'false'; ?>,
            debugMode: <?php echo $settings['console_debug_mode'] ? 'true' : 'false'; ?>,
            buttonSelectors: <?php echo wp_json_encode($settings['custom_button_selectors']); ?>
        };
        </script>
        <?php
    }

    /**
     * Inject Shadow Listener scripts into page footer.
     */
    public static function injectAnalyticsScripts(): void
    {
        // Check if we're on a funnel page or a funnel sub-route
        $isFunnelPage = is_singular('hp-funnel');
        $funnelRoute = get_query_var('hp_funnel_route');
        $funnelSlug = get_query_var('hp_funnel_slug');

        if (!$isFunnelPage && !$funnelRoute) {
            return;
        }

        // Enqueue the external analytics script if it exists
        $analyticsJsPath = HP_RW_PATH . 'assets/js/funnel-analytics.js';
        if (file_exists($analyticsJsPath)) {
            wp_enqueue_script(
                'hp-funnel-analytics',
                HP_RW_URL . 'assets/js/funnel-analytics.js',
                [],
                HP_RW_VERSION,
                true
            );
            return;
        }

        // Inline fallback script
        ?>
        <script>
        (function() {
            'use strict';
            var settings = window.hpFunnelSettings || {};
            var funnelData = window.hpFunnelData || {};

            function pushEvent(eventName, data) {
                // GTM dataLayer
                if (settings.pushToGtm !== false) {
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({ event: eventName, ecommerce: data });
                }
                // GA4 gtag
                if (settings.pushToGa4 !== false && typeof gtag === 'function') {
                    gtag('event', eventName, data);
                }
                // Debug
                if (settings.debugMode) {
                    console.log('HP Funnel Analytics:', eventName, data);
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                if (!funnelData.value) return;

                var path = window.location.pathname;

                // VIEW_ITEM on landing page
                if (settings.trackViewItem !== false && !path.includes('/checkout') && !path.includes('/thank-you')) {
                    pushEvent('view_item', {
                        currency: funnelData.currency,
                        value: funnelData.value,
                        items: funnelData.items
                    });
                }

                // ADD_TO_CART on button click
                if (settings.trackAddToCart !== false) {
                    var selectors = settings.buttonSelectors || '.hp-funnel-cta-btn';
                    document.querySelectorAll(selectors).forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            pushEvent('add_to_cart', {
                                currency: funnelData.currency,
                                value: funnelData.value,
                                items: funnelData.items
                            });
                        });
                    });
                }

                // BEGIN_CHECKOUT on /checkout/ URL
                if (settings.trackBeginCheckout !== false && path.includes('/checkout')) {
                    pushEvent('begin_checkout', {
                        currency: funnelData.currency,
                        value: funnelData.value,
                        items: funnelData.items
                    });
                }

                // PURCHASE on /thank-you/ URL
                if (settings.trackPurchase !== false && (path.includes('/thank-you') || path.includes('/thankyou'))) {
                    var params = new URLSearchParams(window.location.search);
                    var orderId = params.get('order_id') || 'HP-' + Date.now();
                    pushEvent('purchase', {
                        transaction_id: orderId,
                        currency: funnelData.currency,
                        value: funnelData.value,
                        items: funnelData.items
                    });
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Handle Canonical Swaps for Products and Categories.
     */
    public static function handleCanonicalSwaps($canonical)
    {
        $settings = self::getSettings();

        // A. Product Canonical Swap (Type-1 funnels)
        if ($settings['type1_product_swap'] && is_singular('product')) {
            $funnelId = get_field('product_funnel_override', get_the_ID());
            if ($funnelId) {
                return get_permalink($funnelId);
            }
        }

        // B. Category Canonical Swap (Type-2/3 funnels)
        if ($settings['type2_category_swap'] && is_product_category()) {
            $term = get_queried_object();
            if ($term) {
                $funnelId = get_field('category_canonical_funnel', 'product_cat_' . $term->term_id);
                if ($funnelId) {
                    return get_permalink($funnelId);
                }
            }
        }

        return $canonical;
    }

    /**
     * Add 'Funnel SEO' column to WooCommerce products list.
     */
    public static function addProductColumns($columns)
    {
        $columns['hp_funnel_canonical'] = 'Funnel SEO';
        return $columns;
    }

    /**
     * Render the content for 'Funnel SEO' column.
     */
    public static function renderProductColumn($column, $postId)
    {
        if ('hp_funnel_canonical' !== $column) {
            return;
        }

        $funnelId = get_field('product_funnel_override', $postId);
        if ($funnelId) {
            $funnelTitle = get_the_title($funnelId);
            $editLink = get_edit_post_link($funnelId);
            echo '<div style="background:#d1fae5; color:#065f46; padding:2px 6px; border-radius:4px; font-size:11px; font-weight:600; display:inline-block;">Active</div>';
            echo '<br><a href="' . esc_url($editLink) . '" style="font-size:11px;" title="' . esc_attr($funnelTitle) . '">→ ' . esc_html(wp_trim_words($funnelTitle, 3)) . '</a>';
        } else {
            echo '<span style="color:#ccc;">—</span>';
        }
    }

    /**
     * Get price range display string for a funnel.
     */
    public static function getPriceRangeDisplay(int $postId): string
    {
        $minPrice = (float) get_post_meta($postId, 'funnel_min_price', true);
        $maxPrice = (float) get_post_meta($postId, 'funnel_max_price', true);

        if ($minPrice <= 0) {
            return '';
        }

        $settings = self::getSettings();
        $format = $settings['price_display_format'];
        $currency = get_woocommerce_currency_symbol();

        if ($maxPrice <= $minPrice || $format === 'starting_at') {
            return sprintf('Starting at %s%.2f', $currency, $minPrice);
        }

        if ($format === 'from') {
            return sprintf('From %s%.2f', $currency, $minPrice);
        }

        // Default: range
        return sprintf('%s%.2f – %s%.2f', $currency, $minPrice, $currency, $maxPrice);
    }
}
