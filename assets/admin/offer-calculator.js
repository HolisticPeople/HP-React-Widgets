/**
 * Offer Admin - Product Search & Display
 */
(function($) {
    'use strict';

    const cache = {};

    function debounce(fn, wait) {
        let t;
        return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function fmt(n) {
        return '$' + (parseFloat(n) || 0).toFixed(2);
    }

    // ========================================
    // PRODUCT SEARCH API
    // ========================================

    async function searchProducts(query) {
        const res = await fetch(
            `${hpOfferCalc.restUrl}admin/product-search?search=${encodeURIComponent(query)}`,
            { headers: { 'X-WP-Nonce': hpOfferCalc.nonce } }
        );
        if (!res.ok) throw new Error('Search failed');
        return res.json();
    }

    async function fetchProductBySku(sku) {
        if (!sku) return null;
        if (cache[sku]) return cache[sku];
        
        try {
            const res = await fetch(
                `${hpOfferCalc.restUrl}admin/product-search?sku=${encodeURIComponent(sku)}`,
                { headers: { 'X-WP-Nonce': hpOfferCalc.nonce } }
            );
            if (!res.ok) return null;
            const data = await res.json();
            if (data.success && data.products?.length) {
                cache[sku] = data.products[0];
                return data.products[0];
            }
        } catch (e) {
            console.error('Fetch product error:', e);
        }
        return null;
    }

    // ========================================
    // SEARCH DROPDOWN
    // ========================================

    function showDropdown($input, products) {
        hideDropdown($input);
        if (!products.length) return;

        const $wrap = $input.closest('.acf-input');
        $wrap.css('position', 'relative');

        const $dd = $('<div class="hp-search-dropdown"></div>').css({
            position: 'absolute',
            zIndex: 99999,
            background: '#fff',
            border: '1px solid #ddd',
            borderRadius: '6px',
            boxShadow: '0 4px 16px rgba(0,0,0,0.12)',
            maxHeight: '280px',
            overflowY: 'auto',
            width: '100%',
            marginTop: '4px'
        });

        products.forEach(p => {
            const $item = $(`
                <div class="hp-search-item" style="display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; border-bottom:1px solid #f0f0f0;">
                    ${p.image_url ? `<img src="${esc(p.image_url)}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">` : '<div style="width:40px; height:40px; background:#f0f0f0; border-radius:4px;"></div>'}
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${esc(p.name)}</div>
                        <div style="font-size:12px; color:#666;">SKU: ${esc(p.sku)}</div>
                    </div>
                    <div style="font-weight:600; color:#00a32a;">${fmt(p.price)}</div>
                </div>
            `);
            $item.on('click', () => selectProduct($input, p));
            $item.on('mouseenter', function() { $(this).css('background', '#f8f8f8'); });
            $item.on('mouseleave', function() { $(this).css('background', '#fff'); });
            $dd.append($item);
        });

        $wrap.append($dd);
    }

    function hideDropdown($input) {
        if ($input?.length) {
            $input.closest('.acf-input').find('.hp-search-dropdown').remove();
        } else {
            $('.hp-search-dropdown').remove();
        }
    }

    // ========================================
    // FIND SKU FIELD IN ROW
    // ========================================
    
    function findSkuField($row) {
        // Try different field patterns - fields are text type (hidden via CSS)
        let $field = $row.find('[data-name="single_product_sku"] input');
        if ($field.length) {
            console.log('[HP Offer] Found SKU via data-name single_product_sku');
            return $field;
        }
        
        $field = $row.find('[data-name="sku"] input');
        if ($field.length) {
            console.log('[HP Offer] Found SKU via data-name sku');
            return $field;
        }
        
        // Fallback: any input with sku in name
        $field = $row.find('input[name*="[single_product_sku]"]');
        if ($field.length) {
            console.log('[HP Offer] Found SKU via name contains single_product_sku');
            return $field;
        }
        
        $field = $row.find('input[name*="[sku]"]').not('[name*="[product_search]"]');
        if ($field.length) {
            console.log('[HP Offer] Found SKU via name contains sku');
            return $field;
        }
        
        console.warn('[HP Offer] SKU field not found');
        return $();
    }
    
    function findQtyField($row) {
        let $field = $row.find('[data-name="single_product_qty"] input');
        if ($field.length) return $field;
        
        $field = $row.find('[data-name="qty"] input');
        if ($field.length) return $field;
        
        $field = $row.find('input[name*="[single_product_qty]"]');
        if ($field.length) return $field;
        
        $field = $row.find('input[name*="[qty]"]').not('.hp-qty-input');
        if ($field.length) return $field;
        
        return $();
    }
    
    function findProductContainer($row) {
        let $container = $row.find('.hp-single-product-container');
        if ($container.length) return $container;
        
        $container = $row.find('.hp-bundle-product-container');
        if ($container.length) return $container;
        
        $container = $row.find('.hp-kit-product-container');
        if ($container.length) return $container;
        
        return $();
    }

    // ========================================
    // PRODUCT SELECTION
    // ========================================

    function selectProduct($input, product) {
        const $row = $input.closest('.acf-row');
        $input.val('');
        hideDropdown($input);
        cache[product.sku] = product;

        // Set SKU field
        const $skuField = findSkuField($row);
        console.log('[HP Offer] Setting SKU field:', {
            found: $skuField.length > 0,
            fieldName: $skuField.attr('name'),
            sku: product.sku
        });
        
        if ($skuField.length) {
            $skuField.val(product.sku).trigger('change').trigger('input');
            console.log('[HP Offer] SKU field value after set:', $skuField.val());
        } else {
            console.error('[HP Offer] SKU field not found in row!');
            // Debug: show all inputs in the row
            $row.find('input').each(function() {
                console.log('[HP Offer] Input found:', $(this).attr('name'), $(this).attr('type'));
            });
        }

        // Get qty
        const $qtyField = findQtyField($row);
        const qty = parseInt($qtyField.val()) || 1;

        // Show product card
        showProductCard($row, product, qty);
    }

    function showProductCard($row, product, qty = 1) {
        const $container = findProductContainer($row);
        if (!$container.length) return;

        const isKit = $container.hasClass('hp-kit-product-container');
        
        $container.html(`
            <div class="hp-product-card">
                ${product.image_url ? `<img src="${esc(product.image_url)}" alt="">` : '<div style="width:48px; height:48px; background:#f0f0f0; border-radius:4px;"></div>'}
                <div class="hp-product-info">
                    <div class="hp-product-name">${esc(product.name)}</div>
                    <div class="hp-product-sku">SKU: ${esc(product.sku)}</div>
                </div>
                ${!isKit ? `
                <div class="hp-product-qty">
                    <span>Qty:</span>
                    <input type="number" value="${qty}" min="1" max="99" class="hp-qty-input">
                </div>
                ` : ''}
                <div class="hp-product-price">${fmt(product.price * qty)}</div>
                <span class="hp-product-remove" title="Remove">×</span>
            </div>
        `);

        // Handle qty change
        $container.find('.hp-qty-input').on('change input', function() {
            const newQty = parseInt($(this).val()) || 1;
            const $qtyField = findQtyField($row);
            $qtyField.val(newQty);
            $container.find('.hp-product-price').text(fmt(product.price * newQty));
        });

        // Handle remove
        $container.find('.hp-product-remove').on('click', function(e) {
            e.preventDefault();
            clearProduct($row);
        });
    }

    function clearProduct($row) {
        findSkuField($row).val('').trigger('change');
        findProductContainer($row).html('');
    }

    // ========================================
    // LOAD EXISTING PRODUCTS
    // ========================================

    async function loadExistingProducts() {
        console.log('[HP Offer] Loading existing products...');
        
        // Find all offer rows (not clones) - use broader selector
        const $offerRows = $('.acf-field[data-name="funnel_offers"] .acf-row:not(.acf-clone)');
        console.log('[HP Offer] Found', $offerRows.length, 'offer rows to process');
        
        for (let i = 0; i < $offerRows.length; i++) {
            const $row = $($offerRows[i]);
            
            // Check if this is a top-level offer row (has offer_name field)
            if ($row.find('[data-name="offer_name"]').length > 0) {
                console.log('[HP Offer] Processing offer row', i);
                await loadProductForRow($row);
            }
            
            // Also load bundle/kit products
            const $bundleRows = $row.find('.acf-field[data-name="bundle_items"] .acf-row:not(.acf-clone)');
            for (let j = 0; j < $bundleRows.length; j++) {
                await loadProductForRow($($bundleRows[j]));
            }
            
            const $kitRows = $row.find('.acf-field[data-name="kit_products"] .acf-row:not(.acf-clone)');
            for (let j = 0; j < $kitRows.length; j++) {
                await loadProductForRow($($kitRows[j]));
            }
        }
        
        console.log('[HP Offer] Finished loading existing products');
    }
    
    async function loadProductForRow($row) {
        const $skuField = findSkuField($row);
        const sku = $skuField.val();
        
        console.log('[HP Offer] loadProductForRow - SKU field found:', $skuField.length > 0, 'value:', sku);
        
        if (!sku) {
            console.log('[HP Offer] No SKU value, skipping');
            return;
        }
        
        console.log('[HP Offer] Fetching product for SKU:', sku);
        
        const $qtyField = findQtyField($row);
        const qty = parseInt($qtyField.val()) || 1;
        
        const product = await fetchProductBySku(sku);
        if (product) {
            console.log('[HP Offer] Product found, showing card:', product.name);
            showProductCard($row, product, qty);
        } else {
            console.warn('[HP Offer] Product not found for SKU:', sku);
        }
    }

    // Debounced search
    const debouncedSearch = debounce(function($input) {
        const q = $input.val().trim();
        if (q.length < 2) {
            hideDropdown($input);
            return;
        }
        searchProducts(q).then(data => {
            if (data.success && data.products?.length) {
                showDropdown($input, data.products);
            } else {
                hideDropdown($input);
            }
        }).catch(() => hideDropdown($input));
    }, 300);

    // ========================================
    // INIT
    // ========================================

    function init() {
        if (typeof hpOfferCalc === 'undefined') {
            console.warn('[HP Offer] hpOfferCalc not defined');
            return;
        }

        console.log('[HP Offer] Initializing...');

        // Load existing products - try multiple approaches
        function tryLoadProducts() {
            console.log('[HP Offer] Attempting to load existing products...');
            
            // Check if ACF repeater rows exist
            const $rows = $('.acf-field[data-name="funnel_offers"] .acf-row:not(.acf-clone)');
            console.log('[HP Offer] Found', $rows.length, 'offer rows');
            
            if ($rows.length > 0) {
                loadExistingProducts();
            } else {
                // Retry after a delay if rows not found yet
                setTimeout(tryLoadProducts, 500);
            }
        }

        // Start loading after DOM is ready
        setTimeout(tryLoadProducts, 500);

        // Also try on ACF ready if available
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function() {
                console.log('[HP Offer] ACF ready event fired');
                setTimeout(loadExistingProducts, 300);
            });
        }

        // Search input
        $(document).on('input', '.hp-product-search-field input', function() {
            debouncedSearch($(this));
        });

        // Hide on blur
        $(document).on('blur', '.hp-product-search-field input', function() {
            setTimeout(() => hideDropdown($(this)), 200);
        });

        // Prevent enter submit
        $(document).on('keydown', '.hp-product-search-field input', function(e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Close dropdown on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hp-search-dropdown, .hp-product-search-field').length) {
                hideDropdown();
            }
        });

        // Clear product display on new rows
        if (typeof acf !== 'undefined') {
            acf.addAction('append', function($el) {
                $el.find('[class*="hp-"][class*="-product-container"]').html('');
            });
        }

        console.log('[HP Offer] Admin initialized');
    }

    $(document).ready(init);

})(jQuery);
