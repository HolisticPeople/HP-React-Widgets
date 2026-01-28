<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin enhancements for Funnel Styling fields.
 * 
 * Provides section background admin UI enhancements and AJAX handlers.
 * All styling fields are now managed in the Styling tab of the main
 * Funnel Configuration field group (group_hp_funnel_config.json).
 * 
 * @since 2.33.2
 * @version 2.43.40 - Removed legacy metabox code and field-hiding filters
 */
class FunnelStylingFields
{
    public static function init(): void
    {
        // v2.33.2: Enqueue section background admin UI enhancements
        add_action('acf/input/admin_enqueue_scripts', [self::class, 'enqueueSectionBackgroundAdmin']);

        // v2.33.37: AJAX handler for refreshing section backgrounds
        add_action('wp_ajax_hp_refresh_section_backgrounds', [self::class, 'ajaxRefreshSectionBackgrounds']);
    }

    /**
     * Enqueue section background admin UI enhancements (v2.33.72).
     * Adds bulk actions and live preview to section_backgrounds repeater.
     * Uses FunnelConfigLoader::detectConfiguredSections for consistent naming.
     */
    public static function enqueueSectionBackgroundAdmin(): void
    {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'hp-funnel') {
            wp_enqueue_script(
                'hp-rw-section-bg-admin',
                HP_RW_URL . 'assets/admin/section-bg-admin.js',
                ['jquery', 'acf-input'],
                HP_RW_VERSION,
                true
            );

            // Pass section names and styling colors to JavaScript (v2.33.72)
            global $post;
            $sectionNames = [];
            $stylingColors = [];

            if ($post && $post->ID) {
                // Use FunnelConfigLoader to get consistent section detection
                if (class_exists('\HP_RW\Services\FunnelConfigLoader')) {
                    $sections = \HP_RW\Services\FunnelConfigLoader::detectConfiguredSections($post->ID);
                    foreach ($sections as $section) {
                        $sectionNames[] = $section['section_label'];
                    }
                }

                // Get styling colors for color picker palette
                $colorFields = [
                    'text_color_accent' => 'Text Accent',
                    'text_color_basic' => 'Basic Text',
                    'text_color_note' => 'Note Text',
                    'text_color_discount' => 'Discount Text',
                    'page_bg_color' => 'Page BG Color',
                    'card_bg_color' => 'Card Background',
                    'input_bg_color' => 'Input Background',
                    'border_color' => 'Border Color'
                ];

                foreach ($colorFields as $fieldName => $label) {
                    $color = get_field($fieldName, $post->ID);
                    if (!empty($color)) {
                        $stylingColors[] = [
                            'color' => $color,
                            'label' => $label
                        ];
                    }
                }
            }

            wp_localize_script('hp-rw-section-bg-admin', 'hpSectionBgData', [
                'sectionNames' => $sectionNames,
                'stylingColors' => $stylingColors,
                'refreshNonce' => wp_create_nonce('hp_refresh_sections_' . ($post ? $post->ID : 0))
            ]);

            wp_enqueue_style(
                'hp-rw-section-bg-admin',
                HP_RW_URL . 'assets/admin/section-bg-admin.css',
                [],
                HP_RW_VERSION
            );
        }
    }

    /**
     * AJAX handler to refresh section backgrounds (v2.33.37).
     * Re-syncs the section_backgrounds repeater with actual funnel sections.
     */
    public static function ajaxRefreshSectionBackgrounds(): void
    {
        // Verify nonce
        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        if (!$postId || !wp_verify_nonce($nonce, 'hp_refresh_sections_' . $postId)) {
            wp_send_json_error('Invalid request');
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Verify post type
        if (get_post_type($postId) !== 'hp-funnel') {
            wp_send_json_error('Invalid post type');
            return;
        }

        // Refresh section backgrounds
        \HP_RW\Services\FunnelConfigLoader::autoPopulateSectionBackgrounds($postId);

        wp_send_json_success([
            'message' => 'Section backgrounds refreshed successfully'
        ]);
    }
}
