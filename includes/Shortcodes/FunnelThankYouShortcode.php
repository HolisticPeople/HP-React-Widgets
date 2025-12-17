<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelThankYou shortcode - renders the thank you page for sales funnels.
 * 
 * Usage:
 *   [hp_funnel_thankyou funnel="illumodine"]  - by slug
 *   [hp_funnel_thankyou id="123"]             - by post ID
 * 
 * The funnel configuration is loaded from the hp-funnel CPT via ACF fields.
 * Order details are loaded from URL parameters (orderId, piId).
 */
class FunnelThankYouShortcode
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
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
        }

        if (!$config || !$config['active']) {
            return '<div class="hp-funnel-error" style="padding: 20px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px;">Funnel not found or inactive. Please check the funnel slug/ID.</div>';
        }

        // Get order info from URL params
        $orderId = isset($_GET['orderId']) ? absint($_GET['orderId']) : null;
        $piId = isset($_GET['piId']) ? sanitize_text_field($_GET['piId']) : null;
        
        // Check if upsell was already accepted/skipped
        $upsellAccepted = isset($_GET['upsellAccepted']);
        $upsellSkipped = isset($_GET['upsellSkipped']);

        // Build upsell config if enabled and not yet processed
        $upsellConfig = null;
        if ($config['thankyou']['show_upsell'] && 
            $config['thankyou']['upsell'] && 
            !$upsellAccepted && 
            !$upsellSkipped) {
            $upsell = $config['thankyou']['upsell'];
            $upsellConfig = [
                'upsellProductSku'         => $upsell['sku'],
                'upsellProductQty'         => $upsell['qty'],
                'upsellProductPrice'       => $upsell['price'],
                'upsellProductTitle'       => $upsell['productName'],
                'upsellProductDescription' => $upsell['description'],
                'upsellProductImageUrl'    => $upsell['image'],
                'upsellProductImageAlt'    => $upsell['productName'],
            ];
        }

        // Build props for React component
        $props = [
            'funnelId'         => $config['slug'],
            'funnelName'       => $config['name'],
            'logoUrl'          => $config['hero']['logo'],
            'orderId'          => $orderId,
            'piId'             => $piId,
            'headline'         => $config['thankyou']['headline'],
            'message'          => $config['thankyou']['message'],
            'upsellConfig'     => $upsellConfig,
            'accentColor'      => $config['styling']['accent_color'],
            'footerText'       => $config['footer']['text'],
            'footerDisclaimer' => $config['footer']['disclaimer'],
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

        $rootId = 'hp-funnel-thankyou-' . esc_attr($config['slug']) . '-' . uniqid();

        return $customCss . sprintf(
            '<div id="%s" class="hp-funnel-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($config['slug']),
            esc_attr('FunnelThankYou'),
            esc_attr(wp_json_encode($props))
        );
    }
}
