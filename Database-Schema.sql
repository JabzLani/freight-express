-- Add tracking table
CREATE TABLE shipment_tracking (
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
CREATE TABLE tracking_history (
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

-- Drivers table
CREATE TABLE drivers (
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

-- Email notifications log
CREATE TABLE email_logs (
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
CREATE TABLE notification_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    email_notifications BOOLEAN DEFAULT true,
    sms_notifications BOOLEAN DEFAULT false,
    push_notifications BOOLEAN DEFAULT true,
    tracking_updates BOOLEAN DEFAULT true,
    promotional_emails BOOLEAN DEFAULT true,
    FOREIGN KEY (user_id) REFERENCES users(id)
);