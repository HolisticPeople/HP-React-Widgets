<?php
require_once 'wp-load.php';
$sku = 'PRLabS-02355';
$details = \HP_RW\Services\ProductCatalogService::getProductDetails($sku);
echo "Details for $sku:\n";
print_r($details);

$o = [
    'id' => 'test-bundle',
    'type' => 'fixed_bundle',
    'discount_type' => 'percent',
    'discount_value' => 23,
    'bundle_items' => [['sku' => $sku, 'qty' => 1]]
];

$discountType = $o['discount_type'] ?? 'none';
$discountValue = (float) ($o['discount_value'] ?? 0);
$price = $details['price'];
$salePrice = $price;

if ($discountType === 'percent' && $discountValue > 0) {
    $salePrice = $price * (1 - ($discountValue / 100));
}

echo "\nCalculation check:\n";
echo "Original price: $price\n";
echo "Discount Type: $discountType\n";
echo "Discount Value: $discountValue\n";
echo "Sale Price: $salePrice\n";
echo "Calculated Sale Price (rounded): " . round($salePrice, 2) . "\n";





















