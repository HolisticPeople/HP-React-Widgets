<?php
namespace HP_RW\Services;

use HP_RW\Plugin;
use HP_RW\Services\ProductCatalogService;

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
     * @param int $existingPostId Optional post ID if updating
     * @return array Import result
     */
    public static function importFunnel(array $data, string $mode = self::MODE_SKIP, int $existingPostId = 0): array
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
        $existingPost = $existingPostId ? get_post($existingPostId) : FunnelConfigLoader::findPostBySlug($slug);

        if ($existingPost) {
            if ($mode === self::MODE_SKIP) {
                return [
                    'success' => true,
                    'result' => self::RESULT_SKIPPED,
                    'post_id' => $existingPost->ID,
                    'slug' => $slug,
                    'message' => 'Funnel with this slug already exists',
                ];
            } elseif ($mode === self::MODE_CREATE_NEW && !$existingPostId) {
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
            
            // Handle both 'offers' (v1 schema) and legacy 'products'
            if (!empty($data['offers'])) {
                self::importOffers($postId, $data['offers']);
            } elseif (!empty($data['products'])) {
                self::importProducts($postId, $data['products']);
            }

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
            self::importSeo($postId, $data['seo'] ?? []);
            self::importResponsive($postId, $data['responsive'] ?? []);

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
     * Import offers section (v1 schema).
     * Maps to 'funnel_offers' repeater and constructs 'products_data' JSON.
     */
    private static function importOffers(int $postId, array $offers): void
    {
        if (empty($offers)) return;

        // Round 2: Handle section_title for new offers format
        if (isset($offers['section_title'])) {
            self::setField($postId, 'offers_section_title', $offers['section_title']);
            $offerItems = $offers['items'] ?? [];
        } else {
            // Legacy format - offers is directly an array of items
            $offerItems = $offers;
        }

        $offersData = [];
        foreach ($offerItems as $o) {
            if (empty($o['id']) || empty($o['type'])) continue;

            $offer = [
                'offer_id' => $o['id'],
                'offer_name' => $o['name'] ?? '',
                'offer_description' => $o['description'] ?? '',
                'offer_type' => $o['type'],
                'offer_enabled' => isset($o['enabled']) ? (bool) $o['enabled'] : true, // Round 2
                'offer_badge' => $o['badge'] ?? '',
                'offer_is_featured' => !empty($o['is_featured']),
                'offer_image' => $o['image'] ?? '',
                'offer_image_alt' => $o['image_alt'] ?? '',
                'offer_discount_label' => $o['discount_label'] ?? '',
                'offer_price' => $o['price'] ?? 0,
                'offer_bonus_message' => $o['bonus_message'] ?? '',
                'kit_max_items' => $o['max_total_items'] ?? 6,
            ];

            // Resolve products for this offer to build 'products_data' JSON
            $productsList = [];
            $discountType = $o['discount_type'] ?? 'none';
            $discountValue = (float) ($o['discount_value'] ?? 0);
            $calculatedPrice = 0;

            if ($o['type'] === 'single' && !empty($o['product_sku'])) {
                $p = self::resolveProductForData($o['product_sku'], $o['quantity'] ?? 1, 'must', $discountType, $discountValue);
                if ($p) {
                    $productsList[] = $p;
                    $calculatedPrice += $p['salePrice'] * $p['qty'];
                }
            } elseif ($o['type'] === 'fixed_bundle' && !empty($o['bundle_items'])) {
                foreach ($o['bundle_items'] as $item) {
                    $p = self::resolveProductForData($item['sku'], $item['qty'] ?? 1, 'must', $discountType, $discountValue);
                    if ($p) {
                        $productsList[] = $p;
                        $calculatedPrice += $p['salePrice'] * $p['qty'];
                    }
                }
            } elseif ($o['type'] === 'customizable_kit' && !empty($o['kit_products'])) {
                foreach ($o['kit_products'] as $item) {
                    $productsList[] = self::resolveProductForData(
                        $item['sku'], 
                        $item['qty'] ?? 1, 
                        $item['role'] ?? 'optional',
                        $item['discount_type'] ?? 'none',
                        $item['discount_value'] ?? 0
                    );
                }
            }

            // If offer price is not set, use calculated price from items
            if (empty($offer['offer_price']) && $calculatedPrice > 0) {
                $offer['offer_price'] = round($calculatedPrice, 2);
            }

            // Remove nulls (unresolved products)
            $productsList = array_filter($productsList);
            $offer['products_data'] = wp_json_encode(array_values($productsList));

            $offersData[] = $offer;
        }

        self::setField($postId, 'funnel_offers', $offersData);
    }

    /**
     * Helper to fetch product details and format for 'products_data' JSON.
     */
    private static function resolveProductForData(string $sku, int $qty, string $role, string $discountType = 'none', float $discountValue = 0): ?array
    {
        $details = ProductCatalogService::getProductDetails($sku);
        if (!$details) return null;

        $price = $details['price'];
        $salePrice = $price;

        if ($discountType === 'percent' && $discountValue > 0) {
            $salePrice = $price * (1 - ($discountValue / 100));
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $salePrice = max(0, $price - $discountValue);
        }

        return [
            'sku' => $sku,
            'name' => $details['name'],
            'price' => $price,
            'image' => $details['image_url'],
            'qty' => $qty,
            'role' => $role,
            'salePrice' => round($salePrice, 2),
        ];
    }

    /**
     * Legacy wrapper for importFunnel.
     */
    public static function import(array $data, string $mode = self::MODE_SKIP): array
    {
        return self::importFunnel($data, $mode);
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
            self::updateMeta($postId, 'funnel_status', $funnel['status'] ?? null);
            self::updateMeta($postId, 'stripe_mode', $funnel['stripe_mode'] ?? null);
            return;
        }

        update_field('funnel_slug', $funnel['slug'], $postId);
        update_field('funnel_status', $funnel['status'] ?? null, $postId);
        update_field('stripe_mode', $funnel['stripe_mode'] ?? null, $postId);
        // Round 2
        update_field('enable_scroll_navigation', !empty($funnel['enable_scroll_navigation']), $postId);
    }

    /**
     * Import header section.
     */
    private static function importHeader(int $postId, array $header): void
    {
        if (empty($header)) return;

        self::setField($postId, 'header_logo', $header['logo'] ?? null);
        self::setUrlField($postId, 'header_logo_link', $header['logo_link'] ?? null);
        self::setField($postId, 'header_sticky', !empty($header['sticky']));
        self::setField($postId, 'header_transparent', !empty($header['transparent']));
        
        if (!empty($header['nav_items'])) {
            self::setField($postId, 'header_nav_items', array_map(function($item) {
                return [
                    'label' => $item['label'] ?? null,
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

        self::setField($postId, 'hero_title', $hero['title'] ?? null);
        self::setField($postId, 'hero_subtitle', $hero['subtitle'] ?? null);
        self::setField($postId, 'hero_tagline', $hero['tagline'] ?? null);
        self::setField($postId, 'hero_description', $hero['description'] ?? null);
        self::setUrlField($postId, 'hero_image', $hero['image'] ?? null);
        self::setField($postId, 'hero_image_alt', $hero['image_alt'] ?? null);
        self::setUrlField($postId, 'hero_logo', $hero['logo'] ?? null);
        self::setUrlField($postId, 'hero_logo_link', $hero['logo_link'] ?? null);
        self::setField($postId, 'hero_cta_text', $hero['cta_text'] ?? null);
    }

    /**
     * Import benefits section.
     */
    private static function importBenefits(int $postId, array $benefits): void
    {
        if (empty($benefits)) return;

        self::setField($postId, 'hero_benefits_title', $benefits['title'] ?? null);
        self::setField($postId, 'hero_benefits_subtitle', $benefits['subtitle'] ?? null); // Round 2
        self::setField($postId, 'enable_benefit_categories', !empty($benefits['enable_categories'])); // Round 2
        
        if (!empty($benefits['items'])) {
            self::setField($postId, 'hero_benefits', array_map(function($item) {
                return [
                    'text' => $item['text'] ?? $item['benefit_text'] ?? null, // Fallback for old format
                    'icon' => $item['icon'] ?? null,
                    'category' => $item['category'] ?? null, // Round 2
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

        self::setField($postId, 'features_title', $features['title'] ?? null);
        self::setField($postId, 'features_subtitle', $features['subtitle'] ?? null);
        
        if (!empty($features['items'])) {
            self::setField($postId, 'features_list', array_map(function($item) {
                return [
                    'icon' => $item['icon'] ?? null,
                    'title' => $item['title'] ?? $item['feature_title'] ?? null, // Fallback for old format
                    'description' => $item['description'] ?? $item['feature_description'] ?? null, // Fallback for old format
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

        self::setField($postId, 'authority_title', $authority['title'] ?? null);
        self::setField($postId, 'authority_subtitle', $authority['subtitle'] ?? null);
        self::setField($postId, 'authority_name', $authority['name'] ?? null);
        self::setField($postId, 'authority_credentials', $authority['credentials'] ?? null);
        self::setField($postId, 'authority_image', $authority['image'] ?? null);
        self::setField($postId, 'authority_image_alt', $authority['image_alt'] ?? null);
        self::setField($postId, 'authority_bio', $authority['bio'] ?? null);
        
        // Simple quotes (flat list)
        if (!empty($authority['quotes'])) {
            self::setField($postId, 'authority_quotes', array_map(function($q) {
                return ['text' => is_string($q) ? $q : ($q['text'] ?? null)];
            }, $authority['quotes']));
        }

        // Quote categories (grouped)
        if (!empty($authority['quote_categories'])) {
            self::setField($postId, 'authority_quote_categories', array_map(function($cat) {
                return [
                    'title' => $cat['title'] ?? null,
                    'quotes' => implode("\n", $cat['quotes'] ?? []),
                ];
            }, $authority['quote_categories']));
        }

        // Article link
        if (!empty($authority['article_link'])) {
            self::setField($postId, 'authority_article_text', $authority['article_link']['text'] ?? null);
            self::setUrlField($postId, 'authority_article_url', $authority['article_link']['url'] ?? null);
        }
    }

    /**
     * Import testimonials section.
     */
    private static function importTestimonials(int $postId, array $testimonials): void
    {
        if (empty($testimonials)) return;

        self::setField($postId, 'testimonials_title', $testimonials['title'] ?? null);
        self::setField($postId, 'testimonials_subtitle', $testimonials['subtitle'] ?? null);
        
        if (!empty($testimonials['items'])) {
            self::setField($postId, 'testimonials_list', array_map(function($t) {
                return [
                    'name' => $t['name'] ?? null,
                    'role' => $t['role'] ?? null,
                    'title' => $t['title'] ?? null,
                    'quote' => $t['quote'] ?? null,
                    'image' => $t['image'] ?? null,
                    'rating' => $t['rating'] ?? null,
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

        self::setField($postId, 'science_title', $science['title'] ?? null);
        self::setField($postId, 'science_subtitle', $science['subtitle'] ?? null);
        
        if (!empty($science['sections'])) {
            self::setField($postId, 'science_sections', array_map(function($s) {
                return [
                    'title' => $s['title'] ?? null,
                    'description' => $s['description'] ?? null,
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

        self::setField($postId, 'faq_title', $faq['title'] ?? null);
        
        if (!empty($faq['items'])) {
            self::setField($postId, 'faq_list', array_map(function($f) {
                return [
                    'question' => $f['question'] ?? null,
                    'answer' => $f['answer'] ?? null,
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

        self::setField($postId, 'cta_title', $cta['title'] ?? null);
        self::setField($postId, 'cta_subtitle', $cta['subtitle'] ?? null);
        self::setField($postId, 'cta_button_text', $cta['button_text'] ?? null);
        self::setUrlField($postId, 'cta_button_url', $cta['button_url'] ?? null);
    }

    /**
     * Import checkout section.
     */
    private static function importCheckout(int $postId, array $checkout): void
    {
        if (empty($checkout)) return;

        self::setUrlField($postId, 'checkout_url', $checkout['url'] ?? null);
        self::setField($postId, 'free_shipping_countries', $checkout['free_shipping_countries'] ?? null);
        self::setField($postId, 'global_discount_percent', $checkout['global_discount_percent'] ?? null);
        self::setField($postId, 'enable_points_redemption', $checkout['enable_points_redemption'] ?? null);
        self::setField($postId, 'show_order_summary', $checkout['show_order_summary'] ?? null);
        // Round 2 additions
        self::setField($postId, 'checkout_page_title', $checkout['page_title'] ?? null);
        self::setField($postId, 'checkout_page_subtitle', $checkout['page_subtitle'] ?? null);
        self::setField($postId, 'checkout_tos_page_id', $checkout['tos_page_id'] ?? null);
        self::setField($postId, 'checkout_privacy_page_id', $checkout['privacy_page_id'] ?? null);
    }

    /**
     * Import SEO section (mapped to Yoast).
     */
    private static function importSeo(int $postId, array $seo): void
    {
        if (empty($seo)) return;
        FunnelSeoService::setSeoMeta($postId, $seo);
    }

    /**
     * Import thank you section.
     */
    private static function importThankYou(int $postId, array $thankyou): void
    {
        if (empty($thankyou)) return;

        self::setUrlField($postId, 'thankyou_url', $thankyou['url'] ?? null);
        self::setField($postId, 'thankyou_headline', $thankyou['headline'] ?? null);
        self::setField($postId, 'thankyou_message', $thankyou['message'] ?? null);
        self::setField($postId, 'show_upsell', !empty($thankyou['show_upsell']));
        
        if (!empty($thankyou['upsell']) && !empty($thankyou['upsell']['sku'])) {
            self::setField($postId, 'upsell_config', [
                'sku' => $thankyou['upsell']['sku'],
                'qty' => $thankyou['upsell']['qty'] ?? null,
                'discount_percent' => $thankyou['upsell']['discount_percent'] ?? null,
                'headline' => $thankyou['upsell']['headline'] ?? null,
                'description' => $thankyou['upsell']['description'] ?? null,
                'image' => $thankyou['upsell']['image'] ?? null,
            ]);
        }
    }

    /**
     * Import styling section.
     */
    private static function importStyling(int $postId, array $styling): void
    {
        if (empty($styling)) return;

        $accentColor = $styling['accent_color'] ?? null;
        $textAccent = $styling['text_color_accent'] ?? $accentColor;
        
        // Check if text accent differs from global accent (means override)
        $hasOverride = ($accentColor !== null && $textAccent !== $accentColor);
        
        self::setField($postId, 'accent_color', $accentColor);
        // Text colors
        self::setField($postId, 'text_color_basic', $styling['text_color_basic'] ?? null);
        self::setField($postId, 'text_color_accent_override', $hasOverride ? 1 : 0);
        if ($hasOverride) {
            self::setField($postId, 'text_color_accent', $textAccent);
        }
        self::setField($postId, 'text_color_note', $styling['text_color_note'] ?? null);
        self::setField($postId, 'text_color_discount', $styling['text_color_discount'] ?? null);
        // UI element colors
        self::setField($postId, 'page_bg_color', $styling['page_bg_color'] ?? null);
        self::setField($postId, 'card_bg_color', $styling['card_bg_color'] ?? null);
        self::setField($postId, 'input_bg_color', $styling['input_bg_color'] ?? null);
        self::setField($postId, 'border_color', $styling['border_color'] ?? null);
        // Background type settings
        self::setField($postId, 'background_type', $styling['background_type'] ?? null);
        self::setField($postId, 'background_image', $styling['background_image'] ?? null);
        self::setField($postId, 'custom_css', $styling['custom_css'] ?? null);

        // NEW: Section background mode (replaces alternate_section_bg)
        // Handle legacy imports: if old format, convert to new format
        if (isset($styling['alternate_section_bg']) && !isset($styling['section_background_mode'])) {
            // Legacy import - convert to new structure
            if (!empty($styling['alternate_section_bg'])) {
                self::setField($postId, 'section_background_mode', 'alternating');
                self::setField($postId, 'alternating_type', 'solid');
                self::setField($postId, 'alternating_solid_color', $styling['alternate_bg_color'] ?? '#1a1a2e');
            } else {
                self::setField($postId, 'section_background_mode', 'solid');
            }
        } else {
            // New format - import all gradient fields
            self::setField($postId, 'section_background_mode', $styling['section_background_mode'] ?? 'solid');

            // Alternating mode fields
            self::setField($postId, 'alternating_type', $styling['alternating_type'] ?? null);
            self::setField($postId, 'alternating_solid_color', $styling['alternating_solid_color'] ?? null);
            self::setField($postId, 'alternating_gradient_type', $styling['alternating_gradient_type'] ?? null);
            self::setField($postId, 'alternating_gradient_preset', $styling['alternating_gradient_preset'] ?? null);
            self::setField($postId, 'alternating_gradient_color_mode', $styling['alternating_gradient_color_mode'] ?? null);
            self::setField($postId, 'alternating_gradient_start_color', $styling['alternating_gradient_start_color'] ?? null);
            self::setField($postId, 'alternating_gradient_end_color', $styling['alternating_gradient_end_color'] ?? null);

            // All gradient mode fields
            self::setField($postId, 'all_gradient_default_type', $styling['all_gradient_default_type'] ?? null);
            self::setField($postId, 'all_gradient_default_preset', $styling['all_gradient_default_preset'] ?? null);
            self::setField($postId, 'all_gradient_default_color_mode', $styling['all_gradient_default_color_mode'] ?? null);
            self::setField($postId, 'all_gradient_default_start_color', $styling['all_gradient_default_start_color'] ?? null);
            self::setField($postId, 'all_gradient_default_end_color', $styling['all_gradient_default_end_color'] ?? null);
            self::setField($postId, 'all_gradient_sections', $styling['all_gradient_sections'] ?? []);
        }
    }

    /**
     * Import footer section.
     */
    private static function importFooter(int $postId, array $footer): void
    {
        if (empty($footer)) return;

        self::setField($postId, 'footer_text', $footer['text'] ?? null);
        self::setField($postId, 'footer_disclaimer', $footer['disclaimer'] ?? null);
        
        if (!empty($footer['links'])) {
            self::setField($postId, 'footer_links', array_map(function($l) {
                return [
                    'label' => $l['label'] ?? null,
                    'url' => self::toAbsoluteUrl($l['url'] ?? ''),
                ];
            }, $footer['links']));
        }
    }

    /**
     * Import responsive section (v2.32.0).
     */
    private static function importResponsive(int $postId, array $responsive): void
    {
        if (empty($responsive)) return;

        // Breakpoint overrides
        if (!empty($responsive['breakpoint_overrides'])) {
            self::setField($postId, 'responsive_breakpoint_override', true);
            if (!empty($responsive['breakpoints'])) {
                self::setField($postId, 'responsive_breakpoint_tablet', $responsive['breakpoints']['tablet'] ?? 640);
                self::setField($postId, 'responsive_breakpoint_laptop', $responsive['breakpoints']['laptop'] ?? 1024);
                self::setField($postId, 'responsive_breakpoint_desktop', $responsive['breakpoints']['desktop'] ?? 1440);
            }
        }

        // Content max-width
        if (isset($responsive['content_max_width'])) {
            self::setField($postId, 'responsive_content_max_width', (int) $responsive['content_max_width']);
        }

        // Scroll settings
        if (!empty($responsive['scroll_settings'])) {
            $scroll = $responsive['scroll_settings'];
            if (isset($scroll['enable_smooth_scroll'])) {
                self::setField($postId, 'responsive_enable_smooth_scroll', (bool) $scroll['enable_smooth_scroll']);
            }
            if (isset($scroll['scroll_duration'])) {
                self::setField($postId, 'responsive_scroll_duration', (int) $scroll['scroll_duration']);
            }
            if (isset($scroll['scroll_easing'])) {
                self::setField($postId, 'responsive_scroll_easing', $scroll['scroll_easing']);
            }
            if (isset($scroll['enable_scroll_snap'])) {
                self::setField($postId, 'responsive_enable_scroll_snap', (bool) $scroll['enable_scroll_snap']);
            }
        }

        // Mobile settings
        if (!empty($responsive['mobile_settings'])) {
            $mobile = $responsive['mobile_settings'];
            if (isset($mobile['sticky_cta_enabled'])) {
                self::setField($postId, 'mobile_sticky_cta_enabled', (bool) $mobile['sticky_cta_enabled']);
            }
            if (isset($mobile['sticky_cta_text'])) {
                self::setField($postId, 'mobile_sticky_cta_text', $mobile['sticky_cta_text']);
            }
            if (isset($mobile['sticky_cta_target'])) {
                self::setField($postId, 'mobile_sticky_cta_target', $mobile['sticky_cta_target']);
            }
            if (isset($mobile['enable_skeleton_placeholders'])) {
                self::setField($postId, 'mobile_enable_skeleton_placeholders', (bool) $mobile['enable_skeleton_placeholders']);
            }
            if (isset($mobile['reduce_animations'])) {
                self::setField($postId, 'mobile_reduce_animations', (bool) $mobile['reduce_animations']);
            }
        }

        // Per-section settings
        if (!empty($responsive['sections'])) {
            $sections = $responsive['sections'];
            
            // Hero
            if (!empty($sections['hero'])) {
                $hero = $sections['hero'];
                if (isset($hero['height_behavior'])) {
                    self::setField($postId, 'responsive_hero_height_behavior', $hero['height_behavior']);
                }
                if (isset($hero['mobile_image_position'])) {
                    self::setField($postId, 'responsive_hero_mobile_image_position', $hero['mobile_image_position']);
                }
                if (isset($hero['mobile_title_size'])) {
                    self::setField($postId, 'responsive_hero_mobile_title_size', $hero['mobile_title_size']);
                }
            }

            // Infographics
            if (!empty($sections['infographics'])) {
                $info = $sections['infographics'];
                if (isset($info['height_behavior'])) {
                    self::setField($postId, 'responsive_infographics_height_behavior', $info['height_behavior']);
                }
                if (isset($info['mobile_mode'])) {
                    self::setField($postId, 'responsive_infographics_mobile_mode', $info['mobile_mode']);
                }
                if (isset($info['tablet_mode'])) {
                    self::setField($postId, 'responsive_infographics_tablet_mode', $info['tablet_mode']);
                }
                if (isset($info['desktop_mode'])) {
                    self::setField($postId, 'responsive_infographics_desktop_mode', $info['desktop_mode']);
                }
            }

            // Testimonials
            if (!empty($sections['testimonials'])) {
                $test = $sections['testimonials'];
                if (isset($test['height_behavior'])) {
                    self::setField($postId, 'responsive_testimonials_height_behavior', $test['height_behavior']);
                }
                if (isset($test['mobile_mode'])) {
                    self::setField($postId, 'responsive_testimonials_mobile_mode', $test['mobile_mode']);
                }
                if (isset($test['tablet_mode'])) {
                    self::setField($postId, 'responsive_testimonials_tablet_mode', $test['tablet_mode']);
                }
                if (isset($test['desktop_mode'])) {
                    self::setField($postId, 'responsive_testimonials_desktop_mode', $test['desktop_mode']);
                }
            }

            // Simple height behavior for other sections
            $simpleSections = ['products', 'benefits', 'features', 'authority', 'science', 'faq', 'cta'];
            foreach ($simpleSections as $sectionName) {
                if (!empty($sections[$sectionName]['height_behavior'])) {
                    self::setField($postId, "responsive_{$sectionName}_height_behavior", $sections[$sectionName]['height_behavior']);
                }
            }
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

