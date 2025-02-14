<div class="card">
    <div class="card-body">
        <h5 class="card-title">Admin Menu</h5>
        <div class="list-group list-group-flush">
            <a href="/matrimony/admin/dashboard.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="/matrimony/admin/manage-users.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'manage-users.php' ? 'active' : ''; ?>">
                <i class="bi bi-people me-2"></i> Manage Users
            </a>
            <a href="/matrimony/admin/verify-users.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'verify-users.php' ? 'active' : ''; ?>">
                <i class="bi bi-person-check me-2"></i> Verify Users
            </a>
            <a href="/matrimony/admin/reports.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up me-2"></i> Reports
            </a>
        </div>
    </div>
</div>
