/**
 * HP Offer Products Table (Tabulator-based)
 * Simplified version inspired by EAO products table
 * @version 1.1.0 - Added tiered pricing for Must Have kit products
 */
(function($) {
    'use strict';

    window.HPOfferTable = {
        tables: {}, // Store table instances by container ID
        
        /**
         * Initialize or update a Tabulator table for an offer
         * @param {string} containerId - The container ID
         * @param {array} products - Array of product data
         * @param {object} options - Options including offerType ('single', 'fixed_bundle', 'customizable_kit')
         */
        render: function(containerId, products, options) {
            options = options || {};
            const isKit = options.offerType === 'customizable_kit';
            const $container = $('#' + containerId);
            if (!$container.length) {
                console.warn('[HP Offer Table] Container not found:', containerId);
                return;
            }

            // Map products to table data
            const data = this._mapProducts(products, options);
            
            // Build columns (with Role column for kits)
            const columns = this._buildColumns(isKit);
            
            // Check if table exists
            if (this.tables[containerId]) {
                // Update existing table
                this.tables[containerId].setData(data);
            } else {
                // Create new table
                this.tables[containerId] = new Tabulator('#' + containerId, {
                    data: data,
                    layout: "fitColumns",
                    columnHeaderVertAlign: "middle",
                    headerVisible: true,
                    columnDefaults: { resizable: false, headerSort: false },
                    movableColumns: false,
                    reactiveData: false,
                    columns: columns,
                    placeholder: "No products added. Use search above to add products."
                });

                // On table built, store reference
                this.tables[containerId].on('tableBuilt', () => {
                    console.log('[HP Offer Table] Table built:', containerId);
                });
            }

            // Update summary (pass isKit for tiered pricing calculation)
            this._updateSummary($container, products, isKit);
        },

        /**
         * Build column definitions
         * @param {boolean} isKit - Whether this is a customizable kit (shows Role column and subsequent pricing)
         */
        _buildColumns: function(isKit) {
            const columns = [
                { 
                    title: "", 
                    field: "thumb", 
                    width: 50, 
                    widthGrow: 0, 
                    headerSort: false, 
                    formatter: "html"
                },
                { 
                    title: "Product", 
                    field: "product", 
                    minWidth: 180, 
                    widthGrow: 1, 
                    headerSort: false, 
                    formatter: "html"
                },
                { 
                    title: "Qty", 
                    field: "qty", 
                    width: 70, 
                    widthGrow: 0, 
                    headerSort: false, 
                    hozAlign: "center", 
                    formatter: "html"
                }
            ];

            // Add Role column for kits only
            if (isKit) {
                columns.push({
                    title: "Role",
                    field: "role",
                    width: 110,
                    widthGrow: 0,
                    headerSort: false,
                    hozAlign: "center",
                    formatter: "html"
                });
            }

            // Pricing group - base pricing columns
            const pricingColumns = [
                { 
                    title: "Price", 
                    field: "price", 
                    width: 80, 
                    widthGrow: 0, 
                    hozAlign: "center", 
                    formatter: "html"
                },
                { 
                    title: "Disc. %", 
                    field: "discount", 
                    width: 90, 
                    widthGrow: 0, 
                    hozAlign: "center", 
                    formatter: "html"
                },
                { 
                    title: "Sale Price", 
                    field: "sale_price", 
                    width: 90, 
                    widthGrow: 0, 
                    hozAlign: "center", 
                    formatter: "html"
                }
            ];

            // Add subsequent pricing columns for kits (only visible for Must Have products)
            if (isKit) {
                pricingColumns.push(
                    { 
                        title: "Subseq. %", 
                        field: "subseq_discount", 
                        width: 90, 
                        widthGrow: 0, 
                        hozAlign: "center", 
                        formatter: "html"
                    },
                    { 
                        title: "Subseq. Price", 
                        field: "subseq_price", 
                        width: 100, 
                        widthGrow: 0, 
                        hozAlign: "center", 
                        formatter: "html"
                    }
                );
            }

            columns.push({
                title: isKit ? "First Unit / Subsequent" : "Pricing",
                resizable: false,
                columns: pricingColumns
            });

            // Total and actions
            columns.push(
                { 
                    title: "Total", 
                    field: "total", 
                    width: 80, 
                    widthGrow: 0, 
                    headerSort: false, 
                    hozAlign: "center", 
                    formatter: "html"
                },
                { 
                    title: "", 
                    field: "actions", 
                    width: 50, 
                    widthGrow: 0, 
                    headerSort: false, 
                    hozAlign: "center", 
                    formatter: "html"
                }
            );

            return columns;
        },

        /**
         * Map products to table row data
         */
        _mapProducts: function(products, options) {
            const isKit = options.offerType === 'customizable_kit';
            
            return (products || []).map((p, index) => {
                const originalPrice = parseFloat(p.price) || 0;
                const salePrice = (p.salePrice !== undefined && p.salePrice !== null) 
                    ? parseFloat(p.salePrice) 
                    : originalPrice;
                const qty = parseInt(p.qty) || 0;  // Allow 0
                
                // Subsequent pricing for Must Have products
                const role = p.role || 'optional';
                const subseqDiscountPercent = parseFloat(p.subsequentDiscountPercent) || 0;
                const subseqSalePrice = (p.subsequentSalePrice !== undefined && p.subsequentSalePrice !== null)
                    ? parseFloat(p.subsequentSalePrice)
                    : salePrice;  // Default to first unit price
                
                // Calculate line total with tiered pricing for Must Have products
                let lineTotal = 0;
                if (isKit && role === 'must' && qty > 0) {
                    // First unit at salePrice, additional at subsequentSalePrice
                    const firstQty = 1;
                    const additionalQty = Math.max(0, qty - 1);
                    lineTotal = (salePrice * firstQty) + (subseqSalePrice * additionalQty);
                } else {
                    lineTotal = salePrice * qty;
                }
                
                // Calculate discount percent
                let discountPercent = 0;
                if (originalPrice > 0 && salePrice < originalPrice) {
                    discountPercent = ((originalPrice - salePrice) / originalPrice) * 100;
                }

                const sku = p.sku || '';
                
                const rowData = {
                    id: sku,
                    sku: sku,
                    _originalPrice: originalPrice,
                    _salePrice: salePrice,
                    _subseqSalePrice: subseqSalePrice,
                    _subseqDiscountPercent: subseqDiscountPercent,
                    _qty: qty,
                    _role: role,
                    thumb: `<div class="hp-item-thumb"><img src="${p.image || ''}" alt=""></div>`,
                    product: this._renderProduct(p),
                    qty: this._renderQty(p),
                    price: `<span class="hp-price-original">$${originalPrice.toFixed(2)}</span>`,
                    discount: this._renderDiscount(p, discountPercent),
                    sale_price: this._renderSalePrice(p, salePrice),
                    total: `<span class="hp-line-total">$${lineTotal.toFixed(2)}</span>`,
                    actions: this._renderActions(p)
                };

                // Add role column and subsequent pricing for kits
                if (isKit) {
                    rowData.role = this._renderRole(p, role);
                    rowData.subseq_discount = this._renderSubseqDiscount(p, subseqDiscountPercent, role);
                    rowData.subseq_price = this._renderSubseqPrice(p, subseqSalePrice, role);
                }

                return rowData;
            });
        },

        _renderProduct: function(p) {
            const sku = p.sku ? `<span class="hp-item-sku">SKU: ${p.sku}</span>` : '';
            return `<div class="hp-item-content">
                <div class="hp-item-name">${this._escapeHtml(p.name || '')}</div>
                <div class="hp-item-meta">${sku}</div>
            </div>`;
        },

        _renderQty: function(p) {
            const qty = parseInt(p.qty) || 0;
            const sku = p.sku || '';
            const role = p.role || 'optional';
            // Must Have = min 1, Optional = min 0
            const minQty = (role === 'must') ? 1 : 0;
            return `<input type="number" class="hp-qty-input" value="${qty}" min="${minQty}" max="99" data-sku="${this._escapeAttr(sku)}" data-role="${role}">`;
        },

        _renderDiscount: function(p, discountPercent) {
            const sku = p.sku || '';
            const val = discountPercent.toFixed(1);
            return `<div class="hp-discount-control">
                <input type="number" class="hp-discount-input" value="${val}" min="0" max="100" step="0.1" data-sku="${this._escapeAttr(sku)}">
                <span class="hp-percent-symbol">%</span>
            </div>`;
        },

        _renderSalePrice: function(p, salePrice) {
            const sku = p.sku || '';
            return `<div class="hp-sale-price-control">
                <span class="hp-currency">$</span>
                <input type="number" class="hp-sale-price-input" value="${salePrice.toFixed(2)}" min="0" step="0.01" data-sku="${this._escapeAttr(sku)}" data-original="${parseFloat(p.price) || 0}">
            </div>`;
        },

        _renderActions: function(p) {
            const sku = p.sku || '';
            return `<button type="button" class="button button-link-delete hp-remove-btn" data-sku="${this._escapeAttr(sku)}" title="Remove">
                <span class="dashicons dashicons-trash"></span>
            </button>`;
        },

        /**
         * Render role dropdown for kit products
         * - must: minimum qty 1 (required in kit)
         * - optional: minimum qty 0 (can be removed)
         */
        _renderRole: function(p, role) {
            const sku = p.sku || '';
            // Normalize legacy 'default' role to 'optional'
            const normalizedRole = (role === 'default') ? 'optional' : role;
            return `<select class="hp-role-select" data-sku="${this._escapeAttr(sku)}">
                <option value="must" ${normalizedRole === 'must' ? 'selected' : ''}>Must Have</option>
                <option value="optional" ${normalizedRole === 'optional' ? 'selected' : ''}>Optional</option>
            </select>`;
        },

        /**
         * Render subsequent discount percent input (for Must Have products only)
         */
        _renderSubseqDiscount: function(p, discountPercent, role) {
            const sku = p.sku || '';
            // Only show for Must Have products
            if (role !== 'must') {
                return '<span class="hp-na-field" title="Only for Must Have products">—</span>';
            }
            const val = discountPercent.toFixed(1);
            return `<div class="hp-discount-control">
                <input type="number" class="hp-subseq-discount-input" value="${val}" min="0" max="100" step="0.1" data-sku="${this._escapeAttr(sku)}">
                <span class="hp-percent-symbol">%</span>
            </div>`;
        },

        /**
         * Render subsequent sale price input (for Must Have products only)
         */
        _renderSubseqPrice: function(p, subseqPrice, role) {
            const sku = p.sku || '';
            const originalPrice = parseFloat(p.price) || 0;
            // Only show for Must Have products
            if (role !== 'must') {
                return '<span class="hp-na-field" title="Only for Must Have products">—</span>';
            }
            return `<div class="hp-sale-price-control">
                <span class="hp-currency">$</span>
                <input type="number" class="hp-subseq-price-input" value="${subseqPrice.toFixed(2)}" min="0" step="0.01" data-sku="${this._escapeAttr(sku)}" data-original="${originalPrice}">
            </div>`;
        },

        /**
         * Update the summary section below the table
         * @param {jQuery} $container - Table container element
         * @param {array} products - Products array
         * @param {boolean} isKit - Whether this is a kit offer (for tiered pricing)
         */
        _updateSummary: function($container, products, isKit) {
            let $summary = $container.siblings('.hp-offer-table-summary');
            if (!$summary.length) {
                $summary = $('<div class="hp-offer-table-summary"></div>');
                $container.after($summary);
            }

            let totalOriginal = 0;
            let totalSale = 0;

            (products || []).forEach(p => {
                const originalPrice = parseFloat(p.price) || 0;
                const salePrice = (p.salePrice !== undefined && p.salePrice !== null) 
                    ? parseFloat(p.salePrice) 
                    : originalPrice;
                const qty = parseInt(p.qty);
                const role = p.role || 'optional';
                
                // Allow qty 0 - only count items with qty > 0
                if (!isNaN(qty) && qty > 0) {
                    totalOriginal += originalPrice * qty;
                    
                    // Use tiered pricing for Must Have products in kits
                    if (isKit && role === 'must') {
                        const subseqSalePrice = (p.subsequentSalePrice !== undefined && p.subsequentSalePrice !== null)
                            ? parseFloat(p.subsequentSalePrice)
                            : salePrice;
                        const firstQty = 1;
                        const additionalQty = Math.max(0, qty - 1);
                        totalSale += (salePrice * firstQty) + (subseqSalePrice * additionalQty);
                    } else {
                        totalSale += salePrice * qty;
                    }
                }
            });

            const totalDiscount = totalOriginal - totalSale;
            const hasDiscount = totalDiscount > 0;
            const discountPercent = totalOriginal > 0 ? ((totalDiscount / totalOriginal) * 100) : 0;

            let html = `
                <div class="hp-summary-row">
                    <span class="hp-summary-label">Subtotal:</span>
                    <span class="hp-summary-value ${hasDiscount ? 'strikethrough' : ''}">$${totalOriginal.toFixed(2)}</span>
                </div>`;
            
            if (hasDiscount) {
                html += `
                <div class="hp-summary-row hp-discount-row">
                    <span class="hp-summary-label">Discount (${discountPercent.toFixed(1)}%):</span>
                    <span class="hp-summary-value hp-discount-value">-$${totalDiscount.toFixed(2)}</span>
                </div>`;
            }

            html += `
                <div class="hp-summary-row hp-total-row">
                    <span class="hp-summary-label">Offer Total:</span>
                    <span class="hp-summary-value hp-total-value">$${totalSale.toFixed(2)}</span>
                </div>`;

            $summary.html(html);

            // Auto-update the Offer Price field with the calculated total
            this._updateOfferPriceField($container, totalSale);
            
            // Auto-update the Discount Label field
            this._updateDiscountLabelField($container, discountPercent, hasDiscount);
        },

        /**
         * Update the Offer Price ACF field with the calculated total
         */
        _updateOfferPriceField: function($container, totalSale) {
            // Find the offer row
            const $row = $container.closest('.acf-row');
            if (!$row.length) return;

            // Find the offer_price field input
            const $priceField = $row.find('.acf-field[data-name="offer_price"] input[type="number"]');
            if ($priceField.length) {
                // Always update to match the table total
                $priceField.val(totalSale.toFixed(2));
            }
        },

        /**
         * Update the Discount Label ACF field with auto-generated text
         */
        _updateDiscountLabelField: function($container, discountPercent, hasDiscount) {
            // Find the offer row
            const $row = $container.closest('.acf-row');
            if (!$row.length) return;

            // Find the discount_label field input
            const $labelField = $row.find('.acf-field[data-name="offer_discount_label"] input[type="text"]');
            if (!$labelField.length) return;

            // Generate auto label
            const autoLabel = hasDiscount ? `Save ${discountPercent.toFixed(0)}%` : '';
            
            // Update placeholder to show what auto value would be
            $labelField.attr('placeholder', autoLabel || 'No discount');
            
            // If field is empty or matches a previous auto value, update it
            const currentVal = $labelField.val();
            const isAutoValue = !currentVal || /^Save \d+(\.\d+)?%$/.test(currentVal);
            
            if (isAutoValue) {
                $labelField.val(autoLabel);
            }
        },

        /**
         * Destroy a table instance
         */
        destroy: function(containerId) {
            if (this.tables[containerId]) {
                this.tables[containerId].destroy();
                delete this.tables[containerId];
            }
        },

        _escapeHtml: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        _escapeAttr: function(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;');
        }
    };

})(jQuery);

