<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.67
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.67');
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

/**
 * Sanitize ThemeHigh address data to fix corrupted entries (arrays instead of strings).
 * This runs on plugins_loaded to repair data before ThemeHigh reads it.
 */
add_action('plugins_loaded', function () {
    // Hook into user meta retrieval to sanitize on-the-fly
    add_filter('get_user_metadata', 'hp_rw_sanitize_thwma_meta', 1, 4);
}, 0);

/**
 * Filter to sanitize thwma_custom_address meta before it's returned.
 */
function hp_rw_sanitize_thwma_meta($value, $object_id, $meta_key, $single) {
    // Only intercept thwma_custom_address meta
    if ($meta_key !== 'thwma_custom_address') {
        return $value;
    }
    
    // Prevent infinite recursion
    static $is_sanitizing = [];
    if (isset($is_sanitizing[$object_id])) {
        return $value;
    }
    $is_sanitizing[$object_id] = true;
    
    // Get raw value from database directly
    global $wpdb;
    $meta_row = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
        $object_id,
        'thwma_custom_address'
    ));
    
    unset($is_sanitizing[$object_id]);
    
    if (empty($meta_row)) {
        return $value;
    }
    
    $raw_value = maybe_unserialize($meta_row);
    
    if (!is_array($raw_value)) {
        return $value;
    }
    
    $needs_repair = false;
    
    // Sanitize all address fields
    foreach (['billing', 'shipping'] as $type) {
        if (!isset($raw_value[$type]) || !is_array($raw_value[$type])) {
            continue;
        }
        
        foreach ($raw_value[$type] as $key => $addr_data) {
            if (!is_array($addr_data)) {
                continue;
            }
            
            foreach ($addr_data as $field => $field_value) {
                if (is_array($field_value)) {
                    // Convert array to string - take first non-empty string value
                    $string_value = '';
                    foreach ($field_value as $v) {
                        if (is_string($v) && !empty($v)) {
                            $string_value = $v;
                            break;
                        }
                    }
                    $raw_value[$type][$key][$field] = $string_value;
                    $needs_repair = true;
                } elseif (is_object($field_value)) {
                    $raw_value[$type][$key][$field] = '';
                    $needs_repair = true;
                }
            }
        }
    }
    
    // Auto-repair the database if we found corrupted data
    if ($needs_repair) {
        $wpdb->update(
            $wpdb->usermeta,
            ['meta_value' => maybe_serialize($raw_value)],
            ['user_id' => $object_id, 'meta_key' => 'thwma_custom_address'],
            ['%s'],
            ['%d', '%s']
        );
    }
    
    // Return sanitized value in the format WordPress expects
    // For get_user_meta with $single=true, return array with single value
    // For $single=false, return array of arrays
    if ($single) {
        return [$raw_value];
    }
    return [[$raw_value]];
}

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
