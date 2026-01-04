<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * 2.24.26
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '2.24.26');
define('HP_RW_FILE', __FILE__);
define('HP_RW_PATH', plugin_dir_path(__FILE__));
define('HP_RW_URL', plugin_dir_url(__FILE__));

// Simple Autoloader
spl_autoload_register(function ($class) {
    // #region agent log
    $log = ['sessionId' => 'debug-site-crash', 'runId' => 'initial', 'hypothesisId' => 'A', 'location' => 'hp-react-widgets.php:21', 'message' => 'Autoloader check', 'data' => ['class' => $class], 'timestamp' => microtime(true)*1000];
    file_put_contents('c:\DEV\WC Plugins\My Plugins\HP-React-Widgets\.cursor\debug.log', json_encode($log) . PHP_EOL, FILE_APPEND);
    // #endregion
    $prefix = 'HP_RW\\';
    $base_dir = HP_RW_PATH . 'includes/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    // #region agent log
    $log = ['sessionId' => 'debug-site-crash', 'runId' => 'initial', 'hypothesisId' => 'B', 'location' => 'hp-react-widgets.php:33', 'message' => 'Autoloader attempting file', 'data' => ['file' => $file, 'exists' => file_exists($file)], 'timestamp' => microtime(true)*1000];
    file_put_contents('c:\DEV\WC Plugins\My Plugins\HP-React-Widgets\.cursor\debug.log', json_encode($log) . PHP_EOL, FILE_APPEND);
    // #endregion
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize Plugin
add_action('plugins_loaded', function () {
    // #region agent log
    $log = ['sessionId' => 'debug-site-crash', 'runId' => 'initial', 'hypothesisId' => 'C', 'location' => 'hp-react-widgets.php:44', 'message' => 'plugins_loaded hook triggered', 'data' => [], 'timestamp' => microtime(true)*1000];
    file_put_contents('c:\DEV\WC Plugins\My Plugins\HP-React-Widgets\.cursor\debug.log', json_encode($log) . PHP_EOL, FILE_APPEND);
    // #endregion
    if (class_exists('HP_RW\\Plugin')) {
        // #region agent log
        $log = ['sessionId' => 'debug-site-crash', 'runId' => 'initial', 'hypothesisId' => 'C', 'location' => 'hp-react-widgets.php:49', 'message' => 'HP_RW\\Plugin exists, calling init', 'data' => [], 'timestamp' => microtime(true)*1000];
        file_put_contents('c:\DEV\WC Plugins\My Plugins\HP-React-Widgets\.cursor\debug.log', json_encode($log) . PHP_EOL, FILE_APPEND);
        // #endregion
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
