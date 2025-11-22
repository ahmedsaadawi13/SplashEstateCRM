<?php
// FILE: /app/views/dashboard/index.php
require_once '../app/views/layouts/header.php';
?>

<h1>Dashboard</h1>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Leads</h3>
        <p class="stat-number"><?php echo $stats['total_leads']; ?></p>
        <span class="stat-label"><?php echo $stats['new_leads']; ?> new</span>
    </div>

    <div class="stat-card">
        <h3>Clients</h3>
        <p class="stat-number"><?php echo $stats['total_clients']; ?></p>
    </div>

    <div class="stat-card">
        <h3>Properties</h3>
        <p class="stat-number"><?php echo $stats['total_properties']; ?></p>
        <span class="stat-label"><?php echo $stats['available_properties']; ?> available</span>
    </div>

    <div class="stat-card">
        <h3>Active Deals</h3>
        <p class="stat-number"><?php echo $stats['active_deals']; ?></p>
    </div>

    <div class="stat-card">
        <h3>My Tasks</h3>
        <p class="stat-number"><?php echo $stats['my_tasks']; ?></p>
        <span class="stat-label">pending</span>
    </div>

    <?php if ($subscription): ?>
    <div class="stat-card">
        <h3>Subscription</h3>
        <p class="stat-label"><?php echo View::escape($subscription['plan_name']); ?></p>
        <small>Leads: <?php echo $usage['leads_count']; ?>/<?php echo $subscription['max_leads'] == -1 ? '∞' : $subscription['max_leads']; ?></small>
    </div>
    <?php endif; ?>
</div>

<div class="dashboard-grid">
    <div class="dashboard-section">
        <h2>Recent Leads</h2>
        <?php if (!empty($recent_leads)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_leads as $lead): ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/leads/view/<?php echo $lead['id']; ?>">
                                <?php echo View::escape($lead['first_name'] . ' ' . $lead['last_name']); ?>
                            </a>
                        </td>
                        <td><?php echo View::escape($lead['email']); ?></td>
                        <td><?php echo View::statusBadge($lead['status']); ?></td>
                        <td><?php echo View::formatDate($lead['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No recent leads.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>Upcoming Tasks</h2>
        <?php if (!empty($upcoming_tasks)): ?>
            <ul class="task-list">
                <?php foreach ($upcoming_tasks as $task): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/tasks/view/<?php echo $task['id']; ?>">
                        <?php echo View::escape($task['title']); ?>
                    </a>
                    <span class="task-due"><?php echo View::formatDate($task['due_date']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No upcoming tasks.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../app/views/layouts/footer.php'; ?>
