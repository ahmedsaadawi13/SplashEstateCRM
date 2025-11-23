<?php
// FILE: /app/views/leads/index.php
require_once '../app/views/layouts/header.php';

// Configure advanced filter fields for leads
$module = 'leads';
$currentFilters = $filters;

$filterFields = array(
    array(
        'name' => 'search',
        'label' => 'Search',
        'type' => 'text',
        'placeholder' => 'Name, email, phone, notes...'
    ),
    array(
        'name' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'options' => array(
            'new' => 'New',
            'contacted' => 'Contacted',
            'qualified' => 'Qualified',
            'won' => 'Won',
            'lost' => 'Lost'
        )
    ),
    array(
        'name' => 'source',
        'label' => 'Source',
        'type' => 'select',
        'options' => array(
            'Website' => 'Website',
            'Referral' => 'Referral',
            'Social Media' => 'Social Media',
            'Advertisement' => 'Advertisement',
            'Walk-in' => 'Walk-in',
            'Other' => 'Other'
        )
    ),
    array(
        'name' => 'interest_type',
        'label' => 'Interest Type',
        'type' => 'select',
        'options' => array(
            'buy' => 'Buy',
            'sell' => 'Sell',
            'rent' => 'Rent'
        )
    ),
    array(
        'name' => 'assigned_to',
        'label' => 'Assigned Agent',
        'type' => 'select',
        'options' => $agents // Populated by controller
    ),
    array(
        'name' => 'budget',
        'label' => 'Budget Range',
        'type' => 'numericrange'
    ),
    array(
        'name' => 'created',
        'label' => 'Created Date',
        'type' => 'daterange'
    )
);

$sortOptions = array(
    'l.created_at' => 'Created Date',
    'l.first_name' => 'First Name',
    'l.last_name' => 'Last Name',
    'l.status' => 'Status',
    'l.budget_min' => 'Budget'
);
?>

<div class="page-header">
    <h1>Leads</h1>
    <div class="header-actions">
        <a href="<?php echo BASE_URL; ?>/export/leadsCSV?<?php echo http_build_query($filters); ?>" class="btn btn-secondary">Export CSV</a>
        <a href="<?php echo BASE_URL; ?>/export/leadsPDF?<?php echo http_build_query($filters); ?>" class="btn btn-secondary">Export PDF</a>
        <a href="<?php echo BASE_URL; ?>/leads/create" class="btn btn-primary">Create Lead</a>
    </div>
</div>

<?php require_once '../app/views/partials/advanced_filters.php'; ?>

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
