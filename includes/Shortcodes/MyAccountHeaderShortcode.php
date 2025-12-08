<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

class MyAccountHeaderShortcode
{
    /**
     * Render the My Account header shortcode container with hydrated props.
     *
     * @param array $atts
     */
    public function render(array $atts = []): string
    {
        // Ensure assets are enqueued when this shortcode is used.
        wp_enqueue_script(AssetLoader::HANDLE);

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();

        // Build nav items from WooCommerce My Account pages.
        $nav_items = [];

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

            // Optional: Add custom nav items.
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
            // Fallback if WooCommerce is not available.
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
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr('hp-my-account-header-root'),
            esc_attr('MyAccountHeader'),
            esc_attr(wp_json_encode($props))
        );
    }
}





