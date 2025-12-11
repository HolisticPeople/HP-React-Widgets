<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelProducts shortcode - renders the product showcase section.
 * 
 * Usage:
 *   [hp_funnel_products funnel="illumodine"]
 *   [hp_funnel_products funnel="illumodine" layout="horizontal" show_prices="true"]
 */
class FunnelProductsShortcode
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
            'funnel'        => '',
            'id'            => '',
            'title'         => '',           // Override default title
            'subtitle'      => '',           // Override default subtitle
            'layout'        => 'grid',       // grid or horizontal
            'show_prices'   => 'true',
            'show_features' => 'true',
            'cta_text'      => 'Select',     // Default CTA text
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $products = $config['products'] ?? [];

        // Don't render if no products
        if (empty($products)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No products configured for this funnel.');
            }
            return '';
        }

        // Format products for React
        $formattedProducts = $this->formatProductsForReact($products);

        // Build props for React component
        $props = [
            'title'         => !empty($atts['title']) ? $atts['title'] : 'Choose Your Package',
            'subtitle'      => $atts['subtitle'],
            'products'      => $formattedProducts,
            'defaultCtaText' => $atts['cta_text'],
            'defaultCtaUrl' => $config['checkout']['url'],
            'showPrices'    => filter_var($atts['show_prices'], FILTER_VALIDATE_BOOLEAN),
            'showFeatures'  => filter_var($atts['show_features'], FILTER_VALIDATE_BOOLEAN),
            'layout'        => $atts['layout'],
        ];

        return $this->renderWidget('FunnelProducts', $config['slug'], $props);
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
                'price'        => (float) $product['price'],
                'regularPrice' => isset($product['regularPrice']) ? (float) $product['regularPrice'] : null,
                'image'        => $product['image'] ?? '',
                'badge'        => $product['badge'] ?? '',
                'features'     => $product['features'] ?? [],
                'isBestValue'  => $product['isBestValue'] ?? false,
            ];
        }

        return $result;
    }

    /**
     * Load funnel config from attributes.
     *
     * @param array $atts Shortcode attributes
     * @return array|null Config or null
     */
    private function loadConfig(array $atts): ?array
    {
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        }

        if (!$config || empty($config['active'])) {
            return null;
        }

        return $config;
    }

    /**
     * Render error message.
     *
     * @param string $message Error message
     * @return string HTML
     */
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

    /**
     * Render the React widget container.
     *
     * @param string $component Component name
     * @param string $slug Funnel slug
     * @param array $props Props for React
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-products-' . esc_attr($slug) . '-' . uniqid();

        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-products-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

