<?php
/**
 * Register the Express Shop Custom Post Type.
 *
 * This ensures the post type is always available, regardless of ACF Pro's
 * internal post type registry state.
 *
 * @package HP_RW
 */

namespace HP_RW;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class FunnelPostType
{
    /**
     * Post type key.
     */
    public const POST_TYPE = 'hp-funnel';

    /**
     * Initialize the post type registration.
     */
    public static function init(): void
    {
        add_action('init', [self::class, 'register'], 5); // Priority 5 to run before ACF
    }

    /**
     * Register the Express Shop post type.
     */
    public static function register(): void
    {
        // Skip if already registered (e.g., by ACF Pro)
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name'                     => __('Express Shops', 'hp-react-widgets'),
            'singular_name'            => __('Express Shop', 'hp-react-widgets'),
            'menu_name'                => __('Express Shops', 'hp-react-widgets'),
            'all_items'                => __('All Express Shops', 'hp-react-widgets'),
            'edit_item'                => __('Edit Express Shop', 'hp-react-widgets'),
            'view_item'                => __('View Express Shop', 'hp-react-widgets'),
            'view_items'               => __('View Express Shops', 'hp-react-widgets'),
            'add_new_item'             => __('Add New Express Shop', 'hp-react-widgets'),
            'add_new'                  => __('Add New Express Shop', 'hp-react-widgets'),
            'new_item'                 => __('New Express Shop', 'hp-react-widgets'),
            'parent_item_colon'        => __('Parent Express Shop:', 'hp-react-widgets'),
            'search_items'             => __('Search Express Shops', 'hp-react-widgets'),
            'not_found'                => __('No Express Shops found', 'hp-react-widgets'),
            'not_found_in_trash'       => __('No Express Shops found in Trash', 'hp-react-widgets'),
            'archives'                 => __('Express Shop Archives', 'hp-react-widgets'),
            'attributes'               => __('Express Shop Attributes', 'hp-react-widgets'),
            'featured_image'           => __('Express Shop Featured Image', 'hp-react-widgets'),
            'insert_into_item'         => __('Insert into Express Shop', 'hp-react-widgets'),
            'uploaded_to_this_item'    => __('Uploaded to this Express Shop', 'hp-react-widgets'),
            'filter_items_list'        => __('Filter Express Shops list', 'hp-react-widgets'),
            'filter_by_date'           => __('Filter Express Shops by date', 'hp-react-widgets'),
            'items_list_navigation'    => __('Express Shops list navigation', 'hp-react-widgets'),
            'items_list'               => __('Express Shops list', 'hp-react-widgets'),
            'item_published'           => __('Express Shop published.', 'hp-react-widgets'),
            'item_published_privately' => __('Express Shop published privately.', 'hp-react-widgets'),
            'item_reverted_to_draft'   => __('Express Shop reverted to draft.', 'hp-react-widgets'),
            'item_scheduled'           => __('Express Shop scheduled.', 'hp-react-widgets'),
            'item_updated'             => __('Express Shop updated.', 'hp-react-widgets'),
            'item_link'                => __('Express Shop Link', 'hp-react-widgets'),
            'item_link_description'    => __('A link to an Express Shop.', 'hp-react-widgets'),
        ];

        $args = [
            'labels'                => $labels,
            'description'           => __('Express Shop configurations for HP React Widgets', 'hp-react-widgets'),
            'public'                => true,
            'hierarchical'          => false,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'show_in_rest'          => true,
            'rest_base'             => '',
            'rest_namespace'        => 'wp/v2',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position'         => 56,
            'menu_icon'             => 'dashicons-filter',
            'capability_type'       => 'post',
            'supports'              => [
                'title',
                'editor',
                'revisions',
                'page-attributes',
                'thumbnail',
                'elementor',
            ],
            'has_archive'           => 'express-shops',
            'rewrite'               => [
                'slug'       => 'express-shop',
                'with_front' => true,
                'feeds'      => false,
                'pages'      => false,
            ],
            'query_var'             => true,
            'can_export'            => true,
            'delete_with_user'      => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}















