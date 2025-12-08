<?php
namespace HP_RW;

use HP_RW\Shortcodes\AddressCardPickerShortcode;
use WP_Error;
use WP_REST_Request;

/**
 * REST API endpoints for address management actions used by the AddressCardPicker.
 */
class AddressApi
{
    /**
     * Hook into WordPress.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes under the hp-rw/v1 namespace.
     */
    public function register_routes(): void
    {
        register_rest_route(
            'hp-rw/v1',
            '/address/delete',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_delete'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'type' => [
                        'required'          => true,
                        'validate_callback' => function ($value): bool {
                            return in_array($value, ['billing', 'shipping'], true);
                        },
                    ],
                    'id'   => [
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            'hp-rw/v1',
            '/address/set-default',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_set_default'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'type' => [
                        'required'          => true,
                        'validate_callback' => function ($value): bool {
                            return in_array($value, ['billing', 'shipping'], true);
                        },
                    ],
                    'id'   => [
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            'hp-rw/v1',
            '/address/copy',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_copy'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'fromType' => [
                        'required'          => true,
                        'validate_callback' => function ($value): bool {
                            return in_array($value, ['billing', 'shipping'], true);
                        },
                    ],
                    'toType'   => [
                        'required'          => true,
                        'validate_callback' => function ($value): bool {
                            return in_array($value, ['billing', 'shipping'], true);
                        },
                    ],
                    'id'       => [
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            'hp-rw/v1',
            '/address/update',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_update'],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    'type' => [
                        'required'          => true,
                        'validate_callback' => function ($value): bool {
                            return in_array($value, ['billing', 'shipping'], true);
                        },
                    ],
                    'id'   => [
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Delete a ThemeHigh additional address for the current user.
     */
    public function handle_delete(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $type = (string) $request->get_param('type');
        $id   = (string) $request->get_param('id');

        // Only allow deleting ThemeHigh-style additional addresses: th_billing_{key} / th_shipping_{key}
        if (!preg_match('/^th_' . preg_quote($type, '/') . '_(.+)$/', $id, $matches)) {
            return new WP_Error('hp_rw_invalid_id', 'This address cannot be deleted from the slider.', ['status' => 400]);
        }

        $key      = $matches[1];
        $meta_key = 'thwma_custom_address';
        $meta     = get_user_meta($user_id, $meta_key, true);

        if (is_array($meta) && isset($meta[$type][$key])) {
            unset($meta[$type][$key]);
            update_user_meta($user_id, $meta_key, $meta);
        }

        $hydrator  = new AddressCardPickerShortcode();
        $addresses = $hydrator->get_user_addresses($user_id, $type);
        $selected  = $hydrator->get_default_address_id($addresses);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => $selected,
        ];
    }

    /**
     * Promote an address to be the default WooCommerce address for its type.
     */
    public function handle_set_default(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $type = (string) $request->get_param('type');
        $id   = (string) $request->get_param('id');

        if (!in_array($type, ['billing', 'shipping'], true)) {
            return new WP_Error('hp_rw_invalid_type', 'Invalid address type.', ['status' => 400]);
        }

        $hydrator  = new AddressCardPickerShortcode();
        $addresses = $hydrator->get_user_addresses($user_id, $type);

        $chosen   = null;
        $current  = null;
        foreach ($addresses as $address) {
            if (isset($address['id']) && (string) $address['id'] === $id) {
                $chosen = $address;
            }
            if (!empty($address['isDefault'])) {
                $current = $address;
            }
        }

        if (!$chosen) {
            return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
        }

        // If the chosen address comes from ThemeHigh (th_{type}_{key}), perform a true swap:
        //  - Chosen address becomes the new WooCommerce default
        //  - Previous default is written back into the same ThemeHigh slot
        if ($current && isset($chosen['id']) && preg_match('/^th_' . preg_quote($type, '/') . '_(.+)$/', (string) $chosen['id'], $m)) {
            $th_key   = $m[1];
            $meta_key = 'thwma_custom_address';
            $meta     = get_user_meta($user_id, $meta_key, true);

            if (is_array($meta) && isset($meta[$type][$th_key])) {
                $prefix = $type . '_';

                // Build a ThemeHigh-style entry from the current default address.
                // Note: Convert country name back to code if needed
                $entry = [
                    $prefix . 'first_name' => $this->ensure_string($current['firstName'] ?? ''),
                    $prefix . 'last_name'  => $this->ensure_string($current['lastName'] ?? ''),
                    $prefix . 'company'    => $this->ensure_string($current['company'] ?? ''),
                    $prefix . 'address_1'  => $this->ensure_string($current['address1'] ?? ''),
                    $prefix . 'address_2'  => $this->ensure_string($current['address2'] ?? ''),
                    $prefix . 'city'       => $this->ensure_string($current['city'] ?? ''),
                    $prefix . 'state'      => $this->ensure_string($current['state'] ?? ''),
                    $prefix . 'postcode'   => $this->ensure_string($current['postcode'] ?? ''),
                    $prefix . 'country'    => $this->get_country_code($current['country'] ?? ''),
                    $prefix . 'phone'      => $this->ensure_string($current['phone'] ?? ''),
                    $prefix . 'email'      => $this->ensure_string($current['email'] ?? ''),
                ];

                // Replace, instead of appending, so the total number of addresses stays constant.
                $meta[$type][$th_key] = $entry;
                update_user_meta($user_id, $meta_key, $meta);
            }
        }

        // Map normalized address array for the newly chosen default back into WooCommerce user meta fields.
        $field_map = [
            'firstName' => 'first_name',
            'lastName'  => 'last_name',
            'company'   => 'company',
            'address1'  => 'address_1',
            'address2'  => 'address_2',
            'city'      => 'city',
            'state'     => 'state',
            'postcode'  => 'postcode',
            'country'   => 'country',
        ];

        foreach ($field_map as $source_key => $meta_suffix) {
            if (isset($chosen[$source_key])) {
                update_user_meta($user_id, $type . '_' . $meta_suffix, $chosen[$source_key]);
            }
        }

        // Phone + email are only meaningful for billing; shipping only has phone.
        if ($type === 'billing') {
            if (isset($chosen['phone'])) {
                update_user_meta($user_id, 'billing_phone', $chosen['phone']);
            }
            if (isset($chosen['email'])) {
                update_user_meta($user_id, 'billing_email', $chosen['email']);
            }
        } else {
            if (isset($chosen['phone'])) {
                update_user_meta($user_id, 'shipping_phone', $chosen['phone']);
            }
        }

        // Re-hydrate updated list.
        $addresses = $hydrator->get_user_addresses($user_id, $type);
        $selected  = $hydrator->get_default_address_id($addresses);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => $selected,
        ];
    }

