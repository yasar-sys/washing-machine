<?php
require 'config.php';

// Security check - must be logged in as regular user
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Prevent admin from accessing booking page
if (isAdmin()) {
    header('Location: admin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check balance with prepared statement
$balance_sql = "SELECT balance FROM users WHERE id = ?";
$balance_stmt = mysqli_prepare($conn, $balance_sql);
mysqli_stmt_bind_param($balance_stmt, "i", $user_id);
mysqli_stmt_execute($balance_stmt);
$balance_result = mysqli_stmt_get_result($balance_stmt);
$balance_row = mysqli_fetch_assoc($balance_result);
$balance = $balance_row['balance'];
mysqli_stmt_close($balance_stmt);

// Check if user has at least washing price
if ($balance < WASHING_PRICE) {
    $error = "Insufficient balance. You need at least " . formatCurrency(WASHING_PRICE) . " to book a slot.";
}

// Get available locations
$location_sql = "SELECT * FROM locations WHERE status = 'active' ORDER BY name";
$location_result = mysqli_query($conn, $location_sql);
$locations = [];
while($loc = mysqli_fetch_assoc($location_result)) {
    $locations[$loc['id']] = $loc['name'];
}

// Process booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$error && isset($_POST['book_slot'])) {
    $location_id = sanitize($_POST['location_id']);
    $booking_date = sanitize($_POST['date']);
    $start_time = sanitize($_POST['time']);
    
    // Calculate end time (1.5 hours later)
    $end_time = date('H:i:s', strtotime($start_time) + (BOOKING_DURATION * 60));
    
    // Validate location
    if (!array_key_exists($location_id, $locations)) {
        $error = "Please select a valid location";
    } else {
        // Check if slot is available for this location
        $check_sql = "SELECT id FROM bookings 
                      WHERE location_id = ?
                      AND booking_date = ? 
                      AND start_time = ?
                      AND status != 'expired'";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "iss", $location_id, $booking_date, $start_time);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "This time slot is already booked at the selected location! Please choose another time.";
        } else {
            // Generate unique OTP
            do {
                $otp = sprintf("%04d", rand(0, 9999));
                $otp_sql = "SELECT id FROM bookings WHERE otp = ?";
                $otp_stmt = mysqli_prepare($conn, $otp_sql);
                mysqli_stmt_bind_param($otp_stmt, "s", $otp);
                mysqli_stmt_execute($otp_stmt);
                mysqli_stmt_store_result($otp_stmt);
                $otp_exists = mysqli_stmt_num_rows($otp_stmt) > 0;
                mysqli_stmt_close($otp_stmt);
            } while ($otp_exists);
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Get location name
                $loc_name_sql = "SELECT name FROM locations WHERE id = ?";
                $loc_name_stmt = mysqli_prepare($conn, $loc_name_sql);
                mysqli_stmt_bind_param($loc_name_stmt, "i", $location_id);
                mysqli_stmt_execute($loc_name_stmt);
                $loc_name_result = mysqli_stmt_get_result($loc_name_stmt);
                $location = mysqli_fetch_assoc($loc_name_result);
                $location_name = $location['name'];
                mysqli_stmt_close($loc_name_stmt);
                
                // Insert booking with 1.5 hours duration
                $sql = "INSERT INTO bookings (user_id, location_id, booking_date, start_time, end_time, otp, amount, duration) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iissssdi", $user_id, $location_id, $booking_date, 
                                      $start_time, $end_time, $otp, WASHING_PRICE, BOOKING_DURATION);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                
                // Deduct from balance
                $update_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "di", WASHING_PRICE, $user_id);
                mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);
                
                // Record transaction
                $trans_sql = "INSERT INTO transactions (user_id, amount, type, note) 
                             VALUES (?, ?, 'debit', ?)";
                $trans_note = "Washing machine booking at $location_name - OTP: $otp";
                $trans_stmt = mysqli_prepare($conn, $trans_sql);
                mysqli_stmt_bind_param($trans_stmt, "ids", $user_id, WASHING_PRICE, $trans_note);
                mysqli_stmt_execute($trans_stmt);
                mysqli_stmt_close($trans_stmt);
                
                // Update daily income
                $income_sql = "INSERT INTO daily_income (date, washing_income) 
                              VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE washing_income = washing_income + ?";
                $income_stmt = mysqli_prepare($conn, $income_sql);
                mysqli_stmt_bind_param($income_stmt, "sdd", $booking_date, WASHING_PRICE, WASHING_PRICE);
                mysqli_stmt_execute($income_stmt);
                mysqli_stmt_close($income_stmt);
                
                // Commit transaction
                mysqli_commit($conn);
                
                $success = "Booking successful! Your OTP: <strong>$otp</strong>. " .
                          "This OTP will work at $location_name during the first hour of your booked time.";
                
                // Clear form
                $_POST = array();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Booking failed. Please try again. Error: " . $e->getMessage();
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}

