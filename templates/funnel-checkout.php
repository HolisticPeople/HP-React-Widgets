<?php
/**
 * Template for funnel checkout page.
 * 
 * Available variables:
 * - $funnel: Array with funnel configuration (via get_query_var('hp_current_funnel'))
 *
 * This template attempts to load an Elementor template in this order:
 * 1. Template ID from funnel ACF field 'checkout_template_id'
 * 2. Template named "HP Express Shop Checkout"
 * 3. Fallback to direct shortcode rendering
 *
 * It automatically injects funnel styles (CSS variables, background) before the content.
 *
 * @package HP_React_Widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

$funnel = get_query_var('hp_current_funnel');
if (!$funnel) {
    wp_redirect(home_url('/'));
    exit;
}

/**
 * Build funnel styles CSS (same logic as FunnelStylesShortcode).
 * This ensures the checkout page has the same look as the landing page.
 */
if (!function_exists('hp_build_funnel_styles')) {
function hp_build_funnel_styles($funnel) {
    $styling = $funnel['styling'] ?? [];
    $slug = esc_attr($funnel['slug'] ?? 'checkout');
    
    $accentColor = $styling['accent_color'] ?? '#eab308';
    $bgType = strtolower($styling['background_type'] ?? 'gradient');
    $bgColor = $styling['background_color'] ?? '';
    $bgImage = $styling['background_image'] ?? '';
    $customCss = $styling['custom_css'] ?? '';
    
    // Text colors
    $textBasic = $styling['text_color_basic'] ?? '#e5e5e5';
    $textAccent = $styling['text_color_accent'] ?? '#eab308';
    $textNote = $styling['text_color_note'] ?? '#a3a3a3';
    $textDiscount = $styling['text_color_discount'] ?? '#22c55e';
    
    // UI element colors
    $borderColor = $styling['border_color'] ?? '#7c3aed';
    $cardBgColor = $styling['card_bg_color'] ?? '#1a1a1a';
    $pageBgColor = $styling['page_bg_color'] ?? '#121212';
    $inputBgColor = $styling['input_bg_color'] ?? '#333333';
    
    // Build background
    if (strpos($bgType, 'solid') !== false || $bgType === 'color') {
        $background = $bgColor ?: $pageBgColor;
    } elseif (strpos($bgType, 'image') !== false && $bgImage) {
        $background = "url('{$bgImage}') center/cover no-repeat fixed";
    } else {
        // Default gradient
        $background = 'linear-gradient(135deg, #0f0f1a 0%, #1a1525 25%, #151520 50%, #1a1a25 75%, #0f0f1a 100%)';
    }
    
    // Convert hex to RGB for glow effects
    $hexToRgb = function($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
    };
    $accentRgb = $hexToRgb($accentColor);
    $borderRgb = $hexToRgb($borderColor);
    
    return "
    <style id=\"hp-funnel-styles-{$slug}\">
        :root {
            --hp-funnel-accent: {$accentColor};
            --hp-funnel-accent-rgb: {$accentRgb};
            --hp-funnel-bg: {$background};
            --hp-funnel-text-basic: {$textBasic};
            --hp-funnel-text-accent: {$textAccent};
            --hp-funnel-text-note: {$textNote};
            --hp-funnel-text-discount: {$textDiscount};
            --hp-funnel-border: {$borderColor};
            --hp-funnel-border-rgb: {$borderRgb};
            --hp-funnel-card-bg: {$cardBgColor};
            --hp-funnel-page-bg: {$pageBgColor};
            --hp-funnel-input-bg: {$inputBgColor};
        }
        
        /* Global funnel page background */
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
            background: transparent;
        }
        
        /* Accent color utilities */
        .hp-funnel-accent { color: var(--hp-funnel-accent); }
        .hp-funnel-accent-bg { background-color: var(--hp-funnel-accent); }
        .hp-funnel-accent-glow { box-shadow: 0 0 30px rgba(var(--hp-funnel-accent-rgb), 0.5); }
        
        /* Override Tailwind classes in React checkout app */
        .hp-funnel-checkout-app h1,
        .hp-funnel-checkout-app h2,
        .hp-funnel-checkout-app h3 {
            color: var(--hp-funnel-text-accent) !important;
        }
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
        .hp-funnel-checkout-app label,
        .hp-funnel-checkout-app .text-sm {
            color: var(--hp-funnel-text-note) !important;
        }
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
    </style>
    <script>(function(){document.body.classList.add('hp-funnel-{$slug}');})();</script>
    ";
}
} // end function_exists check

/**
 * Get the Elementor template ID for checkout.
 * Priority: Funnel-specific ACF field > Named template "HP Express Shop Checkout"
 */
function hp_get_checkout_template_id($funnel) {
    // 1. Check funnel-specific template ID (ACF field)
    if (!empty($funnel['id'])) {
        $funnel_template = get_field('checkout_template_id', $funnel['id']);
        if ($funnel_template) {
            return (int) $funnel_template;
        }
    }
    
    // 2. Look for template by name "HP Express Shop Checkout"
    $templates = get_posts([
        'post_type' => 'elementor_library',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'title' => 'HP Express Shop Checkout',
    ]);
    if (!empty($templates)) {
        return $templates[0]->ID;
    }
    
    return null;
}

$template_id = hp_get_checkout_template_id($funnel);

// Build funnel styles (injected right after header)
$funnel_styles = hp_build_funnel_styles($funnel);

// Check if Elementor is active and template exists
if (class_exists('\Elementor\Plugin') && $template_id) {
    // Get the Elementor content
    $elementor = \Elementor\Plugin::instance();
    $content = $elementor->frontend->get_builder_content_for_display($template_id);
    
    if ($content) {
        get_header();
        // Inject funnel styles for consistent look
        echo $funnel_styles;
        ?>
        <div id="hp-funnel-checkout-page" class="hp-funnel-page hp-funnel-checkout-page hp-funnel-page-<?php echo esc_attr($funnel['slug']); ?>">
            <?php echo $content; ?>
        </div>
        <?php
        get_footer();
        exit;
    }
}

// Fallback: Direct shortcode rendering
get_header();
// Inject funnel styles for consistent look
echo $funnel_styles;
?>

<div id="hp-funnel-checkout-page" class="hp-funnel-page hp-funnel-checkout-page hp-funnel-page-<?php echo esc_attr($funnel['slug']); ?>">
    <?php 
    // Render the checkout SPA shortcode
    echo do_shortcode('[hp_funnel_checkout_app funnel="' . esc_attr($funnel['slug']) . '"]'); 
    ?>
</div>

<?php
get_footer();

