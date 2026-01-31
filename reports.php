<?php
require 'config.php';
require 'includes/functions.php';

// Check if admin is logged in
if (!isAdmin()) {
    header('Location: admin.php');
    exit();
}

// Set default period
$period = isset($_GET['period']) ? intval($_GET['period']) : 7;
$export = isset($_GET['export']) ? $_GET['export'] : null;

// Validate period
if (!in_array($period, [7, 15, 30, 90])) {
    $period = 7;
}

// Get report data
$report_data = getDetailedReport($period);
$daily_data = getDailyReportData($period);
$location_data = getLocationReportData($period);
$service_data = getServiceReportData($period);

// Handle PDF export
if ($export == 'pdf') {
    require_once 'includes/pdf_generator.php';
    generatePDFReport($period, $report_data, $daily_data, $location_data, $service_data);
    exit();
}

// Handle Excel export
if ($export == 'excel') {
    require_once 'includes/excel_generator.php';
    generateExcelReport($period, $report_data, $daily_data, $location_data, $service_data);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Reports - WashMate Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    .report-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        text-align: center;
    }
    .period-selector {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }
    .period-btn {
        padding: 10px 20px;
        background: rgba(255,255,255,0.2);
        color: white;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    .period-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
    }
    .period-btn.active {
        background: white;
        color: #667eea;
    }
    .export-options {
        display: flex;
        justify-content: center;
        gap: 15px;
        margin-top: 20px;
    }
    .stats-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-top: 4px solid;
    }
    .stat-card.washing {
        border-color: #4361ee;
    }
    .stat-card.tea {
        border-color: #f59e0b;
    }
    .stat-card.coffee {
        border-color: #92400e;
    }
    .stat-card.total {
        border-color: #10b981;
    }
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 15px;
    }
    .stat-card.washing .stat-icon {
        color: #4361ee;
    }
    .stat-card.tea .stat-icon {
        color: #f59e0b;
    }
    .stat-card.coffee .stat-icon {
        color: #92400e;
    }
    .stat-card.total .stat-icon {
        color: #10b981;
    }
    .stat-amount {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 10px 0;
    }
    .stat-label {
        color: #6b7280;
        font-size: 0.95rem;
    }
    .chart-container {
        background: white;
        padding: 25px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .chart-wrapper {
        height: 300px;
        margin-top: 20px;
    }
    .tables-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    .data-table {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .data-table h3 {
        margin-bottom: 20px;
        color: #374151;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .daily-table {
        max-height: 400px;
        overflow-y: auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th {
        background: #f8fafc;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
    }
    td {
        padding: 12px 15px;
        border-bottom: 1px solid #e5e7eb;
        color: #4b5563;
    }
    tr:hover {
        background: #f9fafb;
    }
    .positive {
        color: #10b981;
        font-weight: 600;
    }
    .location-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
    }
    .location-item:last-child {
        border-bottom: none;
    }
    .location-name {
        font-weight: 600;
        color: #374151;
    }
    .location-income {
        font-weight: 700;
        color: #10b981;
    }
    .no-data {
        text-align: center;
        padding: 40px;
        color: #9ca3af;
    }
    .no-data i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    </style>
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <div class="header-left">
                <div class="logo">
                    <i class="fas fa-washing-machine"></i>
                    <h1>WashMate <span class="admin-badge">Reports</span></h1>
                </div>
                <div class="admin-info">
                    <span class="welcome">Income Reports</span>
                    <h2 class="admin-name">Last <?php echo $period; ?> Days</h2>
                </div>
            </div>
            <div class="header-right">
                <a href="admin.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Admin
                </a>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <main class="admin-main">
            <!-- Report Header -->
            <div class="report-header">
                <h1><i class="fas fa-chart-line"></i> Income Analysis Report</h1>
                <p>Detailed breakdown of all income sources for the selected period</p>
                
                <div class="period-selector">
                    <a href="?period=7" class="period-btn <?php echo $period == 7 ? 'active' : ''; ?>">
                        7 Days
                    </a>
                    <a href="?period=15" class="period-btn <?php echo $period == 15 ? 'active' : ''; ?>">
                        15 Days
                    </a>
                    <a href="?period=30" class="period-btn <?php echo $period == 30 ? 'active' : ''; ?>">
                        30 Days
                    </a>
                    <a href="?period=90" class="period-btn <?php echo $period == 90 ? 'active' : ''; ?>">
                        90 Days
                    </a>
                </div>
                
                <div class="export-options">
                    <a href="?period=<?php echo $period; ?>&export=pdf" class="btn btn-primary">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    <a href="?period=<?php echo $period; ?>&export=excel" class="btn btn-success">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                    <a href="reports.php?print=1" class="btn btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Report
                    </a>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="stats-cards">
                <div class="stat-card washing">
                    <div class="stat-icon">
                        <i class="fas fa-washing-machine"></i>
                    </div>
                    <div class="stat-amount">৳<?php echo number_format($report_data['washing_income'], 2); ?></div>
                    <div class="stat-label">Washing Machine Income</div>
                    <div class="stat-subtext"><?php echo $report_data['washing_bookings']; ?> bookings</div>
                </div>
                
                <div class="stat-card tea">
                    <div class="stat-icon">
                        <i class="fas fa-mug-hot"></i>
                    </div>
                    <div class="stat-amount">৳<?php echo number_format($report_data['tea_income'], 2); ?></div>
                    <div class="stat-label">Tea Sales</div>
                    <div class="stat-subtext"><?php echo $report_data['tea_sales']; ?> cups</div>
                </div>
                
                <div class="stat-card coffee">
                    <div class="stat-icon">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <div class="stat-amount">৳<?php echo number_format($report_data['coffee_income'], 2); ?></div>
                    <div class="stat-label">Coffee Sales</div>
                    <div class="stat-subtext"><?php echo $report_data['coffee_sales']; ?> cups</div>
                </div>
                
                <div class="stat-card total">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-amount">৳<?php echo number_format($report_data['total_income'], 2); ?></div>
                    <div class="stat-label">Total Income</div>
                    <div class="stat-subtext"><?php echo $report_data['total_transactions']; ?> transactions</div>
                </div>
            </div>

            <!-- Income Trend Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-area"></i> Income Trend (Last <?php echo $period; ?> Days)</h3>
                <div class="chart-wrapper">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>

            <!-- Tables Container -->
            <div class="tables-container">
                <!-- Daily Income Table -->
                <div class="data-table daily-table">
                    <h3><i class="fas fa-calendar-day"></i> Daily Income</h3>
                    <?php if (count($daily_data) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Washing</th>
                                <th>Tea</th>
                                <th>Coffee</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($daily_data as $day): ?>
                            <tr>
                                <td><?php echo date('M d', strtotime($day['date'])); ?></td>
                                <td class="positive">৳<?php echo number_format($day['washing_income'], 2); ?></td>
                                <td class="positive">৳<?php echo number_format($day['tea_income'], 2); ?></td>
                                <td class="positive">৳<?php echo number_format($day['coffee_income'], 2); ?></td>
                                <td><strong>৳<?php echo number_format($day['total_income'], 2); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-database"></i>
                        <p>No daily data available</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Location-wise Income -->
                <div class="data-table">
                    <h3><i class="fas fa-map-marker-alt"></i> Location Performance</h3>
                    <?php if (count($location_data) > 0): ?>
                        <?php foreach ($location_data as $location): ?>
                        <div class="location-item">
                            <div class="location-info">
                                <div class="location-name"><?php echo htmlspecialchars($location['name']); ?></div>
                                <div class="location-stats">
                                    <small><?php echo $location['bookings']; ?> bookings</small>
                                </div>
                            </div>
                            <div class="location-income">
                                ৳<?php echo number_format($location['income'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-map-marker-alt"></i>
                        <p>No location data available</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Service Breakdown Chart -->
            <div class="chart-container">
                <h3><i class="fas fa-chart-pie"></i> Service Breakdown</h3>
                <div class="chart-wrapper">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>

            <!-- Additional Insights -->
            <div class="admin-card">
                <div class="card-header">
                    <h2><i class="fas fa-lightbulb"></i> Key Insights</h2>
                </div>
                <div class="insights-grid">
                    <div class="insight-item">
                        <div class="insight-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Average Daily Income</h4>
                            <p class="insight-value">৳<?php echo number_format($report_data['avg_daily_income'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="insight-item">
                        <div class="insight-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Best Performing Service</h4>
                            <p class="insight-value"><?php echo $service_data['best_service']; ?></p>
                            <p class="insight-subtext">৳<?php echo number_format($service_data['best_service_income'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="insight-item">
                        <div class="insight-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Busiest Day</h4>
                            <p class="insight-value"><?php echo $service_data['busiest_day']; ?></p>
                            <p class="insight-subtext">৳<?php echo number_format($service_data['busiest_day_income'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="insight-item">
                        <div class="insight-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Active Users</h4>
                            <p class="insight-value"><?php echo $report_data['active_users']; ?> users</p>
                            <p class="insight-subtext"><?php echo $report_data['new_users']; ?> new this period</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Prepare chart data
    const dailyLabels = <?php echo json_encode(array_map(function($d) { 
        return date('M d', strtotime($d['date'])); 
    }, $daily_data)); ?>;
    
    const washingData = <?php echo json_encode(array_column($daily_data, 'washing_income')); ?>;
    const teaData = <?php echo json_encode(array_column($daily_data, 'tea_income')); ?>;
    const coffeeData = <?php echo json_encode(array_column($daily_data, 'coffee_income')); ?>;
    
    // Income Trend Chart
    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
    new Chart(incomeCtx, {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [
                {
                    label: 'Washing',
                    data: washingData,
                    borderColor: '#4361ee',
                    backgroundColor: 'rgba(67, 97, 238, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Tea',
                    data: teaData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Coffee',
                    data: coffeeData,
                    borderColor: '#92400e',
                    backgroundColor: 'rgba(146, 64, 14, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ৳' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '৳' + value;
                        }
                    }
                }
            }
        }
    });

    // Service Breakdown Chart
    const serviceCtx = document.getElementById('serviceChart').getContext('2d');
    new Chart(serviceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Washing Machine', 'Tea', 'Coffee'],
            datasets: [{
                data: [
                    <?php echo $report_data['washing_income']; ?>,
                    <?php echo $report_data['tea_income']; ?>,
                    <?php echo $report_data['coffee_income']; ?>
                ],
                backgroundColor: [
                    '#4361ee',
                    '#f59e0b',
                    '#92400e'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return context.label + ': ৳' + value.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Print functionality
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            window.print();
        }
    });

    // Auto-refresh every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>