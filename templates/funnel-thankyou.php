<?php
/**
 * Template for funnel thank you page.
 * 
 * Available variables:
 * - $funnel: Array with funnel configuration (via get_query_var('hp_current_funnel'))
 *
 * This template attempts to load an Elementor template in this order:
 * 1. Template ID from funnel ACF field 'thankyou_template_id'
 * 2. Template named "Funnel Thank You Template"
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
 * Get the Elementor template ID for thank you page.
 */
function hp_get_thankyou_template_id($funnel) {
    // 1. Check funnel-specific template ID (ACF field)
    if (!empty($funnel['id'])) {
        $funnel_template = get_field('thankyou_template_id', $funnel['id']);
        if ($funnel_template) {
            return (int) $funnel_template;
        }
    }
    
    // 2. Check global option
    $global_template = get_option('hp_funnel_thankyou_template_id');
    if ($global_template) {
        return (int) $global_template;
    }
    
    // 3. Look for template by name "Funnel Thank You Template"
    $templates = get_posts([
        'post_type' => 'elementor_library',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'title' => 'Funnel Thank You Template',
    ]);
    if (!empty($templates)) {
        return $templates[0]->ID;
    }
    
    return null;
}

$template_id = hp_get_thankyou_template_id($funnel);

// Check if Elementor is active and template exists
if (class_exists('\Elementor\Plugin') && $template_id) {
    // Get the Elementor content
    $elementor = \Elementor\Plugin::instance();
    $content = $elementor->frontend->get_builder_content_for_display($template_id);
    
    if ($content) {
        get_header();
        ?>
        <div id="hp-funnel-thankyou-page" class="hp-funnel-page hp-funnel-thankyou-page">
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

<div id="hp-funnel-thankyou-page" class="hp-funnel-page hp-funnel-thankyou-page">
    <?php 
    // Render the thank you shortcode
    echo do_shortcode('[hp_funnel_thankyou funnel="' . esc_attr($funnel['slug']) . '"]'); 
    ?>
</div>

<?php
get_footer();

