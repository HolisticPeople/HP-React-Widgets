import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useMemo } from 'react';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';

// Icon components - Extended for Round 2
const CheckIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

const StarIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
  </svg>
);

const ShieldIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
  </svg>
);

const HeartIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
  </svg>
);

// Additional icons for Round 2
const LeafIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z" />
    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
  </svg>
);

const BoltIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
  </svg>
);

const FlaskIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2" />
    <path d="M8.5 2h7" />
    <path d="M7 16h10" />
  </svg>
);

const BrainIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 4.44-1.54" />
    <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-4.44-1.54" />
  </svg>
);

const SunIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="4" />
    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
  </svg>
);

const MoonIcon = () => (
  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z" />
  </svg>
);

const iconMap: Record<string, React.FC> = {
  check: CheckIcon,
  star: StarIcon,
  shield: ShieldIcon,
  heart: HeartIcon,
  leaf: LeafIcon,
  bolt: BoltIcon,
  flask: FlaskIcon,
  brain: BrainIcon,
  sun: SunIcon,
  moon: MoonIcon,
};

// Category display labels
const categoryLabels: Record<string, string> = {
  health: 'Health & Wellness',
  science: 'Science & Research',
  quality: 'Quality & Purity',
  results: 'Results & Benefits',
  support: 'Support & Care',
};

export interface Benefit {
  text: string;
  icon?: 'check' | 'star' | 'shield' | 'heart' | 'leaf' | 'bolt' | 'flask' | 'brain' | 'sun' | 'moon' | string;
  category?: string | null;
}

export interface FunnelBenefitsProps {
  title?: string;
  subtitle?: string;
  benefits: Benefit[] | string[];
  columns?: 2 | 3 | 4 | 5;
  defaultIcon?: 'check' | 'star' | 'shield' | 'heart';
  showCards?: boolean;
  backgroundColor?: string;
  backgroundGradient?: string;
  className?: string;
  enableCategories?: boolean; // Round 2: Enable categorized column layout
  // Responsive settings (v2.32.0)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
}

export const FunnelBenefits = ({
  title = 'Why Choose Us?',
  subtitle,
  benefits,
  columns = 3,
  defaultIcon = 'check',
  showCards = true,
  backgroundColor,
  backgroundGradient,
  className,
  enableCategories = false,
  heightBehavior = 'scrollable', // Changed from fit_viewport - overflow issues on mobile
}: FunnelBenefitsProps) => {
  // Responsive hooks
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  
  // Normalize benefits to always be objects
  const normalizedBenefits: Benefit[] = benefits.map((b) =>
    typeof b === 'string' ? { text: b, icon: defaultIcon } : b
  );

  // Group benefits by category (Round 2 improvement)
  const categorizedBenefits = useMemo(() => {
    if (!enableCategories) return null;
    
    const groups: Record<string, Benefit[]> = {};
    normalizedBenefits.forEach(benefit => {
      const cat = benefit.category || 'uncategorized';
      if (!groups[cat]) groups[cat] = [];
      groups[cat].push(benefit);
    });
    
    // Return only categories that have benefits, preserving order
    const categoryOrder = ['health', 'science', 'quality', 'results', 'support', 'uncategorized'];
    return categoryOrder
      .filter(cat => groups[cat]?.length > 0)
      .map(cat => ({ key: cat, label: categoryLabels[cat] || cat, benefits: groups[cat] }));
  }, [normalizedBenefits, enableCategories]);

  const gridCols: Record<number, string> = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
    5: 'md:grid-cols-2 lg:grid-cols-5',
  };

  // Build background style
  const bgStyle: React.CSSProperties = {};
  if (backgroundColor) {
    bgStyle.backgroundColor = backgroundColor;
  } else if (backgroundGradient) {
    bgStyle.background = backgroundGradient;
  }
  // If no custom background, section is transparent to show global funnel background

  // Render a single benefit item
  const renderBenefit = (benefit: Benefit, index: number) => {
    const IconComponent = iconMap[benefit.icon || defaultIcon] || CheckIcon;

    if (showCards) {
      return (
        <Card
          key={index}
          className="p-6 bg-card/50 backdrop-blur-sm border-border/50 hover:border-accent/50 transition-all duration-300 hover:shadow-[0_0_20px_hsl(45_95%_60%/0.2)]"
        >
          <div className="flex items-start gap-3">
            <span className="text-accent flex-shrink-0 mt-1">
              <IconComponent />
            </span>
            <p className="text-foreground">{benefit.text}</p>
          </div>
        </Card>
      );
    }

    return (
      <div key={index} className="flex items-start gap-3">
        <span className="text-accent flex-shrink-0 mt-1">
          <IconComponent />
        </span>
        <p className="text-foreground">{benefit.text}</p>
      </div>
    );
  };

  return (
    <section
      className={cn(
        'hp-funnel-benefits hp-funnel-section py-20 px-4',
        heightClassName,
        className
      )}
      style={{ ...bgStyle, ...heightStyle }}
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
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Categorized Column Layout (Round 2) */}
        {enableCategories && categorizedBenefits && categorizedBenefits.length > 0 ? (
          <div className={cn('grid gap-8', gridCols[Math.min(categorizedBenefits.length, 5) as 2|3|4|5] || 'lg:grid-cols-3')}>
            {categorizedBenefits.map(({ key, label, benefits: catBenefits }) => (
              <div key={key} className="space-y-4">
                {/* Category Title - enlarged on mobile for better visibility */}
                <h3 className="text-xl md:text-lg font-semibold text-accent border-b border-accent/30 pb-2 mb-4">
                  {label}
                </h3>
                {/* Benefits in this category */}
                <div className="space-y-3">
                  {catBenefits.map((benefit, idx) => renderBenefit(benefit, idx))}
                </div>
              </div>
            ))}
          </div>
        ) : (
          /* Standard Grid Layout */
          <div className={cn('grid gap-6', gridCols[columns] || 'lg:grid-cols-3')}>
            {normalizedBenefits.map((benefit, index) => renderBenefit(benefit, index))}
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelBenefits;

