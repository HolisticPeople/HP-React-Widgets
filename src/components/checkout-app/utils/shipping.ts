import type { ShippingRate } from '../types';

/**
 * Helper to get service code from rate (handles both camelCase and snake_case)
 */
export const getServiceCode = (rate: ShippingRate | null): string => {
  if (!rate) return '';
  const rateAny = rate as Record<string, any>;
  return (rate.serviceCode || rateAny.service_code || '') as string;
};

/**
 * Helper function to extract TOTAL shipping cost from a rate object (shipping + other fees)
 */
export const extractShippingCost = (rate: ShippingRate | null): number => {
  if (!rate) return 0;
  const serviceCode = getServiceCode(rate);
  if (serviceCode === 'free_shipping') return 0;
  const rateAny = rate as Record<string, any>;
  
  // ShipStation returns shipping_amount_raw, check all possible field names
  const rawShipping = rateAny.shipping_amount_raw ?? rateAny.base_amount_raw ?? rate.shipmentCost ?? rateAny.shipment_cost ?? 0;
  const shipmentCost = typeof rawShipping === 'number' ? rawShipping : parseFloat(String(rawShipping)) || 0;
  
  // Also include other costs (insurance, fuel surcharge, etc.)
  const rawOther = rateAny.other_cost_raw ?? (rate as any).otherCost ?? rateAny.other_cost ?? 0;
  const otherCost = typeof rawOther === 'number' ? rawOther : parseFloat(String(rawOther)) || 0;
  
  return shipmentCost + otherCost;
};

































