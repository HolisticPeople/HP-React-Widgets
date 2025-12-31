<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for styling schema, color palettes, and theme presets.
 * 
 * Provides structured styling information for AI agents to generate
 * visually appealing and consistent funnel designs.
 */
class StylingSchema
{
    /**
     * Get the complete styling schema.
     *
     * @return array Styling schema definition
     */
    public static function getSchema(): array
    {
        return [
            'version' => '1.0',
            'css_custom_properties' => self::getCssPropertyDefinitions(),
            'color_palette_structure' => self::getColorPaletteStructure(),
            'theme_presets' => self::getThemePresets(),
            'generation_guidelines' => self::getGenerationGuidelines(),
        ];
    }

    /**
     * Get CSS custom property definitions.
     *
     * @return array CSS property definitions
     */
    public static function getCssPropertyDefinitions(): array
    {
        return [
            'colors' => [
                '--hp-accent-color' => [
                    'description' => 'Primary accent color for buttons, badges, and UI highlights',
                    'type' => 'color',
                    'maps_to' => 'styling.accent_color',
                    'usage' => ['Primary buttons', 'Active states', 'Badges', 'Progress indicators'],
                ],
                '--hp-text-color-basic' => [
                    'description' => 'Main text color, typically off-white for dark themes',
                    'type' => 'color',
                    'maps_to' => 'styling.text_color_basic',
                    'usage' => ['Body text', 'Headings', 'Labels'],
                ],
                '--hp-text-color-accent' => [
                    'description' => 'Accent text color for highlights and links',
                    'type' => 'color',
                    'maps_to' => 'styling.text_color_accent',
                    'usage' => ['Links', 'Highlighted text', 'Price emphasis'],
                ],
                '--hp-text-color-note' => [
                    'description' => 'Muted text color for descriptions and secondary content',
                    'type' => 'color',
                    'maps_to' => 'styling.text_color_note',
                    'usage' => ['Descriptions', 'Captions', 'Helper text'],
                ],
                '--hp-text-color-discount' => [
                    'description' => 'Color for discount and savings displays',
                    'type' => 'color',
                    'maps_to' => 'styling.text_color_discount',
                    'usage' => ['Discount badges', 'Savings text', 'Sale prices'],
                ],
                '--hp-page-bg-color' => [
                    'description' => 'Page background color',
                    'type' => 'color',
                    'maps_to' => 'styling.page_bg_color',
                    'usage' => ['Main page background'],
                ],
                '--hp-card-bg-color' => [
                    'description' => 'Card and panel background color',
                    'type' => 'color',
                    'maps_to' => 'styling.card_bg_color',
                    'usage' => ['Product cards', 'Content panels', 'Modal backgrounds'],
                ],
                '--hp-input-bg-color' => [
                    'description' => 'Form input background color',
                    'type' => 'color',
                    'maps_to' => 'styling.input_bg_color',
                    'usage' => ['Text inputs', 'Selects', 'Textareas'],
                ],
                '--hp-border-color' => [
                    'description' => 'Border and divider color',
                    'type' => 'color',
                    'maps_to' => 'styling.border_color',
                    'usage' => ['Card borders', 'Dividers', 'Focus rings'],
                ],
            ],
            'backgrounds' => [
                '--hp-background-type' => [
                    'description' => 'Type of background (solid, gradient, or image)',
                    'type' => 'enum',
                    'values' => ['solid', 'gradient', 'image'],
                    'maps_to' => 'styling.background_type',
                ],
                '--hp-background-image' => [
                    'description' => 'Background image URL (when background_type is "image")',
                    'type' => 'url',
                    'maps_to' => 'styling.background_image',
                ],
            ],
        ];
    }

