// Types for the Checkout SPA

export type CheckoutStep = 'checkout' | 'processing' | 'upsell' | 'thankyou';

export type OfferType = 'single' | 'fixed_bundle' | 'customizable_kit';
export type ProductRole = 'must' | 'optional';  // 'must' = min qty 1, 'optional' = min qty 0
export type DiscountType = 'none' | 'percent' | 'fixed';

/**
 * Base offer structure shared by all offer types
 */
export interface BaseOffer {
  id: string;
  name: string;
  description?: string;
  type: OfferType;
  badge?: string;
  isFeatured?: boolean;
  image?: string;
  discountLabel?: string;
  discountType: DiscountType;
  discountValue: number;
  calculatedPrice?: number;
  originalPrice?: number;
}

/**
 * Product data from WooCommerce
 */
export interface OfferProduct {
  sku: string;
  name: string;
  price: number;
  regularPrice: number;
  image?: string;
}

/**
 * Single product offer
 */
export interface SingleOffer extends BaseOffer {
  type: 'single';
  productSku: string;
  quantity: number;
  product?: OfferProduct;
}

/**
 * Bundle item in a fixed bundle offer
 */
export interface BundleItem {
  sku: string;
  qty: number;
  name: string;
  price: number;
  regularPrice: number;
  image?: string;
}

/**
 * Fixed bundle offer - pre-configured set of products
 */
export interface FixedBundleOffer extends BaseOffer {
  type: 'fixed_bundle';
  bundleItems: BundleItem[];
}

/**
 * Kit product with role and quantity settings
 */
export interface KitProduct {
  sku: string;
  role: ProductRole;
  qty: number;
  maxQty: number;
  name: string;
  price: number;
  regularPrice: number;
  discountType: DiscountType;
  discountValue: number;
  discountedPrice: number;
  image?: string;
}

/**
 * Customizable kit offer - customer picks products
 */
export interface CustomizableKitOffer extends BaseOffer {
  type: 'customizable_kit';
  kitProducts: KitProduct[];
  maxTotalItems: number;
  defaultOriginalPrice?: number;
  defaultPriceAfterProductDiscounts?: number;
}

/**
 * Union type for all offer types
 */
export type Offer = SingleOffer | FixedBundleOffer | CustomizableKitOffer;

/**
 * Customer's selection for a kit
 */
export interface KitSelection {
  [sku: string]: number; // sku -> quantity
}

/**
 * @deprecated Use Offer instead - kept for backward compatibility
 */
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
  salePrice?: number;  // Admin-set price per unit (overrides WC price)
}

export interface CartData {
  items: CartItem[];
  offerTotal?: number;  // Admin-set total price for the entire offer (overrides sum of items)
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

export interface TextColors {
  basic: string;    // Off-white, main text
  accent: string;   // Orange/gold, highlighted text
  note: string;     // Muted, secondary text
  discount: string; // Green, savings/discounts
}

export interface FunnelCheckoutAppConfig {
  funnelId: string;
  funnelName: string;
  funnelSlug: string;
  offers: Offer[];
  defaultOfferId?: string;
  logoUrl?: string;
  logoLink?: string;
  landingUrl: string;
  freeShippingCountries: string[];
  enablePoints: boolean;
  enableCustomerLookup: boolean;
  stripePublishableKey: string;
  upsellOffers: UpsellOffer[];
  showUpsell: boolean;
  thankYouHeadline: string;
  thankYouMessage: string;
  accentColor: string;
  textColors?: TextColors;
  footerText: string;
  footerDisclaimer: string;
}


