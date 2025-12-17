import { cn } from '@/lib/utils';

export interface FooterLink {
  label: string;
  url: string;
}

export interface FunnelFooterProps {
  funnelName?: string;
  text?: string;
  disclaimer?: string;
  links?: FooterLink[];
  showCopyright?: boolean;
  className?: string;
}

export const FunnelFooter = ({
  funnelName = '',
  text,
  disclaimer,
  links = [],
  showCopyright = true,
  className,
}: FunnelFooterProps) => {
  const currentYear = new Date().getFullYear();

  return (
    <footer
      className={cn(
        'hp-funnel-footer py-8 px-4 border-t border-border/50 bg-background/50',
        className
      )}
    >
      <div className="max-w-6xl mx-auto">
        {/* Main content */}
        <div className="text-center">
          {/* Footer text */}
          {text && (
            <p className="text-muted-foreground mb-4">{text}</p>
          )}

          {/* Footer links */}
          {links.length > 0 && (
            <nav className="flex flex-wrap justify-center gap-4 mb-6">
              {links.map((link, index) => (
                <a
                  key={index}
                  href={link.url}
                  className="text-muted-foreground hover:text-accent transition-colors text-sm"
                >
                  {link.label}
                </a>
              ))}
            </nav>
          )}

          {/* Copyright */}
          {showCopyright && (
            <p className="text-muted-foreground text-sm mb-4">
              © {currentYear} {funnelName || 'All Rights Reserved'}
            </p>
          )}

          {/* Disclaimer */}
          {disclaimer && (
            <p className="text-xs text-muted-foreground/70 max-w-3xl mx-auto">
              {disclaimer}
            </p>
          )}
        </div>
      </div>
    </footer>
  );
};

export default FunnelFooter;














