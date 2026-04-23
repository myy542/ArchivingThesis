<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$archive = new ArchiveManager($conn);
$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"] ?? '';
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is coordinator
if ($role !== 'coordinator') {
    header("Location: ../authentication/login.php");
    exit;
}

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$fullName = $first . " " . $last;
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "CO";

// Check if thesis_table exists
$thesis_table_exists = false;
$check_thesis = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis && $check_thesis->num_rows > 0) {
    $thesis_table_exists = true;
}

// ==================== GET ALL PENDING THESES (for list view) - FIXED: Use is_archived = 0 ====================
$pending_theses_list = [];
if ($thesis_table_exists && $thesis_id == 0) {
    // Since wala'y status column, pending means is_archived = 0
    $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                      FROM thesis_table t
                      JOIN user_table u ON t.student_id = u.user_id
                      WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                      ORDER BY t.date_submitted DESC";
    $pending_result = $conn->query($pending_query);
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $pending_theses_list[] = $row;
        }
    }
}

// ==================== GET SINGLE THESIS DETAILS (for review view) ====================
$thesis = null;
$thesis_title = 'Unknown Thesis';
$thesis_author = 'Unknown Author';
$thesis_abstract = 'No abstract available.';
$thesis_file = '';
$thesis_date = '';
$thesis_is_archived = 0;
$student_name = '';
$student_id = null;

if ($thesis_id > 0 && $thesis_table_exists) {
    $thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email, u.user_id as student_user_id
                     FROM thesis_table t
                     JOIN user_table u ON t.student_id = u.user_id
                     WHERE t.thesis_id = ?";
    $thesis_stmt = $conn->prepare($thesis_query);
    $thesis_stmt->bind_param("i", $thesis_id);
    $thesis_stmt->execute();
    $thesis_result = $thesis_stmt->get_result();
    if ($thesis_row = $thesis_result->fetch_assoc()) {
        $thesis = $thesis_row;
        $thesis_title = $thesis_row['title'];
        $thesis_author = $thesis_row['adviser'] ?? 'Unknown Author';
        $thesis_abstract = $thesis_row['abstract'] ?? 'No abstract available.';
        $thesis_file = $thesis_row['file_path'] ?? '';
        $thesis_date = isset($thesis_row['date_submitted']) ? date('M d, Y', strtotime($thesis_row['date_submitted'])) : date('M d, Y');
        $thesis_is_archived = $thesis_row['is_archived'] ?? 0;
        $student_name = $thesis_row['first_name'] . ' ' . $thesis_row['last_name'];
        $student_id = $thesis_row['student_user_id'];
    }
    $thesis_stmt->close();
}

// Determine status for display (since wala'y status column, pending if not archived)
$thesis_status = ($thesis_is_archived == 1) ? 'archived' : 'pending_coordinator';

$message = '';
$messageType = '';

// ==================== HANDLE FORWARD TO DEAN ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_to_dean'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $coordinator_name = $fullName;
    
    $conn->begin_transaction();
    
    try {
        // Since wala'y status column, we can add a column for forwarded status or just note
        // For now, we'll just notify the dean
        // Option: Add a forwarded_to_dean column if needed
        // $updateQuery = "UPDATE thesis_table SET forwarded_to_dean = 1, forwarded_to_dean_at = NOW() WHERE thesis_id = ?";
        
        // Notify Dean
        $dean_query = "SELECT user_id FROM user_table WHERE role_id = 4";
        $dean_result = $conn->query($dean_query);
        if ($dean_result && $dean_result->num_rows > 0) {
            $notifMessage = "📋 Thesis ready for Dean approval: \"" . $thesis_title . "\" from student " . $student_name . ". Forwarded by Coordinator: " . $coordinator_name;
            $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_forward', 0, NOW())";
            $insert_stmt = $conn->prepare($insert_notif);
            
            while ($dean = $dean_result->fetch_assoc()) {
                $dean_id = $dean['user_id'];
                $insert_stmt->bind_param("iis", $dean_id, $thesis_id_post, $notifMessage);
                $insert_stmt->execute();
            }
            $insert_stmt->close();
        }
        
        // Notify student
        $student_notif = "📢 Your thesis \"" . $thesis_title . "\" has been forwarded to the Dean for final approval by Coordinator " . $coordinator_name;
        $insert_student = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'student_notif', 0, NOW())";
        $student_stmt = $conn->prepare($insert_student);
        $student_stmt->bind_param("iis", $student_id, $thesis_id_post, $student_notif);
        $student_stmt->execute();
        $student_stmt->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✓ Thesis forwarded to Dean successfully!";
        header("Location: coordinatorDashboard.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: coordinatorDashboard.php");
        exit();
    }
}

