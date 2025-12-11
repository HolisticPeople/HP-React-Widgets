<?php
namespace HP_RW;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers the hp_funnel Custom Post Type for managing funnel configurations.
 */
class FunnelPostType
{
    public const POST_TYPE = 'hp_funnel';

    /**
     * Initialize the CPT registration.
     */
    public static function init(): void
    {
        add_action('init', [self::class, 'register']);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [self::class, 'add_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [self::class, 'render_column'], 10, 2);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'on_save'], 10, 3);
    }

    /**
     * Register the hp_funnel post type.
     */
    public static function register(): void
    {
        $labels = [
            'name'                  => __('Funnels', 'hp-react-widgets'),
            'singular_name'         => __('Funnel', 'hp-react-widgets'),
            'menu_name'             => __('Funnels', 'hp-react-widgets'),
            'name_admin_bar'        => __('Funnel', 'hp-react-widgets'),
            'add_new'               => __('Add New', 'hp-react-widgets'),
            'add_new_item'          => __('Add New Funnel', 'hp-react-widgets'),
            'new_item'              => __('New Funnel', 'hp-react-widgets'),
            'edit_item'             => __('Edit Funnel', 'hp-react-widgets'),
            'view_item'             => __('View Funnel', 'hp-react-widgets'),
            'all_items'             => __('All Funnels', 'hp-react-widgets'),
            'search_items'          => __('Search Funnels', 'hp-react-widgets'),
            'not_found'             => __('No funnels found.', 'hp-react-widgets'),
            'not_found_in_trash'    => __('No funnels found in Trash.', 'hp-react-widgets'),
            'archives'              => __('Funnel Archives', 'hp-react-widgets'),
            'filter_items_list'     => __('Filter funnels list', 'hp-react-widgets'),
            'items_list_navigation' => __('Funnels list navigation', 'hp-react-widgets'),
            'items_list'            => __('Funnels list', 'hp-react-widgets'),
        ];

        $args = [
            'labels'              => $labels,
            'description'         => __('Sales funnel configurations for HP React Widgets', 'hp-react-widgets'),
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 56, // After WooCommerce
            'menu_icon'           => 'dashicons-filter',
            'supports'            => ['title', 'revisions'],
            'show_in_rest'        => true, // Enable Gutenberg/REST API
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add custom columns to the funnels list table.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public static function add_columns(array $columns): array
    {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add our columns after title
            if ($key === 'title') {
                $new_columns['funnel_slug'] = __('Slug', 'hp-react-widgets');
                $new_columns['shortcode'] = __('Shortcode', 'hp-react-widgets');
                $new_columns['status'] = __('Status', 'hp-react-widgets');
            }
        }
        
        return $new_columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public static function render_column(string $column, int $post_id): void
    {
        switch ($column) {
            case 'funnel_slug':
                $slug = get_field('funnel_slug', $post_id);
                if (!$slug) {
                    $slug = get_post_field('post_name', $post_id);
                }
                echo '<code>' . esc_html($slug) . '</code>';
                break;
                
            case 'shortcode':
                $slug = get_field('funnel_slug', $post_id);
                if (!$slug) {
                    $slug = get_post_field('post_name', $post_id);
                }
                $shortcode = '[hp_funnel_hero funnel="' . esc_attr($slug) . '"]';
                echo '<code style="font-size: 11px; background: #f0f0f1; padding: 2px 6px; border-radius: 3px;">' . esc_html($shortcode) . '</code>';
                break;
                
            case 'status':
                $status = get_field('funnel_status', $post_id);
                if ($status === 'inactive') {
                    echo '<span style="color: #d63638;">●</span> ' . __('Inactive', 'hp-react-widgets');
                } else {
                    echo '<span style="color: #00a32a;">●</span> ' . __('Active', 'hp-react-widgets');
                }
                break;
        }
    }

    /**
     * Handle post save - clear caches.
     *
     * @param int $post_id Post ID
     * @param \WP_Post $post Post object
     * @param bool $update Whether this is an update
     */
    public static function on_save(int $post_id, \WP_Post $post, bool $update): void
    {
        // Skip autosaves and revisions
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (wp_is_post_revision($post_id)) {
            return;
        }

        // Clear the funnel config cache
        if (class_exists('HP_RW\\Services\\FunnelConfigLoader')) {
            Services\FunnelConfigLoader::clearCache($post_id);
        }

        // Auto-generate slug from title if not set
        if (function_exists('get_field') && function_exists('update_field')) {
            $slug = get_field('funnel_slug', $post_id);
            if (empty($slug)) {
                $auto_slug = sanitize_title($post->post_title);
                update_field('funnel_slug', $auto_slug, $post_id);
            }
        }
    }

    /**
     * Get a funnel by slug.
     *
     * @param string $slug Funnel slug
     * @return \WP_Post|null
     */
    public static function getBySlug(string $slug): ?\WP_Post
    {
        // First try ACF field
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'funnel_slug',
                    'value' => $slug,
                ],
            ],
        ]);

        if (!empty($posts)) {
            return $posts[0];
        }

        // Fallback to post_name (WP slug)
        $post = get_page_by_path($slug, OBJECT, self::POST_TYPE);
        
        return $post instanceof \WP_Post ? $post : null;
    }

    /**
     * Get all published funnels.
     *
     * @return array Array of WP_Post objects
     */
    public static function getAll(): array
    {
        return get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
    }
}

