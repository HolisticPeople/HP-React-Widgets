<?php
namespace HP_RW\Shortcodes;

use HP_RW\Services\FunnelConfigLoader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * FunnelStyles shortcode - outputs global CSS variables and background for a funnel.
 * 
 * Usage:
 *   [hp_funnel_styles funnel="illumodine"]
 * 
 * Place this at the TOP of your funnel page (before any section shortcodes).
 * It sets CSS variables that all funnel sections will use.
 */
class FunnelStylesShortcode
{
    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes
     * @return string HTML/CSS output
     */
    public function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'funnel' => '',
            'id'     => '',
        ], $atts);

        // Load config
        $config = $this->loadConfig($atts);
        if (!$config) {
            return '';
        }

        $styling = $config['styling'] ?? [];
        
        // Build CSS variables
        $accentColor = $styling['accent_color'] ?? '#eab308';
        $bgType = $styling['background_type'] ?? 'gradient';
        $bgColor = $styling['background_color'] ?? '';
        $bgImage = $styling['background_image'] ?? '';
        $customCss = $styling['custom_css'] ?? '';

        // Build background CSS
        $background = $this->buildBackground($bgType, $bgColor, $bgImage);
        $slug = esc_attr($config['slug']);

        $cssVars = "
            --hp-funnel-accent: {$accentColor};
            --hp-funnel-accent-rgb: " . $this->hexToRgb($accentColor) . ";
            --hp-funnel-bg: {$background};
        ";

        // Output CSS with high specificity to override Elementor
        $output = "<style id=\"hp-funnel-styles-{$slug}\">
            :root {
                {$cssVars}
            }
            
            /* Global funnel page background - high specificity to override Elementor */
            body.hp-funnel-{$slug},
            body.hp-funnel-{$slug} .elementor,
            body.hp-funnel-{$slug} .elementor-inner,
            body.hp-funnel-{$slug} .elementor-section-wrap,
            body.hp-funnel-{$slug} .e-con,
            .hp-funnel-page-{$slug} {
                background: {$background} !important;
            }
            
            body.hp-funnel-{$slug} {
                min-height: 100vh;
            }
            
            /* Make Elementor sections transparent */
            body.hp-funnel-{$slug} .elementor-section,
            body.hp-funnel-{$slug} .elementor-element,
            body.hp-funnel-{$slug} .e-con {
                background-color: transparent !important;
            }
            
            /* HP Funnel sections - transparent by default */
            .hp-funnel-section {
                background: transparent !important;
            }
            
            /* Accent color utilities */
            .hp-funnel-accent {
                color: var(--hp-funnel-accent);
            }
            .hp-funnel-accent-bg {
                background-color: var(--hp-funnel-accent);
            }
            .hp-funnel-accent-glow {
                box-shadow: 0 0 30px rgba(var(--hp-funnel-accent-rgb), 0.5);
            }

            {$customCss}
        </style>";

        // Add body class via inline script (runs immediately)
        $output .= "<script>(function(){document.body.classList.add('hp-funnel-{$slug}');})();</script>";

        return $output;
    }

    /**
     * Build background CSS value.
     */
    private function buildBackground(string $type, string $color, string $image): string
    {
        // Normalize type value (ACF might store "Default Gradient", "Solid Color", etc.)
        $normalizedType = strtolower(trim($type));
        
        // Check for solid color
        if (strpos($normalizedType, 'solid') !== false || $normalizedType === 'color') {
            return $color ?: '#1a1a2e';
        }
        
        // Check for image
        if (strpos($normalizedType, 'image') !== false) {
            if ($image) {
                return "url('{$image}') center/cover no-repeat fixed";
            }
            return '#1a1a2e';
        }
        
        // Default: gradient (matches "gradient", "default gradient", etc.)
        // Dark gradient with subtle purple/gold undertones for Illumodine style
        return 'linear-gradient(135deg, #0f0f1a 0%, #1a1525 25%, #151520 50%, #1a1a25 75%, #0f0f1a 100%)';
    }

    /**
     * Convert hex color to RGB values.
     */
    private function hexToRgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        return "{$r}, {$g}, {$b}";
    }

    /**
     * Load funnel config from attributes.
     */
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
}

