<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

$user_created = date('F Y');
$check_created_column = $conn->query("SHOW COLUMNS FROM user_table LIKE 'created_at'");
if ($check_created_column && $check_created_column->num_rows > 0) {
    $user_query_full = "SELECT created_at FROM user_table WHERE user_id = ?";
    $user_stmt_full = $conn->prepare($user_query_full);
    $user_stmt_full->bind_param("i", $user_id);
    $user_stmt_full->execute();
    $user_result_full = $user_stmt_full->get_result();
    if ($user_row = $user_result_full->fetch_assoc()) {
        $user_created = date('F Y', strtotime($user_row['created_at']));
    }
    $user_stmt_full->close();
}

// AUDIT LOGS TABLE
$check_audit_table = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if (!$check_audit_table || $check_audit_table->num_rows == 0) {
    $conn->query("CREATE TABLE audit_logs (
        audit_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(255),
        table_name VARCHAR(100),
        record_id INT,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

$check_ip = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
if (!$check_ip || $check_ip->num_rows == 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) AFTER description");
}

function logAdminAction($conn, $user_id, $action, $table, $record_id, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if ($ip == '::1') $ip = '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip);
    $log_stmt->execute();
    $log_stmt->close();
}

logAdminAction($conn, $user_id, "Admin accessed dashboard", "user_table", $user_id, "Admin $fullName accessed the admin dashboard");

// DASHBOARDS - UPDATED: Faculty to Research Adviser
$dashboards = [
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php', 'role_id' => 1],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php', 'role_id' => 2],
    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php', 'role_id' => 3],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php', 'role_id' => 4],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php', 'role_id' => 5],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php', 'role_id' => 6]
];

// STATISTICS
$stats = [];
foreach ($dashboards as $id => $dash) {
    $result = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE role_id = $id AND status = 'Active'");
    $stats[$dash['name']] = $result->fetch_assoc()['c'];
}
$total = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE status = 'Active'")->fetch_assoc()['c'];
$stats['Total Users'] = $total;

// GET ALL USERS FOR STATS
$all_users_count = $conn->query("SELECT COUNT(*) as c FROM user_table")->fetch_assoc()['c'];
$active_users = $stats['Total Users'];
$inactive_users = $all_users_count - $active_users;

// MONTHLY DATA
$monthly = array_fill(0, 12, 0);
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$has_created = $conn->query("SHOW COLUMNS FROM user_table LIKE 'created_at'")->num_rows > 0;
if ($has_created) {
    $m = $conn->query("SELECT MONTH(created_at) as mo, COUNT(*) as c FROM user_table WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)");
    while ($r = $m->fetch_assoc()) $monthly[$r['mo']-1] = $r['c'];
}

// GET AUDIT LOGS COUNT
$logs_count = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'];

$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows) {
    $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $n->bind_param("i", $user_id);
    $n->execute();
    $notificationCount = $n->get_result()->fetch_assoc()['c'];
    $n->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/admindashboard.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-icon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="#"><i class="fas fa-cog"></i> Settings</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>
    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="admin-label">ADMINISTRATOR</div></div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header"><i class="fas fa-chalkboard-user"></i><span>Quick Access</span></div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank"><i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i><span><?= $dashboard['name'] ?> Dashboard</span><i class="fas fa-external-link-alt link-icon"></i></a>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info"><h1>Admin Dashboard</h1><p>Welcome back, <?= htmlspecialchars($first_name) ?>! • System Overview</p></div>
            <div class="admin-info"><div class="admin-name"><?= htmlspecialchars($fullName) ?></div><div class="admin-since">Admin since <?= $user_created ?></div></div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-details"><h3><?= number_format($stats['Total Users']) ?></h3><p>Active Users</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-details"><h3><?= number_format($stats['Student']) ?></h3><p>Students</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div class="stat-details"><h3><?= number_format($stats['Research Adviser']) ?></h3><p>Research Advisers</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-tie"></i></div><div class="stat-details"><h3><?= number_format($stats['Dean']) ?></h3><p>Deans</p></div></div>
        </div>
        
        <div class="stats-grid-second">
            <div class="stat-card-small"><div class="stat-icon-small"><i class="fas fa-book-reader"></i></div><div class="stat-details-small"><h4><?= number_format($stats['Librarian']) ?></h4><p>Librarians</p></div></div>
            <div class="stat-card-small"><div class="stat-icon-small"><i class="fas fa-clipboard-list"></i></div><div class="stat-details-small"><h4><?= number_format($stats['Coordinator']) ?></h4><p>Coordinators</p></div></div>
            <div class="stat-card-small"><div class="stat-icon-small"><i class="fas fa-user-shield"></i></div><div class="stat-details-small"><h4><?= number_format($stats['Admin']) ?></h4><p>Admins</p></div></div>
            <div class="stat-card-small"><div class="stat-icon-small"><i class="fas fa-chart-line"></i></div><div class="stat-details-small"><h4><?= number_format($logs_count) ?></h4><p>Total Logs</p></div></div>
        </div>
        
        <div class="charts-row">
            <div class="chart-card"><h3><i class="fas fa-chart-pie"></i> User Distribution by Role</h3><div class="chart-container"><canvas id="userDistributionChart"></canvas></div></div>
            <div class="chart-card"><h3><i class="fas fa-chart-line"></i> User Registration Trend</h3><div class="chart-container"><canvas id="registrationChart"></canvas></div></div>
        </div>
        
        <div class="cards-row">
            <div class="info-card">
                <h3><i class="fas fa-users"></i> User Management</h3>
                <div class="info-stats">
                    <div class="info-stat"><div class="number"><?= $active_users ?></div><div class="label">Active</div></div>
                    <div class="info-stat"><div class="number"><?= $inactive_users ?></div><div class="label">Inactive</div></div>
                    <div class="info-stat"><div class="number"><?= $all_users_count ?></div><div class="label">Total</div></div>
                </div>
                <a href="users.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> Manage Users</a>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-history"></i> Audit Logs</h3>
                <div class="info-stats">
                    <div class="info-stat"><div class="number"><?= $logs_count ?></div><div class="label">Total Logs</div></div>
                    <div class="info-stat"><div class="number"><?= date('M d, Y') ?></div><div class="label">Today</div></div>
                </div>
                <a href="audit_logs.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> View Logs</a>
            </div>
        </div>
    </main>
    <script>
        window.userData = {
            stats: { 
                students: <?= $stats['Student'] ?? 0 ?>, 
                research_advisers: <?= $stats['Research Adviser'] ?? 0 ?>, 
                deans: <?= $stats['Dean'] ?? 0 ?>, 
                librarians: <?= $stats['Librarian'] ?? 0 ?>, 
                coordinators: <?= $stats['Coordinator'] ?? 0 ?>, 
                admins: <?= $stats['Admin'] ?? 0 ?> 
            },
            monthlyData: <?= json_encode($monthly) ?>, 
            months: <?= json_encode($months) ?>
        };
    </script>
    <script src="js/admindashboard.js"></script>
</body>
</html>