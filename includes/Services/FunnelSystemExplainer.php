<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service that provides comprehensive documentation of the funnel CPT system
 * for AI agents to understand how funnels work.
 */
class FunnelSystemExplainer
{
    /**
     * Get complete system documentation for AI agents.
     *
     * @return array Complete system documentation
     */
    public static function getSystemExplanation(): array
    {
        return [
            'overview' => 'HP Funnels are custom post types (hp-funnel) that define complete sales funnels with modular sections, product offers, and integrated checkout. Each funnel is a self-contained landing page with styling, content sections, offers, and a Stripe-powered checkout flow.',
            
            'cpt_structure' => self::getCptStructure(),
            'sections' => self::getSectionDocumentation(),
            'offer_types' => self::getOfferTypeDocumentation(),
            'styling' => self::getStylingDocumentation(),
            'checkout_flow' => self::getCheckoutFlowDocumentation(),
            'decision_points' => self::getDecisionPointsDocumentation(),
            'best_practices' => self::getBestPractices(),
        ];
    }

    /**
     * Get CPT structure documentation.
     */
    private static function getCptStructure(): array
    {
        return [
            'post_type' => 'hp-funnel',
            'slug_field' => 'funnel_slug',
            'url_pattern' => '/express-shop/{slug}/',
            'sub_routes' => [
                [
                    'pattern' => '/express-shop/{slug}/checkout/',
                    'purpose' => 'Checkout SPA - handles customer info, shipping, payment',
                    'shortcode' => '[hp_funnel_checkout_app]',
                ],
                [
                    'pattern' => '/express-shop/{slug}/thankyou/',
                    'purpose' => 'Thank you page with order confirmation and optional upsell',
                    'shortcode' => '[hp_funnel_thankyou]',
                ],
            ],
            'storage' => [
                'method' => 'ACF Pro fields stored in post meta',
                'export_format' => 'JSON conforming to hp-funnel/v1 schema',
                'caching' => 'Transient-based caching with automatic invalidation on save',
            ],
        ];
    }

