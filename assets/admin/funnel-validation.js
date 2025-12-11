/**
 * HP Funnel ACF Validation Enhancement
 * 
 * Adds visual indicators to ACF tabs when they contain validation errors.
 * Also improves the error message to show specific field names.
 */
(function($) {
    'use strict';

    if (typeof acf === 'undefined') {
        return;
    }

    /**
     * Initialize validation enhancement on document ready
     */
    $(document).ready(function() {
        // Only run on hp-funnel post type
        if ($('body').hasClass('post-type-hp-funnel')) {
            initValidationEnhancement();
        }
    });

    /**
     * Initialize validation enhancement
     */
    function initValidationEnhancement() {
        // Hook into ACF validation
        acf.addAction('validation_failure', onValidationFailure);
        acf.addAction('validation_success', onValidationSuccess);
        
        // Also check on form submit attempt
        acf.addAction('invalid_field', markTabWithError);
        acf.addAction('valid_field', clearFieldError);
        
        // Clear tab markers when switching tabs
        acf.addAction('show_tab', function() {
            // Small delay to let ACF render
            setTimeout(checkAllTabErrors, 100);
        });
    }

    /**
     * Called when validation fails
     */
    function onValidationFailure($form) {
        clearAllTabMarkers();
        
        // Find all fields with errors
        var $errorFields = $form.find('.acf-field.acf-error');
        var tabsWithErrors = new Set();
        var fieldNames = [];
        
        $errorFields.each(function() {
            var $field = $(this);
            var fieldLabel = $field.find('> .acf-label label').first().text().trim();
            
            if (fieldLabel) {
                fieldNames.push(fieldLabel);
            }
            
            // Find the parent tab
            var $tabContent = $field.closest('.acf-tab-wrap').prev('.acf-field-tab');
            if ($tabContent.length === 0) {
                // Try finding by tab group
                var $tabGroup = $field.closest('.acf-fields');
                var tabIndex = findTabIndexForField($field);
                if (tabIndex !== null) {
                    tabsWithErrors.add(tabIndex);
                }
            }
            
            // Also try by data attribute
            var $parentTab = findParentTab($field);
            if ($parentTab) {
                markTabAsError($parentTab);
            }
        });
        
        // Update the error message with field names
        updateErrorMessage(fieldNames);
        
        // Scroll to first error
        if ($errorFields.length > 0) {
            $('html, body').animate({
                scrollTop: $errorFields.first().offset().top - 100
            }, 300);
        }
    }

    /**
     * Called when validation succeeds
     */
    function onValidationSuccess($form) {
        clearAllTabMarkers();
        clearEnhancedErrorMessage();
    }

    /**
     * Mark a tab as having an error
     */
    function markTabWithError(field) {
        var $field = field.$el || $(field);
        var $parentTab = findParentTab($field);
        
        if ($parentTab) {
            markTabAsError($parentTab);
        }
    }

    /**
     * Clear error marker when field becomes valid
     */
    function clearFieldError(field) {
        setTimeout(checkAllTabErrors, 100);
    }

    /**
     * Find the parent tab for a field
     */
    function findParentTab($field) {
        // Walk up to find the tab this field belongs to
        var $current = $field;
        var $allTabs = $('.acf-tab-button');
        
        // Find which tab group this field is in
        while ($current.length && !$current.hasClass('acf-postbox')) {
            // Check if we hit a tab wrap boundary
            if ($current.prev('.acf-field-tab').length) {
                var tabKey = $current.prev('.acf-field-tab').data('key');
                return $allTabs.filter('[data-key="' + tabKey + '"]');
            }
            $current = $current.parent();
        }
        
        // Fallback: find by iterating tabs and checking visibility
        var fieldKey = $field.data('key');
        var $activeTab = null;
        
        $allTabs.each(function() {
            var $tab = $(this);
            var $tabContent = getTabContent($tab);
            
            if ($tabContent.find('[data-key="' + fieldKey + '"]').length > 0) {
                $activeTab = $tab;
                return false;
            }
        });
        
        return $activeTab;
    }

    /**
     * Get the content area for a tab
     */
    function getTabContent($tab) {
        var tabKey = $tab.data('key');
        var $tabField = $('.acf-field-tab[data-key="' + tabKey + '"]');
        
        // Get all siblings until next tab
        var $content = $();
        var $next = $tabField.nextAll().each(function() {
            var $el = $(this);
            if ($el.hasClass('acf-field-tab')) {
                return false; // Stop at next tab
            }
            $content = $content.add($el);
        });
        
        return $content;
    }

    /**
     * Find tab index for a field
     */
    function findTabIndexForField($field) {
        var $tabs = $('.acf-tab-button');
        var $fieldParent = $field.parent();
        
        // Check each tab's content
        var foundIndex = null;
        $tabs.each(function(index) {
            var $tab = $(this);
            var $tabContent = getTabContent($tab);
            
            if ($tabContent.find($field).length > 0) {
                foundIndex = index;
                return false;
            }
        });
        
        return foundIndex;
    }

    /**
     * Mark a tab button as having errors
     */
    function markTabAsError($tab) {
        if (!$tab || !$tab.length) return;
        
        $tab.addClass('hp-tab-has-error');
        
        // Add error badge if not present
        if ($tab.find('.hp-tab-error-badge').length === 0) {
            $tab.append('<span class="hp-tab-error-badge" title="This tab has validation errors">!</span>');
        }
    }

    /**
     * Clear all tab error markers
     */
    function clearAllTabMarkers() {
        $('.acf-tab-button').removeClass('hp-tab-has-error');
        $('.hp-tab-error-badge').remove();
    }

    /**
     * Check all tabs for errors and update markers
     */
    function checkAllTabErrors() {
        clearAllTabMarkers();
        
        $('.acf-field.acf-error').each(function() {
            var $parentTab = findParentTab($(this));
            if ($parentTab) {
                markTabAsError($parentTab);
            }
        });
    }

    /**
     * Update the error message with specific field names
     */
    function updateErrorMessage(fieldNames) {
        // Remove any existing enhanced message
        clearEnhancedErrorMessage();
        
        if (fieldNames.length === 0) return;
        
        // Find ACF's error message
        var $errorNotice = $('.acf-admin-notice.-error, .notice-error').first();
        
        if ($errorNotice.length) {
            var detailsHtml = '<div class="hp-validation-details" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.2);">';
            detailsHtml += '<strong>Fields requiring attention:</strong><ul style="margin: 5px 0 0 20px; list-style: disc;">';
            
            fieldNames.forEach(function(name) {
                detailsHtml += '<li>' + escapeHtml(name) + '</li>';
            });
            
            detailsHtml += '</ul></div>';
            
            $errorNotice.find('p').first().after(detailsHtml);
        }
    }

    /**
     * Clear enhanced error message
     */
    function clearEnhancedErrorMessage() {
        $('.hp-validation-details').remove();
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Add styles for error indicators
     */
    function addStyles() {
        if ($('#hp-funnel-validation-styles').length) return;
        
        var css = `
            .acf-tab-button.hp-tab-has-error {
                position: relative;
            }
            .acf-tab-button.hp-tab-has-error a {
                color: #d63638 !important;
                border-color: #d63638 !important;
            }
            .hp-tab-error-badge {
                position: absolute;
                top: -5px;
                right: -5px;
                background: #d63638;
                color: white;
                width: 16px;
                height: 16px;
                border-radius: 50%;
                font-size: 11px;
                font-weight: bold;
                display: flex;
                align-items: center;
                justify-content: center;
                line-height: 1;
            }
            .hp-validation-details {
                font-size: 13px;
            }
            .hp-validation-details ul {
                color: inherit;
            }
            .hp-validation-details li {
                margin-bottom: 2px;
            }
        `;
        
        $('<style id="hp-funnel-validation-styles">' + css + '</style>').appendTo('head');
    }

    // Add styles immediately
    addStyles();

})(jQuery);

