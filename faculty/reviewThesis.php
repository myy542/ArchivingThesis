<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$archive = new ArchiveManager($conn);
$faculty_id = (int)$_SESSION["user_id"];
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ==================== FUNCTION TO NOTIFY COORDINATOR ====================
function notifyCoordinator($conn, $thesis_id, $thesis_title, $student_name, $faculty_name) {
    // Get all coordinators (role_id = 6)
    $coord_query = "SELECT user_id FROM user_table WHERE role_id = 6";
    $coord_result = $conn->query($coord_query);
    
    if (!$coord_result || $coord_result->num_rows == 0) {
        error_log("No coordinator found");
        return false;
    }
    
    $message = "📢 New thesis forwarded for review: \"" . $thesis_title . "\" from student " . $student_name . ". Faculty: " . $faculty_name . " has approved this thesis.";
    $link = "../coordinator/reviewThesis.php?id=" . $thesis_id;
    
    $notified = false;
    while ($coordinator = $coord_result->fetch_assoc()) {
        $coordinator_id = $coordinator['user_id'];
        
        $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                    VALUES (?, ?, ?, 'faculty_forward', ?, 0, NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $notifStmt->bind_param("iiss", $coordinator_id, $thesis_id, $message, $link);
        
        if ($notifStmt->execute()) {
            $notified = true;
        }
        $notifStmt->close();
    }
    
    return $notified;
}

// HANDLE ARCHIVE REQUEST
if(isset($_POST['archive_thesis'])) {
    $archive_thesis_id = $_POST['thesis_id'];
    $notes = $_POST['archive_notes'] ?? '';
    $retention = $_POST['retention_period'] ?? 5;
    
    if($archive->archiveThesis($archive_thesis_id, $_SESSION['user_id'], $notes, $retention)) {
        $_SESSION['success_message'] = "Thesis archived successfully!";
        header("Location: reviewThesis.php?id=" . $archive_thesis_id);
        exit();
    } else {
        $_SESSION['error_message'] = implode("<br>", $archive->getErrors());
        header("Location: reviewThesis.php?id=" . $archive_thesis_id);
        exit();
    }
}

if ($thesis_id == 0) {
    header("Location: facultyDashboard.php");
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = trim($faculty["first_name"] ?? "");
$last  = trim($faculty["last_name"] ?? "");
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "FA";

$thesis = null;

try {
    $query = "SELECT 
                t.*, 
                u.user_id as student_user_id,
                u.first_name as student_first_name, 
                u.last_name as student_last_name, 
                u.email as student_email
              FROM thesis_table t
              JOIN user_table u ON t.student_id = u.user_id
              WHERE t.thesis_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $thesis_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $thesis = $result->fetch_assoc();
    $stmt->close();

    if (!$thesis) {
        header("Location: facultyDashboard.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching thesis: " . $e->getMessage());
    header("Location: facultyDashboard.php");
    exit;
}

$message = '';
$messageType = '';

// Determine thesis status based on available columns
// Since wala'y status column, pending ang default unless archived
$is_archived = isset($thesis['is_archived']) ? (int)$thesis['is_archived'] : 0;
$thesis_status = ($is_archived == 1) ? 'archived' : 'pending';

// ==================== HANDLE APPROVE ====================
if (isset($_POST['approve_thesis'])) {
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    
    $conn->begin_transaction();
    
    try {
        // Since wala'y status column, we'll just add feedback and notify coordinator
        // Option: Add a new column for approval status if needed
        // For now, we'll just record the feedback
        
        if (!empty($feedback)) {
            $insertQuery = "INSERT INTO feedback_table (thesis_id, faculty_id, comments, feedback_date) 
                           VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iis", $thesis_id, $faculty_id, $feedback);
            $stmt->execute();
            $stmt->close();
        }
        
        $student_name = $thesis['student_first_name'] . ' ' . $thesis['student_last_name'];
        $faculty_name = $first . ' ' . $last;
        $thesis_title = $thesis['title'];
        
        notifyCoordinator($conn, $thesis_id, $thesis_title, $student_name, $faculty_name);
        
        // Notify student
        $studentMessage = "✅ Your thesis \"" . $thesis_title . "\" has been approved by faculty " . $faculty_name . " and forwarded to the coordinator for final review.";
        $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'thesis_approved', 0, NOW())");
        $notifStmt->bind_param("iis", $thesis['student_user_id'], $thesis_id, $studentMessage);
        $notifStmt->execute();
        $notifStmt->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✓ Thesis approved and forwarded to Coordinator!";
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
    }
}

// ==================== HANDLE REJECT ====================
if (isset($_POST['reject_thesis'])) {
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    
    $conn->begin_transaction();
    
    try {
        if (!empty($feedback)) {
            $insertQuery = "INSERT INTO feedback_table (thesis_id, faculty_id, comments, feedback_date) 
                           VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iis", $thesis_id, $faculty_id, $feedback);
            $stmt->execute();
            $stmt->close();
        }
        
        $student_id = $thesis['student_user_id'];
        
        $notifMessage = "❌ Your thesis '" . $thesis['title'] . "' has been rejected. Reason: " . $feedback;
        $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) 
                      VALUES (?, ?, ?, 'thesis_rejected', 0, NOW())";
        $stmt2 = $conn->prepare($notifQuery);
        $stmt2->bind_param("iis", $student_id, $thesis_id, $notifMessage);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✓ Thesis rejected! Student has been notified.";
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
    }
}

// ==================== HANDLE REVISE ====================
if (isset($_POST['revise_thesis'])) {
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    
    $conn->begin_transaction();
    
    try {
        if (!empty($feedback)) {
            $insertQuery = "INSERT INTO feedback_table (thesis_id, faculty_id, comments, feedback_date) 
                           VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iis", $thesis_id, $faculty_id, $feedback);
            $stmt->execute();
            $stmt->close();
        }
        
        $student_id = $thesis['student_user_id'];
        
        $notifMessage = "📝 Your thesis '" . $thesis['title'] . "' needs revisions. Feedback: " . $feedback;
        $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) 
                      VALUES (?, ?, ?, 'thesis_revision', 0, NOW())";
        $stmt2 = $conn->prepare($notifQuery);
        $stmt2->bind_param("iis", $student_id, $thesis_id, $notifMessage);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        
        $_SESSION['success_message'] = "✓ Revision request sent to student!";
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: reviewThesis.php?id=" . $thesis_id);
        exit();
    }
}

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

