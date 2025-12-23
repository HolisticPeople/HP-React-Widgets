import { useState, useEffect, useCallback, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

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

const LoaderIcon = () => (
  <svg className="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
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
    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21" />
    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
    <line x1="12" y1="22.08" x2="12" y2="12" />
  </svg>
);

export interface FunnelCheckoutProduct {
  id: string;
  sku: string;
  name: string;
  description?: string;
  price: number;
  image?: string;
  badge?: string;
  freeItemSku?: string;
  freeItemQty?: number;
  isBestValue?: boolean;
}

export interface ShippingRate {
  serviceCode: string;
  serviceName: string;
  shipmentCost: number;
  otherCost: number;
}

export interface FunnelCheckoutProps {
  funnelId: string;
  funnelName: string;
  products: FunnelCheckoutProduct[];
  selectedProductId?: string;
  thankYouUrl: string;
  backUrl?: string;
  logoUrl?: string;
  logoLink?: string;
  freeShippingCountries?: string[];
  apiBase?: string;
  stripePublishable?: string;
}

interface FormData {
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  address: string;
  city: string;
  state: string;
  zipCode: string;
  country: string;
}

interface Totals {
  subtotal: number;
  discount_total: number;
  shipping_total: number;
  global_discount: number;
  points_discount: number;
  grand_total: number;
}

export const FunnelCheckout = ({
  funnelId,
  funnelName,
  products,
  selectedProductId: initialProductId,
  thankYouUrl,
  backUrl,
  logoUrl,
  logoLink = '/',
  freeShippingCountries = ['US'],
  apiBase = '/wp-json/hp-rw/v1',
}: FunnelCheckoutProps) => {
  const [selectedProductId, setSelectedProductId] = useState<string>(
    initialProductId || (products.length > 0 ? products[0].id : '')
  );
  const [quantity, setQuantity] = useState(1);
  
  const [formData, setFormData] = useState<FormData>({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    zipCode: '',
    country: 'US',
  });

  const [shippingRates, setShippingRates] = useState<ShippingRate[]>([]);
  const [selectedRate, setSelectedRate] = useState<ShippingRate | null>(null);
  const [totals, setTotals] = useState<Totals | null>(null);
  const [isCalculating, setIsCalculating] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const selectedProduct = useMemo(
    () => products.find(p => p.id === selectedProductId),
    [products, selectedProductId]
  );

  const isFreeShipping = useMemo(
    () => freeShippingCountries.includes(formData.country.toUpperCase()),
    [formData.country, freeShippingCountries]
  );

  // Build cart items for API
  const getCartItems = useCallback(() => {
    if (!selectedProduct) return [];
    
    const items: Array<{ sku: string; qty: number; exclude_global_discount?: boolean; item_discount_percent?: number }> = [
      { sku: selectedProduct.sku, qty: quantity }
    ];
    
    // Add free item if configured
    if (selectedProduct.freeItemSku) {
      items.push({
        sku: selectedProduct.freeItemSku,
        qty: (selectedProduct.freeItemQty || 1) * quantity,
        exclude_global_discount: true,
        item_discount_percent: 100,
      });
    }
    
    return items;
  }, [selectedProduct, quantity]);

  // Calculate totals
  const fetchTotals = useCallback(async () => {
    const items = getCartItems();
    if (items.length === 0) return;

    setIsCalculating(true);
    try {
      const address = {
        first_name: formData.firstName,
        last_name: formData.lastName,
        address_1: formData.address,
        city: formData.city,
        state: formData.state,
        postcode: formData.zipCode,
        country: formData.country,
        email: formData.email,
        phone: formData.phone,
      };

      let currentRate = selectedRate;

      // Handle free shipping for specified countries
      if (isFreeShipping) {
        const freeRate: ShippingRate = {
          serviceCode: 'free_shipping',
          serviceName: 'Free Shipping',
          shipmentCost: 0,
          otherCost: 0,
        };
        if (!currentRate || currentRate.serviceCode !== 'free_shipping') {
          currentRate = freeRate;
          setSelectedRate(freeRate);
          setShippingRates([freeRate]);
        }
      } else if (!currentRate && formData.zipCode && formData.country && formData.address) {
        // Fetch real shipping rates
        try {
          const ratesRes = await fetch(`${apiBase}/checkout/shipping-rates`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ address, items }),
          });
          const ratesData = await ratesRes.json();
          if (ratesData.rates && ratesData.rates.length > 0) {
            setShippingRates(ratesData.rates);
            currentRate = ratesData.rates[0];
            setSelectedRate(ratesData.rates[0]);
          }
        } catch (e) {
          console.warn('Shipping fetch failed', e);
        }
      }

      // Fetch totals
      const res = await fetch(`${apiBase}/checkout/totals`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          items,
          address,
          funnel_id: funnelId,
          selected_rate: currentRate ? {
            serviceName: currentRate.serviceName,
            amount: currentRate.shipmentCost + currentRate.otherCost,
          } : null,
        }),
      });
      
      const data = await res.json();
      setTotals(data);
    } catch (err) {
      console.error('Failed to fetch totals', err);
    } finally {
      setIsCalculating(false);
    }
  }, [formData, getCartItems, selectedRate, funnelId, isFreeShipping, apiBase]);

  // Trigger totals update
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

    setIsSubmitting(true);

    try {
      const items = getCartItems();
      const address = {
        first_name: formData.firstName,
        last_name: formData.lastName,
        address_1: formData.address,
        city: formData.city,
        state: formData.state,
        postcode: formData.zipCode,
        country: formData.country,
        email: formData.email,
        phone: formData.phone,
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

      const payload = {
        funnel_id: funnelId,
        funnel_name: funnelName,
        items,
        shipping_address: address,
        customer: {
          email: formData.email,
          first_name: formData.firstName,
          last_name: formData.lastName,
        },
        selected_rate: submitRate ? {
          serviceName: submitRate.serviceName,
          amount: submitRate.shipmentCost + submitRate.otherCost,
        } : null,
      };

      const res = await fetch(`${apiBase}/checkout/create-intent`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok) {
        if (data.code === 'funnel_off' && data.data?.redirect) {
          window.location.href = data.data.redirect;
          return;
        }
        throw new Error(data.message || 'Checkout failed');
      }

      if (data.client_secret) {
        // Build hosted payment URL
        const returnUrl = new URL(thankYouUrl, window.location.origin);
        returnUrl.searchParams.set('funnel', funnelId);
        
        // Build the hosted confirm URL
        const wpBase = window.location.origin;
        const confirmUrl = new URL(wpBase);
        confirmUrl.searchParams.set('hp_fb_confirm', '1');
        confirmUrl.searchParams.set('cs', data.client_secret);
        confirmUrl.searchParams.set('fid', funnelId);
        if (data.publishable) {
          confirmUrl.searchParams.set('pk', data.publishable);
        }
        confirmUrl.searchParams.set('succ', encodeURIComponent(returnUrl.toString()));

        window.location.href = confirmUrl.toString();
      } else {
        throw new Error('Invalid response from server');
      }
    } catch (err: any) {
      console.error('Checkout failed', err);
      setError(err.message || 'Something went wrong. Please try again.');
      setIsSubmitting(false);
    }
  };

  const displayTotal = totals?.grand_total ?? (selectedProduct ? selectedProduct.price * quantity : 0);

  return (
    <div className="hp-funnel-checkout min-h-screen bg-background py-12 px-4">
      <div className="max-w-6xl mx-auto">
        {/* Logo */}
        {logoUrl && (
          <div className="mb-8">
            <a href={logoLink} target="_blank" rel="noopener noreferrer">
              <img src={logoUrl} alt={funnelName} className="h-8 opacity-70 hover:opacity-100 transition-opacity" />
            </a>
          </div>
        )}

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
                    setSelectedProductId(product.id);
                    setQuantity(1);
                  }}
                  className={cn(
                    "p-6 rounded-lg border-2 cursor-pointer transition-all duration-300 mb-4 relative",
                    selectedProductId === product.id
                      ? "border-accent bg-accent/10 shadow-[0_0_20px_hsl(45_95%_60%/0.3)]"
                      : "border-border/50 hover:border-accent/50"
                  )}
                >
                  {product.badge && (
                    <div className="absolute top-0 right-6 -translate-y-1/2 bg-accent text-background px-4 py-1 rounded-full text-sm font-bold uppercase tracking-wide shadow-lg">
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
                  onClick={() => setQuantity(q => Math.max(1, q - 1))}
                  className="border-accent/50 hover:bg-accent/20"
                >
                  <MinusIcon />
                </Button>
                <div className="text-3xl font-bold text-accent w-16 text-center">{quantity}</div>
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  onClick={() => setQuantity(q => q + 1)}
                  className="border-accent/50 hover:bg-accent/20"
                >
                  <PlusIcon />
                </Button>
              </div>
              
              {selectedProduct?.freeItemSku && (
                <div className="mt-4 p-4 bg-accent/10 rounded-lg border border-accent/30">
                  <p className="text-accent font-semibold flex items-center gap-2">
                    <PackageIcon />
                    You'll receive {(selectedProduct.freeItemQty || 1) * quantity} FREE bonus item{((selectedProduct.freeItemQty || 1) * quantity) > 1 ? 's' : ''}!
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

                {totals?.global_discount > 0 && (
                  <div className="flex justify-between text-green-500">
                    <span>Discount</span>
                    <span>-${totals.global_discount.toFixed(2)}</span>
                  </div>
                )}

                <div className="flex justify-between text-foreground">
                  <span>Shipping</span>
                  <span className="font-semibold text-accent">
                    {isCalculating ? (
                      <LoaderIcon />
                    ) : isFreeShipping ? (
                      'FREE'
                    ) : totals?.shipping_total ? (
                      `$${totals.shipping_total.toFixed(2)}`
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

              {!isFreeShipping && (
                <p className="text-sm text-muted-foreground">
                  * Shipping rates calculated based on your location
                </p>
              )}
            </Card>

            {/* Checkout Form */}
            <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
              <h3 className="text-xl font-bold mb-6 text-accent">Shipping Information</h3>

              {error && (
                <div className="mb-4 p-3 bg-destructive/10 border border-destructive/30 rounded-lg text-destructive text-sm">
                  {error}
                </div>
              )}

              <form onSubmit={handleSubmit} className="space-y-4">
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
                  <Label htmlFor="email" className="text-foreground">Email</Label>
                  <Input
                    id="email"
                    name="email"
                    type="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    required
                    className="bg-input text-foreground border-border/50"
                  />
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
                      {/* Add more countries as needed */}
                    </select>
                  </div>
                </div>

                <Button
                  type="submit"
                  size="lg"
                  disabled={isSubmitting || isCalculating}
                  className="w-full bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-lg py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
                >
                  {isSubmitting ? (
                    <>
                      <LoaderIcon />
                      <span className="ml-2">Processing...</span>
                    </>
                  ) : (
                    'Complete Your Order'
                  )}
                </Button>
              </form>

              <p className="text-xs text-muted-foreground text-center mt-4">
                By completing this purchase, you agree to our terms and conditions.
              </p>
            </Card>
          </div>
        </div>

        {/* Back Link */}
        {backUrl && (
          <div className="text-center mt-8">
            <a href={backUrl} className="text-accent hover:text-accent/80">
              ← Back to Product Information
            </a>
          </div>
        )}
      </div>

      {/* Footer */}
      <footer className="py-8 px-4 border-t border-border/50 mt-12">
        <div className="max-w-6xl mx-auto text-center text-muted-foreground text-sm">
          <p className="mb-2">© {new Date().getFullYear()} {funnelName}</p>
          <p className="text-xs">
            These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure or prevent any disease.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default FunnelCheckout;