// ==================== HANDLE REQUEST REVISIONS ====================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_revision'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $revision_feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $coordinator_name = $fullName;
    
    $conn->begin_transaction();
    
    try {
        // Since wala'y status column, we just add feedback and notify
        
        // Get faculty advisers
        $faculty_query = "SELECT user_id FROM user_table WHERE role_id = 3";
        $faculty_result = $conn->query($faculty_query);
        
        if ($faculty_result && $faculty_result->num_rows > 0) {
            $notifMessage = "📝 Revision requested for thesis: \"" . $thesis_title . "\". Coordinator: " . $coordinator_name . " Feedback: " . $revision_feedback . " Please revise and resubmit.";
            $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'revision_request', 0, NOW())";
            $insert_stmt = $conn->prepare($insert_notif);
            
            while ($faculty = $faculty_result->fetch_assoc()) {
                $faculty_id = $faculty['user_id'];
                $insert_stmt->bind_param("iis", $faculty_id, $thesis_id_post, $notifMessage);
                $insert_stmt->execute();
            }
            $insert_stmt->close();
        }
        
        // Notify student
        $student_notif = "📝 Revision requested for your thesis \"" . $thesis_title . "\". Coordinator feedback: " . $revision_feedback;
        $insert_student = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'student_notif', 0, NOW())";
        $student_stmt = $conn->prepare($insert_student);
        $student_stmt->bind_param("iis", $student_id, $thesis_id_post, $student_notif);
        $student_stmt->execute();
        $student_stmt->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✓ Revision requested! Faculty has been notified.";
        header("Location: coordinatorDashboard.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: coordinatorDashboard.php");
        exit();
    }
}

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $messageType = "success";
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $messageType = "error";
    unset($_SESSION['error_message']);
}

