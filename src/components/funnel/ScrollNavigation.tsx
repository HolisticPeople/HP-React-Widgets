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

// Known section type patterns - only these will be included
const KNOWN_SECTION_TYPES = [
  { pattern: 'hero-section', name: 'Home', priority: 1 },
  { pattern: 'benefits', name: 'Benefits', priority: 2 },
  { pattern: 'offers', name: 'Offers', priority: 3 },
  { pattern: 'products', name: 'Products', priority: 3 },
  { pattern: 'testimonials', name: 'Reviews', priority: 4 },
  { pattern: 'faq', name: 'FAQ', priority: 5 },
  { pattern: 'cta', name: 'Order', priority: 6 },
];

// Maximum number of dots to show
const MAX_SECTIONS = 6;

/**
 * Fixed scroll navigation on the right side of the viewport.
 * Styled to match: https://etemplates.wdesignkit.com/theplusaddons/one-page-scroll-navigation-demo-2/
 * 
 * Only includes recognized HP funnel section types (hero, benefits, offers, testimonials, etc.)
 * Maximum of 6 sections to keep the navigation clean.
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

  useEffect(() => {
    setMounted(true);
  }, []);

  // Find funnel sections - strictly filtered
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
        setSectionInfos(infos.slice(0, MAX_SECTIONS));
        return;
      }

      // Auto-detect - ONLY include recognized section types
      const allSections = document.querySelectorAll<HTMLElement>('.hp-funnel-section');
      const foundSections: Array<SectionInfo & { priority: number }> = [];

      allSections.forEach((section) => {
        // Skip nested sections
        const parent = section.parentElement?.closest('.hp-funnel-section');
        if (parent) return;

        // Skip small sections (less than 200px height)
        if (section.offsetHeight < 200) return;

        const className = section.className;
        
        // Find matching known type
        const matchedType = KNOWN_SECTION_TYPES.find(type => 
          className.includes(type.pattern)
        );

        if (matchedType) {
          // Check if we already have this type (avoid duplicates)
          const alreadyHasType = foundSections.some(s => 
            s.name === matchedType.name
          );
          
          if (!alreadyHasType) {
            foundSections.push({
              element: section,
              name: section.dataset.sectionName || matchedType.name,
              id: section.id || `section-${foundSections.length}`,
              priority: matchedType.priority,
            });
          }
        }
      });

      // Sort by priority and limit
      foundSections.sort((a, b) => a.priority - b.priority);
      setSectionInfos(foundSections.slice(0, MAX_SECTIONS));
    };

    const timer = setTimeout(findSections, 1000);

    return () => clearTimeout(timer);
  }, [providedSections]);

  // Track scroll position
  useEffect(() => {
    if (sectionInfos.length === 0) return;

    const handleScroll = () => {
      if (scrollingRef.current) return;

      const scrollY = window.scrollY;
      const viewportCenter = scrollY + window.innerHeight * 0.4;
      
      let newActiveIndex = 0;
      
      for (let i = 0; i < sectionInfos.length; i++) {
        const rect = sectionInfos[i].element.getBoundingClientRect();
        const sectionTop = scrollY + rect.top;
        const sectionBottom = sectionTop + rect.height;
        
        if (viewportCenter >= sectionTop && viewportCenter < sectionBottom) {
          newActiveIndex = i;
          break;
        } else if (viewportCenter >= sectionBottom) {
          newActiveIndex = i;
        }
      }

      setActiveIndex(newActiveIndex);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();

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

      if (scrollTimeoutRef.current) clearTimeout(scrollTimeoutRef.current);
      scrollTimeoutRef.current = setTimeout(() => {
        scrollingRef.current = false;
      }, 1000);
    }
  }, [sectionInfos]);

  useEffect(() => {
    return () => {
      if (scrollTimeoutRef.current) clearTimeout(scrollTimeoutRef.current);
    };
  }, []);

  // Don't render if not mounted or fewer than 2 sections
  if (!mounted || sectionInfos.length < 2) return null;

  // Golden accent color matching reference
  const activeColor = accentColor || '#D4A853';

  const navContent = (
    <nav
      className={cn(
        'fixed right-8 top-1/2 -translate-y-1/2 z-[9999] flex flex-col items-end gap-6',
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
            className="group flex items-center gap-3 focus:outline-none transition-all duration-300"
            title={info.name}
            aria-label={`Go to ${info.name}`}
            aria-current={isActive ? 'true' : undefined}
          >
            {/* Section name - visible on hover */}
            <span 
              className={cn(
                'text-[11px] font-medium uppercase tracking-[0.15em] opacity-0 group-hover:opacity-100 transition-all duration-300 whitespace-nowrap pr-2',
                isActive ? 'opacity-100' : ''
              )}
              style={{ color: isActive ? activeColor : '#9CA3AF' }}
            >
              {info.name}
            </span>
            
            {/* Line indicator */}
            <div 
              className="transition-all duration-300 rounded-full"
              style={{
                width: isActive ? '32px' : '16px',
                height: '2px',
                backgroundColor: isActive ? activeColor : 'rgba(156, 163, 175, 0.4)',
              }}
            />
            
            {/* Dot */}
            <div 
              className="transition-all duration-300 rounded-full"
              style={{
                width: '10px',
                height: '10px',
                backgroundColor: isActive ? activeColor : 'transparent',
                border: `2px solid ${isActive ? activeColor : 'rgba(156, 163, 175, 0.5)'}`,
                boxShadow: isActive ? `0 0 12px ${activeColor}` : 'none',
              }}
            />
          </button>
        );
      })}
    </nav>
  );

  return createPortal(navContent, document.body);
};

export default ScrollNavigation;
