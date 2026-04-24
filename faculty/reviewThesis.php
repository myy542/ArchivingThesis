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

$stmt = $conn->prepare("SELECT first_name, last_name, department_id FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = trim($faculty["first_name"] ?? "");
$last  = trim($faculty["last_name"] ?? "");
$fullName = $first . " " . $last;
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "FA";
$faculty_dept_id = $faculty['department_id'] ?? null;

$thesis = null;

try {
    $query = "SELECT 
                t.*, 
                u.user_id as student_user_id,
                u.first_name as student_first_name, 
                u.last_name as student_last_name, 
                u.email as student_email,
                d.department_name,
                d.department_code
              FROM thesis_table t
              JOIN user_table u ON t.student_id = u.user_id
              LEFT JOIN department_table d ON t.department_id = d.department_id
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
    
    // Check if thesis belongs to faculty's department
    if ($thesis['department_id'] != $faculty_dept_id) {
        $_SESSION['error_message'] = "You are not authorized to review this thesis. This thesis belongs to a different department.";
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

$is_archived = isset($thesis['is_archived']) ? (int)$thesis['is_archived'] : 0;
$thesis_status = ($is_archived == 1) ? 'archived' : 'pending';

// ==================== HANDLE APPROVE ====================
if (isset($_POST['approve_thesis'])) {
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
        
        $student_name = $thesis['student_first_name'] . ' ' . $thesis['student_last_name'];
        $faculty_name = $first . ' ' . $last;
        $thesis_title = $thesis['title'];
        
        notifyCoordinator($conn, $thesis_id, $thesis_title, $student_name, $faculty_name);
        
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
    <link rel="stylesheet" href="css/reviewThesis.css">
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
            <div style="display: flex; align-items: center; gap: 1rem;"><div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div><h1>Review Thesis</h1></div>
            <div class="user-info"><div class="avatar"><?= htmlspecialchars($initials) ?></div></div>
        </header>
        <a href="facultyDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        <div class="review-container">
            <?php if (!empty($message)): ?><div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
            <div class="thesis-header"><h2><?= htmlspecialchars($thesis['title']) ?></h2><span class="status-badge <?= $is_archived == 1 ? 'archived' : 'pending' ?>"><?= $is_archived == 1 ? 'Archived' : 'Pending Review' ?></span></div>
            <div class="thesis-details">
                <div class="detail-item"><span class="detail-label">Student Name: </span><span class="detail-value"><?= htmlspecialchars($thesis['student_first_name'] . ' ' . $thesis['student_last_name']) ?></span></div>
                <div class="detail-item"><span class="detail-label">Email: </span><span class="detail-value"><?= htmlspecialchars($thesis['student_email']) ?></span></div>
                <div class="detail-item"><span class="detail-label">Department: </span><span class="detail-value"><?= htmlspecialchars($thesis['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($thesis['department_code'] ?? 'N/A') ?>)</span></div>
                <div class="detail-item"><span class="detail-label">Year: </span><span class="detail-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></span></div>
                <div class="detail-item"><span class="detail-label">Date Submitted: </span><span class="detail-value"><?= date('F d, Y', strtotime($thesis['date_submitted'] ?? 'now')) ?></span></div>
            </div>
            <div class="thesis-abstract"><h3>Abstract</h3><p><?= nl2br(htmlspecialchars($thesis['abstract'] ?? 'No abstract provided.')) ?></p></div>
            <div class="thesis-file">
                <h3><i class="fas fa-file-pdf"></i> Manuscript File</h3>
                <?php if (!empty($thesis['file_path'])): $file_path = '../' . $thesis['file_path']; if (file_exists($file_path)): ?>
                    <div class="file-actions"><a href="<?= htmlspecialchars($file_path) ?>" class="file-link" target="_blank"><i class="fas fa-eye"></i> View PDF</a><a href="<?= htmlspecialchars($file_path) ?>" class="file-link download" download><i class="fas fa-download"></i> Download</a></div>
                    <div class="pdf-viewer"><iframe src="<?= htmlspecialchars($file_path) ?>"></iframe></div>
                <?php else: ?><p>File not found on server.</p><?php endif; ?>
                <?php else: ?><p>No manuscript file uploaded.</p><?php endif; ?>
            </div>
            <?php if ($is_archived == 0): ?>
            <div class="action-buttons">
                <button class="btn btn-approve" onclick="showApproveModal()"><i class="fas fa-check-circle"></i> APPROVE & FORWARD</button>
                <button class="btn btn-revise" onclick="showReviseModal()"><i class="fas fa-edit"></i> REQUEST REVISION</button>
                <button class="btn btn-reject" onclick="showRejectModal()"><i class="fas fa-times-circle"></i> REJECT THESIS</button>
                <button class="btn btn-archive" onclick="openArchiveModal(<?= $thesis_id ?>)"><i class="fas fa-archive"></i> ARCHIVE THESIS</button>
            </div>
            <?php elseif ($is_archived == 1): ?>
            <div class="action-buttons"><div style="background:#e2e3e5; padding:1rem; border-radius:8px; width:100%;"><i class="fas fa-archive"></i> This thesis has been archived.</div></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal"><div class="modal-content"><h3 style="color:#28a745;"><i class="fas fa-check-circle"></i> Approve Thesis</h3><p>Forward this thesis to Coordinator for review? The coordinator will be notified.</p><form method="POST"><input type="hidden" name="approve_thesis" value="1"><textarea name="feedback" rows="3" placeholder="Optional feedback for the student..."></textarea><div class="modal-buttons"><button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeApproveModal()">Cancel</button><button type="submit" class="btn btn-approve">Confirm Approve</button></div></form></div></div>

<!-- Revise Modal -->
<div id="reviseModal" class="modal"><div class="modal-content"><h3 style="color:#ffc107;"><i class="fas fa-edit"></i> Request Revision</h3><p>Request revisions for this thesis? Student will be notified.</p><form method="POST"><input type="hidden" name="revise_thesis" value="1"><textarea name="feedback" rows="3" placeholder="Revision instructions..." required></textarea><div class="modal-buttons"><button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeReviseModal()">Cancel</button><button type="submit" class="btn btn-revise">Confirm Revision</button></div></form></div></div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal"><div class="modal-content"><h3 style="color:#dc3545;"><i class="fas fa-times-circle"></i> Reject Thesis</h3><p>Are you sure you want to reject this thesis?</p><form method="POST"><input type="hidden" name="reject_thesis" value="1"><textarea name="feedback" rows="3" placeholder="Feedback for the student..." required></textarea><div class="modal-buttons"><button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeRejectModal()">Cancel</button><button type="submit" class="btn btn-reject">Confirm Reject</button></div></form></div></div>

<!-- Archive Modal -->
<div id="archiveModal" class="modal"><div class="modal-content"><h3><i class="fas fa-archive"></i> Archive Thesis</h3><form method="POST"><input type="hidden" name="thesis_id" id="archive_thesis_id" value="<?= $thesis_id ?>"><div class="form-group"><label>Retention Period:</label><select name="retention_period"><option value="5">5 years</option><option value="10">10 years</option><option value="20">20 years</option></select></div><div class="form-group"><label>Notes:</label><textarea name="archive_notes" rows="3" placeholder="Optional notes..."></textarea></div><div class="modal-buttons"><button type="button" class="btn" style="background:#6c757d; color:white;" onclick="closeArchiveModal()">Cancel</button><button type="submit" name="archive_thesis" class="btn btn-archive">Archive</button></div></form></div></div>

<script>
    window.userData = {
        fullName: '<?php echo addslashes($fullName); ?>',
        initials: '<?php echo addslashes($initials); ?>',
        thesisId: <?php echo $thesis_id; ?>
    };
</script>

<script src="js/reviewThesis.js"></script>
</body>
</html>