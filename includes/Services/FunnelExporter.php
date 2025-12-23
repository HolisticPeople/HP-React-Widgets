<?php
namespace HP_RW\Services;

use HP_RW\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for exporting funnels to JSON format.
 * 
 * Exports all ACF fields in a structured, AI-friendly format.
 */
class FunnelExporter
{
    /**
     * Export a single funnel to JSON-compatible array.
     *
     * @param int $postId Funnel post ID
     * @return array|null Exported data or null if not found
     */
    public static function exportById(int $postId): ?array
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== Plugin::FUNNEL_POST_TYPE) {
            return null;
        }

        return self::exportPost($post);
    }

    /**
     * Export a funnel by slug.
     *
     * @param string $slug Funnel slug
     * @return array|null Exported data or null if not found
     */
    public static function exportBySlug(string $slug): ?array
    {
        $post = FunnelConfigLoader::findPostBySlug($slug);
        if (!$post) {
            return null;
        }

        return self::exportPost($post);
    }

    /**
     * Export all funnels.
     *
     * @param bool $activeOnly Only export active funnels
     * @return array Array of exported funnels
     */
    public static function exportAll(bool $activeOnly = false): array
    {
        $posts = FunnelConfigLoader::getAllPosts();
        $result = [];

        foreach ($posts as $post) {
            $data = self::exportPost($post);
            
            if ($activeOnly && ($data['funnel']['status'] ?? 'active') === 'inactive') {
                continue;
            }
            
            $result[] = $data;
        }

        return $result;
    }

    /**
     * Export a WP_Post to structured array.
     *
     * @param \WP_Post $post Funnel post
     * @return array Exported data
     */
    private static function exportPost(\WP_Post $post): array
    {
        $postId = $post->ID;

        // Build the structured export
        $data = [
            '$schema' => FunnelSchema::VERSION,
            
            'funnel' => [
                'name' => $post->post_title,
                'slug' => get_field('funnel_slug', $postId) ?: $post->post_name,
                'status' => get_field('funnel_status', $postId) ?: 'active',
                'stripe_mode' => get_field('stripe_mode', $postId) ?: 'auto',
            ],

            'header' => self::exportHeader($postId),
            'hero' => self::exportHero($postId),
            'benefits' => self::exportBenefits($postId),
            'products' => self::exportProducts($postId),
            'features' => self::exportFeatures($postId),
            'authority' => self::exportAuthority($postId),
            'testimonials' => self::exportTestimonials($postId),
            'faq' => self::exportFaq($postId),
            'cta' => self::exportCta($postId),
            'checkout' => self::exportCheckout($postId),
            'thankyou' => self::exportThankYou($postId),
            'styling' => self::exportStyling($postId),
            'footer' => self::exportFooter($postId),
            'science' => self::exportScience($postId),
        ];

        // Remove empty sections
        foreach ($data as $key => $value) {
            if (is_array($value) && empty(array_filter($value, function($v) {
                return $v !== null && $v !== '' && $v !== [];
            }))) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Export header section.
     */
    private static function exportHeader(int $postId): array
    {
        return [
            'logo' => self::resolveImageUrl(get_field('header_logo', $postId)),
            'logo_link' => get_field('header_logo_link', $postId) ?: '',
            'sticky' => (bool) get_field('header_sticky', $postId),
            'transparent' => (bool) get_field('header_transparent', $postId),
            'nav_items' => self::exportRepeater(get_field('header_nav_items', $postId) ?: [], ['label', 'url', 'is_external']),
        ];
    }

    /**
     * Export hero section.
     */
    private static function exportHero(int $postId): array
    {
        return [
            'title' => get_field('hero_title', $postId) ?: '',
            'subtitle' => get_field('hero_subtitle', $postId) ?: '',
            'tagline' => get_field('hero_tagline', $postId) ?: '',
            'description' => get_field('hero_description', $postId) ?: '',
            'image' => self::resolveImageUrl(get_field('hero_image', $postId)),
            'logo' => self::resolveImageUrl(get_field('hero_logo', $postId)),
            'logo_link' => get_field('hero_logo_link', $postId) ?: '',
            'cta_text' => get_field('hero_cta_text', $postId) ?: 'Get Your Special Offer Now',
        ];
    }

    /**
     * Export benefits section.
     */
    private static function exportBenefits(int $postId): array
    {
        $benefits = get_field('hero_benefits', $postId) ?: [];
        $items = [];
        
        foreach ($benefits as $b) {
            if (!empty($b['text'])) {
                $items[] = [
                    'text' => $b['text'],
                    'icon' => $b['icon'] ?? 'check',
                ];
            }
        }

        return [
            'title' => get_field('hero_benefits_title', $postId) ?: 'Why Choose Us?',
            'items' => $items,
        ];
    }

    /**
     * Export products section.
     */
    private static function exportProducts(int $postId): array
    {
        $products = get_field('funnel_products', $postId) ?: [];
        $result = [];

        foreach ($products as $p) {
            if (empty($p['sku'])) {
                continue;
            }

            $product = [
                'sku' => $p['sku'],
                'display_name' => $p['display_name'] ?? '',
                'display_price' => !empty($p['display_price']) ? (float) $p['display_price'] : null,
                'description' => $p['description'] ?? '',
                'image' => self::resolveImageUrl($p['image'] ?? null),
                'badge' => $p['badge'] ?? '',
                'is_best_value' => !empty($p['is_best_value']),
                'features' => [],
                'free_item_sku' => $p['free_item_sku'] ?? '',
                'free_item_qty' => (int) ($p['free_item_qty'] ?? 1),
            ];

            // Export features
            if (!empty($p['features']) && is_array($p['features'])) {
                foreach ($p['features'] as $f) {
                    if (!empty($f['text'])) {
                        $product['features'][] = ['text' => $f['text']];
                    }
                }
            }

            $result[] = $product;
        }

        return $result;
    }

    /**
     * Export features section.
     */
    private static function exportFeatures(int $postId): array
    {
        $features = get_field('features_list', $postId) ?: [];
        $items = [];

        foreach ($features as $f) {
            if (!empty($f['title'])) {
                $items[] = [
                    'icon' => $f['icon'] ?? 'check',
                    'title' => $f['title'],
                    'description' => $f['description'] ?? '',
                ];
            }
        }

        return [
            'title' => get_field('features_title', $postId) ?: 'Key Features',
            'subtitle' => get_field('features_subtitle', $postId) ?: '',
            'items' => $items,
        ];
    }

    /**
     * Export authority section.
     */
    private static function exportAuthority(int $postId): array
    {
        // Simple quotes
        $quotes = get_field('authority_quotes', $postId) ?: [];
        $quoteItems = [];
        foreach ($quotes as $q) {
            if (!empty($q['text'])) {
                $quoteItems[] = ['text' => $q['text']];
            }
        }

        // Quote categories
        $categories = get_field('authority_quote_categories', $postId) ?: [];
        $categoryItems = [];
        foreach ($categories as $cat) {
            if (!empty($cat['title'])) {
                $quotesText = $cat['quotes'] ?? '';
                $categoryItems[] = [
                    'title' => $cat['title'],
                    'quotes' => is_string($quotesText) ? array_filter(explode("\n", $quotesText)) : (array) $quotesText,
                ];
            }
        }

        // Article link
        $articleLink = null;
        $articleText = get_field('authority_article_text', $postId);
        $articleUrl = get_field('authority_article_url', $postId);
        if ($articleText && $articleUrl) {
            $articleLink = ['text' => $articleText, 'url' => $articleUrl];
        }

        return [
            'title' => get_field('authority_title', $postId) ?: 'Who We Are',
            'subtitle' => get_field('authority_subtitle', $postId) ?: '',
            'name' => get_field('authority_name', $postId) ?: '',
            'credentials' => get_field('authority_credentials', $postId) ?: '',
            'image' => self::resolveImageUrl(get_field('authority_image', $postId)),
            'bio' => get_field('authority_bio', $postId) ?: '',
            'quotes' => $quoteItems,
            'quote_categories' => $categoryItems,
            'article_link' => $articleLink,
        ];
    }

    /**
     * Export testimonials section.
     */
    private static function exportTestimonials(int $postId): array
    {
        $testimonials = get_field('testimonials_list', $postId) ?: [];
        $items = [];

        foreach ($testimonials as $t) {
            if (!empty($t['name']) && !empty($t['quote'])) {
                $items[] = [
                    'name' => $t['name'],
                    'role' => $t['role'] ?? '',
                    'title' => $t['title'] ?? '',
                    'quote' => $t['quote'],
                    'image' => self::resolveImageUrl($t['image'] ?? null),
                    'rating' => (int) ($t['rating'] ?? 5),
                ];
            }
        }

        return [
            'title' => get_field('testimonials_title', $postId) ?: 'What Our Customers Say',
            'subtitle' => get_field('testimonials_subtitle', $postId) ?: '',
            'items' => $items,
        ];
    }

    /**
     * Export science section.
     */
    private static function exportScience(int $postId): array
    {
        $sections = get_field('science_sections', $postId) ?: [];
        $items = [];

        foreach ($sections as $s) {
            if (!empty($s['title'])) {
                $bulletsText = $s['bullets'] ?? '';
                $items[] = [
                    'title' => $s['title'],
                    'description' => $s['description'] ?? '',
                    'bullets' => is_string($bulletsText) ? array_filter(explode("\n", $bulletsText)) : (array) $bulletsText,
                ];
            }
        }

        return [
            'title' => get_field('science_title', $postId) ?: 'The Science Behind Our Product',
            'subtitle' => get_field('science_subtitle', $postId) ?: '',
            'sections' => $items,
        ];
    }

    /**
     * Export FAQ section.
     */
    private static function exportFaq(int $postId): array
    {
        $faqs = get_field('faq_list', $postId) ?: [];
        $items = [];

        foreach ($faqs as $f) {
            if (!empty($f['question']) && !empty($f['answer'])) {
                $items[] = [
                    'question' => $f['question'],
                    'answer' => $f['answer'],
                ];
            }
        }

        return [
            'title' => get_field('faq_title', $postId) ?: 'Frequently Asked Questions',
            'items' => $items,
        ];
    }

    /**
     * Export CTA section.
     */
    private static function exportCta(int $postId): array
    {
        return [
            'title' => get_field('cta_title', $postId) ?: 'Ready to Get Started?',
            'subtitle' => get_field('cta_subtitle', $postId) ?: '',
            'button_text' => get_field('cta_button_text', $postId) ?: 'Order Now',
            'button_url' => get_field('cta_button_url', $postId) ?: '',
        ];
    }

    /**
     * Export checkout section.
     */
    private static function exportCheckout(int $postId): array
    {
        return [
            'url' => get_field('checkout_url', $postId) ?: '/checkout/',
            'free_shipping_countries' => get_field('free_shipping_countries', $postId) ?: 'US',
            'global_discount_percent' => (float) (get_field('global_discount_percent', $postId) ?: 0),
            'enable_points_redemption' => (bool) (get_field('enable_points_redemption', $postId) ?? true),
            'show_order_summary' => (bool) (get_field('show_order_summary', $postId) ?? true),
        ];
    }

    /**
     * Export thank you section.
     */
    private static function exportThankYou(int $postId): array
    {
        $upsellConfig = get_field('upsell_config', $postId) ?: [];

        return [
            'url' => get_field('thankyou_url', $postId) ?: '/thank-you/',
            'headline' => get_field('thankyou_headline', $postId) ?: 'Thank You for Your Order!',
            'message' => get_field('thankyou_message', $postId) ?: '',
            'show_upsell' => (bool) get_field('show_upsell', $postId),
            'upsell' => !empty($upsellConfig['sku']) ? [
                'sku' => $upsellConfig['sku'],
                'qty' => (int) ($upsellConfig['qty'] ?? 1),
                'discount_percent' => (float) ($upsellConfig['discount_percent'] ?? 0),
                'headline' => $upsellConfig['headline'] ?? '',
                'description' => $upsellConfig['description'] ?? '',
                'image' => self::resolveImageUrl($upsellConfig['image'] ?? null),
            ] : null,
        ];
    }

    /**
     * Export styling section.
     */
    private static function exportStyling(int $postId): array
    {
        $accentColor = get_field('accent_color', $postId) ?: '#eab308';
        $accentOverride = (bool) get_field('text_color_accent_override', $postId);
        $customTextAccent = get_field('text_color_accent', $postId) ?: '';
        
        // Resolve text accent: use custom if override checked, otherwise use global accent
        $textAccent = ($accentOverride && !empty($customTextAccent)) ? $customTextAccent : $accentColor;
        
        return [
            'accent_color' => $accentColor,
            // Text colors
            'text_color_basic' => get_field('text_color_basic', $postId) ?: '#e5e5e5',
            'text_color_accent' => $textAccent,
            'text_color_note' => get_field('text_color_note', $postId) ?: '#a3a3a3',
            'text_color_discount' => get_field('text_color_discount', $postId) ?: '#22c55e',
            // UI element colors
            'page_bg_color' => get_field('page_bg_color', $postId) ?: '#121212',
            'card_bg_color' => get_field('card_bg_color', $postId) ?: '#1a1a1a',
            'input_bg_color' => get_field('input_bg_color', $postId) ?: '#333333',
            'border_color' => get_field('border_color', $postId) ?: '#7c3aed',
            // Background type settings
            'background_type' => get_field('background_type', $postId) ?: 'gradient',
            'background_image' => self::resolveImageUrl(get_field('background_image', $postId)),
            'custom_css' => get_field('custom_css', $postId) ?: '',
        ];
    }

    /**
     * Export footer section.
     */
    private static function exportFooter(int $postId): array
    {
        $links = get_field('footer_links', $postId) ?: [];
        $linkItems = [];

        foreach ($links as $l) {
            if (!empty($l['label']) && !empty($l['url'])) {
                $linkItems[] = [
                    'label' => $l['label'],
                    'url' => $l['url'],
                ];
            }
        }

        return [
            'text' => get_field('footer_text', $postId) ?: '',
            'disclaimer' => get_field('footer_disclaimer', $postId) ?: '',
            'links' => $linkItems,
        ];
    }

    /**
     * Helper: Export repeater field to array.
     */
    private static function exportRepeater(array $items, array $fields): array
    {
        $result = [];
        foreach ($items as $item) {
            $row = [];
            foreach ($fields as $field) {
                if (isset($item[$field])) {
                    $row[$field] = $item[$field];
                }
            }
            if (!empty($row)) {
                $result[] = $row;
            }
        }
        return $result;
    }

    /**
     * Helper: Resolve image field to URL.
     */
    private static function resolveImageUrl($value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (is_array($value) && isset($value['url'])) {
            return (string) $value['url'];
        }

        if (is_numeric($value)) {
            $imageData = wp_get_attachment_image_src((int) $value, 'large');
            if ($imageData && isset($imageData[0])) {
                return $imageData[0];
            }
        }

        return '';
    }

    /**
     * Export to JSON string.
     *
     * @param int $postId Funnel post ID
     * @param bool $pretty Use pretty formatting
     * @return string|null JSON string or null
     */
    public static function toJson(int $postId, bool $pretty = true): ?string
    {
        $data = self::exportById($postId);
        if (!$data) {
            return null;
        }

        $flags = JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return wp_json_encode($data, $flags);
    }
}

