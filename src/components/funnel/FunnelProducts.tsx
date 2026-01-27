import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useResponsive } from '@/hooks/use-responsive';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';
import { smoothScrollTo } from '@/hooks/use-smooth-scroll';

const CheckIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

export interface FunnelProduct {
  id: string;
  sku: string;
  name: string;
  description?: string;
  price: number;
  regularPrice?: number;
  image?: string;
  badge?: string;
  features?: string[];
  isBestValue?: boolean;
  ctaText?: string;
  ctaUrl?: string;
}

export interface FunnelProductsProps {
  title?: string;
  subtitle?: string;
  products: FunnelProduct[];
  defaultCtaText?: string;
  defaultCtaUrl?: string;
  showPrices?: boolean;
  showFeatures?: boolean;
  layout?: 'grid' | 'horizontal';
  backgroundColor?: string;
  backgroundGradient?: string;
  className?: string;
  // Responsive settings (v2.32.10)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
  mobileLayout?: 'stacked' | 'compact' | 'carousel';
  tabletColumns?: 1 | 2;
  desktopColumns?: 2 | 3 | 4;
}

export const FunnelProducts = ({
  title = 'Choose Your Package',
  subtitle,
  products,
  defaultCtaText = 'Select',
  defaultCtaUrl,
  showPrices = true,
  showFeatures = true,
  layout = 'grid',
  backgroundColor,
  backgroundGradient,
  className,
  heightBehavior = 'scrollable', // Products section often needs scrolling
  mobileLayout = 'stacked',
  tabletColumns = 2,
  desktopColumns = 3,
}: FunnelProductsProps) => {
  // Responsive hooks
  const { breakpoint, isMobile, isTablet } = useResponsive();
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  
  // v2.43.0: Auto-adjust columns based on offer count for optimal layout
  const productCount = products.length;
  const autoDesktopColumns = productCount <= 2 ? productCount : desktopColumns;
  const autoTabletColumns = productCount === 1 ? 1 : tabletColumns;
  
  // Determine effective columns based on breakpoint
  const effectiveColumns = isMobile 
    ? 1 // Mobile always single column for stacked/compact
    : isTablet 
      ? autoTabletColumns 
      : autoDesktopColumns;
  
  const handleProductClick = (product: FunnelProduct) => {
    const url = product.ctaUrl || defaultCtaUrl;

    // If checkout is embedded on the same page, select the offer in-place and scroll to it.
    const checkoutNode =
      document.querySelector<HTMLElement>('[data-component="FunnelCheckoutApp"]') ||
      document.querySelector<HTMLElement>('[data-component="FunnelCheckout"]') ||
      document.querySelector<HTMLElement>('[data-component="CheckoutStep"]');

    if (checkoutNode) {
      // Persist selection in URL (so refresh keeps it, where supported)
      try {
        const current = new URL(window.location.href);
        current.searchParams.set('offer', product.id);
        window.history.replaceState({}, '', current.toString());
      } catch {}

      // Notify any checkout component to select this offer
      window.dispatchEvent(
        new CustomEvent('hp_funnel_offer_select', { detail: { offerId: product.id, sku: product.sku } })
      );

      smoothScrollTo(checkoutNode, { offset: -20 });
      return;
    }

    // Fallback: navigate to checkout URL
    if (url) {
      const targetUrl = new URL(url, window.location.origin);
      // offerId is the canonical identifier in the new offers system
      targetUrl.searchParams.set('offer', product.id);
      window.location.href = targetUrl.toString();
    }
  };

  // Extended grid column options for responsive support (v2.43.0: wider cards for 1-2 offers)
  const gridCols: Record<number, string> = {
    1: 'max-w-lg mx-auto', // Single offer: wider centered card
    2: 'md:grid-cols-2 max-w-3xl mx-auto', // Two offers: wider cards, centered
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
  };

  // Use effectiveColumns for responsive behavior, considering product count
  const columnClass = gridCols[Math.min(productCount <= 2 ? productCount : effectiveColumns, 4)] || gridCols[3];

  // Build background style, merge with height behavior
  const bgStyle: React.CSSProperties = { ...heightStyle };
  if (backgroundColor) {
    bgStyle.backgroundColor = backgroundColor;
  } else if (backgroundGradient) {
    bgStyle.background = backgroundGradient;
  }

  return (
    <section
      className={cn(
        'hp-funnel-products hp-funnel-section py-20 px-4',
        heightClassName,
        className
      )}
      style={bgStyle}
    >
      <div className="max-w-5xl mx-auto">
        {/* Section Header */}
        {(title || subtitle) && (
          <div className="text-center mb-12">
            {title && (
              <h2 className="text-4xl md:text-5xl font-bold text-accent mb-4">
                {title}
              </h2>
            )}
            {subtitle && (
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Products Grid */}
        <div
          className={cn(
            'grid gap-8',
            layout === 'grid' && columnClass,
            layout === 'horizontal' && 'md:grid-cols-1 max-w-2xl mx-auto'
          )}
        >
          {products.map((product) => (
            <Card
              key={product.id}
              onClick={() => {
                // On mobile, only the CTA button is clickable - card click is disabled
                if (!isMobile) {
                  handleProductClick(product);
                }
              }}
              className={cn(
                'relative p-8 transition-all duration-300 flex flex-col h-full',
                // Only show pointer cursor on desktop
                !isMobile && 'cursor-pointer',
                product.isBestValue
                  ? 'bg-gradient-to-br from-accent/10 to-card/70 backdrop-blur-sm border-accent shadow-[0_0_40px_hsl(45_95%_60%/0.3)] scale-105'
                  : 'bg-card/70 backdrop-blur-sm border-border/50 hover:border-accent/50 hover:shadow-[0_0_30px_hsl(var(--accent)/0.3)]',
                layout === 'horizontal' && 'md:flex-row md:items-center md:gap-8'
              )}
            >
              {/* Badge */}
              {product.badge && (
                <div className="hp-funnel-badge-pill absolute top-0 right-6 -translate-y-1/2 px-4 py-1 rounded-full font-bold text-sm uppercase tracking-wide shadow-lg">
                  {product.badge}
                </div>
              )}

              {/* Product Image */}
              {product.image && (
                <div
                  className={cn(
                    'flex justify-center mb-6',
                    layout === 'horizontal' && 'md:mb-0 md:flex-shrink-0'
                  )}
                >
                  <img
                    src={product.image}
                    alt={product.name}
                    className="h-32 w-auto drop-shadow-lg"
                  />
                </div>
              )}

              {/* Product Info */}
              <div className={cn('flex flex-col flex-1', layout === 'horizontal' && 'md:flex-1')}>
                {/* Price */}
                {showPrices && (
                  <div className="mb-4">
                    <span className="text-accent text-5xl font-bold">
                      ${product.price.toFixed(0)}
                    </span>
                    {product.regularPrice && product.regularPrice > product.price && (
                      <span className="text-muted-foreground line-through ml-3 text-xl">
                        ${product.regularPrice.toFixed(0)}
                      </span>
                    )}
                  </div>
                )}

                {/* Name & Description */}
                <h3 className="text-2xl font-bold mb-2 text-foreground">
                  {product.name}
                </h3>

                {product.description && (
                  <p className="text-lg mb-4 text-accent">{product.description}</p>
                )}

                {/* Features */}
                {showFeatures && product.features && product.features.length > 0 && (
                  <ul className="space-y-2 text-foreground/90 mb-6">
                    {product.features.map((feature, idx) => (
                      <li key={idx} className="flex items-center gap-2">
                        <span className="text-accent">
                          <CheckIcon />
                        </span>
                        {feature}
                      </li>
                    ))}
                  </ul>
                )}

                {/* CTA Button - styled like floating CTA on mobile for consistency */}
                {(product.ctaUrl || defaultCtaUrl) && (
                  <div className="mt-auto pt-4">
                    <Button
                      onClick={(e) => {
                        e.stopPropagation();
                        handleProductClick(product);
                      }}
                      className="hp-funnel-cta-btn w-full rounded-full transition-all duration-300"
                      style={isMobile ? {
                        // Match floating CTA style exactly
                        fontSize: '20px',
                        fontWeight: 700,
                        textTransform: 'uppercase',
                        letterSpacing: '0.5px',
                        padding: '14px 48px',
                      } : {
                        fontSize: '18px',
                        fontWeight: 700,
                        padding: '24px 32px',
                      }}
                    >
                      {product.ctaText || defaultCtaText}
                    </Button>
                  </div>
                )}
              </div>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
};

export default FunnelProducts;

