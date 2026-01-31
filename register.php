<?php
require 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $mobile = sanitize($_POST['mobile']);
    $pin = sanitize($_POST['pin']);
    $confirm_pin = sanitize($_POST['confirm_pin']);
    
    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Please enter a valid name (minimum 2 characters)";
    }
    
    if (strlen($mobile) != 11 || !preg_match('/^01[3-9]\d{8}$/', $mobile)) {
        $errors[] = "Please enter a valid 11-digit mobile number";
    }
    
    if (strlen($pin) != 4 || !is_numeric($pin)) {
        $errors[] = "PIN must be exactly 4 digits (numbers only)";
    }
    
    if ($pin !== $confirm_pin) {
        $errors[] = "PINs do not match";
    }
    
    // Check if mobile exists using prepared statement
    $check_sql = "SELECT id FROM users WHERE mobile = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $mobile);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        $errors[] = "This mobile number is already registered";
    }
    mysqli_stmt_close($check_stmt);
    
    // If no errors, insert user
    if (empty($errors)) {
        $sql = "INSERT INTO users (name, mobile, pin, balance) VALUES (?, ?, ?, 0.00)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $name, $mobile, $pin);
            
            if (mysqli_stmt_execute($stmt)) {
                $user_id = mysqli_insert_id($conn);
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_mobile'] = $mobile;
                $_SESSION['balance'] = 0.00;
                $_SESSION['is_admin'] = false;
                
                mysqli_stmt_close($stmt);
                
                header('Location: dashboard.php');
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WashMate</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-wrapper">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-washing-machine"></i>
                    <h1>WashMate</h1>
                </div>
                <p class="tagline">Create Your Account</p>
            </div>
            
            <div class="login-card">
                <div class="card-header">
                    <h2><i class="fas fa-user-plus"></i> New Account</h2>
                    <p>Fill in your details to get started</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="error-box">
                        <?php foreach ($errors as $error): ?>
                            <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="login-form">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <input type="text" name="name" placeholder="Full Name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <input type="text" name="mobile" placeholder="Mobile Number (01XXXXXXXXX)" 
                               pattern="01[3-9]\d{8}" 
                               value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="pin" placeholder="4-digit PIN" 
                               maxlength="4" pattern="\d{4}" 
                               value="<?php echo isset($_POST['pin']) ? htmlspecialchars($_POST['pin']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="confirm_pin" placeholder="Confirm 4-digit PIN" 
                               maxlength="4" pattern="\d{4}" 
                               value="<?php echo isset($_POST['confirm_pin']) ? htmlspecialchars($_POST['confirm_pin']) : ''; ?>" 
                               required>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    
                    <div class="login-footer">
                        <a href="index.php" class="back-link">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                    </div>
                </form>
            </div>
            
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Important Notes:</h3>
                <ul>
                    <li>Mobile number must be 11 digits (Bangladeshi format)</li>
                    <li>PIN must be exactly 4 digits (numbers only)</li>
                    <li>Keep your PIN secure and don't share it</li>
                    <li>You can recharge your account after registration</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>