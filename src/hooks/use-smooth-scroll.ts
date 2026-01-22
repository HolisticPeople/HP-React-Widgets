/**
 * useSmoothScroll - Hook for smooth scrolling between sections
 * 
 * Provides easing-based smooth scroll with configurable duration and easing curves.
 * Reads settings from PHP via hpReactSettings.responsive.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { useCallback, useRef } from 'react';
import { getResponsiveSettings } from './use-responsive';

// Easing functions
const easingFunctions = {
  'ease-out-cubic': (t: number): number => 1 - Math.pow(1 - t, 3),
  'ease-out-quad': (t: number): number => 1 - Math.pow(1 - t, 2),
  'linear': (t: number): number => t,
};

type EasingType = keyof typeof easingFunctions;

interface ScrollOptions {
  duration?: number;
  easing?: EasingType;
  offset?: number; // Additional offset from target (e.g., for sticky header)
}

interface SmoothScrollReturn {
  scrollTo: (target: HTMLElement | string | number, options?: ScrollOptions) => void;
  scrollToSection: (sectionIndex: number, options?: ScrollOptions) => void;
  cancelScroll: () => void;
  isScrolling: boolean;
}

/**
 * Hook for smooth scrolling with easing
 * 
 * Usage:
 * ```tsx
 * const { scrollTo, scrollToSection } = useSmoothScroll();
 * 
 * // Scroll to element
 * scrollTo('#my-section');
 * scrollTo(document.getElementById('my-section'));
 * 
 * // Scroll to section by index
 * scrollToSection(2);
 * 
 * // Custom options
 * scrollTo('#target', { duration: 1000, easing: 'ease-out-quad', offset: -100 });
 * ```
 */
export function useSmoothScroll(): SmoothScrollReturn {
  const animationRef = useRef<number | null>(null);
  const isScrollingRef = useRef(false);

  const cancelScroll = useCallback(() => {
    if (animationRef.current !== null) {
      cancelAnimationFrame(animationRef.current);
      animationRef.current = null;
    }
    isScrollingRef.current = false;
  }, []);

  const scrollTo = useCallback((
    target: HTMLElement | string | number,
    options: ScrollOptions = {}
  ) => {
    // Get settings from PHP
    const settings = getResponsiveSettings();
    
    // Check if smooth scroll is enabled
    if (!settings.enableSmoothScroll) {
      // Fall back to native scroll
      if (typeof target === 'number') {
        window.scrollTo(0, target);
      } else {
        const element = typeof target === 'string' 
          ? document.querySelector(target) as HTMLElement
          : target;
        if (element) {
          element.scrollIntoView({ behavior: 'auto' });
        }
      }
      return;
    }

    // Respect reduced motion preference
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      if (typeof target === 'number') {
        window.scrollTo(0, target);
      } else {
        const element = typeof target === 'string' 
          ? document.querySelector(target) as HTMLElement
          : target;
        if (element) {
          element.scrollIntoView({ behavior: 'auto' });
        }
      }
      return;
    }

    // Cancel any existing animation
    cancelScroll();

    // Resolve target to Y position
    let targetY: number;
    if (typeof target === 'number') {
      targetY = target;
    } else {
      const element = typeof target === 'string' 
        ? document.querySelector(target) as HTMLElement
        : target;
      if (!element) {
        console.warn('[useSmoothScroll] Target element not found:', target);
        return;
      }
      const rect = element.getBoundingClientRect();
      targetY = rect.top + window.scrollY;
    }

    // Apply offset
    const offset = options.offset ?? 0;
    targetY += offset;

    // Get scroll settings
    const duration = options.duration ?? settings.scrollDuration;
    const easingName = options.easing ?? settings.scrollEasing;
    const easingFn = easingFunctions[easingName] || easingFunctions['ease-out-cubic'];

    const startY = window.scrollY;
    const distance = targetY - startY;
    let startTime: number | null = null;

    isScrollingRef.current = true;

    const step = (currentTime: number) => {
      if (!startTime) startTime = currentTime;
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = easingFn(progress);

      window.scrollTo(0, startY + distance * eased);

      if (progress < 1) {
        animationRef.current = requestAnimationFrame(step);
      } else {
        isScrollingRef.current = false;
        animationRef.current = null;
      }
    };

    animationRef.current = requestAnimationFrame(step);
  }, [cancelScroll]);

  const scrollToSection = useCallback((
    sectionIndex: number,
    options: ScrollOptions = {}
  ) => {
    // Find all funnel sections
    const sections = document.querySelectorAll('.hp-funnel-section');
    const section = sections[sectionIndex] as HTMLElement;
    
    if (section) {
      scrollTo(section, options);
    } else {
      console.warn('[useSmoothScroll] Section not found at index:', sectionIndex);
    }
  }, [scrollTo]);

  return {
    scrollTo,
    scrollToSection,
    cancelScroll,
    get isScrolling() {
      return isScrollingRef.current;
    },
  };
}

/**
 * Utility function for one-off smooth scroll (no hook needed)
 */
export function smoothScrollTo(
  target: HTMLElement | string | number,
  options: ScrollOptions = {}
): void {
  const settings = getResponsiveSettings();
  
  // Check if smooth scroll is enabled
  if (!settings.enableSmoothScroll || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    if (typeof target === 'number') {
      window.scrollTo(0, target);
    } else {
      const element = typeof target === 'string' 
        ? document.querySelector(target) as HTMLElement
        : target;
      if (element) {
        element.scrollIntoView({ behavior: 'auto' });
      }
    }
    return;
  }

  // Resolve target
  let targetY: number;
  if (typeof target === 'number') {
    targetY = target;
  } else {
    const element = typeof target === 'string' 
      ? document.querySelector(target) as HTMLElement
      : target;
    if (!element) return;
    targetY = element.getBoundingClientRect().top + window.scrollY;
  }

  targetY += options.offset ?? 0;

  const duration = options.duration ?? settings.scrollDuration;
  const easingFn = easingFunctions[options.easing ?? settings.scrollEasing] || easingFunctions['ease-out-cubic'];

  const startY = window.scrollY;
  const distance = targetY - startY;
  let startTime: number | null = null;

  const step = (currentTime: number) => {
    if (!startTime) startTime = currentTime;
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    window.scrollTo(0, startY + distance * easingFn(progress));
    if (progress < 1) requestAnimationFrame(step);
  };

  requestAnimationFrame(step);
}

export default useSmoothScroll;
