<?php
require_once 'config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Redirect if no user ID provided
if (!isset($_GET['id'])) {
    header('Location: messages.php');
    exit();
}

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$other_user_id = intval($_GET['id']);
$error_message = '';
$success_message = '';

// Get other user's details
$stmt = mysqli_prepare($conn, "
    SELECT 
        p.*,
        u.is_premium,
        u.show_contact
    FROM profiles p
    JOIN users u ON u.id = p.user_id
    WHERE u.id = ? AND u.is_verified = 1
");
mysqli_stmt_bind_param($stmt, "i", $other_user_id);
mysqli_stmt_execute($stmt);
$other_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$other_user) {
    header('Location: messages.php');
    exit();
}

// Get or create conversation
$stmt = mysqli_prepare($conn, "
    SELECT id FROM chat_conversations 
    WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
");
mysqli_stmt_bind_param($stmt, "iiii", $user_id, $other_user_id, $other_user_id, $user_id);
mysqli_stmt_execute($stmt);
$conversation = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$conversation) {
    $stmt = mysqli_prepare($conn, "INSERT INTO chat_conversations (user1_id, user2_id) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $other_user_id);
    mysqli_stmt_execute($stmt);
    $conversation_id = mysqli_insert_id($conn);
} else {
    $conversation_id = $conversation['id'];
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO chat_messages (conversation_id, sender_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $conversation_id, $user_id, $message);
        mysqli_stmt_execute($stmt);
    }
}

// Mark messages as read
$stmt = mysqli_prepare($conn, "
    UPDATE chat_messages 
    SET is_read = 1 
    WHERE conversation_id = ? AND sender_id = ? AND is_read = 0
");
mysqli_stmt_bind_param($stmt, "ii", $conversation_id, $other_user_id);
mysqli_stmt_execute($stmt);

// Get messages
$stmt = mysqli_prepare($conn, "
    SELECT 
        m.*,
        DATE_FORMAT(m.created_at, '%h:%i %p') as time,
        DATE(m.created_at) as date
    FROM chat_messages m
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
mysqli_stmt_bind_param($stmt, "i", $conversation_id);
mysqli_stmt_execute($stmt);
$messages = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

closeDB($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?php echo htmlspecialchars($other_user['first_name']); ?> - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .chat-container {
            height: calc(100vh - 300px);
            overflow-y: auto;
        }
        .message-bubble {
            max-width: 70%;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
        }
        .message-sent {
            background-color: #007bff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 0.25rem;
        }
        .message-received {
            background-color: #e9ecef;
            margin-right: auto;
            border-bottom-left-radius: 0.25rem;
        }
        .message-time {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        .message-date {
            text-align: center;
            margin: 1rem 0;
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="card shadow-sm">
            <!-- Chat Header -->
            <div class="card-header bg-white">
                <div class="d-flex align-items-center">
                    <a href="messages.php" class="btn btn-link text-dark p-0 me-3">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="position-relative">
                        <?php if ($other_user['profile_pic']): ?>
                            <img src="<?php echo htmlspecialchars($other_user['profile_pic']); ?>" 
                                 class="rounded-circle" alt="Profile Picture"
                                 style="width: 40px; height: 40px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                 style="width: 40px; height: 40px;">
                                <i class="bi bi-person text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($other_user['is_premium']): ?>
                            <i class="bi bi-patch-check-fill text-primary position-absolute bottom-0 end-0"></i>
                        <?php endif; ?>
                    </div>
                    <div class="ms-3">
                        <h6 class="mb-0">
                            <?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>
                        </h6>
                        <small class="text-muted">
                            <?php echo htmlspecialchars($other_user['occupation']); ?> • 
                            <?php echo htmlspecialchars($other_user['city']); ?>
                        </small>
                    </div>
                    <div class="ms-auto">
                        <a href="view-profile.php?id=<?php echo $other_user_id; ?>" class="btn btn-outline-primary btn-sm">
                            View Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="chat-container p-4" id="chatContainer">
                <?php 
                $current_date = null;
                foreach ($messages as $message): 
                    // Show date if it changes
                    if ($current_date !== $message['date']):
                        $current_date = $message['date'];
                        $message_date = strtotime($current_date);
                        $today = strtotime('today');
                        $yesterday = strtotime('yesterday');
                        
                        if ($message_date === $today) {
                            $date_text = 'Today';
                        } elseif ($message_date === $yesterday) {
                            $date_text = 'Yesterday';
                        } else {
                            $date_text = date('F j, Y', $message_date);
                        }
                ?>
                    <div class="message-date"><?php echo $date_text; ?></div>
                <?php endif; ?>

                <div class="message-bubble <?php echo $message['sender_id'] === $user_id ? 'message-sent' : 'message-received'; ?>">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                    <div class="message-time <?php echo $message['sender_id'] === $user_id ? 'text-white-50' : 'text-muted'; ?>">
                        <?php echo $message['time']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Message Input -->
            <div class="card-footer bg-white">
                <form method="POST" id="messageForm" class="mb-0">
                    <div class="input-group">
                        <textarea class="form-control" name="message" placeholder="Type a message..." rows="1" required></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll to bottom of chat
        const chatContainer = document.getElementById('chatContainer');
        chatContainer.scrollTop = chatContainer.scrollHeight;

        // Auto-expand textarea
        const textarea = document.querySelector('textarea');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Submit form on Ctrl/Cmd + Enter
        textarea.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                document.getElementById('messageForm').submit();
            }
        });
    </script>
</body>
</html>
