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
        // Register color fields as a separate field group
        add_action('acf/init', [self::class, 'registerColorFieldGroup'], 20);
        
        // Register hero title size as separate field group
        add_action('acf/init', [self::class, 'registerHeroTitleSizeField'], 25);
        
        // Hide the redundant background_color field (we use page_bg_color instead)
        add_filter('acf/prepare_field/name=background_color', [self::class, 'hideBackgroundColorField']);
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
                // Text Colors header
                [
                    'key' => 'field_text_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<p style="margin:0 0 10px;color:#23282d;font-weight:600;font-size:14px;">Text Colors</p>',
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
                    'wrapper' => ['width' => '20'],
                ],
                // Custom Accent toggle
                [
                    'key' => 'field_text_color_accent_override',
                    'label' => 'Custom Accent',
                    'name' => 'text_color_accent_override',
                    'type' => 'true_false',
                    'instructions' => '',
                    'message' => 'Use custom color instead of global Accent',
                    'default_value' => 0,
                    'ui' => 1,
                    'wrapper' => ['width' => '20'],
                ],
                // Accent Text color (conditional)
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
                    'wrapper' => ['width' => '20'],
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
                    'wrapper' => ['width' => '20'],
                ],
                // UI Colors header
                [
                    'key' => 'field_ui_colors_header',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<hr style="margin:20px 0 15px;border:0;border-top:1px solid #ddd;"><p style="margin:0 0 10px;color:#23282d;font-weight:600;font-size:14px;">UI Element Colors</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                // Page Background color
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
     * Hide the background_color field from the Styling tab.
     * We now use page_bg_color instead.
     */
    public static function hideBackgroundColorField($field)
    {
        global $post;
        if ($post && $post->post_type === 'hp-funnel') {
            return false;
        }
        return $field;
    }
}
