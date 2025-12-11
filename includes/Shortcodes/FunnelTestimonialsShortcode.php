<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelTestimonials shortcode - renders customer testimonials.
 * 
 * Usage:
 *   [hp_funnel_testimonials funnel="illumodine"]
 *   [hp_funnel_testimonials funnel="illumodine" columns="3" show_ratings="true"]
 */
class FunnelTestimonialsShortcode
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
            'funnel'       => '',
            'id'           => '',
            'title'        => '',
            'subtitle'     => '',
            'columns'      => '3',
            'show_ratings' => 'true',
            'layout'       => 'cards', // cards, carousel, simple
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        // Use testimonials from loader config
        $testimonialsConfig = $config['testimonials'] ?? [];
        $testimonials = $testimonialsConfig['items'] ?? [];

        if (empty($testimonials)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No testimonials configured for this funnel.');
            }
            return '';
        }

        $props = [
            'title'        => !empty($atts['title']) ? $atts['title'] : ($testimonialsConfig['title'] ?? 'What Our Customers Say'),
            'subtitle'     => !empty($atts['subtitle']) ? $atts['subtitle'] : ($testimonialsConfig['subtitle'] ?? ''),
            'testimonials' => $testimonials,
            'columns'      => min((int) $atts['columns'], 3),
            'showRatings'  => filter_var($atts['show_ratings'], FILTER_VALIDATE_BOOLEAN),
            'layout'       => $atts['layout'],
            'ctaText'      => $config['hero']['cta_text'] ?? '',
            'ctaUrl'       => $config['checkout']['url'] ?? '',
        ];

        return $this->renderWidget('FunnelTestimonials', $config['slug'], $props);
    }

    private function resolveImageUrl($value): string
    {
        if (empty($value)) {
            return '';
        }
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        if (is_array($value) && isset($value['url'])) {
            return (string) $value['url'];
        }
        if (is_numeric($value)) {
            $imageData = wp_get_attachment_image_src((int) $value, 'thumbnail');
            if ($imageData && isset($imageData[0])) {
                return $imageData[0];
            }
        }
        return '';
    }

    private function loadConfig(array $atts): ?array
    {
        $config = null;
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
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
        $rootId = 'hp-funnel-testimonials-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-testimonials-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

