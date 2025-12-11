<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stripe service for payment operations.
 * 
 * Reads Stripe credentials from EAO plugin settings.
 * Supports test/live mode selection and off-session charges for one-click upsells.
 */
class StripeService
{
    private string $secret;
    public string $publishable;
    public string $mode = 'test'; // 'test' or 'live'

    /**
     * @param string|null $modeOverride 'test' | 'live' to force a mode, or null to derive from settings
     */
    public function __construct(?string $modeOverride = null)
    {
        $opts = get_option('hp_rw_settings', []);
        $env = isset($opts['env']) && $opts['env'] === 'production' ? 'production' : 'staging';
        $eao = get_option('eao_stripe_settings', []);
        
        $useMode = $modeOverride;
        if ($useMode !== 'test' && $useMode !== 'live') {
            // Derive from global environment
            $useMode = ($env === 'production') ? 'live' : 'test';
        }
        
        if ($useMode === 'test') {
            $this->secret = (string) ($eao['test_secret'] ?? '');
            $this->publishable = (string) ($eao['test_publishable'] ?? '');
            $this->mode = 'test';
            // If test keys missing, fallback to live
            if ($this->secret === '' || $this->publishable === '') {
                $this->secret = (string) ($eao['live_secret'] ?? '');
                $this->publishable = (string) ($eao['live_publishable'] ?? '');
                $this->mode = 'live';
            }
        } else {
            $this->secret = (string) ($eao['live_secret'] ?? '');
            $this->publishable = (string) ($eao['live_publishable'] ?? '');
            $this->mode = 'live';
        }
    }

    /**
     * Check if Stripe is properly configured.
     */
    public function isConfigured(): bool
    {
        return $this->secret !== '' && $this->publishable !== '';
    }

    /**
     * Get headers for Stripe API requests.
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secret,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Create or retrieve an existing Stripe customer.
     * 
     * @param string $email Customer email
     * @param string $name Customer name
     * @param int $userId WordPress user ID (0 for guests)
     * @return string|null Stripe customer ID or null on failure
     */
    public function createOrGetCustomer(string $email, string $name = '', int $userId = 0): ?string
    {
        // Reuse stored customer id if set
        if ($userId > 0) {
            $metaKeyCurrent = $this->mode === 'live' 
                ? '_hp_rw_stripe_customer_id_live' 
                : '_hp_rw_stripe_customer_id_test';
            $existing = get_user_meta($userId, $metaKeyCurrent, true);
            
            // Back-compat: check legacy meta key
            if (!$existing) {
                $legacy = get_user_meta($userId, '_hp_fb_stripe_customer_id', true);
                if (is_string($legacy) && $legacy !== '' && $this->mode === 'live') {
                    $existing = $legacy;
                }
            }
            
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }
        
        $body = ['email' => $email];
        if ($name !== '') {
            $body['name'] = $name;
        }
        
        $resp = wp_remote_post('https://api.stripe.com/v1/customers', [
            'headers' => $this->headers(),
            'body'    => $body,
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($data) || empty($data['id'])) {
            return null;
        }
        
        $cus = (string) $data['id'];
        if ($userId > 0) {
            $metaKeyCurrent = $this->mode === 'live' 
                ? '_hp_rw_stripe_customer_id_live' 
                : '_hp_rw_stripe_customer_id_test';
            update_user_meta($userId, $metaKeyCurrent, $cus);
        }
        
        return $cus;
    }

    /**
     * Create a Stripe PaymentIntent.
     * 
     * @param array $params PaymentIntent parameters
     * @return array|null PaymentIntent data or null on failure
     */
    public function createPaymentIntent(array $params): ?array
    {
        $resp = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => $this->headers(),
            'body'    => $params,
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Retrieve an existing PaymentIntent.
     * 
     * @param string $pi PaymentIntent ID
     * @return array|null PaymentIntent data or null on failure
     */
    public function retrievePaymentIntent(string $pi): ?array
    {
        $resp = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . rawurlencode($pi), [
            'headers' => ['Authorization' => 'Bearer ' . $this->secret],
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Update a PaymentIntent with provided fields.
     * 
     * @param string $piId PaymentIntent ID
     * @param array $fields Fields to update
     * @return bool Success status
     */
    public function updatePaymentIntent(string $piId, array $fields): bool
    {
        if ($piId === '' || empty($fields)) {
            return false;
        }
        
        $resp = wp_remote_post('https://api.stripe.com/v1/payment_intents/' . rawurlencode($piId), [
            'headers' => $this->headers(),
            'body'    => $fields,
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return false;
        }
        
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code >= 200 && $code < 300;
    }

    /**
     * Update a Stripe Charge.
     * 
     * @param string $chargeId Charge ID
     * @param array $fields Fields to update
     * @return bool Success status
     */
    public function updateCharge(string $chargeId, array $fields): bool
    {
        if ($chargeId === '' || empty($fields)) {
            return false;
        }
        
        $resp = wp_remote_post('https://api.stripe.com/v1/charges/' . rawurlencode($chargeId), [
            'headers' => $this->headers(),
            'body'    => $fields,
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return false;
        }
        
        $code = (int) wp_remote_retrieve_response_code($resp);
        return $code >= 200 && $code < 300;
    }

    /**
     * Retrieve a Stripe Customer.
     * 
     * @param string $customerId Customer ID
     * @return array|null Customer data or null on failure
     */
    public function retrieveCustomer(string $customerId): ?array
    {
        if ($customerId === '') {
            return null;
        }
        
        $resp = wp_remote_get('https://api.stripe.com/v1/customers/' . rawurlencode($customerId), [
            'headers' => ['Authorization' => 'Bearer ' . $this->secret],
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return null;
        }
        
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Charge a customer off-session using their saved payment method.
     * Used for one-click upsells after initial purchase.
     * 
     * @param string $customerId Stripe customer ID
     * @param string $paymentMethodId Saved payment method ID
     * @param int $amountCents Amount in cents
     * @param string $currency Currency code (default: usd)
     * @param array $metadata Additional metadata
     * @return array|null PaymentIntent data or null on failure
     */
    public function chargeOffSession(
        string $customerId,
        string $paymentMethodId,
        int $amountCents,
        string $currency = 'usd',
        array $metadata = []
    ): ?array {
        $params = [
            'amount'              => $amountCents,
            'currency'            => strtolower($currency),
            'customer'            => $customerId,
            'payment_method'      => $paymentMethodId,
            'off_session'         => 'true',
            'confirm'             => 'true',
            'payment_method_types[]' => 'card',
        ];
        
        foreach ($metadata as $key => $value) {
            $params['metadata[' . $key . ']'] = $value;
        }
        
        return $this->createPaymentIntent($params);
    }

    /**
     * List payment methods for a customer.
     * 
     * @param string $customerId Stripe customer ID
     * @param string $type Payment method type (default: card)
     * @return array List of payment methods
     */
    public function listPaymentMethods(string $customerId, string $type = 'card'): array
    {
        if ($customerId === '') {
            return [];
        }
        
        $url = 'https://api.stripe.com/v1/payment_methods?' . http_build_query([
            'customer' => $customerId,
            'type'     => $type,
        ]);
        
        $resp = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $this->secret],
            'timeout' => 25,
        ]);
        
        if (is_wp_error($resp)) {
            return [];
        }
        
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
    }
}


