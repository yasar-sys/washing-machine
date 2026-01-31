<?php
require 'config.php';
require 'includes/functions.php';

// Set content type
header('Content-Type: application/json');

// Allow ESP32 access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log API request
logApiRequest();

// Washing Machine OTP verification
if (isset($_GET['otp'])) {
    $otp = sanitize($_GET['otp']);
    
    // Find active booking with this OTP
    $sql = "SELECT b.*, u.mobile, l.name as location_name 
           FROM bookings b 
           JOIN users u ON b.user_id = u.id 
           LEFT JOIN locations l ON b.location_id = l.id
           WHERE b.otp = ? 
           AND b.status = 'active' 
           AND b.booking_date = CURDATE() 
           AND b.start_time <= CURTIME() 
           AND b.end_time >= CURTIME()";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $otp);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $booking = mysqli_fetch_assoc($result);
        
        // Check if OTP is still valid (first hour only)
        $booking_start = strtotime($booking['start_time']);
        $current_time = time();
        $time_elapsed = ($current_time - $booking_start) / 3600; // in hours
        
        if ($time_elapsed <= 1) {
            // Mark OTP as used
            $update_sql = "UPDATE bookings SET status = 'used' WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "i", $booking['id']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            // Log access
            $log_note = "Washing machine OTP used at {$booking['location_name']}";
            logTransaction($booking['user_id'], 'debit', 0, $log_note);
            
            echo json_encode([
                'success' => true,
                'message' => 'OTP valid. Washing machine activated.',
                'booking_time' => date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                 date('h:i A', strtotime($booking['end_time'])),
                'location' => $booking['location_name'],
                'relay_pin' => 16,
                'duration' => 5400 // 1.5 hours in seconds
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'OTP expired. Works only in first hour of booking.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired OTP'
        ]);
    }
    mysqli_stmt_close($stmt);
}

// Tea/Coffee OTP verification
elseif (isset($_GET['tea_coffee_otp'])) {
    $otp = sanitize($_GET['tea_coffee_otp']);
    
    // Find tea/coffee transaction with this OTP
    $sql = "SELECT t.*, u.mobile, u.balance 
           FROM tea_coffee_transactions t
           JOIN users u ON t.user_id = u.id 
           WHERE t.otp = ? 
           AND t.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $otp);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $transaction = mysqli_fetch_assoc($result);
        
        // Check if OTP already used
        if (empty($transaction['machine_response'])) {
            // Update transaction with machine response
            $update_sql = "UPDATE tea_coffee_transactions 
                          SET machine_response = 'DISPENSED_' . UNIX_TIMESTAMP() 
                          WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "i", $transaction['id']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
            
            // Determine GPIO pin based on service type
            $relay_pin = $transaction['service_type'] == 'tea' ? 3 : 10;
            
            echo json_encode([
                'success' => true,
                'message' => ucfirst($transaction['service_type']) . ' dispensed successfully.',
                'service_type' => $transaction['service_type'],
                'amount' => $transaction['amount'],
                'relay_pin' => $relay_pin,
                'relay_duration' => 5 // seconds for dispensing
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'OTP already used'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired OTP'
        ]);
    }
    mysqli_stmt_close($stmt);
}

// Card verification for tea/coffee
elseif (isset($_GET['card_number']) && isset($_GET['service'])) {
    $card_number = sanitize($_GET['card_number']);
    $service = sanitize($_GET['service']);
    
    // Validate service type
    if (!in_array($service, ['tea', 'coffee'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid service type'
        ]);
        exit();
    }
    
    // Get card details
    $sql = "SELECT uc.*, u.id as user_id, u.name, u.mobile, u.balance 
           FROM user_cards uc
           JOIN users u ON uc.user_id = u.id 
           WHERE uc.card_number = ? 
           AND uc.status = 'active'";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $card_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $card = mysqli_fetch_assoc($result);
        
        // Get service price
        $price = $service == 'tea' ? TEA_PRICE : COFFEE_PRICE;
        
        // Check user balance
        if ($card['balance'] >= $price) {
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Create tea/coffee transaction
                $trans_sql = "INSERT INTO tea_coffee_transactions 
                            (user_id, card_id, service_type, amount) 
                            VALUES (?, ?, ?, ?)";
                $trans_stmt = mysqli_prepare($conn, $trans_sql);
                mysqli_stmt_bind_param($trans_stmt, "iisd", 
                    $card['user_id'], $card['id'], $service, $price);
                mysqli_stmt_execute($trans_stmt);
                $transaction_id = mysqli_insert_id($conn);
                mysqli_stmt_close($trans_stmt);
                
                // Update card last used time
                $update_sql = "UPDATE user_cards 
                              SET last_used_at = NOW() 
                              WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $card['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Determine GPIO pin
                $relay_pin = $service == 'tea' ? 3 : 10;
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($service) . ' dispensed via card.',
                    'user_name' => $card['name'],
                    'service_type' => $service,
                    'amount' => $price,
                    'remaining_balance' => $card['balance'] - $price,
                    'relay_pin' => $relay_pin,
                    'relay_duration' => 5,
                    'transaction_id' => $transaction_id
                ]);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode([
                    'success' => false,
                    'message' => 'Transaction failed. Please try again.'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient balance. Please recharge.'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Card not registered or inactive'
        ]);
    }
    mysqli_stmt_close($stmt);
}

// Check active booking slot
elseif (isset($_GET['check_slot'])) {
    // Check if any booking is active now
    $sql = "SELECT b.otp, l.name as location_name 
           FROM bookings b
           LEFT JOIN locations l ON b.location_id = l.id
           WHERE b.status = 'active' 
           AND b.booking_date = CURDATE() 
           AND b.start_time <= CURTIME() 
           AND b.end_time >= CURTIME()
           LIMIT 1";
    
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $booking = mysqli_fetch_assoc($result);
        echo json_encode([
            'has_booking' => true,
            'otp' => $booking['otp'],
            'location' => $booking['location_name'],
            'current_time' => date('H:i:s')
        ]);
    } else {
        echo json_encode([
            'has_booking' => false,
            'message' => 'No active booking',
            'current_time' => date('H:i:s')
        ]);
    }
}

// Machine status update
elseif (isset($_GET['machine_status'])) {
    $machine_id = sanitize($_GET['machine_status']);
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : 'online';
    
    // Log machine status (in a real system, you'd have a machines table)
    $log_sql = "INSERT INTO system_logs (action_type, description) 
               VALUES ('MACHINE_STATUS', ?)";
    $log_stmt = mysqli_prepare($conn, $log_sql);
    $log_desc = "Machine $machine_id status: $status";
    mysqli_stmt_bind_param($log_stmt, "s", $log_desc);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated',
        'machine_id' => $machine_id,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// System heartbeat/ping
elseif (isset($_GET['ping'])) {
    echo json_encode([
        'success' => true,
        'message' => 'WashMate API is running',
        'version' => '2.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'services' => [
            'washing' => WASHING_PRICE,
            'tea' => TEA_PRICE,
            'coffee' => COFFEE_PRICE
        ]
    ]);
}

// Invalid request
else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid API request',
        'available_endpoints' => [
            '/api.php?otp=XXXX',
            '/api.php?tea_coffee_otp=XXXX',
            '/api.php?card_number=XXXX&service=tea|coffee',
            '/api.php?check_slot=1',
            '/api.php?machine_status=ID',
            '/api.php?ping=1'
        ]
    ]);
}

mysqli_close($conn);
?>