-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    tracking_number VARCHAR(50) UNIQUE,
    pickup_address TEXT,
    delivery_address TEXT,
    service_type VARCHAR(50),
    weight DECIMAL(10,2),
    size VARCHAR(20),
    total DECIMAL(10,2),
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    estimated_delivery DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Drivers table
CREATE TABLE IF NOT EXISTS drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    vehicle_type VARCHAR(50),
    vehicle_number VARCHAR(50),
    status ENUM('available', 'busy', 'offline') DEFAULT 'available',
    current_lat DECIMAL(10,8),
    current_lng DECIMAL(11,8)
);

-- Add tracking table
CREATE TABLE IF NOT EXISTS shipment_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracking_number VARCHAR(50) UNIQUE NOT NULL,
    order_id INT,
    user_id INT,
    status ENUM('label_created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'delayed') DEFAULT 'label_created',
    current_lat DECIMAL(10,8),
    current_lng DECIMAL(11,8),
    current_location VARCHAR(255),
    estimated_delivery DATETIME,
    actual_delivery DATETIME,
    driver_id INT,
    vehicle_info VARCHAR(100),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
);

-- Tracking history table
CREATE TABLE IF NOT EXISTS tracking_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tracking_number VARCHAR(50),
    status VARCHAR(50),
    location VARCHAR(255),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    description TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tracking_number) REFERENCES shipment_tracking(tracking_number)
);

-- Email notifications log
CREATE TABLE IF NOT EXISTS email_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email_type VARCHAR(50),
    recipient_email VARCHAR(100),
    tracking_number VARCHAR(50),
    subject VARCHAR(255),
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_message TEXT
);

-- User notification preferences
CREATE TABLE IF NOT EXISTS notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    email_notifications BOOLEAN DEFAULT true,
    sms_notifications BOOLEAN DEFAULT false,
    push_notifications BOOLEAN DEFAULT true,
    tracking_updates BOOLEAN DEFAULT true,
    promotional_emails BOOLEAN DEFAULT true,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
