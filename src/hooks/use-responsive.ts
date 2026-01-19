/**
 * useResponsive - Hook for responsive breakpoint detection
 * 
 * Reads breakpoint settings from PHP via wp_localize_script (hpReactSettings.responsive)
 * and provides reactive breakpoint detection for funnel components.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { useState, useEffect, useMemo } from 'react';

// Type for the responsive settings from PHP
interface ResponsiveSettings {
  breakpoints: {
    tablet: number;  // Mobile ends here
    laptop: number;  // Tablet ends here
    desktop: number; // Laptop ends here
  };
  contentMaxWidth: number;
  enableSmoothScroll: boolean;
  scrollDuration: number;
  scrollEasing: 'ease-out-cubic' | 'ease-out-quad' | 'linear';
  enableScrollSnap: boolean;
}

// Breakpoint type
export type Breakpoint = 'mobile' | 'tablet' | 'laptop' | 'desktop';

// Return type for the hook
export interface ResponsiveState {
  // Current breakpoint name
  breakpoint: Breakpoint;
  // Boolean flags for each breakpoint (current or larger)
  isMobile: boolean;      // true when on mobile
  isTablet: boolean;      // true when tablet or smaller
  isLaptop: boolean;      // true when laptop or smaller
  isDesktop: boolean;     // true when desktop
  // Exact breakpoint match (useful for specific breakpoint logic)
  isExactMobile: boolean;
  isExactTablet: boolean;
  isExactLaptop: boolean;
  isExactDesktop: boolean;
  // Current window width
  width: number;
  // Settings from PHP
  settings: ResponsiveSettings;
}

// Default settings (fallback if PHP settings not loaded)
const DEFAULT_SETTINGS: ResponsiveSettings = {
  breakpoints: {
    tablet: 640,
    laptop: 1024,
    desktop: 1440,
  },
  contentMaxWidth: 1400,
  enableSmoothScroll: true,
  scrollDuration: 800,
  scrollEasing: 'ease-out-cubic',
  enableScrollSnap: false,
};

/**
 * Get responsive settings from the global hpReactSettings object
 * (set by PHP via wp_localize_script)
 */
function getSettings(): ResponsiveSettings {
  const globalSettings = (window as any).hpReactSettings?.responsive;
  
  if (!globalSettings) {
    return DEFAULT_SETTINGS;
  }
  
  return {
    breakpoints: {
      tablet: globalSettings.breakpoints?.tablet ?? DEFAULT_SETTINGS.breakpoints.tablet,
      laptop: globalSettings.breakpoints?.laptop ?? DEFAULT_SETTINGS.breakpoints.laptop,
      desktop: globalSettings.breakpoints?.desktop ?? DEFAULT_SETTINGS.breakpoints.desktop,
    },
    contentMaxWidth: globalSettings.contentMaxWidth ?? DEFAULT_SETTINGS.contentMaxWidth,
    enableSmoothScroll: globalSettings.enableSmoothScroll ?? DEFAULT_SETTINGS.enableSmoothScroll,
    scrollDuration: globalSettings.scrollDuration ?? DEFAULT_SETTINGS.scrollDuration,
    scrollEasing: globalSettings.scrollEasing ?? DEFAULT_SETTINGS.scrollEasing,
    enableScrollSnap: globalSettings.enableScrollSnap ?? DEFAULT_SETTINGS.enableScrollSnap,
  };
}

/**
 * Determine the current breakpoint based on window width
 */
function getBreakpoint(width: number, settings: ResponsiveSettings): Breakpoint {
  if (width < settings.breakpoints.tablet) {
    return 'mobile';
  }
  if (width < settings.breakpoints.laptop) {
    return 'tablet';
  }
  if (width < settings.breakpoints.desktop) {
    return 'laptop';
  }
  return 'desktop';
}

/**
 * Hook for responsive breakpoint detection
 * 
 * Usage:
 * ```tsx
 * const { breakpoint, isMobile, isTablet, settings } = useResponsive();
 * 
 * // Render different layouts based on breakpoint
 * if (isMobile) {
 *   return <MobileLayout />;
 * }
 * ```
 */
export function useResponsive(): ResponsiveState {
  const settings = useMemo(() => getSettings(), []);
  
  const [width, setWidth] = useState<number>(() => {
    if (typeof window !== 'undefined') {
      return window.innerWidth;
    }
    return 1024; // Default to laptop for SSR
  });

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const handleResize = () => {
      setWidth(window.innerWidth);
    };

    // Use ResizeObserver for better performance if available
    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(() => {
        setWidth(window.innerWidth);
      });
      observer.observe(document.documentElement);
      return () => observer.disconnect();
    }

    // Fallback to window resize event
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const breakpoint = useMemo(() => getBreakpoint(width, settings), [width, settings]);

  const result = useMemo(() => ({
    breakpoint,
    // "Is X or smaller" logic
    isMobile: breakpoint === 'mobile',
    isTablet: breakpoint === 'mobile' || breakpoint === 'tablet',
    isLaptop: breakpoint !== 'desktop',
    isDesktop: breakpoint === 'desktop',
    // Exact match logic
    isExactMobile: breakpoint === 'mobile',
    isExactTablet: breakpoint === 'tablet',
    isExactLaptop: breakpoint === 'laptop',
    isExactDesktop: breakpoint === 'desktop',
    width,
    settings,
  }), [breakpoint, width, settings]);
  
  return result;
}

/**
 * Get just the current breakpoint name (lighter than full hook)
 */
export function useBreakpoint(): Breakpoint {
  const { breakpoint } = useResponsive();
  return breakpoint;
}

/**
 * Get responsive settings without reactive width tracking
 * (useful for one-time reads like scroll duration)
 */
export function getResponsiveSettings(): ResponsiveSettings {
  return getSettings();
}

export default useResponsive;
