import { Address, AddressType } from '@/types/address';
import { Pencil, Trash2, Star, Copy, Check } from 'lucide-react';
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

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
        'address-card group cursor-pointer min-w-[280px] max-w-[320px] flex-shrink-0 h-full min-h-[260px] flex flex-col',
        isSelected && 'selected',
        address.isDefault && 'is-default'
      )}
      onClick={onSelect}
    >
      {/* Default Badge */}
      {address.isDefault && (
        <div className="absolute -top-2.5 left-4">
          <span className="default-badge">
            <Star className="h-3 w-3 fill-current" />
            Default
          </span>
        </div>
      )}

      {/* Selection Indicator */}
      {isSelected && (
        <div className="absolute top-3 right-3">
          <div className="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
            <Check className="h-4 w-4" />
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
                <Pencil className="h-4 w-4" />
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
                <Trash2 className="h-4 w-4" />
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
                <Star
                  className={cn(
                    'h-4 w-4',
                    address.isDefault && 'fill-current'
                  )}
                />
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
                <Copy className="h-4 w-4" />
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


