<?php
// FILE: /app/controllers/DashboardController.php

/**
 * SplashEstate CRM - Dashboard Controller
 * Displays main dashboard with statistics and overview
 */

class DashboardController extends Controller {

    /**
     * Display dashboard
     */
    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $userId = $this->getUserId();
        $userRole = $this->getUserRole();

        // Get statistics
        $stats = array();

        // Leads statistics
        $leadModel = $this->model('Lead');
        $stats['total_leads'] = $leadModel->count($tenantId, array());
        $stats['new_leads'] = $leadModel->count($tenantId, array('status' => 'new'));

        // Clients statistics
        $clientModel = $this->model('Client');
        $stats['total_clients'] = $clientModel->count($tenantId, array());

        // Properties statistics
        $propertyModel = $this->model('Property');
        $stats['total_properties'] = $propertyModel->count($tenantId, array());
        $stats['available_properties'] = $propertyModel->count($tenantId, array('status' => 'available'));

        // Deals statistics
        $dealModel = $this->model('Deal');
        $stats['total_deals'] = $dealModel->count($tenantId, array());
        $stats['active_deals'] = $dealModel->getActiveDealsCount($tenantId);

        // Tasks statistics
        $taskModel = $this->model('Task');
        $stats['pending_tasks'] = $taskModel->count($tenantId, array('status' => 'pending'));
        $stats['my_tasks'] = $taskModel->countByAssignee($tenantId, $userId, 'pending');

        // Recent activity
        $recentLeads = $leadModel->getAll($tenantId, 1, 5);
        $recentDeals = $dealModel->getAll($tenantId, 1, 5);
        $upcomingTasks = $taskModel->getUpcoming($tenantId, $userId, 5);

        // Get subscription info
        $subscriptionModel = $this->model('Subscription');
        $subscription = $subscriptionModel->getActiveSubscription($tenantId);
        $usage = $subscriptionModel->getUsageStats($tenantId);

        $data = array(
            'title' => 'Dashboard',
            'stats' => $stats,
            'recent_leads' => $recentLeads,
            'recent_deals' => $recentDeals,
            'upcoming_tasks' => $upcomingTasks,
            'subscription' => $subscription,
            'usage' => $usage
        );

        $this->view('dashboard/index', $data);
    }
}
