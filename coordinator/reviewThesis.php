<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION["role"] ?? '';
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is coordinator
if ($role !== 'coordinator') {
    header("Location: ../authentication/login.php");
    exit;
}

// Get user info with department
$stmt = $conn->prepare("SELECT first_name, last_name, department_id FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$fullName = $first . " " . $last;
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "CO";
$coordinator_dept_id = $user['department_id'] ?? null;

// Check if thesis_table exists
$thesis_table_exists = false;
$check_thesis = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis && $check_thesis->num_rows > 0) {
    $thesis_table_exists = true;
}

// GET ALL PENDING THESES (for list view)
$pending_theses_list = [];
if ($thesis_table_exists && $thesis_id == 0) {
    if ($coordinator_dept_id) {
        $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                          FROM thesis_table t
                          JOIN user_table u ON t.student_id = u.user_id
                          LEFT JOIN department_table d ON t.department_id = d.department_id
                          WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                          AND t.department_id = ?
                          ORDER BY t.date_submitted DESC";
        $pending_stmt = $conn->prepare($pending_query);
        $pending_stmt->bind_param("i", $coordinator_dept_id);
    } else {
        $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                          FROM thesis_table t
                          JOIN user_table u ON t.student_id = u.user_id
                          LEFT JOIN department_table d ON t.department_id = d.department_id
                          WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                          ORDER BY t.date_submitted DESC";
        $pending_stmt = $conn->prepare($pending_query);
    }
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $pending_theses_list[] = $row;
        }
    }
    $pending_stmt->close();
}

// GET SINGLE THESIS DETAILS
$thesis = null;
$thesis_title = 'Unknown Thesis';
$thesis_author = 'Unknown Author';
$thesis_abstract = 'No abstract available.';
$thesis_file = '';
$thesis_date = '';
$thesis_is_archived = 0;
$student_name = '';
$student_id = null;
$thesis_department_name = '';
$thesis_department_id = null;

if ($thesis_id > 0 && $thesis_table_exists) {
    $thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email, u.user_id as student_user_id,
                            d.department_name, d.department_code
                     FROM thesis_table t
                     JOIN user_table u ON t.student_id = u.user_id
                     LEFT JOIN department_table d ON t.department_id = d.department_id
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
        $thesis_department_name = $thesis_row['department_name'] ?? 'N/A';
        $thesis_department_id = $thesis_row['department_id'] ?? null;
        
        // Check if coordinator has access to this thesis
        if ($coordinator_dept_id && $thesis_department_id != $coordinator_dept_id) {
            $_SESSION['error_message'] = "You are not authorized to review this thesis.";
            header("Location: coordinatorDashboard.php");
            exit;
        }
    }
    $thesis_stmt->close();
}

$message = '';
$messageType = '';

// HANDLE FORWARD TO DEAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['forward_to_dean'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $coordinator_name = $fullName;
    
    // Find dean with same department
    $dean_query = "SELECT user_id FROM user_table WHERE role_id IN (2,3,4,5) AND department_id = ? LIMIT 1";
    $dean_stmt = $conn->prepare($dean_query);
    $dean_stmt->bind_param("i", $thesis_department_id);
    $dean_stmt->execute();
    $dean_result = $dean_stmt->get_result();
    $dean = $dean_result->fetch_assoc();
    $dean_stmt->close();
    
    if ($dean) {
        $notifMessage = "Thesis ready for Dean approval: \"" . $thesis_title . "\" from student " . $student_name . ". Forwarded by Coordinator: " . $coordinator_name;
        
        $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'dean_forward', 0, NOW())";
        $insert_stmt = $conn->prepare($insert_notif);
        $insert_stmt->bind_param("iis", $dean['user_id'], $thesis_id_post, $notifMessage);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        $_SESSION['success_message'] = "Thesis forwarded to Dean successfully!";
    } else {
        $_SESSION['error_message'] = "No dean found for this department.";
    }
    
    header("Location: coordinatorDashboard.php");
    exit();
}

