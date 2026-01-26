import { useState, useEffect, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { FunnelUpsell, UpsellOffer } from './FunnelUpsell';

// Inline icons
const CheckCircleIcon = () => (
  <svg className="w-16 h-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
    <polyline points="22 4 12 14.01 9 11.01" />
  </svg>
);

const LoaderIcon = () => (
  <svg className="w-8 h-8 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
    <path d="M12 2a10 10 0 0 1 10 10" strokeLinecap="round" />
  </svg>
);

const PackageIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21" />
    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
    <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
    <line x1="12" y1="22.08" x2="12" y2="12" />
  </svg>
);

interface OrderItem {
  name: string;
  qty: number;
  price: number;
  subtotal: number;
  total: number;
  image: string;
  sku: string;
}

interface OrderSummary {
  order_id: number;
  order_number: string;
  items: OrderItem[];
  shipping_total: number;
  shipping_method_title?: string;
  fees_total: number;
  points_redeemed: { points: number; value: number };
  items_discount: number;
  grand_total: number;
  status: string;
  shipping_address?: {
    first_name?: string;
    last_name?: string;
    company?: string;
    address_1?: string;
    address_2?: string;
    city?: string;
    state?: string;
    postcode?: string;
    country?: string;
  };
  view_order_url?: string;
}

export interface FunnelThankYouProps {
  // Order context (can be from URL params or props)
  orderId?: number;
  piId?: string;
  funnelId: string;
  funnelName: string;
  
  // Upsell configuration
  upsellOffers?: UpsellOffer[];
  showUpsell?: boolean;
  
  // Display
  logoUrl?: string;
  logoLink?: string;
  headline?: string;
  subheadline?: string;
  
  // API
  apiBase?: string;
}

