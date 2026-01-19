/**
 * StickyCTA - Global sticky CTA button for mobile
 * 
 * Displays a fixed CTA button at the bottom of the screen on mobile.
 * Automatically hides other section CTAs to avoid redundancy.
 * 
 * @package HP-React-Widgets
 * @since 2.32.0
 * @version 2.32.9 - Finalized position (bottom: 80), removed debug logs
 * @author Amnon Manneberg
 */

import { useEffect, useState, useCallback, CSSProperties } from 'react';
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
    // IMPORTANT: Exclude products/offers section - those CTAs must remain visible!
    if (isMobile) {
      const style = document.createElement('style');
      style.id = 'hp-sticky-cta-hide-section-ctas';
      // Hide CTAs only in non-product sections (Hero, Authority, Science, Benefits, Testimonials)
      style.textContent = `
        @media (max-width: 767px) {
          /* Hide CTA buttons in specific sections, but NOT in products/offers */
          .hp-funnel-hero-section .hp-funnel-cta-btn,
          .hp-funnel-hero-section button:not(.hp-sticky-cta-button),
          .hp-funnel-authority .hp-funnel-cta-btn,
          .hp-funnel-authority button:not(.hp-sticky-cta-button),
          .hp-funnel-science .hp-funnel-cta-btn,
          .hp-funnel-science button:not(.hp-sticky-cta-button),
          .hp-funnel-benefits .hp-funnel-cta-btn,
          .hp-funnel-benefits button:not(.hp-sticky-cta-button),
          .hp-funnel-testimonials .hp-funnel-cta-btn,
          .hp-funnel-testimonials button:not(.hp-sticky-cta-button) {
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

  // Check if element is in viewport
  const isElementInViewport = useCallback((el: Element | null): boolean => {
    if (!el) return false;
    const rect = el.getBoundingClientRect();
    // Consider element visible if at least 30% is in viewport
    const viewportHeight = window.innerHeight;
    const elementTop = rect.top;
    const elementBottom = rect.bottom;
    const visibleTop = Math.max(0, elementTop);
    const visibleBottom = Math.min(viewportHeight, elementBottom);
    const visibleHeight = Math.max(0, visibleBottom - visibleTop);
    return visibleHeight > rect.height * 0.3;
  }, []);

  // Track when in offers/products section - hide there since that's where they can buy
  useEffect(() => {
    if (!isMobile) return;

    const checkVisibility = () => {
      // Find the offers/products section
      const offersSection = document.querySelector('.hp-funnel-products') 
        || document.querySelector('[data-section-type="products"]')
        || document.querySelector('[data-section-type="offers"]');
      
      // Also check for checkout section
      const checkoutSection = document.querySelector('.hp-funnel-checkout')
        || document.querySelector('[data-section-type="checkout"]');
      
      // Hide if user is viewing the offers or checkout section
      const inOffersSection = isElementInViewport(offersSection);
      const inCheckoutSection = isElementInViewport(checkoutSection);
      const shouldHide = inOffersSection || inCheckoutSection;
      setIsVisible(!shouldHide);
    };

    // Check immediately
    checkVisibility();
    
    // Check on scroll
    window.addEventListener('scroll', checkVisibility, { passive: true });
    return () => window.removeEventListener('scroll', checkVisibility);
  }, [isMobile, isElementInViewport]);

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
        // Position above the mobile sticky footer
        bottom: 80,
        left: 0,
        right: 0,
        zIndex: 9998,
        padding: '8px 16px',
        // Flex container to center the capsule button
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        // No gradient background - let the button stand on its own
        background: 'transparent',
      }}
    >
      <button
        onClick={handleClick}
        className="hp-sticky-cta-button"
        style={{
          ...buttonStyle,
          // Capsule shape: auto width, large horizontal padding, fully rounded
          padding: '14px 48px',
          fontSize: '15px',
          fontWeight: 700,
          border: 'none',
          borderRadius: '9999px', // Fully rounded capsule
          cursor: 'pointer',
          textTransform: 'uppercase',
          letterSpacing: '0.5px',
          boxShadow: '0 4px 16px rgba(212, 168, 83, 0.4)',
          transition: 'transform 0.2s ease, box-shadow 0.2s ease',
          whiteSpace: 'nowrap',
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