    /**
     * Copy an address from one type to another (e.g. billing -> shipping).
     */
    public function handle_copy(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $fromType = (string) $request->get_param('fromType');
        $toType   = (string) $request->get_param('toType');
        $id       = (string) $request->get_param('id');

        if (!in_array($fromType, ['billing', 'shipping'], true) || !in_array($toType, ['billing', 'shipping'], true)) {
            return new WP_Error('hp_rw_invalid_type', 'Invalid address type.', ['status' => 400]);
        }

        $hydrator  = new AddressCardPickerShortcode();
        $addresses = $hydrator->get_user_addresses($user_id, $fromType);

        $chosen = null;
        foreach ($addresses as $address) {
            if (isset($address['id']) && (string) $address['id'] === $id) {
                $chosen = $address;
                break;
            }
        }

        if (!$chosen) {
            return new WP_Error('hp_rw_not_found', 'Source address not found.', ['status' => 404]);
        }

        // Copy should create a NEW ThemeHigh address entry for the target type,
        // without touching the target's current default WooCommerce address.
        $meta_key = 'thwma_custom_address';
        $meta     = get_user_meta($user_id, $meta_key, true);
        if (!is_array($meta)) {
            $meta = [
                'billing'  => [],
                'shipping' => [],
            ];
        }

        if (!isset($meta[$toType]) || !is_array($meta[$toType])) {
            $meta[$toType] = [];
        }

        $prefix = $toType . '_';

        // Map normalized address fields into ThemeHigh-style keys.
        // Note: Convert country name back to code for THWMA storage
        $entry = [
            $prefix . 'first_name' => $this->ensure_string($chosen['firstName'] ?? ''),
            $prefix . 'last_name'  => $this->ensure_string($chosen['lastName'] ?? ''),
            $prefix . 'company'    => $this->ensure_string($chosen['company'] ?? ''),
            $prefix . 'address_1'  => $this->ensure_string($chosen['address1'] ?? ''),
            $prefix . 'address_2'  => $this->ensure_string($chosen['address2'] ?? ''),
            $prefix . 'city'       => $this->ensure_string($chosen['city'] ?? ''),
            $prefix . 'state'      => $this->ensure_string($chosen['state'] ?? ''),
            $prefix . 'postcode'   => $this->ensure_string($chosen['postcode'] ?? ''),
            $prefix . 'country'    => $this->get_country_code($chosen['country'] ?? ''),
            $prefix . 'phone'      => $this->ensure_string($chosen['phone'] ?? ''),
            $prefix . 'email'      => $this->ensure_string($chosen['email'] ?? ''),
        ];

        $meta[$toType][] = $entry;
        update_user_meta($user_id, $meta_key, $meta);

        // Return updated target-type addresses (Woo default + ThemeHigh list).
        $targetAddresses = $hydrator->get_user_addresses($user_id, $toType);
        $selected        = $hydrator->get_default_address_id($targetAddresses);

        return [
            'success'    => true,
            'fromType'   => $fromType,
            'toType'     => $toType,
            'addresses'  => $targetAddresses,
            'selectedId' => $selected,
        ];
    }

