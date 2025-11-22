<?php
// FILE: /app/views/leads/create.php
require_once '../app/views/layouts/header.php';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
if (empty($csrfToken)) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}
?>

<div class="page-header">
    <h1>Create Lead</h1>
    <a href="<?php echo BASE_URL; ?>/leads/index" class="btn btn-secondary">Back to List</a>
</div>

<div class="dashboard-section" style="max-width: 800px;">
    <form method="POST" action="<?php echo BASE_URL; ?>/leads/create">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <div class="form-group">
            <label for="first_name">First Name *</label>
            <input type="text" id="first_name" name="first_name" class="form-control"
                   value="<?php echo isset($formData['first_name']) ? View::escape($formData['first_name']) : ''; ?>" required>
            <?php if (isset($errors['first_name'])): ?>
                <span class="error"><?php echo View::escape($errors['first_name']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" class="form-control"
                   value="<?php echo isset($formData['last_name']) ? View::escape($formData['last_name']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?php echo isset($formData['email']) ? View::escape($formData['email']) : ''; ?>">
            <?php if (isset($errors['email'])): ?>
                <span class="error"><?php echo View::escape($errors['email']); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   value="<?php echo isset($formData['phone']) ? View::escape($formData['phone']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="source">Source</label>
            <input type="text" id="source" name="source" class="form-control"
                   value="<?php echo isset($formData['source']) ? View::escape($formData['source']) : 'Website'; ?>">
        </div>

        <div class="form-group">
            <label for="interest_type">Interest Type</label>
            <select id="interest_type" name="interest_type" class="form-control">
                <option value="">Select...</option>
                <option value="buy">Buy</option>
                <option value="sell">Sell</option>
                <option value="rent">Rent</option>
                <option value="lease">Lease</option>
            </select>
        </div>

        <div class="form-group">
            <label for="budget_min">Budget Min</label>
            <input type="number" id="budget_min" name="budget_min" class="form-control"
                   value="<?php echo isset($formData['budget_min']) ? View::escape($formData['budget_min']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="budget_max">Budget Max</label>
            <input type="number" id="budget_max" name="budget_max" class="form-control"
                   value="<?php echo isset($formData['budget_max']) ? View::escape($formData['budget_max']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="assigned_to">Assign To</label>
            <select id="assigned_to" name="assigned_to" class="form-control">
                <option value="">Unassigned</option>
                <?php foreach ($agents as $agent): ?>
                    <option value="<?php echo $agent['id']; ?>">
                        <?php echo View::escape($agent['first_name'] . ' ' . $agent['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="4"><?php echo isset($formData['notes']) ? View::escape($formData['notes']) : ''; ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Create Lead</button>
        <a href="<?php echo BASE_URL; ?>/leads/index" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php require_once '../app/views/layouts/footer.php'; ?>
