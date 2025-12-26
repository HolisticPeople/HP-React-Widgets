import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

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
  
  className?: string;
}

// Title size mapping with CSS values (to override WordPress theme CSS)
const titleSizes: Record<string, { mobile: string; desktop: string }> = {
  'sm': { mobile: '1.875rem', desktop: '2.25rem' },    // 30px → 36px
  'md': { mobile: '2.25rem', desktop: '3rem' },        // 36px → 48px
  'lg': { mobile: '3rem', desktop: '3.75rem' },        // 48px → 60px
  'xl': { mobile: '3.75rem', desktop: '4.5rem' },      // 60px → 72px
  '2xl': { mobile: '4.5rem', desktop: '6rem' },        // 72px → 96px
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
  backgroundGradient,
  backgroundColor,
  backgroundImage,
  useGlobalBackground = true,
  accentColor,
  textAlign = 'left',
  imagePosition = 'right',
  minHeight = '600px',
  titleSize = 'xl', // Default to xl (matches reference funnel)
  className,
}: FunnelHeroSectionProps) => {
  const customStyles: React.CSSProperties = {
    minHeight,
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
            'grid gap-12 items-center',
            hasImage && 'md:grid-cols-2',
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

            <div className={cn(
              'flex gap-4 mt-8',
              textAlign === 'center' && 'justify-center',
              textAlign === 'right' && 'justify-end'
            )}>
              <Button
                size="lg"
                onClick={() => window.location.href = ctaUrl}
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

          {/* Hero Image (side position) */}
          {hasImage && (
            <div
              className={cn(
                'relative flex justify-center items-center',
                imagePosition === 'left' && 'md:order-1'
              )}
            >
              <div className="absolute inset-0 bg-gradient-to-r from-accent/20 via-primary/30 to-accent/20 rounded-full blur-3xl scale-75" />
              <img
                src={heroImage}
                alt={heroImageAlt}
                className="relative w-full max-w-md h-auto drop-shadow-[0_0_40px_hsl(45_95%_60%/0.6)]"
              />
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default FunnelHeroSection;

