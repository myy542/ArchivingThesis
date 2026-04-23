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

// REMOVED: student_table query - diretso na lang gamit ang user_id
$student_id = $user_id;

// Get thesis details
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

// If status is not set, determine from is_archived
if (!isset($thesis['status']) || $thesis['status'] === null) {
    $thesis['status'] = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';
}

// Get all feedback
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

// Helper function for status display
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #f5f5f5; color: #000000; line-height: 1.6; }
        body.dark-mode { background: #2d2d2d; color: #e0e0e0; }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 0; left: -300px; width: 280px; height: 100vh;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white; display: flex; flex-direction: column; z-index: 1000;
            transition: left 0.3s ease; box-shadow: 5px 0 20px rgba(0,0,0,0.3);
        }
        .sidebar.show { left: 0; }
        .sidebar-header { padding: 2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header h2 { font-size: 1.5rem; color: white; }
        .sidebar-header p { font-size: 0.875rem; color: rgba(255,255,255,0.9); }
        .sidebar-nav { flex: 1; padding: 1.5rem 0.5rem; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: rgba(255,255,255,0.9); text-decoration: none; border-radius: 8px; transition: all 0.2s; font-weight: 500; }
        .nav-link i { width: 20px; color: white; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; }
        .sidebar-footer { padding: 1.5rem; border-top: 1px solid rgba(255,255,255,0.2); }
        .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1rem; color: rgba(255,255,255,0.9); text-decoration: none; border-radius: 8px; }
        .logout-btn:hover { background: rgba(255,255,255,0.2); }
        .overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
        .overlay.show { display: block; }

        /* Main Content */
        .main-content { flex: 1; margin-left: 0; min-height: 100vh; padding: 2rem; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1rem; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(110,110,110,0.1); }
        body.dark-mode .topbar { background: #3a3a3a; }
        .topbar h1 { font-size: 1.5rem; color: #732529; }
        body.dark-mode .topbar h1 { color: #FE4853; }
        .hamburger-menu { font-size: 1.5rem; cursor: pointer; color: #FE4853; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        .hamburger-menu:hover { background: rgba(254,72,83,0.1); }
        .user-info { display: flex; align-items: center; gap: 1rem; }
        .user-name { font-weight: 500; color: #333; }
        body.dark-mode .user-name { color: #e0e0e0; }
        .avatar { width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, #FE4853 0%, #732529 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .mobile-menu-btn { position: fixed; top: 16px; right: 16px; z-index: 1001; background: #FE4853; color: white; padding: 12px 15px; border-radius: 10px; cursor: pointer; display: none; font-size: 1.2rem; }

        /* Back Button */
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #FE4853; text-decoration: none; margin-bottom: 1rem; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }

        /* Thesis Card */
        .thesis-card { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(110,110,110,0.1); border-left: 4px solid #FE4853; }
        body.dark-mode .thesis-card { background: #3a3a3a; }
        .thesis-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; }
        body.dark-mode .thesis-header { border-bottom-color: #555; }
        .thesis-title { font-size: 1.5rem; font-weight: 700; color: #732529; }
        body.dark-mode .thesis-title { color: #FE4853; }
        .status { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-pending-coordinator { background: #cce5ff; color: #004085; }
        .status-forwarded { background: #d1ecf1; color: #0c5460; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-archived { background: #e2e3e5; color: #383d41; }

        /* Info Grid */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .info-item { background: #f8fafc; padding: 0.8rem; border-radius: 8px; }
        body.dark-mode .info-item { background: #4a4a4a; }
        .info-label { font-size: 0.7rem; color: #6E6E6E; margin-bottom: 0.2rem; text-transform: uppercase; }
        .info-value { font-size: 0.9rem; font-weight: 500; }

        /* Abstract */
        .abstract-section { margin-bottom: 1.5rem; }
        .abstract-section h3 { color: #732529; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; }
        body.dark-mode .abstract-section h3 { color: #FE4853; }
        .abstract-text { background: #f8fafc; padding: 1rem; border-radius: 8px; line-height: 1.6; font-size: 0.9rem; }
        body.dark-mode .abstract-text { background: #4a4a4a; }

        /* Keywords */
        .keywords-section { margin-bottom: 1.5rem; }
        .keywords-section h3 { color: #732529; margin-bottom: 0.5rem; font-size: 1rem; }
        body.dark-mode .keywords-section h3 { color: #FE4853; }
        .keyword { display: inline-block; padding: 0.2rem 0.6rem; background: #fef2f2; color: #FE4853; border-radius: 20px; font-size: 0.7rem; margin-right: 0.3rem; margin-bottom: 0.3rem; }

        /* File Section */
        .file-section { background: #f8fafc; border-radius: 12px; padding: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        body.dark-mode .file-section { background: #4a4a4a; }
        .file-info { display: flex; align-items: center; gap: 1rem; }
        .file-info i { font-size: 1.5rem; color: #FE4853; }
        .btn-download { background: #10b981; color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .btn-download:hover { background: #059669; transform: translateY(-2px); }
        .pdf-viewer { margin-top: 1rem; border-radius: 12px; overflow: hidden; border: 1px solid #e0e0e0; background: white; }
        .pdf-viewer iframe { width: 100%; height: 600px; border: none; }
        .pdf-error { padding: 2rem; text-align: center; color: #6E6E6E; }

        /* Feedback Section */
        .feedback-section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(110,110,110,0.1); margin-top: 1.5rem; }
        body.dark-mode .feedback-section { background: #3a3a3a; }
        .feedback-section h3 { color: #732529; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        body.dark-mode .feedback-section h3 { color: #FE4853; }
        .feedback-item { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; border-left: 3px solid #FE4853; }
        body.dark-mode .feedback-item { background: #4a4a4a; }
        .feedback-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.5rem; }
        .feedback-author { font-weight: 600; color: #732529; display: flex; align-items: center; gap: 0.5rem; }
        body.dark-mode .feedback-author { color: #FE4853; }
        .feedback-date { font-size: 0.7rem; color: #6E6E6E; }
        .feedback-comment { font-size: 0.85rem; line-height: 1.5; }
        .no-feedback { text-align: center; padding: 2rem; color: #6E6E6E; }

        /* Buttons */
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.8rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-primary { background: #FE4853; color: white; }
        .btn-primary:hover { background: #732529; transform: translateY(-2px); }
        .btn-secondary { background: #f0f0f0; color: #333; }
        body.dark-mode .btn-secondary { background: #4a4a4a; color: #e0e0e0; }

        /* Theme Toggle */
        .theme-toggle { position: fixed; bottom: 20px; left: 20px; width: 45px; height: 45px; background: #FE4853; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(254,72,83,0.3); z-index: 1000; transition: all 0.3s; }
        .theme-toggle:hover { transform: scale(1.1); }

        @media (max-width: 768px) {
            .mobile-menu-btn { display: block; }
            .main-content { padding: 1rem; margin-top: 60px; }
            .topbar { display: none; }
            .thesis-card { padding: 1rem; }
            .thesis-title { font-size: 1.2rem; }
            .info-grid { grid-template-columns: 1fr; }
            .pdf-viewer iframe { height: 400px; }
            .feedback-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
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

            <!-- File Section -->
            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div><strong>Manuscript File</strong><br><small><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></small></div>
                </div>
                <?php if (!empty($thesis['file_path'])): ?>
                    <a href="../<?= htmlspecialchars($thesis['file_path']) ?>" class="btn-download" download><i class="fas fa-download"></i> Download PDF</a>
                <?php endif; ?>
            </div>

            <!-- PDF Viewer -->
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

        <!-- Feedback Section -->
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
            <?php if (isset($thesis['status']) && $thesis['status'] == 'pending'): ?>
                <span class="btn btn-secondary" style="opacity: 0.7; cursor: not-allowed;"><i class="fas fa-edit"></i> Edit (Locked - Under Review)</span>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');

    function openSidebar() { sidebar.classList.add('show'); overlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('show'); overlay.classList.remove('show'); document.body.style.overflow = ''; }
    function toggleSidebar(e) { e.stopPropagation(); if (sidebar.classList.contains('show')) closeSidebar(); else openSidebar(); }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && sidebar.classList.contains('show')) closeSidebar(); });
    window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('show')) closeSidebar(); });

    // Dark mode
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.classList.remove('fa-moon'); icon.classList.add('fa-sun');
                localStorage.setItem('darkMode', 'true');
            } else {
                icon.classList.remove('fa-sun'); icon.classList.add('fa-moon');
                localStorage.setItem('darkMode', 'false');
            }
        });
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
            themeToggle.querySelector('i').classList.remove('fa-moon');
            themeToggle.querySelector('i').classList.add('fa-sun');
        }
    }
</script>

</body>
</html>