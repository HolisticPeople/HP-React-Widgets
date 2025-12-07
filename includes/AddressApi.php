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

        $chosen        = null;
        $currentFlight = null;
        foreach ($addresses as $address) {
            if (isset($address['id']) && (string) $address['id'] === $id) {
                $chosen = $address;
            }
            if (!empty($address['isDefault'])) {
                $currentFlight = $address;
            }
        }

        if (!$chosen) {
            return new WP_Error('hp_rw_not_found', 'Address not found.', ['status' => 404]);
        }

        // Preserve the existing default by appending it to ThemeHigh's custom addresses
        // so users never lose an address when promoting another one to default.
        if ($currentFlight && isset($currentFlight['id']) && (string) $currentFlight['id'] !== (string) $chosen['id']) {
            $meta_key = 'thwma_custom_address';
            $meta     = get_user_meta($user_id, $meta_key, true);
            if (!is_array($meta)) {
                $meta = [
                    'billing'  => [],
                    'shipping' => [],
                ];
            }

            if (!isset($meta[$type]) || !is_array($meta[$type])) {
                $meta[$type] = [];
            }

            $prefix = $type . '_';

            $entry = [
                $prefix . 'first_name' => $currentFlight['firstName'] ?? '',
                $prefix . 'last_name'  => $currentFlight['lastName'] ?? '',
                $prefix . 'company'    => $currentFlight['company'] ?? '',
                $prefix . 'address_1'  => $currentFlight['address1'] ?? '',
                $prefix . 'address_2'  => $currentFlight['address2'] ?? '',
                $prefix . 'city'       => $currentFlight['city'] ?? '',
                $prefix . 'state'      => $currentFlight['state'] ?? '',
                $prefix . 'postcode'   => $currentFlight['postcode'] ?? '',
                $prefix . 'country'    => $currentFlight['country'] ?? '',
                $prefix . 'phone'      => $currentFlight['phone'] ?? '',
                $prefix . 'email'      => $currentFlight['email'] ?? '',
                $prefix . 'address_title' => $currentFlight['address1'] ?? '',
            ];

            $meta[$type][] = $entry;
            update_user_meta($user_id, $meta_key, $meta);
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

        // Reuse same mapping logic as set_default but for the target type.
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
                update_user_meta($user_id, $toType . '_' . $meta_suffix, $chosen[$source_key]);
            }
        }

        // Phone + email nuances: if copying into billing, carry over both; into shipping, just phone.
        if ($toType === 'billing') {
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

        // Return updated target-type addresses for potential UI refresh.
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
}


