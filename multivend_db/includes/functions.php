<?php
/**
 * Common Functions for WashMate System
 */

/**
 * Get admin statistics
 */
function getAdminStatistics() {
    global $conn;
    
    $stats = [];
    
    // Total users
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
    $stats['total_users'] = $result->fetch_assoc()['count'];
    
    // Total income
    $result = mysqli_query($conn, "SELECT SUM(total_income) as total FROM daily_income");
    $stats['total_income'] = $result->fetch_assoc()['total'] ?: 0;
    
    // Today's income
    $result = mysqli_query($conn, "SELECT * FROM daily_income WHERE date = CURDATE()");
    if ($result->num_rows > 0) {
        $today = $result->fetch_assoc();
        $stats['today_income'] = $today['total_income'] ?: 0;
        $stats['today_washing'] = $today['washing_income'] ?: 0;
        $stats['today_tea'] = $today['tea_income'] ?: 0;
        $stats['today_coffee'] = $today['coffee_income'] ?: 0;
    } else {
        $stats['today_income'] = 0;
        $stats['today_washing'] = 0;
        $stats['today_tea'] = 0;
        $stats['today_coffee'] = 0;
    }
    
    // Pending recharges
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM recharge_requests WHERE status = 'pending'");
    $stats['pending_recharges'] = $result->fetch_assoc()['count'];
    
    // Active locations
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM locations WHERE status = 'active'");
    $stats['active_locations'] = $result->fetch_assoc()['count'];
    
    // Today's bookings
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE booking_date = CURDATE()");
    $stats['today_bookings'] = $result->fetch_assoc()['count'];
    
    // Active bookings
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings WHERE status = 'active'");
    $stats['active_bookings'] = $result->fetch_assoc()['count'];
    
    // Today's tea/coffee sales
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tea_coffee_transactions WHERE DATE(created_at) = CURDATE() AND service_type = 'tea'");
    $stats['today_tea_sales'] = $result->fetch_assoc()['count'];
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM tea_coffee_transactions WHERE DATE(created_at) = CURDATE() AND service_type = 'coffee'");
    $stats['today_coffee_sales'] = $result->fetch_assoc()['count'];
    
    // Card users
    $result = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as count FROM user_cards WHERE status = 'active'");
    $stats['card_users'] = $result->fetch_assoc()['count'];
    
    // Total bookings
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM bookings");
    $stats['total_bookings'] = $result->fetch_assoc()['count'];
    
    // Total transactions
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM transactions");
    $stats['total_transactions'] = $result->fetch_assoc()['count'];
    
    return $stats;
}

/**
 * Get pending recharge requests
 */
