<?php
/**
 * Plugin Name:       HP React Widgets
 * Description:       Container plugin for React-based widgets (Side Cart, Multi-Address, etc.) integrated via Shortcodes.
 * Version:           0.0.76
 * Author:            Holistic People
 * Text Domain:       hp-react-widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HP_RW_VERSION', '0.0.76');
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
    // Diagnostic: show raw data for user
    if (isset($_GET['hp_diagnose_addresses']) && current_user_can('manage_options')) {
        global $wpdb;
        
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8375;
        
        echo "<h1>Full Address Diagnostic for User $user_id</h1>";
        
        // 1. Check ALL user meta for this user that contains arrays
        echo "<h2>1. All WooCommerce Address Meta Fields:</h2>";
        $wc_meta_keys = [
            'billing_first_name', 'billing_last_name', 'billing_company',
            'billing_address_1', 'billing_address_2', 'billing_city',
            'billing_state', 'billing_postcode', 'billing_country',
            'billing_phone', 'billing_email',
            'shipping_first_name', 'shipping_last_name', 'shipping_company',
            'shipping_address_1', 'shipping_address_2', 'shipping_city',
            'shipping_state', 'shipping_postcode', 'shipping_country',
            'shipping_phone'
        ];
        
        foreach ($wc_meta_keys as $meta_key) {
            $value = get_user_meta($user_id, $meta_key, true);
            $type_str = gettype($value);
            if (is_array($value)) {
                echo "<p style='color:red; font-weight:bold'>⚠️ ARRAY in $meta_key: " . htmlspecialchars(print_r($value, true)) . "</p>";
            } else {
                echo "<p>$meta_key ($type_str) = " . htmlspecialchars((string)$value) . "</p>";
            }
        }
        
        // 2. Check thwma_custom_address
        echo "<h2>2. ThemeHigh Custom Addresses (thwma_custom_address):</h2>";
        $meta_value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'thwma_custom_address' LIMIT 1",
            $user_id
        ));
        
        echo "<h3>Raw:</h3>";
        echo "<pre style='background:#f0f0f0; padding:10px; overflow:auto; max-height:200px;'>" . htmlspecialchars($meta_value) . "</pre>";
        
        $unserialized = maybe_unserialize($meta_value);
        
        if (is_array($unserialized)) {
            foreach (['billing', 'shipping'] as $type) {
                if (isset($unserialized[$type]) && is_array($unserialized[$type])) {
                    echo "<h3>$type addresses:</h3>";
                    foreach ($unserialized[$type] as $key => $addr) {
                        $key_type = is_numeric($key) ? "<span style='color:red'>NUMERIC KEY!</span>" : "string key";
                        echo "<h4>Key: '$key' ($key_type)</h4>";
                        if (is_array($addr)) {
                            foreach ($addr as $field => $value) {
                                $type_str = gettype($value);
                                if (is_array($value)) {
                                    echo "<p style='color:red; font-weight:bold'>⚠️ ARRAY: $field = " . htmlspecialchars(print_r($value, true)) . "</p>";
                                } else {
                                    echo "<p>$field ($type_str) = " . htmlspecialchars((string)$value) . "</p>";
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // 3. Check other ThemeHigh meta
        echo "<h2>3. Other ThemeHigh Meta:</h2>";
        $th_meta = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE 'thwma%'",
            $user_id
        ));
        foreach ($th_meta as $row) {
            if ($row->meta_key === 'thwma_custom_address') continue;
            $value = maybe_unserialize($row->meta_value);
            echo "<p><strong>{$row->meta_key}:</strong> ";
            if (is_array($value)) {
                echo "<pre>" . htmlspecialchars(print_r($value, true)) . "</pre>";
            } else {
                echo htmlspecialchars((string)$value);
            }
            echo "</p>";
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
            
            // Fix 4: Clean up invalid default_billing/default_shipping references
            foreach (['default_billing' => 'billing', 'default_shipping' => 'shipping'] as $default_key => $type) {
                if (isset($raw_value[$default_key])) {
                    $default_value = $raw_value[$default_key];
                    // Check if the default points to a valid address key
                    if (!empty($default_value) && isset($raw_value[$type]) && is_array($raw_value[$type])) {
                        if (!isset($raw_value[$type][$default_value])) {
                            // Invalid reference - clear it or set to first valid key
                            $valid_keys = array_keys($raw_value[$type]);
                            if (!empty($valid_keys)) {
                                $raw_value[$default_key] = $valid_keys[0];
                                $details[] = "User {$row->user_id}: Fixed $default_key from '$default_value' to '{$valid_keys[0]}'";
                            } else {
                                unset($raw_value[$default_key]);
                                $details[] = "User {$row->user_id}: Removed invalid $default_key '$default_value'";
                            }
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
        
        delete_option('hp_rw_thwma_repair_time');
        
        // Clear all caches
        wp_cache_flush();
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('user_meta');
            wp_cache_flush_group('users');
        }
        
        $detail_html = $details ? "<br><br>Details:<br>" . implode("<br>", $details) : "";
        wp_die("HP React Widgets: Repaired $repaired user address records. Cache cleared. $detail_html <br><br><a href='" . remove_query_arg('hp_repair_addresses') . "'>Go back</a>");
    }
    
    // Force clear cache for a user
    if (isset($_GET['hp_clear_user_cache']) && current_user_can('manage_options')) {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8375;
        
        // Clear WordPress object cache
        wp_cache_flush();
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_delete($user_id, 'users');
        clean_user_cache($user_id);
        
        // Clear transients
        delete_transient('hp_rw_address_repaired_' . $user_id);
        
        wp_die("Cache cleared for user $user_id. <a href='" . remove_query_arg(['hp_clear_user_cache', 'user_id']) . "'>Go back</a>");
    }
    
    // Fix invalid state for primary WooCommerce address
    if (isset($_GET['hp_fix_primary_state']) && current_user_can('manage_options')) {
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8375;
        $details = [];
        
        foreach (['billing', 'shipping'] as $type) {
            $country = get_user_meta($user_id, $type . '_country', true);
            $state = get_user_meta($user_id, $type . '_state', true);
            
            if (!empty($country) && !empty($state)) {
                // Check if country has states
                $valid_states = WC()->countries->get_states($country);
                
                if (empty($valid_states)) {
                    // Country has no states - clear the state
                    update_user_meta($user_id, $type . '_state', '');
                    $details[] = "Cleared $type state '$state' (country $country has no states)";
                } elseif (!isset($valid_states[$state])) {
                    // State is not valid for this country - clear it
                    update_user_meta($user_id, $type . '_state', '');
                    $details[] = "Cleared invalid $type state '$state' for country $country";
                }
            }
        }
        
        clean_user_cache($user_id);
        
        $result = $details ? implode("<br>", $details) : "No invalid states found.";
        wp_die("HP React Widgets: $result <br><br><a href='" . remove_query_arg(['hp_fix_primary_state', 'user_id']) . "'>Go back</a>");
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
