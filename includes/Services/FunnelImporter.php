<?php
namespace HP_RW\Services;

use HP_RW\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for importing funnels from JSON format.
 * 
 * Uses ACF's update_field() to properly save data with field references.
 */
class FunnelImporter
{
    /**
     * Import result constants.
     */
    public const RESULT_CREATED = 'created';
    public const RESULT_UPDATED = 'updated';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_ERROR = 'error';

    /**
     * Import mode constants.
     */
    public const MODE_CREATE_NEW = 'create_new';
    public const MODE_UPDATE = 'update';
    public const MODE_SKIP = 'skip';

    /**
     * Import a funnel from JSON string.
     *
     * @param string $json JSON string
     * @param string $mode Import mode for existing funnels
     * @return array Import result
     */
    public static function fromJson(string $json, string $mode = self::MODE_SKIP): array
    {
        $data = json_decode($json, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'result' => self::RESULT_ERROR,
                'error' => 'Invalid JSON: ' . json_last_error_msg(),
            ];
        }

        return self::import($data, $mode);
    }

    /**
     * Import a funnel from array data.
     *
     * @param array $data Funnel data
     * @param string $mode Import mode for existing funnels
     * @return array Import result
     */
    public static function import(array $data, string $mode = self::MODE_SKIP): array
    {
        // Validate schema
        $validation = FunnelSchema::validate($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'result' => self::RESULT_ERROR,
                'error' => 'Validation failed: ' . implode(', ', $validation['errors']),
            ];
        }

        $funnel = $data['funnel'];
        $slug = $funnel['slug'];

        // Check for existing funnel
        $existingPost = FunnelConfigLoader::findPostBySlug($slug);

        if ($existingPost) {
            if ($mode === self::MODE_SKIP) {
                return [
                    'success' => true,
                    'result' => self::RESULT_SKIPPED,
                    'post_id' => $existingPost->ID,
                    'slug' => $slug,
                    'message' => 'Funnel with this slug already exists',
                ];
            } elseif ($mode === self::MODE_CREATE_NEW) {
                // Generate new unique slug
                $slug = self::generateUniqueSlug($slug);
                $funnel['slug'] = $slug;
                $data['funnel'] = $funnel;
            }
            // MODE_UPDATE will proceed with existing post
        }

        try {
            if ($existingPost && $mode === self::MODE_UPDATE) {
                // Update existing funnel
                $postId = $existingPost->ID;
                
                wp_update_post([
                    'ID' => $postId,
                    'post_title' => $funnel['name'],
                    'post_status' => 'publish',
                ]);

                $result = self::RESULT_UPDATED;
            } else {
                // Create new funnel
                $postId = wp_insert_post([
                    'post_type' => Plugin::FUNNEL_POST_TYPE,
                    'post_title' => $funnel['name'],
                    'post_name' => $slug,
                    'post_status' => 'publish',
                ]);

                if (is_wp_error($postId)) {
                    return [
                        'success' => false,
                        'result' => self::RESULT_ERROR,
                        'error' => $postId->get_error_message(),
                    ];
                }

                $result = self::RESULT_CREATED;
            }

            // Import all sections using ACF update_field()
            self::importFunnelFields($postId, $funnel);
            self::importHeader($postId, $data['header'] ?? []);
            self::importHero($postId, $data['hero'] ?? []);
            self::importBenefits($postId, $data['benefits'] ?? []);
            self::importProducts($postId, $data['products'] ?? []);
            self::importFeatures($postId, $data['features'] ?? []);
            self::importAuthority($postId, $data['authority'] ?? []);
            self::importTestimonials($postId, $data['testimonials'] ?? []);
            self::importFaq($postId, $data['faq'] ?? []);
            self::importCta($postId, $data['cta'] ?? []);
            self::importCheckout($postId, $data['checkout'] ?? []);
            self::importThankYou($postId, $data['thankyou'] ?? []);
            self::importStyling($postId, $data['styling'] ?? []);
            self::importFooter($postId, $data['footer'] ?? []);
            self::importScience($postId, $data['science'] ?? []);

            // Clear cache
            FunnelConfigLoader::clearCache($postId);

            return [
                'success' => true,
                'result' => $result,
                'post_id' => $postId,
                'slug' => $slug,
                'edit_url' => get_edit_post_link($postId, 'raw'),
                'message' => $result === self::RESULT_CREATED 
                    ? 'Funnel created successfully' 
                    : 'Funnel updated successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'result' => self::RESULT_ERROR,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Import multiple funnels.
     *
     * @param array $funnels Array of funnel data
     * @param string $mode Import mode
     * @return array Results for each funnel
     */
    public static function importMultiple(array $funnels, string $mode = self::MODE_SKIP): array
    {
        $results = [];

        foreach ($funnels as $i => $data) {
            $slug = $data['funnel']['slug'] ?? "funnel-$i";
            $results[$slug] = self::import($data, $mode);
        }

        return $results;
    }

    /**
     * Generate a unique slug by appending a number.
     */
    private static function generateUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 2;

        while (FunnelConfigLoader::findPostBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Import funnel core fields.
     */
    private static function importFunnelFields(int $postId, array $funnel): void
    {
        if (!function_exists('update_field')) {
            self::updateMeta($postId, 'funnel_slug', $funnel['slug']);
            self::updateMeta($postId, 'funnel_status', $funnel['status'] ?? 'active');
            self::updateMeta($postId, 'stripe_mode', $funnel['stripe_mode'] ?? 'auto');
            return;
        }

        update_field('funnel_slug', $funnel['slug'], $postId);
        update_field('funnel_status', $funnel['status'] ?? 'active', $postId);
        update_field('stripe_mode', $funnel['stripe_mode'] ?? 'auto', $postId);
    }

    /**
     * Import header section.
     */
    private static function importHeader(int $postId, array $header): void
    {
        if (empty($header)) return;

        self::setField($postId, 'header_logo', $header['logo'] ?? '');
        self::setUrlField($postId, 'header_logo_link', $header['logo_link'] ?? '');
        self::setField($postId, 'header_sticky', !empty($header['sticky']));
        self::setField($postId, 'header_transparent', !empty($header['transparent']));
        
        if (!empty($header['nav_items'])) {
            self::setField($postId, 'header_nav_items', array_map(function($item) {
                return [
                    'label' => $item['label'] ?? '',
                    'url' => self::toAbsoluteUrl($item['url'] ?? ''),
                    'is_external' => !empty($item['is_external']),
                ];
            }, $header['nav_items']));
        }
    }

    /**
     * Import hero section.
     */
    private static function importHero(int $postId, array $hero): void
    {
        if (empty($hero)) return;

        self::setField($postId, 'hero_title', $hero['title'] ?? '');
        self::setField($postId, 'hero_subtitle', $hero['subtitle'] ?? '');
        self::setField($postId, 'hero_tagline', $hero['tagline'] ?? '');
        self::setField($postId, 'hero_description', $hero['description'] ?? '');
        self::setField($postId, 'hero_image', $hero['image'] ?? '');
        self::setField($postId, 'hero_logo', $hero['logo'] ?? '');
        self::setUrlField($postId, 'hero_logo_link', $hero['logo_link'] ?? '');
        self::setField($postId, 'hero_cta_text', $hero['cta_text'] ?? 'Get Your Special Offer Now');
    }

    /**
     * Import benefits section.
     */
    private static function importBenefits(int $postId, array $benefits): void
    {
        if (empty($benefits)) return;

        self::setField($postId, 'hero_benefits_title', $benefits['title'] ?? 'Why Choose Us?');
        
        if (!empty($benefits['items'])) {
            self::setField($postId, 'hero_benefits', array_map(function($item) {
                return [
                    'text' => $item['text'] ?? '',
                    'icon' => $item['icon'] ?? 'check',
                ];
            }, $benefits['items']));
        }
    }

    /**
     * Import products section.
     */
    private static function importProducts(int $postId, array $products): void
    {
        if (empty($products)) return;

        $productData = [];
        foreach ($products as $p) {
            if (empty($p['sku'])) continue;

            $product = [
                'sku' => $p['sku'],
                'display_name' => $p['display_name'] ?? '',
                'display_price' => $p['display_price'] ?? '',
                'description' => $p['description'] ?? '',
                'image' => $p['image'] ?? '',
                'badge' => $p['badge'] ?? '',
                'is_best_value' => !empty($p['is_best_value']),
                'free_item_sku' => $p['free_item_sku'] ?? '',
                'free_item_qty' => $p['free_item_qty'] ?? 1,
                'features' => [],
            ];

            if (!empty($p['features'])) {
                $product['features'] = array_map(function($f) {
                    return ['text' => $f['text'] ?? ''];
                }, $p['features']);
            }

            $productData[] = $product;
        }

        self::setField($postId, 'funnel_products', $productData);
    }

    /**
     * Import features section.
     */
    private static function importFeatures(int $postId, array $features): void
    {
        if (empty($features)) return;

        self::setField($postId, 'features_title', $features['title'] ?? 'Key Features');
        self::setField($postId, 'features_subtitle', $features['subtitle'] ?? '');
        
        if (!empty($features['items'])) {
            self::setField($postId, 'features_list', array_map(function($item) {
                return [
                    'icon' => $item['icon'] ?? 'check',
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                ];
            }, $features['items']));
        }
    }

    /**
     * Import authority section.
     */
    private static function importAuthority(int $postId, array $authority): void
    {
        if (empty($authority)) return;

        self::setField($postId, 'authority_title', $authority['title'] ?? 'Who We Are');
        self::setField($postId, 'authority_subtitle', $authority['subtitle'] ?? '');
        self::setField($postId, 'authority_name', $authority['name'] ?? '');
        self::setField($postId, 'authority_credentials', $authority['credentials'] ?? '');
        self::setField($postId, 'authority_image', $authority['image'] ?? '');
        self::setField($postId, 'authority_bio', $authority['bio'] ?? '');
        
        // Simple quotes (flat list)
        if (!empty($authority['quotes'])) {
            self::setField($postId, 'authority_quotes', array_map(function($q) {
                return ['text' => is_string($q) ? $q : ($q['text'] ?? '')];
            }, $authority['quotes']));
        }

        // Quote categories (grouped)
        if (!empty($authority['quote_categories'])) {
            self::setField($postId, 'authority_quote_categories', array_map(function($cat) {
                return [
                    'title' => $cat['title'] ?? '',
                    'quotes' => implode("\n", $cat['quotes'] ?? []),
                ];
            }, $authority['quote_categories']));
        }

        // Article link
        if (!empty($authority['article_link'])) {
            self::setField($postId, 'authority_article_text', $authority['article_link']['text'] ?? '');
            self::setUrlField($postId, 'authority_article_url', $authority['article_link']['url'] ?? '');
        }
    }

    /**
     * Import testimonials section.
     */
    private static function importTestimonials(int $postId, array $testimonials): void
    {
        if (empty($testimonials)) return;

        self::setField($postId, 'testimonials_title', $testimonials['title'] ?? 'What Our Customers Say');
        self::setField($postId, 'testimonials_subtitle', $testimonials['subtitle'] ?? '');
        
        if (!empty($testimonials['items'])) {
            self::setField($postId, 'testimonials_list', array_map(function($t) {
                return [
                    'name' => $t['name'] ?? '',
                    'role' => $t['role'] ?? '',
                    'title' => $t['title'] ?? '',
                    'quote' => $t['quote'] ?? '',
                    'image' => $t['image'] ?? '',
                    'rating' => $t['rating'] ?? 5,
                ];
            }, $testimonials['items']));
        }
    }

    /**
     * Import science section.
     */
    private static function importScience(int $postId, array $science): void
    {
        if (empty($science)) return;

        self::setField($postId, 'science_title', $science['title'] ?? 'The Science Behind Our Product');
        self::setField($postId, 'science_subtitle', $science['subtitle'] ?? '');
        
        if (!empty($science['sections'])) {
            self::setField($postId, 'science_sections', array_map(function($s) {
                return [
                    'title' => $s['title'] ?? '',
                    'description' => $s['description'] ?? '',
                    'bullets' => implode("\n", $s['bullets'] ?? []),
                ];
            }, $science['sections']));
        }
    }

    /**
     * Import FAQ section.
     */
    private static function importFaq(int $postId, array $faq): void
    {
        if (empty($faq)) return;

        self::setField($postId, 'faq_title', $faq['title'] ?? 'Frequently Asked Questions');
        
        if (!empty($faq['items'])) {
            self::setField($postId, 'faq_list', array_map(function($f) {
                return [
                    'question' => $f['question'] ?? '',
                    'answer' => $f['answer'] ?? '',
                ];
            }, $faq['items']));
        }
    }

    /**
     * Import CTA section.
     */
    private static function importCta(int $postId, array $cta): void
    {
        if (empty($cta)) return;

        self::setField($postId, 'cta_title', $cta['title'] ?? 'Ready to Get Started?');
        self::setField($postId, 'cta_subtitle', $cta['subtitle'] ?? '');
        self::setField($postId, 'cta_button_text', $cta['button_text'] ?? 'Order Now');
        self::setUrlField($postId, 'cta_button_url', $cta['button_url'] ?? '');
    }

    /**
     * Import checkout section.
     */
    private static function importCheckout(int $postId, array $checkout): void
    {
        if (empty($checkout)) return;

        self::setUrlField($postId, 'checkout_url', $checkout['url'] ?? '/checkout/');
        self::setField($postId, 'free_shipping_countries', $checkout['free_shipping_countries'] ?? 'US');
        self::setField($postId, 'global_discount_percent', $checkout['global_discount_percent'] ?? 0);
        self::setField($postId, 'enable_points_redemption', $checkout['enable_points_redemption'] ?? true);
        self::setField($postId, 'show_order_summary', $checkout['show_order_summary'] ?? true);
    }

    /**
     * Import thank you section.
     */
    private static function importThankYou(int $postId, array $thankyou): void
    {
        if (empty($thankyou)) return;

        self::setUrlField($postId, 'thankyou_url', $thankyou['url'] ?? '/thank-you/');
        self::setField($postId, 'thankyou_headline', $thankyou['headline'] ?? 'Thank You for Your Order!');
        self::setField($postId, 'thankyou_message', $thankyou['message'] ?? '');
        self::setField($postId, 'show_upsell', !empty($thankyou['show_upsell']));
        
        if (!empty($thankyou['upsell']) && !empty($thankyou['upsell']['sku'])) {
            self::setField($postId, 'upsell_config', [
                'sku' => $thankyou['upsell']['sku'],
                'qty' => $thankyou['upsell']['qty'] ?? 1,
                'discount_percent' => $thankyou['upsell']['discount_percent'] ?? 0,
                'headline' => $thankyou['upsell']['headline'] ?? '',
                'description' => $thankyou['upsell']['description'] ?? '',
                'image' => $thankyou['upsell']['image'] ?? '',
            ]);
        }
    }

    /**
     * Import styling section.
     */
    private static function importStyling(int $postId, array $styling): void
    {
        if (empty($styling)) return;

        self::setField($postId, 'accent_color', $styling['accent_color'] ?? '#eab308');
        self::setField($postId, 'background_type', $styling['background_type'] ?? 'gradient');
        self::setField($postId, 'background_color', $styling['background_color'] ?? '');
        self::setField($postId, 'background_image', $styling['background_image'] ?? '');
        self::setField($postId, 'custom_css', $styling['custom_css'] ?? '');
    }

    /**
     * Import footer section.
     */
    private static function importFooter(int $postId, array $footer): void
    {
        if (empty($footer)) return;

        self::setField($postId, 'footer_text', $footer['text'] ?? '');
        self::setField($postId, 'footer_disclaimer', $footer['disclaimer'] ?? '');
        
        if (!empty($footer['links'])) {
            self::setField($postId, 'footer_links', array_map(function($l) {
                return [
                    'label' => $l['label'] ?? '',
                    'url' => self::toAbsoluteUrl($l['url'] ?? ''),
                ];
            }, $footer['links']));
        }
    }

    /**
     * Set field using ACF update_field or fallback to post meta.
     */
    private static function setField(int $postId, string $fieldName, $value): void
    {
        if (function_exists('update_field')) {
            update_field($fieldName, $value, $postId);
        } else {
            self::updateMeta($postId, $fieldName, $value);
        }
    }

    /**
     * Set a URL field, converting relative URLs to absolute.
     * ACF URL fields require full URLs with protocol.
     */
    private static function setUrlField(int $postId, string $fieldName, string $value): void
    {
        $absoluteUrl = self::toAbsoluteUrl($value);
        self::setField($postId, $fieldName, $absoluteUrl);
    }

    /**
     * Convert a relative URL to absolute URL.
     * Preserves already-absolute URLs.
     *
     * @param string $url URL (relative or absolute)
     * @return string Absolute URL
     */
    private static function toAbsoluteUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Already absolute
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Protocol-relative URL
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        // Relative URL - prepend site URL
        $siteUrl = rtrim(home_url(), '/');
        
        // Ensure URL starts with /
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        return $siteUrl . $url;
    }

    /**
     * Fallback: Update post meta directly.
     */
    private static function updateMeta(int $postId, string $key, $value): void
    {
        if (is_array($value)) {
            $value = maybe_serialize($value);
        }
        update_post_meta($postId, $key, $value);
    }
}