function getPendingRecharges() {
    global $conn;
    
    $sql = "SELECT r.*, u.balance as user_balance 
           FROM recharge_requests r
           LEFT JOIN users u ON r.user_id = u.id
           WHERE r.status = 'pending'
           ORDER BY r.created_at DESC";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get recharge history
 */
function getRechargeHistory($limit = 20) {
    global $conn;
    
    $sql = "SELECT r.*, u.balance as current_balance 
           FROM recharge_requests r
           LEFT JOIN users u ON r.user_id = u.id
           WHERE r.status IN ('approved', 'rejected')
           AND r.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
           ORDER BY r.processed_at DESC 
           LIMIT $limit";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get today's bookings
 */
function getTodayBookings() {
    global $conn;
    
    $sql = "SELECT b.*, u.name, u.mobile, l.name as location_name 
           FROM bookings b 
           JOIN users u ON b.user_id = u.id 
           LEFT JOIN locations l ON b.location_id = l.id
           WHERE b.booking_date = CURDATE() 
           ORDER BY b.start_time";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get all locations
 */
function getAllLocations() {
    global $conn;
    
    $sql = "SELECT * FROM locations ORDER BY name";
    $result = mysqli_query($conn, $sql);
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    
    return $locations;
}

/**
 * Get current prices
 */
function getCurrentPrices() {
    global $conn;
    
    $sql = "SELECT * FROM service_prices 
           WHERE status = 'active' 
           ORDER BY service_type";
    $result = mysqli_query($conn, $sql);
    
    $prices = [];
    while ($row = $result->fetch_assoc()) {
        $prices[] = $row;
    }
    
    return $prices;
}

/**
 * Get card users
 */
function getCardUsers() {
    global $conn;
    
    $sql = "SELECT u.name, u.mobile, uc.*,
           (SELECT COUNT(*) FROM tea_coffee_transactions WHERE card_id = uc.id) as card_usage_count
           FROM user_cards uc
           JOIN users u ON uc.user_id = u.id
           WHERE uc.status = 'active'
           ORDER BY uc.registered_at DESC";
    
    return mysqli_query($conn, $sql);
}

/**
 * Get income report for specific period
 */
function getIncomeReport($days) {
    global $conn;
    
    $sql = "SELECT 
               SUM(washing_income) as washing_income,
               SUM(tea_income) as tea_income,
               SUM(coffee_income) as coffee_income,
               SUM(recharge_income) as recharge_income,
               SUM(total_income) as total_income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    
    $result = mysqli_query($conn, $sql);
    return $result->fetch_assoc();
}

/**
 * Get location-wise income
 */
function getLocationWiseIncome() {
    global $conn;
    
    $sql = "SELECT 
               l.name,
               COUNT(b.id) as total_bookings,
               SUM(b.amount) as total_income,
               SUM(CASE WHEN b.booking_date = CURDATE() THEN b.amount ELSE 0 END) as today_income,
               SUM(CASE WHEN b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN b.amount ELSE 0 END) as week_income
           FROM locations l
           LEFT JOIN bookings b ON l.id = b.location_id
           WHERE l.status = 'active'
           GROUP BY l.id, l.name
           ORDER BY total_income DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $locations = [];
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    
    return $locations;
}

/**
 * Approve recharge request
 */
function approveRecharge($request_id, $amount) {
    global $conn;
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Get request details
        $sql = "SELECT * FROM recharge_requests WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $request_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $request = $result->fetch_assoc();
        mysqli_stmt_close($stmt);
        
        if ($request) {
            // Update request status
            $sql = "UPDATE recharge_requests 
                   SET status = 'approved', 
                       amount = ?,
                       processed_at = NOW(),
                       approved_at = NOW(),
                       admin_note = 'Approved by admin'
                   WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $amount, $request_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Add balance to user
            $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $amount, $request['user_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Record transaction
            $note = "Recharge via " . strtoupper($request['payment_method']) . 
                   " - TRX: {$request['transaction_id']} - Approved by admin";
            $sql = "INSERT INTO transactions (user_id, amount, type, note, service_type) 
                   VALUES (?, ?, 'credit', ?, 'recharge')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ids", $request['user_id'], $amount, $note);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Update daily income
            $sql = "INSERT INTO daily_income (date, recharge_income, total_income) 
                   VALUES (CURDATE(), ?, ?) 
                   ON DUPLICATE KEY UPDATE 
                       recharge_income = recharge_income + VALUES(recharge_income),
                       total_income = total_income + VALUES(total_income)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "dd", $amount, $amount);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            return true;
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}

/**
 * Reject recharge request
 */
function rejectRecharge($request_id, $reason) {
    global $conn;
    
    $sql = "UPDATE recharge_requests 
           SET status = 'rejected', 
               admin_note = ?,
               processed_at = NOW()
           WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $reason, $request_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Manual top-up user
 */
function manualTopup($mobile, $amount, $note) {
    global $conn;
    
    // Find user by mobile
    $sql = "SELECT id FROM users WHERE mobile = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        mysqli_stmt_close($stmt);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Update balance
            $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $amount, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Record transaction
            $trans_note = "Manual top-up by admin - $note";
            $sql = "INSERT INTO transactions (user_id, amount, type, note, service_type) 
                   VALUES (?, ?, 'credit', ?, 'recharge')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ids", $user_id, $amount, $trans_note);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Update daily income
            $sql = "INSERT INTO daily_income (date, recharge_income, total_income) 
                   VALUES (CURDATE(), ?, ?) 
                   ON DUPLICATE KEY UPDATE 
                       recharge_income = recharge_income + VALUES(recharge_income),
                       total_income = total_income + VALUES(total_income)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "dd", $amount, $amount);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            return ['success' => true, 'message' => "Successfully added ৳$amount to $mobile"];
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => "Failed to add balance: " . $e->getMessage()];
        }
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => "User not found with mobile: $mobile"];
    }
}

/**
 * Get detailed report data
 */
