<?php
namespace HP_RW\Admin;

use HP_RW\Util\Resolver;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoint for product lookup in admin.
 * Used by the ACF product search field to auto-populate form fields.
 */
class ProductLookupApi
{
    /**
     * Initialize the API.
     */
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    /**
     * Register REST routes.
     */
    public static function registerRoutes(): void
    {
        register_rest_route('hp-rw/v1', '/admin/product-lookup', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'handleLookup'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'validate_callback' => function ($value) {
                        return is_numeric($value) && (int) $value > 0;
                    },
                ],
            ],
        ]);
    }

    /**
     * Handle product lookup request.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handleLookup(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request->get_param('product_id');
        
        $product = wc_get_product($productId);
        if (!$product) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Product not found',
            ], 404);
        }

        // Get product image
        $imageId = $product->get_image_id();
        $imageUrl = '';
        if ($imageId) {
            $imageData = wp_get_attachment_image_src($imageId, 'thumbnail');
            if ($imageData && isset($imageData[0])) {
                $imageUrl = $imageData[0];
            }
        }

        // Get short description, trimmed
        $shortDesc = $product->get_short_description();
        if (strlen($shortDesc) > 100) {
            $shortDesc = substr($shortDesc, 0, 100) . '...';
        }
        $shortDesc = wp_strip_all_tags($shortDesc);

        return new WP_REST_Response([
            'success' => true,
            'product' => [
                'id'               => $product->get_id(),
                'sku'              => $product->get_sku(),
                'name'             => $product->get_name(),
                'price'            => (float) $product->get_price(),
                'regular_price'    => (float) $product->get_regular_price(),
                'sale_price'       => $product->get_sale_price() ? (float) $product->get_sale_price() : null,
                'short_description' => $shortDesc,
                'image_id'         => $imageId,
                'image_url'        => $imageUrl,
                'in_stock'         => $product->is_in_stock(),
                'stock_qty'        => $product->get_stock_quantity(),
            ],
        ]);
    }
}


