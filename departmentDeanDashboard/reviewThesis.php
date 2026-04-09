<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and is a Dean
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
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

// Get thesis details from database
$thesis = null;
$thesis_title = 'Unknown Thesis';
$thesis_author = 'Unknown Author';
$thesis_abstract = 'No abstract available.';
$thesis_file = '';
$thesis_date = '';
$thesis_status = '';
$submitted_by = '';
$coordinator_name = '';

if ($thesis_id > 0) {
    $thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                     FROM theses t
                     JOIN user_table u ON t.submitted_by = u.user_id
                     WHERE t.thesis_id = ?";
    $thesis_stmt = $conn->prepare($thesis_query);
    $thesis_stmt->bind_param("i", $thesis_id);
    $thesis_stmt->execute();
    $thesis_result = $thesis_stmt->get_result();
    if ($thesis_row = $thesis_result->fetch_assoc()) {
        $thesis = $thesis_row;
        $thesis_title = $thesis_row['title'];
        $thesis_author = $thesis_row['author'] ?? ($thesis_row['first_name'] . ' ' . $thesis_row['last_name']);
        $thesis_abstract = $thesis_row['abstract'] ?? 'No abstract available.';
        $thesis_file = $thesis_row['file_path'] ?? '';
        $thesis_date = isset($thesis_row['created_at']) ? date('M d, Y', strtotime($thesis_row['created_at'])) : date('M d, Y');
        $thesis_status = $thesis_row['status'] ?? 'pending';
        $submitted_by = $thesis_row['submitted_by'];
    }
    $thesis_stmt->close();
}

// Get coordinator name who forwarded this thesis
$coordinator_query = "SELECT message FROM notifications WHERE thesis_id = ? AND type = 'dean_forward' ORDER BY created_at DESC LIMIT 1";
$coordinator_stmt = $conn->prepare($coordinator_query);
$coordinator_stmt->bind_param("i", $thesis_id);
$coordinator_stmt->execute();
$coordinator_result = $coordinator_stmt->get_result();
if ($coordinator_row = $coordinator_result->fetch_assoc()) {
    // Extract coordinator name from message
    $msg = $coordinator_row['message'];
    if (preg_match('/by Coordinator (.+?)\./', $msg, $matches)) {
        $coordinator_name = $matches[1];
    }
}
$coordinator_stmt->close();

// Process form submission - Approve Thesis (for Dean)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['approve_thesis'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $dean_feedback = isset($_POST['dean_feedback']) ? trim($_POST['dean_feedback']) : '';
    
    // Update thesis status to 'approved'
    $update_query = "UPDATE theses SET status = 'approved', dean_feedback = ? WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $dean_feedback, $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Notify Coordinator and Faculty
    $notifMessage = "✅ Thesis \"" . $thesis_title . "\" has been APPROVED by Dean " . $fullName;
    
    // Notify Coordinator
    $coord_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_approved', 0, NOW())";
    $coord_stmt = $conn->prepare($coord_notif);
    $coord_stmt->bind_param("iis", $user_id, $thesis_id_post, $notifMessage);
    $coord_stmt->execute();
    $coord_stmt->close();
    
    // Notify Faculty (submitted_by)
    if ($submitted_by) {
        $faculty_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_approved', 0, NOW())";
        $faculty_stmt = $conn->prepare($faculty_notif);
        $faculty_stmt->bind_param("iis", $submitted_by, $thesis_id_post, $notifMessage);
        $faculty_stmt->execute();
        $faculty_stmt->close();
    }
    
    header("Location: dean.php?msg=approved");
    exit;
}

