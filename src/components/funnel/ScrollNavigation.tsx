import { useState, useEffect, useCallback } from 'react';
import { cn } from '@/lib/utils';

export interface ScrollNavigationProps {
  sections?: string[];
  className?: string;
}

/**
 * Fixed scroll navigation dots on the right side of the viewport.
 * Automatically detects sections with hp-funnel-section class or uses provided section IDs.
 */
export const ScrollNavigation = ({
  sections: providedSections,
  className,
}: ScrollNavigationProps) => {
  const [sectionElements, setSectionElements] = useState<HTMLElement[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);

  // Find all funnel sections on mount
  useEffect(() => {
    if (providedSections && providedSections.length > 0) {
      // Use provided section IDs
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

  // Don't render if fewer than 2 sections
  if (sectionElements.length < 2) return null;

  return (
    <nav
      className={cn(
        'fixed right-4 top-1/2 -translate-y-1/2 z-40 flex flex-col gap-3',
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
};

export default ScrollNavigation;
