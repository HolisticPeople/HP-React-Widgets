import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const QuoteIcon = () => (
  <svg className="w-8 h-8" viewBox="0 0 24 24" fill="currentColor" opacity={0.3}>
    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
  </svg>
);

export interface Quote {
  text: string;
}

export interface FunnelAuthorityProps {
  title?: string;
  name: string;
  credentials?: string;
  image?: string;
  bio?: string;
  quotes?: Quote[] | string[];
  layout?: 'side-by-side' | 'centered' | 'card';
  className?: string;
}

export const FunnelAuthority = ({
  title = 'Who We Are',
  name,
  credentials,
  image,
  bio,
  quotes = [],
  layout = 'side-by-side',
  className,
}: FunnelAuthorityProps) => {
  // Normalize quotes to always be objects
  const normalizedQuotes = quotes.map((q) =>
    typeof q === 'string' ? { text: q } : q
  );

  return (
    <section
      className={cn(
        'hp-funnel-authority py-20 px-4 bg-gradient-to-br from-secondary/20 via-background to-primary/10',
        className
      )}
    >
      <div className="max-w-6xl mx-auto">
        {/* Section Title */}
        {title && (
          <h2 className="text-4xl md:text-5xl font-bold text-center text-accent mb-12">
            {title}
          </h2>
        )}

        {layout === 'side-by-side' && (
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
                      className="relative pl-6 border-l-4 border-accent/50 italic text-foreground/90"
                    >
                      <span className="absolute -left-2 -top-2 text-accent opacity-30">
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

        {layout === 'centered' && (
          <div className="text-center max-w-3xl mx-auto">
            {/* Image */}
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

            {/* Name & Credentials */}
            <h3 className="text-3xl font-bold text-foreground">{name}</h3>
            {credentials && (
              <p className="text-xl text-accent mt-2">{credentials}</p>
            )}

            {/* Bio */}
            {bio && (
              <div
                className="text-muted-foreground prose prose-invert max-w-none mt-6"
                dangerouslySetInnerHTML={{ __html: bio }}
              />
            )}

            {/* Quotes */}
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

        {layout === 'card' && (
          <Card className="p-8 md:p-12 bg-card/50 backdrop-blur-sm border-border/50 max-w-4xl mx-auto">
            <div className="flex flex-col md:flex-row gap-8 items-center">
              {/* Image */}
              {image && (
                <div className="flex-shrink-0">
                  <img
                    src={image}
                    alt={name}
                    className="w-40 h-40 rounded-full object-cover shadow-lg"
                  />
                </div>
              )}

              {/* Content */}
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

            {/* Quotes */}
            {normalizedQuotes.length > 0 && (
              <div className="mt-8 pt-8 border-t border-border/50 space-y-4">
                {normalizedQuotes.map((quote, index) => (
                  <blockquote
                    key={index}
                    className="relative pl-6 border-l-4 border-accent/50 italic text-foreground/90"
                  >
                    "{quote.text}"
                  </blockquote>
                ))}
              </div>
            )}
          </Card>
        )}
      </div>
    </section>
  );
};

export default FunnelAuthority;

