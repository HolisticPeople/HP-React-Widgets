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
        add_action('acf/init', [self::class, 'registerFields'], 20);
    }

    /**
     * Register styling color fields.
     */
    public static function registerFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        // Get the parent field group - "Funnel Configuration" 
        // Fields are added to the Styling tab (field_styling_tab)
        $parentGroup = 'group_hp_funnel_config';

        // Text Color - Basic (off-white, main text)
        acf_add_local_field([
            'key' => 'field_text_color_basic',
            'label' => 'Text Color - Basic',
            'name' => 'text_color_basic',
            'type' => 'color_picker',
            'parent' => $parentGroup,
            'instructions' => 'Main text color (off-white). Used for body text and general content.',
            'default_value' => '#e5e5e5',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
            // Place after accent_color (menu_order 59) but before background_type (menu_order 60)
            // ACF sorts fields by menu_order, fields with same menu_order sort by key
            'menu_order' => 59,
        ]);

        // Text Color - Accent (orange/gold, highlighted text)
        acf_add_local_field([
            'key' => 'field_text_color_accent',
            'label' => 'Text Color - Accent',
            'name' => 'text_color_accent',
            'type' => 'color_picker',
            'parent' => $parentGroup,
            'instructions' => 'Accent text color. Used for headings, CTAs, and highlighted text.',
            'default_value' => '#eab308',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
            'menu_order' => 59,
        ]);

        // Text Color - Note (muted, secondary text)
        acf_add_local_field([
            'key' => 'field_text_color_note',
            'label' => 'Text Color - Note',
            'name' => 'text_color_note',
            'type' => 'color_picker',
            'parent' => $parentGroup,
            'instructions' => 'Muted text color. Used for descriptions, labels, and secondary content.',
            'default_value' => '#a3a3a3',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
            'menu_order' => 59,
        ]);

        // Text Color - Discount (green, savings)
        acf_add_local_field([
            'key' => 'field_text_color_discount',
            'label' => 'Text Color - Discount',
            'name' => 'text_color_discount',
            'type' => 'color_picker',
            'parent' => $parentGroup,
            'instructions' => 'Discount/savings text color. Used for sale prices and savings labels.',
            'default_value' => '#22c55e',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'conditional_logic' => 0,
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
            'menu_order' => 59,
        ]);
    }
}
