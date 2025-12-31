<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for building product kits from health protocols.
 * Calculates quantities, pricing, and generates offer configurations.
 */
class ProtocolKitBuilder
{
    /**
     * Build a kit from a protocol specification.
     *
     * @param array $protocol Protocol specification
     * @return array Kit configuration with pricing options
     */
    public static function buildKit(array $protocol): array
    {
        $protocolName = $protocol['protocol_name'] ?? 'Custom Protocol Kit';
        $durationDays = $protocol['duration_days'] ?? 30;
        $supplements = $protocol['supplements'] ?? [];
        $targetDiscount = $protocol['target_discount_percent'] ?? 20;
        $economicConstraints = $protocol['economic_constraints'] ?? [];

        if (empty($supplements)) {
            return [
                'error' => 'No supplements specified in protocol',
                'protocol_name' => $protocolName,
            ];
        }

        // Calculate product quantities for each supplement
        $products = [];
        $errors = [];
        $totalCost = 0;
        $totalRetail = 0;

        foreach ($supplements as $supplement) {
            $result = self::findAndCalculateProduct($supplement, $durationDays);
            
            if (isset($result['error'])) {
                $errors[] = $result['error'];
                continue;
            }

            $products[] = $result;
            $totalCost += $result['total_cost'];
            $totalRetail += $result['total_retail'];
        }

        if (empty($products)) {
            return [
                'error' => 'Could not find any matching products',
                'details' => $errors,
            ];
        }

        // Calculate economics
        $economics = [
            'total_cost' => round($totalCost, 2),
            'total_retail' => round($totalRetail, 2),
            'max_theoretical_profit' => round($totalRetail - $totalCost, 2),
        ];

        // Generate pricing options
        $offerOptions = self::generatePricingOptions(
            $totalRetail,
            $totalCost,
            $targetDiscount,
            $economicConstraints
        );

        // Build suggested offer structure
        $kitName = "{$durationDays}-Day {$protocolName}";

        return [
            'kit_name' => $kitName,
            'duration_days' => $durationDays,
            'products' => $products,
            'economics' => $economics,
            'offer_options' => $offerOptions,
            'decision_point' => self::buildPricingDecisionPoint($kitName, $offerOptions),
            'suggested_offer' => self::buildSuggestedOffer($kitName, $products, $offerOptions),
            'warnings' => $errors,
        ];
    }

    /**
     * Find product and calculate quantities for a supplement.
     *
     * @param array $supplement Supplement specification
     * @param int $durationDays Duration in days
     * @return array Product calculation or error
     */
    private static function findAndCalculateProduct(array $supplement, int $durationDays): array
    {
        $name = $supplement['name'] ?? '';
        $servingsPerDay = $supplement['servings_per_day'] ?? 1;
        $sku = $supplement['sku'] ?? null;

        // If SKU provided, use it directly
        if ($sku) {
            $calculation = ProductCatalogService::calculateSupply($sku, $durationDays, $servingsPerDay);
            
            if (!$calculation || isset($calculation['error'])) {
                return ['error' => "Product SKU '{$sku}' not found or missing serving info"];
            }

            $economics = ProductCatalogService::getProductEconomics($sku);
            $cost = $economics ? $economics['cost'] * $calculation['bottles_needed'] : 0;

            return [
                'sku' => $sku,
                'name' => $calculation['name'],
                'qty' => $calculation['bottles_needed'],
                'role' => 'must',
                'covers_days' => $calculation['covers_days'],
                'servings_per_day' => $servingsPerDay,
                'unit_price' => $calculation['price_per_bottle'],
                'total_retail' => $calculation['total_price'],
                'unit_cost' => $economics['cost'] ?? 0,
                'total_cost' => $cost,
            ];
        }

        // Search for product by name
        $products = ProductCatalogService::searchProducts([
            'search' => $name,
            'has_serving_info' => true,
            'limit' => 5,
        ]);

        if (empty($products)) {
            return ['error' => "No product found matching '{$name}'"];
        }

        // Use the first matching product (could be enhanced with better matching logic)
        $product = $products[0];
        $productSku = $product['sku'];

        $calculation = ProductCatalogService::calculateSupply($productSku, $durationDays, $servingsPerDay);
        
        if (!$calculation || isset($calculation['error'])) {
            return ['error' => "Product '{$name}' found but missing serving information"];
        }

        $economics = ProductCatalogService::getProductEconomics($productSku);
        $cost = $economics ? $economics['cost'] * $calculation['bottles_needed'] : 0;

        return [
            'sku' => $productSku,
            'name' => $calculation['name'],
            'qty' => $calculation['bottles_needed'],
            'role' => 'must',
            'covers_days' => $calculation['covers_days'],
            'servings_per_day' => $servingsPerDay,
            'unit_price' => $calculation['price_per_bottle'],
            'total_retail' => $calculation['total_price'],
            'unit_cost' => $economics['cost'] ?? 0,
            'total_cost' => $cost,
            'matched_from_search' => $name,
        ];
    }