$pageTitle = "Review Thesis";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
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
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.archived { background: #e2e3e5; color: #383d41; }
        
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
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-approve:hover {
            background: #1e7e34;
            transform: translateY(-2px);
        }
        .btn-revise {
            background: #ffc107;
            color: #212529;
        }
        .btn-revise:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        .btn-reject:hover {
            background: #b02a37;
            transform: translateY(-2px);
        }
        .btn-archive {
            background: #6c757d;
            color: white;
        }
        .btn-archive:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #FE4853; text-decoration: none; margin-bottom: 1rem; }
        .mobile-menu-btn { position: fixed; top: 16px; right: 16px; z-index: 1001; background: #FE4853; color: white; padding: 12px 15px; border-radius: 10px; cursor: pointer; display: none; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%; }
        body.dark-mode .modal-content { background: #3a3a3a; }
        .modal-content h3 { margin-bottom: 1rem; }
        .modal-content textarea { width: 100%; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px; margin: 1rem 0; font-family: inherit; resize: vertical; }
        .modal-content select { width: 100%; padding: 0.75rem; border: 1px solid #e0e0e0; border-radius: 6px; margin: 0.5rem 0 1rem 0; }
        .modal-buttons { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        
        @media (max-width: 768px) {
            .thesis-details { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .mobile-menu-btn { display: flex; }
            .main-content { padding: 1rem; margin-top: 60px; }
            .topbar { display: none; }
            .pdf-viewer iframe { height: 250px; }
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
    <div class="sidebar-header"><h2>Theses Archive</h2><p>Faculty Portal</p></div>
    <nav class="sidebar-nav">
        <a href="facultyDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="reviewThesis.php" class="nav-link active"><i class="fas fa-book-reader"></i> Review Theses</a>
        <a href="facultyFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> My Feedback</a>
        <a href="archived_theses.php" class="nav-link"><i class="fas fa-archive"></i> Archived Theses</a>
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
                <h1>Review Thesis</h1>
            </div>
            <div class="user-info"><div class="avatar"><?= htmlspecialchars($initials) ?></div></div>
        </header>

        <a href="facultyDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

        <div class="review-container">
            <?php if (!empty($message)): ?>
                <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="thesis-header">
                <h2><?= htmlspecialchars($thesis['title']) ?></h2>
                <span class="status-badge <?= $is_archived == 1 ? 'archived' : 'pending' ?>">
                    <?= $is_archived == 1 ? 'Archived' : 'Pending Review' ?>
                </span>
            </div>

            <div class="thesis-details">
                <div class="detail-item"><span class="detail-label">Student Name: </span><span class="detail-value"><?= htmlspecialchars($thesis['student_first_name'] . ' ' . $thesis['student_last_name']) ?></span></div>
                <div class="detail-item"><span class="detail-label">Email: </span><span class="detail-value"><?= htmlspecialchars($thesis['student_email']) ?></span></div>
                <div class="detail-item"><span class="detail-label">Department: </span><span class="detail-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></span></div>
                <div class="detail-item"><span class="detail-label">Year: </span><span class="detail-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></span></div>
                <div class="detail-item"><span class="detail-label">Date Submitted: </span><span class="detail-value"><?= date('F d, Y', strtotime($thesis['date_submitted'] ?? 'now')) ?></span></div>
            </div>

            <div class="thesis-abstract"><h3>Abstract</h3><p><?= nl2br(htmlspecialchars($thesis['abstract'] ?? 'No abstract provided.')) ?></p></div>

            <div class="thesis-file">
                <h3><i class="fas fa-file-pdf"></i> Manuscript File</h3>
                <?php if (!empty($thesis['file_path'])): 
                    $file_path = '../' . $thesis['file_path'];
                    if (file_exists($file_path)): ?>
                        <div class="file-actions">
                            <a href="<?= htmlspecialchars($file_path) ?>" class="file-link" target="_blank"><i class="fas fa-eye"></i> View PDF</a>
                            <a href="<?= htmlspecialchars($file_path) ?>" class="file-link download" download><i class="fas fa-download"></i> Download</a>
                        </div>
                        <div class="pdf-viewer"><iframe src="<?= htmlspecialchars($file_path) ?>"></iframe></div>
                    <?php else: ?>
                        <p>File not found on server.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>No manuscript file uploaded.</p>
                <?php endif; ?>
            </div>

            <?php if ($is_archived == 0): ?>
            <div class="action-buttons">
                <button class="btn btn-approve" onclick="showApproveModal()">
                    <i class="fas fa-check-circle"></i> APPROVE & FORWARD
                </button>
                <button class="btn btn-revise" onclick="showReviseModal()">
                    <i class="fas fa-edit"></i> REQUEST REVISION
                </button>
                <button class="btn btn-reject" onclick="showRejectModal()">
                    <i class="fas fa-times-circle"></i> REJECT THESIS
                </button>
                <button class="btn btn-archive" onclick="openArchiveModal(<?= $thesis_id ?>)">
                    <i class="fas fa-archive"></i> ARCHIVE THESIS
                </button>
            </div>
            <?php elseif ($is_archived == 1): ?>
            <div class="action-buttons">
                <div style="background:#e2e3e5; padding:1rem; border-radius:8px; width:100%;">
                    <i class="fas fa-archive"></i> This thesis has been archived.
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#28a745;"><i class="fas fa-check-circle"></i> Approve Thesis</h3>
        <p>Forward this thesis to Coordinator for review? The coordinator will be notified.</p>
        <form method="POST" action="">
            <input type="hidden" name="approve_thesis" value="1">
            <textarea name="feedback" rows="3" placeholder="Optional feedback for the student..." style="width:100%; margin:1rem 0; padding:0.75rem; border:1px solid #e0e0e0; border-radius:6px;"></textarea>
            <div class="modal-buttons">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeApproveModal()">Cancel</button>
                <button type="submit" class="btn btn-approve">Confirm Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- Revise Modal -->
<div id="reviseModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#ffc107;"><i class="fas fa-edit"></i> Request Revision</h3>
        <p>Request revisions for this thesis? Student will be notified.</p>
        <form method="POST" action="">
            <input type="hidden" name="revise_thesis" value="1">
            <textarea name="feedback" rows="3" placeholder="Revision instructions..." style="width:100%; margin:1rem 0; padding:0.75rem; border:1px solid #e0e0e0; border-radius:6px;" required></textarea>
            <div class="modal-buttons">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeReviseModal()">Cancel</button>
                <button type="submit" class="btn btn-revise">Confirm Revision</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <h3 style="color:#dc3545;"><i class="fas fa-times-circle"></i> Reject Thesis</h3>
        <p>Are you sure you want to reject this thesis?</p>
        <form method="POST" action="">
            <input type="hidden" name="reject_thesis" value="1">
            <textarea name="feedback" rows="3" placeholder="Feedback for the student..." style="width:100%; margin:1rem 0; padding:0.75rem; border:1px solid #e0e0e0; border-radius:6px;" required></textarea>
            <div class="modal-buttons">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn btn-reject">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<!-- Archive Modal -->
<div id="archiveModal" class="modal">
    <div class="modal-content">
        <h3><i class="fas fa-archive"></i> Archive Thesis</h3>
        <form method="POST">
            <input type="hidden" name="thesis_id" id="archive_thesis_id" value="<?= $thesis_id ?>">
            <div class="form-group">
                <label>Retention Period:</label>
                <select name="retention_period" class="form-control">
                    <option value="5">5 years</option>
                    <option value="10">10 years</option>
                    <option value="20">20 years</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes:</label>
                <textarea name="archive_notes" rows="3" placeholder="Optional notes..." style="width:100%; padding:0.75rem; border:1px solid #e0e0e0; border-radius:6px;"></textarea>
            </div>
            <div class="modal-buttons">
                <button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeArchiveModal()">Cancel</button>
                <button type="submit" name="archive_thesis" class="btn btn-archive">Archive</button>
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
    
    function showApproveModal(){
        document.getElementById('approveModal').style.display='flex';
    }
    function closeApproveModal(){
        document.getElementById('approveModal').style.display='none';
    }
    
    function showReviseModal(){
        document.getElementById('reviseModal').style.display='flex';
    }
    function closeReviseModal(){
        document.getElementById('reviseModal').style.display='none';
    }
    
    function showRejectModal(){
        document.getElementById('rejectModal').style.display='flex';
    }
    function closeRejectModal(){
        document.getElementById('rejectModal').style.display='none';
    }
    
    function openArchiveModal(id){
        document.getElementById('archive_thesis_id').value=id;
        document.getElementById('archiveModal').style.display='flex';
    }
    function closeArchiveModal(){
        document.getElementById('archiveModal').style.display='none';
    }
    
    window.onclick=function(e){
        if(e.target.classList.contains('modal')){
            e.target.style.display='none';
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.getElementById('approveModal').style.display='none';
            document.getElementById('reviseModal').style.display='none';
            document.getElementById('rejectModal').style.display='none';
            document.getElementById('archiveModal').style.display='none';
            if (sidebar.classList.contains('show')) toggleSidebar();
        }
    });
</script>
</body>
</html>