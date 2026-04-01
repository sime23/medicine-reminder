-- =====================================================
-- MEDICINE REMINDER WEB APPLICATION - DATABASE SCHEMA
-- =====================================================
-- Database: medicine_reminder
-- Description: Complete SQL setup for the Medicine Reminder App
-- Author: Senior Full-Stack Developer
-- Date: 2026-03-31
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS medicine
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE medicine;

-- =====================================================
-- TABLE: users
-- Description: Stores user account information and preferences
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    -- Notification preferences
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 0,
    push_notifications TINYINT(1) DEFAULT 1,
    reminder_time_before INT DEFAULT 15, -- minutes before dose
    -- Theme preferences
    theme VARCHAR(20) DEFAULT 'light',
    -- Security and metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: medications
-- Description: Stores medication information for each user
-- =====================================================
CREATE TABLE IF NOT EXISTS medications (
    medication_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    medication_name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200) DEFAULT NULL,
    brand_name VARCHAR(200) DEFAULT NULL,
    dosage_amount VARCHAR(100) NOT NULL, -- e.g., "500mg", "2 tablets"
    dosage_form ENUM('tablet', 'capsule', 'liquid', 'injection', 'inhaler', 'drops', 'patch', 'other') DEFAULT 'tablet',
    color VARCHAR(50) DEFAULT NULL, -- for visual identification
    shape VARCHAR(50) DEFAULT NULL,
    instructions TEXT DEFAULT NULL, -- special instructions
    prescribed_by VARCHAR(200) DEFAULT NULL, -- doctor name
    prescribed_date DATE DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL, -- NULL means ongoing
    total_quantity INT DEFAULT NULL,
    remaining_quantity INT DEFAULT NULL,
    refill_reminder TINYINT(1) DEFAULT 0,
    refill_threshold INT DEFAULT 7, -- days before running out
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_medications (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: schedules
-- Description: Stores dosing schedules for each medication
-- =====================================================
CREATE TABLE IF NOT EXISTS schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    medication_id INT NOT NULL,
    user_id INT NOT NULL,
    -- Frequency settings
    frequency_type ENUM('once', 'daily', 'weekly', 'custom', 'as_needed') NOT NULL DEFAULT 'daily',
    -- For 'once' - specific date and time
    specific_date DATE DEFAULT NULL,
    -- For 'daily' - multiple times per day
    times_per_day INT DEFAULT 1,
    -- For 'weekly' - specific days
    monday TINYINT(1) DEFAULT 0,
    tuesday TINYINT(1) DEFAULT 0,
    wednesday TINYINT(1) DEFAULT 0,
    thursday TINYINT(1) DEFAULT 0,
    friday TINYINT(1) DEFAULT 0,
    saturday TINYINT(1) DEFAULT 0,
    sunday TINYINT(1) DEFAULT 0,
    -- Time slots (up to 6 times per day)
    time_1 TIME DEFAULT NULL,
    time_2 TIME DEFAULT NULL,
    time_3 TIME DEFAULT NULL,
    time_4 TIME DEFAULT NULL,
    time_5 TIME DEFAULT NULL,
    time_6 TIME DEFAULT NULL,
    -- For 'custom' - interval in hours
    interval_hours INT DEFAULT NULL,
    last_taken TIMESTAMP NULL,
    next_due TIMESTAMP NULL,
    -- Schedule metadata
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (medication_id) REFERENCES medications(medication_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_schedules (user_id, is_active),
    INDEX idx_next_due (next_due)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: medication_logs
-- Description: Tracks taken, missed, or skipped doses
-- =====================================================
CREATE TABLE IF NOT EXISTS medication_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    medication_id INT NOT NULL,
    schedule_id INT DEFAULT NULL,
    scheduled_time TIMESTAMP NOT NULL,
    taken_time TIMESTAMP NULL,
    status ENUM('taken', 'missed', 'skipped', 'snoozed', 'pending') NOT NULL DEFAULT 'pending',
    -- Details when taken
    quantity_taken VARCHAR(50) DEFAULT NULL,
    taken_with_food TINYINT(1) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    -- Side effects tracking
    side_effects TEXT DEFAULT NULL,
    severity ENUM('mild', 'moderate', 'severe') DEFAULT NULL,
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(medication_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE SET NULL,
    INDEX idx_user_logs (user_id, scheduled_time),
    INDEX idx_status (status),
    INDEX idx_date_range (scheduled_time, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: notifications
-- Description: Stores notification history and pending alerts
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    medication_id INT NOT NULL,
    schedule_id INT DEFAULT NULL,
    log_id INT DEFAULT NULL,
    notification_type ENUM('reminder', 'overdue', 'refill', 'custom') NOT NULL DEFAULT 'reminder',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    scheduled_for TIMESTAMP NOT NULL,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    is_read TINYINT(1) DEFAULT 0,
    channel ENUM('app', 'email', 'sms', 'push') DEFAULT 'app',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    action_taken VARCHAR(50) DEFAULT NULL, -- 'taken', 'snoozed', 'dismissed'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (medication_id) REFERENCES medications(medication_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE SET NULL,
    FOREIGN KEY (log_id) REFERENCES medication_logs(log_id) ON DELETE SET NULL,
    INDEX idx_user_notifications (user_id, is_read, scheduled_for),
    INDEX idx_pending (scheduled_for, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TABLE: caregiver_access
-- Description: Allows caregivers to monitor patient's medications
-- =====================================================
CREATE TABLE IF NOT EXISTS caregiver_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    caregiver_id INT NOT NULL,
    access_level ENUM('view', 'manage') DEFAULT 'view',
    status ENUM('pending', 'active', 'revoked') DEFAULT 'pending',
    message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (caregiver_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_caregiver (patient_id, caregiver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT SAMPLE DATA (Optional - for testing)
-- =====================================================

-- Sample user (password: 'password123' - hashed with bcrypt)
-- INSERT INTO users (email, password_hash, first_name, last_name, phone, email_notifications, push_notifications)
-- VALUES ('demo@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '+1234567890', 1, 1);

-- =====================================================
-- STORED PROCEDURES (Optional - for advanced functionality)
-- =====================================================

DELIMITER //

-- Procedure to get upcoming doses for a user
CREATE PROCEDURE IF NOT EXISTS GetUpcomingDoses(IN p_user_id INT, IN p_hours_ahead INT)
BEGIN
    SELECT 
        m.medication_id,
        m.medication_name,
        m.dosage_amount,
        m.dosage_form,
        m.color,
        s.schedule_id,
        s.next_due,
        s.frequency_type,
        TIMESTAMPDIFF(MINUTE, NOW(), s.next_due) as minutes_until
    FROM schedules s
    JOIN medications m ON s.medication_id = m.medication_id
    WHERE s.user_id = p_user_id 
        AND s.is_active = 1
        AND m.is_active = 1
        AND s.next_due <= DATE_ADD(NOW(), INTERVAL p_hours_ahead HOUR)
        AND s.next_due >= NOW()
    ORDER BY s.next_due ASC;
END //

-- Procedure to get medication adherence statistics
CREATE PROCEDURE IF NOT EXISTS GetAdherenceStats(IN p_user_id INT, IN p_days INT)
BEGIN
    SELECT 
        COUNT(*) as total_doses,
        SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken_count,
        SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_count,
        SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped_count,
        ROUND(
            (SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 
            2
        ) as adherence_rate
    FROM medication_logs
    WHERE user_id = p_user_id 
        AND scheduled_time >= DATE_SUB(NOW(), INTERVAL p_days DAY);
END //

DELIMITER ;

-- =====================================================
-- VIEWS (Optional - for common queries)
-- =====================================================

-- View for today's medication schedule
CREATE OR REPLACE VIEW vw_today_schedule AS
SELECT 
    ml.log_id,
    ml.user_id,
    m.medication_id,
    m.medication_name,
    m.dosage_amount,
    m.dosage_form,
    m.color,
    m.instructions,
    ml.scheduled_time,
    ml.taken_time,
    ml.status,
    ml.notes,
    TIME(ml.scheduled_time) as schedule_time,
    CASE 
        WHEN ml.status = 'taken' THEN 'taken'
        WHEN ml.status = 'missed' THEN 'missed'
        WHEN ml.scheduled_time < NOW() AND ml.status = 'pending' THEN 'overdue'
        WHEN ml.scheduled_time <= DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND ml.status = 'pending' THEN 'upcoming'
        ELSE 'later'
    END as time_status
FROM medication_logs ml
JOIN medications m ON ml.medication_id = m.medication_id
WHERE DATE(ml.scheduled_time) = CURDATE()
ORDER BY ml.scheduled_time ASC;

-- =====================================================
-- END OF DATABASE SCHEMA
-- =====================================================
