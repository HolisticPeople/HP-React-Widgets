import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export interface FunnelCtaProps {
  title?: string;
  subtitle?: string;
  buttonText?: string;
  buttonUrl: string;
  buttonSecondaryText?: string;
  buttonSecondaryUrl?: string;
  backgroundStyle?: 'gradient' | 'solid' | 'transparent';
  alignment?: 'center' | 'left';
  className?: string;
}

export const FunnelCta = ({
  title = 'Ready to Get Started?',
  subtitle,
  buttonText = 'Order Now',
  buttonUrl,
  buttonSecondaryText,
  buttonSecondaryUrl,
  backgroundStyle = 'gradient',
  alignment = 'center',
  className,
}: FunnelCtaProps) => {
  const bgClasses = {
    gradient: 'bg-gradient-to-r from-primary/30 via-secondary/40 to-primary/30',
    solid: 'bg-primary/20',
    transparent: 'bg-transparent',
  };

  return (
    <section
      className={cn(
        'hp-funnel-cta py-20 px-4',
        bgClasses[backgroundStyle],
        className
      )}
    >
      <div
        className={cn(
          'max-w-4xl mx-auto',
          alignment === 'center' && 'text-center',
          alignment === 'left' && 'text-left'
        )}
      >
        {title && (
          <h2 className="text-4xl md:text-5xl font-bold text-accent mb-4">
            {title}
          </h2>
        )}

        {subtitle && (
          <p className="text-xl text-muted-foreground mb-8 max-w-2xl mx-auto">
            {subtitle}
          </p>
        )}

        <div
          className={cn(
            'flex gap-4 flex-wrap',
            alignment === 'center' && 'justify-center',
            alignment === 'left' && 'justify-start'
          )}
        >
          <Button
            size="lg"
            onClick={() => (window.location.href = buttonUrl)}
            className="bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
          >
            {buttonText}
          </Button>

          {buttonSecondaryText && buttonSecondaryUrl && (
            <Button
              size="lg"
              variant="outline"
              onClick={() => (window.location.href = buttonSecondaryUrl)}
              className="font-bold text-xl px-8 py-6 rounded-full border-accent/50 text-accent hover:bg-accent/10"
            >
              {buttonSecondaryText}
            </Button>
          )}
        </div>
      </div>
    </section>
  );
};

export default FunnelCta;














