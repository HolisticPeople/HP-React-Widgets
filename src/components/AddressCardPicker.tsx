import { useState, useRef, useEffect, useCallback } from 'react';
import { AddressCardPickerProps, Address, AddressType } from '@/types/address';
import { AddressCard } from './AddressCard';
import { cn } from '@/lib/utils';
import { EditAddressModal } from './EditAddressModal';

// Refined inline SVG icons for navigation - stroked for modern look.
const HpArrowLeftIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <polyline
      points="15 18 9 12 15 6"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpArrowRightIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <polyline
      points="9 18 15 12 9 6"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpMapPinIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path
      d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <circle
      cx="12"
      cy="10"
      r="3"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpPlusIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <line
      x1="12"
      y1="5"
      x2="12"
      y2="19"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
    />
    <line
      x1="5"
      y1="12"
      x2="19"
      y2="12"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
    />
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
  const [isEditOpen, setIsEditOpen] = useState(false);
  const [editingAddress, setEditingAddress] = useState<Address | null>(null);
  const [isAddMode, setIsAddMode] = useState(false);

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
    
    // Dispatch custom event for external integrations (e.g., THWMA checkout integration)
    window.dispatchEvent(
      new CustomEvent('hpAddressSelected', {
        detail: {
          address,
          type,
          addressId: address.id,
        },
      })
    );
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

    // Prefer inline edit modal; fall back to native WC edit URL if provided.
    setEditingAddress(address);
    setIsAddMode(false);
    setIsEditOpen(true);
  };

  const handleAddNew = () => {
    // Create an empty address template for the add modal
    const emptyAddress: Address = {
      id: 'new',
      firstName: '',
      lastName: '',
      company: '',
      address1: '',
      address2: '',
      city: '',
      state: '',
      postcode: '',
      country: '',
      phone: '',
      email: '',
      isDefault: false,
    };
    setEditingAddress(emptyAddress);
    setIsAddMode(true);
    setIsEditOpen(true);
  };

  const typeLabel = type === 'billing' ? 'Billing' : 'Shipping';
  const displayTitle = title || `${typeLabel} Addresses`;

  if (items.length === 0) {
    return (
      <div ref={containerRef}>
        <div className="rounded-xl border border-border/50 bg-card/50 p-8 text-center">
          <div className="flex flex-col items-center gap-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
              <HpMapPinIcon />
            </div>
            <p className="text-muted-foreground">No {type} addresses found</p>
            {showActions && (
              <button
                className="flex items-center gap-2 px-4 py-2 rounded-lg bg-primary/20 hover:bg-primary/30 text-primary transition-colors"
                onClick={handleAddNew}
              >
                <HpPlusIcon />
                <span>Add Address</span>
              </button>
            )}
          </div>
        </div>
        
        {editingAddress && (
          <EditAddressModal
            address={editingAddress}
            type={type}
            isOpen={isEditOpen}
            isAddMode={isAddMode}
            onClose={() => {
              setIsEditOpen(false);
              setEditingAddress(null);
              setIsAddMode(false);
            }}
            onSubmit={async (updated) => {
              try {
                const result = await apiFetch('address/create', {
                  type,
                  firstName: updated.firstName,
                  lastName: updated.lastName,
                  company: updated.company,
                  address1: updated.address1,
                  address2: updated.address2,
                  city: updated.city,
                  state: updated.state,
                  postcode: updated.postcode,
                  country: updated.country,
                  phone: updated.phone,
                  email: updated.email,
                });

                if (result && result.success && Array.isArray(result.addresses)) {
                  setItems(result.addresses);
                  setActiveId(result.selectedId);
                  
                  const newAddress = result.addresses.find(
                    (addr: Address) => addr.id === result.selectedId
                  );
                  if (newAddress) {
                    window.dispatchEvent(
                      new CustomEvent('hpAddressSelected', {
                        detail: {
                          address: newAddress,
                          type,
                          addressId: result.selectedId,
                        },
                      })
                    );
                  }
                }
              } catch (e) {
                console.error('[HP-React-Widgets] Failed to create address', e);
              } finally {
                setIsEditOpen(false);
                setEditingAddress(null);
                setIsAddMode(false);
              }
            }}
          />
        )}
      </div>
    );
  }

  return (
    <div ref={containerRef}>
      {/* Header */}
      <div className="flex items-center justify-between" style={{ marginBottom: '4px' }}>
        <div className="flex items-center gap-3">
          <h3 className="text-lg font-semibold text-foreground flex items-center gap-2" style={{ lineHeight: 1, margin: 0 }}>
            {displayTitle}
            <span className="text-sm font-normal text-muted-foreground">
              ({items.length})
            </span>
          </h3>
          
          {/* Add New Address Button */}
          {showActions && (
            <button
              className="flex items-center justify-center w-7 h-7 rounded-full bg-primary/20 hover:bg-primary/30 text-primary transition-colors"
              onClick={handleAddNew}
              aria-label="Add new address"
              title="Add new address"
            >
              <HpPlusIcon />
            </button>
          )}
        </div>

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

      {editingAddress && (
        <EditAddressModal
          address={editingAddress}
          type={type}
          isOpen={isEditOpen}
          isAddMode={isAddMode}
          onClose={() => {
            setIsEditOpen(false);
            setEditingAddress(null);
            setIsAddMode(false);
          }}
          onSubmit={async (updated) => {
            try {
              // Use different endpoint for create vs update
              const endpoint = isAddMode ? 'address/create' : 'address/update';
              const result = await apiFetch(endpoint, {
                type,
                id: updated.id,
                firstName: updated.firstName,
                lastName: updated.lastName,
                company: updated.company,
                address1: updated.address1,
                address2: updated.address2,
                city: updated.city,
                state: updated.state,
                postcode: updated.postcode,
                country: updated.country,
                phone: updated.phone,
                email: updated.email,
              });

              if (result && result.success && Array.isArray(result.addresses)) {
                setItems(result.addresses);
                // For add mode, select the newly created address
                // For edit mode, keep the edited address selected
                const selectedId = isAddMode ? result.selectedId : updated.id;
                setActiveId(selectedId);
                
                // Dispatch event so checkout fields update with the address
                const targetAddress = result.addresses.find(
                  (addr: Address) => addr.id === selectedId
                );
                if (targetAddress) {
                  window.dispatchEvent(
                    new CustomEvent('hpAddressSelected', {
                      detail: {
                        address: targetAddress,
                        type,
                        addressId: selectedId,
                      },
                    })
                  );
                }
              } else if (!isAddMode) {
                // Fallback optimistic update only for edit mode
                setItems((prev) =>
                  prev.map((addr) => (addr.id === updated.id ? { ...addr, ...updated } : addr))
                );
                setActiveId(updated.id);
              }
            } catch (e) {
              console.error(`[HP-React-Widgets] Failed to ${isAddMode ? 'create' : 'update'} address`, e);
              if (!isAddMode) {
                // Keep local optimistic update so the user sees their change.
                setItems((prev) =>
                  prev.map((addr) => (addr.id === updated.id ? { ...addr, ...updated } : addr))
                );
                setActiveId(updated.id);
              }
            } finally {
              setIsEditOpen(false);
              setEditingAddress(null);
              setIsAddMode(false);
            }
          }}
        />
      )}
    </div>
  );
};

export default AddressCardPicker;


