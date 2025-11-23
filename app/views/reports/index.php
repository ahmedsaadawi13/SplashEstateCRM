<?php
// FILE: /app/views/reports/index.php
require_once '../app/views/layouts/header.php';
?>

<div class="page-header">
    <h1>Reports & Analytics</h1>
    <div class="header-actions">
        <form method="GET" action="<?php echo BASE_URL; ?>/reports/index" class="date-filter-form">
            <label>From:</label>
            <input type="date" name="start_date" value="<?php echo $startDate; ?>">
            <label>To:</label>
            <input type="date" name="end_date" value="<?php echo $endDate; ?>">
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>

<div class="reports-grid">
    <!-- Lead Conversion Funnel -->
    <div class="report-card">
        <div class="card-header">
            <h3>Lead Conversion Funnel</h3>
            <a href="<?php echo BASE_URL; ?>/reports/export/lead-funnel?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm">Export</a>
        </div>
        <div class="card-body">
            <canvas id="leadFunnelChart"></canvas>
        </div>
        <div class="card-footer">
            <a href="<?php echo BASE_URL; ?>/reports/leads" class="btn btn-link">View Details →</a>
        </div>
    </div>

    <!-- Lead Sources -->
    <div class="report-card">
        <div class="card-header">
            <h3>Lead Sources</h3>
        </div>
        <div class="card-body">
            <canvas id="leadSourcesChart"></canvas>
        </div>
    </div>

    <!-- Revenue Trends -->
    <div class="report-card full-width">
        <div class="card-header">
            <h3>Revenue Trends (Last 6 Months)</h3>
            <a href="<?php echo BASE_URL; ?>/reports/export/revenue-trends" class="btn btn-sm">Export</a>
        </div>
        <div class="card-body">
            <canvas id="revenueTrendsChart"></canvas>
        </div>
        <div class="card-footer">
            <a href="<?php echo BASE_URL; ?>/reports/revenue" class="btn btn-link">View Details →</a>
        </div>
    </div>

    <!-- Deals Pipeline -->
    <div class="report-card">
        <div class="card-header">
            <h3>Deals Pipeline</h3>
        </div>
        <div class="card-body">
            <canvas id="dealsPipelineChart"></canvas>
        </div>
    </div>

    <!-- Property Types -->
    <div class="report-card">
        <div class="card-header">
            <h3>Property Type Distribution</h3>
        </div>
        <div class="card-body">
            <canvas id="propertyTypesChart"></canvas>
        </div>
        <div class="card-footer">
            <a href="<?php echo BASE_URL; ?>/reports/properties" class="btn btn-link">View Details →</a>
        </div>
    </div>

    <!-- Agent Performance -->
    <div class="report-card full-width">
        <div class="card-header">
            <h3>Agent Performance</h3>
            <a href="<?php echo BASE_URL; ?>/reports/export/agent-performance?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-sm">Export</a>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Total Leads</th>
                        <th>Won Leads</th>
                        <th>Total Deals</th>
                        <th>Won Deals</th>
                        <th>Revenue</th>
                        <th>Commission</th>
                        <th>Conversion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dashboardData['agents'] as $agent): ?>
                    <tr>
                        <td><strong><?php echo View::escape($agent['agent_name']); ?></strong></td>
                        <td><?php echo $agent['total_leads']; ?></td>
                        <td><?php echo $agent['won_leads']; ?></td>
                        <td><?php echo $agent['total_deals']; ?></td>
                        <td><?php echo $agent['won_deals']; ?></td>
                        <td><?php echo View::formatCurrency($agent['total_revenue']); ?></td>
                        <td><?php echo View::formatCurrency($agent['total_commission']); ?></td>
                        <td>
                            <?php
                            $conversionRate = $agent['total_leads'] > 0 ? ($agent['won_leads'] / $agent['total_leads'] * 100) : 0;
                            echo number_format($conversionRate, 1) . '%';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <a href="<?php echo BASE_URL; ?>/reports/agents" class="btn btn-link">View Details →</a>
        </div>
    </div>
</div>

<style>
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.report-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.report-card.full-width {
    grid-column: 1 / -1;
}

.card-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 18px;
    color: #2c3e50;
}

.card-body {
    padding: 20px;
}

.card-footer {
    padding: 10px 20px;
    background: #f8f9fa;
    border-top: 1px solid #ddd;
    text-align: right;
}

.date-filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.date-filter-form label {
    font-weight: 600;
    margin: 0;
}

.date-filter-form input[type="date"] {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<script>
// Dashboard data from PHP
const dashboardData = <?php echo json_encode($dashboardData); ?>;

// Chart color palettes
const colorPalette = [
    '#3498db', '#2ecc71', '#f39c12', '#e74c3c', '#9b59b6',
    '#1abc9c', '#34495e', '#16a085', '#27ae60', '#2980b9'
];

// Lead Conversion Funnel Chart
const leadFunnelCtx = document.getElementById('leadFunnelChart').getContext('2d');
new Chart(leadFunnelCtx, {
    type: 'bar',
    data: {
        labels: dashboardData.leads.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
        datasets: [{
            label: 'Number of Leads',
            data: dashboardData.leads.map(item => item.count),
            backgroundColor: colorPalette.slice(0, dashboardData.leads.length),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Lead Sources Pie Chart
const leadSourcesCtx = document.getElementById('leadSourcesChart').getContext('2d');
new Chart(leadSourcesCtx, {
    type: 'pie',
    data: {
        labels: dashboardData.sources.map(item => item.source),
        datasets: [{
            data: dashboardData.sources.map(item => item.count),
            backgroundColor: colorPalette.slice(0, dashboardData.sources.length)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Revenue Trends Line Chart
const revenueTrendsCtx = document.getElementById('revenueTrendsChart').getContext('2d');
new Chart(revenueTrendsCtx, {
    type: 'line',
    data: {
        labels: dashboardData.revenue_trends.map(item => item.period),
        datasets: [
            {
                label: 'Revenue',
                data: dashboardData.revenue_trends.map(item => parseFloat(item.revenue)),
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Commission',
                data: dashboardData.revenue_trends.map(item => parseFloat(item.commission)),
                borderColor: '#2ecc71',
                backgroundColor: 'rgba(46, 204, 113, 0.1)',
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Deals Pipeline Chart
const dealsPipelineCtx = document.getElementById('dealsPipelineChart').getContext('2d');
new Chart(dealsPipelineCtx, {
    type: 'doughnut',
    data: {
        labels: dashboardData.pipeline.map(item => item.stage.replace('_', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')),
        datasets: [{
            data: dashboardData.pipeline.map(item => item.count),
            backgroundColor: colorPalette.slice(0, dashboardData.pipeline.length)
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Property Types Chart
const propertyTypesCtx = document.getElementById('propertyTypesChart').getContext('2d');
new Chart(propertyTypesCtx, {
    type: 'bar',
    data: {
        labels: dashboardData.properties.map(item => item.property_type.charAt(0).toUpperCase() + item.property_type.slice(1)),
        datasets: [
            {
                label: 'Total',
                data: dashboardData.properties.map(item => item.count),
                backgroundColor: '#3498db'
            },
            {
                label: 'Sold',
                data: dashboardData.properties.map(item => item.sold_count),
                backgroundColor: '#2ecc71'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});
</script>

<?php require_once '../app/views/layouts/footer.php'; ?>
