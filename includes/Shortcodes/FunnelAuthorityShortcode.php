<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelAuthority shortcode - renders the "Who We Are" / expert section.
 * 
 * Usage:
 *   [hp_funnel_authority funnel="illumodine"]
 *   [hp_funnel_authority funnel="illumodine" layout="centered"]
 */
class FunnelAuthorityShortcode
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
            'funnel' => '',
            'id'     => '',
            'title'  => '',
            'layout' => 'side-by-side', // side-by-side, centered, card
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $postId = $config['id'];
        $name = get_field('authority_name', $postId);

        if (empty($name)) {
            if (current_user_can('manage_options')) {
                return $this->renderError('No authority/expert configured for this funnel.');
            }
            return '';
        }

        // Extract quotes
        $quotesRaw = get_field('authority_quotes', $postId) ?: [];
        $quotes = [];
        foreach ($quotesRaw as $q) {
            if (!empty($q['text'])) {
                $quotes[] = ['text' => $q['text']];
            }
        }

        // Resolve image URL
        $image = get_field('authority_image', $postId);
        $imageUrl = $this->resolveImageUrl($image);

        $props = [
            'title'       => !empty($atts['title']) ? $atts['title'] : (get_field('authority_title', $postId) ?: 'Who We Are'),
            'name'        => $name,
            'credentials' => get_field('authority_credentials', $postId) ?: '',
            'image'       => $imageUrl,
            'bio'         => get_field('authority_bio', $postId) ?: '',
            'quotes'      => $quotes,
            'layout'      => $atts['layout'],
        ];

        return $this->renderWidget('FunnelAuthority', $config['slug'], $props);
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
            $imageData = wp_get_attachment_image_src((int) $value, 'large');
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
        $rootId = 'hp-funnel-authority-' . esc_attr($slug) . '-' . uniqid();
        return sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-authority-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

