<?php include APP_PATH . '/views/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Workflow Automation</h2>
            <p class="text-muted">Automate repetitive tasks and streamline your processes</p>
        </div>
        <div class="col-md-6 text-right">
            <a href="/workflows/create" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create Workflow
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/workflows" class="form-inline">
                <div class="form-group mr-3">
                    <label for="status" class="mr-2">Status:</label>
                    <select name="status" id="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $currentStatus === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="active" <?php echo $currentStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $currentStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="trigger" class="mr-2">Trigger:</label>
                    <select name="trigger" id="trigger" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?php echo $currentTrigger === 'all' ? 'selected' : ''; ?>>All Triggers</option>
                        <?php foreach ($triggerTypes as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $currentTrigger === $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($workflows)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
                <h4>No Workflows Yet</h4>
                <p class="text-muted">Create your first workflow to automate tasks and save time</p>
                <a href="/workflows/create" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Create Your First Workflow
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($workflows as $workflow): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($workflow['name']); ?></h5>
                                <span class="badge badge-<?php echo $workflow['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($workflow['status']); ?>
                                </span>
                            </div>

                            <?php if ($workflow['description']): ?>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars($workflow['description']); ?></p>
                            <?php endif; ?>

                            <div class="mb-3">
                                <span class="badge badge-info">
                                    <i class="fas fa-bolt"></i> <?php echo $triggerTypes[$workflow['trigger_type']]; ?>
                                </span>
                            </div>

                            <!-- Statistics -->
                            <div class="row text-center small mb-3">
                                <div class="col-4">
                                    <div class="text-muted">Total</div>
                                    <div class="font-weight-bold"><?php echo $workflow['stats']['total_executions']; ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Success</div>
                                    <div class="font-weight-bold text-success"><?php echo $workflow['stats']['successful']; ?></div>
                                </div>
                                <div class="col-4">
                                    <div class="text-muted">Failed</div>
                                    <div class="font-weight-bold text-danger"><?php echo $workflow['stats']['failed']; ?></div>
                                </div>
                            </div>

                            <?php if ($workflow['stats']['last_execution']): ?>
                                <div class="text-muted small mb-3">
                                    <i class="fas fa-clock"></i> Last run: <?php echo date('M j, Y g:i A', strtotime($workflow['stats']['last_execution'])); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Actions -->
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <a href="/workflows/edit/<?php echo $workflow['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="/workflows/executions/<?php echo $workflow['id']; ?>" class="btn btn-outline-info" title="View History">
                                    <i class="fas fa-history"></i>
                                </a>
                                <a href="/workflows/toggle/<?php echo $workflow['id']; ?>"
                                   class="btn btn-outline-<?php echo $workflow['status'] === 'active' ? 'warning' : 'success'; ?>"
                                   title="<?php echo $workflow['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                   onclick="return confirm('Are you sure you want to <?php echo $workflow['status'] === 'active' ? 'deactivate' : 'activate'; ?> this workflow?')">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <a href="/workflows/delete/<?php echo $workflow['id']; ?>"
                                   class="btn btn-outline-danger"
                                   title="Delete"
                                   onclick="return confirm('Are you sure you want to delete this workflow? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
