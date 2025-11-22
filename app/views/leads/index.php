<?php
// FILE: /app/views/leads/index.php
require_once '../app/views/layouts/header.php';
?>

<div class="page-header">
    <h1>Leads</h1>
    <a href="<?php echo BASE_URL; ?>/leads/create" class="btn btn-primary">Create Lead</a>
</div>

<div class="filters">
    <form method="GET" action="<?php echo BASE_URL; ?>/leads/index" class="filter-form">
        <input type="text" name="search" placeholder="Search..." value="<?php echo isset($filters['search']) ? View::escape($filters['search']) : ''; ?>" class="form-control">

        <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="new" <?php echo (isset($filters['status']) && $filters['status'] === 'new') ? 'selected' : ''; ?>>New</option>
            <option value="contacted" <?php echo (isset($filters['status']) && $filters['status'] === 'contacted') ? 'selected' : ''; ?>>Contacted</option>
            <option value="qualified" <?php echo (isset($filters['status']) && $filters['status'] === 'qualified') ? 'selected' : ''; ?>>Qualified</option>
            <option value="won" <?php echo (isset($filters['status']) && $filters['status'] === 'won') ? 'selected' : ''; ?>>Won</option>
            <option value="lost" <?php echo (isset($filters['status']) && $filters['status'] === 'lost') ? 'selected' : ''; ?>>Lost</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?php echo BASE_URL; ?>/leads/index" class="btn btn-link">Clear</a>
    </form>
</div>

<?php if (!empty($leads)): ?>
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Status</th>
            <th>Source</th>
            <th>Assigned To</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($leads as $lead): ?>
        <tr>
            <td>
                <a href="<?php echo BASE_URL; ?>/leads/view/<?php echo $lead['id']; ?>">
                    <?php echo View::escape($lead['first_name'] . ' ' . $lead['last_name']); ?>
                </a>
            </td>
            <td><?php echo View::escape($lead['email']); ?></td>
            <td><?php echo View::escape($lead['phone']); ?></td>
            <td><?php echo View::statusBadge($lead['status']); ?></td>
            <td><?php echo View::escape($lead['source']); ?></td>
            <td><?php echo View::escape($lead['assigned_to_name']); ?></td>
            <td><?php echo View::formatDate($lead['created_at']); ?></td>
            <td>
                <a href="<?php echo BASE_URL; ?>/leads/edit/<?php echo $lead['id']; ?>" class="btn btn-sm">Edit</a>
                <a href="<?php echo BASE_URL; ?>/leads/view/<?php echo $lead['id']; ?>" class="btn btn-sm">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php echo View::pagination($currentPage, $totalPages, BASE_URL . '/leads/index'); ?>

<?php else: ?>
<p>No leads found.</p>
<?php endif; ?>

<?php require_once '../app/views/layouts/footer.php'; ?>
