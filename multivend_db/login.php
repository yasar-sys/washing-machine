<?php
require 'config.php';

// Initialize variables
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mobile = sanitize($_POST['mobile']);
    $pin = sanitize($_POST['pin']);
    
    // Basic validation
    if (empty($mobile) || empty($pin)) {
        $error = "Please enter mobile number and PIN";
    } elseif (strlen($mobile) != 11 || !preg_match('/^01[3-9]\d{8}$/', $mobile)) {
        $error = "Please enter a valid 11-digit mobile number";
    } elseif (strlen($pin) != 4 || !is_numeric($pin)) {
        $error = "PIN must be 4 digits";
    } else {
        // Use prepared statement to prevent SQL injection
        $sql = "SELECT * FROM users WHERE mobile = ? AND pin = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $mobile, $pin);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_mobile'] = $user['mobile'];
                $_SESSION['balance'] = $user['balance'];
                $_SESSION['is_admin'] = false;
                
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "i", $user['id']);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                
                // Close statement
                mysqli_stmt_close($stmt);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid mobile number or PIN";
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error = "Database error. Please try again.";
        }
    }
    
    // If there's an error, store it in session and redirect
    if (!empty($error)) {
        $_SESSION['login_error'] = $error;
        header('Location: index.php');
        exit();
    }
} else {
    // If not POST request, redirect to index
    header('Location: index.php');
    exit();
}
?>