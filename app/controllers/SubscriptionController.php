<?php
// FILE: /app/controllers/SubscriptionController.php

/**
 * SplashEstate CRM - Subscription Controller
 * Manages subscription plans and billing
 */

class SubscriptionController extends Controller {

    private $stripe;
    private $tenantModel;
    private $subscriptionModel;
    private $planModel;

    public function __construct() {
        $this->tenantModel = $this->model('Tenant');
        $this->subscriptionModel = $this->model('Subscription');
        $this->planModel = $this->model('Plan');

        // Initialize Stripe if configured
        if (defined('STRIPE_SECRET_KEY') && !empty(STRIPE_SECRET_KEY)) {
            require_once APP_PATH . '/helpers/StripeService.php';
            try {
                $this->stripe = new StripeService();
            } catch (Exception $e) {
                error_log("Stripe initialization error: " . $e->getMessage());
            }
        }
    }

    /**
     * View current subscription
     */
    public function index() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();

        // Get current subscription
        $subscription = $this->subscriptionModel->getActiveSubscription($tenantId);
        $tenant = $this->tenantModel->getById($tenantId);

        // Get current plan
        $currentPlan = null;
        if ($subscription) {
            $currentPlan = $this->planModel->getById($subscription['plan_id']);
        }

        // Get available plans
        $plans = $this->planModel->getActivePlans();

        // Get usage stats
        $usageStats = $this->subscriptionModel->getUsageStats($tenantId);
        $usage = array(
            'leads' => isset($usageStats['leads_count']) ? $usageStats['leads_count'] : 0,
            'properties' => isset($usageStats['properties_count']) ? $usageStats['properties_count'] : 0,
            'agents' => isset($usageStats['agents_count']) ? $usageStats['agents_count'] : 0
        );

        $data = array(
            'title' => 'Subscription & Billing',
            'subscription' => $subscription,
            'currentPlan' => $currentPlan,
            'tenant' => $tenant,
            'plans' => $plans,
            'usage' => $usage,
            'stripe_key' => defined('STRIPE_PUBLISHABLE_KEY') ? STRIPE_PUBLISHABLE_KEY : ''
        );

