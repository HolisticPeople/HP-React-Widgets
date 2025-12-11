import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const StarIcon = ({ filled }: { filled: boolean }) => (
  <svg
    className={cn('w-5 h-5', filled ? 'text-accent fill-accent' : 'text-muted-foreground')}
    viewBox="0 0 24 24"
    fill={filled ? 'currentColor' : 'none'}
    stroke="currentColor"
    strokeWidth="2"
  >
    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
  </svg>
);

const QuoteIcon = () => (
  <svg className="w-10 h-10 text-accent/20" viewBox="0 0 24 24" fill="currentColor">
    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
  </svg>
);

export interface Testimonial {
  name: string;
  role?: string;
  quote: string;
  image?: string;
  rating?: number;
}

export interface FunnelTestimonialsProps {
  title?: string;
  subtitle?: string;
  testimonials: Testimonial[];
  columns?: 2 | 3;
  showRatings?: boolean;
  layout?: 'cards' | 'carousel' | 'simple';
  className?: string;
}

export const FunnelTestimonials = ({
  title = 'What Our Customers Say',
  subtitle,
  testimonials,
  columns = 3,
  showRatings = true,
  layout = 'cards',
  className,
}: FunnelTestimonialsProps) => {
  const gridCols = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
  };

  const renderRating = (rating: number = 5) => (
    <div className="flex gap-1 mb-3">
      {[1, 2, 3, 4, 5].map((star) => (
        <StarIcon key={star} filled={star <= rating} />
      ))}
    </div>
  );

  return (
    <section
      className={cn(
        'hp-funnel-testimonials py-20 px-4 bg-gradient-to-b from-background via-secondary/10 to-background',
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

        {/* Testimonials Grid */}
        {layout === 'cards' && (
          <div className={cn('grid gap-8', gridCols[columns])}>
            {testimonials.map((testimonial, index) => (
              <Card
                key={index}
                className="p-6 bg-card/50 backdrop-blur-sm border-border/50 hover:border-accent/30 transition-all duration-300"
              >
                <div className="relative">
                  <span className="absolute -top-2 -left-2">
                    <QuoteIcon />
                  </span>

                  {showRatings && renderRating(testimonial.rating)}

                  <p className="text-foreground/90 italic mb-6 relative z-10">
                    "{testimonial.quote}"
                  </p>

                  <div className="flex items-center gap-4">
                    {testimonial.image && (
                      <img
                        src={testimonial.image}
                        alt={testimonial.name}
                        className="w-12 h-12 rounded-full object-cover"
                      />
                    )}
                    <div>
                      <p className="font-bold text-foreground">{testimonial.name}</p>
                      {testimonial.role && (
                        <p className="text-sm text-muted-foreground">{testimonial.role}</p>
                      )}
                    </div>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        )}

        {layout === 'simple' && (
          <div className="max-w-4xl mx-auto space-y-8">
            {testimonials.map((testimonial, index) => (
              <div
                key={index}
                className="text-center py-8 border-b border-border/30 last:border-0"
              >
                {showRatings && (
                  <div className="flex justify-center mb-4">
                    {renderRating(testimonial.rating)}
                  </div>
                )}

                <p className="text-xl text-foreground/90 italic mb-6">
                  "{testimonial.quote}"
                </p>

                <div className="flex items-center justify-center gap-4">
                  {testimonial.image && (
                    <img
                      src={testimonial.image}
                      alt={testimonial.name}
                      className="w-10 h-10 rounded-full object-cover"
                    />
                  )}
                  <div className="text-left">
                    <p className="font-bold text-foreground">{testimonial.name}</p>
                    {testimonial.role && (
                      <p className="text-sm text-muted-foreground">{testimonial.role}</p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Note: Carousel layout would require additional dependencies */}
        {layout === 'carousel' && (
          <div className="overflow-x-auto pb-4 -mx-4 px-4">
            <div className="flex gap-6" style={{ minWidth: 'max-content' }}>
              {testimonials.map((testimonial, index) => (
                <Card
                  key={index}
                  className="p-6 bg-card/50 backdrop-blur-sm border-border/50 w-80 flex-shrink-0"
                >
                  {showRatings && renderRating(testimonial.rating)}

                  <p className="text-foreground/90 italic mb-6">
                    "{testimonial.quote}"
                  </p>

                  <div className="flex items-center gap-4">
                    {testimonial.image && (
                      <img
                        src={testimonial.image}
                        alt={testimonial.name}
                        className="w-12 h-12 rounded-full object-cover"
                      />
                    )}
                    <div>
                      <p className="font-bold text-foreground">{testimonial.name}</p>
                      {testimonial.role && (
                        <p className="text-sm text-muted-foreground">{testimonial.role}</p>
                      )}
                    </div>
                  </div>
                </Card>
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelTestimonials;

