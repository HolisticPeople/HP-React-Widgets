import { useState, useCallback, useEffect, useRef, useMemo } from 'react';

// Stripe types (basic definitions - full types from @stripe/stripe-js if needed)
interface Stripe {
  elements: (options?: any) => StripeElements;
  confirmPayment: (options: any) => Promise<{ error?: any; paymentIntent?: any }>;
}

interface StripeElements {
  create: (type: string, options?: any) => StripeElement;
  getElement: (type: string) => StripeElement | null;
  submit: () => Promise<{ error?: any }>;
}

interface ExpressCheckoutAvailablePaymentMethods {
  applePay?: boolean;
  googlePay?: boolean;
  link?: boolean;
}

interface StripeElement {
  mount: (element: string | HTMLElement) => void;
  unmount: () => void;
  on: (event: string, handler: (event: any) => void) => void;
  destroy: () => void;
}

declare global {
  interface Window {
    Stripe?: (key: string) => Stripe;
    __hpStripeInstance?: Stripe;
    __hpStripeKey?: string;
    __hpStripeLoading?: boolean;
    __hpStripeCallbacks?: ((stripe: Stripe | null) => void)[];
  }
}

interface UseStripePaymentOptions {
  publishableKey: string;
  stripeMode?: string;
  onPaymentSuccess?: (paymentIntentId: string) => void;
  onPaymentError?: (error: string) => void;
  onExpressCheckoutClick?: (resolve: (params: { lineItems?: any[] }) => void) => void;
  onExpressCheckoutConfirm?: () => Promise<{ clientSecret: string; billingDetails: any } | null>;
}

// Global Stripe singleton loader - ensures only ONE Stripe instance exists
function loadStripeSingleton(publishableKey: string): Promise<Stripe | null> {
  return new Promise((resolve) => {
    // If already have an instance, return it
    if (window.__hpStripeInstance && window.__hpStripeKey === publishableKey) {
      resolve(window.__hpStripeInstance);
      return;
    }

    // If we have an instance but key changed, recreate with the new key (mode switch)
    if (window.__hpStripeInstance && window.__hpStripeKey && window.__hpStripeKey !== publishableKey && window.Stripe) {
      window.__hpStripeInstance = window.Stripe(publishableKey);
      window.__hpStripeKey = publishableKey;
      resolve(window.__hpStripeInstance);
      return;
    }

    // If currently loading, queue up the callback
    if (window.__hpStripeLoading) {
      window.__hpStripeCallbacks = window.__hpStripeCallbacks || [];
      window.__hpStripeCallbacks.push(resolve);
      return;
    }

    // Check if Stripe is already loaded (by another script)
    if (window.Stripe) {
      window.__hpStripeInstance = window.Stripe(publishableKey);
      window.__hpStripeKey = publishableKey;
      resolve(window.__hpStripeInstance);
      return;
    }

    // Start loading
    window.__hpStripeLoading = true;
    window.__hpStripeCallbacks = [resolve];

    const script = document.createElement('script');
    script.src = 'https://js.stripe.com/v3/';
    script.async = true;

    script.onload = () => {
      if (window.Stripe) {
        window.__hpStripeInstance = window.Stripe(publishableKey);
        window.__hpStripeKey = publishableKey;
      }
      window.__hpStripeLoading = false;
      
      // Notify all waiting callbacks
      const callbacks = window.__hpStripeCallbacks || [];
      window.__hpStripeCallbacks = [];
      callbacks.forEach(cb => cb(window.__hpStripeInstance || null));
    };

    script.onerror = () => {
      window.__hpStripeLoading = false;
      const callbacks = window.__hpStripeCallbacks || [];
      window.__hpStripeCallbacks = [];
      callbacks.forEach(cb => cb(null));
    };

    document.head.appendChild(script);
  });
}

