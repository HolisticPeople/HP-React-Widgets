<?php
namespace HP_RW\Services;

use HP_RW\Plugin;
use HP_RW\Util\Resolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for loading and caching funnel configurations from the CPT.
 * 
 * Provides a consistent interface to retrieve funnel config regardless of
 * whether it's stored in CPT/ACF or legacy options.
 */
class FunnelConfigLoader
{
    private const CACHE_PREFIX = 'hp_rw_funnel_config_';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    /**
     * Get funnel config by slug or ID.
     *
     * @param string|int $slugOrId Funnel slug or post ID
     * @return array|null Normalized config array or null if not found
     */
    public static function get($slugOrId): ?array
    {
        if (is_numeric($slugOrId)) {
            return self::getById((int) $slugOrId);
        }
        return self::getBySlug((string) $slugOrId);
    }

    /**
     * Get funnel config by slug.
     *
     * @param string $slug Funnel slug
     * @return array|null Normalized config array or null if not found
     */
    public static function getBySlug(string $slug): ?array
    {
        if (empty($slug)) {
            return null;
        }

        // Check cache first
        $cacheKey = self::CACHE_PREFIX . 'slug_' . $slug;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Find the funnel post
        $post = self::findPostBySlug($slug);
        if (!$post) {
            // Try legacy options as fallback
            return self::getLegacyConfig($slug);
        }

        $config = self::loadFromPost($post);
        
        // Cache the result
        set_transient($cacheKey, $config, self::CACHE_TTL);
        
        return $config;
    }

    /**
     * Get funnel config by post ID.
     *
     * @param int $postId Post ID
     * @return array|null Normalized config array or null if not found
     */
    public static function getById(int $postId): ?array
    {
        if ($postId <= 0) {
            return null;
        }

        // Check cache first
        $cacheKey = self::CACHE_PREFIX . 'id_' . $postId;
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $post = get_post($postId);
        if (!$post || $post->post_type !== Plugin::FUNNEL_POST_TYPE) {
            return null;
        }

        $config = self::loadFromPost($post);
        
        // Cache the result
        set_transient($cacheKey, $config, self::CACHE_TTL);
        
        return $config;
    }

