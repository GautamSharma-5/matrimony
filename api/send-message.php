<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$connection_id = filter_var($_POST['connection_id'], FILTER_SANITIZE_NUMBER_INT);
$message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit();
}

$conn = connectDB();

// Verify that the user is part of this connection
$stmt = $conn->prepare("
    SELECT id FROM connections 
    WHERE id = ? AND (sender_id = ? OR receiver_id = ?) 
    AND status = 'accepted'
");
$stmt->bind_param("iii", $connection_id, $user_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    $stmt->close();
    closeDB($conn);
    exit();
}

// Insert the message
$stmt = $conn->prepare("
    INSERT INTO messages (connection_id, sender_id, message) 
    VALUES (?, ?, ?)
");
$stmt->bind_param("iis", $connection_id, $user_id, $message);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message_id' => $stmt->insert_id
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}

$stmt->close();
closeDB($conn);
