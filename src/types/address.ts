export interface Address {
  id: string;
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  phone?: string;
  email?: string;
  isDefault: boolean;
}

export type AddressType = 'billing' | 'shipping';

export interface AddressCardPickerProps {
  addresses: Address[];
  type: AddressType;
  selectedId?: string;
  onSelect?: (address: Address) => void;
  onEdit?: (address: Address) => void;
  onDelete?: (address: Address) => void;
  onSetDefault?: (address: Address) => void;
  onCopy?: (address: Address, targetType: AddressType) => void;
  showActions?: boolean;
  title?: string;
}