export function useStripePayment(options: UseStripePaymentOptions) {
  const { publishableKey, stripeMode, onPaymentSuccess, onPaymentError, onExpressCheckoutClick, onExpressCheckoutConfirm } = options;
  
  const [isLoading, setIsLoading] = useState(true);
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isCardComplete, setIsCardComplete] = useState(false);
  const [expressCheckoutAvailable, setExpressCheckoutAvailable] = useState<ExpressCheckoutAvailablePaymentMethods | null>(null);
  
  const stripeRef = useRef<Stripe | null>(null);
  const elementsRef = useRef<StripeElements | null>(null);
  const cardElementRef = useRef<StripeElement | null>(null);
  const expressCheckoutRef = useRef<StripeElement | null>(null);
  
  // Use refs for callbacks to avoid recreating confirmPayment on every render
  const onPaymentSuccessRef = useRef(onPaymentSuccess);
  const onPaymentErrorRef = useRef(onPaymentError);
  const onExpressCheckoutClickRef = useRef(onExpressCheckoutClick);
  const onExpressCheckoutConfirmRef = useRef(onExpressCheckoutConfirm);
  onPaymentSuccessRef.current = onPaymentSuccess;
  onPaymentErrorRef.current = onPaymentError;
  onExpressCheckoutClickRef.current = onExpressCheckoutClick;
  onExpressCheckoutConfirmRef.current = onExpressCheckoutConfirm;

  // Load Stripe.js using singleton pattern
  useEffect(() => {
    if (!publishableKey) {
      console.warn('[useStripePayment] Missing publishableKey!');
      setError('Payment is not configured (missing Stripe publishable key).');
      setIsLoading(false);
      return;
    }

    let cancelled = false;

    loadStripeSingleton(publishableKey).then((stripe) => {
      if (cancelled) return;
      
      if (stripe) {
        stripeRef.current = stripe;
      } else {
        setError('Failed to load payment system');
      }
      setIsLoading(false);
    });

    return () => {
      cancelled = true;
    };
  }, [publishableKey]);

  // Track if already mounted to prevent duplicate mounts
  const isMountedRef = useRef(false);

  // Mount card element
  const mountCardElement = useCallback((container: HTMLElement | string) => {
    // Prevent duplicate mounts
    if (isMountedRef.current) {
      return;
    }

    if (!stripeRef.current) {
      return;
    }

    isMountedRef.current = true;

    // Create Elements instance with deferred PaymentIntent mode
    // 'mode: payment' allows us to collect payment details before creating a PaymentIntent
    elementsRef.current = stripeRef.current.elements({
      mode: 'payment',
      amount: 1000, // Placeholder - will be updated when we have final amount
      currency: 'usd',
      appearance: {
        theme: 'night',
        variables: {
          colorPrimary: 'hsl(45, 95%, 60%)',
          colorBackground: 'hsl(240, 10%, 10%)',
          colorText: 'hsl(0, 0%, 95%)',
          colorDanger: 'hsl(0, 84%, 60%)',
          fontFamily: 'system-ui, sans-serif',
          borderRadius: '8px',
        },
      },
    });

    // Create card element
    cardElementRef.current = elementsRef.current.create('payment', {
      layout: 'tabs',
    });

    // Mount to container
    cardElementRef.current.mount(container);

    // Listen for changes
    cardElementRef.current.on('change', (event: any) => {
      setIsCardComplete(event.complete);
      if (event.error) {
        setError(event.error.message);
      } else {
        setError(null);
      }
    });
  }, [stripeMode]);

  // Unmount card element
  const unmountCardElement = useCallback(() => {
    if (cardElementRef.current) {
      cardElementRef.current.destroy();
      cardElementRef.current = null;
    }
    if (expressCheckoutRef.current) {
      expressCheckoutRef.current.destroy();
      expressCheckoutRef.current = null;
    }
    elementsRef.current = null;
    isMountedRef.current = false;
    setExpressCheckoutAvailable(null);
  }, []);

  // Track if Express Checkout is already mounted
  const isExpressCheckoutMountedRef = useRef(false);

  // Mount Express Checkout Element (Apple Pay, Google Pay, etc.)
  const mountExpressCheckout = useCallback((container: HTMLElement | string) => {
    // Prevent duplicate mounts
    if (isExpressCheckoutMountedRef.current || !elementsRef.current) {
      return;
    }

    isExpressCheckoutMountedRef.current = true;

    // Create Express Checkout Element
    expressCheckoutRef.current = elementsRef.current.create('expressCheckout', {
      buttonType: {
        applePay: 'buy',
        googlePay: 'buy',
      },
      buttonHeight: 48,
      buttonTheme: {
        applePay: 'black',
        googlePay: 'black',
      },
    });

    // Mount to container
    expressCheckoutRef.current.mount(container);

    // Listen for ready event to check which wallets are available
    expressCheckoutRef.current.on('ready', (event: { availablePaymentMethods?: ExpressCheckoutAvailablePaymentMethods }) => {
      if (event.availablePaymentMethods) {
        setExpressCheckoutAvailable(event.availablePaymentMethods);
      } else {
        setExpressCheckoutAvailable(null);
      }
    });

    // Handle click event - resolve with payment details when wallet sheet opens
    expressCheckoutRef.current.on('click', (event: { resolve: (params?: { lineItems?: any[] }) => void }) => {
      if (onExpressCheckoutClickRef.current) {
        onExpressCheckoutClickRef.current(event.resolve);
      } else {
        // Default: just resolve without line items
        event.resolve();
      }
    });

    // Handle confirm event - when user authorizes payment in wallet
    expressCheckoutRef.current.on('confirm', async () => {
      if (!onExpressCheckoutConfirmRef.current || !stripeRef.current || !elementsRef.current) {
        return;
      }

      setIsProcessing(true);
      setError(null);

      try {
        // Get client secret and billing details from parent
        const result = await onExpressCheckoutConfirmRef.current();
        if (!result) {
          setError('Failed to prepare payment');
          setIsProcessing(false);
          return;
        }

        const { clientSecret, billingDetails } = result;

        // Confirm payment with Stripe
        const { error: confirmError, paymentIntent } = await stripeRef.current.confirmPayment({
          elements: elementsRef.current,
          clientSecret,
          confirmParams: {
            payment_method_data: {
              billing_details: billingDetails,
            },
            return_url: window.location.href,
          },
          redirect: 'if_required',
        });

        if (confirmError) {
          setError(confirmError.message || 'Payment failed');
          if (onPaymentErrorRef.current) {
            onPaymentErrorRef.current(confirmError.message || 'Payment failed');
          }
          setIsProcessing(false);
          return;
        }

        if (paymentIntent?.status === 'succeeded') {
          if (onPaymentSuccessRef.current) {
            onPaymentSuccessRef.current(paymentIntent.id);
          }
        } else {
          setError('Payment was not completed');
        }
      } catch (err: any) {
        const message = err.message || 'An unexpected error occurred';
        setError(message);
        if (onPaymentErrorRef.current) {
          onPaymentErrorRef.current(message);
        }
      } finally {
        setIsProcessing(false);
      }
    });
  }, []);

  // Unmount Express Checkout Element only
  const unmountExpressCheckout = useCallback(() => {
    if (expressCheckoutRef.current) {
      expressCheckoutRef.current.destroy();
      expressCheckoutRef.current = null;
    }
    isExpressCheckoutMountedRef.current = false;
    setExpressCheckoutAvailable(null);
  }, []);

  // Confirm payment
  const confirmPayment = useCallback(async (
    clientSecret: string,
    billingDetails: {
      name: string;
      email: string;
      phone?: string;
      address?: {
        line1: string;
        line2?: string;
        city: string;
        state: string;
        postal_code: string;
        country: string;
      };
    }
  ): Promise<boolean> => {
    if (!stripeRef.current || !elementsRef.current) {
      setError('Payment system not ready');
      return false;
    }

    setIsProcessing(true);
    setError(null);

    try {
      // Required for Deferred Intent flow (using 'mode' in elements() initialization)
      const { error: submitError } = await elementsRef.current.submit();
      if (submitError) {
        setError(submitError.message || 'Validation failed');
        setIsProcessing(false);
        return false;
      }

      const { error: confirmError, paymentIntent } = await stripeRef.current.confirmPayment({
        elements: elementsRef.current,
        clientSecret,
        confirmParams: {
          payment_method_data: {
            billing_details: billingDetails,
          },
          return_url: window.location.href, // Fallback for redirect-based methods
        },
        redirect: 'if_required', // Handle 3DS inline when possible
      });

      if (confirmError) {
        setError(confirmError.message || 'Payment failed');
        if (onPaymentErrorRef.current) {
          onPaymentErrorRef.current(confirmError.message || 'Payment failed');
        }
        return false;
      }

      if (paymentIntent?.status === 'succeeded') {
        if (onPaymentSuccessRef.current) {
          onPaymentSuccessRef.current(paymentIntent.id);
        }
        return true;
      }

      // Handle other statuses
      if (paymentIntent?.status === 'requires_action') {
        // 3DS should be handled automatically, but if we get here something went wrong
        setError('Additional authentication required. Please try again.');
        return false;
      }

      setError('Payment was not completed');
      return false;
    } catch (err: any) {
      const message = err.message || 'An unexpected error occurred';
      setError(message);
      if (onPaymentErrorRef.current) {
        onPaymentErrorRef.current(message);
      }
      return false;
    } finally {
      setIsProcessing(false);
    }
  }, []); // Removed callbacks - using refs instead

  const isReady = !isLoading && !!stripeRef.current;

  // Check if any Express Checkout wallet is available
  const hasExpressCheckout = expressCheckoutAvailable 
    && (expressCheckoutAvailable.applePay || expressCheckoutAvailable.googlePay || expressCheckoutAvailable.link);

  // Memoize return object to prevent unnecessary re-renders in consumers
  return useMemo(() => ({
    isLoading,
    isProcessing,
    isCardComplete,
    error,
    mountCardElement,
    unmountCardElement,
    confirmPayment,
    isReady,
    // Express Checkout
    mountExpressCheckout,
    unmountExpressCheckout,
    expressCheckoutAvailable,
    hasExpressCheckout,
  }), [isLoading, isProcessing, isCardComplete, error, mountCardElement, unmountCardElement, confirmPayment, isReady, mountExpressCheckout, unmountExpressCheckout, expressCheckoutAvailable, hasExpressCheckout]);
}

