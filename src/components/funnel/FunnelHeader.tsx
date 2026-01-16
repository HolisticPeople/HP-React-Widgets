import { cn } from '@/lib/utils';

export interface NavItem {
  label: string;
  url: string;
  isExternal?: boolean;
}

export interface FunnelHeaderProps {
  logoUrl?: string;
  logoLink?: string;
  logoAlt?: string;
  navItems?: NavItem[];
  sticky?: boolean;
  transparent?: boolean;
  className?: string;
}

export const FunnelHeader = ({
  logoUrl,
  logoLink = '/',
  logoAlt = 'Logo',
  navItems = [],
  sticky = false,
  transparent = false,
  className,
}: FunnelHeaderProps) => {
  return (
    <header
      className={cn(
        'hp-funnel-header w-full z-50 transition-all duration-300',
        sticky && 'sticky top-0',
        transparent ? 'bg-transparent' : 'bg-background/95 backdrop-blur-sm',
        className
      )}
    >
      <div className="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
        {/* Logo */}
        {logoUrl && (
          <a
            href={logoLink}
            className="flex-shrink-0"
            target={logoLink.startsWith('http') ? '_blank' : undefined}
            rel={logoLink.startsWith('http') ? 'noopener noreferrer' : undefined}
          >
            <img
              src={logoUrl}
              alt={logoAlt}
              className="h-10 w-auto opacity-90 hover:opacity-100 transition-opacity"
            />
          </a>
        )}

        {/* Navigation */}
        {navItems.length > 0 && (
          <nav className="hidden md:flex items-center gap-6">
            {navItems.map((item, index) => (
              <a
                key={index}
                href={item.url}
                target={item.isExternal ? '_blank' : undefined}
                rel={item.isExternal ? 'noopener noreferrer' : undefined}
                className="text-foreground/80 hover:text-foreground transition-colors font-medium"
              >
                {item.label}
              </a>
            ))}
          </nav>
        )}

        {/* Mobile menu button (placeholder for future enhancement) */}
        {navItems.length > 0 && (
          <button className="md:hidden p-2 text-foreground/80 hover:text-foreground">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
        )}
      </div>
    </header>
  );
};

export default FunnelHeader;














