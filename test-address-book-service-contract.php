<?php
declare(strict_types=1);

$root = __DIR__;
$api = file_get_contents($root . '/includes/AddressApi.php');
$picker = file_get_contents($root . '/includes/Shortcodes/AddressCardPickerShortcode.php');
if (strpos($api, "get_service('address')") === false || strpos($api, 'delete_address') === false || strpos($api, 'save_address') === false) {
    throw new RuntimeException('Widget address mutations must prefer HP Core AddressService.');
}
if (strpos($picker, 'get_hydrated_addresses($user_id, $type)') === false) {
    throw new RuntimeException('Address picker must prefer HP Core hydrated addresses.');
}
echo "HP React Widgets address-book consumer contract passed\n";
