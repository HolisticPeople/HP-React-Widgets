import * as React from 'react';
import { cn } from '@/lib/utils';
import { useIsMobile } from '@/hooks/use-mobile';
import { useResponsive } from '@/hooks/use-responsive';
import { useHeightBehavior, HeightBehavior } from '@/hooks/use-height-behavior';
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  type CarouselApi,
} from '@/components/ui/carousel';

/**
 * FunnelInfographics - Responsive infographic comparison display.
 * 
 * Displays a full-width infographic on desktop and breaks into separate 
 * panels (title, left, right) on mobile with stack or carousel layout options.
 * 
 * @package HP-React-Widgets
 * @since 2.20.0
 * @version 1.0.0 - Initial implementation
 * @author Amnon Manneberg
 */

export interface FunnelInfographicsProps {
  title?: string;
  desktopImage: string;
  useMobileImages?: boolean;
  desktopFallback?: 'scale' | 'scroll';
  titleImage?: string;
  leftPanelImage?: string;
  rightPanelImage?: string;
  mobileLayout?: 'stack' | 'carousel';
  altText?: string;
  className?: string;
  // Responsive settings (v2.32.11)
  heightBehavior?: HeightBehavior | { mobile?: HeightBehavior; tablet?: HeightBehavior; desktop?: HeightBehavior };
  // Per-breakpoint display modes
  mobileMode?: 'split_panels' | 'swipe' | 'scale' | 'hide';
  tabletMode?: 'split_panels' | 'swipe' | 'scale' | 'full_image';
  desktopMode?: 'full_image' | 'scale';
}

