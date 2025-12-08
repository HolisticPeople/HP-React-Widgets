<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

class MultiAddressShortcode
{
    /**
     * Render the multi-address shortcode container with hydrated props.
     *
     * @param array $atts
     */
    public function render(array $atts = []): string
    {
        // Ensure assets are enqueued when this shortcode is used.
        wp_enqueue_script(AssetLoader::HANDLE);

        // Hydration: Fetch addresses server-side.
        $user_id   = get_current_user_id();
        $addresses = [];

        if ($user_id) {
            $customer = new \WC_Customer($user_id);

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
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr('hp-multi-address-root'),
            esc_attr('MultiAddress'),
            esc_attr(wp_json_encode($props))
        );
    }
}





