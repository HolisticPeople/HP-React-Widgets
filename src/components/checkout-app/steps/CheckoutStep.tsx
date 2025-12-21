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
  CheckoutProduct, 
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

interface CheckoutStepProps {
  funnelId: string;
  funnelName: string;
  products: CheckoutProduct[];
  selectedProductId: string;
  onSelectProduct: (id: string) => void;
  quantity: number;
  onQuantityChange: (qty: number) => void;
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
  products,
  selectedProductId,
  onSelectProduct,
  quantity,
  onQuantityChange,
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
      // Auto-fill address from shipping
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

  // Selected product
  const selectedProduct = useMemo(
    () => products.find(p => p.id === selectedProductId),
    [products, selectedProductId]
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

      // Handle free shipping
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
        // Fetch real shipping rates
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

      // Fetch totals
      const result = await api.calculateTotals(address, items, currentRate, pointsToRedeem);
      setTotals(result);
    } catch (err) {
      console.error('[CheckoutStep] Failed to fetch totals', err);
    } finally {
      setIsCalculating(false);
    }
  }, [formData, getCartItems, selectedRate, isFreeShipping, pointsToRedeem, api, onSelectRate]);

  // Trigger totals update when cart changes
  useEffect(() => {
    fetchTotals();
  }, [selectedProductId, quantity]); // eslint-disable-line

  // Debounced address change
  useEffect(() => {
    const hasCore = formData.address && formData.city && formData.zipCode && formData.country.length >= 2;
    if (hasCore) {
      const timer = setTimeout(() => fetchTotals(), 500);
      return () => clearTimeout(timer);
    }
  }, [formData.address, formData.city, formData.state, formData.zipCode, formData.country]); // eslint-disable-line

  // Email blur handler for customer lookup
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

      // Ensure we have a shipping rate
      let submitRate = selectedRate;
      if (!submitRate && isFreeShipping) {
        submitRate = {
          serviceCode: 'free_shipping',
          serviceName: 'Free Shipping',
          shipmentCost: 0,
          otherCost: 0,
        };
      }

      // Create payment intent
      const result = await api.createPaymentIntent(
        items,
        address,
        formData.email,
        formData.firstName,
        formData.lastName,
        submitRate,
        pointsToRedeem
      );

      // Confirm payment with Stripe
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
      // onComplete will be called by the onPaymentSuccess callback
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

  const displayTotal = totals?.grandTotal ?? (selectedProduct ? selectedProduct.price * quantity : 0);

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
        {/* Left Column - Product Selection */}
        <div className="space-y-6">
          <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
            <h2 className="text-2xl font-bold mb-6 text-accent">Select Your Package</h2>
            
            {products.map((product) => (
              <div
                key={product.id}
                onClick={() => {
                  onSelectProduct(product.id);
                  onQuantityChange(1);
                }}
                className={cn(
                  "p-6 rounded-lg border-2 cursor-pointer transition-all duration-300 mb-4 relative",
                  selectedProductId === product.id
                    ? "border-accent bg-accent/10 shadow-[0_0_20px_hsl(45_95%_60%/0.3)]"
                    : "border-border/50 hover:border-accent/50"
                )}
              >
                {product.badge && (
                  <div className="absolute -top-3 right-4 bg-accent text-accent-foreground px-3 py-1 rounded-full text-sm font-bold">
                    {product.badge}
                  </div>
                )}
                <div className="flex items-center gap-6">
                  {product.image && (
                    <img src={product.image} alt={product.name} className="w-20 h-auto" />
                  )}
                  <div className="flex-1">
                    <h3 className="text-xl font-bold text-foreground mb-1">{product.name}</h3>
                    {product.description && (
                      <p className="text-accent font-semibold">{product.description}</p>
                    )}
                    <p className="text-2xl font-bold text-accent mt-2">
                      ${product.price.toFixed(2)}
                    </p>
                    {isFreeShipping && (
                      <p className="text-sm text-muted-foreground">+ FREE Shipping</p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </Card>

          {/* Quantity Selector */}
          <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
            <h3 className="text-xl font-bold mb-4 text-accent">Quantity</h3>
            <div className="flex items-center gap-4">
              <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => onQuantityChange(Math.max(1, quantity - 1))}
                className="border-accent/50 hover:bg-accent/20"
              >
                <MinusIcon />
              </Button>
              <div className="text-3xl font-bold text-accent w-16 text-center">{quantity}</div>
              <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => onQuantityChange(quantity + 1)}
                className="border-accent/50 hover:bg-accent/20"
              >
                <PlusIcon />
              </Button>
            </div>
            
            {selectedProduct?.freeItem?.sku && (
              <div className="mt-4 p-4 bg-accent/10 rounded-lg border border-accent/30">
                <p className="text-accent font-semibold flex items-center gap-2">
                  <PackageIcon />
                  You'll receive {(selectedProduct.freeItem.qty || 1) * quantity} FREE bonus item{((selectedProduct.freeItem.qty || 1) * quantity) > 1 ? 's' : ''}!
                </p>
              </div>
            )}
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
              <div className="flex justify-between text-foreground">
                <span>{selectedProduct?.name} × {quantity}</span>
                <span className="font-semibold">
                  ${((selectedProduct?.price || 0) * quantity).toFixed(2)}
                </span>
              </div>

              {totals?.globalDiscount && totals.globalDiscount > 0 && (
                <div className="flex justify-between text-green-500">
                  <span>Discount</span>
                  <span>-${totals.globalDiscount.toFixed(2)}</span>
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
                  <p className="text-xs text-accent mt-1">
                    ✓ Welcome back! Your info has been loaded.
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

