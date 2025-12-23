import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

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
}: FunnelProductsProps) => {
  const handleProductClick = (product: FunnelProduct) => {
    const url = product.ctaUrl || defaultCtaUrl;
    if (url) {
      const targetUrl = new URL(url, window.location.origin);
      targetUrl.searchParams.set('product', product.id);
      targetUrl.searchParams.set('sku', product.sku);
      window.location.href = targetUrl.toString();
    }
  };

  const gridCols = {
    1: 'max-w-md mx-auto',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
  };

  const columnClass = gridCols[Math.min(products.length, 3) as 1 | 2 | 3];

  // Build background style
  const bgStyle: React.CSSProperties = {};
  if (backgroundColor) {
    bgStyle.backgroundColor = backgroundColor;
  } else if (backgroundGradient) {
    bgStyle.background = backgroundGradient;
  }

  return (
    <section
      className={cn(
        'hp-funnel-products hp-funnel-section py-20 px-4',
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
              className={cn(
                'relative p-8 transition-all duration-300',
                product.isBestValue
                  ? 'bg-gradient-to-br from-accent/10 to-card/70 backdrop-blur-sm border-accent shadow-[0_0_40px_hsl(45_95%_60%/0.3)] scale-105'
                  : 'bg-card/70 backdrop-blur-sm border-border/50 hover:border-accent/30',
                layout === 'horizontal' && 'md:flex md:items-center md:gap-8'
              )}
            >
              {/* Badge */}
              {product.badge && (
                <div className="absolute -top-3 right-4 bg-accent text-accent-foreground px-4 py-1.5 rounded-full font-bold text-sm uppercase tracking-wide shadow-lg">
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
              <div className={cn(layout === 'horizontal' && 'md:flex-1')}>
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

                {/* CTA Button */}
                {(product.ctaUrl || defaultCtaUrl) && (
                  <Button
                    onClick={() => handleProductClick(product)}
                    className="w-full bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-lg py-6 rounded-full transition-all duration-300 shadow-lg hover:shadow-xl"
                  >
                    {product.ctaText || defaultCtaText}
                  </Button>
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