    /**
     * Get documentation for all funnel sections.
     */
    private static function getSectionDocumentation(): array
    {
        return [
            'header' => [
                'purpose' => 'Logo and navigation bar at the top of the page',
                'shortcode' => '[hp_funnel_header]',
                'key_options' => ['sticky', 'transparent'],
                'required' => false,
                'acf_fields' => ['header_logo', 'header_logo_link', 'header_sticky', 'header_transparent', 'header_nav_items'],
            ],
            'hero' => [
                'purpose' => 'Main headline, value proposition, and primary CTA - the first thing visitors see',
                'shortcode' => '[hp_funnel_hero_section]',
                'key_options' => ['image_position', 'text_align', 'min_height'],
                'required' => true,
                'decision_points' => [
                    'title_style' => 'benefit-focused, problem-focused, or curiosity-driven',
                    'subtitle_vs_tagline' => 'Use subtitle for expansion, tagline for brevity',
                    'image_style' => 'Product photo, lifestyle image, or abstract',
                ],
                'acf_fields' => ['hero_title', 'hero_subtitle', 'hero_tagline', 'hero_description', 'hero_image', 'hero_cta_text'],
                'content_guidelines' => [
                    'title' => '3-8 words, action-oriented, compelling',
                    'subtitle' => '5-12 words, expands on title',
                    'cta_text' => '3-6 words, action verb + urgency',
                ],
            ],
            'benefits' => [
                'purpose' => 'Grid of benefit statements with icons - builds value proposition',
                'shortcode' => '[hp_funnel_benefits]',
                'key_options' => ['columns', 'show_cards', 'default_icon'],
                'recommended_count' => '6-12 items',
                'required' => false,
                'acf_fields' => ['benefits_title', 'benefits_items'],
                'content_guidelines' => [
                    'item_text' => '5-15 words each, specific outcomes not generic claims',
                    'icons' => 'check, star, shield, heart - match to benefit type',
                ],
            ],
            'products' => [
                'purpose' => 'Product/offer showcase with pricing - uses offers from funnel config',
                'shortcode' => '[hp_funnel_products]',
                'key_options' => ['layout'],
                'required' => true,
                'note' => 'Displays offers defined in the offers section, not direct products',
                'acf_fields' => ['offers'],
            ],
            'features' => [
                'purpose' => 'Detailed feature cards with icons, titles, and descriptions',
                'shortcode' => '[hp_funnel_features]',
                'key_options' => ['columns', 'layout'],
                'best_for' => 'Technical/scientific products requiring detailed explanation',
                'recommended_count' => '4-6 items',
                'acf_fields' => ['features_title', 'features_subtitle', 'features_items'],
            ],
            'authority' => [
                'purpose' => 'Expert bio, credentials, and quotes - builds trust and credibility',
                'shortcode' => '[hp_funnel_authority]',
                'key_options' => ['layout'],
                'best_for' => 'Products with expert endorsement or scientific backing',
                'acf_fields' => ['authority_title', 'authority_name', 'authority_credentials', 'authority_image', 'authority_bio', 'authority_quotes'],
            ],
            'science' => [
                'purpose' => 'Scientific/technical explanation sections',
                'shortcode' => '[hp_funnel_science]',
                'best_for' => 'Supplements, health products, technical products',
                'acf_fields' => ['science_title', 'science_subtitle', 'science_sections'],
                'content_guidelines' => [
                    'style' => 'Accessible scientific language, cite mechanisms not just claims',
                    'sections' => '2-4 distinct topics with bullets',
                ],
            ],
            'testimonials' => [
                'purpose' => 'Customer reviews and social proof',
                'shortcode' => '[hp_funnel_testimonials]',
                'key_options' => ['columns', 'layout', 'show_ratings'],
                'recommended_count' => '3-6 testimonials',
                'note' => 'Should be real testimonials, NOT AI-generated fake reviews',
                'acf_fields' => ['testimonials_title', 'testimonials_items'],
            ],
            'faq' => [
                'purpose' => 'Accordion of frequently asked questions - addresses objections',
                'shortcode' => '[hp_funnel_faq]',
                'recommended_count' => '4-8 questions',
                'acf_fields' => ['faq_title', 'faq_items'],
                'content_guidelines' => [
                    'derive_from' => 'Common objections, usage questions, ingredient queries, shipping/return policies',
                ],
            ],
            'cta' => [
                'purpose' => 'Secondary call-to-action block - reinforces main offer',
                'shortcode' => '[hp_funnel_cta]',
                'key_options' => ['alignment', 'background'],
                'acf_fields' => ['cta_title', 'cta_subtitle', 'cta_button_text', 'cta_button_url'],
            ],
            'footer' => [
                'purpose' => 'Disclaimer, copyright, and legal links',
                'shortcode' => '[hp_funnel_footer]',
                'required' => true,
                'acf_fields' => ['footer_text', 'footer_disclaimer', 'footer_links'],
                'note' => 'FDA disclaimer required for supplement products',
            ],
        ];
    }

