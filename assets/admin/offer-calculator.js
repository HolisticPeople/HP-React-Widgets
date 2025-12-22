/**
 * Funnel Offer Admin - Product Management
 * EAO-style single search + product list approach
 */
(function($) {
    'use strict';

    if (typeof hpOfferCalc === 'undefined') {
        console.warn('[HP Offer] hpOfferCalc not defined');
        return;
    }

    let searchTimeout = null;
    let searchRequest = null;

    // ========================================
    // INITIALIZATION
    // ========================================

    function init() {
        console.log('[HP Offer] Initializing v2...');

        // Wait for ACF to be ready
        if (typeof acf !== 'undefined') {
            acf.addAction('ready', function() {
                setTimeout(initializeAllOffers, 300);
            });
            
            // Handle new offer rows
            acf.addAction('append', function($el) {
                setTimeout(function() {
                    initializeOfferRow($el);
                }, 100);
            });
        } else {
            setTimeout(initializeAllOffers, 500);
        }

        // Bind events
        bindEvents();

        console.log('[HP Offer] Admin initialized');
    }

    function bindEvents() {
        // Search input
        $(document).on('input', '.hp-offer-search-input', function() {
            handleSearch($(this));
        });

        // Prevent form submit on enter in search
        $(document).on('keydown', '.hp-offer-search-input', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        // Close dropdown on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.hp-search-wrapper').length) {
                $('.hp-search-dropdown').remove();
            }
        });

        // Product quantity change
        $(document).on('change', '.hp-product-item .hp-qty-input', function() {
            const $item = $(this).closest('.hp-product-item');
            const $section = $(this).closest('.hp-products-section');
            const sku = $item.data('sku');
            const qty = parseInt($(this).val()) || 1;
            
            updateProductQty($section, sku, qty);
        });

        // Product role change (for kits)
        $(document).on('change', '.hp-product-item .hp-role-select', function() {
            const $item = $(this).closest('.hp-product-item');
            const $section = $(this).closest('.hp-products-section');
            const sku = $item.data('sku');
            const role = $(this).val();
            
            updateProductRole($section, sku, role);
        });

        // Remove product
        $(document).on('click', '.hp-product-remove', function(e) {
            e.preventDefault();
            const $item = $(this).closest('.hp-product-item');
            const $section = $(this).closest('.hp-products-section');
            const sku = $item.data('sku');
            
            removeProduct($section, sku);
        });

        // Offer type change - preserve products
        $(document).on('change', '.acf-field[data-name="offer_type"] select', function() {
            const $offerRow = $(this).closest('.acf-row');
            setTimeout(function() {
                refreshOfferUI($offerRow);
            }, 50);
        });
    }

    function initializeAllOffers() {
        console.log('[HP Offer] Initializing all offers...');
        
        const $offerRows = $('.acf-field[data-name="funnel_offers"] .acf-row:not(.acf-clone)');
        console.log('[HP Offer] Found', $offerRows.length, 'offer rows');
        
        $offerRows.each(function() {
            initializeOfferRow($(this));
        });
    }

    function initializeOfferRow($row) {
        const $container = $row.find('[data-offer-products]');
        if (!$container.length) {
            console.log('[HP Offer] No products container in row, trying to find group...');
            // Try to find it via the group wrapper
            const $group = $row.find('.acf-field[data-name="products_wrapper"]');
            console.log('[HP Offer] Products wrapper group found:', $group.length > 0);
            return;
        }

        // Check if already initialized
        if ($container.data('initialized')) {
            return;
        }
        $container.data('initialized', true);

        const offerType = getOfferType($row);
        console.log('[HP Offer] Initializing offer row, type:', offerType);

        // Update placeholder based on type
        const $searchInput = $container.find('.hp-offer-search-input');
        if (offerType === 'single') {
            $searchInput.attr('placeholder', 'Search to select a product...');
        } else {
            $searchInput.attr('placeholder', 'Search to add products...');
        }

        // Load existing products
        loadExistingProducts($row, $container);
    }

    // ========================================
    // UI UPDATES
    // ========================================

    function refreshOfferUI($row) {
        const $container = $row.find('[data-offer-products]');
        if (!$container.length) return;

        const newType = getOfferType($row);
        const $list = $container.find('.hp-products-list');
        
        // Update the list type
        $list.attr('data-type', newType);

        // Update max products in search
        const maxProducts = newType === 'single' ? 1 : (newType === 'customizable_kit' ? 20 : 10);
        $container.find('.hp-offer-search-input').attr('data-max', maxProducts);

        // If switching TO single and we have multiple products, keep only the first
        if (newType === 'single') {
            const products = getProductsData($row);
            if (products.length > 1) {
                const firstProduct = products[0];
                saveProductsData($row, [firstProduct]);
                renderProductsList($container.find('.hp-products-list'), [firstProduct], newType);
            }
        }

        // Re-render to update controls (role selector for kits, etc)
        const products = getProductsData($row);
        renderProductsList($container.find('.hp-products-list'), products, newType);
    }

    // ========================================
    // SEARCH FUNCTIONALITY
    // ========================================

    function handleSearch($input) {
        const term = $input.val().trim();
        
        if (term.length < 2) {
            hideDropdown($input);
            return;
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch($input, term);
        }, 300);
    }

    async function performSearch($input, term) {
        if (searchRequest) {
            searchRequest.abort();
        }

        const $wrapper = $input.closest('.hp-search-wrapper');
        
        // Show loading
        showDropdown($wrapper, '<div style="padding: 12px; color: #666;">Searching...</div>');

        try {
            const response = await fetch(`${hpOfferCalc.restUrl}products/search?term=${encodeURIComponent(term)}`, {
                headers: { 'X-WP-Nonce': hpOfferCalc.nonce }
            });

            if (!response.ok) throw new Error('Search failed');

            const products = await response.json();
            
            if (products.length === 0) {
                showDropdown($wrapper, '<div style="padding: 12px; color: #666;">No products found</div>');
                return;
            }

            const $section = $input.closest('.hp-products-section');
            const $row = $section.closest('.acf-row');
            const existingProducts = getProductsData($row);
            const existingSkus = existingProducts.map(p => p.sku);

            let html = '';
            products.forEach(product => {
                const isAdded = existingSkus.includes(product.sku);
                const addedClass = isAdded ? 'is-added' : '';
                const addedLabel = isAdded ? '<span style="color:#00a32a;font-size:11px;">✓ Added</span>' : '';
                
                html += `
                    <div class="hp-search-item ${addedClass}" 
                         data-sku="${escapeAttr(product.sku)}"
                         data-name="${escapeAttr(product.name)}"
                         data-price="${product.price}"
                         data-image="${escapeAttr(product.image || '')}">
                        <img src="${product.image || ''}" alt="">
                        <div class="hp-search-item-info">
                            <div class="hp-search-item-name">${escapeHtml(product.name)}</div>
                            <div class="hp-search-item-sku">SKU: ${escapeHtml(product.sku)} ${addedLabel}</div>
                        </div>
                        <div class="hp-search-item-price">$${parseFloat(product.price).toFixed(2)}</div>
                    </div>
                `;
            });

            showDropdown($wrapper, html);

            // Bind click on results
            $wrapper.find('.hp-search-dropdown .hp-search-item').on('click', function() {
                if ($(this).hasClass('is-added')) return;
                
                const productData = {
                    sku: $(this).data('sku'),
                    name: $(this).data('name'),
                    price: parseFloat($(this).data('price')),
                    image: $(this).data('image'),
                    qty: 1,
                    role: 'optional' // default for kits
                };

                addProduct($section, productData);
                hideDropdown($input);
                $input.val('');
            });

        } catch (error) {
            console.error('[HP Offer] Search error:', error);
            showDropdown($wrapper, '<div style="padding: 12px; color: #d63638;">Search error</div>');
        }
    }

    function showDropdown($wrapper, content) {
        let $dropdown = $wrapper.find('.hp-search-dropdown');
        if (!$dropdown.length) {
            $dropdown = $('<div class="hp-search-dropdown"></div>');
            $wrapper.append($dropdown);
        }
        $dropdown.html(content).show();
    }

    function hideDropdown($input) {
        if ($input) {
            $input.closest('.hp-search-wrapper').find('.hp-search-dropdown').remove();
        } else {
            $('.hp-search-dropdown').remove();
        }
    }

    // ========================================
    // PRODUCT MANAGEMENT
    // ========================================

    function addProduct($section, productData) {
        const $row = $section.closest('.acf-row');
        const offerType = getOfferType($row);
        const maxProducts = offerType === 'single' ? 1 : 10;
        
        let products = getProductsData($row);

        // Check if already exists
        if (products.some(p => p.sku === productData.sku)) {
            console.log('[HP Offer] Product already in list:', productData.sku);
            return;
        }

        // Check max
        if (products.length >= maxProducts) {
            if (offerType === 'single') {
                // Replace existing product
                products = [productData];
            } else {
                alert(`Maximum ${maxProducts} products allowed`);
                return;
            }
        } else {
            products.push(productData);
        }

        saveProductsData($row, products);
        renderProductsList($section.find('.hp-products-list'), products, offerType);

        console.log('[HP Offer] Product added:', productData.sku);
    }

    function removeProduct($section, sku) {
        const $row = $section.closest('.acf-row');
        let products = getProductsData($row);
        
        products = products.filter(p => p.sku !== sku);
        
        saveProductsData($row, products);
        renderProductsList($section.find('.hp-products-list'), products, getOfferType($row));

        console.log('[HP Offer] Product removed:', sku);
    }

    function updateProductQty($section, sku, qty) {
        const $row = $section.closest('.acf-row');
        let products = getProductsData($row);
        
        const product = products.find(p => p.sku === sku);
        if (product) {
            product.qty = qty;
            saveProductsData($row, products);
        }
    }

    function updateProductRole($section, sku, role) {
        const $row = $section.closest('.acf-row');
        let products = getProductsData($row);
        
        const product = products.find(p => p.sku === sku);
        if (product) {
            product.role = role;
            saveProductsData($row, products);
        }
    }

    // ========================================
    // RENDER PRODUCTS LIST
    // ========================================

    function renderProductsList($list, products, offerType) {
        if (!products || products.length === 0) {
            $list.html('');
            return;
        }

        let html = '';
        products.forEach(product => {
            const isKit = offerType === 'customizable_kit';
            const kitClass = isKit ? 'is-kit' : '';
            
            html += `
                <div class="hp-product-item ${kitClass}" data-sku="${escapeAttr(product.sku)}">
                    <img src="${product.image || ''}" alt="">
                    <div class="hp-product-info">
                        <div class="hp-product-name">${escapeHtml(product.name)}</div>
                        <div class="hp-product-sku">SKU: ${escapeHtml(product.sku)}</div>
                    </div>
                    <div class="hp-product-controls">
                        ${isKit ? getRoleSelector(product.role || 'optional') : ''}
                        <div class="hp-qty-control">
                            <label>Qty:</label>
                            <input type="number" class="hp-qty-input" value="${product.qty || 1}" min="1" max="99">
                        </div>
                        <div class="hp-product-price">$${parseFloat(product.price).toFixed(2)}</div>
                        <button type="button" class="hp-product-remove" title="Remove">×</button>
                    </div>
                </div>
            `;
        });

        $list.html(html);
    }

    function getRoleSelector(currentRole) {
        const options = [
            { value: 'must', label: 'Required' },
            { value: 'default', label: 'Default' },
            { value: 'optional', label: 'Optional' }
        ];

        let html = '<div class="hp-role-control"><select class="hp-role-select">';
        options.forEach(opt => {
            const selected = opt.value === currentRole ? 'selected' : '';
            html += `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
        });
        html += '</select></div>';
        
        return html;
    }

    // ========================================
    // DATA PERSISTENCE
    // ========================================

    function getProductsData($row) {
        // First, check if we have PHP-injected data (for initial page load)
        const rowIndex = getRowIndex($row);
        if (typeof window.hpOfferSavedProducts !== 'undefined' && window.hpOfferSavedProducts[rowIndex]) {
            const savedJson = window.hpOfferSavedProducts[rowIndex];
            console.log('[HP Offer] Using PHP-injected data for row', rowIndex);
            try {
                const products = typeof savedJson === 'string' ? JSON.parse(savedJson) : savedJson;
                // Clear from the map so we don't reload it again after user edits
                delete window.hpOfferSavedProducts[rowIndex];
                return products;
            } catch (e) {
                console.warn('[HP Offer] Error parsing injected data:', e);
            }
        }

        // Fall back to textarea value (for user-edited data)
        let $field = $row.find('.acf-field[data-name="products_data"] textarea');
        if (!$field.length) {
            $field = $row.find('textarea[name*="products_data"]');
        }
        
        if (!$field.length) {
            console.warn('[HP Offer] products_data field not found');
            return [];
        }

        const json = $field.val();
        if (!json) return [];

        try {
            return JSON.parse(json);
        } catch (e) {
            console.warn('[HP Offer] Invalid JSON in products_data:', e);
            return [];
        }
    }

    function getRowIndex($row) {
        // Get the index of this row within the repeater
        const $allRows = $row.closest('.acf-repeater').find('> .acf-row:not(.acf-clone)');
        return $allRows.index($row);
    }

    function saveProductsData($row, products) {
        const $field = $row.find('.acf-field[data-name="products_data"] textarea');
        if (!$field.length) {
            console.warn('[HP Offer] products_data field not found for saving');
            return;
        }

        const json = JSON.stringify(products);
        $field.val(json);
        
        console.log('[HP Offer] Saved products data:', products.length, 'products');
    }

    function loadExistingProducts($row, $container) {
        const products = getProductsData($row);
        const offerType = getOfferType($row);
        
        console.log('[HP Offer] Loaded products:', products.length);
        
        if (products.length > 0) {
            renderProductsList($container.find('.hp-products-list'), products, offerType);
            // Also save to textarea for form submission
            saveProductsData($row, products);
        }
    }

    // ========================================
    // HELPERS
    // ========================================

    function getOfferType($row) {
        return $row.find('.acf-field[data-name="offer_type"] select').val() || 'single';
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;');
    }

    // ========================================
    // INIT
    // ========================================

    $(document).ready(init);

})(jQuery);
