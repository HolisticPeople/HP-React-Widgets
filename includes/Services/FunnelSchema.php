<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for defining and validating funnel JSON schema.
 * 
 * Provides schema definitions for AI agents and validation for imports.
 */
class FunnelSchema
{
    /**
     * Schema version.
     */
    public const VERSION = 'hp-funnel/v1';

    /**
     * Get the complete JSON schema definition.
     *
     * @return array Schema definition
     */
    public static function getSchema(): array
    {
        return [
            '$schema' => self::VERSION,
            'type' => 'object',
            'required' => ['funnel'],
            'properties' => [
                'funnel' => [
                    'type' => 'object',
                    'description' => 'Core funnel identity and settings',
                    'required' => ['name', 'slug'],
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Display name of the funnel'],
                        'slug' => ['type' => 'string', 'description' => 'URL-safe identifier, lowercase with hyphens'],
                        'status' => ['type' => 'string', 'enum' => ['active', 'inactive'], 'default' => 'active'],
                        'stripe_mode' => ['type' => 'string', 'enum' => ['auto', 'live', 'test'], 'default' => 'auto'],
                    ],
                ],
                'seo' => [
                    'type' => 'object',
                    'description' => 'Yoast SEO metadata for the funnel',
                    'properties' => [
                        'focus_keyword' => ['type' => 'string', 'description' => 'Primary keyword for SEO analysis'],
                        'meta_title' => ['type' => 'string', 'description' => 'Custom SEO title (optional)'],
                        'meta_description' => ['type' => 'string', 'description' => 'Meta description for search results'],
                        'cornerstone_content' => ['type' => 'boolean', 'default' => false, 'description' => 'Mark as cornerstone content'],
                    ],
                ],
                'header' => [
                    'type' => 'object',
                    'description' => 'Header section with logo and navigation',
                    'properties' => [
                        'logo' => ['type' => 'string', 'description' => 'URL to logo image'],
                        'logo_link' => ['type' => 'string', 'description' => 'URL the logo links to'],
                        'sticky' => ['type' => 'boolean', 'default' => false],
                        'transparent' => ['type' => 'boolean', 'default' => false],
                        'nav_items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'label' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                    'is_external' => ['type' => 'boolean', 'default' => false],
                                ],
                            ],
                        ],
                    ],
                ],
                'hero' => [
                    'type' => 'object',
                    'description' => 'Hero section with headline, image, and call-to-action',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Main headline, 3-8 words, compelling'],
                        'title_size' => ['type' => 'string', 'enum' => ['sm', 'md', 'lg', 'xl', '2xl'], 'default' => 'lg'],
                        'subtitle' => ['type' => 'string', 'description' => 'Secondary headline'],
                        'tagline' => ['type' => 'string', 'description' => 'Short tagline or value proposition'],
                        'description' => ['type' => 'string', 'description' => 'Longer description paragraph'],
                        'image' => ['type' => 'string', 'description' => 'URL to hero image'],
                        'image_alt' => ['type' => 'string', 'description' => 'ALT text for the hero image (SEO critical)'],
                        'logo' => ['type' => 'string', 'description' => 'Override logo for hero section'],
                        'logo_link' => ['type' => 'string'],
                        'cta_text' => ['type' => 'string', 'description' => 'Call-to-action button text', 'default' => 'Get Your Special Offer Now'],
                    ],
                ],
                'benefits' => [
                    'type' => 'object',
                    'description' => 'Benefits section configuration',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'Why Choose Us?'],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of benefit items, recommend 6-12',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => ['type' => 'string', 'description' => 'Benefit text, 5-15 words'],
                                    'icon' => ['type' => 'string', 'enum' => ['check', 'star', 'shield', 'heart'], 'default' => 'check'],
                                ],
                            ],
                        ],
                    ],
                ],
                'offers' => [
                    'type' => 'array',
                    'description' => 'Offers available in this funnel (single products, bundles, or customizable kits)',
                    'items' => [
                        'type' => 'object',
                        'required' => ['id', 'name', 'type'],
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'Unique offer identifier'],
                            'name' => ['type' => 'string', 'description' => 'Display name for the offer'],
                            'description' => ['type' => 'string', 'description' => 'Optional offer description'],
                            'type' => ['type' => 'string', 'enum' => ['single', 'fixed_bundle', 'customizable_kit'], 'description' => 'Offer type'],
                            'badge' => ['type' => 'string', 'description' => 'Badge text like "BEST VALUE" or "20% OFF"'],
                            'is_featured' => ['type' => 'boolean', 'default' => false, 'description' => 'Highlight this offer'],
                            'image' => ['type' => 'string', 'description' => 'Override image URL'],
                            'image_alt' => ['type' => 'string', 'description' => 'ALT text for the product image (SEO critical)'],
                            'discount_label' => ['type' => 'string', 'description' => 'Marketing label shown to customer (e.g., "Save 25%")'],
                            'discount_type' => ['type' => 'string', 'enum' => ['none', 'percent', 'fixed'], 'default' => 'none'],
                            'discount_value' => ['type' => 'number', 'default' => 0, 'description' => 'Discount amount (% or $)'],
                            // Single product fields
                            'product_sku' => ['type' => 'string', 'description' => 'Product SKU (for single type)'],
                            'quantity' => ['type' => 'integer', 'default' => 1, 'description' => 'Quantity (for single type)'],
                            // Fixed bundle fields
                            'bundle_items' => [
                                'type' => 'array',
                                'description' => 'Products in the bundle (for fixed_bundle type)',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'sku' => ['type' => 'string'],
                                        'qty' => ['type' => 'integer', 'default' => 1],
                                    ],
                                ],
                            ],
                            // Customizable kit fields
                            'kit_products' => [
                                'type' => 'array',
                                'description' => 'Products available in the kit (for customizable_kit type)',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'sku' => ['type' => 'string'],
                                        'role' => ['type' => 'string', 'enum' => ['must', 'optional']],  // must=min qty 1, optional=min qty 0
                                        'qty' => ['type' => 'integer', 'default' => 1, 'description' => 'Default/starting quantity'],
                                        'max_qty' => ['type' => 'integer', 'default' => 3, 'description' => 'Max quantity allowed'],
                                        'discount_type' => ['type' => 'string', 'enum' => ['none', 'percent', 'fixed'], 'default' => 'none'],
                                        'discount_value' => ['type' => 'number', 'default' => 0, 'description' => 'Per-product discount'],
                                    ],
                                ],
                            ],
                            'max_total_items' => ['type' => 'integer', 'description' => 'Max items in kit (0 = no limit)'],
                        ],
                    ],
                ],
                'features' => [
                    'type' => 'object',
                    'description' => 'Features section with detailed feature cards',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'Key Features'],
                        'subtitle' => ['type' => 'string'],
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'icon' => ['type' => 'string', 'enum' => ['check', 'star', 'shield', 'heart', 'bolt', 'leaf']],
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
                'authority' => [
                    'type' => 'object',
                    'description' => '"Who We Are" section with expert bio and credentials',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'Who We Are'],
                        'subtitle' => ['type' => 'string'],
                        'name' => ['type' => 'string', 'description' => 'Expert/authority name'],
                        'credentials' => ['type' => 'string', 'description' => 'Credentials and titles'],
                        'image' => ['type' => 'string', 'description' => 'Photo URL'],
                        'image_alt' => ['type' => 'string', 'description' => 'ALT text for the authority image (SEO critical)'],
                        'bio' => ['type' => 'string', 'description' => 'Biography, can include HTML'],
                        'quotes' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'text' => ['type' => 'string'],
                                ],
                            ],
                        ],
                        'quote_categories' => [
                            'type' => 'array',
                            'description' => 'Grouped quotes by category (e.g., "On Detoxification")',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'quotes' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                        'article_link' => [
                            'type' => 'object',
                            'properties' => [
                                'text' => ['type' => 'string'],
                                'url' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'science' => [
                    'type' => 'object',
                    'description' => 'Scientific/technical information section',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'The Science Behind Our Product'],
                        'subtitle' => ['type' => 'string'],
                        'sections' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                    'bullets' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ],
                'testimonials' => [
                    'type' => 'object',
                    'description' => 'Customer testimonials section',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'What Our Customers Say'],
                        'subtitle' => ['type' => 'string'],
                        'display_mode' => ['type' => 'string', 'enum' => ['grid', 'carousel', 'list'], 'default' => 'grid'],
                        'columns' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4, 'default' => 3],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of testimonials, recommend 3-6',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'role' => ['type' => 'string', 'description' => 'Role or location'],
                                    'title' => ['type' => 'string', 'description' => 'Review title like "Excellent!"'],
                                    'quote' => ['type' => 'string'],
                                    'image' => ['type' => 'string'],
                                    'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'default' => 5],
                                ],
                            ],
                        ],
                    ],
                ],
                'faq' => [
                    'type' => 'object',
                    'description' => 'FAQ accordion section',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'Frequently Asked Questions'],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Array of Q&A pairs, recommend 4-8',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'question' => ['type' => 'string'],
                                    'answer' => ['type' => 'string', 'description' => 'Can include HTML'],
                                ],
                            ],
                        ],
                    ],
                ],
                'cta' => [
                    'type' => 'object',
                    'description' => 'Secondary call-to-action section',
                    'properties' => [
                        'title' => ['type' => 'string', 'default' => 'Ready to Get Started?'],
                        'subtitle' => ['type' => 'string'],
                        'button_text' => ['type' => 'string', 'default' => 'Order Now'],
                        'button_url' => ['type' => 'string', 'description' => 'Leave empty to use checkout URL'],
                    ],
                ],
                'checkout' => [
                    'type' => 'object',
                    'description' => 'Checkout configuration',
                    'properties' => [
                        'url' => ['type' => 'string', 'default' => '/checkout/'],
                        'free_shipping_countries' => ['type' => 'string', 'description' => 'Comma-separated country codes', 'default' => 'US'],
                        'global_discount_percent' => ['type' => 'number', 'default' => 0],
                        'enable_points_redemption' => ['type' => 'boolean', 'default' => true],
                        'show_order_summary' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'thankyou' => [
                    'type' => 'object',
                    'description' => 'Thank you page configuration',
                    'properties' => [
                        'url' => ['type' => 'string', 'default' => '/thank-you/'],
                        'headline' => ['type' => 'string', 'default' => 'Thank You for Your Order!'],
                        'message' => ['type' => 'string'],
                        'show_upsell' => ['type' => 'boolean', 'default' => false],
                        'upsell' => [
                            'type' => 'object',
                            'properties' => [
                                'sku' => ['type' => 'string'],
                                'qty' => ['type' => 'integer', 'default' => 1],
                                'discount_percent' => ['type' => 'number'],
                                'headline' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'image' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'styling' => [
                    'type' => 'object',
                    'description' => 'Visual styling options',
                    'properties' => [
                        // Primary accent color (for buttons, UI highlights)
                        'accent_color' => ['type' => 'string', 'description' => 'Primary accent hex color', 'default' => '#eab308'],
                        // Text colors
                        'text_color_basic' => ['type' => 'string', 'description' => 'Main text color (off-white)', 'default' => '#e5e5e5'],
                        'text_color_accent' => ['type' => 'string', 'description' => 'Accent text (inherits from accent_color unless overridden)', 'default' => '#eab308'],
                        'text_color_note' => ['type' => 'string', 'description' => 'Muted text color (descriptions)', 'default' => '#a3a3a3'],
                        'text_color_discount' => ['type' => 'string', 'description' => 'Discount/savings text color', 'default' => '#22c55e'],
                        // UI element colors
                        'page_bg_color' => ['type' => 'string', 'description' => 'Page background color', 'default' => '#121212'],
                        'card_bg_color' => ['type' => 'string', 'description' => 'Card/panel background color', 'default' => '#1a1a1a'],
                        'input_bg_color' => ['type' => 'string', 'description' => 'Form input background color', 'default' => '#333333'],
                        'border_color' => ['type' => 'string', 'description' => 'Border/divider color', 'default' => '#7c3aed'],
                        // Background type settings
                        'background_type' => ['type' => 'string', 'enum' => ['solid', 'gradient', 'image'], 'default' => 'solid'],
                        'background_image' => ['type' => 'string'],
                        'custom_css' => ['type' => 'string'],
                    ],
                ],
                'footer' => [
                    'type' => 'object',
                    'description' => 'Footer section',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'disclaimer' => ['type' => 'string', 'description' => 'Legal disclaimer text'],
                        'links' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'label' => ['type' => 'string'],
                                    'url' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get field descriptions for AI agents.
     *
     * @return array Field descriptions and recommendations
     */
    public static function getFieldDescriptions(): array
    {
        return [
            'funnel.name' => 'Human-readable funnel name, e.g., "Illumodine" or "Summer Sale 2024"',
            'funnel.slug' => 'URL-safe identifier, lowercase, hyphens instead of spaces, e.g., "illumodine" or "summer-sale-2024"',
            'hero.title' => 'Main headline, 3-8 words, action-oriented and compelling. Examples: "Transform Your Health Today", "Unlock Your Potential"',
            'hero.subtitle' => 'Secondary headline that expands on the title, 5-12 words',
            'hero.cta_text' => 'Call-to-action button text, 3-6 words, action verbs. Examples: "Get Your Special Offer Now", "Start Your Journey"',
            'benefits.items' => 'Array of 6-12 benefit statements. Each should be 5-15 words, specific and compelling',
            'offers' => 'Array of offers: single products, fixed bundles, or customizable kits. Each offer has a type, discount settings, and product configuration.',
            'offers[].type' => 'Offer type: "single" (one product), "fixed_bundle" (pre-configured set), or "customizable_kit" (customer picks products)',
            'offers[].discount_label' => 'Marketing-friendly discount label shown to customers, e.g., "Save 25%"',
            'offers[].kit_products[].role' => 'Product role in kit: "must" (min qty 1, required), "optional" (min qty 0, can be removed)',
            'features.items' => 'Array of 4-6 detailed features with icons. Good for technical/scientific products',
            'authority' => 'Expert/authority section to build trust. Include credentials, bio, and notable quotes',
            'testimonials.items' => 'Array of 3-6 customer testimonials. Include name, role/location, and quote',
            'faq.items' => 'Array of 4-8 frequently asked questions. Address common concerns and objections',
            'styling.accent_color' => 'Primary accent color as hex code. Default gold: #eab308',
        ];
    }

    /**
     * Get an example funnel JSON for AI agents.
     *
     * @return array Example funnel data
     */
    public static function getExample(): array
    {
        return [
            '$schema' => self::VERSION,
            'funnel' => [
                'name' => 'Illumodine',
                'slug' => 'illumodine',
                'status' => 'active',
            ],
            'header' => [
                'logo' => 'https://example.com/logo.png',
                'sticky' => true,
                'transparent' => true,
            ],
            'hero' => [
                'title' => 'Transform Your Health',
                'subtitle' => 'With Nascent Iodine',
                'tagline' => 'The purest form of iodine for optimal thyroid support',
                'description' => 'Discover the power of nascent iodine, formulated for maximum bioavailability.',
                'image' => 'https://example.com/hero-image.png',
                'cta_text' => 'Get Your Special Offer Now',
            ],
            'benefits' => [
                'title' => 'Why Choose Illumodine?',
                'items' => [
                    ['text' => 'Supports healthy thyroid function', 'icon' => 'check'],
                    ['text' => 'Boosts natural energy levels', 'icon' => 'check'],
                    ['text' => '100% pure and vegan formula', 'icon' => 'check'],
                    ['text' => 'Helps with detoxification', 'icon' => 'shield'],
                    ['text' => 'Supports immune system health', 'icon' => 'heart'],
                    ['text' => 'Made in the USA', 'icon' => 'star'],
                ],
            ],
            'offers' => [
                [
                    'id' => 'offer-small',
                    'name' => 'Small Bottle (0.5 oz)',
                    'description' => 'Perfect for trying Illumodine',
                    'type' => 'single',
                    'badge' => '',
                    'is_featured' => false,
                    'discount_label' => '',
                    'discount_type' => 'none',
                    'discount_value' => 0,
                    'product_sku' => 'ILL-SMALL',
                    'quantity' => 1,
                ],
                [
                    'id' => 'offer-large',
                    'name' => 'Large Bottle (2 oz)',
                    'description' => '4x more product, best value',
                    'type' => 'single',
                    'badge' => 'BEST VALUE',
                    'is_featured' => true,
                    'discount_label' => 'Save 25%',
                    'discount_type' => 'percent',
                    'discount_value' => 25,
                    'product_sku' => 'ILL-LARGE',
                    'quantity' => 1,
                ],
                [
                    'id' => 'offer-kit',
                    'name' => 'Build Your Kit',
                    'description' => 'Customize your supplement kit',
                    'type' => 'customizable_kit',
                    'badge' => 'CUSTOMIZE',
                    'is_featured' => false,
                    'discount_label' => 'Save up to 30%',
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'max_total_items' => 6,
                    'kit_products' => [
                        ['sku' => 'ILL-SMALL', 'role' => 'must', 'qty' => 1, 'max_qty' => 3, 'discount_type' => 'percent', 'discount_value' => 15],
                        ['sku' => 'ILL-LARGE', 'role' => 'optional', 'qty' => 1, 'max_qty' => 2, 'discount_type' => 'percent', 'discount_value' => 20],
                    ],
                ],
            ],
            'features' => [
                'title' => 'The Science Behind Illumodine',
                'subtitle' => 'Clinically-formulated for maximum bioavailability',
                'items' => [
                    ['icon' => 'shield', 'title' => 'Nascent Iodine Technology', 'description' => 'Electromagnetic process creates atomic iodine for superior absorption'],
                    ['icon' => 'bolt', 'title' => 'Supports Thyroid Function', 'description' => 'Essential mineral for healthy thyroid hormone production'],
                    ['icon' => 'heart', 'title' => 'Boosts Energy', 'description' => 'Supports healthy metabolic rate and natural energy levels'],
                ],
            ],
            'authority' => [
                'title' => 'Meet Dr. Gabriel Cousens',
                'name' => 'Dr. Gabriel Cousens, M.D.',
                'credentials' => 'MD, MD(H), DD, Diplomat of the American Board of Integrative Holistic Medicine',
                'bio' => '<p>World-renowned holistic physician with over 40 years of experience in natural health.</p>',
                'quotes' => [
                    ['text' => 'Iodine is one of the most important minerals for optimal health.'],
                ],
            ],
            'testimonials' => [
                'title' => 'What Our Customers Say',
                'items' => [
                    ['name' => 'Sarah M.', 'role' => 'Verified Buyer', 'quote' => 'Amazing energy improvement after just 2 weeks!', 'rating' => 5],
                    ['name' => 'Michael R.', 'role' => 'Health Enthusiast', 'quote' => 'The best iodine supplement I have tried.', 'rating' => 5],
                ],
            ],
            'faq' => [
                'title' => 'Frequently Asked Questions',
                'items' => [
                    ['question' => 'What is nascent iodine?', 'answer' => '<p>Nascent iodine is iodine in its atomic form for superior absorption.</p>'],
                    ['question' => 'How should I take it?', 'answer' => '<p>Take 1-3 drops daily in water on an empty stomach.</p>'],
                ],
            ],
            'cta' => [
                'title' => 'Ready to Transform Your Health?',
                'subtitle' => 'Join thousands of satisfied customers',
                'button_text' => 'Get Your Illumodine Now',
            ],
            'checkout' => [
                'url' => '/funnels/illumodine/checkout/',
                'free_shipping_countries' => 'US',
                'enable_points_redemption' => true,
            ],
            'thankyou' => [
                'url' => '/funnels/illumodine/thank-you/',
                'headline' => 'Thank You for Your Order!',
                'show_upsell' => false,
            ],
            'styling' => [
                'accent_color' => '#eab308',
                'background_type' => 'solid',
            ],
            'footer' => [
                'disclaimer' => 'These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure or prevent any disease.',
                'links' => [
                    ['label' => 'Privacy Policy', 'url' => '/privacy-policy/'],
                    ['label' => 'Terms of Service', 'url' => '/terms/'],
                ],
            ],
        ];
    }

    /**
     * Validate JSON data against schema.
     *
     * @param array $data JSON data to validate
     * @return array Validation result with 'valid' and 'errors' keys
     */
    public static function validate(array $data): array
    {
        $errors = [];

        // Check required funnel object
        if (!isset($data['funnel']) || !is_array($data['funnel'])) {
            $errors[] = 'Missing required "funnel" object';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check required funnel fields
        if (empty($data['funnel']['name'])) {
            $errors[] = 'Missing required field: funnel.name';
        }

        if (empty($data['funnel']['slug'])) {
            $errors[] = 'Missing required field: funnel.slug';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $data['funnel']['slug'])) {
            $errors[] = 'Invalid funnel.slug: must be lowercase alphanumeric with hyphens only';
        }

        // Validate offers if present
        if (isset($data['offers']) && is_array($data['offers'])) {
            foreach ($data['offers'] as $i => $offer) {
                if (empty($offer['id'])) {
                    $errors[] = "Offer at index $i is missing required 'id' field";
                }
                if (empty($offer['name'])) {
                    $errors[] = "Offer at index $i is missing required 'name' field";
                }
                if (empty($offer['type'])) {
                    $errors[] = "Offer at index $i is missing required 'type' field";
                } elseif (!in_array($offer['type'], ['single', 'fixed_bundle', 'customizable_kit'])) {
                    $errors[] = "Offer at index $i has invalid 'type' (must be single, fixed_bundle, or customizable_kit)";
                }
                // Type-specific validation
                if (($offer['type'] ?? '') === 'single' && empty($offer['product_sku'])) {
                    $errors[] = "Offer at index $i (single) is missing required 'product_sku' field";
                }
                if (($offer['type'] ?? '') === 'fixed_bundle' && empty($offer['bundle_items'])) {
                    $errors[] = "Offer at index $i (fixed_bundle) is missing required 'bundle_items' field";
                }
                if (($offer['type'] ?? '') === 'customizable_kit' && empty($offer['kit_products'])) {
                    $errors[] = "Offer at index $i (customizable_kit) is missing required 'kit_products' field";
                }
            }
        }

        // Validate status if present
        if (isset($data['funnel']['status']) && !in_array($data['funnel']['status'], ['active', 'inactive'])) {
            $errors[] = 'Invalid funnel.status: must be "active" or "inactive"';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get the complete schema response for API.
     *
     * @return array Complete schema with descriptions and example
     */
    public static function getSchemaResponse(): array
    {
        return [
            'version' => self::VERSION,
            'schema' => self::getSchema(),
            'field_descriptions' => self::getFieldDescriptions(),
            'example' => self::getExample(),
            'ai_generation_hints' => self::getAiGenerationHints(),
            'content_guidelines' => self::getContentGuidelines(),
        ];
    }

    /**
     * Get AI generation hints for each section.
     * 
     * These hints help AI agents understand how to generate effective content
     * for each section of the funnel.
     *
     * @return array AI generation hints by section
     */
    public static function getAiGenerationHints(): array
    {
        return [
            'general' => [
                'tone' => 'Professional, trustworthy, and persuasive without being pushy',
                'audience' => 'Health-conscious consumers interested in natural supplements',
                'avoid' => [
                    'Medical claims that could violate FDA regulations',
                    'Guaranteed results or cure language',
                    'Comparison to prescription medications',
                    'Pressure tactics or artificial scarcity',
                ],
                'include' => [
                    'Science-backed information where applicable',
                    'Clear benefit statements',
                    'Social proof and authority signals',
                    'Clear calls-to-action',
                ],
            ],
            'hero' => [
                'title' => [
                    'length' => '3-8 words',
                    'style' => 'Action-oriented, benefit-focused',
                    'examples' => [
                        'Transform Your Health Today',
                        'Unlock Your Natural Energy',
                        'Support Your Thyroid Naturally',
                    ],
                    'patterns' => [
                        '[Action Verb] Your [Benefit]',
                        'The [Adjective] Way to [Benefit]',
                        'Discover [Benefit] with [Product]',
                    ],
                ],
                'subtitle' => [
                    'length' => '5-12 words',
                    'style' => 'Expands on title, adds specificity',
                    'relationship' => 'Should complement, not repeat, the title',
                ],
                'tagline' => [
                    'length' => '8-15 words',
                    'style' => 'Value proposition or unique selling point',
                ],
                'cta_text' => [
                    'length' => '3-6 words',
                    'style' => 'Action verb + benefit or offer',
                    'examples' => [
                        'Get Your Special Offer Now',
                        'Start Your Health Journey',
                        'Claim Your Discount Today',
                    ],
                ],
            ],
            'benefits' => [
                'count' => '6-12 items',
                'item_length' => '5-15 words each',
                'structure' => [
                    'Start with action verb or benefit outcome',
                    'Be specific rather than vague',
                    'Mix emotional and functional benefits',
                ],
                'categories' => [
                    'Health outcomes (e.g., "Supports healthy thyroid function")',
                    'Quality/purity (e.g., "100% pure and vegan formula")',
                    'Convenience (e.g., "Easy to take daily")',
                    'Trust signals (e.g., "Made in USA-based facility")',
                ],
                'icon_mapping' => [
                    'check' => 'General benefits, features',
                    'star' => 'Quality, excellence',
                    'shield' => 'Protection, safety, purity',
                    'heart' => 'Health, wellness, care',
                ],
            ],
            'offers' => [
                'structure' => [
                    'Include 2-4 offers for choice without overwhelm',
                    'One should be clearly "featured" or "best value"',
                    'Consider: entry-level, mid-tier, best value, custom',
                ],
                'pricing_psychology' => [
                    'Use odd pricing (e.g., $47 instead of $50)',
                    'Show original price with discount',
                    'Highlight savings in absolute and percentage terms',
                ],
                'badge_examples' => [
                    'BEST VALUE',
                    'MOST POPULAR',
                    'SAVE 25%',
                    'STARTER',
                    'PREMIUM',
                ],
                'naming' => [
                    'Be descriptive of what customer gets',
                    'Include quantity or duration when relevant',
                    'Examples: "90-Day Supply", "Family Pack", "Starter Kit"',
                ],
            ],
            'features' => [
                'count' => '3-6 items',
                'structure' => [
                    'title' => '2-4 words, feature name',
                    'description' => '15-30 words, explain the benefit',
                ],
                'focus' => 'Technical/scientific differentiators',
                'icon_mapping' => [
                    'check' => 'General features',
                    'star' => 'Premium/unique features',
                    'shield' => 'Safety, protection features',
                    'heart' => 'Health benefits',
                    'bolt' => 'Energy, performance',
                    'leaf' => 'Natural, organic, plant-based',
                ],
            ],
            'authority' => [
                'purpose' => 'Build trust through expertise and credentials',
                'elements' => [
                    'Expert name with credentials',
                    'Professional photo',
                    'Bio highlighting relevant experience',
                    'Notable quotes that support product benefits',
                ],
                'bio_length' => '100-200 words',
                'quotes_count' => '2-5 quotes',
            ],
            'science' => [
                'purpose' => 'Provide scientific backing without medical claims',
                'structure' => [
                    'Section title describing the science',
                    'Brief explanation in plain language',
                    'Bullet points for key facts',
                ],
                'tone' => 'Educational, not promotional',
                'avoid' => 'Specific medical claims, cure language',
            ],
            'testimonials' => [
                'count' => '3-6 testimonials',
                'structure' => [
                    'name' => 'First name + last initial or full name',
                    'role' => 'Identifier like "Verified Buyer" or location',
                    'quote' => '20-50 words, specific benefit experienced',
                ],
                'variety' => 'Include diverse perspectives and use cases',
                'authenticity' => 'Use realistic, relatable language',
            ],
            'faq' => [
                'count' => '4-8 questions',
                'categories' => [
                    'Product usage (how to take, dosage)',
                    'Ingredients/quality',
                    'Shipping/delivery',
                    'Returns/guarantee',
                    'Results/expectations',
                ],
                'answer_length' => '50-150 words each',
                'address_objections' => 'Anticipate and overcome purchase hesitations',
            ],
            'cta' => [
                'purpose' => 'Secondary conversion point after scrolling content',
                'title' => [
                    'length' => '4-8 words',
                    'style' => 'Question or statement that invites action',
                ],
                'button_text' => [
                    'length' => '2-5 words',
                    'style' => 'Clear action verb',
                ],
            ],
        ];
    }

    /**
     * Get content guidelines for AI generation.
     *
     * @return array Content guidelines
     */
    public static function getContentGuidelines(): array
    {
        return [
            'compliance' => [
                'fda' => [
                    'rule' => 'No claims that products diagnose, treat, cure, or prevent disease',
                    'required_disclaimer' => 'These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure or prevent any disease.',
                    'safe_language' => [
                        'Supports healthy [function]',
                        'May help with [general wellness goal]',
                        'Promotes [positive state]',
                    ],
                    'avoid_language' => [
                        'Cures [condition]',
                        'Treats [disease]',
                        'Prevents [illness]',
                        'Guaranteed results',
                    ],
                ],
                'ftc' => [
                    'rule' => 'Testimonials must reflect typical results or include disclaimer',
                    'rule' => 'No false or misleading claims',
                ],
            ],
            'brand_voice' => [
                'primary_attributes' => [
                    'Trustworthy',
                    'Knowledgeable',
                    'Caring',
                    'Professional',
                ],
                'secondary_attributes' => [
                    'Approachable',
                    'Empowering',
                    'Science-backed',
                ],
                'avoid' => [
                    'Hype or exaggeration',
                    'Pressure tactics',
                    'Competitor bashing',
                    'Overpromising',
                ],
            ],
            'seo' => [
                'considerations' => [
                    'Include relevant keywords naturally',
                    'Use descriptive headings',
                    'Provide valuable, unique content',
                ],
                'meta' => [
                    'title_length' => '50-60 characters',
                    'description_length' => '150-160 characters',
                ],
            ],
            'accessibility' => [
                'images' => 'Include alt text for all images',
                'contrast' => 'Ensure sufficient color contrast for text',
                'structure' => 'Use semantic heading hierarchy',
            ],
            'conversion_optimization' => [
                'above_fold' => [
                    'Clear value proposition',
                    'Visible call-to-action',
                    'Trust signals (badges, reviews)',
                ],
                'social_proof' => [
                    'Customer testimonials',
                    'Expert endorsements',
                    'Trust badges',
                    'Review counts/ratings',
                ],
                'urgency' => [
                    'Use sparingly and authentically',
                    'Avoid fake countdown timers',
                    'Highlight genuine limited offers',
                ],
            ],
            'content_derivation' => [
                'from_article' => [
                    'hero.title' => 'Extract main benefit or transformation',
                    'hero.subtitle' => 'Secondary benefit or product description',
                    'benefits.items' => 'Pull key points and convert to benefit statements',
                    'features.items' => 'Extract technical or scientific details',
                    'science.sections' => 'Summarize research or mechanism explanations',
                    'authority.quotes' => 'Pull notable expert statements',
                    'faq.items' => 'Address questions raised or implied in article',
                ],
                'from_protocol' => [
                    'offers' => 'Build product bundles based on protocol requirements',
                    'benefits.items' => 'Derive from protocol goals and expected outcomes',
                    'science.sections' => 'Explain why each component is included',
                ],
                'from_product_labels' => [
                    'styling.accent_color' => 'Extract dominant brand color',
                    'styling.page_bg_color' => 'Match product packaging aesthetic',
                    'benefits.items' => 'Include label claims as benefits',
                    'features.items' => 'Highlight key ingredients from label',
                ],
            ],
        ];
    }
}

