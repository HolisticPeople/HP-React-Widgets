import { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
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

const ArrowLeftIcon = () => (
  <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M19 12H5M12 19l-7-7 7-7" />
  </svg>
);

const ArrowRightIcon = () => (
  <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <path d="M5 12h14M12 5l7 7-7 7" />
  </svg>
);

export interface Testimonial {
  name: string;
  role?: string;
  title?: string;  // Review title like "Excellent!" or "Love this stuff"
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
  ctaText?: string;
  ctaUrl?: string;
  className?: string;
}

export const FunnelTestimonials = ({
  title = 'What Our Customers Say',
  subtitle,
  testimonials,
  columns = 3,
  showRatings = true,
  layout = 'cards',
  ctaText,
  ctaUrl,
  className,
}: FunnelTestimonialsProps) => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const carouselRef = useRef<HTMLDivElement>(null);

  const gridCols = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
  };

  const renderRating = (rating: number = 5) => (
    <div className="flex gap-0.5 mb-3">
      {[1, 2, 3, 4, 5].map((star) => (
        <StarIcon key={star} filled={star <= rating} />
      ))}
    </div>
  );

  const scrollCarousel = (direction: 'prev' | 'next') => {
    if (!carouselRef.current) return;
    const cardWidth = 320 + 24; // card width + gap
    const newIndex = direction === 'next' 
      ? Math.min(currentIndex + 1, testimonials.length - 1)
      : Math.max(currentIndex - 1, 0);
    setCurrentIndex(newIndex);
    carouselRef.current.scrollTo({
      left: newIndex * cardWidth,
      behavior: 'smooth'
    });
  };

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
                  {/* Quote icon and stars row */}
                  <div className="flex items-start justify-between mb-3">
                    <span className="flex-shrink-0">
                      <QuoteIcon />
                    </span>
                    {showRatings && (
                      <div className="flex gap-0.5">
                        {[1, 2, 3, 4, 5].map((star) => (
                          <StarIcon key={star} filled={star <= (testimonial.rating || 5)} />
                        ))}
                      </div>
                    )}
                  </div>

                  {testimonial.title && (
                    <h3 className="text-lg font-bold text-foreground mb-2">
                      {testimonial.title}
                    </h3>
                  )}

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
                      <p className="font-semibold text-foreground">— {testimonial.name}</p>
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

                {testimonial.title && (
                  <h3 className="text-xl font-bold text-foreground mb-3">
                    {testimonial.title}
                  </h3>
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
                    <p className="font-bold text-foreground">— {testimonial.name}</p>
                    {testimonial.role && (
                      <p className="text-sm text-muted-foreground">{testimonial.role}</p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Carousel layout with navigation */}
        {layout === 'carousel' && (
          <div className="relative">
            <div 
              ref={carouselRef}
              className="overflow-x-auto pb-4 scroll-smooth scrollbar-hide"
              style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
              <div className="flex gap-6" style={{ minWidth: 'max-content', paddingLeft: '1rem', paddingRight: '1rem' }}>
                {testimonials.map((testimonial, index) => (
                  <Card
                    key={index}
                    className="p-6 bg-card/50 backdrop-blur-sm border-border/50 w-80 flex-shrink-0"
                  >
                    {showRatings && renderRating(testimonial.rating)}

                    {testimonial.title && (
                      <h3 className="text-lg font-bold text-foreground mb-2">
                        {testimonial.title}
                      </h3>
                    )}

                    <p className="text-foreground/90 italic mb-6 text-sm">
                      "{testimonial.quote}"
                    </p>

                    <div className="flex items-center gap-3">
                      {testimonial.image && (
                        <img
                          src={testimonial.image}
                          alt={testimonial.name}
                          className="w-10 h-10 rounded-full object-cover"
                        />
                      )}
                      <div>
                        <p className="font-semibold text-foreground text-sm">— {testimonial.name}</p>
                        {testimonial.role && (
                          <p className="text-xs text-muted-foreground">{testimonial.role}</p>
                        )}
                      </div>
                    </div>
                  </Card>
                ))}
              </div>
            </div>

            {/* Navigation arrows */}
            <button
              onClick={() => scrollCarousel('prev')}
              disabled={currentIndex === 0}
              className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-4 w-10 h-10 rounded-full bg-background/80 border border-border/50 flex items-center justify-center text-foreground hover:bg-accent/20 hover:border-accent/50 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
            >
              <ArrowLeftIcon />
            </button>
            <button
              onClick={() => scrollCarousel('next')}
              disabled={currentIndex >= testimonials.length - 3}
              className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-4 w-10 h-10 rounded-full bg-background/80 border border-border/50 flex items-center justify-center text-foreground hover:bg-accent/20 hover:border-accent/50 transition-all disabled:opacity-30 disabled:cursor-not-allowed"
            >
              <ArrowRightIcon />
            </button>
          </div>
        )}

        {/* CTA */}
        {ctaText && ctaUrl && (
          <div className="text-center mt-12">
            <Button
              size="lg"
              onClick={() => window.location.href = ctaUrl}
              className="bg-accent hover:bg-accent/90 text-accent-foreground font-bold text-xl px-12 py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
            >
              {ctaText}
            </Button>
          </div>
        )}
      </div>
    </section>
  );
};

export default FunnelTestimonials;
