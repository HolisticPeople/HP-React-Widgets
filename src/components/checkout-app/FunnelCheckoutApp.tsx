import { useState, useCallback, useMemo, useEffect, useRef } from 'react';
import { CheckoutStep } from './steps/CheckoutStep';
import { ProcessingStep } from './steps/ProcessingStep';
import { UpsellStep } from './steps/UpsellStep';
import { ThankYouStep } from './steps/ThankYouStep';
import { useCheckoutApi } from './hooks/useCheckoutApi';
import { useResponsive } from '@/hooks/use-responsive';
import { cn } from '@/lib/utils';
import type { 
  CheckoutStep as CheckoutStepType, 
  FunnelCheckoutAppConfig, 
  CartItem, 
  Address, 
  ShippingRate,
  OrderSummary,
  CustomerData,
  Offer,
  KitSelection,
  KitProduct,
  CustomizableKitOffer,
} from './types';

// Default values as constants to prevent recreation on each render
const DEFAULT_FREE_SHIPPING_COUNTRIES: string[] = ['US'];
const DEFAULT_UPSELL_OFFERS: any[] = [];

export interface FunnelCheckoutAppProps extends FunnelCheckoutAppConfig {
  apiBase?: string;
}

export const FunnelCheckoutApp = (props: FunnelCheckoutAppProps) => {
  // Guard against undefined props (can happen with multiple render attempts)
  if (!props || typeof props !== 'object') {
    return <div className="hp-funnel-error p-4 bg-red-900/50 text-red-200 rounded">Loading checkout...</div>;
  }
  
  const {
    funnelId,
    funnelName,
    funnelSlug,
    offers: rawOffers,
    defaultOfferId,
    logoUrl,
    logoLink = '/',
    landingUrl,
    freeShippingCountries = DEFAULT_FREE_SHIPPING_COUNTRIES,
    enablePoints = true,
    enableCustomerLookup = true,
    stripePublishableKey,
    stripeMode = 'live',
    upsellOffers = DEFAULT_UPSELL_OFFERS,
    showUpsell = true,
    thankYouHeadline = 'Thank You for Your Order!',
    thankYouMessage = 'Your order has been confirmed.',
    accentColor = '#eab308',
    footerText = '',
    footerDisclaimer = '',
    apiBase = '/wp-json/hp-rw/v1',
  } = props;

  // Ensure offers is always a stable array reference - computed BEFORE state hooks
  const offers = Array.isArray(rawOffers) ? rawOffers : [];
  
  // Guard against missing required data
  if (!funnelId || offers.length === 0) {
    return <div className="hp-funnel-error p-4 bg-yellow-900/50 text-yellow-200 rounded">Initializing checkout...</div>;
  }

  // Current step in the checkout flow
  const [currentStep, setCurrentStep] = useState<CheckoutStepType>('checkout');
  
  // Compute default offer ID once - simple calculation, no function
  const initialOfferId = defaultOfferId || (offers.length > 0 ? (offers.find(o => o.isFeatured)?.id || offers[0].id) : '');
  
  // Offer selection - now uses offer ID instead of product ID
  const [selectedOfferId, setSelectedOfferId] = useState<string>(initialOfferId);
  
  // Kit selection for customizable kits - start empty, will be set via useEffect or handleOfferSelect
  const [kitSelection, setKitSelection] = useState<KitSelection>({});
  
  // Offer quantity multiplier (for non-kit offers - buy multiple of the same offer)
  const [offerQuantity, setOfferQuantity] = useState<number>(1);
  
  // Initialize kit selection on mount if the initial offer is a kit
  useEffect(() => {
    const offer = offers.find(o => o.id === selectedOfferId);
    if (offer?.type === 'customizable_kit' && 'kitProducts' in offer) {
      const kitProducts = (offer as CustomizableKitOffer).kitProducts || [];
      const newSelection: KitSelection = {};
      kitProducts.forEach((product: KitProduct) => {
        const minQty = product.role === 'must' ? 1 : 0;
        newSelection[product.sku] = Math.max(minQty, product.qty || 0);
      });
      setKitSelection(newSelection);
    }
  // Only run once on mount
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  
  // Checkout data that persists across steps
  const [customerData, setCustomerData] = useState<CustomerData | null>(null);
  const [shippingAddress, setShippingAddress] = useState<Address | null>(null);
  const [selectedRate, setSelectedRate] = useState<ShippingRate | null>(null);
  const [pointsToRedeem, setPointsToRedeem] = useState(0);
  
  // Payment result data
  const [paymentIntentId, setPaymentIntentId] = useState<string>('');
  const [orderId, setOrderId] = useState<number>(0);
  const [orderSummary, setOrderSummary] = useState<OrderSummary | null>(null);
  
  // Current upsell index (for multiple upsells)
  const [currentUpsellIndex, setCurrentUpsellIndex] = useState(0);

  // API hook
  const api = useCheckoutApi({ apiBase, funnelId, funnelName });

  // Track if we've done URL-based initialization
  const hasInitializedFromUrl = useRef(false);

  // On mount: Check URL for thank-you state restoration
  // This allows the page to be refreshed and still show the correct step
  useEffect(() => {
    if (hasInitializedFromUrl.current) return;
    hasInitializedFromUrl.current = true;
    
    const path = window.location.pathname;
    const params = new URLSearchParams(window.location.search);
    
    // Check if we're on the thank-you URL
    if (path.includes('/thank-you')) {
      const urlOrderId = parseInt(params.get('order_id') || '0', 10);
      const urlPiId = params.get('pi_id') || '';
      
      if (urlOrderId || urlPiId) {
        // Restore state from URL
        if (urlOrderId) setOrderId(urlOrderId);
        if (urlPiId) setPaymentIntentId(urlPiId);
        setCurrentStep('thankyou');
        
        // Fetch order summary
        api.getOrderSummary(urlOrderId || undefined, urlPiId || undefined)
          .then(summary => {
            if (summary) setOrderSummary(summary);
          })
          .catch(err => {
            console.error('[FunnelCheckoutApp] Failed to restore order summary:', err);
          });
      }
    }
    // Check if we're on the upsell URL
    else if (path.includes('/upsell')) {
      const urlOrderId = parseInt(params.get('order_id') || '0', 10);
      const urlPiId = params.get('pi_id') || '';
      
      if (urlOrderId || urlPiId) {
        if (urlOrderId) setOrderId(urlOrderId);
        if (urlPiId) setPaymentIntentId(urlPiId);
        
        // If we have upsells, show upsell step, otherwise go to thank you
        if (showUpsell && upsellOffers.length > 0) {
          setCurrentStep('upsell');
        } else {
          setCurrentStep('thankyou');
        }
        
        // Fetch order summary
        api.getOrderSummary(urlOrderId || undefined, urlPiId || undefined)
          .then(summary => {
            if (summary) setOrderSummary(summary);
          })
          .catch(err => {
            console.error('[FunnelCheckoutApp] Failed to restore order summary:', err);
          });
      }
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Get the selected offer
  const selectedOffer = useMemo(
    () => offers.find(o => o.id === selectedOfferId) as Offer | undefined,
    [offers, selectedOfferId]
  );

  // Scroll to top when step changes
  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [currentStep]);
  
  // Update browser URL when step changes (Round 2 improvement for Google Merchant Center)
  // This creates a canonical thank-you URL at /express-shop/{slug}/thank-you/
  // For thank-you step, include order_id and pi_id in URL for refresh support
  useEffect(() => {
    if (!funnelSlug) return;
    
    const basePath = `/express-shop/${funnelSlug}`;
    let newPath = basePath + '/checkout/';
    let newSearch = '';
    
    if (currentStep === 'thankyou') {
      newPath = basePath + '/thank-you/';
      // Include order identifiers in URL for refresh support
      const params = new URLSearchParams();
      if (orderId) params.set('order_id', String(orderId));
      if (paymentIntentId) params.set('pi_id', paymentIntentId);
      newSearch = params.toString() ? '?' + params.toString() : '';
    } else if (currentStep === 'upsell') {
      newPath = basePath + '/upsell/';
      // Also include order info for upsell step
      const params = new URLSearchParams();
      if (orderId) params.set('order_id', String(orderId));
      if (paymentIntentId) params.set('pi_id', paymentIntentId);
      newSearch = params.toString() ? '?' + params.toString() : '';
    } else if (currentStep === 'processing') {
      newPath = basePath + '/processing/';
    }
    
    const fullUrl = newPath + newSearch;
    const currentFullPath = window.location.pathname + window.location.search;
    
    // Only update if URL is different from current
    if (currentFullPath !== fullUrl) {
      window.history.replaceState({ step: currentStep, orderId, piId: paymentIntentId }, '', fullUrl);
    }
  }, [currentStep, funnelSlug, orderId, paymentIntentId]);

  // Handle offer selection change - reset kit selection and quantity ONLY when changing offers
  const handleOfferSelect = useCallback((offerId: string) => {
    // Don't reset if selecting the same offer (prevents kit selection reset on internal clicks)
    if (offerId === selectedOfferId) {
      return;
    }
    
    setSelectedOfferId(offerId);
    setOfferQuantity(1); // Reset quantity when changing offers
    
    // Find the new offer and initialize kit selection if needed
    const newOffer = offers.find(o => o.id === offerId);
    if (newOffer?.type === 'customizable_kit' && 'kitProducts' in newOffer) {
      const kitProducts = (newOffer as CustomizableKitOffer).kitProducts || [];
      const newSelection: KitSelection = {};
      kitProducts.forEach((product: KitProduct) => {
        // Use admin-set qty as default/minimum for 'must' products
        // For 'must' products, admin qty IS the minimum required
        newSelection[product.sku] = product.qty || (product.role === 'must' ? 1 : 0);
      });
      setKitSelection(newSelection);
    } else {
      setKitSelection({});
    }
  }, [offers, selectedOfferId]);

  // Allow other on-page sections (e.g., FunnelProducts) to select an offer without navigation
  useEffect(() => {
    const onSelect = (e: Event) => {
      const ce = e as CustomEvent<{ offerId?: string }>;
      const offerId = ce?.detail?.offerId;
      if (!offerId) return;
      handleOfferSelect(offerId);
      setCurrentStep('checkout');
    };
    window.addEventListener('hp_funnel_offer_select', onSelect as EventListener);
    return () => window.removeEventListener('hp_funnel_offer_select', onSelect as EventListener);
  }, [handleOfferSelect]);
  
  // Handle offer quantity change (for non-kit offers)
  const handleOfferQuantityChange = useCallback((qty: number) => {
    setOfferQuantity(Math.max(1, qty)); // Minimum 1
  }, []);

  // Handle kit product quantity change with minimum enforcement
  const handleKitQuantityChange = useCallback((sku: string, qty: number) => {
    // Get the current offer to check product roles
    if (!selectedOffer || selectedOffer.type !== 'customizable_kit') {
      // Not a kit offer, just update the quantity
      setKitSelection(prev => ({ ...prev, [sku]: Math.max(0, qty) }));
      return;
    }
    
    // Find the product in the kit
    const kitProducts = (selectedOffer as CustomizableKitOffer).kitProducts || [];
    const product = kitProducts.find((p: KitProduct) => p.sku === sku);
    
    // Enforce minimum based on role: 'must' = min 1, 'optional' = min 0
    const minQty = product?.role === 'must' ? 1 : 0;
    const validQty = Math.max(minQty, qty);
    
    setKitSelection(prev => ({ ...prev, [sku]: validQty }));
  }, [selectedOffer]);

  // Build cart items from selection (multiplied by offerQuantity for non-kit offers)
  const getCartItems = useCallback((): CartItem[] => {
    if (!selectedOffer) return [];
    
    const items: CartItem[] = [];
    
    switch (selectedOffer.type) {
      case 'single': {
        // Calculate per-unit price from offer's calculated price
        const perUnitPrice = selectedOffer.calculatedPrice 
          ? selectedOffer.calculatedPrice / (selectedOffer.quantity || 1)
          : undefined;
        items.push({ 
          sku: selectedOffer.productSku, 
          qty: (selectedOffer.quantity || 1) * offerQuantity, // Multiply by quantity selector
          salePrice: perUnitPrice,  // Use calculated offer price
        });
        break;
      }
      
      case 'fixed_bundle': {
        selectedOffer.bundleItems.forEach(item => {
          items.push({ 
            sku: item.sku, 
            qty: item.qty * offerQuantity, // Multiply by quantity selector
            // Use the effective sale price (0 = FREE, regularPrice = no discount)
            salePrice: item.salePrice ?? item.price,
            regularPrice: item.regularPrice,
          });
        });
        break;
      }
      
      case 'customizable_kit': {
        // Kit offers don't use offerQuantity - they have their own customization
        selectedOffer.kitProducts.forEach((product: KitProduct) => {
          const selectedQty = kitSelection[product.sku] || 0;
          if (selectedQty > 0) {
            // For Must Have products, admin-set qty is the minimum required
            // All units up to minQty are at discountedPrice, beyond that at subsequentSalePrice
            const minQty = product.role === 'must' ? (product.qty || 1) : 0;
            const hasTieredPricing = product.role === 'must' 
              && product.subsequentSalePrice !== undefined 
              && product.subsequentSalePrice !== product.discountedPrice;
            
            if (hasTieredPricing && selectedQty > minQty) {
              // Split into two line items: min qty at kit price, rest at subsequent price
              // Required units at kit included price
              items.push({
                sku: product.sku,
                qty: minQty,
                salePrice: product.discountedPrice,
                label: '(Kit Included)',
              });
              // Additional units at subsequent price
              items.push({
                sku: product.sku,
                qty: selectedQty - minQty,
                salePrice: product.subsequentSalePrice,
              });
            } else {
              // Single line item for all units
              items.push({
                sku: product.sku,
                qty: selectedQty,
                salePrice: product.discountedPrice,
                itemDiscountPercent: product.discountType === 'percent' ? product.discountValue : undefined,
              });
            }
          }
        });
        break;
      }
    }
    
    return items;
  }, [selectedOffer, kitSelection, offerQuantity]);

  // Calculate current price for display (multiplied by offerQuantity for non-kit offers)
  const calculateOfferPrice = useMemo(() => {
    if (!selectedOffer) return { original: 0, discounted: 0 };
    
    let original = 0;
    let discounted = 0;
    
    switch (selectedOffer.type) {
      case 'single':
      case 'fixed_bundle':
        // Multiply by offerQuantity for non-kit offers
        original = (selectedOffer.originalPrice || 0) * offerQuantity;
        discounted = (selectedOffer.calculatedPrice || 0) * offerQuantity;
        break;
        
      case 'customizable_kit': {
        // Kit offers don't use offerQuantity
        let subtotal = 0;
        let originalTotal = 0;
        
        selectedOffer.kitProducts.forEach((product: KitProduct) => {
          const selectedQty = kitSelection[product.sku] || 0;
          if (selectedQty > 0) {
            originalTotal += product.regularPrice * selectedQty;
            
            // For Must Have products, admin-set qty is the minimum required
            // All units up to minQty are at discountedPrice, beyond that at subsequentSalePrice
            const minQty = product.role === 'must' ? (product.qty || 1) : 0;
            
            // Apply tiered pricing for Must Have products when qty exceeds minimum
            if (product.role === 'must' && selectedQty > minQty && product.subsequentSalePrice !== undefined) {
              // Required qty at discountedPrice, additional at subsequentSalePrice
              const additionalQty = selectedQty - minQty;
              subtotal += (product.discountedPrice * minQty) + (product.subsequentSalePrice * additionalQty);
            } else {
              // All units at same price
              subtotal += product.discountedPrice * selectedQty;
            }
          }
        });
        
        // Apply global kit discount
        let finalPrice = subtotal;
        if (selectedOffer.discountType === 'percent' && selectedOffer.discountValue > 0) {
          finalPrice = subtotal * (1 - selectedOffer.discountValue / 100);
        } else if (selectedOffer.discountType === 'fixed' && selectedOffer.discountValue > 0) {
          finalPrice = Math.max(0, subtotal - selectedOffer.discountValue);
        }
        
        original = originalTotal;
        discounted = Math.round(finalPrice * 100) / 100;
        break;
      }
    }
    
    return { 
      original: Math.round(original * 100) / 100, 
      discounted: Math.round(discounted * 100) / 100 
    };
  }, [selectedOffer, kitSelection, offerQuantity]);

  // Handle customer lookup success
  const handleCustomerLookup = useCallback((data: CustomerData) => {
    setCustomerData(data);
    
    // Auto-fill shipping address if available
    if (data.shipping) {
      setShippingAddress(data.shipping);
    }
  }, []);

  // Handle checkout step completion (payment successful)
  const handleCheckoutComplete = useCallback(async (piId: string, address: Address, orderDraftId: string) => {
    setPaymentIntentId(piId);
    setShippingAddress(address);
    setCurrentStep('processing');
    
    try {
      // 1. Explicitly complete the order on the backend
      const result = await api.completeOrder(orderDraftId, piId);
      if (result.success) {
        setOrderId(result.orderId);
      }

      // 2. Fetch order summary
      const summary = await api.getOrderSummary(result.orderId, piId);
      if (summary) {
        setOrderSummary(summary);
        
        // Decide next step
        if (showUpsell && upsellOffers.length > 0) {
          setCurrentStep('upsell');
        } else {
          setCurrentStep('thankyou');
        }
      } else {
        // Fallback - go to thank you without summary
        setCurrentStep('thankyou');
      }
    } catch (err) {
      console.error('[FunnelCheckoutApp] Failed to complete order or get summary:', err);
      // Still proceed to next step
      if (showUpsell && upsellOffers.length > 0) {
        setCurrentStep('upsell');
      } else {
        setCurrentStep('thankyou');
      }
    }
  }, [api, showUpsell, upsellOffers.length]);

  // Handle upsell accept
  const handleUpsellAccept = useCallback(async () => {
    if (!orderId || !paymentIntentId) return;
    
    const offer = upsellOffers[currentUpsellIndex];
    if (!offer) return;
    
    try {
      await api.chargeUpsell(
        orderId,
        paymentIntentId,
        offer.sku,
        1,
        offer.discountPercent
      );
      
      // Refresh order summary
      const summary = await api.getOrderSummary(orderId);
      if (summary) {
        setOrderSummary(summary);
      }
    } catch (err) {
      console.error('[FunnelCheckoutApp] Upsell charge failed:', err);
    }
    
    // Move to next upsell or thank you
    if (currentUpsellIndex < upsellOffers.length - 1) {
      setCurrentUpsellIndex(i => i + 1);
    } else {
      setCurrentStep('thankyou');
    }
  }, [orderId, paymentIntentId, currentUpsellIndex, upsellOffers, api]);

  // Handle upsell decline
  const handleUpsellDecline = useCallback(() => {
    if (currentUpsellIndex < upsellOffers.length - 1) {
      setCurrentUpsellIndex(i => i + 1);
    } else {
      setCurrentStep('thankyou');
    }
  }, [currentUpsellIndex, upsellOffers.length]);

  // Apply CSS custom properties for accent color
  const rootStyle = useMemo(() => ({
    '--funnel-accent': accentColor,
  } as React.CSSProperties), [accentColor]);

  return (
    <div 
      className="hp-funnel-checkout-app hp-funnel-section hp-funnel-checkout min-h-screen bg-background"
      style={rootStyle}
      data-section-type="checkout"
    >
      {/* Logo Header */}
      {logoUrl && (
        <div className="py-6 px-4">
          <div className="max-w-6xl mx-auto">
            <a href={logoLink} target="_blank" rel="noopener noreferrer">
              <img 
                src={logoUrl} 
                alt={funnelName} 
                className="h-8 opacity-70 hover:opacity-100 transition-opacity" 
              />
            </a>
          </div>
        </div>
      )}

      {/* Step Content */}
      <div className="px-4 pb-12">
        {currentStep === 'checkout' && (
          <CheckoutStep
            funnelId={funnelId}
            funnelName={funnelName}
            offers={offers}
            selectedOfferId={selectedOfferId}
            onSelectOffer={handleOfferSelect}
            kitSelection={kitSelection}
            onKitQuantityChange={handleKitQuantityChange}
            offerQuantity={offerQuantity}
            onOfferQuantityChange={handleOfferQuantityChange}
            offerPrice={calculateOfferPrice}
            customerData={customerData}
            onCustomerLookup={handleCustomerLookup}
            shippingAddress={shippingAddress}
            onShippingAddressChange={setShippingAddress}
            selectedRate={selectedRate}
            onSelectRate={setSelectedRate}
            pointsToRedeem={pointsToRedeem}
            onPointsRedeemChange={setPointsToRedeem}
            freeShippingCountries={freeShippingCountries}
            enablePoints={enablePoints}
            enableCustomerLookup={enableCustomerLookup}
            showAllOffers={props.showAllOffers ?? true}
            stripePublishableKey={stripePublishableKey}
            stripeMode={stripeMode}
            landingUrl={landingUrl}
            apiBase={apiBase}
            getCartItems={getCartItems}
            initialUserData={props.initialUserData}
            onComplete={handleCheckoutComplete}
            pageTitle={props.pageTitle}
            pageSubtitle={props.pageSubtitle}
            tosPageId={props.tosPageId}
            privacyPageId={props.privacyPageId}
          />
        )}

        {currentStep === 'processing' && (
          <ProcessingStep 
            message="Processing your order..."
          />
        )}

        {currentStep === 'upsell' && upsellOffers[currentUpsellIndex] && (
          <UpsellStep
            offer={upsellOffers[currentUpsellIndex]}
            orderId={orderId}
            paymentIntentId={paymentIntentId}
            onAccept={handleUpsellAccept}
            onDecline={handleUpsellDecline}
            apiBase={apiBase}
          />
        )}

        {currentStep === 'thankyou' && (
          <ThankYouStep
            funnelName={funnelName}
            headline={thankYouHeadline}
            message={thankYouMessage}
            orderSummary={orderSummary}
            logoUrl={logoUrl}
            logoLink={logoLink}
          />
        )}
      </div>

      {/* Footer */}
      <footer className="py-8 px-4 border-t border-border/50 mt-auto">
        <div className="max-w-6xl mx-auto text-center text-muted-foreground text-sm">
          {footerText && <p className="mb-2">{footerText}</p>}
          {footerDisclaimer && (
            <p className="text-xs">{footerDisclaimer}</p>
          )}
        </div>
      </footer>
    </div>
  );
};

export default FunnelCheckoutApp;
