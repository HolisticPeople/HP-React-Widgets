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

        // Enqueue the React bundle (only loads on pages with this shortcode).
        AssetLoader::enqueue_bundle();

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

        // 2. Additional addresses from custom storage (compatible with ThemeHigh data structure)
        // Structure: ['billing' => ['key' => [...fields...]], 'shipping' => [...]]
        $meta_key = apply_filters('hp_rw_address_meta_key', 'thwma_custom_address');
        $th_addresses = get_user_meta($user_id, $meta_key, true);

        if (is_array($th_addresses) && !empty($th_addresses[$type])) {
            $counter = 1;
            $needs_repair = false;
            
            // Get primary address name as fallback for addresses missing names
            $primary = $this->format_wc_address($customer, $type, false);
            $fallback_first = $primary['firstName'] ?? '';
            $fallback_last  = $primary['lastName'] ?? '';
            $fallback_phone = $primary['phone'] ?? '';
            $fallback_email = $primary['email'] ?? '';
            
            foreach ($th_addresses[$type] as $key => $addr_data) {
                // Determine prefix based on type (ThemeHigh usually prefixes fields with billing_ or shipping_)
                $prefix = $type . '_';

                // Sanitize all fields - ensure they are strings, not arrays
                $sanitized = $this->sanitize_address_data($addr_data, $prefix);
                
                // Check if we had to repair any data
                if ($sanitized !== $addr_data) {
                    $th_addresses[$type][$key] = $sanitized;
                    $needs_repair = true;
                }

                // Skip if main address fields are missing
                if (empty($sanitized[$prefix . 'address_1']) && empty($sanitized[$prefix . 'first_name'])) {
                    continue;
                }

                $country_code = $sanitized[$prefix . 'country'] ?? '';
                
                // Use stored name, or fall back to primary address name
                $first_name = !empty($sanitized[$prefix . 'first_name']) 
                    ? $sanitized[$prefix . 'first_name'] 
                    : $fallback_first;
                $last_name = !empty($sanitized[$prefix . 'last_name']) 
                    ? $sanitized[$prefix . 'last_name'] 
                    : $fallback_last;
                    
                // Also fall back for phone and email
                $phone = !empty($sanitized[$prefix . 'phone']) 
                    ? $sanitized[$prefix . 'phone'] 
                    : $fallback_phone;
                $email = !empty($sanitized[$prefix . 'email']) 
                    ? $sanitized[$prefix . 'email'] 
                    : $fallback_email;

                $addresses[] = [
                    'id'        => "th_{$type}_{$key}",
                    'firstName' => $first_name,
                    'lastName'  => $last_name,
                    'company'   => $sanitized[$prefix . 'company'] ?? '',
                    'address1'  => $sanitized[$prefix . 'address_1'] ?? '',
                    'address2'  => $sanitized[$prefix . 'address_2'] ?? '',
                    'city'      => $sanitized[$prefix . 'city'] ?? '',
                    'state'     => $sanitized[$prefix . 'state'] ?? '',
                    'postcode'  => $sanitized[$prefix . 'postcode'] ?? '',
                    'country'   => $this->get_country_name($country_code),
                    'phone'     => $phone,
                    'email'     => $email,
                    'isDefault' => false,
                    'label'     => sprintf('#%d', $counter++),
                ];
            }
            
            // Auto-repair corrupted data in database
            if ($needs_repair) {
                update_user_meta($user_id, $meta_key, $th_addresses);
            }
        }

        return $addresses;
    }
    
    /**
     * Sanitize address data to ensure all values are strings.
     * This fixes corrupted data where arrays were saved instead of strings.
     */
    private function sanitize_address_data(array $addr_data, string $prefix): array
    {
        $fields = [
            'first_name', 'last_name', 'company', 'address_1', 'address_2',
            'city', 'state', 'postcode', 'country', 'phone', 'email'
        ];
        
        foreach ($fields as $field) {
            $key = $prefix . $field;
            if (isset($addr_data[$key])) {
                $addr_data[$key] = $this->ensure_string($addr_data[$key]);
            }
        }
        
        return $addr_data;
    }
    
    /**
     * Ensure a value is a string (not an array or object).
     */
    private function ensure_string($value): string
    {
        if (is_array($value)) {
            // If it's an array, try to get the first string element or return empty
            foreach ($value as $v) {
                if (is_string($v) && !empty($v)) {
                    return $v;
                }
            }
            return '';
        }

        if (is_object($value)) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Format a WooCommerce customer address into the AddressCardPicker format.
     */
    private function format_wc_address(\WC_Customer $customer, string $type, bool $isDefault = false): ?array
    {
        $getter_prefix = $type === 'billing' ? 'get_billing_' : 'get_shipping_';

        $first_name = call_user_func([$customer, $getter_prefix . 'first_name']);
        $last_name  = call_user_func([$customer, $getter_prefix . 'last_name']);
        $address_1  = call_user_func([$customer, $getter_prefix . 'address_1']);

        // Shipping addresses often don't have names if user never shipped to a different address.
        // Fall back to billing name in this case.
        if ($type === 'shipping' && empty($first_name)) {
            $first_name = $customer->get_billing_first_name();
            $last_name  = $customer->get_billing_last_name();
        }

        if (empty($first_name) && empty($address_1)) {
            return null;
        }

        $country_code = call_user_func([$customer, $getter_prefix . 'country']);

        // For shipping, phone may also be empty - fall back to billing phone
        $phone = $type === 'billing' 
            ? $customer->get_billing_phone() 
            : ($customer->get_shipping_phone() ?: $customer->get_billing_phone());

        return [
            'id'        => "{$type}_primary",
            'firstName' => $first_name,
            'lastName'  => $last_name,
            'company'   => call_user_func([$customer, $getter_prefix . 'company']),
            'address1'  => $address_1,
            'address2'  => call_user_func([$customer, $getter_prefix . 'address_2']),
            'city'      => call_user_func([$customer, $getter_prefix . 'city']),
            'state'     => call_user_func([$customer, $getter_prefix . 'state']),
            'postcode'  => call_user_func([$customer, $getter_prefix . 'postcode']),
            'country'   => $this->get_country_name($country_code),
            'phone'     => $phone,
            // Email is only meaningful for billing; we keep it empty for shipping.
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
