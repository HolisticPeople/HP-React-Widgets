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

        $cssVars = "
            --hp-funnel-accent: {$accentColor};
            --hp-funnel-accent-rgb: " . $this->hexToRgb($accentColor) . ";
            --hp-funnel-bg: {$background};
        ";

        // Output CSS
        $output = "<style id=\"hp-funnel-styles-{$config['slug']}\">
            :root {
                {$cssVars}
            }
            
            /* Global funnel page background */
            .hp-funnel-page,
            body.hp-funnel-{$config['slug']} {
                background: {$background};
                min-height: 100vh;
            }
            
            /* Section default - transparent to show page background */
            .hp-funnel-section {
                background: transparent;
            }
            
            /* Section with explicit background override */
            .hp-funnel-section[data-bg] {
                background: attr(data-bg);
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

        // Add body class via inline script
        $output .= "<script>document.body.classList.add('hp-funnel-{$config['slug']}');</script>";

        return $output;
    }

    /**
     * Build background CSS value.
     */
    private function buildBackground(string $type, string $color, string $image): string
    {
        switch ($type) {
            case 'solid':
                return $color ?: '#1a1a2e';
            
            case 'image':
                if ($image) {
                    return "url('{$image}') center/cover no-repeat";
                }
                return '#1a1a2e';
            
            case 'gradient':
            default:
                // Default dark gradient with accent glow
                return 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f0f23 100%)';
        }
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

