<?php
// FILE: /app/helpers/ReportService.php

/**
 * SplashEstate CRM - Report Service
 * Generates reports and analytics data
 */

class ReportService {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get lead conversion funnel data
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Funnel data by status
     */
    public function getLeadConversionFunnel($tenantId, $startDate = null, $endDate = null) {
        $sql = "SELECT status, COUNT(*) as count
                FROM leads
                WHERE tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if ($startDate) {
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY status ORDER BY FIELD(status, 'new', 'contacted', 'qualified', 'won', 'lost')";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Lead funnel error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get lead sources breakdown
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Source distribution
     */
    public function getLeadSourcesBreakdown($tenantId, $startDate = null, $endDate = null) {
        $sql = "SELECT source, COUNT(*) as count
                FROM leads
                WHERE tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if ($startDate) {
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY source ORDER BY count DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Lead sources error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get agent performance metrics
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Agent performance data
     */
    public function getAgentPerformance($tenantId, $startDate = null, $endDate = null) {
        $sql = "SELECT
                    u.id,
                    CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                    COUNT(DISTINCT l.id) as total_leads,
                    COUNT(DISTINCT CASE WHEN l.status = 'won' THEN l.id END) as won_leads,
                    COUNT(DISTINCT d.id) as total_deals,
                    COUNT(DISTINCT CASE WHEN d.stage = 'closed_won' THEN d.id END) as won_deals,
                    COALESCE(SUM(CASE WHEN d.stage = 'closed_won' THEN d.deal_value ELSE 0 END), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN d.stage = 'closed_won' THEN d.commission ELSE 0 END), 0) as total_commission
                FROM users u
                LEFT JOIN leads l ON l.assigned_to = u.id AND l.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if ($startDate) {
            $sql .= " AND l.created_at >= :start_date_leads";
            $params[':start_date_leads'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $sql .= " AND l.created_at <= :end_date_leads";
            $params[':end_date_leads'] = $endDate . ' 23:59:59';
        }

        $sql .= " LEFT JOIN deals d ON d.agent_id = u.id AND d.tenant_id = :tenant_id2";
        $params[':tenant_id2'] = $tenantId;

        if ($startDate) {
            $sql .= " AND d.created_at >= :start_date_deals";
            $params[':start_date_deals'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $sql .= " AND d.created_at <= :end_date_deals";
            $params[':end_date_deals'] = $endDate . ' 23:59:59';
        }

        $sql .= " WHERE u.tenant_id = :tenant_id3 AND u.role IN ('agent', 'tenant_admin')
                  GROUP BY u.id, agent_name
                  ORDER BY total_revenue DESC";

        $params[':tenant_id3'] = $tenantId;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Agent performance error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get deals pipeline overview
     * @param int $tenantId Tenant ID
     * @return array Deal stages with counts and values
     */
    public function getDealsPipeline($tenantId) {
        $sql = "SELECT
                    stage,
                    COUNT(*) as count,
                    SUM(deal_value) as total_value,
                    AVG(probability) as avg_probability
                FROM deals
                WHERE tenant_id = :tenant_id
                GROUP BY stage
                ORDER BY FIELD(stage, 'lead', 'meeting', 'proposal', 'negotiation', 'closed_won', 'closed_lost')";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':tenant_id' => $tenantId));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Deals pipeline error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get revenue trends over time
     * @param int $tenantId Tenant ID
     * @param string $period Period: day, week, month, year
     * @param int $limit Number of periods to fetch
     * @return array Revenue by period
     */
    public function getRevenueTrends($tenantId, $period = 'month', $limit = 12) {
        $dateFormat = match($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m'
        };

        $sql = "SELECT
                    DATE_FORMAT(created_at, :date_format) as period,
                    COUNT(*) as deal_count,
                    SUM(deal_value) as revenue,
                    SUM(commission) as commission
                FROM deals
                WHERE tenant_id = :tenant_id AND stage = 'closed_won'
                GROUP BY period
                ORDER BY period DESC
                LIMIT :limit";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':date_format', $dateFormat);
            $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_reverse($results); // Oldest to newest
        } catch(PDOException $e) {
            error_log("Revenue trends error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get property type distribution
     * @param int $tenantId Tenant ID
     * @return array Property types with counts
     */
    public function getPropertyTypeDistribution($tenantId) {
        $sql = "SELECT
                    property_type,
                    COUNT(*) as count,
                    AVG(price) as avg_price,
                    SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold_count
                FROM properties
                WHERE tenant_id = :tenant_id
                GROUP BY property_type
                ORDER BY count DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':tenant_id' => $tenantId));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Property type distribution error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get task completion metrics
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Task metrics
     */
    public function getTaskMetrics($tenantId, $startDate = null, $endDate = null) {
        $sql = "SELECT
                    status,
                    priority,
                    COUNT(*) as count
                FROM tasks
                WHERE tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if ($startDate) {
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }

        if ($endDate) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " GROUP BY status, priority";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Task metrics error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get comprehensive dashboard summary
     * @param int $tenantId Tenant ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Dashboard metrics
     */
    public function getDashboardSummary($tenantId, $startDate = null, $endDate = null) {
        $summary = array(
            'leads' => $this->getLeadConversionFunnel($tenantId, $startDate, $endDate),
            'sources' => $this->getLeadSourcesBreakdown($tenantId, $startDate, $endDate),
            'agents' => $this->getAgentPerformance($tenantId, $startDate, $endDate),
            'pipeline' => $this->getDealsPipeline($tenantId),
            'revenue_trends' => $this->getRevenueTrends($tenantId, 'month', 6),
            'properties' => $this->getPropertyTypeDistribution($tenantId),
            'tasks' => $this->getTaskMetrics($tenantId, $startDate, $endDate)
        );

        return $summary;
    }
}
