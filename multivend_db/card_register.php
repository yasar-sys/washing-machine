<?php
require 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if user already has a card
$check_sql = "SELECT * FROM user_cards WHERE user_id = ? AND status = 'active'";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $user_id);
mysqli_stmt_execute($check_stmt);
$card_result = mysqli_stmt_get_result($check_stmt);
$has_card = mysqli_fetch_assoc($card_result);
mysqli_stmt_close($check_stmt);

// Get user info
$user_sql = "SELECT name, mobile FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user_info = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

// Process card registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_card'])) {
    $card_number = sanitize($_POST['card_number']);
    $card_type = sanitize($_POST['card_type']);
    
    // Validation
    if (empty($card_number)) {
        $error = "Please enter card number";
    } elseif (strlen($card_number) < 8) {
        $error = "Card number must be at least 8 characters";
    } elseif (!in_array($card_type, ['rfid', 'nfc'])) {
        $error = "Please select valid card type";
    } else {
        // Check if card is already registered
        $card_check_sql = "SELECT id FROM user_cards WHERE card_number = ? AND status = 'active'";
        $card_check_stmt = mysqli_prepare($conn, $card_check_sql);
        mysqli_stmt_bind_param($card_check_stmt, "s", $card_number);
        mysqli_stmt_execute($card_check_stmt);
        mysqli_stmt_store_result($card_check_stmt);
        
        if (mysqli_stmt_num_rows($card_check_stmt) > 0) {
            $error = "This card is already registered to another user";
        } elseif ($has_card) {
            $error = "You already have an active card. Please deactivate it first.";
        } else {
            // Register the card
            $insert_sql = "INSERT INTO user_cards (user_id, card_number, card_type, status) 
                          VALUES (?, ?, ?, 'active')";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "iss", $user_id, $card_number, $card_type);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                $success = "Card registered successfully! You can now use it to buy tea and coffee.";
                
                // Log the registration
                $log_sql = "INSERT INTO system_logs (user_id, action_type, description) 
                           VALUES (?, 'CARD_REGISTER', ?)";
                $log_stmt = mysqli_prepare($conn, $log_sql);
                $log_desc = "Registered $card_type card: " . substr($card_number, -4);
                mysqli_stmt_bind_param($log_stmt, "is", $user_id, $log_desc);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);
                
                // Refresh card info
                $has_card = [
                    'card_number' => $card_number,
                    'card_type' => $card_type,
                    'registered_at' => date('Y-m-d H:i:s')
                ];
            } else {
                $error = "Failed to register card. Please try again.";
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($card_check_stmt);
    }
}

// Process card deactivation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deactivate_card'])) {
    $deactivate_sql = "UPDATE user_cards SET status = 'blocked' WHERE user_id = ? AND status = 'active'";
    $deactivate_stmt = mysqli_prepare($conn, $deactivate_sql);
    mysqli_stmt_bind_param($deactivate_stmt, "i", $user_id);
    
    if (mysqli_stmt_execute($deactivate_stmt)) {
        $success = "Card deactivated successfully.";
        $has_card = false;
        
        // Log the deactivation
        $log_sql = "INSERT INTO system_logs (user_id, action_type, description) 
                   VALUES (?, 'CARD_DEACTIVATE', 'Card deactivated by user')";
        $log_stmt = mysqli_prepare($conn, $log_sql);
        mysqli_stmt_bind_param($log_stmt, "i", $user_id);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    } else {
        $error = "Failed to deactivate card. Please try again.";
    }
    mysqli_stmt_close($deactivate_stmt);
}

// Get card usage history
$usage_sql = "SELECT t.*, uc.card_number 
             FROM tea_coffee_transactions t
             LEFT JOIN user_cards uc ON t.card_id = uc.id
             WHERE t.user_id = ? AND t.card_id IS NOT NULL
             ORDER BY t.created_at DESC 
             LIMIT 10";
