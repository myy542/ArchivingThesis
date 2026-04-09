<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// GET USER DATA
$user_query = "SELECT first_name, last_name, email FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$first_name = $user_data['first_name'] ?? '';
$last_name = $user_data['last_name'] ?? '';
$fullName = trim($first_name . " " . $last_name);
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
$user_stmt->close();

// Set role display name and dashboard link
$role_display = '';
$dashboard_link = '';
switch($role) {
    case 'dean': 
        $role_display = 'DEAN'; 
        $dashboard_link = 'dean.php';
        break;
    case 'coordinator': 
        $role_display = 'COORDINATOR'; 
        $dashboard_link = 'coordinatorDashboard.php';
        break;
    case 'faculty': 
        $role_display = 'FACULTY'; 
        $dashboard_link = 'facultyDashboard.php';
        break;
    case 'librarian': 
        $role_display = 'LIBRARIAN'; 
        $dashboard_link = 'librarian_dashboard.php';
        break;
    case 'admin': 
        $role_display = 'ADMIN'; 
        $dashboard_link = 'admindashboard.php';
        break;
    default: 
        $role_display = strtoupper($role);
        $dashboard_link = 'dashboard.php';
}

// CREATE NOTIFICATIONS TABLE IF NOT EXISTS
$check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check_notif || $check_notif->num_rows == 0) {
    $conn->query("CREATE TABLE notifications (
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
    )");
}

// Get the correct ID column name
$id_column = 'notification_id';
$check_id = $conn->query("SHOW COLUMNS FROM notifications LIKE 'id'");
if ($check_id && $check_id->num_rows > 0) {
    $id_column = 'id';
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// GET TOTAL NOTIFICATIONS COUNT
$count_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_notifications = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = ceil($total_notifications / $limit);

// GET NOTIFICATIONS WITH PAGINATION
$notifications = [];
$notif_query = "SELECT $id_column as id, user_id, thesis_id, message, type, link, is_read, created_at 
                FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("iii", $user_id, $limit, $offset);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    // Get thesis title if thesis_id exists
    if ($row['thesis_id']) {
        $thesis_q = $conn->prepare("SELECT title FROM theses WHERE thesis_id = ?");
        $thesis_q->bind_param("i", $row['thesis_id']);
        $thesis_q->execute();
        $thesis_result = $thesis_q->get_result();
        if ($thesis_row = $thesis_result->fetch_assoc()) {
            $row['thesis_title'] = $thesis_row['title'];
        }
        $thesis_q->close();
    }
    $notifications[] = $row;
}
$notif_stmt->close();

// GET UNREAD COUNT FOR BADGE
$unread_count = 0;
$unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['count'] ?? 0;
$unread_stmt->close();

// MARK SINGLE NOTIFICATION AS READ
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE $id_column = ? AND user_id = ?");
    $update->bind_param("ii", $notif_id, $user_id);
    $update->execute();
    $update->close();
    
    header("Location: notifications.php?page=" . $page);
    exit;
}

// MARK ALL NOTIFICATIONS AS READ
if (isset($_GET['mark_all_read'])) {
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();
    
    header("Location: notifications.php?page=1");
    exit;
}

// DELETE NOTIFICATION
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $delete = $conn->prepare("DELETE FROM notifications WHERE $id_column = ? AND user_id = ?");
    $delete->bind_param("ii", $notif_id, $user_id);
    $delete->execute();
    $delete->close();
    
    header("Location: notifications.php?page=" . $page);
    exit;
}

