<?php
header('Content-Type: application/json');
require_once '../config/database.php';

class CalculatorAPI
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function calculateQuote($data)
    {
        // Validate input
        $required = ['pickup', 'delivery', 'size', 'weight', 'delivery_type'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['error' => "Missing field: $field"];
            }
        }

        // Calculate distance (simplified - use Google Maps Distance Matrix in production)
        $distance = $this->calculateDistance($data['pickup'], $data['delivery']);

        // Get pricing from database
        $price = $this->calculatePrice($distance, $data);

        return [
            'success' => true,
            'quote' => [
                'distance' => $distance,
                'price' => $price,
                'quote_id' => uniqid('QUOTE_')
            ]
        ];
    }

    private function calculateDistance($origin, $destination)
    {
        // Mock calculation - implement Google Maps API
        return rand(10, 1000); // Random distance for demo
    }

    private function calculatePrice($distance, $data)
    {
        $basePrice = 10;
        $perKm = 1.5;
        $weightSurcharge = $data['weight'] * 0.5;

        // Get size multiplier
        $sizeMultiplier = $this->getSizeMultiplier($data['size']);

        // Get delivery type multiplier
        $deliveryMultiplier = $this->getDeliveryMultiplier($data['delivery_type']);

        return round(($basePrice + ($distance * $perKm) + $weightSurcharge)
            * $sizeMultiplier * $deliveryMultiplier, 2);
    }

    private function getSizeMultiplier($size)
    {
        $multipliers = [
            'small' => 1.0,
            'medium' => 1.5,
            'large' => 2.0,
            'pallet' => 3.0
        ];
        return $multipliers[$size] ?? 1.0;
    }

    private function getDeliveryMultiplier($type)
    {
        $multipliers = [
            'standard' => 1.0,
            'express' => 1.5,
            'same_day' => 2.0
        ];
        return $multipliers[$type] ?? 1.0;
    }
}

// Handle request
$api = new CalculatorAPI();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $api->calculateQuote($data);
    echo json_encode($result);
}
?>