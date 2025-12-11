/**
 * Funnel Product Lookup - Search and auto-fill product fields.
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

    // Search products via REST API
    async function searchProducts(query) {
        const response = await fetch(
            `${hpRwAdmin.restUrl}hp-rw/v1/admin/product-search?search=${encodeURIComponent(query)}`,
            {
                headers: {
                    'X-WP-Nonce': hpRwAdmin.nonce,
                },
            }
        );
        
        if (!response.ok) {
            throw new Error('Search failed');
        }
        
        return response.json();
    }

    // Create search results dropdown
    function showSearchResults($searchField, products) {
        // Remove existing dropdown
        hideSearchResults($searchField);
        
        if (products.length === 0) {
            return;
        }

        const $dropdown = $(`
            <div class="hp-product-search-results" style="
                position: absolute;
                z-index: 10000;
                background: white;
                border: 1px solid #ddd;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                max-height: 300px;
                overflow-y: auto;
                width: 100%;
                margin-top: 2px;
            "></div>
        `);

        products.forEach(product => {
            const $item = $(`
                <div class="hp-product-search-item" data-product='${JSON.stringify(product).replace(/'/g, "&#39;")}' style="
                    padding: 10px 12px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                ">
                    ${product.image_url 
                        ? `<img src="${product.image_url}" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">` 
                        : '<div style="width: 40px; height: 40px; background: #f0f0f0; border-radius: 4px;"></div>'
                    }
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${escapeHtml(product.name)}
                        </div>
                        <div style="font-size: 12px; color: #666;">
                            SKU: ${escapeHtml(product.sku)} · $${product.price.toFixed(2)}
                            ${!product.in_stock ? '<span style="color: #d63638;"> (Out of stock)</span>' : ''}
                        </div>
                    </div>
                </div>
            `);
            
            $item.on('click', function() {
                selectProduct($searchField, product);
            });
            
            $item.on('mouseenter', function() {
                $(this).css('background', '#f7f7f7');
            }).on('mouseleave', function() {
                $(this).css('background', 'white');
            });
            
            $dropdown.append($item);
        });

        // Position relative to search field
        const $wrapper = $searchField.closest('.acf-input');
        $wrapper.css('position', 'relative');
        $wrapper.append($dropdown);
    }

    // Hide search results dropdown
    function hideSearchResults($searchField) {
        $searchField.closest('.acf-input').find('.hp-product-search-results').remove();
    }

    // Select a product and fill the row
    function selectProduct($searchField, product) {
        const $row = $searchField.closest('.acf-row');
        
        // Clear search field and hide results
        $searchField.val('');
        hideSearchResults($searchField);
        
        // Fill the product fields
        fillProductFields($row, product);
        
        // Show feedback
        showFeedback($row, `✓ Selected: ${product.name} (${product.sku})`);
    }

    // Fill product fields in a row
    function fillProductFields($row, product) {
        // Find fields by data-name attribute (ACF standard)
        const fieldMap = {
            'sku': product.sku,
            'display_name': product.name,
            'display_price': product.price,
        };

        Object.entries(fieldMap).forEach(([fieldName, value]) => {
            const $field = $row.find(`[data-name="${fieldName}"] input`);
            if ($field.length) {
                $field.val(value).trigger('change');
            }
        });

        // Handle image field specially
        if (product.image_id) {
            const $imageField = $row.find('[data-name="image"]');
            if ($imageField.length) {
                const $input = $imageField.find('input[type="hidden"]').first();
                if ($input.length && !$input.val()) {
                    $input.val(product.image_id).trigger('change');
                    
                    // Show preview
                    if (product.image_url) {
                        const $uploader = $imageField.find('.acf-image-uploader');
                        if ($uploader.find('.image-wrap').length === 0) {
                            $uploader.addClass('has-value').prepend(`
                                <div class="image-wrap" style="margin-bottom: 10px;">
                                    <img src="${product.image_url}" alt="" style="max-width: 150px; height: auto; border-radius: 4px;">
                                </div>
                            `);
                        }
                    }
                }
            }
        }
    }

    // Show feedback message
    function showFeedback($row, message, isError = false) {
        $row.find('.hp-product-feedback').remove();
        
        const $feedback = $(`
            <div class="hp-product-feedback" style="
                padding: 8px 12px;
                margin: 8px 0;
                border-radius: 4px;
                font-size: 13px;
                ${isError 
                    ? 'background: #fcf0f1; border-left: 4px solid #d63638; color: #d63638;' 
                    : 'background: #edfaef; border-left: 4px solid #00a32a; color: #00713a;'
                }
            ">${escapeHtml(message)}</div>
        `);
        
        $row.find('[data-name="product_search"]').after($feedback);
        
        setTimeout(() => $feedback.fadeOut(300, function() { $(this).remove(); }), 4000);
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Debounced search handler
    const debouncedSearch = debounce(function($input) {
        const query = $input.val().trim();
        
        if (query.length < 2) {
            hideSearchResults($input);
            return;
        }

        // Show loading indicator
        $input.css('background-image', 'url(data:image/svg+xml,...)')  // Could add spinner

        searchProducts(query)
            .then(data => {
                if (data.success && data.products.length > 0) {
                    showSearchResults($input, data.products);
                } else {
                    hideSearchResults($input);
                    if (query.length >= 2) {
                        showFeedback($input.closest('.acf-row'), 'No products found for "' + query + '"', true);
                    }
                }
            })
            .catch(error => {
                console.error('Product search error:', error);
                hideSearchResults($input);
            });
    }, 300);

    // Initialize on document ready
    $(document).ready(function() {
        if (typeof hpRwAdmin === 'undefined') {
            return;
        }
        
        // Handle typing in product search field
        $(document).on('input', '[data-name="product_search"] input[type="text"]', function() {
            debouncedSearch($(this));
        });

        // Handle focus out - hide results after a delay
        $(document).on('focusout', '[data-name="product_search"] input[type="text"]', function() {
            const $input = $(this);
            setTimeout(() => hideSearchResults($input), 200);
        });

        // Handle Enter key to prevent form submission
        $(document).on('keydown', '[data-name="product_search"] input[type="text"]', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Close dropdowns when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hp-product-search-results, [data-name="product_search"]').length) {
                $('.hp-product-search-results').remove();
            }
        });
        
        console.log('HP Funnel Product Search initialized');
    });

})(jQuery);
