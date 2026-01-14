import { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { cn } from '@/lib/utils';
import { HpCloseIcon, HpChevronDownIcon } from '@/components/icons/HpMenuIcons';
import type { MenuItem, MenuSection, HpMenuProps } from '@/types/menu';

/**
 * Menu Item Component - renders a single menu item with optional image and children
 */
interface MenuItemComponentProps {
  item: MenuItem;
  index: number;
}

function MenuItemComponent({ item, index }: MenuItemComponentProps) {
  const [isExpanded, setIsExpanded] = useState(false);
  const hasChildren = item.children && item.children.length > 0;

  const handleClick = () => {
    if (hasChildren) {
      setIsExpanded(!isExpanded);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.04, duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
    >
      <div
        className={cn(
          'hp-menu-glass-item group cursor-pointer',
          isExpanded && '!bg-secondary/70'
        )}
        onClick={handleClick}
      >
        <div className="flex items-stretch" style={{ minHeight: '64px' }}>
          {/* Side image - square, full height */}
          {item.image && (
            <div 
              className="relative flex-shrink-0 overflow-hidden"
              style={{ width: '64px', height: '64px' }}
            >
              <img
                src={item.image}
                alt=""
                className="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all"
                style={{ transitionDuration: '500ms' }}
              />
              <div 
                className="absolute inset-0"
                style={{ background: 'linear-gradient(to right, transparent, hsl(var(--secondary) / 0.8))' }}
              />
            </div>
          )}

          {/* Content */}
          <div
            className={cn(
              'flex-1 flex items-center justify-between',
              item.image ? 'px-4' : 'pl-5 pr-4'
            )}
            style={{ paddingTop: '1rem', paddingBottom: '1rem' }}
          >
            {item.href && !hasChildren ? (
              <a 
                href={item.href}
                className="hp-menu-label font-display text-lg text-foreground/90 group-hover:text-foreground transition-colors"
                style={{ textDecoration: 'none' }}
              >
                {item.label}
              </a>
            ) : (
              <span className="hp-menu-label font-display text-lg text-foreground/90 group-hover:text-foreground transition-colors">
                {item.label}
              </span>
            )}

            {hasChildren && (
              <motion.div
                animate={{ rotate: isExpanded ? 180 : 0 }}
                transition={{ duration: 0.2 }}
                className="flex-shrink-0"
              >
                <HpChevronDownIcon className="text-muted-foreground group-hover:text-primary transition-colors" />
              </motion.div>
            )}
          </div>
        </div>
      </div>

      {/* Children subcategories */}
      <AnimatePresence>
        {isExpanded && hasChildren && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
            className="overflow-hidden"
          >
            <div style={{ paddingLeft: '2rem', paddingRight: '0.5rem', paddingTop: '0.5rem', paddingBottom: '0.5rem' }}>
              {item.children?.map((child, childIndex) => (
                <motion.a
                  key={child.href}
                  href={child.href}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: childIndex * 0.05 }}
                  className="block rounded-lg text-sm text-muted-foreground hover:text-foreground hover:bg-secondary/40 transition-all"
                  style={{ 
                    padding: '0.625rem 1rem',
                    transitionDuration: '200ms',
                    textDecoration: 'none'
                  }}
                >
                  {child.label}
                </motion.a>
              ))}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </motion.div>
  );
}

/**
 * Menu Trigger - Animated hamburger button
 */
interface MenuTriggerProps {
  isOpen: boolean;
  onClick: () => void;
  className?: string;
}

function MenuTrigger({ isOpen, onClick, className }: MenuTriggerProps) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'hp-menu-trigger relative flex items-center justify-center rounded-full',
        'hover:bg-secondary/80 hover:border-border/50 transition-all',
        className
      )}
      style={{
        width: '44px',
        height: '44px',
        backgroundColor: 'hsl(var(--secondary) / 0.6)',
        backdropFilter: 'blur(12px)',
        WebkitBackdropFilter: 'blur(12px)',
        border: '1px solid hsl(var(--border) / 0.3)',
        boxShadow: 'var(--shadow-soft)',
        cursor: 'pointer',
        transitionDuration: '300ms'
      }}
      aria-label={isOpen ? 'Close menu' : 'Open menu'}
      aria-expanded={isOpen}
    >
      <div 
        className="relative flex flex-col justify-between"
        style={{ width: '20px', height: '14px' }}
      >
        <motion.span
          style={{
            display: 'block',
            height: '1.5px',
            backgroundColor: 'hsl(var(--foreground) / 0.8)',
            borderRadius: '9999px',
            transformOrigin: 'left center'
          }}
          animate={{
            rotate: isOpen ? 45 : 0,
            y: isOpen ? -1 : 0,
            width: '100%',
          }}
          transition={{ duration: 0.2 }}
        />
        <motion.span
          style={{
            display: 'block',
            height: '1.5px',
            backgroundColor: 'hsl(var(--foreground) / 0.8)',
            borderRadius: '9999px',
          }}
          animate={{
            opacity: isOpen ? 0 : 1,
            x: isOpen ? 10 : 0,
          }}
          transition={{ duration: 0.15 }}
        />
        <motion.span
          style={{
            display: 'block',
            height: '1.5px',
            backgroundColor: 'hsl(var(--foreground) / 0.8)',
            borderRadius: '9999px',
            transformOrigin: 'left center',
            width: isOpen ? '100%' : '70%'
          }}
          animate={{
            rotate: isOpen ? -45 : 0,
            y: isOpen ? 1 : 0,
            width: isOpen ? '100%' : '70%',
          }}
          transition={{ duration: 0.2 }}
        />
      </div>
    </button>
  );
}