        $this->view('subscription/index', $data);
    }

    /**
     * Upgrade/change plan
     */
    public function changePlan() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();

        // Handle JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $newPlanId = isset($input['plan_id']) ? (int)$input['plan_id'] : 0;

        if (!$newPlanId) {
            $this->jsonResponse(array('error' => 'Invalid plan selected'), 400);
            return;
        }

        // Get new plan details
        $newPlan = $this->planModel->getById($newPlanId);
        if (!$newPlan || $newPlan['status'] !== 'active') {
            $this->jsonResponse(array('error' => 'Plan not found'), 404);
            return;
        }

        // Get current subscription
        $currentSubscription = $this->subscriptionModel->getActiveSubscription($tenantId);

        // Check if Stripe is configured
        if (!$this->stripe || empty(STRIPE_SECRET_KEY)) {
            // Free plan or no Stripe - update directly
            $this->updateSubscriptionDirect($tenantId, $newPlanId);
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Plan updated successfully'
            ));
            return;
        }

        // Handle Stripe subscription
        $tenant = $this->tenantModel->getById($tenantId);

        try {
            // Create or update Stripe customer
            if (empty($tenant['stripe_customer_id'])) {
                $customer = $this->stripe->createCustomer(array(
                    'email' => $tenant['email'],
                    'name' => $tenant['name'],
                    'metadata' => array(
                        'tenant_id' => $tenantId
                    )
                ));

                // Save customer ID
                $this->tenantModel->updateTenant($tenantId, array(
                    'stripe_customer_id' => $customer['id']
                ));

                $tenant['stripe_customer_id'] = $customer['id'];
            }

            // Handle payment method from form
            if (!empty($input['payment_method_id'])) {
                $this->stripe->attachPaymentMethod($input['payment_method_id'], $tenant['stripe_customer_id']);
                $this->stripe->setDefaultPaymentMethod($tenant['stripe_customer_id'], $input['payment_method_id']);
            }

            // Create or update subscription
            if (empty($tenant['stripe_subscription_id'])) {
                // Create new subscription
                $subscription = $this->stripe->createSubscription(array(
                    'customer' => $tenant['stripe_customer_id'],
                    'price_id' => $newPlan['stripe_price_id'],
                    'metadata' => array(
                        'tenant_id' => $tenantId,
                        'plan_id' => $newPlanId
                    )
                ));

                // Save subscription ID
                $this->tenantModel->updateTenant($tenantId, array(
                    'stripe_subscription_id' => $subscription['id']
                ));
            } else {
                // Update existing subscription
                $stripeSubscription = $this->stripe->getSubscription($tenant['stripe_subscription_id']);

                $this->stripe->updateSubscription($tenant['stripe_subscription_id'], array(
                    'price_id' => $newPlan['stripe_price_id'],
                    'subscription_item_id' => $stripeSubscription['items']['data'][0]['id']
                ));
            }

            // Update local subscription
            $this->updateSubscriptionDirect($tenantId, $newPlanId);

            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Subscription updated successfully'
            ));

        } catch (Exception $e) {
            error_log("Stripe subscription error: " . $e->getMessage());
            $this->jsonResponse(array(
                'error' => 'Payment failed: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $tenant = $this->tenantModel->getById($tenantId);

        try {
            $endDate = null;

            if ($this->stripe && !empty($tenant['stripe_subscription_id'])) {
                // Cancel at period end
                $subscription = $this->stripe->cancelSubscription($tenant['stripe_subscription_id'], false);
                if (isset($subscription['current_period_end'])) {
                    $endDate = date('F j, Y', $subscription['current_period_end']);
                }
            }

            // Update local subscription status
            $sql = "UPDATE tenant_subscriptions
                    SET status = 'cancelled', auto_renew = 0, updated_at = NOW()
                    WHERE tenant_id = :tenant_id AND status = 'active'";

            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute(array(':tenant_id' => $tenantId));

            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Subscription will be cancelled at the end of the billing period',
                'end_date' => $endDate
            ));

        } catch (Exception $e) {
            error_log("Subscription cancellation error: " . $e->getMessage());
            $this->jsonResponse(array(
                'error' => 'Failed to cancel subscription'
            ), 500);
        }
    }

    /**
     * Payment history
     */
    public function invoices() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $tenant = $this->tenantModel->getById($tenantId);

        $invoices = array();

        if ($this->stripe && !empty($tenant['stripe_customer_id'])) {
            try {
                $result = $this->stripe->listInvoices($tenant['stripe_customer_id'], 20);
                $invoices = isset($result['data']) ? $result['data'] : array();
            } catch (Exception $e) {
                error_log("Fetch invoices error: " . $e->getMessage());
            }
        }

        $data = array(
            'title' => 'Payment History',
            'invoices' => $invoices
        );

        $this->view('subscription/invoices', $data);
    }

    /**
     * Webhook handler for Stripe events
     */
    public function webhook() {
        // Get raw POST data
        $payload = @file_get_contents('php://input');
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

        if (!$this->stripe || empty(STRIPE_WEBHOOK_SECRET)) {
            http_response_code(400);
            echo json_encode(array('error' => 'Webhook not configured'));
            exit;
        }

        // Verify signature
        if (!$this->stripe->verifyWebhookSignature($payload, $signature, STRIPE_WEBHOOK_SECRET)) {
            http_response_code(400);
            echo json_encode(array('error' => 'Invalid signature'));
            exit;
        }

        // Parse event
        $event = json_decode($payload, true);

        // Handle different event types
        switch ($event['type']) {
            case 'customer.subscription.updated':
            case 'customer.subscription.created':
                $this->handleSubscriptionUpdate($event['data']['object']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event['data']['object']);
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;
        }

        http_response_code(200);
        echo json_encode(array('received' => true));
        exit;
    }

    /**
     * Handle subscription update webhook
     */
    private function handleSubscriptionUpdate($subscription) {
        $tenantId = isset($subscription['metadata']['tenant_id']) ? (int)$subscription['metadata']['tenant_id'] : 0;

        if (!$tenantId) {
            return;
        }

        $status = $subscription['status'];
        $planId = isset($subscription['metadata']['plan_id']) ? (int)$subscription['metadata']['plan_id'] : 0;

        // Update local subscription
        if ($planId) {
            $this->updateSubscriptionDirect($tenantId, $planId, $status);
        }
    }

    /**
     * Handle subscription deleted webhook
     */
    private function handleSubscriptionDeleted($subscription) {
        $tenantId = isset($subscription['metadata']['tenant_id']) ? (int)$subscription['metadata']['tenant_id'] : 0;

        if (!$tenantId) {
            return;
        }

        // Update status to cancelled
        $sql = "UPDATE tenant_subscriptions
                SET status = 'cancelled', updated_at = NOW()
                WHERE tenant_id = :tenant_id";

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute(array(':tenant_id' => $tenantId));
    }

    /**
     * Handle successful payment webhook
     */
    private function handlePaymentSucceeded($invoice) {
        // Log successful payment
        error_log("Payment succeeded for invoice: " . $invoice['id']);

        // Send payment receipt email
        $customerId = $invoice['customer'];
        // Could fetch customer and send email here
    }

    /**
     * Handle failed payment webhook
     */
    private function handlePaymentFailed($invoice) {
        // Log failed payment
        error_log("Payment failed for invoice: " . $invoice['id']);

        // Send payment failed notification
        $customerId = $invoice['customer'];
        // Could fetch customer and send notification here
    }

    /**
     * Update subscription directly in database
     */
    private function updateSubscriptionDirect($tenantId, $planId, $status = 'active') {
        // Cancel existing subscriptions
        $sql = "UPDATE tenant_subscriptions
                SET status = 'cancelled', updated_at = NOW()
                WHERE tenant_id = :tenant_id AND status = 'active'";

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute(array(':tenant_id' => $tenantId));

        // Create new subscription
        $sql = "INSERT INTO tenant_subscriptions
                (tenant_id, plan_id, status, start_date, auto_renew, created_at)
                VALUES
                (:tenant_id, :plan_id, :status, CURDATE(), 1, NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute(array(
            ':tenant_id' => $tenantId,
            ':plan_id' => $planId,
            ':status' => $status
        ));
    }
}
