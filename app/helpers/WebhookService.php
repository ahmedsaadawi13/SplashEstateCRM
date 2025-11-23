<?php
// FILE: /app/helpers/WebhookService.php

/**
 * SplashEstate CRM - Webhook Service
 * Handles webhook delivery and retry logic
 */

class WebhookService {

    private $webhookModel;
    private $logModel;
    private $timeout = 10; // seconds

    public function __construct() {
        require_once APP_PATH . '/models/Webhook.php';
        require_once APP_PATH . '/models/WebhookLog.php';

        $this->webhookModel = new Webhook();
        $this->logModel = new WebhookLog();
    }

    /**
     * Trigger webhook event
     * @param int $tenantId Tenant ID
     * @param string $eventType Event type (e.g., 'lead.created')
     * @param array $data Event data
     * @return bool Success
     */
    public function trigger($tenantId, $eventType, $data) {
        // Get active webhooks for this event
        $webhooks = $this->webhookModel->getActiveForEvent($tenantId, $eventType);

        if (empty($webhooks)) {
            return true; // No webhooks configured, no error
        }

        $success = true;

        foreach ($webhooks as $webhook) {
            $delivered = $this->deliver($webhook, $eventType, $data);
            if (!$delivered) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Deliver webhook to endpoint
     * @param array $webhook Webhook configuration
     * @param string $eventType Event type
     * @param array $data Event data
     * @param int $attempt Attempt number
     * @return bool Success
     */
    public function deliver($webhook, $eventType, $data, $attempt = 1) {
        // Build payload
        $payload = array(
            'event' => $eventType,
            'data' => $data,
            'timestamp' => time(),
            'webhook_id' => $webhook['id']
        );

        $payloadJson = json_encode($payload);

        // Generate signature if secret is configured
        $signature = null;
        if (!empty($webhook['secret'])) {
            $signature = $this->generateSignature($payloadJson, $webhook['secret']);
        }

        // Create log entry
        $logId = $this->logModel->create(array(
            'webhook_id' => $webhook['id'],
            'event_type' => $eventType,
            'payload' => $payloadJson,
            'attempt' => $attempt,
            'status' => 'pending'
        ));

        // Send HTTP request
        try {
            $response = $this->sendRequest($webhook['url'], $payloadJson, $signature);

            $isSuccess = $response['code'] >= 200 && $response['code'] < 300;

            // Update log
            $this->logModel->update($logId, array(
                'response_code' => $response['code'],
                'response_body' => substr($response['body'], 0, 5000), // Limit size
                'status' => $isSuccess ? 'success' : 'failed',
                'delivered_at' => date('Y-m-d H:i:s'),
                'error_message' => !$isSuccess ? $response['error'] : null
            ));

            return $isSuccess;

        } catch (Exception $e) {
            // Update log with error
            $this->logModel->update($logId, array(
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ));

            return false;
        }
    }

    /**
     * Retry failed webhooks
     * @return array Retry results
     */
    public function retryFailed() {
        $failedLogs = $this->logModel->getFailedForRetry();

        $results = array(
            'total' => count($failedLogs),
            'success' => 0,
            'failed' => 0
        );

        foreach ($failedLogs as $log) {
            $webhook = array(
                'id' => $log['webhook_id'],
                'url' => $log['url'],
                'secret' => $log['secret']
            );

            $data = json_decode($log['payload'], true);
            $eventData = isset($data['data']) ? $data['data'] : array();

            // Mark as retrying
            $this->logModel->update($log['id'], array('status' => 'retrying'));

            // Attempt delivery
            $success = $this->deliver($webhook, $log['event_type'], $eventData, $log['attempt'] + 1);

            if ($success) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Send HTTP request to webhook endpoint
     * @param string $url Webhook URL
     * @param string $payload JSON payload
     * @param string|null $signature Signature header
     * @return array Response with code, body, and error
     */
    private function sendRequest($url, $payload, $signature = null) {
        $ch = curl_init();

        $headers = array(
            'Content-Type: application/json',
            'User-Agent: SplashEstate-Webhook/1.0'
        );

        if ($signature) {
            $headers[] = 'X-Webhook-Signature: ' . $signature;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return array(
            'code' => $code,
            'body' => $body,
            'error' => $error
        );
    }

    /**
     * Generate HMAC signature for webhook
     * @param string $payload Payload JSON
     * @param string $secret Webhook secret
     * @return string Signature
     */
    private function generateSignature($payload, $secret) {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verify webhook signature
     * @param string $payload Payload JSON
     * @param string $signature Received signature
     * @param string $secret Webhook secret
     * @return bool Valid
     */
    public function verifySignature($payload, $signature, $secret) {
        $expected = $this->generateSignature($payload, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Test webhook endpoint
     * @param string $url Webhook URL
     * @param string|null $secret Optional secret
     * @return array Test result
     */
    public function testEndpoint($url, $secret = null) {
        $testData = array(
            'event' => 'webhook.test',
            'data' => array(
                'message' => 'This is a test webhook from SplashEstate CRM'
            ),
            'timestamp' => time(),
            'test' => true
        );

        $payload = json_encode($testData);
        $signature = $secret ? $this->generateSignature($payload, $secret) : null;

        try {
            $response = $this->sendRequest($url, $payload, $signature);

            $isSuccess = $response['code'] >= 200 && $response['code'] < 300;

            return array(
                'success' => $isSuccess,
                'status_code' => $response['code'],
                'response' => $response['body'],
                'error' => $response['error']
            );

        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
}
