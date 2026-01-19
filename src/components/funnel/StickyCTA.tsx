/**
 * StickyCTA - Global sticky CTA button for mobile
 * 
 * Displays a fixed CTA button at the bottom of the screen on mobile.
 * Automatically hides other section CTAs to avoid redundancy.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @author Amnon Manneberg
 */

import { useEffect, useState, CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import { useResponsive } from '@/hooks/use-responsive';
import { smoothScrollTo } from '@/hooks/use-smooth-scroll';

export interface StickyCTAProps {
  text: string;
  target?: 'scroll_to_offers' | 'scroll_to_checkout' | 'custom';
  customTarget?: string; // CSS selector for custom target
  backgroundColor?: string;
  textColor?: string;
  accentColor?: string;
  className?: string;
  hideAfterScroll?: number; // Hide after scrolling past this section index
}

/**
 * Sticky CTA button that appears on mobile at the bottom of the screen.
 * 
 * Usage:
 * ```tsx
 * <StickyCTA 
 *   text="Get Your Kit Now" 
 *   target="scroll_to_offers"
 *   backgroundColor="#D4A853"
 *   textColor="#1a1a1a"
 * />
 * ```
 */
export const StickyCTA = ({
  text,
  target = 'scroll_to_offers',
  customTarget,
  backgroundColor = '#D4A853',
  textColor = '#1a1a1a',
  accentColor,
  className = '',
}: StickyCTAProps) => {
  const { isMobile } = useResponsive();
  const [mounted, setMounted] = useState(false);
  const [isVisible, setIsVisible] = useState(true);

  useEffect(() => {
    setMounted(true);
    
    // Hide section CTAs when sticky CTA is active on mobile
    if (isMobile) {
      const style = document.createElement('style');
      style.id = 'hp-sticky-cta-hide-section-ctas';
      style.textContent = `
        @media (max-width: 639px) {
          .hp-funnel-section .hp-section-cta,
          .hp-funnel-section [data-cta-button="true"] {
            display: none !important;
          }
        }
      `;
      document.head.appendChild(style);
      
      return () => {
        const existingStyle = document.getElementById('hp-sticky-cta-hide-section-ctas');
        if (existingStyle) {
          existingStyle.remove();
        }
      };
    }
  }, [isMobile]);

  // Track scroll position to hide when in checkout/thank you area
  useEffect(() => {
    if (!isMobile) return;

    const handleScroll = () => {
      // Hide if we're near the bottom (likely in checkout section)
      const scrollPercent = window.scrollY / (document.body.scrollHeight - window.innerHeight);
      setIsVisible(scrollPercent < 0.85);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, [isMobile]);

  const handleClick = () => {
    let targetElement: HTMLElement | null = null;

    switch (target) {
      case 'scroll_to_offers':
        // Find products/offers section
        targetElement = document.querySelector('.hp-funnel-section.hp-funnel-products') as HTMLElement
          || document.querySelector('[data-section-type="products"]') as HTMLElement
          || document.querySelector('[data-section-type="offers"]') as HTMLElement;
        break;
      case 'scroll_to_checkout':
        // Find checkout section or navigate
        targetElement = document.querySelector('.hp-funnel-checkout') as HTMLElement
          || document.querySelector('[data-section-type="checkout"]') as HTMLElement;
        break;
      case 'custom':
        if (customTarget) {
          targetElement = document.querySelector(customTarget) as HTMLElement;
        }
        break;
    }

    if (targetElement) {
      // Use smooth scroll with offset for the sticky header
      smoothScrollTo(targetElement, { offset: -20 });
    }
  };

  // Only show on mobile
  if (!mounted || !isMobile || !isVisible) {
    return null;
  }

  const buttonStyle: CSSProperties = {
    backgroundColor: accentColor || backgroundColor,
    color: textColor,
  };

  const content = (
    <div
      className={`hp-sticky-cta-container ${className}`}
      style={{
        position: 'fixed',
        bottom: 0,
        left: 0,
        right: 0,
        zIndex: 9998,
        padding: '12px 16px',
        paddingBottom: 'calc(12px + env(safe-area-inset-bottom, 0px))',
        background: 'linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 70%, transparent 100%)',
      }}
    >
      <button
        onClick={handleClick}
        className="hp-sticky-cta-button"
        style={{
          ...buttonStyle,
          width: '100%',
          padding: '16px 24px',
          fontSize: '16px',
          fontWeight: 600,
          border: 'none',
          borderRadius: '8px',
          cursor: 'pointer',
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
          boxShadow: '0 4px 12px rgba(0,0,0,0.3)',
          transition: 'transform 0.2s ease, box-shadow 0.2s ease',
        }}
        onTouchStart={(e) => {
          (e.currentTarget as HTMLElement).style.transform = 'scale(0.98)';
        }}
        onTouchEnd={(e) => {
          (e.currentTarget as HTMLElement).style.transform = 'scale(1)';
        }}
      >
        {text}
      </button>
    </div>
  );

  return createPortal(content, document.body);
};

export default StickyCTA;
