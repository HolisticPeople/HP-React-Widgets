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
        $resolvedStripeMode = 'auto';
        $stripeMode = (string) ($config['stripe_mode'] ?? 'auto');
        $stripeKey = $this->getStripePublishableKey($stripeMode, $resolvedStripeMode);

        // Get PayPal config (can be set independently or follow Stripe mode)
        $paypalMode = (string) ($config['paypal_mode'] ?? 'auto');
        $paypalConfig = $this->getPayPalConfig($paypalMode, $resolvedStripeMode);

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
            'showAllOffers'       => $config['checkout']['show_all_offers'] ?? 'all',
            'stripePublishableKey' => $stripeKey,
            'stripeMode'          => $resolvedStripeMode, // Use the resolved 'test' or 'live'
            'paypalEnabled'       => $paypalConfig['enabled'],
            'paypalClientId'      => $paypalConfig['client_id'],
            'upsellOffers'        => $this->buildUpsellOffers($config['thankyou']['upsell'] ?? null),
            'showUpsell'          => (bool) ($config['thankyou']['show_upsell'] ?? false),
            'thankYouHeadline'    => $config['thankyou']['headline'] ?? 'Thank You for Your Order!',
            'thankYouMessage'     => $config['thankyou']['message'] ?? 'Your order has been confirmed.',
            'accentColor'         => $config['styling']['accent_color'] ?? '#eab308',
            'footerText'          => $config['footer']['text'] ?? '',
            'footerDisclaimer'    => $config['footer']['disclaimer'] ?? '',
            'initialUserData'     => $initialUserData,
            // Page title and legal page IDs for checkout
            'pageTitle'           => $config['checkout']['page_title'] ?? 'Secure Your Order',
            'pageSubtitle'        => $config['checkout']['page_subtitle'] ?? '',
            'tosPageId'           => (int) ($config['checkout']['tos_page_id'] ?? 0),
            'privacyPageId'       => (int) ($config['checkout']['privacy_page_id'] ?? 0),
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
                'savedAddresses' => $this->getSavedAddresses($userId, 'shipping'),
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
            'savedAddresses' => [],
        ];
    }
    
    /**
     * Get saved addresses for a user from HP-Multi-Address or ThemeHigh.
     *
     * @param int    $userId User ID
     * @param string $type   Address type ('shipping' or 'billing')
     * @return array Array of addresses formatted for React
     */
    private function getSavedAddresses(int $userId, string $type = 'shipping'): array
    {
        $addresses = [];
        $prefix = $type . '_';
        
        // First add the default WooCommerce address
        if (class_exists('WC_Customer')) {
            $customer = new \WC_Customer($userId);
            $defaultAddress = [
                'id'        => $type . '_primary',
                'firstName' => $type === 'shipping' ? $customer->get_shipping_first_name() : $customer->get_billing_first_name(),
                'lastName'  => $type === 'shipping' ? $customer->get_shipping_last_name() : $customer->get_billing_last_name(),
                'company'   => $type === 'shipping' ? $customer->get_shipping_company() : $customer->get_billing_company(),
                'address1'  => $type === 'shipping' ? $customer->get_shipping_address_1() : $customer->get_billing_address_1(),
                'address2'  => $type === 'shipping' ? $customer->get_shipping_address_2() : $customer->get_billing_address_2(),
                'city'      => $type === 'shipping' ? $customer->get_shipping_city() : $customer->get_billing_city(),
                'state'     => $type === 'shipping' ? $customer->get_shipping_state() : $customer->get_billing_state(),
                'postcode'  => $type === 'shipping' ? $customer->get_shipping_postcode() : $customer->get_billing_postcode(),
                'country'   => $type === 'shipping' ? $customer->get_shipping_country() : $customer->get_billing_country(),
                'phone'     => $customer->get_billing_phone(),
                'email'     => $customer->get_billing_email(),
                'isDefault' => true,
            ];
            
            // Only add if address has content
            if (!empty($defaultAddress['address1']) || !empty($defaultAddress['firstName'])) {
                $addresses[] = $defaultAddress;
            }
        }
        
        // Try to get additional addresses from HP-Multi-Address
        if (defined('HP_MA_ADDRESS_KEY')) {
            $savedAddresses = get_user_meta($userId, HP_MA_ADDRESS_KEY, true);
            if (is_array($savedAddresses) && isset($savedAddresses[$type])) {
                foreach ($savedAddresses[$type] as $key => $addr) {
                    // Skip if it matches the primary address
                    $address = [
                        'id'        => $key,
                        'firstName' => $addr[$prefix . 'first_name'] ?? '',
                        'lastName'  => $addr[$prefix . 'last_name'] ?? '',
                        'company'   => $addr[$prefix . 'company'] ?? '',
                        'address1'  => $addr[$prefix . 'address_1'] ?? '',
                        'address2'  => $addr[$prefix . 'address_2'] ?? '',
                        'city'      => $addr[$prefix . 'city'] ?? '',
                        'state'     => $addr[$prefix . 'state'] ?? '',
                        'postcode'  => $addr[$prefix . 'postcode'] ?? '',
                        'country'   => $addr[$prefix . 'country'] ?? '',
                        'phone'     => $addr[$prefix . 'phone'] ?? '',
                        'email'     => $addr['billing_email'] ?? '',
                        'isDefault' => false,
                    ];
                    
                    // Check if this is a duplicate of the primary
                    if (!empty($addresses) && $this->isSameAddress($addresses[0], $address)) {
                        continue;
                    }
                    
                    if (!empty($address['address1']) || !empty($address['firstName'])) {
                        $addresses[] = $address;
                    }
                }
            }
        }
        
        // Also try ThemeHigh Multi-Address format
        $thwmaAddresses = get_user_meta($userId, 'thwma_custom_address', true);
        if (is_array($thwmaAddresses) && isset($thwmaAddresses[$type])) {
            foreach ($thwmaAddresses[$type] as $key => $addr) {
                $address = [
                    'id'        => 'thwma_' . $key,
                    'firstName' => $addr[$prefix . 'first_name'] ?? '',
                    'lastName'  => $addr[$prefix . 'last_name'] ?? '',
                    'company'   => $addr[$prefix . 'company'] ?? '',
                    'address1'  => $addr[$prefix . 'address_1'] ?? '',
                    'address2'  => $addr[$prefix . 'address_2'] ?? '',
                    'city'      => $addr[$prefix . 'city'] ?? '',
                    'state'     => $addr[$prefix . 'state'] ?? '',
                    'postcode'  => $addr[$prefix . 'postcode'] ?? '',
                    'country'   => $addr[$prefix . 'country'] ?? '',
                    'phone'     => $addr[$prefix . 'phone'] ?? '',
                    'email'     => $addr['billing_email'] ?? '',
                    'isDefault' => false,
                ];
                
                // Check for duplicates
                $isDuplicate = false;
                foreach ($addresses as $existing) {
                    if ($this->isSameAddress($existing, $address)) {
                        $isDuplicate = true;
                        break;
                    }
                }
                
                if (!$isDuplicate && (!empty($address['address1']) || !empty($address['firstName']))) {
                    $addresses[] = $address;
                }
            }
        }
        
        return $addresses;
    }
    
    /**
     * Check if two addresses are the same.
     */
    private function isSameAddress(array $a, array $b): bool
    {
        return ($a['address1'] ?? '') === ($b['address1'] ?? '') 
            && ($a['postcode'] ?? '') === ($b['postcode'] ?? '')
            && ($a['city'] ?? '') === ($b['city'] ?? '');
    }

    /**
     * Get customer points balance.
     *
     * @param int $userId User ID
     * @return int Points balance
     */
    private function getCustomerPointsBalance(int $userId): int
    {
        // Try YITH WooCommerce Points and Rewards (most common on this site)
        if (function_exists('ywpar_get_customer')) {
            $customer = ywpar_get_customer($userId);
            if ($customer && method_exists($customer, 'get_total_points')) {
                return (int) $customer->get_total_points();
            }
        }
        
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

    /**
     * Get PayPal configuration based on mode.
     *
     * @param string $paypalMode PayPal mode from funnel config: 'auto', 'live', or 'sandbox'
     * @param string $stripeMode Resolved Stripe mode to use when paypalMode is 'auto'
     * @return array PayPal config with 'enabled' and 'client_id'
     */
    private function getPayPalConfig(string $paypalMode, string $stripeMode): array
    {
        $paypalSettings = get_option('hp_rw_paypal_settings', []);
        
        $enabled = !empty($paypalSettings['enabled']);
        
        // Determine which credentials to use
        $resolvedMode = $paypalMode;
        if ($paypalMode === 'auto' || $paypalMode === '') {
            // Follow Stripe mode: 'test' -> 'sandbox', 'live' -> 'live'
            $resolvedMode = ($stripeMode === 'test') ? 'sandbox' : 'live';
        }
        
        // Get appropriate client ID
        if ($resolvedMode === 'sandbox' || $resolvedMode === 'test') {
            $clientId = $paypalSettings['sandbox_client_id'] ?? '';
        } else {
            $clientId = $paypalSettings['live_client_id'] ?? '';
        }
        
        return [
            'enabled'   => $enabled && !empty($clientId),
            'client_id' => $clientId,
        ];
    }
}

