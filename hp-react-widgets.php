<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.4
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.4');
define('HP_RW_FILE', __FILE__);
define('HP_RW_PATH', plugin_dir_path(__FILE__));
define('HP_RW_URL', plugin_dir_url(__FILE__));

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
