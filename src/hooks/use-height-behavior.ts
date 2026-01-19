/**
 * useHeightBehavior - Hook for section height management
 * 
 * Provides CSS classes and styles for fit_viewport vs scrollable sections.
 * fit_viewport tries to constrain content to ~80% viewport height.
 * scrollable allows natural content height with scroll.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { useMemo, CSSProperties } from 'react';
import { useResponsive, Breakpoint } from './use-responsive';

export type HeightBehavior = 'fit_viewport' | 'scrollable' | 'auto';

interface HeightBehaviorConfig {
  default: HeightBehavior;
  mobile?: HeightBehavior;
  tablet?: HeightBehavior;
  laptop?: HeightBehavior;
  desktop?: HeightBehavior;
}

interface HeightBehaviorResult {
  // Current effective height behavior
  behavior: HeightBehavior;
  // CSS class to apply (e.g., 'hp-height-fit', 'hp-height-scroll')
  className: string;
  // Inline styles for the section
  style: CSSProperties;
  // Whether this section should try to fit viewport
  isFitViewport: boolean;
  // Whether this section is naturally scrollable
  isScrollable: boolean;
}

/**
 * Get the height behavior for the current breakpoint
 */
function getBehaviorForBreakpoint(
  config: HeightBehaviorConfig,
  breakpoint: Breakpoint
): HeightBehavior {
  // Check for breakpoint-specific override
  const override = config[breakpoint];
  if (override) {
    return override;
  }
  
  // Fall back to default
  return config.default;
}

/**
 * Hook for managing section height behavior
 * 
 * Usage:
 * ```tsx
 * const { className, style, isFitViewport } = useHeightBehavior({
 *   default: 'fit_viewport',
 *   mobile: 'scrollable', // Override for mobile
 * });
 * 
 * return (
 *   <section className={`my-section ${className}`} style={style}>
 *     {content}
 *   </section>
 * );
 * ```
 */
export function useHeightBehavior(
  config: HeightBehaviorConfig | HeightBehavior = 'auto'
): HeightBehaviorResult {
  const { breakpoint } = useResponsive();
  
  // Normalize config
  const normalizedConfig: HeightBehaviorConfig = useMemo(() => {
    if (typeof config === 'string') {
      return { default: config };
    }
    return config;
  }, [config]);
  
  // Get behavior for current breakpoint
  const behavior = useMemo(() => {
    return getBehaviorForBreakpoint(normalizedConfig, breakpoint);
  }, [normalizedConfig, breakpoint]);
  
  // Generate CSS class
  const className = useMemo(() => {
    switch (behavior) {
      case 'fit_viewport':
        return 'hp-height-fit';
      case 'scrollable':
        return 'hp-height-scroll';
      default:
        return 'hp-height-auto';
    }
  }, [behavior]);
  
  // Generate inline styles
  const style = useMemo((): CSSProperties => {
    switch (behavior) {
      case 'fit_viewport':
        return {
          minHeight: '80vh',
          maxHeight: '100vh',
          display: 'flex',
          flexDirection: 'column',
          justifyContent: 'center',
          overflow: 'hidden',
        };
      case 'scrollable':
        return {
          minHeight: 'auto',
          maxHeight: 'none',
          overflow: 'visible',
        };
      default:
        return {};
    }
  }, [behavior]);
  
  return {
    behavior,
    className,
    style,
    isFitViewport: behavior === 'fit_viewport',
    isScrollable: behavior === 'scrollable',
  };
}

/**
 * CSS for height behavior classes (to be injected globally)
 */
export const HEIGHT_BEHAVIOR_CSS = `
/* Height Behavior Classes */
.hp-height-fit {
  min-height: 80vh;
  max-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
}

.hp-height-scroll {
  min-height: auto;
  max-height: none;
  overflow: visible;
}

.hp-height-auto {
  /* Natural height - no constraints */
}

/* Fit viewport with scroll fallback for overflow content */
.hp-height-fit-with-scroll {
  min-height: 80vh;
  max-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}

/* Content container within fit sections */
.hp-height-fit .hp-section-content,
.hp-height-fit-with-scroll .hp-section-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
`;

export default useHeightBehavior;
