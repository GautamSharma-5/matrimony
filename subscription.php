<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = connectDB();

// Get user's current subscription
$stmt = $conn->prepare("
    SELECT * FROM subscriptions 
    WHERE user_id = ? AND end_date > NOW() 
    ORDER BY end_date DESC LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_subscription = $stmt->get_result()->fetch_assoc();

closeDB($conn);

// Subscription plans
$plans = [
    'basic' => [
        'name' => 'Basic Plan',
        'price' => 999,
        'duration' => '3 months',
        'features' => [
            'View contact details',
            'Send unlimited messages',
            'Advanced search filters',
            '3 months validity'
        ]
    ],
    'premium' => [
        'name' => 'Premium Plan',
        'price' => 1999,
        'duration' => '6 months',
        'features' => [
            'All Basic Plan features',
            'Profile highlight',
            'Priority customer support',
            'View verified profiles only',
            '6 months validity'
        ]
    ],
    'vip' => [
        'name' => 'VIP Plan',
        'price' => 3999,
        'duration' => '12 months',
        'features' => [
            'All Premium Plan features',
            'Personal relationship manager',
            'Background verification service',
            'Premium profile badge',
            '12 months validity'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <?php if ($current_subscription): ?>
            <div class="alert alert-info mb-4">
                <h5>Current Subscription</h5>
                <p>You are currently on the <?php echo $current_subscription['plan_name']; ?> plan.</p>
                <p>Valid until: <?php echo date('F j, Y', strtotime($current_subscription['end_date'])); ?></p>
            </div>
        <?php endif; ?>

        <h2 class="text-center mb-5">Choose Your Perfect Plan</h2>

        <div class="row">
            <?php foreach ($plans as $plan_id => $plan): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header text-center py-3">
                            <h4 class="mb-0"><?php echo $plan['name']; ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h2 class="mb-0">₹<?php echo number_format($plan['price']); ?></h2>
                                <p class="text-muted"><?php echo $plan['duration']; ?></p>
                            </div>
                            <ul class="list-unstyled">
                                <?php foreach ($plan['features'] as $feature): ?>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        <?php echo $feature; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-footer text-center py-3">
                            <button class="btn btn-primary btn-lg w-100" 
                                    onclick="startPayment('<?php echo $plan_id; ?>', <?php echo $plan['price']; ?>, '<?php echo $plan['name']; ?>')">
                                Subscribe Now
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function startPayment(planId, amount, planName) {
            fetch('api/create-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    plan_id: planId,
                    amount: amount
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.order_id) {
                    const options = {
                        key: 'YOUR_RAZORPAY_KEY_ID', // Replace with your Razorpay Key ID
                        amount: amount * 100, // Amount in paise
                        currency: 'INR',
                        name: 'Indian Matrimony',
                        description: planName,
                        order_id: data.order_id,
                        handler: function(response) {
                            // Handle successful payment
                            verifyPayment(response, planId);
                        },
                        prefill: {
                            name: '<?php echo isset($user_profile["first_name"]) ? $user_profile["first_name"] . " " . $user_profile["last_name"] : ""; ?>',
                            email: '<?php echo isset($user_email) ? $user_email : ""; ?>'
                        },
                        theme: {
                            color: '#FF4B6E'
                        }
                    };
                    
                    const rzp = new Razorpay(options);
                    rzp.open();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to create order. Please try again.');
            });
        }

        function verifyPayment(response, planId) {
            fetch('api/verify-payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature,
                    plan_id: planId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Payment successful! Your subscription has been activated.');
                    window.location.reload();
                } else {
                    alert('Payment verification failed. Please contact support.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment verification failed. Please contact support.');
            });
        }
    </script>
</body>
</html>
