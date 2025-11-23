<?php
// FILE: /app/views/partials/bulk_operations.php
/**
 * Bulk Operations Toolbar Component
 * Include this in list views to enable bulk operations
 *
 * Required variables:
 * - $entityType: Entity type (leads, clients, properties, deals, tasks)
 * - $agents: Array of agents for assignment dropdown (optional)
 * - $statusOptions: Array of status options (optional)
 */

$entityType = isset($entityType) ? $entityType : 'leads';
$agents = isset($agents) ? $agents : array();
$statusOptions = isset($statusOptions) ? $statusOptions : array();
?>

<!-- Bulk Actions Toolbar (hidden by default, shown when items selected) -->
<div id="bulkActionsToolbar" class="bulk-actions-toolbar" style="display: none;">
    <div class="toolbar-content">
        <span class="selected-count"><strong><span id="selectedCount">0</span></strong> items selected</span>

        <div class="toolbar-actions">
            <!-- Assign to Agent -->
            <?php if (!empty($agents)): ?>
            <select id="bulkAssignAgent" class="form-control">
                <option value="">Assign to...</option>
                <?php foreach ($agents as $id => $name): ?>
                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-primary"
                    onclick="bulkAssign('<?php echo $entityType; ?>', document.getElementById('bulkAssignAgent').value, '<?php echo BASE_URL; ?>')">
                Assign
            </button>
            <?php endif; ?>

            <!-- Update Status -->
            <?php if (!empty($statusOptions)): ?>
            <select id="bulkUpdateStatus" class="form-control">
                <option value="">Change status to...</option>
                <?php foreach ($statusOptions as $value => $label): ?>
                    <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-sm btn-primary"
                    onclick="bulkUpdateStatus('<?php echo $entityType; ?>', document.getElementById('bulkUpdateStatus').value, '<?php echo BASE_URL; ?>')">
                Update
            </button>
            <?php endif; ?>

            <!-- Email -->
            <?php if (in_array($entityType, array('leads', 'clients'))): ?>
            <button type="button" class="btn btn-sm btn-info"
                    onclick="showBulkEmailModal('<?php echo $entityType; ?>')">
                📧 Email Selected
            </button>
            <?php endif; ?>

            <!-- Convert Leads -->
            <?php if ($entityType === 'leads'): ?>
            <button type="button" class="btn btn-sm btn-success"
                    onclick="bulkConvertLeads('<?php echo BASE_URL; ?>')">
                Convert to Clients
            </button>
            <?php endif; ?>

            <!-- Export -->
            <button type="button" class="btn btn-sm btn-secondary"
                    onclick="bulkExport('<?php echo $entityType; ?>')">
                Export Selected
            </button>

            <!-- Delete -->
            <button type="button" class="btn btn-sm btn-danger"
                    onclick="bulkDelete('<?php echo $entityType; ?>', '<?php echo BASE_URL; ?>')">
                Delete
            </button>
        </div>
    </div>
</div>

<!-- Bulk Email Modal -->
<div id="bulkEmailModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeBulkEmailModal()">&times;</span>
        <h3>Send Bulk Email</h3>

        <form onsubmit="event.preventDefault(); sendBulkEmail('<?php echo BASE_URL; ?>');">
            <input type="hidden" id="bulkEmailEntityType" value="">
            <input type="hidden" id="bulkEmailIds" value="">

            <div class="form-group">
                <label for="bulkEmailSubject">Subject *</label>
                <input type="text" id="bulkEmailSubject" class="form-control" required
                       placeholder="Email subject">
            </div>

            <div class="form-group">
                <label for="bulkEmailMessage">Message *</label>
                <textarea id="bulkEmailMessage" class="form-control" rows="10" required
                          placeholder="Email message..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Send Email</button>
                <button type="button" class="btn btn-secondary" onclick="closeBulkEmailModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Bulk Actions Toolbar */
.bulk-actions-toolbar {
    background: #2c3e50;
    color: white;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.toolbar-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.selected-count {
    font-size: 16px;
}

.toolbar-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.toolbar-actions .form-control {
    min-width: 150px;
    padding: 6px 10px;
}

/* Bulk checkbox styling */
.bulk-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.bulk-checkbox-header {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Modal styles (additional to existing) */
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.modal-content h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #2c3e50;
}

.close {
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #aaa;
    line-height: 20px;
}

.close:hover {
    color: #000;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .toolbar-content {
        flex-direction: column;
        align-items: stretch;
    }

    .toolbar-actions {
        flex-direction: column;
    }

    .toolbar-actions .form-control,
    .toolbar-actions .btn {
        width: 100%;
    }
}
</style>

<script>
// Initialize bulk operations when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to all bulk checkboxes
    const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox');
    bulkCheckboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', toggleBulkActions);
    });
});

// Set BASE_URL for JavaScript
window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>
