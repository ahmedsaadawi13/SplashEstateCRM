<?php
// FILE: /app/views/clients/index.php
require_once '../app/views/layouts/header.php';
?>

<div class="page-header">
    <h1>Clients</h1>
    <a href="<?php echo BASE_URL; ?>/clients/create" class="btn btn-primary">Create Client</a>
</div>

<div class="filters">
    <form method="GET" action="<?php echo BASE_URL; ?>/clients/index" class="filter-form">
        <input type="text" name="search" placeholder="Search..." value="<?php echo isset($filters['search']) ? View::escape($filters['search']) : ''; ?>" class="form-control">

        <select name="client_type" class="form-control">
            <option value="">All Types</option>
            <option value="buyer" <?php echo (isset($filters['client_type']) && $filters['client_type'] === 'buyer') ? 'selected' : ''; ?>>Buyer</option>
            <option value="seller" <?php echo (isset($filters['client_type']) && $filters['client_type'] === 'seller') ? 'selected' : ''; ?>>Seller</option>
            <option value="landlord" <?php echo (isset($filters['client_type']) && $filters['client_type'] === 'landlord') ? 'selected' : ''; ?>>Landlord</option>
            <option value="tenant" <?php echo (isset($filters['client_type']) && $filters['client_type'] === 'tenant') ? 'selected' : ''; ?>>Tenant</option>
        </select>

        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?php echo BASE_URL; ?>/clients/index" class="btn btn-link">Clear</a>
    </form>
</div>

<?php if (!empty($clients)): ?>
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clients as $client): ?>
        <tr>
            <td>
                <a href="<?php echo BASE_URL; ?>/clients/view/<?php echo $client['id']; ?>">
                    <?php echo View::escape($client['first_name'] . ' ' . $client['last_name']); ?>
                </a>
            </td>
            <td><?php echo View::escape($client['email']); ?></td>
            <td><?php echo View::escape($client['phone']); ?></td>
            <td><?php echo View::escape(ucfirst($client['client_type'])); ?></td>
            <td><?php echo View::statusBadge($client['status']); ?></td>
            <td><?php echo View::formatDate($client['created_at']); ?></td>
            <td>
                <a href="<?php echo BASE_URL; ?>/clients/edit/<?php echo $client['id']; ?>" class="btn btn-sm">Edit</a>
                <a href="<?php echo BASE_URL; ?>/clients/view/<?php echo $client['id']; ?>" class="btn btn-sm">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php echo View::pagination($currentPage, $totalPages, BASE_URL . '/clients/index'); ?>

<?php else: ?>
<p>No clients found.</p>
<?php endif; ?>

<?php require_once '../app/views/layouts/footer.php'; ?>
