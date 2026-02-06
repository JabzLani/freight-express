<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once 'email.php';

class OrderAPI
{
    private $conn;
    private $email;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->email = new EmailNotification();
    }

    public function createOrder($data)
    {
        try {
            // Basic validation
            if (!isset($data['user_id']) || !isset($data['pickup']) || !isset($data['delivery'])) {
                throw new Exception("Missing required fields");
            }

            // Generate tracking number
            $trackingNumber = 'TRK' . strtoupper(uniqid());

            // Insert into orders
            $query = "INSERT INTO orders 
                     (user_id, tracking_number, pickup_address, delivery_address, service_type, weight, size, total, payment_status, estimated_delivery) 
                     VALUES 
                     (:user_id, :tracking, :pickup, :delivery, :service, :weight, :size, :total, 'pending', :estimated)";

            $stmt = $this->conn->prepare($query);

            // Mock calculated values
            $total = $data['total'] ?? 99.99;
            $estimated = date('Y-m-d H:i:s', strtotime('+3 days'));

            $stmt->bindParam(':user_id', $data['user_id']);
            $stmt->bindParam(':tracking', $trackingNumber);
            $stmt->bindParam(':pickup', $data['pickup']);
            $stmt->bindParam(':delivery', $data['delivery']);
            $stmt->bindParam(':service', $data['service_type']);
            $stmt->bindParam(':weight', $data['weight']);
            $stmt->bindParam(':size', $data['size']);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':estimated', $estimated);

            if ($stmt->execute()) {
                $orderId = $this->conn->lastInsertId();

                // Initialize tracking
                $this->initializeTracking($orderId, $trackingNumber, $data['user_id']);

                // Send email
                $orderData = array_merge($data, [
                    'order_id' => $orderId,
                    'tracking_number' => $trackingNumber,
                    'estimated_delivery' => $estimated,
                    'payment_status' => 'pending'
                ]);

                $this->email->sendOrderConfirmation($orderData);

                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'tracking_number' => $trackingNumber,
                    'message' => 'Order created successfully'
                ];
            }

            return ['success' => false, 'message' => 'Failed to create order'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function initializeTracking($orderId, $trackingNumber, $userId)
    {
        $query = "INSERT INTO shipment_tracking (tracking_number, order_id, user_id, status) VALUES (:tracking, :order_id, :user_id, 'label_created')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':tracking', $trackingNumber);
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
    }
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api = new OrderAPI();
    $data = json_decode(file_get_contents('php://input'), true);
    echo json_encode($api->createOrder($data));
}
?>