function getDetailedReport($days) {
    global $conn;
    
    $data = [];
    
    // Get income totals
    $sql = "SELECT 
               SUM(washing_income) as washing_income,
               SUM(tea_income) as tea_income,
               SUM(coffee_income) as coffee_income,
               SUM(total_income) as total_income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    
    $result = mysqli_query($conn, $sql);
    $income = $result->fetch_assoc();
    
    $data['washing_income'] = $income['washing_income'] ?: 0;
    $data['tea_income'] = $income['tea_income'] ?: 0;
    $data['coffee_income'] = $income['coffee_income'] ?: 0;
    $data['total_income'] = $income['total_income'] ?: 0;
    
    // Get booking counts
    $sql = "SELECT COUNT(*) as count FROM bookings 
           WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    $result = mysqli_query($conn, $sql);
    $data['washing_bookings'] = $result->fetch_assoc()['count'];
    
    // Get tea/coffee sales
    $sql = "SELECT 
               SUM(CASE WHEN service_type = 'tea' THEN 1 ELSE 0 END) as tea_sales,
               SUM(CASE WHEN service_type = 'coffee' THEN 1 ELSE 0 END) as coffee_sales
           FROM tea_coffee_transactions 
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $result = mysqli_query($conn, $sql);
    $sales = $result->fetch_assoc();
    $data['tea_sales'] = $sales['tea_sales'] ?: 0;
    $data['coffee_sales'] = $sales['coffee_sales'] ?: 0;
    
    // Get transaction count
    $sql = "SELECT COUNT(*) as count FROM transactions 
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $result = mysqli_query($conn, $sql);
    $data['total_transactions'] = $result->fetch_assoc()['count'];
    
    // Get active users
    $sql = "SELECT COUNT(DISTINCT user_id) as count FROM transactions 
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $result = mysqli_query($conn, $sql);
    $data['active_users'] = $result->fetch_assoc()['count'];
    
    // Get new users
    $sql = "SELECT COUNT(*) as count FROM users 
           WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
    $result = mysqli_query($conn, $sql);
    $data['new_users'] = $result->fetch_assoc()['count'];
    
    // Calculate average daily income
    $data['avg_daily_income'] = $data['total_income'] / $days;
    
    return $data;
}

/**
 * Get daily report data
 */
function getDailyReportData($days) {
    global $conn;
    
    $sql = "SELECT 
               date,
               washing_income,
               tea_income,
               coffee_income,
               total_income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           ORDER BY date DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

/**
 * Get location report data
 */
function getLocationReportData($days) {
    global $conn;
    
    $sql = "SELECT 
               l.name,
               COUNT(b.id) as bookings,
               SUM(b.amount) as income
           FROM locations l
           LEFT JOIN bookings b ON l.id = b.location_id
           WHERE l.status = 'active'
           AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           GROUP BY l.id, l.name
           ORDER BY income DESC";
    
    $result = mysqli_query($conn, $sql);
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

/**
 * Get service report data
 */
function getServiceReportData($days) {
    global $conn;
    
    $data = [];
    
    // Get best performing service
    $sql = "SELECT 
               'washing' as service,
               SUM(washing_income) as income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           UNION ALL
           SELECT 
               'tea' as service,
               SUM(tea_income) as income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           UNION ALL
           SELECT 
               'coffee' as service,
               SUM(coffee_income) as income
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           ORDER BY income DESC
           LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    $best = $result->fetch_assoc();
    $data['best_service'] = ucfirst($best['service']);
    $data['best_service_income'] = $best['income'];
    
    // Get busiest day
    $sql = "SELECT 
               date,
               total_income,
               DAYNAME(date) as day_name
           FROM daily_income 
           WHERE date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
           ORDER BY total_income DESC
           LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    $busiest = $result->fetch_assoc();
    $data['busiest_day'] = $busiest['day_name'];
    $data['busiest_day_income'] = $busiest['total_income'];
    
    return $data;
}

/**
 * Log API request
 */
function logApiRequest() {
    global $conn;
    
    $action_type = 'API_REQUEST';
    $description = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $sql = "INSERT INTO system_logs (action_type, description, ip_address, user_agent) 
           VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssss", $action_type, $description, $ip_address, $user_agent);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Log transaction
 */
function logTransaction($user_id, $type, $amount, $note) {
    global $conn;
    
    $sql = "INSERT INTO system_logs (user_id, action_type, description) 
           VALUES (?, 'TRANSACTION', ?)";
    $stmt = mysqli_prepare($conn, $sql);
    $description = "$type: ৳$amount - $note";
    mysqli_stmt_bind_param($stmt, "is", $user_id, $description);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Generate random OTP
 */
function generateOTP($length = 4) {
    return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y h:i A') {
    return date($format, strtotime($date));
}

/**
 * Check if string is valid mobile number
 */
function isValidMobile($mobile) {
    return preg_match('/^01[3-9]\d{8}$/', $mobile);
}

/**
 * Check if string is valid PIN
 */
function isValidPIN($pin) {
    return preg_match('/^\d{4}$/', $pin);
}

/**
 * Get user by mobile
 */
function getUserByMobile($mobile) {
    global $conn;
    
    $sql = "SELECT * FROM users WHERE mobile = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $mobile);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = $result->fetch_assoc();
    mysqli_stmt_close($stmt);
    
    return $user;
}
?>