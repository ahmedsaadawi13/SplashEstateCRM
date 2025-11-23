<?php
// FILE: /app/helpers/StripeService.php

/**
 * SplashEstate CRM - Stripe Service
 * Standalone Stripe API integration for payment processing
 */

class StripeService {

    private $apiKey;
    private $apiVersion = '2023-10-16';
    private $apiBase = 'https://api.stripe.com/v1';

    /**
     * Constructor
     */
    public function __construct($apiKey = null) {
        $this->apiKey = $apiKey ?: (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '');

        if (empty($this->apiKey)) {
            throw new Exception('Stripe API key is required');
        }
    }

    /**
     * Create a customer
     * @param array $data Customer data (email, name, description)
     * @return array Customer object
     */
    public function createCustomer($data) {
        return $this->request('POST', '/customers', array(
            'email' => $data['email'],
            'name' => isset($data['name']) ? $data['name'] : null,
            'description' => isset($data['description']) ? $data['description'] : null,
            'metadata' => isset($data['metadata']) ? $data['metadata'] : array()
        ));
    }

    /**
     * Get customer by ID
     * @param string $customerId Stripe customer ID
     * @return array Customer object
     */
    public function getCustomer($customerId) {
        return $this->request('GET', '/customers/' . $customerId);
    }

    /**
     * Update customer
     * @param string $customerId Stripe customer ID
     * @param array $data Update data
     * @return array Customer object
     */
    public function updateCustomer($customerId, $data) {
        return $this->request('POST', '/customers/' . $customerId, $data);
    }

    /**
     * Create a payment method (card)
     * @param array $data Payment method data
     * @return array Payment method object
     */
    public function createPaymentMethod($data) {
        return $this->request('POST', '/payment_methods', array(
            'type' => 'card',
            'card' => array(
                'number' => $data['number'],
                'exp_month' => $data['exp_month'],
                'exp_year' => $data['exp_year'],
                'cvc' => $data['cvc']
            ),
            'billing_details' => isset($data['billing_details']) ? $data['billing_details'] : array()
        ));
    }

    /**
     * Attach payment method to customer
     * @param string $paymentMethodId Payment method ID
     * @param string $customerId Customer ID
     * @return array Payment method object
     */
    public function attachPaymentMethod($paymentMethodId, $customerId) {
        return $this->request('POST', '/payment_methods/' . $paymentMethodId . '/attach', array(
            'customer' => $customerId
        ));
    }

    /**
     * Set default payment method for customer
     * @param string $customerId Customer ID
     * @param string $paymentMethodId Payment method ID
     * @return array Customer object
     */
    public function setDefaultPaymentMethod($customerId, $paymentMethodId) {
        return $this->updateCustomer($customerId, array(
            'invoice_settings' => array(
                'default_payment_method' => $paymentMethodId
            )
        ));
    }

    /**
     * Create a subscription
     * @param array $data Subscription data
     * @return array Subscription object
     */
    public function createSubscription($data) {
        $params = array(
            'customer' => $data['customer'],
            'items' => array(
                array('price' => $data['price_id'])
            )
        );

        if (isset($data['trial_days'])) {
            $params['trial_period_days'] = $data['trial_days'];
        }

        if (isset($data['metadata'])) {
            $params['metadata'] = $data['metadata'];
        }

        return $this->request('POST', '/subscriptions', $params);
    }

    /**
     * Get subscription by ID
     * @param string $subscriptionId Subscription ID
     * @return array Subscription object
     */
    public function getSubscription($subscriptionId) {
        return $this->request('GET', '/subscriptions/' . $subscriptionId);
    }

    /**
     * Update subscription (change plan)
     * @param string $subscriptionId Subscription ID
     * @param array $data Update data
     * @return array Subscription object
     */
    public function updateSubscription($subscriptionId, $data) {
        $params = array();

        if (isset($data['price_id'])) {
            $params['items'] = array(
                array(
                    'id' => $data['subscription_item_id'],
                    'price' => $data['price_id']
                )
            );
            $params['proration_behavior'] = 'always_invoice';
        }

        if (isset($data['cancel_at_period_end'])) {
            $params['cancel_at_period_end'] = $data['cancel_at_period_end'];
        }

        return $this->request('POST', '/subscriptions/' . $subscriptionId, $params);
    }

