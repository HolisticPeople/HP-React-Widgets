<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelCheckoutApp shortcode - renders the full checkout SPA experience.
 * 
 * This is a single-page application that handles:
 * - Checkout step (product selection, customer lookup, payment)
 * - Processing step (payment confirmation)
 * - Upsell step(s) (optional one-click upsells)
 * - Thank you step (order confirmation)
 * 
 * Usage:
 *   [hp_funnel_checkout_app funnel="illumodine"]   - by slug
 *   [hp_funnel_checkout_app id="123"]              - by post ID
 *   [hp_funnel_checkout_app]                       - auto-detect from context
 *   [hp_funnel_checkout_app product="sku123"]      - pre-select a product by SKU
 * 
 * The funnel configuration is loaded from the hp-funnel CPT via ACF fields.
 */
class FunnelCheckoutAppShortcode
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
            'funnel'  => '',       // Funnel slug
            'id'      => '',       // Funnel post ID
            'product' => '',       // Pre-selected product SKU
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
            return '<div class="hp-funnel-error" style="padding: 20px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px;">Funnel not found or inactive.</div>';
        }

        // Determine default product ID
        $defaultProductId = '';
        if (!empty($atts['product'])) {
            // Find product by SKU
            foreach ($config['products'] as $product) {
                if ($product['sku'] === $atts['product']) {
                    $defaultProductId = (string) $product['id'];
                    break;
                }
            }
        }
        // Fallback: check URL parameter
        if (empty($defaultProductId) && isset($_GET['product'])) {
            $productParam = sanitize_text_field($_GET['product']);
            foreach ($config['products'] as $product) {
                if ($product['sku'] === $productParam || (string) $product['id'] === $productParam) {
                    $defaultProductId = (string) $product['id'];
                    break;
                }
            }
        }
        // Fallback: use first product
        if (empty($defaultProductId) && !empty($config['products'])) {
            $defaultProductId = (string) $config['products'][0]['id'];
        }

        // Build the landing URL (for "back" link)
        $landingUrl = $config['checkout']['back_url'] ?? '';
        if (empty($landingUrl)) {
            // Try to get the funnel's permalink
            $funnelPosts = get_posts([
                'post_type'   => 'hp-funnel',
                'name'        => $config['slug'],
                'numberposts' => 1,
            ]);
            if (!empty($funnelPosts)) {
                $landingUrl = get_permalink($funnelPosts[0]->ID);
            } else {
                $landingUrl = home_url('/');
            }
        }

        // Get Stripe publishable key
        $stripeKey = $this->getStripePublishableKey();

        // Build props for React component
        $props = [
            'funnelId'            => (string) $config['id'],
            'funnelName'          => $config['name'],
            'funnelSlug'          => $config['slug'],
            'products'            => $this->formatProductsForReact($config['products']),
            'defaultProductId'    => $defaultProductId,
            'logoUrl'             => $config['hero']['logo'] ?? '',
            'logoLink'            => $config['hero']['logo_link'] ?? home_url('/'),
            'landingUrl'          => $landingUrl,
            'freeShippingCountries' => $config['checkout']['free_shipping_countries'] ?? ['US'],
            'globalDiscountPercent' => (float) ($config['checkout']['global_discount_percent'] ?? 0),
            'enablePoints'        => (bool) ($config['checkout']['enable_points'] ?? true),
            'enableCustomerLookup' => (bool) ($config['checkout']['enable_customer_lookup'] ?? true),
            'stripePublishableKey' => $stripeKey,
            'upsellOffers'        => $this->formatUpsellsForReact($config['upsells'] ?? []),
            'showUpsell'          => !empty($config['upsells']) && ($config['checkout']['show_upsell'] ?? true),
            'thankYouHeadline'    => $config['thank_you']['headline'] ?? 'Thank You for Your Order!',
            'thankYouMessage'     => $config['thank_you']['message'] ?? 'Your order has been confirmed.',
            'accentColor'         => $config['styling']['accent_color'] ?? 'hsl(45, 95%, 60%)',
            'footerText'          => $config['footer']['text'] ?? '',
            'footerDisclaimer'    => $config['footer']['disclaimer'] ?? '',
        ];

        // Unique container ID
        $rootId = 'hp-checkout-app-' . substr(md5($config['slug'] . uniqid()), 0, 8);

        return sprintf(
            '<div id="%s" class="hp-funnel-%s hp-checkout-app" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($config['slug']),
            esc_attr('FunnelCheckoutApp'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Format products array for React consumption.
     */
    private function formatProductsForReact(array $products): array
    {
        return array_map(function ($product) {
            $formatted = [
                'id'          => (string) $product['id'],
                'sku'         => $product['sku'],
                'name'        => $product['name'],
                'price'       => (float) $product['price'],
            ];

            if (!empty($product['regular_price'])) {
                $formatted['regularPrice'] = (float) $product['regular_price'];
            }
            if (!empty($product['description'])) {
                $formatted['description'] = $product['description'];
            }
            if (!empty($product['image'])) {
                $formatted['image'] = $product['image'];
            }
            if (!empty($product['badge'])) {
                $formatted['badge'] = $product['badge'];
            }
            if (!empty($product['features'])) {
                $formatted['features'] = $product['features'];
            }
            if (!empty($product['free_item_sku'])) {
                $formatted['freeItem'] = [
                    'sku' => $product['free_item_sku'],
                    'qty' => (int) ($product['free_item_qty'] ?? 1),
                ];
            }
            if (!empty($product['is_best_value'])) {
                $formatted['isBestValue'] = true;
            }

            return $formatted;
        }, $products);
    }

    /**
     * Format upsell offers for React consumption.
     */
    private function formatUpsellsForReact(array $upsells): array
    {
        return array_map(function ($upsell) {
            return [
                'sku'             => $upsell['sku'] ?? '',
                'name'            => $upsell['name'] ?? '',
                'description'     => $upsell['description'] ?? '',
                'image'           => $upsell['image'] ?? '',
                'regularPrice'    => (float) ($upsell['regular_price'] ?? 0),
                'offerPrice'      => (float) ($upsell['offer_price'] ?? 0),
                'discountPercent' => (int) ($upsell['discount_percent'] ?? 0),
                'features'        => $upsell['features'] ?? [],
            ];
        }, $upsells);
    }

    /**
     * Get Stripe publishable key from WooCommerce Stripe Gateway settings.
     */
    private function getStripePublishableKey(): string
    {
        // First check for WooCommerce Stripe Gateway
        $stripeSettings = get_option('woocommerce_stripe_settings', []);
        
        if (!empty($stripeSettings['testmode']) && $stripeSettings['testmode'] === 'yes') {
            return $stripeSettings['test_publishable_key'] ?? '';
        }
        
        return $stripeSettings['publishable_key'] ?? '';
    }
}

