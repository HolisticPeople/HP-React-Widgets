import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

// Icon components
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

const iconMap: Record<string, React.FC> = {
  check: CheckIcon,
  star: StarIcon,
  shield: ShieldIcon,
  heart: HeartIcon,
};

export interface Benefit {
  text: string;
  icon?: 'check' | 'star' | 'shield' | 'heart' | string;
}

export interface FunnelBenefitsProps {
  title?: string;
  subtitle?: string;
  benefits: Benefit[] | string[];
  columns?: 2 | 3 | 4;
  defaultIcon?: 'check' | 'star' | 'shield' | 'heart';
  showCards?: boolean;
  className?: string;
}

export const FunnelBenefits = ({
  title = 'Why Choose Us?',
  subtitle,
  benefits,
  columns = 3,
  defaultIcon = 'check',
  showCards = true,
  className,
}: FunnelBenefitsProps) => {
  // Normalize benefits to always be objects
  const normalizedBenefits: Benefit[] = benefits.map((b) =>
    typeof b === 'string' ? { text: b, icon: defaultIcon } : b
  );

  const gridCols = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
  };

  return (
    <section
      className={cn(
        'hp-funnel-benefits py-20 px-4 bg-gradient-to-b from-background via-secondary/20 to-background',
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
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Benefits Grid */}
        <div className={cn('grid gap-6', gridCols[columns])}>
          {normalizedBenefits.map((benefit, index) => {
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
          })}
        </div>
      </div>
    </section>
  );
};

export default FunnelBenefits;

