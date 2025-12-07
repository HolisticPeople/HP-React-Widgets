import { useState, useRef, useEffect, useCallback } from 'react';
import { AddressCardPickerProps, Address, AddressType } from '@/types/address';
import { AddressCard } from './AddressCard';
import { cn } from '@/lib/utils';

// Local inline SVG icons for navigation and headings.
const HpArrowLeftIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M14 5L8 11L14 17" />
    <path d="M8 11H20" />
  </svg>
);

const HpArrowRightIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M10 5L16 11L10 17" />
    <path d="M4 11H16" />
  </svg>
);

const HpMapPinIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M12 2C8.7 2 6 4.7 6 8C6 11.9 10.3 16.7 11.6 18.1C11.8 18.3 12.2 18.3 12.4 18.1C13.7 16.7 18 11.9 18 8C18 4.7 15.3 2 12 2Z" />
    <circle cx="12" cy="8" r="2.5" />
  </svg>
);

interface AddressCopiedEventDetail {
  fromType: AddressType;
  toType: AddressType;
  addresses: Address[];
  selectedId?: string;
}

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
  editUrl,
}: AddressCardPickerProps) => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [visibleCards, setVisibleCards] = useState(3);
  const sliderRef = useRef<HTMLDivElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Local, mutable list so REST actions can update UI without a full page reload.
  const [items, setItems] = useState<Address[]>(addresses);
  const [activeId, setActiveId] = useState<string | undefined>(selectedId);

  useEffect(() => {
    setItems(addresses);
  }, [addresses]);

  useEffect(() => {
    setActiveId(selectedId);
  }, [selectedId]);

  // Listen for copy events dispatched from any AddressCardPicker instance
  // and update this slider if it's the target type.
  useEffect(() => {
    const handler = (event: Event) => {
      const custom = event as CustomEvent<AddressCopiedEventDetail>;
      if (!custom.detail) return;
      if (custom.detail.toType !== type) return;

      if (Array.isArray(custom.detail.addresses)) {
        setItems(custom.detail.addresses);
        if (custom.detail.selectedId) {
          setActiveId(custom.detail.selectedId);
        }
      }
    };

    window.addEventListener('hpRWAddressCopied' as any, handler);
    return () => window.removeEventListener('hpRWAddressCopied' as any, handler);
  }, [type]);

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

  const maxIndex = Math.max(0, items.length - visibleCards);
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
    setActiveId(address.id);
    onSelect?.(address);
  };

  const apiFetch = useCallback(
    async (endpoint: string, payload: any) => {
      if (typeof window === 'undefined' || !window.hpReactSettings) {
        console.warn('[HP-React-Widgets] hpReactSettings is not available on window.');
        return null;
      }

      const { root, nonce } = window.hpReactSettings;

      const response = await fetch(`${root}hp-rw/v1/${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce,
        },
        body: JSON.stringify(payload),
      });

      if (!response.ok) {
        console.error('[HP-React-Widgets] Address API request failed', response.status);
        return null;
      }

      return response.json();
    },
    []
  );

  const handleDeleteAddress = async (address: Address) => {
    if (onDelete) {
      onDelete(address);
      return;
    }

    // Prevent deleting the primary WooCommerce address.
    if (address.id.endsWith('_primary')) {
      return;
    }

    if (!window.confirm('Delete this address? This cannot be undone.')) {
      return;
    }

    try {
      const result = await apiFetch('address/delete', { type, id: address.id });
      if (result && result.success && Array.isArray(result.addresses)) {
        setItems(result.addresses);
        setActiveId(result.selectedId ?? activeId);
      }
    } catch (e) {
      console.error('[HP-React-Widgets] Failed to delete address', e);
    }
  };

  const handleSetDefault = async (address: Address) => {
    if (onSetDefault) {
      onSetDefault(address);
      return;
    }

    try {
      const result = await apiFetch('address/set-default', { type, id: address.id });
      if (result && result.success && Array.isArray(result.addresses)) {
        setItems(result.addresses);
        setActiveId(result.selectedId ?? activeId);
      }
    } catch (e) {
      console.error('[HP-React-Widgets] Failed to set default address', e);
    }
  };

  const handleCopy = async (address: Address, targetType: AddressType) => {
    if (onCopy) {
      onCopy(address, targetType);
      return;
    }

    try {
      const result = await apiFetch('address/copy', {
        fromType: type,
        toType: targetType,
        id: address.id,
      });
      if (result && result.success && Array.isArray(result.addresses)) {
        // Notify any AddressCardPicker instance with matching type so it can
        // update its list immediately without a full page reload.
        const event = new CustomEvent<AddressCopiedEventDetail>('hpRWAddressCopied', {
          detail: {
            fromType: type,
            toType: targetType,
            addresses: result.addresses,
            selectedId: result.selectedId,
          },
        });
        window.dispatchEvent(event);
      }
    } catch (e) {
      console.error('[HP-React-Widgets] Failed to copy address', e);
    }
  };

  const handleEdit = (address: Address) => {
    if (onEdit) {
      onEdit(address);
      return;
    }

    if (editUrl) {
      window.location.href = editUrl;
    }
  };

  const typeLabel = type === 'billing' ? 'Billing' : 'Shipping';
  const displayTitle = title || `${typeLabel} Addresses`;

  if (items.length === 0) {
    return (
      <div className="rounded-xl border border-border/50 bg-card/50 p-8 text-center">
        <div className="flex flex-col items-center gap-3">
          <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
            <HpMapPinIcon />
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
          {displayTitle}
          <span className="text-sm font-normal text-muted-foreground">
            ({items.length})
          </span>
        </h3>

        {/* Navigation Arrows - Desktop */}
        {items.length > visibleCards && (
          <div className="hidden sm:flex items-center gap-2">
            <button
              className="slider-nav-btn"
              onClick={handlePrev}
              disabled={!canScrollLeft}
              aria-label="Previous addresses"
            >
              <HpArrowLeftIcon />
            </button>
            <button
              className="slider-nav-btn"
              onClick={handleNext}
              disabled={!canScrollRight}
              aria-label="Next addresses"
            >
              <HpArrowRightIcon />
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
          className="address-slider flex gap-4 overflow-x-auto scroll-smooth pb-2 -mx-2 px-2"
          style={{
            scrollSnapType: 'x mandatory',
          }}
        >
          {items.map((address, index) => (
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
                isSelected={activeId === address.id}
                onSelect={() => handleSelect(address)}
                onEdit={() => handleEdit(address)}
                onDelete={() => handleDeleteAddress(address)}
                onSetDefault={() => handleSetDefault(address)}
                onCopy={() => handleCopy(address, type === 'billing' ? 'shipping' : 'billing')}
                showActions={showActions}
              />
            </div>
          ))}
        </div>
      </div>

      {/* Pagination Dots - Mobile */}
      {items.length > visibleCards && (
        <div className="flex justify-center gap-2 pt-2 sm:hidden">
          {Array.from({ length: Math.ceil(items.length / visibleCards) }).map((_, i) => (
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
      {items.length > visibleCards && (
        <div className="flex sm:hidden items-center justify-center gap-4">
          <button
            className="slider-nav-btn"
            onClick={handlePrev}
            disabled={!canScrollLeft}
            aria-label="Previous addresses"
          >
            <HpArrowLeftIcon />
          </button>
          <span className="text-sm text-muted-foreground">
            {currentIndex + 1} - {Math.min(currentIndex + visibleCards, items.length)} of {items.length}
          </span>
          <button
            className="slider-nav-btn"
            onClick={handleNext}
            disabled={!canScrollRight}
            aria-label="Next addresses"
          >
            <HpArrowRightIcon />
          </button>
        </div>
      )}
    </div>
  );
};

export default AddressCardPicker;


