import { useState, useEffect } from 'react';
import { Address, AddressType } from '@/types/address';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface EditAddressModalProps {
  address: Address;
  type: AddressType;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (updated: Address) => void;
  /** When true, modal is in "Add" mode vs "Edit" mode */
  isAddMode?: boolean;
}

interface FormErrors {
  firstName?: string;
  lastName?: string;
  address1?: string;
  city?: string;
  postcode?: string;
  country?: string;
  email?: string;
  phone?: string;
}

export const EditAddressModal = ({
  address,
  type,
  isOpen,
  onClose,
  onSubmit,
  isAddMode = false,
}: EditAddressModalProps) => {
  const [formData, setFormData] = useState<Address>(address);
  const [errors, setErrors] = useState<FormErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Reset form when address changes or modal opens
  useEffect(() => {
    if (isOpen) {
      setFormData(address);
      setErrors({});
    }
  }, [address, isOpen]);

  const validateEmail = (email: string): boolean => {
    if (!email) return true; // Optional field
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  const validate = (): boolean => {
    const newErrors: FormErrors = {};

    if (!formData.firstName?.trim()) {
      newErrors.firstName = 'First name is required';
    }
    if (!formData.lastName?.trim()) {
      newErrors.lastName = 'Last name is required';
    }
    if (!formData.address1?.trim()) {
      newErrors.address1 = 'Address is required';
    }
    if (!formData.city?.trim()) {
      newErrors.city = 'City is required';
    }
    if (!formData.postcode?.trim()) {
      newErrors.postcode = 'Postcode is required';
    }
    if (!formData.country?.trim()) {
      newErrors.country = 'Country is required';
    }
    if (formData.email && !validateEmail(formData.email)) {
      newErrors.email = 'Invalid email format';
    }
    // Phone required for all addresses (shipping phone is mandatory; keep behaviour consistent here)
    if (!formData.phone?.trim()) {
      newErrors.phone = 'Phone is required';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleChange = (field: keyof Address) => (
    e: React.ChangeEvent<HTMLInputElement>
  ) => {
    setFormData((prev) => ({ ...prev, [field]: e.target.value }));
    // Clear error when user starts typing
    if (errors[field as keyof FormErrors]) {
      setErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validate()) return;

    setIsSubmitting(true);
    try {
      onSubmit(formData);
      onClose();
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCancel = () => {
    setErrors({});
    onClose();
  };

  const typeLabel = type === 'billing' ? 'Billing' : 'Shipping';
  const modalTitle = isAddMode ? `Add New ${typeLabel} Address` : `Edit ${typeLabel} Address`;

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && handleCancel()}>
      <DialogContent className="max-w-2xl bg-card border-border">
        <DialogHeader>
          <DialogTitle className="text-foreground">
            {modalTitle}
          </DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Row 1: First Name, Last Name, Company */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <FormField
              label="First Name"
              required
              error={errors.firstName}
            >
              <Input
                value={formData.firstName}
                onChange={handleChange('firstName')}
                placeholder="First name"
                className={cn(errors.firstName && 'border-destructive')}
              />
            </FormField>

            <FormField
              label="Last Name"
              required
              error={errors.lastName}
            >
              <Input
                value={formData.lastName}
                onChange={handleChange('lastName')}
                placeholder="Last name"
                className={cn(errors.lastName && 'border-destructive')}
              />
            </FormField>

            <FormField label="Company">
              <Input
                value={formData.company || ''}
                onChange={handleChange('company')}
                placeholder="Company (optional)"
              />
            </FormField>
          </div>

          {/* Row 2: Address Line 1, Address Line 2 */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <FormField
              label="Address Line 1"
              required
              error={errors.address1}
            >
              <Input
                value={formData.address1}
                onChange={handleChange('address1')}
                placeholder="Street address"
                className={cn(errors.address1 && 'border-destructive')}
              />
            </FormField>

            <FormField label="Address Line 2">
              <Input
                value={formData.address2 || ''}
                onChange={handleChange('address2')}
                placeholder="Apt, suite, unit (optional)"
              />
            </FormField>
          </div>

          {/* Row 3: City, State, Postcode */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <FormField
              label="City"
              required
              error={errors.city}
            >
              <Input
                value={formData.city}
                onChange={handleChange('city')}
                placeholder="City"
                className={cn(errors.city && 'border-destructive')}
              />
            </FormField>

            <FormField label="State / Province">
              <Input
                value={formData.state}
                onChange={handleChange('state')}
                placeholder="State"
              />
            </FormField>

            <FormField
              label="Postcode / ZIP"
              required
              error={errors.postcode}
            >
              <Input
                value={formData.postcode}
                onChange={handleChange('postcode')}
                placeholder="Postcode"
                className={cn(errors.postcode && 'border-destructive')}
              />
            </FormField>
          </div>

          {/* Row 4: Country, Phone, Email */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <FormField
              label="Country"
              required
              error={errors.country}
            >
              <Input
                value={formData.country}
                onChange={handleChange('country')}
                placeholder="Country"
                className={cn(errors.country && 'border-destructive')}
              />
            </FormField>

            <FormField
              label="Phone"
              required
              error={errors.phone}
            >
              <Input
                value={formData.phone || ''}
                onChange={handleChange('phone')}
                placeholder="Phone"
                className={cn(errors.phone && 'border-destructive')}
              />
            </FormField>

            <FormField
              label="Email"
              error={errors.email}
            >
              <Input
                type="email"
                value={formData.email || ''}
                onChange={handleChange('email')}
                placeholder="Email (optional)"
                className={cn(errors.email && 'border-destructive')}
              />
            </FormField>
          </div>

          <DialogFooter className="gap-2 pt-4">
            <Button
              type="button"
              variant="ghost"
              onClick={handleCancel}
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isSubmitting}
            >
              {isSubmitting ? 'Saving...' : isAddMode ? 'Add Address' : 'Save Changes'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
};

// Helper component for form fields
interface FormFieldProps {
  label: string;
  required?: boolean;
  error?: string;
  children: React.ReactNode;
}

const FormField = ({ label, required, error, children }: FormFieldProps) => (
  <div className="space-y-1.5">
    <Label className="text-sm text-muted-foreground">
      {label}
      {required && <span className="text-destructive ml-0.5">*</span>}
    </Label>
    {children}
    {error && (
      <p className="text-xs text-destructive">{error}</p>
    )}
  </div>
);

export default EditAddressModal;


