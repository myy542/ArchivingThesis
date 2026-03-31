<?php
session_start();
include("../config/db.php");

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

// Sample feedback data
$feedbacks = [
    ['id' => 1, 'thesis' => 'Impact of AI on education', 'message' => 'Please revise the methodology section. The research methodology needs more detail on data collection and analysis procedures.', 'date' => '2026-03-01', 'read' => false],
    ['id' => 2, 'thesis' => 'Blockchain for voting', 'message' => 'Add more recent references. The literature review should include papers from 2024-2025.', 'date' => '2026-02-28', 'read' => false],
    ['id' => 3, 'thesis' => 'Low-cost water filter', 'message' => 'Your abstract needs to be more concise. Please limit to 250 words and include key findings.', 'date' => '2026-02-27', 'read' => true],
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Feedback | Thesis Management System</title>
    
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

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            transition: left 0.3s ease;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .hamburger {
            display: none;
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

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }

        .page-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #991b1b;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #111827;
        }

        .stat-content p {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 4px;
        }

        /* Feedback List */
        .feedback-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .feedback-item {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
            position: relative;
        }

        .feedback-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .feedback-item.unread {
            border-left: 4px solid #dc2626;
            background: #fffaf5;
        }

        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .thesis-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .thesis-title i {
            color: #dc2626;
            font-size: 1.1rem;
        }

        .thesis-title h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .date {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .date i {
            font-size: 0.7rem;
        }

        .feedback-message {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            padding: 16px;
            background: #fef2f2;
            border-radius: 16px;
        }

        .feedback-message i {
            color: #dc2626;
            font-size: 1rem;
            opacity: 0.7;
        }

        .feedback-message p {
            color: #4b5563;
            line-height: 1.5;
            font-size: 0.9rem;
            flex: 1;
        }

        .feedback-actions {
            display: flex;
            justify-content: flex-end;
            gap: 16px;
            align-items: center;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .status-badge.unread {
            background: #fef2f2;
            color: #dc2626;
        }

        .status-badge.read {
            background: #f3f4f6;
            color: #6b7280;
        }

        .delete-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .delete-btn:hover {
            background: #fee2e2;
            transform: scale(1.02);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
            border: 1px solid #fee2e2;
        }

        .empty-state i {
            font-size: 3rem;
            color: #dc2626;
            margin-bottom: 16px;
        }

        .empty-state p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 99;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
            
            .hamburger {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .feedback-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .feedback-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            
            .feedback-item {
                padding: 16px;
            }
            
            .feedback-message {
                padding: 12px;
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

        body.dark-mode .stat-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .stat-content h3 {
            color: #fecaca;
        }

        body.dark-mode .feedback-item {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .feedback-item.unread {
            background: #2a2a2a;
        }

        body.dark-mode .thesis-title h3 {
            color: #fecaca;
        }

        body.dark-mode .feedback-message {
            background: #3d3d3d;
        }

        body.dark-mode .feedback-message p {
            color: #cbd5e1;
        }

        body.dark-mode .empty-state {
            background: #2d2d2d;
            border-color: #991b1b;
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

        body.dark-mode .delete-btn {
            background: #3d3d3d;
            color: #fecaca;
        }

        body.dark-mode .delete-btn:hover {
            background: #4a4a4a;
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
                <input type="text" placeholder="Search feedback...">
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
            <a href="reviewThesis.php" class="nav-item">
                <i class="fas fa-file-alt"></i>
                <span>Review Theses</span>
            </a>
            <a href="myFeedback.php" class="nav-item active">
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
            <h2>My Feedback</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-comment-dots"></i></div>
                <div class="stat-content">
                    <h3><?= count($feedbacks) ?></h3>
                    <p>Total Feedback</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                <div class="stat-content">
                    <h3><?= count(array_filter($feedbacks, function($f) { return !$f['read']; })) ?></h3>
                    <p>Unread</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?= count(array_filter($feedbacks, function($f) { return $f['read']; })) ?></h3>
                    <p>Read</p>
                </div>
            </div>
        </div>

        <!-- Feedback List -->
        <div class="feedback-list">
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <p>No feedback sent yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="feedback-item <?= $feedback['read'] ? 'read' : 'unread' ?>">
                        <div class="feedback-header">
                            <div class="thesis-title">
                                <i class="fas fa-book"></i>
                                <h3><?= htmlspecialchars($feedback['thesis']) ?></h3>
                            </div>
                            <div class="date">
                                <i class="far fa-calendar-alt"></i>
                                <span><?= date('F d, Y', strtotime($feedback['date'])) ?></span>
                            </div>
                        </div>
                        <div class="feedback-message">
                            <i class="fas fa-quote-left"></i>
                            <p><?= htmlspecialchars($feedback['message']) ?></p>
                        </div>
                        <div class="feedback-actions">
                            <span class="status-badge <?= $feedback['read'] ? 'read' : 'unread' ?>">
                                <i class="fas <?= $feedback['read'] ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                                <?= $feedback['read'] ? 'Read' : 'Unread' ?>
                            </span>
                            <a href="notification_handler.php?action=delete_feedback&id=<?= $feedback['id'] ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this feedback?')">
                                <i class="fas fa-trash-alt"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInput = document.querySelector('.search-area input');

        // Toggle Sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Toggle Profile Dropdown
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }

        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        // Search functionality
        function handleSearch() {
            const term = searchInput.value.toLowerCase();
            const feedbackItems = document.querySelectorAll('.feedback-item');
            
            feedbackItems.forEach(item => {
                const title = item.querySelector('.thesis-title h3')?.textContent.toLowerCase() || '';
                const message = item.querySelector('.feedback-message p')?.textContent.toLowerCase() || '';
                
                if (title.includes(term) || message.includes(term)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Dark Mode
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
                applyDarkMode();
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                        applyDarkMode();
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                        removeDarkMode();
                    }
                });
            }
        }

        function applyDarkMode() {
            const style = document.createElement('style');
            style.id = 'darkModeStyle';
            style.textContent = `
                body.dark-mode { background: #1a1a1a; }
                body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
                body.dark-mode .logo { color: #fecaca; }
                body.dark-mode .search-area { background: #3d3d3d; }
                body.dark-mode .search-area input { background: #3d3d3d; color: white; }
                body.dark-mode .profile-name { color: #fecaca; }
                body.dark-mode .stat-card { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .stat-content h3 { color: #fecaca; }
                body.dark-mode .feedback-item { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .feedback-item.unread { background: #2a2a2a; }
                body.dark-mode .thesis-title h3 { color: #fecaca; }
                body.dark-mode .feedback-message { background: #3d3d3d; }
                body.dark-mode .feedback-message p { color: #cbd5e1; }
                body.dark-mode .empty-state { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .profile-dropdown { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .profile-dropdown a { color: #e5e7eb; }
                body.dark-mode .delete-btn { background: #3d3d3d; color: #fecaca; }
            `;
            if (!document.getElementById('darkModeStyle')) {
                document.head.appendChild(style);
            }
        }

        function removeDarkMode() {
            const style = document.getElementById('darkModeStyle');
            if (style) style.remove();
        }

        // Event Listeners
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', toggleSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }

        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>