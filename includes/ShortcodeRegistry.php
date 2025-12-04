<?php
namespace HP_RW;

class ShortcodeRegistry
{
    private $assetLoader;

    public function __construct(AssetLoader $assetLoader)
    {
        $this->assetLoader = $assetLoader;
    }

    /**
     * Register shortcodes based on which ones are enabled in settings.
     */
    public function register(): void
    {
        $enabled = Plugin::get_enabled_shortcodes();

        if (in_array('hp_multi_address', $enabled, true)) {
            add_shortcode('hp_multi_address', [$this, 'renderMultiAddress']);
        }

        if (in_array('hp_my_account_header', $enabled, true)) {
            add_shortcode('hp_my_account_header', [$this, 'renderMyAccountHeader']);
        }
    }

    public function renderMultiAddress($atts)
    {
        // Ensure assets are enqueued when this shortcode is used
        wp_enqueue_script(AssetLoader::HANDLE);

        // Hydration: Fetch addresses server-side
        $user_id   = get_current_user_id();
        $addresses = [];

        if ($user_id) {
            $customer = new \WC_Customer($user_id);
            // For this POC, we expose the standard billing/shipping addresses.

            $addresses[] = [
                'id'         => 'billing',
                'type'       => 'billing',
                'first_name' => $customer->get_billing_first_name(),
                'last_name'  => $customer->get_billing_last_name(),
                'address_1'  => $customer->get_billing_address_1(),
                'city'       => $customer->get_billing_city(),
                'state'      => $customer->get_billing_state(),
                'postcode'   => $customer->get_billing_postcode(),
                'country'    => $customer->get_billing_country(),
                'phone'      => $customer->get_billing_phone(),
            ];

            $addresses[] = [
                'id'         => 'shipping',
                'type'       => 'shipping',
                'first_name' => $customer->get_shipping_first_name(),
                'last_name'  => $customer->get_shipping_last_name(),
                'address_1'  => $customer->get_shipping_address_1(),
                'city'       => $customer->get_shipping_city(),
                'state'      => $customer->get_shipping_state(),
                'postcode'   => $customer->get_shipping_postcode(),
                'country'    => $customer->get_shipping_country(),
            ];
        }

        $props = [
            'addresses'  => $addresses,
            'isLoggedIn' => $user_id > 0,
        ];

        return sprintf(
            '<div id="hp-multi-address-root" data-props="%s"></div>',
            htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8')
        );
    }

    public function renderMyAccountHeader($atts)
    {
        // Ensure assets are enqueued when this shortcode is used
        wp_enqueue_script(AssetLoader::HANDLE);

        // Hydration: Fetch user and nav data
        $user_id = get_current_user_id();
        $user    = wp_get_current_user();

        // Build nav items from WooCommerce My Account pages
        $nav_items = [];

        // Check if WooCommerce is active
        if (function_exists('wc_get_account_endpoint_url')) {
            $nav_items = [
                [
                    'id'    => 'orders',
                    'label' => 'Orders',
                    'icon'  => 'orders',
                    'href'  => wc_get_account_endpoint_url('orders'),
                ],
                [
                    'id'    => 'addresses',
                    'label' => 'Addresses',
                    'icon'  => 'addresses',
                    'href'  => wc_get_account_endpoint_url('edit-address'),
                ],
                [
                    'id'    => 'profile',
                    'label' => 'Profile',
                    'icon'  => 'profile',
                    'href'  => wc_get_account_endpoint_url('edit-account'),
                ],
            ];

            // Optional: Add custom nav items
            if (shortcode_exists('my_points_rewards')) {
                $nav_items[] = [
                    'id'    => 'points',
                    'label' => 'My Points',
                    'icon'  => 'points',
                    'href'  => wc_get_account_endpoint_url('points-and-rewards'),
                ];
            }

            $logout_url = wc_get_account_endpoint_url('customer-logout');
        } else {
            // Fallback if WooCommerce not available
            $nav_items = [
                [
                    'id'    => 'orders',
                    'label' => 'Orders',
                    'icon'  => 'orders',
                    'href'  => '/my-account/orders/',
                ],
                [
                    'id'    => 'addresses',
                    'label' => 'Addresses',
                    'icon'  => 'addresses',
                    'href'  => '/my-account/edit-address/',
                ],
                [
                    'id'    => 'profile',
                    'label' => 'Profile',
                    'icon'  => 'profile',
                    'href'  => '/my-account/edit-account/',
                ],
            ];
            $logout_url = '/my-account/customer-logout/';
        }

        $props = [
            'userName'    => $user->display_name ?: 'Guest',
            'avatarUrl'   => get_avatar_url($user_id),
            'navItems'    => $nav_items,
            'activeNavId' => '',
            'logoutUrl'   => $logout_url,
        ];

        return sprintf(
            '<div id="hp-my-account-header-root" data-props="%s"></div>',
            htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8')
        );
    }
}


