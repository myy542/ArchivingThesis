<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS ADMIN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA FROM DATABASE
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status, contact_number, address, birth_date FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
    $user_phone = $user_data['contact_number'] ?? 'Not provided';
    $user_address = $user_data['address'] ?? 'Not provided';
    $user_birth_date = $user_data['birth_date'] ?? '';
    $user_role_id = $user_data['role_id'];
    $user_status = $user_data['status'];
}

// Member since - default current date (since wala'y created_at)
$user_created = date('F Y');

// GET STATISTICS FOR ADMIN
$stats = [];

// Total users
$total_users = $conn->query("SELECT COUNT(*) as c FROM user_table")->fetch_assoc()['c'];
$active_users = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE status = 'Active'")->fetch_assoc()['c'];
$inactive_users = $total_users - $active_users;

// Total theses
$theses_count = 0;
$check_theses_table = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_theses_table && $check_theses_table->num_rows > 0) {
    $theses_count = $conn->query("SELECT COUNT(*) as c FROM thesis_table")->fetch_assoc()['c'];
}

// Total departments
$departments_count = 0;
$check_dept_table = $conn->query("SHOW TABLES LIKE 'department_table'");
if ($check_dept_table && $check_dept_table->num_rows > 0) {
    $departments_count = $conn->query("SELECT COUNT(*) as c FROM department_table")->fetch_assoc()['c'];
}

$stats = [
    'total_users' => $total_users,
    'active_users' => $active_users,
    'inactive_users' => $inactive_users,
    'total_theses' => $theses_count,
    'total_departments' => $departments_count
];

// GET RECENT ACTIVITIES - FIXED: removed created_at from user_table
$recent_activities = [];

// Get recent user registrations (using latest user_id as proxy for newest users since wala'y created_at)
$user_activities = $conn->query("SELECT user_id, first_name, last_name FROM user_table ORDER BY user_id DESC LIMIT 3");
if ($user_activities && $user_activities->num_rows > 0) {
    while ($row = $user_activities->fetch_assoc()) {
        $recent_activities[] = [
            'icon' => 'user-plus',
            'action' => 'New user registered',
            'title' => $row['first_name'] . ' ' . $row['last_name'],
            'date' => date('M d, Y') // current date since wala'y created_at
        ];
    }
}

// Get recent thesis submissions
$thesis_activities = $conn->query("SELECT thesis_id, title, date_submitted FROM thesis_table ORDER BY date_submitted DESC LIMIT 3");
if ($thesis_activities && $thesis_activities->num_rows > 0) {
    while ($row = $thesis_activities->fetch_assoc()) {
        $recent_activities[] = [
            'icon' => 'file-alt',
            'action' => 'New thesis submitted',
            'title' => substr($row['title'], 0, 50) . (strlen($row['title']) > 50 ? '...' : ''),
            'date' => date('M d, Y', strtotime($row['date_submitted']))
        ];
    }
}

// Sort activities by date (most recent first)
usort($recent_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activities = array_slice($recent_activities, 0, 5);

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

// GET RECENT NOTIFICATIONS FOR DROPDOWN
$recentNotifications = [];
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    if ($row['thesis_id']) {
        $thesis_q = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
        $thesis_q->bind_param("i", $row['thesis_id']);
        $thesis_q->execute();
        $thesis_title = $thesis_q->get_result()->fetch_assoc();
        $row['thesis_title'] = $thesis_title['title'] ?? 'Unknown';
        $thesis_q->close();
    }
    $recentNotifications[] = $row;
}
$notif_list->close();

$user_role = "System Administrator";
$user_bio = "Experienced system administrator responsible for managing the Thesis Archiving System. Ensures smooth operation, user management, and data integrity.";

