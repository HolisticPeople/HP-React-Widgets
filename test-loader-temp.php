<?php
/**
 * Temporary test script for FunnelConfigLoader
 * Run via: wp eval-file test-loader-temp.php
 */

$slug = 'liver-detox-protocol';

echo "Testing FunnelConfigLoader::getBySlug('$slug')...\n";

try {
    $result = \HP_RW\Services\FunnelConfigLoader::getBySlug($slug);
    
    if ($result === null) {
        echo "Result: NULL (funnel not found)\n";
    } else {
        echo "Result type: " . gettype($result) . "\n";
        echo "Is array: " . (is_array($result) ? 'yes' : 'no') . "\n";
        
        if (is_array($result)) {
            echo "Keys: " . implode(', ', array_keys($result)) . "\n";
            echo "Funnel name: " . ($result['name'] ?? 'N/A') . "\n";
            echo "Focus keyword: " . ($result['seo']['focus_keyword'] ?? 'N/A') . "\n";
        }
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

