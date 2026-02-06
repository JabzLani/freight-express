<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Check if Composer dependencies are installed
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
} else {
    // Fallback if PHPMailer is missing
    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        class MockMailer
        {
            public $isSMTP;
            public $Host;
            public $SMTPAuth;
            public $Username;
            public $Password;
            public $SMTPSecure;
            public $Port;
            public $Subject;
            public $Body;
            public $ErrorInfo = 'PHPMailer not installed';

            public function __construct($exceptions = null)
            {
            }
            public function isSMTP()
            {
            }
            public function setFrom($address, $name = '', $auto = true)
            {
            }
            public function addAddress($address, $name = '')
            {
            }
            public function isHTML($isHtml = true)
            {
            }
            public function send()
            {
                return false;
            }
        }

        // Alias the namespace
        class_alias('MockMailer', 'PHPMailer\\PHPMailer\\PHPMailer');
        class_alias('Exception', 'PHPMailer\\PHPMailer\\Exception');
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailNotification
{
    private $conn;
    private $mail;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->mail = new PHPMailer(true);
        $this->configureMailer();
    }

    private function configureMailer()
    {
        // SMTP Configuration
        $this->mail->isSMTP();
        $this->mail->Host = 'smtp.gmail.com';
        $this->mail->SMTPAuth = true;
        $this->mail->Username = 'notifications@freightexpress.com';
        $this->mail->Password = 'your-smtp-password';
        $this->mail->SMTPSecure = 'tls';
        $this->mail->Port = 587;
        $this->mail->setFrom('notifications@freightexpress.com', 'FreightExpress');
    }

    public function sendOrderConfirmation($orderData)
    {
        try {
            $user = $this->getUserById($orderData['user_id']);

            $this->mail->addAddress($user['email'], $user['name']);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Order Confirmation - FreightExpress';

            $template = $this->getOrderConfirmationTemplate($orderData, $user);
            $this->mail->Body = $template;

            $this->mail->send();

            // Log email sent
            $this->logEmail('order_confirmation', $user['email'], $orderData['tracking_number'], 'Order Confirmation');

            return ['success' => true, 'message' => 'Order confirmation sent'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $this->mail->ErrorInfo];
        }
    }

    public function sendTrackingUpdate($trackingData)
    {
        try {
            $user = $this->getUserById($trackingData['user_id']);

            $this->mail->addAddress($user['email'], $user['name']);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Shipment Update - Tracking #' . $trackingData['tracking_number'];

            $template = $this->getTrackingUpdateTemplate($trackingData, $user);
            $this->mail->Body = $template;

            $this->mail->send();

            // Also send SMS if user has phone
            if ($user['phone']) {
                $this->sendSMS($user['phone'], $trackingData);
            }

            return ['success' => true, 'message' => 'Tracking update sent'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $this->mail->ErrorInfo];
        }
    }

    public function sendWelcomeEmail($userData)
    {
        try {
            $this->mail->addAddress($userData['email'], $userData['name']);
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Welcome to FreightExpress!';

            $template = $this->getWelcomeTemplate($userData);
            $this->mail->Body = $template;

            $this->mail->send();

            return ['success' => true, 'message' => 'Welcome email sent'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $this->mail->ErrorInfo];
        }
    }

    private function getOrderConfirmationTemplate($order, $user)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 5px; }
                .tracking-number { background: #2563eb; color: white; padding: 10px; border-radius: 5px; text-align: center; font-size: 24px; margin: 20px 0; }
                .details { margin: 20px 0; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Order Confirmed!</h1>
                    <p>Thank you for choosing FreightExpress</p>
                </div>
                
                <div class='content'>
                    <p>Dear {$user['name']},</p>
                    <p>Your order has been confirmed and is being processed.</p>
                    
                    <div class='tracking-number'>
                        TRACKING: {$order['tracking_number']}
                    </div>
                    
                    <div class='details'>
                        <div class='detail-row'>
                            <span>Order ID:</span>
                            <span><strong>{$order['order_id']}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span>Order Date:</span>
                            <span>" . date('F j, Y', strtotime($order['created_at'])) . "</span>
                        </div>
                        <div class='detail-row'>
                            <span>Total Amount:</span>
                            <span><strong>\${$order['total']}</strong></span>
                        </div>
                        <div class='detail-row'>
                            <span>Payment Status:</span>
                            <span><strong>{$order['payment_status']}</strong></span>
                        </div>
                    </div>
                    
                    <h3>Shipment Details:</h3>
                    <ul>
                        <li><strong>Pickup:</strong> {$order['pickup_address']}</li>
                        <li><strong>Delivery:</strong> {$order['delivery_address']}</li>
                        <li><strong>Estimated Delivery:</strong> {$order['estimated_delivery']}</li>
                        <li><strong>Service Type:</strong> {$order['service_type']}</li>
                    </ul>
                    
                    <p>You can track your shipment at any time by visiting:</p>
                    <p><a href='https://freightexpress.com/track/{$order['tracking_number']}'>https://freightexpress.com/track/{$order['tracking_number']}</a></p>
                    
                    <p>If you have any questions, please contact our support team.</p>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " FreightExpress. All rights reserved.</p>
                    <p>This is an automated email, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getTrackingUpdateTemplate($tracking, $user)
    {
        $statusColors = [
            'picked_up' => '#4CAF50',
            'in_transit' => '#2196F3',
            'out_for_delivery' => '#FF9800',
            'delivered' => '#8BC34A',
            'delayed' => '#F44336'
        ];

        $statusText = [
            'picked_up' => 'Picked Up',
            'in_transit' => 'In Transit',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'delayed' => 'Delayed'
        ];

        $color = $statusColors[$tracking['status']] ?? '#666';
        $status = $statusText[$tracking['status']] ?? $tracking['status'];

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .status-badge { 
                    background: {$color}; 
                    color: white; 
                    padding: 10px 20px; 
                    border-radius: 5px; 
                    display: inline-block;
                    font-size: 18px;
                    margin: 10px 0;
                }
                .map-link { 
                    display: block; 
                    margin: 20px 0; 
                    padding: 15px;
                    background: #f0f8ff;
                    border-left: 4px solid #2563eb;
                    text-decoration: none;
                    color: #2563eb;
                }
                .timeline { margin: 30px 0; }
                .timeline-item { 
                    display: flex; 
                    margin: 15px 0;
                    padding-left: 20px;
                    border-left: 3px solid #2563eb;
                }
                .timeline-time { width: 100px; color: #666; }
                .timeline-content { flex: 1; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Shipment Update</h2>
                <p>Dear {$user['name']},</p>
                
                <div class='status-badge'>{$status}</div>
                
                <p>Your shipment <strong>{$tracking['tracking_number']}</strong> has been updated.</p>
                
                <div class='timeline'>
                    <h3>Recent Activity:</h3>
                    " . $this->generateTimelineHTML($tracking['history']) . "
                </div>
                
                <a href='https://freightexpress.com/track/{$tracking['tracking_number']}' class='map-link'>
                    <strong>📍 View Live Location on Map</strong><br>
                    Click here to see real-time tracking
                </a>
                
                <p><strong>Next Update:</strong> You'll receive another notification when the shipment status changes.</p>
                
                <p>Need help? <a href='https://freightexpress.com/contact'>Contact our support team</a></p>
                
                <div class='footer'>
                    <p>© " . date('Y') . " FreightExpress</p>
                    <p><small>To manage email preferences, visit your account settings.</small></p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function sendSMS($phone, $trackingData)
    {
        // Integrate with Twilio or similar SMS service
        $message = "FreightExpress Update: Your shipment {$trackingData['tracking_number']} is now {$trackingData['status']}. View tracking: https://freightexpress.com/track/{$trackingData['tracking_number']}";

        // Use Twilio API to send SMS
        // $twilio->messages->create($phone, ['from' => '+1234567890', 'body' => $message]);

        return true;
    }

    private function getWelcomeTemplate($user)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 5px; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                .btn { background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to FreightExpress!</h1>
                </div>
                <div class='content'>
                    <p>Hi {$user['name']},</p>
                    <p>Thank you for creating an account with FreightExpress. We're excited to have you on board.</p>
                    <p>You can now book shipments, track your packages, and manage your orders easily.</p>
                    <p style='text-align: center;'>
                        <a href='https://freightexpress.com/dashboard' class='btn'>Go to Dashboard</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " FreightExpress. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function generateTimelineHTML($history)
    {
        if (empty($history) || !is_array($history)) {
            return '<p>No history available.</p>';
        }

        $html = '';
        foreach ($history as $event) {
            $time = date('H:i', strtotime($event['timestamp']));
            $date = date('M j', strtotime($event['timestamp']));
            $status = ucfirst(str_replace('_', ' ', $event['status']));
            $location = !empty($event['location']) ? " - {$event['location']}" : "";
            $description = !empty($event['description']) ? $event['description'] : '';

            $html .= "
            <div class='timeline-item'>
                <div class='timeline-time'>
                    <div>{$time}</div>
                    <div style='font-size: 12px;'>{$date}</div>
                </div>
                <div class='timeline-content'>
                    <strong>{$status}</strong>{$location}
                    <div style='color: #666; font-size: 13px;'>{$description}</div>
                </div>
            </div>";
        }
        return $html;
    }

    private function getUserById($userId)
    {
        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function logEmail($type, $recipient, $trackingNumber = null, $subject = null, $status = 'sent')
    {
        try {
            $query = "INSERT INTO email_logs 
                    (email_type, recipient_email, tracking_number, subject, status) 
                    VALUES (:type, :recipient, :tracking, :subject, :status)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':recipient', $recipient);
            $stmt->bindParam(':tracking', $trackingNumber);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':status', $status);

            $stmt->execute();
        } catch (Exception $e) {
            // Log error silently so we don't break the flow
            error_log("Email logging failed: " . $e->getMessage());
        }
    }
}

// Handle email requests
$email = new EmailNotification();
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'order_confirmation':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($email->sendOrderConfirmation($data));
        break;

    case 'tracking_update':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($email->sendTrackingUpdate($data));
        break;

    case 'welcome':
        $data = json_decode(file_get_contents('php://input'), true);
        echo json_encode($email->sendWelcomeEmail($data));
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>