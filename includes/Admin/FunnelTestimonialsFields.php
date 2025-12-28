<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds display mode controls to the Testimonials tab via JavaScript injection.
 * This integrates seamlessly with the existing ACF UI-defined fields.
 */
class FunnelTestimonialsFields
{
    public static function init(): void
    {
        // Register display settings fields
        add_action('acf/init', [self::class, 'registerHiddenFields'], 20);
    }

    /**
     * Register the actual ACF fields for storing values.
     * These are visible but styled to be compact.
     */
    public static function registerHiddenFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Register fields - these will be visible for ACF to properly save
        acf_add_local_field_group([
            'key' => 'group_testimonials_display_settings',
            'title' => 'Testimonials Display Settings',
            'fields' => [
                [
                    'key' => 'field_testimonials_display_mode',
                    'label' => 'Display Mode',
                    'name' => 'testimonials_display_mode',
                    'type' => 'button_group',
                    'choices' => [
                        'cards' => 'Grid',
                        'carousel' => 'Slider',
                    ],
                    'default_value' => 'cards',
                    'layout' => 'horizontal',
                    'return_format' => 'value',
                    'wrapper' => ['width' => '40'],
                ],
                [
                    'key' => 'field_testimonials_columns',
                    'label' => 'Columns',
                    'name' => 'testimonials_columns',
                    'type' => 'button_group',
                    'choices' => [
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ],
                    'default_value' => '3',
                    'layout' => 'horizontal',
                    'return_format' => 'value',
                    'wrapper' => ['width' => '30'],
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_testimonials_display_mode',
                                'operator' => '==',
                                'value' => 'cards',
                            ],
                        ],
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
            'menu_order' => 7,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

}
