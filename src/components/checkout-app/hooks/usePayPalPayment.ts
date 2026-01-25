import { useState, useCallback, useEffect, useRef, useMemo } from 'react';

// PayPal types
interface PayPalButtonsComponent {
  render: (container: HTMLElement | string) => Promise<void>;
  close: () => Promise<void>;
  isEligible: () => boolean;
}

interface PayPalNamespace {
  Buttons: (options: PayPalButtonOptions) => PayPalButtonsComponent;
  FUNDING: {
    PAYPAL: string;
    VENMO: string;
    CREDIT: string;
    CARD: string;
  };
}

interface PayPalButtonOptions {
  style?: {
    layout?: 'vertical' | 'horizontal';
    color?: 'gold' | 'blue' | 'silver' | 'black' | 'white';
    shape?: 'rect' | 'pill' | 'sharp';
    height?: number;
    label?: 'paypal' | 'checkout' | 'buynow' | 'pay';
    tagline?: boolean;
  };
  fundingSource?: string;
  createOrder: () => Promise<string>;
  onApprove: (data: { orderID: string }) => Promise<void>;
  onCancel?: (data: { orderID: string }) => void;
  onError?: (err: Error) => void;
}

declare global {
  interface Window {
    paypal?: PayPalNamespace;
    __hpPayPalLoading?: boolean;
    __hpPayPalClientId?: string;
    __hpPayPalCallbacks?: ((paypal: PayPalNamespace | null) => void)[];
  }
}

interface UsePayPalPaymentOptions {
  clientId: string;
  currency?: string;
  onCreateOrder: () => Promise<{
    funnelId: string;
    funnelName: string;
    amount: number;
    items: any[];
    customer: any;
    shippingAddress: any;
    selectedRate: any;
    pointsToRedeem: number;
    offerTotal?: number;
  }>;
  onPaymentSuccess?: (orderId: number, orderNumber: string) => void;
  onPaymentError?: (error: string) => void;
}

// Global PayPal SDK loader - singleton pattern
function loadPayPalSingleton(clientId: string, currency: string = 'USD'): Promise<PayPalNamespace | null> {
  return new Promise((resolve) => {
    // If already have PayPal loaded with same client ID
    if (window.paypal && window.__hpPayPalClientId === clientId) {
      resolve(window.paypal);
      return;
    }

    // If currently loading, queue up the callback
    if (window.__hpPayPalLoading) {
      window.__hpPayPalCallbacks = window.__hpPayPalCallbacks || [];
      window.__hpPayPalCallbacks.push(resolve);
      return;
    }

    // Remove any existing PayPal script to reload with new client ID
    const existingScript = document.querySelector('script[src*="paypal.com/sdk/js"]');
    if (existingScript) {
      existingScript.remove();
      delete window.paypal;
    }

    // Start loading
    window.__hpPayPalLoading = true;
    window.__hpPayPalClientId = clientId;
    window.__hpPayPalCallbacks = [resolve];

    const script = document.createElement('script');
    script.src = `https://www.paypal.com/sdk/js?client-id=${clientId}&currency=${currency}&components=buttons&disable-funding=venmo,credit,card`;
    script.async = true;

    script.onload = () => {
      window.__hpPayPalLoading = false;
      const callbacks = window.__hpPayPalCallbacks || [];
      window.__hpPayPalCallbacks = [];
      callbacks.forEach(cb => cb(window.paypal || null));
    };

    script.onerror = () => {
      window.__hpPayPalLoading = false;
      window.__hpPayPalClientId = undefined;
      const callbacks = window.__hpPayPalCallbacks || [];
      window.__hpPayPalCallbacks = [];
      callbacks.forEach(cb => cb(null));
    };

    document.head.appendChild(script);
  });
}

