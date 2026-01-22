import { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useSmoothScroll } from '@/hooks/use-smooth-scroll';
import { useResponsive } from '@/hooks/use-responsive';
import { NavigationTooltip } from './NavigationTooltip';

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

// Known section type patterns - used for detection and default names
// The data-section-name attribute can override the default name
const KNOWN_SECTION_TYPES = [
  { pattern: 'hero-section', name: 'Home', priority: 1 },
  { pattern: 'benefits', name: 'Benefits', priority: 2 },
  { pattern: 'science', name: 'Science', priority: 3 },
  { pattern: 'infographics', name: 'Comparison', priority: 3.5 }, // Infographics - default name can be overridden by data-section-name
  { pattern: 'features', name: 'Features', priority: 4 },
  { pattern: 'offers', name: 'Offers', priority: 5 },
  { pattern: 'products', name: 'Offers', priority: 5 }, // Products shows as "Offers"
  { pattern: 'authority', name: 'Expert', priority: 6 },
  { pattern: 'testimonials', name: 'Reviews', priority: 7 },
  { pattern: 'faq', name: 'FAQ', priority: 8 },
  { pattern: 'cta', name: 'Order', priority: 9 },
];

const MAX_SECTIONS = 10;

/**
 * Fixed scroll navigation - simple vertical capsule with dots.
 * Tooltip appears on hover.
 */
export const ScrollNavigation = ({
  sections: providedSections,
  accentColor,
}: ScrollNavigationProps) => {
  const [sectionInfos, setSectionInfos] = useState<SectionInfo[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [mounted, setMounted] = useState(false);
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null);
  const scrollingRef = useRef(false);
  const scrollTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  
  // Use smooth scroll hook with PHP settings
  const { scrollTo } = useSmoothScroll();
  const { isMobile, settings } = useResponsive();

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

      const allSections = document.querySelectorAll<HTMLElement>('.hp-funnel-section');
      const foundSections: Array<SectionInfo & { priority: number }> = [];

      allSections.forEach((section) => {
        const parent = section.parentElement?.closest('.hp-funnel-section');
        if (parent) return;
        
        // Infographics sections use lower height threshold (images may still be loading)
        const isInfographics = section.className.includes('infographics');
        const minHeight = isInfographics ? 50 : 200;
        if (section.offsetHeight < minHeight) return;

        const className = section.className;
        const matchedType = KNOWN_SECTION_TYPES.find(type => 
          className.includes(type.pattern)
        );

        if (matchedType) {
          // Use actual name (from data-section-name attribute or default type name)
          const actualName = section.dataset.sectionName || matchedType.name;
          
          // Only deduplicate if same actual name (allows multiple infographics with different names)
          const alreadyHasName = foundSections.some(s => s.name === actualName);
          if (!alreadyHasName) {
            // Get actual vertical position on page for proper ordering
            const rect = section.getBoundingClientRect();
            const topPosition = window.scrollY + rect.top;
            
            foundSections.push({
              element: section,
              name: actualName,
              id: section.id || `section-${foundSections.length}`,
              priority: topPosition, // Use actual page position instead of hardcoded priority
            });
          }
        }
      });

      // Sort by actual vertical position on page
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
      
      // Use custom smooth scroll with easing from PHP settings
      // Use slightly shorter duration on mobile for snappier feel
      const duration = isMobile 
        ? Math.round(settings.scrollDuration * 0.75) 
        : settings.scrollDuration;
      
      scrollTo(sectionInfos[index].element, { duration });

      if (scrollTimeoutRef.current) clearTimeout(scrollTimeoutRef.current);
      scrollTimeoutRef.current = setTimeout(() => {
        scrollingRef.current = false;
      }, duration + 200); // Wait for scroll to complete plus buffer
    }
  }, [sectionInfos, scrollTo, isMobile, settings.scrollDuration]);

  useEffect(() => {
    return () => {
      if (scrollTimeoutRef.current) clearTimeout(scrollTimeoutRef.current);
    };
  }, []);

  // Hide on mobile - the dots take up valuable screen space
  // Users can swipe naturally on mobile
  if (!mounted || sectionInfos.length < 2 || isMobile) return null;

  const activeColor = accentColor || '#D4A853';

  const navContent = (
    <nav
      style={{
        position: 'fixed',
        right: '20px',
        top: '50%',
        transform: 'translateY(-50%)',
        zIndex: 9999,
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: '12px',
        padding: '16px 10px',
        backgroundColor: 'rgba(30, 30, 30, 0.6)',
        backdropFilter: 'blur(8px)',
        WebkitBackdropFilter: 'blur(8px)',
        borderRadius: '24px',
        border: '1px solid rgba(255, 255, 255, 0.1)',
      }}
      aria-label="Page sections"
    >
      {sectionInfos.map((info, index) => {
        const isActive = index === activeIndex;
        const isHovered = hoveredIndex === index;

        return (
          <div
            key={info.id}
            style={{
              position: 'relative',
            }}
          >
            <button
              onClick={() => scrollToSection(index)}
              aria-label={`Go to ${info.name}`}
              aria-current={isActive ? 'true' : undefined}
              style={{
                width: '10px',
                height: '10px',
                borderRadius: '50%',
                border: 'none',
                padding: 0,
                cursor: 'pointer',
                transition: 'all 0.3s ease',
                backgroundColor: isActive ? activeColor : 'rgba(255, 255, 255, 0.3)',
                boxShadow: isActive ? `0 0 8px ${activeColor}` : 'none',
                transform: isActive ? 'scale(1.2)' : 'scale(1)',
              }}
              onMouseEnter={(e) => {
                setHoveredIndex(index);
                if (!isActive) {
                  e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.6)';
                  e.currentTarget.style.transform = 'scale(1.1)';
                }
              }}
              onMouseLeave={(e) => {
                setHoveredIndex(null);
                if (!isActive) {
                  e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
                  e.currentTarget.style.transform = 'scale(1)';
                }
              }}
            />
            <NavigationTooltip
              text={info.name}
              isVisible={isHovered}
              accentColor={activeColor}
            />
          </div>
        );
      })}
    </nav>
  );

  return createPortal(navContent, document.body);
};

export default ScrollNavigation;
