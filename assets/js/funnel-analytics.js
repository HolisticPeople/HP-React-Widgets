/**
 * HP Funnel Analytics - Shadow Listener
 * 
 * Bridges custom React buttons to Google Ads/GA4.
 * Provides dual tracking to both GTM dataLayer and GA4 gtag.
 * 
 * Events tracked:
 * - view_item: On funnel landing page load
 * - add_to_cart: On buy button click
 * - begin_checkout: On /checkout/ URL
 * - purchase: On /thank-you/ URL with order_id
 * 
 * @since 2.9.0
 */
(function() {
    'use strict';

    // Get settings and data injected by PHP
    var settings = window.hpFunnelSettings || {};
    var funnelData = window.hpFunnelData || {};

    /**
     * Push event to both GTM dataLayer and GA4 gtag.
     * 
     * @param {string} eventName - GA4 event name
     * @param {object} data - Ecommerce data object
     */
    function pushEvent(eventName, data) {
        // GTM dataLayer
        if (settings.pushToGtm !== false) {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: eventName,
                ecommerce: data
            });
        }

        // GA4 gtag (if available)
        if (settings.pushToGa4 !== false && typeof gtag === 'function') {
            gtag('event', eventName, data);
        }

        // Debug logging
        if (settings.debugMode) {
            console.log('%c[HP Funnel Analytics]', 'color: #2271b1; font-weight: bold;', eventName, data);
        }
    }

    /**
     * Update funnel data with dynamic offer selection.
     * Called when user selects a different offer or changes quantity.
     * 
     * @param {object} offerData - Updated offer data
     */
    function updateFunnelData(offerData) {
        if (offerData && typeof offerData === 'object') {
            window.hpFunnelData = Object.assign({}, funnelData, offerData);
            funnelData = window.hpFunnelData;

            if (settings.debugMode) {
                console.log('%c[HP Funnel Analytics]', 'color: #2271b1; font-weight: bold;', 'Data updated:', funnelData);
            }
        }
    }

    /**
     * Attach click listeners to buy buttons.
     */
    function attachButtonListeners() {
        var selectors = settings.buttonSelectors || '.hp-funnel-cta-btn, [data-checkout-submit], .hp-checkout-submit-btn, .offer-card-select-btn';
        
        // Split by comma and trim
        var selectorList = selectors.split(',').map(function(s) { return s.trim(); }).join(', ');
        
        document.querySelectorAll(selectorList).forEach(function(btn) {
            // Avoid double-binding
            if (btn.hasAttribute('data-hp-analytics-bound')) {
                return;
            }
            btn.setAttribute('data-hp-analytics-bound', 'true');

            btn.addEventListener('click', function() {
                if (settings.trackAddToCart !== false && funnelData.value) {
                    pushEvent('add_to_cart', {
                        currency: funnelData.currency || 'USD',
                        value: funnelData.value,
                        items: funnelData.items || []
                    });
                }
            });
        });
    }

    /**
     * Handle URL-based step tracking.
     */
    function handleUrlTracking() {
        var path = window.location.pathname;

        // BEGIN_CHECKOUT on /checkout/ URL
        if (path.includes('/checkout')) {
            if (settings.trackBeginCheckout !== false && funnelData.value) {
                pushEvent('begin_checkout', {
                    currency: funnelData.currency || 'USD',
                    value: funnelData.value,
                    items: funnelData.items || []
                });
            }
            return; // Don't fire view_item on checkout
        }

        // PURCHASE on /thank-you/ URL
        if (path.includes('/thank-you') || path.includes('/thankyou')) {
            if (settings.trackPurchase !== false) {
                var params = new URLSearchParams(window.location.search);
                var orderId = params.get('order_id') || 'HP-' + Date.now();
                
                // Try to get actual order value from query params or use funnel default
                var orderValue = parseFloat(params.get('order_total')) || funnelData.value || 0;
                
                pushEvent('purchase', {
                    transaction_id: orderId,
                    currency: funnelData.currency || 'USD',
                    value: orderValue,
                    items: funnelData.items || []
                });
            }
            return; // Don't fire view_item on thank you
        }

        // VIEW_ITEM on landing page (not checkout or thank-you)
        if (settings.trackViewItem !== false && funnelData.value) {
            pushEvent('view_item', {
                currency: funnelData.currency || 'USD',
                value: funnelData.value,
                items: funnelData.items || []
            });
        }
    }

    /**
     * Initialize analytics tracking.
     */
    function init() {
        // Check if we have funnel data
        if (!funnelData.funnelId && !funnelData.value) {
            if (settings.debugMode) {
                console.log('%c[HP Funnel Analytics]', 'color: #d63638; font-weight: bold;', 'No funnel data found, skipping initialization');
            }
            return;
        }

        if (settings.debugMode) {
            console.log('%c[HP Funnel Analytics]', 'color: #00a32a; font-weight: bold;', 'Initialized', {
                funnelId: funnelData.funnelId,
                funnelName: funnelData.funnelName,
                value: funnelData.value,
                settings: settings
            });
        }

        // Handle URL-based tracking
        handleUrlTracking();

        // Attach button listeners
        attachButtonListeners();

        // Re-attach listeners on DOM mutations (for SPA navigation)
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                var shouldRebind = mutations.some(function(m) {
                    return m.addedNodes.length > 0;
                });
                if (shouldRebind) {
                    attachButtonListeners();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    // Expose update function globally for React components
    window.hpFunnelAnalytics = {
        updateData: updateFunnelData,
        pushEvent: pushEvent
    };

    // Initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

















