<?php
// FILE: /app/views/subscription/invoices.php
include APP_PATH . '/views/layouts/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1>Payment History</h1>
        <p>View your billing and invoice history</p>
        <a href="<?php echo BASE_URL; ?>/subscription/index" class="btn btn-secondary">
            &larr; Back to Subscription
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Invoices</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($invoices)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($invoice['number']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', $invoice['created']); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($invoice['lines']['data'])) {
                                            echo htmlspecialchars($invoice['lines']['data'][0]['description']);
                                        } else {
                                            echo 'Subscription Payment';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($invoice['amount_paid'] / 100, 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = ucfirst($invoice['status']);

                                        switch ($invoice['status']) {
                                            case 'paid':
                                                $statusClass = 'success';
                                                break;
                                            case 'open':
                                                $statusClass = 'warning';
                                                break;
                                            case 'void':
                                            case 'uncollectible':
                                                $statusClass = 'danger';
                                                break;
                                            default:
                                                $statusClass = 'secondary';
                                        }
                                        ?>
                                        <span class="badge badge-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($invoice['invoice_pdf']): ?>
                                            <a href="<?php echo htmlspecialchars($invoice['invoice_pdf']); ?>"
                                               target="_blank"
                                               class="btn btn-sm btn-outline">
                                                Download PDF
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($invoice['hosted_invoice_url']): ?>
                                            <a href="<?php echo htmlspecialchars($invoice['hosted_invoice_url']); ?>"
                                               target="_blank"
                                               class="btn btn-sm btn-primary">
                                                View Invoice
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <p>No payment history available yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Summary -->
    <?php if (!empty($invoices)): ?>
        <div class="card">
            <div class="card-header">
                <h2>Payment Summary</h2>
            </div>
            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-label">Total Paid</div>
                        <div class="summary-value">
                            $<?php
                            $total = 0;
                            foreach ($invoices as $invoice) {
                                if ($invoice['status'] === 'paid') {
                                    $total += $invoice['amount_paid'];
                                }
                            }
                            echo number_format($total / 100, 2);
                            ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <div class="summary-label">Total Invoices</div>
                        <div class="summary-value">
                            <?php echo count($invoices); ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <div class="summary-label">Paid Invoices</div>
                        <div class="summary-value">
                            <?php
                            $paidCount = 0;
                            foreach ($invoices as $invoice) {
                                if ($invoice['status'] === 'paid') {
                                    $paidCount++;
                                }
                            }
                            echo $paidCount;
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
}

.table th,
.table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #666;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 16px;
}

.summary-item {
    padding: 24px;
    background: #f8f9fa;
    border-radius: 6px;
    text-align: center;
}

.summary-label {
    font-weight: 600;
    color: #666;
    margin-bottom: 8px;
}

.summary-value {
    font-size: 32px;
    font-weight: bold;
    color: #007bff;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 14px;
}

.content-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.content-header > div {
    flex: 1;
}
</style>

<?php include APP_PATH . '/views/layouts/footer.php'; ?>
