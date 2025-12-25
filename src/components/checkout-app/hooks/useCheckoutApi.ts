import { useCallback, useMemo } from 'react';
import type { CartItem, ShippingRate, TotalsResponse, Address, OrderSummary } from '../types';

interface UseCheckoutApiOptions {
  apiBase?: string;
  funnelId: string;
  funnelName: string;
}

interface CreatePaymentIntentResponse {
  clientSecret: string;
  publishableKey: string;
  orderDraftId: string;
  amountCents: number;
  piId: string;
}

export function useCheckoutApi(options: UseCheckoutApiOptions) {
  const { apiBase = '/wp-json/hp-rw/v1', funnelId, funnelName } = options;

  const getShippingRates = useCallback(async (
    address: Address,
    items: CartItem[]
  ): Promise<ShippingRate[]> => {
    const res = await fetch(`${apiBase}/checkout/shipping-rates`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        address: {
          first_name: address.firstName,
          last_name: address.lastName,
          address_1: address.address1,
          address_2: address.address2,
          city: address.city,
          state: address.state,
          postcode: address.postcode,
          country: address.country,
          phone: address.phone,
          email: address.email,
        },
        items: items.map(item => ({
          sku: item.sku,
          qty: item.qty,
          exclude_global_discount: item.excludeGlobalDiscount,
          item_discount_percent: item.itemDiscountPercent,
        })),
      }),
    });

    if (!res.ok) {
      throw new Error('Failed to get shipping rates');
    }

    const data = await res.json();
    return data.rates || [];
  }, [apiBase]);

  const calculateTotals = useCallback(async (
    address: Address,
    items: CartItem[],
    selectedRate: ShippingRate | null,
    pointsToRedeem: number = 0,
    offerTotal?: number  // Admin-set total for entire offer
  ): Promise<TotalsResponse> => {
    const res = await fetch(`${apiBase}/checkout/totals`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        funnel_id: funnelId,
        address: {
          first_name: address.firstName,
          last_name: address.lastName,
          address_1: address.address1,
          address_2: address.address2,
          city: address.city,
          state: address.state,
          postcode: address.postcode,
          country: address.country,
          phone: address.phone,
          email: address.email,
        },
        items: items.map(item => ({
          sku: item.sku,
          qty: item.qty,
          exclude_global_discount: item.excludeGlobalDiscount,
          item_discount_percent: item.itemDiscountPercent,
          salePrice: item.salePrice,  // Admin-set price per unit
        })),
        selected_rate: selectedRate ? {
          serviceName: selectedRate.serviceName,
          amount: selectedRate.shipmentCost + selectedRate.otherCost,
        } : null,
        points_to_redeem: pointsToRedeem,
        offer_total: offerTotal,  // Admin-set total price for entire offer
      }),
    });

    if (!res.ok) {
      throw new Error('Failed to calculate totals');
    }

    const data = await res.json();
    return {
      subtotal: data.subtotal || 0,
      discountTotal: data.discount_total || 0,
      shippingTotal: data.shipping_total || 0,
      globalDiscount: data.global_discount || 0,
      pointsDiscount: data.points_discount || 0,
      grandTotal: data.grand_total || 0,
    };
  }, [apiBase, funnelId]);

  const createPaymentIntent = useCallback(async (
    items: CartItem[],
    shippingAddress: Address,
    customerEmail: string,
    customerFirstName: string,
    customerLastName: string,
    selectedRate: ShippingRate | null,
    pointsToRedeem: number = 0,
    offerTotal?: number  // Admin-set total for entire offer
  ): Promise<CreatePaymentIntentResponse> => {
    const res = await fetch(`${apiBase}/checkout/create-intent`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        funnel_id: funnelId,
        funnel_name: funnelName,
        items: items.map(item => ({
          sku: item.sku,
          qty: item.qty,
          exclude_global_discount: item.excludeGlobalDiscount,
          item_discount_percent: item.itemDiscountPercent,
          salePrice: item.salePrice,  // Admin-set price per unit
        })),
        offer_total: offerTotal,  // Admin-set total price for entire offer
        shipping_address: {
          first_name: shippingAddress.firstName,
          last_name: shippingAddress.lastName,
          address_1: shippingAddress.address1,
          address_2: shippingAddress.address2,
          city: shippingAddress.city,
          state: shippingAddress.state,
          postcode: shippingAddress.postcode,
          country: shippingAddress.country,
          phone: shippingAddress.phone,
          email: shippingAddress.email,
        },
        customer: {
          email: customerEmail,
          first_name: customerFirstName,
          last_name: customerLastName,
        },
        selected_rate: selectedRate ? {
          serviceName: selectedRate.serviceName,
          amount: selectedRate.shipmentCost + selectedRate.otherCost,
        } : null,
        points_to_redeem: pointsToRedeem,
      }),
    });

    const data = await res.json();

    if (!res.ok) {
      if (res.status === 409 && data.code === 'funnel_off') {
        throw { code: 'funnel_off', redirect: data.data?.redirect || '/' };
      }
      throw new Error(data.message || 'Failed to create payment intent');
    }

    return {
      clientSecret: data.client_secret,
      publishableKey: data.publishable,
      orderDraftId: data.order_draft_id,
      amountCents: data.amount_cents,
      piId: data.pi_id || '',
    };
  }, [apiBase, funnelId, funnelName]);

  const getOrderSummary = useCallback(async (
    orderId?: number,
    piId?: string
  ): Promise<OrderSummary | null> => {
    const params = new URLSearchParams();
    if (orderId) params.set('order_id', String(orderId));
    if (piId) params.set('pi_id', piId);

    const res = await fetch(`${apiBase}/checkout/order-summary?${params}`);
    
    if (!res.ok) {
      if (res.status === 404) return null;
      throw new Error('Failed to get order summary');
    }

    const data = await res.json();
    return {
      orderId: data.order_id,
      orderNumber: data.order_number,
      items: data.items.map((item: any) => ({
        name: item.name,
        qty: item.qty,
        price: item.price,
        subtotal: item.subtotal,
        total: item.total,
        image: item.image,
        sku: item.sku,
      })),
      shippingTotal: data.shipping_total || 0,
      feesTotal: data.fees_total || 0,
      pointsRedeemed: data.points_redeemed || { points: 0, value: 0 },
      itemsDiscount: data.items_discount || 0,
      grandTotal: data.grand_total || 0,
      status: data.status || '',
    };
  }, [apiBase]);

  const chargeUpsell = useCallback(async (
    parentOrderId: number,
    parentPiId: string,
    sku: string,
    qty: number,
    discountPercent?: number
  ): Promise<{ success: boolean; orderId: number }> => {
    const res = await fetch(`${apiBase}/upsell/charge`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        parent_order_id: parentOrderId,
        parent_pi_id: parentPiId,
        items: [{
          sku,
          qty,
          item_discount_percent: discountPercent,
        }],
      }),
    });

    const data = await res.json();

    if (!res.ok) {
      if (data.code === 'requires_action') {
        throw new Error('Additional authentication required. Please contact support.');
      }
      throw new Error(data.message || 'Failed to process upsell');
    }

    return {
      success: true,
      orderId: data.order_id,
    };
  }, [apiBase]);

  // Memoize the return object to prevent infinite loops in consumers
  return useMemo(() => ({
    getShippingRates,
    calculateTotals,
    createPaymentIntent,
    getOrderSummary,
    chargeUpsell,
  }), [getShippingRates, calculateTotals, createPaymentIntent, getOrderSummary, chargeUpsell]);
}


