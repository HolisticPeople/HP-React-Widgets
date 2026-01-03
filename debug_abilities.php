<?php
if (!function_exists('wp_get_abilities')) {
    echo "Abilities API NOT FOUND (function wp_get_abilities missing)\n";
} else {
    $abilities = wp_get_abilities();
    echo "Registered Abilities (" . count($abilities) . "):\n";
    echo json_encode(array_keys($abilities), JSON_PRETTY_PRINT) . "\n";
}

if (has_filter('woocommerce_mcp_get_abilities')) {
    echo "WooCommerce MCP Filter active.\n";
    $mcp_abilities = apply_filters('woocommerce_mcp_get_abilities', []);
    echo "MCP Filtered Abilities (" . count($mcp_abilities) . "):\n";
    echo json_encode(array_keys($mcp_abilities), JSON_PRETTY_PRINT) . "\n";
} else {
    echo "WooCommerce MCP Filter NOT FOUND.\n";
}















