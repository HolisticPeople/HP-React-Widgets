<?php
namespace HP_RW\Shortcodes;

use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode for rendering the scroll navigation dots component.
 * 
 * Usage: [hp_funnel_scroll_navigation]
 * 
 * The component auto-detects sections with .hp-funnel-section class,
 * or specific section IDs can be passed via the sections attribute.
 */
class FunnelScrollNavigationShortcode
{
    /**
     * Register the shortcode.
     */
    public static function register(): void
    {
        add_shortcode('hp_funnel_scroll_navigation', [self::class, 'render']);
    }

    /**
     * Render the shortcode.
     *
     * @param array|string $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render($atts): string
    {
        $atts = shortcode_atts([
            'sections' => '', // Comma-separated section IDs (optional)
        ], $atts, 'hp_funnel_scroll_navigation');

        // Check if scroll navigation is enabled for this funnel
        $funnelConfig = FunnelConfigLoader::getFromContext();
        
        // If we have funnel config, check the general.enable_scroll_navigation setting
        if ($funnelConfig) {
            $enabled = $funnelConfig['general']['enable_scroll_navigation'] ?? false;
            if (!$enabled) {
                return ''; // Don't render if disabled in funnel settings
            }
        }

        // Build config for React component
        $config = [
            'sections' => !empty($atts['sections']) 
                ? array_map('trim', explode(',', $atts['sections'])) 
                : [],
        ];

        // Enqueue assets
        \HP_RW\Plugin::enqueueFunnelAssets();

        return sprintf(
            '<div data-component="ScrollNavigation" data-config="%s"></div>',
            esc_attr(json_encode($config))
        );
    }
}
