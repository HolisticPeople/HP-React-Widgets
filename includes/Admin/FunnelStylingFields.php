<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel Colors.
 * Consolidated color options: text colors and UI element colors.
 * Accent text defaults to the global Accent Color but can be overridden.
 */
class FunnelStylingFields
{
    public static function init(): void
    {
        add_action('acf/init', [self::class, 'registerFieldGroup'], 20);
        add_action('acf/init', [self::class, 'registerHeroTitleSizeField'], 25);
        // Hide the redundant background_color field from the Styling tab
        add_filter('acf/prepare_field/name=background_color', [self::class, 'hideBackgroundColorField']);
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
            'menu_order' => 5, // After main config, before colors
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }

    /**
     * Hide the background_color field from the Styling tab.
     * We now use page_bg_color in Funnel Colors instead.
     */
    public static function hideBackgroundColorField($field)
    {
        // Only hide on hp-funnel post type
        global $post;
        if ($post && $post->post_type === 'hp-funnel') {
            return false; // Returning false hides the field
        }
        return $field;
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
                    'message' => '<p style="margin:0 0 5px;color:#23282d;font-weight:600;">Text Colors</p>',
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
                    'wrapper' => ['width' => '20'],
                ],
                [
                    'key' => 'field_text_color_accent_override',
                    'label' => 'Custom Accent',
                    'name' => 'text_color_accent_override',
                    'type' => 'true_false',
                    'instructions' => '',
                    'message' => 'Use custom color instead of global Accent',
                    'default_value' => 0,
                    'ui' => 1,
                    'ui_on_text' => '',
                    'ui_off_text' => '',
                    'wrapper' => ['width' => '20'],
                ],
                [
                    'key' => 'field_text_color_accent',
                    'label' => 'Accent Text',
                    'name' => 'text_color_accent',
                    'type' => 'color_picker',
                    'instructions' => 'Headings, CTAs',
                    'default_value' => '#eab308',
                    'enable_opacity' => 0,
                    'return_format' => 'string',
                    'wrapper' => ['width' => '20'],
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_text_color_accent_override',
                                'operator' => '==',
                                'value' => '1',
                            ],
                        ],
                    ],
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
                    'wrapper' => ['width' => '20'],
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
                    'wrapper' => ['width' => '20'],
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
