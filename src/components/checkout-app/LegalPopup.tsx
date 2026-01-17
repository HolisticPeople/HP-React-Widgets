import { useState, useEffect, useCallback } from 'react';

const CloseIcon = () => (
  <svg className="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
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
      fetchContent();
      // Prevent body scroll when popup is open
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }

    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen, fetchContent]);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isOpen) {
        onClose();
      }
    };

    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  return (
    <div 
      className="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      onClick={onClose}
    >
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" />
      
      {/* Modal */}
      <div 
        className="relative w-full max-w-3xl max-h-[80vh] bg-card rounded-xl shadow-2xl border border-border/50 flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-border/50">
          <h2 className="text-xl font-bold text-accent">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="h-10 w-10 rounded-full flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary/50 transition-colors border-0 outline-none bg-transparent"
          >
            <CloseIcon />
          </button>
        </div>
        
        {/* Content */}
        <div className="flex-1 overflow-y-auto p-6">
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
            <div 
              className="prose prose-invert max-w-none text-muted-foreground
                prose-headings:text-accent prose-headings:font-bold
                prose-p:text-muted-foreground prose-p:leading-relaxed
                prose-a:text-accent prose-a:no-underline hover:prose-a:underline
                prose-strong:text-muted-foreground
                prose-ul:text-muted-foreground prose-ol:text-muted-foreground
                prose-li:marker:text-accent"
              dangerouslySetInnerHTML={{ __html: content }}
            />
          )}
        </div>
        
        {/* Footer */}
        <div className="p-4 border-t border-border/50">
          <button
            type="button"
            onClick={onClose}
            className="w-full h-12 rounded-lg font-semibold text-base transition-all duration-300 bg-accent text-accent-foreground hover:shadow-[0_0_20px_hsl(45_95%_60%/0.5)] border-0 outline-none"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  );
};

export default LegalPopup;
