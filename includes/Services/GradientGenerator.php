<?php
/**
 * Gradient Generator Service
 *
 * Generates CSS gradient strings for section backgrounds.
 * Supports linear, radial, and conic gradients with multiple preset patterns.
 *
 * @package HP_RW
 * @since 2.33.0
 */

namespace HP_RW\Services;

class GradientGenerator
{
    /**
     * Gradient preset mappings
     */
    private const LINEAR_PRESETS = [
        'vertical-down'       => '180deg',
        'vertical-up'         => '0deg',
        'horizontal-right'    => '90deg',
        'horizontal-left'     => '270deg',
        'diagonal-topright'   => '45deg',
        'diagonal-topleft'    => '315deg',
        'diagonal-bottomright'=> '135deg',
        'diagonal-bottomleft' => '225deg',
    ];

    private const RADIAL_PRESETS = [
        'circle-center'  => 'circle at center',
        'circle-top'     => 'circle at top',
        'circle-bottom'  => 'circle at bottom',
        'circle-left'    => 'circle at left',
        'circle-right'   => 'circle at right',
        'ellipse-center' => 'ellipse at center',
        'ellipse-top'    => 'ellipse at top',
        'ellipse-bottom' => 'ellipse at bottom',
        'ellipse-left'   => 'ellipse at left',
        'ellipse-right'  => 'ellipse at right',
    ];

    private const CONIC_PRESETS = [
        'conic-center-0'   => 'from 0deg at center',
        'conic-center-45'  => 'from 45deg at center',
        'conic-center-90'  => 'from 90deg at center',
        'conic-center-135' => 'from 135deg at center',
        'conic-center-180' => 'from 180deg at center',
        'conic-center-225' => 'from 225deg at center',
        'conic-center-270' => 'from 270deg at center',
        'conic-center-315' => 'from 315deg at center',
    ];

    /**
     * Generate CSS gradient string from configuration.
     *
     * @param array  $config        Gradient configuration with keys: gradient_type, gradient_preset, color_mode, gradient_start_color, gradient_end_color
     * @param string $fallbackColor Fallback solid color (used for auto mode start color or if gradient is disabled)
     * @param string $pageBgColor   Page background color (used for auto mode end color)
     * @return string CSS gradient or solid color
     */
    public static function generateGradient(array $config, string $fallbackColor, string $pageBgColor): string
    {
        $type = $config['gradient_type'] ?? 'solid';
        $preset = $config['gradient_preset'] ?? 'vertical-down';
        $colorMode = $config['color_mode'] ?? 'auto';

        // Solid color - no gradient
        if ($type === 'solid' || empty($type)) {
            return $fallbackColor;
        }

        // Determine colors based on mode
        if ($colorMode === 'manual') {
            $startColor = $config['gradient_start_color'] ?? $fallbackColor;
            $endColor = $config['gradient_end_color'] ?? $pageBgColor;
        } else {
            // Auto mode: fallback color → page background
            $startColor = $fallbackColor;
            $endColor = $pageBgColor;
        }

        // Generate gradient based on type
        switch ($type) {
            case 'linear':
                return self::generateLinearGradient($preset, $startColor, $endColor);
            case 'radial':
                return self::generateRadialGradient($preset, $startColor, $endColor);
            case 'conic':
                return self::generateConicGradient($preset, $startColor, $endColor);
            default:
                return $fallbackColor;
        }
    }

    /**
     * Generate linear gradient CSS.
     *
     * @param string $preset Preset name (e.g., 'vertical-down', 'horizontal-right')
     * @param string $start  Start color (hex)
     * @param string $end    End color (hex)
     * @return string CSS linear-gradient value
     */
    private static function generateLinearGradient(string $preset, string $start, string $end): string
    {
        $deg = self::LINEAR_PRESETS[$preset] ?? '180deg';
        return "linear-gradient({$deg}, {$start}, {$end})";
    }

    /**
     * Generate radial gradient CSS.
     *
     * @param string $preset Preset name (e.g., 'circle-center', 'ellipse-top')
     * @param string $start  Start color (hex)
     * @param string $end    End color (hex)
     * @return string CSS radial-gradient value
     */
    private static function generateRadialGradient(string $preset, string $start, string $end): string
    {
        $pos = self::RADIAL_PRESETS[$preset] ?? 'circle at center';
        return "radial-gradient({$pos}, {$start}, {$end})";
    }

    /**
     * Generate conic gradient CSS.
     *
     * @param string $preset Preset name (e.g., 'conic-center-0', 'conic-center-90')
     * @param string $start  Start color (hex)
     * @param string $end    End color (hex)
     * @return string CSS conic-gradient value
     */
    private static function generateConicGradient(string $preset, string $start, string $end): string
    {
        $angle = self::CONIC_PRESETS[$preset] ?? 'from 0deg at center';
        return "conic-gradient({$angle}, {$start}, {$end})";
    }

    /**
     * Get all available gradient presets grouped by type.
     *
     * @return array Presets organized by gradient type
     */
    public static function getAllPresets(): array
    {
        return [
            'linear' => self::LINEAR_PRESETS,
            'radial' => self::RADIAL_PRESETS,
            'conic' => self::CONIC_PRESETS,
        ];
    }
}
