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
                'slug' => FunnelConfigLoader::getFieldValue('funnel_slug', $postId) ?: $post->post_name,
                'status' => FunnelConfigLoader::getFieldValue('funnel_status', $postId),
                'stripe_mode' => FunnelConfigLoader::getFieldValue('stripe_mode', $postId),
                'enable_scroll_navigation' => (bool) FunnelConfigLoader::getFieldValue('enable_scroll_navigation', $postId), // Round 2
            ],

            'header' => self::exportHeader($postId),
            'hero' => self::exportHero($postId),
            'benefits' => self::exportBenefits($postId),
            'offers' => self::exportOffers($postId),
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
            'responsive' => self::exportResponsive($postId),
            'infographics' => self::exportInfographics($postId),
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
            'logo' => self::resolveImageUrl(FunnelConfigLoader::getFieldValue('header_logo', $postId)),
            'logo_link' => FunnelConfigLoader::getFieldValue('header_logo_link', $postId) ?: '',
            'sticky' => (bool) FunnelConfigLoader::getFieldValue('header_sticky', $postId),
            'transparent' => (bool) FunnelConfigLoader::getFieldValue('header_transparent', $postId),
            'nav_items' => self::exportRepeater(FunnelConfigLoader::getFieldValue('header_nav_items', $postId) ?: [], ['label', 'url', 'is_external']),
        ];
    }

    /**
     * Export hero section.
     */
    private static function exportHero(int $postId): array
    {
        return [
            'title' => FunnelConfigLoader::getFieldValue('hero_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('hero_subtitle', $postId),
            'tagline' => FunnelConfigLoader::getFieldValue('hero_tagline', $postId),
            'description' => FunnelConfigLoader::getFieldValue('hero_description', $postId),
            'image' => self::resolveImageUrl(FunnelConfigLoader::getFieldValue('hero_image', $postId)),
            'logo' => self::resolveImageUrl(FunnelConfigLoader::getFieldValue('hero_logo', $postId)),
            'logo_link' => FunnelConfigLoader::getFieldValue('hero_logo_link', $postId),
            'cta_text' => FunnelConfigLoader::getFieldValue('hero_cta_text', $postId),
        ];
    }

    /**
     * Export benefits section.
     */
    private static function exportBenefits(int $postId): array
    {
        $benefits = FunnelConfigLoader::getFieldValue('hero_benefits', $postId) ?: [];
        $items = [];
        
        foreach ($benefits as $b) {
            if (!empty($b['text'])) {
                $items[] = [
                    'text' => $b['text'],
                    'icon' => $b['icon'] ?? 'check',
                    'category' => $b['category'] ?? null, // Round 2
                ];
            }
        }

        return [
            'title' => FunnelConfigLoader::getFieldValue('hero_benefits_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('hero_benefits_subtitle', $postId), // Round 2
            'enable_categories' => (bool) FunnelConfigLoader::getFieldValue('enable_benefit_categories', $postId), // Round 2
            'items' => $items,
        ];
    }

    /**
     * Export offers section.
     */
    private static function exportOffers(int $postId): array
    {
        $offers = FunnelConfigLoader::getFieldValue('funnel_offers', $postId) ?: [];
        $result = [
            'section_title' => FunnelConfigLoader::getFieldValue('offers_section_title', $postId) ?: 'Choose Your Package', // Round 2
            'items' => [],
        ];
        $offerIndex = 0;

        foreach ($offers as $o) {
            $offerIndex++;
            $offerType = $o['offer_type'] ?? 'single';
            
            // Generate offer ID from stored value or create one
            $offerId = $o['offer_id'] ?? ('offer-' . $offerIndex);
            
            $offer = [
                'id' => $offerId,
                'name' => $o['offer_name'] ?? '',
                'type' => $offerType,
                'enabled' => isset($o['offer_enabled']) ? (bool) $o['offer_enabled'] : true, // Round 2
                'description' => $o['offer_description'] ?? '',
                'image' => self::resolveImageUrl($o['offer_image'] ?? null),
                'badge' => $o['offer_badge'] ?? '',
                'discount_type' => $o['offer_discount_type'] ?? 'none',
                'discount_value' => (float) ($o['offer_discount_value'] ?? 0),
                'offer_price' => isset($o['offer_price']) && $o['offer_price'] !== '' ? (float) $o['offer_price'] : null,
            ];

            // Get products from products_data JSON or fallbacks
            $products = [];
            $productsJson = $o['products_data'] ?? '';
            if (!empty($productsJson)) {
                $products = json_decode($productsJson, true);
            }

            if ($offerType === 'single') {
                $product = !empty($products) ? $products[0] : null;
                $offer['product_sku'] = $product['sku'] ?? $o['single_product_sku'] ?? '';
                $offer['quantity'] = (int) ($product['qty'] ?? $o['single_product_qty'] ?? 1);
            } elseif ($offerType === 'fixed_bundle') {
                $offer['bundle_items'] = [];
                $items = !empty($products) ? $products : ($o['bundle_items'] ?? []);
                foreach ($items as $item) {
                    if (!empty($item['sku'])) {
                        $offer['bundle_items'][] = [
                            'sku' => $item['sku'],
                            'qty' => (int) ($item['qty'] ?? 1),
                        ];
                    }
                }
            } elseif ($offerType === 'customizable_kit') {
                $offer['kit_products'] = [];
                $items = !empty($products) ? $products : ($o['kit_products'] ?? []);
                foreach ($items as $item) {
                    if (!empty($item['sku'])) {
                        $offer['kit_products'][] = [
                            'sku' => $item['sku'],
                            'qty' => (int) ($item['qty'] ?? 1),
                            'role' => $item['role'] ?? 'optional',
                        ];
                    }
                }
            }

            $result['items'][] = $offer;
        }

        return $result;
    }

    /**
     * Export features section.
     */
    private static function exportFeatures(int $postId): array
    {
        $features = FunnelConfigLoader::getFieldValue('features_list', $postId) ?: [];
        $items = [];

        foreach ($features as $f) {
            if (!empty($f['title'])) {
                $items[] = [
                    'icon' => $f['icon'],
                    'title' => $f['title'],
                    'description' => $f['description'],
                ];
            }
        }

        return [
            'title' => FunnelConfigLoader::getFieldValue('features_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('features_subtitle', $postId),
            'items' => $items,
        ];
    }

    /**
     * Export authority section.
     */
    private static function exportAuthority(int $postId): array
    {
        // Simple quotes
        $quotes = FunnelConfigLoader::getFieldValue('authority_quotes', $postId) ?: [];
        $quoteItems = [];
        foreach ($quotes as $q) {
            if (!empty($q['text'])) {
                $quoteItems[] = ['text' => $q['text']];
            }
        }

        // Quote categories
        $categories = FunnelConfigLoader::getFieldValue('authority_quote_categories', $postId) ?: [];
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
        $articleText = FunnelConfigLoader::getFieldValue('authority_article_text', $postId);
        $articleUrl = FunnelConfigLoader::getFieldValue('authority_article_url', $postId);
        if ($articleText && $articleUrl) {
            $articleLink = ['text' => $articleText, 'url' => $articleUrl];
        }

        return [
            'title' => FunnelConfigLoader::getFieldValue('authority_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('authority_subtitle', $postId),
            'name' => FunnelConfigLoader::getFieldValue('authority_name', $postId),
            'credentials' => FunnelConfigLoader::getFieldValue('authority_credentials', $postId),
            'image' => self::resolveImageUrl(FunnelConfigLoader::getFieldValue('authority_image', $postId)),
            'bio' => FunnelConfigLoader::getFieldValue('authority_bio', $postId),
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
        $testimonials = FunnelConfigLoader::getFieldValue('testimonials_list', $postId) ?: [];
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
            'title' => FunnelConfigLoader::getFieldValue('testimonials_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('testimonials_subtitle', $postId),
            'items' => $items,
        ];
    }

    /**
     * Export science section.
     */
    private static function exportScience(int $postId): array
    {
        $sections = FunnelConfigLoader::getFieldValue('science_sections', $postId) ?: [];
        $items = [];

        foreach ($sections as $s) {
            if (!empty($s['title'])) {
                $bulletsText = $s['bullets'] ?? '';
                $items[] = [
                    'title' => $s['title'],
                    'description' => $s['description'],
                    'bullets' => is_string($bulletsText) ? array_filter(explode("\n", $bulletsText)) : (array) $bulletsText,
                ];
            }
        }

        return [
            'title' => FunnelConfigLoader::getFieldValue('science_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('science_subtitle', $postId),
            'sections' => $items,
        ];
    }

    /**
     * Export FAQ section.
     */
    private static function exportFaq(int $postId): array
    {
        $faqs = FunnelConfigLoader::getFieldValue('faq_list', $postId) ?: [];
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
            'title' => FunnelConfigLoader::getFieldValue('faq_title', $postId),
            'items' => $items,
        ];
    }

    /**
     * Export CTA section.
     */
    private static function exportCta(int $postId): array
    {
        return [
            'title' => FunnelConfigLoader::getFieldValue('cta_title', $postId),
            'subtitle' => FunnelConfigLoader::getFieldValue('cta_subtitle', $postId),
            'button_text' => FunnelConfigLoader::getFieldValue('cta_button_text', $postId),
            'button_url' => FunnelConfigLoader::getFieldValue('cta_button_url', $postId),
        ];
    }

    /**
     * Export checkout section.
     */
    private static function exportCheckout(int $postId): array
    {
        return [
            'url' => FunnelConfigLoader::getFieldValue('checkout_url', $postId),
            'free_shipping_countries' => FunnelConfigLoader::getFieldValue('free_shipping_countries', $postId),
            'global_discount_percent' => (float) FunnelConfigLoader::getFieldValue('global_discount_percent', $postId),
            'enable_points_redemption' => (bool) FunnelConfigLoader::getFieldValue('enable_points_redemption', $postId),
            'show_order_summary' => (bool) FunnelConfigLoader::getFieldValue('show_order_summary', $postId),
            // Round 2 additions
            'page_title' => FunnelConfigLoader::getFieldValue('checkout_page_title', $postId),
            'page_subtitle' => FunnelConfigLoader::getFieldValue('checkout_page_subtitle', $postId),
            'tos_page_id' => (int) FunnelConfigLoader::getFieldValue('checkout_tos_page_id', $postId),
            'privacy_page_id' => (int) FunnelConfigLoader::getFieldValue('checkout_privacy_page_id', $postId),
        ];
    }

    /**
     * Export thank you section.
     */
    private static function exportThankYou(int $postId): array
    {
        $upsellConfig = FunnelConfigLoader::getFieldValue('upsell_config', $postId) ?: [];

        return [
            'url' => FunnelConfigLoader::getFieldValue('thankyou_url', $postId),
            'headline' => FunnelConfigLoader::getFieldValue('thankyou_headline', $postId),
            'message' => FunnelConfigLoader::getFieldValue('thankyou_message', $postId),
            'show_upsell' => (bool) FunnelConfigLoader::getFieldValue('show_upsell', $postId),
            'upsell' => !empty($upsellConfig['sku']) ? [
                'sku' => $upsellConfig['sku'],
                'qty' => (int) $upsellConfig['qty'],
                'discount_percent' => (float) $upsellConfig['discount_percent'],
                'headline' => $upsellConfig['headline'],
                'description' => $upsellConfig['description'],
                'image' => self::resolveImageUrl($upsellConfig['image'] ?? null),
            ] : null,
        ];
    }

    /**
     * Export styling section.
     */
    private static function exportStyling(int $postId): array
    {
        $accentColor = FunnelConfigLoader::getFieldValue('accent_color', $postId);
        $accentOverride = (bool) FunnelConfigLoader::getFieldValue('text_color_accent_override', $postId);
        $customTextAccent = FunnelConfigLoader::getFieldValue('text_color_accent', $postId);
        
        // Resolve text accent: use custom if override checked, otherwise use global accent
        $textAccent = ($accentOverride && !empty($customTextAccent)) ? $customTextAccent : $accentColor;
        
        return [
            'accent_color' => $accentColor,
            // Text colors
            'text_color_basic' => FunnelConfigLoader::getFieldValue('text_color_basic', $postId),
            'text_color_accent' => $textAccent,
            'text_color_note' => FunnelConfigLoader::getFieldValue('text_color_note', $postId),
            'text_color_discount' => FunnelConfigLoader::getFieldValue('text_color_discount', $postId),
            // UI element colors
            'page_bg_color' => FunnelConfigLoader::getFieldValue('page_bg_color', $postId),
            'card_bg_color' => FunnelConfigLoader::getFieldValue('card_bg_color', $postId),
            'input_bg_color' => FunnelConfigLoader::getFieldValue('input_bg_color', $postId),
            'border_color' => FunnelConfigLoader::getFieldValue('border_color', $postId),
            // Background type settings
            'background_type' => FunnelConfigLoader::getFieldValue('background_type', $postId),
            'background_image' => self::resolveImageUrl(FunnelConfigLoader::getFieldValue('background_image', $postId)),
            'custom_css' => FunnelConfigLoader::getFieldValue('custom_css', $postId),

            // v2.33.2: Per-section background configuration (replaces mode-based system)
            'section_backgrounds' => FunnelConfigLoader::getFieldValue('section_backgrounds', $postId) ?: [],
        ];
    }

    /**
     * Export footer section.
     */
    private static function exportFooter(int $postId): array
    {
        $links = FunnelConfigLoader::getFieldValue('footer_links', $postId) ?: [];
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
            'text' => FunnelConfigLoader::getFieldValue('footer_text', $postId),
            'disclaimer' => FunnelConfigLoader::getFieldValue('footer_disclaimer', $postId),
            'links' => $linkItems,
        ];
    }

    /**
     * Export responsive section (v2.32.0).
     */
    private static function exportResponsive(int $postId): array
    {
        $hasOverrides = (bool) FunnelConfigLoader::getFieldValue('responsive_breakpoint_override', $postId);
        
        return [
            'breakpoint_overrides' => $hasOverrides,
            'breakpoints' => $hasOverrides ? [
                'tablet' => (int) FunnelConfigLoader::getFieldValue('responsive_breakpoint_tablet', $postId),
                'laptop' => (int) FunnelConfigLoader::getFieldValue('responsive_breakpoint_laptop', $postId),
                'desktop' => (int) FunnelConfigLoader::getFieldValue('responsive_breakpoint_desktop', $postId),
            ] : null,
            'content_max_width' => (int) FunnelConfigLoader::getFieldValue('responsive_content_max_width', $postId),
            'scroll_settings' => [
                'enable_smooth_scroll' => (bool) FunnelConfigLoader::getFieldValue('responsive_enable_smooth_scroll', $postId),
                'scroll_duration' => (int) FunnelConfigLoader::getFieldValue('responsive_scroll_duration', $postId),
                'scroll_easing' => FunnelConfigLoader::getFieldValue('responsive_scroll_easing', $postId),
                'enable_scroll_snap' => (bool) FunnelConfigLoader::getFieldValue('responsive_enable_scroll_snap', $postId),
            ],
            'mobile_settings' => [
                'sticky_cta_enabled' => (bool) FunnelConfigLoader::getFieldValue('mobile_sticky_cta_enabled', $postId),
                'sticky_cta_text' => FunnelConfigLoader::getFieldValue('mobile_sticky_cta_text', $postId),
                'sticky_cta_target' => FunnelConfigLoader::getFieldValue('mobile_sticky_cta_target', $postId),
                'enable_skeleton_placeholders' => (bool) FunnelConfigLoader::getFieldValue('mobile_enable_skeleton_placeholders', $postId),
                'reduce_animations' => (bool) FunnelConfigLoader::getFieldValue('mobile_reduce_animations', $postId),
            ],
            'sections' => [
                'hero' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_hero_height_behavior', $postId),
                    'mobile_image_position' => FunnelConfigLoader::getFieldValue('responsive_hero_mobile_image_position', $postId),
                    'mobile_title_size' => FunnelConfigLoader::getFieldValue('responsive_hero_mobile_title_size', $postId),
                ],
                'infographics' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_infographics_height_behavior', $postId),
                    'mobile_mode' => FunnelConfigLoader::getFieldValue('responsive_infographics_mobile_mode', $postId),
                    'tablet_mode' => FunnelConfigLoader::getFieldValue('responsive_infographics_tablet_mode', $postId),
                    'desktop_mode' => FunnelConfigLoader::getFieldValue('responsive_infographics_desktop_mode', $postId),
                ],
                'testimonials' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_testimonials_height_behavior', $postId),
                    'mobile_mode' => FunnelConfigLoader::getFieldValue('responsive_testimonials_mobile_mode', $postId),
                    'tablet_mode' => FunnelConfigLoader::getFieldValue('responsive_testimonials_tablet_mode', $postId),
                    'desktop_mode' => FunnelConfigLoader::getFieldValue('responsive_testimonials_desktop_mode', $postId),
                ],
                'products' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_products_height_behavior', $postId),
                ],
                'benefits' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_benefits_height_behavior', $postId),
                ],
                'features' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_features_height_behavior', $postId),
                ],
                'authority' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_authority_height_behavior', $postId),
                ],
                'science' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_science_height_behavior', $postId),
                ],
                'faq' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_faq_height_behavior', $postId),
                ],
                'cta' => [
                    'height_behavior' => FunnelConfigLoader::getFieldValue('responsive_cta_height_behavior', $postId),
                ],
            ],
        ];
    }

    /**
     * Export infographics section (v2.34.0).
     * 
     * Exports all infographic configurations including desktop and mobile images.
     */
    private static function exportInfographics(int $postId): array
    {
        $infographics = FunnelConfigLoader::getFieldValue('funnel_infographics', $postId) ?: [];
        $items = [];

        foreach ($infographics as $info) {
            $item = [
                'label' => $info['info_label'] ?? '',
                'nav_label' => $info['info_nav_label'] ?? '',
                'title' => $info['info_title'] ?? '',
                'desktop_image' => self::resolveImageUrl($info['info_desktop_image'] ?? null),
                'use_mobile_images' => !empty($info['use_mobile_images']),
                'title_image' => self::resolveImageUrl($info['info_title_image'] ?? null),
                'left_panel_image' => self::resolveImageUrl($info['info_left_panel'] ?? null),
                'right_panel_image' => self::resolveImageUrl($info['info_right_panel'] ?? null),
                'alt_text' => $info['info_alt_text'] ?? '',
            ];

            // Only include if there's at least a desktop image or label
            if (!empty($item['desktop_image']) || !empty($item['label'])) {
                $items[] = $item;
            }
        }

        return [
            'items' => $items,
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

