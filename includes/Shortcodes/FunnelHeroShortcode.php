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
 * All configuration can be passed as shortcode attributes for easy Elementor setup.
 * 
 * Usage examples:
 * 
 * Minimal (uses defaults):
 * [hp_funnel_hero title="Illumodine™" subtitle="The best Iodine!" checkout_url="/checkout/"]
 * 
 * With products (SKUs are looked up from WooCommerce):
 * [hp_funnel_hero title="Illumodine™" products="ILLUM-05OZ,ILLUM-2OZ" checkout_url="/checkout/"]
 * 
 * Full configuration:
 * [hp_funnel_hero 
 *   funnel="illumodine"
 *   title="Illumodine™" 
 *   subtitle="The best Iodine in the world!"
 *   tagline="Pure, High-Potency Iodine Supplement"
 *   description="The most bioavailable iodine supplement on Earth"
 *   hero_image="https://example.com/bottle.png"
 *   logo_url="https://example.com/logo.png"
 *   cta_text="Get Your Special Offer Now"
 *   checkout_url="/illumodine-checkout/"
 *   products="ILLUM-05OZ,ILLUM-2OZ"
 *   benefits="Supports thyroid health|Promotes mental clarity|Detoxifies heavy metals"
 * ]
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

        // All available attributes with defaults
        $atts = shortcode_atts([
            // Identity
            'funnel'          => 'default',
            
            // Hero content
            'title'           => 'Special Offer',
            'subtitle'        => '',
            'tagline'         => '',
            'description'     => '',
            'hero_image'      => '',
            
            // Branding
            'logo_url'        => '',
            'logo_link'       => '',
            
            // Call to action
            'cta_text'        => 'Get Your Special Offer Now',
            'checkout_url'    => '/checkout/',
            
            // Products - comma-separated SKUs (will fetch from WooCommerce)
            'products'        => '',
            
            // Benefits - pipe-separated list
            'benefits'        => '',
            'benefits_title'  => 'Why Choose Us?',
            
            // Styling
            'accent_color'    => '',
            'background'      => '',
            
            // Product display overrides (JSON or simple format)
            'product_config'  => '', // JSON: [{"sku":"X","name":"Y","price":29,"badge":"BEST"}]
        ], $atts);

        $funnelId = sanitize_key($atts['funnel']);

        // Try to load stored config first, then override with attributes
        $storedConfig = $this->getFunnelConfig($funnelId);

        // Build products array
        $products = $this->buildProducts($atts, $storedConfig);

        // Parse benefits
        $benefits = $this->parseBenefits($atts['benefits'], $storedConfig);

        // Build props for React component
        $props = [
            'funnelId'          => $funnelId,
            'funnelName'        => $atts['title'] ?: ($storedConfig['name'] ?? ucfirst($funnelId)),
            'title'             => $atts['title'] ?: ($storedConfig['hero_title'] ?? 'Special Offer'),
            'subtitle'          => $atts['subtitle'] ?: ($storedConfig['hero_subtitle'] ?? ''),
            'tagline'           => $atts['tagline'] ?: ($storedConfig['hero_tagline'] ?? ''),
            'description'       => $atts['description'] ?: ($storedConfig['hero_description'] ?? ''),
            'heroImage'         => $atts['hero_image'] ?: ($storedConfig['hero_image'] ?? ''),
            'logoUrl'           => $atts['logo_url'] ?: ($storedConfig['logo_url'] ?? ''),
            'logoLink'          => $atts['logo_link'] ?: ($storedConfig['logo_link'] ?? home_url('/')),
            'products'          => $products,
            'checkoutUrl'       => $atts['checkout_url'] ?: ($storedConfig['checkout_url'] ?? '/checkout/'),
            'ctaText'           => $atts['cta_text'] ?: ($storedConfig['cta_text'] ?? 'Get Your Special Offer Now'),
            'benefits'          => $benefits,
            'benefitsTitle'     => $atts['benefits_title'] ?: ($storedConfig['benefits_title'] ?? 'Why Choose Us?'),
            'accentColor'       => $atts['accent_color'] ?: ($storedConfig['accent_color'] ?? ''),
            'backgroundGradient' => $atts['background'] ?: ($storedConfig['background_gradient'] ?? ''),
        ];

        $rootId = 'hp-funnel-hero-' . esc_attr($funnelId) . '-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr('FunnelHero'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Build products array from attributes or stored config.
     */
    private function buildProducts(array $atts, array $storedConfig): array
    {
        $products = [];

        // Try product_config JSON first (most flexible)
        if (!empty($atts['product_config'])) {
            $decoded = json_decode($atts['product_config'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $pc) {
                    if (empty($pc['sku'])) continue;
                    $products[] = $this->buildProductFromConfig($pc);
                }
                if (!empty($products)) {
                    return $products;
                }
            }
        }

        // Try comma-separated SKUs
        if (!empty($atts['products'])) {
            $skus = array_map('trim', explode(',', $atts['products']));
            foreach ($skus as $sku) {
                if (empty($sku)) continue;
                $product = $this->buildProductFromSku($sku);
                if ($product) {
                    $products[] = $product;
                }
            }
            if (!empty($products)) {
                return $products;
            }
        }

        // Fall back to stored config
        if (!empty($storedConfig['products'])) {
            foreach ($storedConfig['products'] as $pc) {
                if (empty($pc['sku'])) continue;
                $products[] = $this->buildProductFromConfig($pc);
            }
        }

        return $products;
    }

    /**
     * Build product data from a config array.
     */
    private function buildProductFromConfig(array $config): array
    {
        $sku = (string) ($config['sku'] ?? '');
        $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
        $wcData = $wcProduct ? Resolver::getProductDisplayData($wcProduct) : [];

        return [
            'id'           => $config['id'] ?? $sku,
            'sku'          => $sku,
            'name'         => $config['name'] ?? $config['display_name'] ?? ($wcData['name'] ?? $sku),
            'description'  => $config['description'] ?? '',
            'price'        => (float) ($config['price'] ?? $config['display_price'] ?? ($wcData['price'] ?? 0)),
            'regularPrice' => $wcData['regular_price'] ?? null,
            'image'        => $config['image'] ?? ($wcData['image'] ?? ''),
            'badge'        => $config['badge'] ?? '',
            'features'     => $config['features'] ?? [],
            'isBestValue'  => !empty($config['is_best_value']) || !empty($config['best_value']),
        ];
    }

    /**
     * Build product data from just a SKU (fetches from WooCommerce).
     */
    private function buildProductFromSku(string $sku): ?array
    {
        $wcProduct = Resolver::resolveProductFromItem(['sku' => $sku]);
        if (!$wcProduct) {
            return null;
        }

        $wcData = Resolver::getProductDisplayData($wcProduct);

        return [
            'id'           => $sku,
            'sku'          => $sku,
            'name'         => $wcData['name'] ?? $sku,
            'description'  => $wcProduct->get_short_description() ?: '',
            'price'        => (float) ($wcData['price'] ?? 0),
            'regularPrice' => $wcData['regular_price'] ?? null,
            'image'        => $wcData['image'] ?? '',
            'badge'        => '',
            'features'     => [],
            'isBestValue'  => false,
        ];
    }

    /**
     * Parse benefits from attribute or stored config.
     */
    private function parseBenefits(string $benefitsAttr, array $storedConfig): array
    {
        if (!empty($benefitsAttr)) {
            // Pipe-separated list
            return array_map('trim', explode('|', $benefitsAttr));
        }

        return $storedConfig['benefits'] ?? [];
    }

    /**
     * Get funnel configuration from stored settings.
     */
    private function getFunnelConfig(string $funnelId): array
    {
        // Try main settings first
        $opts = get_option('hp_rw_settings', []);
        if (!empty($opts['funnel_configs'][$funnelId])) {
            return $opts['funnel_configs'][$funnelId];
        }

        // Try separate option
        $funnelOpts = get_option('hp_rw_funnel_' . $funnelId, []);
        if (!empty($funnelOpts)) {
            return $funnelOpts;
        }

        return [];
    }
}

