import { useState, useRef, useEffect } from 'react';
import { cn } from '@/lib/utils';

interface Option {
  value: string;
  label: string;
}

interface SearchableSelectProps {
  options: Option[];
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
  error?: boolean;
}

export const SearchableSelect = ({
  options,
  value,
  onChange,
  placeholder = 'Select...',
  className,
  error,
}: SearchableSelectProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const [search, setSearch] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);

  // Find the selected option's label
  const selectedOption = options.find((opt) => opt.value === value);
  const displayValue = selectedOption?.label || '';

  // Filter and sort options based on search
  // Prioritize options that START with the search term
  const filteredOptions = search
    ? options
        .filter((opt) =>
          opt.label.toLowerCase().includes(search.toLowerCase())
        )
        .sort((a, b) => {
          const searchLower = search.toLowerCase();
          const aLabel = a.label.toLowerCase();
          const bLabel = b.label.toLowerCase();
          
          // Check if labels start with search (skip emoji/flag at start)
          const aText = aLabel.replace(/^[^\w\s]+\s*/, ''); // Remove leading emoji/flag
          const bText = bLabel.replace(/^[^\w\s]+\s*/, '');
          
          const aStartsWith = aText.startsWith(searchLower);
          const bStartsWith = bText.startsWith(searchLower);
          
          // Items starting with search come first
          if (aStartsWith && !bStartsWith) return -1;
          if (!aStartsWith && bStartsWith) return 1;
          
          // If both start or both don't start, sort alphabetically
          return aText.localeCompare(bText);
        })
    : options;

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
        setSearch('');
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Focus input when dropdown opens and scroll to selected
  useEffect(() => {
    if (isOpen) {
      inputRef.current?.focus();
      // Scroll to selected option
      if (listRef.current && value) {
        const selectedEl = listRef.current.querySelector('[data-selected="true"]');
        if (selectedEl) {
          selectedEl.scrollIntoView({ block: 'center' });
        }
      }
    }
  }, [isOpen, value]);

  const handleSelect = (optionValue: string) => {
    onChange(optionValue);
    setIsOpen(false);
    setSearch('');
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setIsOpen(false);
      setSearch('');
    } else if (e.key === 'Enter' && filteredOptions.length > 0) {
      e.preventDefault();
      handleSelect(filteredOptions[0].value);
    }
  };

  return (
    <div ref={containerRef} className="relative">
      {/* Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          "flex h-10 w-full items-center justify-between rounded-md border border-input px-3 py-2 text-sm text-left",
          "ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
          error && 'border-destructive',
          className
        )}
        style={{ backgroundColor: 'hsl(var(--card))' }}
      >
        <span className={cn("truncate", !value && 'text-muted-foreground')}>
          {displayValue || placeholder}
        </span>
        <svg
          className={cn("h-4 w-4 shrink-0 opacity-50 transition-transform ml-2", isOpen && "rotate-180")}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {/* Dropdown */}
      {isOpen && (
        <div 
          className="absolute z-[100] mt-1 w-full rounded-md border border-border shadow-xl overflow-hidden"
          style={{ backgroundColor: 'hsl(var(--card))' }}
        >
          {/* Search Input */}
          <div className="p-2 border-b border-border" style={{ backgroundColor: 'hsl(var(--card))' }}>
            <input
              ref={inputRef}
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder="Type to search..."
              className="w-full px-3 py-2 text-sm border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-ring"
              style={{ backgroundColor: 'hsl(var(--background))' }}
            />
          </div>

          {/* Options List */}
          <div 
            ref={listRef}
            className="max-h-[220px] overflow-y-auto p-2 flex flex-col gap-1"
            style={{ 
              backgroundColor: 'hsl(var(--card))',
              scrollbarWidth: 'thin',
              scrollbarColor: 'hsl(var(--muted-foreground) / 0.3) transparent'
            }}
          >
            {filteredOptions.length === 0 ? (
              <div className="px-3 py-3 text-sm text-muted-foreground text-center">
                No results found
              </div>
            ) : (
              filteredOptions.map((option) => (
                <button
                  key={option.value}
                  type="button"
                  data-selected={option.value === value}
                  onClick={() => handleSelect(option.value)}
                  className={cn(
                    "w-full px-3 py-2 text-sm text-left transition-colors rounded-md",
                    "hover:bg-accent hover:text-accent-foreground",
                    "focus:outline-none focus:bg-accent focus:text-accent-foreground",
                    option.value === value && "bg-primary/10 text-primary font-medium"
                  )}
                >
                  {option.label}
                </button>
              ))
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default SearchableSelect;