    /**
     * Generate pricing options at different discount levels.
     *
     * @param float $totalRetail Total retail price
     * @param float $totalCost Total COGS
     * @param float $targetDiscount Target discount percentage
     * @param array $constraints Economic constraints
     * @return array Pricing options
     */
    private static function generatePricingOptions(
        float $totalRetail,
        float $totalCost,
        float $targetDiscount,
        array $constraints
    ): array {
        $minProfitPercent = $constraints['min_profit_percent'] ?? 10;
        $minProfitDollars = $constraints['min_profit_dollars'] ?? 50;

        // Generate options at different discount levels
        $discountLevels = [
            ['id' => 'aggressive', 'discount' => $targetDiscount + 5, 'label' => 'Aggressive'],
            ['id' => 'balanced', 'discount' => $targetDiscount, 'label' => 'Balanced', 'recommended' => true],
            ['id' => 'conservative', 'discount' => $targetDiscount - 5, 'label' => 'Conservative'],
            ['id' => 'minimum_viable', 'discount' => $targetDiscount + 10, 'label' => 'Maximum Discount'],
        ];

        $options = [];

        foreach ($discountLevels as $level) {
            $discountPercent = max(0, min(50, $level['discount'])); // Cap at 50%
            $price = $totalRetail * (1 - $discountPercent / 100);
            $price = self::roundPrice($price);
            
            $profit = $price - $totalCost;
            $profitMargin = $price > 0 ? ($profit / $price) * 100 : 0;

            // Check if meets guidelines
            $meetsPercentGuideline = $profitMargin >= $minProfitPercent;
            $meetsDollarsGuideline = $profit >= $minProfitDollars;
            $meetsGuidelines = $meetsPercentGuideline && $meetsDollarsGuideline;

            $option = [
                'id' => $level['id'],
                'label' => $level['label'],
                'discount_percent' => $discountPercent,
                'price' => $price,
                'profit' => round($profit, 2),
                'profit_margin' => round($profitMargin, 1),
                'meets_guidelines' => $meetsGuidelines,
                'badge' => $discountPercent >= 10 ? "{$discountPercent}% OFF" : '',
                'shipping' => self::getShippingRecommendation($price, $profit),
            ];

            if (!empty($level['recommended'])) {
                $option['recommended'] = true;
                $option['recommendation_reason'] = 'Best balance of value proposition and margin';
            }

            if (!$meetsGuidelines) {
                $option['warning'] = $meetsPercentGuideline 
                    ? "Profit \${$profit} below minimum \${$minProfitDollars}"
                    : "Margin {$profitMargin}% below minimum {$minProfitPercent}%";
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * Get shipping recommendation based on price and profit.
     *
     * @param float $price Offer price
     * @param float $profit Profit amount
     * @return array Shipping recommendation
     */
    private static function getShippingRecommendation(float $price, float $profit): array
    {
        $domesticFreeThreshold = EconomicsService::getGuidelines()['shipping']['domestic']['free_shipping_threshold'] ?? 100;
        
        return [
            'domestic' => $price >= $domesticFreeThreshold ? 'FREE' : 'Standard rates',
            'international' => EconomicsService::getInternationalSubsidyRecommendation($profit),
        ];
    }

    /**
     * Build the pricing decision point for AI interaction.
     *
     * @param string $kitName Kit name
     * @param array $options Pricing options
     * @return array Decision point structure
     */
    private static function buildPricingDecisionPoint(string $kitName, array $options): array
    {
        $validOptions = array_filter($options, fn($opt) => $opt['meets_guidelines']);
        $allMeetGuidelines = count($validOptions) === count($options);

        return [
            'decision_point' => 'pricing_strategy',
            'question' => "Which pricing strategy for the {$kitName}?",
            'context' => $allMeetGuidelines 
                ? 'All options meet minimum guidelines. Higher discounts may drive more conversions.'
                : 'Some options do not meet profit guidelines.',
            'options' => $options,
            'recommendation' => 'balanced',
            'recommendation_reason' => 'Best balance of customer value and profit margin',
        ];
    }

    /**
     * Build a suggested offer configuration.
     *
     * @param string $kitName Kit name
     * @param array $products Products in kit
     * @param array $options Pricing options
     * @return array Offer configuration
     */
    private static function buildSuggestedOffer(string $kitName, array $products, array $options): array
    {
        // Find the balanced option
        $balancedOption = null;
        foreach ($options as $option) {
            if ($option['id'] === 'balanced') {
                $balancedOption = $option;
                break;
            }
        }

        if (!$balancedOption) {
            $balancedOption = $options[0] ?? [];
        }

        $bundleItems = [];
        foreach ($products as $product) {
            $bundleItems[] = [
                'sku' => $product['sku'],
                'qty' => $product['qty'],
            ];
        }

        return [
            'type' => 'fixed_bundle',
            'name' => $kitName,
            'badge' => $balancedOption['badge'] ?? '',
            'is_featured' => true,
            'discount_label' => "Save {$balancedOption['discount_percent']}%",
            'discount_type' => 'percent',
            'discount_value' => $balancedOption['discount_percent'],
            'bundle_items' => $bundleItems,
            'suggested_price' => $balancedOption['price'],
            'expected_profit' => $balancedOption['profit'],
        ];
    }

    /**
     * Round price to .99 or .00 pattern.
     *
     * @param float $price Price to round
     * @return float Rounded price
     */
    private static function roundPrice(float $price): float
    {
        // Round to nearest dollar, then subtract 0.01 for .99 pricing
        $rounded = round($price);
        return $rounded - 0.01;
    }
}

















