<?php
namespace HP_RW\Rest;

use HP_RW\Services\ShippingService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ShippingApi
{
    public function register_routes(): void
    {
        register_rest_route('hp-rw/v1', '/shipstation/rates', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_rates'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_rates(WP_REST_Request $request)
    {
        $address = (array) $request->get_param('address');
        $items   = (array) $request->get_param('items');

        if (empty($items)) {
            return new WP_Error('bad_request', 'Items required', ['status' => 400]);
        }

        $service = new ShippingService();
        $result  = $service->getRates($address, $items);

        if (!$result['success']) {
            return new WP_REST_Response([
                'code'    => 'shipping_error',
                'message' => $result['error'] ?? 'Failed to get rates',
            ], 502);
        }

        return new WP_REST_Response(['rates' => $result['rates']], 200);
    }
}

