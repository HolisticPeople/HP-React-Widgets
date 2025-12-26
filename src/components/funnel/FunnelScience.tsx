import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
  layout?: 'columns' | 'stacked' | 'cards';
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
        'hp-funnel-science py-20 px-4 bg-gradient-to-b from-background via-secondary/10 to-background',
        className
      )}
    >
      <div className="max-w-6xl mx-auto">
        {/* Section Header */}
        {(title || subtitle) && (
          <div className="text-center mb-12">
            {title && (
              <h2 className="text-4xl md:text-5xl font-bold text-accent mb-4">
                {title}
              </h2>
            )}
            {subtitle && (
              <p className="text-xl text-muted-foreground max-w-3xl mx-auto">
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Columns Layout */}
        {layout === 'columns' && (
          <div className={cn(
            'grid gap-8',
            sections.length === 2 && 'md:grid-cols-2',
            sections.length >= 3 && 'md:grid-cols-2 lg:grid-cols-3'
          )}>
            {sections.map((section, index) => (
              <div key={index} className="space-y-4">
                <h3 className="text-2xl font-bold text-foreground">
                  {section.title}
                </h3>
                {section.description && (
                  <p className="text-muted-foreground">
                    {section.description}
                  </p>
                )}
                {section.bullets && section.bullets.length > 0 && (
                  <ul className="space-y-2 text-foreground/90">
                    {section.bullets.map((bullet, bIndex) => (
                      <li key={bIndex} className="flex items-start gap-2">
                        <span className="text-accent mt-1">•</span>
                        <span>{bullet}</span>
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
          <div className="space-y-12">
            {sections.map((section, index) => (
              <div 
                key={index} 
                className={cn(
                  'grid gap-8 items-center',
                  index % 2 === 0 ? 'md:grid-cols-[2fr,1fr]' : 'md:grid-cols-[1fr,2fr] md:flex-row-reverse'
                )}
              >
                <div className="space-y-4">
                  <h3 className="text-3xl font-bold text-foreground">
                    {section.title}
                  </h3>
                  {section.description && (
                    <p className="text-lg text-muted-foreground">
                      {section.description}
                    </p>
                  )}
                  {section.bullets && section.bullets.length > 0 && (
                    <ul className="space-y-3 text-foreground/90">
                      {section.bullets.map((bullet, bIndex) => (
                        <li key={bIndex} className="flex items-start gap-3">
                          <svg className="w-5 h-5 text-accent flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <polyline points="20 6 9 17 4 12" />
                          </svg>
                          <span>{bullet}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <div className="hidden md:block">
                  {/* Decorative element */}
                  <div className="aspect-square bg-gradient-to-br from-accent/10 via-primary/10 to-accent/10 rounded-2xl flex items-center justify-center">
                    <div className="text-8xl text-accent/20 font-bold">{index + 1}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Cards Layout */}
        {layout === 'cards' && (
          <div className={cn(
            'grid gap-6',
            sections.length === 2 && 'md:grid-cols-2',
            sections.length >= 3 && 'md:grid-cols-2 lg:grid-cols-3'
          )}>
            {sections.map((section, index) => (
              <Card 
                key={index} 
                className="p-8 bg-card/50 backdrop-blur-sm border-border/50 hover:border-accent/30 transition-all duration-300"
              >
                <h3 className="text-2xl font-bold text-accent mb-4">
                  {section.title}
                </h3>
                {section.description && (
                  <p className="text-muted-foreground mb-4">
                    {section.description}
                  </p>
                )}
                {section.bullets && section.bullets.length > 0 && (
                  <ul className="space-y-2 text-foreground/90">
                    {section.bullets.map((bullet, bIndex) => (
                      <li key={bIndex} className="flex items-start gap-2">
                        <span className="text-accent mt-1">•</span>
                        <span className="text-sm">{bullet}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </Card>
            ))}
          </div>
        )}

        {/* CTA */}
        {ctaText && ctaUrl && (
          <div className="text-center mt-12">
            <Button
              size="lg"
              onClick={() => window.location.href = ctaUrl}
              className="hp-funnel-cta-btn font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
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














