<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.70
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.70');
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

// Diagnostic tool to see actual data structure
add_action('init', function () {
    // Diagnostic: show raw data for user 8375
    if (isset($_GET['hp_diagnose_addresses']) && current_user_can('manage_options')) {
        global $wpdb;
        
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8375;
        
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'thwma_custom_address' LIMIT 1",
            $user_id
        ));
        
        echo "<h2>Raw meta_value for user $user_id:</h2>";
        echo "<pre>" . htmlspecialchars($meta_value) . "</pre>";
        
        echo "<h2>Unserialized:</h2>";
        $unserialized = maybe_unserialize($meta_value);
        echo "<pre>" . htmlspecialchars(print_r($unserialized, true)) . "</pre>";
        
        // Check for arrays in values
        echo "<h2>Checking for array values:</h2>";
        if (is_array($unserialized)) {
            foreach (['billing', 'shipping'] as $type) {
                if (isset($unserialized[$type]) && is_array($unserialized[$type])) {
                    foreach ($unserialized[$type] as $key => $addr) {
                        echo "<h3>$type / $key:</h3>";
                        if (is_array($addr)) {
                            foreach ($addr as $field => $value) {
                                $type_str = gettype($value);
                                if (is_array($value)) {
                                    echo "<p style='color:red'><strong>ARRAY FOUND:</strong> $field = " . htmlspecialchars(print_r($value, true)) . "</p>";
                                } else {
                                    echo "<p>$field ($type_str) = " . htmlspecialchars((string)$value) . "</p>";
                                }
                            }
                        }
                    }
                }
            }
        }
        
        wp_die();
    }
    
    // Repair tool
    if (isset($_GET['hp_repair_addresses']) && current_user_can('manage_options')) {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'thwma_custom_address'"
        );
        
        $repaired = 0;
        $details = [];
        
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
                
                $new_addresses = [];
                $seen_hashes = [];
                
                foreach ($raw_value[$type] as $key => $addr_data) {
                    if (!is_array($addr_data)) {
                        continue;
                    }
                    
                    // Fix 1: Convert numeric keys to proper string keys
                    if (is_numeric($key)) {
                        $new_key = 'addr_' . time() . '_' . wp_rand(1000, 9999);
                        $details[] = "User {$row->user_id}: $type key '$key' → '$new_key'";
                        $needs_repair = true;
                        // Small delay to ensure unique timestamps
                        usleep(1000);
                    } else {
                        $new_key = $key;
                    }
                    
                    // Fix 2: Sanitize array/object values to strings
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
                            $addr_data[$field] = $string_value;
                            $needs_repair = true;
                            $details[] = "User {$row->user_id}: $type/$new_key/$field was array";
                        }
                    }
                    
                    // Fix 3: Remove duplicates by hashing address content
                    $hash = md5(serialize($addr_data));
                    if (isset($seen_hashes[$hash])) {
                        $details[] = "User {$row->user_id}: $type removed duplicate '$new_key'";
                        $needs_repair = true;
                        continue; // Skip duplicate
                    }
                    $seen_hashes[$hash] = true;
                    
                    $new_addresses[$new_key] = $addr_data;
                }
                
                $raw_value[$type] = $new_addresses;
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
        
        delete_option('hp_rw_thwma_repair_time');
        
        $detail_html = $details ? "<br><br>Details:<br>" . implode("<br>", $details) : "";
        wp_die("HP React Widgets: Repaired $repaired user address records. $detail_html <br><br><a href='" . remove_query_arg('hp_repair_addresses') . "'>Go back</a>");
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
