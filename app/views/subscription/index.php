<?php
// FILE: /app/views/subscription/index.php
include APP_PATH . '/views/layouts/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1>Subscription Management</h1>
        <p>Manage your subscription plan and billing</p>
    </div>

    <!-- Current Plan Overview -->
    <div class="card">
        <div class="card-header">
            <h2>Current Plan</h2>
        </div>
        <div class="card-body">
            <?php if (isset($currentPlan)): ?>
                <div class="current-plan-info">
                    <div class="plan-details">
                        <h3><?php echo htmlspecialchars($currentPlan['name']); ?></h3>
                        <p class="plan-price">
                            $<?php echo number_format($currentPlan['price'], 2); ?> /
                            <?php echo htmlspecialchars($currentPlan['billing_cycle']); ?>
                        </p>
                        <p class="plan-description">
                            <?php echo htmlspecialchars($currentPlan['description']); ?>
                        </p>
                    </div>

                    <?php if (isset($subscription) && $subscription): ?>
                        <div class="subscription-status">
                            <p><strong>Status:</strong>
                                <span class="badge badge-<?php echo $subscription['status'] == 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($subscription['status']); ?>
                                </span>
                            </p>
                            <?php if (isset($subscription['current_period_end'])): ?>
                                <p><strong>Next Billing Date:</strong>
                                    <?php echo date('F j, Y', strtotime($subscription['current_period_end'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="usage-stats">
                        <h4>Current Usage</h4>
                        <div class="usage-grid">
                            <div class="usage-item">
                                <div class="usage-label">Leads</div>
                                <div class="usage-value">
                                    <?php echo number_format($usage['leads']); ?> /
                                    <?php echo $currentPlan['max_leads'] == -1 ? 'Unlimited' : number_format($currentPlan['max_leads']); ?>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-progress" style="width: <?php
                                        echo $currentPlan['max_leads'] == -1 ? 0 : min(100, ($usage['leads'] / $currentPlan['max_leads']) * 100);
                                    ?>%"></div>
                                </div>
                            </div>

                            <div class="usage-item">
                                <div class="usage-label">Properties</div>
                                <div class="usage-value">
                                    <?php echo number_format($usage['properties']); ?> /
                                    <?php echo $currentPlan['max_properties'] == -1 ? 'Unlimited' : number_format($currentPlan['max_properties']); ?>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-progress" style="width: <?php
                                        echo $currentPlan['max_properties'] == -1 ? 0 : min(100, ($usage['properties'] / $currentPlan['max_properties']) * 100);
                                    ?>%"></div>
                                </div>
                            </div>

                            <div class="usage-item">
                                <div class="usage-label">Agents</div>
                                <div class="usage-value">
                                    <?php echo number_format($usage['agents']); ?> /
                                    <?php echo $currentPlan['max_agents'] == -1 ? 'Unlimited' : number_format($currentPlan['max_agents']); ?>
                                </div>
                                <div class="usage-bar">
                                    <div class="usage-progress" style="width: <?php
                                        echo $currentPlan['max_agents'] == -1 ? 0 : min(100, ($usage['agents'] / $currentPlan['max_agents']) * 100);
                                    ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <p class="alert alert-info">You don't have an active subscription plan.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Available Plans -->
    <div class="card">
        <div class="card-header">
            <h2>Available Plans</h2>
        </div>
        <div class="card-body">
            <div class="plans-grid">
                <?php foreach ($plans as $plan): ?>
                    <div class="plan-card <?php echo isset($currentPlan) && $currentPlan['id'] == $plan['id'] ? 'current-plan' : ''; ?>">
                        <div class="plan-header">
                            <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                            <?php if (isset($currentPlan) && $currentPlan['id'] == $plan['id']): ?>
                                <span class="badge badge-primary">Current Plan</span>
                            <?php endif; ?>
                        </div>

                        <div class="plan-price">
                            <span class="price-amount">$<?php echo number_format($plan['price'], 0); ?></span>
                            <span class="price-period">/ <?php echo htmlspecialchars($plan['billing_cycle']); ?></span>
                        </div>

                        <div class="plan-description">
                            <p><?php echo htmlspecialchars($plan['description']); ?></p>
                        </div>

                        <div class="plan-features">
                            <h4>What's included:</h4>
                            <ul>
                                <li><?php echo $plan['max_leads'] == -1 ? 'Unlimited' : number_format($plan['max_leads']); ?> Leads</li>
                                <li><?php echo $plan['max_properties'] == -1 ? 'Unlimited' : number_format($plan['max_properties']); ?> Properties</li>
                                <li><?php echo $plan['max_agents'] == -1 ? 'Unlimited' : number_format($plan['max_agents']); ?> Agents</li>
                                <?php
                                $features = array_map('trim', explode(',', $plan['features']));
                                foreach ($features as $feature):
                                ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="plan-action">
                            <?php if (isset($currentPlan) && $currentPlan['id'] == $plan['id']): ?>
                                <button class="btn btn-secondary" disabled>Current Plan</button>
                            <?php elseif (isset($currentPlan) && $plan['price'] > $currentPlan['price']): ?>
                                <button class="btn btn-primary" onclick="confirmPlanChange(<?php echo $plan['id']; ?>, 'upgrade')">
                                    Upgrade to <?php echo htmlspecialchars($plan['name']); ?>
                                </button>
                            <?php elseif (isset($currentPlan)): ?>
                                <button class="btn btn-outline" onclick="confirmPlanChange(<?php echo $plan['id']; ?>, 'downgrade')">
                                    Downgrade to <?php echo htmlspecialchars($plan['name']); ?>
                                </button>
                            <?php else: ?>
                                <button class="btn btn-primary" onclick="confirmPlanChange(<?php echo $plan['id']; ?>, 'subscribe')">
                                    Subscribe
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Billing Actions -->
    <?php if (isset($subscription) && $subscription && $subscription['status'] == 'active'): ?>
        <div class="card">
            <div class="card-header">
                <h2>Billing Management</h2>
            </div>
            <div class="card-body">
                <div class="billing-actions">
                    <a href="<?php echo BASE_URL; ?>/subscription/invoices" class="btn btn-outline">
                        View Payment History
                    </a>
                    <button class="btn btn-outline" onclick="managePaymentMethods()">
                        Payment Methods
                    </button>
                    <button class="btn btn-danger" onclick="confirmCancellation()">
                        Cancel Subscription
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Plan Change Confirmation Modal -->
<div id="planChangeModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Confirm Plan Change</h3>
            <span class="close" onclick="closePlanModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p id="modalMessage"></p>
            <div id="paymentMethodSection" style="display: none;">
                <h4>Payment Information</h4>
                <div id="card-element"></div>
                <div id="card-errors" class="alert alert-danger" style="display: none;"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePlanModal()">Cancel</button>
            <button class="btn btn-primary" id="confirmButton" onclick="executePlanChange()">Confirm</button>
        </div>
    </div>
</div>

<!-- Cancellation Modal -->
<div id="cancelModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Cancel Subscription</h3>
            <span class="close" onclick="closeCancelModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to cancel your subscription?</p>
            <p>Your access will continue until the end of your current billing period.</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeCancelModal()">Keep Subscription</button>
            <button class="btn btn-danger" onclick="executeCancel()">Yes, Cancel</button>
        </div>
    </div>
</div>

<style>
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.plan-card {
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    padding: 24px;
    background: #fff;
    transition: all 0.3s ease;
}

.plan-card:hover {
    border-color: #007bff;
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
}

.plan-card.current-plan {
    border-color: #28a745;
    background: #f8fff9;
}

.plan-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.plan-header h3 {
    margin: 0;
    font-size: 24px;
}

.plan-price {
    margin: 16px 0;
}

.price-amount {
    font-size: 36px;
    font-weight: bold;
    color: #007bff;
}

.price-period {
    font-size: 16px;
    color: #666;
}

.plan-features {
    margin: 20px 0;
}

.plan-features h4 {
    font-size: 14px;
    color: #666;
    margin-bottom: 12px;
}

.plan-features ul {
    list-style: none;
    padding: 0;
}

.plan-features li {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.plan-features li:before {
    content: "✓ ";
    color: #28a745;
    font-weight: bold;
    margin-right: 8px;
}

.plan-action {
    margin-top: 20px;
}

.usage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 16px;
}

.usage-item {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 6px;
}

.usage-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
}

.usage-value {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
}

.usage-bar {
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
}

.usage-progress {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s ease;
}

.billing-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.current-plan-info {
    display: grid;
    gap: 24px;
}

.plan-details {
    padding-bottom: 24px;
    border-bottom: 1px solid #e0e0e0;
}

.plan-details h3 {
    margin: 0 0 8px 0;
    font-size: 28px;
}

.plan-price {
    font-size: 24px;
    font-weight: bold;
    color: #007bff;
    margin: 8px 0;
}

.subscription-status {
    padding: 16px;
    background: #f8f9fa;
    border-radius: 6px;
}

.subscription-status p {
    margin: 8px 0;
}

.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}

.close:hover {
    color: #000;
}
</style>

<script>
let selectedPlanId = null;
let changeType = null;

function confirmPlanChange(planId, type) {
    selectedPlanId = planId;
    changeType = type;

    const modal = document.getElementById('planChangeModal');
    const title = document.getElementById('modalTitle');
    const message = document.getElementById('modalMessage');

    if (type === 'upgrade') {
        title.textContent = 'Upgrade Plan';
        message.textContent = 'You will be charged a prorated amount for the upgrade. Your next billing date will remain the same.';
    } else if (type === 'downgrade') {
        title.textContent = 'Downgrade Plan';
        message.textContent = 'Your plan will be downgraded at the end of your current billing period.';
    } else {
        title.textContent = 'Subscribe to Plan';
        message.textContent = 'Please confirm your subscription.';
        // Show payment method section for new subscriptions
        document.getElementById('paymentMethodSection').style.display = 'block';
    }

    modal.style.display = 'block';
}

function closePlanModal() {
    document.getElementById('planChangeModal').style.display = 'none';
    selectedPlanId = null;
    changeType = null;
}

function executePlanChange() {
    if (!selectedPlanId) return;

    const confirmButton = document.getElementById('confirmButton');
    confirmButton.disabled = true;
    confirmButton.textContent = 'Processing...';

    fetch('<?php echo BASE_URL; ?>/subscription/changePlan', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            plan_id: selectedPlanId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Plan changed successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to change plan'));
            confirmButton.disabled = false;
            confirmButton.textContent = 'Confirm';
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirm';
    });
}

function confirmCancellation() {
    document.getElementById('cancelModal').style.display = 'block';
}

function closeCancelModal() {
    document.getElementById('cancelModal').style.display = 'none';
}

function executeCancel() {
    if (!confirm('Are you absolutely sure?')) return;

    fetch('<?php echo BASE_URL; ?>/subscription/cancel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Subscription cancelled. Access will continue until ' + data.end_date);
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to cancel subscription'));
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function managePaymentMethods() {
    window.location.href = '<?php echo BASE_URL; ?>/subscription/paymentMethods';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const planModal = document.getElementById('planChangeModal');
    const cancelModal = document.getElementById('cancelModal');

    if (event.target === planModal) {
        closePlanModal();
    }
    if (event.target === cancelModal) {
        closeCancelModal();
    }
}
</script>

<?php include APP_PATH . '/views/layouts/footer.php'; ?>
