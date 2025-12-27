<?php
namespace HP_RW\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal Stripe API wrapper for HP React Widgets checkout.
 *
 * IMPORTANT:
 * - Mode can be forced per funnel ('test' | 'live'), otherwise defaults to WooCommerce Stripe "testmode".
 * - This class is used by REST endpoints (CheckoutApi/UpsellApi) and order creation metadata.
 */
class StripeService
{
    public string $mode = 'test'; // 'test' | 'live'
    public string $publishable = '';
    private string $secret = '';
    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct(?string $mode = null)
    {
        $this->mode = $this->normalizeMode($mode);

        // Prefer WooCommerce Stripe Gateway settings (most common)
        $stripeSettings = get_option('woocommerce_stripe_settings', []);
        $stripeApiSettings = get_option('woocommerce_stripe_api_settings', []);

        if ($this->mode === 'test') {
            $this->publishable = (string) ($stripeSettings['test_publishable_key'] ?: ($stripeApiSettings['publishable_key_test'] ?? ''));
            $this->secret = (string) ($stripeSettings['test_secret_key'] ?: ($stripeApiSettings['secret_key_test'] ?? ''));
        } else {
            $this->publishable = (string) ($stripeSettings['publishable_key'] ?: ($stripeApiSettings['publishable_key_live'] ?? ''));
            $this->secret = (string) ($stripeSettings['secret_key'] ?: ($stripeApiSettings['secret_key_live'] ?? ''));
        }
    }

    public function isConfigured(): bool
    {
        return $this->publishable !== '' && $this->secret !== '';
    }

    /**
     * Create or get Stripe customer. Stores per-mode customer id on WP user if available.
     */
    public function createOrGetCustomer(string $email, string $name = '', int $userId = 0): string
    {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        if ($userId > 0) {
            $metaKey = $this->mode === 'live' ? '_hp_rw_stripe_customer_id_live' : '_hp_rw_stripe_customer_id_test';
            $existing = (string) get_user_meta($userId, $metaKey, true);
            if ($existing !== '') {
                return $existing;
            }
        }

        $res = $this->request('POST', '/customers', [
            'email' => $email,
            'name'  => $name,
        ]);

        $id = (string) ($res['id'] ?? '');
        if ($id !== '' && $userId > 0) {
            $metaKey = $this->mode === 'live' ? '_hp_rw_stripe_customer_id_live' : '_hp_rw_stripe_customer_id_test';
            update_user_meta($userId, $metaKey, $id);
        }

        return $id;
    }

    public function createPaymentIntent(array $params): ?array
    {
        $res = $this->request('POST', '/payment_intents', $params);
        return is_array($res) ? $res : null;
    }

    public function retrievePaymentIntent(string $piId): ?array
    {
        $piId = sanitize_text_field($piId);
        if ($piId === '') return null;
        $res = $this->request('GET', '/payment_intents/' . rawurlencode($piId));
        return is_array($res) ? $res : null;
    }

    public function updatePaymentIntent(string $piId, array $params): ?array
    {
        $piId = sanitize_text_field($piId);
        if ($piId === '') return null;
        $res = $this->request('POST', '/payment_intents/' . rawurlencode($piId), $params);
        return is_array($res) ? $res : null;
    }

    public function retrieveCustomer(string $customerId): ?array
    {
        $customerId = sanitize_text_field($customerId);
        if ($customerId === '') return null;
        $res = $this->request('GET', '/customers/' . rawurlencode($customerId));
        return is_array($res) ? $res : null;
    }

    public function listPaymentMethods(string $customerId, string $type = 'card'): array
    {
        $customerId = sanitize_text_field($customerId);
        $type = sanitize_text_field($type) ?: 'card';
        if ($customerId === '') return [];
        $res = $this->request('GET', '/payment_methods', [
            'customer' => $customerId,
            'type'     => $type,
        ]);
        $data = $res['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    /**
     * Create an off-session PaymentIntent and confirm it immediately.
     * Returns Stripe PI payload, or ['error' => ...] on error.
     */
    public function chargeOffSession(
        string $customerId,
        string $paymentMethodId,
        int $amountCents,
        string $currency,
        array $metadata = []
    ): array {
        $customerId = sanitize_text_field($customerId);
        $paymentMethodId = sanitize_text_field($paymentMethodId);
        $currency = sanitize_text_field($currency) ?: 'usd';
        $amountCents = max(1, (int) $amountCents);

        return $this->request('POST', '/payment_intents', [
            'amount' => $amountCents,
            'currency' => $currency,
            'customer' => $customerId,
            'payment_method' => $paymentMethodId,
            'confirm' => 'true',
            'off_session' => 'true',
            'payment_method_types[]' => 'card',
            'metadata' => $metadata,
        ]);
    }

    private function normalizeMode(?string $mode): string
    {
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';
        if ($mode === 'live' || $mode === 'test') {
            return $mode;
        }

        // Default behavior (backward compatible): follow WC Stripe Gateway "testmode"
        $stripeSettings = get_option('woocommerce_stripe_settings', []);
        $wcTestMode = !empty($stripeSettings['testmode']) && $stripeSettings['testmode'] === 'yes';
        return $wcTestMode ? 'test' : 'live';
    }

    /**
     * Stripe API request helper using WP HTTP API.
     *
     * @return array Stripe response array, or ['error' => ['message' => ...]] for failures.
     */
    private function request(string $method, string $path, array $params = []): array
    {
        if (!$this->isConfigured()) {
            return ['error' => ['message' => 'Stripe keys are missing']];
        }

        $url = rtrim($this->apiBase, '/') . $path;
        $args = [
            'method'  => strtoupper($method),
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secret,
            ],
        ];

        // Stripe expects application/x-www-form-urlencoded (nested arrays supported via http_build_query)
        if ($args['method'] === 'GET') {
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }
        } else {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query($params);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return ['error' => ['message' => $response->get_error_message()]];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = [];
        }

        if ($code >= 400) {
            // Stripe error payload format: { error: { message, type, ... } }
            if (!empty($data['error'])) {
                return $data;
            }
            return ['error' => ['message' => 'Stripe request failed', 'status' => $code]];
        }

        return $data;
    }
}