// Generate available dates (today + next 5 days as per PDF)
$dates = [];
for ($i = 0; $i < 6; $i++) { // 6 days (0-5) for today + next 5
    $date = date('Y-m-d', strtotime("+$i days"));
    $dates[$date] = date('d M, Y', strtotime($date));
}

// Get booked slots for selected location and date
$selected_date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
$selected_location = isset($_POST['location_id']) ? $_POST['location_id'] : key($locations);

$booked_slots_sql = "SELECT start_time FROM bookings 
                     WHERE location_id = ? 
                     AND booking_date = ? 
                     AND status != 'expired'";
$booked_stmt = mysqli_prepare($conn, $booked_slots_sql);
mysqli_stmt_bind_param($booked_stmt, "is", $selected_location, $selected_date);
mysqli_stmt_execute($booked_stmt);
$booked_result = mysqli_stmt_get_result($booked_stmt);
$booked_slots = [];
while ($row = mysqli_fetch_assoc($booked_result)) {
    $booked_slots[] = $row['start_time'];
}
mysqli_stmt_close($booked_stmt);

// Generate available time slots (8 AM to 10 PM, 1.5 hour slots)
$current_hour = date('H');
$available_slots = [];

// For today, start from next available slot (current time + 1 hour)
$start_hour = ($selected_date == date('Y-m-d')) ? max(8, $current_hour + 1) : 8;

for ($h = $start_hour; $h <= 22; $h++) {
    // Only allow slots that end before 11:30 PM
    if (($h * 60) + BOOKING_DURATION <= (22 * 60) + 30) {
        $slot_time = sprintf("%02d:00:00", $h);
        $end_slot_time = date('h:i A', strtotime($slot_time) + (BOOKING_DURATION * 60));
        $slot_display = date('h:i A', strtotime($slot_time)) . ' - ' . $end_slot_time;
        $available_slots[$slot_time] = $slot_display;
    }
}

