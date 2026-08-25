<?php
namespace HP_RW;

use WP_Error;
use WP_REST_Request;

/**
 * REST API endpoints for address management actions used by the AddressCardPicker.
 */
class AddressApi
{
    /**
     * Return HP Core's address-book service when the provider is active.
     *
     * HP React Widgets is a contract consumer. It never reads or writes the
     * underlying additional-address storage directly.
     */
    public static function get_address_service(): ?object
    {
        if (!class_exists('\\HP_Core\\Plugin') || !method_exists('\\HP_Core\\Plugin', 'get_service')) {
            return null;
        }
        $service = \HP_Core\Plugin::get_service('address');
        return ($service && method_exists($service, 'get_hydrated_addresses')) ? $service : null;
    }

    /** Return a stable fail-soft error when HP Core cannot serve an action. */
    private function address_service_unavailable(): WP_Error
    {
        return new WP_Error(
            'hp_rw_address_service_unavailable',
            'Additional address management is temporarily unavailable.',
            ['status' => 503]
        );
    }

    /** Map the widget's normalized payload to HP Core's prefixed contract. */
    private function to_service_address(array $payload, string $type): array
    {
        $prefix = $type . '_';

        return [
            $prefix . 'first_name' => $this->ensure_string($payload['firstName'] ?? ''),
            $prefix . 'last_name'  => $this->ensure_string($payload['lastName'] ?? ''),
            $prefix . 'company'    => $this->ensure_string($payload['company'] ?? ''),
            $prefix . 'address_1'  => $this->ensure_string($payload['address1'] ?? ''),
            $prefix . 'address_2'  => $this->ensure_string($payload['address2'] ?? ''),
            $prefix . 'city'       => $this->ensure_string($payload['city'] ?? ''),
            $prefix . 'state'      => $this->ensure_string($payload['state'] ?? ''),
            $prefix . 'postcode'   => $this->ensure_string($payload['postcode'] ?? ''),
            $prefix . 'country'    => $this->get_country_code($payload['country'] ?? ''),
            $prefix . 'phone'      => $this->ensure_string($payload['phone'] ?? ''),
            $prefix . 'email'      => $this->ensure_string($payload['email'] ?? ''),
        ];
    }

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