    /**
     * Get color palette structure for AI generation.
     *
     * @return array Color palette structure
     */
    public static function getColorPaletteStructure(): array
    {
        return [
            'primary_colors' => [
                'accent_color' => [
                    'role' => 'Primary brand color, used for CTAs and highlights',
                    'considerations' => [
                        'Should stand out against background',
                        'Consider brand identity and product packaging',
                        'Ensure sufficient contrast for accessibility',
                    ],
                    'derivation_hints' => [
                        'from_product_label' => 'Extract the dominant brand color from product label',
                        'from_brand' => 'Use established brand color guidelines',
                        'from_theme' => 'Select based on emotional association (gold=premium, green=health, blue=trust)',
                    ],
                ],
                'text_color_accent' => [
                    'role' => 'Secondary accent for text highlights',
                    'relationship' => 'Often same as accent_color, or a tint/shade thereof',
                ],
            ],
            'text_colors' => [
                'text_color_basic' => [
                    'role' => 'Primary readable text',
                    'dark_theme' => 'Light color (off-white) for dark backgrounds',
                    'light_theme' => 'Dark color (charcoal) for light backgrounds',
                ],
                'text_color_note' => [
                    'role' => 'Secondary/muted text',
                    'relationship' => 'Lighter/darker variant of basic text with reduced opacity feel',
                ],
                'text_color_discount' => [
                    'role' => 'Positive/savings emphasis',
                    'recommendation' => 'Green tones work universally for "savings" association',
                ],
            ],
            'background_colors' => [
                'page_bg_color' => [
                    'role' => 'Main page background',
                    'dark_theme' => 'Very dark (near black) for premium feel',
                    'light_theme' => 'Off-white or light gray',
                ],
                'card_bg_color' => [
                    'role' => 'Elevated content areas',
                    'relationship' => 'Slightly lighter/darker than page_bg for depth',
                ],
                'input_bg_color' => [
                    'role' => 'Form inputs',
                    'recommendation' => 'Subtle contrast from card background',
                ],
            ],
            'utility_colors' => [
                'border_color' => [
                    'role' => 'Borders, dividers, focus rings',
                    'options' => [
                        'accent_tint' => 'Tinted version of accent color for cohesion',
                        'neutral' => 'Neutral gray for minimal distraction',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get theme presets.
     *
     * @return array Theme presets
     */
    public static function getThemePresets(): array
    {
        return [
            'dark_gold' => [
                'name' => 'Dark Gold (Default)',
                'description' => 'Premium dark theme with gold accents - recommended for health supplements',
                'inspiration' => 'Premium supplement packaging, luxury brands',
                'colors' => [
                    'accent_color' => '#eab308',
                    'text_color_basic' => '#e5e5e5',
                    'text_color_accent' => '#eab308',
                    'text_color_note' => '#a3a3a3',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#121212',
                    'card_bg_color' => '#1a1a1a',
                    'input_bg_color' => '#333333',
                    'border_color' => '#7c3aed',
                ],
            ],
            'dark_emerald' => [
                'name' => 'Dark Emerald',
                'description' => 'Dark theme with green accents - great for natural/organic products',
                'inspiration' => 'Natural health, plant-based products',
                'colors' => [
                    'accent_color' => '#10b981',
                    'text_color_basic' => '#e5e5e5',
                    'text_color_accent' => '#34d399',
                    'text_color_note' => '#9ca3af',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#0a0f0d',
                    'card_bg_color' => '#111816',
                    'input_bg_color' => '#1a2420',
                    'border_color' => '#065f46',
                ],
            ],
            'dark_violet' => [
                'name' => 'Dark Violet',
                'description' => 'Dark theme with purple accents - sophisticated and modern',
                'inspiration' => 'Premium wellness, modern technology',
                'colors' => [
                    'accent_color' => '#8b5cf6',
                    'text_color_basic' => '#f1f5f9',
                    'text_color_accent' => '#a78bfa',
                    'text_color_note' => '#94a3b8',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#0f0a1a',
                    'card_bg_color' => '#1a1625',
                    'input_bg_color' => '#2a2438',
                    'border_color' => '#6d28d9',
                ],
            ],
            'dark_rose' => [
                'name' => 'Dark Rose',
                'description' => 'Dark theme with rose/pink accents - elegant and feminine',
                'inspiration' => 'Beauty, womens health, elegance',
                'colors' => [
                    'accent_color' => '#f43f5e',
                    'text_color_basic' => '#fafafa',
                    'text_color_accent' => '#fb7185',
                    'text_color_note' => '#a1a1aa',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#18181b',
                    'card_bg_color' => '#27272a',
                    'input_bg_color' => '#3f3f46',
                    'border_color' => '#be123c',
                ],
            ],
            'dark_ocean' => [
                'name' => 'Dark Ocean',
                'description' => 'Dark theme with blue accents - trustworthy and professional',
                'inspiration' => 'Medical, trust, professionalism',
                'colors' => [
                    'accent_color' => '#3b82f6',
                    'text_color_basic' => '#f1f5f9',
                    'text_color_accent' => '#60a5fa',
                    'text_color_note' => '#94a3b8',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#0c1222',
                    'card_bg_color' => '#131c2e',
                    'input_bg_color' => '#1e293b',
                    'border_color' => '#1d4ed8',
                ],
            ],
            'dark_amber' => [
                'name' => 'Dark Amber',
                'description' => 'Warm dark theme with amber/orange accents - energetic and vital',
                'inspiration' => 'Energy, vitality, warmth',
                'colors' => [
                    'accent_color' => '#f59e0b',
                    'text_color_basic' => '#fef3c7',
                    'text_color_accent' => '#fbbf24',
                    'text_color_note' => '#a3a3a3',
                    'text_color_discount' => '#22c55e',
                    'page_bg_color' => '#1c1410',
                    'card_bg_color' => '#292018',
                    'input_bg_color' => '#3d2f1f',
                    'border_color' => '#b45309',
                ],
            ],
            'light_clean' => [
                'name' => 'Light Clean',
                'description' => 'Clean light theme - fresh and clinical',
                'inspiration' => 'Clinical, clean, pure',
                'colors' => [
                    'accent_color' => '#0284c7',
                    'text_color_basic' => '#1e293b',
                    'text_color_accent' => '#0369a1',
                    'text_color_note' => '#64748b',
                    'text_color_discount' => '#16a34a',
                    'page_bg_color' => '#f8fafc',
                    'card_bg_color' => '#ffffff',
                    'input_bg_color' => '#f1f5f9',
                    'border_color' => '#e2e8f0',
                ],
            ],
            'light_natural' => [
                'name' => 'Light Natural',
                'description' => 'Warm light theme with natural earth tones',
                'inspiration' => 'Organic, natural, earthy',
                'colors' => [
                    'accent_color' => '#65a30d',
                    'text_color_basic' => '#1c1917',
                    'text_color_accent' => '#4d7c0f',
                    'text_color_note' => '#78716c',
                    'text_color_discount' => '#16a34a',
                    'page_bg_color' => '#fafaf9',
                    'card_bg_color' => '#ffffff',
                    'input_bg_color' => '#f5f5f4',
                    'border_color' => '#d6d3d1',
                ],
            ],
        ];
    }

    /**
     * Get generation guidelines for AI.
     *
     * @return array Generation guidelines
     */
    public static function getGenerationGuidelines(): array
    {
        return [
            'color_derivation' => [
                'from_product_image' => [
                    'step_1' => 'Identify dominant colors in product packaging',
                    'step_2' => 'Extract primary brand color for accent_color',
                    'step_3' => 'Determine if dark or light theme based on packaging aesthetic',
                    'step_4' => 'Generate complementary colors for other properties',
                ],
                'from_brand_guidelines' => [
                    'step_1' => 'Use primary brand color as accent_color',
                    'step_2' => 'Apply brand secondary colors where appropriate',
                    'step_3' => 'Maintain brand consistency across all elements',
                ],
                'from_inspiration' => [
                    'premium_supplement' => 'Dark theme with gold or amber accent',
                    'natural_organic' => 'Dark or light theme with green accent',
                    'energy_vitality' => 'Dark theme with orange or amber accent',
                    'calm_wellness' => 'Light theme with blue or teal accent',
                    'feminine_beauty' => 'Dark or light theme with rose or pink accent',
                    'professional_clinical' => 'Light theme with blue accent',
                ],
            ],
            'contrast_requirements' => [
                'wcag_aa' => [
                    'normal_text' => '4.5:1 contrast ratio minimum',
                    'large_text' => '3:1 contrast ratio minimum',
                    'ui_components' => '3:1 contrast ratio minimum',
                ],
                'recommendations' => [
                    'buttons' => 'Ensure accent_color has sufficient contrast with button text',
                    'cards' => 'card_bg_color should be distinct from page_bg_color',
                    'inputs' => 'input_bg_color should provide clear field boundaries',
                ],
            ],
            'theme_selection' => [
                'dark_themes' => [
                    'when_to_use' => 'Premium products, supplements, evening/night contexts',
                    'benefits' => 'Luxurious feel, reduces eye strain, makes products pop',
                    'recommended_for' => 'Most health supplement funnels',
                ],
                'light_themes' => [
                    'when_to_use' => 'Clean/clinical products, daytime contexts, medical settings',
                    'benefits' => 'Professional, clean, trustworthy',
                    'recommended_for' => 'Medical devices, clinical products, organic foods',
                ],
            ],
            'customization_tips' => [
                'starting_point' => 'Begin with a preset that matches product category',
                'accent_first' => 'Change accent_color first to match brand',
                'test_contrast' => 'Verify readability after any color changes',
                'consistency' => 'Keep color temperature consistent (warm with warm, cool with cool)',
            ],
        ];
    }

    /**
     * Generate a color palette suggestion based on inputs.
     *
     * @param array $inputs Generation inputs
     * @return array Suggested palette
     */
    public static function suggestPalette(array $inputs): array
    {
        $accentColor = $inputs['accent_color'] ?? null;
        $theme = $inputs['theme'] ?? 'dark';
        $inspiration = $inputs['inspiration'] ?? null;
        $productCategory = $inputs['product_category'] ?? 'supplement';

        // Start with a preset based on theme preference
        $presets = self::getThemePresets();
        
        // Select base preset
        if ($theme === 'light') {
            $basePalette = $presets['light_clean']['colors'];
        } else {
            // For dark theme, try to match category
            $categoryPresets = [
                'supplement' => 'dark_gold',
                'organic' => 'dark_emerald',
                'energy' => 'dark_amber',
                'wellness' => 'dark_violet',
                'beauty' => 'dark_rose',
                'clinical' => 'dark_ocean',
            ];
            $presetKey = $categoryPresets[$productCategory] ?? 'dark_gold';
            $basePalette = $presets[$presetKey]['colors'];
        }

        // Override accent color if provided
        if ($accentColor) {
            $basePalette['accent_color'] = $accentColor;
            $basePalette['text_color_accent'] = $accentColor;
            // Adjust border to be a darker version of accent
            $basePalette['border_color'] = self::darkenColor($accentColor, 30);
        }

        return [
            'palette' => $basePalette,
            'source' => [
                'base_preset' => $presetKey ?? 'light_clean',
                'customizations' => $accentColor ? ['accent_color'] : [],
            ],
            'preview_css' => self::generatePreviewCss($basePalette),
        ];
    }

    /**
     * Generate preview CSS from palette.
     *
     * @param array $palette Color palette
     * @return string CSS custom properties
     */
    public static function generatePreviewCss(array $palette): string
    {
        $css = ":root {\n";
        $propertyMap = [
            'accent_color' => '--hp-accent-color',
            'text_color_basic' => '--hp-text-color-basic',
            'text_color_accent' => '--hp-text-color-accent',
            'text_color_note' => '--hp-text-color-note',
            'text_color_discount' => '--hp-text-color-discount',
            'page_bg_color' => '--hp-page-bg-color',
            'card_bg_color' => '--hp-card-bg-color',
            'input_bg_color' => '--hp-input-bg-color',
            'border_color' => '--hp-border-color',
        ];

        foreach ($propertyMap as $key => $property) {
            if (isset($palette[$key])) {
                $css .= "  {$property}: {$palette[$key]};\n";
            }
        }
        $css .= "}";

        return $css;
    }

    /**
     * Darken a hex color.
     *
     * @param string $hex Hex color
     * @param int $percent Percentage to darken
     * @return string Darkened hex color
     */
    private static function darkenColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Validate a color palette.
     *
     * @param array $palette Color palette to validate
     * @return array Validation result
     */
    public static function validatePalette(array $palette): array
    {
        $errors = [];
        $warnings = [];
        $requiredColors = [
            'accent_color',
            'text_color_basic',
            'page_bg_color',
            'card_bg_color',
        ];

        // Check required colors
        foreach ($requiredColors as $color) {
            if (empty($palette[$color])) {
                $errors[] = "Missing required color: {$color}";
            } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $palette[$color])) {
                $errors[] = "Invalid hex color format for {$color}: {$palette[$color]}";
            }
        }

        // Check contrast (basic check)
        if (!empty($palette['text_color_basic']) && !empty($palette['page_bg_color'])) {
            $contrast = self::calculateContrast($palette['text_color_basic'], $palette['page_bg_color']);
            if ($contrast < 4.5) {
                $warnings[] = "Low contrast between text and background: {$contrast}:1 (minimum 4.5:1 recommended)";
            }
        }

        // Check card vs page distinction
        if (!empty($palette['card_bg_color']) && !empty($palette['page_bg_color'])) {
            if ($palette['card_bg_color'] === $palette['page_bg_color']) {
                $warnings[] = "card_bg_color and page_bg_color are identical - cards won't be visually distinct";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate contrast ratio between two colors.
     *
     * @param string $hex1 First hex color
     * @param string $hex2 Second hex color
     * @return float Contrast ratio
     */
    private static function calculateContrast(string $hex1, string $hex2): float
    {
        $l1 = self::relativeLuminance($hex1);
        $l2 = self::relativeLuminance($hex2);

        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Calculate relative luminance of a color.
     *
     * @param string $hex Hex color
     * @return float Relative luminance
     */
    private static function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $r = $r <= 0.03928 ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = $g <= 0.03928 ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = $b <= 0.03928 ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }
}

















