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
        
        // Primary accent color - used for text accent AND UI accents
        $accentColor = $styling['accent_color'] ?? '#eab308';
        
        // Text colors (accent uses primary accent_color)
        $textBasic = $styling['text_color_basic'] ?? '#e5e5e5';
        $textAccent = $accentColor; // Consolidated: use accent_color for text accent
        $textNote = $styling['text_color_note'] ?? '#a3a3a3';
        $textDiscount = $styling['text_color_discount'] ?? '#22c55e';
        
        // UI element colors
        $pageBgColor = $styling['page_bg_color'] ?? '#121212';
        $cardBgColor = $styling['card_bg_color'] ?? '#1a1a1a';
        $inputBgColor = $styling['input_bg_color'] ?? '#333333';
        $borderColor = $styling['border_color'] ?? '#7c3aed';
        
        // Background settings (page_bg_color is used for solid backgrounds)
        $bgType = $styling['background_type'] ?? 'gradient';
        $bgImage = $styling['background_image'] ?? '';
        $customCss = $styling['custom_css'] ?? '';

        // Build background CSS (solid background uses page_bg_color)
        $background = $this->buildBackground($bgType, $pageBgColor, $bgImage);
        $slug = esc_attr($config['slug']);

        $cssVars = "
            --hp-funnel-accent: {$accentColor};
            --hp-funnel-accent-rgb: " . $this->hexToRgb($accentColor) . ";
            --hp-funnel-text-basic: {$textBasic};
            --hp-funnel-text-basic-rgb: " . $this->hexToRgb($textBasic) . ";
            --hp-funnel-text-accent: {$textAccent};
            --hp-funnel-text-accent-rgb: " . $this->hexToRgb($textAccent) . ";
            --hp-funnel-text-note: {$textNote};
            --hp-funnel-text-note-rgb: " . $this->hexToRgb($textNote) . ";
            --hp-funnel-text-discount: {$textDiscount};
            --hp-funnel-text-discount-rgb: " . $this->hexToRgb($textDiscount) . ";
            --hp-funnel-bg: {$background};
            --hp-funnel-border: {$borderColor};
            --hp-funnel-border-rgb: " . $this->hexToRgb($borderColor) . ";
            --hp-funnel-card-bg: {$cardBgColor};
            --hp-funnel-page-bg: {$pageBgColor};
            --hp-funnel-input-bg: {$inputBgColor};
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
            
            /* HP Funnel sections - transparent by default (no !important so inline styles can override) */
            .hp-funnel-section {
                background: transparent;
            }
            
            /* Text color utilities */
            .hp-text-basic {
                color: var(--hp-funnel-text-basic) !important;
            }
            .hp-text-accent, .hp-funnel-accent {
                color: var(--hp-funnel-text-accent) !important;
            }
            .hp-text-note {
                color: var(--hp-funnel-text-note) !important;
            }
            .hp-text-discount {
                color: var(--hp-funnel-text-discount) !important;
            }
            
            /* Accent color utilities */
            .hp-funnel-accent-bg {
                background-color: var(--hp-funnel-accent);
            }
            .hp-funnel-accent-glow {
                box-shadow: 0 0 30px rgba(var(--hp-funnel-accent-rgb), 0.5);
            }
            
            /* Override Tailwind classes in React checkout app */
            .hp-funnel-checkout-app .text-foreground,
            .hp-funnel-checkout-app [class*='text-foreground'] {
                color: var(--hp-funnel-text-basic) !important;
            }
            .hp-funnel-checkout-app .text-muted-foreground,
            .hp-funnel-checkout-app [class*='text-muted'] {
                color: var(--hp-funnel-text-note) !important;
            }
            .hp-funnel-checkout-app .text-accent,
            .hp-funnel-checkout-app [class*='text-accent'] {
                color: var(--hp-funnel-text-accent) !important;
            }
            .hp-funnel-checkout-app .text-green-500, 
            .hp-funnel-checkout-app .text-emerald-500,
            .hp-funnel-checkout-app [class*='text-green'],
            .hp-funnel-checkout-app [class*='text-emerald'] {
                color: var(--hp-funnel-text-discount) !important;
            }
            
            /* Heading colors */
            .hp-funnel-checkout-app h1,
            .hp-funnel-checkout-app h2,
            .hp-funnel-checkout-app h3 {
                color: var(--hp-funnel-text-accent) !important;
            }
            
            /* Body/paragraph text */
            .hp-funnel-checkout-app p,
            .hp-funnel-checkout-app span,
            .hp-funnel-checkout-app label,
            .hp-funnel-checkout-app div {
                color: var(--hp-funnel-text-basic);
            }
            
            /* Input labels */
            .hp-funnel-checkout-app label,
            .hp-funnel-checkout-app .text-sm {
                color: var(--hp-funnel-text-note) !important;
            }
            
            /* Prices and discounts */
            .hp-funnel-checkout-app .line-through {
                color: var(--hp-funnel-text-note) !important;
            }
            
            /* UI Element color overrides for React checkout app */
            .hp-funnel-checkout-app {
                --background: var(--hp-funnel-page-bg);
                --card: var(--hp-funnel-card-bg);
                --border: var(--hp-funnel-border);
                --input: var(--hp-funnel-input-bg);
            }
            
            /* Border color overrides */
            .hp-funnel-checkout-app [class*='border-border'],
            .hp-funnel-checkout-app [class*='border-accent'],
            .hp-funnel-checkout-app .border {
                border-color: var(--hp-funnel-border) !important;
            }
            
            /* Card background overrides */
            .hp-funnel-checkout-app [class*='bg-card'],
            .hp-funnel-checkout-app [class*='bg-secondary'] {
                background-color: var(--hp-funnel-card-bg) !important;
            }
            
            /* Input background overrides */
            .hp-funnel-checkout-app [class*='bg-input'],
            .hp-funnel-checkout-app input,
            .hp-funnel-checkout-app select,
            .hp-funnel-checkout-app textarea {
                background-color: var(--hp-funnel-input-bg) !important;
            }
            
            /* Page background for the checkout app container */
            .hp-funnel-checkout-app [class*='bg-background'] {
                background-color: var(--hp-funnel-page-bg) !important;
            }

            {$customCss}
        </style>";

        // Add body class via inline script (runs immediately)
        $output .= "<script>(function(){document.body.classList.add('hp-funnel-{$slug}');})();</script>";

        return $output;
    }

    /**
     * Build background CSS value.
     * 
     * @param string $type Background type (gradient, solid, image)
     * @param string $pageBgColor Page background color (used for solid backgrounds)
     * @param string $image Background image URL
     * @return string CSS background value
     */
    private function buildBackground(string $type, string $pageBgColor, string $image = ''): string
    {
        // Normalize type value (ACF might store "Default Gradient", "Solid Color", etc.)
        $normalizedType = strtolower(trim($type));
        
        // Check for solid color - uses page_bg_color
        if (strpos($normalizedType, 'solid') !== false || $normalizedType === 'color') {
            return $pageBgColor ?: '#121212';
        }
        
        // Check for image
        if (strpos($normalizedType, 'image') !== false) {
            if ($image) {
                return "url('{$image}') center/cover no-repeat fixed";
            }
            return $pageBgColor ?: '#121212';
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
     * Load funnel config from attributes or auto-detect from context.
     * 
     * When used in an Elementor template for the funnel CPT, no attributes
     * are needed - the funnel is detected automatically from the current post.
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
        
        return ($config && !empty($config['active'])) ? $config : null;
    }
}

