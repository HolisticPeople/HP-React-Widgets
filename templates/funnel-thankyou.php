<?php
/**
 * Template for funnel thank you page.
 * 
 * Available variables:
 * - $funnel: Array with funnel configuration (via get_query_var('hp_current_funnel'))
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

