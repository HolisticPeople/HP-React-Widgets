/**
 * Offer Calculator and Product Search for Admin
 * 
 * - Product search with autocomplete for SKU fields
 * - Display selected product info (name, image, price)
 * - Calculate discount savings for customizable kits in real-time
 */
(function($) {
    'use strict';

    // Cache for product data
    const productCache = {};

    /**
     * Debounce helper
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return '$' + (amount || 0).toFixed(2);
    }

    // ========================================
    // PRODUCT SEARCH
    // ========================================

    /**
     * Search products via REST API
     */
    async function searchProducts(query) {
        const response = await fetch(
            `${hpOfferCalc.restUrl}admin/product-search?search=${encodeURIComponent(query)}`,
            {
                headers: {
                    'X-WP-Nonce': hpOfferCalc.nonce,
                },
            }
        );
        
        if (!response.ok) {
            throw new Error('Search failed');
        }
        
        return response.json();
    }

    /**
     * Fetch product by SKU
     */
    async function fetchProductBySku(sku) {
        if (!sku) return null;
        
        // Check cache
        if (productCache[sku]) {
            return productCache[sku];
        }

        try {
            const response = await fetch(
                `${hpOfferCalc.restUrl}admin/product-search?sku=${encodeURIComponent(sku)}`,
                {
                    headers: {
                        'X-WP-Nonce': hpOfferCalc.nonce,
                    },
                }
            );
            
            if (!response.ok) return null;
            
            const data = await response.json();
            if (data.success && data.products && data.products.length > 0) {
                const product = data.products[0];
                productCache[sku] = product;
                return product;
            }
        } catch (e) {
            console.error('Error fetching product:', e);
        }
        
        return null;
    }

    /**
     * Show search results dropdown
     */
    function showSearchResults($searchField, products) {
        hideSearchResults($searchField);
        
        if (products.length === 0) return;

        const $wrapper = $searchField.closest('.acf-input');
        $wrapper.css('position', 'relative');

        const $dropdown = $(`
            <div class="hp-product-search-results" style="
                position: absolute;
                z-index: 99999;
                background: white;
                border: 1px solid #ddd;
                border-radius: 6px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                max-height: 320px;
                overflow-y: auto;
                width: 100%;
                margin-top: 4px;
            "></div>
        `);

        products.forEach(product => {
            const $item = $(`
                <div class="hp-product-search-item" style="
                    padding: 12px;
                    cursor: pointer;
                    border-bottom: 1px solid #f0f0f0;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    transition: background 0.15s;
                ">
                    ${product.image_url 
                        ? `<img src="${escapeHtml(product.image_url)}" alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 4px; flex-shrink: 0;">` 
                        : '<div style="width: 45px; height: 45px; background: #f0f0f0; border-radius: 4px; flex-shrink: 0;"></div>'
                    }
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; color: #1e1e1e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            ${escapeHtml(product.name)}
                        </div>
                        <div style="font-size: 12px; color: #757575; margin-top: 2px;">
                            SKU: <strong>${escapeHtml(product.sku)}</strong>
                        </div>
                    </div>
                    <div style="text-align: right; flex-shrink: 0;">
                        <div style="font-weight: 600; color: #00a32a;">${formatCurrency(product.price)}</div>
                        ${!product.in_stock ? '<div style="font-size: 11px; color: #d63638;">Out of stock</div>' : ''}
                    </div>
                </div>
            `);
            
            $item.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                selectProduct($searchField, product);
            });
            
            $item.on('mouseenter', function() {
                $(this).css('background', '#f7f7f7');
            }).on('mouseleave', function() {
                $(this).css('background', 'white');
            });
            
            $dropdown.append($item);
        });

        $wrapper.append($dropdown);
    }

    /**
     * Hide search results
     */
    function hideSearchResults($searchField) {
        if ($searchField && $searchField.length) {
            $searchField.closest('.acf-input').find('.hp-product-search-results').remove();
        } else {
            $('.hp-product-search-results').remove();
        }
    }

    /**
     * Select a product from search results
     */
    function selectProduct($searchField, product) {
        const $row = $searchField.closest('.acf-row');
        
        // Clear search and hide dropdown
        $searchField.val('');
        hideSearchResults($searchField);
        
        // Cache the product
        productCache[product.sku] = product;
        
        // Find and fill the SKU field
        // Try different possible field names
        const skuFields = ['sku', 'single_product_sku'];
        let $skuField = null;
        
        for (const fieldName of skuFields) {
            $skuField = $row.find(`[data-name="${fieldName}"] input`);
            if ($skuField.length) break;
        }
        
        if ($skuField && $skuField.length) {
            $skuField.val(product.sku).trigger('change');
        }
        
        // Update the product display
        updateProductDisplay($row, product);
    }

    /**
     * Update product display area
     */
    function updateProductDisplay($row, product) {
        const $displayContainer = $row.find('.hp-selected-product-display');
        if (!$displayContainer.length) return;

        if (!product) {
            $displayContainer.html('');
            return;
        }

        $displayContainer.html(`
            <div class="hp-product-display">
                ${product.image_url 
                    ? `<img src="${escapeHtml(product.image_url)}" alt="">` 
                    : '<div style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 4px;"></div>'
                }
                <div class="product-info">
                    <div class="product-name">${escapeHtml(product.name)}</div>
                    <div class="product-sku">SKU: ${escapeHtml(product.sku)}</div>
                </div>
                <div class="product-price">${formatCurrency(product.price)}</div>
                <span class="hp-product-remove" title="Clear selection">✕</span>
            </div>
        `);

        // Handle remove click
        $displayContainer.find('.hp-product-remove').on('click', function(e) {
            e.preventDefault();
            clearProductSelection($row);
        });
    }

    /**
     * Clear product selection
     */
    function clearProductSelection($row) {
        // Clear SKU field
        const skuFields = ['sku', 'single_product_sku'];
        for (const fieldName of skuFields) {
            const $field = $row.find(`[data-name="${fieldName}"] input`);
            if ($field.length) {
                $field.val('').trigger('change');
            }
        }
        
        // Clear display
        $row.find('.hp-selected-product-display').html('');
    }

    /**
     * Load and display product info for existing SKU values
     */
    async function loadExistingProducts() {
        // Find all SKU fields that have values
        const $skuFields = $('[data-name="sku"] input, [data-name="single_product_sku"] input');
        
        for (const field of $skuFields) {
            const $field = $(field);
            const sku = $field.val();
            if (!sku) continue;
            
            const $row = $field.closest('.acf-row');
            const product = await fetchProductBySku(sku);
            
            if (product) {
                updateProductDisplay($row, product);
            }
        }
    }

    // Debounced search
    const debouncedSearch = debounce(function($input) {
        const query = $input.val().trim();
        
        if (query.length < 2) {
            hideSearchResults($input);
            return;
        }

        searchProducts(query)
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    showSearchResults($input, data.products);
                } else {
                    hideSearchResults($input);
                }
            })
            .catch(error => {
                console.error('Product search error:', error);
                hideSearchResults($input);
            });
    }, 300);

    // ========================================
    // KIT DISCOUNT CALCULATOR
    // ========================================

    /**
     * Fetch product prices by SKUs
     */
    async function fetchProductPrices(skus) {
        const uncachedSkus = skus.filter(sku => !productCache[sku]);
        
        if (uncachedSkus.length > 0) {
            try {
                const response = await $.ajax({
                    url: hpOfferCalc.restUrl + 'products/prices',
                    method: 'POST',
                    headers: {
                        'X-WP-Nonce': hpOfferCalc.nonce
                    },
                    data: JSON.stringify({ skus: uncachedSkus }),
                    contentType: 'application/json'
                });

                if (response && response.prices) {
                    Object.entries(response.prices).forEach(([sku, price]) => {
                        if (!productCache[sku]) {
                            productCache[sku] = { sku, price };
                        } else {
                            productCache[sku].price = price;
                        }
                    });
                }
            } catch (error) {
                console.error('Error fetching product prices:', error);
            }
        }

        const result = {};
        skus.forEach(sku => {
            result[sku] = productCache[sku]?.price || 0;
        });
        return result;
    }

    /**
     * Calculate and update kit discount display
     */
    async function calculateKitDiscount($row) {
        const offerType = $row.find('[data-key="field_offer_type"] select').val();
        if (offerType !== 'customizable_kit') return;

        const $calculator = $row.find('#hp-kit-calculator');
        if (!$calculator.length) return;

        // Collect kit products
        const products = [];
        $row.find('[data-key="field_kit_products"] .acf-row:not(.acf-clone)').each(function() {
            const $productRow = $(this);
            const sku = $productRow.find('[data-name="sku"] input').val();
            const role = $productRow.find('[data-name="role"] select').val();
            const qty = parseInt($productRow.find('[data-name="qty"] input').val()) || 0;
            const discountType = $productRow.find('[data-name="discount_type"] select').val();
            const discountValue = parseFloat($productRow.find('[data-name="discount_value"] input').val()) || 0;
            
            if (sku && (role === 'must' || role === 'default') && qty > 0) {
                products.push({ sku, qty, discountType, discountValue });
            }
        });

        if (products.length === 0) {
            updateCalculatorDisplay($calculator, 0, 0, 0);
            return;
        }

        // Get global kit discount
        const globalDiscountType = $row.find('[data-key="field_offer_discount_type"] select').val();
        const globalDiscountValue = parseFloat($row.find('[data-key="field_offer_discount_value"] input').val()) || 0;

        // Fetch prices
        const prices = await fetchProductPrices(products.map(p => p.sku));
        
        let originalTotal = 0;
        let afterProductDiscounts = 0;

        products.forEach(product => {
            const price = prices[product.sku] || 0;
            originalTotal += price * product.qty;

            let discountedPrice = price;
            if (product.discountType === 'percent' && product.discountValue > 0) {
                discountedPrice = price * (1 - product.discountValue / 100);
            } else if (product.discountType === 'fixed' && product.discountValue > 0) {
                discountedPrice = Math.max(0, price - product.discountValue);
            }
            
            afterProductDiscounts += discountedPrice * product.qty;
        });

        // Apply global discount
        let finalPrice = afterProductDiscounts;
        if (globalDiscountType === 'percent' && globalDiscountValue > 0) {
            finalPrice = afterProductDiscounts * (1 - globalDiscountValue / 100);
        } else if (globalDiscountType === 'fixed' && globalDiscountValue > 0) {
            finalPrice = Math.max(0, afterProductDiscounts - globalDiscountValue);
        }

        updateCalculatorDisplay($calculator, originalTotal, afterProductDiscounts, finalPrice);
    }

    /**
     * Update calculator display
     */
    function updateCalculatorDisplay($calculator, originalTotal, afterProductDiscounts, finalPrice) {
        const savings = originalTotal - finalPrice;
        const savingsPercent = originalTotal > 0 ? (savings / originalTotal * 100) : 0;

        $calculator.find('#calc-original').text(formatCurrency(originalTotal));
        $calculator.find('#calc-after-products').text(formatCurrency(afterProductDiscounts));
        $calculator.find('#calc-final').text(formatCurrency(finalPrice));
        $calculator.find('#calc-savings')
            .text(formatCurrency(savings) + ' (' + savingsPercent.toFixed(1) + '%)')
            .css('color', savingsPercent >= 10 ? '#00a32a' : '#1d2327');
    }

    // Debounced calculator
    const debouncedCalculate = debounce(function($row) {
        calculateKitDiscount($row);
    }, 300);

    // ========================================
    // INITIALIZATION
    // ========================================

    function init() {
        if (typeof hpOfferCalc === 'undefined') {
            console.warn('hpOfferCalc not defined');
            return;
        }

        // Load existing products
        setTimeout(loadExistingProducts, 500);

        // Product search - handle input
        $(document).on('input', '[data-name="product_search"] input, [data-name="single_product_search"] input', function() {
            debouncedSearch($(this));
        });

        // Hide results on blur (with delay for click)
        $(document).on('blur', '[data-name="product_search"] input, [data-name="single_product_search"] input', function() {
            const $input = $(this);
            setTimeout(() => hideSearchResults($input), 200);
        });

        // Prevent form submit on Enter
        $(document).on('keydown', '[data-name="product_search"] input, [data-name="single_product_search"] input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Close dropdowns when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hp-product-search-results, [data-name="product_search"], [data-name="single_product_search"]').length) {
                hideSearchResults();
            }
        });

        // Kit calculator - watch for changes
        $(document).on('change', '[data-key="field_kit_products"] input, [data-key="field_kit_products"] select', function() {
            const $row = $(this).closest('[data-key="field_funnel_offers"] > .acf-input > .acf-repeater > .acf-row');
            if ($row.length) {
                debouncedCalculate($row);
            }
        });

        $(document).on('change', '[data-key="field_offer_discount_type"], [data-key="field_offer_discount_value"]', function() {
            const $row = $(this).closest('[data-key="field_funnel_offers"] > .acf-input > .acf-repeater > .acf-row');
            if ($row.length) {
                debouncedCalculate($row);
            }
        });

        // When SKU field changes (e.g., manually typed), load product info
        $(document).on('change', '[data-name="sku"] input, [data-name="single_product_sku"] input', async function() {
            const $field = $(this);
            const sku = $field.val();
            const $row = $field.closest('.acf-row');
            
            if (sku) {
                const product = await fetchProductBySku(sku);
                if (product) {
                    updateProductDisplay($row, product);
                }
            } else {
                updateProductDisplay($row, null);
            }
        });

        // ACF repeater row added - refresh product displays
        if (typeof acf !== 'undefined') {
            acf.addAction('append', function($el) {
                // Clear any product display in new rows
                $el.find('.hp-selected-product-display').html('');
            });
        }

        console.log('HP Offer Calculator initialized');
    }

    $(document).ready(init);

})(jQuery);
