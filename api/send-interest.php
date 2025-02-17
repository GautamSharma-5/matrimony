<?php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
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

$sender_id = $_SESSION['user_id'];
$receiver_id = filter_var($_POST['user_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);

if (!$receiver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid receiver ID']);
    exit();
}

try {
    $conn = connectDB();
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    // Check if a conversation already exists
    $stmt = mysqli_prepare($conn, "
        SELECT id FROM chat_conversations 
        WHERE (user1_id = ? AND user2_id = ?) 
        OR (user1_id = ? AND user2_id = ?)
    ");
    mysqli_stmt_bind_param($stmt, "iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($conversation = mysqli_fetch_assoc($result)) {
        $conversation_id = $conversation['id'];
    } else {
        // Create new conversation
        $stmt = mysqli_prepare($conn, "
            INSERT INTO chat_conversations (user1_id, user2_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, "ii", $sender_id, $receiver_id);
        mysqli_stmt_execute($stmt);
        $conversation_id = mysqli_insert_id($conn);
    }
    
    // Get receiver's name
    $stmt = mysqli_prepare($conn, "
        SELECT first_name FROM profiles WHERE user_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "i", $receiver_id);
    mysqli_stmt_execute($stmt);
    $receiver = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    // Send interest message
    $interest_message = "Hi " . $receiver['first_name'] . ", I liked your profile and would like to connect with you!";
    $stmt = mysqli_prepare($conn, "
        INSERT INTO chat_messages (conversation_id, sender_id, message, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($stmt, "iis", $conversation_id, $sender_id, $interest_message);
    mysqli_stmt_execute($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Interest sent successfully',
        'conversation_id' => $conversation_id
    ]);
    
} catch (Exception $e) {
    if (isset($conn)) {
        mysqli_rollback($conn);
    }
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send interest: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        closeDB($conn);
    }
}
