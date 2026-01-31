<?php
// setup.php
// Database Auto Setup File

echo "<h2>Setting up Washing Machine Booking System</h2>";
echo "<p>Database: washing_machine</p>";

$host = 'localhost';
$user = 'root';
$pass = ''; // আপনার পাসওয়ার্ড থাকলে এখানে দিন

// Step 1: Connect to MySQL
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Step 2: Create Database
$sql = "CREATE DATABASE IF NOT EXISTS washing_machine 
        CHARACTER SET utf8mb4 
        COLLATE utf8mb4_unicode_ci";
        
if (mysqli_query($conn, $sql)) {
    echo "✓ Database 'washing_machine' created<br>";
} else {
    echo "✗ Error creating database: " . mysqli_error($conn) . "<br>";
}

// Step 3: Select Database
mysqli_select_db($conn, 'washing_machine');

// Step 4: Create Tables
$queries = [];

// Users table
$queries[] = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(11) UNIQUE NOT NULL,
    pin VARCHAR(4) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Bookings table
$queries[] = "CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    otp VARCHAR(4) UNIQUE NOT NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    amount DECIMAL(10,2) DEFAULT 35.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Transactions table
$queries[] = "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Daily income table
$queries[] = "CREATE TABLE IF NOT EXISTS daily_income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    total_income DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Execute all queries
foreach ($queries as $index => $query) {
    if (mysqli_query($conn, $query)) {
        $table_names = ['users', 'bookings', 'transactions', 'daily_income'];
        echo "✓ Table '{$table_names[$index]}' created<br>";
    } else {
        echo "✗ Error creating table: " . mysqli_error($conn) . "<br>";
    }
}

// Step 5: Insert Sample Data
echo "<br><h3>Inserting Sample Data...</h3>";

// Check if users already exist
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$row = mysqli_fetch_assoc($check);

if ($row['count'] == 0) {
    // Insert test users
    mysqli_query($conn, "INSERT INTO users (name, mobile, pin, balance) VALUES
                        ('Test User 1', '01712345678', '1234', 500.00),
                        ('Test User 2', '01898765432', '5678', 350.00)");
    echo "✓ Test users added<br>";
    
    // Insert sample bookings
    mysqli_query($conn, "INSERT INTO bookings (user_id, booking_date, start_time, end_time, otp, status) VALUES
                        (1, CURDATE(), '14:00:00', '15:00:00', '1234', 'active'),
                        (1, CURDATE(), '16:00:00', '17:00:00', '5678', 'used')");
    echo "✓ Sample bookings added<br>";
    
    // Insert sample transactions
    mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, note) VALUES
                        (1, 35.00, 'debit', 'Washing machine booking'),
                        (1, 500.00, 'credit', 'Initial balance'),
                        (2, 100.00, 'credit', 'Admin topup')");
    echo "✓ Sample transactions added<br>";
} else {
    echo "✓ Data already exists<br>";
}

// Step 6: Create Indexes
$indexes = [
    "CREATE INDEX IF NOT EXISTS idx_users_mobile ON users(mobile)",
    "CREATE INDEX IF NOT EXISTS idx_bookings_date ON bookings(booking_date)",
    "CREATE INDEX IF NOT EXISTS idx_bookings_otp ON bookings(otp)",
    "CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status)",
    "CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_daily_income_date ON daily_income(date)"
];

foreach ($indexes as $index_query) {
    mysqli_query($conn, $index_query);
}
echo "✓ Indexes created<br>";

echo "<br><div class='success'><h3>✅ Setup Complete!</h3>";
echo "<p>Database: <strong>washing_machine</strong> successfully created and configured.</p>";
echo "<p><a href='index.php'>Go to Home Page</a></p>";
echo "<p><a href='admin.php'>Go to Admin Panel</a></p></div>";

mysqli_close($conn);
?>