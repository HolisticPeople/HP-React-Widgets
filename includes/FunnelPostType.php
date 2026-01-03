<?php
/**
 * Register the HP Funnel Custom Post Type.
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
     * Register the HP Funnel post type.
     */
    public static function register(): void
    {
        // Skip if already registered (e.g., by ACF Pro)
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name'                     => __('HP Funnels', 'hp-react-widgets'),
            'singular_name'            => __('HP Funnel', 'hp-react-widgets'),
            'menu_name'                => __('HP Funnels', 'hp-react-widgets'),
            'all_items'                => __('All HP-Funnels', 'hp-react-widgets'),
            'edit_item'                => __('Edit HP-Funnel', 'hp-react-widgets'),
            'view_item'                => __('View HP-Funnel', 'hp-react-widgets'),
            'view_items'               => __('View HP-Funnels', 'hp-react-widgets'),
            'add_new_item'             => __('Add New HP-Funnel', 'hp-react-widgets'),
            'add_new'                  => __('Add New HP-Funnel', 'hp-react-widgets'),
            'new_item'                 => __('New HP-Funnel', 'hp-react-widgets'),
            'parent_item_colon'        => __('Parent Funnel:', 'hp-react-widgets'),
            'search_items'             => __('Search HP-Funnels', 'hp-react-widgets'),
            'not_found'                => __('No funnels found', 'hp-react-widgets'),
            'not_found_in_trash'       => __('No funnels found in Trash', 'hp-react-widgets'),
            'archives'                 => __('HP-Funnel Archives', 'hp-react-widgets'),
            'attributes'               => __('Funnel Attributes', 'hp-react-widgets'),
            'featured_image'           => __('HP-Funnel Featured Image', 'hp-react-widgets'),
            'insert_into_item'         => __('Insert into funnel', 'hp-react-widgets'),
            'uploaded_to_this_item'    => __('Uploaded to this funnel', 'hp-react-widgets'),
            'filter_items_list'        => __('Filter funnels list', 'hp-react-widgets'),
            'filter_by_date'           => __('Filter funnels by date', 'hp-react-widgets'),
            'items_list_navigation'    => __('Funnels list navigation', 'hp-react-widgets'),
            'items_list'               => __('HP-Funnels list', 'hp-react-widgets'),
            'item_published'           => __('HP-Funnel published.', 'hp-react-widgets'),
            'item_published_privately' => __('Funnel published privately.', 'hp-react-widgets'),
            'item_reverted_to_draft'   => __('Funnel reverted to draft.', 'hp-react-widgets'),
            'item_scheduled'           => __('Funnel scheduled.', 'hp-react-widgets'),
            'item_updated'             => __('HP-Funnel updated.', 'hp-react-widgets'),
            'item_link'                => __('HP-Funnel Link', 'hp-react-widgets'),
            'item_link_description'    => __('A link to a funnel.', 'hp-react-widgets'),
        ];

        $args = [
            'labels'                => $labels,
            'description'           => __('Sales funnel configurations for HP React Widgets', 'hp-react-widgets'),
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
                'revisions',
                'page-attributes',
                'thumbnail',
                'custom-fields',
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















