<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Util\Resolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelCheckout shortcode - renders a checkout page for sales funnels.
 * 
 * Usage: [hp_funnel_checkout funnel="illumodine"]
 * 
 * The funnel configuration is loaded from hp_rw_settings option.
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
            'funnel'  => 'default',
            'product' => '', // Optional: pre-select a product
        ], $atts);

        $funnelId = sanitize_key($atts['funnel']);
        
        // Check for product from query string
        $selectedProduct = sanitize_key($atts['product']);
        if (empty($selectedProduct) && isset($_GET['product'])) {
            $selectedProduct = sanitize_key($_GET['product']);
        }

        // Get funnel configuration
        $config = $this->getFunnelConfig($funnelId);

        if (empty($config)) {
            return '<div class="hp-funnel-error">Funnel configuration not found.</div>';
        }

        // Build product data from SKUs
        $products = $this->buildProductsFromConfig($config);

        if (empty($products)) {
            return '<div class="hp-funnel-error">No products configured for this funnel.</div>';
        }

        // Build props for React component
        $props = [
            'funnelId'              => $funnelId,
            'funnelName'            => $config['name'] ?? ucfirst($funnelId),
            'products'              => $products,
            'thankYouUrl'           => $this->getThankYouUrl($funnelId, $config),
            'backUrl'               => $this->getBackUrl($funnelId, $config),
            'logoUrl'               => $config['logo_url'] ?? '',
            'logoLink'              => $config['logo_link'] ?? home_url('/'),
            'freeShippingCountries' => $config['free_shipping_countries'] ?? ['US'],
            'apiBase'               => rest_url('hp-rw/v1'),
        ];

        // Add selected product if specified
        if ($selectedProduct) {
            $props['selectedProductId'] = $selectedProduct;
        }

        $rootId = 'hp-funnel-checkout-' . esc_attr($funnelId) . '-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr('FunnelCheckout'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Get funnel configuration from settings.
     */
    private function getFunnelConfig(string $funnelId): array
    {
        $opts = get_option('hp_rw_settings', []);
        
        if (!empty($opts['funnel_configs']) && isset($opts['funnel_configs'][$funnelId])) {
            return $opts['funnel_configs'][$funnelId];
        }

        // Try to load from a separate option for the funnel
        $funnelOpts = get_option('hp_rw_funnel_' . $funnelId, []);
        if (!empty($funnelOpts)) {
            return $funnelOpts;
        }

        return [];
    }

    /**
     * Build product data from funnel configuration.
     */
    private function buildProductsFromConfig(array $config): array
    {
        $products = [];
        
        if (empty($config['products']) || !is_array($config['products'])) {
            return $products;
        }

        foreach ($config['products'] as $productConfig) {
            if (!is_array($productConfig) || empty($productConfig['sku'])) {
                continue;
            }

            $sku = (string) $productConfig['sku'];
            $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);

            if (!$wcProduct) {
                continue;
            }

            $productData = Resolver::getProductDisplayData($wcProduct);

            $products[] = [
                'id'           => $productConfig['id'] ?? $sku,
                'sku'          => $sku,
                'name'         => $productConfig['display_name'] ?? $productData['name'],
                'description'  => $productConfig['description'] ?? '',
                'price'        => (float) ($productConfig['display_price'] ?? $productData['price']),
                'image'        => $productConfig['image'] ?? $productData['image'],
                'badge'        => $productConfig['badge'] ?? '',
                'freeItemSku'  => $productConfig['free_item_sku'] ?? '',
                'freeItemQty'  => (int) ($productConfig['free_item_qty'] ?? 1),
                'isBestValue'  => !empty($productConfig['is_best_value']),
            ];
        }

        return $products;
    }

    /**
     * Get the thank you URL for this funnel.
     */
    private function getThankYouUrl(string $funnelId, array $config): string
    {
        if (!empty($config['thankyou_url'])) {
            return $config['thankyou_url'];
        }

        // Default: look for a page with the funnel thank-you shortcode
        $thankYouPage = get_page_by_path("funnels/{$funnelId}/thank-you");
        if ($thankYouPage) {
            return get_permalink($thankYouPage);
        }

        // Fallback to a query param based URL
        return add_query_arg([
            'hp_funnel_thankyou' => $funnelId,
        ], home_url('/'));
    }

    /**
     * Get the back URL for this funnel (landing page).
     */
    private function getBackUrl(string $funnelId, array $config): string
    {
        if (!empty($config['landing_url'])) {
            return $config['landing_url'];
        }

        // Default: look for a page with the funnel hero shortcode
        $landingPage = get_page_by_path("funnels/{$funnelId}");
        if ($landingPage) {
            return get_permalink($landingPage);
        }

        return '';
    }
}

