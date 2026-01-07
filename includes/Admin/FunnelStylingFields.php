<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel Styling Colors.
 * Creates a separate field group that appears after Funnel Configuration.
 * Accent text defaults to the global Accent Color but can be overridden.
 */
class FunnelStylingFields
{
    public static function init(): void
    {
        // Fields registered via ACF JSON: group_hp_funnel_config.json
        // add_action('acf/init', [self::class, 'registerColorFieldGroup'], 20);
        // add_action('acf/init', [self::class, 'registerHeroTitleSizeField'], 25);
        
        // Hide original fields from Styling tab (by key, not name, to preserve our local fields)
        add_filter('acf/prepare_field/key=field_accent_color', [self::class, 'hideFunnelField']);
        add_filter('acf/prepare_field/key=field_background_type', [self::class, 'hideFunnelField']);
        add_filter('acf/prepare_field/name=background_color', [self::class, 'hideFunnelField']);
    }

    /**
     * Register color fields as a seamless field group.
     * Positioned to appear right after Funnel Configuration.
     */
    public static function registerColorFieldGroup(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_funnel_styling_colors',
            'title' => 'Styling Colors',
            'fields' => [
                // Accent Colors header
                [
                    'key' => 'field_accent_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<p style="margin:0 0 10px;color:#23282d;font-weight:600;font-size:14px;">Accent Colors</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                // Accent Color (for UI elements)
                [
                    'key' => 'field_accent_color_local',
                    'label' => 'UI Accent',
                    'name' => 'accent_color',
                    'type' => 'color_picker',
                    'instructions' => 'Buttons, links, borders',
                    'default_value' => '#eab308',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                // Accent Text color (always visible)
                [
                    'key' => 'field_text_color_accent',
                    'label' => 'Text Accent',
                    'name' => 'text_color_accent',
                    'type' => 'color_picker',
                    'instructions' => 'Headings, CTAs',
                    'default_value' => '#eab308',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                // Text Colors header
                [
                    'key' => 'field_text_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<hr style="margin:20px 0 15px;border:0;border-top:1px solid #ddd;"><p style="margin:0 0 10px;color:#23282d;font-weight:600;font-size:14px;">Text Colors</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                // Basic Text color
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
                // Note Text color
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
                // Discount Text color
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
                // UI Colors header
                [
                    'key' => 'field_ui_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<hr style="margin:20px 0 15px;border:0;border-top:1px solid #ddd;"><p style="margin:0 0 10px;color:#23282d;font-weight:600;font-size:14px;">Page Background</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                // Background Type (moved from Styling tab)
                [
                    'key' => 'field_background_type_local',
                    'label' => 'Background Type',
                    'name' => 'background_type',
                    'type' => 'select',
                    'instructions' => '',
                    'choices' => [
                        'solid' => 'Solid Color',
                        'gradient' => 'Gradient',
                        'image' => 'Image',
                    ],
                    'default_value' => 'solid',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                    'wrapper' => ['width' => '25'],
                ],
                // Page Background color
                [
                    'key' => 'field_page_bg_color',
                    'label' => 'Page BG Color',
                    'name' => 'page_bg_color',
                    'type' => 'color_picker',
                    'instructions' => 'Solid or gradient start',
                    'default_value' => '#121212',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '25'],
                ],
                // Card Background color
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
                // Input Background color
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
                // Border color
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
            'menu_order' => 3, // After Funnel Configuration (0), Funnel Offers (1-2)
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Register the hero title size field as a separate field group.
     * This appears below the main Funnel Configuration metabox.
     */
    public static function registerHeroTitleSizeField(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_hero_title_size',
            'title' => 'Hero Title Settings',
            'fields' => [
                [
                    'key' => 'field_hero_title_size',
                    'label' => 'Title Size',
                    'name' => 'hero_title_size',
                    'type' => 'select',
                    'instructions' => 'Controls the display size of the hero title text',
                    'choices' => [
                        'sm' => 'Small (3xl → 4xl)',
                        'md' => 'Medium (4xl → 5xl)',
                        'lg' => 'Large (5xl → 6xl)',
                        'xl' => 'Extra Large (6xl → 7xl) - Default',
                        '2xl' => '2X Large (7xl → 8xl)',
                    ],
                    'default_value' => 'xl',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                    'wrapper' => ['width' => '50'],
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
            'menu_order' => 5,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Hide fields from the Styling tab that are now in the Styling Colors metabox.
     */
    public static function hideFunnelField($field)
    {
        global $post;
        if ($post && $post->post_type === 'hp-funnel') {
            return false;
        }
        return $field;
    }
}
