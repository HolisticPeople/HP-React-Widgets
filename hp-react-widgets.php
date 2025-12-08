<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.66
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.66');
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
 * This runs early on init to repair data before ThemeHigh reads it.
 */
add_action('init', function () {
    // Only run for logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        return;
    }
    
    // Check if we've already repaired this user's data in this session
    $repaired_key = 'hp_rw_address_repaired_' . $user_id;
    if (get_transient($repaired_key)) {
        return;
    }
    
    // Get the raw meta directly from database to avoid any filters
    global $wpdb;
    $meta_value = $wpdb->get_var($wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
        $user_id,
        'thwma_custom_address'
    ));
    
    if (empty($meta_value)) {
        set_transient($repaired_key, 1, HOUR_IN_SECONDS);
        return;
    }
    
    $raw_value = maybe_unserialize($meta_value);
    
    if (!is_array($raw_value)) {
        set_transient($repaired_key, 1, HOUR_IN_SECONDS);
        return;
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
        update_user_meta($user_id, 'thwma_custom_address', $raw_value);
    }
    
    // Mark as repaired for this session (1 hour)
    set_transient($repaired_key, 1, HOUR_IN_SECONDS);
}, 1); // Priority 1 to run very early

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
