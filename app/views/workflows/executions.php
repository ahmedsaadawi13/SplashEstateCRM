<?php include APP_PATH . '/views/partials/header.php'; ?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>Workflow Executions: <?php echo htmlspecialchars($workflow['name']); ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/workflows">Workflows</a></li>
                    <li class="breadcrumb-item active">Executions</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $totalExecutions; ?></h3>
                    <p class="text-muted mb-0">Total Executions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-success">
                        <?php
                        $successful = 0;
                        foreach ($executions as $exec) {
                            if ($exec['status'] === 'completed') $successful++;
                        }
                        echo $successful;
                        ?>
                    </h3>
                    <p class="text-muted mb-0">Successful</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-danger">
                        <?php
                        $failed = 0;
                        foreach ($executions as $exec) {
                            if ($exec['status'] === 'failed') $failed++;
                        }
                        echo $failed;
                        ?>
                    </h3>
                    <p class="text-muted mb-0">Failed</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="text-warning">
                        <?php
                        $pending = 0;
                        foreach ($executions as $exec) {
                            if (in_array($exec['status'], array('pending', 'running'))) $pending++;
                        }
                        echo $pending;
                        ?>
                    </h3>
                    <p class="text-muted mb-0">Pending/Running</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Executions List -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Execution History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($executions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No executions yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Completed</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($executions as $execution): ?>
                                <tr>
                                    <td>
                                        <a href="/workflows/execution/<?php echo $execution['id']; ?>">
                                            #<?php echo $execution['id']; ?>
                                        </a>
                                    </td>
                                    <td>
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
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($execution['created_at'])); ?></td>
                                    <td>
                                        <?php if ($execution['completed_at']): ?>
                                            <?php echo date('M j, Y g:i A', strtotime($execution['completed_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($execution['completed_at']): ?>
                                            <?php
                                            $start = strtotime($execution['created_at']);
                                            $end = strtotime($execution['completed_at']);
                                            $duration = $end - $start;
                                            echo $duration . 's';
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/workflows/execution/<?php echo $execution['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="/workflows/executions/<?php echo $workflow['id']; ?>?page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include APP_PATH . '/views/partials/footer.php'; ?>
