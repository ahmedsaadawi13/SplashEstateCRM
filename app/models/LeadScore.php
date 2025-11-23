<?php
/**
 * Lead Score Model
 *
 * Manages lead scores and scoring history
 */

require_once APP_PATH . '/core/Model.php';

class LeadScore extends Model
{
    protected $table = 'lead_scores';

    /**
     * Get score for a lead
     */
    public function getByLead($leadId)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE lead_id = :lead_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':lead_id' => $leadId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get or create score record for a lead
     */
    public function getOrCreate($leadId)
    {
        $score = $this->getByLead($leadId);

        if (!$score) {
            $scoreId = $this->create([
                'lead_id' => $leadId,
                'score' => 0,
                'grade' => 'F',
                'last_calculated_at' => date('Y-m-d H:i:s')
            ]);

            $score = $this->getById($scoreId);
        }

        return $score;
    }

    /**
     * Update score for a lead
     */
    public function updateScore($leadId, $newScore, $breakdown = null, $changedBy = null, $changeReason = null)
    {
        // Get current score
        $currentScore = $this->getOrCreate($leadId);

        // Calculate grade
        $newGrade = $this->calculateGrade($newScore);

        // Update score
        $updated = $this->update($currentScore['id'], [
            'score' => $newScore,
            'grade' => $newGrade,
            'score_breakdown' => $breakdown ? json_encode($breakdown) : null,
            'last_calculated_at' => date('Y-m-d H:i:s')
        ]);

        if ($updated) {
            // Log history if score changed
            if ($currentScore['score'] != $newScore) {
                $this->logHistory(
                    $leadId,
                    $currentScore['score'],
                    $newScore,
                    $currentScore['grade'],
                    $newGrade,
                    $changedBy,
                    $changeReason
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Calculate letter grade from score
     */
    public function calculateGrade($score)
    {
        // Default grading scale
        if ($score >= 80) {
            return 'A';
        } elseif ($score >= 60) {
            return 'B';
        } elseif ($score >= 40) {
            return 'C';
        } elseif ($score >= 20) {
            return 'D';
        } else {
            return 'F';
        }
    }

    /**
     * Log score change history
     */
    private function logHistory($leadId, $oldScore, $newScore, $oldGrade, $newGrade, $changedBy = null, $changeReason = null)
    {
        $sql = "INSERT INTO lead_score_history
                (lead_id, old_score, new_score, old_grade, new_grade, changed_by, change_reason)
                VALUES (:lead_id, :old_score, :new_score, :old_grade, :new_grade, :changed_by, :change_reason)";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':lead_id' => $leadId,
            ':old_score' => $oldScore,
            ':new_score' => $newScore,
            ':old_grade' => $oldGrade,
            ':new_grade' => $newGrade,
            ':changed_by' => $changedBy,
            ':change_reason' => $changeReason
        ]);
    }

    /**
     * Get score history for a lead
     */
    public function getHistory($leadId, $limit = 50)
    {
        $sql = "SELECT * FROM lead_score_history
                WHERE lead_id = :lead_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':lead_id', $leadId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get top scoring leads for a tenant
     */
    public function getTopLeads($tenantId, $limit = 10, $minGrade = null)
    {
        $sql = "SELECT ls.*, l.name, l.email, l.phone, l.source, l.status
                FROM {$this->table} ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id";

        if ($minGrade) {
            $sql .= " AND ls.grade <= :min_grade";
        }

        $sql .= " ORDER BY ls.score DESC, ls.last_calculated_at DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);

        if ($minGrade) {
            $stmt->bindParam(':min_grade', $minGrade);
        }

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get score distribution for a tenant
     */
    public function getDistribution($tenantId)
    {
        $sql = "SELECT
                    ls.grade,
                    COUNT(*) as count,
                    AVG(ls.score) as avg_score,
                    MIN(ls.score) as min_score,
                    MAX(ls.score) as max_score
                FROM {$this->table} ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id
                GROUP BY ls.grade
                ORDER BY ls.grade ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get scoring statistics for a tenant
     */
    public function getStatistics($tenantId)
    {
        $sql = "SELECT
                    COUNT(*) as total_scored_leads,
                    AVG(ls.score) as average_score,
                    MIN(ls.score) as min_score,
                    MAX(ls.score) as max_score,
                    SUM(CASE WHEN ls.grade = 'A' THEN 1 ELSE 0 END) as grade_a,
                    SUM(CASE WHEN ls.grade = 'B' THEN 1 ELSE 0 END) as grade_b,
                    SUM(CASE WHEN ls.grade = 'C' THEN 1 ELSE 0 END) as grade_c,
                    SUM(CASE WHEN ls.grade = 'D' THEN 1 ELSE 0 END) as grade_d,
                    SUM(CASE WHEN ls.grade = 'F' THEN 1 ELSE 0 END) as grade_f
                FROM {$this->table} ls
                INNER JOIN leads l ON ls.lead_id = l.id
                WHERE l.tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Round average score
        if ($stats['average_score']) {
            $stats['average_score'] = round($stats['average_score'], 1);
        }

        return $stats;
    }

    /**
     * Recalculate all scores for a tenant
     */
    public function recalculateAll($tenantId)
    {
        require_once APP_PATH . '/helpers/LeadScoringEngine.php';
        require_once APP_PATH . '/models/Lead.php';

        $leadModel = new Lead();
        $scoringEngine = new LeadScoringEngine();

        // Get all leads for tenant
        $leads = $leadModel->getAll(['tenant_id' => $tenantId]);

        $count = 0;
        foreach ($leads as $lead) {
            if ($scoringEngine->calculateScore($lead['id'])) {
                $count++;
            }
        }

        return $count;
    }
}