// HANDLE REQUEST REVISIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_revision'])) {
    $thesis_id_post = intval($_POST['thesis_id']);
    $revision_feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';
    $coordinator_name = $fullName;
    
    // Get faculty advisers in the same department
    $faculty_query = "SELECT user_id FROM user_table WHERE role_id IN (2,3,4) AND department_id = ?";
    $faculty_stmt = $conn->prepare($faculty_query);
    $faculty_stmt->bind_param("i", $thesis_department_id);
    $faculty_stmt->execute();
    $faculty_result = $faculty_stmt->get_result();
    
    if ($faculty_result && $faculty_result->num_rows > 0) {
        $notifMessage = "Revision requested for thesis: \"" . $thesis_title . "\". Coordinator: " . $coordinator_name . " Feedback: " . $revision_feedback;
        $insert_notif = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'revision_request', 0, NOW())";
        $insert_stmt = $conn->prepare($insert_notif);
        
        while ($faculty = $faculty_result->fetch_assoc()) {
            $faculty_id = $faculty['user_id'];
            $insert_stmt->bind_param("iis", $faculty_id, $thesis_id_post, $notifMessage);
            $insert_stmt->execute();
        }
        $insert_stmt->close();
    }
    $faculty_stmt->close();
    
    // Notify student
    $student_notif = "Revision requested for your thesis \"" . $thesis_title . "\". Coordinator feedback: " . $revision_feedback;
    $insert_student = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'student_notif', 0, NOW())";
    $student_stmt = $conn->prepare($insert_student);
    $student_stmt->bind_param("iis", $student_id, $thesis_id_post, $student_notif);
    $student_stmt->execute();
    $student_stmt->close();
    
    $_SESSION['success_message'] = "Revision requested! Faculty has been notified.";
    header("Location: coordinatorDashboard.php");
    exit();
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
    <link rel="stylesheet" href="css/reviewThesis.css">
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
            <!-- LIST VIEW -->
            <div class="pending-list-container">
                <h2><i class="fas fa-clock"></i> Theses Waiting for Coordinator Review</h2>
                <?php if (empty($pending_theses_list)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: #dc2626; margin-bottom: 12px;"></i>
                        <p>No pending theses to review.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="pending-table">
                            <thead>
                                <tr><th>Thesis Title</th><th>Student</th><th>Department</th><th>Date Submitted</th><th>Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_theses_list as $thesis_item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($thesis_item['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($thesis_item['first_name'] . ' ' . $thesis_item['last_name']) ?></td>
                                    <td><?= htmlspecialchars($thesis_item['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($thesis_item['department_code'] ?? 'N/A') ?>)</td>
                                    <td><?= date('M d, Y', strtotime($thesis_item['date_submitted'])) ?></td>
                                    <td><a href="reviewThesis.php?id=<?= $thesis_item['thesis_id'] ?>" class="btn-review-link"><i class="fas fa-chevron-right"></i> Review</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- DETAIL VIEW -->
            <div class="review-container">
                <?php if (!empty($message)): ?>
                    <div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="thesis-header">
                    <h2><?= htmlspecialchars($thesis_title) ?></h2>
                    <span class="status-badge pending_coordinator">Pending Coordinator Review</span>
                </div>

                <div class="thesis-details">
                    <div class="detail-item"><span class="detail-label">Student Name: </span><span class="detail-value"><?= htmlspecialchars($student_name) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Adviser: </span><span class="detail-value"><?= htmlspecialchars($thesis_author) ?></span></div>
                    <div class="detail-item"><span class="detail-label">Department: </span><span class="detail-value"><?= htmlspecialchars($thesis_department_name) ?></span></div>
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
                    <button class="btn btn-forward" onclick="showForwardModal()"><i class="fas fa-check-circle"></i> FORWARD TO DEAN</button>
                    <button class="btn btn-revise" onclick="showReviseModal()"><i class="fas fa-edit"></i> REQUEST REVISIONS</button>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Forward Modal -->
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

<!-- Revise Modal -->
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

<script src="js/reviewThesis.js"></script>
</body>
</html>