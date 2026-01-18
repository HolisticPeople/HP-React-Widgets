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
            'funnel'        => '',
            'id'            => '',
            'title'         => '',
            'desktop_image' => '',
            'title_image'   => '',
            'left_panel'    => '',
            'right_panel'   => '',
            'mobile_layout' => '',
            'alt_text'      => '',
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

        // Props for React component
        $props = [
            'title'           => $atts['title'] ?: ($infographics['title'] ?? ''),
            'desktopImage'    => $desktopImage,
            'titleImage'      => $titleImage,
            'leftPanelImage'  => $leftPanelImage,
            'rightPanelImage' => $rightPanelImage,
            'mobileLayout'    => $atts['mobile_layout'] ?: ($infographics['mobile_layout'] ?? 'stack'),
            'altText'         => $atts['alt_text'] ?: ($infographics['alt_text'] ?? ''),
        ];

        return $this->renderWidget('FunnelInfographics', $config['slug'], $props);
    }

    /**
     * Render the React widget container.
     *
     * @param string $component React component name
     * @param string $slug      Funnel slug
     * @param array  $props     Component props
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-infographics-' . esc_attr($slug) . '-' . uniqid();
        $propsJson = wp_json_encode($props);

        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-infographics-%s" data-hp-widget="1" data-component="%s" data-props=\'%s\' data-section-name="Infographics"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr($propsJson)
        );
    }
}
