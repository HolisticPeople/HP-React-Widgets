import { useState, useCallback, useMemo } from 'react';
import type { CustomerData, Address } from '../types';

interface UseCustomerLookupOptions {
  apiBase?: string;
  onSuccess?: (data: CustomerData) => void;
}

interface CustomerLookupResponse {
  user_id: number;
  points_balance: number;
  billing: {
    first_name: string;
    last_name: string;
    company?: string;
    address_1: string;
    address_2?: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
    phone?: string;
    email?: string;
  } | null;
  shipping: {
    first_name: string;
    last_name: string;
    company?: string;
    address_1: string;
    address_2?: string;
    city: string;
    state: string;
    postcode: string;
    country: string;
    phone?: string;
  } | null;
}

function mapAddress(addr: CustomerLookupResponse['billing'] | CustomerLookupResponse['shipping']): Address | null {
  if (!addr || !addr.address_1) return null;
  return {
    firstName: addr.first_name || '',
    lastName: addr.last_name || '',
    company: addr.company || '',
    address1: addr.address_1 || '',
    address2: addr.address_2 || '',
    city: addr.city || '',
    state: addr.state || '',
    postcode: addr.postcode || '',
    country: addr.country || '',
    phone: addr.phone || '',
    email: 'email' in addr ? addr.email || '' : '',
  };
}

export function useCustomerLookup(options: UseCustomerLookupOptions = {}) {
  const { apiBase = '/wp-json/hp-rw/v1', onSuccess } = options;
  
  const [customerData, setCustomerData] = useState<CustomerData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasLookedUp, setHasLookedUp] = useState(false);

  const lookup = useCallback(async (email: string): Promise<CustomerData | null> => {
    if (!email || !email.includes('@')) {
      return null;
    }

    setIsLoading(true);
    setError(null);

    try {
      const res = await fetch(`${apiBase}/checkout/customer`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });

      if (!res.ok) {
        throw new Error('Failed to lookup customer');
      }

      const data: CustomerLookupResponse = await res.json();
      
      const customer: CustomerData = {
        userId: data.user_id || 0,
        email,
        pointsBalance: data.points_balance || 0,
        billing: mapAddress(data.billing),
        shipping: mapAddress(data.shipping),
      };

      setCustomerData(customer);
      setHasLookedUp(true);
      
      if (onSuccess) {
        onSuccess(customer);
      }

      return customer;
    } catch (err: any) {
      console.error('[useCustomerLookup] Error:', err);
      setError(err.message || 'Customer lookup failed');
      setHasLookedUp(true);
      return null;
    } finally {
      setIsLoading(false);
    }
  }, [apiBase, onSuccess]);

  const reset = useCallback(() => {
    setCustomerData(null);
    setHasLookedUp(false);
    setError(null);
  }, []);

  // Memoize return object to prevent unnecessary re-renders in consumers
  return useMemo(() => ({
    customerData,
    isLoading,
    error,
    hasLookedUp,
    lookup,
    reset,
  }), [customerData, isLoading, error, hasLookedUp, lookup, reset]);
}


