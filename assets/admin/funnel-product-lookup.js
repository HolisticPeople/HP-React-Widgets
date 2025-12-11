/**
 * Funnel Product Lookup - Auto-fills product fields when a product is selected.
 */
(function($) {
    'use strict';

    // Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Fetch product data from REST API
    async function fetchProductData(productId) {
        const response = await fetch(
            `${hpRwAdmin.restUrl}hp-rw/v1/admin/product-lookup?product_id=${productId}`,
            {
                headers: {
                    'X-WP-Nonce': hpRwAdmin.nonce,
                },
            }
        );
        
        if (!response.ok) {
            throw new Error('Failed to fetch product');
        }
        
        return response.json();
    }

    // Auto-fill fields in a product row
    function autoFillProductRow($row, productData) {
        const product = productData.product;
        
        // Fill SKU
        const $skuField = $row.find('.hp-product-sku input[type="text"]');
        if ($skuField.length && !$skuField.val()) {
            $skuField.val(product.sku);
        }
        
        // Fill name
        const $nameField = $row.find('.hp-product-name input[type="text"]');
        if ($nameField.length && !$nameField.val()) {
            $nameField.val(product.name);
        }
        
        // Fill price
        const $priceField = $row.find('.hp-product-price input[type="number"]');
        if ($priceField.length && !$priceField.val()) {
            $priceField.val(product.price);
        }
        
        // Fill image (using ACF's image field)
        if (product.image_id) {
            const $imageField = $row.find('.hp-product-image');
            const $imageInput = $imageField.find('input[type="hidden"]');
            const $imagePreview = $imageField.find('.image-wrap');
            
            // Only set if no image is currently selected
            if ($imageInput.length && !$imageInput.val()) {
                // ACF image fields are complex - we'll set the hidden input
                // and let ACF handle the preview on next page load
                // For immediate feedback, show a placeholder
                $imageInput.val(product.image_id);
                
                // Try to update the preview
                if (product.image_url && $imagePreview.length === 0) {
                    // Create a simple preview
                    $imageField.find('.acf-image-uploader').prepend(
                        `<div class="image-wrap" style="margin-bottom: 10px;">
                            <img src="${product.image_url}" alt="" style="max-width: 100px; height: auto;">
                            <p class="description" style="font-size: 11px; color: #666;">
                                Image auto-filled. Save to confirm.
                            </p>
                        </div>`
                    );
                }
            }
        }
        
        // Show success feedback
        showFeedback($row, `Loaded: ${product.name} (${product.sku}) - $${product.price}`);
    }

    // Show feedback message
    function showFeedback($row, message, isError = false) {
        // Remove any existing feedback
        $row.find('.hp-product-feedback').remove();
        
        const $feedback = $(`
            <div class="hp-product-feedback" style="
                padding: 8px 12px;
                margin: 5px 0 10px;
                border-radius: 4px;
                font-size: 13px;
                ${isError 
                    ? 'background: #fcf0f1; border-left: 4px solid #d63638; color: #d63638;' 
                    : 'background: #edfaef; border-left: 4px solid #00a32a; color: #00713a;'
                }
            ">
                ${message}
            </div>
        `);
        
        $row.find('.hp-product-lookup').after($feedback);
        
        // Auto-remove after 5 seconds
        setTimeout(() => $feedback.fadeOut(300, function() { $(this).remove(); }), 5000);
    }

    // Handle product selection change
    function handleProductChange($selectField) {
        const productId = $selectField.val();
        const $row = $selectField.closest('.acf-row');
        
        if (!productId) {
            return;
        }
        
        // Show loading state
        $selectField.prop('disabled', true);
        showFeedback($row, 'Loading product data...');
        
        fetchProductData(productId)
            .then(data => {
                if (data.success) {
                    autoFillProductRow($row, data);
                } else {
                    showFeedback($row, data.error || 'Failed to load product', true);
                }
            })
            .catch(error => {
                console.error('Product lookup error:', error);
                showFeedback($row, 'Error loading product data', true);
            })
            .finally(() => {
                $selectField.prop('disabled', false);
            });
    }

    // Initialize on document ready
    $(document).ready(function() {
        // Check if we're on a funnel edit page
        if (typeof hpRwAdmin === 'undefined') {
            return;
        }
        
        // Use event delegation to handle dynamically added rows
        $(document).on('change', '.hp-product-lookup select', function() {
            handleProductChange($(this));
        });
        
        // Also handle Select2 change events (ACF uses Select2)
        $(document).on('select2:select', '.hp-product-lookup select', function() {
            handleProductChange($(this));
        });
        
        console.log('HP Funnel Product Lookup initialized');
    });

})(jQuery);

