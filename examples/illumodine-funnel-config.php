<?php
/**
 * Example Illumodine Funnel Configuration
 * 
 * This file shows how to configure the Illumodine funnel.
 * You can add this to your theme's functions.php or create a separate plugin.
 * 
 * The configuration will be stored in: wp_options -> hp_rw_funnel_illumodine
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Illumodine funnel configuration on activation or via admin.
 */
function hp_register_illumodine_funnel_config() {
    $config = [
        // Basic info
        'name' => 'Illumodine™',
        'id'   => 'illumodine',
        
        // Branding
        'logo_url'  => 'https://holisticpeople.com/wp-content/uploads/holisticpeople-logo.png',
        'logo_link' => 'https://holisticpeople.com',
        
        // Hero section
        'hero_title'       => 'Illumodine™',
        'hero_subtitle'    => 'The best Iodine in the world!',
        'hero_tagline'     => 'Pure, High-Potency Iodine Supplement',
        'hero_description' => 'The most bioavailable iodine supplement on Earth – charged with True Scalar Energy™ for maximum effectiveness',
        'hero_image'       => 'https://holisticpeople.com/wp-content/uploads/illum-2oz-bottle.png',
        
        // CTA
        'cta_text' => 'Get Your Special Offer Now',
        
        // Benefits
        'benefits_title' => 'Why Illumodine™?',
        'benefits' => [
            'Powerful antioxidant – 3,000x more effective than normal tissue at absorbing radiation',
            'Anti-bacterial, anti-viral, anti-fungal, and anti-parasitic properties',
            'Supports brain function and mental clarity',
            'Enhances mood and reduces anxiety and depression',
            'Supports thyroid health and hormone balance',
            'Promotes detoxification of heavy metals (fluoride, mercury, lead, aluminum)',
            'Provides cellular and DNA protection',
            'Supports energy production at the mitochondrial level',
            'Anti-inflammatory and anti-allergenic benefits',
            'Helps eliminate mutated cells through healthy apoptosis',
            'Supports serotonin production for well-being',
            'Activates and protects the pineal gland',
        ],
        
        // Products
        'products' => [
            [
                'id'             => 'small',
                'sku'            => 'ILLUM-05OZ', // Update with actual SKU
                'display_name'   => 'Starter Size',
                'description'    => '0.5 fl oz (15ml)',
                'display_price'  => 29.00,
                'image'          => 'https://holisticpeople.com/wp-content/uploads/illum-05oz-bottle.png',
                'badge'          => '',
                'features'       => [
                    'Perfect for trying Illumodine™',
                    'Pure, high-potency formula',
                    'Scalar energy charged',
                ],
                'is_best_value'  => false,
            ],
            [
                'id'             => 'large',
                'sku'            => 'ILLUM-2OZ', // Update with actual SKU
                'display_name'   => 'Value Pack',
                'description'    => '2 fl oz (60ml)',
                'display_price'  => 114.00,
                'image'          => 'https://holisticpeople.com/wp-content/uploads/illum-2oz-bottle.png',
                'badge'          => 'BEST VALUE',
                'features'       => [
                    'Maximum savings per dose',
                    'Get a FREE 0.5oz bottle',
                    'Longer-lasting supply',
                ],
                'is_best_value'  => true,
                'free_item_sku'  => 'ILLUM-05OZ', // Free item SKU
                'free_item_qty'  => 1,
            ],
        ],
        
        // URLs
        'checkout_url'  => '/funnels/illumodine/checkout/',
        'thankyou_url'  => '/funnels/illumodine/thank-you/',
        'landing_url'   => '/funnels/illumodine/',
        
        // Shipping
        'free_shipping_countries' => ['US'],
        
        // Discounts
        'global_discount_percent' => 10, // 10% global discount
        
        // Upsell offers
        'upsell_offers' => [
            [
                'sku'              => 'DIGEST-XYM', // Update with actual SKU
                'display_name'     => 'DigestXym™',
                'description'      => 'Premium digestive enzyme blend for optimal nutrient absorption',
                'image'            => 'https://holisticpeople.com/wp-content/uploads/digestxym.png',
                'regular_price'    => 49.00,
                'discount_percent' => 30,
                'features'         => [
                    'Enhances nutrient absorption',
                    'Supports healthy digestion',
                    'Works synergistically with Illumodine™',
                ],
            ],
        ],
        
        // Thank you page
        'thankyou_headline'    => 'Thank You for Your Order!',
        'thankyou_subheadline' => 'Your Illumodine™ order is being processed and will ship within 1-2 business days.',
        
        // Payment styling (for hosted payment page)
        'payment_style' => [
            'accent_color'     => '#eab308', // Gold
            'background_color' => '#020617', // Dark blue
            'card_color'       => '#0f172a', // Slate
        ],
    ];
    
    update_option('hp_rw_funnel_illumodine', $config);
    
    return $config;
}

/**
 * Add to main plugin settings as well for discovery
 */
function hp_add_illumodine_to_main_settings() {
    $opts = get_option('hp_rw_settings', []);
    
    if (!isset($opts['funnel_configs'])) {
        $opts['funnel_configs'] = [];
    }
    
    // Reference the illumodine config
    $opts['funnel_configs']['illumodine'] = get_option('hp_rw_funnel_illumodine', []);
    
    // Add to funnels registry for CORS/mode settings
    if (!isset($opts['funnels'])) {
        $opts['funnels'] = [];
    }
    
    // Check if illumodine is already registered
    $found = false;
    foreach ($opts['funnels'] as $f) {
        if (isset($f['id']) && $f['id'] === 'illumodine') {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $opts['funnels'][] = [
            'id'              => 'illumodine',
            'name'            => 'Illumodine™',
            'mode_staging'    => 'test',
            'mode_production' => 'live',
        ];
    }
    
    update_option('hp_rw_settings', $opts);
}

// Run on theme activation or manually
// add_action('after_switch_theme', 'hp_register_illumodine_funnel_config');
// add_action('after_switch_theme', 'hp_add_illumodine_to_main_settings');

// Or run manually via WP-CLI:
// wp eval "require 'examples/illumodine-funnel-config.php'; hp_register_illumodine_funnel_config(); hp_add_illumodine_to_main_settings();"















