<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for calculating profitability, validating offers against economic guidelines,
 * and recommending shipping subsidies.
 */
class EconomicsService
{
    /**
     * Option name for economic guidelines.
     */
    private const OPTION_GUIDELINES = 'hp_funnel_economics';

    /**
     * Default economic guidelines.
     */
    private const DEFAULT_GUIDELINES = [
        'profit_requirements' => [
            'min_profit_percent' => 10,
            'min_profit_dollars' => 50,
            'apply_rule' => 'higher', // higher, lower, percent_only, dollars_only
        ],
        'shipping' => [
            'domestic' => [
                'countries' => ['US'],
                'free_shipping_threshold' => 100,
                'base_cost' => 5.99,
                'per_item_cost' => 0.50,
                'weight_rate' => 0.15,
            ],
            'international' => [
                'base_cost' => 15.99,
                'per_item_cost' => 2.00,
                'weight_rate' => 0.35,
                'subsidy_tiers' => [
                    ['min_profit' => 100, 'subsidy_percent' => 50],
                    ['min_profit' => 75, 'subsidy_percent' => 35],
                    ['min_profit' => 50, 'subsidy_percent' => 20],
                    ['min_profit' => 0, 'subsidy_percent' => 0],
                ],
            ],
        ],
        'pricing_strategy' => [
            'round_to' => 0.99,
            'min_discount_display' => 10,
            'max_discount_percent' => 40,
        ],
        'payment_processing_rate' => 0.033, // 3.3% Stripe fee
    ];

    /**
     * Get economic guidelines from options.
     *
     * @return array Guidelines configuration
     */
    public static function getGuidelines(): array
    {
        $stored = get_option(self::OPTION_GUIDELINES, []);
        
        return array_replace_recursive(self::DEFAULT_GUIDELINES, $stored);
    }

    /**
     * Save economic guidelines.
     *
     * @param array $guidelines Guidelines to save
     * @return bool Success
     */
    public static function saveGuidelines(array $guidelines): bool
    {
        return update_option(self::OPTION_GUIDELINES, $guidelines);
    }

