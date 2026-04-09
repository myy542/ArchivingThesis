<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS A FACULTY
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// GET LOGGED-IN USER INFO FROM SESSION
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA FROM DATABASE
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
}

// Check if created_at column exists
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

// ==================== NOTIFICATION SYSTEM ====================
// Create notifications table if not exists (using 'notifications' table)
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    thesis_id INT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255) NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Determine the correct ID column name
$id_column = 'notification_id';
$check_id_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'notification_id'");
if (!$check_id_col || $check_id_col->num_rows == 0) {
    $check_id_col2 = $conn->query("SHOW COLUMNS FROM notifications LIKE 'id'");
    if ($check_id_col2 && $check_id_col2->num_rows > 0) {
        $id_column = 'id';
    }
}

// MARK NOTIFICATION AS READ (via AJAX)
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update_query = "UPDATE notifications SET is_read = 1 WHERE $id_column = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $notif_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL NOTIFICATIONS AS READ
if (isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET NOTIFICATION COUNT - Unread notifications only
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['count'];
}
$notif_stmt->close();

// GET RECENT NOTIFICATIONS for dropdown
$recentNotifications = [];
$notif_list_query = "SELECT $id_column as id, user_id, thesis_id, message, type, is_read, created_at 
                     FROM notifications 
                     WHERE user_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 10";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();
// ==================== END NOTIFICATION SYSTEM ====================

// GET STATISTICS - Using 'theses' table
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$archivedCount = 0;
$totalCount = 0;

try {
    // Check if theses table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'theses'");
    if ($table_check && $table_check->num_rows > 0) {
        $countsQuery = "SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
            COUNT(*) as total
        FROM theses";
        
        $countsResult = $conn->query($countsQuery);
        if ($countsResult) {
            $counts = $countsResult->fetch_assoc();
            $pendingCount = $counts['pending'] ?? 0;
            $approvedCount = $counts['approved'] ?? 0;
            $rejectedCount = $counts['rejected'] ?? 0;
            $archivedCount = $counts['archived'] ?? 0;
            $totalCount = $counts['total'] ?? 0;
        }
    }
} catch (Exception $e) {
    error_log("Faculty Dashboard - Counts error: " . $e->getMessage());
}