    /**
     * Update an existing address (either the primary Woo address or a ThemeHigh entry).
     */
    public function handle_update(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $type = (string) $request->get_param('type');
        $id   = (string) $request->get_param('id');

        if (!in_array($type, ['billing', 'shipping'], true)) {
            return new WP_Error('hp_rw_invalid_type', 'Invalid address type.', ['status' => 400]);
        }

        $payload = [
            'firstName' => (string) $request->get_param('firstName'),
            'lastName'  => (string) $request->get_param('lastName'),
            'company'   => (string) $request->get_param('company'),
            'address1'  => (string) $request->get_param('address1'),
            'address2'  => (string) $request->get_param('address2'),
            'city'      => (string) $request->get_param('city'),
            'state'     => (string) $request->get_param('state'),
            'postcode'  => (string) $request->get_param('postcode'),
            'country'   => (string) $request->get_param('country'),
            'phone'     => (string) $request->get_param('phone'),
            'email'     => (string) $request->get_param('email'),
        ];

        // Update primary WooCommerce address.
        if (preg_match('/^' . preg_quote($type, '/') . '_primary$/', $id)) {
            $customer = new \WC_Customer($user_id);

            $setter_prefix = $type === 'billing' ? 'set_billing_' : 'set_shipping_';

            $map = [
                'firstName' => 'first_name',
                'lastName'  => 'last_name',
                'company'   => 'company',
                'address1'  => 'address_1',
                'address2'  => 'address_2',
                'city'      => 'city',
                'state'     => 'state',
                'postcode'  => 'postcode',
                'country'   => 'country',
            ];

            foreach ($map as $source => $suffix) {
                if ($payload[$source] !== '') {
                    $method = $setter_prefix . $suffix;
                    if (is_callable([$customer, $method])) {
                        $customer->$method($payload[$source]);
                    }
                }
            }

            // Phone + email
            if ($type === 'billing') {
                if ($payload['phone'] !== '') {
                    $customer->set_billing_phone($payload['phone']);
                }
                if ($payload['email'] !== '') {
                    $customer->set_billing_email($payload['email']);
                }
            } else {
                if ($payload['phone'] !== '') {
                    $customer->set_shipping_phone($payload['phone']);
                }
            }

            $customer->save();
        } elseif (preg_match('/^th_' . preg_quote($type, '/') . '_(.+)$/', $id, $m)) {
            // Update ThemeHigh entry for this user.
            $th_key   = $m[1];
            $meta_key = 'thwma_custom_address';
            $meta     = get_user_meta($user_id, $meta_key, true);

            if (!is_array($meta) || !isset($meta[$type][$th_key])) {
                return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
            }

            $prefix = $type . '_';

            $entry = [
                $prefix . 'first_name' => $this->ensure_string($payload['firstName']),
                $prefix . 'last_name'  => $this->ensure_string($payload['lastName']),
                $prefix . 'company'    => $this->ensure_string($payload['company']),
                $prefix . 'address_1'  => $this->ensure_string($payload['address1']),
                $prefix . 'address_2'  => $this->ensure_string($payload['address2']),
                $prefix . 'city'       => $this->ensure_string($payload['city']),
                $prefix . 'state'      => $this->ensure_string($payload['state']),
                $prefix . 'postcode'   => $this->ensure_string($payload['postcode']),
                $prefix . 'country'    => $this->get_country_code($payload['country']),
                $prefix . 'phone'      => $this->ensure_string($payload['phone']),
                $prefix . 'email'      => $this->ensure_string($payload['email']),
            ];

            $meta[$type][$th_key] = $entry;
            update_user_meta($user_id, $meta_key, $meta);
        } else {
            return new WP_Error('hp_rw_invalid_id', 'Unsupported address ID format.', ['status' => 400]);
        }

        // Re-hydrate updated list.
        $hydrator  = new AddressCardPickerShortcode();
        $addresses = $hydrator->get_user_addresses($user_id, $type);
        $selected  = $hydrator->get_default_address_id($addresses);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => $selected,
        ];
    }

    /**
     * Ensure a value is a string (not an array or object).
     *
     * @param mixed $value The value to convert.
     * @return string
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
     * Convert a country name or "Name (CODE)" format to just the country code.
     *
     * @param string $country The country value (could be "United States (US)", "US", etc.)
     * @return string The 2-letter country code
     */
    private function get_country_code(string $country): string
    {
        $country = $this->ensure_string($country);

        if (empty($country)) {
            return '';
        }

        // If already a 2-letter code, return as-is
        if (strlen($country) === 2 && $country === strtoupper($country)) {
            return $country;
        }

        // Check for format "Country Name (XX)" - extract code from parentheses
        if (preg_match('/\(([A-Z]{2})\)$/', $country, $matches)) {
            return $matches[1];
        }

        // Try to find the country code by name using WooCommerce
        if (function_exists('WC') && WC()->countries) {
            $countries = WC()->countries->get_countries();
            $code = array_search($country, $countries, true);
            if ($code !== false) {
                return $code;
            }
        }

        // If we can't determine the code, return the original value
        // (it might already be a valid code that we didn't recognize)
        return $country;
    }
}

