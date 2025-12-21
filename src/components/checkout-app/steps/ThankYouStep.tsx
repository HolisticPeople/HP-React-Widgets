import { Card } from '@/components/ui/card';
import type { OrderSummary } from '../types';

const CheckCircleIcon = () => (
  <svg className="w-16 h-16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
    <polyline points="22 4 12 14.01 9 11.01" />
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

interface ThankYouStepProps {
  funnelName: string;
  headline?: string;
  message?: string;
  orderSummary: OrderSummary | null;
  logoUrl?: string;
  logoLink?: string;
}

export const ThankYouStep = ({
  funnelName,
  headline = 'Thank You for Your Order!',
  message = 'Your order has been confirmed and is being processed.',
  orderSummary,
  logoLink = '/',
}: ThankYouStepProps) => {
  return (
    <div className="max-w-4xl mx-auto py-12">
      {/* Success Header */}
      <div className="text-center mb-12">
        <div className="text-accent mb-6 flex justify-center">
          <CheckCircleIcon />
        </div>
        <h1 className="text-4xl md:text-5xl font-bold mb-4 text-accent">
          {headline}
        </h1>
        <p className="text-xl text-muted-foreground">
          {message}
        </p>
        {orderSummary && (
          <p className="text-lg text-foreground mt-4">
            Order #{orderSummary.orderNumber}
          </p>
        )}
      </div>

      {/* Order Summary */}
      {orderSummary && (
        <Card className="p-8 bg-card/50 backdrop-blur-sm border-border/50 mb-8">
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
            {orderSummary.itemsDiscount > 0 && (
              <div className="flex justify-between text-green-500">
                <span>Discount</span>
                <span>-${orderSummary.itemsDiscount.toFixed(2)}</span>
              </div>
            )}
            
            {orderSummary.pointsRedeemed.value > 0 && (
              <div className="flex justify-between text-green-500">
                <span>Points Redeemed ({orderSummary.pointsRedeemed.points} pts)</span>
                <span>-${orderSummary.pointsRedeemed.value.toFixed(2)}</span>
              </div>
            )}
            
            <div className="flex justify-between text-foreground">
              <span>Shipping</span>
              <span>
                {orderSummary.shippingTotal === 0 
                  ? <span className="text-accent">FREE</span>
                  : `$${orderSummary.shippingTotal.toFixed(2)}`
                }
              </span>
            </div>

            <div className="flex justify-between text-xl font-bold pt-2 border-t border-border/50">
              <span className="text-accent">Total</span>
              <span className="text-accent">${orderSummary.grandTotal.toFixed(2)}</span>
            </div>
          </div>
        </Card>
      )}

      {/* What's Next Section */}
      <Card className="p-8 bg-gradient-to-br from-secondary/30 to-card/50 backdrop-blur-sm border-border/50 mb-8">
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
        </ul>
      </Card>

      {/* Continue Shopping */}
      <div className="text-center">
        <a 
          href={logoLink}
          className="text-accent hover:text-accent/80 font-medium"
        >
          ← Continue Shopping
        </a>
      </div>
    </div>
  );
};

export default ThankYouStep;

