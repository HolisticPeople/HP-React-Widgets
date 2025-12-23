/**
 * HP Offer Products Table (Tabulator-based)
 * Simplified version inspired by EAO products table
 * @version 1.0.0
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

            // Update summary
            this._updateSummary($container, products);
        },

        /**
         * Build column definitions
         * @param {boolean} isKit - Whether this is a customizable kit (shows Role column)
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

            // Pricing group
            columns.push({
                title: "Pricing",
                resizable: false,
                columns: [
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
                ]
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
                const qty = parseInt(p.qty) || 1;
                const lineTotal = salePrice * qty;
                
                // Calculate discount percent
                let discountPercent = 0;
                if (originalPrice > 0 && salePrice < originalPrice) {
                    discountPercent = ((originalPrice - salePrice) / originalPrice) * 100;
                }

                const sku = p.sku || '';
                const role = p.role || 'optional';
                
                const rowData = {
                    id: sku,
                    sku: sku,
                    _originalPrice: originalPrice,
                    _salePrice: salePrice,
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

                // Add role column for kits
                if (isKit) {
                    rowData.role = this._renderRole(p, role);
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
            const qty = parseInt(p.qty) || 1;
            const sku = p.sku || '';
            return `<input type="number" class="hp-qty-input" value="${qty}" min="1" max="99" data-sku="${this._escapeAttr(sku)}">`;
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
         * Update the summary section below the table
         */
        _updateSummary: function($container, products) {
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
                const qty = parseInt(p.qty) || 1;
                totalOriginal += originalPrice * qty;
                totalSale += salePrice * qty;
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

