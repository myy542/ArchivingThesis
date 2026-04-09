<?php
session_start();
include("../config/db.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'coordinator';

// First, check what columns exist in the user_table
$columns_query = "SHOW COLUMNS FROM user_table";
$columns_result = $conn->query($columns_query);
$existing_columns = [];
if ($columns_result) {
    while ($col = $columns_result->fetch_assoc()) {
        $existing_columns[] = $col['Field'];
    }
}

// Build query based on existing columns
$select_fields = ['first_name', 'last_name', 'email'];
if (in_array('username', $existing_columns)) $select_fields[] = 'username';
if (in_array('phone', $existing_columns)) $select_fields[] = 'phone';
if (in_array('contact', $existing_columns)) $select_fields[] = 'contact';
if (in_array('birth_date', $existing_columns)) $select_fields[] = 'birth_date';
if (in_array('address', $existing_columns)) $select_fields[] = 'address';
if (in_array('role_id', $existing_columns)) $select_fields[] = 'role_id';
if (in_array('created_at', $existing_columns)) $select_fields[] = 'created_at';

$user_query = "SELECT " . implode(', ', $select_fields) . " FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $email = $user_data['email'];
    $username = $user_data['username'] ?? '';
    $phone = $user_data['phone'] ?? ($user_data['contact'] ?? 'Not provided');
    $birth_date = $user_data['birth_date'] ?? 'Not provided';
    $address = $user_data['address'] ?? 'Not provided';
    $member_since = isset($user_data['created_at']) ? date('F Y', strtotime($user_data['created_at'])) : 'March 2026';
} else {
    $first_name = 'User';
    $last_name = '';
    $email = 'Not provided';
    $username = '';
    $phone = 'Not provided';
    $birth_date = 'Not provided';
    $address = 'Not provided';
    $member_since = 'March 2026';
}

$userName = trim($first_name . ' ' . $last_name);
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);

// Get position based on role
$position = '';
if ($role == 'coordinator') {
    $position = 'Research Coordinator';
} elseif ($role == 'faculty') {
    $position = 'Faculty Member';
} elseif ($role == 'student') {
    $position = 'Student';
} else {
    $position = 'User';
}

$user_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Profile | Thesis Management System</title>
    
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
            padding: 0 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            z-index: 99;
            transition: all 0.3s ease;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* HAMBURGER MENU - ALWAYS VISIBLE */
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
            transition: all 0.3s ease;
        }

        .hamburger:hover {
            background: #fee2e2;
        }

        .logo {
            font-size: 1.35rem;
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
            color: #1f2937;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Profile Dropdown */
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
            min-width: 180px;
            display: none;
            overflow: hidden;
            z-index: 100;
            border: 1px solid #fee2e2;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            transition: 0.2s;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
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
            font-size: 1.35rem;
            font-weight: 700;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 5px;
            text-transform: uppercase;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #fecaca;
            transition: 0.2s;
        }

        .nav-item i {
            width: 22px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 500;
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
            padding: 10px 16px;
            text-decoration: none;
            color: #fecaca;
            border-radius: 10px;
            transition: 0.2s;
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
            display: flex;
            gap: 32px;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            width: 280px;
            background: white;
            border-radius: 24px;
            border: 1px solid #fee2e2;
            padding: 32px 24px;
            text-align: center;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
        }

        .profile-sidebar h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .profile-sidebar p {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .member-since {
            font-size: 0.75rem;
            color: #9ca3af;
            padding-top: 16px;
            border-top: 1px solid #fee2e2;
            margin-top: 16px;
        }

        /* Profile Main Card */
        .profile-main {
            flex: 1;
            background: white;
            border-radius: 24px;
            border: 1px solid #fee2e2;
            padding: 32px;
        }

        .profile-main h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #fee2e2;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #1f2937;
        }

        .about-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #fee2e2;
        }

        .about-section h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .about-section p {
            font-size: 0.9rem;
            color: #6b7280;
            line-height: 1.5;
        }

        .edit-profile-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-top: 24px;
        }

        .edit-profile-btn:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }
           .change-pass-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-top: 24px;
        }
          .change-pass-btn:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 15px;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .profile-container {
                flex-direction: column;
            }
            
            .profile-sidebar {
                width: 100%;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .profile-main {
                padding: 24px;
            }
            
            .profile-sidebar {
                padding: 24px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            .profile-main {
                padding: 20px;
            }
            .profile-sidebar {
                padding: 20px;
            }
            .profile-avatar-large {
                width: 80px;
                height: 80px;
                font-size: 2rem;
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
        body.dark-mode .profile-sidebar {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .profile-sidebar h3 {
            color: #fecaca;
        }
        body.dark-mode .profile-main {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .profile-main h2 {
            color: #fecaca;
            border-bottom-color: #991b1b;
        }
        body.dark-mode .info-value {
            color: #fecaca;
        }
        body.dark-mode .about-section {
            border-top-color: #991b1b;
        }
        body.dark-mode .about-section h3 {
            color: #fecaca;
        }
        body.dark-mode .member-since {
            border-top-color: #991b1b;
            color: #9ca3af;
        }
        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .profile-dropdown a {
            color: #fecaca;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?php echo htmlspecialchars($userName); ?></span>
                    <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub"><?php echo strtoupper($role); ?></div>
        </div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item <?php echo $currentPage == 'coordinatorDashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="reviewThesis.php" class="nav-item <?php echo $currentPage == 'reviewThesis.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Review Theses
            </a>
            <a href="myFeedback.php" class="nav-item <?php echo $currentPage == 'myFeedback.php' ? 'active' : ''; ?>">
                <i class="fas fa-comment"></i> My Feedback
            </a>
            <a href="notification.php" class="nav-item <?php echo $currentPage == 'notification.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> Notifications
            </a>
            <a href="forwardedTheses.php" class="nav-item <?php echo $currentPage == 'forwardedTheses.php' ? 'active' : ''; ?>">
                <i class="fas fa-arrow-right"></i> Forwarded to Dean
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
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="profile-avatar-large"><?php echo htmlspecialchars($initials); ?></div>
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p><?php echo htmlspecialchars($position); ?></p>
                <div class="member-since">
                    <i class="far fa-calendar-alt"></i> Member since: <?php echo $member_since; ?>
                </div>
            </div>

            <div class="profile-main">
                <h2>Personal Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($userName); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($username ?: $first_name); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($phone); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Birth Date</span>
                        <span class="info-value"><?php echo htmlspecialchars($birth_date); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($address); ?></span>
                    </div>
                </div>

                <div class="about-section">
                    <h3>About Me</h3>
                    <p><?php echo htmlspecialchars($position); ?> dedicated to academic excellence and research development. Committed to providing quality thesis management and guidance to students.</p>
                </div>

                <a href="editProfile.php" class="edit-profile-btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>

              <a href="changepass.php" class="change-pass-btn">
                    <i class="fas fa-key"></i> Change Password
                </a>
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

        // ==================== INITIALIZE ====================
        initDarkMode();
        
        console.log('Profile Page Initialized - Menu Bar Style Sidebar');
    </script>
</body>
</html>