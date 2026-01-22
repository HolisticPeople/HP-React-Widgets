import { useState } from 'react';
import { cn } from '@/lib/utils';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';

const ChevronIcon = ({ open }: { open: boolean }) => (
  <svg
    className={cn(
      'w-5 h-5 transition-transform duration-200',
      open && 'rotate-180'
    )}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth="2"
  >
    <polyline points="6 9 12 15 18 9" />
  </svg>
);

export interface FaqItem {
  question: string;
  answer: string;
}

export interface FunnelFaqProps {
  title?: string;
  subtitle?: string;
  faqs: FaqItem[];
  allowMultiple?: boolean;
  className?: string;
  // Responsive settings (v2.32.9)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
}

export const FunnelFaq = ({
  title = 'Frequently Asked Questions',
  subtitle,
  faqs,
  allowMultiple = false,
  className,
  heightBehavior = 'scrollable', // FAQ is typically scrollable content
}: FunnelFaqProps) => {
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  const [openItems, setOpenItems] = useState<Set<number>>(new Set());

  const toggleItem = (index: number) => {
    setOpenItems((prev) => {
      const next = new Set(prev);
      if (next.has(index)) {
        next.delete(index);
      } else {
        if (!allowMultiple) {
          next.clear();
        }
        next.add(index);
      }
      return next;
    });
  };

  return (
    <section
      className={cn(
        'hp-funnel-faq hp-funnel-section py-20 px-4 bg-gradient-to-b from-background to-secondary/10',
        heightClassName,
        className
      )}
      style={heightStyle}
    >
      <div className="max-w-3xl mx-auto">
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

        {/* FAQ Accordion */}
        <div className="space-y-4">
          {faqs.map((faq, index) => {
            const isOpen = openItems.has(index);

            return (
              <div
                key={index}
                className="rounded-xl border border-border/60 bg-card/60 backdrop-blur-sm overflow-hidden transition-all duration-200 hover:border-accent/40"
              >
                <button
                  onClick={() => toggleItem(index)}
                  className={cn(
                    "w-full flex items-center justify-between p-6 text-left transition-colors",
                    "bg-card/40 hover:bg-card/55"
                  )}
                  aria-expanded={isOpen}
                >
                  <span className="font-medium text-lg text-foreground/95 pr-4">
                    {faq.question}
                  </span>
                  <span className="text-accent flex-shrink-0">
                    <ChevronIcon open={isOpen} />
                  </span>
                </button>

                <div
                  className={cn(
                    'overflow-hidden transition-all duration-300',
                    isOpen ? 'max-h-[500px] opacity-100' : 'max-h-0 opacity-0'
                  )}
                >
                  <div
                    className="px-6 pb-6 text-muted-foreground prose prose-invert max-w-none"
                    dangerouslySetInnerHTML={{ __html: faq.answer }}
                  />
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
};

export default FunnelFaq;














