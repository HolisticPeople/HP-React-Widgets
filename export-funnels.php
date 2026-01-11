<?php
/**
 * Export funnels to JSON files using FunnelExporter service.
 * Run this file via WP-CLI: wp eval-file export-funnels.php
 */

if (!defined('ABSPATH')) {
    $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die("Cannot find wp-load.php\n");
    }
}

if (!class_exists('\HP_RW\Services\FunnelExporter')) {
    die("HP-React-Widgets FunnelExporter not available\n");
}

$savePath = plugin_dir_path(__FILE__) . 'funnels';
if (!is_dir($savePath)) {
    mkdir($savePath, 0755, true);
}

// Export these specific funnels
$slugsToExport = ['illumodine', 'liver-detox-protocol'];

$exported = 0;
foreach ($slugsToExport as $slug) {
    echo "Exporting funnel: $slug\n";
    
    $data = \HP_RW\Services\FunnelExporter::exportBySlug($slug);
    
    if (!$data) {
        echo "  ERROR: Funnel not found\n";
        continue;
    }
    
    $filename = $savePath . '/' . $slug . '.json';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    file_put_contents($filename, $json);
    $exported++;
    echo "  SUCCESS: Exported to $filename\n";
}

echo "\nTotal exported: $exported funnels\n";



















