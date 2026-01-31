-- washing_machine_complete.sql
-- Complete Database for WashMate System
-- Version: 2.0 (Final)

-- --------------------------------------------------------
-- Database Creation
-- --------------------------------------------------------
DROP DATABASE IF EXISTS washing_machine;
CREATE DATABASE washing_machine 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE washing_machine;

-- --------------------------------------------------------
-- Table 1: Users
-- --------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(11) UNIQUE NOT NULL,
    pin VARCHAR(4) NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile (mobile),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 2: Locations
-- --------------------------------------------------------
CREATE TABLE locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    machine_count INT DEFAULT 1,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 3: Bookings (1.5 hours duration)
-- --------------------------------------------------------
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    location_id INT NOT NULL,
    booking_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    otp VARCHAR(4) NOT NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    amount DECIMAL(10,2) DEFAULT 35.00,
    duration INT DEFAULT 90, -- 1.5 hours in minutes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (location_id, booking_date, start_time),
    UNIQUE KEY unique_otp (otp),
    INDEX idx_user_id (user_id),
    INDEX idx_location_id (location_id),
    INDEX idx_booking_date (booking_date),
    INDEX idx_status (status),
    INDEX idx_otp (otp),
    INDEX idx_created (created_at),
    CONSTRAINT chk_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 4: Transactions
-- --------------------------------------------------------
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    note TEXT,
    service_type ENUM('washing', 'tea', 'coffee', 'recharge') DEFAULT 'washing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_service_type (service_type),
    INDEX idx_created (created_at),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 5: Daily Income
