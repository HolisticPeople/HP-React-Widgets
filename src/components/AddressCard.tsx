import { Address, AddressType } from '@/types/address';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

// Refined inline SVG icons - stroked for a modern, lightweight look.
const HpEditIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <path
      d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <path
      d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpDeleteIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <polyline
      points="3 6 5 6 21 6"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <path
      d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <line x1="10" y1="11" x2="10" y2="17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    <line x1="14" y1="11" x2="14" y2="17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
  </svg>
);

const HpStarIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <polygon
      points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpCopyIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <rect
      x="9"
      y="9"
      width="13"
      height="13"
      rx="2"
      ry="2"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <path
      d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

const HpCheckIcon = () => (
  <svg
    className="hp-icon"
    viewBox="0 0 24 24"
    aria-hidden="true"
  >
    <polyline
      points="20 6 9 17 4 12"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
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
  // Build display name with fallback for addresses missing name data
  const firstName = address.firstName?.trim() || '';
  const lastName = address.lastName?.trim() || '';
  const fullName = firstName || lastName 
    ? `${firstName} ${lastName}`.trim()
    : null; // null means no name to display
  
  // Build a short address summary for when name is missing
  const addressSummary = address.city 
    ? `${address.city}${address.postcode ? ` ${address.postcode}` : ''}`
    : address.address1 || 'Address';
    
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
        {/* DEBUG: Show raw data - REMOVE AFTER DEBUGGING */}
        <div className="text-[10px] text-yellow-400 bg-black/80 p-1 rounded mb-1 break-all font-mono">
          RAW: fn="{address.firstName}" | COMPUTED: fullName="{fullName}" | SHOWS: {fullName ? 'NAME' : 'FALLBACK'}
        </div>
        <div className="flex items-center justify-between gap-2">
          {fullName ? (
            <p className="font-semibold text-foreground text-base leading-tight">
              {fullName}
            </p>
          ) : (
            <p className="font-semibold text-foreground text-base leading-tight text-muted-foreground italic">
              {addressSummary}
            </p>
          )}
          {address.label && !address.isDefault && (
            <span className="text-xs text-muted-foreground font-medium">
              {address.label}
            </span>
          )}
        </div>
        {address.company && (
          <p className="text-sm text-muted-foreground">{address.company}</p>
        )}
        <div className="space-y-0.5 text-sm text-secondary-foreground">
          <p>
            {address.address1}
            {address.address2 && `, ${address.address2}`}
          </p>
          <p>
            {address.city}
            {address.state && `, ${address.state}`} {address.postcode}
          </p>
          <p className="text-muted-foreground">{address.country}</p>
        </div>
        <div className="space-y-0.5 text-sm text-muted-foreground pt-1">
          {address.phone && <p>{address.phone}</p>}
          {address.email && <p>{address.email}</p>}
        </div>
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

          {!address.isDefault && (
            <Tooltip>
              <TooltipTrigger asChild>
                <button
                  className="action-btn"
                  onClick={(e) => {
                    e.stopPropagation();
                    onSetDefault?.();
                  }}
                  aria-label="Set as default"
                >
                  <HpStarIcon />
                </button>
              </TooltipTrigger>
              <TooltipContent className="tooltip-content">
                <p>Set as default</p>
              </TooltipContent>
            </Tooltip>
          )}

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


