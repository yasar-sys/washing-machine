<?php
require 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Get user data
$sql = "SELECT * FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

// Process recharge request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_recharge'])) {
    $payment_method = sanitize($_POST['payment_method']);
    $payment_number = sanitize($_POST['payment_number']);
    $transaction_id = sanitize($_POST['transaction_id']);
    
    // Validation
    $errors = [];
    
    if (!in_array($payment_method, ['bkash', 'nagad', 'rocket'])) {
        $errors[] = "Please select a valid payment method";
    }
    
    if (empty($payment_number) || strlen($payment_number) != 11) {
        $errors[] = "Please enter a valid 11-digit mobile number";
    }
    
    if (empty($transaction_id)) {
        $errors[] = "Please enter transaction ID";
    }
    
    // Check if transaction ID already exists
    $check_sql = "SELECT id FROM recharge_requests WHERE transaction_id = '$transaction_id'";
    $check_result = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_result) > 0) {
        $errors[] = "This transaction ID has already been submitted";
    }
    
    if (empty($errors)) {
        // Insert recharge request
        $sql = "INSERT INTO recharge_requests 
                (user_id, user_name, user_mobile, payment_method, payment_number, transaction_id, amount, status) 
                VALUES (
                    '$user_id',
                    '{$user['name']}',
                    '{$user['mobile']}',
                    '$payment_method',
                    '$payment_number',
                    '$transaction_id',
                    0.00,  // Amount will be set by admin
                    'pending'
                )";
        
        if (mysqli_query($conn, $sql)) {
            $message = "Recharge request submitted successfully! Admin will verify and add balance.";
            $message_type = 'success';
            
            // Clear form
            $_POST = array();
        } else {
            $message = "Failed to submit request. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = 'error';
    }
}

// Get user's pending requests
$pending_sql = "SELECT * FROM recharge_requests 
               WHERE user_id = '$user_id' 
               AND status = 'pending'
               ORDER BY created_at DESC";
$pending_result = mysqli_query($conn, $pending_sql);

// Get user's completed requests (last 7 days)
$completed_sql = "SELECT * FROM recharge_requests 
                 WHERE user_id = '$user_id' 
                 AND status IN ('approved', 'rejected')
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY created_at DESC 
                 LIMIT 10";
