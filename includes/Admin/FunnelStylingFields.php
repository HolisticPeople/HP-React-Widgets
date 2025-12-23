<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel Styling options.
 * Adds text color customization: basic, accent, note, discount.
 */
class FunnelStylingFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFieldGroup'], 20);
    }

    /**
     * Register text colors field group.
     * Creates a new field group that appears on hp-funnel post type, 
     * positioned after the main Funnel Configuration group.
     */
    public static function registerFieldGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_funnel_text_colors',
            'title' => 'Text Colors',
            'fields' => [
                [
                    'key' => 'field_text_colors_instructions',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'instructions' => '',
                    'message' => '<p style="margin:0;color:#666;">Configure text colors used throughout the funnel. These override default colors in the checkout and landing pages.</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                [
                    'key' => 'field_text_color_basic',
                    'label' => 'Basic Text',
                    'name' => 'text_color_basic',
                    'type' => 'color_picker',
                    'instructions' => 'Main text color (off-white)',
                    'default_value' => '#e5e5e5',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_text_color_accent',
                    'label' => 'Accent Text',
                    'name' => 'text_color_accent',
                    'type' => 'color_picker',
                    'instructions' => 'Headings, CTAs (gold/orange)',
                    'default_value' => '#eab308',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_text_color_note',
                    'label' => 'Note Text',
                    'name' => 'text_color_note',
                    'type' => 'color_picker',
                    'instructions' => 'Secondary text (muted gray)',
                    'default_value' => '#a3a3a3',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
                [
                    'key' => 'field_text_color_discount',
                    'label' => 'Discount Text',
                    'name' => 'text_color_discount',
                    'type' => 'color_picker',
                    'instructions' => 'Savings/discounts (green)',
                    'default_value' => '#22c55e',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => [
                        'width' => '25',
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'hp-funnel',
                    ],
                ],
            ],
            'menu_order' => 15, // After Funnel Configuration and Funnel Offers
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);
    }
}
