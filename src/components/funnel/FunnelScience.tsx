import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

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
  layout?: 'columns' | 'stacked';
  className?: string;
}

export const FunnelScience = ({
  title = 'The Science Behind Our Product',
  subtitle,
  sections,
  ctaText,
  ctaUrl,
  layout = 'columns',
  className,
}: FunnelScienceProps) => {
  return (
    <section
      className={cn(
        'hp-funnel-science hp-funnel-section py-16 md:py-20 px-4',
        className
      )}
    >
      <div className="max-w-6xl mx-auto">
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

        {/* Cards Grid Layout */}
        {layout === 'columns' && (
          <div className={cn(
            'grid gap-6 md:gap-8',
            sections.length === 1 && 'max-w-2xl mx-auto',
            sections.length === 2 && 'md:grid-cols-2',
            sections.length >= 3 && 'md:grid-cols-2 lg:grid-cols-3'
          )}>
            {sections.map((section, index) => (
              <div 
                key={index} 
                className="hp-science-card rounded-xl p-6 md:p-8 transition-all duration-300 hover:shadow-lg"
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
        {ctaText && ctaUrl && (
          <div className="text-center mt-12 md:mt-16">
            <Button
              size="lg"
              onClick={() => window.location.href = ctaUrl}
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