$pageTitle = "My Profile";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
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

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

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
            align-items: center;
            justify-content: center;
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
            padding: 5px 10px;
            border-radius: 40px;
        }

        .profile-trigger:hover {
            background: #fee2e2;
        }

        .profile-name {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
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
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
            color: #dc2626;
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
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
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

        .nav-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.15);
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

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            color: #6b7280;
            margin-top: 5px;
        }

        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
        }

        /* Left Column */
        .profile-left {
            position: sticky;
            top: 90px;
            height: fit-content;
        }

        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            text-align: center;
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .profile-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .profile-avatar-large {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .profile-card h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .user-role {
            color: #dc2626;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            padding: 20px 0;
            border-top: 1px solid #fee2e2;
            border-bottom: 1px solid #fee2e2;
            margin-bottom: 20px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
        }

        .btn-edit {
            flex: 1;
            padding: 10px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            background: #dc2626;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-edit:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .btn-change-password {
            flex: 1;
            padding: 10px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-change-password:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Right Column */
        .info-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #fee2e2;
        }

        .card-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            width: 140px;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .info-value {
            flex: 1;
            color: #1f2937;
            font-size: 0.85rem;
        }

        .bio-text {
            color: #4b5563;
            line-height: 1.6;
            font-size: 0.85rem;
        }

        .view-all {
            font-size: 0.75rem;
            color: #dc2626;
            text-decoration: none;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .activity-item {
            display: flex;
            gap: 14px;
            padding: 8px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
        }

        .activity-details {
            flex: 1;
        }

        .activity-action {
            font-weight: 600;
            font-size: 0.85rem;
            color: #1f2937;
        }

        .activity-title {
            font-size: 0.8rem;
            color: #6b7280;
            margin: 4px 0;
        }

        .activity-time {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1100;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            width: 500px;
            max-width: 90%;
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #fee2e2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #991b1b;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
        }

        .modal-close:hover {
            color: #dc2626;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #fee2e2;
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #fee2e2;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel {
            background: #fef2f2;
            color: #6b7280;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-save {
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-save:hover {
            background: #991b1b;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .profile-container {
                grid-template-columns: 1fr;
            }
            .profile-left {
                position: relative;
                top: 0;
            }
            .search-area {
                display: none;
            }
            .profile-name {
                display: none;
            }
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
            .info-label {
                width: 100%;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .top-nav,
        body.dark-mode .profile-card,
        body.dark-mode .info-card,
        body.dark-mode .modal-content {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-card h2,
        body.dark-mode .info-value,
        body.dark-mode .activity-action,
        body.dark-mode .form-group label {
            color: #e5e7eb;
        }

        body.dark-mode .info-label,
        body.dark-mode .activity-title {
            color: #9ca3af;
        }

        body.dark-mode .search-area,
        body.dark-mode .activity-icon {
            background: #3d3d3d;
        }

        body.dark-mode .search-area input {
            background: #3d3d3d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn">
                <span></span><span></span><span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
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
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>" data-link="<?= htmlspecialchars($notif['link'] ?? '#') ?>">
                                    <div class="notif-icon">
                                        <?php if(strpos($notif['message'], 'registration') !== false): ?>
                                            <i class="fas fa-user-plus"></i>
                                        <?php elseif(strpos($notif['message'], 'thesis') !== false): ?>
                                            <i class="fas fa-file-alt"></i>
                                        <?php elseif(strpos($notif['message'], 'approved') !== false): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php elseif(strpos($notif['message'], 'rejected') !== false): ?>
                                            <i class="fas fa-times-circle"></i>
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
            <div class="logo-sub">ADMINISTRATOR</div>
        </div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="audit_logs.php" class="nav-item">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </a>
            <a href="theses.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Theses</span>
            </a>
            <a href="backup_management.php" class="nav-item">
                <i class="fas fa-database"></i>
                <span>Backup</span>
            </a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-cog"></i> My Profile</h1>
            <p>View and manage your personal information</p>
        </div>

        <div class="profile-container">
            <!-- Left Column -->
            <div class="profile-left">
                <div class="profile-card">
                    <div class="profile-avatar-large">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <h2><?= htmlspecialchars($fullName) ?></h2>
                    <p class="user-role"><?= $user_role ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['active_users']) ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['total_theses']) ?></div>
                            <div class="stat-label">Total Theses</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($stats['total_departments']) ?></div>
                            <div class="stat-label">Departments</div>
                        </div>
                    </div>
                    
                    <div class="profile-actions">
                        <a href="edit_profile.php" class="btn-edit">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="change_password.php" class="btn-change-password">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="profile-right">
                <!-- Personal Information -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                    </div>
                    <div class="info-content">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?= htmlspecialchars($fullName) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email Address:</span>
                            <span class="info-value"><?= htmlspecialchars($user_email) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?= htmlspecialchars($username) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone Number:</span>
                            <span class="info-value"><?= htmlspecialchars($user_phone) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?= htmlspecialchars($user_address) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Birth Date:</span>
                            <span class="info-value"><?= $user_birth_date ? date('F d, Y', strtotime($user_birth_date)) : 'Not provided' ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Role:</span>
                            <span class="info-value"><?= $user_role ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value"><?= htmlspecialchars($user_status) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Member Since:</span>
                            <span class="info-value"><?= $user_created ?></span>
                        </div>
                    </div>
                </div>

                <!-- Bio -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> About Me</h3>
                    </div>
                    <div class="info-content">
                        <p class="bio-text"><?= htmlspecialchars($user_bio) ?></p>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Activity</h3>
                        <a href="audit_logs.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-<?= $activity['icon'] ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-action"><?= htmlspecialchars($activity['action']) ?></div>
                                <div class="activity-title"><?= htmlspecialchars($activity['title']) ?></div>
                                <div class="activity-time"><?= htmlspecialchars($activity['date']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
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

        // Sidebar Functions
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        function toggleSidebar(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // Profile Dropdown
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
        }

        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }

        // Notification Dropdown
        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        }

        function closeNotificationDropdown(e) {
            if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        }

        if (notificationIcon) {
            notificationIcon.addEventListener('click', toggleNotificationDropdown);
            document.addEventListener('click', closeNotificationDropdown);
        }

        // Mark notification as read
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
        }

        // Dark Mode
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

        initDarkMode();
        console.log('Admin Profile Page Initialized');
    </script>
</body>
</html>