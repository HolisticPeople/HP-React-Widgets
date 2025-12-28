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
     * Inject display mode UI next to the Section Title in Testimonials tab.
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
            .hp-testimonials-display-controls {
                display: inline-flex;
                align-items: center;
                gap: 12px;
                margin-left: 20px;
                vertical-align: middle;
            }
            .hp-testimonials-display-controls label {
                font-weight: 500;
                color: #1d2327;
                margin-right: 4px;
            }
            .hp-display-toggle {
                display: inline-flex;
                border: 1px solid #8c8f94;
                border-radius: 4px;
                overflow: hidden;
            }
            .hp-display-toggle button {
                padding: 6px 14px;
                border: none;
                background: #f0f0f1;
                color: #50575e;
                cursor: pointer;
                font-size: 13px;
                display: flex;
                align-items: center;
                gap: 6px;
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
            }
            .hp-columns-select {
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }
            .hp-columns-select select {
                padding: 4px 8px;
                border-radius: 4px;
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
                        <label>Display:</label>
                        <div class="hp-display-toggle">
                            <button type="button" data-mode="cards" class="${displayMode === 'cards' ? 'active' : ''}">
                                <span class="dashicons dashicons-grid-view"></span> Grid
                            </button>
                            <button type="button" data-mode="carousel" class="${displayMode === 'carousel' ? 'active' : ''}">
                                <span class="dashicons dashicons-slides"></span> Slider
                            </button>
                        </div>
                        <div class="hp-columns-select" style="${displayMode === 'carousel' ? 'display:none' : ''}">
                            <label>Columns:</label>
                            <select>
                                <option value="2" ${columns === '2' ? 'selected' : ''}>2</option>
                                <option value="3" ${columns === '3' ? 'selected' : ''}>3</option>
                            </select>
                        </div>
                    </div>
                `;
                
                // Insert controls next to the title field label
                var $label = $titleField.find('.acf-label');
                if ($label.length) {
                    $label.css('display', 'flex').css('align-items', 'center').css('flex-wrap', 'wrap');
                    $label.append(controlsHtml);
                }
                
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
