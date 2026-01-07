<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds display mode controls integrated into the Testimonials tab.
 * Uses ACF fields for proper saving with JS-enhanced UI.
 */
class FunnelTestimonialsFields
{
    public static function init(): void
    {
        // Registered via ACF JSON: group_hp_funnel_config.json
        // add_action('acf/init', [self::class, 'registerFields'], 20);
        
        // Inject custom UI via JavaScript
        add_action('acf/input/admin_footer', [self::class, 'injectDisplayModeUI']);
    }

    /**
     * Register ACF fields - these are hidden and controlled via custom JS UI.
     */
    public static function registerFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key' => 'group_testimonials_display_settings',
            'title' => 'Testimonials Display',
            'fields' => [
                [
                    'key' => 'field_testimonials_display_mode',
                    'label' => 'Display Mode',
                    'name' => 'testimonials_display_mode',
                    'type' => 'select',
                    'choices' => [
                        'cards' => 'Grid',
                        'carousel' => 'Slider',
                    ],
                    'default_value' => 'cards',
                    'ui' => 0,
                    'wrapper' => ['class' => 'hp-testimonials-hidden-field', 'width' => '50'],
                ],
                [
                    'key' => 'field_testimonials_columns',
                    'label' => 'Columns',
                    'name' => 'testimonials_columns',
                    'type' => 'select',
                    'choices' => [
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ],
                    'default_value' => '3',
                    'ui' => 0,
                    'wrapper' => ['class' => 'hp-testimonials-hidden-field', 'width' => '50'],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'hp-funnel',
                    ],
                ],
            ],
            'menu_order' => 8,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'hide_on_screen' => '',
            'active' => true,
        ]);
    }

    /**
     * Inject display mode UI into the Testimonials tab, on the same row as Section Title.
     */
    public static function injectDisplayModeUI(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'hp-funnel') {
            return;
        }
        ?>
        <style>
            /* Hide the actual ACF fields */
            .hp-testimonials-hidden-field { 
                position: absolute !important;
                left: -9999px !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
            
            /* Section Title row becomes flex */
            .hp-testimonials-title-row {
                display: flex !important;
                align-items: flex-start;
                gap: 24px;
            }
            .hp-testimonials-title-row > .acf-label {
                flex-shrink: 0;
            }
            .hp-testimonials-title-row > .acf-input {
                flex: 1;
                min-width: 200px;
            }
            
            /* Display controls container */
            .hp-testimonials-display-controls {
                display: flex;
                align-items: center;
                gap: 20px;
                padding-top: 24px;
                flex-shrink: 0;
            }
            .hp-control-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .hp-control-group > label {
                font-weight: 500;
                color: #1d2327;
                font-size: 13px;
                white-space: nowrap;
            }
            
            /* Button toggle group */
            .hp-btn-toggle {
                display: inline-flex;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                overflow: hidden;
                background: #f0f0f1;
            }
            .hp-btn-toggle button {
                padding: 5px 12px;
                border: none;
                background: transparent;
                color: #50575e;
                cursor: pointer;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 5px;
                transition: all 0.15s ease;
                line-height: 1.4;
            }
            .hp-btn-toggle button:not(:last-child) {
                border-right: 1px solid #c3c4c7;
            }
            .hp-btn-toggle button:hover {
                background: #dcdcde;
            }
            .hp-btn-toggle button.active {
                background: #2271b1;
                color: #fff;
            }
            .hp-btn-toggle button .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
                line-height: 14px;
            }
        </style>
        <script>
        (function($) {
            if (typeof acf === 'undefined') return;
            
            acf.addAction('ready', function() {
                // Find the testimonials_title field
                var $titleField = $('[data-name="testimonials_title"]');
                if (!$titleField.length) return;
                
                // Get current values from the actual ACF select fields
                var $modeSelect = $('[data-name="testimonials_display_mode"] select');
                var $colsSelect = $('[data-name="testimonials_columns"] select');
                
                var displayMode = $modeSelect.val() || 'cards';
                var columns = $colsSelect.val() || '3';
                
                // Build controls HTML
                var controlsHtml = `
                    <div class="hp-testimonials-display-controls">
                        <div class="hp-control-group">
                            <label>Display:</label>
                            <div class="hp-btn-toggle hp-mode-toggle">
                                <button type="button" data-value="cards" class="${displayMode === 'cards' ? 'active' : ''}">
                                    <span class="dashicons dashicons-grid-view"></span> Grid
                                </button>
                                <button type="button" data-value="carousel" class="${displayMode === 'carousel' ? 'active' : ''}">
                                    <span class="dashicons dashicons-slides"></span> Slider
                                </button>
                            </div>
                        </div>
                        <div class="hp-control-group hp-cols-control" style="${displayMode === 'carousel' ? 'display:none' : ''}">
                            <label>Columns:</label>
                            <div class="hp-btn-toggle hp-cols-toggle">
                                <button type="button" data-value="2" class="${columns === '2' ? 'active' : ''}">2</button>
                                <button type="button" data-value="3" class="${columns === '3' ? 'active' : ''}">3</button>
                                <button type="button" data-value="4" class="${columns === '4' ? 'active' : ''}">4</button>
                            </div>
                        </div>
                    </div>
                `;
                
                // Add flex class and append controls
                $titleField.addClass('hp-testimonials-title-row');
                $titleField.append(controlsHtml);
                
                // Handle mode toggle clicks
                $(document).on('click', '.hp-mode-toggle button', function(e) {
                    e.preventDefault();
                    var value = $(this).data('value');
                    
                    // Update button states
                    $('.hp-mode-toggle button').removeClass('active');
                    $(this).addClass('active');
                    
                    // Update actual ACF field
                    $modeSelect.val(value).trigger('change');
                    
                    // Show/hide columns
                    if (value === 'carousel') {
                        $('.hp-cols-control').hide();
                    } else {
                        $('.hp-cols-control').show();
                    }
                });
                
                // Handle columns toggle clicks
                $(document).on('click', '.hp-cols-toggle button', function(e) {
                    e.preventDefault();
                    var value = $(this).data('value');
                    
                    // Update button states
                    $('.hp-cols-toggle button').removeClass('active');
                    $(this).addClass('active');
                    
                    // Update actual ACF field
                    $colsSelect.val(value).trigger('change');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