// Process form submission - Reject Thesis (for Dean)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reject_thesis'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $dean_feedback = isset($_POST['dean_feedback']) ? trim($_POST['dean_feedback']) : '';
    
    // Update thesis status to 'rejected'
    $update_query = "UPDATE theses SET status = 'rejected', dean_feedback = ? WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $dean_feedback, $thesis_id_post);
    $update_stmt->execute();
    $update_stmt->close();
    
    // Notify Coordinator and Faculty
    $notifMessage = "❌ Thesis \"" . $thesis_title . "\" has been REJECTED by Dean " . $fullName . ". Reason: " . $dean_feedback;
    
    // Notify Coordinator
    $coord_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_rejected', 0, NOW())";
    $coord_stmt = $conn->prepare($coord_notif);
    $coord_stmt->bind_param("iis", $user_id, $thesis_id_post, $notifMessage);
    $coord_stmt->execute();
    $coord_stmt->close();
    
    // Notify Faculty
    if ($submitted_by) {
        $faculty_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_rejected', 0, NOW())";
        $faculty_stmt = $conn->prepare($faculty_notif);
        $faculty_stmt->bind_param("iis", $submitted_by, $thesis_id_post, $notifMessage);
        $faculty_stmt->execute();
        $faculty_stmt->close();
    }
    
    header("Location: dean.php?msg=rejected");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Review Thesis | Dean Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }
        
        /* Top Navigation */
        .top-nav {
            position: fixed; top: 0; right: 0; left: 0; height: 70px;
            background: white; display: flex; align-items: center;
            justify-content: space-between; padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05); z-index: 99;
            border-bottom: 1px solid #fee2e2;
        }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px;
            background: #fef2f2; border: none; border-radius: 8px; cursor: pointer;
            align-items: center; justify-content: center; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 200px; }
        .nav-right { display: flex; align-items: center; gap: 20px; position: relative; }
        
        /* Profile */
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 0; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden;
            z-index: 100; border: 1px solid #fee2e2; }
        .profile-dropdown.show { display: block; animation: fadeSlideDown 0.2s ease; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px;
            text-decoration: none; color: #1f2937; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Sidebar */
        .sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex;
            flex-direction: column; z-index: 1000; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 6px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px;
            border-radius: 12px; text-decoration: none; color: #fecaca; font-weight: 500;
            transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 12px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px;
            text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 32px; flex-wrap: wrap; gap: 15px; }
        .page-header h2 { font-size: 1.75rem; font-weight: 700; color: #991b1b; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #dc2626;
            text-decoration: none; font-weight: 500; font-size: 0.9rem; padding: 8px 16px;
            background: #fef2f2; border-radius: 30px; transition: all 0.2s; }
        .back-link:hover { background: #fee2e2; transform: translateX(-3px); }
        
        .thesis-detail-card { background: white; border-radius: 24px; padding: 32px;
            margin-bottom: 32px; border: 1px solid #fee2e2; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .thesis-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; margin-bottom: 16px;
            padding-bottom: 16px; border-bottom: 1px solid #fee2e2; }
        .thesis-meta { display: flex; gap: 24px; margin-bottom: 24px; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; gap: 8px; color: #6b7280; font-size: 0.85rem; }
        .meta-item i { color: #dc2626; width: 16px; }
        .abstract-section { margin-bottom: 24px; }
        .abstract-section h4 { font-size: 1rem; font-weight: 600; color: #991b1b; margin-bottom: 12px;
            display: flex; align-items: center; gap: 8px; }
        .abstract-section p { color: #4b5563; line-height: 1.6; font-size: 0.95rem; }
        
        .file-section { background: #fef2f2; border-radius: 16px; padding: 16px 20px;
            margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 15px; }
        .file-info { display: flex; align-items: center; gap: 12px; }
        .file-info i { font-size: 1.5rem; color: #dc2626; }
        .file-name { font-weight: 600; color: #1f2937; font-size: 0.9rem; }
        .download-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px;
            background: white; color: #dc2626; text-decoration: none; border-radius: 30px;
            font-weight: 500; font-size: 0.8rem; border: 1px solid #fee2e2; transition: all 0.2s; }
        .download-btn:hover { background: #fee2e2; transform: translateY(-2px); }
        
        .pdf-viewer { margin-top: 1rem; border-radius: 12px; overflow: hidden; border: 1px solid #fee2e2; background: white; }
        .pdf-viewer iframe { width: 100%; height: 600px; border: none; }
        .pdf-viewer .pdf-error { padding: 40px; text-align: center; color: #9ca3af; background: #fef2f2; }
        .pdf-viewer .pdf-error i { font-size: 3rem; margin-bottom: 1rem; color: #dc2626; }
        
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 30px;
            font-size: 0.75rem; font-weight: 600; margin-bottom: 20px; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-forwarded { background: #dbeafe; color: #2563eb; }
        .status-approved { background: #d1fae5; color: #059669; }
        .status-rejected { background: #fee2e2; color: #dc2626; }
        
        .action-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-top: 24px; }
        .action-card { background: white; border-radius: 24px; padding: 28px; border: 1px solid #fee2e2;
            transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .action-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            border-color: #dc2626; }
        .action-icon { width: 50px; height: 50px; background: #fef2f2; border-radius: 16px;
            display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .action-icon i { font-size: 1.5rem; color: #dc2626; }
        .action-card h3 { font-size: 1.2rem; font-weight: 600; color: #991b1b; margin-bottom: 12px; }
        .action-card p { color: #6b7280; margin-bottom: 24px; line-height: 1.5; font-size: 0.9rem; }
        
        .btn-approve { display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 14px 20px; background: #10b981; color: white; border: none;
            border-radius: 14px; font-weight: 600; font-size: 0.9rem; cursor: pointer;
            transition: all 0.2s; }
        .btn-approve:hover { background: #059669; transform: translateY(-2px); }
        .btn-reject { display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 14px 20px; background: #dc3545; color: white; border: none;
            border-radius: 14px; font-weight: 600; font-size: 0.9rem; cursor: pointer;
            transition: all 0.2s; }
        .btn-reject:hover { background: #b02a37; transform: translateY(-2px); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1100; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 24px; width: 500px; max-width: 90%;
            animation: slideUp 0.3s ease; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid #fee2e2;
            display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; color: #991b1b; }
        .close-modal { font-size: 1.5rem; cursor: pointer; color: #9ca3af; }
        .close-modal:hover { color: #dc2626; }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem;
            color: #1f2937; margin-bottom: 8px; }
        .form-group textarea { width: 100%; padding: 12px; border: 1px solid #fee2e2;
            border-radius: 12px; font-size: 0.85rem; resize: vertical; font-family: inherit; }
        .form-group textarea:focus { outline: none; border-color: #dc2626; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #fee2e2;
            display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { padding: 10px 20px; background: #fef2f2; color: #6b7280; border: none;
            border-radius: 10px; cursor: pointer; font-weight: 500; }
        .btn-cancel:hover { background: #fee2e2; }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 768px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { padding: 20px; }
            .action-cards { grid-template-columns: 1fr; gap: 20px; }
            .search-area { display: none; }
            .profile-name { display: none; }
            .pdf-viewer iframe { height: 400px; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .thesis-detail-card { padding: 20px; }
            .thesis-title { font-size: 1.2rem; }
            .pdf-viewer iframe { height: 300px; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #3d3d3d; }
        body.dark-mode .profile-name { color: #fecaca; }
        body.dark-mode .thesis-detail-card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .thesis-title { color: #fecaca; border-bottom-color: #991b1b; }
        body.dark-mode .abstract-section p { color: #cbd5e1; }
        body.dark-mode .file-section { background: #3d3d3d; }
        body.dark-mode .file-name { color: #fecaca; }
        body.dark-mode .download-btn { background: #2d2d2d; color: #fecaca; border-color: #991b1b; }
        body.dark-mode .action-card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .action-card h3 { color: #fecaca; }
        body.dark-mode .profile-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .modal-content { background: #2d2d2d; }
        body.dark-mode .modal-header { border-bottom-color: #991b1b; }
        body.dark-mode .form-group label { color: #e5e7eb; }
        body.dark-mode .form-group textarea { background: #3d3d3d; border-color: #991b1b; color: white; }
        body.dark-mode .pdf-viewer { background: #2d2d2d; border-color: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
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
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">DEPARTMENT DEAN</div></div>
        <div class="nav-menu">
            <a href="dean.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item active"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="department.php" class="nav-item"><i class="fas fa-building"></i><span>Department</span></a>
            <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h2>Review Thesis</h2>
            <a href="dean.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <?php if (!$thesis): ?>
        <div class="thesis-detail-card">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-file-alt" style="font-size: 3rem; color: #dc2626; margin-bottom: 16px;"></i>
                <h3 style="color: #991b1b;">Thesis Not Found</h3>
                <p style="color: #6b7280;">The thesis you are looking for does not exist.</p>
                <a href="dean.php" class="back-link" style="margin-top: 20px;">Go back to Dashboard</a>
            </div>
        </div>
        <?php else: ?>
        
        <div class="thesis-detail-card">
            <div class="thesis-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h1 class="thesis-title" style="margin-bottom: 0; padding-bottom: 0; border-bottom: none;"><?= htmlspecialchars($thesis_title) ?></h1>
                <span class="status-badge status-<?= strtolower(str_replace('_', ' ', $thesis_status)) ?>">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $thesis_status))) ?>
                </span>
            </div>
            
            <div class="thesis-meta">
                <div class="meta-item"><i class="fas fa-user"></i><span><?= htmlspecialchars($thesis_author) ?></span></div>
                <div class="meta-item"><i class="fas fa-calendar-alt"></i><span>Submitted: <?= $thesis_date ?></span></div>
                <?php if (!empty($coordinator_name)): ?>
                <div class="meta-item"><i class="fas fa-user-check"></i><span>Forwarded by: <?= htmlspecialchars($coordinator_name) ?></span></div>
                <?php endif; ?>
            </div>
            
            <div class="abstract-section">
                <h4><i class="fas fa-align-left"></i> Abstract</h4>
                <p><?= nl2br(htmlspecialchars($thesis_abstract)) ?></p>
            </div>
            
            <!-- Manuscript File Section -->
            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div class="file-name"><?= !empty($thesis_file) ? basename($thesis_file) : 'No file uploaded' ?></div>
                </div>
                <?php if (!empty($thesis_file)): ?>
                <a href="<?= htmlspecialchars('../' . $thesis_file) ?>" class="download-btn" download><i class="fas fa-download"></i> Download</a>
                <?php endif; ?>
            </div>
            
            <!-- PDF Viewer -->
            <?php if (!empty($thesis_file)): 
                $full_file_path = '../' . $thesis_file;
                if (file_exists($full_file_path)):
            ?>
            <div class="pdf-viewer">
                <iframe src="<?= htmlspecialchars($full_file_path) ?>"></iframe>
            </div>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>PDF file not found.</p></div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>No manuscript file uploaded.</p></div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($thesis_status == 'forwarded_to_dean'): ?>
        <div class="action-cards">
            <div class="action-card">
                <div class="action-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Approve Thesis</h3>
                <p>Approve this thesis. The coordinator and faculty will be notified of your decision.</p>
                <button class="btn-approve" onclick="openApproveModal()"><i class="fas fa-check"></i> Approve Thesis</button>
            </div>
            <div class="action-card">
                <div class="action-icon"><i class="fas fa-times-circle"></i></div>
                <h3>Reject Thesis</h3>
                <p>Reject this thesis and provide feedback. The coordinator and faculty will be notified.</p>
                <button class="btn-reject" onclick="openRejectModal()"><i class="fas fa-times"></i> Reject Thesis</button>
            </div>
        </div>
        <?php elseif ($thesis_status == 'approved'): ?>
        <div class="action-card" style="grid-column: span 2;">
            <div class="action-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Thesis Approved</h3>
            <p>This thesis has been approved by the Dean.</p>
        </div>
        <?php elseif ($thesis_status == 'rejected'): ?>
        <div class="action-card" style="grid-column: span 2;">
            <div class="action-icon"><i class="fas fa-times-circle"></i></div>
            <h3>Thesis Rejected</h3>
            <p>This thesis has been rejected by the Dean.</p>
        </div>
        <?php else: ?>
        <div class="action-card" style="grid-column: span 2;">
            <div class="action-icon"><i class="fas fa-info-circle"></i></div>
            <h3>Pending Coordinator Review</h3>
            <p>This thesis is still pending review by the Coordinator.</p>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </main>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 style="color:#10b981;"><i class="fas fa-check-circle"></i> Approve Thesis</h3>
                <span class="close-modal" onclick="closeApproveModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="approve_thesis" value="1">
                    <div class="form-group">
                        <label>Feedback (Optional)</label>
                        <textarea name="dean_feedback" rows="3" placeholder="Optional feedback for the faculty and coordinator..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeApproveModal()">Cancel</button>
                    <button type="submit" class="btn-approve">Confirm Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 style="color:#dc3545;"><i class="fas fa-times-circle"></i> Reject Thesis</h3>
                <span class="close-modal" onclick="closeRejectModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                    <input type="hidden" name="reject_thesis" value="1">
                    <div class="form-group">
                        <label>Reason for Rejection <span style="color:#dc3545;">*</span></label>
                        <textarea name="dean_feedback" rows="3" placeholder="Please provide reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-reject">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const approveModal = document.getElementById('approveModal');
        const rejectModal = document.getElementById('rejectModal');

        function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleSidebar(e) { e.stopPropagation(); if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); }
        
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') {
            if (sidebar.classList.contains('open')) closeSidebar();
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            if (approveModal.classList.contains('show')) closeApproveModal();
            if (rejectModal.classList.contains('show')) closeRejectModal();
        }});
        
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
        
        function toggleProfileDropdown(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); }
        function closeProfileDropdown(e) { if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); }
        if (profileWrapper) { profileWrapper.addEventListener('click', toggleProfileDropdown); document.addEventListener('click', closeProfileDropdown); }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
            if (darkModeToggle) { darkModeToggle.addEventListener('change', function() {
                if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); }
                else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); }
            }); }
        }
        
        function openApproveModal() { if (approveModal) approveModal.classList.add('show'); }
        function closeApproveModal() { if (approveModal) approveModal.classList.remove('show'); }
        function openRejectModal() { if (rejectModal) rejectModal.classList.add('show'); }
        function closeRejectModal() { if (rejectModal) rejectModal.classList.remove('show'); }
        
        window.onclick = function(event) {
            if (event.target === approveModal) closeApproveModal();
            if (event.target === rejectModal) closeRejectModal();
        }
        
        initDarkMode();
        console.log('Dean Review Thesis Page Initialized');
    </script>
</body>
</html>