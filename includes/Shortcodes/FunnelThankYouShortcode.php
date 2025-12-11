<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

if (!defined('ABSPATH')) {
    exit;
}

class FunnelThankYouShortcode
{
    public function render(array $atts = []): string
    {
        AssetLoader::enqueue_bundle();

        $default_atts = [
            'funnel_id'          => 'default',
            'logo_url'           => HP_RW_URL . 'src/assets/holisticpeople-logo.png',
            'footer_text'        => '© 2024 HolisticPeople.com - Manufactured for Dr. Gabriel Cousens',
            'footer_disclaimer'  => 'These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure or prevent any disease.',
            // Upsell config (JSON encoded)
            'upsell_product_sku'         => '',
            'upsell_product_qty'         => 1,
            'upsell_product_price'       => 0,
            'upsell_product_title'       => '',
            'upsell_product_description' => '',
            'upsell_product_image_url'   => '',
            'upsell_product_image_alt'   => '',
        ];

        $atts = shortcode_atts($default_atts, $atts);

        // Get order/payment info from URL params
        $order_id = isset($_GET['orderId']) ? absint($_GET['orderId']) : null;
        $pi_id    = isset($_GET['piId']) ? sanitize_text_field($_GET['piId']) : null;
        
        // Check if upsell was already accepted/skipped
        $upsell_accepted = isset($_GET['upsellAccepted']);
        $upsell_skipped  = isset($_GET['upsellSkipped']);

        // Build upsell config if provided
        $upsell_config = null;
        if (!empty($atts['upsell_product_sku']) && !$upsell_accepted && !$upsell_skipped) {
            $upsell_config = [
                'upsellProductSku'         => sanitize_text_field($atts['upsell_product_sku']),
                'upsellProductQty'         => absint($atts['upsell_product_qty']),
                'upsellProductPrice'       => (float) $atts['upsell_product_price'],
                'upsellProductTitle'       => sanitize_text_field($atts['upsell_product_title']),
                'upsellProductDescription' => sanitize_text_field($atts['upsell_product_description']),
                'upsellProductImageUrl'    => esc_url($atts['upsell_product_image_url']),
                'upsellProductImageAlt'    => sanitize_text_field($atts['upsell_product_image_alt']),
            ];
        }

        $props = [
            'funnelId'         => sanitize_text_field($atts['funnel_id']),
            'logoUrl'          => esc_url($atts['logo_url']),
            'orderId'          => $order_id,
            'piId'             => $pi_id,
            'upsellConfig'     => $upsell_config,
            'footerText'       => sanitize_text_field($atts['footer_text']),
            'footerDisclaimer' => sanitize_text_field($atts['footer_disclaimer']),
        ];

        $root_id = 'hp-funnel-thankyou-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($root_id),
            esc_attr('FunnelThankYou'),
            esc_attr(wp_json_encode($props))
        );
    }
}
