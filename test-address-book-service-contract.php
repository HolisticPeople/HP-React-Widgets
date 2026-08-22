<?php
declare(strict_types=1);

$root = __DIR__;
$api = file_get_contents($root . '/includes/AddressApi.php');
$picker = file_get_contents($root . '/includes/Shortcodes/AddressCardPickerShortcode.php');
$funnel = file_get_contents($root . '/includes/Shortcodes/FunnelCheckoutAppShortcode.php');
$runtime = $api . $picker . $funnel;

if (strpos($api, "get_service('address')") === false || strpos($api, 'delete_address') === false || strpos($api, 'save_address') === false) {
    throw new RuntimeException('Widget address mutations must use HP Core AddressService.');
}
if (strpos($picker, 'get_hydrated_addresses($user_id, $type)') === false) {
    throw new RuntimeException('Address picker must use HP Core hydrated addresses.');
}
foreach (['thwma_custom_address', 'HP_MA_ADDRESS_KEY', 'get_address_meta_key'] as $forbidden) {
    if (strpos($runtime, $forbidden) !== false) {
        throw new RuntimeException("Runtime address consumer contains forbidden storage dependency: {$forbidden}");
    }
}
if (strpos($api, 'hp_rw_address_service_unavailable') === false || strpos($picker, 'native WooCommerce address only') === false) {
    throw new RuntimeException('HP Core absence must fail softly to native Woo reads and bounded mutation errors.');
}
if (strpos($api, "'th_' . \$type . '_' . \$new_key") === false || strpos($picker, 'get_default_address_id') === false) {
    throw new RuntimeException('Stable address IDs and selection behavior must be preserved.');
}
echo "HP React Widgets address-book consumer contract passed\n";
