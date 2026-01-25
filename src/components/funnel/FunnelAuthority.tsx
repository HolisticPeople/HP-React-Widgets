import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useResponsive } from '@/hooks/use-responsive';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';
import { smoothScrollTo } from '@/hooks/use-smooth-scroll';

const QuoteIcon = () => (
  <svg className="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" opacity={0.5}>
    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
  </svg>
);

export interface Quote {
  text: string;
}

export interface QuoteCategory {
  title: string;
  quotes: string[];
}

export interface ArticleLink {
  text: string;
  url: string;
}

export interface FunnelAuthorityProps {
  title?: string;
  subtitle?: string;
  name: string;
  credentials?: string;
  image?: string;
  bio?: string;
  // Simple quotes (flat list)
  quotes?: Quote[] | string[];
  // Categorized quotes (grouped by topic)
  quoteCategories?: QuoteCategory[];
  // Optional article link
  articleLink?: ArticleLink;
  // CTA
  ctaText?: string;
  ctaUrl?: string;
  ctaBehavior?: 'scroll_offers' | 'checkout';
  checkoutUrl?: string;
  featuredOfferId?: string;
  layout?: 'side-by-side' | 'centered' | 'card' | 'illumodine';
  className?: string;
  // Responsive settings (v2.32.9)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
  mobileLayout?: 'stacked' | 'centered'; // Mobile always stacks, but can be centered
}

