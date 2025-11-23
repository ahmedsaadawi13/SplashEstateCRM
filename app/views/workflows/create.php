<?php include APP_PATH . '/views/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Create Workflow</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/workflows">Workflows</a></li>
                    <li class="breadcrumb-item active">Create</li>
                </ol>
            </nav>
        </div>
    </div>

    <form method="POST" action="/workflows/store" id="workflowForm">
        <div class="row">
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">Workflow Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="trigger_type">Trigger Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="trigger_type" name="trigger_type" required>
                                <option value="">Select a trigger...</option>
                                <?php foreach ($triggerTypes as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">When should this workflow be triggered?</small>
                        </div>
                    </div>
                </div>

                <!-- Conditions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Conditions (Optional)</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Add conditions to control when this workflow runs. Leave empty to run on every trigger.</p>

                        <div id="conditionsContainer"></div>

                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCondition()">
                            <i class="fas fa-plus"></i> Add Condition
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Actions <span class="text-danger">*</span></h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Define what actions to perform when this workflow is triggered.</p>

                        <div id="actionsContainer"></div>

                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown">
                                <i class="fas fa-plus"></i> Add Action
                            </button>
                            <div class="dropdown-menu">
                                <?php foreach ($actionTypes as $type => $config): ?>
                                    <a class="dropdown-item" href="#" onclick="addAction('<?php echo $type; ?>'); return false;">
                                        <i class="fas fa-<?php echo $config['icon']; ?>"></i> <?php echo $config['name']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="execution_order">Execution Order</label>
                            <input type="number" class="form-control" id="execution_order" name="execution_order" value="0" min="0">
                            <small class="form-text text-muted">Lower numbers execute first</small>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save"></i> Create Workflow
                        </button>
                        <a href="/workflows" class="btn btn-outline-secondary btn-block">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let conditionIndex = 0;
let actionIndex = 0;

const operators = <?php echo json_encode($operators); ?>;
const actionTypes = <?php echo json_encode($actionTypes); ?>;

function addCondition() {
    const container = document.getElementById('conditionsContainer');
    const div = document.createElement('div');
    div.className = 'border rounded p-3 mb-3';
    div.innerHTML = `
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Field</label>
                    <input type="text" class="form-control" name="conditions[${conditionIndex}][field]" placeholder="e.g., status">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Operator</label>
                    <select class="form-control" name="conditions[${conditionIndex}][operator]">
                        ${Object.entries(operators).map(([value, label]) =>
                            `<option value="${value}">${label}</option>`
                        ).join('')}
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>Value</label>
                    <input type="text" class="form-control" name="conditions[${conditionIndex}][value]" placeholder="Value to compare">
                </div>
            </div>
            <div class="col-md-1">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-danger btn-block" onclick="this.parentElement.parentElement.parentElement.remove()">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(div);
    conditionIndex++;
}

function addAction(type) {
    const container = document.getElementById('actionsContainer');
    const config = actionTypes[type];
    const div = document.createElement('div');
    div.className = 'border rounded p-3 mb-3';

    let fieldsHtml = '';

    // Generate fields based on action type
    switch(type) {
        case 'send_email':
            fieldsHtml = `
                <div class="form-group">
                    <label>To (Email Address)</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][to]" placeholder="recipient@example.com or {{email}}">
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][subject]" placeholder="Email subject">
                </div>
                <div class="form-group">
                    <label>Body</label>
                    <textarea class="form-control" name="actions[${actionIndex}][config][body]" rows="4" placeholder="Use {{field_name}} for dynamic values"></textarea>
                </div>
            `;
            break;

        case 'create_task':
            fieldsHtml = `
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][title]" placeholder="Task title">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="actions[${actionIndex}][config][description]" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Assign to User ID</label>
                            <input type="text" class="form-control" name="actions[${actionIndex}][config][assigned_to]" placeholder="User ID or {{assigned_to}}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="text" class="form-control" name="actions[${actionIndex}][config][due_date]" placeholder="+7 days">
                        </div>
                    </div>
                </div>
            `;
            break;

        case 'update_field':
            fieldsHtml = `
                <div class="form-group">
                    <label>Entity Type</label>
                    <select class="form-control" name="actions[${actionIndex}][config][entity_type]">
                        <option value="lead">Lead</option>
                        <option value="client">Client</option>
                        <option value="deal">Deal</option>
                        <option value="property">Property</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Field Name</label>
                            <input type="text" class="form-control" name="actions[${actionIndex}][config][field_name]" placeholder="e.g., status">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>New Value</label>
                            <input type="text" class="form-control" name="actions[${actionIndex}][config][field_value]" placeholder="New value">
                        </div>
                    </div>
                </div>
            `;
            break;

        case 'assign_to_user':
            fieldsHtml = `
                <div class="form-group">
                    <label>Entity Type</label>
                    <select class="form-control" name="actions[${actionIndex}][config][entity_type]">
                        <option value="lead">Lead</option>
                        <option value="client">Client</option>
                        <option value="deal">Deal</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>User ID</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][user_id]" placeholder="User ID or {{assigned_to}}">
                </div>
            `;
            break;

        case 'send_webhook':
            fieldsHtml = `
                <div class="form-group">
                    <label>Webhook URL</label>
                    <input type="url" class="form-control" name="actions[${actionIndex}][config][url]" placeholder="https://api.example.com/webhook">
                </div>
                <div class="form-group">
                    <label>Method</label>
                    <select class="form-control" name="actions[${actionIndex}][config][method]">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                        <option value="PUT">PUT</option>
                    </select>
                </div>
            `;
            break;

        case 'send_notification':
            fieldsHtml = `
                <div class="form-group">
                    <label>User ID</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][user_id]" placeholder="User ID or {{assigned_to}}">
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" class="form-control" name="actions[${actionIndex}][config][title]" placeholder="Notification title">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea class="form-control" name="actions[${actionIndex}][config][message]" rows="2"></textarea>
                </div>
            `;
            break;
    }

    div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">
                <i class="fas fa-${config.icon}"></i> ${config.name}
            </h6>
            <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-trash"></i>
            </button>
        </div>

        <input type="hidden" name="actions[${actionIndex}][type]" value="${type}">

        ${fieldsHtml}

        <div class="form-group mb-0">
            <label>Delay (minutes)</label>
            <input type="number" class="form-control" name="actions[${actionIndex}][delay]" value="0" min="0">
            <small class="form-text text-muted">Wait before executing this action</small>
        </div>
    `;

    container.appendChild(div);
    actionIndex++;
}

// Validate form
document.getElementById('workflowForm').addEventListener('submit', function(e) {
    const actionsContainer = document.getElementById('actionsContainer');
    if (actionsContainer.children.length === 0) {
        e.preventDefault();
        alert('Please add at least one action to the workflow');
        return false;
    }
});
</script>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
