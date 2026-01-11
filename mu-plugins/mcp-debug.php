<?php
/**
 * MCP early debug logger (temporary).
 *
 * Logs raw body and JSON decode errors for WooCommerce MCP REST requests
 * before WordPress parses the request. Remove after investigation.
 */

if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/woocommerce/mcp') === false) {
        return;
    }

    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody, true);
    $jsonError = json_last_error();
    $jsonErrorMsg = json_last_error_msg();

    // Try to capture headers safely
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $headers = array_change_key_case($headers ?: []);
    unset($headers['x-mcp-api-key'], $headers['authorization']);

    error_log('[MCP-DEBUG-EARLY] uri=' . $uri .
        ' method=' . ($_SERVER['REQUEST_METHOD'] ?? '') .
        ' json_error=' . $jsonError . ' (' . $jsonErrorMsg . ')' .
        ' headers=' . json_encode($headers) .
        ' body=' . $rawBody .
        ' decoded=' . json_encode($decoded));
}, 0);



/**
 * MCP early debug logger (temporary).
 *
 * Logs raw body and JSON decode errors for WooCommerce MCP REST requests
 * before WordPress parses the request. Remove after investigation.
 */

if (!defined('ABSPATH')) { exit; }

add_action('rest_api_init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/woocommerce/mcp') === false) {
        return;
    }

    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody, true);
    $jsonError = json_last_error();
    $jsonErrorMsg = json_last_error_msg();

    // Try to capture headers safely
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $headers = array_change_key_case($headers ?: []);
    unset($headers['x-mcp-api-key'], $headers['authorization']);

    error_log('[MCP-DEBUG-EARLY] uri=' . $uri .
        ' method=' . ($_SERVER['REQUEST_METHOD'] ?? '') .
        ' json_error=' . $jsonError . ' (' . $jsonErrorMsg . ')' .
        ' headers=' . json_encode($headers) .
        ' body=' . $rawBody .
        ' decoded=' . json_encode($decoded));
}, 0);








