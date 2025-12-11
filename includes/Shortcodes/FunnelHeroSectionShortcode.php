<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelHeroSection shortcode - renders the hero section with headline, image, and CTA.
 * 
 * Usage:
 *   [hp_funnel_hero_section funnel="illumodine"]
 *   [hp_funnel_hero_section funnel="illumodine" image_position="left" text_align="center"]
 */
class FunnelHeroSectionShortcode
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
            'image_position' => '', // Override: right, left, background
            'text_align'     => '', // Override: left, center, right
            'min_height'     => '', // Override: e.g., "600px"
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return $this->renderError('Funnel not found or inactive.');
        }

        $hero = $config['hero'];
        $styling = $config['styling'];

        // Build background gradient from config
        $backgroundGradient = $this->buildBackgroundGradient($styling);

        // Build props for React component
        $props = [
            'title'              => $hero['title'],
            'subtitle'           => $hero['subtitle'],
            'tagline'            => $hero['tagline'],
            'description'        => $hero['description'],
            'heroImage'          => $hero['image'],
            'heroImageAlt'       => $config['name'],
            'ctaText'            => $hero['cta_text'],
            'ctaUrl'             => $config['checkout']['url'],
            'backgroundGradient' => $backgroundGradient,
            'accentColor'        => $styling['accent_color'],
            'textAlign'          => !empty($atts['text_align']) ? $atts['text_align'] : 'left',
            'imagePosition'      => !empty($atts['image_position']) ? $atts['image_position'] : 'right',
            'minHeight'          => !empty($atts['min_height']) ? $atts['min_height'] : '600px',
        ];

        return $this->renderWidget('FunnelHeroSection', $config['slug'], $props, $styling);
    }

    /**
     * Build CSS gradient string from styling config.
     *
     * @param array $styling Styling config
     * @return string|null CSS gradient or null
     */
    private function buildBackgroundGradient(array $styling): ?string
    {
        $type = $styling['background_type'] ?? 'gradient';
        
        if ($type === 'solid' && !empty($styling['background_color'])) {
            return $styling['background_color'];
        }
        
        if ($type === 'image' && !empty($styling['background_image'])) {
            return null; // Let React handle background image
        }
        
        // Default gradient - return null to use React's default
        return null;
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
     * @param array $styling Styling config
     * @return string HTML
     */
    private function renderWidget(string $component, string $slug, array $props, array $styling = []): string
    {
        $rootId = 'hp-funnel-hero-section-' . esc_attr($slug) . '-' . uniqid();

        // Add custom CSS if present
        $customCss = '';
        if (!empty($styling['custom_css'])) {
            $customCss = sprintf(
                '<style>.hp-funnel-hero-section-%s { %s }</style>',
                esc_attr($slug),
                wp_strip_all_tags($styling['custom_css'])
            );
        }

        return $customCss . sprintf(
            '<div id="%s" class="hp-funnel-section hp-funnel-hero-section-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($slug),
            esc_attr($component),
            esc_attr(wp_json_encode($props))
        );
    }
}

