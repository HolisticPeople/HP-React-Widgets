import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

// Icon components
const CheckIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polyline points="20 6 9 17 4 12" />
  </svg>
);

const StarIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="currentColor">
    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
  </svg>
);

const ShieldIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
  </svg>
);

const HeartIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
  </svg>
);

const BoltIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
  </svg>
);

const LeafIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z" />
    <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12" />
  </svg>
);

const iconMap: Record<string, React.FC> = {
  check: CheckIcon,
  star: StarIcon,
  shield: ShieldIcon,
  heart: HeartIcon,
  bolt: BoltIcon,
  leaf: LeafIcon,
};

export interface Feature {
  icon?: string;
  title: string;
  description?: string;
}

export interface FunnelFeaturesProps {
  title?: string;
  subtitle?: string;
  features: Feature[];
  columns?: 2 | 3 | 4;
  layout?: 'cards' | 'list' | 'grid';
  className?: string;
}

export const FunnelFeatures = ({
  title = 'Key Features',
  subtitle,
  features,
  columns = 3,
  layout = 'cards',
  className,
}: FunnelFeaturesProps) => {
  const gridCols = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
  };

  return (
    <section
      className={cn(
        'hp-funnel-features py-20 px-4 bg-gradient-to-b from-background to-secondary/10',
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

        {/* Features Grid */}
        {layout === 'cards' && (
          <div className={cn('grid gap-8', gridCols[columns])}>
            {features.map((feature, index) => {
              const IconComponent = iconMap[feature.icon || 'check'] || CheckIcon;

              return (
                <Card
                  key={index}
                  className="p-8 bg-card/50 backdrop-blur-sm border-border/50 hover:border-accent/30 transition-all duration-300 text-center"
                >
                  <div className="flex justify-center mb-4 text-accent">
                    <IconComponent />
                  </div>
                  <h3 className="text-xl font-bold text-foreground mb-2">
                    {feature.title}
                  </h3>
                  {feature.description && (
                    <p className="text-muted-foreground">{feature.description}</p>
                  )}
                </Card>
              );
            })}
          </div>
        )}

        {layout === 'list' && (
          <div className="max-w-3xl mx-auto space-y-6">
            {features.map((feature, index) => {
              const IconComponent = iconMap[feature.icon || 'check'] || CheckIcon;

              return (
                <div
                  key={index}
                  className="flex items-start gap-4 p-6 bg-card/30 rounded-lg border border-border/50"
                >
                  <span className="text-accent flex-shrink-0">
                    <IconComponent />
                  </span>
                  <div>
                    <h3 className="text-xl font-bold text-foreground mb-1">
                      {feature.title}
                    </h3>
                    {feature.description && (
                      <p className="text-muted-foreground">{feature.description}</p>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {layout === 'grid' && (
          <div className={cn('grid gap-6', gridCols[columns])}>
            {features.map((feature, index) => {
              const IconComponent = iconMap[feature.icon || 'check'] || CheckIcon;

              return (
                <div key={index} className="flex items-start gap-3">
                  <span className="text-accent flex-shrink-0 mt-1">
                    <IconComponent />
                  </span>
                  <div>
                    <h3 className="font-bold text-foreground">{feature.title}</h3>
                    {feature.description && (
                      <p className="text-sm text-muted-foreground">{feature.description}</p>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelFeatures;














