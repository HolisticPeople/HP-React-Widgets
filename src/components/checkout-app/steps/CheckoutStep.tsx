import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { cn } from '@/lib/utils';
import { useCustomerLookup } from '../hooks/useCustomerLookup';
import { useCheckoutApi } from '../hooks/useCheckoutApi';
import { useStripePayment } from '../hooks/useStripePayment';
import type { 
  Offer,
  SingleOffer,
  FixedBundleOffer,
  CustomizableKitOffer,
  KitProduct,
  KitSelection,
  CartItem, 
  Address, 
  ShippingRate, 
  TotalsResponse,
  CustomerData,
} from '../types';

// Inline icons
const MinusIcon = () => (
  <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="5" y1="12" x2="19" y2="12" />
  </svg>
);

const PlusIcon = () => (
  <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="12" y1="5" x2="12" y2="19" />
    <line x1="5" y1="12" x2="19" y2="12" />
  </svg>
);

const LoaderIcon = ({ className = "w-4 h-4" }: { className?: string }) => (
  <svg className={`${className} animate-spin`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
    <path d="M12 2a10 10 0 0 1 10 10" strokeLinecap="round" />
  </svg>
);

const ShieldIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
  </svg>
);

const TruckIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="1" y="3" width="15" height="13" />
    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
    <circle cx="5.5" cy="18.5" r="2.5" />
    <circle cx="18.5" cy="18.5" r="2.5" />
  </svg>
);

const PackageIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
  </svg>
);

const StarIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
  </svg>
);

