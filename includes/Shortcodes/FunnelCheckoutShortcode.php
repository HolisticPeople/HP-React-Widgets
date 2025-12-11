<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelCheckout shortcode - renders the checkout page for sales funnels.
 * 
 * Usage:
 *   [hp_funnel_checkout funnel="illumodine"]  - by slug
 *   [hp_funnel_checkout id="123"]             - by post ID
 * 
 * The funnel configuration is loaded from the hp-funnel CPT via ACF fields.
 */
class FunnelCheckoutShortcode
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

        // Load config by ID or slug
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        }

        if (!$config || !$config['active']) {
            return '<div class="hp-funnel-error" style="padding: 20px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px;">Funnel not found or inactive. Please check the funnel slug/ID.</div>';
        }

        // Build props for React component
        $props = [
            'funnelId'              => $config['slug'],
            'funnelName'            => $config['name'],
            'logoUrl'               => $config['hero']['logo'],
            'products'              => $this->formatProductsForReact($config['products']),
            'defaultOffer'          => $this->getDefaultOffer($config['products']),
            'checkoutSuccessUrl'    => $config['thankyou']['url'],
            'checkoutReturnUrl'     => $config['checkout']['url'],
            'landingUrl'            => '/', // Back button destination
            'freeShippingCountries' => $config['checkout']['free_shipping_countries'],
            'globalDiscountPercent' => $config['checkout']['global_discount_percent'],
            'enablePoints'          => $config['checkout']['enable_points'],
            'showOrderSummary'      => $config['checkout']['show_order_summary'],
            'stripeMode'            => $config['stripe_mode'],
            'accentColor'           => $config['styling']['accent_color'],
            'footerText'            => $config['footer']['text'],
            'footerDisclaimer'      => $config['footer']['disclaimer'],
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

        $rootId = 'hp-funnel-checkout-' . esc_attr($config['slug']) . '-' . uniqid();

        return $customCss . sprintf(
            '<div id="%s" class="hp-funnel-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($config['slug']),
            esc_attr('FunnelCheckout'),
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
            $formatted = [
                'id'           => $product['id'] ?? $product['sku'],
                'sku'          => $product['sku'],
                'name'         => $product['name'],
                'description'  => $product['description'] ?? '',
                'price'        => $product['price'],
                'regularPrice' => $product['regularPrice'] ?? null,
                'image'        => $product['image'],
                'imageAlt'     => $product['name'],
                'badge'        => $product['badge'] ?? '',
                'features'     => $product['features'] ?? [],
                'isBestValue'  => $product['isBestValue'] ?? false,
            ];

            // Add free item info if present
            if (!empty($product['freeItem']['sku'])) {
                $formatted['freeItem'] = [
                    'sku' => $product['freeItem']['sku'],
                    'qty' => $product['freeItem']['qty'],
                ];
            }

            $result[] = $formatted;
        }

        return $result;
    }

    /**
     * Get the default selected offer ID.
     *
     * @param array $products Products from config
     * @return string Default product ID
     */
    private function getDefaultOffer(array $products): string
    {
        // Prefer "best value" product
        foreach ($products as $product) {
            if (!empty($product['isBestValue'])) {
                return $product['id'] ?? $product['sku'];
            }
        }

        // Fall back to first product
        if (!empty($products[0])) {
            return $products[0]['id'] ?? $products[0]['sku'];
        }

        return '';
    }
}
