<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers ACF fields for the Science section tab in Funnel Configuration.
 * This tab allows configuring the scientific/technical information displayed
 * by the [hp_funnel_science] shortcode.
 */
class FunnelScienceFields
{
    public static function init(): void
    {
        // Register Science tab and fields
        add_action('acf/init', [self::class, 'registerScienceFields'], 15);
    }

    /**
     * Register the Science tab and its fields.
     * Added to the main Funnel Configuration field group.
     */
    public static function registerScienceFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Add Science tab and fields as a separate field group
        // This will appear as its own metabox but can be positioned near Funnel Configuration
        acf_add_local_field_group([
            'key' => 'group_funnel_science',
            'title' => 'Science Section',
            'fields' => [
                // Instructions
                [
                    'key' => 'field_science_instructions',
                    'label' => '',
                    'name' => '',
                    'type' => 'message',
                    'message' => '<p style="color:#666;">Configure the Science section displayed by <code>[hp_funnel_science]</code>. This section presents scientific/technical information about your product.</p>',
                    'new_lines' => '',
                    'esc_html' => 0,
                ],
                // Science Title
                [
                    'key' => 'field_science_title',
                    'label' => 'Section Title',
                    'name' => 'science_title',
                    'type' => 'text',
                    'instructions' => 'Main heading for the science section',
                    'default_value' => 'The Science Behind Our Product',
                    'placeholder' => 'The Science Behind Our Product',
                    'wrapper' => ['width' => '50'],
                ],
                // Science Subtitle
                [
                    'key' => 'field_science_subtitle',
                    'label' => 'Subtitle',
                    'name' => 'science_subtitle',
                    'type' => 'text',
                    'instructions' => 'Optional subtitle below the main heading',
                    'placeholder' => '',
                    'wrapper' => ['width' => '50'],
                ],
                // Science Sections (repeater)
                [
                    'key' => 'field_science_sections',
                    'label' => 'Science Cards',
                    'name' => 'science_sections',
                    'type' => 'repeater',
                    'instructions' => 'Add cards with scientific information. Each card has a title, description, and bullet points.',
                    'min' => 0,
                    'max' => 6,
                    'layout' => 'block',
                    'button_label' => 'Add Science Card',
                    'sub_fields' => [
                        [
                            'key' => 'field_science_section_title',
                            'label' => 'Card Title',
                            'name' => 'title',
                            'type' => 'text',
                            'instructions' => '',
                            'placeholder' => 'e.g., Brain Function & Mental Clarity',
                            'wrapper' => ['width' => '100'],
                        ],
                        [
                            'key' => 'field_science_section_description',
                            'label' => 'Description',
                            'name' => 'description',
                            'type' => 'textarea',
                            'instructions' => 'Main paragraph explaining this benefit/topic',
                            'rows' => 3,
                            'wrapper' => ['width' => '100'],
                        ],
                        [
                            'key' => 'field_science_section_bullets',
                            'label' => 'Bullet Points',
                            'name' => 'bullets',
                            'type' => 'textarea',
                            'instructions' => 'One bullet point per line. These will be displayed as a list.',
                            'rows' => 4,
                            'new_lines' => 'br',
                            'wrapper' => ['width' => '100'],
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
            'menu_order' => 4, // After Funnel Configuration and Styling Colors
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
    }
}

