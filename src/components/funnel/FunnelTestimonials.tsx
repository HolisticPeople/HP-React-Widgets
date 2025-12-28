import { useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const StarIcon = ({ filled }: { filled: boolean }) => (
  <svg
    className={cn('w-5 h-5', filled ? 'fill-current' : '')}
    viewBox="0 0 24 24"
    fill={filled ? 'currentColor' : 'none'}
    stroke="currentColor"
    strokeWidth="2"
    style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
  >
    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
  </svg>
);

const QuoteIcon = () => (
  <svg 
    className="w-10 h-10 opacity-40" 
    viewBox="0 0 24 24" 
    fill="currentColor"
    style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
  >
    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z" />
  </svg>
);

const ArrowLeftIcon = () => (
  <span style={{ fontSize: '20px', lineHeight: 1 }}>‹</span>
);

const ArrowRightIcon = () => (
  <span style={{ fontSize: '20px', lineHeight: 1 }}>›</span>
);

export interface Testimonial {
  name: string;
  role?: string;
  title?: string;
  quote: string;
  image?: string;
  rating?: number;
}

export interface FunnelTestimonialsProps {
  title?: string;
  subtitle?: string;
  testimonials: Testimonial[];
  columns?: 2 | 3 | 4;
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

  const gridCols: Record<number, string> = {
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 lg:grid-cols-4',
  };

  const renderRating = (rating: number = 5) => (
    <div className="flex gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <StarIcon key={star} filled={star <= rating} />
      ))}
    </div>
  );

  const scrollCarousel = (direction: 'prev' | 'next') => {
    if (!carouselRef.current) return;
    const container = carouselRef.current;
    const cardWidth = container.querySelector('.testimonial-card')?.clientWidth || 320;
    const gap = 24;
    const scrollAmount = cardWidth + gap;
    
    const newIndex = direction === 'next' 
      ? Math.min(currentIndex + 1, testimonials.length - 1)
      : Math.max(currentIndex - 1, 0);
    
    setCurrentIndex(newIndex);
    container.scrollTo({
      left: newIndex * scrollAmount,
      behavior: 'smooth'
    });
  };

  // Card style used by both layouts
  const cardStyle = {
    backgroundColor: 'var(--hp-funnel-card-bg, #1a1a1a)',
    border: '1px solid var(--hp-funnel-border, #7c3aed)',
  };

  const renderTestimonialCard = (testimonial: Testimonial, index: number, isCarousel = false) => (
    <div
      key={index}
      className={cn(
        'testimonial-card rounded-xl p-6 md:p-8 transition-all duration-300',
        isCarousel && 'w-[320px] md:w-[400px] flex-shrink-0'
      )}
      style={cardStyle}
    >
      <div className="relative">
        {/* Quote icon and stars row */}
        <div className="flex items-start justify-between mb-4">
          <QuoteIcon />
          {showRatings && renderRating(testimonial.rating)}
        </div>

        {testimonial.title && (
          <h3 
            className="text-lg font-bold mb-3"
            style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
          >
            {testimonial.title}
          </h3>
        )}

        <p 
          className="italic mb-6 leading-relaxed"
          style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
        >
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
            <p 
              className="font-semibold"
              style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
            >
              — {testimonial.name}
            </p>
            {testimonial.role && (
              <p 
                className="text-sm"
                style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
              >
                {testimonial.role}
              </p>
            )}
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <section
      className={cn(
        'hp-funnel-testimonials hp-funnel-section py-16 md:py-20 px-4',
        className
      )}
    >
      <div className="max-w-6xl mx-auto">
        {/* Section Header */}
        {(title || subtitle) && (
          <div className="text-center mb-12">
            {title && (
              <h2 
                className="text-3xl md:text-4xl lg:text-5xl font-bold italic mb-4"
                style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
              >
                {title}
              </h2>
            )}
            {subtitle && (
              <p 
                className="text-lg md:text-xl max-w-2xl mx-auto"
                style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
              >
                {subtitle}
              </p>
            )}
          </div>
        )}

        {/* Grid Layout */}
        {layout === 'cards' && (
          <div className={cn('grid gap-6 md:gap-8', gridCols[columns])}>
            {testimonials.map((testimonial, index) => 
              renderTestimonialCard(testimonial, index)
            )}
          </div>
        )}

        {/* Simple Layout */}
        {layout === 'simple' && (
          <div className="max-w-4xl mx-auto space-y-8">
            {testimonials.map((testimonial, index) => (
              <div
                key={index}
                className="text-center py-8 border-b last:border-0"
                style={{ borderColor: 'var(--hp-funnel-border, #7c3aed)' }}
              >
                {showRatings && (
                  <div className="flex justify-center mb-4">
                    {renderRating(testimonial.rating)}
                  </div>
                )}

                {testimonial.title && (
                  <h3 
                    className="text-xl font-bold mb-3"
                    style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                  >
                    {testimonial.title}
                  </h3>
                )}

                <p 
                  className="text-xl italic mb-6"
                  style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                >
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
                    <p 
                      className="font-bold"
                      style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                    >
                      — {testimonial.name}
                    </p>
                    {testimonial.role && (
                      <p 
                        className="text-sm"
                        style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
                      >
                        {testimonial.role}
                      </p>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Carousel/Slider Layout */}
        {layout === 'carousel' && (
          <div className="relative px-12 md:px-16">
            {/* Navigation arrows - positioned outside cards */}
            {testimonials.length > 1 && (
              <button
                onClick={() => scrollCarousel('prev')}
                disabled={currentIndex === 0}
                className="absolute left-0 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:scale-110"
                style={{ 
                  backgroundColor: 'var(--hp-funnel-card-bg, #1a1a1a)',
                  border: '1px solid var(--hp-funnel-border, #7c3aed)',
                  color: 'var(--hp-funnel-text-basic, #e5e5e5)',
                }}
              >
                <ArrowLeftIcon />
              </button>
            )}

            <div 
              ref={carouselRef}
              className="overflow-x-auto scroll-smooth"
              style={{ scrollbarWidth: 'none', msOverflowStyle: 'none' }}
            >
              <style>{`.hp-funnel-testimonials::-webkit-scrollbar { display: none; }`}</style>
              <div 
                className="flex gap-6"
                style={{ minWidth: 'max-content' }}
              >
                {testimonials.map((testimonial, index) => (
                  <div
                    key={index}
                    className="testimonial-card rounded-xl p-6 flex-shrink-0"
                    style={{
                      ...cardStyle,
                      width: 'min(600px, 80vw)',
                      minHeight: 'auto',
                    }}
                  >
                    {/* Quote icon and stars row */}
                    <div className="flex items-center justify-between mb-3">
                      <QuoteIcon />
                      {showRatings && renderRating(testimonial.rating)}
                    </div>

                    {testimonial.title && (
                      <h3 
                        className="text-lg font-bold mb-2"
                        style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
                      >
                        {testimonial.title}
                      </h3>
                    )}

                    <p 
                      className="italic mb-4 leading-relaxed line-clamp-4"
                      style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                    >
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
                        <p 
                          className="font-semibold text-sm"
                          style={{ color: 'var(--hp-funnel-text-basic, #e5e5e5)' }}
                        >
                          — {testimonial.name}
                        </p>
                        {testimonial.role && (
                          <p 
                            className="text-xs"
                            style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
                          >
                            {testimonial.role}
                          </p>
                        )}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Right arrow */}
            {testimonials.length > 1 && (
              <button
                onClick={() => scrollCarousel('next')}
                disabled={currentIndex >= testimonials.length - 1}
                className="absolute right-0 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed hover:scale-110"
                style={{ 
                  backgroundColor: 'var(--hp-funnel-card-bg, #1a1a1a)',
                  border: '1px solid var(--hp-funnel-border, #7c3aed)',
                  color: 'var(--hp-funnel-text-basic, #e5e5e5)',
                }}
              >
                <ArrowRightIcon />
              </button>
            )}
          </div>
        )}

        {/* CTA */}
        {ctaText && ctaUrl && (
          <div className="text-center mt-12">
            <Button
              size="lg"
              onClick={() => window.location.href = ctaUrl}
              className="hp-funnel-cta-btn font-bold text-lg md:text-xl px-10 md:px-12 py-5 md:py-6 rounded-full shadow-lg hover:shadow-xl transition-all duration-300"
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
