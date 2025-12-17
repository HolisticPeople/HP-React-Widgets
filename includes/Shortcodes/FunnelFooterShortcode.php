<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelFooter shortcode - renders the footer section.
 * 
 * Usage:
 *   [hp_funnel_footer funnel="illumodine"]
 *   [hp_funnel_footer funnel="illumodine" show_copyright="true"]
 */
class FunnelFooterShortcode
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
            'funnel'         => '',
            'id'             => '',
            'text'           => '',
            'disclaimer'     => '',
            'show_copyright' => 'true',
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $postId = $config['id'];
        $footer = $config['footer'] ?? [];

        // Extract footer links
        $linksRaw = get_field('footer_links', $postId) ?: [];
        $links = [];
        foreach ($linksRaw as $link) {
            if (!empty($link['label']) && !empty($link['url'])) {
                $links[] = [
                    'label' => $link['label'],
                    'url'   => $link['url'],
                ];
            }
        }

        $props = [
            'funnelName'    => $config['name'],
            'text'          => !empty($atts['text']) ? $atts['text'] : ($footer['text'] ?? ''),
            'disclaimer'    => !empty($atts['disclaimer']) ? $atts['disclaimer'] : ($footer['disclaimer'] ?? ''),
            'links'         => $links,
            'showCopyright' => filter_var($atts['show_copyright'], FILTER_VALIDATE_BOOLEAN),
        ];

        return $this->renderWidget('FunnelFooter', $config['slug'], $props);
    }

    private function loadConfig(array $atts): ?array
    {
        $config = null;
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
        $rootId = 'hp-funnel-footer-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-footer-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}














