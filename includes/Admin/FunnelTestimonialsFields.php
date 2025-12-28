<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds display mode field to the Testimonials configuration.
 */
class FunnelTestimonialsFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFieldGroup'], 25);
    }

    /**
     * Register a seamless field group for testimonials display settings.
     * Positioned to appear within the Testimonials section.
     */
    public static function registerFieldGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_testimonials_display',
            'title' => 'Testimonials Display',
            'fields' => [
                [
                    'key' => 'field_testimonials_display_mode',
                    'label' => 'Display Mode',
                    'name' => 'testimonials_display_mode',
                    'type' => 'button_group',
                    'instructions' => 'Choose how to display testimonials',
                    'choices' => [
                        'cards' => 'Grid',
                        'carousel' => 'Slider',
                    ],
                    'default_value' => 'cards',
                    'layout' => 'horizontal',
                    'return_format' => 'value',
                    'wrapper' => [
                        'width' => '50',
                    ],
                ],
                [
                    'key' => 'field_testimonials_columns',
                    'label' => 'Columns (Grid only)',
                    'name' => 'testimonials_columns',
                    'type' => 'button_group',
                    'instructions' => '',
                    'choices' => [
                        '2' => '2 Columns',
                        '3' => '3 Columns',
                    ],
                    'default_value' => '3',
                    'layout' => 'horizontal',
                    'return_format' => 'value',
                    'wrapper' => [
                        'width' => '50',
                    ],
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
            'menu_order' => 6, // After other metaboxes
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }
}
