<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelScience shortcode - renders the science/technical information section.
 * 
 * Usage:
 *   [hp_funnel_science funnel="illumodine"]
 *   [hp_funnel_science funnel="illumodine" layout="columns"]
 */
class FunnelScienceShortcode
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
            'funnel'   => '',
            'id'       => '',
            'title'    => '',
            'subtitle' => '',
            'layout'   => 'columns',
            'cta_text' => '',
            'cta_url'  => '',
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

        $science = $config['science'] ?? [];
        $sections = [];

        // Build sections from config
        if (!empty($science['sections']) && is_array($science['sections'])) {
            foreach ($science['sections'] as $section) {
                $sections[] = [
                    'title'       => $section['title'] ?? '',
                    'description' => $section['description'] ?? '',
                    'bullets'     => $section['bullets'] ?? [],
                ];
            }
        }

        // If no sections configured, return empty
        if (empty($sections)) {
            return '';
        }

        // Props for React component
        $props = [
            'title'    => $atts['title'] ?: ($science['title'] ?? 'The Science Behind Our Product'),
            'subtitle' => $atts['subtitle'] ?: ($science['subtitle'] ?? ''),
            'sections' => $sections,
            'layout'   => $atts['layout'],
            'ctaText'  => $atts['cta_text'] ?: ($config['hero']['cta_text'] ?? ''),
            'ctaUrl'   => $atts['cta_url'] ?: ($config['checkout']['url'] ?? '#checkout'),
        ];

        return $this->renderWidget('FunnelScience', $config['slug'], $props);
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
        $rootId = 'hp-funnel-science-' . esc_attr($slug) . '-' . uniqid();
        $propsJson = wp_json_encode($props);

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props=\'%s\'></div>',
            esc_attr($rootId),
            esc_attr($component),
            esc_attr($propsJson)
        );
    }
}
