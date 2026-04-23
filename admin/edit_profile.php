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
    $user_phone = $user_data['contact_number'] ?? '';
    $user_address = $user_data['address'] ?? '';
    $user_birth_date = $user_data['birth_date'] ?? '';
    $user_role_id = $user_data['role_id'];
    $user_status = $user_data['status'];
}

// Member since - default current date
$user_created = date('F Y');

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

// HANDLE FORM SUBMISSION
$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name = trim($_POST['last_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['contact_number'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    $new_birth_date = trim($_POST['birth_date'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($new_first_name)) $errors[] = "First name is required.";
    if (empty($new_last_name)) $errors[] = "Last name is required.";
    if (empty($new_email)) $errors[] = "Email is required.";
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    
    // Check if email already exists for another user
    $check_email = $conn->prepare("SELECT user_id FROM user_table WHERE email = ? AND user_id != ?");
    $check_email->bind_param("si", $new_email, $user_id);
    $check_email->execute();
    $check_email->store_result();
    if ($check_email->num_rows > 0) {
        $errors[] = "Email already used by another user.";
    }
    $check_email->close();
    
    if (empty($errors)) {
        $update_query = "UPDATE user_table SET first_name = ?, last_name = ?, email = ?, contact_number = ?, address = ?, birth_date = ? WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ssssssi", $new_first_name, $new_last_name, $new_email, $new_phone, $new_address, $new_birth_date, $user_id);
        
        if ($update_stmt->execute()) {
            // Update session variables
            $_SESSION['first_name'] = $new_first_name;
            $_SESSION['last_name'] = $new_last_name;
            
            $message = "Profile updated successfully!";
            $message_type = "success";
            
            // Refresh user data
            $fullName = $new_first_name . " " . $new_last_name;
            $initials = strtoupper(substr($new_first_name, 0, 1) . substr($new_last_name, 0, 1));
            $user_email = $new_email;
            $user_phone = $new_phone;
            $user_address = $new_address;
            $user_birth_date = $new_birth_date;
            
            // Redirect to profile page after 2 seconds
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'profile.php';
                }, 2000);
            </script>";
        } else {
            $message = "Error updating profile: " . $conn->error;
            $message_type = "error";
        }
        $update_stmt->close();
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

$user_role = "System Administrator";

$pageTitle = "Edit Profile";
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

        /* Edit Profile Container */
        .edit-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .edit-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .edit-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }

        .edit-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .edit-card .subtitle {
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #fee2e2;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert i {
            font-size: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
            color: #1f2937;
        }

        .form-group label i {
            color: #dc2626;
            margin-right: 6px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #fee2e2;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            transition: all 0.2s;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-group input:disabled {
            background: #fef2f2;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #fee2e2;
        }

        .btn-save {
            flex: 1;
            padding: 12px 24px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-save:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .btn-cancel {
            flex: 1;
            padding: 12px 24px;
            background: #fef2f2;
            color: #6b7280;
            border: 1px solid #fee2e2;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .edit-card {
                padding: 20px;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .search-area {
                display: none;
            }
            .profile-name {
                display: none;
            }
            .form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            .edit-card h2 {
                font-size: 1.3rem;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .top-nav,
        body.dark-mode .edit-card,
        body.dark-mode .notification-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .edit-card h2,
        body.dark-mode .form-group label {
            color: #fecaca;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group textarea {
            background: #3d3d3d;
            border-color: #991b1b;
            color: white;
        }

        body.dark-mode .form-group input:focus {
            border-color: #dc2626;
        }

        body.dark-mode .btn-cancel {
            background: #3d3d3d;
            color: #e5e7eb;
            border-color: #991b1b;
        }

        body.dark-mode .btn-cancel:hover {
            background: #4a4a4a;
        }

        body.dark-mode .search-area,
        body.dark-mode .activity-icon {
            background: #3d3d3d;
        }

        body.dark-mode .search-area input {
            background: #3d3d3d;
            color: white;
        }

        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
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
                    <a href="edit_profile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
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
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
            <p>Update your personal information</p>
        </div>

        <div class="edit-container">
            <div class="edit-card">
                <h2><i class="fas fa-user-circle"></i> Personal Information</h2>
                <p class="subtitle">Update your account details</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $message_type ?>">
                        <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user_email) ?>" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user-tag"></i> Username</label>
                        <input type="text" value="<?= htmlspecialchars($username) ?>" disabled>
                        <small style="color: #6b7280; font-size: 0.7rem;">Username cannot be changed</small>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="tel" name="contact_number" value="<?= htmlspecialchars($user_phone) ?>" placeholder="Enter your phone number">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($user_address) ?>" placeholder="Enter your address">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Birth Date</label>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($user_birth_date) ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Role</label>
                        <input type="text" value="<?= $user_role ?>" disabled>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="profile.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
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
        console.log('Admin Edit Profile Page Initialized');
    </script>
</body>
</html>