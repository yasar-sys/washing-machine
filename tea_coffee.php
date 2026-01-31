<?php
require 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$otp_generated = '';

// Get user balance
$balance_sql = "SELECT balance FROM users WHERE id = ?";
$balance_stmt = mysqli_prepare($conn, $balance_sql);
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$user = mysqli_fetch_assoc($balance_result);
$balance = $user['balance'];
mysqli_stmt_close($balance_stmt);

// Check if user has registered card
$card_sql = "SELECT * FROM user_cards WHERE user_id = ? AND status = 'active' LIMIT 1";
$card_stmt = mysqli_prepare($conn, $card_sql);
mysqli_stmt_bind_param($card_stmt, "i", $user_id);
mysqli_stmt_execute($card_stmt);
$card_result = mysqli_stmt_get_result($card_stmt);
$has_card = mysqli_fetch_assoc($card_result);
mysqli_stmt_close($card_stmt);

// Process tea/coffee purchase
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['buy_tea'])) {
        $service_type = 'tea';
        $amount = TEA_PRICE;
    } elseif (isset($_POST['buy_coffee'])) {
        $service_type = 'coffee';
        $amount = COFFEE_PRICE;
    } else {
        $error = "Invalid request";
    }
    
    if (!$error) {
        // Check balance
        if ($balance < $amount) {
            $error = "Insufficient balance. You need " . formatCurrency($amount) . " to buy $service_type.";
        } else {
            // Check if using card or OTP
            $use_card = isset($_POST['use_card']) && $has_card;
            $card_id = $use_card ? $has_card['id'] : NULL;
            
            // Generate OTP if not using card
            $otp = NULL;
            if (!$use_card) {
                do {
                    $otp = substr(strtoupper($service_type), 0, 1) . sprintf("%03d", rand(0, 999));
                    $otp_check_sql = "SELECT id FROM tea_coffee_transactions WHERE otp = ?";
                    $otp_stmt = mysqli_prepare($conn, $otp_check_sql);
                    mysqli_stmt_bind_param($otp_stmt, "s", $otp);
                    mysqli_stmt_execute($otp_stmt);
                    mysqli_stmt_store_result($otp_stmt);
                    $otp_exists = mysqli_stmt_num_rows($otp_stmt) > 0;
                    mysqli_stmt_close($otp_stmt);
                } while ($otp_exists);
                
                $otp_generated = $otp;
            }
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Insert tea/coffee transaction
                $sql = "INSERT INTO tea_coffee_transactions (user_id, card_id, service_type, amount, otp) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iisds", $user_id, $card_id, $service_type, $amount, $otp);
                mysqli_stmt_execute($stmt);
                $transaction_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);
                
                // Update user balance (handled by trigger, but we check anyway)
                $update_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "di", $amount, $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Update local balance
                $balance -= $amount;
                
                if ($use_card) {
                    $success = ucfirst($service_type) . " purchased successfully using your card!";
                } else {
                    $success = ucfirst($service_type) . " purchased successfully! Your OTP: <strong>$otp</strong>";
                }
                
                // Log the purchase
                $log_sql = "INSERT INTO system_logs (user_id, action_type, description) 
                           VALUES (?, 'PURCHASE', ?)";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                $log_desc = "Purchased $service_type for " . formatCurrency($amount) . 
                           ($use_card ? " using card" : " using OTP");
                mysqli_stmt_bind_param($log_stmt, "is", $user_id, $log_desc);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Purchase failed. Please try again.";
            }
        }
    }
}

