<?php
/**
 * Lead Scoring Rule Model
 *
 * Manages scoring rules for lead qualification
 */

require_once APP_PATH . '/core/Model.php';

class LeadScoringRule extends Model
{
    protected $table = 'lead_scoring_rules';

    /**
     * Get all scoring rules for a tenant
     */
    public function getByTenant($tenantId, $status = 'active')
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id";

        if ($status !== 'all') {
            $sql .= " AND status = :status";
        }

        $sql .= " ORDER BY execution_order ASC, id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId);

        if ($status !== 'all') {
            $stmt->bindParam(':status', $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get rules by type
     */
    public function getByType($tenantId, $ruleType, $status = 'active')
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND rule_type = :rule_type";

        if ($status !== 'all') {
            $sql .= " AND status = :status";
        }

        $sql .= " ORDER BY execution_order ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId);
        $stmt->bindParam(':rule_type', $ruleType);

        if ($status !== 'all') {
            $stmt->bindParam(':status', $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Evaluate a rule against lead data
     */
    public function evaluate($rule, $leadData)
    {
        $fieldName = $rule['field_name'];
        $operator = $rule['operator'];
        $fieldValue = $rule['field_value'];

        // Get actual value from lead data
        $actualValue = isset($leadData[$fieldName]) ? $leadData[$fieldName] : null;

        // Evaluate based on operator
        switch ($operator) {
            case 'equals':
                return $actualValue == $fieldValue;

            case 'not_equals':
                return $actualValue != $fieldValue;

            case 'contains':
                return stripos($actualValue, $fieldValue) !== false;

            case 'not_contains':
                return stripos($actualValue, $fieldValue) === false;

            case 'greater_than':
                return is_numeric($actualValue) && is_numeric($fieldValue) && $actualValue > $fieldValue;

            case 'less_than':
                return is_numeric($actualValue) && is_numeric($fieldValue) && $actualValue < $fieldValue;

            case 'greater_than_or_equal':
                return is_numeric($actualValue) && is_numeric($fieldValue) && $actualValue >= $fieldValue;

            case 'less_than_or_equal':
                return is_numeric($actualValue) && is_numeric($fieldValue) && $actualValue <= $fieldValue;

            case 'is_empty':
                return empty($actualValue);

            case 'is_not_empty':
                return !empty($actualValue);

            case 'in':
                $values = explode(',', $fieldValue);
                $values = array_map('trim', $values);
                return in_array($actualValue, $values);

            case 'not_in':
                $values = explode(',', $fieldValue);
                $values = array_map('trim', $values);
                return !in_array($actualValue, $values);

            default:
                return false;
        }
    }

    /**
     * Get statistics about rules
     */
    public function getStatistics($tenantId)
    {
        $sql = "SELECT
                    COUNT(*) as total_rules,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_rules,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_rules,
                    SUM(CASE WHEN rule_type = 'demographic' THEN 1 ELSE 0 END) as demographic_rules,
                    SUM(CASE WHEN rule_type = 'behavioral' THEN 1 ELSE 0 END) as behavioral_rules,
                    SUM(CASE WHEN rule_type = 'engagement' THEN 1 ELSE 0 END) as engagement_rules,
                    SUM(CASE WHEN rule_type = 'custom' THEN 1 ELSE 0 END) as custom_rules
                FROM {$this->table}
                WHERE tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert null to 0 for cleaner output
        foreach ($stats as $key => $value) {
            $stats[$key] = (int)$value;
        }

        return $stats;
    }

    /**
     * Reorder rules
     */
    public function reorder($ruleId, $newOrder)
    {
        $sql = "UPDATE {$this->table}
                SET execution_order = :execution_order
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':execution_order' => $newOrder,
            ':id' => $ruleId
        ]);
    }

    /**
     * Toggle rule status
     */
    public function toggleStatus($ruleId)
    {
        $rule = $this->getById($ruleId);

        if (!$rule) {
            return false;
        }

        $newStatus = $rule['status'] === 'active' ? 'inactive' : 'active';

        return $this->update($ruleId, ['status' => $newStatus]);
    }

    /**
     * Get available rule types
     */
    public static function getRuleTypes()
    {
        return array(
            'demographic' => array(
                'name' => 'Demographic',
                'description' => 'Based on lead characteristics (budget, location, property type)',
                'icon' => 'user-circle'
            ),
            'behavioral' => array(
                'name' => 'Behavioral',
                'description' => 'Based on lead actions (property views, contact frequency)',
                'icon' => 'chart-line'
            ),
            'engagement' => array(
                'name' => 'Engagement',
                'description' => 'Based on lead interactions (email opens, responses)',
                'icon' => 'comments'
            ),
            'custom' => array(
                'name' => 'Custom',
                'description' => 'Custom scoring criteria',
                'icon' => 'cog'
            )
        );
    }

    /**
     * Get available operators
     */
    public static function getOperators()
    {
        return array(
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_than_or_equal' => 'Greater Than or Equal',
            'less_than_or_equal' => 'Less Than or Equal',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'in' => 'In List (comma-separated)',
            'not_in' => 'Not In List'
        );
    }
}
