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
import { getServiceCode, extractShippingCost } from '../utils/shipping';
import { getCarrierLogo } from '../utils/carrierLogos';
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

// Country name to code mapping (handles cases where full name is passed instead of code)
const COUNTRY_NAME_TO_CODE: Record<string, string> = {
  'united states': 'US', 'usa': 'US', 'u.s.a.': 'US', 'u.s.': 'US',
  'canada': 'CA',
  'united kingdom': 'GB', 'uk': 'GB', 'great britain': 'GB', 'england': 'GB',
  'australia': 'AU',
  'new zealand': 'NZ',
  'germany': 'DE', 'deutschland': 'DE',
  'france': 'FR',
  'italy': 'IT', 'italia': 'IT',
  'spain': 'ES', 'españa': 'ES',
  'netherlands': 'NL', 'holland': 'NL',
  'belgium': 'BE',
  'austria': 'AT',
  'switzerland': 'CH',
  'sweden': 'SE',
  'norway': 'NO',
  'denmark': 'DK',
  'finland': 'FI',
  'ireland': 'IE',
  'portugal': 'PT',
  'japan': 'JP',
  'south korea': 'KR', 'korea': 'KR',
  'singapore': 'SG',
  'hong kong': 'HK',
  'israel': 'IL',
  'mexico': 'MX',
  'brazil': 'BR',
};

// Normalize country value to 2-letter ISO code
const normalizeCountryCode = (value: string | undefined | null): string => {
  if (!value) return 'US';
  const v = value.trim();
  // Already a 2-letter code? Return uppercase
  if (v.length === 2) return v.toUpperCase();
  // Try mapping from full name
  const mapped = COUNTRY_NAME_TO_CODE[v.toLowerCase()];
  return mapped || 'US';
};

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

