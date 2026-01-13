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

        // Get offers from config
        $offers = $config['offers'] ?? [];
        
        // Determine default offer ID
        $defaultOfferId = '';
        if (!empty($atts['product'])) {
            // Find offer by product SKU (for single offers) or offer ID
            foreach ($offers as $offer) {
                if (($offer['productSku'] ?? '') === $atts['product'] || $offer['id'] === $atts['product']) {
                    $defaultOfferId = $offer['id'];
                    break;
                }
            }
        }
        // Fallback: check URL parameter
        if (empty($defaultOfferId) && isset($_GET['offer'])) {
            $offerParam = sanitize_text_field($_GET['offer']);
            foreach ($offers as $offer) {
                if ($offer['id'] === $offerParam) {
                    $defaultOfferId = $offer['id'];
                    break;
                }
            }
        }
        // Fallback: use featured offer or first offer
        if (empty($defaultOfferId) && !empty($offers)) {
            $featured = array_filter($offers, fn($o) => !empty($o['isFeatured']));
            $defaultOfferId = !empty($featured) ? reset($featured)['id'] : $offers[0]['id'];
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

        // Get Stripe mode + publishable key (mode can be forced per funnel)
        $resolvedMode = 'auto';
        $stripeMode = (string) ($config['stripe_mode'] ?? 'auto');
        $stripeKey = $this->getStripePublishableKey($stripeMode, $resolvedMode);

        // Get logged-in user data for autofill
        $initialUserData = $this->getLoggedInUserData();

        // Build props for React component
        $props = [
            'funnelId'            => (string) $config['id'],
            'funnelName'          => $config['name'],
            'funnelSlug'          => $config['slug'],
            'offers'              => $offers, // New offers system
            'defaultOfferId'      => $defaultOfferId,
            'logoUrl'             => $config['hero']['logo'] ?? '',
            'logoLink'            => $config['hero']['logo_link'] ?? home_url('/'),
            'landingUrl'          => $landingUrl,
            'freeShippingCountries' => $config['checkout']['free_shipping_countries'] ?? ['US'],
            'enablePoints'        => (bool) ($config['checkout']['enable_points'] ?? true),
            'enableCustomerLookup' => (bool) ($config['checkout']['enable_customer_lookup'] ?? true),
            'showAllOffers'       => (bool) ($config['checkout']['show_all_offers'] ?? true),
            'stripePublishableKey' => $stripeKey,
            'stripeMode'          => $resolvedMode, // Use the resolved 'test' or 'live'
            'upsellOffers'        => $this->buildUpsellOffers($config['thankyou']['upsell'] ?? null),
            'showUpsell'          => (bool) ($config['thankyou']['show_upsell'] ?? false),
            'thankYouHeadline'    => $config['thankyou']['headline'] ?? 'Thank You for Your Order!',
            'thankYouMessage'     => $config['thankyou']['message'] ?? 'Your order has been confirmed.',
            'accentColor'         => $config['styling']['accent_color'] ?? '#eab308',
            'footerText'          => $config['footer']['text'] ?? '',
            'footerDisclaimer'    => $config['footer']['disclaimer'] ?? '',
            'initialUserData'     => $initialUserData,
        ];

        // Unique container ID
        $rootId = 'hp-checkout-app-' . substr(md5($config['slug'] . uniqid()), 0, 8);

        // #region agent log - HTML debug output
        $debugData = [
            'configShowAll' => $config['checkout']['show_all_offers'] ?? 'KEY_NOT_SET',
            'configShowAllType' => gettype($config['checkout']['show_all_offers'] ?? null),
            'propsShowAll' => $props['showAllOffers'],
            'propsShowAllType' => gettype($props['showAllOffers']),
            'pluginVersion' => HP_RW_VERSION,
        ];
        $debugHtml = '<!-- HP_DEBUG:' . wp_json_encode($debugData) . ' -->';
        // #endregion

        return $debugHtml . sprintf(
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
     * Build upsell offers array from the thankyou upsell config.
     * 
     * The config loader returns a single upsell object or null, but we need an array
     * for the React component.
     *
     * @param array|null $upsellConfig Single upsell config from FunnelConfigLoader
     * @return array Array of upsell offers for React
     */
    private function buildUpsellOffers(?array $upsellConfig): array
    {
        if (!$upsellConfig || empty($upsellConfig['sku'])) {
            return [];
        }

        // Calculate regular price from discount
        $discountPercent = (float) ($upsellConfig['discount'] ?? 0);
        $offerPrice = (float) ($upsellConfig['price'] ?? 0);
        $regularPrice = $discountPercent > 0 
            ? $offerPrice / (1 - $discountPercent / 100) 
            : $offerPrice;

        return [[
            'sku'             => $upsellConfig['sku'] ?? '',
            'name'            => $upsellConfig['productName'] ?? '',
            'description'     => $upsellConfig['description'] ?? '',
            'image'           => $upsellConfig['image'] ?? '',
            'regularPrice'    => round($regularPrice, 2),
            'offerPrice'      => round($offerPrice, 2),
            'discountPercent' => (int) $discountPercent,
            'features'        => [],
        ]];
    }

    /**
     * Get logged-in user data for form autofill.
     * Returns null if not logged in.
     *
     * @return array|null User data with shipping address
     */
    private function getLoggedInUserData(): ?array
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $userId = get_current_user_id();
        $user = get_userdata($userId);
        
        if (!$user) {
            return null;
        }

        // Get WooCommerce customer data if available
        if (class_exists('WC_Customer')) {
            $customer = new \WC_Customer($userId);
            
            return [
                'userId'        => $userId,
                'email'         => $user->user_email,
                'firstName'     => $customer->get_shipping_first_name() ?: $customer->get_billing_first_name() ?: $user->first_name,
                'lastName'      => $customer->get_shipping_last_name() ?: $customer->get_billing_last_name() ?: $user->last_name,
                'phone'         => $customer->get_billing_phone() ?: '',
                'address'       => $customer->get_shipping_address_1() ?: $customer->get_billing_address_1(),
                'city'          => $customer->get_shipping_city() ?: $customer->get_billing_city(),
                'state'         => $customer->get_shipping_state() ?: $customer->get_billing_state(),
                'postcode'      => $customer->get_shipping_postcode() ?: $customer->get_billing_postcode(),
                'country'       => $customer->get_shipping_country() ?: $customer->get_billing_country() ?: 'US',
                'pointsBalance' => $this->getCustomerPointsBalance($userId),
            ];
        }

        // Fallback without WooCommerce
        return [
            'userId'        => $userId,
            'email'         => $user->user_email,
            'firstName'     => $user->first_name,
            'lastName'      => $user->last_name,
            'phone'         => '',
            'address'       => '',
            'city'          => '',
            'state'         => '',
            'postcode'      => '',
            'country'       => 'US',
            'pointsBalance' => 0,
        ];
    }

    /**
     * Get customer points balance.
     *
     * @param int $userId User ID
     * @return int Points balance
     */
    private function getCustomerPointsBalance(int $userId): int
    {
        // Try WooCommerce Points and Rewards
        if (class_exists('WC_Points_Rewards_Manager')) {
            return (int) \WC_Points_Rewards_Manager::get_users_points($userId);
        }
        
        // Try myCRED
        if (function_exists('mycred_get_users_balance')) {
            return (int) mycred_get_users_balance($userId);
        }
        
        return 0;
    }

    /**
     * Get Stripe publishable key from WooCommerce Stripe Gateway settings.
     */
    private function getStripePublishableKey(string $stripeMode = 'auto', string &$outMode = 'auto'): string
    {
        // First check for WooCommerce Stripe Gateway
        $stripeSettings = get_option('woocommerce_stripe_settings', []);
        $stripeApiSettings = get_option('woocommerce_stripe_api_settings', []);

        $mode = strtolower(trim($stripeMode));
        if ($mode !== 'test' && $mode !== 'live') {
            // Backward-compatible "auto": follow Woo Stripe's own testmode toggle
            $mode = (!empty($stripeSettings['testmode']) && $stripeSettings['testmode'] === 'yes') ? 'test' : 'live';
        }

        $outMode = $mode;

        if ($mode === 'test') {
            return (string) ($stripeSettings['test_publishable_key'] ?: ($stripeApiSettings['publishable_key_test'] ?? ''));
        }
        return (string) ($stripeSettings['publishable_key'] ?: ($stripeApiSettings['publishable_key_live'] ?? ''));
    }
}

