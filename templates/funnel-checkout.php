<?php
/**
 * Template for funnel checkout page.
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

<div id="hp-funnel-checkout-page" class="hp-funnel-page hp-funnel-checkout-page">
    <?php 
    // Render the checkout SPA shortcode
    echo do_shortcode('[hp_funnel_checkout_app funnel="' . esc_attr($funnel['slug']) . '"]'); 
    ?>
</div>

<?php
get_footer();

