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
                        'subtitle' => ['type' => 'string', 'description' => 'Secondary headline'],
                        'tagline' => ['type' => 'string', 'description' => 'Short tagline or value proposition'],
                        'description' => ['type' => 'string', 'description' => 'Longer description paragraph'],
                        'image' => ['type' => 'string', 'description' => 'URL to hero image'],
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
                                        'role' => ['type' => 'string', 'enum' => ['must', 'default', 'optional']],
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
                        'accent_color' => ['type' => 'string', 'description' => 'Hex color', 'default' => '#eab308'],
                        'background_type' => ['type' => 'string', 'enum' => ['gradient', 'solid', 'image'], 'default' => 'gradient'],
                        'background_color' => ['type' => 'string'],
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
            'offers[].kit_products[].role' => 'Product role in kit: "must" (required), "default" (pre-selected), or "optional" (customer adds)',
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
                        ['sku' => 'ILL-SMALL', 'role' => 'default', 'qty' => 1, 'max_qty' => 3, 'discount_type' => 'percent', 'discount_value' => 15],
                        ['sku' => 'ILL-LARGE', 'role' => 'optional', 'qty' => 0, 'max_qty' => 2, 'discount_type' => 'percent', 'discount_value' => 20],
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
                'background_type' => 'gradient',
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
        ];
    }
}

