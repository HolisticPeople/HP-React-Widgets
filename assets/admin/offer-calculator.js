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
        console.log('[HP Offer] Initializing v3...');

        // Multiple initialization strategies
        if (typeof acf !== 'undefined') {
            console.log('[HP Offer] ACF found, adding ready action');
            acf.addAction('ready', function() {
                console.log('[HP Offer] ACF ready fired');
                setTimeout(initializeAllOffers, 300);
            });
            
            // Handle new offer rows
            acf.addAction('append', function($el) {
                console.log('[HP Offer] ACF append fired');
                setTimeout(function() {
                    initializeOfferRow($el);
                }, 100);
            });
            
            // Handle offer row reordering - re-initialize tables after sort
            acf.addAction('sortstop', function($repeater) {
                console.log('[HP Offer] ACF sortstop fired - reinitializing offers');
                // Re-initialize all offers after a short delay to let ACF settle
                setTimeout(function() {
                    reinitializeAllOffers();
                }, 150);
            });
        }
        
        // Fallback: also try on document ready
        $(document).ready(function() {
            console.log('[HP Offer] Document ready');
            setTimeout(initializeAllOffers, 500);
        });
        
        // Additional fallback for late-loading ACF
        $(window).on('load', function() {
            console.log('[HP Offer] Window load');
            setTimeout(initializeAllOffers, 200);
        });

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

        // Product quantity change (Tabulator)
        $(document).on('change', '.hp-qty-input', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const role = $(this).data('role') || 'optional';
            const minQty = (role === 'must') ? 1 : 0;
            let qty = parseInt($(this).val());
            
            // Enforce minimum based on role
            if (isNaN(qty) || qty < minQty) {
                qty = minQty;
                $(this).val(qty);
            }
            
            updateProductQty($section, sku, qty);
            rerenderProductsList($section);
        });

        // Product sale price change (Tabulator)
        $(document).on('change', '.hp-sale-price-input', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const originalPrice = parseFloat($(this).data('original')) || 0;
            const salePrice = parseFloat($(this).val()) || 0;
            
            updateProductSalePrice($section, sku, salePrice);
            rerenderProductsList($section);
        });

        // Product discount percent change (Tabulator)
        $(document).on('change', '.hp-discount-input', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const discountPercent = parseFloat($(this).val()) || 0;
            
            // Get original price and calculate sale price from discount
            const $row = $(this).closest('.tabulator-row');
            const $salePriceInput = $row.find('.hp-sale-price-input');
            const originalPrice = parseFloat($salePriceInput.data('original')) || 0;
            const salePrice = originalPrice * (1 - (discountPercent / 100));
            
            updateProductSalePrice($section, sku, salePrice);
            rerenderProductsList($section);
        });

        // Product role change (for kits - Tabulator)
        $(document).on('change', '.hp-role-select', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const role = $(this).val();
            
            updateProductRole($section, sku, role);
            
            // If changing to 'must' and qty is 0, set to 1
            const $row = $section.closest('.acf-row');
            const products = getProductsData($row);
            const product = products.find(p => p.sku === sku);
            if (product && role === 'must' && product.qty < 1) {
                updateProductQty($section, sku, 1);
            }
            
            rerenderProductsList($section);
        });

        // Subsequent discount percent change (for Must Have kit products)
        $(document).on('change', '.hp-subseq-discount-input', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const subseqDiscountPercent = parseFloat($(this).val()) || 0;
            
            // Get original price and calculate subsequent sale price from discount
            const $row = $(this).closest('.tabulator-row');
            const $subseqPriceInput = $row.find('.hp-subseq-price-input');
            const originalPrice = parseFloat($subseqPriceInput.data('original')) || 0;
            const subseqSalePrice = originalPrice * (1 - (subseqDiscountPercent / 100));
            
            updateProductSubsequentPricing($section, sku, subseqDiscountPercent, subseqSalePrice);
            rerenderProductsList($section);
        });

        // Subsequent sale price change (for Must Have kit products)
        $(document).on('change', '.hp-subseq-price-input', function() {
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            const originalPrice = parseFloat($(this).data('original')) || 0;
            const subseqSalePrice = parseFloat($(this).val()) || 0;
            
            // Calculate discount percent from price
            let subseqDiscountPercent = 0;
            if (originalPrice > 0 && subseqSalePrice < originalPrice) {
                subseqDiscountPercent = ((originalPrice - subseqSalePrice) / originalPrice) * 100;
            }
            
            updateProductSubsequentPricing($section, sku, subseqDiscountPercent, subseqSalePrice);
            rerenderProductsList($section);
        });

        // Remove product (supports both old class and Tabulator)
        $(document).on('click', '.hp-product-remove, .hp-remove-btn', function(e) {
            e.preventDefault();
            const $section = $(this).closest('.hp-products-section');
            const sku = $(this).data('sku');
            
            removeProduct($section, sku);
        });

        // Offer type change - preserve products
        $(document).on('change', '.acf-field[data-name="offer_type"] select', function() {
            const $offerRow = $(this).closest('.acf-row');
            setTimeout(function() {
                refreshOfferUI($offerRow);
            }, 50);
        });

        // Expanded view: clicking the full-height hit area collapses the offer.
        $(document).on('click', '.hp-offer-collapse-hit', function(e) {
            // If user clicked the actual remove icon, let ACF handle removal.
            if ($(e.target).closest('.acf-icon.-minus').length) return;

            e.preventDefault();
            e.stopPropagation();

            const $row = $(this).closest('.acf-row');
            const $orderHandle = $row.children('.acf-row-handle.order');
            if ($orderHandle.length) {
                const $icon = $orderHandle.find('.acf-icon').first();
                if ($icon.length) {
                    $icon.trigger('click');
                } else {
                    $orderHandle.trigger('click');
                }
            } else {
                $row.addClass('-collapsed');
            }
        });

        // Expand by clicking anywhere on a collapsed row (no need for an expand button)
        $(document).on('click', '.acf-field[data-name="funnel_offers"] .acf-row.-collapsed, .acf-field[data-key="field_funnel_offers"] .acf-row.-collapsed', function(e) {
            // Ignore clicks on the remove handle area
            if ($(e.target).closest('.acf-row-handle.remove').length) return;
            // Ignore clicks on links/buttons just in case
            if ($(e.target).closest('a,button').length) return;

            const $row = $(this);
            const $orderHandle = $row.children('.acf-row-handle.order');
            if ($orderHandle.length) {
                const $icon = $orderHandle.find('.acf-icon').first();
                if ($icon.length) {
                    $icon.trigger('click');
                } else {
                    $orderHandle.trigger('click');
                }
            } else {
                $row.removeClass('-collapsed');
            }
        });
    }

    function initializeAllOffers() {
        console.log('[HP Offer] Initializing all offers...');
        
        // Try multiple selectors
        let $offerRows = $('.acf-field[data-name="funnel_offers"] .acf-row:not(.acf-clone)');
        
        if ($offerRows.length === 0) {
            // Try alternative selector
            $offerRows = $('.acf-field[data-key="field_funnel_offers"] .acf-row:not(.acf-clone)');
            console.log('[HP Offer] Using key selector, found:', $offerRows.length);
        }
        
        if ($offerRows.length === 0) {
            // Try even broader selector
            $offerRows = $('[data-name="funnel_offers"]').find('.acf-row').not('.acf-clone');
            console.log('[HP Offer] Using broad selector, found:', $offerRows.length);
        }
        
        console.log('[HP Offer] Found', $offerRows.length, 'offer rows');
        
        $offerRows.each(function(index) {
            console.log('[HP Offer] Processing row', index);
            initializeOfferRow($(this));
            decorateCollapseZone($(this));
        });
    }

    /**
     * Reinitialize all offers after ACF reordering.
     * This destroys existing Tabulator instances and rebuilds them.
     */
    function reinitializeAllOffers() {
        console.log('[HP Offer] Reinitializing all offers after sort...');
        
        // Destroy all existing Tabulator tables
        if (window.HPOfferTable && window.HPOfferTable.tables) {
            Object.keys(window.HPOfferTable.tables).forEach(function(tableId) {
                window.HPOfferTable.destroy(tableId);
            });
        }
        
        // Clear initialized flag and rebuild each offer
        let $offerRows = $('.acf-field[data-name="funnel_offers"] .acf-row:not(.acf-clone)');
        if ($offerRows.length === 0) {
            $offerRows = $('.acf-field[data-key="field_funnel_offers"] .acf-row:not(.acf-clone)');
        }
        
        $offerRows.each(function(index) {
            const $row = $(this);
            const $container = $row.find('[data-offer-products]');
            
            if ($container.length) {
                // Clear the initialized flag to force rebuild
                $container.data('initialized', false);
                $container.removeData('tableId');
                
                // Clear the container HTML to force fresh rebuild
                $container.empty();
            }
        });
        
        // Now reinitialize all offers
        initializeAllOffers();
    }

    function initializeOfferRow($row) {
        const $container = $row.find('[data-offer-products]');
        if (!$container.length) {
            console.log('[HP Offer] No products container in row');
            return;
        }

        // Always ensure collapse zone exists (even if table already initialized)
        decorateCollapseZone($row);

        // Check if already initialized
        if ($container.data('initialized')) {
            return;
        }
        $container.data('initialized', true);

        const offerType = getOfferType($row);
        console.log('[HP Offer] Initializing offer row, type:', offerType);

        // Build the UI since ACF strips input tags from message fields
        const placeholder = offerType === 'single' 
            ? 'Search to select a product...' 
            : 'Search to add products...';
            
        // Generate unique ID for this table
        const tableId = 'hp-offer-table-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6);
        
        $container.html(`
            <div style="border: 1px solid #ddd; border-radius: 6px; background: #fff;">
                <div class="hp-search-wrapper" style="padding: 12px; border-bottom: 1px solid #eee; background: #f9f9f9; position: relative;">
                    <input type="text" class="hp-offer-search-input" placeholder="${placeholder}" 
                           style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                </div>
                <div class="hp-products-list" id="${tableId}" style="min-height: 60px;"></div>
            </div>
        `);
        
        // Store table ID on container for later reference
        $container.data('tableId', tableId);

        // Load existing products
        loadExistingProducts($row, $container);
    }

    function decorateCollapseZone($row) {
        const $removeHandle = $row.children('.acf-row-handle.remove');
        if (!$removeHandle.length) return;

        $removeHandle.addClass('hp-offer-collapse-zone');

        // Add a full-height hit area so the whole column behaves like a button (tooltip included)
        if (!$removeHandle.find('.hp-offer-collapse-hit').length) {
            const $hit = $('<div class="hp-offer-collapse-hit" title="Click here to collapse this offer" aria-label="Click here to collapse this offer"></div>');
            $removeHandle.prepend($hit);
        }
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
                
                const originalPrice = parseFloat($(this).data('price')) || 0;
                const productData = {
                    sku: $(this).data('sku'),
                    name: $(this).data('name'),
                    price: originalPrice,
                    salePrice: originalPrice, // Default sale price = original price
                    // Subsequent pricing defaults to full price (no discount)
                    subsequentDiscountPercent: 0,
                    subsequentSalePrice: originalPrice, // Full price for additional units
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

    function updateProductSalePrice($section, sku, salePrice) {
        const $row = $section.closest('.acf-row');
        let products = getProductsData($row);
        
        const product = products.find(p => p.sku === sku);
        if (product) {
            product.salePrice = salePrice;
            saveProductsData($row, products);
        }
    }

    function updateProductSubsequentPricing($section, sku, discountPercent, salePrice) {
        const $row = $section.closest('.acf-row');
        let products = getProductsData($row);
        
        const product = products.find(p => p.sku === sku);
        if (product) {
            product.subsequentDiscountPercent = discountPercent;
            product.subsequentSalePrice = salePrice;
            saveProductsData($row, products);
        }
    }

    function rerenderProductsList($section) {
        const $row = $section.closest('.acf-row');
        const products = getProductsData($row);
        const offerType = getOfferType($row);
        renderProductsList($section.find('.hp-products-list'), products, offerType);
    }

    // ========================================
    // RENDER PRODUCTS LIST (Using Tabulator)
    // ========================================

    function renderProductsList($list, products, offerType) {
        const tableId = $list.attr('id');
        
        if (!tableId) {
            console.warn('[HP Offer] No table ID found on products list');
            return;
        }

        if (!products || products.length === 0) {
            // Destroy existing table if any
            if (window.HPOfferTable) {
                window.HPOfferTable.destroy(tableId);
            }
            $list.html('<div style="padding: 20px; text-align: center; color: #999; font-style: italic;">No products added. Use search above to add products.</div>');
            // Remove summary
            $list.siblings('.hp-offer-table-summary').remove();
            // Still update collapsed meta (type / price / image) even when there are no products yet
            try {
                const $row = $list.closest('.acf-row');
                const $container = $row.find('[data-offer-products]').first();
                if (window.HPOfferTable && $container.length && window.HPOfferTable._updateCollapsedOfferMeta) {
                    window.HPOfferTable._updateCollapsedOfferMeta($container, [], 0);
                }
            } catch (e) {}
            return;
        }

        // Use Tabulator to render
        if (window.HPOfferTable) {
            window.HPOfferTable.render(tableId, products, { offerType: offerType });
        } else {
            console.warn('[HP Offer] HPOfferTable not loaded');
        }
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
        // Check if this is an EXISTING offer (has an ID that was set on page load)
        // New offers shouldn't load from PHP-injected data
        const $idField = $row.find('.acf-field[data-name="offer_id"] input');
        const offerId = $idField.val();
        const isExistingOffer = offerId && offerId.length > 5; // Valid IDs are like "offer-7f079867"
        
        const rowIndex = getRowIndex($row);
        console.log('[HP Offer] Getting products for row', rowIndex, 'offerId:', offerId, 'isExisting:', isExistingOffer);
        
        // Only use PHP-injected data for EXISTING offers (not newly added ones)
        if (isExistingOffer && typeof window.hpOfferSavedProducts !== 'undefined') {
            const savedJson = window.hpOfferSavedProducts[rowIndex];
            console.log('[HP Offer] Found injected data for row', rowIndex, ':', savedJson ? 'yes' : 'no');
            if (savedJson) {
                try {
                    const products = typeof savedJson === 'string' ? JSON.parse(savedJson) : savedJson;
                    // Clear from the map so we don't reload it again
                    delete window.hpOfferSavedProducts[rowIndex];
                    return products;
                } catch (e) {
                    console.warn('[HP Offer] Error parsing injected data:', e);
                }
            }
        }

        // Fall back to textarea value
        let $field = $row.find('.acf-field[data-name="products_data"] textarea');
        if (!$field.length) {
            $field = $row.find('textarea[name*="products_data"]');
        }
        
        if (!$field.length) {
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
        // Try multiple selectors as ACF structure can vary
        const $repeater = $row.closest('.acf-repeater');
        let $allRows = $repeater.find('> .acf-row:not(.acf-clone)');
        
        // If direct child selector fails, try without it
        if ($allRows.length === 0) {
            $allRows = $repeater.find('.acf-row:not(.acf-clone)');
        }
        
        // Also try finding via data attribute
        const dataIndex = $row.attr('data-id');
        if (dataIndex && dataIndex.match(/^row-\d+$/)) {
            const idx = parseInt(dataIndex.replace('row-', ''), 10);
            console.log('[HP Offer] Got row index from data-id:', idx);
            return idx;
        }
        
        const index = $allRows.index($row);
        console.log('[HP Offer] Computed row index:', index, 'from', $allRows.length, 'rows');
        return index >= 0 ? index : 0; // Default to 0 if not found
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
