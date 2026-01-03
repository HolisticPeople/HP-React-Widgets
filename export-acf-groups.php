<?php
/**
 * Export ACF field groups to JSON files in the plugin's acf-json folder.
 * Run this file via WP-CLI: wp eval-file export-acf-groups.php
 */

if (!defined('ABSPATH')) {
    // Load WordPress if running directly
    $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die("Cannot find wp-load.php\n");
    }
}

if (!function_exists('acf_get_field_groups')) {
    die("ACF Pro is not available\n");
}

$savePath = plugin_dir_path(__FILE__) . 'acf-json';
if (!is_dir($savePath)) {
    echo "Creating acf-json folder...\n";
    mkdir($savePath, 0755, true);
}

$groups = acf_get_field_groups();
$exported = 0;

echo "Found " . count($groups) . " field groups\n\n";

foreach ($groups as $group) {
    // Get full group with fields
    $fullGroup = acf_get_field_group($group['key']);
    if (!$fullGroup) {
        echo "Skipping (no data): " . $group['title'] . "\n";
        continue;
    }
    
    $fullGroup['fields'] = acf_get_fields($group['key']);
    
    // Generate filename
    $filename = $savePath . '/' . $group['key'] . '.json';
    
    // Write JSON
    $json = json_encode($fullGroup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filename, $json);
    $exported++;
    echo "Exported: " . $group['title'] . " (" . $group['key'] . ")\n";
}

echo "\nTotal exported: $exported field groups to $savePath\n";