$completed_result = mysqli_query($conn, $completed_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recharge Balance - WashMate</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <div class="logo">
                    <i class="fas fa-washing-machine"></i>
                    <h1>WashMate</h1>
                </div>
                <div class="user-info">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <div class="header-right">
                <div class="balance-info">
                    <i class="fas fa-wallet"></i>
                    <span>৳<?php echo number_format($user['balance'], 2); ?></span>
                </div>
            </div>
        </header>

        <main class="recharge-main">
            <div class="recharge-container">
                <!-- Left Column - Recharge Form -->
                <div class="form-column">
                    <div class="recharge-form-card">
                        <div class="form-header">
                            <h2><i class="fas fa-credit-card"></i> Submit Recharge Request</h2>
                            <p>Fill in your payment details to add balance</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $message_type; ?>">
                                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="recharge-form">
                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i> Your Information
                                </label>
                                <div class="user-details">
                                    <div class="detail-item">
                                        <span class="label">Name:</span>
                                        <span class="value"><?php echo $user['name']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Mobile:</span>
                                        <span class="value"><?php echo $user['mobile']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="label">Current Balance:</span>
                                        <span class="value">৳<?php echo number_format($user['balance'], 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-money-bill-wave"></i> Payment Method
                                </label>
                                <select name="payment_method" class="payment-select" required>
                                    <option value="">-- Select Payment Method --</option>
                                    <option value="bkash" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'bkash' ? 'selected' : ''; ?>>bKash</option>
                                    <option value="nagad" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'nagad' ? 'selected' : ''; ?>>Nagad</option>
                                    <option value="rocket" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] == 'rocket' ? 'selected' : ''; ?>>Rocket</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-mobile-alt"></i> Payment Mobile Number
                                </label>
                                <input type="text" name="payment_number" 
                                       placeholder="Enter 11-digit mobile number" 
                                       pattern="01[3-9]\d{8}"
                                       value="<?php echo isset($_POST['payment_number']) ? $_POST['payment_number'] : ''; ?>"
                                       required>
                                <small class="help-text">
                                    <i class="fas fa-info-circle"></i>
                                    The mobile number you used for payment
                                </small>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-receipt"></i> Transaction ID
                                </label>
                                <input type="text" name="transaction_id" 
                                       placeholder="Enter transaction ID from payment" 
                                       value="<?php echo isset($_POST['transaction_id']) ? $_POST['transaction_id'] : ''; ?>"
                                       required>
                                <small class="help-text">
                                    <i class="fas fa-info-circle"></i>
                                    You'll find this in your payment confirmation SMS
                                </small>
                            </div>

                            <div class="instruction-box">
                                <h4><i class="fas fa-info-circle"></i> Instructions:</h4>
                                <ol>
                                    <li>Send money to your preferred payment method</li>
                                    <li>Select the payment method you used</li>
                                    <li>Enter the mobile number you used for payment</li>
                                    <li>Enter the Transaction ID from confirmation SMS</li>
                                    <li>Admin will verify and add balance to your account</li>
                                </ol>
                            </div>

                            <button type="submit" name="submit_recharge" class="btn-recharge-submit">
                                <i class="fas fa-paper-plane"></i> Submit Recharge Request
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Request History -->
                <div class="history-column">
                    <!-- Pending Requests -->
                    <div class="history-card">
                        <h3><i class="fas fa-clock"></i> Pending Requests</h3>
                        
                        <?php if (mysqli_num_rows($pending_result) > 0): ?>
                            <div class="requests-list">
                                <?php while($request = mysqli_fetch_assoc($pending_result)): ?>
                                <div class="request-item pending">
                                    <div class="request-icon">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="request-details">
                                        <div class="request-method">
                                            <span class="method"><?php echo strtoupper($request['payment_method']); ?></span>
                                            <span class="number"><?php echo $request['payment_number']; ?></span>
                                        </div>
                                        <div class="request-info">
                                            <p class="trxid">TRX: <?php echo $request['transaction_id']; ?></p>
                                            <p class="time"><?php echo date('M d, h:i A', strtotime($request['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="request-status">
                                        <span class="status pending">Pending</span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-requests">
                                <i class="far fa-clock"></i>
                                <p>No pending recharge requests</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent History -->
                    <div class="history-card">
                        <h3><i class="fas fa-history"></i> Recent History (7 days)</h3>
                        
                        <?php if (mysqli_num_rows($completed_result) > 0): ?>
                            <div class="requests-list">
                                <?php while($request = mysqli_fetch_assoc($completed_result)): 
                                    $status_class = $request['status'];
                                    $status_icon = $request['status'] == 'approved' ? 'check-circle' : 'times-circle';
                                ?>
                                <div class="request-item <?php echo $status_class; ?>">
                                    <div class="request-icon">
                                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                    </div>
                                    <div class="request-details">
                                        <div class="request-method">
                                            <span class="method"><?php echo strtoupper($request['payment_method']); ?></span>
                                            <span class="number"><?php echo $request['payment_number']; ?></span>
                                        </div>
                                        <div class="request-info">
                                            <p class="trxid">TRX: <?php echo $request['transaction_id']; ?></p>
                                            <?php if ($request['status'] == 'approved'): ?>
                                                <p class="amount">+৳<?php echo number_format($request['amount'], 2); ?></p>
                                            <?php endif; ?>
                                            <p class="time"><?php echo date('M d, h:i A', strtotime($request['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="request-status">
                                        <span class="status <?php echo $status_class; ?>">
                                            <?php echo ucfirst($status_class); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-requests">
                                <i class="far fa-file-alt"></i>
                                <p>No recharge history found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>