/**
 * Section Background Admin UI Enhancements (v2.33.2)
 *
 * Features:
 * - Bulk actions (Apply to Selected, Apply to All, Apply to Odd, Apply to Even)
 * - Live preview rectangles showing current background in each row
 * - Real-time updates when user changes settings
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
            const solidColor = $row.find('[data-name="solid_color"] input').val() || '#1a1a2e';
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
                // Auto mode: use solid_color as fallback
                startColor = $row.find('[data-name="solid_color"] input').val() || '#1a1a2e';
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
     * Add preview rectangle and checkbox to each repeater row
     */
    function addPreviewsAndCheckboxes() {
        const $repeater = $('[data-key="field_section_backgrounds"]');
        if (!$repeater.length) return;

        // Add bulk action buttons above repeater
        if (!$('.hp-bulk-actions').length) {
            const bulkActionsHTML = `
                <div class="hp-bulk-actions">
                    <label>
                        <input type="checkbox" class="hp-select-all" /> Select All
                    </label>
                    <button type="button" class="button hp-apply-selected">Apply to Selected</button>
                    <button type="button" class="button hp-apply-all">Apply to All</button>
                    <button type="button" class="button hp-apply-odd">Apply to Odd (1,3,5,7)</button>
                    <button type="button" class="button hp-apply-even">Apply to Even (2,4,6,8)</button>
                </div>
            `;
            $repeater.before(bulkActionsHTML);
        }

        // Add checkbox and preview to each row
        $repeater.find('.acf-row').each(function() {
            const $row = $(this);

            // Skip if already has checkbox
            if ($row.find('.hp-row-checkbox').length) return;

            // Get section label for display
            const $sectionLabel = $row.find('[data-name="section_label"] input');
            const sectionLabel = $sectionLabel.val() || '';

            // Add checkbox before section_id field
            const $firstCell = $row.find('[data-name="section_id"]').closest('td');
            $firstCell.prepend('<input type="checkbox" class="hp-row-checkbox" style="margin-right: 8px;" />');

            // Add preview after section_label field
            const $labelCell = $row.find('[data-name="section_label"]').closest('td');
            $labelCell.append('<div class="hp-section-bg-preview"></div>');

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
            solid_color: $sourceRow.find('[data-name="solid_color"] input').val(),
            gradient_type: $sourceRow.find('[data-name="gradient_type"] select').val(),
            gradient_preset: $sourceRow.find('[data-name="gradient_preset"] select').val(),
            color_mode: $sourceRow.find('[data-name="color_mode"] select').val(),
            gradient_start_color: $sourceRow.find('[data-name="gradient_start_color"] input').val(),
            gradient_end_color: $sourceRow.find('[data-name="gradient_end_color"] input').val()
        };

        $targetRows.each(function() {
            const $target = $(this);

            $target.find('[data-name="background_type"] select').val(sourceData.background_type).trigger('change');
            $target.find('[data-name="solid_color"] input').val(sourceData.solid_color).trigger('change');
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
        // Select All checkbox
        $(document).on('change', '.hp-select-all', function() {
            const checked = $(this).is(':checked');
            $('.hp-row-checkbox').prop('checked', checked);
        });

        // Apply to Selected
        $(document).on('click', '.hp-apply-selected', function() {
            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $checkedRows = $allRows.filter(function() {
                return $(this).find('.hp-row-checkbox').is(':checked');
            });

            if ($checkedRows.length === 0) {
                alert('Please select at least one section.');
                return;
            }

            // Use first row as source
            const $sourceRow = $allRows.first();

            // Copy to all checked rows except source
            const $targetRows = $checkedRows.not($sourceRow);
            copyRowSettings($sourceRow, $targetRows);
        });

        // Apply to All
        $(document).on('click', '.hp-apply-all', function() {
            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $sourceRow = $allRows.first();
            const $targetRows = $allRows.slice(1);

            copyRowSettings($sourceRow, $targetRows);
        });

        // Apply to Odd (1, 3, 5, 7 = sections 2, 4, 6, 8 in 0-indexed rows)
        $(document).on('click', '.hp-apply-odd', function() {
            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $sourceRow = $allRows.first();

            // Target rows: 2, 4, 6, 8 (0-indexed: 2, 4, 6, 8)
            const $targetRows = $allRows.filter(function(index) {
                return index === 2 || index === 4 || index === 6 || index === 8;
            });

            copyRowSettings($sourceRow, $targetRows);
        });

        // Apply to Even (2, 4, 6, 8 = sections 1, 3, 5, 7 in 0-indexed rows)
        $(document).on('click', '.hp-apply-even', function() {
            const $repeater = $('[data-key="field_section_backgrounds"]');
            const $allRows = $repeater.find('.acf-row');
            const $sourceRow = $allRows.first();

            // Target rows: 1, 3, 5, 7 (0-indexed: 1, 3, 5, 7)
            const $targetRows = $allRows.filter(function(index) {
                return index === 1 || index === 3 || index === 5 || index === 7;
            });

            copyRowSettings($sourceRow, $targetRows);
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
        acf.addAction('ready', function() {
            addPreviewsAndCheckboxes();
            initBulkActions();
            initLivePreview();
        });

        // Re-initialize when repeater rows are added
        acf.addAction('append', function($el) {
            if ($el.closest('[data-key="field_section_backgrounds"]').length) {
                addPreviewsAndCheckboxes();
            }
        });
    }

})(jQuery);
