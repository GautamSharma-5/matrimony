<?php
require_once '../config/database.php';
require_once '../vendor/autoload.php';
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$razorpay_payment_id = $data['razorpay_payment_id'] ?? '';
$razorpay_order_id = $data['razorpay_order_id'] ?? '';
$razorpay_signature = $data['razorpay_signature'] ?? '';
$plan_id = $data['plan_id'] ?? '';

if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature) || empty($plan_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

try {
    // Initialize Razorpay API
    $api = new Api('YOUR_RAZORPAY_KEY_ID', 'YOUR_RAZORPAY_KEY_SECRET');

    // Verify signature
    $attributes = [
        'razorpay_payment_id' => $razorpay_payment_id,
        'razorpay_order_id' => $razorpay_order_id,
        'razorpay_signature' => $razorpay_signature
    ];
    $api->utility->verifyPaymentSignature($attributes);

    // Get subscription details based on plan_id
    $plan_details = [
        'basic' => ['duration' => 3, 'name' => 'Basic Plan'],
        'premium' => ['duration' => 6, 'name' => 'Premium Plan'],
        'vip' => ['duration' => 12, 'name' => 'VIP Plan']
    ];

    $plan = $plan_details[$plan_id] ?? null;
    if (!$plan) {
        throw new Exception('Invalid plan');
    }

    $conn = connectDB();

    // Begin transaction
    $conn->begin_transaction();

    try {
        // Insert subscription record
        $stmt = $conn->prepare("
            INSERT INTO subscriptions (
                user_id, 
                plan_name, 
                start_date, 
                end_date, 
                payment_status,
                razorpay_payment_id,
                razorpay_order_id
            ) VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MONTH), 'completed', ?, ?)
        ");

        $stmt->bind_param(
            "isiss",
            $_SESSION['user_id'],
            $plan['name'],
            $plan['duration'],
            $razorpay_payment_id,
            $razorpay_order_id
        );
        $stmt->execute();

        // Update user's premium status
        $stmt = $conn->prepare("UPDATE users SET is_premium = 1 WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    } finally {
        closeDB($conn);
    }
} catch (SignatureVerificationError $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment signature']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
