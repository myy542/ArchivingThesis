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

// ==================== NOTIFICATION HANDLERS ====================
// MARK NOTIFICATION AS READ (via AJAX)
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update_query = "UPDATE notifications SET status = 1 WHERE notification_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $notif_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL NOTIFICATIONS AS READ
if (isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE notifications SET status = 1 WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// DASHBOARDS
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

// Check if may data para sa graph
$hasMonthlyData = false;
foreach ($monthly as $val) {
    if ($val > 0) {
        $hasMonthlyData = true;
        break;
    }
}

// Sample data para sa graph kung walay actual data
$sample_monthly_data = [3, 5, 7, 9, 12, 15, 18, 20, 16, 12, 8, 4];
if (!$hasMonthlyData) {
    $monthly = $sample_monthly_data;
}

// GET AUDIT LOGS COUNT
$logs_count = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'];

// GET THESES COUNT
$theses_count = 0;
$check_theses_table = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_theses_table && $check_theses_table->num_rows > 0) {
    $theses_count = $conn->query("SELECT COUNT(*) as c FROM thesis_table")->fetch_assoc()['c'];
}

// ==================== NOTIFICATION SYSTEM ====================
// Create notifications table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    thesis_id INT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255) NULL,
    status TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (status)
)");

// GET NOTIFICATION COUNT - using 'status' instead of 'is_read'
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND status = 0");
    $n->bind_param("i", $user_id);
    $n->execute();
    $result = $n->get_result();
    if ($row = $result->fetch_assoc()) {
        $notificationCount = $row['c'];
    }
    $n->close();
}

