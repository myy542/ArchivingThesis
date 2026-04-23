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

// CHECK IF IP_ADDRESS COLUMN EXISTS
$check_ip = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
if (!$check_ip || $check_ip->num_rows == 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) AFTER description");
}

// LOG FUNCTION - for important actions only (add, edit, delete, toggle)
function logAdminAction($conn, $user_id, $action, $table, $record_id, $description) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if ($ip_address == '::1') $ip_address = '127.0.0.1';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// NOTE: Wala nay automatic log sa page access para dili mag-create og daghang logs
// Ang logs kay para lang sa importanteng actions (add, edit, delete, toggle status)

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

// ==================== GET NOTIFICATION COUNT - FIXED ====================
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    // Check which column exists: is_read or status
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    if ($col_check && $col_check->num_rows > 0) {
        // Use is_read column
        $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->bind_param("i", $user_id);
        $n->execute();
        $result = $n->get_result();
        if ($row = $result->fetch_assoc()) {
            $notificationCount = $row['c'];
        }
        $n->close();
    } else {
        // Fallback to status column
        $col_check2 = $conn->query("SHOW COLUMNS FROM notifications LIKE 'status'");
        if ($col_check2 && $col_check2->num_rows > 0) {
            $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND status = 0");
            $n->bind_param("i", $user_id);
            $n->execute();
            $result = $n->get_result();
            if ($row = $result->fetch_assoc()) {
                $notificationCount = $row['c'];
            }
            $n->close();
        }
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }
        
        .top-nav { 
            position: fixed; top: 0; right: 0; left: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #ffcdd2; 
        }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; padding: 12px; align-items: center; justify-content: center; }
        .hamburger span { display: block; width: 20px; height: 2px; background: #dc2626; border-radius: 2px; transition: 0.3s; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #d32f2f; }
        .logo span { color: #d32f2f; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; border: 1px solid #ffcdd2; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 220px; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 12px; border-radius: 40px; transition: background 0.3s; }
        .profile-trigger:hover { background: #ffebee; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 38px; height: 38px; background: linear-gradient(135deg, #dc2626, #5b3b3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 100; border: 1px solid #ffcdd2; }
        .profile-dropdown.show { display: block; animation: fadeIn 0.2s; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; transition: 0.2s; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #ffebee; color: #dc2626; }
        .profile-dropdown hr { margin: 0; border-color: #ffcdd2; }
        
        .sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: linear-gradient(180deg, #b71c1c 0%, #d32f2f 100%); display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: center; }
        .logo-container .logo { color: white; font-size: 1.4rem; }
        .logo-container .logo span { color: #ffcdd2; }
        .admin-label { font-size: 0.7rem; color: #ffcdd2; margin-top: 5px; letter-spacing: 1px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 6px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #ffebee; transition: all 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; font-size: 1.1rem; }
        .nav-item:hover { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.25); color: white; }
        .dashboard-links { padding: 16px; border-top: 1px solid rgba(255,255,255,0.15); border-bottom: 1px solid rgba(255,255,255,0.15); margin: 5px 0; }
        .dashboard-links-header { display: flex; align-items: center; gap: 10px; padding: 8px 12px; color: #ffcdd2; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .dashboard-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; text-decoration: none; color: #ffebee; font-size: 0.8rem; transition: all 0.2s; }
        .dashboard-link:hover { background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .dashboard-link .link-icon { margin-left: auto; font-size: 0.7rem; opacity: 0.7; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 15px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; position: relative; width: 55px; height: 28px; background: rgba(255,255,255,0.25); border-radius: 30px; }
        .toggle-label i { position: absolute; top: 50%; transform: translateY(-50%); font-size: 12px; z-index: 1; }
        .toggle-label i:first-child { left: 8px; color: #f39c12; }
        .toggle-label i:last-child { right: 8px; color: #f1c40f; }
        .toggle-label .slider { position: absolute; top: 3px; left: 3px; width: 22px; height: 22px; background: white; border-radius: 50%; transition: transform 0.3s; }
        #darkmode:checked + .toggle-label .slider { transform: translateX(27px); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #ffebee; border-radius: 10px; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 30px; transition: margin-left 0.3s; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #d32f2f; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: #6b7280; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 22px 20px; display: flex; align-items: center; gap: 18px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(211,47,47,0.1); }
        .stat-icon { width: 55px; height: 55px; background: #ffebee; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #d32f2f; }
        .stat-details h3 { font-size: 1.8rem; font-weight: 700; color: #d32f2f; margin-bottom: 5px; }
        .stat-details p { font-size: 0.8rem; color: #6b7280; }
        
        .filter-bar { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; border: 1px solid #ffcdd2; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .filter-select { padding: 10px 16px; border-radius: 40px; border: 1px solid #ffcdd2; background: #f8f9fa; font-size: 0.85rem; cursor: pointer; }
        .date-input { padding: 10px 16px; border-radius: 40px; border: 1px solid #ffcdd2; background: #f8f9fa; font-size: 0.85rem; }
        .filter-btn { background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .filter-btn:hover { background: #b71c1c; transform: translateY(-2px); }
        .clear-btn { background: #fef2f2; color: #6b7280; border: 1px solid #ffcdd2; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .clear-btn:hover { background: #fee2e2; }
        
        .logs-section { background: white; border-radius: 20px; padding: 25px; border: 1px solid #ffcdd2; }
        .table-responsive { overflow-x: auto; }
        .logs-table { width: 100%; border-collapse: collapse; }
        .logs-table th { text-align: left; padding: 14px 12px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #ffcdd2; }
        .logs-table td { padding: 14px 12px; border-bottom: 1px solid #ffebee; font-size: 0.85rem; vertical-align: top; }
        .logs-table tr:hover td { background: #ffebee; }
        .action-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 500; background: #ffebee; color: #d32f2f; }
        .ip-badge { font-family: monospace; background: #fef2f2; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; display: inline-block; }
        .empty-state { text-align: center; padding: 60px; color: #6b7280; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #d32f2f; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .top-nav { padding: 0 16px; }
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-select, .date-input, .filter-btn, .clear-btn { width: 100%; }
            .search-area { display: none; }
            .profile-name { display: none; }
        }
        @media (max-width: 480px) { 
            .main-content { padding: 16px; } 
            .stat-card { padding: 16px; } 
            .stat-icon { width: 45px; height: 45px; font-size: 1.2rem; } 
            .stat-details h3 { font-size: 1.4rem; } 
        }
        
        body.dark-mode { background: #0f172a; }
        body.dark-mode .top-nav { background: #1e293b; border-bottom-color: #334155; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #334155; border-color: #475569; }
        body.dark-mode .search-area input { color: white; }
        body.dark-mode .profile-name { color: #e5e7eb; }
        body.dark-mode .stat-card, body.dark-mode .filter-bar, body.dark-mode .logs-section { background: #1e293b; border-color: #334155; }
        body.dark-mode .stat-details h3 { color: #fecaca; }
        body.dark-mode .logs-table td { color: #e5e7eb; border-bottom-color: #334155; }
        body.dark-mode .logs-table th { color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode .logs-table tr:hover td { background: #334155; }
        body.dark-mode .profile-dropdown { background: #1e293b; border-color: #334155; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .profile-dropdown a:hover { background: #334155; }
        body.dark-mode .filter-select, body.dark-mode .date-input { background: #334155; border-color: #475569; color: white; }
        body.dark-mode .clear-btn { background: #334155; color: #e5e7eb; border-color: #475569; }
        body.dark-mode .clear-btn:hover { background: #475569; }
        body.dark-mode .ip-badge { background: #334155; }
    </style>
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
                        <tr><td colspan="7" class="empty-state"><i class="fas fa-database"></i><p>No audit logs found</p></div><div class="empty-state"></div>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($log['user']) ?></strong></td>
                            <td><span class="action-badge"><?= htmlspecialchars($log['action']) ?></span></div>
                            <td><?= htmlspecialchars($log['table']) ?></div>
                            <td><?= $log['record_id'] > 0 ? '#'.$log['record_id'] : '-' ?></div>
                            <td><?= htmlspecialchars($log['description']) ?></div>
                            <td><span class="ip-badge"><?= htmlspecialchars($log['ip_address']) ?></span></div>
                            <td><?= $log['created_at'] ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </div>
            </div>
        </div>
    </main>
    
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInputFilter = document.getElementById('searchInputFilter');
        const actionFilter = document.getElementById('actionFilter');
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        const applyFilters = document.getElementById('applyFilters');
        const clearFilters = document.getElementById('clearFilters');
        
        function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleSidebar(e) { e.stopPropagation(); if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); }
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            }
        });
        
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
        
        if (profileWrapper && profileDropdown) {
            profileWrapper.addEventListener('click', function(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
            document.addEventListener('click', function(e) { if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); });
        }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); }
                    else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); }
                });
            }
        }
        
        function applyFilter() {
            const search = searchInputFilter ? searchInputFilter.value.trim() : '';
            const action = actionFilter ? actionFilter.value : '';
            const from = dateFrom ? dateFrom.value : '';
            const to = dateTo ? dateTo.value : '';
            let url = window.location.pathname + '?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (action) url += 'action=' + encodeURIComponent(action) + '&';
            if (from) url += 'date_from=' + encodeURIComponent(from) + '&';
            if (to) url += 'date_to=' + encodeURIComponent(to);
            window.location.href = url;
        }
        
        function clearAllFilters() { window.location.href = window.location.pathname; }
        if (applyFilters) applyFilters.addEventListener('click', applyFilter);
        if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);
        
        initDarkMode();
        console.log('Audit Logs Page Loaded - Menu Bar Style Sidebar');
    </script>
</body>
</html>