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
        $cta = $config['cta'] ?? [];

        // Build background gradient from config
        $backgroundGradient = $this->buildBackgroundGradient($styling);

        // Get CTA button behavior
        $buttonBehavior = $cta['button_behavior'] ?? 'scroll_offers';
        
        // Get featured offer ID for checkout behavior
        $featuredOfferId = '';
        if ($buttonBehavior === 'checkout') {
            $offers = $config['offers'] ?? [];
            $featured = array_filter($offers, fn($o) => !empty($o['isFeatured']));
            $featuredOfferId = !empty($featured) ? reset($featured)['id'] : (!empty($offers) ? $offers[0]['id'] : '');
        }

        // Build props for React component
        $props = [
            'title'              => $hero['title'],
            'titleSize'          => !empty($hero['title_size']) ? $hero['title_size'] : 'xl',
            'subtitle'           => $hero['subtitle'],
            'tagline'            => $hero['tagline'],
            'description'        => $hero['description'],
            'heroImage'          => $hero['image'],
            'heroImageAlt'       => $config['name'],
            'ctaText'            => $hero['cta_text'],
            'ctaUrl'             => $config['checkout']['url'],
            'ctaBehavior'        => $buttonBehavior,
            'checkoutUrl'        => $config['checkout']['url'],
            'featuredOfferId'    => $featuredOfferId,
            'backgroundGradient' => $backgroundGradient,
            'accentColor'        => $styling['accent_color'],
            'textAlign'          => !empty($atts['text_align']) ? $atts['text_align'] : 'left',
            'imagePosition'      => !empty($atts['image_position']) ? $atts['image_position'] : 'right',
            'minHeight'          => !empty($atts['min_height']) ? $atts['min_height'] : '600px',
            // Scroll navigation - automatically rendered when enabled
            'enableScrollNavigation' => !empty($config['general']['enable_scroll_navigation']),
        ];

        // Build output with alternating background support
        return $this->renderWidgetWithAlternatingBg($config, $props, $styling);
    }

    /**
     * Build CSS gradient string from styling config.
     *
     * @param array $styling Styling config
     * @return string|null CSS gradient or null
     */
    private function buildBackgroundGradient(array $styling): ?string
    {
        $type = $styling['background_type'] ?? 'solid';
        
        if ($type === 'solid' && !empty($styling['page_bg_color'])) {
            return $styling['page_bg_color'];
        }
        
        if ($type === 'image' && !empty($styling['background_image'])) {
            return null; // Let React handle background image
        }
        
        // Default gradient - return null to use React's default
        return null;
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

    /**
     * Render widget with alternating background support.
     * This method adds CSS and JS for alternating section backgrounds if enabled.
     *
     * @param array $config Full funnel config
     * @param array $props Props for React
     * @param array $styling Styling config
     * @return string HTML
     */
    private function renderWidgetWithAlternatingBg(array $config, array $props, array $styling): string
    {
        $slug = $config['slug'];
        $output = '';
        
        // Add alternating background CSS and JS if enabled
        if (!empty($config['styling']['alternate_section_bg']) && !empty($config['styling']['alternate_bg_color'])) {
            $altBgColor = sanitize_hex_color($config['styling']['alternate_bg_color']);
            if ($altBgColor) {
                // CSS for alternate sections - full width background
                $output .= sprintf(
                    '<style>.hp-funnel-section.hp-alt-bg { 
                        background-color: %s !important; 
                        width: 100vw !important;
                        position: relative !important;
                        left: 50%% !important;
                        right: 50%% !important;
                        margin-left: -50vw !important;
                        margin-right: -50vw !important;
                        padding-left: calc(50vw - 50%%) !important;
                        padding-right: calc(50vw - 50%%) !important;
                        box-sizing: border-box !important;
                    }</style>',
                    $altBgColor
                );
                
                // JS to apply the class to alternate sections (skip hero sections)
                // First non-hero section gets alt bg, then every other section
                $output .= '<script>
(function() {
    function applyAltBg() {
        var sections = document.querySelectorAll(".hp-funnel-section");
        var sectionCount = 0;
        sections.forEach(function(section) {
            // Skip hero sections (they have their own background)
            if (section.classList.contains("hp-funnel-hero-section") || 
                section.className.includes("hp-funnel-hero-section-")) {
                return;
            }
            // Apply to 1st, 3rd, 5th... non-hero sections (sectionCount 0, 2, 4...)
            if (sectionCount % 2 === 0) {
                section.classList.add("hp-alt-bg");
            }
            sectionCount++;
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", applyAltBg);
    } else {
        setTimeout(applyAltBg, 100);
    }
})();
</script>';
            }
        }
        
        // Render the widget
        $output .= $this->renderWidget('FunnelHeroSection', $slug, $props, $styling);
        
        return $output;
    }
}