    /**
     * Get documentation for offer types.
     */
    private static function getOfferTypeDocumentation(): array
    {
        return [
            'single' => [
                'description' => 'Single product with optional quantity and discount',
                'fields' => ['product_sku', 'quantity', 'discount_type', 'discount_value'],
                'use_case' => 'Simple product offerings, entry-level options',
                'example' => [
                    'id' => 'offer-small',
                    'name' => 'Small Bottle (0.5 oz)',
                    'type' => 'single',
                    'product_sku' => 'ILL-SMALL',
                    'quantity' => 1,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                ],
            ],
            'fixed_bundle' => [
                'description' => 'Pre-configured set of products sold together',
                'fields' => ['bundle_items[]', 'discount_type', 'discount_value'],
                'use_case' => 'Value packs, starter kits, protocol bundles',
                'pricing' => 'Sum of individual products with overall discount applied',
                'example' => [
                    'id' => 'offer-bundle',
                    'name' => '90-Day Supply Kit',
                    'type' => 'fixed_bundle',
                    'badge' => 'BEST VALUE',
                    'is_featured' => true,
                    'discount_label' => 'Save 20%',
                    'discount_type' => 'percent',
                    'discount_value' => 20,
                    'bundle_items' => [
                        ['sku' => 'ILL-LARGE', 'qty' => 2],
                        ['sku' => 'SEL-200MCG', 'qty' => 1],
                    ],
                ],
            ],
            'customizable_kit' => [
                'description' => 'Customer picks products within constraints',
                'fields' => ['kit_products[]', 'max_total_items'],
                'product_roles' => [
                    'must' => 'Required in kit, minimum quantity 1 - cannot be removed',
                    'optional' => 'Can be added, minimum quantity 0 - customer chooses',
                ],
                'use_case' => 'Build-your-own bundles, flexible protocol kits',
                'pricing' => 'Sum of selected products with per-product or overall discounts',
                'example' => [
                    'id' => 'offer-kit',
                    'name' => 'Build Your Kit',
                    'type' => 'customizable_kit',
                    'badge' => 'CUSTOMIZE',
                    'discount_label' => 'Save up to 30%',
                    'max_total_items' => 6,
                    'kit_products' => [
                        ['sku' => 'ILL-SMALL', 'role' => 'must', 'qty' => 1, 'max_qty' => 3, 'discount_type' => 'percent', 'discount_value' => 15],
                        ['sku' => 'ILL-LARGE', 'role' => 'optional', 'qty' => 0, 'max_qty' => 2, 'discount_type' => 'percent', 'discount_value' => 20],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get styling documentation.
     */
    private static function getStylingDocumentation(): array
    {
        return [
            'approach' => 'CSS custom properties injected via [hp_funnel_styles] shortcode at page top',
            'key_colors' => [
                'accent_color' => [
                    'purpose' => 'Primary brand color for buttons, badges, and highlights',
                    'format' => 'hex',
                    'default' => '#eab308',
                    'usage' => ['buttons', 'badges', 'icons', 'links'],
                ],
                'text_color_basic' => [
                    'purpose' => 'Main body text color',
                    'format' => 'hex',
                    'default' => '#e5e5e5',
                    'note' => 'Should contrast well with page_bg_color',
                ],
                'text_color_accent' => [
                    'purpose' => 'Highlighted/emphasized text',
                    'format' => 'hex',
                    'default' => 'inherits from accent_color',
                ],
                'text_color_note' => [
                    'purpose' => 'Muted text for descriptions and secondary content',
                    'format' => 'hex',
                    'default' => '#a3a3a3',
                ],
                'text_color_discount' => [
                    'purpose' => 'Savings/discount highlights',
                    'format' => 'hex',
                    'default' => '#22c55e',
                    'note' => 'Typically green to indicate positive savings',
                ],
                'page_bg_color' => [
                    'purpose' => 'Page background color',
                    'format' => 'hex',
                    'default' => '#121212',
                ],
                'card_bg_color' => [
                    'purpose' => 'Card/panel background color',
                    'format' => 'hex',
                    'default' => '#1a1a1a',
                    'note' => 'Should be slightly lighter than page_bg for depth',
                ],
                'input_bg_color' => [
                    'purpose' => 'Form input background color',
                    'format' => 'hex',
                    'default' => '#333333',
                ],
                'border_color' => [
                    'purpose' => 'Accent borders and dividers',
                    'format' => 'hex',
                    'default' => '#7c3aed',
                ],
            ],
            'theme_presets' => [
                'dark_gold' => [
                    'description' => 'Premium dark theme with gold accents',
                    'accent_color' => '#eab308',
                    'page_bg_color' => '#121212',
                    'card_bg_color' => '#1a1a1a',
                    'text_color_basic' => '#e5e5e5',
                ],
                'dark_purple' => [
                    'description' => 'Modern dark theme with purple accents',
                    'accent_color' => '#7c3aed',
                    'page_bg_color' => '#0f0f1a',
                    'card_bg_color' => '#1a1a2e',
                    'text_color_basic' => '#e5e5e5',
                ],
                'dark_green' => [
                    'description' => 'Natural dark theme with green accents',
                    'accent_color' => '#22c55e',
                    'page_bg_color' => '#0f1a0f',
                    'card_bg_color' => '#1a2e1a',
                    'text_color_basic' => '#e5e5e5',
                ],
                'light_blue' => [
                    'description' => 'Clean light theme with blue accents',
                    'accent_color' => '#3b82f6',
                    'page_bg_color' => '#f8fafc',
                    'card_bg_color' => '#ffffff',
                    'text_color_basic' => '#1e293b',
                ],
            ],
            'recommendations' => [
                'Dark themes recommended for premium/health products',
                'Match accent color to product branding/label',
                'Ensure sufficient contrast between text and background colors',
                'Use border_color sparingly for emphasis',
            ],
        ];
    }

    /**
     * Get checkout flow documentation.
     */
    private static function getCheckoutFlowDocumentation(): array
    {
        return [
            'type' => 'Single-page checkout app (SPA)',
            'shortcode' => '[hp_funnel_checkout_app]',
            'steps' => [
                [
                    'name' => 'customer_info',
                    'description' => 'Email, name, and address collection with returning customer lookup',
                ],
                [
                    'name' => 'shipping',
                    'description' => 'Shipping address and method selection with live rate calculation',
                ],
                [
                    'name' => 'payment',
                    'description' => 'Stripe Elements integration with optional loyalty points redemption',
                ],
                [
                    'name' => 'processing',
                    'description' => 'Order processing animation while payment completes',
                ],
                [
                    'name' => 'upsell',
                    'description' => 'Optional one-click upsell offer (if configured)',
                ],
                [
                    'name' => 'thankyou',
                    'description' => 'Order confirmation with details and next steps',
                ],
            ],
            'payment_integration' => [
                'provider' => 'Stripe',
                'method' => 'Stripe Elements with Payment Intents API',
                'pci_compliance' => 'Card data never touches WordPress server',
            ],
            'features' => [
                'Returning customer email lookup with auto-fill',
                'Real-time shipping rate calculation via ShipStation',
                'Loyalty points redemption (if customer has points)',
                'Order notes field',
                'Address validation',
            ],
        ];
    }

    /**
     * Get decision points documentation for AI agents.
     */
    private static function getDecisionPointsDocumentation(): array
    {
        return [
            'philosophy' => 'AI agents should present choices at key decision points rather than making autonomous decisions. This keeps the admin in control while leveraging AI for speed and suggestions.',
            'categories' => [
                'offer_structure' => [
                    'kit_type' => [
                        'options' => ['fixed_bundle', 'customizable_kit', 'tiered_singles'],
                        'considerations' => 'Protocol kits work best as fixed bundles, flexible products as customizable kits',
                    ],
                    'discount_strategy' => [
                        'options' => ['percent_off', 'fixed_discount', 'tiered_pricing'],
                        'considerations' => 'Percent off is easier to communicate, fixed discount better for high-value items',
                    ],
                    'featured_offer' => [
                        'options' => ['best_value', 'most_popular', 'none'],
                        'considerations' => 'Feature the offer with best margin that still provides customer value',
                    ],
                ],
                'content' => [
                    'headline_style' => [
                        'options' => ['benefit-focused', 'problem-focused', 'curiosity-driven'],
                        'examples' => [
                            'benefit-focused' => 'Transform Your Health Today',
                            'problem-focused' => 'Tired of Low Energy?',
                            'curiosity-driven' => 'The Secret to Optimal Thyroid Function',
                        ],
                    ],
                    'tone' => [
                        'options' => ['professional', 'conversational', 'scientific', 'urgent'],
                        'considerations' => 'Match tone to target audience and product type',
                    ],
                    'sections_to_include' => [
                        'minimal' => ['header', 'hero', 'products', 'footer'],
                        'standard' => ['header', 'hero', 'benefits', 'products', 'testimonials', 'faq', 'footer'],
                        'comprehensive' => 'All sections including science, authority, features',
                    ],
                ],
                'layout' => [
                    'benefits_columns' => ['2', '3', '4'],
                    'testimonials_style' => ['cards', 'carousel', 'simple_list'],
                    'hero_image_position' => ['right', 'left', 'background'],
                ],
                'styling' => [
                    'theme_direction' => ['dark', 'light', 'match_product_branding'],
                    'accent_color' => 'Extract from product label or use presets',
                ],
                'pricing' => [
                    'discount_level' => ['10%', '15%', '20%', '25%', 'custom'],
                    'free_shipping_threshold' => 'Domestic orders over threshold get free shipping',
                ],
            ],
        ];
    }

    /**
     * Get best practices for funnel creation.
     */
    private static function getBestPractices(): array
    {
        return [
            'content' => [
                'Keep hero title under 8 words',
                'Use specific benefit statements, not generic claims',
                'Include social proof (testimonials) for higher conversion',
                'Address top 4-6 objections in FAQ',
                'Never generate fake testimonials - use placeholders if none available',
            ],
            'offers' => [
                'Always have at least 2 offers for comparison effect',
                'Feature the best-value offer (not necessarily cheapest)',
                'Use clear discount badges (e.g., "Save 25%")',
                'Ensure all offers meet minimum profit margins',
            ],
            'styling' => [
                'Match funnel colors to product branding',
                'Dark themes convert better for premium/health products',
                'Ensure CTA buttons have high contrast with background',
                'Use consistent styling across all sections',
            ],
            'checkout' => [
                'Keep checkout form simple - only essential fields',
                'Show order summary throughout checkout',
                'Display trust signals (secure checkout, money-back guarantee)',
            ],
        ];
    }
}


















