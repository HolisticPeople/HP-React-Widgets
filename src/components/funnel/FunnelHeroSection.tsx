import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ScrollNavigation } from './ScrollNavigation';
import { useResponsive } from '@/hooks/use-responsive';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';
import { smoothScrollTo } from '@/hooks/use-smooth-scroll';

export interface FunnelHeroSectionProps {
  // Content
  title: string;
  subtitle?: string;
  tagline?: string;
  description?: string;
  heroImage?: string;
  heroImageAlt?: string;
  
  // CTA
  ctaText?: string;
  ctaUrl: string;
  ctaSecondaryText?: string;
  ctaSecondaryUrl?: string;
  ctaBehavior?: 'scroll_offers' | 'checkout'; // Button behavior
  checkoutUrl?: string;
  featuredOfferId?: string;
  
  // Styling
  backgroundGradient?: string;
  backgroundColor?: string;
  backgroundImage?: string;
  useGlobalBackground?: boolean; // If true, section is transparent to show page background
  accentColor?: string;
  textAlign?: 'left' | 'center' | 'right';
  imagePosition?: 'right' | 'left' | 'background';
  minHeight?: string;
  titleSize?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'; // Title size modifier
  
  // Responsive settings (v2.32.0)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
  mobileImagePosition?: 'below' | 'above' | 'hidden'; // Image position on mobile
  mobileTitleSize?: 'sm' | 'md' | 'lg'; // Override title size on mobile
  
  // Scroll navigation (rendered automatically when enabled)
  enableScrollNavigation?: boolean;
  
  className?: string;
}

// Title size mapping with CSS values (to override WordPress theme CSS)
const titleSizes: Record<string, { mobile: string; tablet: string; desktop: string }> = {
  'sm': { mobile: '1.5rem', tablet: '1.875rem', desktop: '2.25rem' },     // 24px → 30px → 36px
  'md': { mobile: '1.875rem', tablet: '2.25rem', desktop: '3rem' },       // 30px → 36px → 48px
  'lg': { mobile: '2.25rem', tablet: '3rem', desktop: '3.75rem' },        // 36px → 48px → 60px
  'xl': { mobile: '2.5rem', tablet: '3.75rem', desktop: '4.5rem' },       // 40px → 60px → 72px
  '2xl': { mobile: '3rem', tablet: '4.5rem', desktop: '6rem' },           // 48px → 72px → 96px
};

