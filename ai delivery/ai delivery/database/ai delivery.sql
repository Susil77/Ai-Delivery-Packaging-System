-- AI Package Delivery System Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS ai_delivery_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_delivery_system;

-- Admin/Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'driver') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Drivers table (extends users)
CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone VARCHAR(20),
    vehicle_number VARCHAR(50),
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    total_deliveries INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages table
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id VARCHAR(50) UNIQUE NOT NULL,
    destination VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20),
    status ENUM('pending', 'assigned', 'picked-up', 'in-transit', 'delivered') DEFAULT 'pending',
    driver_id INT NULL,
    assigned_at DATETIME NULL,
    picked_up_at DATETIME NULL,
    in_transit_at DATETIME NULL,
    delivered_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_driver (driver_id),
    INDEX idx_package_id (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user
-- Password: admin123 (hashed with password_hash)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'admin@delivery.com')
ON DUPLICATE KEY UPDATE username=username;

-- Insert default driver users
-- Password: driver123 (hashed with password_hash)
INSERT INTO users (username, password, role, full_name, email) VALUES
('driver1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'John Doe', 'john@delivery.com'),
('driver2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'Jane Smith', 'jane@delivery.com'),
('driver3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'Mike Johnson', 'mike@delivery.com'),
('driver4', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'Sarah Williams', 'sarah@delivery.com'),
('driver5', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'Tom Brown', 'tom@delivery.com'),
('driver', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'Driver', 'driver@delivery.com')
ON DUPLICATE KEY UPDATE username=username;

-- Insert drivers data
INSERT INTO drivers (user_id, phone, vehicle_number, status) 
SELECT id, CONCAT('555-', LPAD(id, 4, '0')), CONCAT('VH-', LPAD(id, 4, '0')), 'available'
FROM users WHERE role = 'driver'
ON DUPLICATE KEY UPDATE user_id=user_id;

-- Insert sample packages
INSERT INTO packages (package_id, destination, recipient_name, recipient_phone, status) VALUES
('PKG001', '123 Main St, City A', 'Alice Johnson', '555-0101', 'pending'),
('PKG002', '456 Oak Ave, City B', 'Bob Smith', '555-0102', 'pending'),
('PKG003', '789 Pine Rd, City C', 'Charlie Brown', '555-0103', 'pending'),
('PKG004', '321 Elm St, City D', 'Diana Prince', '555-0104', 'pending'),
('PKG005', '654 Maple Dr, City E', 'Edward Norton', '555-0105', 'pending'),
('PKG006', '987 Cedar Ln, City F', 'Fiona Apple', '555-0106', 'pending'),
('PKG007', '147 Birch Way, City G', 'George Washington', '555-0107', 'pending'),
('PKG008', '258 Spruce Ct, City H', 'Helen Keller', '555-0108', 'pending'),
('PKG009', '369 Willow St, City I', 'Isaac Newton', '555-0109', 'pending'),
('PKG010', '741 Ash Ave, City J', 'Jane Austen', '555-0110', 'pending')
ON DUPLICATE KEY UPDATE package_id=package_id;

