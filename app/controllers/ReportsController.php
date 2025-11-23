<?php
// FILE: /app/controllers/ReportsController.php

/**
 * SplashEstate CRM - Reports Controller
 * Handles reporting and analytics
 */

class ReportsController extends Controller {

    private $reportService;

    public function __construct() {
        require_once APP_PATH . '/helpers/ReportService.php';
        $this->reportService = new ReportService();
    }

    /**
     * Main reports dashboard
     */
    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        // Get date range from request or default to last 30 days
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

        // Get comprehensive dashboard data
        $dashboardData = $this->reportService->getDashboardSummary($tenantId, $startDate, $endDate);

        $data = array(
            'title' => 'Reports & Analytics',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dashboardData' => $dashboardData
        );

        $this->view('reports/index', $data);
    }

    /**
     * Lead analytics report
     */
    public function leads() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

        $data = array(
            'title' => 'Lead Analytics',
            'funnel' => $this->reportService->getLeadConversionFunnel($tenantId, $startDate, $endDate),
            'sources' => $this->reportService->getLeadSourcesBreakdown($tenantId, $startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate
        );

        $this->view('reports/leads', $data);
    }

    /**
     * Agent performance report
     */
    public function agents() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

        $data = array(
            'title' => 'Agent Performance',
            'agents' => $this->reportService->getAgentPerformance($tenantId, $startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate
        );

        $this->view('reports/agents', $data);
    }

    /**
     * Revenue report
     */
    public function revenue() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $period = isset($_GET['period']) ? $_GET['period'] : 'month';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;

        $data = array(
            'title' => 'Revenue Report',
            'trends' => $this->reportService->getRevenueTrends($tenantId, $period, $limit),
            'pipeline' => $this->reportService->getDealsPipeline($tenantId),
            'period' => $period,
            'limit' => $limit
        );

        $this->view('reports/revenue', $data);
    }

    /**
     * Properties report
     */
    public function properties() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        $data = array(
            'title' => 'Property Analytics',
            'distribution' => $this->reportService->getPropertyTypeDistribution($tenantId)
        );

        $this->view('reports/properties', $data);
    }

    /**
     * API endpoint for chart data
     */
    public function chartData($type) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

        $chartData = array();

        switch ($type) {
            case 'lead-funnel':
                $chartData = $this->reportService->getLeadConversionFunnel($tenantId, $startDate, $endDate);
                break;

            case 'lead-sources':
                $chartData = $this->reportService->getLeadSourcesBreakdown($tenantId, $startDate, $endDate);
                break;

            case 'agent-performance':
                $chartData = $this->reportService->getAgentPerformance($tenantId, $startDate, $endDate);
                break;

            case 'deals-pipeline':
                $chartData = $this->reportService->getDealsPipeline($tenantId);
                break;

            case 'revenue-trends':
                $period = isset($_GET['period']) ? $_GET['period'] : 'month';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
                $chartData = $this->reportService->getRevenueTrends($tenantId, $period, $limit);
                break;

            case 'property-types':
                $chartData = $this->reportService->getPropertyTypeDistribution($tenantId);
                break;

            case 'task-metrics':
                $chartData = $this->reportService->getTaskMetrics($tenantId, $startDate, $endDate);
                break;

            default:
                $this->jsonResponse(array('error' => 'Invalid chart type'), 400);
                return;
        }

        $this->jsonResponse(array(
            'success' => true,
            'data' => $chartData
        ));
    }

    /**
     * Export report data to CSV
     */
    public function export($type) {
        $this->requireLogin();

        require_once APP_PATH . '/helpers/ExportService.php';
        $exportService = new ExportService();

        $tenantId = $this->getTenantId();
        $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

        switch ($type) {
            case 'lead-funnel':
                $data = $this->reportService->getLeadConversionFunnel($tenantId, $startDate, $endDate);
                $headers = array('status' => 'Status', 'count' => 'Count');
                $filename = 'lead_funnel_' . date('Y-m-d') . '.csv';
                break;

            case 'agent-performance':
                $data = $this->reportService->getAgentPerformance($tenantId, $startDate, $endDate);
                $headers = array(
                    'agent_name' => 'Agent',
                    'total_leads' => 'Total Leads',
                    'won_leads' => 'Won Leads',
                    'total_deals' => 'Total Deals',
                    'won_deals' => 'Won Deals',
                    'total_revenue' => 'Revenue',
                    'total_commission' => 'Commission'
                );
                $filename = 'agent_performance_' . date('Y-m-d') . '.csv';
                break;

            case 'revenue-trends':
                $period = isset($_GET['period']) ? $_GET['period'] : 'month';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
                $data = $this->reportService->getRevenueTrends($tenantId, $period, $limit);
                $headers = array(
                    'period' => 'Period',
                    'deal_count' => 'Deals',
                    'revenue' => 'Revenue',
                    'commission' => 'Commission'
                );
                $filename = 'revenue_trends_' . date('Y-m-d') . '.csv';
                break;

            default:
                $this->setFlash('error', 'Invalid export type');
                $this->redirect('reports/index');
                return;
        }

        $exportService->exportToCSV($data, $headers, $filename);
    }
}