    /**
     * Calculate profitability for an offer.
     *
     * @param array $items Array of items with sku and qty
     * @param float $proposedPrice Proposed selling price
     * @param string $shippingScenario 'domestic' or 'international'
     * @return array Profitability calculation
     */
    public static function calculateOfferProfitability(
        array $items,
        float $proposedPrice,
        string $shippingScenario = 'domestic'
    ): array {
        $guidelines = self::getGuidelines();
        
        // Calculate totals from items
        $retailTotal = 0;
        $cogsTotal = 0;
        $totalWeight = 0;
        $itemCount = 0;

        foreach ($items as $item) {
            $sku = $item['sku'] ?? '';
            $qty = $item['qty'] ?? 1;
            
            $economics = ProductCatalogService::getProductEconomics($sku);
            $details = ProductCatalogService::getProductDetails($sku);
            
            if ($economics) {
                $retailTotal += $economics['price'] * $qty;
                $cogsTotal += $economics['cost'] * $qty;
                $totalWeight += ($details['weight_oz'] ?? 0) * $qty;
                $itemCount += $qty;
            }
        }

        // Calculate discount
        $discountPercent = $retailTotal > 0 
            ? (($retailTotal - $proposedPrice) / $retailTotal) * 100 
            : 0;

        // Calculate shipping cost (what we pay)
        $shippingCost = self::calculateShippingCost($shippingScenario, $itemCount, $totalWeight);
        
        // Calculate payment processing fee
        $processingRate = $guidelines['payment_processing_rate'];
        $processingFee = $proposedPrice * $processingRate;

        // Total cost
        $totalCost = $cogsTotal + $processingFee;
        
        // For domestic free shipping over threshold, we absorb shipping cost
        $weAbsorbShipping = 0;
        $customerPaysShipping = $shippingCost;
        
        if ($shippingScenario === 'domestic') {
            $threshold = $guidelines['shipping']['domestic']['free_shipping_threshold'];
            if ($proposedPrice >= $threshold) {
                $weAbsorbShipping = $shippingCost;
                $customerPaysShipping = 0;
                $totalCost += $shippingCost;
            }
        } else {
            // International: apply subsidy based on profit
            $grossProfit = $proposedPrice - $cogsTotal - $processingFee;
            $subsidyPercent = self::getSubsidyPercent($grossProfit);
            $weAbsorbShipping = $shippingCost * ($subsidyPercent / 100);
            $customerPaysShipping = $shippingCost - $weAbsorbShipping;
            $totalCost += $weAbsorbShipping;
        }

        // Calculate profit
        $grossProfit = $proposedPrice - $totalCost;
        $profitMarginPercent = $proposedPrice > 0 ? ($grossProfit / $proposedPrice) * 100 : 0;

        // Check guidelines
        $minPercent = $guidelines['profit_requirements']['min_profit_percent'];
        $minDollars = $guidelines['profit_requirements']['min_profit_dollars'];
        $applyRule = $guidelines['profit_requirements']['apply_rule'];

        $meetsPercent = $profitMarginPercent >= $minPercent;
        $meetsDollars = $grossProfit >= $minDollars;
        
        $passesAll = match ($applyRule) {
            'higher' => $meetsPercent && $meetsDollars,
            'lower' => $meetsPercent || $meetsDollars,
            'percent_only' => $meetsPercent,
            'dollars_only' => $meetsDollars,
            default => $meetsPercent && $meetsDollars,
        };

        return [
            'economics' => [
                'retail_total' => round($retailTotal, 2),
                'proposed_price' => round($proposedPrice, 2),
                'discount_percent' => round($discountPercent, 1),
                'costs' => [
                    'cogs_total' => round($cogsTotal, 2),
                    'estimated_shipping' => round($weAbsorbShipping, 2),
                    'payment_processing' => round($processingFee, 2),
                    'total_cost' => round($totalCost, 2),
                ],
                'profit' => [
                    'gross_profit' => round($grossProfit, 2),
                    'profit_margin_percent' => round($profitMarginPercent, 1),
                    'profit_margin_dollars' => round($grossProfit, 2),
                ],
                'guidelines_check' => [
                    'meets_minimum_percent' => $meetsPercent,
                    'meets_minimum_dollars' => $meetsDollars,
                    'passes_all' => $passesAll,
                ],
                'shipping_recommendation' => [
                    'domestic' => [
                        'customer_pays' => $shippingScenario === 'domestic' ? $customerPaysShipping : null,
                        'we_absorb' => $shippingScenario === 'domestic' ? $weAbsorbShipping : null,
                        'reason' => $proposedPrice >= ($guidelines['shipping']['domestic']['free_shipping_threshold'] ?? 100)
                            ? 'Order over threshold - free shipping'
                            : 'Below threshold - standard rates apply',
                    ],
                    'international' => self::getInternationalShippingBreakdown($grossProfit, $shippingCost),
                ],
            ],
            'valid' => $passesAll,
            'warnings' => self::generateWarnings($profitMarginPercent, $grossProfit, $discountPercent, $guidelines),
            'suggestions' => $passesAll ? [] : self::generateSuggestions($items, $cogsTotal, $processingRate, $guidelines),
        ];
    }

