import { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';

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

      const allSections = document.querySelectorAll<HTMLElement>('.hp-funnel-section');
      const foundSections: Array<SectionInfo & { priority: number }> = [];

      // #region agent log
      fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:74',message:'Starting section scan',data:{totalSections:allSections.length},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'H1-H5'})}).catch(()=>{});
      // #endregion

      allSections.forEach((section, idx) => {
        const parent = section.parentElement?.closest('.hp-funnel-section');
        // #region agent log
        if (parent) { fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:76',message:'SKIPPED: has parent',data:{idx,className:section.className,id:section.id},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'H3'})}).catch(()=>{}); return; }
        // #endregion
        if (parent) return;
        // #region agent log
        if (section.offsetHeight < 200) { fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:77',message:'SKIPPED: height too small',data:{idx,className:section.className,height:section.offsetHeight},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'H2'})}).catch(()=>{}); return; }
        // #endregion
        if (section.offsetHeight < 200) return;

        const className = section.className;
        const matchedType = KNOWN_SECTION_TYPES.find(type => 
          className.includes(type.pattern)
        );

        // #region agent log
        fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:84',message:'Section checked',data:{idx,className,matchedPattern:matchedType?.pattern||null,sectionName:section.dataset.sectionName||null,id:section.id},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'H1,H4'})}).catch(()=>{});
        // #endregion

        if (matchedType) {
          // Use actual name (from data-section-name attribute or default type name)
          const actualName = section.dataset.sectionName || matchedType.name;
          
          // Only deduplicate if same actual name (allows multiple infographics with different names)
          const alreadyHasName = foundSections.some(s => s.name === actualName);
          // #region agent log
          fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:89',message:'Dedup check',data:{idx,actualName,alreadyHasName,existingNames:foundSections.map(s=>s.name)},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'H5'})}).catch(()=>{});
          // #endregion
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
            // #region agent log
            fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:101',message:'ADDED section',data:{idx,actualName,id:section.id},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'SUCCESS'})}).catch(()=>{});
            // #endregion
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
      sectionInfos[index].element.scrollIntoView({ behavior: 'smooth', block: 'start' });

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

  if (!mounted || sectionInfos.length < 2) return null;

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

        return (
          <button
            key={info.id}
            onClick={() => scrollToSection(index)}
            title={info.name}
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
              if (!isActive) {
                e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.6)';
                e.currentTarget.style.transform = 'scale(1.1)';
              }
            }}
            onMouseLeave={(e) => {
              if (!isActive) {
                e.currentTarget.style.backgroundColor = 'rgba(255, 255, 255, 0.3)';
                e.currentTarget.style.transform = 'scale(1)';
              }
            }}
          />
        );
      })}
    </nav>
  );

  return createPortal(navContent, document.body);
};

export default ScrollNavigation;
