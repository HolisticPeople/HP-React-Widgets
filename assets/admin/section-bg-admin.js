/**
 * Section Background Admin UI Enhancements (v2.33.19)
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
     * Add preview rectangle and checkbox to each repeater row
     */
    function addPreviewsAndCheckboxes() {
        const $repeater = $('[data-key="field_section_backgrounds"]');
        if (!$repeater.length) return;

        // Add bulk action buttons above repeater
        if (!$('.hp-bulk-actions').length) {
            const bulkActionsHTML = `
                <div class="hp-bulk-actions">
                    <label style="font-weight: 600; margin-right: 15px;">
                        Select a row to copy, then apply to:
                    </label>
                    <button type="button" class="button hp-apply-odd">Odd (Home, Science, Offers...)</button>
                    <button type="button" class="button hp-apply-even">Even (Benefits, Features, Expert...)</button>
                    <button type="button" class="button hp-apply-all">All</button>
                </div>
            `;
            $repeater.before(bulkActionsHTML);
        }

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

            // Get section name from server-side data or use default
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
