<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($thesis_id == 0) {
    header("Location: projects.php");
    exit;
}

$user_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

$first_name = $user_data['first_name'] ?? '';
$last_name = $user_data['last_name'] ?? '';
$fullName = trim($first_name . " " . $last_name);
$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) : "U";

$student_id = $user_id;

$thesis_query = "SELECT t.*, 
                 (SELECT COUNT(*) FROM feedback_table WHERE thesis_id = t.thesis_id) as feedback_count
                 FROM thesis_table t
                 WHERE t.thesis_id = ? AND t.student_id = ?";
$thesis_stmt = $conn->prepare($thesis_query);
$thesis_stmt->bind_param("ii", $thesis_id, $student_id);
$thesis_stmt->execute();
$thesis_result = $thesis_stmt->get_result();
$thesis = $thesis_result->fetch_assoc();
$thesis_stmt->close();

if (!$thesis) {
    header("Location: projects.php");
    exit;
}

if (!isset($thesis['status']) || $thesis['status'] === null) {
    $thesis['status'] = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';
}

$feedback_query = "SELECT f.*, u.first_name, u.last_name, u.role_id
                   FROM feedback_table f
                   JOIN user_table u ON f.faculty_id = u.user_id
                   WHERE f.thesis_id = ?
                   ORDER BY f.feedback_date DESC";
$feedback_stmt = $conn->prepare($feedback_query);
$feedback_stmt->bind_param("i", $thesis_id);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();
$feedbacks = [];
while ($row = $feedback_result->fetch_assoc()) {
    $feedbacks[] = $row;
}
$feedback_stmt->close();

function getStatusClass($status) {
    $status = strtolower((string)$status);
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'pending_coordinator': return 'status-pending-coordinator';
        case 'forwarded_to_dean': return 'status-forwarded';
        case 'approved': return 'status-approved';
        case 'rejected': return 'status-rejected';
        case 'archived': return 'status-archived';
        default: return 'status-pending';
    }
}

function getStatusText($status) {
    $status = strtolower((string)$status);
    switch ($status) {
        case 'pending': return 'Pending Faculty Review';
        case 'pending_coordinator': return 'Pending Coordinator Review';
        case 'forwarded_to_dean': return 'Forwarded to Dean';
        case 'approved': return 'Approved';
        case 'rejected': return 'Rejected';
        case 'archived': return 'Archived';
        default: return ucfirst($status);
    }
}

function getRoleName($role_id) {
    switch ($role_id) {
        case 3: return 'Faculty';
        case 4: return 'Dean';
        case 6: return 'Coordinator';
        default: return 'Reviewer';
    }
}

$pageTitle = "View Project - " . htmlspecialchars($thesis['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/view_project.css">
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
<div class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Theses Archive</h2>
        <p>Student Portal</p>
    </div>
    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
        <a href="projects.php" class="nav-link active"><i class="fas fa-folder-open"></i> My Projects</a>
        <a href="submission.php" class="nav-link"><i class="fas fa-upload"></i> Submit Thesis</a>
        <a href="archived.php" class="nav-link"><i class="fas fa-archive"></i> Archived Theses</a>
    </nav>
    <div class="sidebar-footer">
        <a href="profile.php" class="nav-link" style="margin-bottom: 0.5rem;"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
                <h1>Project Details</h1>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
        </header>

        <a href="projects.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to My Projects</a>

        <div class="thesis-card">
            <div class="thesis-header">
                <h1 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h1>
                <span class="status <?= getStatusClass($thesis['status']) ?>"><?= getStatusText($thesis['status']) ?></span>
            </div>

            <div class="info-grid">
                <div class="info-item"><div class="info-label"><i class="fas fa-user-tie"></i> Adviser</div><div class="info-value"><?= htmlspecialchars($thesis['adviser'] ?? 'Not Assigned') ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-building"></i> Department</div><div class="info-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-calendar"></i> Year</div><div class="info-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-calendar-alt"></i> Date Submitted</div><div class="info-value"><?= date('F d, Y', strtotime($thesis['date_submitted'])) ?></div></div>
            </div>

            <div class="abstract-section">
                <h3><i class="fas fa-align-left"></i> Abstract</h3>
                <div class="abstract-text"><?= nl2br(htmlspecialchars($thesis['abstract'])) ?></div>
            </div>

            <?php if (!empty($thesis['keywords'])): ?>
            <div class="keywords-section">
                <h3><i class="fas fa-tags"></i> Keywords</h3>
                <?php 
                $keywords = explode(',', $thesis['keywords']);
                foreach ($keywords as $kw): 
                    $kw = trim($kw);
                    if (!empty($kw)):
                ?>
                    <span class="keyword"><?= htmlspecialchars($kw) ?></span>
                <?php endif; endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div><strong>Manuscript File</strong><br><small><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></small></div>
                </div>
                <?php if (!empty($thesis['file_path'])): ?>
                    <a href="../<?= htmlspecialchars($thesis['file_path']) ?>" class="btn-download" download><i class="fas fa-download"></i> Download PDF</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($thesis['file_path'])): 
                $full_file_path = '../' . $thesis['file_path'];
                if (file_exists($full_file_path)):
            ?>
            <div class="pdf-viewer">
                <iframe src="<?= htmlspecialchars($full_file_path) ?>"></iframe>
            </div>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i> <p>PDF file not found on server.</p></div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i> <p>No manuscript file uploaded.</p></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="feedback-section">
            <h3><i class="fas fa-comments"></i> Feedback & Reviews</h3>
            <?php if (empty($feedbacks)): ?>
                <div class="no-feedback">
                    <i class="fas fa-comment-slash"></i>
                    <p>No feedback received yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                <div class="feedback-item">
                    <div class="feedback-header">
                        <div class="feedback-author">
                            <i class="fas fa-user-circle"></i>
                            <?= htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']) ?>
                            <span style="font-size: 0.7rem; background: #fef2f2; padding: 2px 8px; border-radius: 12px; color: #FE4853;">
                                <?= getRoleName($feedback['role_id']) ?>
                            </span>
                        </div>
                        <div class="feedback-date">
                            <i class="far fa-clock"></i> <?= date('F d, Y h:i A', strtotime($feedback['feedback_date'])) ?>
                        </div>
                    </div>
                    <div class="feedback-comment">
                        <?= nl2br(htmlspecialchars($feedback['comments'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
            <a href="projects.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Projects</a>
        </div>
    </main>
</div>

<script src="js/view_project.js"></script>
</body>
</html>