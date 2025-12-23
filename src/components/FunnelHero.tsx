import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

// Icon components (inline SVGs to avoid theme conflicts)
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
}

export interface FunnelHeroProps {
  // Funnel identity
  funnelId: string;
  funnelName: string;
  
  // Hero content
  title: string;
  titleSize?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  subtitle?: string;
  tagline?: string;
  description?: string;
  heroImage?: string;
  logoUrl?: string;
  logoLink?: string;
  
  // Products
  products: FunnelProduct[];
  
  // Navigation
  checkoutUrl: string;
  ctaText?: string;
  
  // Benefits section
  benefits?: string[];
  benefitsTitle?: string;
  
  // Additional sections (rendered as HTML from PHP)
  additionalSections?: string;
  
  // Styling
  accentColor?: string;
  backgroundGradient?: string;
}

// Title size mapping with CSS values (to override WordPress theme CSS)
const titleSizes: Record<string, { mobile: string; desktop: string }> = {
  'sm': { mobile: '1.875rem', desktop: '2.25rem' },    // 30px → 36px
  'md': { mobile: '2.25rem', desktop: '3rem' },        // 36px → 48px
  'lg': { mobile: '3rem', desktop: '3.75rem' },        // 48px → 60px
  'xl': { mobile: '3.75rem', desktop: '4.5rem' },      // 60px → 72px
  '2xl': { mobile: '4.5rem', desktop: '6rem' },        // 72px → 96px
};

