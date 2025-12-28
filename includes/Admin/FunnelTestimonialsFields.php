<?php
namespace HP_RW\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds display mode controls to the Testimonials tab via JavaScript injection.
 * This integrates seamlessly with the existing ACF UI-defined fields.
 */
class FunnelTestimonialsFields
{
    public static function init(): void
    {
        // Register hidden fields to store the values
        add_action('acf/init', [self::class, 'registerHiddenFields'], 20);
        
        // Inject UI controls via JavaScript
        add_action('acf/input/admin_footer', [self::class, 'injectDisplayModeUI']);
    }

    /**
     * Register the actual ACF fields (hidden, values managed via JS).
     */
    public static function registerHiddenFields(): void
    {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Register fields in a hidden/seamless field group
        acf_add_local_field_group([
            'key' => 'group_testimonials_display_hidden',
            'title' => '',
            'fields' => [
                [
                    'key' => 'field_testimonials_display_mode',
                    'label' => '',
                    'name' => 'testimonials_display_mode',
                    'type' => 'text',
                    'default_value' => 'cards',
                    'wrapper' => ['class' => 'hp-hidden-field'],
                ],
                [
                    'key' => 'field_testimonials_columns',
                    'label' => '',
                    'name' => 'testimonials_columns',
                    'type' => 'text',
                    'default_value' => '3',
                    'wrapper' => ['class' => 'hp-hidden-field'],
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
            'menu_order' => 99,
            'position' => 'normal',
            'style' => 'seamless',
            'label_placement' => 'top',
        ]);
    }

    /**
     * Inject display mode UI on the same row as Section Title input.
     */
    public static function injectDisplayModeUI(): void
    {
        global $post;
        if (!$post || $post->post_type !== 'hp-funnel') {
            return;
        }
        ?>
        <style>
            .hp-hidden-field { display: none !important; }
            
            /* Make the title field row a flex container */
            .hp-testimonials-row {
                display: flex !important;
                align-items: flex-start;
                gap: 20px;
            }
            .hp-testimonials-row .acf-input {
                flex: 1;
                min-width: 0;
            }
            
            .hp-testimonials-display-controls {
                display: flex;
                align-items: center;
                gap: 16px;
                padding-top: 28px; /* Align with input field */
                flex-shrink: 0;
            }
            .hp-testimonials-display-controls .hp-control-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .hp-testimonials-display-controls label {
                font-weight: 500;
                color: #1d2327;
                font-size: 13px;
                white-space: nowrap;
            }
            .hp-display-toggle {
                display: inline-flex;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                overflow: hidden;
            }
            .hp-display-toggle button {
                padding: 6px 12px;
                border: none;
                background: #f0f0f1;
                color: #50575e;
                cursor: pointer;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 5px;
                transition: all 0.15s ease;
            }
            .hp-display-toggle button:not(:last-child) {
                border-right: 1px solid #8c8f94;
            }
            .hp-display-toggle button:hover {
                background: #dcdcde;
            }
            .hp-display-toggle button.active {
                background: #2271b1;
                color: #fff;
            }
            .hp-display-toggle button .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
                line-height: 16px;
            }
            .hp-columns-select {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .hp-columns-select select {
                padding: 5px 28px 5px 10px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                background: #fff;
                font-size: 13px;
                min-width: 60px;
                appearance: none;
                -webkit-appearance: none;
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23555' d='M2 4l4 4 4-4z'/%3E%3C/svg%3E");
                background-repeat: no-repeat;
                background-position: right 8px center;
            }
        </style>
        <script>
        (function($) {
            if (typeof acf === 'undefined') return;
            
            acf.addAction('ready', function() {
                // Find the testimonials_title field
                var $titleField = $('[data-name="testimonials_title"]');
                if (!$titleField.length) return;
                
                // Get current values from hidden fields
                var displayMode = $('[data-name="testimonials_display_mode"] input').val() || 'cards';
                var columns = $('[data-name="testimonials_columns"] input').val() || '3';
                
                // Create the controls HTML
                var controlsHtml = `
                    <div class="hp-testimonials-display-controls">
                        <div class="hp-control-group">
                            <label>Display:</label>
                            <div class="hp-display-toggle">
                                <button type="button" data-mode="cards" class="${displayMode === 'cards' ? 'active' : ''}">
                                    <span class="dashicons dashicons-grid-view"></span> Grid
                                </button>
                                <button type="button" data-mode="carousel" class="${displayMode === 'carousel' ? 'active' : ''}">
                                    <span class="dashicons dashicons-slides"></span> Slider
                                </button>
                            </div>
                        </div>
                        <div class="hp-control-group hp-columns-select" style="${displayMode === 'carousel' ? 'display:none' : ''}">
                            <label>Columns:</label>
                            <select>
                                <option value="2" ${columns === '2' ? 'selected' : ''}>2</option>
                                <option value="3" ${columns === '3' ? 'selected' : ''}>3</option>
                            </select>
                        </div>
                    </div>
                `;
                
                // Add class to make the field row a flex container
                $titleField.addClass('hp-testimonials-row');
                
                // Append controls after the input wrapper
                $titleField.append(controlsHtml);
                
                // Handle toggle clicks
                $(document).on('click', '.hp-display-toggle button', function(e) {
                    e.preventDefault();
                    var mode = $(this).data('mode');
                    
                    // Update UI
                    $('.hp-display-toggle button').removeClass('active');
                    $(this).addClass('active');
                    
                    // Show/hide columns selector
                    if (mode === 'carousel') {
                        $('.hp-columns-select').hide();
                    } else {
                        $('.hp-columns-select').show();
                    }
                    
                    // Update hidden field
                    $('[data-name="testimonials_display_mode"] input').val(mode).trigger('change');
                });
                
                // Handle columns change
                $(document).on('change', '.hp-columns-select select', function() {
                    $('[data-name="testimonials_columns"] input').val($(this).val()).trigger('change');
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}
