<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelHeader shortcode - renders the header section with logo and navigation.
 * 
 * Usage:
 *   [hp_funnel_header funnel="illumodine"]
 *   [hp_funnel_header funnel="illumodine" sticky="true" transparent="true"]
 */
class FunnelHeaderShortcode
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
            'funnel'      => '',
            'id'          => '',
            'sticky'      => 'false',
            'transparent' => 'false',
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        // Build props for React component
        $props = [
            'logoUrl'     => $config['hero']['logo'] ?? '',
            'logoLink'    => $config['hero']['logo_link'] ?? home_url('/'),
            'logoAlt'     => $config['name'],
            'navItems'    => $config['header']['nav_items'] ?? [],
            'sticky'      => filter_var($atts['sticky'], FILTER_VALIDATE_BOOLEAN),
            'transparent' => filter_var($atts['transparent'], FILTER_VALIDATE_BOOLEAN),
        ];

        return $this->renderWidget('FunnelHeader', $config['slug'], $props);
    }

    /**
     * Load funnel config from attributes.
     *
     * @param array $atts Shortcode attributes
     * @return array|null Config or null
     */
    private function loadConfig(array $atts): ?array
    {
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
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
        $rootId = 'hp-funnel-header-' . esc_attr($slug) . '-' . uniqid();

        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-header-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