export function usePayPalPayment(options: UsePayPalPaymentOptions) {
  const { clientId, currency = 'USD', onCreateOrder, onPaymentSuccess, onPaymentError } = options;

  const [isLoading, setIsLoading] = useState(true);
  const [isProcessing, setIsProcessing] = useState(false);
  const [isAvailable, setIsAvailable] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const paypalRef = useRef<PayPalNamespace | null>(null);
  const buttonsRef = useRef<PayPalButtonsComponent | null>(null);
  const isMountedRef = useRef(false);
  const containerRef = useRef<HTMLElement | null>(null);

  // Use refs for callbacks to avoid recreating functions
  const onCreateOrderRef = useRef(onCreateOrder);
  const onPaymentSuccessRef = useRef(onPaymentSuccess);
  const onPaymentErrorRef = useRef(onPaymentError);
  onCreateOrderRef.current = onCreateOrder;
  onPaymentSuccessRef.current = onPaymentSuccess;
  onPaymentErrorRef.current = onPaymentError;

  // Load PayPal SDK
  useEffect(() => {
    if (!clientId) {
      setIsLoading(false);
      setIsAvailable(false);
      return;
    }

    let cancelled = false;

    loadPayPalSingleton(clientId, currency).then((paypal) => {
      if (cancelled) return;

      if (paypal) {
        paypalRef.current = paypal;
        setIsAvailable(true);
      } else {
        setError('Failed to load PayPal');
        setIsAvailable(false);
      }
      setIsLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [clientId, currency]);

  // Mount PayPal button
  const mountPayPalButton = useCallback((container: HTMLElement) => {
    if (isMountedRef.current || !paypalRef.current) {
      return;
    }

    isMountedRef.current = true;
    containerRef.current = container;

    const restBase = (window as any).hpRwConfig?.restUrl || '/wp-json';

    buttonsRef.current = paypalRef.current.Buttons({
      style: {
        layout: 'horizontal',
        color: 'black',      // Match dark theme of checkout
        shape: 'rect',
        height: 48,          // Match Stripe Express Checkout button height
        label: 'pay',
        tagline: false,
      },
      fundingSource: paypalRef.current.FUNDING.PAYPAL,

      createOrder: async () => {
        setIsProcessing(true);
        setError(null);

        try {
          const orderData = await onCreateOrderRef.current();

          const response = await fetch(`${restBase}/hp-rw/v1/paypal/create-order`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              funnel_id: orderData.funnelId,
              funnel_name: orderData.funnelName,
              customer: orderData.customer,
              shipping_address: orderData.shippingAddress,
              items: orderData.items,
              selected_rate: orderData.selectedRate,
              points_to_redeem: orderData.pointsToRedeem,
              offer_total: orderData.offerTotal,
            }),
          });

          const result = await response.json();

          if (!response.ok || !result.paypal_order_id) {
            throw new Error(result.message || 'Failed to create PayPal order');
          }

          return result.paypal_order_id;
        } catch (err: any) {
          const message = err.message || 'Failed to create order';
          setError(message);
          setIsProcessing(false);
          throw err;
        }
      },

      onApprove: async (data) => {
        try {
          const response = await fetch(`${restBase}/hp-rw/v1/paypal/capture-order`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              paypal_order_id: data.orderID,
            }),
          });

          const result = await response.json();

          if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to capture payment');
          }

          if (onPaymentSuccessRef.current) {
            onPaymentSuccessRef.current(result.order_id, result.order_number);
          }
        } catch (err: any) {
          const message = err.message || 'Payment capture failed';
          setError(message);
          if (onPaymentErrorRef.current) {
            onPaymentErrorRef.current(message);
          }
        } finally {
          setIsProcessing(false);
        }
      },

      onCancel: () => {
        setIsProcessing(false);
        setError(null);
      },

      onError: (err) => {
        const message = err.message || 'PayPal encountered an error';
        setError(message);
        setIsProcessing(false);
        if (onPaymentErrorRef.current) {
          onPaymentErrorRef.current(message);
        }
      },
    });

    // Check if PayPal button is eligible before rendering
    if (buttonsRef.current.isEligible()) {
      buttonsRef.current.render(container).catch((err) => {
        console.error('[usePayPalPayment] Failed to render PayPal button:', err);
        setIsAvailable(false);
      });
    } else {
      setIsAvailable(false);
    }
  }, []);

  // Unmount PayPal button
  const unmountPayPalButton = useCallback(() => {
    if (buttonsRef.current) {
      buttonsRef.current.close().catch(() => {});
      buttonsRef.current = null;
    }
    if (containerRef.current) {
      containerRef.current.innerHTML = '';
      containerRef.current = null;
    }
    isMountedRef.current = false;
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      unmountPayPalButton();
    };
  }, [unmountPayPalButton]);

  return useMemo(() => ({
    isLoading,
    isProcessing,
    isAvailable,
    error,
    mountPayPalButton,
    unmountPayPalButton,
  }), [isLoading, isProcessing, isAvailable, error, mountPayPalButton, unmountPayPalButton]);
}