    /**
     * Validate an offer against economic guidelines.
     *
     * @param array $offer Offer configuration
     * @return array Validation result
     */
    public static function validateOffer(array $offer): array
    {
        // Extract items based on offer type
        $items = [];
        $type = $offer['type'] ?? 'single';
        
        switch ($type) {
            case 'single':
                $items[] = [
                    'sku' => $offer['product_sku'] ?? $offer['productSku'] ?? '',
                    'qty' => $offer['quantity'] ?? 1,
                ];
                break;
                
            case 'fixed_bundle':
                $items = $offer['bundle_items'] ?? $offer['bundleItems'] ?? [];
                break;
                
            case 'customizable_kit':
                // For kits, use the default configuration
                $kitProducts = $offer['kit_products'] ?? $offer['kitProducts'] ?? [];
                foreach ($kitProducts as $kitProduct) {
                    if (($kitProduct['role'] ?? 'optional') === 'must' || ($kitProduct['qty'] ?? $kitProduct['quantity'] ?? 0) > 0) {
                        $items[] = [
                            'sku' => $kitProduct['sku'],
                            'qty' => $kitProduct['qty'] ?? $kitProduct['quantity'] ?? 1,
                        ];
                    }
                }
                break;
        }

        if (empty($items)) {
            return [
                'valid' => false,
                'errors' => ['No products found in offer'],
            ];
        }

        // Calculate retail total for price derivation if not provided
        $retailTotal = 0;
        foreach ($items as $item) {
            $economics = ProductCatalogService::getProductEconomics($item['sku']);
            if ($economics) {
                $retailTotal += $economics['price'] * ($item['qty'] ?? 1);
            }
        }

        // Calculate proposed price from discount
        $discountType = $offer['discount_type'] ?? $offer['discountType'] ?? 'none';
        $discountValue = (float) ($offer['discount_value'] ?? $offer['discountValue'] ?? 0);
        
        $proposedPrice = $retailTotal;
        
        // Priority: 1) calculatedPrice (pre-enriched), 2) offerPrice/offer_price (explicit), 3) discount-based
        $calculatedPrice = $offer['calculatedPrice'] ?? null;
        $offerPrice = isset($offer['offer_price']) ? $offer['offer_price'] : ($offer['offerPrice'] ?? null);

        if ($calculatedPrice !== null && $calculatedPrice !== '') {
            $proposedPrice = (float) $calculatedPrice;
        } elseif ($offerPrice !== null && $offerPrice !== '') {
            $proposedPrice = (float) $offerPrice;
        } elseif ($discountType === 'percent' && $discountValue > 0) {
            $proposedPrice = $retailTotal * (1 - $discountValue / 100);
        } elseif ($discountType === 'fixed' && $discountValue > 0) {
            $proposedPrice = $retailTotal - $discountValue;
        }

        return self::calculateOfferProfitability($items, $proposedPrice);
    }

    /**
     * Calculate shipping cost.
     * Note: This is currently disabled as we use live Shipstation rates.
     *
     * @param string $scenario 'domestic' or 'international'
     * @param int $itemCount Number of items
     * @param float $weightOz Total weight in ounces
     * @return float Shipping cost
     */
    public static function calculateShippingCost(string $scenario, int $itemCount, float $weightOz): float
    {
        return 0.0;
    }

    /**
     * Get subsidy percentage based on profit.
     *
     * @param float $profit Gross profit
     * @return int Subsidy percentage
     */
    public static function getSubsidyPercent(float $profit): int
    {
        $guidelines = self::getGuidelines();
        $tiers = $guidelines['shipping']['international']['subsidy_tiers'] ?? [];

        foreach ($tiers as $tier) {
            if ($profit >= $tier['min_profit']) {
                return $tier['subsidy_percent'];
            }
        }

        return 0;
    }

    /**
     * Get international subsidy recommendation string.
     *
     * @param float $profit Gross profit
     * @return string Recommendation
     */
    public static function getInternationalSubsidyRecommendation(float $profit): string
    {
        $subsidyPercent = self::getSubsidyPercent($profit);
        
        if ($subsidyPercent === 0) {
            return 'No subsidy - customer pays full shipping';
        }
        
        return "{$subsidyPercent}% subsidized";
    }

