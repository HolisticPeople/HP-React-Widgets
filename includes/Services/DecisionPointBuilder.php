<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds structured decision point responses for AI agent interactions.
 * 
 * Decision points are used when the AI needs to present choices to the admin,
 * allowing for interactive funnel building rather than autonomous decisions.
 */
class DecisionPointBuilder
{
    /**
     * Decision point types.
     */
    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTI_CHOICE = 'multiple_choice';
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_INPUT = 'input';
    public const TYPE_RANGE = 'range';
    public const TYPE_REVIEW = 'review';

    /**
     * Build a single choice decision point.
     */
    public static function singleChoice(
        string $id,
        string $title,
        string $description,
        array $options,
        ?string $recommendation = null,
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_SINGLE_CHOICE,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'options' => array_map(function($opt, $key) use ($recommendation) {
                $option = is_array($opt) ? $opt : ['label' => $opt, 'value' => $key];
                if ($recommendation !== null && ($option['value'] ?? $key) === $recommendation) {
                    $option['recommended'] = true;
                }
                return $option;
            }, $options, array_keys($options)),
            'recommendation' => $recommendation,
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    /**
     * Build a multiple choice decision point.
     */
    public static function multipleChoice(
        string $id,
        string $title,
        string $description,
        array $options,
        array $recommendations = [],
        array $constraints = [],
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_MULTI_CHOICE,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'options' => array_map(function($opt, $key) use ($recommendations) {
                $option = is_array($opt) ? $opt : ['label' => $opt, 'value' => $key];
                $value = $option['value'] ?? $key;
                if (in_array($value, $recommendations, true)) {
                    $option['recommended'] = true;
                }
                return $option;
            }, $options, array_keys($options)),
            'recommendations' => $recommendations,
            'constraints' => array_merge([
                'min_selections' => 1,
                'max_selections' => count($options),
            ], $constraints),
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    /**
     * Build a confirmation decision point.
     */
    public static function confirmation(
        string $id,
        string $title,
        string $description,
        array $details,
        bool $recommendApproval = true,
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_CONFIRMATION,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'details' => $details,
            'recommendation' => $recommendApproval ? 'approve' : 'reject',
            'actions' => [
                ['value' => 'approve', 'label' => 'Approve', 'recommended' => $recommendApproval],
                ['value' => 'reject', 'label' => 'Reject', 'recommended' => !$recommendApproval],
                ['value' => 'modify', 'label' => 'Request Modifications'],
            ],
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    /**
     * Build an input decision point.
     */
    public static function input(
        string $id,
        string $title,
        string $description,
        string $inputType = 'text',
        array $validation = [],
        ?string $suggestion = null,
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_INPUT,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'input_type' => $inputType,
            'validation' => $validation,
            'suggestion' => $suggestion,
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    /**
     * Build a range decision point.
     */
    public static function range(
        string $id,
        string $title,
        string $description,
        float $min,
        float $max,
        float $step = 1,
        ?float $suggestion = null,
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_RANGE,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'range' => [
                'min' => $min,
                'max' => $max,
                'step' => $step,
            ],
            'suggestion' => $suggestion,
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    /**
     * Build a review decision point for complex data review.
     */
    public static function review(
        string $id,
        string $title,
        string $description,
        array $sections,
        array $editableFields = [],
        array $context = []
    ): array {
        return [
            'decision_point' => true,
            'type' => self::TYPE_REVIEW,
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'sections' => $sections,
            'editable_fields' => $editableFields,
            'actions' => [
                ['value' => 'approve', 'label' => 'Approve All'],
                ['value' => 'edit', 'label' => 'Edit & Continue'],
                ['value' => 'regenerate', 'label' => 'Regenerate'],
            ],
            'context' => $context,
            'awaiting_response' => true,
        ];
    }

    // =====================================================
    // FUNNEL-SPECIFIC DECISION POINT BUILDERS
    // =====================================================

    /**
     * Build product selection decision point for kit building.
     */
    public static function productSelection(
        array $products,
        string $protocolName,
        int $supplyDays,
        array $context = []
    ): array {
        $options = [];
        $recommendations = [];

        foreach ($products as $product) {
            $options[] = [
                'value' => $product['sku'],
                'label' => $product['name'],
                'details' => [
                    'price' => '$' . number_format($product['price'], 2),
                    'quantity_needed' => $product['quantity_needed'],
                    'covers_days' => $product['covers_days'] ?? $supplyDays,
                ],
            ];
            if (!empty($product['recommended'])) {
                $recommendations[] = $product['sku'];
            }
        }

        return self::multipleChoice(
            'product_selection',
            sprintf('Select Products for %s', $protocolName),
            sprintf('Choose which products to include in the %d-day supply kit:', $supplyDays),
            $options,
            $recommendations,
            ['min_selections' => 1],
            array_merge($context, [
                'protocol_name' => $protocolName,
                'supply_days' => $supplyDays,
            ])
        );
    }

    /**
     * Build pricing strategy decision point.
     */
    public static function pricingStrategy(
        float $retailTotal,
        float $costTotal,
        array $strategies,
        array $context = []
    ): array {
        $options = [];
        $recommendation = null;
        $bestMargin = 0;

        foreach ($strategies as $key => $strategy) {
            $finalPrice = $strategy['price'];
            $profit = $finalPrice - $costTotal;
            $margin = ($profit / $finalPrice) * 100;
            
            $options[] = [
                'value' => $key,
                'label' => $strategy['name'],
                'details' => [
                    'retail_total' => '$' . number_format($retailTotal, 2),
                    'discount' => $strategy['discount_display'] ?? '-',
                    'final_price' => '$' . number_format($finalPrice, 2),
                    'profit' => '$' . number_format($profit, 2),
                    'margin' => round($margin, 1) . '%',
                ],
            ];

            if ($margin > $bestMargin && $margin >= 10) {
                $bestMargin = $margin;
                $recommendation = $key;
            }
        }

        return self::singleChoice(
            'pricing_strategy',
            'Select Pricing Strategy',
            'Choose the pricing approach for this offer:',
            $options,
            $recommendation,
            array_merge($context, [
                'retail_total' => $retailTotal,
                'cost_total' => $costTotal,
            ])
        );
    }

    /**
     * Build color palette decision point.
     */
    public static function colorPalette(
        array $palettes,
        ?string $inspiration = null,
        array $context = []
    ): array {
        $options = [];

        foreach ($palettes as $key => $palette) {
            $options[] = [
                'value' => $key,
                'label' => $palette['name'],
                'preview' => [
                    'accent_color' => $palette['accent_color'],
                    'background_color' => $palette['background_color'],
                    'text_color' => $palette['text_color'],
                    'button_color' => $palette['button_color'] ?? $palette['accent_color'],
                ],
                'description' => $palette['description'] ?? '',
            ];
        }

        return self::singleChoice(
            'color_palette',
            'Select Color Palette',
            $inspiration 
                ? sprintf('Based on "%s", I suggest these color palettes:', $inspiration)
                : 'Choose a color palette for the funnel:',
            $options,
            $palettes[0]['name'] ?? null,
            array_merge($context, [
                'inspiration' => $inspiration,
            ])
        );
    }

    /**
     * Build offer type decision point.
     */
    public static function offerType(array $context = []): array
    {
        return self::singleChoice(
            'offer_type',
            'Select Offer Type',
            'Choose the type of offer to create:',
            [
                [
                    'value' => 'single',
                    'label' => 'Single Product',
                    'description' => 'A single product with optional quantity discount.',
                ],
                [
                    'value' => 'fixed_bundle',
                    'label' => 'Fixed Bundle',
                    'description' => 'A pre-configured set of products at a bundled price.',
                ],
                [
                    'value' => 'customizable_kit',
                    'label' => 'Customizable Kit',
                    'description' => 'Customer picks products from a selection with constraints.',
                ],
            ],
            'fixed_bundle',
            $context
        );
    }

    /**
     * Build section content review decision point.
     */
    public static function sectionContentReview(
        string $sectionType,
        array $content,
        array $alternatives = [],
        array $context = []
    ): array {
        $sections = [
            [
                'title' => ucfirst($sectionType) . ' Content',
                'data' => $content,
            ],
        ];

        if (!empty($alternatives)) {
            $sections[] = [
                'title' => 'Alternative Options',
                'data' => $alternatives,
            ];
        }

        return self::review(
            'section_content_' . $sectionType,
            sprintf('Review %s Section', ucfirst($sectionType)),
            sprintf('Review the generated content for the %s section:', $sectionType),
            $sections,
            array_keys($content),
            array_merge($context, [
                'section_type' => $sectionType,
            ])
        );
    }

    /**
     * Build economics validation decision point.
     */
    public static function economicsValidation(
        array $economics,
        array $issues = [],
        array $suggestions = [],
        array $context = []
    ): array {
        $sections = [
            [
                'title' => 'Current Economics',
                'data' => [
                    'Retail Total' => '$' . number_format($economics['retail_total'] ?? 0, 2),
                    'Cost Total' => '$' . number_format($economics['cost_total'] ?? 0, 2),
                    'Offer Price' => '$' . number_format($economics['offer_price'] ?? 0, 2),
                    'Profit' => '$' . number_format($economics['profit'] ?? 0, 2),
                    'Margin' => round($economics['margin'] ?? 0, 1) . '%',
                ],
            ],
        ];

        if (!empty($issues)) {
            $sections[] = [
                'title' => 'Issues',
                'data' => $issues,
                'type' => 'warning',
            ];
        }

        if (!empty($suggestions)) {
            $sections[] = [
                'title' => 'Suggestions',
                'data' => $suggestions,
                'type' => 'info',
            ];
        }

        return self::confirmation(
            'economics_validation',
            'Economics Validation',
            'Review the economics for this offer:',
            $sections,
            empty($issues),
            $context
        );
    }

    /**
     * Build version backup decision point.
     */
    public static function versionBackup(
        string $funnelName,
        string $action,
        array $changes = [],
        array $context = []
    ): array {
        return self::confirmation(
            'version_backup',
            'Create Backup?',
            sprintf('Would you like to create a backup before %s the funnel "%s"?', $action, $funnelName),
            [
                [
                    'title' => 'Proposed Changes',
                    'data' => $changes,
                ],
            ],
            true,
            $context
        );
    }

    /**
     * Build supply duration decision point.
     */
    public static function supplyDuration(array $context = []): array
    {
        return self::singleChoice(
            'supply_duration',
            'Select Supply Duration',
            'How many days should this kit cover?',
            [
                ['value' => 30, 'label' => '30 Days (1 month)', 'description' => 'Trial size'],
                ['value' => 60, 'label' => '60 Days (2 months)', 'description' => 'Standard'],
                ['value' => 90, 'label' => '90 Days (3 months)', 'description' => 'Best value - recommended'],
                ['value' => 180, 'label' => '180 Days (6 months)', 'description' => 'Maximum savings'],
            ],
            90,
            $context
        );
    }

    /**
     * Build discount amount decision point.
     */
    public static function discountAmount(
        float $retailTotal,
        float $costTotal,
        array $context = []
    ): array {
        $breakeven = $retailTotal - $costTotal;
        $maxSafeDiscount = $breakeven * 0.9; // Keep 10% minimum profit
        $maxPercent = ($maxSafeDiscount / $retailTotal) * 100;

        return self::range(
            'discount_amount',
            'Set Discount Percentage',
            sprintf(
                'Set the discount for this offer (max safe discount: %d%% to maintain 10%% margin):',
                floor($maxPercent)
            ),
            0,
            min(50, floor($maxPercent)),
            5,
            min(20, floor($maxPercent * 0.5)),
            array_merge($context, [
                'retail_total' => $retailTotal,
                'cost_total' => $costTotal,
                'max_safe_discount' => floor($maxPercent),
            ])
        );
    }

    // =====================================================
    // RESPONSE HANDLING
    // =====================================================

    /**
     * Parse a decision point response.
     */
    public static function parseResponse(array $decisionPoint, $response): array
    {
        $type = $decisionPoint['type'] ?? '';

        switch ($type) {
            case self::TYPE_SINGLE_CHOICE:
                return [
                    'valid' => self::validateSingleChoice($decisionPoint, $response),
                    'value' => $response,
                    'decision_id' => $decisionPoint['id'],
                ];

            case self::TYPE_MULTI_CHOICE:
                $values = is_array($response) ? $response : [$response];
                return [
                    'valid' => self::validateMultiChoice($decisionPoint, $values),
                    'values' => $values,
                    'decision_id' => $decisionPoint['id'],
                ];

            case self::TYPE_CONFIRMATION:
                return [
                    'valid' => in_array($response, ['approve', 'reject', 'modify']),
                    'action' => $response,
                    'decision_id' => $decisionPoint['id'],
                ];

            case self::TYPE_INPUT:
                return [
                    'valid' => self::validateInput($decisionPoint, $response),
                    'value' => $response,
                    'decision_id' => $decisionPoint['id'],
                ];

            case self::TYPE_RANGE:
                $value = floatval($response);
                $range = $decisionPoint['range'] ?? [];
                return [
                    'valid' => $value >= ($range['min'] ?? 0) && $value <= ($range['max'] ?? 100),
                    'value' => $value,
                    'decision_id' => $decisionPoint['id'],
                ];

            case self::TYPE_REVIEW:
                return [
                    'valid' => in_array($response['action'] ?? '', ['approve', 'edit', 'regenerate']),
                    'action' => $response['action'] ?? '',
                    'edits' => $response['edits'] ?? [],
                    'decision_id' => $decisionPoint['id'],
                ];

            default:
                return [
                    'valid' => false,
                    'error' => 'Unknown decision point type',
                    'decision_id' => $decisionPoint['id'],
                ];
        }
    }

    /**
     * Validate single choice response.
     */
    private static function validateSingleChoice(array $decisionPoint, $response): bool
    {
        $validValues = array_map(function($opt) {
            return is_array($opt) ? ($opt['value'] ?? null) : $opt;
        }, $decisionPoint['options'] ?? []);

        return in_array($response, $validValues, true);
    }

    /**
     * Validate multiple choice response.
     */
    private static function validateMultiChoice(array $decisionPoint, array $responses): bool
    {
        $validValues = array_map(function($opt) {
            return is_array($opt) ? ($opt['value'] ?? null) : $opt;
        }, $decisionPoint['options'] ?? []);

        $constraints = $decisionPoint['constraints'] ?? [];
        $min = $constraints['min_selections'] ?? 1;
        $max = $constraints['max_selections'] ?? count($validValues);

        if (count($responses) < $min || count($responses) > $max) {
            return false;
        }

        foreach ($responses as $response) {
            if (!in_array($response, $validValues, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate input response.
     */
    private static function validateInput(array $decisionPoint, $response): bool
    {
        $validation = $decisionPoint['validation'] ?? [];

        if (!empty($validation['required']) && empty($response)) {
            return false;
        }

        if (!empty($validation['pattern']) && !preg_match($validation['pattern'], $response)) {
            return false;
        }

        if (!empty($validation['min_length']) && strlen($response) < $validation['min_length']) {
            return false;
        }

        if (!empty($validation['max_length']) && strlen($response) > $validation['max_length']) {
            return false;
        }

        return true;
    }
}

















