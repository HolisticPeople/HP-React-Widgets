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
} from './types';

export interface FunnelCheckoutAppProps extends FunnelCheckoutAppConfig {
  apiBase?: string;
}

export const FunnelCheckoutApp = (props: FunnelCheckoutAppProps) => {
  const {
    funnelId,
    funnelName,
    funnelSlug,
    products,
    defaultProductId,
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
  
  // Checkout data that persists across steps
  const [selectedProductId, setSelectedProductId] = useState<string>(
    defaultProductId || (products.length > 0 ? products[0].id : '')
  );
  const [quantity, setQuantity] = useState(1);
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

  // Get the selected product
  const selectedProduct = useMemo(
    () => products.find(p => p.id === selectedProductId),
    [products, selectedProductId]
  );

  // Build cart items from selection
  const getCartItems = useCallback((): CartItem[] => {
    if (!selectedProduct) return [];
    
    const items: CartItem[] = [
      { sku: selectedProduct.sku, qty: quantity }
    ];
    
    // Add free item if configured
    if (selectedProduct.freeItem?.sku) {
      items.push({
        sku: selectedProduct.freeItem.sku,
        qty: (selectedProduct.freeItem.qty || 1) * quantity,
        excludeGlobalDiscount: true,
        itemDiscountPercent: 100,
      });
    }
    
    return items;
  }, [selectedProduct, quantity]);

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
            products={products}
            selectedProductId={selectedProductId}
            onSelectProduct={setSelectedProductId}
            quantity={quantity}
            onQuantityChange={setQuantity}
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