export const FunnelInfographics = ({
  title,
  desktopImage,
  useMobileImages = true,
  desktopFallback = 'scale',
  titleImage,
  leftPanelImage,
  rightPanelImage,
  mobileLayout = 'stack',
  altText = 'Infographic comparison',
  className,
  heightBehavior = 'scrollable', // Infographics typically scrollable
  mobileMode = 'split_panels',
  tabletMode = 'scale',
  desktopMode = 'full_image',
}: FunnelInfographicsProps) => {
  const isMobile = useIsMobile();
  const { breakpoint, isTablet } = useResponsive();
  const { className: heightClassName, style: heightStyle } = useHeightBehavior(heightBehavior);
  
  // Determine effective mode based on breakpoint
  const effectiveMode = breakpoint === 'mobile' 
    ? mobileMode 
    : breakpoint === 'tablet' 
      ? tabletMode 
      : desktopMode;
  const [api, setApi] = React.useState<CarouselApi>();
  const [current, setCurrent] = React.useState(0);
  const [count, setCount] = React.useState(0);
  
  // Determine if we should use mobile-specific images
  // Only use mobile images if enabled AND at least one mobile image exists
  const hasMobileImages = !!(titleImage || leftPanelImage || rightPanelImage);
  const shouldUseMobileImages = useMobileImages && hasMobileImages;

  // Track carousel state for dots indicator
  React.useEffect(() => {
    if (!api) return;

    setCount(api.scrollSnapList().length);
    setCurrent(api.selectedScrollSnap());

    api.on('select', () => {
      setCurrent(api.selectedScrollSnap());
    });
  }, [api]);

  // Desktop View - Full image (hidden on mobile when using mobile-specific images)
  const renderDesktop = () => (
    <div className={cn(
      "hp-infographics-desktop",
      shouldUseMobileImages ? "hidden md:block" : "" // Show on all devices if not using mobile images
    )}>
      {desktopImage && (
        <img
          src={desktopImage}
          alt={altText}
          className="w-full h-auto rounded-lg shadow-lg"
          loading="lazy"
        />
      )}
    </div>
  );

  // Mobile Fallback View - Desktop image on mobile with scale or scroll
  const renderMobileFallback = () => {
    if (!isMobile || shouldUseMobileImages || !desktopImage) return null;

    if (desktopFallback === 'scroll') {
      // Horizontal scroll mode - maintain original size, allow swipe
      return (
        <div className="hp-infographics-mobile-scroll md:hidden">
          <div className="overflow-x-auto -mx-4 px-4 scrollbar-thin scrollbar-thumb-gray-400">
            <img
              src={desktopImage}
              alt={altText}
              className="h-auto rounded-lg shadow-lg"
              style={{ 
                minWidth: '150%', // Ensure image is wider than viewport
                maxWidth: 'none'
              }}
              loading="lazy"
            />
          </div>
          <p 
            className="text-center text-sm mt-3 opacity-60"
            style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
          >
            ← Swipe to view full image →
          </p>
        </div>
      );
    }

    // Scale mode - fit width (default) - handled by renderDesktop with full visibility
    return null;
  };

  // Mobile Stack View - Title + Left + Right stacked vertically
  const renderMobileStack = () => (
    <div className="hp-infographics-mobile-stack flex flex-col gap-4 md:hidden">
      {titleImage && (
        <img
          src={titleImage}
          alt={`${altText} - Title`}
          className="w-full h-auto rounded-lg shadow-md"
          loading="lazy"
        />
      )}
      {leftPanelImage && (
        <img
          src={leftPanelImage}
          alt={`${altText} - Left panel`}
          className="w-full h-auto rounded-lg shadow-md"
          loading="lazy"
        />
      )}
      {rightPanelImage && (
        <img
          src={rightPanelImage}
          alt={`${altText} - Right panel`}
          className="w-full h-auto rounded-lg shadow-md"
          loading="lazy"
        />
      )}
    </div>
  );

  // Mobile Carousel View - Title on top, swipeable panels with dots
  const renderMobileCarousel = () => {
    const panels = [
      { src: leftPanelImage, label: 'Left panel' },
      { src: rightPanelImage, label: 'Right panel' },
    ].filter(p => p.src);

    return (
      <div className="hp-infographics-mobile-carousel flex flex-col gap-4 md:hidden">
        {/* Title image at top */}
        {titleImage && (
          <img
            src={titleImage}
            alt={`${altText} - Title`}
            className="w-full h-auto rounded-lg shadow-md"
            loading="lazy"
          />
        )}

        {/* Carousel for left/right panels */}
        {panels.length > 0 && (
          <div className="relative">
            <Carousel 
              setApi={setApi} 
              className="w-full"
              opts={{
                align: 'start',
                loop: true,
              }}
            >
              <CarouselContent>
                {panels.map((panel, index) => (
                  <CarouselItem key={index}>
                    <img
                      src={panel.src}
                      alt={`${altText} - ${panel.label}`}
                      className="w-full h-auto rounded-lg shadow-md"
                      loading="lazy"
                    />
                  </CarouselItem>
                ))}
              </CarouselContent>
            </Carousel>

            {/* Dots indicator */}
            {count > 1 && (
              <div className="flex justify-center gap-2 mt-4">
                {Array.from({ length: count }).map((_, index) => (
                  <button
                    key={index}
                    onClick={() => api?.scrollTo(index)}
                    className={cn(
                      'w-2.5 h-2.5 rounded-full transition-all duration-300',
                      index === current
                        ? 'w-6 bg-amber-500'
                        : 'bg-gray-400 hover:bg-gray-300'
                    )}
                    style={{
                      backgroundColor: index === current 
                        ? 'var(--hp-funnel-text-accent, #eab308)' 
                        : undefined
                    }}
                    aria-label={`Go to slide ${index + 1}`}
                  />
                ))}
              </div>
            )}

            {/* Swipe hint */}
            <p 
              className="text-center text-sm mt-2 opacity-60"
              style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
            >
              Swipe to compare
            </p>
          </div>
        )}
      </div>
    );
  };

  // If no images, render nothing
  if (!desktopImage && !leftPanelImage && !rightPanelImage) {
    return null;
  }

  return (
    <section
      className={cn(
        'hp-funnel-infographics hp-funnel-section py-4 md:py-16 px-4',
        'min-h-0 h-auto flex-none self-start',
        heightClassName,
        className
      )}
      style={{ ...heightStyle, minHeight: 'auto', height: 'auto' }}
      data-effective-mode={effectiveMode}
    >
      <div className="max-w-6xl mx-auto w-full">
        {/* Optional section title */}
        {title && (
          <h2 
            className="text-2xl md:text-3xl lg:text-4xl font-bold text-center mb-8 md:mb-12"
            style={{ color: 'var(--hp-funnel-text-accent, #eab308)' }}
          >
            {title}
          </h2>
        )}

        {/* Desktop view - always rendered, visibility depends on shouldUseMobileImages */}
        {renderDesktop()}

        {/* Mobile fallback - desktop image on mobile when mobile images disabled */}
        {renderMobileFallback()}

        {/* Mobile view with separate images - based on mobileLayout setting */}
        {isMobile && shouldUseMobileImages && (
          mobileLayout === 'carousel' 
            ? renderMobileCarousel() 
            : renderMobileStack()
        )}
      </div>
    </section>
  );
};

export default FunnelInfographics;
