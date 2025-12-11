<?php
namespace HP_RW\Shortcodes;

use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode for FunnelScience section.
 * 
 * Displays detailed scientific/technical information about the product.
 * 
 * Usage: [hp_funnel_science funnel="illumodine"]
 */
class FunnelScienceShortcode extends AbstractShortcode
{
    /**
     * Register the shortcode.
     *
     * @return void
     */
    public function register(): void
    {
        add_shortcode('hp_funnel_science', [$this, 'render']);
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render($atts): string
    {
        $atts = shortcode_atts([
            'funnel' => '',
            'title' => '',
            'subtitle' => '',
            'layout' => 'columns',
            'cta_text' => '',
            'cta_url' => '',
        ], $atts, 'hp_funnel_science');

        $slug = sanitize_key($atts['funnel']);
        $config = $slug ? FunnelConfigLoader::getBySlug($slug) : [];

        $science = $config['science'] ?? [];
        $sections = [];

        // Build sections from config
        if (!empty($science['sections']) && is_array($science['sections'])) {
            foreach ($science['sections'] as $section) {
                $sections[] = [
                    'title' => $section['title'] ?? '',
                    'description' => $section['description'] ?? '',
                    'bullets' => $section['bullets'] ?? [],
                ];
            }
        }

        // Props for React component
        $props = [
            'title' => $atts['title'] ?: ($science['title'] ?? 'The Science Behind Our Product'),
            'subtitle' => $atts['subtitle'] ?: ($science['subtitle'] ?? ''),
            'sections' => $sections,
            'layout' => $atts['layout'],
            'ctaText' => $atts['cta_text'] ?: ($config['hero']['cta_text'] ?? ''),
            'ctaUrl' => $atts['cta_url'] ?: ($config['checkout']['url'] ?? '#checkout'),
        ];

        return $this->renderReactContainer('FunnelScience', $props);
    }

    /**
     * Render React container.
     *
     * @param string $component Component name.
     * @param array  $props     Component props.
     * @return string HTML container.
     */
    protected function renderReactContainer(string $component, array $props): string
    {
        $id = 'hp-rw-' . $component . '-' . wp_unique_id();
        $propsJson = wp_json_encode($props);

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props=\'%s\'></div>',
            esc_attr($id),
            esc_attr($component),
            esc_attr($propsJson)
        );
    }
}

