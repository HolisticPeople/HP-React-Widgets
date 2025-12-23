<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel Colors.
 * Consolidated color options: text colors and UI element colors.
 * Note: accent_color is in the main Styling tab and used for both text accent and UI accents.
 */
class FunnelStylingFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFieldGroup'], 20);
    }

    /**
     * Register Funnel Colors field group.
     * Creates a consolidated color configuration that appears on hp-funnel post type.
     */
    public static function registerFieldGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_funnel_colors',
            'title' => 'Funnel Colors',
            'fields' => [
                // Text Colors section
                [
                    'key' => 'field_text_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<p style="margin:0 0 5px;color:#23282d;font-weight:600;">Text Colors</p><p style="margin:0;color:#666;font-size:12px;">Accent text uses the Accent Color from the Styling tab.</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                [
                    'key' => 'field_text_color_basic',
                    'label' => 'Basic Text',
                    'name' => 'text_color_basic',
                    'type' => 'color_picker',
                    'instructions' => 'Main text (off-white)',
                    'default_value' => '#e5e5e5',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                [
                    'key' => 'field_text_color_note',
                    'label' => 'Note Text',
                    'name' => 'text_color_note',
                    'type' => 'color_picker',
                    'instructions' => 'Secondary (muted)',
                    'default_value' => '#a3a3a3',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                [
                    'key' => 'field_text_color_discount',
                    'label' => 'Discount Text',
                    'name' => 'text_color_discount',
                    'type' => 'color_picker',
                    'instructions' => 'Savings (green)',
                    'default_value' => '#22c55e',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                // UI Colors section
                [
                    'key' => 'field_ui_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<hr style="margin:20px 0 10px;border:0;border-top:1px solid #ddd;"><p style="margin:0 0 5px;color:#23282d;font-weight:600;">UI Element Colors</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                [
                    'key' => 'field_page_bg_color',
                    'label' => 'Page Background',
                    'name' => 'page_bg_color',
                    'type' => 'color_picker',
                    'instructions' => 'Main background',
                    'default_value' => '#121212',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                [
                    'key' => 'field_card_bg_color',
                    'label' => 'Card Background',
                    'name' => 'card_bg_color',
                    'type' => 'color_picker',
                    'instructions' => 'Cards/panels',
                    'default_value' => '#1a1a1a',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                [
                    'key' => 'field_input_bg_color',
                    'label' => 'Input Background',
                    'name' => 'input_bg_color',
                    'type' => 'color_picker',
                    'instructions' => 'Form inputs',
                    'default_value' => '#333333',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                [
                    'key' => 'field_border_color',
                    'label' => 'Border Color',
                    'name' => 'border_color',
                    'type' => 'color_picker',
                    'instructions' => 'Borders/dividers',
                    'default_value' => '#7c3aed',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
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
            'menu_order' => 15,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ]);
    }
}
