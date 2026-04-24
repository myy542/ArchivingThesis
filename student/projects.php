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

// Get user data
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

// student_id = user_id
$student_id = $user_id;

// Get all projects/theses
$projects_query = "SELECT t.*, 
                   (SELECT COUNT(*) FROM feedback_table WHERE thesis_id = t.thesis_id) as feedback_count
                   FROM thesis_table t
                   WHERE t.student_id = ?
                   ORDER BY t.date_submitted DESC";
$projects_stmt = $conn->prepare($projects_query);
$projects_stmt->bind_param("i", $student_id);
$projects_stmt->execute();
$projects_result = $projects_stmt->get_result();

$projects = [];
while ($row = $projects_result->fetch_assoc()) {
    if (!isset($row['status']) || $row['status'] === null) {
        $row['status'] = ($row['is_archived'] == 1) ? 'archived' : 'pending';
    }
    $projects[] = $row;
}
$projects_stmt->close();

// Helper functions
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

function calculateProgress($status, $feedback_count) {
    $status_lower = strtolower((string)$status);
    if ($status_lower == 'archived') return 100;
    if ($status_lower == 'approved') return 100;
    if ($status_lower == 'rejected') return 0;
    if ($status_lower == 'forwarded_to_dean') return 85;
    if ($status_lower == 'pending_coordinator') return 70;
    if ($status_lower == 'pending') return 50 + min($feedback_count * 5, 20);
    return 30;
}

// Get notification count
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['count'];
}
$notif_stmt->close();

$pageTitle = "My Projects";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/projects.css">
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>
<div class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Theses Archive</h2>
        <p>Student Portal</p>
    </div>
    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="projects.php" class="nav-link active">
            <i class="fas fa-folder-open"></i> My Projects
        </a>
        <a href="submission.php" class="nav-link">
            <i class="fas fa-upload"></i> Submit Thesis
        </a>
        <a href="archived.php" class="nav-link">
            <i class="fas fa-archive"></i> Archived Theses
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="profile.php" class="nav-link" style="margin-bottom: 0.5rem;">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </div>
                <h1>My Projects</h1>
            </div>
            <div class="user-info">
                <div class="notification-container">
                    <a href="notification.php" class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="avatar-container">
                    <div class="avatar-dropdown">
                        <div class="avatar" id="avatarBtn">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="dropdown-content" id="dropdownMenu">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <hr>
                            <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="projects-container">
            <?php if (empty($projects)): ?>
                <div class="no-projects">
                    <i class="fas fa-folder-open"></i>
                    <h3>No Projects Yet</h3>
                    <p>You haven't submitted any thesis projects yet.</p>
                    <a href="submission.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-upload"></i> Submit Your First Thesis
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): 
                    $progress = calculateProgress($project['status'], $project['feedback_count'] ?? 0);
                    $statusClass = getStatusClass($project['status']);
                    $statusText = getStatusText($project['status']);
                ?>
                    <div class="project-card" id="project-<?= $project['thesis_id'] ?>">
                        <div class="project-header">
                            <h2>
                                <?= htmlspecialchars($project['title']) ?>
                                <?php if (($project['feedback_count'] ?? 0) > 0): ?>
                                    <span class="feedback-badge">
                                        <i class="fas fa-comment"></i> <?= $project['feedback_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </h2>
                            <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>

                        <div class="project-progress">
                            <div class="progress-label">
                                <span>Overall Progress</span>
                                <span><?= $progress ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= $progress ?>%;"></div>
                            </div>
                        </div>

                        <div class="project-meta">
                            <div><i class="fas fa-user-tie"></i> <strong>Adviser:</strong> <?= htmlspecialchars($project['adviser'] ?? 'Not Assigned') ?></div>
                            <div><i class="fas fa-tags"></i> <strong>Keywords:</strong> <?= htmlspecialchars($project['keywords'] ?? 'None') ?></div>
                            <div><i class="fas fa-building"></i> <strong>Department:</strong> <?= htmlspecialchars($project['department'] ?? 'N/A') ?></div>
                            <div><i class="fas fa-calendar"></i> <strong>Year:</strong> <?= htmlspecialchars($project['year'] ?? 'N/A') ?></div>
                            <div><i class="fas fa-calendar-alt"></i> <strong>Submitted:</strong> <?= date('F d, Y', strtotime($project['date_submitted'])) ?></div>
                        </div>

                        <div class="project-actions">
                            <a href="view_project.php?id=<?= $project['thesis_id'] ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <?php if (!empty($project['file_path'])): ?>
                                <a href="../<?= htmlspecialchars($project['file_path']) ?>" class="btn btn-secondary" download>
                                    <i class="fas fa-download"></i> Download Manuscript
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<script src="js/projects.js"></script>
</body>
</html>