// Close connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Slot - <?php echo SYSTEM_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .slot-duration {
        background: #e3f2fd;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.85rem;
        color: #1976d2;
        display: inline-block;
        margin-left: 10px;
    }
    .location-selector {
        margin-bottom: 25px;
    }
    .location-options {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
    .location-option {
        flex: 1;
        min-width: 200px;
    }
    .location-card {
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        background: white;
    }
    .location-card:hover {
        border-color: #4361ee;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .location-card.selected {
        border-color: #4361ee;
        background: #eef2ff;
    }
    .location-icon {
        font-size: 2rem;
        color: #4361ee;
        margin-bottom: 10px;
    }
    .location-name {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    .location-desc {
        font-size: 0.9rem;
        color: #666;
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
                        <i class="fas fa-washing-machine"></i>
                    </div>
                    <h1><?php echo SYSTEM_NAME; ?></h1>
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
            </div>
        </header>

        <main class="booking-main">
            <div class="booking-container">
                <!-- Left Side - Booking Form -->
                <div class="booking-form-section">
                    <div class="section-header">
                        <h2><i class="fas fa-calendar-alt"></i> Book Washing Slot</h2>
                        <p>Select location, date and time for washing</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="booking-form" id="bookingForm">
                        <!-- Location Selection -->
                        <div class="form-group location-selector">
                            <label>
                                <i class="fas fa-map-marker-alt"></i> Select Location:
                            </label>
                            <div class="location-options">
                                <?php foreach ($locations as $loc_id => $loc_name): ?>
                                <label class="location-option">
                                    <input type="radio" name="location_id" value="<?php echo $loc_id; ?>" 
                                           <?php echo ($selected_location == $loc_id) ? 'checked' : ''; ?> required>
                                    <div class="location-card <?php echo ($selected_location == $loc_id) ? 'selected' : ''; ?>">
                                        <div class="location-icon">
                                            <i class="fas fa-tshirt"></i>
                                        </div>
                                        <div class="location-name"><?php echo htmlspecialchars($loc_name); ?></div>
                                        <div class="location-desc">Washing Machine</div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Date Selection -->
                        <div class="form-group">
                            <label>
                                <i class="far fa-calendar"></i> Select Date:
                            </label>
                            <div class="date-options">
                                <?php foreach ($dates as $date_value => $date_label): 
                                    $is_today = $date_value == date('Y-m-d');
                                ?>
                                <label class="date-option">
                                    <input type="radio" name="date" value="<?php echo $date_value; ?>" 
                                           <?php echo ($selected_date == $date_value || $is_today) ? 'checked' : ''; ?> 
                                           class="date-input"
                                           data-date="<?php echo $date_value; ?>"
                                           required>
                                    <div class="date-box <?php echo $is_today ? 'today' : ''; ?>">
                                        <div class="date-day"><?php echo date('d', strtotime($date_value)); ?></div>
                                        <div class="date-month"><?php echo date('M', strtotime($date_value)); ?></div>
                                        <div class="date-year"><?php echo date('Y', strtotime($date_value)); ?></div>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Time Slot Selection -->
                        <div class="form-group">
                            <label>
                                <i class="far fa-clock"></i> Select Time Slot 
                                <span class="slot-duration">1.5 hours</span>:
                            </label>
                            <select name="time" class="time-select" required>
                                <option value="">-- Choose a time --</option>
                                <?php foreach ($available_slots as $time_value => $time_label): 
                                    $is_booked = in_array($time_value, $booked_slots);
                                ?>
                                <option value="<?php echo $time_value; ?>" 
                                    <?php echo $is_booked ? 'disabled' : ''; ?>
                                    <?php echo (isset($_POST['time']) && $_POST['time'] == $time_value) ? 'selected' : ''; ?>>
                                    <?php echo $time_label; ?>
                                    <?php echo $is_booked ? ' (Booked)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($available_slots) == 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i>
                                No available slots for this date. Please select another date.
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Booking Summary -->
                        <div class="booking-summary">
                            <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
                            <div class="summary-details">
                                <div class="summary-item">
                                    <span class="label">Service:</span>
                                    <span class="value">Washing Machine</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Duration:</span>
                                    <span class="value">1.5 hours</span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Price per slot:</span>
                                    <span class="value"><?php echo formatCurrency(WASHING_PRICE); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Your balance:</span>
                                    <span class="value <?php echo ($balance < WASHING_PRICE) ? 'insufficient' : 'sufficient'; ?>">
                                        <?php echo formatCurrency($balance); ?>
                                        <?php if ($balance < WASHING_PRICE): ?>
                                            <span class="warning-text"> (Insufficient)</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="summary-item total">
                                    <span class="label">Total to pay:</span>
                                    <span class="value"><?php echo formatCurrency(WASHING_PRICE); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="terms-box">
                            <h4><i class="fas fa-info-circle"></i> Important Notes:</h4>
                            <ul>
                                <li>Each booking is for <strong>1.5 hours (90 minutes)</strong></li>
                                <li>OTP will work only during <strong>first hour</strong> of booking</li>
                                <li>Booking cannot be cancelled or rescheduled</li>
                                <li>OTP is unique and can be used only once</li>
                                <li>Please arrive on time for your slot</li>
                            </ul>
                        </div>

                        <button type="submit" name="book_slot" class="btn btn-primary btn-book" 
                                <?php echo ($balance < WASHING_PRICE) ? 'disabled' : ''; ?>>
                            <i class="fas fa-calendar-check"></i> 
                            Confirm Booking for <?php echo formatCurrency(WASHING_PRICE); ?>
                            <?php if ($balance < WASHING_PRICE): ?>
                                <span class="insufficient-text">(Insufficient Balance)</span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="form-footer">
                            <a href="recharge.php" class="btn btn-secondary">
                                <i class="fas fa-wallet"></i> Recharge Balance
                            </a>
                            <a href="dashboard.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Right Side - Slot Visualization -->
                <div class="slots-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-bar"></i> Slot Availability</h2>
                        <div class="date-display">
                            <i class="far fa-calendar"></i>
                            <span id="selectedDateDisplay"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>

                    <!-- Location Info -->
                    <div class="location-info-card">
                        <h3 id="selectedLocationName">
                            <?php echo isset($locations[$selected_location]) ? htmlspecialchars($locations[$selected_location]) : 'Select Location'; ?>
                        </h3>
                        <p class="machine-status">
                            <i class="fas fa-circle" style="color: #10b981;"></i> 
                            <span id="availableCount">
                                <?php echo count($available_slots) - count($booked_slots); ?>
                            </span> slots available out of <?php echo count($available_slots); ?>
                        </p>
                    </div>

                    <!-- Timeline -->
                    <div class="slots-timeline">
                        <div class="timeline-header">
                            <span class="time-label">Time</span>
                            <span class="status-label">Status</span>
                        </div>
                        <?php 
                        // Create 1.5 hour slots from 8 AM to 10 PM
                        $timeline_slots = [];
                        for ($h = 8; $h <= 22; $h++) {
                            if (($h * 60) + BOOKING_DURATION <= (22 * 60) + 30) {
                                $slot_time = sprintf("%02d:00:00", $h);
                                $end_display = date('h:i A', strtotime($slot_time) + (BOOKING_DURATION * 60));
                                $timeline_slots[$slot_time] = date('h:i A', strtotime($slot_time)) . ' - ' . $end_display;
                            }
                        }
                        
                        foreach ($timeline_slots as $time_value => $time_label): 
                            $is_booked = in_array($time_value, $booked_slots);
                            $is_past = ($selected_date == date('Y-m-d')) && (strtotime($time_value) < time());
                            $is_current = ($selected_date == date('Y-m-d')) && 
                                         (strtotime($time_value) <= time() && 
                                          time() < strtotime($time_value) + (BOOKING_DURATION * 60));
                        ?>
                        <div class="time-slot-item <?php echo $is_booked ? 'booked' : ($is_past ? 'past' : ($is_current ? 'current' : 'available')); ?>"
                             data-time="<?php echo $time_value; ?>">
                            <div class="slot-time-display">
                                <i class="fas fa-clock"></i>
                                <?php echo $time_label; ?>
                            </div>
                            <div class="slot-status-indicator">
                                <?php if ($is_booked): ?>
                                    <span class="status booked">
                                        <i class="fas fa-times-circle"></i> Booked
                                    </span>
                                <?php elseif ($is_past): ?>
                                    <span class="status past">
                                        <i class="fas fa-history"></i> Past
                                    </span>
                                <?php elseif ($is_current): ?>
                                    <span class="status current">
                                        <i class="fas fa-play-circle"></i> In Progress
                                    </span>
                                <?php else: ?>
                                    <span class="status available">
                                        <i class="fas fa-check-circle"></i> Available
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Legend -->
                    <div class="slots-legend">
                        <div class="legend-header">
                            <h4><i class="fas fa-key"></i> Color Guide</h4>
                        </div>
                        <div class="legend-items">
                            <div class="legend-item">
                                <span class="legend-color available"></span>
                                <span class="legend-text">Available</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color booked"></span>
                                <span class="legend-text">Booked</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color current"></span>
                                <span class="legend-text">Current</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color past"></span>
                                <span class="legend-text">Past</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Auto-select time slot when clicking on timeline
    document.querySelectorAll('.time-slot-item.available').forEach(slot => {
        slot.addEventListener('click', function() {
            const timeValue = this.getAttribute('data-time');
            const timeSelect = document.querySelector('select[name="time"]');
            
            // Enable and select the corresponding option
            Array.from(timeSelect.options).forEach(option => {
                if (option.value === timeValue) {
                    option.selected = true;
                    option.disabled = false;
                }
            });
            
            // Highlight selected slot
            document.querySelectorAll('.time-slot-item').forEach(s => {
                s.classList.remove('selected');
            });
            this.classList.add('selected');
            
            // Trigger change event
            timeSelect.dispatchEvent(new Event('change'));
        });
    });
    
    // Update date display
    document.querySelectorAll('.date-input').forEach(input => {
        input.addEventListener('change', function() {
            const date = this.getAttribute('data-date');
            const dateObj = new Date(date);
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('selectedDateDisplay').textContent = 
                dateObj.toLocaleDateString('en-US', options);
            
            // Reload page with selected date (simplified - in real app would be AJAX)
            // This is just for demo, in production use AJAX
            window.location.href = `?date=${date}&location=${<?php echo $selected_location; ?>}`;
        });
    });
    
    // Update location display
    document.querySelectorAll('input[name="location_id"]').forEach(input => {
        input.addEventListener('change', function() {
            const locationCard = this.parentElement.querySelector('.location-card');
            const locationName = locationCard.querySelector('.location-name').textContent;
            document.getElementById('selectedLocationName').textContent = locationName;
            
            // Remove selected class from all location cards
            document.querySelectorAll('.location-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to current card
            locationCard.classList.add('selected');
            
            // Reload page with selected location (simplified - in real app would be AJAX)
            window.location.href = `?location=${this.value}&date=${<?php echo "'$selected_date'" ?>}`;
        });
    });
    
    // Initialize display
    document.addEventListener('DOMContentLoaded', function() {
        const selectedDate = "<?php echo $selected_date; ?>";
        const dateObj = new Date(selectedDate);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('selectedDateDisplay').textContent = 
            dateObj.toLocaleDateString('en-US', options);
            
        const selectedLocation = "<?php echo isset($locations[$selected_location]) ? htmlspecialchars($locations[$selected_location]) : ''; ?>";
        if (selectedLocation) {
            document.getElementById('selectedLocationName').textContent = selectedLocation;
        }
    });
    </script>
</body>
</html>