    /**
     * Get detailed international shipping breakdown.
     *
     * @param float $profit Gross profit
     * @param float $shippingCost Shipping cost
     * @return array Breakdown
     */
    private static function getInternationalShippingBreakdown(float $profit, float $shippingCost): array
    {
        $subsidyPercent = self::getSubsidyPercent($profit);
        $weAbsorb = $shippingCost * ($subsidyPercent / 100);
        $customerPays = $shippingCost - $weAbsorb;

        return [
            'customer_pays' => round($customerPays, 2),
            'we_absorb' => round($weAbsorb, 2),
            'subsidy_percent' => $subsidyPercent,
            'reason' => $subsidyPercent > 0 
                ? "Profit \${$profit} qualifies for {$subsidyPercent}% subsidy"
                : "Profit \${$profit} does not qualify for subsidy",
        ];
    }

    /**
     * Generate warnings for the calculation.
     *
     * @param float $marginPercent Profit margin percent
     * @param float $profitDollars Profit in dollars
     * @param float $discountPercent Discount percent
     * @param array $guidelines Guidelines
     * @return array Warnings
     */
    private static function generateWarnings(
        float $marginPercent,
        float $profitDollars,
        float $discountPercent,
        array $guidelines
    ): array {
        $warnings = [];
        
        $minPercent = $guidelines['profit_requirements']['min_profit_percent'];
        $minDollars = $guidelines['profit_requirements']['min_profit_dollars'];
        $maxDiscount = $guidelines['pricing_strategy']['max_discount_percent'];

        if ($marginPercent < $minPercent) {
            $warnings[] = "Margin {$marginPercent}% is below minimum {$minPercent}%";
        }

        if ($profitDollars < $minDollars) {
            $warnings[] = "Profit \${$profitDollars} is below minimum \${$minDollars}";
        }

        if ($discountPercent > $maxDiscount) {
            $warnings[] = "Discount {$discountPercent}% exceeds maximum {$maxDiscount}%";
        }

        return $warnings;
    }

    /**
     * Generate suggestions to meet guidelines.
     *
     * @param array $items Items in offer
     * @param float $cogsTotal Total COGS
     * @param float $processingRate Payment processing rate
     * @param array $guidelines Guidelines
     * @return array Suggestions
     */
    private static function generateSuggestions(
        array $items,
        float $cogsTotal,
        float $processingRate,
        array $guidelines
    ): array {
        $suggestions = [];
        
        $minDollars = $guidelines['profit_requirements']['min_profit_dollars'];
        $minPercent = $guidelines['profit_requirements']['min_profit_percent'];

        // Calculate retail total
        $retailTotal = 0;
        foreach ($items as $item) {
            $details = ProductCatalogService::getProductDetails($item['sku']);
            if ($details) {
                $retailTotal += $details['price'] * ($item['qty'] ?? 1);
            }
        }

        // Suggest price to meet minimum dollars
        // profit = price - cogs - (price * rate)
        // profit = price * (1 - rate) - cogs
        // price = (profit + cogs) / (1 - rate)
        $priceForMinDollars = ($minDollars + $cogsTotal) / (1 - $processingRate);
        $discountForMinDollars = $retailTotal > 0 
            ? (($retailTotal - $priceForMinDollars) / $retailTotal) * 100 
            : 0;

        if ($discountForMinDollars >= 0 && $discountForMinDollars <= 40) {
            $suggestions[] = [
                'action' => 'increase_price',
                'recommended_price' => round($priceForMinDollars, 2),
                'new_discount_percent' => round($discountForMinDollars, 0),
                'new_profit_dollars' => round($minDollars, 2),
                'message' => "Increase price to \$" . round($priceForMinDollars, 2) . " (" . round($discountForMinDollars, 0) . "% off) to meet minimum \${$minDollars} profit",
            ];
        }

        // Suggest reducing discount
        if ($retailTotal > 0) {
            $suggestions[] = [
                'action' => 'reduce_discount',
                'message' => 'Consider reducing discount percentage to improve margin',
            ];
        }

        // Suggest adding higher-margin product
        $suggestions[] = [
            'action' => 'add_higher_margin_product',
            'message' => 'Consider adding a higher-margin product to the bundle',
        ];

        return $suggestions;
    }
}















