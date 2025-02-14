<?php
require_once 'config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conn = connectDB();
$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get all conversations for the current user
$stmt = mysqli_prepare($conn, "
    SELECT 
        c.*,
        CASE 
            WHEN c.user1_id = ? THEN c.user2_id
            ELSE c.user1_id
        END as other_user_id,
        p.first_name,
        p.last_name,
        p.profile_pic,
        u.is_premium,
        (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.id AND sender_id != ? AND is_read = 0) as unread_count,
        (SELECT message FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM chat_messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM chat_conversations c
    JOIN users u ON (CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END) = u.id
    JOIN profiles p ON u.id = p.user_id
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY last_message_time DESC
");
mysqli_stmt_bind_param($stmt, "iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
mysqli_stmt_execute($stmt);
$conversations = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

closeDB($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Indian Matrimony</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .conversation-list {
            height: calc(100vh - 200px);
            overflow-y: auto;
        }
        .conversation-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        .conversation-item.active {
            background-color: #e9ecef;
        }
        .unread-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        .last-message {
            font-size: 0.9rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Messages</h5>
                    </div>
                    <div class="conversation-list">
                        <?php if (empty($conversations)): ?>
                            <div class="text-center p-4">
                                <i class="bi bi-chat-dots text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No conversations yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <a href="chat.php?id=<?php echo $conv['other_user_id']; ?>" class="text-decoration-none">
                                    <div class="conversation-item p-3 border-bottom">
                                        <div class="d-flex align-items-center">
                                            <div class="position-relative">
                                                <?php if ($conv['profile_pic']): ?>
                                                    <img src="<?php echo htmlspecialchars($conv['profile_pic']); ?>" 
                                                         class="rounded-circle" alt="Profile Picture"
                                                         style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                                         style="width: 50px; height: 50px;">
                                                        <i class="bi bi-person text-secondary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($conv['is_premium']): ?>
                                                    <i class="bi bi-patch-check-fill text-primary position-absolute bottom-0 end-0"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-3 flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0">
                                                        <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                                    </h6>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="badge bg-primary rounded-pill unread-badge">
                                                            <?php echo $conv['unread_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="last-message mb-0">
                                                    <?php echo htmlspecialchars($conv['last_message'] ?? ''); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <i class="bi bi-chat-text text-muted" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">Select a conversation</h5>
                            <p class="text-muted">Choose a conversation from the list to start chatting</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
