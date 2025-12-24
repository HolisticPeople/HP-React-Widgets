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
     * Get funnel config from current post context.
     * 
     * This method auto-detects the current funnel when used within an Elementor
     * template or single funnel page, similar to how WooCommerce product shortcodes
     * work on single product templates.
     *
     * @return array|null Normalized config array or null if not in funnel context
     */
    public static function getFromContext(): ?array
    {
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        
        // Method 0: Check query var set by funnel sub-routes (checkout, thankyou, etc.)
        // This is set by Plugin::handleFunnelSubRoutes() for virtual route pages
        $queryVarFunnel = get_query_var('hp_current_funnel');
        if (!empty($queryVarFunnel) && is_array($queryVarFunnel)) {
            if ($debug) {
                error_log('[HP-RW] getFromContext: Found via hp_current_funnel query var - ID: ' . ($queryVarFunnel['id'] ?? 'unknown'));
            }
            return $queryVarFunnel;
        }
        
        // Method 0b: Check query var for funnel slug (set by rewrite rules)
        $queryVarSlug = get_query_var('hp_funnel_slug');
        if (!empty($queryVarSlug)) {
            if ($debug) {
                error_log('[HP-RW] getFromContext: Found hp_funnel_slug query var: ' . $queryVarSlug);
            }
            return self::getBySlug($queryVarSlug);
        }
        
        // Method 1: Check get_queried_object() first - most reliable for single post views
        $queried = get_queried_object();
        if ($queried instanceof \WP_Post && $queried->post_type === Plugin::FUNNEL_POST_TYPE) {
            if ($debug) {
                error_log('[HP-RW] getFromContext: Found via get_queried_object() - ID: ' . $queried->ID);
            }
            return self::getById($queried->ID);
        }
        
        // Method 2: Check global $post
        global $post;
        if ($post instanceof \WP_Post && $post->post_type === Plugin::FUNNEL_POST_TYPE) {
            if ($debug) {
                error_log('[HP-RW] getFromContext: Found via global $post - ID: ' . $post->ID);
            }
            return self::getById($post->ID);
        }
        
        // Method 3: Try Elementor's document system (for theme builder templates)
        if (class_exists('\\Elementor\\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            
            // Check if Elementor Pro is active and has the documents manager
            if (isset($elementor->documents) && method_exists($elementor->documents, 'get_current')) {
                $document = $elementor->documents->get_current();
                if ($document) {
                    // Get the post ID that the template is rendering for
                    $postId = $document->get_main_id();
                    $templatePost = get_post($postId);
                    
                    if ($debug) {
                        error_log('[HP-RW] getFromContext: Elementor document main_id: ' . $postId . ', type: ' . ($templatePost ? $templatePost->post_type : 'null'));
                    }
                    
                    // If the document is a template, we need to find the actual post being rendered
                    if ($templatePost && $templatePost->post_type === 'elementor_library') {
                        // Try get_the_ID() which Elementor usually sets correctly during render
                        $renderedPostId = get_the_ID();
                        if ($debug) {
                            error_log('[HP-RW] getFromContext: get_the_ID() returned: ' . $renderedPostId);
                        }
                        if ($renderedPostId && $renderedPostId !== $postId) {
                            $renderedPost = get_post($renderedPostId);
                            if ($renderedPost && $renderedPost->post_type === Plugin::FUNNEL_POST_TYPE) {
                                if ($debug) {
                                    error_log('[HP-RW] getFromContext: Found via Elementor get_the_ID() - ID: ' . $renderedPostId);
                                }
                                return self::getById($renderedPostId);
                            }
                        }
                    }
                }
            }
        }
        
        // Method 4: Parse URL to extract funnel slug as last resort
        // This handles cases where WordPress query hasn't fully set up the post context
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($debug) {
            error_log('[HP-RW] getFromContext: Trying URL parse, REQUEST_URI: ' . $requestUri);
        }
        if (preg_match('#/express-shop/([^/]+)/?#', $requestUri, $matches)) {
            $slug = sanitize_title($matches[1]);
            if ($debug) {
                error_log('[HP-RW] getFromContext: Extracted slug from URL: ' . $slug);
            }
            $funnelPost = self::findPostBySlug($slug);
            if ($funnelPost) {
                if ($debug) {
                    error_log('[HP-RW] getFromContext: Found via URL parse - ID: ' . $funnelPost->ID);
                }
                return self::getById($funnelPost->ID);
            }
        }
        
        if ($debug) {
            error_log('[HP-RW] getFromContext: No funnel found. queried_object type: ' . (is_object($queried) ? get_class($queried) : gettype($queried)) . ', global $post type: ' . ($post ? $post->post_type : 'null'));
        }
        
        return null;
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
     * @param int    $postId  Post ID
     * @param string $oldSlug Optional old slug to clear (for when slug changes)
     */
    public static function clearCache(int $postId, string $oldSlug = ''): void
    {
        // Clear by ID
        delete_transient(self::CACHE_PREFIX . 'id_' . $postId);
        
        // Clear by funnel_slug (single source of truth)
        $currentSlug = function_exists('get_field') ? get_field('funnel_slug', $postId) : null;
        if (!$currentSlug) {
            $currentSlug = get_post_meta($postId, 'funnel_slug', true);
        }
        if ($currentSlug) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $currentSlug);
        }
        
        // If old slug provided (slug was changed), clear that cache too
        if ($oldSlug && $oldSlug !== $currentSlug) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $oldSlug);
        }
    }

    /**
     * Find a funnel post by slug.
     * 
     * Uses the ACF funnel_slug field as the SINGLE SOURCE OF TRUTH.
     * No fallbacks - the funnel_slug field is definitive.
     *
     * @param string $slug Funnel slug
     * @return \WP_Post|null
     */
    public static function findPostBySlug(string $slug): ?\WP_Post
    {
        // Primary: Query by funnel_slug ACF field - this is the single source of truth
        $posts = get_posts([
            'post_type'      => Plugin::FUNNEL_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'private'],
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
        
        // Fallback: Check post_name for transition period
        // This handles funnels where post_name hasn't been synced to funnel_slug yet
        $posts = get_posts([
            'post_type'      => Plugin::FUNNEL_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'name'           => $slug,
        ]);

        if (!empty($posts)) {
            // Log this so we know syncing is needed
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[HP-RW] findPostBySlug: Found via post_name fallback for '{$slug}'. Consider re-saving funnel to sync slugs.");
            }
            return $posts[0];
        }
        
        return null;
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
        $status = self::getFieldValue('funnel_status', $postId, 'active');
        if ($status === 'inactive') {
            return ['status' => 'inactive', 'active' => false];
        }

        // Get the funnel_slug from ACF - this is the SINGLE SOURCE OF TRUTH for all URLs
        // If not set, derive from post title (this will be auto-saved on next post save)
        $funnelSlug = self::getFieldValue('funnel_slug', $postId, '');
        if (empty($funnelSlug)) {
            $funnelSlug = sanitize_title($post->post_title);
        }

        // Build normalized config
        $config = [
            'id'          => $postId,
            'status'      => $status,
            'active'      => true,
            'name'        => $post->post_title,
            'slug'        => $funnelSlug,
            'stripe_mode' => self::getFieldValue('stripe_mode', $postId, 'auto'),
            
            // Header section
            'header' => [
                'logo'        => self::getFieldValue('header_logo', $postId, ''),
                'logo_link'   => self::getFieldValue('header_logo_link', $postId, home_url('/')),
                'nav_items'   => self::extractNavItems(self::getFieldValue('header_nav_items', $postId, [])),
                'sticky'      => (bool) self::getFieldValue('header_sticky', $postId, false),
                'transparent' => (bool) self::getFieldValue('header_transparent', $postId, false),
            ],
            
            // Hero section
            'hero' => [
                'title'            => self::getFieldValue('hero_title', $postId, ''),
                'title_size'       => self::getFieldValue('hero_title_size', $postId, 'xl'), // Default to xl (largest)
                'subtitle'         => self::getFieldValue('hero_subtitle', $postId, ''),
                'tagline'          => self::getFieldValue('hero_tagline', $postId, ''),
                'description'      => self::getFieldValue('hero_description', $postId, ''),
                'image'            => self::resolveImageUrl(self::getFieldValue('hero_image', $postId, '')),
                'logo'             => self::resolveImageUrl(self::getFieldValue('hero_logo', $postId, '')),
                'logo_link'        => self::getFieldValue('hero_logo_link', $postId, home_url('/')),
                'cta_text'         => self::getFieldValue('hero_cta_text', $postId, 'Get Your Special Offer Now'),
            ],
            
            // Benefits section
            'benefits' => [
                'title'    => self::getFieldValue('hero_benefits_title', $postId, 'Why Choose Us?'),
                'subtitle' => self::getFieldValue('hero_benefits_subtitle', $postId, ''),
                'items'    => self::extractBenefitsWithIcons(self::getFieldValue('hero_benefits', $postId, [])),
            ],
            
            // Offers (replaces legacy products)
            'offers' => self::extractOffers(self::getFieldValue('funnel_offers', $postId, [])),
            
            // Checkout
            // Auto-generate checkout URL based on funnel_slug (single source of truth)
            // Pattern: /express-shop/{funnel_slug}/checkout/
            'checkout' => [
                'url'                    => '/express-shop/' . $funnelSlug . '/checkout/',
                'back_url'               => '/express-shop/' . $funnelSlug . '/',
                'free_shipping_countries' => self::getFieldValue('free_shipping_countries', $postId, ['US']),
                'global_discount_percent' => (float) self::getFieldValue('global_discount_percent', $postId, 0),
                'enable_points'          => (bool) self::getFieldValue('enable_points_redemption', $postId, false),
                'show_order_summary'     => (bool) self::getFieldValue('show_order_summary', $postId, true),
            ],
            
            // Thank you page
            'thankyou' => [
                'url'       => self::getFieldValue('thankyou_url', $postId, '/thank-you/'),
                'headline'  => self::getFieldValue('thankyou_headline', $postId, 'Thank You for Your Order!'),
                'message'   => self::getFieldValue('thankyou_message', $postId, ''),
                'show_upsell' => (bool) self::getFieldValue('show_upsell', $postId, false),
                'upsell'    => self::extractUpsellConfig(self::getFieldValue('upsell_config', $postId, [])),
            ],
            
            // Styling - consolidated colors
            'styling' => self::extractStyling($postId),
            
            // Footer
            'footer' => [
                'text'       => self::getFieldValue('footer_text', $postId, ''),
                'disclaimer' => self::getFieldValue('footer_disclaimer', $postId, ''),
                'links'      => self::extractFooterLinks(self::getFieldValue('footer_links', $postId, [])),
            ],
            
            // Features section
            'features' => [
                'title'    => self::getFieldValue('features_title', $postId, 'Key Features'),
                'subtitle' => self::getFieldValue('features_subtitle', $postId, ''),
                'items'    => self::extractFeatures(self::getFieldValue('features_list', $postId, [])),
            ],
            
            // Authority section
            'authority' => [
                'title'           => self::getFieldValue('authority_title', $postId, 'Who We Are'),
                'subtitle'        => self::getFieldValue('authority_subtitle', $postId, ''),
                'name'            => self::getFieldValue('authority_name', $postId, ''),
                'credentials'     => self::getFieldValue('authority_credentials', $postId, ''),
                'image'           => self::resolveImageUrl(self::getFieldValue('authority_image', $postId, '')),
                'bio'             => self::getFieldValue('authority_bio', $postId, ''),
                'quotes'          => self::extractQuotes(self::getFieldValue('authority_quotes', $postId, [])),
                'quoteCategories' => self::extractQuoteCategories(self::getFieldValue('authority_quote_categories', $postId, [])),
                'articleLink'     => self::extractArticleLink($postId),
            ],
            
            // Testimonials section
            'testimonials' => [
                'title'    => self::getFieldValue('testimonials_title', $postId, 'What Our Customers Say'),
                'subtitle' => self::getFieldValue('testimonials_subtitle', $postId, ''),
                'items'    => self::extractTestimonials(self::getFieldValue('testimonials_list', $postId, [])),
            ],
            
            // FAQ section
            'faq' => [
                'title' => self::getFieldValue('faq_title', $postId, 'Frequently Asked Questions'),
                'items' => self::extractFaqItems(self::getFieldValue('faq_list', $postId, [])),
            ],
            
            // CTA section
            'cta' => [
                'title'      => self::getFieldValue('cta_title', $postId, 'Ready to Get Started?'),
                'subtitle'   => self::getFieldValue('cta_subtitle', $postId, ''),
                'buttonText' => self::getFieldValue('cta_button_text', $postId, 'Order Now'),
                'buttonUrl'  => self::getFieldValue('cta_button_url', $postId, ''),
            ],
            
            // Science section
            'science' => [
                'title'    => self::getFieldValue('science_title', $postId, 'The Science Behind Our Product'),
                'subtitle' => self::getFieldValue('science_subtitle', $postId, ''),
                'sections' => self::extractScienceSections(self::getFieldValue('science_sections', $postId, [])),
            ],
        ];

        return $config;
    }

    /**
     * Extract styling configuration with accent override logic.
     * If text_color_accent_override is checked, use custom text_color_accent,
     * otherwise use the global accent_color.
     *
     * @param int $postId Post ID
     * @return array Styling configuration
     */
    private static function extractStyling(int $postId): array
    {
        $accentColor = self::getFieldValue('accent_color', $postId, '#eab308');
        $accentOverride = (bool) self::getFieldValue('text_color_accent_override', $postId, false);
        $customTextAccent = self::getFieldValue('text_color_accent', $postId, '');
        
        // Use custom text accent if override is checked AND a value is set
        $textAccent = ($accentOverride && !empty($customTextAccent)) ? $customTextAccent : $accentColor;
        
        return [
            // Primary accent color (used for UI accents, buttons, etc.)
            'accent_color'        => $accentColor,
            // Text colors
            'text_color_basic'    => self::getFieldValue('text_color_basic', $postId, '#e5e5e5'),
            'text_color_accent'   => $textAccent, // Inherits from accent_color unless overridden
            'text_color_note'     => self::getFieldValue('text_color_note', $postId, '#a3a3a3'),
            'text_color_discount' => self::getFieldValue('text_color_discount', $postId, '#22c55e'),
            // UI Element colors
            'page_bg_color'       => self::getFieldValue('page_bg_color', $postId, '#121212'),
            'card_bg_color'       => self::getFieldValue('card_bg_color', $postId, '#1a1a1a'),
            'input_bg_color'      => self::getFieldValue('input_bg_color', $postId, '#333333'),
            'border_color'        => self::getFieldValue('border_color', $postId, '#7c3aed'),
            // Background type settings (gradient/solid/image)
            'background_type'     => self::getFieldValue('background_type', $postId, 'gradient'),
            'background_image'    => self::getFieldValue('background_image', $postId, ''),
            'custom_css'          => self::getFieldValue('custom_css', $postId, ''),
        ];
    }

    /**
     * Extract navigation items from ACF repeater.
     *
     * @param array $navItems ACF repeater data
     * @return array Array of nav item objects
     */
    private static function extractNavItems(array $navItems): array
    {
        $result = [];
        foreach ($navItems as $row) {
            if (isset($row['label']) && !empty($row['label'])) {
                $result[] = [
                    'label'      => (string) $row['label'],
                    'url'        => (string) ($row['url'] ?? '#'),
                    'isExternal' => !empty($row['is_external']),
                ];
            }
        }
        return $result;
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
     * Extract benefits with icons from ACF repeater.
     *
     * @param array $benefits ACF repeater data
     * @return array Array of benefit objects with text and icon
     */
    private static function extractBenefitsWithIcons(array $benefits): array
    {
        $result = [];
        foreach ($benefits as $row) {
            if (isset($row['text']) && !empty($row['text'])) {
                $result[] = [
                    'text' => (string) $row['text'],
                    'icon' => $row['icon'] ?? 'check',
                ];
            }
        }
        return $result;
    }

    /**
     * Extract features from ACF repeater.
     *
     * @param array $features ACF repeater data
     * @return array Array of feature objects
     */
    private static function extractFeatures(array $features): array
    {
        $result = [];
        foreach ($features as $row) {
            if (!empty($row['title'])) {
                $result[] = [
                    'icon'        => $row['icon'] ?? 'check',
                    'title'       => (string) $row['title'],
                    'description' => $row['description'] ?? '',
                ];
            }
        }
        return $result;
    }

    /**
     * Extract quotes from ACF repeater.
     *
     * @param array $quotes ACF repeater data
     * @return array Array of quote objects
     */
    private static function extractQuotes(array $quotes): array
    {
        $result = [];
        foreach ($quotes as $row) {
            if (!empty($row['text'])) {
                $result[] = ['text' => (string) $row['text']];
            }
        }
        return $result;
    }

    /**
     * Extract quote categories from ACF repeater.
     *
     * @param array $categories ACF repeater data
     * @return array Array of quote category objects
     */
    private static function extractQuoteCategories(array $categories): array
    {
        $result = [];
        foreach ($categories as $row) {
            if (!empty($row['title'])) {
                // Quotes can be stored as newline-separated text or array
                $quotesRaw = $row['quotes'] ?? '';
                $quotes = is_string($quotesRaw) 
                    ? array_filter(array_map('trim', explode("\n", $quotesRaw)))
                    : (array) $quotesRaw;
                
                $result[] = [
                    'title'  => (string) $row['title'],
                    'quotes' => $quotes,
                ];
            }
        }
        return $result;
    }

    /**
     * Extract article link from ACF fields.
     *
     * @param int $postId Post ID
     * @return array|null Article link object or null
     */
    private static function extractArticleLink(int $postId): ?array
    {
        $text = self::getFieldValue('authority_article_text', $postId, '');
        $url = self::getFieldValue('authority_article_url', $postId, '');
        
        if ($text && $url) {
            return [
                'text' => (string) $text,
                'url'  => (string) $url,
            ];
        }
        return null;
    }

    /**
     * Extract testimonials from ACF repeater.
     *
     * @param array $testimonials ACF repeater data
     * @return array Array of testimonial objects
     */
    private static function extractTestimonials(array $testimonials): array
    {
        $result = [];
        foreach ($testimonials as $row) {
            if (!empty($row['name']) && !empty($row['quote'])) {
                $result[] = [
                    'name'   => (string) $row['name'],
                    'role'   => $row['role'] ?? '',
                    'title'  => $row['title'] ?? '',
                    'quote'  => (string) $row['quote'],
                    'image'  => self::resolveImageUrl($row['image'] ?? null),
                    'rating' => (int) ($row['rating'] ?? 5),
                ];
            }
        }
        return $result;
    }

    /**
     * Extract FAQ items from ACF repeater.
     *
     * @param array $faqs ACF repeater data
     * @return array Array of FAQ objects
     */
    private static function extractFaqItems(array $faqs): array
    {
        $result = [];
        foreach ($faqs as $row) {
            if (!empty($row['question']) && !empty($row['answer'])) {
                $result[] = [
                    'question' => (string) $row['question'],
                    'answer'   => (string) $row['answer'],
                ];
            }
        }
        return $result;
    }

    /**
     * Extract science sections from ACF repeater.
     *
     * @param array $sections ACF repeater data
     * @return array Array of science section objects
     */
    private static function extractScienceSections(array $sections): array
    {
        $result = [];
        foreach ($sections as $row) {
            if (!empty($row['title'])) {
                // Bullets can be stored as newline-separated text or array
                $bulletsRaw = $row['bullets'] ?? '';
                $bullets = is_string($bulletsRaw) 
                    ? array_filter(array_map('trim', explode("\n", $bulletsRaw)))
                    : (array) $bulletsRaw;
                
                $result[] = [
                    'title'       => (string) $row['title'],
                    'description' => $row['description'] ?? '',
                    'bullets'     => $bullets,
                ];
            }
        }
        return $result;
    }

    /**
     * Extract footer links from ACF repeater.
     *
     * @param array $links ACF repeater data
     * @return array Array of link objects
     */
    private static function extractFooterLinks(array $links): array
    {
        $result = [];
        foreach ($links as $row) {
            if (!empty($row['label']) && !empty($row['url'])) {
                $result[] = [
                    'label' => (string) $row['label'],
                    'url'   => (string) $row['url'],
                ];
            }
        }
        return $result;
    }

    /**
     * Extract and enrich offers array from ACF repeater.
     *
     * @param array $offers ACF repeater data
     * @return array Enriched offer data with WooCommerce product info
     */
    private static function extractOffers(array $offers): array
    {
        $result = [];
        $offerIndex = 0;
        
        foreach ($offers as $row) {
            $offerType = $row['offer_type'] ?? 'single';
            $offerId = $row['offer_id'] ?? ('offer-' . ++$offerIndex);
            
            // Base offer data
            $offer = [
                'id'            => $offerId,
                'name'          => $row['offer_name'] ?? '',
                'description'   => $row['offer_description'] ?? '',
                'type'          => $offerType,
                'badge'         => $row['offer_badge'] ?? '',
                'bonusMessage'  => $row['offer_bonus_message'] ?? '',
                'isFeatured'    => !empty($row['offer_is_featured']),
                'image'         => self::resolveImageUrl($row['offer_image'] ?? null),
                'discountLabel' => $row['offer_discount_label'] ?? '',
                'discountType'  => $row['offer_discount_type'] ?? 'none',
                'discountValue' => (float) ($row['offer_discount_value'] ?? 0),
                // Admin-set offer price (from product table totals)
                'offerPrice'    => isset($row['offer_price']) && $row['offer_price'] !== '' 
                                    ? (float) $row['offer_price'] 
                                    : null,
            ];
            
            // Type-specific data
            switch ($offerType) {
                case 'single':
                    $offer = self::enrichSingleOffer($offer, $row);
                    break;
                    
                case 'fixed_bundle':
                    $offer = self::enrichBundleOffer($offer, $row);
                    break;
                    
                case 'customizable_kit':
                    $offer = self::enrichKitOffer($offer, $row);
                    break;
            }
            
            $result[] = $offer;
        }

        return $result;
    }

    /**
     * Get products from the new products_data JSON field or fall back to legacy fields.
     */
    private static function getProductsFromRow(array $row): array
    {
        // Try new products_data JSON field first
        $productsJson = $row['products_data'] ?? '';
        if (!empty($productsJson)) {
            $products = json_decode($productsJson, true);
            if (is_array($products)) {
                return $products;
            }
        }
        
        // Fall back to legacy format based on type
        $offerType = $row['offer_type'] ?? 'single';
        
        if ($offerType === 'single') {
            $sku = $row['single_product_sku'] ?? '';
            if ($sku) {
                return [[
                    'sku' => $sku,
                    'qty' => (int) ($row['single_product_qty'] ?? 1),
                ]];
            }
        } elseif ($offerType === 'fixed_bundle') {
            $items = $row['bundle_items'] ?? [];
            $products = [];
            foreach ($items as $item) {
                if (!empty($item['sku'])) {
                    $products[] = [
                        'sku' => $item['sku'],
                        'qty' => (int) ($item['qty'] ?? 1),
                    ];
                }
            }
            return $products;
        } elseif ($offerType === 'customizable_kit') {
            $items = $row['kit_products'] ?? [];
            $products = [];
            foreach ($items as $item) {
                if (!empty($item['sku'])) {
                    $products[] = [
                        'sku' => $item['sku'],
                        'qty' => (int) ($item['qty'] ?? 1),
                        'role' => $item['role'] ?? 'optional',
                        'max_qty' => (int) ($item['max_qty'] ?? 3),
                        'discount_type' => $item['discount_type'] ?? 'none',
                        'discount_value' => (float) ($item['discount_value'] ?? 0),
                    ];
                }
            }
            return $products;
        }
        
        return [];
    }

    /**
     * Enrich single product offer with WooCommerce data.
     */
    private static function enrichSingleOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row);
        $product = $products[0] ?? null;
        
        if (!$product) {
            return $offer;
        }
        
        $sku = $product['sku'] ?? '';
        $qty = (int) ($product['qty'] ?? 1);
        
        $offer['productSku'] = $sku;
        $offer['quantity'] = $qty;
        
        // Get WooCommerce product data
        if ($sku) {
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
            $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];
            
            $offer['product'] = [
                'sku'          => $sku,
                'name'         => $wcData['name'] ?? $sku,
                'price'        => (float) ($wcData['price'] ?? 0),
                'regularPrice' => (float) ($wcData['regular_price'] ?? $wcData['price'] ?? 0),
                'image'        => $wcData['image'] ?? '',
            ];
            
            // Use product image if no offer image set
            if (empty($offer['image']) && !empty($wcData['image'])) {
                $offer['image'] = $wcData['image'];
            }
            
            // Original price is WooCommerce regular price (before any discounts)
            $offer['originalPrice'] = $offer['product']['regularPrice'] * $qty;
            
            // Use admin-set offer_price if available, otherwise calculate from WC sale price + discount
            if ($offer['offerPrice'] !== null) {
                $offer['calculatedPrice'] = $offer['offerPrice'];
            } else {
                $offer['calculatedPrice'] = self::applyDiscount(
                    $offer['product']['price'] * $qty,
                    $offer['discountType'],
                    $offer['discountValue']
                );
            }
        }
        
        return $offer;
    }

    /**
     * Enrich fixed bundle offer with WooCommerce data.
     */
    private static function enrichBundleOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row);
        $offer['bundleItems'] = [];
        $totalPrice = 0;
        $totalRegularPrice = 0;
        
        foreach ($products as $item) {
            $sku = $item['sku'] ?? '';
            $qty = (int) ($item['qty'] ?? 1);
            // Admin-set sale price (from products_data JSON)
            $adminSalePrice = isset($item['salePrice']) ? (float) $item['salePrice'] : null;
            
            if (empty($sku)) {
                continue;
            }
            
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
            $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];
            
            $wcPrice = (float) ($wcData['price'] ?? 0);
            $regularPrice = (float) ($wcData['regular_price'] ?? $wcPrice);
            // Use admin sale price if set, otherwise use WC price
            $effectivePrice = $adminSalePrice !== null ? $adminSalePrice : $wcPrice;
            
            $offer['bundleItems'][] = [
                'sku'          => $sku,
                'qty'          => $qty,
                'name'         => $wcData['name'] ?? $sku,
                'price'        => $effectivePrice,  // Final sale price
                'regularPrice' => $regularPrice,    // For strikethrough display
                'wcPrice'      => $wcPrice,         // Original WC price
                'image'        => $wcData['image'] ?? '',
            ];
            
            $totalPrice += $effectivePrice * $qty;
            $totalRegularPrice += $regularPrice * $qty;
            
            // Use first product image if no offer image set
            if (empty($offer['image']) && !empty($wcData['image'])) {
                $offer['image'] = $wcData['image'];
            }
        }
        
        // originalPrice is the sum of WooCommerce regular prices (before any discounts)
        $offer['originalPrice'] = $totalRegularPrice;
        
        // Use admin-set offer_price if available, otherwise calculate from discount
        if ($offer['offerPrice'] !== null) {
            $offer['calculatedPrice'] = $offer['offerPrice'];
        } else {
            $offer['calculatedPrice'] = self::applyDiscount(
                $totalPrice,
                $offer['discountType'],
                $offer['discountValue']
            );
        }
        
        return $offer;
    }

    /**
     * Enrich customizable kit offer with WooCommerce data.
     */
    private static function enrichKitOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row);
        $offer['kitProducts'] = [];
        $offer['maxTotalItems'] = (int) ($row['kit_max_items'] ?? 6);
        
        $defaultTotalPrice = 0;
        $defaultTotalRegularPrice = 0;
        
        foreach ($products as $item) {
            $sku = $item['sku'] ?? '';
            // Normalize legacy 'default' role to 'optional'
            $role = $item['role'] ?? 'optional';
            if ($role === 'default') {
                $role = 'optional';
            }
            $qty = (int) ($item['qty'] ?? 1);
            $maxQty = (int) ($item['max_qty'] ?? 3);
            $productDiscountType = $item['discount_type'] ?? 'none';
            $productDiscountValue = (float) ($item['discount_value'] ?? 0);
            
            if (empty($sku)) {
                continue;
            }
            
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
            $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];
            
            $price = (float) ($wcData['price'] ?? 0);
            $regularPrice = (float) ($wcData['regular_price'] ?? $price);
            
            // Use admin-set salePrice if available, otherwise apply discount
            $adminSalePrice = isset($item['salePrice']) ? (float) $item['salePrice'] : null;
            if ($adminSalePrice !== null && $adminSalePrice > 0) {
                $discountedPrice = $adminSalePrice;
            } else {
                $discountedPrice = self::applyDiscount($price, $productDiscountType, $productDiscountValue);
            }
            
            $kitProduct = [
                'sku'           => $sku,
                'role'          => $role,
                'qty'           => $qty,
                'maxQty'        => $maxQty,
                'name'          => $wcData['name'] ?? $sku,
                'price'         => $price,
                'regularPrice'  => $regularPrice,
                'discountType'  => $productDiscountType,
                'discountValue' => $productDiscountValue,
                'discountedPrice' => $discountedPrice,
                'image'         => $wcData['image'] ?? '',
            ];
            
            $offer['kitProducts'][] = $kitProduct;
            
            // Calculate default selection totals (all items with qty > 0)
            if ($qty > 0) {
                $defaultTotalPrice += $discountedPrice * $qty;
                $defaultTotalRegularPrice += $regularPrice * $qty;
            }
            
            // Use first product image if no offer image set
            if (empty($offer['image']) && !empty($wcData['image'])) {
                $offer['image'] = $wcData['image'];
            }
        }
        
        // Apply global kit discount to default selection
        $offer['defaultOriginalPrice'] = $defaultTotalRegularPrice;
        $offer['defaultPriceAfterProductDiscounts'] = $defaultTotalPrice;
        
        // Use admin-set offer_price if available, otherwise calculate from discount
        if ($offer['offerPrice'] !== null) {
            $offer['calculatedPrice'] = $offer['offerPrice'];
        } else {
            $offer['calculatedPrice'] = self::applyDiscount(
                $defaultTotalPrice,
                $offer['discountType'],
                $offer['discountValue']
            );
        }
        
        return $offer;
    }

    /**
     * Apply discount to a price.
     */
    private static function applyDiscount(float $price, string $type, float $value): float
    {
        if ($type === 'percent' && $value > 0) {
            return round($price * (1 - $value / 100), 2);
        }
        if ($type === 'fixed' && $value > 0) {
            return round(max(0, $price - $value), 2);
        }
        return $price;
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
     * Get ACF field value with fallback to post meta.
     * 
     * This handles cases where data was imported but ACF field isn't registered.
     *
     * @param string $fieldName Field name
     * @param int    $postId    Post ID
     * @param mixed  $default   Default value if not found
     * @return mixed Field value
     */
    private static function getFieldValue(string $fieldName, int $postId, $default = '')
    {
        // Try ACF first
        if (function_exists('get_field')) {
            $value = get_field($fieldName, $postId);
            if ($value !== null && $value !== false && $value !== '') {
                return $value;
            }
        }

        // Fall back to post meta
        $metaValue = get_post_meta($postId, $fieldName, true);
        if (!empty($metaValue)) {
            // Handle serialized arrays
            if (is_string($metaValue) && strpos($metaValue, 'a:') === 0) {
                $unserialized = maybe_unserialize($metaValue);
                if (is_array($unserialized)) {
                    return $unserialized;
                }
            }
            return $metaValue;
        }

        return $default;
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
                'page_bg_color'    => $legacy['payment_style']['background_color'] ?? '#121212',
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
