import { Address, AddressType } from '@/types/address';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

// Local inline SVG icons with filled shapes to avoid theme conflicts.
const HpEditIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M4 17.5L4.5 20L7 19.5L18 8.5L15.5 6L4.5 17Z" />
    <path d="M14.5 5.5L16.5 3.5L20.5 7.5L18.5 9.5Z" />
  </svg>
);

const HpDeleteIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <rect x="5" y="7" width="14" height="13" rx="1.5" />
    <rect x="9" y="3" width="6" height="3" rx="1" />
    <path d="M4 7H20" />
  </svg>
);

const HpStarIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M12 3L14.9 8.3L21 9.2L16.5 13.3L17.6 19.4L12 16.6L6.4 19.4L7.5 13.3L3 9.2L9.1 8.3L12 3Z" />
  </svg>
);

const HpCopyIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <rect x="8" y="8" width="11" height="12" rx="2" />
    <rect x="5" y="4" width="11" height="12" rx="2" />
  </svg>
);

const HpCheckIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path d="M5 13L10 18L19 7" />
  </svg>
);

interface AddressCardProps {
  address: Address;
  type: AddressType;
  isSelected?: boolean;
  onSelect?: () => void;
  onEdit?: () => void;
  onDelete?: () => void;
  onSetDefault?: () => void;
  onCopy?: () => void;
  showActions?: boolean;
}

export const AddressCard = ({
  address,
  type,
  isSelected,
  onSelect,
  onEdit,
  onDelete,
  onSetDefault,
  onCopy,
  showActions = true,
}: AddressCardProps) => {
  const fullName = `${address.firstName} ${address.lastName}`;
  const copyTooltip = type === 'billing' ? 'Copy to shipping' : 'Copy to billing';

  return (
    <div
      className={cn(
        'address-card group cursor-pointer min-w-[280px] max-w-[320px] flex-shrink-0 h-full min-h-[320px] flex flex-col',
        isSelected && 'selected',
        address.isDefault && 'is-default'
      )}
      onClick={onSelect}
    >
      {/* Default Badge */}
      {address.isDefault && (
        <div className="absolute top-2 left-4">
          <span className="default-badge">
            <HpStarIcon />
            Default
          </span>
        </div>
      )}

      {/* Selection Indicator */}
      {isSelected && (
        <div className="absolute top-3 right-3">
          <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
            <HpCheckIcon />
          </div>
        </div>
      )}

      {/* Address Content */}
      <div className={cn('space-y-2 pt-2 flex-grow', address.isDefault && 'pt-4')}>
        <p className="font-semibold text-foreground text-base leading-tight">
          {fullName}
        </p>
        {address.company && (
          <p className="text-sm text-muted-foreground">{address.company}</p>
        )}
        <div className="space-y-0.5 text-sm text-secondary-foreground">
          <p>{address.address1}</p>
          {address.address2 && <p>{address.address2}</p>}
          <p>
            {address.city}, {address.state} {address.postcode}
          </p>
          <p className="text-muted-foreground">{address.country}</p>
        </div>
        {address.phone && (
          <p className="text-sm text-muted-foreground pt-1">{address.phone}</p>
        )}
      </div>

      {/* Action Buttons */}
      {showActions && (
        <div className="flex items-center gap-2 mt-4 pt-4 border-t border-border/50">
          <Tooltip>
            <TooltipTrigger asChild>
              <button
                className="action-btn"
                onClick={(e) => {
                  e.stopPropagation();
                  onEdit?.();
                }}
                aria-label="Edit address"
              >
                <HpEditIcon />
              </button>
            </TooltipTrigger>
            <TooltipContent className="tooltip-content">
              <p>Edit address</p>
            </TooltipContent>
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <button
                className="action-btn destructive"
                onClick={(e) => {
                  e.stopPropagation();
                  onDelete?.();
                }}
                aria-label="Delete address"
              >
                <HpDeleteIcon />
              </button>
            </TooltipTrigger>
            <TooltipContent className="tooltip-content">
              <p>Delete address</p>
            </TooltipContent>
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <button
                className={cn(
                  'action-btn',
                  address.isDefault && 'bg-primary/20 text-primary'
                )}
                onClick={(e) => {
                  e.stopPropagation();
                  onSetDefault?.();
                }}
                aria-label="Set as default"
                disabled={address.isDefault}
              >
                <HpStarIcon />
              </button>
            </TooltipTrigger>
            <TooltipContent className="tooltip-content">
              <p>{address.isDefault ? 'Default address' : 'Set as default'}</p>
            </TooltipContent>
          </Tooltip>

          <Tooltip>
            <TooltipTrigger asChild>
              <button
                className="action-btn"
                onClick={(e) => {
                  e.stopPropagation();
                  onCopy?.();
                }}
                aria-label={copyTooltip}
              >
                <HpCopyIcon />
              </button>
            </TooltipTrigger>
            <TooltipContent className="tooltip-content">
              <p>{copyTooltip}</p>
            </TooltipContent>
          </Tooltip>
        </div>
      )}
    </div>
  );
};


