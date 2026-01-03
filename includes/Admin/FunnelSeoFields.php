<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel SEO & Google Shopping compliance.
 * 
 * Fields:
 * - On hp-funnel CPT: Brand override, price range display
 * - On Products: product_funnel_override (for Type-1 canonical swap)
 * - On Product Categories: category_canonical_funnel (for Type-2/3 canonical swap)
 * 
 * @since 2.9.0
 */
class FunnelSeoFields
{
    /**
     * Initialize the fields.
     */
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFunnelSeoFields']);
        add_action('acf/init', [self::class, 'registerProductCanonicalFields']);
        add_action('acf/init', [self::class, 'registerCategoryCanonicalFields']);
    }

    /**
     * Register SEO fields on hp-funnel CPT.
     */
    public static function registerFunnelSeoFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_hp_funnel_seo',
            'title'    => 'SEO & Google Shopping',
            'fields'   => [
                // Tab
                [
                    'key'   => 'field_seo_tab',
                    'label' => 'SEO & Analytics',
                    'type'  => 'tab',
                ],
                // Brand Override
                [
                    'key'          => 'field_funnel_brand_override',
                    'label'        => 'Brand Override',
                    'name'         => 'funnel_brand_override',
                    'type'         => 'text',
                    'instructions' => 'Override auto-detected brand. Leave empty to auto-detect from products (defaults to "HolisticPeople").',
                    'placeholder'  => 'Auto-detect',
                    'wrapper'      => ['width' => '50'],
                ],
                // Calculated Brand (read-only display)
                [
                    'key'          => 'field_funnel_brand_display',
                    'label'        => 'Detected Brand',
                    'name'         => 'funnel_brand',
                    'type'         => 'text',
                    'readonly'     => 1,
                    'instructions' => 'Calculated on save. If all products share one brand, uses that; otherwise uses default.',
                    'wrapper'      => ['width' => '50'],
                ],
                // Price Range Section
                [
                    'key'   => 'field_seo_price_heading',
                    'label' => 'Price Range (Calculated on Save)',
                    'type'  => 'message',
                    'message' => 'These fields are auto-calculated when the funnel is saved and used for Google Shopping schema.',
                ],
                [
                    'key'          => 'field_funnel_min_price',
                    'label'        => 'Min Price',
                    'name'         => 'funnel_min_price',
                    'type'         => 'number',
                    'readonly'     => 1,
                    'prepend'      => '$',
                    'instructions' => 'Minimum offer price (never $0).',
                    'wrapper'      => ['width' => '25'],
                ],
                [
                    'key'          => 'field_funnel_max_price',
                    'label'        => 'Max Price',
                    'name'         => 'funnel_max_price',
                    'type'         => 'number',
                    'readonly'     => 1,
                    'prepend'      => '$',
                    'instructions' => 'Maximum offer price.',
                    'wrapper'      => ['width' => '25'],
                ],
                [
                    'key'          => 'field_funnel_availability',
                    'label'        => 'Availability',
                    'name'         => 'funnel_availability',
                    'type'         => 'select',
                    'readonly'     => 1,
                    'choices'      => [
                        'InStock'     => 'In Stock',
                        'OutOfStock'  => 'Out of Stock',
                        'PreOrder'    => 'Pre-Order',
                        'Backorder'   => 'Backorder',
                    ],
                    'default_value' => 'InStock',
                    'instructions' => 'Auto-calculated from product stock status.',
                    'wrapper'      => ['width' => '25'],
                ],
                [
                    'key'          => 'field_funnel_condition',
                    'label'        => 'Condition',
                    'name'         => 'funnel_condition',
                    'type'         => 'select',
                    'choices'      => [
                        'NewCondition'         => 'New',
                        'RefurbishedCondition' => 'Refurbished',
                        'UsedCondition'        => 'Used',
                    ],
                    'default_value' => 'NewCondition',
                    'instructions' => 'Product condition for schema (usually "New" for supplements).',
                    'wrapper'      => ['width' => '25'],
                ],
                // Price Range Label Override
                [
                    'key'          => 'field_funnel_price_range_label',
                    'label'        => 'Price Range Label (Optional Override)',
                    'name'         => 'funnel_price_range_label',
                    'type'         => 'text',
                    'placeholder'  => 'Auto: "From $89 - $249"',
                    'instructions' => 'Custom label for displaying price range. Leave empty for auto-generated label.',
                    'wrapper'      => ['width' => '100'],
                ],
            ],
            'location' => [
                [
                    ['param' => 'post_type', 'operator' => '==', 'value' => 'hp-funnel'],
                ],
            ],
            'menu_order' => 95, // After main fields, near the end
            'position'   => 'normal',
            'style'      => 'default',
        ]);
    }

    /**
     * Register canonical override field on WooCommerce Products.
     */
    public static function registerProductCanonicalFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_product_funnel_canonical',
            'title'    => 'Funnel SEO',
            'fields'   => [
                [
                    'key'          => 'field_product_funnel_override',
                    'label'        => 'Canonical Funnel Override',
                    'name'         => 'product_funnel_override',
                    'type'         => 'post_object',
                    'post_type'    => ['hp-funnel'],
                    'allow_null'   => 1,
                    'return_format' => 'id',
                    'instructions' => 'If set, this product\'s canonical URL will point to the selected funnel. Use for Type-1 (single product) funnels.',
                ],
            ],
            'location' => [
                [
                    ['param' => 'post_type', 'operator' => '==', 'value' => 'product'],
                ],
            ],
            'menu_order' => 100,
            'position'   => 'side',
            'style'      => 'default',
        ]);
    }

    /**
     * Register canonical override field on WooCommerce Product Categories.
     */
    public static function registerCategoryCanonicalFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'      => 'group_category_funnel_canonical',
            'title'    => 'Funnel SEO',
            'fields'   => [
                [
                    'key'          => 'field_category_canonical_funnel',
                    'label'        => 'Canonical Funnel Override',
                    'name'         => 'category_canonical_funnel',
                    'type'         => 'post_object',
                    'post_type'    => ['hp-funnel'],
                    'allow_null'   => 1,
                    'return_format' => 'id',
                    'instructions' => 'If set, this category\'s canonical URL will point to the selected funnel. Use for Type-2/3 (bundle) funnels.',
                ],
            ],
            'location' => [
                [
                    ['param' => 'taxonomy', 'operator' => '==', 'value' => 'product_cat'],
                ],
            ],
            'menu_order' => 100,
            'position'   => 'side',
            'style'      => 'default',
        ]);
    }
}

