// GET RECENT NOTIFICATIONS FOR DROPDOWN
$recentNotifications = [];
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, type, link, status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list->close();

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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fef2f2;
            color: #1f2937;
            overflow-x: hidden;
        }

        /* Top Navigation - full width */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            z-index: 99;
            border-bottom: 1px solid #ffcdd2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        /* Hamburger - ALWAYS VISIBLE */
        .hamburger {
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            padding: 12px;
            align-items: center;
            justify-content: center;
        }

        .hamburger span {
            display: block;
            width: 20px;
            height: 2px;
            background: #dc2626;
            border-radius: 2px;
            transition: 0.3s;
        }

        .hamburger:hover {
            background: #fee2e2;
        }

        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #d32f2f;
        }

        .logo span {
            color: #d32f2f;
        }

        .search-area {
            display: flex;
            align-items: center;
            background: #fef2f2;
            padding: 8px 16px;
            border-radius: 40px;
            gap: 10px;
            border: 1px solid #ffcdd2;
        }

        .search-area i {
            color: #dc2626;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 220px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Notification Styles */
        .notification-container {
            position: relative;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-icon:hover {
            background: #fee2e2;
        }

        .notification-icon i {
            font-size: 1.2rem;
            color: #dc2626;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .notification-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            display: none;
            overflow: hidden;
            z-index: 1000;
            border: 1px solid #ffcdd2;
            animation: fadeSlideDown 0.2s ease;
        }

        .notification-dropdown.show {
            display: block;
        }

        @keyframes fadeSlideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .notification-header {
            padding: 12px 16px;
            border-bottom: 1px solid #fee2e2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #991b1b;
        }

        .notification-header a {
            font-size: 0.7rem;
            color: #dc2626;
            text-decoration: none;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #fef2f2;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-item:hover {
            background: #fef2f2;
        }

        .notification-item.unread {
            background: #fff5f5;
            border-left: 3px solid #dc2626;
        }

        .notification-item.empty {
            justify-content: center;
            color: #9ca3af;
            cursor: default;
        }

        .notification-item.empty:hover {
            background: transparent;
        }

        .notif-icon {
            width: 36px;
            height: 36px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
        }

        .notif-content {
            flex: 1;
        }

        .notif-message {
            font-size: 0.8rem;
            color: #1f2937;
            margin-bottom: 4px;
            line-height: 1.4;
        }

        .notif-time {
            font-size: 0.65rem;
            color: #9ca3af;
        }

        .notification-footer {
            padding: 10px 16px;
            border-top: 1px solid #fee2e2;
            text-align: center;
        }

        .notification-footer a {
            font-size: 0.75rem;
            color: #dc2626;
            text-decoration: none;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 12px;
            border-radius: 40px;
            transition: background 0.3s;
        }

        .profile-trigger:hover {
            background: #ffebee;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #dc2626, #5b3b3b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #ffcdd2;
        }

        .profile-dropdown.show {
            display: block;
            animation: fadeIn 0.2s;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .profile-dropdown a:hover {
            background: #ffebee;
            color: #dc2626;
        }

        .profile-dropdown hr {
            margin: 0;
            border-color: #ffcdd2;
        }

        /* Sidebar - COLLAPSIBLE MENU BAR (hidden by default) */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #b71c1c 0%, #d32f2f 100%);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar.open {
            left: 0;
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }

        .logo-container .logo {
            color: white;
            font-size: 1.4rem;
        }

        .logo-container .logo span {
            color: #ffcdd2;
        }

        .admin-label {
            font-size: 0.7rem;
            color: #ffcdd2;
            margin-top: 5px;
            letter-spacing: 1px;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #ffebee;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
        }

        .theses-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #ffebee;
            transition: all 0.2s;
            font-weight: 500;
        }

        .theses-link i {
            width: 22px;
            font-size: 1.1rem;
        }

        .theses-link:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }

        .dashboard-links {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin: 5px 0;
        }

        .dashboard-links-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            color: #ffcdd2;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .dashboard-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            text-decoration: none;
            color: #ffebee;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .dashboard-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .dashboard-link .link-icon {
            margin-left: auto;
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .nav-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        .theme-toggle {
            margin-bottom: 15px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            position: relative;
            width: 55px;
            height: 28px;
            background: rgba(255, 255, 255, 0.25);
            border-radius: 30px;
        }

        .toggle-label i {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            z-index: 1;
        }

        .toggle-label i:first-child {
            left: 8px;
            color: #f39c12;
        }

        .toggle-label i:last-child {
            right: 8px;
            color: #f1c40f;
        }

        .toggle-label .slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            transition: transform 0.3s;
        }

        #darkmode:checked + .toggle-label .slider {
            transform: translateX(27px);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #ffebee;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }

        /* Main Content - full width */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 30px;
            transition: margin-left 0.3s;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #b71c1c, #d32f2f);
            border-radius: 20px;
            padding: 30px 35px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-info h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-info p {
            opacity: 0.9;
            font-size: 0.85rem;
        }

        .admin-info {
            text-align: right;
        }

        .admin-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .admin-since {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .stats-grid-second {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 35px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(211, 47, 47, 0.1);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: #ffebee;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #d32f2f;
        }

        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 5px;
        }

        .stat-details p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .stat-card-small {
            background: white;
            border-radius: 16px;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }

        .stat-card-small:hover {
            transform: translateY(-2px);
        }

        .stat-icon-small {
            width: 48px;
            height: 48px;
            background: #ffebee;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #d32f2f;
        }

        .stat-details-small h4 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 4px;
        }

        .stat-details-small p {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Charts */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 35px;
        }

        .chart-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #ffcdd2;
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }

        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .chart-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #d32f2f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-container {
            height: 260px;
            position: relative;
            width: 100%;
            min-height: 250px;
        }

        /* Cards Row */
        .cards-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-top: 25px;
        }

        .info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }

        .info-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(211, 47, 47, 0.1);
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #d32f2f;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin-top: 15px;
        }

        .info-stat {
            text-align: center;
        }

        .info-stat .number {
            font-size: 2rem;
            font-weight: 700;
            color: #d32f2f;
        }

        .info-stat .label {
            font-size: 0.7rem;
            color: #6b7280;
        }

        .btn-view-all {
            display: inline-block;
            margin-top: 15px;
            padding: 8px 16px;
            background: #d32f2f;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
            text-align: center;
        }

        .btn-view-all:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid,
            .stats-grid-second,
            .charts-row,
            .cards-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 16px;
            }

            .sidebar {
                left: -280px;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid,
            .stats-grid-second,
            .charts-row,
            .cards-row {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .search-area {
                display: none;
            }

            .profile-name {
                display: none;
            }

            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
                padding: 25px;
            }

            .admin-info {
                text-align: center;
            }

            .chart-container {
                height: 220px;
            }

            .notification-dropdown {
                width: 320px;
                right: -10px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }

            .stat-card,
            .stat-card-small {
                padding: 16px;
            }

            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
            }

            .stat-details h3 {
                font-size: 1.4rem;
            }

            .chart-container {
                height: 200px;
            }

            .notification-dropdown {
                width: 300px;
                right: -5px;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #0f172a;
        }

        body.dark-mode .top-nav {
            background: #1e293b;
            border-bottom-color: #334155;
        }

        body.dark-mode .logo {
            color: #fecaca;
        }

        body.dark-mode .search-area {
            background: #334155;
            border-color: #475569;
        }

        body.dark-mode .search-area input {
            color: white;
        }

        body.dark-mode .profile-name {
            color: #e5e7eb;
        }

        body.dark-mode .stat-card,
        body.dark-mode .stat-card-small,
        body.dark-mode .chart-card,
        body.dark-mode .info-card,
        body.dark-mode .notification-dropdown {
            background: #1e293b;
            border-color: #334155;
        }

        body.dark-mode .stat-details h3,
        body.dark-mode .stat-details-small h4,
        body.dark-mode .info-stat .number {
            color: #fecaca;
        }

        body.dark-mode .profile-dropdown {
            background: #1e293b;
            border-color: #334155;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }

        body.dark-mode .profile-dropdown a:hover {
            background: #334155;
        }

        body.dark-mode .notification-item {
            border-bottom-color: #334155;
        }

        body.dark-mode .notification-item:hover {
            background: #334155;
        }

        body.dark-mode .notification-item.unread {
            background: #3a2a2a;
        }

        body.dark-mode .notification-header {
            border-bottom-color: #334155;
        }

        body.dark-mode .notification-header h4 {
            color: #fecaca;
        }

        body.dark-mode .notif-message {
            color: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge" id="notificationBadge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <?php if ($notificationCount > 0): ?>
                            <a href="#" id="markAllReadBtn">Mark all as read</a>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty">
                                <div class="notif-icon"><i class="far fa-bell-slash"></i></div>
                                <div class="notif-content">
                                    <div class="notif-message">No notifications yet</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <div class="notification-item <?= $notif['status'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>" data-link="<?= htmlspecialchars($notif['link'] ?? '#') ?>">
                                    <div class="notif-icon">
                                        <?php if(strpos($notif['message'], 'registration') !== false): ?>
                                            <i class="fas fa-user-plus"></i>
                                        <?php elseif(strpos($notif['message'], 'thesis') !== false): ?>
                                            <i class="fas fa-file-alt"></i>
                                        elseif(strpos($notif['message'], 'approved') !== false):
                                            echo '<i class="fas fa-check-circle"></i>';
                                        elseif(strpos($notif['message'], 'rejected') !== false):
                                            echo '<i class="fas fa-times-circle"></i>';
                                        else: ?>
                                            <i class="fas fa-bell"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time">
                                            <i class="far fa-clock"></i> 
                                            <?php 
                                            $date = new DateTime($notif['created_at']);
                                            $now = new DateTime();
                                            $diff = $now->diff($date);
                                            if($diff->days == 0) 
                                                echo 'Today, ' . $date->format('h:i A');
                                            elseif($diff->days == 1) 
                                                echo 'Yesterday, ' . $date->format('h:i A');
                                            else 
                                                echo $date->format('M d, Y h:i A');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
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
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="admin-label">ADMINISTRATOR</div>
        </div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
            <a href="theses.php" class="theses-link"><i class="fas fa-file-alt"></i><span>Theses</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header">
                <i class="fas fa-chalkboard-user"></i><span>Quick Access</span>
            </div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank">
                <i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i>
                <span><?= $dashboard['name'] ?> Dashboard</span>
                <i class="fas fa-external-link-alt link-icon"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                    <span class="slider"></span>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($first_name) ?>! • System Overview</p>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="admin-since">Admin since <?= $user_created ?></div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Total Users']) ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Student']) ?></h3>
                    <p>Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Research Adviser']) ?></h3>
                    <p>Research Advisers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Dean']) ?></h3>
                    <p>Deans</p>
                </div>
            </div>
        </div>
        
        <div class="stats-grid-second">
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-book-reader"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Librarian']) ?></h4>
                    <p>Librarians</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Coordinator']) ?></h4>
                    <p>Coordinators</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-user-shield"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Admin']) ?></h4>
                    <p>Admins</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-file-alt"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($theses_count) ?></h4>
                    <p>Theses</p>
                </div>
            </div>
        </div>
        
        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> User Distribution by Role</h3>
                <div class="chart-container">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> User Registration Trend</h3>
                <div class="chart-container">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="cards-row">
            <div class="info-card">
                <h3><i class="fas fa-users"></i> User Management</h3>
                <div class="info-stats">
                    <div class="info-stat">
                        <div class="number"><?= $active_users ?></div>
                        <div class="label">Active</div>
                    </div>
                    <div class="info-stat">
                        <div class="number"><?= $inactive_users ?></div>
                        <div class="label">Inactive</div>
                    </div>
                    <div class="info-stat">
                        <div class="number"><?= $all_users_count ?></div>
                        <div class="label">Total</div>
                    </div>
                </div>
                <a href="users.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> Manage Users</a>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-file-alt"></i> Theses Management</h3>
                <div class="info-stats">
                    <div class="info-stat">
                        <div class="number"><?= $theses_count ?></div>
                        <div class="label">Total Theses</div>
                    </div>
                </div>
                <a href="theses.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> Manage Theses</a>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const hamburgerBtn = document.getElementById('hamburgerBtn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const profileWrapper = document.getElementById('profileWrapper');
            const profileDropdown = document.getElementById('profileDropdown');
            const darkModeToggle = document.getElementById('darkmode');
            const notificationIcon = document.getElementById('notificationIcon');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const notificationBadge = document.getElementById('notificationBadge');
            const notificationList = document.getElementById('notificationList');
            const markAllReadBtn = document.getElementById('markAllReadBtn');

            // ==================== SIDEBAR FUNCTIONS ====================
            function openSidebar() {
                sidebar.classList.add('open');
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
                console.log('Sidebar opened');
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
                console.log('Sidebar closed');
            }

            function toggleSidebar(e) {
                e.stopPropagation();
                if (sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }

            // Hamburger Button Event
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleSidebar);
                console.log('Hamburger button initialized');
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (sidebar.classList.contains('open')) closeSidebar();
                    if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                    if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
                }
            });

            // Close sidebar on window resize (if screen becomes larger)
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });

            // ==================== PROFILE DROPDOWN ====================
            if (profileWrapper && profileDropdown) {
                profileWrapper.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('show');
                });
                document.addEventListener('click', function(e) {
                    if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });
            }

            // ==================== NOTIFICATION FUNCTIONS ====================
            if (notificationIcon) {
                notificationIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationDropdown.classList.toggle('show');
                    if (profileDropdown.classList.contains('show')) {
                        profileDropdown.classList.remove('show');
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) {
                    notificationDropdown.classList.remove('show');
                }
            });

            function markNotificationAsRead(notifId, element) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'mark_read=1&notif_id=' + notifId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        element.classList.remove('unread');
                        if (notificationBadge) {
                            let c = parseInt(notificationBadge.textContent);
                            if (c > 0) {
                                c--;
                                if (c === 0) {
                                    notificationBadge.style.display = 'none';
                                } else {
                                    notificationBadge.textContent = c;
                                }
                            }
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            function markAllAsRead() {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'mark_all_read=1'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        if (notificationBadge) {
                            notificationBadge.style.display = 'none';
                        }
                        if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
            }

            if (notificationList) {
                notificationList.addEventListener('click', function(e) {
                    const notificationItem = e.target.closest('.notification-item');
                    if (notificationItem && !notificationItem.classList.contains('empty')) {
                        const notifId = notificationItem.dataset.id;
                        const link = notificationItem.dataset.link;
                        if (notifId && notificationItem.classList.contains('unread')) {
                            markNotificationAsRead(notifId, notificationItem);
                        }
                        if (link && link !== '#') {
                            setTimeout(() => {
                                window.location.href = link;
                            }, 300);
                        }
                    }
                });
            }

            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    markAllAsRead();
                });
            }

            if (notificationBadge && notificationBadge.textContent === '') {
                notificationBadge.style.display = 'none';
            } else if (notificationBadge) {
                notificationBadge.style.display = 'flex';
            }

            // ==================== DARK MODE ====================
            function initDarkMode() {
                const isDark = localStorage.getItem('darkMode') === 'true';
                if (isDark) {
                    document.body.classList.add('dark-mode');
                    if (darkModeToggle) darkModeToggle.checked = true;
                }
                if (darkModeToggle) {
                    darkModeToggle.addEventListener('change', function() {
                        if (this.checked) {
                            document.body.classList.add('dark-mode');
                            localStorage.setItem('darkMode', 'true');
                        } else {
                            document.body.classList.remove('dark-mode');
                            localStorage.setItem('darkMode', 'false');
                        }
                    });
                }
            }

            // ==================== BALANCED CHARTS ====================
            function initCharts() {
                // User Distribution Chart - Balanced Doughnut
                const distCtx = document.getElementById('userDistributionChart');
                if (distCtx && window.userData) {
                    if (window.distChartInstance) window.distChartInstance.destroy();
                    
                    window.distChartInstance = new Chart(distCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Students', 'Research Advisers', 'Deans', 'Librarians', 'Coordinators', 'Admins'],
                            datasets: [{
                                data: [
                                    window.userData.stats.students || 0,
                                    window.userData.stats.research_advisers || 0,
                                    window.userData.stats.deans || 0,
                                    window.userData.stats.librarians || 0,
                                    window.userData.stats.coordinators || 0,
                                    window.userData.stats.admins || 0
                                ],
                                backgroundColor: ['#1976d2', '#388e3c', '#f57c00', '#7b1fa2', '#e67e22', '#d32f2f'],
                                borderWidth: 0,
                                cutout: '60%',
                                hoverOffset: 10,
                                borderRadius: 8,
                                spacing: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { 
                                        font: { size: 11 },
                                        boxWidth: 10,
                                        padding: 10
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            const val = ctx.raw || 0;
                                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                            return `${ctx.label}: ${val} (${pct}%)`;
                                        }
                                    },
                                    backgroundColor: '#1f2937',
                                    titleColor: '#fef2f2',
                                    bodyColor: '#fef2f2',
                                    padding: 10,
                                    cornerRadius: 8
                                }
                            },
                            layout: {
                                padding: { top: 10, bottom: 10, left: 10, right: 10 }
                            }
                        }
                    });
                }

                // Registration Trend Chart
                const regCtx = document.getElementById('registrationChart');
                if (regCtx && window.userData) {
                    if (window.regChartInstance) window.regChartInstance.destroy();
                    
                    const maxValue = Math.max(...window.userData.monthlyData, 1);
                    const yAxisMax = Math.ceil(maxValue * 1.2);
                    
                    window.regChartInstance = new Chart(regCtx, {
                        type: 'line',
                        data: {
                            labels: window.userData.months || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                            datasets: [{
                                label: 'New Users',
                                data: window.userData.monthlyData || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                borderColor: '#dc2626',
                                backgroundColor: 'rgba(220, 38, 38, 0.05)',
                                borderWidth: 3,
                                pointBackgroundColor: '#dc2626',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                pointHoverBackgroundColor: '#991b1b',
                                fill: true,
                                tension: 0.3,
                                spanGaps: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: { 
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            return `New Users: ${ctx.raw}`;
                                        }
                                    },
                                    backgroundColor: '#1f2937',
                                    titleColor: '#fef2f2',
                                    bodyColor: '#fef2f2',
                                    padding: 10,
                                    cornerRadius: 8
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: yAxisMax,
                                    grid: {
                                        color: '#fee2e2',
                                        drawBorder: true,
                                        borderDash: [5, 5],
                                        lineWidth: 1
                                    },
                                    ticks: { 
                                        stepSize: 1, 
                                        precision: 0,
                                        color: '#6b7280',
                                        font: { size: 11, weight: '500' }
                                    },
                                    title: { 
                                        display: true, 
                                        text: 'Number of Users', 
                                        font: { size: 10, weight: '500' },
                                        color: '#9ca3af',
                                        padding: { bottom: 10 }
                                    }
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { 
                                        color: '#6b7280',
                                        font: { size: 11, weight: '500' },
                                        maxRotation: 45,
                                        minRotation: 45
                                    },
                                    title: { 
                                        display: true, 
                                        text: 'Months', 
                                        font: { size: 10, weight: '500' },
                                        color: '#9ca3af',
                                        padding: { top: 10 }
                                    }
                                }
                            },
                            elements: {
                                line: { borderJoin: 'round', borderCap: 'round' },
                                point: { hitRadius: 10, hoverRadius: 8 }
                            },
                            layout: {
                                padding: { top: 15, bottom: 15, left: 10, right: 10 }
                            }
                        }
                    });
                }
            }

            // ==================== INITIALIZE ====================
            initDarkMode();
            initCharts();

            console.log('Admin Dashboard Initialized - Menu Bar Style Sidebar with Notifications');
        });
    </script>
</body>
</html>