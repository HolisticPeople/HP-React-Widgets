<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * HpMenu shortcode - renders off-canvas navigation menu
 * 
 * Outputs a hamburger trigger button (inline) and an off-canvas drawer
 * (portaled to body). Menu data is loaded from ACF options page.
 * 
 * Usage:
 *   [hp_menu]
 *   [hp_menu title="Shop Categories"]
 *   [hp_menu footer_text="Custom Footer"]
 */
class HpMenuShortcode
{
    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render(array $atts = []): string
    {
        AssetLoader::enqueue_bundle();

        $atts = shortcode_atts([
            'title'       => '',
            'footer_text' => '',
        ], $atts);

        // Load menu configuration from ACF options
        $config = $this->loadMenuConfig();

        // Check if menu has any items
        if (empty($config['sections'])) {
            return $this->renderEmptyState();
        }

        // Override with shortcode attributes if provided
        $title = !empty($atts['title']) ? $atts['title'] : ($config['title'] ?? 'Menu');
        $footerText = !empty($atts['footer_text']) ? $atts['footer_text'] : ($config['footerText'] ?? '');

        $props = [
            'sections'   => $config['sections'],
            'title'      => $title,
            'footerText' => $footerText,
        ];

        $rootId = 'hp-menu-' . uniqid();

        return sprintf(
            '<div id="%s" class="hp-menu-widget" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr('HpMenu'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Load menu configuration from ACF options page.
     *
     * @return array Menu configuration
     */
    private function loadMenuConfig(): array
    {
        $config = [
            'title'      => 'Menu',
            'footerText' => '',
            'sections'   => [],
        ];

        // Check if ACF is available
        if (!function_exists('get_field')) {
            return $config;
        }

        // Load from ACF options
        $title = get_field('hp_menu_title', 'option');
        if (!empty($title)) {
            $config['title'] = $title;
        }

        $footerText = get_field('hp_menu_footer_text', 'option');
        if (!empty($footerText)) {
            $config['footerText'] = $footerText;
        }

        $sections = get_field('hp_menu_sections', 'option');
        if (!empty($sections) && is_array($sections)) {
            $config['sections'] = $this->transformSections($sections);
        }

        return $config;
    }

    /**
     * Transform ACF repeater data to React component format.
     *
     * @param array $sections Raw ACF sections data
     * @return array Transformed sections
     */
    private function transformSections(array $sections): array
    {
        $transformed = [];

        foreach ($sections as $section) {
            $sectionData = [
                'items' => [],
            ];

            // Add title if present
            if (!empty($section['section_title'])) {
                $sectionData['title'] = $section['section_title'];
            }

            // Process items
            if (!empty($section['section_items']) && is_array($section['section_items'])) {
                foreach ($section['section_items'] as $item) {
                    $itemData = [
                        'label' => $item['item_label'] ?? '',
                    ];

                    // Add link if present
                    if (!empty($item['item_link'])) {
                        $itemData['href'] = $item['item_link'];
                    }

                    // Add image if present (ACF returns URL when return_format is 'url')
                    if (!empty($item['item_image'])) {
                        $itemData['image'] = $item['item_image'];
                    }

                    // Process children/subcategories
                    if (!empty($item['item_children']) && is_array($item['item_children'])) {
                        $children = [];
                        foreach ($item['item_children'] as $child) {
                            if (!empty($child['child_label']) && !empty($child['child_link'])) {
                                $children[] = [
                                    'label' => $child['child_label'],
                                    'href'  => $child['child_link'],
                                ];
                            }
                        }
                        if (!empty($children)) {
                            $itemData['children'] = $children;
                        }
                    }

                    // Only add items with labels
                    if (!empty($itemData['label'])) {
                        $sectionData['items'][] = $itemData;
                    }
                }
            }

            // Only add sections with items
            if (!empty($sectionData['items'])) {
                $transformed[] = $sectionData;
            }
        }

        return $transformed;
    }

    /**
     * Render empty state message for admins.
     *
     * @return string HTML output
     */
    private function renderEmptyState(): string
    {
        // Only show message to admins
        if (!current_user_can('manage_options')) {
            return '';
        }

        $settings_url = admin_url('options-general.php?page=hp-menu-options');

        return sprintf(
            '<div class="hp-menu-empty" style="padding: 12px 16px; background: hsl(38 92%% 50%% / 0.15); border: 1px solid hsl(38 92%% 50%% / 0.3); border-radius: 8px; font-size: 14px; color: hsl(38 92%% 40%%);"><strong>HP Menu:</strong> No menu items configured. <a href="%s" style="color: inherit; text-decoration: underline;">Configure menu items →</a></div>',
            esc_url($settings_url)
        );
    }
}
