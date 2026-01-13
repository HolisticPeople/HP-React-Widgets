import React from 'react';

// USPS Logo - Blue eagle
export const USPSLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2L4 6v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V6l-8-4z" fill="#004B87"/>
    <path d="M7 10l2 2 4-4" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
    <text x="12" y="20" textAnchor="middle" fontSize="4" fill="white" fontWeight="bold">USPS</text>
  </svg>
);

// UPS Logo - Brown shield
export const UPSLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <path d="M12 2L4 6v6c0 5.55 3.84 10.74 8 12 4.16-1.26 8-6.45 8-12V6l-8-4z" fill="#351C15"/>
    <text x="12" y="14" textAnchor="middle" fontSize="6" fill="#FFB500" fontWeight="bold">UPS</text>
  </svg>
);

// FedEx Logo - Purple and orange
export const FedExLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="4" width="20" height="16" rx="2" fill="#4D148C"/>
    <text x="8" y="14" fontSize="5" fill="white" fontWeight="bold">Fed</text>
    <text x="17" y="14" fontSize="5" fill="#FF6600" fontWeight="bold">Ex</text>
  </svg>
);

// DHL Logo - Yellow and red
export const DHLLogo = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <rect x="2" y="6" width="20" height="12" rx="2" fill="#FFCC00"/>
    <text x="12" y="14" textAnchor="middle" fontSize="6" fill="#D40511" fontWeight="bold">DHL</text>
  </svg>
);

// Generic Truck Icon - fallback
export const TruckIcon = () => (
  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
    <rect x="1" y="3" width="15" height="13"/>
    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
    <circle cx="5.5" cy="18.5" r="2.5"/>
    <circle cx="18.5" cy="18.5" r="2.5"/>
  </svg>
);

// Carrier logo detection and mapping
export type CarrierType = 'usps' | 'ups' | 'fedex' | 'dhl' | 'generic';

export const detectCarrier = (serviceName: string): CarrierType => {
  const name = serviceName.toLowerCase();
  if (name.includes('usps') || name.includes('postal') || name.includes('priority mail')) return 'usps';
  if (name.includes('ups') || name.includes('united parcel')) return 'ups';
  if (name.includes('fedex') || name.includes('federal express')) return 'fedex';
  if (name.includes('dhl')) return 'dhl';
  return 'generic';
};

export const getCarrierLogo = (serviceName: string): React.ReactNode => {
  const carrier = detectCarrier(serviceName);
  
  switch (carrier) {
    case 'usps':
      return <USPSLogo />;
    case 'ups':
      return <UPSLogo />;
    case 'fedex':
      return <FedExLogo />;
    case 'dhl':
      return <DHLLogo />;
    default:
      return <TruckIcon />;
  }
};

// Get carrier name for display
export const getCarrierName = (serviceName: string): string => {
  const carrier = detectCarrier(serviceName);
  
  switch (carrier) {
    case 'usps':
      return 'USPS';
    case 'ups':
      return 'UPS';
    case 'fedex':
      return 'FedEx';
    case 'dhl':
      return 'DHL';
    default:
      return '';
  }
};
