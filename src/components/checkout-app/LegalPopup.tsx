import { useState, useEffect, useCallback } from 'react';

const CloseIcon = () => (
  <svg className="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <line x1="18" y1="6" x2="6" y2="18" />
    <line x1="6" y1="6" x2="18" y2="18" />
  </svg>
);

const LoaderIcon = ({ className = "w-6 h-6" }: { className?: string }) => (
  <svg className={`${className} animate-spin`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="12" r="10" strokeOpacity="0.25" />
    <path d="M12 2a10 10 0 0 1 10 10" strokeLinecap="round" />
  </svg>
);

export interface LegalPopupProps {
  type: 'terms' | 'privacy';
  isOpen: boolean;
  onClose: () => void;
  apiBase?: string;
  tosPageId?: number;
  privacyPageId?: number;
}

export const LegalPopup = ({
  type,
  isOpen,
  onClose,
  apiBase = '/wp-json/wp/v2',
  tosPageId,
  privacyPageId,
}: LegalPopupProps) => {
  const [content, setContent] = useState<string>('');
  const [title, setTitle] = useState<string>('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isClosing, setIsClosing] = useState(false);
  const [shouldRender, setShouldRender] = useState(false);

  const pageId = type === 'terms' ? tosPageId : privacyPageId;

  const fetchContent = useCallback(async () => {
    if (!pageId) {
      // Fallback content if no page ID is configured
      setTitle(type === 'terms' ? 'Terms of Service' : 'Privacy Policy');
      setContent(type === 'terms' 
        ? '<p>Please contact us for our full terms of service.</p>'
        : '<p>Please contact us for our full privacy policy.</p>'
      );
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const response = await fetch(`${apiBase}/pages/${pageId}`);
      if (!response.ok) {
        throw new Error('Failed to load content');
      }
      const data = await response.json();
      setTitle(data.title?.rendered || (type === 'terms' ? 'Terms of Service' : 'Privacy Policy'));
      setContent(data.content?.rendered || '');
    } catch (err) {
      setError('Unable to load content. Please try again later.');
    } finally {
      setIsLoading(false);
    }
  }, [pageId, type, apiBase]);

  useEffect(() => {
    if (isOpen) {
      setShouldRender(true);
      setIsClosing(false);
      fetchContent();
      // Prevent body scroll when popup is open
      document.body.style.overflow = 'hidden';
    } else if (shouldRender) {
      // Trigger closing animation
      setIsClosing(true);
      const timer = setTimeout(() => {
        setShouldRender(false);
        document.body.style.overflow = '';
      }, 300); // Match animation duration
      return () => clearTimeout(timer);
    }

    return () => {
      if (!isOpen && !shouldRender) {
        document.body.style.overflow = '';
      }
    };
  }, [isOpen, fetchContent, shouldRender]);

  // Handle close with animation
  const handleClose = useCallback(() => {
    onClose();
  }, [onClose]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        handleClose();
      }
    };

    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [isOpen, handleClose]);

  if (!shouldRender) return null;

  return (
    <div
      className={`fixed inset-0 z-[9999] flex items-center justify-center p-4 transition-opacity duration-200 ${
        isClosing ? 'opacity-0' : 'opacity-100'
      }`}
      onClick={handleClose}
    >
      {/* Backdrop */}
      <div className={`absolute inset-0 bg-black/70 backdrop-blur-sm transition-opacity duration-200 ${
        isClosing ? 'opacity-0' : 'opacity-100'
      }`} />

      {/* Modal */}
      <div
        className={`relative w-full max-w-3xl max-h-[80vh] bg-card rounded-xl shadow-2xl border border-border/50 flex flex-col transition-all duration-300 ${
          isClosing ? 'opacity-0 scale-95 translate-y-4' : 'opacity-100 scale-100 translate-y-0'
        }`}
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-border/50">
          <h2 className="text-xl font-bold text-accent">{title}</h2>
          <button
            type="button"
            onClick={handleClose}
            className="h-11 w-11 rounded-full flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary/50 transition-colors bg-transparent"
            style={{ border: 'none', outline: 'none', boxShadow: 'none' }}
          >
            <CloseIcon />
          </button>
        </div>
        
        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6 legal-popup-content">
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <LoaderIcon className="w-8 h-8 text-accent" />
            </div>
          ) : error ? (
            <div className="text-center py-12 text-muted-foreground">
              <p>{error}</p>
              <button
                type="button"
                onClick={fetchContent}
                className="mt-4 px-6 py-2 rounded-lg border border-border/50 text-muted-foreground hover:text-foreground hover:border-border transition-colors bg-transparent outline-none"
              >
                Try Again
              </button>
            </div>
          ) : (
            <>
              <style>{`
                .legal-content-wrapper,
                .legal-content-wrapper p,
                .legal-content-wrapper li,
                .legal-content-wrapper span,
                .legal-content-wrapper div {
                  color: hsl(var(--muted-foreground)) !important;
                }
                .legal-content-wrapper h1,
                .legal-content-wrapper h2,
                .legal-content-wrapper h3,
                .legal-content-wrapper h4,
                .legal-content-wrapper strong {
                  color: hsl(var(--accent)) !important;
                }
                .legal-content-wrapper a {
                  color: hsl(var(--accent)) !important;
                }

                /* Dark scrollbar styling for the scrolling container */
                .legal-popup-content::-webkit-scrollbar {
                  width: 12px;
                }
                .legal-popup-content::-webkit-scrollbar-track {
                  background: hsl(var(--card));
                  border-radius: 6px;
                }
                .legal-popup-content::-webkit-scrollbar-thumb {
                  background: hsl(var(--border));
                  border-radius: 6px;
                  border: 2px solid hsl(var(--card));
                }
                .legal-popup-content::-webkit-scrollbar-thumb:hover {
                  background: hsl(var(--accent));
                }
              `}</style>
              <div 
                className="legal-content-wrapper prose prose-invert max-w-none"
                dangerouslySetInnerHTML={{ __html: content }}
              />
            </>
          )}
        </div>
        
        {/* Footer */}
        <div className="p-4 border-t border-border/50">
          <button
            type="button"
            onClick={handleClose}
            className="w-full h-14 rounded-lg font-semibold text-lg transition-colors text-muted-foreground hover:text-foreground bg-secondary/50 hover:bg-secondary"
            style={{ border: 'none', outline: 'none', boxShadow: 'none' }}
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default LegalPopup;
