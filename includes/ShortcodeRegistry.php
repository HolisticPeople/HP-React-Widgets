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
        $enabled       = Plugin::get_enabled_shortcodes();
        $allShortcodes = Plugin::get_shortcodes();

        foreach ($enabled as $slug) {
            if (!isset($allShortcodes[$slug])) {
                continue;
            }

            // Built-in shortcodes with dedicated render methods.
            if ($slug === 'hp_multi_address') {
                add_shortcode('hp_multi_address', [$this, 'renderMultiAddress']);
                continue;
            }

            if ($slug === 'hp_my_account_header') {
                add_shortcode('hp_my_account_header', [$this, 'renderMyAccountHeader']);
                continue;
            }

            // Custom shortcode handled via generic renderer and optional hydrator class.
            $config = $allShortcodes[$slug];

            add_shortcode($slug, function ($atts = []) use ($config) {
                return $this->renderGeneric($config, (array) $atts);
            });
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
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr('hp-multi-address-root'),
            esc_attr('MultiAddress'),
            esc_attr(wp_json_encode($props))
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
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr('hp-my-account-header-root'),
            esc_attr('MyAccountHeader'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Generic renderer used for custom shortcodes defined via the wizard.
     *
     * @param array<string,mixed> $config
     * @param array<string,mixed> $atts
     */
    private function renderGeneric(array $config, array $atts): string
    {
        wp_enqueue_script(AssetLoader::HANDLE);

        $rootId    = isset($config['root_id']) ? (string) $config['root_id'] : 'hp-generic-widget-root';
        $component = isset($config['component']) ? (string) $config['component'] : '';

        // If a hydrator class is configured and available, let it take over completely.
        if (!empty($config['hydrator_class'])) {
            $class = 'HP_RW\\Shortcodes\\' . ltrim((string) $config['hydrator_class'], '\\');
            if (class_exists($class) && method_exists($class, 'render')) {
                $instance = new $class();
                /** @var mixed $output */
                $output = $instance->render($atts);
                if (is_string($output)) {
                    return $output;
                }
            }
        }

        $props = isset($config['default_props']) && is_array($config['default_props'])
            ? $config['default_props']
            : [];

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}


