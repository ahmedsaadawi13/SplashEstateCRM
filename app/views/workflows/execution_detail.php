<?php include APP_PATH . '/views/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Execution Details: #<?php echo $execution['id']; ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/workflows">Workflows</a></li>
                    <li class="breadcrumb-item"><a href="/workflows/executions/<?php echo $workflow['id']; ?>">Executions</a></li>
                    <li class="breadcrumb-item active">#<?php echo $execution['id']; ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Execution Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Execution Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Workflow:</strong>
                        </div>
                        <div class="col-md-9">
                            <a href="/workflows/edit/<?php echo $workflow['id']; ?>">
                                <?php echo htmlspecialchars($workflow['name']); ?>
                            </a>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Status:</strong>
                        </div>
                        <div class="col-md-9">
                            <?php
                            $statusClass = array(
                                'completed' => 'success',
                                'failed' => 'danger',
                                'running' => 'info',
                                'pending' => 'warning',
                                'cancelled' => 'secondary'
                            );
                            $class = $statusClass[$execution['status']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?php echo $class; ?>">
                                <?php echo ucfirst($execution['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Started:</strong>
                        </div>
                        <div class="col-md-9">
                            <?php echo date('M j, Y g:i:s A', strtotime($execution['created_at'])); ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Completed:</strong>
                        </div>
                        <div class="col-md-9">
                            <?php if ($execution['completed_at']): ?>
                                <?php echo date('M j, Y g:i:s A', strtotime($execution['completed_at'])); ?>
                                <?php
                                $start = strtotime($execution['created_at']);
                                $end = strtotime($execution['completed_at']);
                                $duration = $end - $start;
                                ?>
                                <span class="text-muted">(<?php echo $duration; ?>s)</span>
                            <?php else: ?>
                                <span class="text-muted">Not completed</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($execution['error_message']): ?>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <strong>Error:</strong>
                            </div>
                            <div class="col-md-9">
                                <div class="alert alert-danger mb-0">
                                    <pre class="mb-0"><?php echo htmlspecialchars(json_encode($execution['error_message'], JSON_PRETTY_PRINT)); ?></pre>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Trigger Data -->
            <?php if ($execution['trigger_data']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Trigger Data</h5>
                    </div>
                    <div class="card-body">
                        <pre><?php echo htmlspecialchars(json_encode($execution['trigger_data'], JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Logs -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Action Execution Logs</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($actionLogs)): ?>
                        <p class="text-muted">No actions executed yet</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($actionLogs as $log): ?>
                                <div class="timeline-item mb-4">
                                    <div class="d-flex">
                                        <div class="mr-3">
                                            <?php
                                            $iconClass = array(
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'pending' => 'warning',
                                                'skipped' => 'secondary'
                                            );
                                            $icon = array(
                                                'completed' => 'check-circle',
                                                'failed' => 'times-circle',
                                                'pending' => 'clock',
                                                'skipped' => 'ban'
                                            );
                                            $class = $iconClass[$log['status']] ?? 'secondary';
                                            $iconName = $icon[$log['status']] ?? 'circle';
                                            ?>
                                            <i class="fas fa-<?php echo $iconName; ?> fa-2x text-<?php echo $class; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="card">
                                                <div class="card-header">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <strong>Action: <?php echo ucfirst(str_replace('_', ' ', $log['action_type'])); ?></strong>
                                                        <span class="badge badge-<?php echo $class; ?>">
                                                            <?php echo ucfirst($log['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <?php if ($log['result']): ?>
                                                        <div class="mb-2">
                                                            <strong>Result:</strong>
                                                            <pre class="mb-0"><?php echo htmlspecialchars(json_encode($log['result'], JSON_PRETTY_PRINT)); ?></pre>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($log['error_message']): ?>
                                                        <div class="alert alert-danger mb-0">
                                                            <strong>Error:</strong>
                                                            <pre class="mb-0"><?php echo htmlspecialchars(json_encode($log['error_message'], JSON_PRETTY_PRINT)); ?></pre>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="text-muted small mt-2">
                                                        <?php if ($log['started_at']): ?>
                                                            Started: <?php echo date('g:i:s A', strtotime($log['started_at'])); ?>
                                                        <?php endif; ?>
                                                        <?php if ($log['completed_at']): ?>
                                                            | Completed: <?php echo date('g:i:s A', strtotime($log['completed_at'])); ?>
                                                            <?php
                                                            $start = strtotime($log['started_at']);
                                                            $end = strtotime($log['completed_at']);
                                                            $duration = $end - $start;
                                                            ?>
                                                            (<?php echo $duration; ?>s)
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <a href="/workflows/executions/<?php echo $workflow['id']; ?>" class="btn btn-outline-primary btn-block">
                        <i class="fas fa-arrow-left"></i> Back to Executions
                    </a>
                    <a href="/workflows/edit/<?php echo $workflow['id']; ?>" class="btn btn-outline-secondary btn-block">
                        <i class="fas fa-edit"></i> Edit Workflow
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
