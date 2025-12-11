<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Util\Resolver;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelHero shortcode - renders a landing page hero for sales funnels.
 * 
 * Usage: [hp_funnel_hero funnel="illumodine"]
 * 
 * The funnel configuration is loaded from hp_rw_settings option.
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
            'funnel'  => 'default',
            'product' => '', // Optional: pre-select a product
        ], $atts);

        $funnelId = sanitize_key($atts['funnel']);
        $selectedProduct = sanitize_key($atts['product']);

        // Get funnel configuration
        $config = $this->getFunnelConfig($funnelId);

        if (empty($config)) {
            return '<div class="hp-funnel-error">Funnel configuration not found.</div>';
        }

        // Build product data from SKUs
        $products = $this->buildProductsFromConfig($config);

        // Build props for React component
        $props = [
            'funnelId'     => $funnelId,
            'funnelName'   => $config['name'] ?? ucfirst($funnelId),
            'title'        => $config['hero_title'] ?? '',
            'subtitle'     => $config['hero_subtitle'] ?? '',
            'tagline'      => $config['hero_tagline'] ?? '',
            'description'  => $config['hero_description'] ?? '',
            'heroImage'    => $config['hero_image'] ?? '',
            'logoUrl'      => $config['logo_url'] ?? '',
            'logoLink'     => $config['logo_link'] ?? home_url('/'),
            'products'     => $products,
            'checkoutUrl'  => $this->getCheckoutUrl($funnelId, $config),
            'ctaText'      => $config['cta_text'] ?? 'Get Your Special Offer Now',
            'benefits'     => $config['benefits'] ?? [],
            'benefitsTitle' => $config['benefits_title'] ?? 'Why Choose Us?',
            'accentColor'  => $config['accent_color'] ?? '',
            'backgroundGradient' => $config['background_gradient'] ?? '',
        ];

        // Add selected product if specified
        if ($selectedProduct) {
            $props['selectedProductId'] = $selectedProduct;
        }

        $rootId = 'hp-funnel-hero-' . esc_attr($funnelId) . '-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr('FunnelHero'),
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
                'regularPrice' => $productData['regular_price'],
                'image'        => $productConfig['image'] ?? $productData['image'],
                'badge'        => $productConfig['badge'] ?? '',
                'features'     => $productConfig['features'] ?? [],
                'isBestValue'  => !empty($productConfig['is_best_value']),
            ];
        }

        return $products;
    }

    /**
     * Get the checkout URL for this funnel.
     */
    private function getCheckoutUrl(string $funnelId, array $config): string
    {
        if (!empty($config['checkout_url'])) {
            return $config['checkout_url'];
        }

        // Default: look for a page with the funnel checkout shortcode
        $checkoutPage = get_page_by_path("funnels/{$funnelId}/checkout");
        if ($checkoutPage) {
            return get_permalink($checkoutPage);
        }

        // Fallback to a query param based URL
        return add_query_arg([
            'hp_funnel_checkout' => $funnelId,
        ], home_url('/'));
    }
}

