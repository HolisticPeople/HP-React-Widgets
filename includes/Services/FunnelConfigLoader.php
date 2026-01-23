<?php
namespace HP_RW\Services;

use HP_RW\Plugin;
use HP_RW\Util\Resolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for loading and caching funnel configurations from the CPT.
 */
class FunnelConfigLoader
{
    private const CACHE_PREFIX = 'hp_rw_funnel_config_';
    private const CACHE_TTL = HOUR_IN_SECONDS;

    /**
     * Static request-level cache to avoid redundant database/ACF calls.
     */
    private static $requestCache = [];

    /**
     * Get funnel config by slug or ID.
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
     */
    public static function getFromContext(): ?array
    {
        // Static cache for context to avoid repeated lookups in the same request
        static $contextCache = null;
        static $contextLookedUp = false;

        if ($contextLookedUp) {
            return $contextCache;
        }

        // Method 0: Check query var set by funnel sub-routes
        $queryVarFunnel = get_query_var('hp_current_funnel');
        if (!empty($queryVarFunnel) && is_array($queryVarFunnel)) {
            $contextCache = $queryVarFunnel;
            $contextLookedUp = true;
            return $queryVarFunnel;
        }
        
        // Method 0b: Check query var for funnel slug
        $queryVarSlug = get_query_var('hp_funnel_slug');
        if (!empty($queryVarSlug)) {
            $contextCache = self::getBySlug($queryVarSlug);
            $contextLookedUp = true;
            return $contextCache;
        }
        
        // Method 1: Check get_queried_object()
        $queried = get_queried_object();
        if ($queried instanceof \WP_Post && $queried->post_type === Plugin::FUNNEL_POST_TYPE) {
            $contextCache = self::getById($queried->ID);
            $contextLookedUp = true;
            return $contextCache;
        }
        
        // Method 2: Check global $post
        global $post;
        if ($post instanceof \WP_Post && $post->post_type === Plugin::FUNNEL_POST_TYPE) {
            $contextCache = self::getById($post->ID);
            $contextLookedUp = true;
            return $contextCache;
        }
        
        // Method 3: Try Elementor's document system
        if (class_exists('\\Elementor\\Plugin')) {
            $elementor = \Elementor\Plugin::instance();
            if (isset($elementor->documents) && method_exists($elementor->documents, 'get_current')) {
                $document = $elementor->documents->get_current();
                if ($document) {
                    $postId = $document->get_main_id();
                    $templatePost = get_post($postId);
                    
                    if ($templatePost && $templatePost->post_type === 'elementor_library') {
                        $renderedPostId = get_the_ID();
                        if ($renderedPostId && $renderedPostId !== $postId) {
                            $renderedPost = get_post($renderedPostId);
                            if ($renderedPost && $renderedPost->post_type === Plugin::FUNNEL_POST_TYPE) {
                                $contextCache = self::getById($renderedPostId);
                                $contextLookedUp = true;
                                return $contextCache;
                            }
                        }
                    } else if ($templatePost && $templatePost->post_type === Plugin::FUNNEL_POST_TYPE) {
                        $contextCache = self::getById($postId);
                        $contextLookedUp = true;
                        return $contextCache;
                    }
                }
            }
        }
        
        // Method 4: Parse URL to extract funnel slug
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#/express-shop/([^/]+)/?#', $requestUri, $matches)) {
            $slug = sanitize_title($matches[1]);
            $funnelPost = self::findPostBySlug($slug);
            if ($funnelPost) {
                $contextCache = self::getById($funnelPost->ID);
                $contextLookedUp = true;
                return $contextCache;
            }
        }
        
