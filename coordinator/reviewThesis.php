<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get thesis ID from URL
$thesis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// CHECK IF NOTIFICATIONS TABLE EXISTS, CREATE IF NOT
$check_notif_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check_notif_table || $check_notif_table->num_rows == 0) {
    $create_notif_table = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            thesis_id INT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            link VARCHAR(255) NULL,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($create_notif_table);
}

// Mark notification as read when viewing this thesis
if ($thesis_id > 0) {
    $update_notif = "UPDATE notifications SET is_read = 1 WHERE thesis_id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_notif);
    $update_stmt->bind_param("ii", $thesis_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Get thesis details from database
$thesis = null;
$thesis_title = 'Unknown Thesis';
$thesis_author = 'Unknown Author';
$thesis_abstract = 'No abstract available.';
$thesis_file = '';
$thesis_date = '';
$thesis_status = '';

if ($thesis_id > 0) {
    $thesis_query = "SELECT thesis_id, title, author, abstract, file_path, created_at, status FROM theses WHERE thesis_id = ?";
    $thesis_stmt = $conn->prepare($thesis_query);
    $thesis_stmt->bind_param("i", $thesis_id);
    $thesis_stmt->execute();
    $thesis_result = $thesis_stmt->get_result();
    if ($thesis_row = $thesis_result->fetch_assoc()) {
        $thesis = $thesis_row;
        $thesis_title = $thesis_row['title'];
        $thesis_author = $thesis_row['author'] ?? 'Unknown Author';
        $thesis_abstract = $thesis_row['abstract'] ?? 'No abstract available.';
        $thesis_file = $thesis_row['file_path'] ?? '';
        $thesis_date = isset($thesis_row['created_at']) ? date('M d, Y', strtotime($thesis_row['created_at'])) : date('M d, Y');
        $thesis_status = $thesis_row['status'] ?? 'Pending';
    }
    $thesis_stmt->close();
}

// Get notification message for this thesis - GAMIT ANG NOTIFICATIONS TABLE
$notification_message = '';
$notif_query = "SELECT message FROM notifications WHERE thesis_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 1";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("ii", $thesis_id, $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notification_message = $notif_row['message'];
}
$notif_stmt->close();

// Process form submission - Forward to Dean
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_to_dean'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    
    // Update thesis status to 'Forwarded to Dean'
    $update_query = "UPDATE theses SET status = 'Forwarded to Dean' WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Update notification status - GAMIT ANG NOTIFICATIONS TABLE
    $update_notif = "UPDATE notifications SET is_read = 1 WHERE thesis_id = ? AND user_id = ?";
    $update_notif_stmt = $conn->prepare($update_notif);
    $update_notif_stmt->bind_param("ii", $thesis_id_post, $user_id);
    $update_notif_stmt->execute();
    $update_notif_stmt->close();
    
    // Add notification for Dean (role_id = 4) - GAMIT ANG NOTIFICATIONS TABLE
    $dean_query = "SELECT user_id FROM user_table WHERE role_id = 4";
    $dean_result = $conn->query($dean_query);
    if ($dean_result && $dean_result->num_rows > 0) {
        $thesis_title_notif = $thesis_title;
        $notifMessage = "📢 New thesis has been forwarded to you: \"" . $thesis_title_notif . "\" by Coordinator " . $fullName . ". Please review for final approval.";
        
        while ($dean = $dean_result->fetch_assoc()) {
            $dean_id = $dean['user_id'];
            $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_forward', 0, NOW())";
            $insert_stmt = $conn->prepare($insert_notif);
            $insert_stmt->bind_param("iis", $dean_id, $thesis_id_post, $notifMessage);
            $insert_stmt->execute();
            $insert_stmt->close();
        }
    }
    
    header("Location: coordinatorDashboard.php?msg=forwarded");
    exit;
}

// Process form submission - Request Revisions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_revisions'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $revision_feedback = trim($_POST['revision_feedback']);
    
    // Update thesis status to 'Pending' (back to faculty)
    $update_query = "UPDATE theses SET status = 'Pending', feedback = ? WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $revision_feedback, $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Update notification status - GAMIT ANG NOTIFICATIONS TABLE
    $update_notif = "UPDATE notifications SET is_read = 1 WHERE thesis_id = ? AND user_id = ?";
    $update_notif_stmt = $conn->prepare($update_notif);
    $update_notif_stmt->bind_param("ii", $thesis_id_post, $user_id);
    $update_notif_stmt->execute();
    $update_notif_stmt->close();
    
    // Get faculty who submitted this thesis - GAMIT ANG NOTIFICATIONS TABLE
    // Kuhaon ang faculty nga nag-submit (author sa thesis)
    $faculty_query = "SELECT author FROM theses WHERE thesis_id = ?";
    $faculty_stmt = $conn->prepare($faculty_query);
    $faculty_stmt->bind_param("i", $thesis_id_post);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();
    $author_name = '';
    if ($faculty_row = $faculty_result->fetch_assoc()) {
        $author_name = $faculty_row['author'];
    }
    $faculty_stmt->close();
    
    // Find faculty user_id by name
    $faculty_id = null;
    if (!empty($author_name)) {
        $name_parts = explode(' ', $author_name);
        $first = $name_parts[0] ?? '';
        $last = $name_parts[1] ?? '';
        
        $find_faculty = "SELECT user_id FROM user_table WHERE role_id = 3 AND first_name LIKE ? AND last_name LIKE ?";
        $find_stmt = $conn->prepare($find_faculty);
        $like_first = "%$first%";
        $like_last = "%$last%";
        $find_stmt->bind_param("ss", $like_first, $like_last);
        $find_stmt->execute();
        $find_result = $find_stmt->get_result();
        if ($find_row = $find_result->fetch_assoc()) {
            $faculty_id = $find_row['user_id'];
        }
        $find_stmt->close();
    }
    
    // If faculty found, send notification
    if ($faculty_id) {
        $notifMessage = "📝 Revision requested for thesis: \"" . $thesis_title . "\". Coordinator feedback: " . $revision_feedback . ". Please revise and resubmit.";
        $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'revision_request', 0, NOW())";
        $insert_stmt = $conn->prepare($insert_notif);
        $insert_stmt->bind_param("iis", $faculty_id, $thesis_id_post, $notifMessage);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    header("Location: coordinatorDashboard.php?msg=revision");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Review Thesis | Thesis Management System</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            border-bottom: 1px solid #fee2e2;
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
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            font-size: 0.9rem;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
            color: #1f2937;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
        }

        /* Profile */
        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 0;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
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
            font-size: 0.9rem;
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
            animation: fadeSlideDown 0.2s ease;
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

        .profile-dropdown a i {
            width: 20px;
            color: #6b7280;
        }

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #fee2e2;
        }

        /* Sidebar - COLLAPSIBLE (hidden by default) */
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
            font-size: 1.3rem;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 6px;
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
            color: #fecaca;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
            font-size: 1.1rem;
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
            padding: 8px 0;
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
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        /* Sidebar Overlay */
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

        /* Main Content - full width */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #991b1b;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #dc2626;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 8px 16px;
            background: #fef2f2;
            border-radius: 30px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: #fee2e2;
            transform: translateX(-3px);
        }

        /* Notification Alert */
        .notification-alert {
            background: #fff5f5;
            border-left: 4px solid #dc2626;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .notification-alert i {
            font-size: 1.5rem;
            color: #dc2626;
        }
        .notification-alert-content {
            flex: 1;
        }
        .notification-alert-title {
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 5px;
        }
        .notification-alert-message {
            font-size: 0.85rem;
            color: #4b5563;
        }

        /* Thesis Detail Card */
        .thesis-detail-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid #fee2e2;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .thesis-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #fee2e2;
        }

        .thesis-meta {
            display: flex;
            gap: 24px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .meta-item i {
            color: #dc2626;
            width: 16px;
        }

        .abstract-section {
            margin-bottom: 24px;
        }

        .abstract-section h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .abstract-section p {
            color: #4b5563;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .file-section {
            background: #fef2f2;
            border-radius: 16px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .file-info i {
            font-size: 1.5rem;
            color: #dc2626;
        }

        .file-details {
            display: flex;
            flex-direction: column;
        }

        .file-name {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.9rem;
        }

        .file-size {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: white;
            color: #dc2626;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 500;
            font-size: 0.8rem;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .download-btn:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }
        .status-forwarded {
            background: #dbeafe;
            color: #2563eb;
        }
        .status-approved {
            background: #d1fae5;
            color: #059669;
        }

        /* Action Cards */
        .action-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .action-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border-color: #dc2626;
        }

        .action-icon {
            width: 50px;
            height: 50px;
            background: #fef2f2;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .action-icon i {
            font-size: 1.5rem;
            color: #dc2626;
        }

        .action-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 12px;
        }

        .action-card p {
            color: #6b7280;
            margin-bottom: 24px;
            line-height: 1.5;
            font-size: 0.9rem;
        }

        .btn-approve {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-approve:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-revise {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            border-radius: 14px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-revise:hover {
            background: #d97706;
            transform: translateY(-2px);
        }

        /* Revision Modal */
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
            border-radius: 24px;
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

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
        }

        .close-modal:hover {
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
            color: #1f2937;
            margin-bottom: 8px;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            font-size: 0.85rem;
            resize: vertical;
        }

        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
        }

        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid #fee2e2;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-cancel {
            padding: 10px 20px;
            background: #fef2f2;
            color: #6b7280;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #fee2e2;
        }

        .btn-submit {
            padding: 10px 20px;
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
        }

        .btn-submit:hover {
            background: #d97706;
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
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .action-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .thesis-detail-card {
                padding: 20px;
            }
            
            .thesis-title {
                font-size: 1.2rem;
            }
            
            .thesis-meta {
                gap: 15px;
            }
            
            .file-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            
            .thesis-detail-card {
                padding: 16px;
            }
            
            .action-card {
                padding: 20px;
            }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .top-nav {
            background: #2d2d2d;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .logo {
            color: #fecaca;
        }

        body.dark-mode .search-area {
            background: #3d3d3d;
        }

        body.dark-mode .search-area input {
            background: #3d3d3d;
            color: white;
        }

        body.dark-mode .profile-name {
            color: #fecaca;
        }

        body.dark-mode .thesis-detail-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .thesis-title {
            color: #fecaca;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .abstract-section p {
            color: #cbd5e1;
        }

        body.dark-mode .file-section {
            background: #3d3d3d;
        }

        body.dark-mode .file-name {
            color: #fecaca;
        }

        body.dark-mode .download-btn {
            background: #2d2d2d;
            color: #fecaca;
            border-color: #991b1b;
        }

        body.dark-mode .download-btn:hover {
            background: #3d3d3d;
        }

        body.dark-mode .action-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .action-card h3 {
            color: #fecaca;
        }

        body.dark-mode .action-card p {
            color: #9ca3af;
        }

        body.dark-mode .action-icon {
            background: #3d3d3d;
        }

        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }

        body.dark-mode .profile-dropdown a:hover {
            background: #3d3d3d;
        }

        body.dark-mode .back-link {
            background: #3d3d3d;
            color: #fecaca;
        }

        body.dark-mode .back-link:hover {
            background: #4a4a4a;
        }

        body.dark-mode .modal-content {
            background: #2d2d2d;
        }

        body.dark-mode .modal-header {
            border-bottom-color: #991b1b;
        }

        body.dark-mode .modal-header h3 {
            color: #fecaca;
        }

        body.dark-mode .form-group label {
            color: #e5e7eb;
        }

        body.dark-mode .form-group textarea {
            background: #3d3d3d;
            border-color: #991b1b;
            color: white;
        }
        
        body.dark-mode .notification-alert {
            background: #3a2a2a;
        }
        body.dark-mode .notification-alert-message {
            color: #cbd5e1;
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
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
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
            <div class="logo-sub">RESEARCH COORDINATOR</div>
        </div>
        
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="reviewThesis.php" class="nav-item active">
                <i class="fas fa-file-alt"></i>
                <span>Review Theses</span>
            </a>
            <a href="myFeedback.php" class="nav-item">
                <i class="fas fa-comment"></i>
                <span>My Feedback</span>
            </a>
            <a href="forwardedTheses.php" class="nav-item">
                <i class="fas fa-arrow-right"></i>
                <span>Forwarded to Dean</span>
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
            <h2>Review Thesis</h2>
            <a href="coordinatorDashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <?php if (!$thesis): ?>
        <div class="thesis-detail-card">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-file-alt" style="font-size: 3rem; color: #dc2626; margin-bottom: 16px; display: inline-block;"></i>
                <h3 style="color: #991b1b; margin-bottom: 8px;">Thesis Not Found</h3>
                <p style="color: #6b7280;">The thesis you are looking for does not exist or has been removed.</p>
                <a href="coordinatorDashboard.php" class="back-link" style="margin-top: 20px; display: inline-flex;">Go back to Dashboard</a>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Display Notification Message -->
        <?php if (!empty($notification_message)): ?>
        <div class="notification-alert">
            <i class="fas fa-bell"></i>
            <div class="notification-alert-content">
                <div class="notification-alert-title">📢 Pending Thesis for Dean Forwarding</div>
                <div class="notification-alert-message"><?= htmlspecialchars($notification_message) ?></div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="thesis-detail-card">
            <div class="thesis-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 class="thesis-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;"><?= htmlspecialchars($thesis_title) ?></h1>
                <span class="status-badge status-<?= strtolower($thesis_status == 'Forwarded to Dean' ? 'forwarded' : ($thesis_status == 'Approved' ? 'approved' : 'pending')) ?>">
                    <?= htmlspecialchars($thesis_status) ?>
                </span>
            </div>
            
            <div class="thesis-meta">
                <div class="meta-item">
                    <i class="fas fa-user"></i>
                    <span><?= htmlspecialchars($thesis_author) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Submitted: <?= $thesis_date ?></span>
                </div>
            </div>
            
            <div class="abstract-section">
                <h4><i class="fas fa-align-left"></i> Abstract</h4>
                <p><?= nl2br(htmlspecialchars($thesis_abstract)) ?></p>
            </div>
            
            <?php if (!empty($thesis_file)): ?>
            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div class="file-details">
                        <span class="file-name"><?= basename($thesis_file) ?></span>
                        <span class="file-size">PDF Document</span>
                    </div>
                </div>
                <a href="<?= $thesis_file ?>" class="download-btn" download>
                    <i class="fas fa-download"></i> Download
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($thesis_status != 'Forwarded to Dean' && $thesis_status != 'Approved'): ?>
        <div class="action-cards">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3>Forward to Dean</h3>
                <p>Approve this thesis and forward it to the dean for final guidelines check and department verification.</p>
                <form method="POST" action="">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="forward_to_dean" value="1">
                    <button type="submit" class="btn-approve" onclick="return confirm('Are you sure you want to approve and forward this thesis to the Dean?')">
                        <i class="fas fa-check"></i> Approve & Forward
                    </button>
                </form>
            </div>
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h3>Request Revisions</h3>
                <p>Send feedback to the faculty adviser for revisions. The thesis will be returned for improvements.</p>
                <button class="btn-revise" onclick="openRevisionModal()">
                    <i class="fas fa-pen"></i> Request Revisions
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="action-card" style="grid-column: span 2;">
            <div class="action-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h3>Thesis Already Processed</h3>
            <p>This thesis has already been <?= strtolower($thesis_status) ?> and cannot be modified.</p>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </main>

    <!-- Revision Modal -->
    <div id="revisionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Revisions</h3>
                <span class="close-modal" onclick="closeRevisionModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="request_revisions" value="1">
                    <div class="form-group">
                        <label>Feedback / Revision Instructions</label>
                        <textarea name="revision_feedback" rows="5" placeholder="Please provide specific feedback and revision instructions for the faculty adviser..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRevisionModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Send Revision Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const revisionModal = document.getElementById('revisionModal');

        // ==================== SIDEBAR FUNCTIONS ====================
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
                if (revisionModal && revisionModal.classList.contains('show')) closeRevisionModal();
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // ==================== PROFILE DROPDOWN ====================
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
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

        // ==================== REVISION MODAL FUNCTIONS ====================
        function openRevisionModal() {
            if (revisionModal) revisionModal.classList.add('show');
        }

        function closeRevisionModal() {
            if (revisionModal) revisionModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target === revisionModal) {
                closeRevisionModal();
            }
        }

        // ==================== INITIALIZE ====================
        initDarkMode();
        
        console.log('Review Thesis Page Initialized - Using notifications table');
    </script>
</body>
</html>