-- --------------------------------------------------------
CREATE TABLE daily_income (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    washing_income DECIMAL(10,2) DEFAULT 0.00,
    tea_income DECIMAL(10,2) DEFAULT 0.00,
    coffee_income DECIMAL(10,2) DEFAULT 0.00,
    recharge_income DECIMAL(10,2) DEFAULT 0.00,
    total_income DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 6: Recharge Requests
-- --------------------------------------------------------
CREATE TABLE recharge_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    user_mobile VARCHAR(11) NOT NULL,
    payment_method ENUM('bkash', 'nagad', 'rocket') NOT NULL,
    payment_number VARCHAR(11) NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    approved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_transaction (transaction_id, payment_method),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created (created_at),
    INDEX idx_processed (processed_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 7: User Cards (RFID/NFC)
-- --------------------------------------------------------
CREATE TABLE user_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_number VARCHAR(20) UNIQUE NOT NULL,
    card_type ENUM('rfid', 'nfc') DEFAULT 'rfid',
    status ENUM('active', 'blocked', 'lost') DEFAULT 'active',
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_card_number (card_number),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 8: Tea/Coffee Transactions
-- --------------------------------------------------------
CREATE TABLE tea_coffee_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_id INT NULL,
    service_type ENUM('tea', 'coffee') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    otp VARCHAR(4) NULL,
    machine_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (card_id) REFERENCES user_cards(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_service_type (service_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 9: Service Prices
-- --------------------------------------------------------
CREATE TABLE service_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_type ENUM('washing', 'tea', 'coffee') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    effective_from DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_service_type (service_type),
    INDEX idx_status (status),
    INDEX idx_effective (effective_from)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Table 10: System Logs
-- --------------------------------------------------------
CREATE TABLE system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_created (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- --------------------------------------------------------
-- Sample Data Insertion
-- --------------------------------------------------------

-- Insert test users
INSERT INTO users (name, mobile, pin, balance, last_login) VALUES
('Test User 1', '01712345678', '1234', 500.00, NOW()),
('Test User 2', '01898765432', '5678', 350.00, NOW()),
('Demo User', '01987654321', '4321', 1000.00, NULL),
('Admin User', '01711111111', '9999', 0.00, NOW());

-- Insert locations
INSERT INTO locations (name, description, machine_count) VALUES
('Muktiqiddha Hall', 'Ground Floor, Near Cafeteria - Washing Machine Area', 2),
('Amor Akushe Hall', 'First Floor, Room 101 - Washing Machine Area', 2),
('Main Building', 'Basement Floor - Central Washing Station', 3);

-- Insert service prices
INSERT INTO service_prices (service_type, amount, effective_from) VALUES
('washing', 35.00, CURDATE()),
('tea', 10.00, CURDATE()),
('coffee', 15.00, CURDATE());

-- Insert sample bookings (1.5 hours)
INSERT INTO bookings (user_id, location_id, booking_date, start_time, end_time, otp, status, duration) VALUES
(1, 1, CURDATE(), '14:00:00', '15:30:00', '1234', 'active', 90),
(1, 1, CURDATE(), '16:00:00', '17:30:00', '5678', 'used', 90),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '11:30:00', '9012', 'active', 90),
(3, 1, CURDATE(), '20:00:00', '21:30:00', '7890', 'expired', 90);

-- Insert sample transactions
INSERT INTO transactions (user_id, amount, type, note, service_type) VALUES
(1, 500.00, 'credit', 'Initial balance', 'recharge'),
(1, 35.00, 'debit', 'Washing machine booking - OTP: 1234', 'washing'),
(2, 350.00, 'credit', 'Initial balance', 'recharge'),
(1, 10.00, 'debit', 'Tea purchase - Card tap', 'tea'),
(2, 15.00, 'debit', 'Coffee purchase - OTP: 1111', 'coffee');

-- Insert today's income
INSERT INTO daily_income (date, washing_income, tea_income, coffee_income, total_income) VALUES 
(CURDATE(), 105.00, 10.00, 15.00, 130.00);

-- Insert sample recharge requests
INSERT INTO recharge_requests (user_id, user_name, user_mobile, payment_method, payment_number, transaction_id, status, amount, admin_note) VALUES
(1, 'Test User 1', '01712345678', 'bkash', '01712345678', 'TRX123456', 'pending', 0.00, NULL),
(2, 'Test User 2', '01898765432', 'nagad', '01898765432', 'TRX654321', 'pending', 0.00, NULL);

-- Insert sample user cards
INSERT INTO user_cards (user_id, card_number, card_type, status) VALUES
(1, '5500112233', 'rfid', 'active'),
(2, '6600223344', 'nfc', 'active'),
(3, '7700334455', 'rfid', 'blocked');

-- Insert sample tea/coffee transactions
INSERT INTO tea_coffee_transactions (user_id, card_id, service_type, amount, otp) VALUES
(1, 1, 'tea', 10.00, 'T123'),
(2, NULL, 'coffee', 15.00, 'C456'),
(1, 1, 'coffee', 15.00, NULL),
(2, 2, 'tea', 10.00, NULL);

-- Insert system logs
INSERT INTO system_logs (user_id, action_type, description, ip_address) VALUES
(1, 'LOGIN', 'User logged in successfully', '192.168.1.100'),
(1, 'BOOKING', 'Booked washing slot at Muktiqiddha Hall', '192.168.1.100'),
(2, 'RECHARGE', 'Submitted recharge request via bKash', '192.168.1.101'),
(NULL, 'SYSTEM', 'Daily cleanup job completed', '127.0.0.1');

-- --------------------------------------------------------
-- Triggers
-- --------------------------------------------------------
DELIMITER //

-- Trigger 1: Update daily income when booking is created
CREATE TRIGGER after_booking_insert 
AFTER INSERT ON bookings 
FOR EACH ROW 
BEGIN
    INSERT INTO daily_income (date, washing_income, total_income) 
    VALUES (NEW.booking_date, NEW.amount, NEW.amount)
    ON DUPLICATE KEY UPDATE 
        washing_income = washing_income + NEW.amount,
        total_income = total_income + NEW.amount;
END//

-- Trigger 2: Update user balance when recharge is approved
CREATE TRIGGER after_recharge_approve 
AFTER UPDATE ON recharge_requests 
FOR EACH ROW 
BEGIN
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        -- Update user balance
        UPDATE users 
        SET balance = balance + NEW.amount 
        WHERE id = NEW.user_id;
        
        -- Record transaction
        INSERT INTO transactions (user_id, amount, type, note, service_type) 
        VALUES (
            NEW.user_id, 
            NEW.amount, 
            'credit', 
            CONCAT('Recharge approved via ', UPPER(NEW.payment_method), ' - TRX: ', NEW.transaction_id),
            'recharge'
        );
        
        -- Update daily income
        INSERT INTO daily_income (date, recharge_income, total_income) 
        VALUES (DATE(NEW.processed_at), NEW.amount, NEW.amount)
        ON DUPLICATE KEY UPDATE 
            recharge_income = recharge_income + NEW.amount,
            total_income = total_income + NEW.amount;
    END IF;
END//

-- Trigger 3: Log tea/coffee transactions
CREATE TRIGGER after_tea_coffee_purchase 
AFTER INSERT ON tea_coffee_transactions 
FOR EACH ROW 
BEGIN
    -- Deduct from user balance
    UPDATE users 
    SET balance = balance - NEW.amount 
    WHERE id = NEW.user_id;
    
    -- Record transaction
    INSERT INTO transactions (user_id, amount, type, note, service_type) 
    VALUES (
        NEW.user_id, 
        NEW.amount, 
        'debit', 
        CONCAT(UPPER(NEW.service_type), ' purchase', 
               IF(NEW.card_id IS NOT NULL, ' via card', ' via OTP'),
               IF(NEW.otp IS NOT NULL, CONCAT(' - OTP: ', NEW.otp), '')),
        NEW.service_type
    );
    
    -- Update daily income
    IF NEW.service_type = 'tea' THEN
        INSERT INTO daily_income (date, tea_income, total_income) 
        VALUES (DATE(NEW.created_at), NEW.amount, NEW.amount)
        ON DUPLICATE KEY UPDATE 
            tea_income = tea_income + NEW.amount,
            total_income = total_income + NEW.amount;
    ELSE
        INSERT INTO daily_income (date, coffee_income, total_income) 
        VALUES (DATE(NEW.created_at), NEW.amount, NEW.amount)
        ON DUPLICATE KEY UPDATE 
            coffee_income = coffee_income + NEW.amount,
            total_income = total_income + NEW.amount;
    END IF;
    
    -- Update card last used time if card was used
    IF NEW.card_id IS NOT NULL THEN
        UPDATE user_cards 
        SET last_used_at = NEW.created_at 
        WHERE id = NEW.card_id;
    END IF;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Events for Automatic Cleanup
-- --------------------------------------------------------
DELIMITER //

-- Event 1: Clean expired bookings every 30 minutes
CREATE EVENT IF NOT EXISTS clean_expired_bookings
ON SCHEDULE EVERY 30 MINUTE
DO
BEGIN
    UPDATE bookings 
    SET status = 'expired' 
    WHERE status = 'active' 
    AND (
        booking_date < CURDATE() 
        OR (booking_date = CURDATE() AND end_time < CURTIME())
    );
END//

-- Event 2: Clean old recharge history daily at 3 AM
CREATE EVENT IF NOT EXISTS clean_old_recharge_history
ON SCHEDULE EVERY 1 DAY
STARTS CONCAT(CURDATE() + INTERVAL 1 DAY, ' 03:00:00')
DO
BEGIN
    DELETE FROM recharge_requests 
    WHERE status IN ('approved', 'rejected') 
    AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
END//

DELIMITER ;

-- --------------------------------------------------------
-- Views
-- --------------------------------------------------------

-- View 1: Active bookings with location info
CREATE VIEW vw_active_bookings AS
SELECT 
    b.*,
    u.name as user_name,
    u.mobile as user_mobile,
    l.name as location_name
FROM bookings b
JOIN users u ON b.user_id = u.id
JOIN locations l ON b.location_id = l.id
WHERE b.status = 'active'
AND b.booking_date >= CURDATE()
ORDER BY b.booking_date, b.start_time;

-- View 2: User summary
CREATE VIEW vw_user_summary AS
SELECT 
    u.*,
    (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as total_bookings,
    (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND status = 'active') as active_bookings,
    (SELECT COUNT(*) FROM user_cards WHERE user_id = u.id AND status = 'active') as active_cards
FROM users u;

-- View 3: Card users
CREATE VIEW vw_card_users AS
SELECT 
    u.id as user_id,
    u.name,
    u.mobile,
    u.balance,
    uc.card_number,
    uc.card_type,
    uc.registered_at,
    uc.last_used_at,
    uc.status as card_status
FROM users u
JOIN user_cards uc ON u.id = uc.user_id
WHERE uc.status = 'active';

-- --------------------------------------------------------
-- Stored Procedures
-- --------------------------------------------------------
DELIMITER //

-- Procedure 1: Get user statistics
CREATE PROCEDURE GetUserStatistics(IN user_mobile VARCHAR(11))
BEGIN
    SELECT 
        u.*,
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.status = 'active' THEN 1 ELSE 0 END) as active_bookings,
        SUM(CASE WHEN b.status = 'used' THEN 1 ELSE 0 END) as used_bookings,
        (SELECT card_number FROM user_cards WHERE user_id = u.id AND status = 'active' LIMIT 1) as card_number
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    WHERE u.mobile = user_mobile
    GROUP BY u.id;
END//

-- Procedure 2: Get daily income report
CREATE PROCEDURE GetDailyIncomeReport(IN report_date DATE)
BEGIN
    SELECT 
        report_date as date,
        COALESCE(SUM(washing_income), 0) as washing_income,
        COALESCE(SUM(tea_income), 0) as tea_income,
        COALESCE(SUM(coffee_income), 0) as coffee_income,
        COALESCE(SUM(total_income), 0) as total_income
    FROM daily_income
    WHERE date = report_date;
END//

DELIMITER ;

-- --------------------------------------------------------
-- Success Message
-- --------------------------------------------------------
SELECT '✅ Database washing_machine created successfully!' as message;
SELECT '✅ 10 tables created' as message;
SELECT '✅ Sample data inserted' as message;
SELECT '✅ 3 triggers created' as message;
SELECT '✅ 2 events created' as message;
SELECT '✅ 3 views created' as message;
SELECT '✅ 2 stored procedures created' as message;
SELECT '✅ System ready for use!' as message;