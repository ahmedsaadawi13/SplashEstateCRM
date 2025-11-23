<?php
/**
 * Lead Scoring Engine
 *
 * Calculates and manages lead scores based on configurable rules
 */

require_once APP_PATH . '/models/LeadScoringRule.php';
require_once APP_PATH . '/models/LeadScore.php';
require_once APP_PATH . '/models/Lead.php';

class LeadScoringEngine
{
    private $ruleModel;
    private $scoreModel;
    private $leadModel;

    public function __construct()
    {
        $this->ruleModel = new LeadScoringRule();
        $this->scoreModel = new LeadScore();
        $this->leadModel = new Lead();
    }

    /**
     * Calculate score for a lead
     *
     * @param int $leadId Lead ID
     * @param string|null $triggeredBy What triggered the calculation
     * @return bool Success status
     */
    public function calculateScore($leadId, $triggeredBy = 'auto')
    {
        // Get lead data
        $lead = $this->leadModel->getById($leadId);

        if (!$lead) {
            return false;
        }

        $tenantId = $lead['tenant_id'];

        // Get all active scoring rules for this tenant
        $rules = $this->ruleModel->getByTenant($tenantId, 'active');

        if (empty($rules)) {
            // No rules defined, set score to 0
            return $this->scoreModel->updateScore($leadId, 0, [], $triggeredBy, 'No scoring rules defined');
        }

        // Calculate score
        $totalScore = 0;
        $breakdown = array();

        foreach ($rules as $rule) {
            // Evaluate rule against lead data
            $matches = $this->ruleModel->evaluate($rule, $lead);

            if ($matches) {
                $points = (int)$rule['points'];
                $totalScore += $points;

                $breakdown[] = array(
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'rule_type' => $rule['rule_type'],
                    'field' => $rule['field_name'],
                    'points' => $points,
                    'matched' => true
                );
            }
        }

        // Ensure score is not negative
        $totalScore = max(0, $totalScore);

        // Ensure score doesn't exceed 100
        $totalScore = min(100, $totalScore);

        // Update score
        return $this->scoreModel->updateScore($leadId, $totalScore, $breakdown, $triggeredBy, 'Score calculated');
    }

    /**
     * Recalculate score when lead is updated
     *
     * @param int $leadId Lead ID
     * @param array $changedFields Fields that were changed
     * @return bool Success status
     */
    public function onLeadUpdate($leadId, $changedFields = array())
    {
        return $this->calculateScore($leadId, 'lead_update');
    }

    /**
     * Calculate initial score when lead is created
     *
     * @param int $leadId Lead ID
     * @return bool Success status
     */
    public function onLeadCreate($leadId)
    {
        return $this->calculateScore($leadId, 'lead_create');
    }

    /**
     * Get score for a lead with details
     *
     * @param int $leadId Lead ID
     * @return array|null Score details
     */
    public function getLeadScore($leadId)
    {
        $score = $this->scoreModel->getByLead($leadId);

        if (!$score) {
            return null;
        }

        // Decode breakdown
        if ($score['score_breakdown']) {
            $score['breakdown'] = json_decode($score['score_breakdown'], true);
        } else {
            $score['breakdown'] = array();
        }

        return $score;
    }

    /**
     * Get score history for a lead
     *
     * @param int $leadId Lead ID
     * @param int $limit Number of records to return
     * @return array Score history
     */
    public function getScoreHistory($leadId, $limit = 50)
    {
        return $this->scoreModel->getHistory($leadId, $limit);
    }

    /**
     * Get high-value leads (Grade A or B)
     *
     * @param int $tenantId Tenant ID
     * @param int $limit Number of leads to return
     * @return array High-value leads
     */
    public function getHighValueLeads($tenantId, $limit = 20)
    {
        return $this->scoreModel->getTopLeads($tenantId, $limit, 'B');
    }

