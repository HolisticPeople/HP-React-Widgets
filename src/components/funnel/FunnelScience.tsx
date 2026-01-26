import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useResponsive } from '@/hooks/use-responsive';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';
import { smoothScrollTo } from '@/hooks/use-smooth-scroll';

export interface ScienceSection {
  title: string;
  description?: string;
  bullets?: string[];
}

export interface FunnelScienceProps {
  title?: string;
  subtitle?: string;
  sections: ScienceSection[];
  ctaText?: string;
  ctaUrl?: string;
  ctaBehavior?: 'scroll_offers' | 'checkout';
  checkoutUrl?: string;
  featuredOfferId?: string;
  layout?: 'columns' | 'stacked';
  className?: string;
  // Responsive settings (v2.32.9)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
  mobileColumns?: 1 | 2;
  tabletColumns?: 1 | 2 | 3;
  desktopColumns?: 2 | 3 | 4;
}

export const FunnelScience = ({
  title = 'The Science Behind Our Product',
  subtitle,
  sections,
  ctaText,
  ctaUrl,
  ctaBehavior = 'scroll_offers',
  checkoutUrl,
  featuredOfferId,
  layout = 'columns',
  className,
  heightBehavior = 'scrollable', // Science is typically scrollable content
  mobileColumns = 1,
  tabletColumns = 2,
  desktopColumns = 3,
}: FunnelScienceProps) => {
  // Responsive hooks
  const { breakpoint } = useResponsive();
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  
  // Determine effective columns based on breakpoint
  const effectiveColumns = breakpoint === 'mobile' 
    ? mobileColumns 
    : breakpoint === 'tablet' 
      ? tabletColumns 
      : desktopColumns;

  // If there are fewer cards than the configured columns, cap columns so cards can use more horizontal space.
  const cappedColumns = Math.max(1, Math.min(effectiveColumns, sections.length || 1));
  
  // Handle CTA click based on behavior setting
  const handleCtaClick = () => {
    if (ctaBehavior === 'checkout') {
      const url = checkoutUrl || ctaUrl || '#checkout';
      const separator = url.includes('?') ? '&' : '?';
      window.location.href = featuredOfferId 
        ? `${url}${separator}offer=${featuredOfferId}`
        : url;
    } else {
      // Scroll to offers section - use same selectors as HeroSection
      const offersSection = document.getElementById('hp-funnel-offers') 
        || document.querySelector('.hp-funnel-products')
        || document.querySelector('[data-section="offers"]')
        || document.querySelector('[data-component="FunnelProducts"]');
      if (offersSection) {
        smoothScrollTo(offersSection as HTMLElement, { offset: -20 });
      } else {
        window.location.href = checkoutUrl || ctaUrl || '#checkout';
      }
    }
  };

  return (
    <section
      className={cn(
        'hp-funnel-science hp-funnel-section py-16 md:py-20 px-4',
        heightClassName,
        className
      )}
      style={heightStyle}
    >
      <div className="max-w-7xl mx-auto">
        {/* Section Header */}
        {(title || subtitle) && (
          <div className="text-center mb-12 md:mb-16">
            {title && (
              <h2 
                className="text-3xl md:text-4xl lg:text-5xl font-bold italic mb-4"
                style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
              >
                {title}
              </h2>
            )}
            {subtitle && (
              <p 
                className="text-lg md:text-xl max-w-3xl mx-auto"
                style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
              >
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Cards Grid Layout - uses responsive column settings */}
        {layout === 'columns' && (
          <div className={cn(
            'grid',
            sections.length === 2 ? 'gap-5 md:gap-6' : 'gap-6 md:gap-8',
            sections.length === 1 && 'max-w-2xl mx-auto',
            // Use cappedColumns for responsive grid (prevents 2 cards being forced into 3 columns)
            cappedColumns === 1 && 'grid-cols-1',
            cappedColumns === 2 && 'grid-cols-1 md:grid-cols-2',
            cappedColumns === 3 && 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
            cappedColumns === 4 && 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
          )}>
            {sections.map((section, index) => (
              <div 
                key={index} 
                className={cn(
                  "hp-science-card rounded-xl transition-all duration-300 hover:shadow-lg",
                  // If only 2 cards, keep them visually wider/shorter by reducing padding at larger breakpoints
                  sections.length === 2 ? "p-5 md:p-6 lg:p-6" : "p-6 md:p-8"
                )}
                style={{
                  backgroundColor: 'var(--hp-funnel-card-bg, #1a1a1a)',
                  border: '1px solid var(--hp-funnel-border, #7c3aed)',
                }}
              >
                <h3 
                  className="text-xl md:text-2xl font-bold mb-4"
                  style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                >
                  {section.title}
                </h3>
                {section.description && (
                  <p 
                    className="mb-4 leading-relaxed"
                    style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                  >
                    {section.description}
                  </p>
                )}
                {section.bullets && section.bullets.length > 0 && (
                  <ul className="space-y-2">
                    {section.bullets.map((bullet, bIndex) => (
                      <li 
                        key={bIndex} 
                        className="flex items-start gap-3"
                        style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                      >
                        <span 
                          className="mt-1.5 flex-shrink-0"
                          style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                        >
                          •
                        </span>
                        <span className="text-sm md:text-base">{bullet}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            ))}
          </div>
        )}

        {/* Stacked Layout */}
        {layout === 'stacked' && (
          <div className="space-y-8 md:space-y-12">
            {sections.map((section, index) => (
              <div 
                key={index} 
                className="hp-science-card rounded-xl p-6 md:p-8"
                style={{
                  backgroundColor: 'var(--hp-funnel-card-bg, #1a1a1a)',
                  border: '1px solid var(--hp-funnel-border, #7c3aed)',
                }}
              >
                <h3 
                  className="text-2xl md:text-3xl font-bold mb-4"
                  style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                >
                  {section.title}
                </h3>
                {section.description && (
                  <p 
                    className="text-lg mb-4 leading-relaxed"
                    style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                  >
                    {section.description}
                  </p>
                )}
                {section.bullets && section.bullets.length > 0 && (
                  <ul className="space-y-3">
                    {section.bullets.map((bullet, bIndex) => (
                      <li 
                        key={bIndex} 
                        className="flex items-start gap-3"
                        style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                      >
                        <svg 
                          className="w-5 h-5 flex-shrink-0 mt-0.5" 
                          viewBox="0 0 24 24" 
                          fill="none" 
                          stroke="currentColor" 
                          strokeWidth="2"
                          style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                        >
                          <polyline points="20 6 9 17 4 12" />
                        </svg>
                        <span>{bullet}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            ))}
          </div>
        )}

        {/* CTA */}
        {ctaText && (
          <div className="text-center mt-12 md:mt-16">
            <Button
              size="lg"
              onClick={handleCtaClick}
              className="hp-funnel-cta-btn font-bold text-lg md:text-xl px-10 md:px-12 py-5 md:py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
            >
              {ctaText}
            </Button>
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelScience;
