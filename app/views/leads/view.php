<?php
// FILE: /app/views/leads/view.php
require_once '../app/views/layouts/header.php';
?>

<div class="page-header">
    <h1>Lead Details</h1>
    <div>
        <a href="<?php echo BASE_URL; ?>/leads/edit/<?php echo $lead['id']; ?>" class="btn btn-primary">Edit</a>
        <a href="<?php echo BASE_URL; ?>/leads/index" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="dashboard-grid">
    <div class="dashboard-section">
        <h2>Contact Information</h2>
        <table class="table">
            <tr>
                <td><strong>Name:</strong></td>
                <td><?php echo View::escape($lead['first_name'] . ' ' . $lead['last_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><a href="mailto:<?php echo View::escape($lead['email']); ?>"><?php echo View::escape($lead['email']); ?></a></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><a href="tel:<?php echo View::escape($lead['phone']); ?>"><?php echo View::escape($lead['phone']); ?></a></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td><?php echo View::statusBadge($lead['status']); ?></td>
            </tr>
            <tr>
                <td><strong>Source:</strong></td>
                <td><?php echo View::escape($lead['source']); ?></td>
            </tr>
            <tr>
                <td><strong>Interest Type:</strong></td>
                <td><?php echo View::escape(ucfirst($lead['interest_type'])); ?></td>
            </tr>
            <tr>
                <td><strong>Budget Range:</strong></td>
                <td><?php echo View::formatCurrency($lead['budget_min']) . ' - ' . View::formatCurrency($lead['budget_max']); ?></td>
            </tr>
            <tr>
                <td><strong>Assigned To:</strong></td>
                <td><?php echo View::escape($lead['assigned_to_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Created:</strong></td>
                <td><?php echo View::formatDate($lead['created_at']); ?></td>
            </tr>
        </table>

        <?php if (!empty($lead['notes'])): ?>
        <div>
            <h3>Notes</h3>
            <p><?php echo nl2br(View::escape($lead['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($lead['status'] !== 'won' && $lead['status'] !== 'lost'): ?>
        <div style="margin-top: 1rem;">
            <a href="<?php echo BASE_URL; ?>/leads/convert/<?php echo $lead['id']; ?>" class="btn btn-success">Convert to Client</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>Activities</h2>
        <?php if (!empty($activities)): ?>
            <ul class="task-list">
                <?php foreach ($activities as $activity): ?>
                <li>
                    <div>
                        <strong><?php echo View::escape($activity['subject']); ?></strong>
                        <br>
                        <small><?php echo View::escape($activity['activity_type']); ?> - <?php echo View::escape($activity['user_name']); ?></small>
                        <br>
                        <?php if (!empty($activity['description'])): ?>
                        <p><?php echo View::escape($activity['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="task-due"><?php echo View::formatDate($activity['created_at']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No activities recorded.</p>
        <?php endif; ?>

        <h2 style="margin-top: 2rem;">Attachments</h2>
        <?php if (!empty($attachments)): ?>
            <ul class="task-list">
                <?php foreach ($attachments as $attachment): ?>
                <li>
                    <div>
                        <a href="<?php echo BASE_URL; ?>/upload/download/<?php echo $attachment['id']; ?>">
                            <?php echo View::escape($attachment['file_name']); ?>
                        </a>
                        <small>(<?php echo number_format($attachment['file_size'] / 1024, 2); ?> KB)</small>
                        <br>
                        <small>Uploaded by <?php echo View::escape($attachment['uploaded_by']); ?></small>
                    </div>
                    <span class="task-due"><?php echo View::formatDate($attachment['created_at']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No attachments.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../app/views/layouts/footer.php'; ?>