// Get recent tea/coffee transactions
$trans_sql = "SELECT * FROM tea_coffee_transactions 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 10";
$trans_stmt = mysqli_prepare($conn, $trans_sql);
mysqli_stmt_bind_param($trans_stmt, "i", $user_id);
mysqli_stmt_execute($trans_stmt);
$trans_result = mysqli_stmt_get_result($trans_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tea & Coffee - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .service-card {
        background: white;
        border-radius: 20px;
        padding: 30px;
        text-align: center;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: all 0.3s;
        border: 2px solid transparent;
    }
    .service-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
    }
    .service-card.tea {
        border-color: #fbbf24;
    }
    .service-card.coffee {
        border-color: #92400e;
    }
    .service-icon {
        font-size: 3rem;
        margin-bottom: 20px;
    }
    .service-card.tea .service-icon {
        color: #f59e0b;
    }
    .service-card.coffee .service-icon {
        color: #92400e;
    }
    .service-price {
        font-size: 2rem;
        font-weight: 700;
        margin: 20px 0;
        color: #333;
    }
    .service-description {
        color: #666;
        margin-bottom: 25px;
        font-size: 0.95rem;
    }
    .card-option {
        background: #f8fafc;
        border-radius: 10px;
        padding: 15px;
        margin: 20px 0;
        border: 2px solid #e2e8f0;
    }
    .card-option.active {
        border-color: #10b981;
        background: #d1fae5;
    }
    .otp-display {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 15px;
        margin: 20px 0;
        text-align: center;
    }
    .otp-code {
        font-size: 2.5rem;
        font-weight: 700;
        letter-spacing: 10px;
        font-family: 'Courier New', monospace;
        margin: 10px 0;
    }
    .otp-instructions {
        background: #eef2ff;
        padding: 15px;
        border-radius: 10px;
        margin-top: 20px;
    }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <h1>Tea & Coffee</h1>
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
                    <span><?php echo formatCurrency($balance); ?></span>
                </div>
                <?php if ($has_card): ?>
                <div class="card-indicator">
                    <i class="fas fa-credit-card"></i>
                    <span>Card: <?php echo substr($has_card['card_number'], -4); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="tea-coffee-main">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="services-container">
                <!-- Tea Card -->
                <div class="service-card tea">
                    <div class="service-icon">
                        <i class="fas fa-mug-hot"></i>
                    </div>
                    <h2>Hot Tea</h2>
                    <div class="service-price">
                        <?php echo formatCurrency(TEA_PRICE); ?>
                    </div>
                    <p class="service-description">
                        Freshly brewed hot tea with milk and sugar
                    </p>
                    
                    <form method="POST">
                        <?php if ($has_card): ?>
                        <div class="card-option <?php echo isset($_POST['use_card']) ? 'active' : ''; ?>">
                            <label>
                                <input type="checkbox" name="use_card" value="1" 
                                       <?php echo isset($_POST['use_card']) ? 'checked' : 'checked'; ?>>
                                <i class="fas fa-credit-card"></i> Use Registered Card
                                <small>(Tap card on machine)</small>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="buy_tea" class="btn btn-primary btn-lg"
                                <?php echo ($balance < TEA_PRICE) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart"></i>
                            Buy Tea for <?php echo formatCurrency(TEA_PRICE); ?>
                        </button>
                        
                        <?php if ($balance < TEA_PRICE): ?>
                        <p class="insufficient-balance">
                            <i class="fas fa-exclamation-circle"></i>
                            Insufficient balance. <a href="recharge.php">Recharge now</a>
                        </p>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Coffee Card -->
                <div class="service-card coffee">
                    <div class="service-icon">
                        <i class="fas fa-coffee"></i>
                    </div>
                    <h2>Hot Coffee</h2>
                    <div class="service-price">
                        <?php echo formatCurrency(COFFEE_PRICE); ?>
                    </div>
                    <p class="service-description">
                        Premium instant coffee with milk and sugar
                    </p>
                    
                    <form method="POST">
                        <?php if ($has_card): ?>
                        <div class="card-option <?php echo isset($_POST['use_card']) ? 'active' : ''; ?>">
                            <label>
                                <input type="checkbox" name="use_card" value="1"
                                       <?php echo isset($_POST['use_card']) ? 'checked' : 'checked'; ?>>
                                <i class="fas fa-credit-card"></i> Use Registered Card
                                <small>(Tap card on machine)</small>
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="buy_coffee" class="btn btn-primary btn-lg"
                                <?php echo ($balance < COFFEE_PRICE) ? 'disabled' : ''; ?>>
                            <i class="fas fa-shopping-cart"></i>
                            Buy Coffee for <?php echo formatCurrency(COFFEE_PRICE); ?>
                        </button>
                        
                        <?php if ($balance < COFFEE_PRICE): ?>
                        <p class="insufficient-balance">
                            <i class="fas fa-exclamation-circle"></i>
                            Insufficient balance. <a href="recharge.php">Recharge now</a>
                        </p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if ($otp_generated): ?>
            <div class="otp-display">
                <h3><i class="fas fa-key"></i> Your OTP for <?php echo ucfirst($service_type); ?></h3>
                <div class="otp-code"><?php echo $otp_generated; ?></div>
                <p>Enter this OTP on the vending machine</p>
                <p><small>OTP expires in 5 minutes</small></p>
            </div>
            
            <div class="otp-instructions">
                <h4><i class="fas fa-info-circle"></i> How to Use OTP:</h4>
                <ol>
                    <li>Go to the tea/coffee vending machine</li>
                    <li>Select <?php echo $service_type; ?> option on the machine</li>
                    <li>Enter the OTP shown above using the keypad</li>
                    <li>Press confirm to receive your <?php echo $service_type; ?></li>
                    <li>OTP will expire after 5 minutes or after use</li>
                </ol>
            </div>
            <?php endif; ?>

            <!-- Card Registration Prompt -->
            <?php if (!$has_card): ?>
            <div class="card-promo">
                <div class="promo-content">
                    <i class="fas fa-credit-card fa-2x"></i>
                    <div>
                        <h3>Register Your Card for Faster Service!</h3>
                        <p>Register an NFC/RFID card to buy tea/coffee with just a tap</p>
                    </div>
                    <a href="card_register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register Card Now
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Transactions -->
            <div class="transactions-history">
                <h3><i class="fas fa-history"></i> Recent Purchases</h3>
                
                <?php if (mysqli_num_rows($trans_result) > 0): ?>
                <div class="transactions-list">
                    <?php while($trans = mysqli_fetch_assoc($trans_result)): ?>
                    <div class="transaction-item">
                        <div class="trans-icon">
                            <?php if ($trans['service_type'] == 'tea'): ?>
                                <i class="fas fa-mug-hot" style="color: #f59e0b;"></i>
                            <?php else: ?>
                                <i class="fas fa-coffee" style="color: #92400e;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="trans-details">
                            <h4><?php echo ucfirst($trans['service_type']); ?> Purchase</h4>
                            <p class="trans-method">
                                <?php echo $trans['card_id'] ? 'Card Tap' : 'OTP: ' . $trans['otp']; ?>
                            </p>
                            <p class="trans-time">
                                <?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?>
                            </p>
                        </div>
                        <div class="trans-amount debit">
                            -<?php echo formatCurrency($trans['amount']); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-mug-hot"></i>
                    <p>No tea/coffee purchases yet</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Machine Instructions -->
            <div class="instructions-box">
                <h3><i class="fas fa-concierge-bell"></i> Machine Instructions</h3>
                <div class="instructions-grid">
                    <div class="instruction">
                        <div class="instruction-icon">
                            <i class="fas fa-keyboard"></i>
                        </div>
                        <h4>Using OTP</h4>
                        <p>1. Select beverage on machine<br>
                           2. Enter 4-digit OTP<br>
                           3. Press confirm button</p>
                    </div>
                    <div class="instruction">
                        <div class="instruction-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h4>Using Card</h4>
                        <p>1. Tap registered card on reader<br>
                           2. Select beverage<br>
                           3. Amount deducted automatically</p>
                    </div>
                    <div class="instruction">
                        <div class="instruction-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h4>Tips</h4>
                        <p>• Keep cup ready before starting<br>
                           • OTP expires in 5 minutes<br>
                           • One OTP per purchase</p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Navigation -->
        <nav class="dashboard-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="booking.php" class="nav-item">
                <i class="fas fa-calendar"></i>
                <span>Book</span>
            </a>
            <a href="tea_coffee.php" class="nav-item active">
                <i class="fas fa-coffee"></i>
                <span>Tea/Coffee</span>
            </a>
            <a href="recharge.php" class="nav-item">
                <i class="fas fa-wallet"></i>
                <span>Balance</span>
            </a>
            <a href="card_register.php" class="nav-item">
                <i class="fas fa-credit-card"></i>
                <span>Card</span>
            </a>
        </nav>
    </div>
    
    <script>
    // Auto-refresh balance after purchase
    <?php if ($success): ?>
    setTimeout(function() {
        location.reload();
    }, 5000);
    <?php endif; ?>
    
    // Copy OTP to clipboard
    function copyOTP() {
        const otp = document.querySelector('.otp-code').textContent;
        navigator.clipboard.writeText(otp).then(() => {
            alert('OTP copied to clipboard!');
        });
    }
    </script>
</body>
</html>
<?php
mysqli_stmt_close($trans_stmt);
mysqli_close($conn);
?>