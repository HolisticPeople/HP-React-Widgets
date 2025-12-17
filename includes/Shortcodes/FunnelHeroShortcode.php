<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelHero shortcode - renders a landing page hero for sales funnels.
 * 
 * Usage:
 *   [hp_funnel_hero funnel="illumodine"]  - by slug
 *   [hp_funnel_hero id="123"]             - by post ID
 * 
 * The funnel configuration is loaded from the hp-funnel CPT via ACF fields.
 */
class FunnelHeroShortcode
{
    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        AssetLoader::enqueue_bundle();

        $atts = shortcode_atts([
            'funnel' => '',    // Funnel slug
            'id'     => '',    // Funnel post ID
        ], $atts);

        // Load config by ID, slug, or auto-detect from context
        $config = null;
        $debugInfo = '';
        
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
            $debugInfo = 'Method: id=' . $atts['id'];
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
            $debugInfo = 'Method: funnel=' . $atts['funnel'];
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
            
            // Debug info
            global $post;
            $queried = get_queried_object();
            $debugInfo = sprintf(
                'Method: context | queried_object: %s (type: %s) | global $post: %s (type: %s) | get_the_ID: %s | URI: %s',
                is_object($queried) ? (property_exists($queried, 'ID') ? $queried->ID : get_class($queried)) : 'null',
                is_object($queried) && $queried instanceof \WP_Post ? $queried->post_type : 'n/a',
                $post instanceof \WP_Post ? $post->ID : 'null',
                $post instanceof \WP_Post ? $post->post_type : 'n/a',
                get_the_ID(),
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            );
        }

        if (!$config || !$config['active']) {
            $errorMsg = $config ? 'Config found but active=' . var_export($config['active'] ?? 'not set', true) : 'Config is null';
            return sprintf(
                '<!-- HP-RW Debug: %s | Result: %s -->
                <div class="hp-funnel-error" style="padding: 20px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px;">Funnel not found or inactive.</div>',
                esc_html($debugInfo),
                esc_html($errorMsg)
            );
        }

        // Build props for React component
        $props = [
            'funnelId'          => $config['slug'],
            'funnelName'        => $config['name'],
            'title'             => $config['hero']['title'],
            'subtitle'          => $config['hero']['subtitle'],
            'tagline'           => $config['hero']['tagline'],
            'description'       => $config['hero']['description'],
            'heroImage'         => $config['hero']['image'],
            'logoUrl'           => $config['hero']['logo'],
            'logoLink'          => $config['hero']['logo_link'],
            'ctaText'           => $config['hero']['cta_text'],
            'checkoutUrl'       => $config['checkout']['url'],
            'products'          => $this->formatProductsForReact($config['products']),
            'benefits'          => $config['hero']['benefits'],
            'benefitsTitle'     => $config['hero']['benefits_title'],
            'accentColor'       => $config['styling']['accent_color'],
            'backgroundType'    => $config['styling']['background_type'],
            'backgroundColor'   => $config['styling']['background_color'],
            'backgroundImage'   => $config['styling']['background_image'],
            'footerText'        => $config['footer']['text'],
            'footerDisclaimer'  => $config['footer']['disclaimer'],
        ];

        // Add custom CSS if present
        $customCss = '';
        if (!empty($config['styling']['custom_css'])) {
            $customCss = sprintf(
                '<style>.hp-funnel-%s { %s }</style>',
                esc_attr($config['slug']),
                wp_strip_all_tags($config['styling']['custom_css'])
            );
        }

        $rootId = 'hp-funnel-hero-' . esc_attr($config['slug']) . '-' . uniqid();

        return $customCss . sprintf(
            '<div id="%s" class="hp-funnel-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($config['slug']),
            esc_attr('FunnelHero'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Format products for React component.
     *
     * @param array $products Products from config
     * @return array Formatted for React
     */
    private function formatProductsForReact(array $products): array
    {
        $result = [];
        
        foreach ($products as $product) {
            $result[] = [
                'id'           => $product['id'] ?? $product['sku'],
                'sku'          => $product['sku'],
                'name'         => $product['name'],
                'description'  => $product['description'] ?? '',
                'price'        => $product['price'],
                'regularPrice' => $product['regularPrice'] ?? null,
                'image'        => $product['image'],
                'badge'        => $product['badge'] ?? '',
                'features'     => $product['features'] ?? [],
                'isBestValue'  => $product['isBestValue'] ?? false,
            ];
        }

        return $result;
    }
}
