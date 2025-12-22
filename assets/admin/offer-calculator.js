/**
 * Offer Calculator for Admin
 * 
 * Calculates and displays the actual discount savings for customizable kits
 * in real-time as admin edits product discounts.
 */
(function($) {
    'use strict';

    // Cache for product prices
    const productPriceCache = {};

    /**
     * Initialize the calculator when ACF fields are loaded.
     */
    function init() {
        if (typeof acf === 'undefined') {
            return;
        }

        // Listen for changes in offer fields
        acf.addAction('ready', setupCalculators);
        acf.addAction('append', setupCalculators);
        
        // Watch for field changes
        $(document).on('change', '[data-key="field_kit_products"] input, [data-key="field_kit_products"] select', debounce(recalculate, 300));
        $(document).on('change', '[data-key="field_offer_discount_type"], [data-key="field_offer_discount_value"]', debounce(recalculate, 300));
    }

    /**
     * Set up calculators for all offer rows.
     */
    function setupCalculators() {
        // Find all offer repeater rows that are customizable_kit
        $('[data-key="field_funnel_offers"] .acf-row').each(function() {
            const $row = $(this);
            const offerType = $row.find('[data-key="field_offer_type"] select').val();
            
            if (offerType === 'customizable_kit') {
                calculateForRow($row);
            }
        });
    }

    /**
     * Recalculate on change.
     */
    function recalculate() {
        const $row = $(this).closest('[data-key="field_funnel_offers"] > .acf-input > .acf-repeater > .acf-row');
        if ($row.length) {
            calculateForRow($row);
        }
    }

    /**
     * Calculate discount for a specific offer row.
     */
    async function calculateForRow($row) {
        const offerType = $row.find('[data-key="field_offer_type"] select').val();
        if (offerType !== 'customizable_kit') {
            return;
        }

        const $calculator = $row.find('#hp-kit-calculator');
        if (!$calculator.length) {
            return;
        }

        // Collect kit products
        const products = [];
        $row.find('[data-key="field_kit_products"] .acf-row:not(.acf-clone)').each(function() {
            const $productRow = $(this);
            const sku = $productRow.find('[data-key="field_kit_product_sku"] input').val();
            const role = $productRow.find('[data-key="field_kit_product_role"] select').val();
            const qty = parseInt($productRow.find('[data-key="field_kit_product_qty"] input').val()) || 0;
            const discountType = $productRow.find('[data-key="field_kit_product_discount_type"] select').val();
            const discountValue = parseFloat($productRow.find('[data-key="field_kit_product_discount_value"] input').val()) || 0;
            
            // Only include products that will be in the default selection
            if (sku && (role === 'must' || role === 'default') && qty > 0) {
                products.push({
                    sku: sku,
                    qty: qty,
                    discountType: discountType,
                    discountValue: discountValue
                });
            }
        });

        if (products.length === 0) {
            updateCalculatorDisplay($calculator, 0, 0, 0);
            return;
        }

        // Get global kit discount
        const globalDiscountType = $row.find('[data-key="field_offer_discount_type"] select').val();
        const globalDiscountValue = parseFloat($row.find('[data-key="field_offer_discount_value"] input').val()) || 0;

        // Fetch prices for all SKUs
        try {
            const prices = await fetchProductPrices(products.map(p => p.sku));
            
            let originalTotal = 0;
            let afterProductDiscounts = 0;

            products.forEach(product => {
                const price = prices[product.sku] || 0;
                const originalPrice = price * product.qty;
                originalTotal += originalPrice;

                let discountedPrice = price;
                if (product.discountType === 'percent' && product.discountValue > 0) {
                    discountedPrice = price * (1 - product.discountValue / 100);
                } else if (product.discountType === 'fixed' && product.discountValue > 0) {
                    discountedPrice = Math.max(0, price - product.discountValue);
                }
                
                afterProductDiscounts += discountedPrice * product.qty;
            });

            // Apply global kit discount
            let finalPrice = afterProductDiscounts;
            if (globalDiscountType === 'percent' && globalDiscountValue > 0) {
                finalPrice = afterProductDiscounts * (1 - globalDiscountValue / 100);
            } else if (globalDiscountType === 'fixed' && globalDiscountValue > 0) {
                finalPrice = Math.max(0, afterProductDiscounts - globalDiscountValue);
            }

            updateCalculatorDisplay($calculator, originalTotal, afterProductDiscounts, finalPrice);
        } catch (error) {
            console.error('Error calculating offer discount:', error);
        }
    }

    /**
     * Update the calculator display.
     */
    function updateCalculatorDisplay($calculator, originalTotal, afterProductDiscounts, finalPrice) {
        const savings = originalTotal - finalPrice;
        const savingsPercent = originalTotal > 0 ? (savings / originalTotal * 100) : 0;

        $calculator.find('#calc-original').text(formatCurrency(originalTotal));
        $calculator.find('#calc-after-products').text(formatCurrency(afterProductDiscounts));
        $calculator.find('#calc-final').text(formatCurrency(finalPrice));
        $calculator.find('#calc-savings').text(
            formatCurrency(savings) + ' (' + savingsPercent.toFixed(1) + '%)'
        );

        // Highlight if savings are significant
        if (savingsPercent >= 10) {
            $calculator.find('#calc-savings').css('color', '#00a32a');
        } else {
            $calculator.find('#calc-savings').css('color', '#1d2327');
        }
    }

    /**
     * Fetch product prices from the server.
     */
    async function fetchProductPrices(skus) {
        // Check cache first
        const uncachedSkus = skus.filter(sku => !productPriceCache.hasOwnProperty(sku));
        
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

                // Cache the results
                if (response && response.prices) {
                    Object.assign(productPriceCache, response.prices);
                }
            } catch (error) {
                console.error('Error fetching product prices:', error);
            }
        }

        // Return all requested prices from cache
        const result = {};
        skus.forEach(sku => {
            result[sku] = productPriceCache[sku] || 0;
        });
        return result;
    }

    /**
     * Format a number as currency.
     */
    function formatCurrency(amount) {
        return '$' + amount.toFixed(2);
    }

    /**
     * Debounce function to limit API calls.
     */
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);

