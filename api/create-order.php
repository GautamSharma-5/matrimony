<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php';
use Razorpay\Api\Api;

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$plan_id = $data['plan_id'] ?? '';
$amount = $data['amount'] ?? 0;

if (empty($plan_id) || empty($amount)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    // Initialize Razorpay API
    $api = new Api('YOUR_RAZORPAY_KEY_ID', 'YOUR_RAZORPAY_KEY_SECRET');

    // Create order
    $order = $api->order->create([
        'amount' => $amount * 100, // Convert to paise
        'currency' => 'INR',
        'payment_capture' => 1,
        'notes' => [
            'plan_id' => $plan_id,
            'user_id' => $_SESSION['user_id']
        ]
    ]);

    echo json_encode([
        'order_id' => $order->id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
