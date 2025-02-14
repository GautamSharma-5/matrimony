<?php
require_once '../config/database.php';
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$conn = connectDB();
$admin_id = $_SESSION['user_id'];

// Check if user is an admin
$stmt = mysqli_prepare($conn, "SELECT * FROM admin_users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$admin) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
        $action = $_POST['action'];
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        if ($action === 'approve' || $action === 'reject') {
            $new_status = ($action === 'approve') ? 'verified' : 'rejected';
            
            // Begin transaction
            mysqli_begin_transaction($conn);
            
            try {
                // Update user verification status
                $stmt = mysqli_prepare($conn, "
                    UPDATE users 
                    SET verification_status = ?,
                        verification_notes = ?
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($stmt, "ssi", $new_status, $notes, $user_id);
                mysqli_stmt_execute($stmt);
                
                // Update verification request
                $stmt = mysqli_prepare($conn, "
                    UPDATE verification_requests 
                    SET status = ?,
                        admin_notes = ?,
                        reviewed_by = ?,
                        reviewed_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND status = 'pending'
                ");
                $request_status = ($action === 'approve') ? 'approved' : 'rejected';
                mysqli_stmt_bind_param($stmt, "ssii", $request_status, $notes, $admin_id, $user_id);
                mysqli_stmt_execute($stmt);
                
                mysqli_commit($conn);
                $success = "Profile has been " . ($action === 'approve' ? 'verified' : 'rejected') . " successfully.";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Error processing request: " . $e->getMessage();
            }
        }
    }
}

// Pagination settings
$items_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Get total number of requests
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM verification_requests WHERE status = 'pending'");
mysqli_stmt_execute($stmt);
$total_requests = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
$total_pages = ceil($total_requests / $items_per_page);

// Get pending verification requests with pagination
$stmt = mysqli_prepare($conn, "
    SELECT 
        vr.*,
        u.email,
        u.phone,
        u.email_verified,
        u.phone_verified,
        p.*,
        CONCAT(a.first_name, ' ', a.last_name) as admin_name,
        (SELECT COUNT(*) FROM verification_requests WHERE user_id = vr.user_id) as total_attempts
    FROM verification_requests vr
    JOIN users u ON vr.user_id = u.id
    JOIN profiles p ON u.id = p.user_id
    LEFT JOIN profiles a ON vr.reviewed_by = a.user_id
    WHERE vr.status = 'pending'
    ORDER BY vr.request_date ASC
    LIMIT ? OFFSET ?
");
mysqli_stmt_bind_param($stmt, "ii", $items_per_page, $offset);
mysqli_stmt_execute($stmt);
$requests = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

closeDB($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container-fluid py-5">
        <div class="row">
            <div class="col-md-3">
                <!-- Admin Sidebar -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Admin Menu</h5>
                        <div class="list-group list-group-flush">
                            <a href="dashboard.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                            <a href="verify-users.php" class="list-group-item list-group-item-action active">
                                <i class="bi bi-person-check me-2"></i> Verify Users
                            </a>
                            <a href="manage-users.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-people me-2"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="card-title mb-0">Pending Verification Requests</h4>
                            <span class="badge bg-primary"><?php echo $total_requests; ?> pending</span>
                        </div>
                        
                        <?php if (empty($requests)): ?>
                            <div class="alert alert-info">
                                No pending verification requests.
                            </div>
                        <?php else: ?>
                            <?php foreach ($requests as $request): ?>
                                <div class="card mb-4">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Request ID: #<?php echo htmlspecialchars($request['id']); ?></span>
                                            <span class="text-muted">
                                                Submitted: <?php echo date('M j, Y g:i A', strtotime($request['request_date'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <?php if ($request['profile_pic']): ?>
                                                    <img src="<?php echo htmlspecialchars($request['profile_pic']); ?>" 
                                                         class="img-fluid rounded" alt="Profile Picture">
                                                <?php else: ?>
                                                    <div class="bg-secondary text-white p-4 rounded text-center">
                                                        <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-9">
                                                <h5 class="mb-3">
                                                    <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                    <?php if ($request['total_attempts'] > 1): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <?php echo $request['total_attempts']; ?> attempts
                                                        </span>
                                                    <?php endif; ?>
                                                </h5>
                                                
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <ul class="list-unstyled">
                                                            <li><strong>Email:</strong> 
                                                                <?php echo htmlspecialchars($request['email']); ?>
                                                                <?php if ($request['email_verified']): ?>
                                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                                <?php endif; ?>
                                                            </li>
                                                            <li><strong>Phone:</strong> 
                                                                <?php echo htmlspecialchars($request['phone']); ?>
                                                                <?php if ($request['phone_verified']): ?>
                                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                                <?php endif; ?>
                                                            </li>
                                                            <li><strong>Gender:</strong> <?php echo htmlspecialchars($request['gender']); ?></li>
                                                            <li><strong>Date of Birth:</strong> <?php echo date('M j, Y', strtotime($request['dob'])); ?></li>
                                                        </ul>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <ul class="list-unstyled">
                                                            <li><strong>Religion:</strong> <?php echo htmlspecialchars($request['religion']); ?></li>
                                                            <li><strong>Occupation:</strong> <?php echo htmlspecialchars($request['occupation']); ?></li>
                                                            <li><strong>Education:</strong> <?php echo htmlspecialchars($request['education']); ?></li>
                                                            <li><strong>Location:</strong> 
                                                                <?php 
                                                                    echo htmlspecialchars($request['city'] . ', ' . 
                                                                    $request['state'] . ', ' . $request['country']); 
                                                                ?>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>

                                                <form method="post" class="mt-3">
                                                    <input type="hidden" name="user_id" value="<?php echo $request['user_id']; ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Admin Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" 
                                                                  placeholder="Add any notes about this verification decision..."></textarea>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="submit" name="action" value="approve" 
                                                                class="btn btn-success">
                                                            <i class="bi bi-check-lg"></i> Approve
                                                        </button>
                                                        <button type="submit" name="action" value="reject" 
                                                                class="btn btn-danger">
                                                            <i class="bi bi-x-lg"></i> Reject
                                                        </button>
                                                        <a href="../view-profile.php?id=<?php echo $request['user_id']; ?>" 
                                                           class="btn btn-primary" target="_blank">
                                                            <i class="bi bi-eye"></i> View Full Profile
                                                        </a>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
