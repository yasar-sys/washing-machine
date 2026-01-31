<?php
// test_db.php - Database Connection Test

echo "<h2>Testing Database Connection</h2>";

// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'washing_machine';

echo "Attempting to connect to database: <strong>$dbname</strong><br><br>";

// Try to connect
$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    echo "<div class='error'>✗ Connection failed: " . mysqli_connect_error() . "</div>";
    
    // Try to connect without database
    $conn2 = mysqli_connect($host, $user, $pass);
    if ($conn2) {
        echo "<br>Trying to check if database exists...<br>";
        $result = mysqli_query($conn2, "SHOW DATABASES LIKE '$dbname'");
        if (mysqli_num_rows($result) > 0) {
            echo "✓ Database '$dbname' exists but connection failed<br>";
            echo "Possible issues:<br>";
            echo "1. Wrong username/password<br>";
            echo "2. Database permission issues<br>";
        } else {
            echo "✗ Database '$dbname' does not exist<br>";
            echo "Please create the database first using:<br>";
            echo "<code>CREATE DATABASE washing_machine;</code>";
        }
        mysqli_close($conn2);
    }
} else {
    echo "<div class='success'>✓ Connected successfully to database: $dbname</div><br>";
    
    // Check tables
    echo "<h3>Checking Tables:</h3>";
    $tables = ['users', 'bookings', 'transactions', 'daily_income'];
    
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "✓ Table '$table' exists<br>";
            
            // Count rows
            $count_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM $table");
            $count = mysqli_fetch_assoc($count_result)['count'];
            echo "&nbsp;&nbsp;&nbsp;&nbsp;Rows: $count<br>";
        } else {
            echo "✗ Table '$table' does not exist<br>";
        }
    }
    
    // Test sample query
    echo "<br><h3>Sample Query Test:</h3>";
    $test_query = "SELECT name, mobile, balance FROM users LIMIT 2";
    $result = mysqli_query($conn, $test_query);
    
    if ($result) {
        echo "✓ Query executed successfully<br>";
        echo "Sample users:<br>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "- {$row['name']} ({$row['mobile']}) - Balance: ৳{$row['balance']}<br>";
        }
    } else {
        echo "✗ Query failed: " . mysqli_error($conn) . "<br>";
    }
    
    mysqli_close($conn);
}

echo "<br><hr>";
echo "<a href='index.php'>Back to Home</a> | ";
echo "<a href='setup.php'>Run Setup</a>";
?>