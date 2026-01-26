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
 * ImageLightbox - Full-screen image viewer with pinch-to-zoom support
 * v2.43.3: Added for mobile touch-to-zoom functionality
 */
const ImageLightbox = ({ 
  src, 
  alt, 
  isOpen, 
  onClose 
}: { 
  src: string; 
  alt: string; 
  isOpen: boolean; 
  onClose: () => void;
}) => {
  if (!isOpen) return null;
  
  return (
    <div 
      className="fixed inset-0 z-[9999] bg-black/95 flex items-center justify-center"
      onClick={onClose}
    >
      {/* Close button */}
      <button
        onClick={onClose}
        className="absolute top-4 right-4 z-10 w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors"
        aria-label="Close"
      >
        <svg className="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
      
      {/* Hint text */}
      <div className="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/70 text-sm">
        Pinch to zoom • Tap to close
      </div>
      
      {/* Image container - allows native pinch-to-zoom */}
      <div 
        className="w-full h-full overflow-auto touch-pinch-zoom"
        onClick={(e) => e.stopPropagation()}
      >
        <img
          src={src}
          alt={alt}
          className="w-full h-auto min-h-full object-contain"
          style={{ touchAction: 'pinch-zoom' }}
        />
      </div>
    </div>
  );
};

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
  
  // v2.43.3: Lightbox state for touch-to-zoom
  const [lightboxImage, setLightboxImage] = React.useState<string | null>(null);
  
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
  // v2.43.4: Added tap-to-zoom for mobile even when showing scaled desktop image
  const renderDesktop = () => (
    <div className={cn(
      "hp-infographics-desktop",
      shouldUseMobileImages ? "hidden md:block" : "" // Show on all devices if not using mobile images
    )}>
      {desktopImage && (
        <>
          <img
            src={desktopImage}
            alt={altText}
            className={cn(
              "w-full h-auto rounded-lg shadow-lg",
              // On mobile (when not using mobile images), make tappable for zoom
              !shouldUseMobileImages && "md:cursor-default cursor-zoom-in active:opacity-90 transition-opacity"
            )}
            loading="lazy"
            onClick={() => {
              // Only trigger lightbox on mobile when not using mobile images
              if (!shouldUseMobileImages && isMobile) {
                setLightboxImage(desktopImage);
              }
            }}
          />
          {/* Tap hint on mobile when showing scaled desktop image */}
          {!shouldUseMobileImages && isMobile && (
            <p 
              className="text-center text-xs mt-2 opacity-50 md:hidden"
              style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
            >
              Tap image to zoom
            </p>
          )}
        </>
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
  // Full-width images with no horizontal margins on mobile
  // v2.43.3: Added tap-to-zoom functionality
  const renderMobileStack = () => (
    <div className="hp-infographics-mobile-stack flex flex-col gap-2 md:hidden">
      {titleImage && (
        <img
          src={titleImage}
          alt={`${altText} - Title`}
          className="w-full h-auto cursor-zoom-in active:opacity-90 transition-opacity"
          loading="lazy"
          onClick={() => setLightboxImage(titleImage)}
        />
      )}
      {leftPanelImage && (
        <img
          src={leftPanelImage}
          alt={`${altText} - Left panel`}
          className="w-full h-auto cursor-zoom-in active:opacity-90 transition-opacity"
          loading="lazy"
          onClick={() => setLightboxImage(leftPanelImage)}
        />
      )}
      {rightPanelImage && (
        <img
          src={rightPanelImage}
          alt={`${altText} - Right panel`}
          className="w-full h-auto cursor-zoom-in active:opacity-90 transition-opacity"
          loading="lazy"
          onClick={() => setLightboxImage(rightPanelImage)}
        />
      )}
      {/* Tap hint - shown once */}
      <p 
        className="text-center text-xs mt-1 opacity-50"
        style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
      >
        Tap image to zoom
      </p>
    </div>
  );

  // Mobile Carousel View - Title on top, swipeable panels with dots
  // Full-width images with no horizontal margins on mobile
  // v2.43.3: Added tap-to-zoom functionality
  const renderMobileCarousel = () => {
    const panels = [
      { src: leftPanelImage, label: 'Left panel' },
      { src: rightPanelImage, label: 'Right panel' },
    ].filter(p => p.src);

    return (
      <div className="hp-infographics-mobile-carousel flex flex-col gap-2 md:hidden">
        {/* Title image at top - full width */}
        {titleImage && (
          <img
            src={titleImage}
            alt={`${altText} - Title`}
            className="w-full h-auto cursor-zoom-in active:opacity-90 transition-opacity"
            loading="lazy"
            onClick={() => setLightboxImage(titleImage)}
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
                      className="w-full h-auto cursor-zoom-in active:opacity-90 transition-opacity"
                      loading="lazy"
                      onClick={() => panel.src && setLightboxImage(panel.src)}
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

            {/* Swipe and tap hint */}
            <p 
              className="text-center text-xs mt-2 opacity-50"
              style={{ color: 'var(--hp-funnel-text-note, #a3a3a3)' }}
            >
              Swipe to compare • Tap to zoom
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
    <>
      {/* v2.43.3: Lightbox for touch-to-zoom on mobile */}
      <ImageLightbox
        src={lightboxImage || ''}
        alt={altText}
        isOpen={!!lightboxImage}
        onClose={() => setLightboxImage(null)}
      />
      
      <section
        className={cn(
          'hp-funnel-infographics hp-funnel-section py-4 md:py-16 px-0 lg:px-4', // v2.43.0: Only large screens get horizontal margins
          className
        )}
        data-effective-mode={effectiveMode}
      >
        {/* Container: full-width on mobile/tablet, max-width on desktop (v2.43.0) */}
        <div className="w-full lg:max-w-6xl lg:mx-auto">
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
    </>
  );
};

export default FunnelInfographics;
