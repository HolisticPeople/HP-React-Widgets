import { useState, useCallback, useMemo } from 'react';
import { CheckoutStep } from './steps/CheckoutStep';
import { ProcessingStep } from './steps/ProcessingStep';
import { UpsellStep } from './steps/UpsellStep';
import { ThankYouStep } from './steps/ThankYouStep';
import { useCheckoutApi } from './hooks/useCheckoutApi';
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

export interface FunnelCheckoutAppProps extends FunnelCheckoutAppConfig {
  apiBase?: string;
}

export const FunnelCheckoutApp = (props: FunnelCheckoutAppProps) => {
  const {
    funnelId,
    funnelName,
    funnelSlug,
    offers,
    defaultOfferId,
    logoUrl,
    logoLink = '/',
    landingUrl,
    freeShippingCountries = ['US'],
    enablePoints = true,
    enableCustomerLookup = true,
    stripePublishableKey,
    upsellOffers = [],
    showUpsell = true,
    thankYouHeadline = 'Thank You for Your Order!',
    thankYouMessage = 'Your order has been confirmed.',
    accentColor = 'hsl(45, 95%, 60%)',
    footerText = '',
    footerDisclaimer = '',
    apiBase = '/wp-json/hp-rw/v1',
  } = props;

  // Current step in the checkout flow
  const [currentStep, setCurrentStep] = useState<CheckoutStepType>('checkout');
  
  // Offer selection - now uses offer ID instead of product ID
  const [selectedOfferId, setSelectedOfferId] = useState<string>(() => {
    if (defaultOfferId) return defaultOfferId;
    // Default to featured offer or first offer
    const featured = offers.find(o => o.isFeatured);
    return featured?.id || (offers.length > 0 ? offers[0].id : '');
  });
  
  // Kit selection for customizable kits
  const [kitSelection, setKitSelection] = useState<KitSelection>(() => {
    // Initialize with admin-set quantities for kit offers
    const selection: KitSelection = {};
    const offer = offers.find(o => o.id === selectedOfferId);
    if (offer?.type === 'customizable_kit') {
      const kitOffer = offer as CustomizableKitOffer;
      kitOffer.kitProducts.forEach((product: KitProduct) => {
        // Use admin-set qty as default, but enforce minimum based on role
        const minQty = product.role === 'must' ? 1 : 0;
        selection[product.sku] = Math.max(minQty, product.qty);
      });
    }
    return selection;
  });
  
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

  // Get the selected offer
  const selectedOffer = useMemo(
    () => offers.find(o => o.id === selectedOfferId) as Offer | undefined,
    [offers, selectedOfferId]
  );

  // Handle offer selection change - reset kit selection if needed
  const handleOfferSelect = useCallback((offerId: string) => {
    setSelectedOfferId(offerId);
    
    // Reset kit selection for the new offer with admin-set quantities
    const offer = offers.find(o => o.id === offerId);
    if (offer?.type === 'customizable_kit') {
      const kitOffer = offer as CustomizableKitOffer;
      const newSelection: KitSelection = {};
      kitOffer.kitProducts.forEach((product: KitProduct) => {
        // Use admin-set qty as default, but enforce minimum based on role
        const minQty = product.role === 'must' ? 1 : 0;
        newSelection[product.sku] = Math.max(minQty, product.qty);
      });
      setKitSelection(newSelection);
    } else {
      setKitSelection({});
    }
  }, [offers]);

  // Handle kit product quantity change
  const handleKitQuantityChange = useCallback((sku: string, qty: number) => {
    // Get the product to check its role
    const offer = offers.find(o => o.id === selectedOfferId);
    if (offer?.type === 'customizable_kit') {
      const kitOffer = offer as CustomizableKitOffer;
      const product = kitOffer.kitProducts.find((p: KitProduct) => p.sku === sku);
      
      // Enforce minimum based on role: 'must' = min 1, 'optional' = min 0
      const minQty = product?.role === 'must' ? 1 : 0;
      const validQty = Math.max(minQty, qty);
      
      setKitSelection(prev => ({
        ...prev,
        [sku]: validQty,
      }));
    } else {
      setKitSelection(prev => ({
        ...prev,
        [sku]: Math.max(0, qty),
      }));
    }
  }, [offers, selectedOfferId]);

  // Build cart items from selection
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
          qty: selectedOffer.quantity,
          salePrice: perUnitPrice,  // Use calculated offer price
        });
        break;
      }
      
      case 'fixed_bundle': {
        selectedOffer.bundleItems.forEach(item => {
          items.push({ 
            sku: item.sku, 
            qty: item.qty,
            // Use the effective price (admin-set sale price) if different from WC price
            salePrice: item.price,  // This is the effective price from config loader
          });
        });
        break;
      }
      
      case 'customizable_kit': {
        // Add selected kit products
        selectedOffer.kitProducts.forEach((product: KitProduct) => {
          const qty = kitSelection[product.sku] || 0;
          if (qty > 0) {
            items.push({
              sku: product.sku,
              qty,
              itemDiscountPercent: product.discountType === 'percent' ? product.discountValue : undefined,
            });
          }
        });
        break;
      }
    }
    
    return items;
  }, [selectedOffer, kitSelection]);

  // Calculate current price for display
  const calculateOfferPrice = useMemo(() => {
    if (!selectedOffer) return { original: 0, discounted: 0 };
    
    switch (selectedOffer.type) {
      case 'single':
      case 'fixed_bundle':
        return {
          original: selectedOffer.originalPrice || 0,
          discounted: selectedOffer.calculatedPrice || 0,
        };
        
      case 'customizable_kit': {
        let subtotal = 0;
        let originalTotal = 0;
        
        selectedOffer.kitProducts.forEach((product: KitProduct) => {
          const qty = kitSelection[product.sku] || 0;
          if (qty > 0) {
            originalTotal += product.regularPrice * qty;
            subtotal += product.discountedPrice * qty;
          }
        });
        
        // Apply global kit discount
        let finalPrice = subtotal;
        if (selectedOffer.discountType === 'percent' && selectedOffer.discountValue > 0) {
          finalPrice = subtotal * (1 - selectedOffer.discountValue / 100);
        } else if (selectedOffer.discountType === 'fixed' && selectedOffer.discountValue > 0) {
          finalPrice = Math.max(0, subtotal - selectedOffer.discountValue);
        }
        
        return {
          original: originalTotal,
          discounted: Math.round(finalPrice * 100) / 100,
        };
      }
    }
    
    return { original: 0, discounted: 0 };
  }, [selectedOffer, kitSelection]);

  // Handle customer lookup success
  const handleCustomerLookup = useCallback((data: CustomerData) => {
    setCustomerData(data);
    
    // Auto-fill shipping address if available
    if (data.shipping) {
      setShippingAddress(data.shipping);
    }
  }, []);

  // Handle checkout step completion (payment successful)
  const handleCheckoutComplete = useCallback(async (piId: string, address: Address) => {
    setPaymentIntentId(piId);
    setShippingAddress(address);
    setCurrentStep('processing');
    
    // Fetch order summary
    try {
      // Give webhook a moment to process
      await new Promise(resolve => setTimeout(resolve, 1500));
      
      const summary = await api.getOrderSummary(undefined, piId);
      if (summary) {
        setOrderId(summary.orderId);
        setOrderSummary(summary);
        
        // Decide next step
        if (showUpsell && upsellOffers.length > 0) {
          setCurrentStep('upsell');
        } else {
          setCurrentStep('thankyou');
        }
      } else {
        // Retry after delay
        await new Promise(resolve => setTimeout(resolve, 2000));
        const retrySummary = await api.getOrderSummary(undefined, piId);
        if (retrySummary) {
          setOrderId(retrySummary.orderId);
          setOrderSummary(retrySummary);
          
          if (showUpsell && upsellOffers.length > 0) {
            setCurrentStep('upsell');
          } else {
            setCurrentStep('thankyou');
          }
        } else {
          // Fallback - go to thank you without summary
          setCurrentStep('thankyou');
        }
      }
    } catch (err) {
      console.error('[FunnelCheckoutApp] Failed to get order summary:', err);
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
      className="hp-funnel-checkout-app min-h-screen bg-background"
      style={rootStyle}
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
            stripePublishableKey={stripePublishableKey}
            landingUrl={landingUrl}
            apiBase={apiBase}
            getCartItems={getCartItems}
            onComplete={handleCheckoutComplete}
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
