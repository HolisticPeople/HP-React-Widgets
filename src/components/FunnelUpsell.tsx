import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

// Inline icons
const CheckIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

const LoaderIcon = () => (
  <svg className="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
    <path d="M12 2a10 10 0 0 1 10 10" strokeLinecap="round" />
  </svg>
);

const GiftIcon = () => (
  <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 12 20 22 4 22 4 12" />
    <rect x="2" y="7" width="20" height="5" />
    <line x1="12" y1="22" x2="12" y2="7" />
    <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z" />
    <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z" />
  </svg>
);

export interface UpsellOffer {
  sku: string;
  name: string;
  description?: string;
  image?: string;
  regularPrice: number;
  offerPrice: number;
  discountPercent?: number;
  features?: string[];
}

export interface FunnelUpsellProps {
  // Order context
  orderId: number;
  piId: string;
  funnelId: string;
  
  // Upsell offer
  offer: UpsellOffer;
  
  // Display
  headline?: string;
  subheadline?: string;
  ctaText?: string;
  declineText?: string;
  
  // Callbacks
  onAccept?: (result: { success: boolean; orderId: number }) => void;
  onDecline?: () => void;
  
  // API
  apiBase?: string;
}

export const FunnelUpsell = ({
  orderId,
  piId,
  funnelId,
  offer,
  headline = "Wait! Special One-Time Offer",
  subheadline = "Add this to your order with one click - no need to re-enter payment info!",
  ctaText = "Yes! Add to My Order",
  declineText = "No thanks, I'll pass on this offer",
  onAccept,
  onDecline,
  apiBase = '/wp-json/hp-rw/v1',
}: FunnelUpsellProps) => {
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isComplete, setIsComplete] = useState(false);

  const handleAccept = async () => {
    setError(null);
    setIsProcessing(true);

    try {
      const res = await fetch(`${apiBase}/upsell/charge`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          parent_order_id: orderId,
          parent_pi_id: piId,
          items: [
            {
              sku: offer.sku,
              qty: 1,
              item_discount_percent: offer.discountPercent,
            },
          ],
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        if (data.code === 'requires_action') {
          // Payment requires additional authentication
          // In a full implementation, you'd handle 3DS here
          throw new Error('Additional authentication required. Please contact support.');
        }
        throw new Error(data.message || 'Failed to process upsell');
      }

      setIsComplete(true);
      
      if (onAccept) {
        onAccept({
          success: true,
          orderId: data.order_id,
        });
      }
    } catch (err: any) {
      console.error('Upsell failed', err);
      setError(err.message || 'Something went wrong. Please try again.');
    } finally {
      setIsProcessing(false);
    }
  };

  const handleDecline = () => {
    if (onDecline) {
      onDecline();
    }
  };

  const savingsAmount = offer.regularPrice - offer.offerPrice;
  const savingsPercent = offer.discountPercent || Math.round((savingsAmount / offer.regularPrice) * 100);

  if (isComplete) {
    return (
      <Card className="hp-funnel-upsell p-8 bg-gradient-to-br from-green-500/20 to-card/50 backdrop-blur-sm border-green-500/50 text-center">
        <div className="text-green-500 text-6xl mb-4 flex justify-center">
          <CheckIcon />
        </div>
        <h2 className="text-3xl font-bold text-foreground mb-2">Added to Your Order!</h2>
        <p className="text-muted-foreground">
          {offer.name} has been added to your order. You'll receive it with your original purchase.
        </p>
      </Card>
    );
  }

  return (
    <Card className="hp-funnel-upsell p-8 bg-gradient-to-br from-accent/10 to-card/50 backdrop-blur-sm border-accent/50">
      {/* Headline */}
      <div className="text-center mb-8">
        <div className="inline-flex items-center gap-2 bg-accent/20 text-accent px-4 py-2 rounded-full mb-4">
          <GiftIcon />
          <span className="font-semibold">Exclusive One-Time Offer</span>
        </div>
        <h2 className="text-3xl md:text-4xl font-bold text-accent mb-2">
          {headline}
        </h2>
        <p className="text-lg text-muted-foreground">
          {subheadline}
        </p>
      </div>

      {/* Product Display */}
      <div className="grid md:grid-cols-2 gap-8 mb-8">
        {/* Product Image */}
        {offer.image && (
          <div className="flex justify-center items-center">
            <div className="relative">
              <div className="absolute inset-0 bg-gradient-to-r from-accent/20 via-primary/20 to-accent/20 rounded-full blur-2xl" />
              <img 
                src={offer.image} 
                alt={offer.name} 
                className="relative max-w-full h-auto max-h-64 drop-shadow-lg"
              />
            </div>
          </div>
        )}

        {/* Product Info */}
        <div className="flex flex-col justify-center">
          <h3 className="text-2xl font-bold text-foreground mb-2">{offer.name}</h3>
          
          {offer.description && (
            <p className="text-muted-foreground mb-4">{offer.description}</p>
          )}

          {/* Pricing */}
          <div className="mb-4">
            <div className="flex items-baseline gap-3">
              <span className="text-4xl font-bold text-accent">
                ${offer.offerPrice.toFixed(2)}
              </span>
              <span className="text-xl text-muted-foreground line-through">
                ${offer.regularPrice.toFixed(2)}
              </span>
            </div>
            <div className="inline-block bg-green-500/20 text-green-500 px-3 py-1 rounded-full text-sm font-semibold mt-2">
              Save {savingsPercent}% (${savingsAmount.toFixed(2)} off!)
            </div>
          </div>

          {/* Features */}
          {offer.features && offer.features.length > 0 && (
            <ul className="space-y-2 mb-6">
              {offer.features.map((feature, idx) => (
                <li key={idx} className="flex items-center gap-2 text-foreground">
                  <span className="text-accent">
                    <CheckIcon />
                  </span>
                  {feature}
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="mb-6 p-4 bg-destructive/10 border border-destructive/30 rounded-lg text-destructive text-center">
          {error}
        </div>
      )}

      {/* CTA Buttons */}
      <div className="space-y-4">
        <Button
          size="lg"
          onClick={handleAccept}
          disabled={isProcessing}
          className="hp-funnel-cta-btn w-full font-bold text-xl py-8 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
        >
          {isProcessing ? (
            <>
              <LoaderIcon />
              <span className="ml-2">Processing...</span>
            </>
          ) : (
            ctaText
          )}
        </Button>

        <button
          type="button"
          onClick={handleDecline}
          disabled={isProcessing}
          className="w-full text-muted-foreground hover:text-foreground text-sm underline transition-colors"
        >
          {declineText}
        </button>
      </div>

      {/* Trust Message */}
      <p className="text-center text-xs text-muted-foreground mt-6">
        🔒 Your payment info is already saved - just click to add to your order!
      </p>
    </Card>
  );
};

export default FunnelUpsell;















