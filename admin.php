<?php
require 'config.php';
require 'includes/functions.php';

// Simple admin authentication
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['admin_login'])) {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);
    
    // Hardcoded admin credentials (for demo)
    $admin_username = 'admin';
    $admin_password = 'admin123'; // In production, use hashed passwords
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_name'] = 'Administrator';
        header('Location: admin.php');
        exit();
    } else {
        $error = "Invalid admin credentials";
    }
}

// Check if admin is logged in
if (!isset($_SESSION['is_admin'])) {
    // Show login form ONLY
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - WashMate</title>
        <link rel="stylesheet" href="css/style.css?ver=1.1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body>
        <div class="login-container">
            <div class="login-wrapper">
                <div class="login-header">
                    <div class="logo">
                        <i class="fas fa-washing-machine"></i>
                        <h1>WashMate Admin</h1>
                    </div>
                    <p class="tagline">Administrator Control Panel</p>
                </div>
                
                <div class="login-card admin-login">
                    <div class="card-header">
                        <h2><i class="fas fa-lock"></i> Admin Authentication</h2>
                        <p>Enter admin credentials to continue</p>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="error-box">
                            <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="login-form">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <input type="text" name="username" placeholder="Admin Username" required>
                        </div>
                        
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <input type="password" name="password" placeholder="Admin Password" required>
                        </div>
                        
                        <input type="hidden" name="admin_login" value="1">
                        
                        <button type="submit" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login as Admin
                        </button>
                        
                        <div class="login-footer">
                            <a href="index.php" class="back-link">
                                <i class="fas fa-arrow-left"></i> Back to User Login
                            </a>
                        </div>
                    </form>
                </div>
                
                <div class="security-notice">
                    <h3><i class="fas fa-shield-alt"></i> Security Notice</h3>
                    <p>This panel is restricted to authorized personnel only. Unauthorized access is prohibited.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit(); // Stop execution here if not logged in
}

// ========== ADMIN IS LOGGED IN - SHOW DASHBOARD ========== //
// Prevent regular users from accessing admin panel
if (!isAdmin()) {
    header('Location: index.php');
    exit();
}

// Handle recharge approval
if (isset($_POST['approve_recharge'])) {
    $request_id = sanitize($_POST['request_id']);
    $amount = floatval(sanitize($_POST['amount']));
    
    if ($amount > 0) {
        approveRecharge($request_id, $amount);
        $success_msg = "Recharge approved successfully!";
    } else {
        $error_msg = "Please enter a valid amount";
    }
}

// Handle recharge rejection
if (isset($_POST['reject_recharge'])) {
    $request_id = sanitize($_POST['request_id']);
    $reject_reason = sanitize($_POST['reject_reason']);
    
    rejectRecharge($request_id, $reject_reason);
    $success_msg = "Recharge request rejected successfully!";
}

// Handle manual top-up
if (isset($_POST['manual_topup'])) {
    $mobile = sanitize($_POST['mobile']);
    $amount = floatval(sanitize($_POST['amount']));
    $note = sanitize($_POST['note']);
    
    $result = manualTopup($mobile, $amount, $note);
    if ($result['success']) {
        $topup_success = "Successfully added ৳$amount to $mobile";
    } else {
        $topup_error = $result['message'];
    }
}

// Handle location management
if (isset($_POST['add_location'])) {
    $name = sanitize($_POST['location_name']);
    $description = sanitize($_POST['location_description']);
    
    $sql = "INSERT INTO locations (name, description) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $name, $description);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_msg = "Location added successfully!";
    } else {
        $error_msg = "Failed to add location";
    }
    mysqli_stmt_close($stmt);
}

// Handle price update
if (isset($_POST['update_price'])) {
    $service_type = sanitize($_POST['service_type']);
    $amount = floatval(sanitize($_POST['price_amount']));
    
    // Deactivate old price
    $deactivate_sql = "UPDATE service_prices SET status = 'inactive' WHERE service_type = ? AND status = 'active'";
    $deactivate_stmt = mysqli_prepare($conn, $deactivate_sql);
    mysqli_stmt_bind_param($deactivate_stmt, "s", $service_type);
    mysqli_stmt_execute($deactivate_stmt);
    mysqli_stmt_close($deactivate_stmt);
    
    // Insert new price
    $insert_sql = "INSERT INTO service_prices (service_type, amount, effective_from) VALUES (?, ?, CURDATE())";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($insert_stmt, "sd", $service_type, $amount);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        $success_msg = "Price updated successfully!";
        
        // Update config constants (for current session)
        if ($service_type == 'washing') {
            define('WASHING_PRICE', $amount);
        } elseif ($service_type == 'tea') {
            define('TEA_PRICE', $amount);
        } elseif ($service_type == 'coffee') {
            define('COFFEE_PRICE', $amount);
        }
    } else {
        $error_msg = "Failed to update price";
    }
    mysqli_stmt_close($insert_stmt);
}