// GET PENDING THESES
$pendingTheses = [];
try {
    $query = "SELECT t.*, u.first_name, u.last_name, u.email 
              FROM theses t
              JOIN user_table u ON t.submitted_by = u.user_id
              WHERE t.status = 'pending'
              ORDER BY t.created_at DESC 
              LIMIT 10";
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $pendingTheses[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Faculty Dashboard - Thesis query error: " . $e->getMessage());
}

// GET ALL SUBMISSIONS
$allSubmissions = [];
$currentFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

try {
    $sql = "SELECT 
            t.*, 
            u.first_name, 
            u.last_name, 
            u.email,
            (SELECT COUNT(*) FROM feedback_table f WHERE f.thesis_id = t.thesis_id) as feedback_count,
            (SELECT MAX(feedback_date) FROM feedback_table f WHERE f.thesis_id = t.thesis_id) as last_feedback_date,
            (SELECT comments FROM feedback_table f WHERE f.thesis_id = t.thesis_id ORDER BY feedback_date DESC LIMIT 1) as latest_feedback
            FROM theses t
            JOIN user_table u ON t.submitted_by = u.user_id";
    
    if ($currentFilter != 'all') {
        $sql .= " WHERE t.status = '" . $conn->real_escape_string($currentFilter) . "'";
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $allSubmissions[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Faculty Dashboard - Submissions query error: " . $e->getMessage());
}

$pageTitle = "Faculty Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Chart.js -->
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

        body.dark-mode {
            background: #1a1a1a;
            color: #e0e0e0;
        }

        /* Top Navigation */
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            border-bottom: 1px solid #fee2e2;
        }

        body.dark-mode .top-nav {
            background: #2d2d2d;
            border-bottom-color: #991b1b;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .hamburger {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: #dc2626;
            border-radius: 2px;
        }

        .hamburger:hover {
            background: #fee2e2;
        }

        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #991b1b;
        }

        .logo span {
            color: #dc2626;
        }

        body.dark-mode .logo {
            color: #fecaca;
        }

        .search-area {
            display: flex;
            align-items: center;
            background: #fef2f2;
            padding: 8px 16px;
            border-radius: 40px;
            gap: 10px;
        }

        .search-area i {
            color: #dc2626;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
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
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .notification-dropdown.show {
            display: block;
            animation: fadeSlideDown 0.2s ease;
        }

        .notification-header {
            padding: 16px 20px;
            border-bottom: 1px solid #fee2e2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
        }

        .mark-all-read {
            font-size: 0.7rem;
            color: #dc2626;
            cursor: pointer;
        }

        .mark-all-read:hover {
            text-decoration: underline;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 20px;
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
            padding: 12px 20px;
            border-top: 1px solid #fee2e2;
            text-align: center;
        }

        .notification-footer a {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.8rem;
        }

        .notification-footer a:hover {
            text-decoration: underline;
        }

        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        body.dark-mode .notification-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .notification-header {
            border-bottom-color: #991b1b;
        }

        body.dark-mode .notification-header h3 {
            color: #fecaca;
        }

        body.dark-mode .notification-item {
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .notification-item:hover {
            background: #3d3d3d;
        }

        body.dark-mode .notification-item.unread {
            background: #3a1a1a;
        }

        body.dark-mode .notif-icon {
            background: #3d3d3d;
        }

        body.dark-mode .notif-message {
            color: #e5e7eb;
        }

        body.dark-mode .notification-footer {
            border-top-color: #991b1b;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.9rem;
        }

        body.dark-mode .profile-name {
            color: #fecaca;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
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
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            font-size: 0.85rem;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e0e0e0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar.open {
            left: 0;
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .logo-container .logo {
            color: white;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 6px;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #fecaca;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .notification-badge-sidebar {
            margin-left: auto;
            background: #ef4444;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .nav-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.15);
        }

        .theme-toggle {
            margin-bottom: 12px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .toggle-label i {
            font-size: 1rem;
            color: #fecaca;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #fecaca;
            border-radius: 10px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Main Content */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #851313, #900c0c);
            border-radius: 28px;
            padding: 32px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            color: white;
        }

        .welcome-info h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-info p {
            opacity: 0.8;
            font-size: 0.85rem;
        }

        .faculty-info {
            text-align: right;
        }

        .faculty-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .faculty-since {
            font-size: 0.7rem;
            opacity: 0.7;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        body.dark-mode .stat-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #ba0202;
        }

        body.dark-mode .stat-content h3 {
            color: #fecaca;
        }

        .stat-content p {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Theses Card */
        .theses-card, .submissions-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid #fee2e2;
        }

        body.dark-mode .theses-card,
        body.dark-mode .submissions-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark-mode .card-header h3 {
            color: #fecaca;
        }

        .view-all {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 6px 12px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #dc2626;
            color: white;
        }

        body.dark-mode .filter-btn {
            background: #3d3d3d;
            color: #fecaca;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 12px;
            color: #dc2626;
        }

        .theses-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .thesis-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #fef2f2;
            border-radius: 16px;
        }

        .thesis-item:hover {
            background: #fee2e2;
            transform: translateX(5px);
        }

        body.dark-mode .thesis-item {
            background: #3d3d3d;
        }

        .thesis-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        body.dark-mode .thesis-title {
            color: #e5e7eb;
        }

        .thesis-meta {
            display: flex;
            gap: 15px;
            font-size: 0.7rem;
            color: #6b7280;
        }

        .review-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-size: 0.75rem;
        }

        .review-btn:hover {
            background: #991b1b;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .theses-table {
            width: 100%;
            border-collapse: collapse;
        }

        .theses-table th {
            text-align: left;
            padding: 12px 8px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #fee2e2;
        }

        .theses-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #fef2f2;
            font-size: 0.85rem;
        }

        body.dark-mode .theses-table td {
            border-bottom-color: #3d3d3d;
            color: #e5e7eb;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .status-badge.status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-badge.status-archived {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn-review-small, .btn-view-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .btn-review-small {
            background: #dc2626;
            color: white;
        }

        .btn-view-small {
            background: #fef2f2;
            color: #dc2626;
        }

        .btn-view-small:hover {
            background: #fee2e2;
        }

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
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
            }
            .faculty-info {
                text-align: center;
            }
            .thesis-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-tabs {
                width: 100%;
                justify-content: center;
            }
            .notification-dropdown {
                width: 320px;
                right: -20px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            .stat-card {
                padding: 16px;
            }
            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.3rem;
            }
            .stat-content h3 {
                font-size: 1.5rem;
            }
            .notification-dropdown {
                width: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay for sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Top Navigation Bar -->
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search theses...">
            </div>
        </div>
        <div class="nav-right">
            <!-- Notification Dropdown -->
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge"><?= $notificationCount > 0 ? $notificationCount : '' ?></span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <span class="mark-all-read" id="markAllReadBtn">Mark all as read</span>
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
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>" data-thesis-id="<?= $notif['thesis_id'] ?>">
                                    <div class="notif-icon">
                                        <?php if(strpos($notif['message'], 'thesis') !== false): ?>
                                            <i class="fas fa-file-alt"></i>
                                        <?php elseif(strpos($notif['message'], 'feedback') !== false): ?>
                                            <i class="fas fa-comment-dots"></i>
                                        <?php else: ?>
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
                                            if($diff->days == 0) echo 'Today, ' . $date->format('h:i A');
                                            elseif($diff->days == 1) echo 'Yesterday, ' . $date->format('h:i A');
                                            else echo $date->format('M d, Y h:i A');
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <a href="notification.php">View all notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">RESEARCH ADVISER</div>
        </div>
        
        <div class="nav-menu">
            <a href="facultyDashboard.php" class="nav-item active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="reviewThesis.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Review Theses</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="notification-badge-sidebar"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="facultyFeedback.php" class="nav-item">
                <i class="fas fa-comment-dots"></i>
                <span>My Feedback</span>
            </a>
            <a href="notification.php" class="nav-item">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge-sidebar"><?= $notificationCount ?></span>
                <?php endif; ?>
            </a>
            <a href="archived_theses.php" class="nav-item">
                <i class="fas fa-archive"></i>
                <span>Archived Theses</span>
                <?php if ($archivedCount > 0): ?>
                    <span class="notification-badge-sidebar"><?= $archivedCount ?></span>
                <?php endif; ?>
            </a>
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
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>Research Adviser Dashboard</h1>
                <p><strong>FACULTY</strong> • Welcome back, <?= htmlspecialchars($first_name) ?>! • Overview of your advising and review activities</p>
            </div>
            <div class="faculty-info">
                <div class="faculty-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="faculty-since">Faculty since <?= $user_created ?></div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($pendingCount) ?></h3>
                    <p>Pending Review</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($approvedCount) ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($rejectedCount) ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-archive"></i></div>
                <div class="stat-content">
                    <h3><?= number_format($archivedCount) ?></h3>
                    <p>Archived</p>
                </div>
            </div>
        </div>

        <!-- Theses Waiting for Review -->
        <div class="theses-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Theses Waiting for Review</h3>
                <a href="reviewThesis.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php if (empty($pendingTheses)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending theses to review</p>
                </div>
            <?php else: ?>
                <div class="theses-list">
                    <?php foreach ($pendingTheses as $thesis): ?>
                    <div class="thesis-item">
                        <div class="thesis-info">
                            <div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div>
                            <div class="thesis-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['created_at'])) ?></span>
                            </div>
                        </div>
                        <a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="review-btn">Review <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Thesis Submissions -->
        <div class="submissions-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> All Thesis Submissions</h3>
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-btn <?= $currentFilter == 'all' ? 'active' : '' ?>">All (<?= $totalCount ?>)</a>
                    <a href="?status=pending" class="filter-btn <?= $currentFilter == 'pending' ? 'active' : '' ?>">Pending (<?= $pendingCount ?>)</a>
                    <a href="?status=approved" class="filter-btn <?= $currentFilter == 'approved' ? 'active' : '' ?>">Approved (<?= $approvedCount ?>)</a>
                    <a href="?status=rejected" class="filter-btn <?= $currentFilter == 'rejected' ? 'active' : '' ?>">Rejected (<?= $rejectedCount ?>)</a>
                    <a href="?status=archived" class="filter-btn <?= $currentFilter == 'archived' ? 'active' : '' ?>">Archived (<?= $archivedCount ?>)</a>
                </div>
            </div>
            <div class="table-responsive">
                <?php if (empty($allSubmissions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No thesis submissions yet.</p>
                    </div>
                <?php else: ?>
                    <table class="theses-table">
                        <thead>
                            <tr>
                                <th>Thesis Title</th>
                                <th>Student</th>
                                <th>Date Submitted</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th>Action</th>
                            </thead>
                        <tbody>
                            <?php foreach ($allSubmissions as $submission): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($submission['title']) ?></strong></td>
                                <td><?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($submission['created_at'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $submission['status'] ?>">
                                        <?= ucfirst($submission['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $submission['feedback_count'] ?> feedback(s)
                                    <?php if ($submission['last_feedback_date']): ?>
                                        <br><small>Last: <?= date('M d', strtotime($submission['last_feedback_date'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($submission['status'] == 'pending'): ?>
                                        <a href="reviewThesis.php?id=<?= $submission['thesis_id'] ?>" class="btn-review-small">
                                            <i class="fas fa-check-circle"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <a href="reviewThesis.php?id=<?= $submission['thesis_id'] ?>" class="btn-view-small">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Dark Mode
        const darkToggle = document.getElementById('darkmode');
        if (darkToggle) {
            darkToggle.addEventListener('change', () => {
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode', darkToggle.checked);
            });
            if (localStorage.getItem('darkMode') === 'true') {
                darkToggle.checked = true;
                document.body.classList.add('dark-mode');
            }
        }

        // Sidebar
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');

        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', toggleSidebar);

        // Profile Dropdown
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileWrapper) {
            profileWrapper.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (!profileWrapper?.contains(e.target) && profileDropdown) {
                profileDropdown.classList.remove('show');
            }
        });

        // ==================== NOTIFICATION FUNCTIONS ====================
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        const markAllReadBtn = document.getElementById('markAllReadBtn');

        // Toggle notification dropdown
        if (notificationIcon) {
            notificationIcon.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
        });

        // Mark single notification as read
        function markAsRead(notifId, element) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_read=1&notif_id=' + notifId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    let currentCount = parseInt(notificationBadge.textContent) || 0;
                    if (currentCount > 0) {
                        currentCount--;
                        if (currentCount === 0) {
                            notificationBadge.textContent = '';
                            notificationBadge.style.display = 'none';
                            if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                        } else {
                            notificationBadge.textContent = currentCount;
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Mark all as read
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
                    notificationBadge.textContent = '';
                    notificationBadge.style.display = 'none';
                    if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
        }

        // Add click event to notification items
        if (notificationList) {
            notificationList.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                if (notificationItem && !notificationItem.classList.contains('empty')) {
                    const notifId = notificationItem.dataset.id;
                    const thesisId = notificationItem.dataset.thesisId;
                    
                    if (notifId) {
                        markAsRead(notifId, notificationItem);
                    }
                    
                    if (thesisId) {
                        setTimeout(() => {
                            window.location.href = 'reviewThesis.php?id=' + thesisId;
                        }, 300);
                    }
                }
            });
        }

        // Mark all as read button
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                markAllAsRead();
            });
        }

        // Initial badge display
        if (notificationBadge && notificationBadge.textContent === '') {
            notificationBadge.style.display = 'none';
        } else if (notificationBadge) {
            notificationBadge.style.display = 'flex';
        }
        // ==================== END NOTIFICATION FUNCTIONS ====================

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.theses-table tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>