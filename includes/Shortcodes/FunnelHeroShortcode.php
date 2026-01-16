<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;
use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelHero shortcode - renders a landing page hero for sales funnels.
 * 
 * Usage:
 *   [hp_funnel_hero funnel="illumodine"]  - by slug
 *   [hp_funnel_hero id="123"]             - by post ID
 * 
 * The funnel configuration is loaded from the hp-funnel CPT via ACF fields.
 */
class FunnelHeroShortcode
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
            'funnel' => '',    // Funnel slug
            'id'     => '',    // Funnel post ID
        ], $atts);

        // Load config by ID, slug, or auto-detect from context
        $config = null;
        $debugInfo = '';
        
        if (!empty($atts['id'])) {
            $config = FunnelConfigLoader::getById((int) $atts['id']);
            $debugInfo = 'Method: id=' . $atts['id'];
        } elseif (!empty($atts['funnel'])) {
            $config = FunnelConfigLoader::getBySlug($atts['funnel']);
            $debugInfo = 'Method: funnel=' . $atts['funnel'];
        } else {
            // Auto-detect from current post context (for use in CPT templates)
            $config = FunnelConfigLoader::getFromContext();
            
            // Debug info
            global $post;
            $queried = get_queried_object();
            $debugInfo = sprintf(
                'Method: context | queried_object: %s (type: %s) | global $post: %s (type: %s) | get_the_ID: %s | URI: %s',
                is_object($queried) ? (property_exists($queried, 'ID') ? $queried->ID : get_class($queried)) : 'null',
                is_object($queried) && $queried instanceof \WP_Post ? $queried->post_type : 'n/a',
                $post instanceof \WP_Post ? $post->ID : 'null',
                $post instanceof \WP_Post ? $post->post_type : 'n/a',
                get_the_ID(),
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            );
        }

        if (!$config || !$config['active']) {
            $errorMsg = $config ? 'Config found but active=' . var_export($config['active'] ?? 'not set', true) : 'Config is null';
            return sprintf(
                '<!-- HP-RW Debug: %s | Result: %s -->
                <div class="hp-funnel-error" style="padding: 20px; background: #fee; color: #c00; border: 1px solid #c00; border-radius: 4px;">Funnel not found or inactive.</div>',
                esc_html($debugInfo),
                esc_html($errorMsg)
            );
        }

        // Build props for React component
        $props = [
            'funnelId'          => $config['slug'],
            'funnelName'        => $config['name'],
            'title'             => $config['hero']['title'],
            'titleSize'         => !empty($config['hero']['title_size']) ? $config['hero']['title_size'] : 'xl',
            'subtitle'          => $config['hero']['subtitle'],
            'tagline'           => $config['hero']['tagline'],
            'description'       => $config['hero']['description'],
            'heroImage'         => $config['hero']['image'],
            'logoUrl'           => $config['hero']['logo'],
            'logoLink'          => $config['hero']['logo_link'],
            'ctaText'           => $config['hero']['cta_text'],
            'checkoutUrl'       => $config['checkout']['url'],
            'products'          => $this->formatProductsForReact($config['products']),
            'benefits'          => $config['hero']['benefits'],
            'benefitsTitle'     => $config['hero']['benefits_title'],
            'accentColor'       => $config['styling']['accent_color'],
            'backgroundType'    => $config['styling']['background_type'],
            'backgroundColor'   => $config['styling']['page_bg_color'],
            'backgroundImage'   => $config['styling']['background_image'],
            'footerText'        => $config['footer']['text'],
            'footerDisclaimer'  => $config['footer']['disclaimer'],
            // Scroll navigation - automatically rendered when enabled
            'enableScrollNavigation' => !empty($config['general']['enable_scroll_navigation']),
        ];
        
        // #region agent log - PHP side debug
        error_log('[HP-RW DEBUG] FunnelHeroShortcode: scroll_nav_raw=' . var_export($config['general']['enable_scroll_navigation'] ?? 'NOT_SET', true) . ', computed=' . var_export($props['enableScrollNavigation'], true) . ', slug=' . $config['slug']);
        // #endregion

        // Add custom CSS if present
        $customCss = '';
        $cssRules = [];
        
        if (!empty($config['styling']['custom_css'])) {
            $cssRules[] = sprintf(
                '.hp-funnel-%s { %s }',
                esc_attr($config['slug']),
                wp_strip_all_tags($config['styling']['custom_css'])
            );
        }
        
        // Add alternating section background CSS and JS if enabled
        $alternatingJs = '';
        if (!empty($config['styling']['alternate_section_bg']) && !empty($config['styling']['alternate_bg_color'])) {
            $altBgColor = sanitize_hex_color($config['styling']['alternate_bg_color']);
            if ($altBgColor) {
                // Add CSS class for alternate sections
                $cssRules[] = sprintf(
                    '.hp-funnel-section.hp-alt-bg { background-color: %s !important; }',
                    $altBgColor
                );
                
                // Add JS to apply the class to even sections (run after page load)
                $alternatingJs = sprintf(
                    '<script>
                    (function() {
                        function applyAltBg() {
                            var sections = document.querySelectorAll(".hp-funnel-section");
                            sections.forEach(function(section, index) {
                                // Skip hero section (index 0), apply to even indices (2nd, 4th, etc. which are index 1, 3, etc.)
                                if (index > 0 && index %% 2 === 0) {
                                    section.classList.add("hp-alt-bg");
                                }
                            });
                        }
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", applyAltBg);
                        } else {
                            setTimeout(applyAltBg, 100);
                        }
                    })();
                    </script>'
                );
            }
        }
        
        if (!empty($cssRules)) {
            $customCss = '<style>' . implode("\n", $cssRules) . '</style>';
        }

        $rootId = 'hp-funnel-hero-' . esc_attr($config['slug']) . '-' . uniqid();

        return $customCss . $alternatingJs . sprintf(
            '<div id="%s" class="hp-funnel-%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($rootId),
            esc_attr($config['slug']),
            esc_attr('FunnelHero'),
            esc_attr(wp_json_encode($props))
        );
    }

    /**
     * Format products for React component.
     *
     * @param array $products Products from config
     * @return array Formatted for React
     */
    private function formatProductsForReact(array $products): array
    {
        $result = [];
        
        foreach ($products as $product) {
            $result[] = [
                'id'           => $product['id'] ?? $product['sku'],
                'sku'          => $product['sku'],
                'name'         => $product['name'],
                'description'  => $product['description'] ?? '',
                'price'        => $product['price'],
                'regularPrice' => $product['regularPrice'] ?? null,
                'image'        => $product['image'],
                'badge'        => $product['badge'] ?? '',
                'features'     => $product['features'] ?? [],
                'isBestValue'  => $product['isBestValue'] ?? false,
            ];
        }

        return $result;
    }
}