$usage_stmt = mysqli_prepare($conn, $usage_sql);
mysqli_stmt_bind_param($usage_stmt, "i", $user_id);
mysqli_stmt_execute($usage_stmt);
$usage_result = mysqli_stmt_get_result($usage_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Card Registration - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .card-registration-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }
    .card-info-box {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        text-align: center;
    }
    .card-number-display {
        font-size: 2rem;
        font-weight: 700;
        letter-spacing: 2px;
        font-family: 'Courier New', monospace;
        background: rgba(255,255,255,0.1);
        padding: 15px;
        border-radius: 10px;
        margin: 20px 0;
    }
    .card-type-badge {
        background: rgba(255,255,255,0.2);
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        display: inline-block;
    }
    .registration-form {
        background: white;
        border-radius: 20px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .form-header {
        text-align: center;
        margin-bottom: 30px;
    }
    .form-header i {
        font-size: 3rem;
        color: #4361ee;
        margin-bottom: 15px;
    }
    .card-types {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    .card-type-option {
        flex: 1;
        text-align: center;
    }
    .card-type-card {
        border: 3px solid #e0e0e0;
        border-radius: 15px;
        padding: 20px;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }
    .card-type-card:hover {
        border-color: #4361ee;
        transform: translateY(-5px);
    }
    .card-type-card.selected {
        border-color: #4361ee;
        background: #eef2ff;
    }
    .card-type-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    .card-type-card.rfid .card-type-icon {
        color: #3b82f6;
    }
    .card-type-card.nfc .card-type-icon {
        color: #10b981;
    }
    .card-input-group {
        position: relative;
        margin: 25px 0;
    }
    .card-input-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        font-size: 1.2rem;
    }
    .card-input {
        width: 100%;
        padding: 15px 15px 15px 45px;
        font-size: 1.1rem;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
    }
    .card-input:focus {
        outline: none;
        border-color: #4361ee;
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }
    .instructions-box {
        background: #f8fafc;
        border-radius: 15px;
        padding: 20px;
        margin: 25px 0;
        border-left: 4px solid #4361ee;
    }
    .instructions-box h4 {
        color: #333;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .usage-history {
        background: white;
        border-radius: 20px;
        padding: 25px;
        margin-top: 30px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    }
    .usage-item {
        display: flex;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #e5e7eb;
    }
    .usage-item:last-child {
        border-bottom: none;
    }
    .usage-icon {
        width: 40px;
        height: 40px;
        background: #eef2ff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
    }
    .usage-icon.tea {
        background: #fef3c7;
        color: #92400e;
    }
    .usage-icon.coffee {
        background: #f3e8ff;
        color: #7c3aed;
    }
    .usage-details {
        flex: 1;
    }
    .usage-amount {
        font-weight: 600;
        color: #ef4444;
    }
    .card-scanner-simulator {
        background: #1f2937;
        border-radius: 15px;
        padding: 30px;
        color: white;
        text-align: center;
        margin: 20px 0;
        font-family: 'Courier New', monospace;
    }
    .scanner-display {
        background: #374151;
        padding: 20px;
        border-radius: 10px;
        margin: 20px 0;
        font-size: 1.2rem;
        letter-spacing: 2px;
    }
    .scan-button {
        background: #10b981;
        color: white;
        border: none;
        padding: 15px 40px;
        border-radius: 10px;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .scan-button:hover {
        background: #059669;
        transform: scale(1.05);
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
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h1>Card Registration</h1>
                </div>
                <div class="user-info">
                    <a href="dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
            <div class="header-right">
                <div class="user-details">
                    <span><?php echo htmlspecialchars($user_info['name']); ?></span>
                    <small><?php echo htmlspecialchars($user_info['mobile']); ?></small>
                </div>
            </div>
        </header>

        <main class="card-registration-container">
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

            <?php if ($has_card): ?>
            <!-- Show registered card -->
            <div class="card-info-box">
                <h2><i class="fas fa-check-circle"></i> Card Registered</h2>
                <div class="card-number-display">
                    **** **** **** <?php echo substr($has_card['card_number'], -4); ?>
                </div>
                <div class="card-details">
                    <div class="card-type-badge">
                        <i class="fas fa-<?php echo $has_card['card_type'] == 'rfid' ? 'wifi' : 'wave-square'; ?>"></i>
                        <?php echo strtoupper($has_card['card_type']); ?> CARD
                    </div>
                    <p class="registration-date">
                        Registered on <?php echo date('M d, Y', strtotime($has_card['registered_at'])); ?>
                    </p>
                </div>
                
                <div class="card-actions">
                    <form method="POST" style="display: inline-block;">
                        <button type="submit" name="deactivate_card" class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to deactivate this card?')">
                            <i class="fas fa-ban"></i> Deactivate Card
                        </button>
                    </form>
                    <a href="tea_coffee.php" class="btn btn-primary">
                        <i class="fas fa-coffee"></i> Use Card Now
                    </a>
                </div>
            </div>
            
            <!-- Card usage instructions -->
            <div class="instructions-box">
                <h4><i class="fas fa-info-circle"></i> How to Use Your Card:</h4>
                <ol>
                    <li>Go to the tea/coffee vending machine</li>
                    <li>Tap your registered card on the card reader</li>
                    <li>Select your beverage (tea or coffee)</li>
                    <li>The amount will be deducted automatically from your balance</li>
                    <li>Card works with RDM6300 RFID/NFC readers</li>
                </ol>
            </div>
            
            <!-- Usage History -->
            <div class="usage-history">
                <h3><i class="fas fa-history"></i> Recent Card Usage</h3>
                <?php if (mysqli_num_rows($usage_result) > 0): ?>
                    <div class="usage-list">
                        <?php while($usage = mysqli_fetch_assoc($usage_result)): ?>
                        <div class="usage-item">
                            <div class="usage-icon <?php echo $usage['service_type']; ?>">
                                <i class="fas fa-<?php echo $usage['service_type'] == 'tea' ? 'mug-hot' : 'coffee'; ?>"></i>
                            </div>
                            <div class="usage-details">
                                <h4><?php echo ucfirst($usage['service_type']); ?> Purchase</h4>
                                <p class="usage-time">
                                    <?php echo date('M d, h:i A', strtotime($usage['created_at'])); ?>
                                </p>
                            </div>
                            <div class="usage-amount">
                                -<?php echo formatCurrency($usage['amount']); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-credit-card"></i>
                        <p>No card usage history yet</p>
                        <a href="tea_coffee.php" class="btn btn-primary">Buy Tea/Coffee Now</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Card Registration Form -->
            <div class="registration-form">
                <div class="form-header">
                    <i class="fas fa-credit-card"></i>
                    <h2>Register Your Card</h2>
                    <p>Register an NFC/RFID card to buy tea and coffee with just a tap</p>
                </div>
                
                <!-- Card Scanner Simulator (for demo) -->
                <div class="card-scanner-simulator">
                    <h3><i class="fas fa-fingerprint"></i> Card Scanner</h3>
                    <p>Place your card near the scanner to read the card number</p>
                    <div class="scanner-display" id="scannerDisplay">
                        Waiting for card...
                    </div>
                    <button type="button" class="scan-button" id="simulateScan">
                        <i class="fas fa-satellite-dish"></i> Simulate Card Scan
                    </button>
                    <p class="scanner-note">
                        <small>In real system, this would be automatic with RDM6300 reader</small>
                    </p>
                </div>
                
                <form method="POST" id="cardForm">
                    <!-- Card Type Selection -->
                    <div class="form-group">
                        <label>Card Type:</label>
                        <div class="card-types">
                            <label class="card-type-option">
                                <input type="radio" name="card_type" value="rfid" checked>
                                <div class="card-type-card rfid selected">
                                    <div class="card-type-icon">
                                        <i class="fas fa-wifi"></i>
                                    </div>
                                    <h4>RFID Card</h4>
                                    <p>125kHz frequency</p>
                                    <small>Compatible with RDM6300</small>
                                </div>
                            </label>
                            <label class="card-type-option">
                                <input type="radio" name="card_type" value="nfc">
                                <div class="card-type-card nfc">
                                    <div class="card-type-icon">
                                        <i class="fas fa-wave-square"></i>
                                    </div>
                                    <h4>NFC Card</h4>
                                    <p>13.56MHz frequency</p>
                                    <small>Mobile phone compatible</small>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Card Number Input -->
                    <div class="form-group">
                        <label>Card Number:</label>
                        <div class="card-input-group">
                            <div class="card-input-icon">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <input type="text" name="card_number" 
                                   class="card-input" 
                                   id="cardNumberInput"
                                   placeholder="Enter 8-20 digit card number"
                                   pattern="[0-9]{8,20}"
                                   required
                                   value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>">
                        </div>
                        <small class="help-text">
                            <i class="fas fa-info-circle"></i>
                            Card number will be read automatically from scanner
                        </small>
                    </div>
                    
                    <!-- Terms and Conditions -->
                    <div class="instructions-box">
                        <h4><i class="fas fa-shield-alt"></i> Important Information:</h4>
                        <ul>
                            <li>Only one card can be registered per account</li>
                            <li>Card will work with all tea/coffee vending machines</li>
                            <li>Keep your card secure - report lost cards immediately</li>
                            <li>Card registration is free of charge</li>
                            <li>You can deactivate your card anytime</li>
                        </ul>
                    </div>
                    
                    <button type="submit" name="register_card" class="btn btn-primary btn-lg">
                        <i class="fas fa-user-plus"></i> Register Card
                    </button>
                    
                    <div class="form-footer">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <a href="tea_coffee.php" class="btn btn-outline">
                            <i class="fas fa-coffee"></i> Skip for Now
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Technical Information -->
            <div class="technical-info">
                <h3><i class="fas fa-microchip"></i> Technical Specifications</h3>
                <div class="specs-grid">
                    <div class="spec-item">
                        <i class="fas fa-wifi"></i>
                        <h4>RFID Support</h4>
                        <p>125kHz EM4100 protocol</p>
                        <small>RDM6300 compatible</small>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-wave-square"></i>
                        <h4>NFC Support</h4>
                        <p>13.56MHz ISO14443</p>
                        <small>Phone NFC compatible</small>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-bolt"></i>
                        <h4>Quick Response</h4>
                        <p>Less than 1 second</p>
                        <small>Instant detection</small>
                    </div>
                    <div class="spec-item">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Secure</h4>
                        <p>Unique card binding</p>
                        <small>Prevents unauthorized use</small>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    // Card type selection
    document.querySelectorAll('input[name="card_type"]').forEach(input => {
        input.addEventListener('change', function() {
            document.querySelectorAll('.card-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            this.parentElement.querySelector('.card-type-card').classList.add('selected');
        });
    });
    
    // Card scanner simulation
    document.getElementById('simulateScan').addEventListener('click', function() {
        const display = document.getElementById('scannerDisplay');
        const cardInput = document.getElementById('cardNumberInput');
        
        // Show scanning animation
        display.textContent = 'Scanning...';
        display.style.color = '#f59e0b';
        
        setTimeout(() => {
            // Generate a random card number for simulation
            const cardNumber = '55' + Math.floor(100000 + Math.random() * 900000);
            display.textContent = 'Card Detected: ' + cardNumber;
            display.style.color = '#10b981';
            
            // Fill the input field
            cardInput.value = cardNumber;
            
            // Add success animation
            display.classList.add('pulse');
            setTimeout(() => {
                display.classList.remove('pulse');
            }, 1000);
        }, 1500);
    });
    
    // Auto-select RFID when typing starts with 55 (simulating RFID cards)
    document.getElementById('cardNumberInput').addEventListener('input', function(e) {
        if (this.value.startsWith('55')) {
            document.querySelector('input[name="card_type"][value="rfid"]').checked = true;
            document.querySelector('.card-type-card.rfid').classList.add('selected');
            document.querySelector('.card-type-card.nfc').classList.remove('selected');
        } else if (this.value.startsWith('88')) {
            document.querySelector('input[name="card_type"][value="nfc"]').checked = true;
            document.querySelector('.card-type-card.nfc').classList.add('selected');
            document.querySelector('.card-type-card.rfid').classList.remove('selected');
        }
    });
    
    // Form validation
    document.getElementById('cardForm')?.addEventListener('submit', function(e) {
        const cardNumber = document.getElementById('cardNumberInput').value;
        if (cardNumber.length < 8) {
            e.preventDefault();
            alert('Card number must be at least 8 digits');
            return false;
        }
        return true;
    });
    
    // Add CSS for pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        .pulse {
            animation: pulse 0.5s ease;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
<?php
mysqli_stmt_close($usage_stmt);
mysqli_close($conn);
?>