const CheckIcon = () => (
  <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

const LockIcon = () => (
  <svg className="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
  </svg>
);

interface CheckoutStepProps {
  funnelId: string;
  funnelName: string;
  offers: Offer[];
  selectedOfferId: string;
  onSelectOffer: (id: string) => void;
  kitSelection: KitSelection;
  onKitQuantityChange: (sku: string, qty: number) => void;
  offerPrice: { original: number; discounted: number };
  customerData: CustomerData | null;
  onCustomerLookup: (data: CustomerData) => void;
  shippingAddress: Address | null;
  onShippingAddressChange: (address: Address) => void;
  selectedRate: ShippingRate | null;
  onSelectRate: (rate: ShippingRate) => void;
  pointsToRedeem: number;
  onPointsRedeemChange: (points: number) => void;
  freeShippingCountries: string[];
  enablePoints: boolean;
  enableCustomerLookup: boolean;
  stripePublishableKey: string;
  landingUrl: string;
  apiBase: string;
  getCartItems: () => CartItem[];
  onComplete: (paymentIntentId: string, address: Address) => void;
}

export const CheckoutStep = ({
  funnelId,
  funnelName,
  offers,
  selectedOfferId,
  onSelectOffer,
  kitSelection,
  onKitQuantityChange,
  offerPrice,
  customerData,
  onCustomerLookup,
  shippingAddress,
  onShippingAddressChange,
  selectedRate,
  onSelectRate,
  pointsToRedeem,
  onPointsRedeemChange,
  freeShippingCountries,
  enablePoints,
  enableCustomerLookup,
  stripePublishableKey,
  landingUrl,
  apiBase,
  getCartItems,
  onComplete,
}: CheckoutStepProps) => {
  // Form state
  const [formData, setFormData] = useState({
    firstName: shippingAddress?.firstName || '',
    lastName: shippingAddress?.lastName || '',
    email: customerData?.email || '',
    phone: shippingAddress?.phone || '',
    address: shippingAddress?.address1 || '',
    city: shippingAddress?.city || '',
    state: shippingAddress?.state || '',
    zipCode: shippingAddress?.postcode || '',
    country: shippingAddress?.country || 'US',
  });

  const [totals, setTotals] = useState<TotalsResponse | null>(null);
  const [shippingRates, setShippingRates] = useState<ShippingRate[]>([]);
  const [isCalculating, setIsCalculating] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  const stripeContainerRef = useRef<HTMLDivElement>(null);
  const stripeMountedRef = useRef(false);

  // Hooks
  const customerLookup = useCustomerLookup({
    apiBase,
    onSuccess: (data) => {
      onCustomerLookup(data);
      if (data.shipping) {
        setFormData(prev => ({
          ...prev,
          firstName: data.shipping!.firstName || prev.firstName,
          lastName: data.shipping!.lastName || prev.lastName,
          phone: data.shipping!.phone || prev.phone,
          address: data.shipping!.address1 || prev.address,
          city: data.shipping!.city || prev.city,
          state: data.shipping!.state || prev.state,
          zipCode: data.shipping!.postcode || prev.zipCode,
          country: data.shipping!.country || prev.country,
        }));
      }
    },
  });

  const api = useCheckoutApi({ apiBase, funnelId, funnelName });

  const stripePayment = useStripePayment({
    publishableKey: stripePublishableKey,
    onPaymentSuccess: (piId) => {
      const address: Address = {
        firstName: formData.firstName,
        lastName: formData.lastName,
        address1: formData.address,
        city: formData.city,
        state: formData.state,
        postcode: formData.zipCode,
        country: formData.country,
        phone: formData.phone,
        email: formData.email,
      };
      onComplete(piId, address);
    },
    onPaymentError: (err) => {
      setError(err);
      setIsSubmitting(false);
    },
  });

  // Get selected offer
  const selectedOffer = useMemo(
    () => offers.find(o => o.id === selectedOfferId) as Offer | undefined,
    [offers, selectedOfferId]
  );

  // Check if free shipping applies
  const isFreeShipping = useMemo(
    () => freeShippingCountries.includes(formData.country.toUpperCase()),
    [formData.country, freeShippingCountries]
  );

  // Points value (10 points = $1)
  const pointsValue = useMemo(
    () => pointsToRedeem / 10,
    [pointsToRedeem]
  );

  const maxRedeemablePoints = useMemo(() => {
    if (!customerData || !totals) return 0;
    const maxByBalance = customerData.pointsBalance;
    const maxByTotal = Math.floor((totals.grandTotal - totals.pointsDiscount) * 10);
    return Math.min(maxByBalance, maxByTotal);
  }, [customerData, totals]);

  // Mount Stripe Elements when ready
  useEffect(() => {
    if (stripePayment.isReady && stripeContainerRef.current && !stripeMountedRef.current) {
      stripePayment.mountCardElement(stripeContainerRef.current);
      stripeMountedRef.current = true;
    }

    return () => {
      if (stripeMountedRef.current) {
        stripePayment.unmountCardElement();
        stripeMountedRef.current = false;
      }
    };
  }, [stripePayment.isReady]);

  // Calculate totals
  const fetchTotals = useCallback(async () => {
    const items = getCartItems();
    if (items.length === 0) return;

    setIsCalculating(true);
    try {
      const address: Address = {
        firstName: formData.firstName,
        lastName: formData.lastName,
        address1: formData.address,
        city: formData.city,
        state: formData.state,
        postcode: formData.zipCode,
        country: formData.country,
        phone: formData.phone,
        email: formData.email,
      };

      let currentRate = selectedRate;

      if (isFreeShipping) {
        const freeRate: ShippingRate = {
          serviceCode: 'free_shipping',
          serviceName: 'Free Shipping',
          shipmentCost: 0,
          otherCost: 0,
        };
        if (!currentRate || currentRate.serviceCode !== 'free_shipping') {
          currentRate = freeRate;
          onSelectRate(freeRate);
          setShippingRates([freeRate]);
        }
      } else if (!currentRate && formData.zipCode && formData.country && formData.address) {
        try {
          const rates = await api.getShippingRates(address, items);
          if (rates.length > 0) {
            setShippingRates(rates);
            currentRate = rates[0];
            onSelectRate(rates[0]);
          }
        } catch (e) {
          console.warn('[CheckoutStep] Shipping fetch failed', e);
        }
      }

      // Pass the admin-set offer total to override calculated sum
      const result = await api.calculateTotals(address, items, currentRate, pointsToRedeem, offerPrice.discounted);
      setTotals(result);
    } catch (err) {
      console.error('[CheckoutStep] Failed to fetch totals', err);
    } finally {
      setIsCalculating(false);
    }
  }, [formData, getCartItems, selectedRate, isFreeShipping, pointsToRedeem, api, onSelectRate, offerPrice.discounted]);

  // Trigger totals update when selection changes
  useEffect(() => {
    fetchTotals();
  }, [selectedOfferId, kitSelection]); // eslint-disable-line

  // Debounced address change
  useEffect(() => {
    const hasCore = formData.address && formData.city && formData.zipCode && formData.country.length >= 2;
    if (hasCore) {
      const timer = setTimeout(() => fetchTotals(), 500);
      return () => clearTimeout(timer);
    }
  }, [formData.address, formData.city, formData.state, formData.zipCode, formData.country]); // eslint-disable-line

  // Email blur handler
  const handleEmailBlur = useCallback(() => {
    if (enableCustomerLookup && formData.email && formData.email.includes('@')) {
      customerLookup.lookup(formData.email);
    }
  }, [enableCustomerLookup, formData.email, customerLookup]);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setFormData(prev => ({
      ...prev,
      [e.target.name]: e.target.value,
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (!formData.phone.trim()) {
      setError('Phone number is required for shipping updates.');
      return;
    }

    if (!stripePayment.isCardComplete) {
      setError('Please complete your payment information.');
      return;
    }

    setIsSubmitting(true);

    try {
      const items = getCartItems();
      const address: Address = {
        firstName: formData.firstName,
        lastName: formData.lastName,
        address1: formData.address,
        city: formData.city,
        state: formData.state,
        postcode: formData.zipCode,
        country: formData.country,
        phone: formData.phone,
        email: formData.email,
      };

      let submitRate = selectedRate;
      if (!submitRate && isFreeShipping) {
        submitRate = {
          serviceCode: 'free_shipping',
          serviceName: 'Free Shipping',
          shipmentCost: 0,
          otherCost: 0,
        };
      }

      const result = await api.createPaymentIntent(
        items,
        address,
        formData.email,
        formData.firstName,
        formData.lastName,
        submitRate,
        pointsToRedeem,
        offerPrice.discounted  // Admin-set offer total
      );

      const success = await stripePayment.confirmPayment(result.clientSecret, {
        name: `${formData.firstName} ${formData.lastName}`,
        email: formData.email,
        phone: formData.phone,
        address: {
          line1: formData.address,
          city: formData.city,
          state: formData.state,
          postal_code: formData.zipCode,
          country: formData.country,
        },
      });

      if (!success) {
        setIsSubmitting(false);
      }
    } catch (err: any) {
      console.error('[CheckoutStep] Checkout failed', err);
      if (err.code === 'funnel_off' && err.redirect) {
        window.location.href = err.redirect;
        return;
      }
      setError(err.message || 'Something went wrong. Please try again.');
      setIsSubmitting(false);
    }
  };

  const displayTotal = totals?.grandTotal ?? offerPrice.discounted;

  // Render offer card based on type
  const renderOfferCard = (offer: Offer) => {
    const isSelected = selectedOfferId === offer.id;
    
    return (
      <div
        key={offer.id}
        onClick={() => onSelectOffer(offer.id)}
        className={cn(
          "p-6 rounded-lg border-2 cursor-pointer transition-all duration-300 mb-4 relative",
          isSelected
            ? "border-accent bg-accent/10 shadow-[0_0_20px_hsl(45_95%_60%/0.3)]"
            : "border-border/50 hover:border-accent/50"
        )}
      >
        {offer.badge && (
          <div className="absolute -top-3 right-4 bg-accent text-accent-foreground px-3 py-1 rounded-full text-sm font-bold">
            {offer.badge}
          </div>
        )}
        
        <div className="flex items-start gap-6">
          {offer.image && (
            <img src={offer.image} alt={offer.name} className="w-20 h-auto rounded" />
          )}
          <div className="flex-1">
            <h3 className="text-xl font-bold text-foreground mb-1">{offer.name}</h3>
            {offer.description && (
              <p className="text-muted-foreground text-sm mb-2">{offer.description}</p>
            )}
            
            {/* Price display */}
            <div className="flex items-baseline gap-2">
              {offer.discountLabel && (
                <span className="text-green-500 text-sm font-semibold">{offer.discountLabel}</span>
              )}
              <span className="text-2xl font-bold text-accent">
                ${(offer.calculatedPrice || 0).toFixed(2)}
              </span>
              {offer.originalPrice && offer.originalPrice > (offer.calculatedPrice || 0) && (
                <span className="text-muted-foreground line-through text-sm">
                  ${offer.originalPrice.toFixed(2)}
                </span>
              )}
            </div>
            
            {isFreeShipping && (
              <p className="text-sm text-muted-foreground">+ FREE Shipping</p>
            )}
            
            {/* Offer type indicator */}
            <div className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
              {offer.type === 'single' && (
                <span className="px-2 py-0.5 bg-secondary rounded">Single Product</span>
              )}
              {offer.type === 'fixed_bundle' && (
                <span className="px-2 py-0.5 bg-secondary rounded">
                  {(offer as FixedBundleOffer).bundleItems.length} Items Bundle
                </span>
              )}
              {offer.type === 'customizable_kit' && (
                <span className="px-2 py-0.5 bg-accent/20 text-accent rounded">Customize Your Kit</span>
              )}
            </div>
          </div>
        </div>
        
        {/* Kit customization UI - only show when selected and is a kit */}
        {isSelected && offer.type === 'customizable_kit' && (
          <div className="mt-6 pt-4 border-t border-border/50">
            <h4 className="font-semibold text-accent mb-4">Customize Your Selection:</h4>
            <div className="space-y-3">
              {(offer as CustomizableKitOffer).kitProducts.map((product: KitProduct) => (
                <div key={product.sku} className="flex items-center justify-between p-3 bg-secondary/50 rounded-lg">
                  <div className="flex items-center gap-3">
                    {product.image && (
                      <img src={product.image} alt={product.name} className="w-12 h-12 object-cover rounded" />
                    )}
                    <div>
                      <p className="font-medium text-foreground">{product.name}</p>
                      <div className="flex items-center gap-2 text-sm">
                        <span className="text-accent">${product.discountedPrice.toFixed(2)}</span>
                        {product.discountedPrice < product.regularPrice && (
                          <span className="text-muted-foreground line-through">${product.regularPrice.toFixed(2)}</span>
                        )}
                      </div>
                      {product.role === 'must' && (
                        <span className="text-xs text-orange-500 flex items-center gap-1">
                          <LockIcon /> Required
                        </span>
                      )}
                    </div>
                  </div>
                  
                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={(e) => {
                        e.stopPropagation();
                        const newQty = (kitSelection[product.sku] || 0) - 1;
                        if (product.role === 'must' && newQty < 1) return;
                        onKitQuantityChange(product.sku, newQty);
                      }}
                      disabled={product.role === 'must' && (kitSelection[product.sku] || 0) <= 1}
                      className="h-8 w-8 border-accent/50 hover:bg-accent/20"
                    >
                      <MinusIcon />
                    </Button>
                    <span className="w-8 text-center font-bold text-accent">
                      {kitSelection[product.sku] || 0}
                    </span>
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={(e) => {
                        e.stopPropagation();
                        const current = kitSelection[product.sku] || 0;
                        if (current < product.maxQty) {
                          onKitQuantityChange(product.sku, current + 1);
                        }
                      }}
                      disabled={(kitSelection[product.sku] || 0) >= product.maxQty}
                      className="h-8 w-8 border-accent/50 hover:bg-accent/20"
                    >
                      <PlusIcon />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
            
            {/* Kit total */}
            <div className="mt-4 p-3 bg-accent/10 rounded-lg">
              <div className="flex justify-between items-center">
                <span className="font-semibold text-foreground">Kit Total:</span>
                <div className="text-right">
                  <span className="text-2xl font-bold text-accent">${offerPrice.discounted.toFixed(2)}</span>
                  {offerPrice.original > offerPrice.discounted && (
                    <span className="ml-2 text-muted-foreground line-through text-sm">
                      ${offerPrice.original.toFixed(2)}
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="text-center mb-12">
        <h1 className="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-accent via-accent to-foreground bg-clip-text text-transparent">
          Secure Your Order
        </h1>
        <p className="text-lg text-muted-foreground">
          Choose your preferred package and complete your purchase
        </p>
      </div>

      <div className="grid lg:grid-cols-2 gap-8">
        {/* Left Column - Offer Selection */}
        <div className="space-y-6">
          <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
            <h2 className="text-2xl font-bold mb-6 text-accent">Select Your Package</h2>
            
            {offers.map(renderOfferCard)}
          </Card>

          {/* Trust Badges */}
          <div className="grid grid-cols-3 gap-4">
            <Card className="p-4 bg-card/30 backdrop-blur-sm border-border/30 text-center">
              <div className="text-accent mx-auto mb-2 flex justify-center">
                <ShieldIcon />
              </div>
              <p className="text-xs text-muted-foreground">Secure Checkout</p>
            </Card>
            <Card className="p-4 bg-card/30 backdrop-blur-sm border-border/30 text-center">
              <div className="text-accent mx-auto mb-2 flex justify-center">
                <TruckIcon />
              </div>
              <p className="text-xs text-muted-foreground">{isFreeShipping ? 'Free Shipping' : 'Fast Shipping'}</p>
            </Card>
            <Card className="p-4 bg-card/30 backdrop-blur-sm border-border/30 text-center">
              <div className="text-accent mx-auto mb-2 flex justify-center">
                <PackageIcon />
              </div>
              <p className="text-xs text-muted-foreground">Quality Guaranteed</p>
            </Card>
          </div>
        </div>

        {/* Right Column - Order Summary & Form */}
        <div className="space-y-6">
          {/* Order Summary */}
          <Card className="p-6 bg-gradient-to-br from-secondary/50 to-card/50 backdrop-blur-sm border-accent/30">
            <h2 className="text-2xl font-bold mb-6 text-accent">Order Summary</h2>
            
            <div className="space-y-3 mb-6">
              {/* Show subtotal (original price) with strikethrough if discounted */}
              <div className="flex justify-between text-foreground">
                <span>{selectedOffer?.name}</span>
                <span className={offerPrice.original > offerPrice.discounted ? 'line-through text-muted-foreground' : 'font-semibold'}>
                  ${offerPrice.original.toFixed(2)}
                </span>
              </div>

              {offerPrice.original > offerPrice.discounted && (
                <div className="flex justify-between text-green-500">
                  <span>Savings</span>
                  <span>-${(offerPrice.original - offerPrice.discounted).toFixed(2)}</span>
                </div>
              )}

              {totals?.pointsDiscount && totals.pointsDiscount > 0 && (
                <div className="flex justify-between text-green-500">
                  <span>Points Redeemed</span>
                  <span>-${totals.pointsDiscount.toFixed(2)}</span>
                </div>
              )}

              <div className="flex justify-between text-foreground">
                <span>Shipping</span>
                <span className="font-semibold text-accent">
                  {isCalculating ? (
                    <LoaderIcon className="w-3 h-3" />
                  ) : isFreeShipping ? (
                    'FREE'
                  ) : totals?.shippingTotal ? (
                    `$${totals.shippingTotal.toFixed(2)}`
                  ) : (
                    'Calculated at checkout'
                  )}
                </span>
              </div>

              <div className="pt-3 border-t border-accent/30 flex justify-between text-xl font-bold">
                <span className="text-accent">Total:</span>
                <span className="text-accent">
                  {isCalculating ? <LoaderIcon /> : `$${displayTotal.toFixed(2)}`}
                </span>
              </div>
            </div>
          </Card>

          {/* Points Redemption */}
          {enablePoints && customerData && customerData.pointsBalance > 0 && (
            <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
              <div className="flex items-center gap-2 mb-4">
                <StarIcon />
                <h3 className="text-xl font-bold text-accent">Redeem Points</h3>
              </div>
              <p className="text-muted-foreground mb-4">
                You have <span className="text-accent font-bold">{customerData.pointsBalance}</span> points available 
                (worth ${(customerData.pointsBalance / 10).toFixed(2)})
              </p>
              <div className="space-y-4">
                <Slider
                  value={[pointsToRedeem]}
                  onValueChange={(value) => onPointsRedeemChange(value[0])}
                  max={maxRedeemablePoints}
                  step={10}
                  className="w-full"
                />
                <div className="flex justify-between text-sm">
                  <span className="text-muted-foreground">Using: {pointsToRedeem} points</span>
                  <span className="text-accent font-semibold">-${pointsValue.toFixed(2)}</span>
                </div>
              </div>
            </Card>
          )}

          {/* Checkout Form */}
          <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
            <h3 className="text-xl font-bold mb-6 text-accent">Shipping Information</h3>

            {error && (
              <div className="mb-4 p-3 bg-destructive/10 border border-destructive/30 rounded-lg text-destructive text-sm">
                {error}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <Label htmlFor="email" className="text-foreground">Email</Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  value={formData.email}
                  onChange={handleInputChange}
                  onBlur={handleEmailBlur}
                  required
                  className="bg-input text-foreground border-border/50"
                />
                {customerLookup.isLoading && (
                  <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                    <LoaderIcon className="w-3 h-3" /> Looking up account...
                  </p>
                )}
                {customerData && customerData.userId > 0 && (
                  <p className="text-xs text-accent mt-1 flex items-center gap-1">
                    <CheckIcon /> Welcome back! Your info has been loaded.
                  </p>
                )}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="firstName" className="text-foreground">First Name</Label>
                  <Input
                    id="firstName"
                    name="firstName"
                    value={formData.firstName}
                    onChange={handleInputChange}
                    required
                    className="bg-input text-foreground border-border/50"
                  />
                </div>
                <div>
                  <Label htmlFor="lastName" className="text-foreground">Last Name</Label>
                  <Input
                    id="lastName"
                    name="lastName"
                    value={formData.lastName}
                    onChange={handleInputChange}
                    required
                    className="bg-input text-foreground border-border/50"
                  />
                </div>
              </div>

              <div>
                <Label htmlFor="phone" className="text-foreground">Phone</Label>
                <Input
                  id="phone"
                  name="phone"
                  type="tel"
                  value={formData.phone}
                  onChange={handleInputChange}
                  required
                  className="bg-input text-foreground border-border/50"
                />
              </div>

              <div>
                <Label htmlFor="address" className="text-foreground">Address</Label>
                <Input
                  id="address"
                  name="address"
                  value={formData.address}
                  onChange={handleInputChange}
                  required
                  className="bg-input text-foreground border-border/50"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="city" className="text-foreground">City</Label>
                  <Input
                    id="city"
                    name="city"
                    value={formData.city}
                    onChange={handleInputChange}
                    required
                    className="bg-input text-foreground border-border/50"
                  />
                </div>
                <div>
                  <Label htmlFor="state" className="text-foreground">State</Label>
                  <Input
                    id="state"
                    name="state"
                    value={formData.state}
                    onChange={handleInputChange}
                    className="bg-input text-foreground border-border/50"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="zipCode" className="text-foreground">ZIP Code</Label>
                  <Input
                    id="zipCode"
                    name="zipCode"
                    value={formData.zipCode}
                    onChange={handleInputChange}
                    required
                    className="bg-input text-foreground border-border/50"
                  />
                </div>
                <div>
                  <Label htmlFor="country" className="text-foreground">Country</Label>
                  <select
                    id="country"
                    name="country"
                    value={formData.country}
                    onChange={handleInputChange}
                    required
                    className="w-full h-10 px-3 rounded-md bg-input text-foreground border border-border/50"
                  >
                    <option value="US">United States</option>
                    <option value="CA">Canada</option>
                    <option value="GB">United Kingdom</option>
                    <option value="AU">Australia</option>
                    <option value="DE">Germany</option>
                    <option value="FR">France</option>
                  </select>
                </div>
              </div>

              {/* Stripe Card Element */}
              <div>
                <Label className="text-foreground mb-2 block">Payment</Label>
                <div 
                  ref={stripeContainerRef}
                  className="p-4 bg-input border border-border/50 rounded-md min-h-[80px]"
                >
                  {stripePayment.isLoading && (
                    <div className="flex items-center justify-center h-12">
                      <LoaderIcon className="w-6 h-6" />
                    </div>
                  )}
                </div>
                {stripePayment.error && (
                  <p className="text-xs text-destructive mt-1">{stripePayment.error}</p>
                )}
              </div>

              <Button
                type="submit"
                size="lg"
                disabled={isSubmitting || isCalculating || stripePayment.isProcessing || !stripePayment.isReady}
                className="w-full bg-gradient-to-r from-accent to-accent/90 hover:from-accent/90 hover:to-accent text-accent-foreground font-bold text-lg py-6 rounded-full shadow-[0_0_30px_hsl(45_95%_60%/0.5)] hover:shadow-[0_0_50px_hsl(45_95%_60%/0.7)] transition-all duration-300"
              >
                {isSubmitting || stripePayment.isProcessing ? (
                  <>
                    <LoaderIcon />
                    <span className="ml-2">Processing...</span>
                  </>
                ) : (
                  `Pay $${displayTotal.toFixed(2)}`
                )}
              </Button>
            </form>

            <p className="text-xs text-muted-foreground text-center mt-4">
              By completing this purchase, you agree to our terms and conditions.
            </p>
          </Card>
        </div>
      </div>

      {/* Back to Landing */}
      <div className="text-center mt-8">
        <a href={landingUrl} className="text-accent hover:text-accent/80">
          ← Back to Product Information
        </a>
      </div>
    </div>
  );
};

export default CheckoutStep;
