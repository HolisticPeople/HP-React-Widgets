<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelBenefits shortcode - renders the benefits section.
 * 
 * Usage:
 *   [hp_funnel_benefits funnel="illumodine"]
 *   [hp_funnel_benefits funnel="illumodine" columns="3" show_cards="true"]
 */
class FunnelBenefitsShortcode
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
            'funnel'             => '',
            'id'                 => '',
            'columns'            => '3',
            'show_cards'         => 'true',
            'default_icon'       => 'check',
            'title'              => '',        // Override default title
            'subtitle'           => '',        // Override default subtitle
            'background_color'   => '',        // Section background color override
            'background_gradient' => '',       // Section background gradient override
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        // Benefits can be in $config['benefits'] (new) or $config['hero']['benefits'] (legacy)
        $benefitsConfig = $config['benefits'] ?? [];
        $benefits = $benefitsConfig['items'] ?? ($config['hero']['benefits'] ?? []);

        // Don't render if no benefits
        if (empty($benefits)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No benefits configured for this funnel.');
            }
            return '';
        }

        // Build props for React component
        $props = [
            'title'              => !empty($atts['title']) ? $atts['title'] : ($benefitsConfig['title'] ?? ($config['hero']['benefits_title'] ?? 'Why Choose Us?')),
            'subtitle'           => !empty($atts['subtitle']) ? $atts['subtitle'] : ($benefitsConfig['subtitle'] ?? ''),
            'benefits'           => $benefits,
            'columns'            => (int) $atts['columns'],
            'showCards'          => filter_var($atts['show_cards'], FILTER_VALIDATE_BOOLEAN),
            'defaultIcon'        => $atts['default_icon'],
            'backgroundColor'    => $atts['background_color'],
            'backgroundGradient' => $atts['background_gradient'],
        ];

        return $this->renderWidget('FunnelBenefits', $config['slug'], $props);
    }

    /**
     * Load funnel config from attributes or auto-detect from context.
     * 
     * When used in an Elementor template for the funnel CPT, no attributes
     * are needed - the funnel is detected automatically from the current post.
     *
     * @param array $atts Shortcode attributes
     * @return array|null Config or null
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

        if (!$config || empty($config['active'])) {
            return null;
        }

        return $config;
    }

    /**
     * Render error message.
     *
     * @param string $message Error message
     * @return string HTML
     */
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

    /**
     * Render the React widget container.
     *
     * @param string $component Component name
     * @param string $slug Funnel slug
     * @param array $props Props for React
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props): string
    {
        $rootId = 'hp-funnel-benefits-' . esc_attr($slug) . '-' . uniqid();

        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-benefits-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