export const FunnelHeroSection = ({
  title,
  subtitle,
  tagline,
  description,
  heroImage,
  heroImageAlt = 'Hero image',
  ctaText = 'Get Started',
  ctaUrl,
  ctaSecondaryText,
  ctaSecondaryUrl,
  ctaBehavior = 'scroll_offers',
  checkoutUrl,
  featuredOfferId,
  backgroundGradient,
  backgroundColor,
  backgroundImage,
  useGlobalBackground = true,
  accentColor,
  textAlign = 'left',
  imagePosition = 'right',
  minHeight = '600px',
  titleSize = 'xl', // Default to xl (matches reference funnel)
  heightBehavior = 'fit_viewport',
  mobileImagePosition = 'below',
  mobileTitleSize,
  enableScrollNavigation = false,
  className,
}: FunnelHeroSectionProps) => {
  // Responsive hooks
  const { isMobile, isTablet, breakpoint } = useResponsive();
  const { className: heightClassName, style: heightStyle, isFitViewport } = useHeightBehavior(heightBehavior);
  
  // Determine effective title size based on breakpoint
  const effectiveTitleSize = isMobile && mobileTitleSize ? mobileTitleSize : titleSize;
  const titleSizeValue = breakpoint === 'mobile' 
    ? titleSizes[effectiveTitleSize]?.mobile 
    : breakpoint === 'tablet'
      ? titleSizes[effectiveTitleSize]?.tablet
      : titleSizes[effectiveTitleSize]?.desktop || titleSizes['xl'].desktop;
  
  // Handle CTA button click based on behavior
  const handleCtaClick = () => {
    if (ctaBehavior === 'checkout') {
      // Navigate to checkout with featured offer
      const url = checkoutUrl || ctaUrl;
      const separator = url.includes('?') ? '&' : '?';
      window.location.href = featuredOfferId 
        ? `${url}${separator}offer=${featuredOfferId}` 
        : url;
    } else {
      // Scroll to offers section using smooth scroll
      const offersSection = document.getElementById('hp-funnel-offers') || 
                           document.querySelector('.hp-funnel-products') ||
                           document.querySelector('[data-section="offers"]');
      if (offersSection) {
        smoothScrollTo(offersSection as HTMLElement, { offset: -20 });
      } else {
        // Fallback: navigate to checkout if no offers section found
        window.location.href = checkoutUrl || ctaUrl;
      }
    }
  };
  
  // Should we show image based on mobile settings?
  const showImage = heroImage && imagePosition !== 'background' && 
    !(isMobile && mobileImagePosition === 'hidden');
  
  // Custom styles including height behavior
  const customStyles: React.CSSProperties = {
    ...heightStyle,
    ...(isFitViewport ? {} : { minHeight }),
  };
  
  if (accentColor) {
    customStyles['--funnel-accent' as any] = accentColor;
  }

  const hasImage = heroImage && imagePosition !== 'background';
  const isBackgroundImage = heroImage && imagePosition === 'background';
  
  // Determine if we should render a background or use transparent (for global background)
  const hasCustomBackground = backgroundGradient || backgroundColor || backgroundImage || !useGlobalBackground;

  return (
    <section
      className={cn(
        'hp-funnel-hero-section hp-funnel-section relative overflow-hidden flex items-center',
        heightClassName,
        className
      )}
      style={customStyles}
    >
      {/* Background - only render if section has custom background */}
      {hasCustomBackground && (
        <>
          {backgroundImage ? (
            <div
              className="absolute inset-0 bg-cover bg-center"
              style={{ backgroundImage: `url(${backgroundImage})` }}
            >
              <div className="absolute inset-0 bg-black/50" />
            </div>
          ) : backgroundColor ? (
            <div className="absolute inset-0" style={{ backgroundColor }} />
          ) : backgroundGradient ? (
            <div className="absolute inset-0" style={{ background: backgroundGradient }} />
          ) : (
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
        </>
      )}

      {/* Background image mode (hero image as background) */}
      {isBackgroundImage && (
        <div
          className="absolute inset-0 bg-cover bg-center"
          style={{ backgroundImage: `url(${heroImage})` }}
        >
          <div className="absolute inset-0 bg-black/50" />
        </div>
      )}

      {/* Animated glow effect */}
      <div className="absolute inset-0 opacity-30 pointer-events-none">
        <div
          className="absolute top-0 left-0 w-full h-full bg-gradient-to-br from-primary/20 via-transparent to-accent/20 animate-pulse"
          style={{ animationDuration: '4s' }}
        />
      </div>

      {/* Content */}
      <div className="relative w-full max-w-7xl mx-auto px-4 py-16">
        <div
          className={cn(
            'grid gap-8 md:gap-12 items-center',
            showImage && 'md:grid-cols-2',
            imagePosition === 'left' && 'md:flex-row-reverse'
          )}
        >
          {/* Text Content */}
          <div
            className={cn(
              'space-y-6',
              textAlign === 'center' && 'text-center',
              textAlign === 'right' && 'text-right',
              imagePosition === 'left' && 'md:order-2'
            )}
          >
            <h1 
              className="font-bold text-accent drop-shadow-[0_0_30px_hsl(45_95%_60%/0.5)]"
              style={{ 
                fontSize: titleSizeValue,
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

            <div className={cn(
              'flex gap-4 mt-8',
              textAlign === 'center' && 'justify-center',
              textAlign === 'right' && 'justify-end'
            )}>
              <Button
                size="lg"
                onClick={handleCtaClick}
                className="hp-funnel-cta-btn font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
              >
                {ctaText}
              </Button>

              {ctaSecondaryText && ctaSecondaryUrl && (
                <Button
                  size="lg"
                  variant="outline"
                  onClick={() => window.location.href = ctaSecondaryUrl}
                  className="font-bold text-xl px-8 py-6 rounded-full border-accent/50 text-accent hover:bg-accent/10"
                >
                  {ctaSecondaryText}
                </Button>
              )}
            </div>
          </div>

          {/* Hero Image (side position) - respects mobile visibility and ordering */}
          {showImage && (
            <div
              className={cn(
                'relative flex justify-center items-center',
                // Desktop: respect imagePosition setting
                imagePosition === 'left' && 'md:order-1',
                // Mobile: use mobileImagePosition for ordering
                isMobile && mobileImagePosition === 'above' && 'order-first',
                isMobile && mobileImagePosition === 'below' && 'order-last'
              )}
            >
              <div className="absolute inset-0 bg-gradient-to-r from-accent/20 via-primary/30 to-accent/20 rounded-full blur-3xl scale-75" />
              <img
                src={heroImage}
                alt={heroImageAlt}
                loading="lazy"
                className="relative w-full max-w-md h-auto drop-shadow-[0_0_40px_hsl(45_95%_60%/0.6)]"
              />
            </div>
          )}
        </div>
      </div>

      {/* Scroll Navigation - rendered automatically when enabled in funnel settings */}
      {enableScrollNavigation && <ScrollNavigation />}
    </section>
  );
};

export default FunnelHeroSection;

