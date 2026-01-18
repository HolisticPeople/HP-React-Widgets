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
 * Usage:
 *   [hp_funnel_infographics funnel="illumodine"]
 *   [hp_funnel_infographics funnel="illumodine" mobile_layout="carousel"]
 * 
 * @package HP_RW\Shortcodes
 * @since 2.20.0
 * @version 1.0.0 - Initial implementation
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

        $infographics = $config['infographics'] ?? [];

        // Build props from config with shortcode attribute overrides
        $desktopImage = $atts['desktop_image'] ?: ($infographics['desktop_image'] ?? '');
        $titleImage = $atts['title_image'] ?: ($infographics['title_image'] ?? '');
        $leftPanelImage = $atts['left_panel'] ?: ($infographics['left_panel_image'] ?? '');
        $rightPanelImage = $atts['right_panel'] ?: ($infographics['right_panel_image'] ?? '');

        // If no images configured, return empty
        if (empty($desktopImage) && empty($leftPanelImage) && empty($rightPanelImage)) {
            return '';
        }

        // Determine useMobileImages - shortcode override, then config, default true
        $useMobileImages = true;
        if ($atts['use_mobile_images'] !== '') {
            $useMobileImages = filter_var($atts['use_mobile_images'], FILTER_VALIDATE_BOOLEAN);
        } elseif (isset($infographics['use_mobile_images'])) {
            $useMobileImages = (bool) $infographics['use_mobile_images'];
        }

        // Props for React component
        $props = [
            'title'            => $atts['title'] ?: ($infographics['title'] ?? ''),
            'desktopImage'     => $desktopImage,
            'useMobileImages'  => $useMobileImages,
            'desktopFallback'  => $atts['desktop_fallback'] ?: ($infographics['desktop_fallback'] ?? 'scale'),
            'titleImage'       => $titleImage,
            'leftPanelImage'   => $leftPanelImage,
            'rightPanelImage'  => $rightPanelImage,
            'mobileLayout'     => $atts['mobile_layout'] ?: ($infographics['mobile_layout'] ?? 'stack'),
            'altText'          => $atts['alt_text'] ?: ($infographics['alt_text'] ?? ''),
        ];

        // Navigation label for scroll navigation dots (empty = exclude from nav)
        $navLabel = $atts['nav_label'] ?: ($infographics['nav_label'] ?? 'Comparison');

        return $this->renderWidget('FunnelInfographics', $config['slug'], $props, $navLabel);
    }

    /**
     * Render the React widget container.
     *
     * @param string $component React component name
     * @param string $slug      Funnel slug
     * @param array  $props     Component props
     * @param string $navLabel  Label for scroll navigation (empty = exclude from nav)
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props, string $navLabel = 'Comparison'): string
    {
        $rootId = 'hp-funnel-infographics-' . esc_attr($slug) . '-' . uniqid();
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
            @media (max-width: 767px) {
                .elementor-widget-shortcode:has(#' . esc_attr($rootId) . '),
                .elementor-widget-shortcode:has(.hp-funnel-infographics-' . esc_attr($slug) . ') {
                    height: auto !important;
                    min-height: 0 !important;
                    flex: 0 0 auto !important;
                }
            }
        </style>';

        // Only include data-section-name if navLabel is not empty
        $sectionNameAttr = !empty($navLabel) ? sprintf(' data-section-name="%s"', esc_attr($navLabel)) : '';

        return $css . sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-infographics-%s" data-hp-widget="1" data-component="%s" data-props=\'%s\'%s style="min-height:auto;height:auto;align-self:start;"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr($propsJson),
            $sectionNameAttr
        );
    }
}
