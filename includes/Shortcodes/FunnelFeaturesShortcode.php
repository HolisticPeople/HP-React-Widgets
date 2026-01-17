<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelFeatures shortcode - renders the features section.
 * 
 * Usage:
 *   [hp_funnel_features funnel="illumodine"]
 *   [hp_funnel_features funnel="illumodine" columns="3" layout="cards"]
 */
class FunnelFeaturesShortcode
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
            'funnel'  => '',
            'id'      => '',
            'title'   => '',
            'subtitle' => '',
            'columns' => '3',
            'layout'  => 'cards', // cards, list, grid
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $features = $this->extractFeatures($config);

        if (empty($features)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No features configured for this funnel.');
            }
            return '';
        }

        $props = [
            'title'    => !empty($atts['title']) ? $atts['title'] : ($config['features_title'] ?? 'Key Features'),
            'subtitle' => !empty($atts['subtitle']) ? $atts['subtitle'] : ($config['features_subtitle'] ?? ''),
            'features' => $features,
            'columns'  => (int) $atts['columns'],
            'layout'   => $atts['layout'],
        ];

        return $this->renderWidget('FunnelFeatures', $config['slug'], $props);
    }

    /**
     * Extract features from config.
     *
     * @param array $config Funnel config
     * @return array Features array
     */
    private function extractFeatures(array $config): array
    {
        $featuresList = get_field('features_list', $config['id']) ?: [];
        $result = [];

        foreach ($featuresList as $feature) {
            if (!empty($feature['title'])) {
                $result[] = [
                    'icon'        => $feature['icon'] ?? 'check',
                    'title'       => $feature['title'],
                    'description' => $feature['description'] ?? '',
                ];
            }
        }

        return $result;
    }

    /**
     * Load funnel config from attributes or auto-detect from context.
     * 
     * When used in an Elementor template for the funnel CPT, no attributes
     * are needed - the funnel is detected automatically from the current post.
     */
    private function loadConfig(array $atts): ?array
    {
        $config = null;
        
        // Try explicit attributes first
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
        }
        
        return ($config && !empty($config['active'])) ? $config : null;
    }

    private function renderError(string $message): string
    {
        if (current_user_can('manage_options')) {
            return sprintf(
                '<div class="hp-funnel-error" style="padding: 10px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px; font-size: 12px;">%s</div>',
                esc_html($message)
            );
        }
        return '';
    }

    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-features-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-features-%s" data-hp-widget="1" data-component="%s" data-props="%s" data-section-name="Features"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}














