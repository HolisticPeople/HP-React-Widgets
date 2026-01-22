import { useState, useEffect, CSSProperties } from 'react';

export interface NavigationTooltipProps {
  text: string;
  isVisible: boolean;
  accentColor?: string;
}

/**
 * Custom tooltip component for scroll navigation
 * Appears to the left of the navigation dots with a larger, more prominent design
 */
export const NavigationTooltip = ({ text, isVisible, accentColor = '#D4A853' }: NavigationTooltipProps) => {
  const [shouldRender, setShouldRender] = useState(false);

  useEffect(() => {
    if (isVisible) {
      setShouldRender(true);
    } else {
      // Delay unmount to allow fade-out animation
      const timer = setTimeout(() => setShouldRender(false), 300);
      return () => clearTimeout(timer);
    }
  }, [isVisible]);

  if (!shouldRender) return null;

  const tooltipStyle: CSSProperties = {
    position: 'absolute',
    right: '100%',
    top: '50%',
    transform: `translateY(-50%) translateX(-12px)`,
    marginRight: '0',
    padding: '8px 16px',
    backgroundColor: 'rgba(30, 30, 30, 0.95)',
    backdropFilter: 'blur(12px)',
    WebkitBackdropFilter: 'blur(12px)',
    borderRadius: '8px',
    border: `1px solid ${accentColor}`,
    boxShadow: `0 4px 12px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1)`,
    whiteSpace: 'nowrap',
    fontSize: '14px',
    fontWeight: '600',
    color: '#ffffff',
    letterSpacing: '0.3px',
    pointerEvents: 'none',
    opacity: isVisible ? 1 : 0,
    transition: 'opacity 0.2s ease, transform 0.2s ease',
    zIndex: 10000,
  };

  // Arrow pointing to the right (towards the dot)
  const arrowStyle: CSSProperties = {
    position: 'absolute',
    right: '-6px',
    top: '50%',
    transform: 'translateY(-50%)',
    width: 0,
    height: 0,
    borderLeft: `6px solid ${accentColor}`,
    borderTop: '6px solid transparent',
    borderBottom: '6px solid transparent',
  };

  return (
    <div style={tooltipStyle}>
      {text}
      <div style={arrowStyle} />
    </div>
  );
};

export default NavigationTooltip;
