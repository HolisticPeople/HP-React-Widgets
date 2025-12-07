import { useState, useRef, useEffect, useCallback } from 'react';
import { ChevronLeft, ChevronRight, MapPin } from 'lucide-react';
import { AddressCardPickerProps, Address } from '@/types/address';
import { AddressCard } from './AddressCard';
import { cn } from '@/lib/utils';

export const AddressCardPicker = ({
  addresses,
  type,
  selectedId,
  onSelect,
  onEdit,
  onDelete,
  onSetDefault,
  onCopy,
  showActions = true,
  title,
}: AddressCardPickerProps) => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [visibleCards, setVisibleCards] = useState(3);
  const sliderRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Calculate visible cards based on container width
  useEffect(() => {
    const updateVisibleCards = () => {
      if (containerRef.current) {
        const width = containerRef.current.offsetWidth;
        if (width < 400) {
          setVisibleCards(1);
        } else if (width < 700) {
          setVisibleCards(2);
        } else {
          setVisibleCards(3);
        }
      }
    };

    updateVisibleCards();
    window.addEventListener('resize', updateVisibleCards);
    return () => window.removeEventListener('resize', updateVisibleCards);
  }, []);

  const maxIndex = Math.max(0, addresses.length - visibleCards);
  const canScrollLeft = currentIndex > 0;
  const canScrollRight = currentIndex < maxIndex;

  const scrollTo = useCallback(
    (index: number) => {
      const newIndex = Math.max(0, Math.min(index, maxIndex));
      setCurrentIndex(newIndex);

      if (sliderRef.current) {
        const cardWidth = 320 + 16; // card width + gap
        sliderRef.current.scrollTo({
          left: newIndex * cardWidth,
          behavior: 'smooth',
        });
      }
    },
    [maxIndex]
  );

  const handlePrev = () => scrollTo(currentIndex - 1);
  const handleNext = () => scrollTo(currentIndex + 1);

  const handleSelect = (address: Address) => {
    onSelect?.(address);
  };

  const typeLabel = type === 'billing' ? 'Billing' : 'Shipping';
  const displayTitle = title || `${typeLabel} Addresses`;

  if (addresses.length === 0) {
    return (
      <div className="rounded-xl border border-border/50 bg-card/50 p-8 text-center">
        <div className="flex flex-col items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
            <MapPin className="h-6 w-6 text-muted-foreground" />
          </div>
          <p className="text-muted-foreground">No {type} addresses found</p>
        </div>
      </div>
    );
  }

  return (
    <div ref={containerRef} className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-foreground flex items-center gap-2">
          <MapPin className="h-5 w-5 text-primary" />
          {displayTitle}
          <span className="text-sm font-normal text-muted-foreground">
            ({addresses.length})
          </span>
        </h3>

        {/* Navigation Arrows - Desktop */}
        {addresses.length > visibleCards && (
          <div className="hidden sm:flex items-center gap-2">
            <button
              className="slider-nav-btn"
              onClick={handlePrev}
              disabled={!canScrollLeft}
              aria-label="Previous addresses"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
            <button
              className="slider-nav-btn"
              onClick={handleNext}
              disabled={!canScrollRight}
              aria-label="Next addresses"
            >
              <ChevronRight className="h-5 w-5" />
            </button>
          </div>
        )}
      </div>

      {/* Slider Container */}
      <div className="relative">
        {/* Gradient Edges */}
        {canScrollLeft && (
          <div className="absolute left-0 top-0 bottom-0 w-12 bg-gradient-to-r from-background to-transparent z-10 pointer-events-none" />
        )}
        {canScrollRight && (
          <div className="absolute right-0 top-0 bottom-0 w-12 bg-gradient-to-l from-background to-transparent z-10 pointer-events-none" />
        )}

        {/* Cards Slider */}
        <div
          ref={sliderRef}
          className="flex gap-4 overflow-x-auto scrollbar-hide scroll-smooth pb-2 -mx-2 px-2"
          style={{
            scrollSnapType: 'x mandatory',
          }}
        >
          {addresses.map((address, index) => (
            <div
              key={address.id}
              className="animate-fade-up h-full"
              style={{
                scrollSnapAlign: 'start',
                animationDelay: `${index * 50}ms`,
              }}
            >
              <AddressCard
                address={address}
                type={type}
                isSelected={selectedId === address.id}
                onSelect={() => handleSelect(address)}
                onEdit={() => onEdit?.(address)}
                onDelete={() => onDelete?.(address)}
                onSetDefault={() => onSetDefault?.(address)}
                onCopy={() => onCopy?.(address, type === 'billing' ? 'shipping' : 'billing')}
                showActions={showActions}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Pagination Dots - Mobile */}
      {addresses.length > visibleCards && (
        <div className="flex justify-center gap-2 pt-2 sm:hidden">
          {Array.from({ length: Math.ceil(addresses.length / visibleCards) }).map((_, i) => (
            <button
              key={i}
              className={cn(
                'slider-dot',
                Math.floor(currentIndex / visibleCards) === i && 'active'
              )}
              onClick={() => scrollTo(i * visibleCards)}
              aria-label={`Go to page ${i + 1}`}
            />
          ))}
        </div>
      )}

      {/* Mobile Navigation */}
      {addresses.length > visibleCards && (
        <div className="flex sm:hidden items-center justify-center gap-4">
          <button
            className="slider-nav-btn"
            onClick={handlePrev}
            disabled={!canScrollLeft}
            aria-label="Previous addresses"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <span className="text-sm text-muted-foreground">
            {currentIndex + 1} - {Math.min(currentIndex + visibleCards, addresses.length)} of {addresses.length}
          </span>
          <button
            className="slider-nav-btn"
            onClick={handleNext}
            disabled={!canScrollRight}
            aria-label="Next addresses"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
        </div>
      )}
    </div>
  );
};

export default AddressCardPicker;