export const FunnelThankYou = ({
  orderId: propOrderId,
  piId: propPiId,
  funnelId,
  funnelName,
  upsellOffers = [],
  showUpsell = true,
  logoUrl,
  logoLink = '/',
  headline = "Thank You for Your Order!",
  subheadline = "Your order has been confirmed and is being processed.",
  apiBase = '/wp-json/hp-rw/v1',
}: FunnelThankYouProps) => {
  const [orderSummary, setOrderSummary] = useState<OrderSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showUpsellOffer, setShowUpsellOffer] = useState(showUpsell && upsellOffers.length > 0);
  const [currentUpsellIndex, setCurrentUpsellIndex] = useState(0);
  const retryCountRef = useRef(0);

  // Get order details from URL if not provided as props
  const orderId = propOrderId || (() => {
    const params = new URLSearchParams(window.location.search);
    return parseInt(params.get('order_id') || params.get('order') || '0', 10);
  })();

  const piId = propPiId || (() => {
    const params = new URLSearchParams(window.location.search);
    return params.get('pi_id') || params.get('payment_intent') || '';
  })();

  // Fetch order summary - SINGLE API call (v2.43.15)
  useEffect(() => {
    const fetchOrderSummary = async () => {
      if (!orderId && !piId) {
        setError('No order information found.');
        setIsLoading(false);
        return;
      }

      setIsLoading(true);
      setError(null);

      try {
        // Build params - API handles lookup by pi_id OR order_id
        const params = new URLSearchParams();
        if (orderId) {
          params.append('order_id', String(orderId));
        }
        if (piId) {
          params.append('pi_id', piId);
        }

        const res = await fetch(`${apiBase}/checkout/order-summary?${params}`);
        
        if (!res.ok) {
          if (res.status === 404) {
            // Order might not be created yet (webhook delay) - retry
            retryCountRef.current += 1;
            if (retryCountRef.current <= 10) {
              setTimeout(fetchOrderSummary, 2000);
              return;
            }

            setError('We could not find your order yet. Please use the link from your confirmation email, or refresh in a moment.');
            setIsLoading(false);
            return;
          }
          if (res.status === 403) {
            // Authorization issue - don't retry, show message
            setError('Unable to load order details. Please check your confirmation email for order information.');
            setIsLoading(false);
            return;
          }
          throw new Error('Failed to load order');
        }

        const data = await res.json();
        setOrderSummary(data);
        setIsLoading(false);
      } catch (err: any) {
        console.error('Failed to fetch order summary', err);
        setError(err.message || 'Failed to load order details');
        setIsLoading(false);
      }
    };

    retryCountRef.current = 0;
    fetchOrderSummary();
  }, [orderId, piId, apiBase]);

  const handleUpsellAccept = () => {
    // Refresh order summary after successful upsell
    if (orderSummary) {
      setIsLoading(true);
      // Re-fetch to get updated order
      fetch(`${apiBase}/checkout/order-summary?order_id=${orderSummary.order_id}&pi_id=${piId}`)
        .then(res => res.json())
        .then(data => {
          setOrderSummary(data);
          // Move to next upsell if available
          if (currentUpsellIndex < upsellOffers.length - 1) {
            setCurrentUpsellIndex(i => i + 1);
          } else {
            setShowUpsellOffer(false);
          }
        })
        .finally(() => setIsLoading(false));
    }
  };

  const handleUpsellDecline = () => {
    // Move to next upsell or hide
    if (currentUpsellIndex < upsellOffers.length - 1) {
      setCurrentUpsellIndex(i => i + 1);
    } else {
      setShowUpsellOffer(false);
    }
  };

  const currentUpsellOffer = upsellOffers[currentUpsellIndex];

  // Full-page loading state - wait for BOTH order confirmation before showing content
  if (isLoading) {
    return (
      <div className="hp-funnel-thankyou min-h-screen bg-background py-12 px-4 flex flex-col items-center justify-center">
        <div className="max-w-md mx-auto text-center">
          {/* Logo during loading */}
          {logoUrl && (
            <div className="mb-8">
              <img 
                src={logoUrl} 
                alt={funnelName} 
                className="h-10 mx-auto opacity-60" 
              />
            </div>
          )}
          
          <Card className="p-12 bg-card/50 backdrop-blur-sm border-border/50">
            <div className="text-accent mb-6 flex justify-center">
              <LoaderIcon />
            </div>
            <h2 className="text-2xl font-bold text-foreground mb-2">
              Processing Your Order
            </h2>
            <p className="text-muted-foreground">
              Please wait while we confirm your payment...
            </p>
            {retryCountRef.current > 0 && (
              <p className="text-sm text-muted-foreground/70 mt-4">
                This may take a few moments...
              </p>
            )}
          </Card>
        </div>
      </div>
    );
  }

  // Error state - show dedicated error page
  if (error) {
    return (
      <div className="hp-funnel-thankyou min-h-screen bg-background py-12 px-4 flex flex-col items-center justify-center">
        <div className="max-w-md mx-auto text-center">
          {/* Logo */}
          {logoUrl && (
            <div className="mb-8">
              <img 
                src={logoUrl} 
                alt={funnelName} 
                className="h-10 mx-auto opacity-60" 
              />
            </div>
          )}
          
          <Card className="p-8 bg-destructive/10 border-destructive/30">
            <h2 className="text-2xl font-bold text-foreground mb-4">
              Order Confirmation
            </h2>
            <p className="text-muted-foreground mb-4">{error}</p>
            <p className="text-muted-foreground">
              Don't worry - your order has been placed. Check your email for confirmation details.
            </p>
            <button 
              onClick={() => window.location.reload()}
              className="mt-6 px-6 py-2 bg-accent text-accent-foreground rounded-lg hover:bg-accent/90 transition-colors"
            >
              Refresh Page
            </button>
          </Card>
        </div>
      </div>
    );
  }

  // Main content - only shown when orderSummary is loaded
  return (
    <div className="hp-funnel-thankyou min-h-screen bg-background py-12 px-4">
      <div className="max-w-4xl mx-auto">
        {/* Logo */}
        {logoUrl && (
          <div className="mb-8 text-center">
            <a href={logoLink} target="_blank" rel="noopener noreferrer">
              <img 
                src={logoUrl} 
                alt={funnelName} 
                className="h-10 mx-auto opacity-80 hover:opacity-100 transition-opacity" 
              />
            </a>
          </div>
        )}

        {/* Upsell Section (shown first if enabled) */}
        {showUpsellOffer && currentUpsellOffer && orderSummary && (
          <div className="mb-12">
            <FunnelUpsell
              orderId={orderSummary.order_id}
              piId={piId}
              funnelId={funnelId}
              offer={currentUpsellOffer}
              onAccept={handleUpsellAccept}
              onDecline={handleUpsellDecline}
              apiBase={apiBase}
            />
          </div>
        )}

        {/* Success Header */}
        <div className="text-center mb-12">
          <div className="text-accent mb-6 flex justify-center">
            <CheckCircleIcon />
          </div>
          <h1 className="text-4xl md:text-5xl font-bold mb-4 text-accent">
            {headline}
          </h1>
          <p className="text-xl text-muted-foreground">
            {subheadline}
          </p>
          {orderSummary && (
            <p className="text-lg text-foreground mt-4">
              Order #{orderSummary.order_number}
            </p>
          )}
        </div>

        {/* Order Summary */}
        {orderSummary && !isLoading && (
          <Card className="p-8 bg-card/50 backdrop-blur-sm border-border/50">
            <h2 className="text-2xl font-bold mb-6 text-accent flex items-center gap-2">
              <PackageIcon />
              Order Summary
            </h2>

            {/* Items */}
            <div className="space-y-4 mb-6">
              {orderSummary.items.map((item, idx) => (
                <div key={idx} className="flex items-center gap-4 p-4 bg-background/50 rounded-lg">
                  {item.image && (
                    <img 
                      src={item.image} 
                      alt={item.name} 
                      className="w-16 h-16 object-cover rounded"
                    />
                  )}
                  <div className="flex-1">
                    <h3 className="font-semibold text-foreground">{item.name}</h3>
                    <p className="text-sm text-muted-foreground">Qty: {item.qty}</p>
                  </div>
                  <div className="text-right">
                    <p className="font-semibold text-foreground">${item.total.toFixed(2)}</p>
                    {item.subtotal !== item.total && (
                      <p className="text-sm text-muted-foreground line-through">
                        ${item.subtotal.toFixed(2)}
                      </p>
                    )}
                  </div>
                </div>
              ))}
            </div>

            {/* Totals */}
            <div className="border-t border-border/50 pt-4 space-y-2">
              {orderSummary.items_discount > 0 && (
                <div className="flex justify-between text-green-500">
                  <span>Discount</span>
                  <span>-${orderSummary.items_discount.toFixed(2)}</span>
                </div>
              )}
              
              {orderSummary.points_redeemed.value > 0 && (
                <div className="flex justify-between text-green-500">
                  <span>Points Redeemed ({orderSummary.points_redeemed.points} pts)</span>
                  <span>-${orderSummary.points_redeemed.value.toFixed(2)}</span>
                </div>
              )}
              
              <div className="flex justify-between text-foreground">
                <span>Shipping</span>
                <span>
                  {orderSummary.shipping_total === 0 
                    ? <span className="text-accent">FREE</span>
                    : `$${orderSummary.shipping_total.toFixed(2)}`
                  }
                </span>
              </div>
              {!!orderSummary.shipping_method_title && (
                <div className="flex justify-between text-muted-foreground text-sm">
                  <span>Method</span>
                  <span>{orderSummary.shipping_method_title}</span>
                </div>
              )}

              <div className="flex justify-between text-xl font-bold pt-2 border-t border-border/50">
                <span className="text-accent">Total</span>
                <span className="text-accent">${orderSummary.grand_total.toFixed(2)}</span>
              </div>
            </div>
          </Card>
        )}

        {/* Shipping Address - above What's Next */}
        {orderSummary && !!orderSummary.shipping_address?.address_1 && (
          <Card className="mt-8 p-8 bg-card/50 backdrop-blur-sm border-border/50">
            <h3 className="text-xl font-semibold text-accent mb-4">Shipping To</h3>
            <div className="text-foreground/90 space-y-1">
              <div className="font-medium">
                {[orderSummary.shipping_address.first_name, orderSummary.shipping_address.last_name]
                  .filter(Boolean)
                  .join(' ')}
              </div>
              {orderSummary.shipping_address.company && <div>{orderSummary.shipping_address.company}</div>}
              <div>{orderSummary.shipping_address.address_1}</div>
              {orderSummary.shipping_address.address_2 && <div>{orderSummary.shipping_address.address_2}</div>}
              <div>
                {[orderSummary.shipping_address.city, orderSummary.shipping_address.state, orderSummary.shipping_address.postcode]
                  .filter(Boolean)
                  .join(', ')}
              </div>
              {orderSummary.shipping_address.country && <div>{orderSummary.shipping_address.country}</div>}
            </div>
            <p className="text-sm text-muted-foreground mt-4">
              If anything looks wrong, please{' '}
              <a
                href="/contact-us/"
                className="text-accent hover:text-accent/80 underline underline-offset-2"
              >
                contact support
              </a>{' '}
              as soon as possible so we can fix it before shipment.
            </p>
          </Card>
        )}

        {/* What's Next Section */}
        <Card className="mt-8 p-8 bg-gradient-to-br from-secondary/30 to-card/50 backdrop-blur-sm border-border/50">
          <h2 className="text-2xl font-bold mb-4 text-accent">What's Next?</h2>
          <ul className="space-y-3 text-foreground">
            <li className="flex items-start gap-3">
              <span className="text-accent mt-1">✉️</span>
              <span>You'll receive a confirmation email with your order details shortly.</span>
            </li>
            <li className="flex items-start gap-3">
              <span className="text-accent mt-1">📦</span>
              <span>Your order will be shipped within 1-2 business days.</span>
            </li>
            <li className="flex items-start gap-3">
              <span className="text-accent mt-1">📧</span>
              <span>You'll receive tracking information once your order ships.</span>
            </li>
            {!!orderSummary?.view_order_url && (
              <li className="flex items-start gap-3">
                <span className="text-accent mt-1">🧾</span>
                <a
                  href={orderSummary.view_order_url}
                  className="text-accent hover:text-accent/80 underline underline-offset-2"
                >
                  View this order in My Account
                </a>
              </li>
            )}
          </ul>
        </Card>

        {/* Continue Shopping */}
        <div className="text-center mt-8">
          <a 
            href={logoLink}
            className="text-accent hover:text-accent/80 font-medium"
          >
            ← Continue Shopping
          </a>
        </div>
      </div>

      {/* Footer */}
      <footer className="py-8 px-4 border-t border-border/50 mt-12">
        <div className="max-w-4xl mx-auto text-center text-muted-foreground text-sm">
          <p className="mb-2">© {new Date().getFullYear()} {funnelName}</p>
          <p className="text-xs">
            If you have any questions about your order, please contact our support team.
          </p>
        </div>
      </footer>
    </div>
  );
};

export default FunnelThankYou;