    /**
     * Cancel subscription
     * @param string $subscriptionId Subscription ID
     * @param bool $immediately Cancel immediately or at period end
     * @return array Subscription object
     */
    public function cancelSubscription($subscriptionId, $immediately = false) {
        if ($immediately) {
            return $this->request('DELETE', '/subscriptions/' . $subscriptionId);
        } else {
            return $this->updateSubscription($subscriptionId, array(
                'cancel_at_period_end' => true
            ));
        }
    }

    /**
     * Create a price
     * @param array $data Price data
     * @return array Price object
     */
    public function createPrice($data) {
        return $this->request('POST', '/prices', array(
            'product' => $data['product_id'],
            'unit_amount' => $data['amount'] * 100, // Convert to cents
            'currency' => isset($data['currency']) ? $data['currency'] : 'usd',
            'recurring' => array(
                'interval' => isset($data['interval']) ? $data['interval'] : 'month'
            ),
            'metadata' => isset($data['metadata']) ? $data['metadata'] : array()
        ));
    }

    /**
     * List invoices for customer
     * @param string $customerId Customer ID
     * @param int $limit Number of invoices to retrieve
     * @return array Invoices list
     */
    public function listInvoices($customerId, $limit = 10) {
        return $this->request('GET', '/invoices', array(
            'customer' => $customerId,
            'limit' => $limit
        ));
    }

    /**
     * Get invoice by ID
     * @param string $invoiceId Invoice ID
     * @return array Invoice object
     */
    public function getInvoice($invoiceId) {
        return $this->request('GET', '/invoices/' . $invoiceId);
    }

    /**
     * Create payment intent (one-time payment)
     * @param array $data Payment data
     * @return array Payment intent object
     */
    public function createPaymentIntent($data) {
        return $this->request('POST', '/payment_intents', array(
            'amount' => $data['amount'] * 100, // Convert to cents
            'currency' => isset($data['currency']) ? $data['currency'] : 'usd',
            'customer' => isset($data['customer']) ? $data['customer'] : null,
            'payment_method' => isset($data['payment_method']) ? $data['payment_method'] : null,
            'description' => isset($data['description']) ? $data['description'] : null,
            'metadata' => isset($data['metadata']) ? $data['metadata'] : array()
        ));
    }

    /**
     * List payment methods for customer
     * @param string $customerId Customer ID
     * @return array Payment methods list
     */
    public function listPaymentMethods($customerId) {
        return $this->request('GET', '/payment_methods', array(
            'customer' => $customerId,
            'type' => 'card'
        ));
    }

    /**
     * Verify webhook signature
     * @param string $payload Webhook payload
     * @param string $signature Stripe signature header
     * @param string $secret Webhook secret
     * @return bool Valid signature
     */
    public function verifyWebhookSignature($payload, $signature, $secret) {
        $elements = explode(',', $signature);
        $timestamp = null;
        $signatures = array();

        foreach ($elements as $element) {
            list($key, $value) = explode('=', $element, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make API request to Stripe
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @return array Response data
     */
    private function request($method, $endpoint, $params = array()) {
        $url = $this->apiBase . $endpoint;

        $ch = curl_init();

        // Set request method and data
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        // Set headers
        $headers = array(
            'Authorization: Bearer ' . $this->apiKey,
            'Stripe-Version: ' . $this->apiVersion,
            'Content-Type: application/x-www-form-urlencoded'
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('Stripe API request failed: ' . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            throw new Exception('Stripe API error: ' . $errorMessage);
        }

        return $data;
    }

    /**
     * Test API connection
     * @return array Test result
     */
    public function testConnection() {
        try {
            // Try to list customers (limit 1 to minimize load)
            $this->request('GET', '/customers', array('limit' => 1));

            return array(
                'success' => true,
                'message' => 'Stripe API connection successful'
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
