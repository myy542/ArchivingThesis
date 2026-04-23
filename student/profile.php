<?php
session_start();
include("../config/db.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get user data - using the correct table name 'user_table'
$stmt = $conn->prepare("
    SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture
    FROM user_table
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

// Process user data
$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$full  = trim($first . " " . $last);
$initials = "";
if ($first && $last) {
    $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
} elseif ($first) {
    $initials = strtoupper(substr($first, 0, 1));
} else {
    $initials = "U";
}

$profilePicUrl = $user["profile_picture"]
    ? "../uploads/profile_pictures/" . $user["profile_picture"]
    : "";

$email   = trim($user["email"] ?? "");
$contact = trim($user["contact_number"] ?? "");
$address = trim($user["address"] ?? "");
$birth   = trim($user["birth_date"] ?? "");

// Get notification count - FIXED using is_read
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifResult = $notif_stmt->get_result()->fetch_assoc();
$notificationCount = $notifResult['total'] ?? 0;
$notif_stmt->close();

$pageTitle = "My Profile";
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        /* Top Navigation - full width */
        .top-nav {
            position: fixed;
            top: 0;
            right: 0;
            left: 0;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        /* Hamburger - ALWAYS VISIBLE */
        .hamburger {
            display: flex;
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
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .notification-icon:hover {
            background: #fee2e2;
        }

        .notification-icon i {
            font-size: 1.2rem;
            color: #dc2626;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
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
            font-size: 1rem;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
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

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #fee2e2;
        }

        /* Sidebar - COLLAPSIBLE (hidden by default) */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar.open {
            left: 0;
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
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
            margin-top: 5px;
            text-transform: uppercase;
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
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .theme-toggle {
            margin-bottom: 15px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
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

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Main Content - full width */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 2rem;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 24px;
            padding: 2rem;
            border: 1px solid #fee2e2;
            transition: all 0.3s;
        }

        .profile-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.1);
        }

        /* Avatar Section */
        .profile-top {
            text-align: center;
            margin-bottom: 2rem;
        }

        .big-avatar-wrapper,
        .big-avatar {
            width: 140px;
            height: 140px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            overflow: hidden;
        }

        .big-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border: 5px solid white;
        }

        .big-avatar {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.2rem;
            font-weight: bold;
            color: white;
        }

        .profile-info h1 {
            margin: 0.5rem 0 0.25rem;
            font-size: 2rem;
            color: #991b1b;
        }

        .student-id {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Details */
        .profile-details {
            margin: 1.5rem 0 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.9rem 0;
            border-bottom: 1px solid #fee2e2;
            gap: 1rem;
        }

        .detail-row strong {
            min-width: 100px;
            font-weight: 600;
            color: #991b1b;
        }

        .detail-row span {
            color: #6b7280;
            text-align: right;
            flex: 1;
        }

        /* Profile Actions */
        .profile-actions {
            display: flex;
            gap: 1rem;
        }

        .edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #dc2626;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.2s;
            flex: 1;
        }

        .edit-btn:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .edit-btn.secondary {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fee2e2;
        }

        .edit-btn.secondary:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        /* Progress Card */
        .profile-card.stats {
            padding: 1.75rem;
        }

        .profile-card.stats h3 {
            color: #991b1b;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }

        .progress-item {
            margin-bottom: 1.25rem;
        }

        .progress-item span {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .progress-bar {
            height: 8px;
            background: #fee2e2;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(to right, #dc2626, #991b1b);
            width: 0%;
            transition: width 0.8s ease;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-container {
                grid-template-columns: 1fr;
                max-width: 800px;
            }
        }

        @media (max-width: 768px) {
            .top-nav {
                padding: 0 16px;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .profile-top {
                margin-bottom: 1.5rem;
            }
            
            .big-avatar-wrapper,
            .big-avatar {
                width: 120px;
                height: 120px;
            }
            
            .profile-info h1 {
                font-size: 1.6rem;
            }
            
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .detail-row span {
                text-align: left;
            }
            
            .profile-actions {
                flex-direction: column;
            }
            
            .edit-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px;
            }
            
            .profile-card {
                padding: 1.5rem;
            }
            
            .big-avatar-wrapper,
            .big-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-info h1 {
                font-size: 1.4rem;
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

        body.dark-mode .profile-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-info h1 {
            color: #fecaca;
        }

        body.dark-mode .detail-row {
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .detail-row strong {
            color: #fecaca;
        }

        body.dark-mode .detail-row span {
            color: #cbd5e1;
        }

        body.dark-mode .edit-btn.secondary {
            background: #3d3d3d;
            color: #fecaca;
            border-color: #991b1b;
        }

        body.dark-mode .edit-btn.secondary:hover {
            background: #4a4a4a;
        }

        body.dark-mode .profile-card.stats h3 {
            color: #fecaca;
        }

        body.dark-mode .progress-bar {
            background: #3d3d3d;
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

        body.dark-mode .big-avatar-img {
            border-color: #3d3d3d;
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
                <input type="text" id="searchInput" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-icon" id="notificationIcon">
                <i class="far fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($full) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="edit_profile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
                    <hr>
                    <a href="../authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">STUDENT PORTAL</div>
        </div>
        
        <div class="nav-menu">
            <a href="student_dashboard.php" class="nav-item">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="projects.php" class="nav-item">
                <i class="fas fa-folder-open"></i>
                <span>My Projects</span>
            </a>
            <a href="submission.php" class="nav-item">
                <i class="fas fa-upload"></i>
                <span>Submit Thesis</span>
            </a>
            <a href="archived.php" class="nav-item">
                <i class="fas fa-archive"></i>
                <span>Archived Theses</span>
            </a>
            <a href="profile.php" class="nav-item active">
                <i class="fas fa-user-circle"></i>
                <span>Profile</span>
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
            <a href="../authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <!-- Main Profile Card -->
            <div class="profile-card main">
                <div class="profile-top">
                    <?php if ($profilePicUrl && file_exists(__DIR__ . "/../uploads/profile_pictures/" . $user["profile_picture"])): ?>
                        <div class="big-avatar-wrapper">
                            <img class="big-avatar-img" src="<?= htmlspecialchars($profilePicUrl) ?>?v=<?= time() ?>" alt="Profile Picture">
                        </div>
                    <?php else: ?>
                        <div class="big-avatar"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>

                    <div class="profile-info">
                        <h1><?= htmlspecialchars($full ?: "User") ?></h1>
                        <p class="student-id">Student</p>
                    </div>
                </div>

                <div class="profile-details">
                    <div class="detail-row">
                        <strong>Email</strong>
                        <span><?= htmlspecialchars($email ?: "Not provided") ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Contact</strong>
                        <span><?= htmlspecialchars($contact ?: "Not provided") ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Address</strong>
                        <span><?= htmlspecialchars($address ?: "Not provided") ?></span>
                    </div>
                    <div class="detail-row">
                        <strong>Birth Date</strong>
                        <span><?= htmlspecialchars($birth ?: "Not provided") ?></span>
                    </div>
                </div>

                <div class="profile-actions">
                    <a href="edit_profile.php" class="edit-btn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="change_password.php" class="edit-btn secondary">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>

            <!-- Progress Card -->
            <div class="profile-card stats">
                <h3>Thesis Progress</h3>

                <div class="progress-item">
                    <span>Overall Progress</span>
                    <div class="progress-bar">
                        <div class="fill" style="width:0%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <span>Proposal</span>
                    <div class="progress-bar">
                        <div class="fill" style="width:0%"></div>
                    </div>
                </div>

                <div class="progress-item">
                    <span>Final Manuscript</span>
                    <div class="progress-bar">
                        <div class="fill" style="width:0%"></div>
                    </div>
                </div>
            </div>
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
        const notificationIcon = document.getElementById('notificationIcon');

        // ==================== SIDEBAR FUNCTIONS ====================
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        function toggleSidebar(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            }
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        // ==================== PROFILE DROPDOWN ====================
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }

        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }

        // ==================== NOTIFICATION CLICK ====================
        if (notificationIcon) {
            notificationIcon.addEventListener('click', function() {
                window.location.href = 'notification.php';
            });
        }

        // ==================== DARK MODE ====================
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                    }
                });
            }
        }

        // ==================== SEARCH FUNCTION ====================
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                console.log('Searching for:', term);
            });
        }

        // ==================== INITIALIZE ====================
        initDarkMode();
        
        console.log('Profile Page Initialized - Menu Bar Style Sidebar');
    </script>
</body>
</html>