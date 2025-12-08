<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.68
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.68');
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
 * Immediately repair corrupted ThemeHigh address data for ALL users.
 * This runs once per day and fixes the trim() array error.
 */
add_action('plugins_loaded', function () {
    // Only run repair once per day
    $last_repair = get_option('hp_rw_thwma_repair_time', 0);
    if (time() - $last_repair < 86400) {
        return;
    }
    
    global $wpdb;
    
    // Get ALL users with thwma_custom_address meta
    $results = $wpdb->get_results(
        "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'thwma_custom_address'"
    );
    
    if (empty($results)) {
        update_option('hp_rw_thwma_repair_time', time());
        return;
    }
    
    foreach ($results as $row) {
        $raw_value = maybe_unserialize($row->meta_value);
        
        if (!is_array($raw_value)) {
            continue;
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
                        // Convert array to string
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
        
        // Update if needed
        if ($needs_repair) {
            $wpdb->update(
                $wpdb->usermeta,
                ['meta_value' => maybe_serialize($raw_value)],
                ['user_id' => $row->user_id, 'meta_key' => 'thwma_custom_address'],
                ['%s'],
                ['%d', '%s']
            );
        }
    }
    
    update_option('hp_rw_thwma_repair_time', time());
}, 0);

// Also add an immediate one-time repair that can be triggered via URL param
add_action('init', function () {
    if (isset($_GET['hp_repair_addresses']) && current_user_can('manage_options')) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'thwma_custom_address'"
        );
        
        $repaired = 0;
        
        foreach ($results as $row) {
            $raw_value = maybe_unserialize($row->meta_value);
            
            if (!is_array($raw_value)) {
                continue;
            }
            
            $needs_repair = false;
            
            foreach (['billing', 'shipping'] as $type) {
                if (!isset($raw_value[$type]) || !is_array($raw_value[$type])) {
                    continue;
                }
                
                foreach ($raw_value[$type] as $key => $addr_data) {
                    if (!is_array($addr_data)) {
                        continue;
                    }
                    
                    foreach ($addr_data as $field => $field_value) {
                        if (is_array($field_value) || is_object($field_value)) {
                            $string_value = '';
                            if (is_array($field_value)) {
                                foreach ($field_value as $v) {
                                    if (is_string($v) && !empty($v)) {
                                        $string_value = $v;
                                        break;
                                    }
                                }
                            }
                            $raw_value[$type][$key][$field] = $string_value;
                            $needs_repair = true;
                        }
                    }
                }
            }
            
            if ($needs_repair) {
                $wpdb->update(
                    $wpdb->usermeta,
                    ['meta_value' => maybe_serialize($raw_value)],
                    ['user_id' => $row->user_id, 'meta_key' => 'thwma_custom_address'],
                    ['%s'],
                    ['%d', '%s']
                );
                $repaired++;
            }
        }
        
        delete_option('hp_rw_thwma_repair_time'); // Reset so automatic repair runs again
        wp_die("HP React Widgets: Repaired $repaired user address records. <a href='" . remove_query_arg('hp_repair_addresses') . "'>Go back</a>");
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
