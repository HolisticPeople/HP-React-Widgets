<?php
namespace HP_RW;

class ShortcodeRegistry
{
    private $assetLoader;

    public function __construct(AssetLoader $assetLoader)
    {
        $this->assetLoader = $assetLoader;
    }

    public function register()
    {
        add_shortcode('hp_multi_address', [$this, 'renderMultiAddress']);
    }

    public function renderMultiAddress($atts)
    {
        // Ensure assets are enqueued when this shortcode is used
        wp_enqueue_script(AssetLoader::HANDLE);

        // Hydration: Fetch addresses server-side
        $user_id = get_current_user_id();
        $addresses = [];

        if ($user_id) {
            $customer = new \WC_Customer($user_id);
            // This is a simplified example. In reality, WC stores billing/shipping.
            // For a "Multi-Address" feature, you might be using a plugin or custom meta.
            // For this POC, we'll just send the standard billing/shipping as the initial list.

            $addresses[] = [
                'id' => 'billing',
                'type' => 'billing',
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'address_1' => $customer->get_billing_address_1(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
                'phone' => $customer->get_billing_phone(),
            ];

            $addresses[] = [
                'id' => 'shipping',
                'type' => 'shipping',
                'first_name' => $customer->get_shipping_first_name(),
                'last_name' => $customer->get_shipping_last_name(),
                'address_1' => $customer->get_shipping_address_1(),
                'city' => $customer->get_shipping_city(),
                'state' => $customer->get_shipping_state(),
                'postcode' => $customer->get_shipping_postcode(),
                'country' => $customer->get_shipping_country(),
            ];
        }

        $props = [
            'addresses' => $addresses,
            'isLoggedIn' => $user_id > 0
        ];

        return sprintf(
            '<div id="hp-multi-address-root" data-props="%s"></div>',
            htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8')
        );
    }
}
