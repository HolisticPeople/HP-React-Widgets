import { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export interface ScrollNavigationProps {
  sections?: string[];
  className?: string;
  accentColor?: string;
}

interface SectionInfo {
  element: HTMLElement;
  name: string;
  id: string;
}

/**
 * Fixed scroll navigation on the right side of the viewport.
 * Styled to match the reference: https://etemplates.wdesignkit.com/theplusaddons/one-page-scroll-navigation-demo-2/
 * 
 * Only includes direct HP funnel shortcode sections (hero, benefits, offers, testimonials, etc.)
 * Excludes nested elements and sub-components.
 */
export const ScrollNavigation = ({
  sections: providedSections,
  className,
  accentColor,
}: ScrollNavigationProps) => {
  const [sectionInfos, setSectionInfos] = useState<SectionInfo[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [mounted, setMounted] = useState(false);
  const scrollingRef = useRef(false);
  const scrollTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Ensure we're mounted (for portal)
  useEffect(() => {
    setMounted(true);
  }, []);

  // Find all funnel sections - only top-level shortcode sections
  useEffect(() => {
    const findSections = () => {
      if (providedSections && providedSections.length > 0) {
        const infos: SectionInfo[] = [];
        providedSections.forEach((id, index) => {
          const el = document.getElementById(id);
          if (el) {
            infos.push({
              element: el,
              name: el.dataset.sectionName || `Section ${index + 1}`,
              id: el.id || `section-${index}`,
            });
          }
        });
        setSectionInfos(infos);
        return;
      }

      // Auto-detect sections - only include top-level shortcode containers
      // Filter by specific class patterns to avoid nested elements
      const sectionPatterns = [
        '.hp-funnel-hero-section-', // Hero section
        '.hp-funnel-benefits-',     // Benefits
        '.hp-funnel-offers-',       // Offers
        '.hp-funnel-products-',     // Products
        '.hp-funnel-testimonials-', // Testimonials
        '.hp-funnel-faq-',          // FAQ
        '.hp-funnel-cta-',          // CTA sections
        '.hp-funnel-footer-',       // Footer
      ];

      const allSections = document.querySelectorAll<HTMLElement>('.hp-funnel-section');
      const topLevelSections: SectionInfo[] = [];
      const seenTypes = new Set<string>();

      allSections.forEach((section) => {
        // Check if this is a top-level section (not nested in another hp-funnel-section)
        const parent = section.parentElement?.closest('.hp-funnel-section');
        if (parent) return; // Skip nested sections

        // Check if section matches one of our patterns
        const className = section.className;
        let sectionType = '';
        
        for (const pattern of sectionPatterns) {
          if (className.includes(pattern.replace('.', ''))) {
            sectionType = pattern.replace('.hp-funnel-', '').replace('-', '');
            break;
          }
        }

        // Only include if we haven't seen this type yet (avoid duplicates)
        // and section has meaningful height
        if (section.offsetHeight > 100) {
          const name = getSectionName(className, sectionType);
          
          // For sections without a specific type, use generic naming
          if (!sectionType || !seenTypes.has(sectionType)) {
            if (sectionType) seenTypes.add(sectionType);
            
            topLevelSections.push({
              element: section,
              name: section.dataset.sectionName || name,
              id: section.id || `section-${topLevelSections.length}`,
            });
          }
        }
      });

      setSectionInfos(topLevelSections);
    };

    // Initial check with delay to allow sections to render
    const timer = setTimeout(findSections, 800);

    // Also watch for DOM changes
    const observer = new MutationObserver(() => {
      setTimeout(findSections, 200);
    });

    observer.observe(document.body, { childList: true, subtree: true });

    return () => {
      clearTimeout(timer);
      observer.disconnect();
    };
  }, [providedSections]);

  // Track scroll position to update active dot
  useEffect(() => {
    if (sectionInfos.length === 0) return;

    const handleScroll = () => {
      // Don't update during programmatic scrolling
      if (scrollingRef.current) return;

      const scrollY = window.scrollY;
      const viewportHeight = window.innerHeight;
      const scrollCenter = scrollY + viewportHeight * 0.4;
      
      let newActiveIndex = 0;
      
      // Find which section is most visible
      sectionInfos.forEach((info, index) => {
        const rect = info.element.getBoundingClientRect();
        const sectionTop = scrollY + rect.top;
        const sectionBottom = sectionTop + rect.height;
        
        // Section is considered active if scroll center is within it
        if (scrollCenter >= sectionTop && scrollCenter < sectionBottom) {
          newActiveIndex = index;
        } else if (scrollCenter >= sectionBottom) {
          // If we've scrolled past this section, it could be active
          newActiveIndex = Math.min(index + 1, sectionInfos.length - 1);
        }
      });

      setActiveIndex(newActiveIndex);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Initial check

    return () => window.removeEventListener('scroll', handleScroll);
  }, [sectionInfos]);

  const scrollToSection = useCallback((index: number) => {
    if (sectionInfos[index]) {
      scrollingRef.current = true;
      setActiveIndex(index);
      
      sectionInfos[index].element.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });

      // Clear scrolling flag after animation completes
      if (scrollTimeoutRef.current) {
        clearTimeout(scrollTimeoutRef.current);
      }
      scrollTimeoutRef.current = setTimeout(() => {
        scrollingRef.current = false;
      }, 1000);
    }
  }, [sectionInfos]);

  // Cleanup timeout on unmount
  useEffect(() => {
    return () => {
      if (scrollTimeoutRef.current) {
        clearTimeout(scrollTimeoutRef.current);
      }
    };
  }, []);

  // Don't render if not mounted or fewer than 2 sections
  if (!mounted || sectionInfos.length < 2) return null;

  // Use accent color or default to golden yellow like the example
  const activeColor = accentColor || '#D4A853';

  const navContent = (
    <nav
      className={cn(
        'fixed right-6 top-1/2 -translate-y-1/2 z-[9999] flex flex-col items-end gap-4',
        className
      )}
      aria-label="Page sections"
    >
      {sectionInfos.map((info, index) => {
        const isActive = index === activeIndex;

        return (
          <button
            key={info.id}
            onClick={() => scrollToSection(index)}
            className="group flex items-center gap-2 focus:outline-none"
            title={info.name}
            aria-label={`Go to ${info.name}`}
            aria-current={isActive ? 'true' : undefined}
          >
            {/* Section name - appears on hover */}
            <span 
              className={cn(
                'text-xs font-medium uppercase tracking-wider opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap',
                isActive ? 'text-[var(--nav-accent)]' : 'text-gray-400'
              )}
              style={{ '--nav-accent': activeColor } as React.CSSProperties}
            >
              {info.name}
            </span>
            
            {/* Line + dot indicator */}
            <div className="flex items-center gap-1">
              {/* Animated line */}
              <div 
                className={cn(
                  'h-[2px] transition-all duration-300 rounded-full',
                  isActive 
                    ? 'w-8 bg-[var(--nav-accent)]' 
                    : 'w-4 bg-gray-500/50 group-hover:w-6 group-hover:bg-gray-400'
                )}
                style={{ '--nav-accent': activeColor } as React.CSSProperties}
              />
              
              {/* Dot */}
              <div 
                className={cn(
                  'w-2.5 h-2.5 rounded-full transition-all duration-300 border-2',
                  isActive 
                    ? 'bg-[var(--nav-accent)] border-[var(--nav-accent)] shadow-[0_0_10px_var(--nav-accent)]' 
                    : 'bg-transparent border-gray-500/50 group-hover:border-gray-400'
                )}
                style={{ '--nav-accent': activeColor } as React.CSSProperties}
              />
            </div>
          </button>
        );
      })}
    </nav>
  );

  // Use portal to render at body level for proper fixed positioning
  return createPortal(navContent, document.body);
};

/**
 * Get a human-readable name for a section based on its class
 */
function getSectionName(className: string, sectionType: string): string {
  if (className.includes('hero-section')) return 'Home';
  if (className.includes('benefits')) return 'Benefits';
  if (className.includes('offers') || className.includes('products')) return 'Offers';
  if (className.includes('testimonials')) return 'Reviews';
  if (className.includes('faq')) return 'FAQ';
  if (className.includes('cta')) return 'Order';
  if (className.includes('footer')) return 'Contact';
  if (sectionType) return sectionType.charAt(0).toUpperCase() + sectionType.slice(1);
  return 'Section';
}

export default ScrollNavigation;
