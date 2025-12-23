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
        add_action('acf/init', [self::class, 'registerFields'], 15);
    }

    /**
     * Register styling color fields.
     */
    public static function registerFields(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        // Add text color fields after the existing accent_color field
        // These will appear in the Styling tab of the Funnel Configuration field group

        // Text Color - Basic (off-white, main text)
        acf_add_local_field([
            'key' => 'field_text_color_basic',
            'label' => 'Text Color - Basic',
            'name' => 'text_color_basic',
            'type' => 'color_picker',
            'parent' => 'group_funnel_config',
            'instructions' => 'Main text color (off-white). Used for body text and general content.',
            'default_value' => '#e5e5e5',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
        ]);

        // Text Color - Accent (orange/gold, highlighted text)
        acf_add_local_field([
            'key' => 'field_text_color_accent',
            'label' => 'Text Color - Accent',
            'name' => 'text_color_accent',
            'type' => 'color_picker',
            'parent' => 'group_funnel_config',
            'instructions' => 'Accent text color. Used for headings, CTAs, and highlighted text.',
            'default_value' => '#eab308',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
        ]);

        // Text Color - Note (muted, secondary text)
        acf_add_local_field([
            'key' => 'field_text_color_note',
            'label' => 'Text Color - Note',
            'name' => 'text_color_note',
            'type' => 'color_picker',
            'parent' => 'group_funnel_config',
            'instructions' => 'Muted text color. Used for descriptions, labels, and secondary content.',
            'default_value' => '#a3a3a3',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
        ]);

        // Text Color - Discount (green, savings)
        acf_add_local_field([
            'key' => 'field_text_color_discount',
            'label' => 'Text Color - Discount',
            'name' => 'text_color_discount',
            'type' => 'color_picker',
            'parent' => 'group_funnel_config',
            'instructions' => 'Discount/savings text color. Used for sale prices and savings labels.',
            'default_value' => '#22c55e',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => [
                'width' => '25',
                'class' => '',
            ],
        ]);
    }
}

