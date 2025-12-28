<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for Funnel Colors.
 * Adds color fields to the existing Styling tab in Funnel Configuration.
 * Accent text defaults to the global Accent Color but can be overridden.
 */
class FunnelStylingFields
{
    /**
     * The field group key for Funnel Configuration.
     * This is the parent for all fields in this group.
     */
    private const FUNNEL_CONFIG_GROUP = 'group_hp_funnel_config';
    
    public static function init(): void
    {
        // Add color fields to the Styling tab
        add_action('acf/init', [self::class, 'addColorFieldsToStylingTab'], 25);
        
        // Register hero title size as separate field group
        add_action('acf/init', [self::class, 'registerHeroTitleSizeField'], 25);
        
        // Hide the redundant background_color field (we use page_bg_color instead)
        add_filter('acf/prepare_field/name=background_color', [self::class, 'hideBackgroundColorField']);
    }

    /**
     * Add color fields to the existing Styling tab.
     * Uses acf_add_local_field to insert fields into the Funnel Configuration group.
     * Fields appear after Custom CSS (menu_order 63) in the Styling tab.
     */
    public static function addColorFieldsToStylingTab(): void
    {
        if (!function_exists('acf_add_local_field')) {
            return;
        }

        // Base menu_order - fields will be added after Custom CSS (63)
        $order = 64;

        // Separator after existing styling fields
        acf_add_local_field([
            'key' => 'field_styling_colors_separator',
            'label' => '',
            'name' => '',
            'type' => 'message',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'message' => '<hr style="margin:25px 0 15px;border:0;border-top:2px solid #ddd;">',
            'new_lines' => '',
            'esc_html' => 0,
        ]);

        // Text Colors header
        acf_add_local_field([
            'key' => 'field_text_colors_header',
            'label' => '',
            'name' => '',
            'type' => 'message',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'message' => '<p style="margin:0 0 5px;color:#23282d;font-weight:600;font-size:14px;">Text Colors</p>',
            'new_lines' => '',
            'esc_html' => 0,
        ]);

        // Basic Text color
        acf_add_local_field([
            'key' => 'field_text_color_basic',
            'label' => 'Basic Text',
            'name' => 'text_color_basic',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Main text (off-white)',
            'default_value' => '#e5e5e5',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '20'],
        ]);

        // Custom Accent toggle
        acf_add_local_field([
            'key' => 'field_text_color_accent_override',
            'label' => 'Custom Accent',
            'name' => 'text_color_accent_override',
            'type' => 'true_false',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => '',
            'message' => 'Use custom color instead of global Accent',
            'default_value' => 0,
            'ui' => 1,
            'wrapper' => ['width' => '20'],
        ]);

        // Accent Text color (conditional)
        acf_add_local_field([
            'key' => 'field_text_color_accent',
            'label' => 'Accent Text',
            'name' => 'text_color_accent',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
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
        ]);

        // Note Text color
        acf_add_local_field([
            'key' => 'field_text_color_note',
            'label' => 'Note Text',
            'name' => 'text_color_note',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Secondary (muted)',
            'default_value' => '#a3a3a3',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '20'],
        ]);

        // Discount Text color
        acf_add_local_field([
            'key' => 'field_text_color_discount',
            'label' => 'Discount Text',
            'name' => 'text_color_discount',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Savings (green)',
            'default_value' => '#22c55e',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '20'],
        ]);

        // UI Colors header
        acf_add_local_field([
            'key' => 'field_ui_colors_header',
            'label' => '',
            'name' => '',
            'type' => 'message',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'message' => '<hr style="margin:20px 0 10px;border:0;border-top:1px solid #ddd;"><p style="margin:0 0 5px;color:#23282d;font-weight:600;font-size:14px;">UI Element Colors</p>',
            'new_lines' => '',
            'esc_html' => 0,
        ]);

        // Page Background color
        acf_add_local_field([
            'key' => 'field_page_bg_color',
            'label' => 'Page Background',
            'name' => 'page_bg_color',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Main background',
            'default_value' => '#121212',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '25'],
        ]);

        // Card Background color
        acf_add_local_field([
            'key' => 'field_card_bg_color',
            'label' => 'Card Background',
            'name' => 'card_bg_color',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Cards/panels',
            'default_value' => '#1a1a1a',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '25'],
        ]);

        // Input Background color
        acf_add_local_field([
            'key' => 'field_input_bg_color',
            'label' => 'Input Background',
            'name' => 'input_bg_color',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Form inputs',
            'default_value' => '#333333',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '25'],
        ]);

        // Border color
        acf_add_local_field([
            'key' => 'field_border_color',
            'label' => 'Border Color',
            'name' => 'border_color',
            'type' => 'color_picker',
            'parent' => self::FUNNEL_CONFIG_GROUP,
            'menu_order' => $order++,
            'instructions' => 'Borders/dividers',
            'default_value' => '#7c3aed',
            'enable_opacity' => 0,
            'return_format' => 'string',
            'wrapper' => ['width' => '25'],
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
