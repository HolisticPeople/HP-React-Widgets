import { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export interface ScrollNavigationProps {
  sections?: string[];
  className?: string;
}

/**
 * Fixed scroll navigation dots on the right side of the viewport.
 * Automatically detects sections with hp-funnel-section class or uses provided section IDs.
 * Uses portal to render at body level for proper fixed positioning.
 */
export const ScrollNavigation = ({
  sections: providedSections,
  className,
}: ScrollNavigationProps) => {
  const [sectionElements, setSectionElements] = useState<HTMLElement[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const [mounted, setMounted] = useState(false);

  // Ensure we're mounted (for portal)
  useEffect(() => {
    setMounted(true);
    // #region agent log
    fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:24',message:'ScrollNavigation mounted',data:{providedSections},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'B,E'})}).catch(()=>{});
    // #endregion
  }, []);

  // Find all funnel sections - with delay to allow other components to render
  useEffect(() => {
    const findSections = () => {
      if (providedSections && providedSections.length > 0) {
        const elements: HTMLElement[] = [];
        providedSections.forEach(id => {
          const el = document.getElementById(id);
          if (el) elements.push(el);
        });
        setSectionElements(elements);
      } else {
        // Auto-detect sections with hp-funnel-section class
        const elements = Array.from(
          document.querySelectorAll<HTMLElement>('.hp-funnel-section')
        );
        setSectionElements(elements);
      }
    };

    // Initial check with delay to allow other sections to render
    const timer = setTimeout(() => {
      findSections();
      // #region agent log
      const allSections = document.querySelectorAll('.hp-funnel-section');
      fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:52',message:'findSections after delay',data:{sectionsFound:allSections.length,sectionClasses:Array.from(allSections).map(s=>s.className)},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'C,D'})}).catch(()=>{});
      // #endregion
    }, 500);

    // Also watch for DOM changes in case sections are added dynamically
    const observer = new MutationObserver(() => {
      findSections();
    });

    observer.observe(document.body, { childList: true, subtree: true });

    return () => {
      clearTimeout(timer);
      observer.disconnect();
    };
  }, [providedSections]);

  // Track scroll position to update active dot
  useEffect(() => {
    if (sectionElements.length === 0) return;

    const handleScroll = () => {
      const scrollY = window.scrollY + window.innerHeight / 3;
      
      let newActiveIndex = 0;
      sectionElements.forEach((section, index) => {
        const sectionTop = section.offsetTop;
        if (scrollY >= sectionTop) {
          newActiveIndex = index;
        }
      });

      setActiveIndex(newActiveIndex);
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Initial check

    return () => window.removeEventListener('scroll', handleScroll);
  }, [sectionElements]);

  const scrollToSection = useCallback((index: number) => {
    if (sectionElements[index]) {
      sectionElements[index].scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });
    }
  }, [sectionElements]);

  // Don't render if not mounted or fewer than 2 sections
  // #region agent log
  fetch('http://127.0.0.1:7242/ingest/03214d4a-d710-4ff7-ac74-904564aaa2c7',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({location:'ScrollNavigation.tsx:95',message:'ScrollNav render decision',data:{mounted,sectionElementsLength:sectionElements.length,willRender:mounted&&sectionElements.length>=2},timestamp:Date.now(),sessionId:'debug-session',hypothesisId:'C,E'})}).catch(()=>{});
  // #endregion
  if (!mounted || sectionElements.length < 2) return null;

  const navContent = (
    <nav
      className={cn(
        'fixed right-4 top-1/2 -translate-y-1/2 z-[9999] flex flex-col gap-3',
        className
      )}
      aria-label="Page sections"
    >
      {sectionElements.map((section, index) => {
        const isActive = index === activeIndex;
        const sectionName = section.dataset.sectionName || 
                           section.id || 
                           `Section ${index + 1}`;

        return (
          <button
            key={index}
            onClick={() => scrollToSection(index)}
            className={cn(
              'w-3 h-3 rounded-full transition-all duration-300 hover:scale-125 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 focus:ring-offset-background',
              isActive
                ? 'bg-accent shadow-[0_0_10px_hsl(var(--accent)/0.5)] scale-110'
                : 'bg-border/50 hover:bg-border'
            )}
            title={sectionName}
            aria-label={`Go to ${sectionName}`}
            aria-current={isActive ? 'true' : undefined}
          />
        );
      })}
    </nav>
  );

  // Use portal to render at body level for proper fixed positioning
  return createPortal(navContent, document.body);
};

export default ScrollNavigation;