/**
 * Off-Canvas Menu Panel - Slides in from left
 */
interface OffCanvasMenuPanelProps {
  isOpen: boolean;
  onClose: () => void;
  sections: MenuSection[];
  title: string;
  footerText: string;
}

function OffCanvasMenuPanel({ 
  isOpen, 
  onClose, 
  sections,
  title,
  footerText
}: OffCanvasMenuPanelProps) {
  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.3 }}
            className="hp-menu-backdrop fixed inset-0"
            style={{ 
              zIndex: 9998,
              backgroundColor: 'hsl(var(--background) / 0.6)',
              backdropFilter: 'blur(4px)',
              WebkitBackdropFilter: 'blur(4px)'
            }}
            onClick={onClose}
          />

          {/* Menu panel */}
          <motion.nav
            initial={{ x: '-100%', opacity: 0.5 }}
            animate={{ x: 0, opacity: 1 }}
            exit={{ x: '-100%', opacity: 0 }}
            transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
            className="hp-menu-panel fixed left-0 top-0 bottom-0"
            style={{ 
              zIndex: 9999, 
              width: '100%', 
              maxWidth: '28rem'
            }}
          >
            <div 
              className="h-full flex flex-col"
              style={{
                backgroundColor: 'hsl(var(--card) / 0.85)',
                backdropFilter: 'blur(24px)',
                WebkitBackdropFilter: 'blur(24px)',
                borderRight: '1px solid hsl(var(--border) / 0.3)',
                boxShadow: 'var(--shadow-float)'
              }}
            >
              {/* Header */}
              <div 
                className="flex items-center justify-between"
                style={{ 
                  padding: '1.25rem 1.5rem',
                  borderBottom: '1px solid hsl(var(--border) / 0.2)'
                }}
              >
                <h2 
                  className="font-display text-2xl text-foreground"
                  style={{ letterSpacing: '0.025em', margin: 0, lineHeight: 1 }}
                >
                  {title}
                </h2>
                <button
                  onClick={onClose}
                  className="hp-menu-close-btn rounded-full hover:bg-secondary/50 transition-colors group"
                  style={{ 
                    padding: '0.5rem',
                    marginRight: '-0.5rem',
                    background: 'transparent',
                    border: 'none',
                    cursor: 'pointer'
                  }}
                  aria-label="Close menu"
                >
                  <HpCloseIcon className="text-muted-foreground group-hover:text-foreground transition-colors" />
                </button>
              </div>

              {/* Menu content */}
              <div 
                className="flex-1 overflow-y-auto scrollbar-hide"
                style={{ padding: '1.5rem 1rem' }}
              >
                {sections.map((section, sectionIndex) => (
                  <div 
                    key={sectionIndex} 
                    style={{ marginBottom: sectionIndex === sections.length - 1 ? 0 : '1.5rem' }}
                  >
                    {section.title && (
                      <motion.p
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.1 }}
                        className="hp-category-label"
                        style={{ 
                          padding: '0 0.5rem',
                          marginBottom: '0.75rem',
                          fontSize: '0.75rem',
                          fontWeight: 500,
                          textTransform: 'uppercase',
                          letterSpacing: '0.1em',
                          color: 'hsl(var(--muted-foreground) / 0.7)'
                        }}
                      >
                        {section.title}
                      </motion.p>
                    )}
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                      {section.items.map((item, itemIndex) => (
                        <MenuItemComponent
                          key={item.label}
                          item={item}
                          index={itemIndex}
                        />
                      ))}
                    </div>
                  </div>
                ))}
              </div>

              {/* Footer accent */}
              {footerText && (
                <div 
                  style={{ 
                    padding: '1rem 1.5rem',
                    borderTop: '1px solid hsl(var(--border) / 0.2)'
                  }}
                >
                  <p 
                    style={{ 
                      fontSize: '0.75rem',
                      textAlign: 'center',
                      color: 'hsl(var(--muted-foreground) / 0.6)',
                      margin: 0
                    }}
                  >
                    {footerText}
                  </p>
                </div>
              )}
            </div>
          </motion.nav>
        </>
      )}
    </AnimatePresence>
  );
}

