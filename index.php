<?php
require 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Check for login error
$login_error = '';
if (isset($_SESSION['login_error'])) {
    $login_error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SYSTEM_NAME; ?> - Smart Washing System</title>
    <link rel="stylesheet" href="css/style.css?ver=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-wrapper">
            <div class="login-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-washing-machine"></i>
                    </div>
                    <h1><?php echo SYSTEM_NAME; ?></h1>
                </div>
                <p class="tagline">Smart Washing & Vending Machine System</p>
            </div>
            
            <div class="login-card">
                <div class="card-header">
                    <h2><i class="fas fa-sign-in-alt"></i> User Login</h2>
                    <p>Enter your credentials to access your account</p>
                </div>
                
                <?php if (!empty($login_error)): ?>
                    <div class="error-box">
                        <p><i class="fas fa-exclamation-circle"></i> <?php echo $login_error; ?></p>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" class="login-form">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <input type="text" name="mobile" placeholder="Mobile Number (01XXXXXXXXX)" 
                               pattern="01[3-9]\d{8}" required
                               value="<?php echo isset($_POST['mobile']) ? htmlspecialchars($_POST['mobile']) : ''; ?>">
                    </div>
                    
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <input type="password" name="pin" placeholder="4-digit PIN" 
                               maxlength="4" pattern="\d{4}" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt"></i> Login to Dashboard
                    </button>
                    
                    <div class="login-footer">
                        <div class="login-links">
                            <a href="register.php" class="login-link">
                                <i class="fas fa-user-plus"></i> Create New Account
                            </a>
                            <a href="admin.php" class="login-link">
                                <i class="fas fa-cog"></i> Admin Panel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="services-preview">
                <h3><i class="fas fa-concierge-bell"></i> Available Services</h3>
                <div class="services-grid">
                    <div class="service-card">
                        <i class="fas fa-washing-machine"></i>
                        <h4>Washing Machine</h4>
                        <p>Book slots for washing clothes</p>
                        <span class="price"><?php echo formatCurrency(WASHING_PRICE); ?>/slot</span>
                    </div>
                    <div class="service-card">
                        <i class="fas fa-coffee"></i>
                        <h4>Tea & Coffee</h4>
                        <p>Instant tea and coffee vending</p>
                        <span class="price">From <?php echo formatCurrency(TEA_PRICE); ?></span>
                    </div>
                    <div class="service-card">
                        <i class="fas fa-credit-card"></i>
                        <h4>Card System</h4>
                        <p>Register NFC/RFID card for quick access</p>
                        <span class="price">Free Registration</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>