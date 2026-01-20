<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelInfographics shortcode - renders responsive infographic comparison images.
 * 
 * Displays a full-width infographic on desktop, and breaks into separate panels
 * (title, left, right) on mobile with stack or carousel layout options.
 * 
 * Supports multiple infographics per funnel via the repeater field.
 * 
 * Usage:
 *   [hp_funnel_infographics funnel="illumodine" info="1"]  // First infographic
 *   [hp_funnel_infographics funnel="illumodine" info="2"]  // Second infographic
 *   [hp_funnel_infographics info="1"]                      // Uses context, first infographic
 * 
 * @package HP_RW\Shortcodes
 * @since 2.20.0
 * @version 2.0.0 - Converted to repeater for multiple infographics
 * @author Amnon Manneberg
 */
class FunnelInfographicsShortcode
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
            'funnel'           => '',
            'id'               => '',
            'info'             => '1',    // Which infographic to display (1-based index)
            'title'            => '',
            'desktop_image'    => '',
            'use_mobile_images'=> '',
            'desktop_fallback' => '',
            'title_image'      => '',
            'left_panel'       => '',
            'right_panel'      => '',
            'mobile_layout'    => '',
            'alt_text'         => '',
            'nav_label'        => '',
        ], $atts);

        // Load config by ID, slug, or auto-detect from context
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug(sanitize_key($atts['funnel']));
        } else {
            $config = FunnelConfigLoader::getFromContext();
        }
        
        if (!$config || empty($config['active'])) {
            return '';
        }

        // Get infographics array from config
        $infographicsArray = $config['infographics'] ?? [];
        
        // Get the requested infographic by index (1-based)
        $infoIndex = max(1, (int) $atts['info']) - 1; // Convert to 0-based
        
        if (!isset($infographicsArray[$infoIndex])) {
            // No infographic at this index
            if (current_user_can('manage_options')) {
                return sprintf(
                    '<div class="hp-funnel-error" style="padding: 10px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px; font-size: 12px;">Infographic #%d not found. Only %d infographic(s) configured.</div>',
                    $infoIndex + 1,
                    count($infographicsArray)
                );
            }
            return '';
        }

        $infographic = $infographicsArray[$infoIndex];

        // Build props from config with shortcode attribute overrides
        $desktopImage = $atts['desktop_image'] ?: ($infographic['desktop_image'] ?? '');
        $titleImage = $atts['title_image'] ?: ($infographic['title_image'] ?? '');
        $leftPanelImage = $atts['left_panel'] ?: ($infographic['left_panel_image'] ?? '');
        $rightPanelImage = $atts['right_panel'] ?: ($infographic['right_panel_image'] ?? '');

        // If no images configured, return empty
        if (empty($desktopImage) && empty($leftPanelImage) && empty($rightPanelImage)) {
            return '';
        }

        // Determine useMobileImages - shortcode override, then config, default false
        $useMobileImages = false;
        if ($atts['use_mobile_images'] !== '') {
            $useMobileImages = filter_var($atts['use_mobile_images'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($infographic['use_mobile_images'])) {
            $useMobileImages = (bool) $infographic['use_mobile_images'];
        }

        // Props for React component
        $props = [
            'title'            => $atts['title'] ?: ($infographic['title'] ?? ''),
            'desktopImage'     => $desktopImage,
            'useMobileImages'  => $useMobileImages,
            'desktopFallback'  => $atts['desktop_fallback'] ?: ($infographic['desktop_fallback'] ?? 'scale'),
            'titleImage'       => $titleImage,
            'leftPanelImage'   => $leftPanelImage,
            'rightPanelImage'  => $rightPanelImage,
            'mobileLayout'     => $atts['mobile_layout'] ?: ($infographic['mobile_layout'] ?? 'stack'),
            'altText'          => $atts['alt_text'] ?: ($infographic['alt_text'] ?? ''),
        ];

        // Navigation label for scroll navigation dots (empty = exclude from nav)
        $navLabel = $atts['nav_label'] ?: ($infographic['nav_label'] ?? '');

        // Include the info index in the root ID for uniqueness
        return $this->renderWidget('FunnelInfographics', $config['slug'], $props, $navLabel, $infoIndex + 1);
    }

    /**
     * Render the React widget container.
     *
     * @param string $component React component name
     * @param string $slug      Funnel slug
     * @param array  $props     Component props
     * @param string $navLabel  Label for scroll navigation (empty = exclude from nav)
     * @param int    $infoIndex The infographic index (1-based)
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props, string $navLabel = '', int $infoIndex = 1): string
    {
        $rootId = 'hp-funnel-infographics-' . esc_attr($slug) . '-' . $infoIndex . '-' . uniqid();
        $propsJson = wp_json_encode($props);

        // CSS to force parent Elementor containers to shrink to content
        $css = '<style>
            .elementor-widget-shortcode:has(#' . esc_attr($rootId) . '),
            .elementor-widget-shortcode:has(.hp-funnel-infographics-' . esc_attr($slug) . ') {
                height: auto !important;
                min-height: 0 !important;
            }
            .elementor-widget-shortcode:has(#' . esc_attr($rootId) . ') > .elementor-widget-container,
            .elementor-widget-shortcode:has(.hp-funnel-infographics-' . esc_attr($slug) . ') > .elementor-widget-container {
                height: auto !important;
                min-height: 0 !important;
            }
        </style>';

        // Only include data-section-name if navLabel is not empty
        $sectionNameAttr = !empty($navLabel) ? sprintf(' data-section-name="%s"', esc_attr($navLabel)) : '';

        return $css . sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-infographics-%s" data-hp-widget="1" data-component="%s" data-props=\'%s\'%s></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr($propsJson),
            $sectionNameAttr
        );
    }
}