$pageTitle = $thesis_id > 0 ? "Review Thesis" : "Pending Theses";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Coordinator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        body.dark-mode { background: #2d2d2d; color: #e0e0e0; }
        
        .sidebar {
            position: fixed; top: 0; left: -300px; width: 280px; height: 100vh;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white; z-index: 1000; transition: left 0.3s ease;
        }
        .sidebar.show { left: 0; }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h2 { font-size: 1.5rem; }
        .sidebar-nav { padding: 1.5rem 0.5rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: rgba(255,255,255,0.9); text-decoration: none; border-radius: 8px; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); }
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.2); }
        .logout-btn { display: flex; align-items: center; gap: 0.75rem; color: rgba(255,255,255,0.9); text-decoration: none; }
        
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .overlay.show { display: block; }
        
        .main-content { margin-left: 0; min-height: 100vh; padding: 2rem; }
        .topbar { background: white; border-radius: 12px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        body.dark-mode .topbar { background: #3a3a3a; }
        
        .hamburger-menu { font-size: 1.5rem; cursor: pointer; color: #FE4853; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #FE4853 0%, #732529 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        .review-container { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; }
        body.dark-mode .review-container { background: #3a3a3a; }
        
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        
        .thesis-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        .thesis-header h2 { color: #732529; }
        .status-badge { padding: 0.5rem 1rem; border-radius: 20px; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .status-badge.pending_coordinator { background: #cce5ff; color: #004085; }
        .status-badge.forwarded_to_dean { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #f8d7da; color: #721c24; }
        
        .thesis-details { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 8px; }
        body.dark-mode .thesis-details { background: #4a4a4a; }
        .detail-label { font-size: 0.85rem; color: #6E6E6E; }
        .detail-value { font-size: 1rem; font-weight: 500; }
        
        .thesis-abstract { margin-bottom: 2rem; }
        .thesis-abstract h3 { color: #732529; margin-bottom: 1rem; }
        .thesis-abstract p { padding: 1rem; background: #f8fafc; border-radius: 8px; line-height: 1.6; }
        
        .thesis-file { 
            margin-bottom: 2rem; 
            background: #f8fafc; 
            border-radius: 12px; 
            padding: 1rem;
        }
        .thesis-file h3 { 
            font-size: 0.9rem; 
            margin-bottom: 0.75rem;
        }
        .file-link { 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            padding: 0.4rem 0.8rem; 
            background: #3b82f6; 
            border-radius: 4px; 
            text-decoration: none; 
            color: white; 
            margin-right: 0.75rem;
            font-size: 0.75rem;
        }
        .pdf-viewer { 
            margin-top: 0.75rem; 
        }
        .pdf-viewer iframe { 
            width: 100%; 
            height: 300px; 
            border: 1px solid #e0e0e0; 
            border-radius: 6px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .btn {
            padding: 0.4rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }
        .btn-forward {
            background: #28a745;
            color: white;
        }
        .btn-forward:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }
        .btn-revise {
            background: #dc3545;
            color: white;
        }
        .btn-revise:hover {
            background: #b02a37;
            transform: translateY(-1px);
        }
        
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #FE4853; text-decoration: none; margin-bottom: 1rem; }
        .mobile-menu-btn { position: fixed; top: 16px; right: 16px; z-index: 1001; background: #FE4853; color: white; padding: 12px 15px; border-radius: 10px; cursor: pointer; display: none; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; }
        body.dark-mode .modal-content { background: #3a3a3a; }
        .modal-content h3 { margin-bottom: 1rem; }
        .modal-content textarea { width: 100%; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px; margin: 1rem 0; font-family: inherit; resize: vertical; }
        .modal-buttons { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        
        /* Pending Theses List Styles */
        .pending-list-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
        }
        .pending-list-container h2 {
            color: #732529;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .pending-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pending-table th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            color: #6E6E6E;
            font-weight: 600;
            font-size: 0.75rem;
            border-bottom: 1px solid #e0e0e0;
        }
        .pending-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.85rem;
        }
        .pending-table tr:hover {
            background: #fef2f2;
        }
        .btn-review-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.7rem;
            transition: all 0.3s;
        }
        .btn-review-link:hover {
            background: #991b1b;
            transform: scale(1.05);
        }
        
        @media (max-width: 768px) {
            .thesis-details { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .mobile-menu-btn { display: flex; }
            .main-content { padding: 1rem; margin-top: 60px; }
            .topbar { display: none; }
            .pdf-viewer iframe { height: 250px; }
            .pending-table { display: block; overflow-x: auto; }
        }
        
        .theme-toggle { margin-bottom: 1rem; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header"><h2>Theses Archive</h2><p>Coordinator Portal</p></div>
    <nav class="sidebar-nav">
        <a href="coordinatorDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="reviewThesis.php" class="nav-link active"><i class="fas fa-book-reader"></i> Review Theses</a>
        <a href="myFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> My Feedback</a>
        <a href="forwardedTheses.php" class="nav-link"><i class="fas fa-arrow-right"></i> Forwarded to Dean</a>
    </nav>
    <div class="sidebar-footer">
        <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i> Dark Mode</label></div>
        <a href="../authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
                <h1><?= $thesis_id > 0 ? "Review Thesis" : "Pending Theses for Review" ?></h1>
            </div>
            <div class="user-info"><div class="avatar"><?= htmlspecialchars($initials) ?></div></div>
        </header>

        <a href="coordinatorDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        <?php if ($thesis_id == 0): ?>
            <!-- LIST VIEW: Show all pending theses -->
            <div class="pending-list-container">
                <h2><i class="fas fa-clock"></i> Theses Waiting for Coordinator Review</h2>
                <?php if (empty($pending_theses_list)): ?>
                    <div class="empty-state" style="text-align: center; padding: 40px;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #dc2626; margin-bottom: 12px;"></i>
                        <p>No pending theses to review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="pending-table">
                            <thead>
                                <tr>
                                    <th>Thesis Title</th>
                                    <th>Student</th>
                                    <th>Department</th>
                                    <th>Date Submitted</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_theses_list as $thesis): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></td>
                                    <td><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></td>
                                    <td><?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></td>
                                    <td>
                                        <a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-review-link">
                                            <i class="fas fa-chevron-right"></i> Review
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- DETAIL VIEW: Show single thesis for review -->
            <div class="review-container">
                <?php if (!empty($message)): ?>
                    <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="thesis-header">
                    <h2><?= htmlspecialchars($thesis_title) ?></h2>
                    <span class="status-badge pending_coordinator">Pending Review</span>
                </div>

                <div class="thesis-details">
                    <div class="detail-item"><span class="detail-label">Student Name: </span><span class="detail-value"><?= htmlspecialchars($student_name) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Adviser: </span><span class="detail-value"><?= htmlspecialchars($thesis_author) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Department: </span><span class="detail-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Year: </span><span class="detail-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></span></div>
                    <div class="detail-item"><span class="detail-label">Date Submitted: </span><span class="detail-value"><?= $thesis_date ?></span></div>
                </div>

                <div class="thesis-abstract"><h3>Abstract</h3><p><?= nl2br(htmlspecialchars($thesis_abstract)) ?></p></div>

                <div class="thesis-file">
                    <h3><i class="fas fa-file-pdf"></i> Manuscript File</h3>
                    <?php if (!empty($thesis_file)): 
                        $file_path = '../' . $thesis_file;
                        if (file_exists($file_path)): ?>
                            <div class="file-actions">
                                <a href="<?= htmlspecialchars($file_path) ?>" class="file-link" target="_blank"><i class="fas fa-eye"></i> View PDF</a>
                                <a href="<?= htmlspecialchars($file_path) ?>" class="file-link download" download><i class="fas fa-download"></i> Download</a>
                            </div>
                            <div class="pdf-viewer"><iframe src="<?= htmlspecialchars($file_path) ?>"></iframe></div>
                        <?php else: ?>
                            <p>File not found.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>No manuscript file uploaded.</p>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-forward" onclick="showForwardModal()">
                        <i class="fas fa-check-circle"></i> FORWARD TO DEAN
                    </button>
                    <button class="btn btn-revise" onclick="showReviseModal()">
                        <i class="fas fa-edit"></i> REQUEST REVISIONS
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Forward to Dean Modal -->
<div id="forwardModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#28a745;"><i class="fas fa-check-circle"></i> Forward to Dean</h3>
        <p>Forward this thesis to the Dean for final approval?</p>
        <form method="POST" action="">
            <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
            <input type="hidden" name="forward_to_dean" value="1">
            <div class="modal-buttons">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeForwardModal()">Cancel</button>
                <button type="submit" class="btn btn-forward">Confirm Forward</button>
            </div>
        </form>
    </div>
</div>

<!-- Request Revisions Modal -->
<div id="reviseModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#dc3545;"><i class="fas fa-edit"></i> Request Revisions</h3>
        <p>Send this thesis back to faculty for revisions.</p>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="thesis_id" value="<?= $thesis_id ?>">
                <input type="hidden" name="request_revision" value="1">
                <div class="form-group">
                    <label>Feedback / Revision Instructions <span style="color:#dc3545;">*</span></label>
                    <textarea name="feedback" rows="5" placeholder="Please provide specific feedback and revision instructions for the faculty adviser..." required></textarea>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 24px; border-top: 1px solid #fee2e2; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeReviseModal()">Cancel</button>
                <button type="submit" class="btn btn-revise">Send Revision Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    const darkToggle = document.getElementById('darkmode');
    if(darkToggle){
        darkToggle.addEventListener('change',()=>{
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode',darkToggle.checked);
        });
        if(localStorage.getItem('darkMode')==='true'){
            darkToggle.checked=true;
            document.body.classList.add('dark-mode');
        }
    }
    
    const sidebar=document.getElementById('sidebar');
    const overlay=document.getElementById('overlay');
    const hamburger=document.getElementById('hamburgerBtn');
    const mobileBtn=document.getElementById('mobileMenuBtn');
    
    function toggleSidebar(){
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }
    
    if(hamburger) hamburger.addEventListener('click',toggleSidebar);
    if(mobileBtn) mobileBtn.addEventListener('click',toggleSidebar);
    if(overlay) overlay.addEventListener('click',toggleSidebar);
    
    function showForwardModal(){
        document.getElementById('forwardModal').style.display='flex';
    }
    function closeForwardModal(){
        document.getElementById('forwardModal').style.display='none';
    }
    
    function showReviseModal(){
        document.getElementById('reviseModal').style.display='flex';
    }
    function closeReviseModal(){
        document.getElementById('reviseModal').style.display='none';
    }
    
    window.onclick=function(e){
        if(e.target.classList.contains('modal')){
            e.target.style.display='none';
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('forwardModal').style.display='none';
            document.getElementById('reviseModal').style.display='none';
            if (sidebar.classList.contains('show')) toggleSidebar();
        }
    });
</script>
</body>
</html>