const ChevronDownIcon = ({ className = "w-4 h-4" }: { className?: string }) => (
  <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

// Collapsible Address Picker component
const AddressPicker = ({
  addresses,
  selectedAddress,
  selectedZip,
  onSelect,
}: {
  addresses: Address[];
  selectedAddress: string;
  selectedZip: string;
  onSelect: (addr: Address) => void;
}) => {
  const [isExpanded, setIsExpanded] = useState(false);
  
  // Find currently selected address for display
  const currentAddress = addresses.find(a => a.address1 === selectedAddress && a.postcode === selectedZip) || addresses[0];
  
  return (
    <div className="mb-4">
      <button
        type="button"
        onClick={() => setIsExpanded(!isExpanded)}
        className="w-full flex items-center justify-between p-3 rounded-lg border border-border/50 bg-input/50 hover:bg-input/80 transition-colors"
      >
        <div className="text-left">
          <p className="text-xs text-muted-foreground mb-1">Shipping to:</p>
          <p className="text-sm text-foreground">
            {currentAddress?.address1}, {currentAddress?.city}, {currentAddress?.state} {currentAddress?.postcode}
          </p>
        </div>
        <div className="flex items-center gap-2 text-muted-foreground">
          <span className="text-xs">{addresses.length} addresses</span>
          <ChevronDownIcon className={cn("w-4 h-4 transition-transform", isExpanded && "rotate-180")} />
        </div>
      </button>
      
      {isExpanded && (
        <div className="mt-2 p-2 rounded-lg border border-border/30 bg-background/50">
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 max-h-[200px] overflow-y-auto scrollbar-thin">
            {addresses.map((addr) => (
              <button
                key={addr.id}
                type="button"
                onClick={() => {
                  onSelect(addr);
                  setIsExpanded(false);
                }}
                className={cn(
                  "p-2 rounded-md border text-left transition-colors text-xs overflow-hidden",
                  selectedAddress === addr.address1 && selectedZip === addr.postcode
                    ? "border-border bg-card/60"
                    : "border-border/30 bg-card/30 hover:border-border/50 hover:bg-card/40"
                )}
              >
                <p className="font-medium text-foreground truncate">
                  {addr.firstName} {addr.lastName}
                </p>
                <p className="text-muted-foreground truncate">{addr.address1}</p>
                <p className="text-muted-foreground truncate">
                  {addr.city}, {addr.state} {addr.postcode}
                </p>
                <p className="text-muted-foreground truncate">{addr.country}</p>
                {addr.isDefault && (
                  <span className="inline-flex items-center gap-1 text-accent mt-1">
                    <StarIcon /> Default
                  </span>
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

interface CheckoutStepProps {
  funnelId: string;
  funnelName: string;
  offers: Offer[];
  selectedOfferId: string;
  onSelectOffer: (id: string) => void;
  kitSelection: KitSelection;
  onKitQuantityChange: (sku: string, qty: number) => void;
  offerQuantity: number;
  onOfferQuantityChange: (qty: number) => void;
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
  showAllOffers?: boolean;
  stripePublishableKey: string;
  stripeMode: string;
  landingUrl: string;
  apiBase: string;
  getCartItems: () => CartItem[];
  initialUserData?: import('../types').InitialUserData | null;
  onComplete: (piId: string, address: Address, orderDraftId: string) => void;
}

export const CheckoutStep = ({
  funnelId,
  funnelName,
  offers: rawOffers,
  selectedOfferId,
  onSelectOffer,
  kitSelection,
  onKitQuantityChange,
  offerQuantity,
  onOfferQuantityChange,
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
  showAllOffers = true,
  stripePublishableKey,
  stripeMode,
  landingUrl,
  apiBase,
  getCartItems,
  initialUserData,
  onComplete,
}: CheckoutStepProps) => {
  // Ensure offers is always an array
  const offers = Array.isArray(rawOffers) ? rawOffers : [];

  // Form state - prefill from initialUserData (logged-in user) if available
  const [formData, setFormData] = useState({
    firstName: shippingAddress?.firstName || initialUserData?.firstName || '',
    lastName: shippingAddress?.lastName || initialUserData?.lastName || '',
    email: customerData?.email || initialUserData?.email || '',
    phone: shippingAddress?.phone || initialUserData?.phone || '',
    address: shippingAddress?.address1 || initialUserData?.address || '',
    city: shippingAddress?.city || initialUserData?.city || '',
    state: shippingAddress?.state || initialUserData?.state || '',
    zipCode: shippingAddress?.postcode || initialUserData?.postcode || '',
    country: normalizeCountryCode(shippingAddress?.country || initialUserData?.country),
  });

  const [totals, setTotals] = useState<TotalsResponse | null>(null);
  const [shippingRates, setShippingRates] = useState<ShippingRate[]>([]);
  const [isCalculating, setIsCalculating] = useState(false);
  const [isFetchingShipping, setIsFetchingShipping] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  
  // Store draft ID across attempts
  const orderDraftIdRef = useRef<string | null>(null);

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
    stripeMode,
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
      onComplete(piId, address, orderDraftIdRef.current || '');
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
    // Max points based on product subtotal only (no shipping) - user can't redeem more than product cost
    const productSubtotal = totals.subtotal - totals.discountTotal - totals.globalDiscount;
    const maxByTotal = Math.floor(Math.max(0, productSubtotal) * 10);
    return Math.min(maxByBalance, maxByTotal);
  }, [customerData, totals]);

  // Refs to hold Stripe functions without triggering re-renders
  const stripePaymentRef = useRef(stripePayment);
  stripePaymentRef.current = stripePayment;

  // Mount Stripe Elements when ready - use empty deps to run only once
  // and check isReady inside the effect
  useEffect(() => {
    // Poll for Stripe readiness to avoid dependency on stripePayment
    const checkAndMount = () => {
      const isReady = stripePaymentRef.current.isReady;
      const hasContainer = !!stripeContainerRef.current;
      const alreadyMounted = stripeMountedRef.current;
      
      if (isReady && hasContainer && !alreadyMounted) {
        stripePaymentRef.current.mountCardElement(stripeContainerRef.current);
        stripeMountedRef.current = true;
        return true;
      }
      return false;
    };

    // Try immediately
    if (checkAndMount()) {
      return;
    }

    // Poll every 100ms until ready (max 5 seconds)
    let attempts = 0;
    const interval = setInterval(() => {
      attempts++;
      if (checkAndMount()) {
        clearInterval(interval);
      } else if (attempts > 50) {
        clearInterval(interval);
      }
    }, 100);

    return () => {
      clearInterval(interval);
      if (stripeMountedRef.current) {
        stripePaymentRef.current.unmountCardElement();
        stripeMountedRef.current = false;
      }
    };
  }, []); // Empty deps - runs only on mount/unmount

  // Refs to avoid dependency loops - these hold current values without triggering re-renders
  const selectedRateRef = useRef(selectedRate);
  const onSelectRateRef = useRef(onSelectRate);
  
  // Keep refs in sync
  useEffect(() => {
    selectedRateRef.current = selectedRate;
  }, [selectedRate]);
  
  useEffect(() => {
    onSelectRateRef.current = onSelectRate;
  }, [onSelectRate]);

  // DEBUG: Track shipping rate state
  const debugShipping = true; // Set to false for production
  
  // Calculate totals - does NOT fetch shipping rates (that's separate)
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

      // Use the actual selected rate if available
      const currentRate = selectedRate;

      // Pass the admin-set offer total to override calculated sum
      const result = await api.calculateTotals(address, items, currentRate, pointsToRedeem, offerPrice.discounted);
      setTotals(result);
    } catch (err) {
      console.error('[CheckoutStep] Failed to fetch totals', err);
    } finally {
      setIsCalculating(false);
    }
  }, [formData, getCartItems, pointsToRedeem, api, offerPrice.discounted, selectedRate]);

  // Debounce timer ref for selection changes
  const selectionDebounceRef = useRef<NodeJS.Timeout | null>(null);

  // Debounced fetchTotals - prevents rapid API calls (protects against loops and DDOS triggers)
  const debouncedFetchTotals = useCallback(() => {
    // Clear any pending call
    if (selectionDebounceRef.current) {
      clearTimeout(selectionDebounceRef.current);
    }
    // Schedule new call with 500ms debounce
    selectionDebounceRef.current = setTimeout(() => {
      fetchTotals();
    }, 500);
  }, [fetchTotals]);

  // Cleanup debounce timer on unmount
  useEffect(() => {
    return () => {
      if (selectionDebounceRef.current) {
        clearTimeout(selectionDebounceRef.current);
      }
    };
  }, []);

  // Trigger totals update when selection, offerPrice, points, or shipping rate change (debounced)
  // This does NOT fetch shipping rates - just recalculates totals
  useEffect(() => {
    debouncedFetchTotals();
  }, [selectedOfferId, kitSelection, offerQuantity, offerPrice.discounted, pointsToRedeem, selectedRate, debouncedFetchTotals]);

  // Create a shipping key to track when we need to refetch rates
  const shippingKeyRef = useRef<string>('');
  // Request counter to ignore stale responses
  const shippingRequestIdRef = useRef<number>(0);
  
  // Fetch shipping rates ONLY when address/zip/country/items actually change
  // Uses ref to avoid dependency on the fetch function itself
  useEffect(() => {
    const hasCore = formData.address && formData.city && formData.zipCode && formData.country.length >= 2;
    if (!hasCore) {
      if (debugShipping) console.log('[SHIPPING DEBUG] useEffect: missing core address fields, skipping');
      return;
    }
    
    const items = getCartItems();
    const itemsKey = items.map(i => `${i.sku}:${i.qty}`).join(',');
    const newShippingKey = `${formData.country}|${formData.zipCode}|${itemsKey}`;
    
    if (debugShipping) console.log('[SHIPPING DEBUG] useEffect triggered:', {
      newKey: newShippingKey,
      oldKey: shippingKeyRef.current,
      changed: newShippingKey !== shippingKeyRef.current,
    });
    
    // Only fetch if shipping key actually changed
    if (newShippingKey === shippingKeyRef.current) {
      if (debugShipping) console.log('[SHIPPING DEBUG] Key unchanged, skipping fetch');
      return;
    }
    shippingKeyRef.current = newShippingKey;
    
    // Increment request ID to invalidate any in-flight requests
    const requestId = ++shippingRequestIdRef.current;
    
    if (debugShipping) console.log('[SHIPPING DEBUG] Key CHANGED! Clearing rates and scheduling fetch (requestId:', requestId, ')');
    
    // Clear stale rates while fetching new ones
    setShippingRates([]);
    setIsFetchingShipping(true);
    onSelectRateRef.current(null as unknown as ShippingRate);
    
    const timer = setTimeout(async () => {
      if (debugShipping) console.log('[SHIPPING DEBUG] Debounce timer fired, executing fetch (requestId:', requestId, ')');
      
      // Capture the current request ID before async call
      const currentRequestId = requestId;
      
      // Call the fetch function but handle response staleness here
      const fetchItems = getCartItems();
      if (fetchItems.length === 0) return;
      
      if (!formData.zipCode || !formData.country || !formData.address) return;
      
      // For free shipping countries, set free rate
      if (isFreeShipping) {
        if (currentRequestId !== shippingRequestIdRef.current) {
          if (debugShipping) console.log('[SHIPPING DEBUG] STALE response ignored (free shipping)', currentRequestId, 'vs', shippingRequestIdRef.current);
          return;
        }
        const freeRate: ShippingRate = {
          serviceCode: 'free_shipping',
          serviceName: 'Free Shipping',
          shipmentCost: 0,
          otherCost: 0,
        };
        setShippingRates([freeRate]);
        setIsFetchingShipping(false);
        onSelectRateRef.current(freeRate);
        return;
      }
      
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
      
      if (debugShipping) console.log('[SHIPPING DEBUG] >>> FETCHING RATES (requestId:', currentRequestId, ') for:', {
        country: formData.country,
        zip: formData.zipCode,
        items: fetchItems.map(i => `${i.sku}:${i.qty}`).join(','),
      });
      
      try {
        const rates = await api.getShippingRates(address, fetchItems);
        
        // Check if this response is still relevant
        if (currentRequestId !== shippingRequestIdRef.current) {
          if (debugShipping) console.log('[SHIPPING DEBUG] STALE response IGNORED (requestId:', currentRequestId, 'current:', shippingRequestIdRef.current, ')');
          return;
        }
        
        if (debugShipping) console.log('[SHIPPING DEBUG] <<< RECEIVED RATES (requestId:', currentRequestId, '):', rates.length, 'rates');
        setIsFetchingShipping(false);
        if (rates.length > 0) {
          setShippingRates(rates);
          onSelectRateRef.current(rates[0]);
        }
      } catch (e) {
        if (currentRequestId === shippingRequestIdRef.current) {
          setIsFetchingShipping(false);
          console.warn('[CheckoutStep] Shipping fetch failed', e);
        }
      }
    }, 500);
    
    return () => {
      if (debugShipping) console.log('[SHIPPING DEBUG] Cleanup: clearing debounce timer');
      clearTimeout(timer);
    };
  }, [formData, getCartItems, isFreeShipping, api]);
  
  // Fetch totals when address changes (separate from shipping rates)
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

      orderDraftIdRef.current = result.orderDraftId;

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

  // Handler for selecting a shipping rate - just updates parent state
  const handleSelectRate = (rate: ShippingRate) => {
    onSelectRate(rate);
  };
  
  // SIMPLIFIED: Compute shipping cost directly from selectedRate on every render
  // No complex state syncing - just derive from the source of truth
  const shippingCost = useMemo(() => {
    if (!selectedRate) {
      if (debugShipping) console.log('[SHIPPING DEBUG] shippingCost: no selectedRate, returning 0');
      return 0;
    }
    const serviceCode = getServiceCode(selectedRate);
    if (serviceCode === 'free_shipping') {
      if (debugShipping) console.log('[SHIPPING DEBUG] shippingCost: free shipping');
      return 0;
    }
    const cost = extractShippingCost(selectedRate);
    if (debugShipping) console.log('[SHIPPING DEBUG] shippingCost DISPLAYED:', cost, 'from', selectedRate.serviceName);
    return cost;
  }, [selectedRate]);
  
  
  // For display logic - check if we have a rate selected
  const hasShippingRate = selectedRate !== null && shippingCost > 0;
  
  // Calculate display total - always use client-side calculation for instant UI updates
  // This gives immediate feedback when points slider moves
  // Backend validation happens at checkout time
  const displayTotal = Math.max(0, offerPrice.discounted + shippingCost - pointsValue);

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
          <div className="hp-funnel-badge-pill absolute top-0 right-6 -translate-y-1/2 px-4 py-1 rounded-full text-sm font-bold uppercase tracking-wide shadow-lg">
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
            
            {/* Price display - hide for customizable kits when selected (details shown in kit area) */}
            {!(isSelected && offer.type === 'customizable_kit') && (
              <>
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
              </>
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
              {(offer as CustomizableKitOffer).kitProducts.map((product: KitProduct) => {
                // Calculate pricing info for display
                const minQty = product.role === 'must' ? (product.qty || 1) : 0;
                const currentQty = kitSelection[product.sku] || 0;
                
                // Prices
                const regularPrice = product.regularPrice;
                const firstUnitPrice = product.discountedPrice; // Admin-set sale price for first N units
                const subsequentPrice = product.subsequentSalePrice ?? regularPrice;
                
                // Discount flags
                const hasFirstUnitDiscount = firstUnitPrice < regularPrice;
                const hasSubsequentDiscount = subsequentPrice < regularPrice;
                const showSubsequentPrice = product.role === 'must' && subsequentPrice !== firstUnitPrice;
                
                // Calculate total discount for this line
                // First N units (minQty) at firstUnitPrice, additional at subsequentPrice
                const originalTotal = regularPrice * currentQty;
                let actualTotal = 0;
                if (currentQty > 0) {
                  if (product.role === 'must' && currentQty > minQty && subsequentPrice !== firstUnitPrice) {
                    // Tiered pricing: first minQty at firstUnitPrice, rest at subsequentPrice
                    actualTotal = (firstUnitPrice * minQty) + (subsequentPrice * (currentQty - minQty));
                  } else {
                    // All units at firstUnitPrice
                    actualTotal = firstUnitPrice * currentQty;
                  }
                }
                const totalDiscount = originalTotal - actualTotal;
                
                return (
                <div key={product.sku} className="flex items-center justify-between p-3 bg-secondary/50 rounded-lg">
                  <div className="flex items-center gap-3">
                    {product.image && (
                      <img src={product.image} alt={product.name} className="w-12 h-12 object-cover rounded" />
                    )}
                    <div>
                      <p className="font-medium text-foreground">{product.name}</p>
                      
                      {/* Price display */}
                      <div className="flex items-center gap-2 text-sm flex-wrap">
                        {/* Original price - struck through if discounted */}
                        {(hasFirstUnitDiscount || hasSubsequentDiscount) ? (
                          <span className="text-muted-foreground line-through">${regularPrice.toFixed(2)}</span>
                        ) : (
                          <span className="text-accent">${regularPrice.toFixed(2)}</span>
                        )}
                        
                        {/* Sale price in accent (what customer pays per unit) */}
                        {hasFirstUnitDiscount && (
                          <span className="text-accent font-semibold">${firstUnitPrice.toFixed(2)}</span>
                        )}
                      </div>
                      
                      {/* Show subsequent pricing note for Must Have products */}
                      {showSubsequentPrice && (
                        <p className="text-xs text-muted-foreground mt-0.5">
                          First {minQty}: ${firstUnitPrice.toFixed(2)} ea | Add'l: ${subsequentPrice.toFixed(2)} ea
                        </p>
                      )}
                      
                      {/* Total discount for this line in green */}
                      {totalDiscount > 0 && currentQty > 0 && (
                        <p className="text-xs text-green-500 font-medium mt-1">
                          Discount ${totalDiscount.toFixed(2)}
                        </p>
                      )}
                    </div>
                  </div>
                  
                  <div className="flex flex-col items-end gap-1">
                    {(() => {
                      // For 'must' products, admin-set qty is the minimum required
                      const minQty = product.role === 'must' ? (product.qty || 1) : 0;
                      const currentQty = kitSelection[product.sku] || 0;
                      const isAtMinimum = currentQty <= minQty;
                      // Default maxQty to 99 if not set or 0
                      const effectiveMaxQty = product.maxQty && product.maxQty > 0 ? product.maxQty : 99;
                      
                      // Calculate line total for this product
                      let lineTotal = 0;
                      if (currentQty > 0) {
                        if (product.role === 'must' && currentQty > minQty && subsequentPrice !== firstUnitPrice) {
                          lineTotal = (firstUnitPrice * minQty) + (subsequentPrice * (currentQty - minQty));
                        } else {
                          lineTotal = firstUnitPrice * currentQty;
                        }
                      }
                      
                      return (
                        <>
                          <div className="flex items-center gap-2">
                            {/* "-" button with "Required" label underneath when at minimum */}
                            <div className="flex flex-col items-center">
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={(e) => {
                                  e.stopPropagation();
                                  const newQty = currentQty - 1;
                                  if (newQty < minQty) return;
                                  onKitQuantityChange(product.sku, newQty);
                                }}
                                disabled={isAtMinimum}
                                className="h-8 w-8 border-accent/50 hover:bg-accent/20"
                              >
                                <MinusIcon />
                              </Button>
                              {/* "Required" label under "-" button */}
                              {product.role === 'must' && isAtMinimum && (
                                <span className="text-[10px] text-orange-500 flex items-center gap-0.5 mt-0.5">
                                  <LockIcon className="h-2.5 w-2.5" /> Required
                                </span>
                              )}
                            </div>
                            
                            {/* Quantity display with line total underneath */}
                            <div className="flex flex-col items-center">
                              <span className="w-8 text-center font-bold text-accent">
                                {currentQty}
                              </span>
                              {/* Line total in orange */}
                              <span className="text-xs text-accent font-medium">
                                ${lineTotal.toFixed(2)}
                              </span>
                            </div>
                            
                            <Button
                              type="button"
                              variant="outline"
                              size="icon"
                              onClick={(e) => {
                                e.stopPropagation();
                                if (currentQty < effectiveMaxQty) {
                                  onKitQuantityChange(product.sku, currentQty + 1);
                                }
                              }}
                              disabled={currentQty >= effectiveMaxQty}
                              className="h-8 w-8 border-accent/50 hover:bg-accent/20"
                            >
                              <PlusIcon />
                            </Button>
                          </div>
                        </>
                      );
                    })()}
                  </div>
                </div>
                );
              })}
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
              {/* Total discount in green */}
              {offerPrice.original > offerPrice.discounted && (
                <div className="flex justify-end mt-1">
                  <span className="text-green-500 font-semibold text-sm">
                    You save ${(offerPrice.original - offerPrice.discounted).toFixed(2)}!
                  </span>
                </div>
              )}
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
            
            {/* Sort offers: selected first, then apply showAllOffers filter */}
            {(() => {
              // Sort offers: selected first
              const sortedOffers = [...offers].sort((a, b) => {
                if (a.id === selectedOfferId) return -1;
                if (b.id === selectedOfferId) return 1;
                return 0;
              });
              
              // Filter to show only selected offer if showAllOffers is false
              const visibleOffers = showAllOffers 
                ? sortedOffers 
                : sortedOffers.filter(o => o.id === selectedOfferId);
              
              return visibleOffers.map((offer, index) => {
                const isSelected = selectedOfferId === offer.id;
                // Apply dimming to non-selected offers when multiple are shown
                const isDimmed = showAllOffers && !isSelected && visibleOffers.length > 1;
                
                return (
                  <div 
                    key={offer.id} 
                    className={isDimmed ? 'opacity-60 hover:opacity-90 transition-opacity' : ''}
                  >
                    {renderOfferCard(offer)}
                  </div>
                );
              });
            })()}
          </Card>

          {/* Quantity Selector - Only for non-kit offers */}
          {selectedOffer && selectedOffer.type !== 'customizable_kit' && (
            <Card className="p-6 bg-card/50 backdrop-blur-sm border-border/50">
              <h2 className="text-xl font-bold mb-4 text-accent">How Many Would You Like?</h2>
              
              <div className="flex items-center justify-center gap-6 mb-4">
                <Button
                  type="button"
                  variant="outline"
                  size="lg"
                  onClick={() => onOfferQuantityChange(offerQuantity - 1)}
                  disabled={offerQuantity <= 1}
                  className="h-14 w-14 border-accent/50 hover:bg-accent/20 text-2xl"
                >
                  <MinusIcon />
                </Button>
                
                <div className="text-center">
                  <span className="text-5xl font-bold text-accent">{offerQuantity}</span>
                  <p className="text-sm text-muted-foreground mt-1">
                    {offerQuantity === 1 ? 'package' : 'packages'}
                  </p>
                </div>
                
                <Button
                  type="button"
                  variant="outline"
                  size="lg"
                  onClick={() => onOfferQuantityChange(offerQuantity + 1)}
                  disabled={offerQuantity >= 10}
                  className="h-14 w-14 border-accent/50 hover:bg-accent/20 text-2xl"
                >
                  <PlusIcon />
                </Button>
              </div>
              
              {/* Bonus Message */}
              {selectedOffer.bonusMessage && offerQuantity > 1 && (
                <div className="mt-4 p-4 bg-accent/10 rounded-lg text-center">
                  <p className="text-accent font-semibold">
                    {selectedOffer.bonusMessage.replace('{qty}', String(offerQuantity))}
                  </p>
                </div>
              )}
              
              {/* Price summary when qty > 1 */}
              {offerQuantity > 1 && (
                <div className="mt-4 pt-4 border-t border-border/30 text-center">
                  <p className="text-muted-foreground text-sm">
                    {offerQuantity} × ${((selectedOffer.calculatedPrice || 0)).toFixed(2)} = 
                    <span className="text-accent font-bold ml-2">${offerPrice.discounted.toFixed(2)}</span>
                  </p>
                </div>
              )}
            </Card>
          )}

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

              {pointsValue > 0 && (
                <div className="flex justify-between text-green-500">
                  <span>Points Redeemed ({pointsToRedeem} pts)</span>
                  <span>-${pointsValue.toFixed(2)}</span>
                </div>
              )}

              <div className="flex justify-between text-foreground">
                <span>Shipping</span>
                <span className="font-semibold text-accent">
                  {isFetchingShipping ? (
                    <LoaderIcon className="w-3 h-3" />
                  ) : isFreeShipping ? (
                    'FREE'
                  ) : hasShippingRate ? (
                    `$${shippingCost.toFixed(2)}`
                  ) : totals?.shippingTotal ? (
                    `$${totals.shippingTotal.toFixed(2)}`
                  ) : formData.address && formData.zipCode ? (
                    <LoaderIcon className="w-3 h-3" />
                  ) : (
                    <span className="text-muted-foreground text-sm">Enter address</span>
                  )}
                </span>
              </div>

              <div className="pt-3 border-t border-accent/30 flex justify-between text-xl font-bold">
                <span className="text-accent">Total:</span>
                <span className="text-accent">
                  ${displayTotal.toFixed(2)}
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
                  min={0}
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

              {/* Address Picker for returning customers with multiple addresses */}
              {customerData && customerData.allAddresses && customerData.allAddresses.shipping.length > 1 && (
                <AddressPicker
                  addresses={customerData.allAddresses.shipping}
                  selectedAddress={formData.address}
                  selectedZip={formData.zipCode}
                  onSelect={(addr) => {
                    setFormData(prev => ({
                      ...prev,
                      firstName: addr.firstName || prev.firstName,
                      lastName: addr.lastName || prev.lastName,
                      phone: addr.phone || prev.phone,
                      address: addr.address1 || '',
                      city: addr.city || '',
                      state: addr.state || '',
                      zipCode: addr.postcode || '',
                      country: addr.country || 'US',
                    }));
                  }}
                />
              )}

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
                    <option value="NZ">New Zealand</option>
                    <option value="DE">Germany</option>
                    <option value="FR">France</option>
                    <option value="IT">Italy</option>
                    <option value="ES">Spain</option>
                    <option value="NL">Netherlands</option>
                    <option value="BE">Belgium</option>
                    <option value="AT">Austria</option>
                    <option value="CH">Switzerland</option>
                    <option value="SE">Sweden</option>
                    <option value="NO">Norway</option>
                    <option value="DK">Denmark</option>
                    <option value="FI">Finland</option>
                    <option value="IE">Ireland</option>
                    <option value="PT">Portugal</option>
                    <option value="PL">Poland</option>
                    <option value="IL">Israel</option>
                    <option value="JP">Japan</option>
                    <option value="SG">Singapore</option>
                    <option value="HK">Hong Kong</option>
                    <option value="MX">Mexico</option>
                    <option value="BR">Brazil</option>
                  </select>
                </div>
              </div>

              {/* Shipping Rate Selection */}
              {shippingRates.length > 1 && (
                <div>
                  <Label className="text-foreground mb-2 block">Shipping Method</Label>
                  <div className="space-y-2">
                    {shippingRates.map((rate) => {
                      // Handle various field name formats from API (ShipStation returns shipping_amount_raw)
                      const rateAny = rate as Record<string, unknown>;
                      const serviceName = rate.serviceName || (rateAny.service_name as string) || 'Shipping';
                      // Check multiple possible field names for the cost
                      // Note: ShipStation returns shipping_amount_raw as a number
                      const rawCost = rateAny.shipping_amount_raw ?? rateAny.base_amount_raw ?? rate.shipmentCost ?? rateAny.shipment_cost ?? 0;
                      const shipmentCost = typeof rawCost === 'number' ? rawCost : parseFloat(String(rawCost)) || 0;
                      const rawOther = rateAny.other_cost_raw ?? rate.otherCost ?? rateAny.other_cost ?? 0;
                      const otherCost = typeof rawOther === 'number' ? rawOther : parseFloat(String(rawOther)) || 0;
                      const totalCost = shipmentCost + otherCost;
                      
                      const rateServiceCode = getServiceCode(rate);
                      const selectedServiceCode = getServiceCode(selectedRate);
                      const isSelected = rateServiceCode === selectedServiceCode;
                      
                      return (
                        <label
                          key={rateServiceCode || `rate-${idx}`}
                          className={cn(
                            "flex items-center justify-between p-3 rounded-md border cursor-pointer transition-colors",
                            isSelected
                              ? 'bg-accent/5 border-accent/50'
                              : 'bg-input border-border/50 hover:border-border'
                          )}
                        >
                          <div className="flex items-center gap-3">
                            <div className={`w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0 ${isSelected ? 'border-accent bg-accent' : 'border-gray-500 bg-gray-700'}`}>
                              {isSelected && <div className="w-1.5 h-1.5 rounded-full bg-gray-900" />}
                            </div>
                            <input
                              type="radio"
                              name="shippingRate"
                              checked={isSelected}
                              onChange={() => handleSelectRate(rate)}
                              className="sr-only"
                            />
                            <div className="flex items-center gap-2">
                              <span className="flex-shrink-0">{getCarrierLogo(serviceName)}</span>
                              <span className={isSelected ? "text-foreground" : "text-gray-400"}>{serviceName}</span>
                            </div>
                          </div>
                          <span className="text-accent font-semibold">
                            {totalCost === 0 ? 'FREE' : `$${totalCost.toFixed(2)}`}
                          </span>
                        </label>
                      );
                    })}
                  </div>
                </div>
              )}

              {/* Stripe Card Element */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <Label className="text-foreground block">Payment</Label>
                  {stripeMode === 'test' && (
                    <span className="px-2 py-0.5 bg-orange-500/20 text-orange-500 text-[10px] font-bold rounded uppercase tracking-wider border border-orange-500/30 animate-pulse">
                      Test Mode
                    </span>
                  )}
                </div>
                
                {stripeMode === 'test' && (
                  <div className="mb-3 p-3 bg-accent/5 border border-accent/20 rounded-md text-[11px] space-y-1">
                    <p className="text-accent/80 font-medium">Use Test Card:</p>
                    <div className="flex gap-4 text-foreground/70">
                      <span>Card: <code className="text-accent bg-accent/10 px-1 rounded select-all cursor-pointer" title="Click to copy" onClick={(e) => {
                        navigator.clipboard.writeText('4242 4242 4242 4242');
                        const el = e.currentTarget;
                        const original = el.innerText;
                        el.innerText = 'Copied!';
                        setTimeout(() => el.innerText = original, 1000);
                      }}>4242 4242 4242 4242</code></span>
                      <span>Exp: <code className="text-accent bg-accent/10 px-1 rounded">12/33</code></span>
                      <span>CVC: <code className="text-accent bg-accent/10 px-1 rounded">333</code></span>
                    </div>
                  </div>
                )}

                <div 
                  ref={stripeContainerRef}
                  className="p-4 bg-input border border-border/50 rounded-md min-h-[80px]"
                >
                  {stripePayment.isLoading && (
                    <div className="flex items-center justify-center h-12">
                      <LoaderIcon className="w-6 h-6" />
                    </div>
                  )}
                  {!stripePublishableKey && !stripePayment.isLoading && (
                    <div className="flex items-center justify-center h-12 text-destructive text-sm text-center">
                      Payment config error: Missing key for {stripeMode} mode.
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
                variant="ghost"
                disabled={isSubmitting || isCalculating || stripePayment.isProcessing || !stripePayment.isReady}
                className="w-full rounded-full py-6 font-bold text-lg transition-all duration-300 shadow-lg hover:shadow-[0_0_20px_rgba(234,179,8,0.3)] !bg-card/30 !border !border-border/30 !text-warning hover:!bg-card/40 hover:!border-border/50 hover:!text-warning focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:!ring-warning focus-visible:ring-offset-2 focus-visible:ring-offset-background"
              >
                {isSubmitting || stripePayment.isProcessing ? (
                  <div className="flex items-center justify-center">
                    <LoaderIcon className="w-6 h-6" />
                    <span className="ml-3">Processing...</span>
                  </div>
                ) : (
                  `Pay $${displayTotal.toFixed(2)}`
                )}
              </Button>
            </form>

            {/* Security Badges */}
            <div className="flex flex-col items-center gap-2 mt-4">
              <span className="text-xs text-muted-foreground">Secured by</span>
              <div className="flex items-center justify-center gap-4">
                {/* Stripe Badge */}
                <div className="flex items-center gap-1.5 px-2 py-1 bg-card/40 rounded border border-border/30">
                  <svg className="w-4 h-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                  </svg>
                  <span className="text-xs font-medium text-foreground/80">Stripe</span>
                </div>
                {/* Google Merchant Badge */}
                <div className="flex items-center gap-1.5 px-2 py-1 bg-card/40 rounded border border-border/30">
                  <svg className="w-4 h-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                  </svg>
                  <span className="text-xs font-medium text-foreground/80">Google</span>
                </div>
                {/* Security Shield Badge */}
                <div className="flex items-center gap-1.5 px-2 py-1 bg-card/40 rounded border border-border/30">
                  <svg className="w-4 h-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                  </svg>
                  <span className="text-xs font-medium text-foreground/80">SSL</span>
                </div>
              </div>
            </div>

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