// Handle report reset
if (isset($_POST['reset_report'])) {
    $report_type = sanitize($_POST['report_type']);
    
    if ($report_type == 'income') {
        mysqli_query($conn, "TRUNCATE TABLE daily_income");
        mysqli_query($conn, "UPDATE transactions SET note = CONCAT(note, ' [RESET]')");
        $success_msg = "Income reports reset successfully!";
    } elseif ($report_type == 'users') {
        // Only delete users with zero balance and no recent activity
        $sql = "DELETE FROM users WHERE balance = 0 AND last_login < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        mysqli_query($conn, $sql);
        $success_msg = "Inactive users cleaned up successfully!";
    }
}

// Get statistics
$stats = getAdminStatistics();

// Get pending recharge requests
$recharge_requests = getPendingRecharges();

// Get recharge history
$recharge_history = getRechargeHistory(20);

// Get today's bookings
$today_bookings = getTodayBookings();

// Get all locations
$locations = getAllLocations();

// Get current prices
$current_prices = getCurrentPrices();

// Get card users
$card_users = getCardUsers();

// Get income report data
$income_report_7 = getIncomeReport(7);
$income_report_15 = getIncomeReport(15);
$income_report_30 = getIncomeReport(30);

// Get location-wise income
$location_income = getLocationWiseIncome();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WashMate</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/charts.css/dist/charts.min.css">
    <style>
    .admin-tabs {
        display: flex;
        background: white;
        border-radius: 10px;
        margin-bottom: 20px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .tab {
        flex: 1;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        background: #f8fafc;
        border-right: 1px solid #e5e7eb;
        transition: all 0.3s;
        font-weight: 600;
        color: #6b7280;
    }
    .tab:last-child {
        border-right: none;
    }
    .tab:hover {
        background: #e5e7eb;
    }
    .tab.active {
        background: #4361ee;
        color: white;
    }
    .tab-content {
        display: none;
        animation: fadeIn 0.5s;
    }
    .tab-content.active {
        display: block;
    }
    .price-control-form {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    .price-display {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .price-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-top: 4px solid;
    }
    .price-card.washing {
        border-color: #4361ee;
    }
    .price-card.tea {
        border-color: #f59e0b;
    }
    .price-card.coffee {
        border-color: #92400e;
    }
    .price-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    .price-card.washing .price-icon {
        color: #4361ee;
    }
    .price-card.tea .price-icon {
        color: #f59e0b;
    }
    .price-card.coffee .price-icon {
        color: #92400e;
    }
    .price-amount {
        font-size: 2rem;
        font-weight: 700;
        margin: 10px 0;
    }
    .price-effective {
        font-size: 0.9rem;
        color: #6b7280;
    }
    .location-manager {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
    }
    .chart-container {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .chart-title {
        margin-bottom: 15px;
        color: #374151;
        font-size: 1.2rem;
    }
    .report-periods {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    .report-period {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .report-income {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 10px 0;
        color: #10b981;
    }
    .reset-options {
        background: #fef2f2;
        padding: 20px;
        border-radius: 10px;
        margin-top: 30px;
        border-left: 4px solid #ef4444;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Admin Header -->
        <header class="admin-header">
            <div class="header-left">
                <div class="logo">
                    <i class="fas fa-washing-machine"></i>
                    <h1>WashMate <span class="admin-badge">Admin</span></h1>
                </div>
                <div class="admin-info">
                    <span class="welcome">Welcome,</span>
                    <h2 class="admin-name"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></h2>
                </div>
            </div>
            <div class="header-right">
                <div class="header-stats">
                    <div class="stat">
                        <i class="fas fa-users"></i>
                        <span><?php echo $stats['total_users']; ?> Users</span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>৳<?php echo number_format($stats['total_income'], 2); ?></span>
                    </div>
                    <div class="stat">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $stats['pending_recharges']; ?> Pending</span>
                    </div>
                </div>
                <a href="logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <main class="admin-main">
            <!-- Tabs Navigation -->
            <div class="admin-tabs" id="adminTabs">
                <div class="tab active" data-tab="dashboard">Dashboard</div>
                <div class="tab" data-tab="recharges">Recharges</div>
                <div class="tab" data-tab="bookings">Bookings</div>
                <div class="tab" data-tab="prices">Price Control</div>
                <div class="tab" data-tab="locations">Locations</div>
                <div class="tab" data-tab="cards">Card Users</div>
                <div class="tab" data-tab="reports">Reports</div>
                <div class="tab" data-tab="management">Management</div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Dashboard Tab -->
            <div class="tab-content active" id="dashboard">
                <div class="stats-overview">
                    <div class="stat-card total-income">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Income</h3>
                            <p class="stat-value">৳<?php echo number_format($stats['total_income'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card today-income">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Today's Income</h3>
                            <p class="stat-value">৳<?php echo number_format($stats['today_income'], 2); ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card total-users">
                        <div class="stat-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Total Users</h3>
                            <p class="stat-value"><?php echo $stats['total_users']; ?></p>
                        </div>
                    </div>
                    
                    <div class="stat-card pending-recharges">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3>Pending Recharges</h3>
                            <p class="stat-value"><?php echo $stats['pending_recharges']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Service-wise Income -->
                <div class="chart-container">
                    <h3 class="chart-title"><i class="fas fa-chart-pie"></i> Today's Income Breakdown</h3>
                    <table class="charts-css column show-labels show-primary-axis data-spacing-10" style="height: 200px;">
                        <tbody>
                            <tr>
                                <th scope="row">Washing</th>
                                <td style="--size: <?php echo $stats['today_washing'] / max($stats['today_income'], 1); ?>;">
                                    <span class="data">৳<?php echo number_format($stats['today_washing'], 2); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tea</th>
                                <td style="--size: <?php echo $stats['today_tea'] / max($stats['today_income'], 1); ?>;">
                                    <span class="data">৳<?php echo number_format($stats['today_tea'], 2); ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Coffee</th>
                                <td style="--size: <?php echo $stats['today_coffee'] / max($stats['today_income'], 1); ?>;">
                                    <span class="data">৳<?php echo number_format($stats['today_coffee'], 2); ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Quick Stats -->
                <div class="admin-columns">
                    <div class="left-column">
                        <div class="admin-card">
                            <div class="card-header">
                                <h2><i class="fas fa-washing-machine"></i> Washing Machines</h2>
                            </div>
                            <div class="quick-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Active Locations:</span>
                                    <span class="stat-value"><?php echo $stats['active_locations']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Today's Bookings:</span>
                                    <span class="stat-value"><?php echo $stats['today_bookings']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Active Bookings:</span>
                                    <span class="stat-value"><?php echo $stats['active_bookings']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="right-column">
                        <div class="admin-card">
                            <div class="card-header">
                                <h2><i class="fas fa-coffee"></i> Tea & Coffee</h2>
                            </div>
                            <div class="quick-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Today's Tea Sales:</span>
                                    <span class="stat-value"><?php echo $stats['today_tea_sales']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Today's Coffee Sales:</span>
                                    <span class="stat-value"><?php echo $stats['today_coffee_sales']; ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Card Users:</span>
                                    <span class="stat-value"><?php echo $stats['card_users']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recharges Tab -->
            <div class="tab-content" id="recharges">
                <!-- Manual Top-up -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-hand-holding-usd"></i> Manual Top-up</h2>
                    </div>
                    
                    <?php if (isset($topup_success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo $topup_success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($topup_error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $topup_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="topup-form">
                        <div class="form-group">
                            <label>Mobile Number</label>
                            <input type="text" name="mobile" placeholder="01XXXXXXXXX" 
                                   pattern="01[3-9]\d{8}" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Amount (৳)</label>
                            <input type="number" name="amount" min="1" step="1" 
                                   placeholder="Enter amount" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes (Optional)</label>
                            <input type="text" name="note" placeholder="Reason for top-up">
                        </div>
                        
                        <button type="submit" name="manual_topup" class="btn-topup">
                            <i class="fas fa-plus-circle"></i> Add Balance
                        </button>
                    </form>
                </div>

                <!-- Pending Recharges -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-clock"></i> Pending Recharge Requests</h2>
                        <span class="badge"><?php echo $recharge_requests->num_rows; ?> pending</span>
                    </div>
                    
                    <?php if ($recharge_requests->num_rows > 0): ?>
                        <div class="recharge-requests">
                            <?php while($request = $recharge_requests->fetch_assoc()): ?>
                            <div class="recharge-item">
                                <div class="request-header">
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($request['user_name']); ?></h4>
                                        <div class="user-details">
                                            <span class="detail">
                                                <i class="fas fa-mobile-alt"></i>
                                                <?php echo htmlspecialchars($request['user_mobile']); ?>
                                            </span>
                                            <span class="detail">
                                                <i class="fas fa-wallet"></i>
                                                ৳<?php echo number_format($request['user_balance'], 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="request-time">
                                        <?php echo date('h:i A', strtotime($request['created_at'])); ?>
                                    </div>
                                </div>
                                
                                <div class="payment-info">
                                    <div class="payment-method">
                                        <span class="method-label">Payment Method:</span>
                                        <span class="method-value"><?php echo strtoupper(htmlspecialchars($request['payment_method'])); ?></span>
                                    </div>
                                    <div class="payment-number">
                                        <span class="label">Payment Number:</span>
                                        <span class="value"><?php echo htmlspecialchars($request['payment_number']); ?></span>
                                    </div>
                                    <div class="transaction-id">
                                        <span class="label">Transaction ID:</span>
                                        <span class="value"><?php echo htmlspecialchars($request['transaction_id']); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Action Form -->
                                <div class="request-actions">
                                    <!-- Approve Form -->
                                    <form method="POST" class="action-form approve-form">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <div class="amount-input">
                                            <input type="number" name="amount" 
                                                   placeholder="Amount (৳)" 
                                                   min="10" step="10" required>
                                            <span class="currency">৳</span>
                                        </div>
                                        <button type="submit" name="approve_recharge" class="btn-approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    
                                    <!-- Reject Form -->
                                    <form method="POST" class="action-form reject-form">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="text" name="reject_reason" 
                                               placeholder="Reason for rejection" 
                                               class="reject-reason">
                                        <button type="submit" name="reject_recharge" class="btn-reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-check-circle"></i>
                            <p>No pending recharge requests</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recharge History -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Recent Recharge History</h2>
                    </div>
                    
                    <?php if ($recharge_history->num_rows > 0): ?>
                        <div class="recharge-history-table">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Payment</th>
                                        <th>TRX ID</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($history = $recharge_history->fetch_assoc()): ?>
                                    <tr class="<?php echo $history['status']; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($history['user_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($history['user_mobile']); ?></small>
                                        </td>
                                        <td>
                                            <span class="method-badge"><?php echo strtoupper(htmlspecialchars($history['payment_method'])); ?></span><br>
                                            <small><?php echo htmlspecialchars($history['payment_number']); ?></small>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($history['transaction_id']); ?></code></td>
                                        <td>
                                            <?php if ($history['status'] == 'approved'): ?>
                                                <span class="amount-approved">+৳<?php echo number_format($history['amount'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="amount-rejected">৳0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $history['status']; ?>">
                                                <?php echo ucfirst($history['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $time = !empty($history['processed_at']) ? $history['processed_at'] : $history['created_at'];
                                            echo date('h:i A', strtotime($time));
                                            ?>
                                        </td>
                                        <td>
                                            <small><?php echo $history['admin_note'] ? htmlspecialchars($history['admin_note']) : '—'; ?></small>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-file-alt"></i>
                            <p>No recharge history found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bookings Tab -->
            <div class="tab-content" id="bookings">
                <!-- Today's Bookings -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-calendar-day"></i> Today's Bookings</h2>
                        <span class="badge"><?php echo $today_bookings->num_rows; ?> slots</span>
                    </div>
                    
                    <?php if ($today_bookings->num_rows > 0): ?>
                        <div class="bookings-list">
                            <?php while($booking = $today_bookings->fetch_assoc()): 
                                $booking_time = date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                              date('h:i A', strtotime($booking['end_time']));
                                $is_past = strtotime($booking['end_time']) < time();
                            ?>
                            <div class="booking-item <?php echo $is_past ? 'past' : 'upcoming'; ?>">
                                <div class="booking-time">
                                    <span class="time"><?php echo $booking_time; ?></span>
                                    <?php if (!$is_past): ?>
                                        <span class="otp">OTP: <?php echo htmlspecialchars($booking['otp']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="booking-user">
                                    <h4><?php echo htmlspecialchars($booking['name']); ?></h4>
                                    <p class="mobile"><?php echo htmlspecialchars($booking['mobile']); ?></p>
                                    <p class="location"><?php echo htmlspecialchars($booking['location_name']); ?></p>
                                </div>
                                <div class="booking-status">
                                    <span class="status <?php echo $booking['status']; ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-calendar-times"></i>
                            <p>No bookings for today</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Location-wise Bookings -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-map-marker-alt"></i> Location-wise Bookings</h2>
                    </div>
                    
                    <div class="location-bookings">
                        <?php foreach ($location_income as $location): ?>
                        <div class="location-booking-item">
                            <div class="location-name">
                                <h4><?php echo htmlspecialchars($location['name']); ?></h4>
                                <span class="booking-count"><?php echo $location['total_bookings']; ?> bookings</span>
                            </div>
                            <div class="location-stats">
                                <div class="stat">
                                    <span class="label">Today:</span>
                                    <span class="value">৳<?php echo number_format($location['today_income'], 2); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="label">This Week:</span>
                                    <span class="value">৳<?php echo number_format($location['week_income'], 2); ?></span>
                                </div>
                                <div class="stat">
                                    <span class="label">Total:</span>
                                    <span class="value">৳<?php echo number_format($location['total_income'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Price Control Tab -->
            <div class="tab-content" id="prices">
                <div class="price-display">
                    <?php foreach ($current_prices as $price): ?>
                    <div class="price-card <?php echo $price['service_type']; ?>">
                        <div class="price-icon">
                            <?php if ($price['service_type'] == 'washing'): ?>
                                <i class="fas fa-washing-machine"></i>
                            <?php elseif ($price['service_type'] == 'tea'): ?>
                                <i class="fas fa-mug-hot"></i>
                            <?php else: ?>
                                <i class="fas fa-coffee"></i>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo ucfirst($price['service_type']); ?></h3>
                        <div class="price-amount">৳<?php echo number_format($price['amount'], 2); ?></div>
                        <p class="price-effective">Since <?php echo date('M d, Y', strtotime($price['effective_from'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Price Update Form -->
                <div class="price-control-form">
                    <h3><i class="fas fa-edit"></i> Update Service Price</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Service Type</label>
                            <select name="service_type" required>
                                <option value="">Select Service</option>
                                <option value="washing">Washing Machine</option>
                                <option value="tea">Tea</option>
                                <option value="coffee">Coffee</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>New Price (৳)</label>
                            <input type="number" name="price_amount" min="1" step="0.01" required>
                        </div>
                        
                        <button type="submit" name="update_price" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Price
                        </button>
                    </form>
                </div>

                <!-- Price History -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Price Change History</h2>
                    </div>
                    
                    <?php
                    $price_history_sql = "SELECT * FROM service_prices ORDER BY effective_from DESC LIMIT 10";
                    $price_history = mysqli_query($conn, $price_history_sql);
                    ?>
                    
                    <?php if ($price_history->num_rows > 0): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Price</th>
                                <th>Effective From</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($history = $price_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo ucfirst($history['service_type']); ?></td>
                                <td>৳<?php echo number_format($history['amount'], 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($history['effective_from'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $history['status']; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-history"></i>
                            <p>No price history found</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Locations Tab -->
            <div class="tab-content" id="locations">
                <div class="location-manager">
                    <!-- Add Location Form -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h2><i class="fas fa-plus-circle"></i> Add New Location</h2>
                        </div>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label>Location Name</label>
                                <input type="text" name="location_name" placeholder="e.g., Muktiqiddha Hall" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="location_description" placeholder="Location details..." rows="3"></textarea>
                            </div>
                            
                            <button type="submit" name="add_location" class="btn btn-primary">
                                <i class="fas fa-save"></i> Add Location
                            </button>
                        </form>
                    </div>

                    <!-- Location List -->
                    <div class="admin-card">
                        <div class="card-header">
                            <h2><i class="fas fa-map-marked-alt"></i> All Locations</h2>
                            <span class="badge"><?php echo count($locations); ?> locations</span>
                        </div>
                        
                        <?php if (count($locations) > 0): ?>
                            <div class="locations-list">
                                <?php foreach ($locations as $location): ?>
                                <div class="location-item">
                                    <div class="location-header">
                                        <h4><?php echo htmlspecialchars($location['name']); ?></h4>
                                        <span class="location-status <?php echo $location['status']; ?>">
                                            <?php echo ucfirst($location['status']); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($location['description'])): ?>
                                    <p class="location-desc"><?php echo htmlspecialchars($location['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="location-footer">
                                        <span class="location-date">Added: <?php echo date('M d, Y', strtotime($location['created_at'])); ?></span>
                                        <div class="location-actions">
                                            <button class="btn-action" onclick="editLocation(<?php echo $location['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action" onclick="deleteLocation(<?php echo $location['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-map-marker-alt"></i>
                                <p>No locations added yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Card Users Tab -->
            <div class="tab-content" id="cards">
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-credit-card"></i> Registered Card Users</h2>
                        <span class="badge"><?php echo $card_users->num_rows; ?> users</span>
                    </div>
                    
                    <?php if ($card_users->num_rows > 0): ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Card Number</th>
                                    <th>Type</th>
                                    <th>Registered</th>
                                    <th>Last Used</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($card_user = $card_users->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($card_user['name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($card_user['mobile']); ?></small>
                                    </td>
                                    <td>
                                        <code>****<?php echo substr($card_user['card_number'], -4); ?></code><br>
                                        <small><?php echo strtoupper($card_user['card_type']); ?></small>
                                    </td>
                                    <td><?php echo strtoupper($card_user['card_type']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($card_user['registered_at'])); ?></td>
                                    <td>
                                        <?php if ($card_user['last_used_at']): ?>
                                            <?php echo date('M d, h:i A', strtotime($card_user['last_used_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $card_user['card_status']; ?>">
                                            <?php echo ucfirst($card_user['card_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $card_user['card_usage_count']; ?> times</td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <p>No card users registered yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Reports Tab -->
            <div class="tab-content" id="reports">
                <!-- Income Reports -->
                <div class="report-periods">
                    <div class="report-period">
                        <h3>Last 7 Days</h3>
                        <div class="report-income">৳<?php echo number_format($income_report_7['total_income'], 2); ?></div>
                        <div class="report-breakdown">
                            <small>Washing: ৳<?php echo number_format($income_report_7['washing_income'], 2); ?></small><br>
                            <small>Tea: ৳<?php echo number_format($income_report_7['tea_income'], 2); ?></small><br>
                            <small>Coffee: ৳<?php echo number_format($income_report_7['coffee_income'], 2); ?></small>
                        </div>
                    </div>
                    
                    <div class="report-period">
                        <h3>Last 15 Days</h3>
                        <div class="report-income">৳<?php echo number_format($income_report_15['total_income'], 2); ?></div>
                        <div class="report-breakdown">
                            <small>Washing: ৳<?php echo number_format($income_report_15['washing_income'], 2); ?></small><br>
                            <small>Tea: ৳<?php echo number_format($income_report_15['tea_income'], 2); ?></small><br>
                            <small>Coffee: ৳<?php echo number_format($income_report_15['coffee_income'], 2); ?></small>
                        </div>
                    </div>
                    
                    <div class="report-period">
                        <h3>Last 30 Days</h3>
                        <div class="report-income">৳<?php echo number_format($income_report_30['total_income'], 2); ?></div>
                        <div class="report-breakdown">
                            <small>Washing: ৳<?php echo number_format($income_report_30['washing_income'], 2); ?></small><br>
                            <small>Tea: ৳<?php echo number_format($income_report_30['tea_income'], 2); ?></small><br>
                            <small>Coffee: ৳<?php echo number_format($income_report_30['coffee_income'], 2); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Detailed Report Link -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-file-alt"></i> Detailed Reports</h2>
                    </div>
                    <div class="report-actions">
                        <a href="reports.php?period=7" class="btn btn-primary">
                            <i class="fas fa-chart-bar"></i> View 7-Day Report
                        </a>
                        <a href="reports.php?period=15" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i> View 15-Day Report
                        </a>
                        <a href="reports.php?period=30" class="btn btn-primary">
                            <i class="fas fa-chart-area"></i> View 30-Day Report
                        </a>
                        <a href="reports.php?export=pdf" class="btn btn-secondary">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                    </div>
                </div>
            </div>

            <!-- Management Tab -->
            <div class="tab-content" id="management">
                <!-- User Search -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-search"></i> Search User</h2>
                    </div>
                    
                    <form method="GET" class="search-form">
                        <div class="search-group">
                            <input type="text" name="search" placeholder="Search by name or mobile number..." 
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                    
                    <?php if (isset($_GET['search'])): ?>
                        <?php
                        $search_term = sanitize($_GET['search']);
                        $search_sql = "SELECT * FROM users 
                                      WHERE mobile LIKE CONCAT('%', ?, '%') 
                                      OR name LIKE CONCAT('%', ?, '%') 
                                      ORDER BY id DESC";
                        $search_stmt = mysqli_prepare($conn, $search_sql);
                        mysqli_stmt_bind_param($search_stmt, "ss", $search_term, $search_term);
                        mysqli_stmt_execute($search_stmt);
                        $search_results = mysqli_stmt_get_result($search_stmt);
                        ?>
                        
                        <div class="search-results">
                            <h3>Search Results (<?php echo $search_results->num_rows; ?> found)</h3>
                            
                            <?php if ($search_results->num_rows > 0): ?>
                                <table class="users-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Mobile</th>
                                            <th>Balance</th>
                                            <th>Joined</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($user = $search_results->fetch_assoc()): ?>
                                        <tr>
                                            <td>#<?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                            <td class="balance">৳<?php echo number_format($user['balance'], 2); ?></td>
                                            <td><?php echo date('d/m/y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                if (isset($user['last_login']) && !empty($user['last_login'])) {
                                                    echo date('h:i A', strtotime($user['last_login']));
                                                } else {
                                                    echo 'Never';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button class="btn-action" onclick="topupUser('<?php echo $user['mobile']; ?>')">
                                                    <i class="fas fa-plus"></i> Top-up
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-slash"></i>
                                    <p>No users found matching "<?php echo htmlspecialchars($_GET['search']); ?>"</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php mysqli_stmt_close($search_stmt); ?>
                    <?php endif; ?>
                </div>

                <!-- System Reset Options -->
                <div class="reset-options">
                    <h3><i class="fas fa-exclamation-triangle"></i> System Management</h3>
                    <p class="warning-note">
                        <i class="fas fa-info-circle"></i>
                        Warning: These actions cannot be undone. Use with caution.
                    </p>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure? This action cannot be undone.')">
                        <div class="form-group">
                            <label>Reset Option</label>
                            <select name="report_type" required>
                                <option value="">Select option</option>
                                <option value="income">Reset Income Reports</option>
                                <option value="users">Clean Up Inactive Users</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="reset_report" class="btn btn-danger">
                            <i class="fas fa-redo"></i> Execute Reset
                        </button>
                    </form>
                </div>

                <!-- System Information -->
                <div class="admin-card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> System Information</h2>
                    </div>
                    <div class="system-info">
                        <div class="info-item">
                            <span class="label">Database Size:</span>
                            <span class="value">
                                <?php
                                $size_sql = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                            FROM information_schema.tables 
                                            WHERE table_schema = 'washing_machine'";
                                $size_result = mysqli_query($conn, $size_sql);
                                $size = mysqli_fetch_assoc($size_result)['size'];
                                echo $size . ' MB';
                                ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="label">Total Bookings:</span>
                            <span class="value"><?php echo $stats['total_bookings']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Total Transactions:</span>
                            <span class="value"><?php echo $stats['total_transactions']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">System Uptime:</span>
                            <span class="value">
                                <?php
                                $uptime_sql = "SELECT TIMEDIFF(NOW(), MIN(created_at)) as uptime FROM system_logs";
                                $uptime_result = mysqli_query($conn, $uptime_sql);
                                $uptime = mysqli_fetch_assoc($uptime_result)['uptime'];
                                echo $uptime;
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assests/js/main.js?ver=1.1"></script>
    <script>
    // Tab functionality
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });

    function topupUser(mobile) {
        document.querySelector('input[name="mobile"]').value = mobile;
        document.querySelector('.tab[data-tab="recharges"]').click();
        document.querySelector('input[name="mobile"]').focus();
    }

    function editLocation(id) {
        alert('Edit location ' + id + ' - This feature is under development');
    }

    function deleteLocation(id) {
        if (confirm('Are you sure you want to delete this location?')) {
            // AJAX call to delete location
            fetch('includes/ajax.php?action=delete_location&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }

    // Auto-hide success/error messages after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.display = 'none';
        });
    }, 5000);
    </script>
</body>
</html>
<?php
mysqli_close($conn);
?>