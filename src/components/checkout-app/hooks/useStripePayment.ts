import { useState, useCallback, useEffect, useRef, useMemo } from 'react';

// Stripe types (basic definitions - full types from @stripe/stripe-js if needed)
interface Stripe {
  elements: (options?: any) => StripeElements;
  confirmPayment: (options: any) => Promise<{ error?: any; paymentIntent?: any }>;
}

interface StripeElements {
  create: (type: string, options?: any) => StripeElement;
  getElement: (type: string) => StripeElement | null;
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
  }
}

interface UseStripePaymentOptions {
  publishableKey: string;
  onPaymentSuccess?: (paymentIntentId: string) => void;
  onPaymentError?: (error: string) => void;
}

export function useStripePayment(options: UseStripePaymentOptions) {
  const { publishableKey, onPaymentSuccess, onPaymentError } = options;
  
  const [isLoading, setIsLoading] = useState(true);
  const [isProcessing, setIsProcessing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isCardComplete, setIsCardComplete] = useState(false);
  
  const stripeRef = useRef<Stripe | null>(null);
  const elementsRef = useRef<StripeElements | null>(null);
  const cardElementRef = useRef<StripeElement | null>(null);
  
  // Use refs for callbacks to avoid recreating confirmPayment on every render
  const onPaymentSuccessRef = useRef(onPaymentSuccess);
  const onPaymentErrorRef = useRef(onPaymentError);
  onPaymentSuccessRef.current = onPaymentSuccess;
  onPaymentErrorRef.current = onPaymentError;

  // Load Stripe.js
  useEffect(() => {
    if (!publishableKey) {
      setIsLoading(false);
      return;
    }

    const loadStripe = async () => {
      // Check if Stripe is already loaded
      if (window.Stripe) {
        stripeRef.current = window.Stripe(publishableKey);
        setIsLoading(false);
        return;
      }

      // Load Stripe.js script
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;
      
      script.onload = () => {
        if (window.Stripe) {
          stripeRef.current = window.Stripe(publishableKey);
        }
        setIsLoading(false);
      };

      script.onerror = () => {
        setError('Failed to load payment system');
        setIsLoading(false);
      };

      document.head.appendChild(script);
    };

    loadStripe();
  }, [publishableKey]);

  // Mount card element
  const mountCardElement = useCallback((container: HTMLElement | string) => {
    if (!stripeRef.current) {
      console.error('[useStripePayment] Stripe not loaded');
      return;
    }

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
  }, []);

  // Unmount card element
  const unmountCardElement = useCallback(() => {
    if (cardElementRef.current) {
      cardElementRef.current.destroy();
      cardElementRef.current = null;
    }
    elementsRef.current = null;
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
  }), [isLoading, isProcessing, isCardComplete, error, mountCardElement, unmountCardElement, confirmPayment, isReady]);
}

