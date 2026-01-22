/**
 * Section Background Admin UI Enhancements (v2.33.65)
 *
 * Features:
 * - Radio button selection (one row at a time) for copying settings
 * - Bulk actions: Apply to Odd (starting from Home/0), Even, or All sections
 * - Live preview rectangles showing current background in each row
 * - Real-time updates when user changes settings
 * - Section names match ScrollNavigation component (Home, Benefits, etc.)
 * - Color picker automatically hidden when background type is "None"
 * - Hidden add/remove row buttons (auto-populated, not editable)
 * - Fixed column alignment (injects headers/cells after .acf-row-handle for stable positioning)
 */

(function($) {
    'use strict';

    // Gradient preset mappings (must match GradientGenerator.php)
    const LINEAR_PRESETS = {
        'vertical-down': '180deg',
        'vertical-up': '0deg',
        'horizontal-right': '90deg',
        'horizontal-left': '270deg',
        'diagonal-topright': '45deg',
        'diagonal-topleft': '315deg',
        'diagonal-bottomright': '135deg',
        'diagonal-bottomleft': '225deg'
    };

    const RADIAL_PRESETS = {
        'circle-center': 'circle at center',
        'circle-top': 'circle at top',
        'circle-bottom': 'circle at bottom',
        'circle-left': 'circle at left',
        'circle-right': 'circle at right',
        'ellipse-center': 'ellipse at center',
        'ellipse-top': 'ellipse at top',
        'ellipse-bottom': 'ellipse at bottom',
        'ellipse-left': 'ellipse at left',
        'ellipse-right': 'ellipse at right'
    };

    const CONIC_PRESETS = {
        'conic-center-0': 'from 0deg at center',
        'conic-center-45': 'from 45deg at center',
        'conic-center-90': 'from 90deg at center',
        'conic-center-135': 'from 135deg at center',
        'conic-center-180': 'from 180deg at center',
        'conic-center-225': 'from 225deg at center',
        'conic-center-270': 'from 270deg at center',
        'conic-center-315': 'from 315deg at center'
    };

    /**
     * Generate CSS gradient string matching GradientGenerator.php logic
     */
    function generateGradientCSS(type, preset, startColor, endColor) {
        if (type === 'linear') {
            const deg = LINEAR_PRESETS[preset] || '180deg';
            return `linear-gradient(${deg}, ${startColor}, ${endColor})`;
        } else if (type === 'radial') {
            const pos = RADIAL_PRESETS[preset] || 'circle at center';
            return `radial-gradient(${pos}, ${startColor}, ${endColor})`;
        } else if (type === 'conic') {
            const angle = CONIC_PRESETS[preset] || 'from 0deg at center';
            return `conic-gradient(${angle}, ${startColor}, ${endColor})`;
        }
        return '';
    }

    /**
     * Get background CSS for a given row's settings
     */
    function getRowBackgroundCSS($row) {
        const bgType = $row.find('[data-name="background_type"] select').val();

        if (bgType === 'none' || !bgType) {
            return 'transparent';
        }

        if (bgType === 'solid') {
            const solidColor = $row.find('[data-name="gradient_start_color"] input').val() || '#1a1a2e';
            return solidColor;
        }

        if (bgType === 'gradient') {
            const gradientType = $row.find('[data-name="gradient_type"] select').val() || 'linear';
            const gradientPreset = $row.find('[data-name="gradient_preset"] select').val() || 'vertical-down';
            const colorMode = $row.find('[data-name="color_mode"] select').val() || 'auto';

            let startColor, endColor;
            if (colorMode === 'manual') {
                startColor = $row.find('[data-name="gradient_start_color"] input').val() || '#1a1a2e';
                endColor = $row.find('[data-name="gradient_end_color"] input').val() || '#121212';
            } else {
                // Auto mode: use gradient_start_color as fallback
                startColor = $row.find('[data-name="gradient_start_color"] input').val() || '#1a1a2e';
                // For preview, use a lighter/darker shade for end color
                endColor = '#121212'; // Approximate page bg color
            }

            return generateGradientCSS(gradientType, gradientPreset, startColor, endColor);
        }

        return 'transparent';
    }

    /**
     * Update preview rectangle for a specific row
     */
    function updatePreview($row) {
        const $preview = $row.find('.hp-section-bg-preview');
        if (!$preview.length) return;

        const backgroundCSS = getRowBackgroundCSS($row);

        if (backgroundCSS === 'transparent') {
            $preview.addClass('transparent').css('background', '');
        } else {
            $preview.removeClass('transparent').css('background', backgroundCSS);
        }
    }

    /**
     * Add bulk action buttons (v2.33.46 - placed inside .acf-input wrapper)
     */
    function addBulkActionButtons() {
        const $repeaterField = $('[data-key="field_section_backgrounds"]');
        if (!$repeaterField.length) return;

        // Find the .acf-input wrapper (this is a child of .acf-field, not a parent)
        const $inputWrapper = $repeaterField.find('.acf-input').first();
        if (!$inputWrapper.length) return;

        // Check if already exists inside the input wrapper
        if ($inputWrapper.find('.hp-bulk-actions').length > 0) {
            return; // Already added
        }

        const bulkActionsHTML = `
            <div class="hp-bulk-actions">
                <label style="font-weight: 600; margin-right: 15px;">
                    Select a row to copy, then apply to:
                </label>
                <button type="button" class="button hp-apply-odd">Odd (Home, Science, Offers...)</button>
                <button type="button" class="button hp-apply-even">Even (Benefits, Features, Expert...)</button>
                <button type="button" class="button hp-apply-all">All</button>
                <span style="margin: 0 15px; color: #ccc;">|</span>
                <button type="button" class="button hp-refresh-sections">
                    <span class="dashicons dashicons-update" style="margin-top: 3px;"></span> Refresh Sections
                </button>
            </div>
        `;

        // Insert at the beginning of .acf-input (before the repeater)
        // The .acf-input wrapper is what ACF hides when tabs are switched
        $inputWrapper.prepend(bulkActionsHTML);
    }

    /**
     * Add preview rectangle and checkbox to each repeater row
     */
    function addPreviewsAndCheckboxes() {
        const $repeater = $('[data-key="field_section_backgrounds"]');
        if (!$repeater.length) return;

        // Add table headers for new columns
        const $table = $repeater.find('.acf-table');
        const $thead = $table.find('thead tr');
        if ($thead.length && !$thead.find('.hp-section-header').length) {
            // Inject BEFORE section_id to avoid alignment issues from hidden column widths
            const $sectionIdHeader = $thead.find('th[data-name="section_id"]');

            if ($sectionIdHeader.length) {
                // Insert our headers BEFORE section_id (which is hidden)
                $sectionIdHeader.before(`
                    <th class="hp-section-header">Section</th>
                    <th class="hp-preview-header">Preview</th>
                `);
            } else {
                // Fallback: use row handle
                const $firstHandle = $thead.find('.acf-row-handle').first();
                if ($firstHandle.length) {
                    $firstHandle.after(`
                        <th class="hp-section-header">Section</th>
                        <th class="hp-preview-header">Preview</th>
                    `);
                }
            }
        }

        // Add checkbox, section name, and preview to each row
        $repeater.find('.acf-row').each(function(index) {
            const $row = $(this);

            // Skip if already has checkbox
            if ($row.find('.hp-row-checkbox').length) return;

            // Get section name from server-side data or fallback (v2.33.40 - restored original logic)
            let sectionName = 'Section';
            if (typeof hpSectionBgData !== 'undefined' && hpSectionBgData.sectionNames && hpSectionBgData.sectionNames[index]) {
                sectionName = hpSectionBgData.sectionNames[index];
            } else if (index === 0) {
                sectionName = 'Hero';
            } else {
                sectionName = `Section ${index}`;
            }

            // Inject BEFORE section_id cell (matches header positioning)
            const $sectionIdCell = $row.find('td[data-name="section_id"]');

            if ($sectionIdCell.length) {
                // Insert Section and Preview cells BEFORE section_id
                $sectionIdCell.before(`
                    <td class="hp-section-name-cell">
                        <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
                            <input type="radio" name="hp-section-select" class="hp-row-radio" />
                            <span class="hp-section-name">${sectionName}</span>
                        </label>
                    </td>
                    <td class="hp-preview-cell"><div class="hp-section-bg-preview"></div></td>
                `);
            } else {
                // Fallback: use row handle
                const $firstHandle = $row.find('.acf-row-handle').first();
                if ($firstHandle.length) {
                    $firstHandle.after(`
                        <td class="hp-section-name-cell">
                            <label style="display: flex; align-items: center; gap: 8px; margin: 0;">
                                <input type="radio" name="hp-section-select" class="hp-row-radio" />
                                <span class="hp-section-name">${sectionName}</span>
                            </label>
                        </td>
                        <td class="hp-preview-cell"><div class="hp-section-bg-preview"></div></td>
                    `);
                }
            }

            // Force browser table recalculation
            $repeater.find('.acf-table')[0].offsetHeight;

            // Initial preview update
            updatePreview($row);
        });
    }

    /**
     * Copy settings from source row to target rows
     */
    function copyRowSettings($sourceRow, $targetRows) {
        const sourceData = {
            background_type: $sourceRow.find('[data-name="background_type"] select').val(),
            gradient_type: $sourceRow.find('[data-name="gradient_type"] select').val(),
            gradient_preset: $sourceRow.find('[data-name="gradient_preset"] select').val(),
            color_mode: $sourceRow.find('[data-name="color_mode"] select').val(),
            gradient_start_color: $sourceRow.find('[data-name="gradient_start_color"] input').val(),
            gradient_end_color: $sourceRow.find('[data-name="gradient_end_color"] input').val()
        };

        $targetRows.each(function() {
            const $target = $(this);

            $target.find('[data-name="background_type"] select').val(sourceData.background_type).trigger('change');
            $target.find('[data-name="gradient_type"] select').val(sourceData.gradient_type).trigger('change');
            $target.find('[data-name="gradient_preset"] select').val(sourceData.gradient_preset).trigger('change');
            $target.find('[data-name="color_mode"] select').val(sourceData.color_mode).trigger('change');
            $target.find('[data-name="gradient_start_color"] input').val(sourceData.gradient_start_color).trigger('change');
            $target.find('[data-name="gradient_end_color"] input').val(sourceData.gradient_end_color).trigger('change');

            updatePreview($target);
        });
    }

    /**
     * Initialize bulk action event handlers
     */
    function initBulkActions() {
        // Get the selected source row
        function getSelectedSourceRow() {
            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $selectedRow = $allRows.filter(function() {
                return $(this).find('.hp-row-radio').is(':checked');
            });

            if ($selectedRow.length === 0) {
                alert('Please select a row to copy from.');
                return null;
            }

            return $selectedRow.first();
        }

        // Apply to All
        $(document).on('click', '.hp-apply-all', function() {
            const $sourceRow = getSelectedSourceRow();
            if (!$sourceRow) return;

            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $targetRows = $allRows.not($sourceRow);

            copyRowSettings($sourceRow, $targetRows);
        });

        // Apply to Odd (Home/Hero, Science, Offers, Reviews = 0-indexed: 0, 2, 4, 6)
        $(document).on('click', '.hp-apply-odd', function() {
            const $sourceRow = getSelectedSourceRow();
            if (!$sourceRow) return;

            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');

            // Target odd rows starting from 0: Home(0), Science(2), Offers(4), Reviews(6), etc.
            const $targetRows = $allRows.filter(function(index) {
                return index % 2 === 0;
            });

            copyRowSettings($sourceRow, $targetRows);
        });

        // Apply to Even (Benefits, Features, Expert = 0-indexed: 1, 3, 5, 7)
        $(document).on('click', '.hp-apply-even', function() {
            const $sourceRow = getSelectedSourceRow();
            if (!$sourceRow) return;

            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');

            // Target even rows: Benefits(1), Features(3), Expert(5), etc.
            const $targetRows = $allRows.filter(function(index) {
                return index % 2 === 1;
            });

            copyRowSettings($sourceRow, $targetRows);
        });

        // Refresh Sections (v2.33.38) - Re-sync section backgrounds with actual funnel sections
        $(document).on('click', '.hp-refresh-sections', function() {
            const $button = $(this);
            const postId = $('#post_ID').val();

            if (!postId) {
                alert('Unable to determine post ID. Please save the funnel first.');
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true);
            const $icon = $button.find('.dashicons');
            $icon.addClass('hp-spin');

            // Call AJAX to refresh sections
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'hp_refresh_section_backgrounds',
                    post_id: postId,
                    nonce: hpSectionBgData.refreshNonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show updated sections
                        window.location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        $button.prop('disabled', false);
                        $icon.removeClass('hp-spin');
                    }
                },
                error: function() {
                    alert('Failed to refresh sections. Please try again.');
                    $button.prop('disabled', false);
                    $icon.removeClass('hp-spin');
                }
            });
        });
    }

    /**
     * Get colors from the styling section in the DOM (v2.33.63)
     */
    function getStylingColorsFromDOM() {
        const colorFields = [
            'accent_color', 'text_color_accent', 'text_color_basic', 'text_color_note',
            'text_color_discount', 'page_bg_color', 'card_bg_color', 'input_bg_color', 'border_color'
        ];
        
        let colors = [];
        colorFields.forEach(name => {
            // Find field by data-name or data-key
            const $field = $(`.acf-field[data-name="${name}"], .acf-field[data-key="field_${name}_local"]`);
            if ($field.length) {
                // Find ANY input that contains a hex code (could be picker or text)
                const $input = $field.find('input').filter(function() {
                    const val = $(this).val();
                    return val && typeof val === 'string' && val.indexOf('#') === 0;
                }).first();

                if ($input.length) {
                    colors.push({
                        color: $input.val().toLowerCase(),
                        label: $field.find('label').first().text().replace(/[:\*]/g, '').trim() || name
                    });
                }
            }
        });
        
        if (colors.length === 0 && typeof hpSectionBgData !== 'undefined' && hpSectionBgData.stylingColors) {
            colors = hpSectionBgData.stylingColors.map(c => ({ color: c.color.toLowerCase(), label: c.label }));
        }
        return colors;
    }

    /**
     * Initialize color pickers with custom palette from styling colors (v2.33.65)
     * Robust cleanup: ensures exactly one picker exists and tooltips are applied.
     */
    function initColorPickers() {
        const stylingColors = getStylingColorsFromDOM();
        const palette = stylingColors.map(item => item.color);
        
        // Target ALL color picker fields on the page
        $('.acf-field[data-type="color_picker"]').each(function() {
            const $field = $(this);
            const $acfInput = $field.find('.acf-input').first();
            
            // Skip if already processed in this run
            if ($field.data('hp-init-v5')) return;
            $field.data('hp-init-v5', true);

            // Find the original input
            const $originalInput = $field.find('input[type="text"], input[type="hidden"]').filter(function() {
                const name = $(this).attr('name') || '';
                return name.indexOf('acf[') === 0;
            }).first();

            if (!$originalInput.length) return;

            const val = $originalInput.val();
            const name = $originalInput.attr('name');

            // Nuclear Cleanup: Clear the input container entirely
            $acfInput.empty();

            // Re-create a clean input element
            const $newInput = $('<input type="text" class="wp-color-picker" />')
                .attr('name', name)
                .val(val);

            $acfInput.append($newInput);

            // Initialize the picker
            const isBgField = name.indexOf('gradient_') !== -1;
            const pickerArgs = {
                palettes: isBgField && palette.length > 0 ? palette : true,
                change: function(event, ui) {
                    const $row = $(this).closest('.acf-row');
                    if ($row.length) updatePreview($row);
                }
            };

            $newInput.wpColorPicker(pickerArgs);
        });

        // Disable search in gradient select dropdowns (v2.33.65)
        $('[data-key="field_section_backgrounds"] select').each(function() {
            $(this).attr('data-minimum-results-for-search', 'Infinity');
            // If already initialized by select2, we need to update it
            if ($(this).data('select2')) {
                $(this).select2({ minimumResultsForSearch: Infinity });
            }
        });
    }

    /**
     * Initialize live preview updates on field changes
     */
    function initLivePreview() {
        const $repeater = $('[data-key="field_section_backgrounds"]');

        // Update preview when any relevant field changes
        $repeater.on('change', '[data-name="background_type"] select', function() {
            const $row = $(this).closest('.acf-row');
            updatePreview($row);
        });

        $repeater.on('change', '[data-name="solid_color"] input', function() {
            const $row = $(this).closest('.acf-row');
            updatePreview($row);
        });

        $repeater.on('change', '[data-name="gradient_type"] select, [data-name="gradient_preset"] select, [data-name="color_mode"] select', function() {
            const $row = $(this).closest('.acf-row');
            updatePreview($row);
        });

        $repeater.on('change', '[data-name="gradient_start_color"] input, [data-name="gradient_end_color"] input', function() {
            const $row = $(this).closest('.acf-row');
            updatePreview($row);
        });

        // Color picker widgets need special handling
        $repeater.on('irischange', 'input[type="text"]', function() {
            const $row = $(this).closest('.acf-row');
            setTimeout(function() {
                updatePreview($row);
            }, 50);
        });
    }

    /**
     * Initialize on ACF ready
     */
    if (typeof acf !== 'undefined') {
        // Inject tooltips on click (v2.33.62)

        // Inject tooltips on click (v2.33.65)
        $(document).on('click', '.wp-picker-container .wp-color-result', function() {
            const $button = $(this);
            const $container = $button.closest('.wp-picker-container');
            
            // Wait for Iris to render
            setTimeout(function() {
                const stylingColors = getStylingColorsFromDOM();
                const $paletteButtons = $container.find('.iris-palette');
                
                // If this is a background field, use the indexed matching which is most reliable
                const $input = $container.find('input.wp-color-picker');
                const name = $input.attr('name') || '';
                
                if (name.indexOf('gradient_') !== -1) {
                    $paletteButtons.each(function(index) {
                        if (stylingColors[index]) {
                            $(this).attr('title', stylingColors[index].label);
                        }
                    });
                }
            }, 100);
        });

        acf.addAction('ready', function() {
            addBulkActionButtons();
            addPreviewsAndCheckboxes();
            initBulkActions();
            initLivePreview();
            // Ensure color pickers are initialized
            setTimeout(initColorPickers, 500);
        });

        // Re-initialize when repeater rows are added
        acf.addAction('append', function($el) {
            if ($el.closest('[data-key="field_section_backgrounds"]').length) {
                addPreviewsAndCheckboxes();
                setTimeout(initColorPickers, 500);
            }
        });
    }

})(jQuery);
