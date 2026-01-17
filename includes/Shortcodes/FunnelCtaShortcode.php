<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelCta shortcode - renders a secondary call-to-action section.
 * 
 * Usage:
 *   [hp_funnel_cta funnel="illumodine"]
 *   [hp_funnel_cta funnel="illumodine" alignment="center" background="gradient"]
 */
class FunnelCtaShortcode
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
            'funnel'     => '',
            'id'         => '',
            'title'      => '',
            'subtitle'   => '',
            'button_text' => '',
            'button_url' => '',
            'alignment'  => 'center',
            'background' => 'gradient', // gradient, solid, transparent
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $postId = $config['id'];

        // Get CTA config from ACF or use checkout URL as fallback
        $buttonBehavior = $config['cta']['button_behavior'] ?? 'scroll_offers';
        
        // Determine button URL based on behavior
        $buttonUrl = !empty($atts['button_url']) 
            ? $atts['button_url'] 
            : (get_field('cta_button_url', $postId) ?: $config['checkout']['url']);
        
        // Get featured offer ID for checkout behavior
        $featuredOfferId = '';
        if ($buttonBehavior === 'checkout') {
            $offers = $config['offers'] ?? [];
            $featured = array_filter($offers, fn($o) => !empty($o['isFeatured']));
            $featuredOfferId = !empty($featured) ? reset($featured)['id'] : (!empty($offers) ? $offers[0]['id'] : '');
        }

        $props = [
            'title'           => !empty($atts['title']) ? $atts['title'] : (get_field('cta_title', $postId) ?: 'Ready to Get Started?'),
            'subtitle'        => !empty($atts['subtitle']) ? $atts['subtitle'] : (get_field('cta_subtitle', $postId) ?: ''),
            'buttonText'      => !empty($atts['button_text']) ? $atts['button_text'] : (get_field('cta_button_text', $postId) ?: 'Order Now'),
            'buttonUrl'       => $buttonUrl,
            'buttonBehavior'  => $buttonBehavior,
            'checkoutUrl'     => $config['checkout']['url'],
            'featuredOfferId' => $featuredOfferId,
            'backgroundStyle' => $atts['background'],
            'alignment'       => $atts['alignment'],
        ];

        return $this->renderWidget('FunnelCta', $config['slug'], $props);
    }

    private function loadConfig(array $atts): ?array
    {
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
        }
        return ($config && !empty($config['active'])) ? $config : null;
    }

    private function renderError(string $message): string
    {
        if (current_user_can('manage_options')) {
            return sprintf(
                '<div class="hp-funnel-error" style="padding: 10px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px; font-size: 12px;">%s</div>',
                esc_html($message)
            );
        }
        return '';
    }

    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-cta-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-cta-%s" data-hp-widget="1" data-component="%s" data-props="%s" data-section-name="Order"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}














