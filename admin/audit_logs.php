<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - ONLY ADMIN CAN ACCESS
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA
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

// CHECK IF AUDIT_LOGS TABLE EXISTS
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

// GET FILTERS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// BUILD QUERY
$query = "SELECT al.*, u.username, u.first_name, u.last_name 
          FROM audit_logs al 
          LEFT JOIN user_table u ON al.user_id = u.user_id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (al.description LIKE ? OR u.username LIKE ? OR al.action_type LIKE ? OR al.table_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($action_filter)) {
    $query .= " AND al.action_type = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$query .= " ORDER BY al.created_at DESC";

// EXECUTE QUERY
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $user_display = 'System';
    if ($row['user_id']) {
        $user_display = !empty($row['username']) ? $row['username'] : 'User ID: ' . $row['user_id'];
        if (!empty($row['first_name']) && !empty($row['last_name'])) {
            $user_display = $row['first_name'] . ' ' . $row['last_name'];
        }
    }
    
    $ip = $row['ip_address'] ?? 'Unknown';
    if ($ip == '::1') $ip = '127.0.0.1';
    
    $logs[] = [
        'id' => $row['audit_id'],
        'user' => $user_display,
        'action' => $row['action_type'],
        'table' => $row['table_name'],
        'record_id' => $row['record_id'],
        'description' => $row['description'],
        'ip_address' => $ip,
        'created_at' => date('M d, Y h:i A', strtotime($row['created_at']))
    ];
}
$stmt->close();

// GET UNIQUE ACTION TYPES
$action_types = [];
$action_result = $conn->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type");
while ($row = $action_result->fetch_assoc()) {
    $action_types[] = $row['action_type'];
}

// GET STATISTICS
$total_logs = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'];
$unique_users = $conn->query("SELECT COUNT(DISTINCT user_id) as c FROM audit_logs WHERE user_id IS NOT NULL")->fetch_assoc()['c'] ?? 0;
$today_logs = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$this_week = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE WEEK(created_at) = WEEK(CURDATE())")->fetch_assoc()['c'] ?? 0;

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    if ($col_check && $col_check->num_rows > 0) {
        $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->bind_param("i", $user_id);
        $n->execute();
        $result = $n->get_result();
        if ($row = $result->fetch_assoc()) {
            $notificationCount = $row['c'];
        }
        $n->close();
    }
}

// DASHBOARDS FOR SIDEBAR
$dashboards = [
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php'],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php'],
    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php'],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php'],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php'],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php']
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/audit_logs.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search logs..."></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="admin-label">ADMINISTRATOR</div></div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="audit_logs.php" class="nav-item active"><i class="fas fa-history"></i><span>Audit Logs</span></a>
            <a href="theses.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Theses</span></a>
            <a href="backup_management.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header"><i class="fas fa-chalkboard-user"></i><span>Quick Access</span></div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank">
                <i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i>
                <span><?= $dashboard['name'] ?> Dashboard</span>
                <i class="fas fa-external-link-alt link-icon"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-history"></i> Audit Logs</h1>
            <p>Track all system activities, user actions, and administrative changes</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-details"><h3><?= number_format($total_logs) ?></h3><p>Total Logs</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-details"><h3><?= number_format($unique_users) ?></h3><p>Unique Users</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-day"></i></div><div class="stat-details"><h3><?= number_format($today_logs) ?></h3><p>Today's Logs</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-week"></i></div><div class="stat-details"><h3><?= number_format($this_week) ?></h3><p>This Week</p></div></div>
        </div>
        
        <div class="filter-bar">
            <input type="text" id="searchInputFilter" class="filter-select" placeholder="Search by user, action, table..." value="<?= htmlspecialchars($search) ?>" style="flex: 2;">
            <select id="actionFilter" class="filter-select">
                <option value="">All Actions</option>
                <?php foreach ($action_types as $action): ?>
                    <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter == $action ? 'selected' : '' ?>><?= htmlspecialchars($action) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="dateFrom" class="date-input" placeholder="Date From" value="<?= htmlspecialchars($date_from) ?>">
            <input type="date" id="dateTo" class="date-input" placeholder="Date To" value="<?= htmlspecialchars($date_to) ?>">
            <button id="applyFilters" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
            <button id="clearFilters" class="clear-btn"><i class="fas fa-times"></i> Clear</button>
        </div>
        
        <div class="logs-section">
            <div class="table-responsive">
                <table class="logs-table">
                    <thead>
                        <tr><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>Description</th><th>IP Address</th><th>Date & Time</th></tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php if (empty($logs)): ?>
                        <tr><td colspan="7" class="empty-state"><i class="fas fa-database"></i><p>No audit logs found</p></td></tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($log['user']) ?></strong></td>
                            <td><span class="action-badge"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td><?= htmlspecialchars($log['table']) ?></td>
                            <td><?= $log['record_id'] > 0 ? '#'.$log['record_id'] : '-' ?></td>
                            <td><?= htmlspecialchars($log['description']) ?></td>
                            <td><span class="ip-badge"><?= htmlspecialchars($log['ip_address']) ?></span></td>
                            <td><?= $log['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.searchParams = {
            search: '<?php echo addslashes($search); ?>',
            action: '<?php echo addslashes($action_filter); ?>',
            dateFrom: '<?php echo addslashes($date_from); ?>',
            dateTo: '<?php echo addslashes($date_to); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/audit_logs.js"></script>
</body>
</html>