        $contextLookedUp = true;
        return null;
    }

    public static function getBySlug(string $slug): ?array
    {
        if (empty($slug)) return null;
        if (isset(self::$requestCache['slug_' . $slug])) return self::$requestCache['slug_' . $slug];

        $transientKey = self::CACHE_PREFIX . 'slug_' . $slug;
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            $config = self::ensureArrayRecursive($cached);
            self::$requestCache['slug_' . $slug] = $config;
            return $config;
        }

        $post = self::findPostBySlug($slug);
        if (!$post) {
            $config = self::getLegacyConfig($slug);
            if ($config) self::$requestCache['slug_' . $slug] = $config;
            return $config;
        }

        if (isset(self::$requestCache['id_' . $post->ID])) {
            $config = self::$requestCache['id_' . $post->ID];
            self::$requestCache['slug_' . $slug] = $config;
            return $config;
        }

        $config = self::loadFromPost($post);
        $config = self::ensureArrayRecursive($config);
        set_transient($transientKey, $config, self::CACHE_TTL);
        self::$requestCache['slug_' . $slug] = $config;
        self::$requestCache['id_' . $post->ID] = $config;
        
        return $config;
    }

    public static function getById(int $postId): ?array
    {
        if ($postId <= 0) return null;
        if (isset(self::$requestCache['id_' . $postId])) return self::$requestCache['id_' . $postId];

        $transientKey = self::CACHE_PREFIX . 'id_' . $postId;
        $cached = get_transient($transientKey);
        if ($cached !== false) {
            $config = self::ensureArrayRecursive($cached);
            self::$requestCache['id_' . $postId] = $config;
            return $config;
        }

        $post = get_post($postId);
        if (!$post || $post->post_type !== Plugin::FUNNEL_POST_TYPE) return null;

        if (isset(self::$requestCache['slug_' . $post->post_name])) {
            $config = self::$requestCache['slug_' . $post->post_name];
            self::$requestCache['id_' . $postId] = $config;
            return $config;
        }

        $config = self::loadFromPost($post);
        $config = self::ensureArrayRecursive($config);
        set_transient($transientKey, $config, self::CACHE_TTL);
        self::$requestCache['id_' . $postId] = $config;
        self::$requestCache['slug_' . $post->post_name] = $config;
        
        return $config;
    }

    public static function clearCache(int $postId, string $oldSlug = ''): void
    {
        unset(self::$requestCache['id_' . $postId]);
        delete_transient(self::CACHE_PREFIX . 'id_' . $postId);
        $post = get_post($postId);
        $currentSlug = $post ? $post->post_name : '';
        if ($currentSlug) {
            unset(self::$requestCache['slug_' . $currentSlug]);
            delete_transient(self::CACHE_PREFIX . 'slug_' . $currentSlug);
        }
        if ($oldSlug && $oldSlug !== $currentSlug) {
            unset(self::$requestCache['slug_' . $oldSlug]);
            delete_transient(self::CACHE_PREFIX . 'slug_' . $oldSlug);
        }
    }

    public static function updateFunnel(string $slug, array $data): array|\WP_Error
    {
        $post = self::findPostBySlug($slug);
        if (!$post) return new \WP_Error('not_found', "Funnel with slug '$slug' not found");
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            $settings = FunnelVersionControl::getSettings();
            if (!empty($settings['auto_backup_on_update'])) FunnelVersionControl::createVersion($post->ID, 'Before AI update', 'ai_agent');
        }
        $result = FunnelImporter::importFunnel($data, 'update', $post->ID);
        if (!$result['success']) return new \WP_Error('import_failed', $result['message'] ?? 'Failed to import funnel');
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) FunnelVersionControl::logAiActivity($post->ID, 'funnel_updated', 'Updated via Abilities API');
        self::clearCache($post->ID);
        return ['success' => true, 'post_id' => $post->ID, 'slug' => $slug];
    }

    public static function updateSections(string $slug, array $sections): array|\WP_Error
    {
        $post = self::findPostBySlug($slug);
        if (!$post) return new \WP_Error('not_found', "Funnel with slug '$slug' not found");
        $currentData = FunnelExporter::exportFunnel($post->ID);
        if (!$currentData) return new \WP_Error('export_failed', 'Failed to load current funnel data');
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) {
            $settings = FunnelVersionControl::getSettings();
            if (!empty($settings['auto_backup_on_update'])) FunnelVersionControl::createVersion($post->ID, 'Before updating: ' . implode(', ', array_keys($sections)), 'ai_agent');
        }
        foreach ($sections as $section => $sectionData) $currentData[$section] = $sectionData;
        $result = FunnelImporter::importFunnel($currentData, 'update', $post->ID);
        if (!$result['success']) return new \WP_Error('import_failed', $result['message'] ?? 'Failed to import funnel');
        if (class_exists('\HP_RW\Services\FunnelVersionControl')) FunnelVersionControl::logAiActivity($post->ID, 'sections_updated', 'Updated sections: ' . implode(', ', array_keys($sections)));
        self::clearCache($post->ID);
        return ['success' => true, 'updated_sections' => array_keys($sections)];
    }

    public static function findPostBySlug(string $slug): ?\WP_Post
    {
        $posts = get_posts(['post_type' => Plugin::FUNNEL_POST_TYPE, 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => 1, 'name' => $slug]);
        return !empty($posts) ? $posts[0] : null;
    }

    public static function getAllPosts(): array { return get_posts(['post_type' => Plugin::FUNNEL_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']); }

    private static function loadFromPost(\WP_Post $post): array
    {
        $postId = $post->ID;
        $status = self::getFieldValue('funnel_status', $postId);
        if ($status === 'inactive') return ['status' => 'inactive', 'active' => false];
        $funnelSlug = $post->post_name ?: sanitize_title($post->post_title);
        $config = [
            'id' => $postId, 'status' => $status, 'active' => true, 'name' => $post->post_title, 'slug' => $funnelSlug,
            'stripe_mode' => self::getFieldValue('stripe_mode', $postId),
            'general' => ['enable_scroll_navigation' => (bool) self::getFieldValue('enable_scroll_navigation', $postId)],
            'header' => ['logo' => self::getFieldValue('header_logo', $postId), 'logo_link' => self::getFieldValue('header_logo_link', $postId), 'nav_items' => self::extractNavItems(self::getFieldValue('header_nav_items', $postId)), 'sticky' => (bool) self::getFieldValue('header_sticky', $postId), 'transparent' => (bool) self::getFieldValue('header_transparent', $postId)],
            'hero' => ['title' => self::getFieldValue('hero_title', $postId), 'title_size' => self::getFieldValue('hero_title_size', $postId), 'subtitle' => self::getFieldValue('hero_subtitle', $postId), 'tagline' => self::getFieldValue('hero_tagline', $postId), 'description' => self::getFieldValue('hero_description', $postId), 'image' => self::resolveImageUrl(self::getFieldValue('hero_image', $postId)), 'image_alt' => self::getFieldValue('hero_image_alt', $postId), 'logo' => self::resolveImageUrl(self::getFieldValue('hero_logo', $postId)), 'logo_link' => self::getFieldValue('hero_logo_link', $postId), 'cta_text' => self::getFieldValue('hero_cta_text', $postId)],
            'benefits' => ['title' => self::getFieldValue('hero_benefits_title', $postId), 'subtitle' => self::getFieldValue('hero_benefits_subtitle', $postId), 'items' => self::extractBenefitsWithIcons(self::getFieldValue('hero_benefits', $postId)), 'enable_categories' => (bool) self::getFieldValue('enable_benefit_categories', $postId)],
            'offers_section' => ['title' => self::getFieldValue('offers_section_title', $postId) ?: 'Choose Your Package'],
            'offers' => (function() use ($postId) { $val = self::getFieldValue('funnel_offers', $postId); return is_array($val) ? self::extractOffers($val) : []; })(),
            'checkout' => ['url' => '/express-shop/' . $funnelSlug . '/checkout/', 'back_url' => '/express-shop/' . $funnelSlug . '/', 'free_shipping_countries' => self::getFieldValue('free_shipping_countries', $postId), 'global_discount_percent' => (float) self::getFieldValue('global_discount_percent', $postId), 'enable_points' => (bool) self::getFieldValue('enable_points_redemption', $postId), 'show_order_summary' => (bool) self::getFieldValue('show_order_summary', $postId), 'show_all_offers' => (bool) self::getFieldValue('checkout_show_all_offers', $postId), 'page_title' => self::getFieldValue('checkout_page_title', $postId) ?: 'Secure Your Order', 'page_subtitle' => self::getFieldValue('checkout_page_subtitle', $postId), 'tos_page_id' => (int) self::getFieldValue('checkout_tos_page_id', $postId), 'privacy_page_id' => (int) self::getFieldValue('checkout_privacy_page_id', $postId)],
            'thankyou' => ['url' => self::getFieldValue('thankyou_url', $postId), 'headline' => self::getFieldValue('thankyou_headline', $postId), 'message' => self::getFieldValue('thankyou_message', $postId), 'show_upsell' => (bool) self::getFieldValue('show_upsell', $postId), 'upsell' => self::extractUpsellConfig(self::getFieldValue('upsell_config', $postId))],
            'styling' => self::extractStyling($postId),
            'footer' => ['text' => self::getFieldValue('footer_text', $postId), 'disclaimer' => self::getFieldValue('footer_disclaimer', $postId), 'links' => self::extractFooterLinks(self::getFieldValue('footer_links', $postId))],
            'features' => ['title' => self::getFieldValue('features_title', $postId), 'subtitle' => self::getFieldValue('features_subtitle', $postId), 'items' => self::extractFeatures(self::getFieldValue('features_list', $postId))],
            'authority' => ['title' => self::getFieldValue('authority_title', $postId), 'subtitle' => self::getFieldValue('authority_subtitle', $postId), 'name' => self::getFieldValue('authority_name', $postId), 'credentials' => self::getFieldValue('authority_credentials', $postId), 'image' => self::resolveImageUrl(self::getFieldValue('authority_image', $postId)), 'image_alt' => self::getFieldValue('authority_image_alt', $postId), 'bio' => self::getFieldValue('authority_bio', $postId), 'quotes' => self::extractQuotes(self::getFieldValue('authority_quotes', $postId)), 'quote_categories' => self::extractQuoteCategories(self::getFieldValue('authority_quote_categories', $postId)), 'article_link' => self::extractArticleLink($postId)],
            'testimonials' => ['title' => self::getFieldValue('testimonials_title', $postId), 'subtitle' => self::getFieldValue('testimonials_subtitle', $postId), 'display_mode' => self::getFieldValue('testimonials_display_mode', $postId), 'columns' => (int) self::getFieldValue('testimonials_columns', $postId), 'items' => (function() use ($postId) { $val = self::getFieldValue('testimonials_list', $postId); return is_array($val) ? self::extractTestimonials($val) : []; })()],
            'faq' => ['title' => self::getFieldValue('faq_title', $postId), 'items' => self::extractFaqItems(self::getFieldValue('faq_list', $postId))],
            'cta' => ['title' => self::getFieldValue('cta_title', $postId), 'subtitle' => self::getFieldValue('cta_subtitle', $postId), 'button_text' => self::getFieldValue('cta_button_text', $postId), 'button_url' => self::getFieldValue('cta_button_url', $postId), 'button_behavior' => self::getFieldValue('cta_button_behavior', $postId) ?: 'scroll_offers'],
            'science' => ['title' => self::getFieldValue('science_title', $postId), 'subtitle' => self::getFieldValue('science_subtitle', $postId), 'sections' => self::extractScienceSections(self::getFieldValue('science_sections', $postId))],
            'infographics' => self::loadInfographics($postId),
            'seo' => ['focus_keyword' => self::getFieldValue('seo_focus_keyword', $postId), 'meta_title' => self::getFieldValue('seo_meta_title', $postId), 'meta_description' => self::getFieldValue('seo_meta_description', $postId), 'cornerstone_content' => (bool) self::getFieldValue('seo_cornerstone_content', $postId)],
            'responsive' => self::extractResponsiveConfig($postId),
        ];
        return $config;
    }

    private static function extractResponsiveConfig(int $postId): array
    {
        $pluginDefaults = \HP_RW\Plugin::get_responsive_settings();
        $hasOverrides = (bool) self::getFieldValue('responsive_breakpoint_override', $postId);
        $breakpoints = ['tablet' => $hasOverrides ? (int) (self::getFieldValue('responsive_breakpoint_tablet', $postId) ?: $pluginDefaults['breakpoint_tablet']) : $pluginDefaults['breakpoint_tablet'], 'laptop' => $hasOverrides ? (int) (self::getFieldValue('responsive_breakpoint_laptop', $postId) ?: $pluginDefaults['breakpoint_laptop']) : $pluginDefaults['breakpoint_laptop'], 'desktop' => $hasOverrides ? (int) (self::getFieldValue('responsive_breakpoint_desktop', $postId) ?: $pluginDefaults['breakpoint_desktop']) : $pluginDefaults['breakpoint_desktop']];
        $contentMaxWidth = (int) (self::getFieldValue('responsive_content_max_width', $postId) ?: $pluginDefaults['content_max_width']);
        $scrollSettings = ['enable_smooth_scroll' => self::getFieldValue('responsive_enable_smooth_scroll', $postId) ?? $pluginDefaults['enable_smooth_scroll'], 'scroll_duration' => (int) (self::getFieldValue('responsive_scroll_duration', $postId) ?: $pluginDefaults['scroll_duration']), 'scroll_easing' => self::getFieldValue('responsive_scroll_easing', $postId) ?: $pluginDefaults['scroll_easing'], 'enable_scroll_snap' => (bool) self::getFieldValue('responsive_enable_scroll_snap', $postId)];
        $mobileSettings = ['sticky_cta_enabled' => (bool) self::getFieldValue('mobile_sticky_cta_enabled', $postId), 'sticky_cta_text' => self::getFieldValue('mobile_sticky_cta_text', $postId) ?: 'Get Your Kit Now', 'sticky_cta_target' => self::getFieldValue('mobile_sticky_cta_target', $postId) ?: 'scroll_to_offers', 'enable_skeleton_placeholders' => (bool) self::getFieldValue('mobile_enable_skeleton_placeholders', $postId), 'reduce_animations' => (bool) self::getFieldValue('mobile_reduce_animations', $postId)];
        $sectionSettings = ['hero' => ['height_behavior' => self::getFieldValue('responsive_hero_height_behavior', $postId) ?: 'fit_viewport', 'mobile_image_position' => self::getFieldValue('responsive_hero_mobile_image_position', $postId) ?: 'below', 'mobile_title_size' => self::getFieldValue('responsive_hero_mobile_title_size', $postId) ?: 'md'], 'infographics' => ['height_behavior' => self::getFieldValue('responsive_infographics_height_behavior', $postId) ?: 'scrollable', 'mobile_mode' => self::getFieldValue('responsive_infographics_mobile_mode', $postId) ?: 'swipe', 'tablet_mode' => self::getFieldValue('responsive_infographics_tablet_mode', $postId) ?: 'split_panels', 'desktop_mode' => self::getFieldValue('responsive_infographics_desktop_mode', $postId) ?: 'full_image'], 'testimonials' => ['height_behavior' => self::getFieldValue('responsive_testimonials_height_behavior', $postId) ?: 'scrollable', 'mobile_mode' => self::getFieldValue('responsive_testimonials_mobile_mode', $postId) ?: 'carousel', 'tablet_mode' => self::getFieldValue('responsive_testimonials_tablet_mode', $postId) ?: 'grid_2col', 'desktop_mode' => self::getFieldValue('responsive_testimonials_desktop_mode', $postId) ?: 'grid_3col'], 'products' => ['height_behavior' => self::getFieldValue('responsive_products_height_behavior', $postId) ?: 'scrollable'], 'benefits' => ['height_behavior' => self::getFieldValue('responsive_benefits_height_behavior', $postId) ?: 'fit_viewport'], 'features' => ['height_behavior' => self::getFieldValue('responsive_features_height_behavior', $postId) ?: 'scrollable'], 'authority' => ['height_behavior' => self::getFieldValue('responsive_authority_height_behavior', $postId) ?: 'fit_viewport'], 'science' => ['height_behavior' => self::getFieldValue('responsive_science_height_behavior', $postId) ?: 'scrollable'], 'faq' => ['height_behavior' => self::getFieldValue('responsive_faq_height_behavior', $postId) ?: 'scrollable'], 'cta' => ['height_behavior' => self::getFieldValue('responsive_cta_height_behavior', $postId) ?: 'fit_viewport']];
        return ['breakpoint_overrides' => $hasOverrides, 'breakpoints' => $breakpoints, 'content_max_width' => $contentMaxWidth, 'scroll_settings' => $scrollSettings, 'mobile_settings' => $mobileSettings, 'sections' => $sectionSettings];
    }

    private static function extractStyling(int $postId): array
    {
        $accentColor = self::getFieldValue('accent_color', $postId);
        $accentOverride = (bool) self::getFieldValue('text_color_accent_override', $postId);
        $customTextAccent = self::getFieldValue('text_color_accent', $postId);
        $textAccent = ($accentOverride && !empty($customTextAccent)) ? $customTextAccent : $accentColor;
        $sectionBackgrounds = self::getFieldValue('section_backgrounds', $postId);
        return ['accent_color' => $accentColor, 'text_color_basic' => self::getFieldValue('text_color_basic', $postId), 'text_color_accent' => $textAccent, 'text_color_note' => self::getFieldValue('text_color_note', $postId), 'text_color_discount' => self::getFieldValue('text_color_discount', $postId), 'page_bg_color' => self::getFieldValue('page_bg_color', $postId), 'card_bg_color' => self::getFieldValue('card_bg_color', $postId), 'input_bg_color' => self::getFieldValue('input_bg_color', $postId), 'border_color' => self::getFieldValue('border_color', $postId), 'background_type' => self::getFieldValue('background_type', $postId), 'background_image' => self::getFieldValue('background_image', $postId), 'custom_css' => self::getFieldValue('custom_css', $postId), 'section_backgrounds' => $sectionBackgrounds ?: []];
    }

    private static function extractNavItems(?array $navItems): array { $result = []; foreach (($navItems ?: []) as $row) if (isset($row['label']) && !empty($row['label'])) $result[] = ['label' => (string) $row['label'], 'url' => (string) ($row['url'] ?? '#'), 'isExternal' => !empty($row['is_external'])]; return $result; }
    private static function extractBenefitsWithIcons(?array $benefits): array { $result = []; foreach (($benefits ?: []) as $row) if (isset($row['text']) && !empty($row['text'])) $result[] = ['text' => (string) $row['text'], 'icon' => $row['icon'] ?? 'check', 'category' => $row['category'] ?? null]; return $result; }
    private static function extractFeatures(?array $features): array { $result = []; foreach (($features ?: []) as $row) if (!empty($row['title'])) $result[] = ['icon' => $row['icon'] ?? 'check', 'title' => (string) $row['title'], 'description' => $row['description'] ?? '']; return $result; }
    private static function extractQuotes(?array $quotes): array { $result = []; foreach (($quotes ?: []) as $row) if (!empty($row['text'])) $result[] = ['text' => (string) $row['text']]; return $result; }
    private static function extractQuoteCategories(?array $categories): array { $result = []; foreach (($categories ?: []) as $row) if (!empty($row['title'])) $result[] = ['title' => (string) $row['title'], 'quotes' => is_string($row['quotes'] ?? '') ? array_filter(array_map('trim', explode("\n", $row['quotes']))) : (array) ($row['quotes'] ?? [])]; return $result; }
    private static function extractArticleLink(int $postId): ?array { $text = self::getFieldValue('authority_article_text', $postId, ''); $url = self::getFieldValue('authority_article_url', $postId, ''); return ($text && $url) ? ['text' => (string) $text, 'url' => (string) $url] : null; }
    private static function extractTestimonials(?array $testimonials): array { $result = []; foreach (($testimonials ?: []) as $row) if (!empty($row['name']) && !empty($row['quote'])) $result[] = ['name' => (string) $row['name'], 'role' => $row['role'] ?? '', 'title' => $row['title'] ?? '', 'quote' => (string) $row['quote'], 'image' => self::resolveImageUrl($row['image'] ?? null), 'rating' => (int) ($row['rating'] ?? 5)]; return $result; }
    private static function extractFaqItems(?array $faqs): array { $result = []; foreach (($faqs ?: []) as $row) if (!empty($row['question']) && !empty($row['answer'])) $result[] = ['question' => (string) $row['question'], 'answer' => (string) $row['answer']]; return $result; }
    private static function extractScienceSections(?array $sections): array { $result = []; foreach (($sections ?: []) as $row) if (!empty($row['title'])) $result[] = ['title' => (string) $row['title'], 'description' => $row['description'] ?? '', 'bullets' => is_string($row['bullets'] ?? '') ? array_filter(array_map('trim', explode("\n", $row['bullets']))) : (array) ($row['bullets'] ?? [])]; return $result; }
    private static function extractFooterLinks(?array $links): array { $result = []; foreach (($links ?: []) as $row) if (!empty($row['label']) && !empty($row['url'])) $result[] = ['label' => (string) $row['label'], 'url' => (string) $row['url']]; return $result; }

    private static function extractOffers(?array $offers): array
    {
        $result = []; $offerIndex = 0;
        foreach (($offers ?: []) as $row) {
            if (!is_array($row) || !($row['offer_enabled'] ?? true)) continue;
            $offerType = $row['offer_type'] ?? 'single';
            $offer = ['id' => $row['offer_id'] ?? ('offer-' . ++$offerIndex), 'name' => $row['offer_name'] ?? '', 'description' => $row['offer_description'] ?? '', 'type' => $offerType, 'badge' => $row['offer_badge'] ?? '', 'bonusMessage' => $row['offer_bonus_message'] ?? '', 'isFeatured' => !empty($row['offer_is_featured']), 'enabled' => true, 'image' => self::resolveImageUrl($row['offer_image'] ?? null), 'image_alt' => $row['offer_image_alt'] ?? '', 'discountLabel' => $row['offer_discount_label'] ?? '', 'discountType' => $row['offer_discount_type'] ?? 'none', 'discountValue' => (float) ($row['offer_discount_value'] ?? 0), 'offerPrice' => isset($row['offer_price']) && $row['offer_price'] !== '' ? (float) $row['offer_price'] : null];
            if ($offerType === 'single') $offer = self::enrichSingleOffer($offer, $row);
            elseif ($offerType === 'fixed_bundle') $offer = self::enrichBundleOffer($offer, $row);
            elseif ($offerType === 'customizable_kit') $offer = self::enrichKitOffer($offer, $row);
            $result[] = $offer;
        }
        return $result;
    }

    private static function getProductsFromRow(array $row): array
    {
        if (!empty($row['products_data'])) { $p = json_decode($row['products_data'], true); if (is_array($p)) return $p; }
        $t = $row['offer_type'] ?? 'single';
        if ($t === 'single' && !empty($row['single_product_sku'])) return [['sku' => $row['single_product_sku'], 'qty' => (int) ($row['single_product_qty'] ?? 1)]];
        if ($t === 'fixed_bundle') { $res = []; foreach (($row['bundle_items'] ?? []) as $i) if (!empty($i['sku'])) $res[] = ['sku' => $i['sku'], 'qty' => (int) ($i['qty'] ?? 1)]; return $res; }
        if ($t === 'customizable_kit') { $res = []; foreach (($row['kit_products'] ?? []) as $i) if (!empty($i['sku'])) $res[] = ['sku' => $i['sku'], 'qty' => (int) ($i['qty'] ?? 1), 'role' => $i['role'] ?? 'optional', 'max_qty' => (int) ($i['max_qty'] ?? 3), 'discount_type' => $i['discount_type'] ?? 'none', 'discount_value' => (float) ($i['discount_value'] ?? 0)]; return $res; }
        return [];
    }

    private static function enrichSingleOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row); $p = $products[0] ?? null; if (!$p) return $offer;
        $sku = $p['sku'] ?? ''; $qty = (int) ($p['qty'] ?? 1); $offer['productSku'] = $sku; $offer['quantity'] = $qty;
        if ($sku) {
            $wcP = Resolver::resolveProductFromItem(['sku' => $sku]); $wcD = $wcP ? Resolver::getProductDisplayData($wcP) : [];
            $offer['product'] = ['sku' => $sku, 'name' => $wcD['name'] ?? $sku, 'price' => (float) ($wcD['price'] ?? 0), 'regularPrice' => (float) ($wcD['regular_price'] ?? $wcD['price'] ?? 0), 'image' => $wcD['image'] ?? ''];
            if (empty($offer['image']) && !empty($wcD['image'])) $offer['image'] = $wcD['image'];
            $offer['originalPrice'] = $offer['product']['regularPrice'] * $qty;
            $offer['calculatedPrice'] = $offer['offerPrice'] !== null ? $offer['offerPrice'] : self::applyDiscount($offer['product']['price'] * $qty, $offer['discountType'], $offer['discountValue']);
        }
        return $offer;
    }

    private static function enrichBundleOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row); $offer['bundleItems'] = []; $totalP = 0; $totalRP = 0;
        foreach ($products as $i) {
            $sku = $i['sku'] ?? ''; $qty = (int) ($i['qty'] ?? 1); $adminSP = isset($i['salePrice']) ? (float) $i['salePrice'] : null; if (empty($sku)) continue;
            $wcP = Resolver::resolveProductFromItem(['sku' => $sku]); $wcD = $wcP ? Resolver::getProductDisplayData($wcP) : [];
            $wcPrice = (float) ($wcD['price'] ?? 0); $regP = (float) ($wcD['regular_price'] ?? $wcPrice); $effP = $adminSP !== null ? $adminSP : $wcPrice;
            $offer['bundleItems'][] = ['sku' => $sku, 'qty' => $qty, 'name' => $wcD['name'] ?? $sku, 'price' => $effP, 'salePrice' => $effP, 'regularPrice' => $regP, 'wcPrice' => $wcPrice, 'image' => $wcD['image'] ?? ''];
            $totalP += $effP * $qty; $totalRP += $regP * $qty;
            if (empty($offer['image']) && !empty($wcD['image'])) $offer['image'] = $wcD['image'];
        }
        $offer['originalPrice'] = $totalRP; $offer['calculatedPrice'] = $offer['offerPrice'] !== null ? $offer['offerPrice'] : self::applyDiscount($totalP, $offer['discountType'], $offer['discountValue']);
        return $offer;
    }

    private static function enrichKitOffer(array $offer, array $row): array
    {
        $products = self::getProductsFromRow($row); $offer['kitProducts'] = []; $offer['maxTotalItems'] = (int) ($row['kit_max_items'] ?? 6); $defTP = 0; $defTRP = 0;
        foreach ($products as $i) {
            $sku = $i['sku'] ?? ''; $role = ($i['role'] ?? 'optional') === 'default' ? 'optional' : ($i['role'] ?? 'optional'); $qty = (int) ($i['qty'] ?? 1); $maxQ = (int) ($i['max_qty'] ?? 99); $pDT = $i['discount_type'] ?? 'none'; $pDV = (float) ($i['discount_value'] ?? 0); if (empty($sku)) continue;
            $wcP = Resolver::resolveProductFromItem(['sku' => $sku]); $wcD = $wcP ? Resolver::getProductDisplayData($wcP) : [];
            $price = (float) ($wcD['price'] ?? 0); $regP = (float) ($wcD['regular_price'] ?? $price); $adminSP = (isset($i['salePrice']) && $i['salePrice'] !== '' && $i['salePrice'] !== null) ? (float)$i['salePrice'] : null;
            $discP = $adminSP !== null ? $adminSP : self::applyDiscount($price, $pDT, $pDV);
            $subDP = (float) ($i['subsequentDiscountPercent'] ?? 0); $subSP = (isset($i['subsequentSalePrice']) && $i['subsequentSalePrice'] !== '' && $i['subsequentSalePrice'] !== null) ? (float)$i['subsequentSalePrice'] : $price;
            $offer['kitProducts'][] = ['sku' => $sku, 'role' => $role, 'qty' => $qty, 'maxQty' => $maxQ, 'name' => $wcD['name'] ?? $sku, 'price' => $price, 'regularPrice' => $regP, 'discountType' => $pDT, 'discountValue' => $pDV, 'discountedPrice' => $discP, 'subsequentDiscountPercent' => $subDP, 'subsequentSalePrice' => $subSP, 'image' => $wcD['image'] ?? ''];
            if ($qty > 0) { $defTP += $discP * $qty; $defTRP += $regP * $qty; }
            if (empty($offer['image']) && !empty($wcD['image'])) $offer['image'] = $wcD['image'];
        }
        $offer['defaultOriginalPrice'] = $defTRP; $offer['defaultPriceAfterProductDiscounts'] = $defTP; $offer['calculatedPrice'] = self::applyDiscount($defTP, $offer['discountType'], $offer['discountValue']);
        return $offer;
    }

    private static function extractUpsellConfig(?array $config): ?array
    {
        if (empty($config) || empty($config['sku'])) return null;
        $sku = (string) $config['sku']; $wcP = Resolver::resolveProductFromItem(['sku' => $sku]); $wcD = $wcP ? Resolver::getProductDisplayData($wcP) : [];
        $dP = (float) ($config['discount_percent'] ?? 0); $bP = $wcD['price'] ?? 0; $fP = $dP > 0 ? $bP * (1 - $dP / 100) : $bP;
        return ['sku' => $sku, 'qty' => (int) ($config['qty'] ?? 1), 'discount' => $dP, 'price' => round($fP, 2), 'headline' => $config['headline'] ?? 'Wait! Special Offer Just For You!', 'description' => $config['description'] ?? '', 'image' => self::resolveImageUrl($config['image'] ?? null, $wcD['image'] ?? ''), 'productName' => $wcD['name'] ?? $sku];
    }

    public static function getFieldValue(string $fieldName, int $postId, $default = null)
    {
        if (function_exists('get_field')) { $v = get_field($fieldName, $postId); if ($v !== null && $v !== false && $v !== '') return self::ensureArrayRecursive($v); }
        $mV = get_post_meta($postId, $fieldName, true); if (!empty($mV)) { if (is_string($mV) && strpos($mV, 'a:') === 0) return self::ensureArrayRecursive(maybe_unserialize($mV)); return self::ensureArrayRecursive($mV); }
        return $default;
    }

    private static function ensureArrayRecursive($v) { if (is_object($v)) $v = (array) $v; if (is_array($v)) foreach ($v as $k => $val) $v[$k] = self::ensureArrayRecursive($val); return $v; }

    private static function loadInfographics(int $postId): array
    {
        $rows = self::getFieldValue('funnel_infographics', $postId); if (empty($rows) || !is_array($rows)) return [];
        $res = []; foreach ($rows as $index => $row) $res[] = ['index' => $index + 1, 'name' => $row['info_name'] ?? '', 'nav_label' => $row['info_nav_label'] ?? '', 'title' => $row['info_title'] ?? '', 'desktop_image' => self::resolveImageUrl($row['info_desktop_image'] ?? ''), 'use_mobile_images' => !empty($row['info_use_mobile_images']), 'desktop_fallback'  => $row['info_desktop_fallback'] ?? 'scale', 'title_image' => self::resolveImageUrl($row['info_title_image'] ?? ''), 'left_panel_image' => self::resolveImageUrl($row['info_left_panel'] ?? ''), 'right_panel_image' => self::resolveImageUrl($row['info_right_panel'] ?? ''), 'mobile_layout' => $row['info_mobile_layout'] ?? 'stack', 'alt_text' => $row['info_alt_text'] ?? '']; return $res;
    }

    private static function resolveImageUrl($v, string $f = ''): string
    {
        if (empty($v)) return $f;
        static $cache = []; $k = is_scalar($v) ? (string)$v : md5(serialize($v)); if (isset($cache[$k])) return $cache[$k];
        if (is_string($v) && filter_var($v, FILTER_VALIDATE_URL)) return $cache[$k] = $v;
        if (is_array($v) && isset($v['url'])) return $cache[$k] = (string) $v['url'];
        if (is_numeric($v)) { $d = wp_get_attachment_image_src((int) $v, 'large'); if ($d && isset($d[0])) return $cache[$k] = $d[0]; }
        return $f;
    }

    private static function getLegacyConfig(string $slug): ?array
    {
        $opts = get_option('hp_rw_settings', []); if (!empty($opts['funnel_configs'][$slug])) return self::normalizeLegacyConfig($opts['funnel_configs'][$slug], $slug);
        $fOpts = get_option('hp_rw_funnel_' . $slug, []); return !empty($fOpts) ? self::normalizeLegacyConfig($fOpts, $slug) : null;
    }

    private static function normalizeLegacyConfig(array $l, string $s): array { return ['id' => 0, 'status' => 'active', 'active' => true, 'name' => $l['name'] ?? ucfirst($s), 'slug' => $s, 'stripe_mode' => 'auto', 'hero' => ['title' => $l['hero_title'] ?? ($l['title'] ?? ''), 'subtitle' => $l['hero_subtitle'] ?? ($l['subtitle'] ?? ''), 'tagline' => $l['hero_tagline'] ?? ($l['tagline'] ?? ''), 'description' => $l['hero_description'] ?? ($l['description'] ?? ''), 'image' => $l['hero_image'] ?? '', 'logo' => $l['logo_url'] ?? '', 'logo_link' => $l['logo_link'] ?? home_url('/'), 'cta_text' => $l['cta_text'] ?? 'Get Your Special Offer Now', 'benefits_title' => $l['benefits_title'] ?? 'Why Choose Us?', 'benefits' => $l['benefits'] ?? []], 'products' => $l['products'] ?? [], 'checkout' => ['url' => $l['checkout_url'] ?? '/checkout/', 'free_shipping_countries' => $l['free_shipping_countries'] ?? ['US'], 'global_discount_percent' => (float) ($l['global_discount_percent'] ?? 0), 'enable_points' => true, 'show_order_summary' => true], 'thankyou' => ['url' => $l['thankyou_url'] ?? '/thank-you/', 'headline' => $l['thankyou_headline'] ?? 'Thank You for Your Order!', 'message' => $l['thankyou_subheadline'] ?? '', 'show_upsell' => !empty($l['upsell_offers']), 'upsell' => null], 'styling' => ['accent_color' => $l['payment_style']['accent_color'] ?? '#eab308', 'background_type' => 'solid', 'page_bg_color' => $l['payment_style']['background_color'] ?? '#121212', 'background_image' => '', 'custom_css' => ''], 'footer' => ['text' => $l['footer_text'] ?? '', 'disclaimer' => $l['footer_disclaimer'] ?? '']]; }

    public static function getAllActive(): array { $f = self::getAllPosts(); $res = []; foreach ($f as $p) { $c = self::loadFromPost($p); if ($c['active']) $res[] = $c; } return $res; }

    public static function detectConfiguredSections(int $postId): array { $sOrder = self::parsePageShortcodes($postId); return !empty($sOrder) ? self::buildSectionsFromShortcodes($sOrder, $postId) : self::buildSectionsFromFields($postId); }

    private static function parsePageShortcodes(int $postId): array
    {
        $p = get_post($postId); if (!$p) return []; $tID = $postId;
        if ($p->post_type === 'hp-funnel') { $lID = get_field('funnel_landing_page', $postId); if (!empty($lID)) $tID = (int) $lID; }
        $eD = get_post_meta($tID, '_elementor_data', true); if (!empty($eD)) { $d = is_string($eD) ? json_decode($eD, true) : $eD; if (is_array($d)) return self::extractShortcodesFromElementor($d); }
        $tP = ($tID !== $postId) ? get_post($tID) : $p; return ($tP && !empty($tP->post_content)) ? self::extractShortcodesFromContent($tP->post_content) : [];
    }

    private static function extractShortcodesFromElementor(array $elements): array
    {
        $res = [];
        foreach ($elements as $e) {
            if (isset($e['widgetType']) && $e['widgetType'] === 'shortcode') { $sT = $e['settings']['shortcode'] ?? ''; if ($sT) { $p = self::parseShortcodeString($sT); if ($p) $res[] = $p; } }
            if (isset($e['widgetType']) && $e['widgetType'] === 'text-editor') { $c = $e['settings']['editor'] ?? ''; $res = array_merge($res, self::extractShortcodesFromContent($c)); }
            if (!empty($e['elements']) && is_array($e['elements'])) $res = array_merge($res, self::extractShortcodesFromElementor($e['elements']));
        }
        return $res;
    }

    private static function extractShortcodesFromContent(string $content): array
    {
        $res = []; preg_match_all('/\[hp_funnel_(\w+)([^\]]*)\]/', $content, $m, PREG_SET_ORDER);
        foreach ($m as $match) { $sN = 'hp_funnel_' . $match[1]; if (isset(self::SHORTCODE_TYPE_MAP[$sN])) $res[] = ['shortcode' => $sN, 'type' => self::SHORTCODE_TYPE_MAP[$sN], 'atts' => (array)shortcode_parse_atts(trim($match[2]))]; }
        return $res;
    }

    private static function parseShortcodeString(string $s): ?array { if (preg_match('/\[hp_funnel_(\w+)([^\]]*)\]/', $s, $m)) { $sN = 'hp_funnel_' . $m[1]; if (isset(self::SHORTCODE_TYPE_MAP[$sN])) return ['shortcode' => $sN, 'type' => self::SHORTCODE_TYPE_MAP[$sN], 'atts' => (array)shortcode_parse_atts(trim($m[2]))]; } return null; }

    private static function buildSectionsFromShortcodes(array $sc, int $pID): array
    {
        $res = []; $tOcc = [];
        foreach ($sc as $s) {
            $t = $s['type']; $l = self::SECTION_LABELS[$t] ?? ucfirst($t);
            if ($t === 'infographics') {
                $iIdx = isset($s['atts']['info']) ? (int) $s['atts']['info'] : 1; $sID = 'infographics_' . $iIdx;
                $rows = get_field('funnel_infographics', $pID); if (is_array($rows) && isset($rows[$iIdx - 1])) $l = $rows[$iIdx - 1]['info_nav_label'] ?: ($rows[$iIdx - 1]['info_name'] ?: $l);
                $res[] = ['section_id' => $sID, 'section_label' => $l, 'section_type' => $t, 'info_index' => $iIdx];
            } else {
                if (!isset($tOcc[$t])) $tOcc[$t] = 0; $tOcc[$t]++; $occ = $tOcc[$t];
                $res[] = ['section_id' => $t . '_' . $occ, 'section_label' => $l, 'section_type' => $t, 'occurrence' => $occ];
            }
        }
        return $res;
    }

    private static function buildSectionsFromFields(int $pID): array
    {
        $res = []; $tOcc = [];
        $add = function($t, $l = null, $iIdx = null) use (&$res, &$tOcc) {
            if (!isset($tOcc[$t])) $tOcc[$t] = 0; $tOcc[$t]++; $occ = $tOcc[$t];
            $s = ['section_id' => $t . '_' . ($iIdx !== null ? $iIdx : $occ), 'section_label' => $l ?: (self::SECTION_LABELS[$t] ?? ucfirst($t)), 'section_type' => $t, 'occurrence' => $occ];
            if ($iIdx !== null) $s['info_index'] = $iIdx; $res[] = $s;
        };
        $add('hero', 'Hero Section');
        $checks = ['benefits' => 'hero_benefits_title', 'infographics' => 'funnel_infographics', 'offers' => null, 'features' => 'features_title', 'authority' => 'authority_title', 'science' => 'science_title', 'testimonials' => 'testimonials_title', 'faq' => 'faq_title', 'cta' => 'cta_title'];
        foreach ($checks as $t => $f) {
            $isC = ($t === 'offers' || ($f && !empty(get_field($f, $pID))));
            if ($isC) {
                if ($t === 'infographics') { $rows = get_field('funnel_infographics', $pID); if (is_array($rows)) foreach ($rows as $idx => $row) $add('infographics', $row['info_nav_label'] ?: ($row['info_name'] ?: 'Comparison'), $idx + 1); }
                else $add($t);
            }
        }
        return $res;
    }

    public static function autoPopulateSectionBackgrounds(int $pID): void
    {
        if (get_post_type($pID) !== 'hp-funnel') return;
        $ex = get_field('section_backgrounds', $pID) ?: []; $idx = []; foreach ($ex as $s) if (isset($s['section_id'])) $idx[$s['section_id']] = $s;
        $conf = self::detectConfiguredSections($pID); $new = [];
        foreach ($conf as $s) {
            $sID = $s['section_id']; $row = ['section_id' => $sID, 'section_label' => $s['section_label'], 'section_type' => $s['section_type']];
            if (isset($s['info_index'])) $row['info_index'] = $s['info_index']; else if (isset($s['occurrence'])) $row['occurrence'] = $s['occurrence'];
            $e = $idx[$sID] ?? null;
            if ($e) { $row['background_type'] = $e['background_type'] ?? 'none'; $row['gradient_type'] = $e['gradient_type'] ?? 'linear'; $row['gradient_preset'] = $e['gradient_preset'] ?? 'vertical-down'; $row['color_mode'] = $e['color_mode'] ?? 'auto'; $row['gradient_start_color'] = $e['gradient_start_color'] ?? '#1a1a2e'; $row['gradient_end_color'] = $e['gradient_end_color'] ?? ''; }
            else { $row['background_type'] = 'none'; $row['gradient_type'] = 'linear'; $row['gradient_preset'] = 'vertical-down'; $row['color_mode'] = 'auto'; $row['gradient_start_color'] = '#1a1a2e'; $row['gradient_end_color'] = ''; }
            $new[] = $row;
        }
        update_field('section_backgrounds', $new, $pID);
    }

    public static function getBackgroundAssets(array $c): string
    {
        static $done = false; if ($done) return '';
        $output = ''; $sectionBackgrounds = $c['styling']['section_backgrounds'] ?? [];
        if (empty($sectionBackgrounds)) { $done = true; return ''; }
        $pageBgColor = sanitize_hex_color($c['styling']['page_bg_color']) ?: '#121212';
        $output .= '<style> body { overflow-x: hidden !important; } .hp-funnel-section.hp-has-bg { width: 100vw !important; position: relative !important; left: 50% !important; right: 50% !important; margin-left: -50vw !important; margin-right: -50vw !important; margin-top: 0 !important; margin-bottom: 0 !important; padding-left: calc(50vw - 50%) !important; padding-right: calc(50vw - 50%) !important; box-sizing: border-box !important; } .hp-funnel-section.hp-funnel-infographics { margin-top: 0 !important; margin-bottom: 0 !important; } </style>';
        $output .= '<script> (function() { var classToType = { "hp-funnel-hero-section": "hero", "hp-funnel-benefits": "benefits", "hp-funnel-products": "offers", "hp-funnel-features": "features", "hp-funnel-authority": "authority", "hp-funnel-testimonials": "testimonials", "hp-funnel-faq": "faq", "hp-funnel-cta": "cta", "hp-funnel-science": "science", "hp-funnel-infographics": "infographics" }; function applyBackgrounds() { var sections = document.querySelectorAll(".hp-funnel-section"); var typeOccurrences = {}; var backgroundMap = window.hpFunnelBackgroundMap || {}; sections.forEach(function(section) { var className = section.className; var sectionType = null; for (var pattern in classToType) { if (className.indexOf(pattern) !== -1) { sectionType = classToType[pattern]; break; } } if (!sectionType) return; var stableId; if (sectionType === "infographics") { var infoIndex = section.getAttribute("data-info-index"); stableId = infoIndex ? "infographics_" + infoIndex : null; } if (!stableId) { if (!typeOccurrences[sectionType]) typeOccurrences[sectionType] = 0; typeOccurrences[sectionType]++; stableId = sectionType + "_" + typeOccurrences[sectionType]; } var background = backgroundMap[stableId]; if (background && background !== "transparent") { section.classList.add("hp-has-bg"); section.style.setProperty("background", background, "important"); } section.setAttribute("data-section-id", stableId); }); } window.hpApplyFunnelBackgrounds = applyBackgrounds; if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", applyBackgrounds); } else { setTimeout(applyBackgrounds, 100); } })(); </script>';
        $done = true; return $output;
    }

    public static function getBackgroundMapScript(array $c): string
    {
        $sectionBackgrounds = $c['styling']['section_backgrounds'] ?? []; if (empty($sectionBackgrounds)) return '';
        $pageBgColor = sanitize_hex_color($c['styling']['page_bg_color']) ?: '#121212'; $backgroundMap = [];
        foreach ($sectionBackgrounds as $section) {
            $sectionId = $section['section_id']; $bgType = $section['background_type'] ?? 'none';
            if ($bgType === 'none') $backgroundMap[$sectionId] = 'transparent';
            elseif ($bgType === 'solid') $backgroundMap[$sectionId] = sanitize_hex_color($section['gradient_start_color']) ?: '#1a1a2e';
            elseif ($bgType === 'gradient') $backgroundMap[$sectionId] = \HP_RW\Services\GradientGenerator::generateGradient(['gradient_type' => $section['gradient_type'] ?? 'linear', 'gradient_preset' => $section['gradient_preset'] ?? 'vertical-down', 'color_mode' => $section['color_mode'] ?? 'auto', 'gradient_start_color' => $section['gradient_start_color'] ?? '', 'gradient_end_color' => $section['gradient_end_color'] ?? ''], $section['gradient_start_color'] ?? '#1a1a2e', $pageBgColor);
        }
        return sprintf('<script>window.hpFunnelBackgroundMap = Object.assign(window.hpFunnelBackgroundMap || {}, %s); if(window.hpApplyFunnelBackgrounds) window.hpApplyFunnelBackgrounds();</script>', wp_json_encode($backgroundMap));
    }
}