    /**
     * Get qualified leads (minimum score)
     *
     * @param int $tenantId Tenant ID
     * @param int $minScore Minimum score threshold
     * @param int $limit Number of leads to return
     * @return array Qualified leads
     */
    public function getQualifiedLeads($tenantId, $minScore = 60, $limit = 50)
    {
        $sql = "SELECT ls.*, l.name, l.email, l.phone, l.source, l.status
                FROM lead_scores ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id
                AND ls.score >= :min_score
                ORDER BY ls.score DESC, ls.last_calculated_at DESC
                LIMIT :limit";

        $stmt = $this->scoreModel->conn->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':min_score', $minScore, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get leads needing attention (low scores)
     *
     * @param int $tenantId Tenant ID
     * @param int $maxScore Maximum score threshold
     * @param int $limit Number of leads to return
     * @return array Leads needing attention
     */
    public function getNurturingLeads($tenantId, $maxScore = 40, $limit = 50)
    {
        $sql = "SELECT ls.*, l.name, l.email, l.phone, l.source, l.status
                FROM lead_scores ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id
                AND ls.score <= :max_score
                AND l.status NOT IN ('converted', 'lost')
                ORDER BY ls.score ASC, ls.last_calculated_at DESC
                LIMIT :limit";

        $stmt = $this->scoreModel->conn->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':max_score', $maxScore, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get scoring performance metrics
     *
     * @param int $tenantId Tenant ID
     * @return array Performance metrics
     */
    public function getPerformanceMetrics($tenantId)
    {
        $stats = $this->scoreModel->getStatistics($tenantId);
        $distribution = $this->scoreModel->getDistribution($tenantId);
        $ruleStats = $this->ruleModel->getStatistics($tenantId);

        // Calculate conversion rates by grade
        $conversionRates = $this->getConversionRatesByGrade($tenantId);

        return array(
            'statistics' => $stats,
            'distribution' => $distribution,
            'rule_stats' => $ruleStats,
            'conversion_rates' => $conversionRates
        );
    }

    /**
     * Get conversion rates by lead grade
     *
     * @param int $tenantId Tenant ID
     * @return array Conversion rates
     */
    private function getConversionRatesByGrade($tenantId)
    {
        $sql = "SELECT
                    ls.grade,
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) as converted_leads,
                    ROUND(SUM(CASE WHEN l.status = 'converted' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as conversion_rate
                FROM lead_scores ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id
                GROUP BY ls.grade
                ORDER BY ls.grade ASC";

        $stmt = $this->scoreModel->conn->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bulk recalculate scores for all leads in a tenant
     *
     * @param int $tenantId Tenant ID
     * @return array Result summary
     */
    public function bulkRecalculate($tenantId)
    {
        $leads = $this->leadModel->getAll(['tenant_id' => $tenantId]);

        $success = 0;
        $failed = 0;
        $errors = array();

        foreach ($leads as $lead) {
            try {
                if ($this->calculateScore($lead['id'], 'bulk_recalculate')) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = "Lead #{$lead['id']}: Calculation returned false";
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Lead #{$lead['id']}: " . $e->getMessage();
            }
        }

        return array(
            'total' => count($leads),
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors
        );
    }

    /**
     * Test scoring rules against a lead
     *
     * @param int $leadId Lead ID
     * @return array Detailed rule evaluation results
     */
    public function testRules($leadId)
    {
        $lead = $this->leadModel->getById($leadId);

        if (!$lead) {
            return null;
        }

        $rules = $this->ruleModel->getByTenant($lead['tenant_id'], 'active');

        $results = array();
        $totalScore = 0;

        foreach ($rules as $rule) {
            $matches = $this->ruleModel->evaluate($rule, $lead);
            $points = $matches ? (int)$rule['points'] : 0;

            if ($matches) {
                $totalScore += $points;
            }

            $results[] = array(
                'rule' => $rule,
                'matched' => $matches,
                'points_awarded' => $points,
                'reason' => $this->getMatchReason($rule, $lead, $matches)
            );
        }

        return array(
            'lead' => $lead,
            'rules' => $results,
            'total_score' => min(100, max(0, $totalScore)),
            'grade' => $this->scoreModel->calculateGrade($totalScore)
        );
    }

    /**
     * Get explanation for why a rule matched or didn't match
     *
     * @param array $rule Scoring rule
     * @param array $lead Lead data
     * @param bool $matched Whether the rule matched
     * @return string Explanation
     */
    private function getMatchReason($rule, $lead, $matched)
    {
        $field = $rule['field_name'];
        $operator = $rule['operator'];
        $expectedValue = $rule['field_value'];
        $actualValue = isset($lead[$field]) ? $lead[$field] : 'null';

        if ($matched) {
            return "Field '{$field}' ({$actualValue}) {$operator} {$expectedValue}";
        } else {
            return "Field '{$field}' ({$actualValue}) does not match condition: {$operator} {$expectedValue}";
        }
    }

    /**
     * Get recommended actions based on lead score
     *
     * @param int $leadId Lead ID
     * @return array Recommended actions
     */
    public function getRecommendedActions($leadId)
    {
        $score = $this->getLeadScore($leadId);

        if (!$score) {
            return array();
        }

        $recommendations = array();

        switch ($score['grade']) {
            case 'A':
                $recommendations[] = array(
                    'priority' => 'high',
                    'action' => 'immediate_contact',
                    'message' => 'High-value lead - Contact immediately'
                );
                $recommendations[] = array(
                    'priority' => 'high',
                    'action' => 'assign_senior',
                    'message' => 'Assign to senior agent or sales manager'
                );
                break;

            case 'B':
                $recommendations[] = array(
                    'priority' => 'medium',
                    'action' => 'schedule_call',
                    'message' => 'Good prospect - Schedule call within 24 hours'
                );
                break;

            case 'C':
                $recommendations[] = array(
                    'priority' => 'medium',
                    'action' => 'nurture',
                    'message' => 'Add to nurture campaign'
                );
                $recommendations[] = array(
                    'priority' => 'low',
                    'action' => 'send_info',
                    'message' => 'Send property information and follow up in 3 days'
                );
                break;

            case 'D':
            case 'F':
                $recommendations[] = array(
                    'priority' => 'low',
                    'action' => 'auto_nurture',
                    'message' => 'Add to automated nurture sequence'
                );
                $recommendations[] = array(
                    'priority' => 'low',
                    'action' => 'qualify',
                    'message' => 'Gather more information to improve score'
                );
                break;
        }

        return $recommendations;
    }
}