        register_rest_route(
            'hp-rw/v1',
            '/address/create',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_create'],
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
                ],
            ]
        );
    }

    /** Delete an HP Core-owned additional address for the current user. */
    public function handle_delete(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $type = (string) $request->get_param('type');
        $id   = (string) $request->get_param('id');

        // Preserve the established browser-facing ID contract.
        if (!preg_match('/^th_' . preg_quote($type, '/') . '_(.+)$/', $id, $matches)) {
            return new WP_Error('hp_rw_invalid_id', 'This address cannot be deleted from the slider.', ['status' => 400]);
        }

        $service = self::get_address_service();
        if (!$service || !method_exists($service, 'delete_address') || !method_exists($service, 'get_default_address_id')) {
            return $this->address_service_unavailable();
        }
        if (!$service->delete_address($user_id, $type, $matches[1])) {
            return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
        }

        $addresses = $service->get_hydrated_addresses($user_id, $type);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => $service->get_default_address_id($addresses),
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

        $service = self::get_address_service();
        $addresses = $service
            ? $service->get_hydrated_addresses($user_id, $type)
            : $this->get_native_woo_addresses($user_id, $type);

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

        // Additional-address promotion is owned atomically by HP Core.
        if ($current && isset($chosen['id']) && preg_match('/^th_' . preg_quote($type, '/') . '_(.+)$/', (string) $chosen['id'], $m)) {
            if (!$service || !method_exists($service, 'set_default_address') || !method_exists($service, 'get_default_address_id')) {
                return $this->address_service_unavailable();
            }
            if (!$service->set_default_address($user_id, $type, $m[1])) {
                return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
            }

            $addresses = $service->get_hydrated_addresses($user_id, $type);
            return [
                'success'    => true,
                'type'       => $type,
                'addresses'  => $addresses,
                'selectedId' => $service->get_default_address_id($addresses),
            ];
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
        $addresses = $service
            ? $service->get_hydrated_addresses($user_id, $type)
            : $this->get_native_woo_addresses($user_id, $type);
        $selected = $service && method_exists($service, 'get_default_address_id')
            ? $service->get_default_address_id($addresses)
            : ($addresses[0]['id'] ?? null);

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

        $service = self::get_address_service();
        if (!$service || !method_exists($service, 'save_address') || !method_exists($service, 'get_default_address_id')) {
            return $this->address_service_unavailable();
        }
        $addresses = $service->get_hydrated_addresses($user_id, $fromType);

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

        $new_key = $service->save_address($user_id, $this->to_service_address($chosen, $toType), $toType);
        if ($new_key === false) {
            return new WP_Error('hp_rw_address_save_failed', 'Unable to copy address.', ['status' => 400]);
        }

        $targetAddresses = $service->get_hydrated_addresses($user_id, $toType);

        return [
            'success'    => true,
            'fromType'   => $fromType,
            'toType'     => $toType,
            'addresses'  => $targetAddresses,
            'selectedId' => 'th_' . $toType . '_' . $new_key,
        ];
    }

    /**
     * Update an existing address (native Woo or HP Core-owned additional entry).
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

        // Validate required fields
        $validation_error = $this->validate_address_payload($payload);
        if ($validation_error) {
            return $validation_error;
        }

        // Update primary WooCommerce address.
        if (preg_match('/^' . preg_quote($type, '/') . '_primary$/', $id)) {
            $customer = new \WC_Customer($user_id);

            $setter_prefix = $type === 'billing' ? 'set_billing_' : 'set_shipping_';

            // Convert country to code if needed
            $country_code = $this->get_country_code($payload['country']);
            $payload['country'] = $country_code;
            
            // Clear state if country doesn't have states
            $states = WC()->countries->get_states($country_code);
            if (empty($states)) {
                $payload['state'] = '';
            }

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
                // Always set the value, even if empty (to clear old values like state)
                $method = $setter_prefix . $suffix;
                if (is_callable([$customer, $method])) {
                    $customer->$method($payload[$source]);
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
            $service = self::get_address_service();
            if (!$service || !method_exists($service, 'update_address') || !method_exists($service, 'get_default_address_id')) {
                return $this->address_service_unavailable();
            }
            if (!$service->update_address($user_id, $this->to_service_address($payload, $type), $type, $m[1])) {
                return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
            }
            $addresses = $service->get_hydrated_addresses($user_id, $type);
            return [
                'success'    => true,
                'type'       => $type,
                'addresses'  => $addresses,
                'selectedId' => $service->get_default_address_id($addresses),
            ];
        } else {
            return new WP_Error('hp_rw_invalid_id', 'Unsupported address ID format.', ['status' => 400]);
        }

        // Re-hydrate updated list.
        $service = self::get_address_service();
        $addresses = $service
            ? $service->get_hydrated_addresses($user_id, $type)
            : $this->get_native_woo_addresses($user_id, $type);
        $selected = $service && method_exists($service, 'get_default_address_id')
            ? $service->get_default_address_id($addresses)
            : ($addresses[0]['id'] ?? null);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => $selected,
        ];
    }

    /** Create a new HP Core-owned additional address for the current user. */
    public function handle_create(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_Error('hp_rw_not_logged_in', 'You must be logged in to manage addresses.', ['status' => 401]);
        }

        $type = (string) $request->get_param('type');

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

        // Validate required fields
        $validation_error = $this->validate_address_payload($payload);
        if ($validation_error) {
            return $validation_error;
        }

        $service = self::get_address_service();
        if (!$service || !method_exists($service, 'save_address')) {
            return $this->address_service_unavailable();
        }

        $new_key = $service->save_address($user_id, $this->to_service_address($payload, $type), $type);
        if ($new_key === false) {
            return new WP_Error('hp_rw_address_save_failed', 'Unable to save address.', ['status' => 400]);
        }
        $addresses = $service->get_hydrated_addresses($user_id, $type);

        return [
            'success'    => true,
            'type'       => $type,
            'addresses'  => $addresses,
            'selectedId' => 'th_' . $type . '_' . $new_key,
        ];
    }

    /** Return only native WooCommerce data when HP Core is unavailable. */
    private function get_native_woo_addresses(int $user_id, string $type): array
    {
        if (!class_exists('WC_Customer')) {
            return [];
        }

        $customer = new \WC_Customer($user_id);
        $getter = $type === 'billing' ? 'get_billing_' : 'get_shipping_';
        $first_name = (string) call_user_func([$customer, $getter . 'first_name']);
        $address_1 = (string) call_user_func([$customer, $getter . 'address_1']);
        if ($first_name === '' && $address_1 === '') {
            return [];
        }

        return [[
            'id'        => $type . '_primary',
            'firstName' => $first_name,
            'lastName'  => (string) call_user_func([$customer, $getter . 'last_name']),
            'company'   => (string) call_user_func([$customer, $getter . 'company']),
            'address1'  => $address_1,
            'address2'  => (string) call_user_func([$customer, $getter . 'address_2']),
            'city'      => (string) call_user_func([$customer, $getter . 'city']),
            'state'     => (string) call_user_func([$customer, $getter . 'state']),
            'postcode'  => (string) call_user_func([$customer, $getter . 'postcode']),
            'country'   => (string) call_user_func([$customer, $getter . 'country']),
            'phone'     => $type === 'billing' ? $customer->get_billing_phone() : $customer->get_shipping_phone(),
            'email'     => $type === 'billing' ? $customer->get_billing_email() : '',
            'isDefault' => true,
        ]];
    }

    /**
     * Validate address payload - phone and email are required for all addresses.
     *
     * @param array $payload The address data.
     * @return WP_Error|null Returns WP_Error if validation fails, null if valid.
     */
    private function validate_address_payload(array $payload)
    {
        $errors = [];

        if (empty(trim($payload['firstName'] ?? ''))) {
            $errors[] = 'First name is required.';
        }
        if (empty(trim($payload['lastName'] ?? ''))) {
            $errors[] = 'Last name is required.';
        }
        if (empty(trim($payload['address1'] ?? ''))) {
            $errors[] = 'Address is required.';
        }
        if (empty(trim($payload['city'] ?? ''))) {
            $errors[] = 'City is required.';
        }
        if (empty(trim($payload['postcode'] ?? ''))) {
            $errors[] = 'Postcode is required.';
        }
        if (empty(trim($payload['country'] ?? ''))) {
            $errors[] = 'Country is required.';
        }
        if (empty(trim($payload['phone'] ?? ''))) {
            $errors[] = 'Phone is required.';
        }
        if (empty(trim($payload['email'] ?? ''))) {
            $errors[] = 'Email is required.';
        } elseif (!is_email($payload['email'])) {
            $errors[] = 'Invalid email format.';
        }

        if (!empty($errors)) {
            return new WP_Error(
                'hp_rw_validation_error',
                implode(' ', $errors),
                ['status' => 400, 'errors' => $errors]
            );
        }

        return null;
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
