<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

/**
 * AddressCardPickerShortcode - PHP Hydrator for HP React Widgets.
 *
 * Usage: [hp_address_card_picker type="billing" show_actions="true"]
 */
class AddressCardPickerShortcode
{
    /**
     * Render the shortcode output.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        // Parse shortcode attributes.
        $atts = shortcode_atts(
            [
                'type'         => 'billing', // 'billing' or 'shipping'
                'show_actions' => 'true',
                'title'        => '',
            ],
            $atts
        );

        // Ensure the React bundle is present.
        wp_enqueue_script(AssetLoader::HANDLE);

        $user_id = get_current_user_id();

        if (!$user_id) {
            return '<div class="hp-address-picker-login-required">Please log in to view your addresses.</div>';
        }

        // Hydrate addresses for the current user.
        $addresses = $this->get_user_addresses($user_id, $atts['type']);

        // Build edit URL for the native WooCommerce "Edit address" screen.
        $edit_url = '';
        if (function_exists('wc_get_endpoint_url') && function_exists('wc_get_page_permalink')) {
            $edit_url = wc_get_endpoint_url('edit-address', $atts['type'], wc_get_page_permalink('myaccount'));
        }

        $props = [
            'addresses'   => $addresses,
            'type'        => $atts['type'],
            'showActions' => $atts['show_actions'] === 'true',
            'title'       => $atts['title'] ?: null,
            'selectedId'  => $this->get_default_address_id($addresses),
            'editUrl'     => $edit_url,
        ];

        // Use a per-instance container ID so multiple instances can exist on a page.
        $container_id = 'hp-address-card-picker-' . $atts['type'] . '-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($container_id),
            esc_attr('AddressCardPicker'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Get user addresses from WooCommerce + ThemeHigh Multi-Address meta.
     */
    /**
     * Retrieve all addresses for a given user + type.
     *
     * Made public so REST handlers can reuse the same normalization logic.
     */
    public function get_user_addresses(int $user_id, string $type): array
    {
        $addresses = [];
        $customer = new \WC_Customer($user_id);

        // 1. Primary/default address from WooCommerce core.
        $primary = $this->format_wc_address($customer, $type, true);
        if ($primary) {
            $addresses[] = $primary;
        }

        // 2. Additional addresses from ThemeHigh Multi-Address (thwma_custom_address)
        // Structure: ['billing' => ['key' => [...fields...]], 'shipping' => [...]]
        $th_addresses = get_user_meta($user_id, 'thwma_custom_address', true);

        if (is_array($th_addresses) && !empty($th_addresses[$type])) {
            foreach ($th_addresses[$type] as $key => $addr_data) {
                // Determine prefix based on type (ThemeHigh usually prefixes fields with billing_ or shipping_)
                $prefix = $type . '_';

                // Skip if main address fields are missing
                if (empty($addr_data[$prefix . 'address_1']) && empty($addr_data[$prefix . 'first_name'])) {
                    continue;
                }

                $country_code = $addr_data[$prefix . 'country'] ?? '';

                $addresses[] = [
                    'id'        => "th_{$type}_{$key}",
                    'firstName' => $addr_data[$prefix . 'first_name'] ?? '',
                    'lastName'  => $addr_data[$prefix . 'last_name'] ?? '',
                    'company'   => $addr_data[$prefix . 'company'] ?? '',
                    'address1'  => $addr_data[$prefix . 'address_1'] ?? '',
                    'address2'  => $addr_data[$prefix . 'address_2'] ?? '',
                    'city'      => $addr_data[$prefix . 'city'] ?? '',
                    'state'     => $addr_data[$prefix . 'state'] ?? '',
                    'postcode'  => $addr_data[$prefix . 'postcode'] ?? '',
                    'country'   => $this->get_country_name($country_code),
                    'phone'     => $addr_data[$prefix . 'phone'] ?? '',
                    'email'     => $addr_data[$prefix . 'email'] ?? '',
                    'isDefault' => false,
                    'label'     => sprintf('#%d', ((int) $key) + 1),
                ];
            }
        }

        return $addresses;
    }

    /**
     * Format a WooCommerce customer address into the AddressCardPicker format.
     */
    private function format_wc_address(\WC_Customer $customer, string $type, bool $isDefault = false): ?array
    {
        $getter_prefix = $type === 'billing' ? 'get_billing_' : 'get_shipping_';

        $first_name = call_user_func([$customer, $getter_prefix . 'first_name']);
        $address_1  = call_user_func([$customer, $getter_prefix . 'address_1']);

        if (empty($first_name) && empty($address_1)) {
            return null;
        }

        $country_code = call_user_func([$customer, $getter_prefix . 'country']);

        return [
            'id'        => "{$type}_primary",
            'firstName' => $first_name,
            'lastName'  => call_user_func([$customer, $getter_prefix . 'last_name']),
            'company'   => call_user_func([$customer, $getter_prefix . 'company']),
            'address1'  => $address_1,
            'address2'  => call_user_func([$customer, $getter_prefix . 'address_2']),
            'city'      => call_user_func([$customer, $getter_prefix . 'city']),
            'state'     => call_user_func([$customer, $getter_prefix . 'state']),
            'postcode'  => call_user_func([$customer, $getter_prefix . 'postcode']),
            'country'   => $this->get_country_name($country_code),
            'phone'     => $type === 'billing' ? $customer->get_billing_phone() : '',
            'email'     => $type === 'billing' ? $customer->get_billing_email() : '',
            'isDefault' => $isDefault,
        ];
    }

    private function get_country_name(string $country_code): string
    {
        if (empty($country_code)) {
            return '';
        }

        $countries = WC()->countries->get_countries();
        return $countries[$country_code] ?? $country_code;
    }

    /**
     * Determine default address ID from a hydrated list.
     *
     * Exposed for reuse by REST handlers when returning updated data.
     */
    public function get_default_address_id(array $addresses): ?string
    {
        foreach ($addresses as $address) {
            if (!empty($address['isDefault'])) {
                return $address['id'];
            }
        }

        return !empty($addresses[0]['id']) ? $addresses[0]['id'] : null;
    }
}
