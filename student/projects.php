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

// UPDATED: Dili na mogamit og student_table - ang user_id kay mao na ang student_id
$student_id = $user_id;

// Get all projects/theses submitted by the student
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
    // If status is not set, determine from is_archived
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

$pageTitle = "My Projects";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            color: #000000;
            line-height: 1.6;
        }

        body.dark-mode {
            background: #2d2d2d;
            color: #e0e0e0;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 5px 0 20px rgba(0,0,0,0.3);
        }

        .sidebar.show {
            left: 0;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            color: white;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link i {
            width: 20px;
            color: white;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.show {
            display: block;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 0;
            min-height: 100vh;
            padding: 2rem;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .topbar {
            background: #3a3a3a;
        }

        .topbar h1 {
            font-size: 1.5rem;
            color: #732529;
        }

        body.dark-mode .topbar h1 {
            color: #FE4853;
        }

        .hamburger-menu {
            font-size: 1.5rem;
            cursor: pointer;
            color: #FE4853;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .hamburger-menu:hover {
            background: rgba(254, 72, 83, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-name {
            font-weight: 500;
            color: #333;
        }

        body.dark-mode .user-name {
            color: #e0e0e0;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .mobile-menu-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1001;
            border: none;
            background: #FE4853;
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            display: none;
            font-size: 1.2rem;
        }

        /* Projects Container */
        .projects-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .no-projects {
            text-align: center;
            padding: 4rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .no-projects {
            background: #3a3a3a;
        }

        .no-projects i {
            font-size: 4rem;
            color: #FE4853;
            margin-bottom: 1rem;
        }

        .no-projects h3 {
            font-size: 1.5rem;
            color: #732529;
            margin-bottom: 0.5rem;
        }

        body.dark-mode .no-projects h3 {
            color: #FE4853;
        }

        .project-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #FE4853;
        }

        body.dark-mode .project-card {
            background: #3a3a3a;
        }

        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.15);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        body.dark-mode .project-header {
            border-bottom-color: #555;
        }

        .project-header h2 {
            font-size: 1.2rem;
            color: #732529;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        body.dark-mode .project-header h2 {
            color: #FE4853;
        }

        .feedback-badge {
            background: #fef2f2;
            color: #FE4853;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .certificate-badge {
            background: #10b981;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending-coordinator {
            background: #cce5ff;
            color: #004085;
        }

        .status-forwarded {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-archived {
            background: #e2e3e5;
            color: #383d41;
        }

        .project-progress {
            margin-bottom: 1rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            margin-bottom: 0.3rem;
            color: #6E6E6E;
        }

        .progress-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #FE4853;
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .project-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
            font-size: 0.8rem;
            color: #6E6E6E;
        }

        body.dark-mode .project-meta {
            color: #e0e0e0;
        }

        .project-meta i {
            color: #FE4853;
            width: 18px;
        }

        .project-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid #eee;
        }

        body.dark-mode .project-actions {
            border-top-color: #555;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: #FE4853;
            color: white;
        }

        .btn-primary:hover {
            background: #732529;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        body.dark-mode .btn-secondary {
            background: #4a4a4a;
            color: #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .btn-certificate {
            background: #8b5cf6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-certificate:hover {
            background: #6d28d9;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .main-content {
                padding: 1rem;
                margin-top: 60px;
            }
            
            .topbar {
                display: none;
            }
            
            .project-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .project-meta {
                grid-template-columns: 1fr;
            }
            
            .project-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .project-card {
                padding: 1rem;
            }
            
            .project-header h2 {
                font-size: 1rem;
            }
        }

        /* Dark mode toggle */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 45px;
            height: 45px;
            background: #FE4853;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3);
            z-index: 1000;
            transition: all 0.3s;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }
    </style>
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
                <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
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
                            <?php if (($project['feedback_count'] ?? 0) > 0): ?>
                                <div><i class="fas fa-comments"></i> <strong>Feedback Received:</strong> <?= $project['feedback_count'] ?></div>
                            <?php endif; ?>
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

<script>
    // Sidebar toggle functionality
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const themeToggle = document.getElementById('themeToggle');

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    function toggleSidebar(e) {
        e.stopPropagation();
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close sidebar on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Close sidebar on window resize (if screen becomes larger)
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Dark mode toggle
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            const icon = themeToggle.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('darkMode', 'true');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
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