export const FunnelAuthority = ({
  title = 'Who We Are',
  subtitle,
  name,
  credentials,
  image,
  bio,
  quotes = [],
  quoteCategories = [],
  articleLink,
  ctaText,
  ctaUrl,
  ctaBehavior = 'scroll_offers',
  checkoutUrl,
  featuredOfferId,
  layout = 'side-by-side',
  className,
  heightBehavior = 'scrollable', // Authority often has long content, use scrollable
  mobileLayout = 'stacked',
}: FunnelAuthorityProps) => {
  // Responsive hooks
  const { isMobile } = useResponsive();
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  
  // Normalize quotes to always be objects
  const normalizedQuotes = quotes.map((q) =>
    typeof q === 'string' ? { text: q } : q
  );

  const hasQuoteCategories = quoteCategories && quoteCategories.length > 0;

  // Handle CTA click based on behavior setting
  const handleCtaClick = () => {
    if (ctaBehavior === 'checkout') {
      // Navigate to checkout with featured offer
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
        // Fallback to checkout if no offers section
        window.location.href = checkoutUrl || ctaUrl || '#checkout';
      }
    }
  };

  return (
    <section
      className={cn(
        'hp-funnel-authority hp-funnel-section py-20 px-4 bg-gradient-to-br from-secondary/20 via-background to-primary/10',
        heightClassName,
        className
      )}
      style={heightStyle}
    >
      <div className="max-w-6xl mx-auto">
        {/* Section Title */}
        {title && (
          <div className="text-center mb-12">
            <h2 className="text-4xl md:text-5xl font-bold text-accent mb-4">
              {title}
            </h2>
            {subtitle && (
              <p className="text-xl text-muted-foreground">{subtitle}</p>
            )}
          </div>
        )}

        {/* Illumodine-style layout with categorized quotes */}
        {(layout === 'illumodine' || hasQuoteCategories) && (
          <div className="grid md:grid-cols-2 gap-12 items-start">
            {/* Image Column */}
            {image && (
              <div className="relative">
                <div className="absolute inset-0 bg-gradient-to-br from-accent/20 to-primary/20 rounded-2xl blur-2xl" />
                <img
                  src={image}
                  alt={name}
                  className="relative rounded-2xl shadow-2xl w-full max-w-md mx-auto"
                />
              </div>
            )}

            {/* Quote Categories Column */}
            <div className="space-y-8">
              {quoteCategories.map((category, catIndex) => (
                <div key={catIndex} className="space-y-3">
                  <h3 className="text-xl font-bold text-accent">{category.title}</h3>
                  <div className="space-y-2">
                    {category.quotes.map((quote, quoteIndex) => (
                      <blockquote
                        key={quoteIndex}
                        className="pl-0 md:pl-4 border-l-0 md:border-l-2 border-accent/30 text-foreground/90 italic"
                      >
                        "{quote}"
                      </blockquote>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Side-by-side layout (original) */}
        {layout === 'side-by-side' && !hasQuoteCategories && (
          <div className="grid md:grid-cols-2 gap-12 items-center">
            {/* Image */}
            {image && (
              <div className="relative">
                <div className="absolute inset-0 bg-gradient-to-br from-accent/20 to-primary/20 rounded-2xl blur-2xl" />
                <img
                  src={image}
                  alt={name}
                  className="relative rounded-2xl shadow-2xl w-full max-w-md mx-auto"
                />
              </div>
            )}

            {/* Content */}
            <div className="space-y-6">
              <div>
                <h3 className="text-3xl font-bold text-foreground">{name}</h3>
                {credentials && (
                  <p className="text-xl text-accent mt-1">{credentials}</p>
                )}
              </div>

              {bio && (
                <div
                  className="text-muted-foreground prose prose-invert max-w-none"
                  dangerouslySetInnerHTML={{ __html: bio }}
                />
              )}

              {normalizedQuotes.length > 0 && (
                <div className="space-y-4 mt-8">
                  {normalizedQuotes.map((quote, index) => (
                    <blockquote
                      key={index}
                      className="relative pl-0 md:pl-6 border-l-0 md:border-l-4 border-accent/50 italic text-foreground/90"
                    >
                      <span className="absolute -left-2 -top-2 text-accent opacity-30 hidden md:block">
                        <QuoteIcon />
                      </span>
                      "{quote.text}"
                    </blockquote>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Centered layout */}
        {layout === 'centered' && !hasQuoteCategories && (
          <div className="text-center max-w-3xl mx-auto">
            {image && (
              <div className="relative inline-block mb-8">
                <div className="absolute inset-0 bg-gradient-to-br from-accent/30 to-primary/30 rounded-full blur-xl" />
                <img
                  src={image}
                  alt={name}
                  className="relative w-48 h-48 rounded-full object-cover shadow-2xl mx-auto"
                />
              </div>
            )}

            <h3 className="text-3xl font-bold text-foreground">{name}</h3>
            {credentials && (
              <p className="text-xl text-accent mt-2">{credentials}</p>
            )}

            {bio && (
              <div
                className="text-muted-foreground prose prose-invert max-w-none mt-6"
                dangerouslySetInnerHTML={{ __html: bio }}
              />
            )}

            {normalizedQuotes.length > 0 && (
              <div className="space-y-6 mt-8">
                {normalizedQuotes.map((quote, index) => (
                  <blockquote
                    key={index}
                    className="relative text-xl italic text-foreground/90 px-8"
                  >
                    <span className="absolute left-0 top-0 text-accent">
                      <QuoteIcon />
                    </span>
                    "{quote.text}"
                  </blockquote>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Card layout */}
        {layout === 'card' && !hasQuoteCategories && (
          <Card className="p-8 md:p-12 bg-card/50 backdrop-blur-sm border-border/50 max-w-4xl mx-auto">
            <div className="flex flex-col md:flex-row gap-8 items-center">
              {image && (
                <div className="flex-shrink-0">
                  <img
                    src={image}
                    alt={name}
                    className="w-40 h-40 rounded-full object-cover shadow-lg"
                  />
                </div>
              )}

              <div className="flex-1 text-center md:text-left">
                <h3 className="text-2xl font-bold text-foreground">{name}</h3>
                {credentials && (
                  <p className="text-lg text-accent mt-1">{credentials}</p>
                )}

                {bio && (
                  <div
                    className="text-muted-foreground mt-4 prose prose-invert max-w-none"
                    dangerouslySetInnerHTML={{ __html: bio }}
                  />
                )}
              </div>
            </div>

            {normalizedQuotes.length > 0 && (
              <div className="mt-8 pt-8 border-t border-border/50 space-y-4">
                {normalizedQuotes.map((quote, index) => (
                  <blockquote
                    key={index}
                    className="relative pl-0 md:pl-6 border-l-0 md:border-l-4 border-accent/50 italic text-foreground/90"
                  >
                    "{quote.text}"
                  </blockquote>
                ))}
              </div>
            )}
          </Card>
        )}

        {/* Article Link & CTA */}
        {(articleLink || ctaText) && (
          <div className="flex flex-col sm:flex-row gap-4 justify-center items-center mt-12">
            {articleLink && (
              <Button
                variant="outline"
                onClick={() => window.location.href = articleLink.url}
                className="border-accent/50 text-accent hover:bg-accent/10"
              >
                {articleLink.text}
                <svg className="w-4 h-4 ml-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M5 12h14M12 5l7 7-7 7" />
                </svg>
              </Button>
            )}
            {ctaText && (
              <Button
                size="lg"
                onClick={handleCtaClick}
                className="hp-funnel-cta-btn font-bold px-8 py-3 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
              >
                {ctaText}
              </Button>
            )}
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelAuthority;