/**
 * HpMenu - Main component for [hp_menu] shortcode
 * 
 * Renders a hamburger trigger button inline and portals an off-canvas
 * drawer to the body. Props are hydrated from ACF via PHP.
 * 
 * @example WordPress shortcode usage:
 * [hp_menu]
 * [hp_menu title="Shop Categories"]
 */
export function HpMenu({
  sections = [],
  title = 'Menu',
  footerText = 'HolisticPeople — Supreme Quality Botanicals',
  triggerClassName,
}: HpMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null);

  // Create portal container on mount
  useEffect(() => {
    // Check if container already exists
    let container = document.getElementById('hp-menu-portal');
    if (!container) {
      container = document.createElement('div');
      container.id = 'hp-menu-portal';
      document.body.appendChild(container);
    }
    setPortalContainer(container);

    return () => {
      // Don't remove on unmount - other instances might use it
    };
  }, []);

  // Close menu on escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        setIsOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen]);

  // Prevent body scroll when menu is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  // Dispatch custom event when menu opens/closes (for external integrations)
  useEffect(() => {
    window.dispatchEvent(
      new CustomEvent('hpOffCanvasMenuStateChange', {
        detail: { isOpen },
      })
    );
  }, [isOpen]);

  return (
    <>
      {/* Hamburger trigger - renders inline where shortcode is placed */}
      <MenuTrigger
        isOpen={isOpen}
        onClick={() => setIsOpen(!isOpen)}
        className={triggerClassName}
      />

      {/* Off-canvas drawer - portaled to body */}
      {portalContainer && createPortal(
        <OffCanvasMenuPanel
          isOpen={isOpen}
          onClose={() => setIsOpen(false)}
          sections={sections}
          title={title}
          footerText={footerText}
        />,
        portalContainer
      )}
    </>
  );
}

export default HpMenu;