// DELETE ALL NOTIFICATIONS
if (isset($_GET['delete_all'])) {
    $delete = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $delete->bind_param("i", $user_id);
    $delete->execute();
    $delete->close();
    
    header("Location: notifications.php?page=1");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; }
        
        .top-nav { 
            position: fixed; top: 0; left: 0; right: 0; height: 70px; background: white; 
            display: flex; align-items: center; justify-content: space-between; padding: 0 32px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #ffcdd2; 
        }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; align-items: center; justify-content: center; }
        .hamburger span { width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .profile-name { font-weight: 500; color: #1f2937; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #991b1b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 180px; display: none; border: 1px solid #ffcdd2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; transition: 0.2s; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        
        .sidebar { 
            position: fixed; top: 0; left: -300px; width: 280px; height: 100%; 
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); 
            display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; 
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .logo-container .logo { color: white; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 5px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 8px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; transition: 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.2); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h1 i {
            color: #dc2626;
        }
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        .btn-mark-all, .btn-delete-all {
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-mark-all {
            background: #10b981;
            color: white;
            border: none;
        }
        .btn-mark-all:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .btn-delete-all {
            background: #dc2626;
            color: white;
            border: none;
        }
        .btn-delete-all:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }
        
        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .stat-icon {
            width: 55px;
            height: 55px;
            background: #fef2f2;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #dc2626;
        }
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
        }
        .stat-info p {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        /* Notifications List */
        .notifications-container {
            background: white;
            border-radius: 24px;
            border: 1px solid #ffcdd2;
            overflow: hidden;
        }
        .notification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #fef2f2;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #fef2f2;
        }
        .notification-item.unread {
            background: #fff5f5;
            border-left: 4px solid #dc2626;
        }
        .notification-icon-wrapper {
            width: 50px;
            height: 50px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            flex-shrink: 0;
        }
        .notification-icon-wrapper i {
            font-size: 1.3rem;
            color: #dc2626;
        }
        .notification-content {
            flex: 1;
        }
        .notification-message {
            font-size: 0.9rem;
            color: #1f2937;
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .notification-meta {
            display: flex;
            gap: 20px;
            font-size: 0.7rem;
            color: #9ca3af;
        }
        .notification-meta i {
            margin-right: 4px;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-mark, .btn-view, .btn-delete {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-mark {
            background: #10b981;
            color: white;
            border: none;
        }
        .btn-mark:hover {
            background: #059669;
        }
        .btn-view {
            background: #3b82f6;
            color: white;
        }
        .btn-view:hover {
            background: #2563eb;
        }
        .btn-delete {
            background: #dc2626;
            color: white;
            border: none;
        }
        .btn-delete:hover {
            background: #991b1b;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #dc2626;
            margin-bottom: 16px;
        }
        .empty-state p {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 24px;
            border-top: 1px solid #fef2f2;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .pagination a {
            background: #fef2f2;
            color: #dc2626;
        }
        .pagination a:hover {
            background: #dc2626;
            color: white;
        }
        .pagination .active {
            background: #dc2626;
            color: white;
        }
        .pagination .disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }
        
        /* Back Button - Improved */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .back-button i {
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }
        .back-button:hover {
            background: #991b1b;
            transform: translateX(-5px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }
        .back-button:hover i {
            transform: translateX(-3px);
        }
        
        @media (max-width: 768px) {
            .stats-cards { grid-template-columns: 1fr; gap: 16px; }
            .notification-item { flex-direction: column; align-items: flex-start; gap: 15px; }
            .notification-actions { align-self: flex-end; }
            .action-buttons { flex-direction: column; width: 100%; }
            .btn-mark-all, .btn-delete-all { justify-content: center; }
            .main-content { padding: 20px; }
            .search-area, .profile-name { display: none; }
            .back-button { padding: 8px 16px; font-size: 0.8rem; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav, body.dark-mode .stat-card, body.dark-mode .notifications-container { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .logo, body.dark-mode .stat-info h3, body.dark-mode .page-header h1 { color: #fecaca; }
        body.dark-mode .notification-item { border-bottom-color: #3d3d3d; }
        body.dark-mode .notification-item:hover { background: #3d3d3d; }
        body.dark-mode .notification-item.unread { background: #3a2a2a; }
        body.dark-mode .notification-message { color: #e5e7eb; }
        body.dark-mode .profile-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .back-button { background: #dc2626; }
        body.dark-mode .back-button:hover { background: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
        </div>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-trigger">
                <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                <hr>
                <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </header>
    
    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub"><?= $role_display ?></div></div>
        <div class="nav-menu">
            <?php if ($role == 'dean'): ?>
                <a href="dean.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="approvedTheses.php" class="nav-item"><i class="fas fa-check-circle"></i> Approved Theses</a>
            <?php elseif ($role == 'coordinator'): ?>
                <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i> Forwarded to Dean</a>
            <?php elseif ($role == 'faculty'): ?>
                <a href="facultyDashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="submitThesis.php" class="nav-item"><i class="fas fa-upload"></i> Submit Thesis</a>
                <a href="myTheses.php" class="nav-item"><i class="fas fa-file-alt"></i> My Theses</a>
            <?php elseif ($role == 'librarian'): ?>
                <a href="librarian_dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="archivedTheses.php" class="nav-item"><i class="fas fa-archive"></i> Archived Theses</a>
            <?php elseif ($role == 'admin'): ?>
                <a href="admindashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
                <a href="users.php" class="nav-item"><i class="fas fa-users"></i> Users</a>
                <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i> Audit Logs</a>
            <?php endif; ?>
            <a href="notifications.php" class="nav-item active"><i class="fas fa-bell"></i> Notifications</a>
        </div>
        <div class="nav-footer">
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </aside>
    
    <main class="main-content">
        <!-- Improved Back Button -->
        <a href="<?= $dashboard_link ?>" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <div class="action-buttons">
                <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn-mark-all" onclick="return confirm('Mark all notifications as read?')">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                <?php endif; ?>
                <?php if ($total_notifications > 0): ?>
                    <a href="notifications.php?delete_all=1" class="btn-delete-all" onclick="return confirm('Delete all notifications? This action cannot be undone.')">
                        <i class="fas fa-trash-alt"></i> Delete All
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <h3><?= $total_notifications ?></h3>
                    <p>Total Notifications</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope-open"></i></div>
                <div class="stat-info">
                    <h3><?= $total_notifications - $unread_count ?></h3>
                    <p>Read</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-info">
                    <h3><?= $unread_count ?></h3>
                    <p>Unread</p>
                </div>
            </div>
        </div>
        
        <!-- Notifications List -->
        <div class="notifications-container">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet.</p>
                    <p style="font-size: 0.8rem; margin-top: 8px;">When you receive notifications, they will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>">
                        <div style="display: flex; align-items: center; flex: 1;">
                            <div class="notification-icon-wrapper">
                                <?php 
                                    $icon = 'fa-bell';
                                    if (strpos($notif['message'], 'approved') !== false) $icon = 'fa-check-circle';
                                    elseif (strpos($notif['message'], 'forwarded') !== false) $icon = 'fa-arrow-right';
                                    elseif (strpos($notif['message'], 'revision') !== false) $icon = 'fa-edit';
                                    elseif (strpos($notif['message'], 'archived') !== false) $icon = 'fa-archive';
                                    elseif (strpos($notif['message'], 'submitted') !== false) $icon = 'fa-file-alt';
                                ?>
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </div>
                                <div class="notification-meta">
                                    <span><i class="far fa-clock"></i> <?= date('F d, Y h:i A', strtotime($notif['created_at'])) ?></span>
                                    <?php if (isset($notif['thesis_title'])): ?>
                                        <span><i class="fas fa-book"></i> Thesis: <?= htmlspecialchars($notif['thesis_title']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($notif['is_read'] == 0): ?>
                                <a href="notifications.php?mark_read=1&id=<?= $notif['id'] ?>&page=<?= $page ?>" class="btn-mark">
                                    <i class="fas fa-check"></i> Mark Read
                                </a>
                            <?php endif; ?>
                            <?php if ($notif['thesis_id']): ?>
                                <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Thesis
                                </a>
                            <?php endif; ?>
                            <a href="notifications.php?delete=1&id=<?= $notif['id'] ?>&page=<?= $page ?>" class="btn-delete" onclick="return confirm('Delete this notification?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="notifications.php?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="notifications.php?page=<?= $i ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="notifications.php?page=<?= $page + 1 ?>">Next <i class="fas fa-chevron-right"></i></a>
                        <?php else: ?>
                            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const profileWrap = document.getElementById('profileWrapper');
        const profileDrop = document.getElementById('profileDropdown');
        
        hamburger?.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); });
        overlay?.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });
        profileWrap?.addEventListener('click', (e) => { e.stopPropagation(); profileDrop.classList.toggle('show'); });
        document.addEventListener('click', () => profileDrop?.classList.remove('show'));
        
        // Dark mode
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) document.body.classList.add('dark-mode');
        
        // Close sidebar on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        });
    </script>
</body>
</html>