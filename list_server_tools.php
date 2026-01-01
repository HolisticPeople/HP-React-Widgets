<?php
if (!class_exists('WP\MCP\Core\McpAdapter')) {
    die("McpAdapter class not found\n");
}
$adapter = WP\MCP\Core\McpAdapter::instance();
$server = $adapter->get_server('woocommerce-mcp');
if (!$server) {
    die("Server woocommerce-mcp not found\n");
}
$tools = $server->get_tools();
echo "Tools in woocommerce-mcp (" . count($tools) . "):\n";
echo json_encode(array_keys($tools), JSON_PRETTY_PRINT) . "\n";