export const FunnelHero = ({
  funnelId,
  funnelName,
  title,
  titleSize = 'xl',
  subtitle,
  tagline,
  description,
  heroImage,
  logoUrl,
  logoLink = '/',
  products,
  checkoutUrl,
  ctaText = 'Get Your Special Offer Now',
  benefits = [],
  benefitsTitle = 'Why Choose Us?',
  additionalSections,
  accentColor,
  backgroundGradient,
}: FunnelHeroProps) => {
  const [selectedProductId, setSelectedProductId] = useState<string | null>(
    products.length > 0 ? products[0].id : null
  );

  const handleCTAClick = () => {
    // Build checkout URL with selected product
    const url = new URL(checkoutUrl, window.location.origin);
    if (selectedProductId) {
      url.searchParams.set('product', selectedProductId);
    }
    url.searchParams.set('funnel', funnelId);
    window.location.href = url.toString();
  };

  // Custom CSS variables for theming
  const customStyles: React.CSSProperties = {};
  if (accentColor) {
    customStyles['--funnel-accent' as any] = accentColor;
  }

  return (
    <div className="hp-funnel-hero min-h-screen bg-background" style={customStyles}>
      {/* Logo */}
      {logoUrl && (
        <div className="absolute top-6 left-6 z-10">
          <a href={logoLink} target="_blank" rel="noopener noreferrer">
            <img 
              src={logoUrl} 
              alt={funnelName} 
              className="h-8 opacity-70 hover:opacity-100 transition-opacity" 
            />
          </a>
        </div>
      )}

      {/* Hero Section */}
      <section 
        className="relative overflow-hidden min-h-[600px] flex items-center"
        style={backgroundGradient ? { background: backgroundGradient } : undefined}
      >
        {/* Default background gradient */}
        {!backgroundGradient && (
          <>
            <div className="absolute inset-0 bg-gradient-to-br from-primary via-secondary to-background opacity-90" />
            <div 
              className="absolute inset-0" 
              style={{ 
                background: 'radial-gradient(ellipse at 30% 50%, hsl(280 65% 35% / 0.4), transparent 50%), radial-gradient(ellipse at 70% 50%, hsl(45 95% 50% / 0.3), transparent 50%)',
              }} 
            />
          </>
        )}

        {/* Animated flowing lines effect */}
        <div className="absolute inset-0 opacity-30">
          <div 
            className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-primary/20 via-transparent to-accent/20 animate-pulse" 
            style={{ animationDuration: '4s' }} 
          />
        </div>

        <div className="relative w-full max-w-7xl mx-auto px-4 py-16">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Left: Text Content */}
            <div className="text-left space-y-6">
              <h1 
                className="font-bold text-accent drop-shadow-[0_0_30px_hsl(45_95%_60%/0.5)]"
                style={{ 
                  fontSize: titleSizes[titleSize]?.desktop || titleSizes['xl'].desktop,
                  lineHeight: 1.1,
                }}
              >
                {title}
              </h1>
              {subtitle && (
                <p className="text-3xl md:text-4xl font-semibold text-accent/90">
                  {subtitle}
                </p>
              )}
              {tagline && (
                <p className="text-xl md:text-2xl text-foreground/90">
                  {tagline}
                </p>
              )}
              {description && (
                <p className="text-lg text-muted-foreground max-w-xl">
                  {description}
                </p>
              )}
              <Button 
                size="lg" 
                onClick={handleCTAClick}
                className="bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 mt-8"
              >
                {ctaText}
              </Button>
            </div>

            {/* Right: Hero Image */}
            {heroImage && (
              <div className="relative flex justify-center items-center">
                <div className="absolute inset-0 bg-gradient-to-r from-accent/20 via-primary/30 to-accent/20 rounded-full blur-3xl scale-75" />
                <img 
                  src={heroImage} 
                  alt={title} 
                  className="relative w-full max-w-md h-auto drop-shadow-[0_0_40px_hsl(45_95%_60%/0.6)]"
                />
              </div>
            )}
          </div>
        </div>
      </section>

      {/* Benefits Section */}
      {benefits.length > 0 && (
        <section className="py-20 px-4 bg-gradient-to-b from-background via-secondary/20 to-background">
          <div className="max-w-6xl mx-auto">
            <h2 className="text-4xl md:text-5xl font-bold text-center mb-12 text-accent">
              {benefitsTitle}
            </h2>

            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
              {benefits.map((benefit, index) => (
                <Card 
                  key={index} 
                  className="p-6 bg-card/50 backdrop-blur-sm border-border/50 hover:border-accent/50 transition-all duration-300 hover:shadow-[0_0_20px_hsl(45_95%_60%/0.2)]"
                >
                  <div className="flex items-start gap-3">
                    <span className="text-accent flex-shrink-0 mt-1">
                      <CheckIcon />
                    </span>
                    <p className="text-foreground">{benefit}</p>
                  </div>
                </Card>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Product Cards Section */}
      {products.length > 0 && (
        <section className="py-20 px-4 bg-gradient-to-br from-primary/20 via-secondary/30 to-background">
          <div className="max-w-4xl mx-auto text-center">
            <h2 className="text-4xl md:text-5xl font-bold mb-6 text-accent">
              Choose Your Package
            </h2>
            
            <div className={cn(
              "grid gap-8 mb-12",
              products.length === 1 ? "max-w-md mx-auto" : 
              products.length === 2 ? "md:grid-cols-2" : 
              "md:grid-cols-2 lg:grid-cols-3"
            )}>
              {products.map((product) => (
                <Card 
                  key={product.id}
                  onClick={() => setSelectedProductId(product.id)}
                  className={cn(
                    "p-8 cursor-pointer transition-all duration-300 relative",
                    product.isBestValue 
                      ? "bg-gradient-to-br from-accent/10 to-card/70 backdrop-blur-sm border-accent shadow-[0_0_40px_hsl(45_95%_60%/0.3)]" 
                      : "bg-card/70 backdrop-blur-sm border-border/50",
                    selectedProductId === product.id && "ring-2 ring-accent"
                  )}
                >
                  {product.badge && (
                    <div 
                      className="absolute top-0 right-6 -translate-y-1/2 px-4 py-1 rounded-full font-bold text-sm uppercase tracking-wide shadow-lg"
                      style={{ 
                        backgroundColor: 'var(--hp-funnel-accent)',
                        color: 'var(--hp-funnel-bg)'
                      }}
                    >
                      {product.badge}
                    </div>
                  )}
                  
                  <div className="text-accent text-5xl font-bold mb-4">
                    ${product.price.toFixed(0)}
                  </div>
                  
                  <h3 className="text-2xl font-bold mb-2 text-foreground">
                    {product.name}
                  </h3>
                  
                  {product.description && (
                    <p className="text-lg mb-4 text-accent">{product.description}</p>
                  )}
                  
                  {product.image && (
                    <div className="flex justify-center mb-4">
                      <img 
                        src={product.image} 
                        alt={product.name} 
                        className="h-32 w-auto drop-shadow-lg"
                      />
                    </div>
                  )}
                  
                  {product.features && product.features.length > 0 && (
                    <ul className="text-left space-y-2 text-foreground/90">
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
                </Card>
              ))}
            </div>

            <Button 
              size="lg" 
              onClick={handleCTAClick}
              className="bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
            >
              {ctaText}
            </Button>
          </div>
        </section>
      )}

      {/* Additional Sections (rendered from PHP) */}
      {additionalSections && (
        <div 
          className="hp-funnel-additional-sections"
          dangerouslySetInnerHTML={{ __html: additionalSections }} 
        />
      )}

      {/* Footer */}
      <footer className="py-8 px-4 border-t border-border/50">
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

export default FunnelHero;















