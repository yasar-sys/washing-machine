<?php
require 'config.php';

// Security check - must be logged in as regular user
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Prevent admin from accessing user dashboard
if (isAdmin()) {
    header('Location: admin.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data with prepared statement
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    // User not found in database
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get active bookings
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get only future bookings and active OTPs
$booking_sql = "SELECT b.*, l.name as location_name 
                FROM bookings b 
                LEFT JOIN locations l ON b.location_id = l.id
                WHERE b.user_id = ? 
                AND (b.booking_date > ? 
                     OR (b.booking_date = ? AND b.end_time > ?))
                AND b.status = 'active'
                ORDER BY b.booking_date, b.start_time";
$booking_stmt = mysqli_prepare($conn, $booking_sql);
mysqli_stmt_bind_param($booking_stmt, "isss", $user_id, $today, $today, $current_time);
mysqli_stmt_execute($booking_stmt);
$booking_result = mysqli_stmt_get_result($booking_stmt);

// Check for expired OTPs and hide them
$expire_sql = "UPDATE bookings 
               SET status = 'expired' 
               WHERE user_id = ? 
               AND (booking_date < ? 
                    OR (booking_date = ? AND end_time < ?))
               AND status = 'active'";
$expire_stmt = mysqli_prepare($conn, $expire_sql);
mysqli_stmt_bind_param($expire_stmt, "isss", $user_id, $today, $today, $current_time);
mysqli_stmt_execute($expire_stmt);
mysqli_stmt_close($expire_stmt);

// Check if user has registered card
$card_sql = "SELECT * FROM user_cards WHERE user_id = ? AND status = 'active' LIMIT 1";
$card_stmt = mysqli_prepare($conn, $card_sql);
mysqli_stmt_bind_param($card_stmt, "i", $user_id);
mysqli_stmt_execute($card_stmt);
$card_result = mysqli_stmt_get_result($card_stmt);
$has_card = mysqli_num_rows($card_result) > 0;
mysqli_stmt_close($card_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?ver=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-washing-machine"></i>
                    </div>
                    <h1><?php echo SYSTEM_NAME; ?></h1>
                </div>
                <div class="user-info">
                    <span class="welcome">Welcome back,</span>
                    <h2 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                </div>
            </div>
            <div class="header-right">
                <div class="balance-info">
                    <i class="fas fa-wallet"></i>
                    <span><?php echo formatCurrency($user['balance']); ?></span>
                </div>
                <a href="recharge.php" class="btn btn-primary">
                    <i class="fas fa-wallet"></i> Recharge
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-main">
            <!-- Quick Services -->
            <div class="services-quick-access">
                <h2><i class="fas fa-bolt"></i> Quick Access</h2>
                <div class="services-grid">
                    <a href="booking.php" class="service-quick-card washing">
                        <div class="service-icon">
                            <i class="fas fa-washing-machine"></i>
                        </div>
                        <div class="service-info">
                            <h3>Book Washing</h3>
                            <p><?php echo formatCurrency(WASHING_PRICE); ?> per slot</p>
                            <span class="status">Available</span>
                        </div>
                    </a>
                    
                    <a href="tea_coffee.php" class="service-quick-card tea-coffee">
                        <div class="service-icon">
                            <i class="fas fa-coffee"></i>
                        </div>
                        <div class="service-info">
                            <h3>Tea & Coffee</h3>
                            <p>Instant hot beverages</p>
                            <span class="status">Ready</span>
                        </div>
                    </a>
                    
                    <?php if ($has_card): ?>
                    <div class="service-quick-card card-active">
                        <div class="service-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="service-info">
                            <h3>Card Registered</h3>
                            <p>Tap to use services</p>
                            <span class="status">Active</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="card_register.php" class="service-quick-card card-register">
                        <div class="service-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="service-info">
                            <h3>Register Card</h3>
                            <p>NFC/RFID card</p>
                            <span class="status">Click to register</span>
                        </div>
                    </a>
                    <?php endif; ?>
                    
                    <a href="recharge.php" class="service-quick-card recharge">
                        <div class="service-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="service-info">
                            <h3>Add Balance</h3>
                            <p>bKash, Nagad, Rocket</p>
                            <span class="status">Instant</span>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Active Bookings Section -->
            <div class="bookings-section">
                <div class="section-header">
                    <h2><i class="fas fa-clock"></i> Your Active Bookings</h2>
                    <a href="booking.php" class="btn btn-secondary">
                        <i class="fas fa-plus"></i> Book New Slot
                    </a>
                </div>

                <?php if (mysqli_num_rows($booking_result) > 0): ?>
                    <div class="bookings-grid">
                        <?php while($booking = mysqli_fetch_assoc($booking_result)): 
                            $booking_time = date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                          date('h:i A', strtotime($booking['end_time']));
                            $is_today = $booking['booking_date'] == $today;
                        ?>
                        <div class="booking-card <?php echo $is_today ? 'today' : ''; ?>">
                            <div class="booking-header">
                                <div class="booking-date">
                                    <div class="date-day"><?php echo date('d', strtotime($booking['booking_date'])); ?></div>
                                    <div class="date-month"><?php echo date('M', strtotime($booking['booking_date'])); ?></div>
                                </div>
                                <div class="booking-title">
                                    <h3>Washing Machine Booking</h3>
                                    <?php if (!empty($booking['location_name'])): ?>
                                    <p class="location">
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($booking['location_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-item">
                                    <span class="label">Time Slot:</span>
                                    <span class="value"><?php echo htmlspecialchars($booking_time); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label">Duration:</span>
                                    <span class="value">1.5 hours</span>
                                </div>
                                <div class="detail-item">
                                    <span class="label">Amount:</span>
                                    <span class="value"><?php echo formatCurrency($booking['amount']); ?></span>
                                </div>
                            </div>
                            
                            <div class="booking-otp-section">
                                <div class="otp-label">
                                    <i class="fas fa-key"></i> Your OTP:
                                </div>
                                <div class="otp-code">
                                    <?php echo htmlspecialchars($booking['otp']); ?>
                                </div>
                                <div class="otp-note">
                                    <i class="fas fa-info-circle"></i> 
                                    Works only during first hour of booking
                                </div>
                            </div>
                            
                            <div class="booking-status">
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                                <span class="time-remaining">
                                    <?php 
                                    if ($is_today) {
                                        $end_time = strtotime($booking['end_time']);
                                        $current = time();
                                        $remaining = $end_time - $current;
                                        if ($remaining > 0) {
                                            $hours = floor($remaining / 3600);
                                            $minutes = floor(($remaining % 3600) / 60);
                                            echo $hours . 'h ' . $minutes . 'm remaining';
                                        }
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-times"></i>
                        <h3>No Active Bookings</h3>
                        <p>You don't have any upcoming washing machine bookings.</p>
                        <a href="booking.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Your First Slot
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Transactions -->
            <div class="transactions-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent Transactions</h2>
                    <a href="#" class="btn btn-secondary">View All</a>
                </div>
                
                <div class="transactions-list">
                    <?php
                    $trans_sql = "SELECT * FROM transactions 
                                 WHERE user_id = ? 
                                 ORDER BY created_at DESC 
                                 LIMIT 5";
                    $trans_stmt = mysqli_prepare($conn, $trans_sql);
                    mysqli_stmt_bind_param($trans_stmt, "i", $user_id);
                    mysqli_stmt_execute($trans_stmt);
                    $trans_result = mysqli_stmt_get_result($trans_stmt);
                    
                    if (mysqli_num_rows($trans_result) > 0):
                        while($trans = mysqli_fetch_assoc($trans_result)):
                    ?>
                    <div class="transaction-item <?php echo $trans['type']; ?>">
                        <div class="trans-icon">
                            <?php if ($trans['type'] == 'credit'): ?>
                                <i class="fas fa-arrow-down"></i>
                            <?php else: ?>
                                <i class="fas fa-arrow-up"></i>
                            <?php endif; ?>
                        </div>
                        <div class="trans-details">
                            <h4><?php echo htmlspecialchars($trans['note']); ?></h4>
                            <p class="trans-time">
                                <?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?>
                            </p>
                        </div>
                        <div class="trans-amount <?php echo $trans['type']; ?>">
                            <?php if ($trans['type'] == 'credit'): ?>
                                +<?php echo formatCurrency($trans['amount']); ?>
                            <?php else: ?>
                                -<?php echo formatCurrency($trans['amount']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <div class="empty-state small">
                        <i class="fas fa-receipt"></i>
                        <p>No transaction history found</p>
                    </div>
                    <?php 
                    endif;
                    mysqli_stmt_close($trans_stmt);
                    ?>
                </div>
            </div>
        </main>

        <!-- Navigation Footer -->
        <nav class="dashboard-nav">
            <a href="dashboard.php" class="nav-item active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="booking.php" class="nav-item">
                <i class="fas fa-calendar"></i>
                <span>Book</span>
            </a>
            <a href="tea_coffee.php" class="nav-item">
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
    // Auto-refresh every 30 seconds to update booking status
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>
<?php
mysqli_stmt_close($booking_stmt);
mysqli_close($conn);
?>