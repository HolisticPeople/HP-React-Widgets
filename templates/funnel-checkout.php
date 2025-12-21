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

// Check if Elementor is active and template exists
if (class_exists('\Elementor\Plugin') && $template_id) {
    // Get the Elementor content
    $elementor = \Elementor\Plugin::instance();
    $content = $elementor->frontend->get_builder_content_for_display($template_id);
    
    if ($content) {
        get_header();
        ?>
        <div id="hp-funnel-checkout-page" class="hp-funnel-page hp-funnel-checkout-page">
            <?php echo $content; ?>
        </div>
        <?php
        get_footer();
        exit;
    }
}

// Fallback: Direct shortcode rendering
get_header();
?>

<div id="hp-funnel-checkout-page" class="hp-funnel-page hp-funnel-checkout-page">
    <?php 
    // Render the checkout SPA shortcode
    echo do_shortcode('[hp_funnel_checkout_app funnel="' . esc_attr($funnel['slug']) . '"]'); 
    ?>
</div>

<?php
get_footer();

