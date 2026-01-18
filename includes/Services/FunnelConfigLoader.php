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
            return self::ensureArrayRecursive($cached);
        }

        // Find the funnel post
        $post = self::findPostBySlug($slug);
        if (!$post) {
            // Try legacy options as fallback
            return self::getLegacyConfig($slug);
        }

        $config = self::loadFromPost($post);
        
        // Final safety check: deep convert to array to ensure no objects leak
        $config = self::ensureArrayRecursive($config);
        
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
            return self::ensureArrayRecursive($cached);
        }

        $post = get_post($postId);
        if (!$post || $post->post_type !== Plugin::FUNNEL_POST_TYPE) {
            return null;
        }

        $config = self::loadFromPost($post);
        
        // Final safety check: deep convert to array to ensure no objects leak
        $config = self::ensureArrayRecursive($config);
        
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
        
        // Clear by post_name (single source of truth)
        $post = get_post($postId);
        $currentSlug = $post ? $post->post_name : '';
        if ($currentSlug) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $currentSlug);
        }
        
        // If old slug provided (slug was changed), clear that cache too
        if ($oldSlug && $oldSlug !== $currentSlug) {
            delete_transient(self::CACHE_PREFIX . 'slug_' . $oldSlug);
        }
    }

    /**
     * Update a funnel with new data.
     *
     * @param string $slug Funnel slug to update
     * @param array $data Funnel data to import
     * @return array|\WP_Error Result with post_id and slug, or WP_Error on failure
     */
    public static function updateFunnel(string $slug, array $data): array|\WP_Error
    {
        $post = self::findPostBySlug($slug);
        if (!$post) {
            return new \WP_Error('not_found', "Funnel with slug '$slug' not found");
        }

        // Auto-backup before update
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            $settings = FunnelVersionControl::getSettings();
            if (!empty($settings['auto_backup_on_update'])) {
                FunnelVersionControl::createVersion($post->ID, 'Before AI update', 'ai_agent');
            }
        }

        // Import as update
        $result = FunnelImporter::importFunnel($data, 'update', $post->ID);

        if (!$result['success']) {
            return new \WP_Error('import_failed', $result['message'] ?? 'Failed to import funnel');
        }

        // Log AI activity
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            FunnelVersionControl::logAiActivity($post->ID, 'funnel_updated', 'Updated via Abilities API');
        }

        // Clear cache
        self::clearCache($post->ID);

        return [
            'success' => true,
            'post_id' => $post->ID,
            'slug' => $slug,
        ];
    }

    /**
     * Update specific sections of a funnel.
     *
     * @param string $slug Funnel slug to update
     * @param array $sections Map of section name => section data
     * @return array|\WP_Error Result with updated_sections, or WP_Error on failure
     */
    public static function updateSections(string $slug, array $sections): array|\WP_Error
    {
        $post = self::findPostBySlug($slug);
        if (!$post) {
            return new \WP_Error('not_found', "Funnel with slug '$slug' not found");
        }

        // Get current funnel data
        $currentData = FunnelExporter::exportFunnel($post->ID);
        if (!$currentData) {
            return new \WP_Error('export_failed', 'Failed to load current funnel data');
        }

        // Auto-backup before update
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            $settings = FunnelVersionControl::getSettings();
            if (!empty($settings['auto_backup_on_update'])) {
                $changedSections = array_keys($sections);
                FunnelVersionControl::createVersion($post->ID, 'Before updating: ' . implode(', ', $changedSections), 'ai_agent');
            }
        }

        // Merge sections
        foreach ($sections as $section => $sectionData) {
            $currentData[$section] = $sectionData;
        }

        // Import merged data
        $result = FunnelImporter::importFunnel($currentData, 'update', $post->ID);

        if (!$result['success']) {
            return new \WP_Error('import_failed', $result['message'] ?? 'Failed to import funnel');
        }

        // Log AI activity
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            FunnelVersionControl::logAiActivity($post->ID, 'sections_updated', 'Updated sections: ' . implode(', ', array_keys($sections)));
        }

        // Clear cache
        self::clearCache($post->ID);

        return [
            'success' => true,
            'updated_sections' => array_keys($sections),
        ];
    }

    /**
     * Find a funnel post by slug.
     * 
     * Uses the WordPress post_name (slug) as the SINGLE SOURCE OF TRUTH.
     * This aligns with native WP functionality and Yoast SEO.
     *
     * @param string $slug Funnel slug
     * @return \WP_Post|null
     */
    public static function findPostBySlug(string $slug): ?\WP_Post
    {
        // Use WordPress native post_name as the single source of truth
        $posts = get_posts([
            'post_type'      => Plugin::FUNNEL_POST_TYPE,
            'post_status'    => ['publish', 'draft', 'private'],
            'posts_per_page' => 1,
            'name'           => $slug,
        ]);

        if (!empty($posts)) {
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

        // #region agent log
        if (function_exists('wp_remote_post')) {
            wp_remote_post('http://127.0.0.1:7243/ingest/fdc1e251-7d8c-4076-b3bd-ed8c4301842f', [
                'blocking' => false,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'location' => 'FunnelConfigLoader.php:421',
                    'message' => 'loadFromPost entry',
                    'data' => ['postId' => $postId, 'postTitle' => $post->post_title],
                    'timestamp' => (int)(microtime(true) * 1000),
                    'sessionId' => 'debug-session',
                    'hypothesisId' => 'A'
                ])
            ]);
        }
        // #endregion

        // Check status
        $status = self::getFieldValue('funnel_status', $postId);
        if ($status === 'inactive') {
            return ['status' => 'inactive', 'active' => false];
        }

        // Use WordPress post_name (slug) as the SINGLE SOURCE OF TRUTH for all URLs
        // This aligns with native WP functionality and Yoast SEO
        $funnelSlug = $post->post_name;
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
            'stripe_mode' => self::getFieldValue('stripe_mode', $postId),
            
            // General settings (Round 2 improvements)
            'general' => [
                'enable_scroll_navigation' => (bool) self::getFieldValue('enable_scroll_navigation', $postId),
            ],
            
            // Header section
            'header' => [
                'logo'        => self::getFieldValue('header_logo', $postId),
                'logo_link'   => self::getFieldValue('header_logo_link', $postId),
                'nav_items'   => self::extractNavItems(self::getFieldValue('header_nav_items', $postId)),
                'sticky'      => (bool) self::getFieldValue('header_sticky', $postId),
                'transparent' => (bool) self::getFieldValue('header_transparent', $postId),
            ],
            
            // Hero section
            'hero' => [
                'title'            => self::getFieldValue('hero_title', $postId),
                'title_size'       => self::getFieldValue('hero_title_size', $postId),
                'subtitle'         => self::getFieldValue('hero_subtitle', $postId),
                'tagline'          => self::getFieldValue('hero_tagline', $postId),
                'description'      => self::getFieldValue('hero_description', $postId),
                'image'            => self::resolveImageUrl(self::getFieldValue('hero_image', $postId)),
                'image_alt'        => self::getFieldValue('hero_image_alt', $postId),
                'logo'             => self::resolveImageUrl(self::getFieldValue('hero_logo', $postId)),
                'logo_link'        => self::getFieldValue('hero_logo_link', $postId),
                'cta_text'         => self::getFieldValue('hero_cta_text', $postId),
            ],
            
            // Benefits section (Round 2 - now supports categorized layout)
            'benefits' => [
                'title'    => self::getFieldValue('hero_benefits_title', $postId),
                'subtitle' => self::getFieldValue('hero_benefits_subtitle', $postId),
                'items'    => self::extractBenefitsWithIcons(self::getFieldValue('hero_benefits', $postId)),
                'enable_categories' => (bool) self::getFieldValue('enable_benefit_categories', $postId),
            ],
            
            // Offers section (replaces legacy products)
            'offers_section' => [
                'title' => self::getFieldValue('offers_section_title', $postId) ?: 'Choose Your Package',
            ],
            
            // Offers items (replaces legacy products)
            'offers' => (function() use ($postId) {
                $val = self::getFieldValue('funnel_offers', $postId);
                if ($val && !is_array($val)) {
                    if (function_exists('wp_remote_post')) {
                        wp_remote_post('http://127.0.0.1:7243/ingest/fdc1e251-7d8c-4076-b3bd-ed8c4301842f', [
                            'blocking' => false,
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => json_encode([
                                'location' => 'FunnelConfigLoader.php:474',
                                'message' => 'funnel_offers weird value',
                                'data' => ['postId' => $postId, 'type' => gettype($val), 'value' => $val],
                                'timestamp' => (int)(microtime(true) * 1000),
                                'sessionId' => 'debug-session',
                                'hypothesisId' => 'A'
                            ])
                        ]);
                    }
                }
                return is_array($val) ? self::extractOffers($val) : [];
            })(),
            
            // Checkout
            // Auto-generate checkout URL based on funnel_slug (single source of truth)
            // Pattern: /express-shop/{funnel_slug}/checkout/
            'checkout' => [
                'url'                    => '/express-shop/' . $funnelSlug . '/checkout/',
                'back_url'               => '/express-shop/' . $funnelSlug . '/',
                'free_shipping_countries' => self::getFieldValue('free_shipping_countries', $postId),
                'global_discount_percent' => (float) self::getFieldValue('global_discount_percent', $postId),
                'enable_points'          => (bool) self::getFieldValue('enable_points_redemption', $postId),
                'show_order_summary'     => (bool) self::getFieldValue('show_order_summary', $postId),
                'show_all_offers'        => (bool) self::getFieldValue('checkout_show_all_offers', $postId),
                // Round 2 improvements - configurable page title and legal popup pages
                'page_title'             => self::getFieldValue('checkout_page_title', $postId) ?: 'Secure Your Order',
                'page_subtitle'          => self::getFieldValue('checkout_page_subtitle', $postId),
                'tos_page_id'            => (int) self::getFieldValue('checkout_tos_page_id', $postId),
                'privacy_page_id'        => (int) self::getFieldValue('checkout_privacy_page_id', $postId),
            ],
            
            // Thank you page
            'thankyou' => [
                'url'       => self::getFieldValue('thankyou_url', $postId),
                'headline'  => self::getFieldValue('thankyou_headline', $postId),
                'message'   => self::getFieldValue('thankyou_message', $postId),
                'show_upsell' => (bool) self::getFieldValue('show_upsell', $postId),
                'upsell'    => self::extractUpsellConfig(self::getFieldValue('upsell_config', $postId)),
            ],
            
            // Styling - consolidated colors
            'styling' => self::extractStyling($postId),
            
            // Footer
            'footer' => [
                'text'       => self::getFieldValue('footer_text', $postId),
                'disclaimer' => self::getFieldValue('footer_disclaimer', $postId),
                'links'      => self::extractFooterLinks(self::getFieldValue('footer_links', $postId)),
            ],
            
            // Features section
            'features' => [
                'title'    => self::getFieldValue('features_title', $postId),
                'subtitle' => self::getFieldValue('features_subtitle', $postId),
                'items'    => self::extractFeatures(self::getFieldValue('features_list', $postId)),
            ],
            
            // Authority section
            'authority' => [
                'title'            => self::getFieldValue('authority_title', $postId),
                'subtitle'         => self::getFieldValue('authority_subtitle', $postId),
                'name'             => self::getFieldValue('authority_name', $postId),
                'credentials'      => self::getFieldValue('authority_credentials', $postId),
                'image'            => self::resolveImageUrl(self::getFieldValue('authority_image', $postId)),
                'image_alt'        => self::getFieldValue('authority_image_alt', $postId),
                'bio'              => self::getFieldValue('authority_bio', $postId),
                'quotes'           => self::extractQuotes(self::getFieldValue('authority_quotes', $postId)),
                'quote_categories' => self::extractQuoteCategories(self::getFieldValue('authority_quote_categories', $postId)),
                'article_link'     => self::extractArticleLink($postId),
            ],
            
            // Testimonials section
            'testimonials' => [
                'title'        => self::getFieldValue('testimonials_title', $postId),
                'subtitle'     => self::getFieldValue('testimonials_subtitle', $postId),
                'display_mode' => self::getFieldValue('testimonials_display_mode', $postId),
                'columns'      => (int) self::getFieldValue('testimonials_columns', $postId),
                'items'        => (function() use ($postId) {
                    $val = self::getFieldValue('testimonials_list', $postId);
                    // #region agent log
                    if (function_exists('wp_remote_post')) {
                        wp_remote_post('http://127.0.0.1:7243/ingest/fdc1e251-7d8c-4076-b3bd-ed8c4301842f', [
                            'blocking' => false,
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => json_encode([
                                'location' => 'FunnelConfigLoader.php:534',
                                'message' => 'testimonials_list value',
                                'data' => ['postId' => $postId, 'type' => gettype($val), 'value' => $val],
                                'timestamp' => (int)(microtime(true) * 1000),
                                'sessionId' => 'debug-session',
                                'hypothesisId' => 'A'
                            ])
                        ]);
                    }
                    // #endregion
                    return is_array($val) ? self::extractTestimonials($val) : [];
                })(),
            ],
            
            // FAQ section
            'faq' => [
                'title' => self::getFieldValue('faq_title', $postId),
                'items' => self::extractFaqItems(self::getFieldValue('faq_list', $postId)),
            ],
            
            // CTA section
            'cta' => [
                'title'           => self::getFieldValue('cta_title', $postId),
                'subtitle'        => self::getFieldValue('cta_subtitle', $postId),
                'button_text'     => self::getFieldValue('cta_button_text', $postId),
                'button_url'      => self::getFieldValue('cta_button_url', $postId),
                'button_behavior' => self::getFieldValue('cta_button_behavior', $postId) ?: 'scroll_offers',
            ],
            
            // Science section
            'science' => [
                'title'    => self::getFieldValue('science_title', $postId),
                'subtitle' => self::getFieldValue('science_subtitle', $postId),
                'sections' => self::extractScienceSections(self::getFieldValue('science_sections', $postId)),
            ],
            
            // Infographics section
            'infographics' => [
                'title'           => self::getFieldValue('infographics_title', $postId),
                'desktop_image'   => self::resolveImageUrl(self::getFieldValue('infographics_desktop_image', $postId)),
                'title_image'     => self::resolveImageUrl(self::getFieldValue('infographics_title_image', $postId)),
                'left_panel_image'=> self::resolveImageUrl(self::getFieldValue('infographics_left_panel', $postId)),
                'right_panel_image' => self::resolveImageUrl(self::getFieldValue('infographics_right_panel', $postId)),
                'mobile_layout'   => self::getFieldValue('infographics_mobile_layout', $postId) ?: 'stack',
                'alt_text'        => self::getFieldValue('infographics_alt_text', $postId),
            ],
            
            // SEO metadata
            'seo' => [
                'focus_keyword'      => self::getFieldValue('seo_focus_keyword', $postId),
                'meta_title'         => self::getFieldValue('seo_meta_title', $postId),
                'meta_description'   => self::getFieldValue('seo_meta_description', $postId),
                'cornerstone_content' => (bool) self::getFieldValue('seo_cornerstone_content', $postId),
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
        $accentColor = self::getFieldValue('accent_color', $postId);
        $accentOverride = (bool) self::getFieldValue('text_color_accent_override', $postId);
        $customTextAccent = self::getFieldValue('text_color_accent', $postId);
        
        // Use custom text accent if override is checked AND a value is set
        $textAccent = ($accentOverride && !empty($customTextAccent)) ? $customTextAccent : $accentColor;
        
        return [
            // Primary accent color (used for UI accents, buttons, etc.)
            'accent_color'        => $accentColor,
            // Text colors
            'text_color_basic'    => self::getFieldValue('text_color_basic', $postId),
            'text_color_accent'   => $textAccent, // Inherits from accent_color unless overridden
            'text_color_note'     => self::getFieldValue('text_color_note', $postId),
            'text_color_discount' => self::getFieldValue('text_color_discount', $postId),
            // UI Element colors
            'page_bg_color'       => self::getFieldValue('page_bg_color', $postId),
            'card_bg_color'       => self::getFieldValue('card_bg_color', $postId),
            'input_bg_color'      => self::getFieldValue('input_bg_color', $postId),
            'border_color'        => self::getFieldValue('border_color', $postId),
            // Background type settings (gradient/solid/image)
            'background_type'     => self::getFieldValue('background_type', $postId),
            'background_image'    => self::getFieldValue('background_image', $postId),
            'custom_css'          => self::getFieldValue('custom_css', $postId),
            // Alternating section backgrounds (Round 2 improvements)
            'alternate_section_bg'=> (bool) self::getFieldValue('alternate_section_bg', $postId),
            'alternate_bg_color'  => self::getFieldValue('alternate_bg_color', $postId),
        ];
    }

    /**
     * Extract navigation items from ACF repeater.
     *
     * @param array|null $navItems ACF repeater data
     * @return array Array of nav item objects
     */
    private static function extractNavItems(?array $navItems): array
    {
        $result = [];
        $navItems = $navItems ?: [];
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
     * @param array|null $benefits ACF repeater data
     * @return array Simple array of benefit strings
     */
    private static function extractBenefits(?array $benefits): array
    {
        $result = [];
        $benefits = $benefits ?: [];
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
     * @param array|null $benefits ACF repeater data
     * @return array Array of benefit objects with text and icon
     */
    private static function extractBenefitsWithIcons(?array $benefits): array
    {
        $result = [];
        $benefits = $benefits ?: [];
        foreach ($benefits as $row) {
            if (isset($row['text']) && !empty($row['text'])) {
                $result[] = [
                    'text'     => (string) $row['text'],
                    'icon'     => $row['icon'] ?? 'check',
                    'category' => $row['category'] ?? null, // Round 2: category for grouped display
                ];
            }
        }
        return $result;
    }

    /**
     * Extract features from ACF repeater.
     *
     * @param array|null $features ACF repeater data
     * @return array Array of feature objects
     */
    private static function extractFeatures(?array $features): array
    {
        $result = [];
        $features = $features ?: [];
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
     * @param array|null $quotes ACF repeater data
     * @return array Array of quote objects
     */
    private static function extractQuotes(?array $quotes): array
    {
        $result = [];
        $quotes = $quotes ?: [];
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
     * @param array|null $categories ACF repeater data
     * @return array Array of quote category objects
     */
    private static function extractQuoteCategories(?array $categories): array
    {
        $result = [];
        $categories = $categories ?: [];
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
     * @param array|null $testimonials ACF repeater data
     * @return array Array of testimonial objects
     */
    private static function extractTestimonials(?array $testimonials): array
    {
        $result = [];
        $testimonials = $testimonials ?: [];
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
     * @param array|null $faqs ACF repeater data
     * @return array Array of FAQ objects
     */
    private static function extractFaqItems(?array $faqs): array
    {
        $result = [];
        $faqs = $faqs ?: [];
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
     * @param array|null $sections ACF repeater data
     * @return array Array of science section objects
     */
    private static function extractScienceSections(?array $sections): array
    {
        $result = [];
        $sections = $sections ?: [];
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
     * @param array|null $links ACF repeater data
     * @return array Array of link objects
     */
    private static function extractFooterLinks(?array $links): array
    {
        $result = [];
        $links = $links ?: [];
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
     * @param array|null $offers ACF repeater data
     * @return array Enriched offer data with WooCommerce product info
     */
    private static function extractOffers(?array $offers): array
    {
        $result = [];
        $offers = $offers ?: [];
        $offerIndex = 0;
        
        foreach ($offers as $row) {
            // Skip non-array items (e.g., section title string mixed in)
            if (!is_array($row)) {
                continue;
            }
            
            $offerType = $row['offer_type'] ?? 'single';
            $offerId = $row['offer_id'] ?? ('offer-' . ++$offerIndex);
            
            // Skip disabled offers (Round 2 improvement)
            $offerEnabled = $row['offer_enabled'] ?? true;
            if (!$offerEnabled) {
                continue;
            }
            
            // Base offer data
            $offer = [
                'id'            => $offerId,
                'name'          => $row['offer_name'] ?? '',
                'description'   => $row['offer_description'] ?? '',
                'type'          => $offerType,
                'badge'         => $row['offer_badge'] ?? '',
                'bonusMessage'  => $row['offer_bonus_message'] ?? '',
                'isFeatured'    => !empty($row['offer_is_featured']),
                'enabled'       => true, // Only enabled offers reach here
                'image'         => self::resolveImageUrl($row['offer_image'] ?? null),
                'image_alt'     => $row['offer_image_alt'] ?? '',
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
                'price'        => $effectivePrice,  // Final sale price (0 = FREE)
                'salePrice'    => $effectivePrice,  // Explicit sale price for backend
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
            // Default to 99 (no limit) if max_qty not set
            $maxQty = (int) ($item['max_qty'] ?? 99);
            $productDiscountType = $item['discount_type'] ?? 'none';
            $productDiscountValue = (float) ($item['discount_value'] ?? 0);
            
            if (empty($sku)) {
                continue;
            }
            
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
            $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];
            
            $price = (float) ($wcData['price'] ?? 0);
            $regularPrice = (float) ($wcData['regular_price'] ?? $price);
            
            // Use admin-set salePrice if explicitly set (including 0 for FREE items), otherwise apply discount
            // Check if salePrice is set and is a valid number (not empty string or null)
            $adminSalePrice = null;
            if (isset($item['salePrice']) && $item['salePrice'] !== '' && $item['salePrice'] !== null) {
                $adminSalePrice = (float) $item['salePrice'];
            }
            if ($adminSalePrice !== null) {
                $discountedPrice = $adminSalePrice;
            } else {
                $discountedPrice = self::applyDiscount($price, $productDiscountType, $productDiscountValue);
            }
            
            // Subsequent pricing for Must Have products (tiered pricing)
            // Used when customer adds more than the required minimum of a Must Have product
            // Default to full WC price (0% discount) for subsequent units
            $subseqDiscountPercent = (float) ($item['subsequentDiscountPercent'] ?? 0);
            $subseqSalePrice = $price; // Default to WC price (full price) for additional units
            if (isset($item['subsequentSalePrice']) && $item['subsequentSalePrice'] !== '' && $item['subsequentSalePrice'] !== null) {
                $subseqSalePrice = (float) $item['subsequentSalePrice'];
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
                // Subsequent pricing (for Must Have products when qty > 1)
                'subsequentDiscountPercent' => $subseqDiscountPercent,
                'subsequentSalePrice' => $subseqSalePrice,
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
        
        // For customizable kits, always calculate from kit products (ignore stored offer_price)
        // The actual price is dynamic based on user selection, so we use defaultTotalPrice
        // which represents the base kit with admin-set quantities
        $offer['calculatedPrice'] = self::applyDiscount(
            $defaultTotalPrice,
            $offer['discountType'],
            $offer['discountValue']
        );
        
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
     * @param array|null $config ACF group data
     * @return array|null Upsell config or null
     */
    private static function extractUpsellConfig(?array $config): ?array
    {
        if (empty($config) || empty($config['sku'])) {
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
    public static function getFieldValue(string $fieldName, int $postId, $default = null)
    {
        // Try ACF first
        if (function_exists('get_field')) {
            $value = get_field($fieldName, $postId);
            if ($value !== null && $value !== false && $value !== '') {
                $ret = self::ensureArrayRecursive($value);
                // #region agent log
                if (function_exists('wp_remote_post')) {
                    wp_remote_post('http://127.0.0.1:7243/ingest/fdc1e251-7d8c-4076-b3bd-ed8c4301842f', [
                        'blocking' => false,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode([
                            'location' => 'FunnelConfigLoader.php:1251',
                            'message' => 'getFieldValue ACF result',
                            'data' => ['field' => $fieldName, 'postId' => $postId, 'type' => gettype($ret), 'value' => is_array($ret)?'array':$ret],
                            'timestamp' => (int)(microtime(true) * 1000),
                            'sessionId' => 'debug-session',
                            'hypothesisId' => 'D'
                        ])
                    ]);
                }
                // #endregion
                return $ret;
            }
        }

        // Fall back to post meta
        $metaValue = get_post_meta($postId, $fieldName, true);
        if (!empty($metaValue)) {
            // #region agent log
            if (function_exists('wp_remote_post')) {
                wp_remote_post('http://127.0.0.1:7243/ingest/fdc1e251-7d8c-4076-b3bd-ed8c4301842f', [
                    'blocking' => false,
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode([
                        'location' => 'FunnelConfigLoader.php:1258',
                        'message' => 'getFieldValue Meta fallback',
                        'data' => ['field' => $fieldName, 'postId' => $postId, 'type' => gettype($metaValue), 'value' => is_array($metaValue)?'array':$metaValue],
                        'timestamp' => (int)(microtime(true) * 1000),
                        'sessionId' => 'debug-session',
                        'hypothesisId' => 'D'
                    ])
                ]);
            }
            // #endregion
            // Handle serialized arrays
            if (is_string($metaValue) && strpos($metaValue, 'a:') === 0) {
                $unserialized = maybe_unserialize($metaValue);
                return self::ensureArrayRecursive($unserialized);
            }
            return self::ensureArrayRecursive($metaValue);
        }

        return $default;
    }

    /**
     * Deeply convert objects to arrays.
     * 
     * @param mixed $value Value to convert
     * @return mixed Converted value
     */
    private static function ensureArrayRecursive($value)
    {
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::ensureArrayRecursive($v);
            }
        }
        return $value;
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
                'background_type'  => 'solid',
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
