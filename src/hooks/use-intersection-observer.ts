/**
 * useIntersectionObserver - Hook for lazy loading and visibility detection
 * 
 * Provides intersection observer functionality for:
 * - Lazy loading components when they enter the viewport
 * - Triggering animations on scroll
 * - Tracking section visibility for analytics
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { useState, useEffect, useRef, RefObject, useCallback } from 'react';

interface UseIntersectionObserverOptions {
  threshold?: number | number[];
  rootMargin?: string;
  triggerOnce?: boolean;
  enabled?: boolean;
}

interface UseIntersectionObserverReturn {
  ref: RefObject<HTMLElement>;
  isIntersecting: boolean;
  hasIntersected: boolean;
  entry: IntersectionObserverEntry | null;
}

/**
 * Hook for observing element intersection with viewport
 * 
 * Usage:
 * ```tsx
 * const { ref, isIntersecting, hasIntersected } = useIntersectionObserver({
 *   threshold: 0.1,
 *   triggerOnce: true,
 * });
 * 
 * return (
 *   <div ref={ref}>
 *     {hasIntersected ? <ActualContent /> : <Skeleton />}
 *   </div>
 * );
 * ```
 */
export function useIntersectionObserver({
  threshold = 0,
  rootMargin = '0px',
  triggerOnce = false,
  enabled = true,
}: UseIntersectionObserverOptions = {}): UseIntersectionObserverReturn {
  const ref = useRef<HTMLElement>(null);
  const [isIntersecting, setIsIntersecting] = useState(false);
  const [hasIntersected, setHasIntersected] = useState(false);
  const [entry, setEntry] = useState<IntersectionObserverEntry | null>(null);

  useEffect(() => {
    if (!enabled || typeof IntersectionObserver === 'undefined') {
      // If not enabled or no IntersectionObserver support, assume visible
      setIsIntersecting(true);
      setHasIntersected(true);
      return;
    }

    const element = ref.current;
    if (!element) return;

    const observer = new IntersectionObserver(
      ([observerEntry]) => {
        setEntry(observerEntry);
        setIsIntersecting(observerEntry.isIntersecting);
        
        if (observerEntry.isIntersecting) {
          setHasIntersected(true);
          
          // Unobserve if triggerOnce
          if (triggerOnce) {
            observer.unobserve(element);
          }
        }
      },
      { threshold, rootMargin }
    );

    observer.observe(element);

    return () => {
      observer.disconnect();
    };
  }, [threshold, rootMargin, triggerOnce, enabled]);

  return { ref: ref as RefObject<HTMLElement>, isIntersecting, hasIntersected, entry };
}

/**
 * Hook for lazy loading content when it enters viewport
 * Specifically designed for mobile performance optimization
 */
export function useLazyLoad({
  rootMargin = '100px', // Load slightly before entering viewport
  enabled = true,
}: {
  rootMargin?: string;
  enabled?: boolean;
} = {}) {
  return useIntersectionObserver({
    threshold: 0,
    rootMargin,
    triggerOnce: true,
    enabled,
  });
}

/**
 * Hook for triggering animations when element is visible
 */
export function useAnimateOnScroll({
  threshold = 0.1,
  enabled = true,
}: {
  threshold?: number;
  enabled?: boolean;
} = {}) {
  const { ref, isIntersecting, hasIntersected } = useIntersectionObserver({
    threshold,
    triggerOnce: true,
    enabled,
  });

  return {
    ref,
    shouldAnimate: hasIntersected,
    isVisible: isIntersecting,
  };
}

/**
 * Hook for tracking section visibility (for analytics)
 */
export function useSectionVisibility(sectionName: string) {
  const { ref, isIntersecting, hasIntersected } = useIntersectionObserver({
    threshold: 0.5, // 50% visible
    triggerOnce: false,
  });

  // Track when section becomes visible
  useEffect(() => {
    if (isIntersecting && typeof window !== 'undefined') {
      // Push to dataLayer for analytics
      (window as any).dataLayer?.push({
        event: 'section_view',
        section_name: sectionName,
        timestamp: Date.now(),
      });
    }
  }, [isIntersecting, sectionName]);

  return { ref, isVisible: isIntersecting, hasBeenSeen: hasIntersected };
}

/**
 * Component wrapper for lazy loading
 */
export function LazySection({
  children,
  fallback,
  rootMargin = '100px',
}: {
  children: React.ReactNode;
  fallback?: React.ReactNode;
  rootMargin?: string;
}) {
  const { ref, hasIntersected } = useLazyLoad({ rootMargin });

  return (
    <div ref={ref as RefObject<HTMLDivElement>}>
      {hasIntersected ? children : fallback}
    </div>
  );
}

export default useIntersectionObserver;
