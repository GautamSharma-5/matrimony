<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['admin_role'])) {
    header("Location: /login.php");
    exit();
}

$conn = connectDB();
$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = intval($_POST['user_id']);
    
    // Don't allow deleting admin users
    $stmt = mysqli_prepare($conn, "SELECT * FROM admin_users WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $is_admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    
    if ($is_admin) {
        $error = "Cannot delete admin users";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Delete user and all related data (cascading will handle related tables)
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            
            mysqli_commit($conn);
            $success = "User deleted successfully";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Handle user verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $user_id = intval($_POST['user_id']);
    
    mysqli_begin_transaction($conn);
    try {
        // Update user verification status
        $stmt = mysqli_prepare($conn, "
            UPDATE users 
            SET is_verified = 1, verification_status = 'verified'
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        
        // Update any pending verification requests
        $stmt = mysqli_prepare($conn, "
            UPDATE verification_requests 
            SET status = 'approved', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND status = 'pending'
        ");
        mysqli_stmt_bind_param($stmt, "ii", $admin_id, $user_id);
        mysqli_stmt_execute($stmt);
        
        mysqli_commit($conn);
        $success = "User verified successfully";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Error verifying user: " . $e->getMessage();
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Search filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query
$where_clause = "WHERE u.id != ?"; // Exclude current admin
$params = [$admin_id];
$types = "i";

if ($search) {
    $where_clause .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= "ssss";
}

if ($filter === 'verified') {
    $where_clause .= " AND u.is_verified = 1";
} elseif ($filter === 'unverified') {
    $where_clause .= " AND u.is_verified = 0";
}

// Get total users count
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) as total 
    FROM users u 
    JOIN profiles p ON u.id = p.user_id 
    $where_clause
");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total_users = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
$total_pages = ceil($total_users / $items_per_page);

// Get users
$stmt = mysqli_prepare($conn, "
    SELECT u.*, p.first_name, p.last_name, p.gender, p.city, p.state,
           (SELECT COUNT(*) FROM verification_requests WHERE user_id = u.id) as verification_attempts
    FROM users u
    JOIN profiles p ON u.id = p.user_id
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $items_per_page;
$params[] = $offset;
$types .= "ii";
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$users = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3">
                <?php include 'includes/sidebar.php'; ?>
            </div>
            
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">Manage Users</h2>
                    <span class="badge bg-primary fs-5"><?php echo $total_users; ?> Users</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-6">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email or phone" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <select name="filter" class="form-select">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="unverified" <?php echo $filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Search</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($user['email']); ?>
                                                <?php if ($user['email_verified']): ?>
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['phone']); ?>
                                                <?php if ($user['phone_verified']): ?>
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['city'] . ', ' . $user['state']); ?></td>
                                            <td>
                                                <?php if ($user['is_verified']): ?>
                                                    <span class="badge bg-success">Verified</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        Pending
                                                        <?php if ($user['verification_attempts'] > 0): ?>
                                                            (<?php echo $user['verification_attempts']; ?> attempts)
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (!$user['is_verified']): ?>
                                                        <form method="post" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to verify this user?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="action" value="verify">
                                                            <button type="submit" class="btn btn-sm btn-success" title="Verify User">
                                                                <i class="bi bi-check-lg"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo $filter; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
