// Types for the Checkout SPA

export type CheckoutStep = 'checkout' | 'processing' | 'upsell' | 'thankyou';

export interface CheckoutProduct {
  id: string;
  sku: string;
  name: string;
  description?: string;
  price: number;
  regularPrice?: number;
  image?: string;
  badge?: string;
  features?: string[];
  freeItem?: {
    sku: string;
    qty: number;
  };
  isBestValue?: boolean;
}

export interface UpsellOffer {
  sku: string;
  name: string;
  description?: string;
  image?: string;
  regularPrice: number;
  offerPrice: number;
  discountPercent?: number;
  features?: string[];
}

export interface CustomerData {
  userId: number;
  email: string;
  pointsBalance: number;
  billing: Address | null;
  shipping: Address | null;
}

export interface Address {
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
}

export interface ShippingRate {
  serviceCode: string;
  serviceName: string;
  shipmentCost: number;
  otherCost: number;
}

export interface CartItem {
  sku: string;
  qty: number;
  excludeGlobalDiscount?: boolean;
  itemDiscountPercent?: number;
}

export interface TotalsResponse {
  subtotal: number;
  discountTotal: number;
  shippingTotal: number;
  globalDiscount: number;
  pointsDiscount: number;
  grandTotal: number;
}

export interface OrderSummary {
  orderId: number;
  orderNumber: string;
  items: OrderItem[];
  shippingTotal: number;
  feesTotal: number;
  pointsRedeemed: { points: number; value: number };
  itemsDiscount: number;
  grandTotal: number;
  status: string;
}

export interface OrderItem {
  name: string;
  qty: number;
  price: number;
  subtotal: number;
  total: number;
  image: string;
  sku: string;
}

export interface FunnelCheckoutAppConfig {
  funnelId: string;
  funnelName: string;
  funnelSlug: string;
  products: CheckoutProduct[];
  defaultProductId?: string;
  logoUrl?: string;
  logoLink?: string;
  landingUrl: string;
  freeShippingCountries: string[];
  globalDiscountPercent: number;
  enablePoints: boolean;
  enableCustomerLookup: boolean;
  stripePublishableKey: string;
  upsellOffers: UpsellOffer[];
  showUpsell: boolean;
  thankYouHeadline: string;
  thankYouMessage: string;
  accentColor: string;
  footerText: string;
  footerDisclaimer: string;
}


