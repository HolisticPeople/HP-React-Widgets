<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           2.44.2
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '2.44.2');
define('HP_RW_FILE', __FILE__);
define('HP_RW_PATH', plugin_dir_path(__FILE__));
define('HP_RW_URL', plugin_dir_url(__FILE__));

// Ensure EAO refund compatibility is registered early for admin-ajax requests
add_action('admin_init', function () {
    if (is_admin() && class_exists('\HP_RW\Admin\EAORefundCompat')) {
        (new \HP_RW\Admin\EAORefundCompat())->register();
    }
}, 1);

// Also register on wp_ajax hooks for cases where admin_init doesn't fire (direct AJAX)
add_action('wp_loaded', function () {
    if (defined('DOING_AJAX') && DOING_AJAX && is_admin()) {
        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
        if ($action === 'eao_payment_get_refund_data' || $action === 'eao_payment_process_refund') {
            if (class_exists('\HP_RW\Admin\EAORefundCompat')) {
                (new \HP_RW\Admin\EAORefundCompat())->register();
            }
        }
    }
}, 1);

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'HP_RW\\';
    $base_dir = HP_RW_PATH . 'includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize Plugin
add_action('plugins_loaded', function () {
    if (class_exists('HP_RW\\Plugin')) {
        \HP_RW\Plugin::init();
    }
});

// Ensure default options are created on activation.
register_activation_hook(__FILE__, function () {
    if (class_exists('HP_RW\\Plugin')) {
        \HP_RW\Plugin::activate();
    }
});

// Add "Settings" link on the Plugins screen.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $settings_url = admin_url('options-general.php?page=hp-react-widgets');
    $links[]      = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'hp-react-widgets') . '</a>';
    return $links;
});