    /**
     * Clear cache for a specific funnel.
     *
     * @param int $postId Post ID
     */
    public static function clearCache(int $postId): void
    {
        // Clear by ID
        delete_transient(self::CACHE_PREFIX . 'id_' . $postId);
        
        // Clear by slug
        $slug = get_field('funnel_slug', $postId);
        if ($slug) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $slug);
        }
        
        // Also clear by post_name
        $post = get_post($postId);
        if ($post) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $post->post_name);
        }
    }

    /**
     * Find a funnel post by slug.
     *
     * @param string $slug Funnel slug
     * @return \WP_Post|null
     */
    public static function findPostBySlug(string $slug): ?\WP_Post
    {
        // First try ACF field
        $posts = get_posts([
            'post_type'      => Plugin::FUNNEL_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'funnel_slug',
                    'value' => $slug,
                ],
            ],
        ]);

        if (!empty($posts)) {
            return $posts[0];
        }

        // Fallback to post_name (WP slug)
        $post = get_page_by_path($slug, OBJECT, Plugin::FUNNEL_POST_TYPE);
        
        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * Get all published funnel posts.
     *
     * @return array Array of WP_Post objects
     */
    public static function getAllPosts(): array
    {
        return get_posts([
            'post_type'      => Plugin::FUNNEL_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }

    /**
     * Load and normalize config from a funnel post.
     *
     * @param \WP_Post $post Funnel post
     * @return array Normalized config
     */
    private static function loadFromPost(\WP_Post $post): array
    {
        $postId = $post->ID;

        // Check status
        $status = get_field('funnel_status', $postId) ?: 'active';
        if ($status === 'inactive') {
            return ['status' => 'inactive', 'active' => false];
        }

        // Build normalized config
        $config = [
            'id'          => $postId,
            'status'      => $status,
            'active'      => true,
            'name'        => $post->post_title,
            'slug'        => get_field('funnel_slug', $postId) ?: $post->post_name,
            'stripe_mode' => get_field('stripe_mode', $postId) ?: 'auto',
            
            // Hero section
            'hero' => [
                'title'         => get_field('hero_title', $postId) ?: '',
                'subtitle'      => get_field('hero_subtitle', $postId) ?: '',
                'tagline'       => get_field('hero_tagline', $postId) ?: '',
                'description'   => get_field('hero_description', $postId) ?: '',
                'image'         => get_field('hero_image', $postId) ?: '',
                'logo'          => get_field('hero_logo', $postId) ?: '',
                'logo_link'     => get_field('hero_logo_link', $postId) ?: home_url('/'),
                'cta_text'      => get_field('hero_cta_text', $postId) ?: 'Get Your Special Offer Now',
                'benefits_title' => get_field('hero_benefits_title', $postId) ?: 'Why Choose Us?',
                'benefits'      => self::extractBenefits(get_field('hero_benefits', $postId) ?: []),
            ],
            
            // Products
            'products' => self::extractProducts(get_field('funnel_products', $postId) ?: []),
            
            // Checkout
            'checkout' => [
                'url'                    => get_field('checkout_url', $postId) ?: '/checkout/',
                'free_shipping_countries' => get_field('free_shipping_countries', $postId) ?: ['US'],
                'global_discount_percent' => (float) (get_field('global_discount_percent', $postId) ?: 0),
                'enable_points'          => (bool) get_field('enable_points_redemption', $postId),
                'show_order_summary'     => (bool) (get_field('show_order_summary', $postId) ?? true),
            ],
            
            // Thank you page
            'thankyou' => [
                'url'       => get_field('thankyou_url', $postId) ?: '/thank-you/',
                'headline'  => get_field('thankyou_headline', $postId) ?: 'Thank You for Your Order!',
                'message'   => get_field('thankyou_message', $postId) ?: '',
                'show_upsell' => (bool) get_field('show_upsell', $postId),
                'upsell'    => self::extractUpsellConfig(get_field('upsell_config', $postId) ?: []),
            ],
            
            // Styling
            'styling' => [
                'accent_color'     => get_field('accent_color', $postId) ?: '#eab308',
                'background_type'  => get_field('background_type', $postId) ?: 'gradient',
                'background_color' => get_field('background_color', $postId) ?: '',
                'background_image' => get_field('background_image', $postId) ?: '',
                'custom_css'       => get_field('custom_css', $postId) ?: '',
            ],
            
            // Footer
            'footer' => [
                'text'       => get_field('footer_text', $postId) ?: '',
                'disclaimer' => get_field('footer_disclaimer', $postId) ?: '',
            ],
        ];

        return $config;
    }

    /**
     * Extract benefits array from ACF repeater.
     *
     * @param array $benefits ACF repeater data
     * @return array Simple array of benefit strings
     */
    private static function extractBenefits(array $benefits): array
    {
        $result = [];
        foreach ($benefits as $row) {
            if (isset($row['text']) && !empty($row['text'])) {
                $result[] = (string) $row['text'];
            }
        }
        return $result;
    }

    /**
     * Extract and enrich products array from ACF repeater.
     *
     * @param array $products ACF repeater data
     * @return array Enriched product data
     */
    private static function extractProducts(array $products): array
    {
        $result = [];
        
        foreach ($products as $row) {
            if (empty($row['sku'])) {
                continue;
            }

            $sku = (string) $row['sku'];
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
            $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];

            // Extract features
            $features = [];
            if (!empty($row['features']) && is_array($row['features'])) {
                foreach ($row['features'] as $feature) {
                    if (isset($feature['text']) && !empty($feature['text'])) {
                        $features[] = (string) $feature['text'];
                    }
                }
            }

            $result[] = [
                'id'           => $sku,
                'sku'          => $sku,
                'name'         => !empty($row['display_name']) ? (string) $row['display_name'] : ($wcData['name'] ?? $sku),
                'description'  => $row['description'] ?? '',
                'price'        => !empty($row['display_price']) ? (float) $row['display_price'] : ($wcData['price'] ?? 0),
                'regularPrice' => $wcData['regular_price'] ?? null,
                'image'        => self::resolveImageUrl($row['image'] ?? null, $wcData['image'] ?? ''),
                'badge'        => $row['badge'] ?? '',
                'features'     => $features,
                'isBestValue'  => !empty($row['is_best_value']),
                'freeItem'     => [
                    'sku' => $row['free_item_sku'] ?? '',
                    'qty' => (int) ($row['free_item_qty'] ?? 1),
                ],
            ];
        }

        return $result;
    }

    /**
     * Extract upsell configuration from ACF group.
     *
     * @param array $config ACF group data
     * @return array|null Upsell config or null
     */
    private static function extractUpsellConfig(array $config): ?array
    {
        if (empty($config['sku'])) {
            return null;
        }

        $sku = (string) $config['sku'];
        $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
        $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];

        $discountPercent = (float) ($config['discount_percent'] ?? 0);
        $basePrice = $wcData['price'] ?? 0;
        $finalPrice = $discountPercent > 0 ? $basePrice * (1 - $discountPercent / 100) : $basePrice;

        return [
            'sku'         => $sku,
            'qty'         => (int) ($config['qty'] ?? 1),
            'discount'    => $discountPercent,
            'price'       => round($finalPrice, 2),
            'headline'    => $config['headline'] ?? 'Wait! Special Offer Just For You!',
            'description' => $config['description'] ?? '',
            'image'       => self::resolveImageUrl($config['image'] ?? null, $wcData['image'] ?? ''),
            'productName' => $wcData['name'] ?? $sku,
        ];
    }

    /**
     * Resolve an image field value to a URL.
     * Handles both image IDs (from ACF) and direct URLs.
     *
     * @param mixed  $value    ACF image value (ID, URL, or array)
     * @param string $fallback Fallback URL
     * @return string Image URL
     */
    private static function resolveImageUrl($value, string $fallback = ''): string
    {
        if (empty($value)) {
            return $fallback;
        }

        // If it's already a URL
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // If it's an array (ACF returns array when return_format is 'array')
        if (is_array($value) && isset($value['url'])) {
            return (string) $value['url'];
        }

        // If it's an ID
        if (is_numeric($value)) {
            $imageData = wp_get_attachment_image_src((int) $value, 'large');
            if ($imageData && isset($imageData[0])) {
                return $imageData[0];
            }
        }

        return $fallback;
    }

    /**
     * Try to load config from legacy options (for backwards compatibility).
     *
     * @param string $slug Funnel slug
     * @return array|null Legacy config or null
     */
    private static function getLegacyConfig(string $slug): ?array
    {
        // Check main settings
        $opts = get_option('hp_rw_settings', []);
        if (!empty($opts['funnel_configs'][$slug])) {
            return self::normalizeLegacyConfig($opts['funnel_configs'][$slug], $slug);
        }

        // Check separate option
        $funnelOpts = get_option('hp_rw_funnel_' . $slug, []);
        if (!empty($funnelOpts)) {
            return self::normalizeLegacyConfig($funnelOpts, $slug);
        }

        return null;
    }

    /**
     * Normalize legacy config format to new structure.
     *
     * @param array  $legacy Legacy config data
     * @param string $slug   Funnel slug
     * @return array Normalized config
     */
    private static function normalizeLegacyConfig(array $legacy, string $slug): array
    {
        return [
            'id'          => 0,
            'status'      => 'active',
            'active'      => true,
            'name'        => $legacy['name'] ?? ucfirst($slug),
            'slug'        => $slug,
            'stripe_mode' => 'auto',
            
            'hero' => [
                'title'         => $legacy['hero_title'] ?? ($legacy['title'] ?? ''),
                'subtitle'      => $legacy['hero_subtitle'] ?? ($legacy['subtitle'] ?? ''),
                'tagline'       => $legacy['hero_tagline'] ?? ($legacy['tagline'] ?? ''),
                'description'   => $legacy['hero_description'] ?? ($legacy['description'] ?? ''),
                'image'         => $legacy['hero_image'] ?? '',
                'logo'          => $legacy['logo_url'] ?? '',
                'logo_link'     => $legacy['logo_link'] ?? home_url('/'),
                'cta_text'      => $legacy['cta_text'] ?? 'Get Your Special Offer Now',
                'benefits_title' => $legacy['benefits_title'] ?? 'Why Choose Us?',
                'benefits'      => $legacy['benefits'] ?? [],
            ],
            
            'products' => $legacy['products'] ?? [],
            
            'checkout' => [
                'url'                    => $legacy['checkout_url'] ?? '/checkout/',
                'free_shipping_countries' => $legacy['free_shipping_countries'] ?? ['US'],
                'global_discount_percent' => (float) ($legacy['global_discount_percent'] ?? 0),
                'enable_points'          => true,
                'show_order_summary'     => true,
            ],
            
            'thankyou' => [
                'url'       => $legacy['thankyou_url'] ?? '/thank-you/',
                'headline'  => $legacy['thankyou_headline'] ?? 'Thank You for Your Order!',
                'message'   => $legacy['thankyou_subheadline'] ?? '',
                'show_upsell' => !empty($legacy['upsell_offers']),
                'upsell'    => null,
            ],
            
            'styling' => [
                'accent_color'     => $legacy['payment_style']['accent_color'] ?? '#eab308',
                'background_type'  => 'gradient',
                'background_color' => $legacy['payment_style']['background_color'] ?? '',
                'background_image' => '',
                'custom_css'       => '',
            ],
            
            'footer' => [
                'text'       => $legacy['footer_text'] ?? '',
                'disclaimer' => $legacy['footer_disclaimer'] ?? '',
            ],
        ];
    }

    /**
     * Get all active funnels.
     *
     * @return array Array of normalized configs
     */
    public static function getAllActive(): array
    {
        $funnels = self::getAllPosts();
        $result = [];

        foreach ($funnels as $post) {
            $config = self::loadFromPost($post);
            if ($config['active']) {
                $result[] = $config;
            }
        }

        return $result;
    }
}
