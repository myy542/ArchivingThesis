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
    $phone = $user_data['phone'] ?? ($user_data['contact'] ?? '');
    $birth_date = $user_data['birth_date'] ?? '';
    $address = $user_data['address'] ?? '';
} else {
    $first_name = '';
    $last_name = '';
    $email = '';
    $username = '';
    $phone = '';
    $birth_date = '';
    $address = '';
}

$userName = trim($first_name . ' ' . $last_name);
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_first_name = trim($_POST['first_name'] ?? '');
    $new_last_name = trim($_POST['last_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_username = trim($_POST['username'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_birth_date = trim($_POST['birth_date'] ?? '');
    $new_address = trim($_POST['address'] ?? '');
    
    // Update user data
    $update_fields = [];
    $update_values = [];
    $types = '';
    
    if (!empty($new_first_name)) {
        $update_fields[] = "first_name = ?";
        $update_values[] = $new_first_name;
        $types .= 's';
    }
    if (!empty($new_last_name)) {
        $update_fields[] = "last_name = ?";
        $update_values[] = $new_last_name;
        $types .= 's';
    }
    if (!empty($new_email)) {
        $update_fields[] = "email = ?";
        $update_values[] = $new_email;
        $types .= 's';
    }
    if (!empty($new_username)) {
        $update_fields[] = "username = ?";
        $update_values[] = $new_username;
        $types .= 's';
    }
    if (in_array('phone', $existing_columns) && !empty($new_phone)) {
        $update_fields[] = "phone = ?";
        $update_values[] = $new_phone;
        $types .= 's';
    }
    if (in_array('contact', $existing_columns) && !empty($new_phone)) {
        $update_fields[] = "contact = ?";
        $update_values[] = $new_phone;
        $types .= 's';
    }
    if (!empty($new_birth_date)) {
        $update_fields[] = "birth_date = ?";
        $update_values[] = $new_birth_date;
        $types .= 's';
    }
    if (!empty($new_address)) {
        $update_fields[] = "address = ?";
        $update_values[] = $new_address;
        $types .= 's';
    }
    
    if (!empty($update_fields)) {
        $update_values[] = $user_id;
        $types .= 'i';
        $update_query = "UPDATE user_table SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param($types, ...$update_values);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh data
            $first_name = $new_first_name ?: $first_name;
            $last_name = $new_last_name ?: $last_name;
            $email = $new_email ?: $email;
            $username = $new_username ?: $username;
            $phone = $new_phone ?: $phone;
            $birth_date = $new_birth_date ?: $birth_date;
            $address = $new_address ?: $address;
            $userName = trim($first_name . ' ' . $last_name);
            $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
        $update_stmt->close();
    }
}

$user_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Edit Profile | Thesis Management System</title>
    
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

        /* Top Navigation - Red Theme */
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

        /* HAMBURGER MENU - THREE LINES */
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

        /* Sidebar - Red Theme */
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
            transform: translateX(0);
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

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Edit Profile Card */
        .edit-profile-card {
            background: white;
            border-radius: 24px;
            border: 1px solid #fee2e2;
            padding: 32px;
            max-width: 700px;
            margin: 0 auto;
        }

        .edit-profile-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .edit-profile-card .subtitle {
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #fee2e2;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #fee2e2;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            background: white;
            color: #1f2937;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #dc2626;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            margin-top: 16px;
        }

        .btn-save:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .btn-cancel {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #f3f4f6;
            color: #6b7280;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-left: 12px;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #10b981;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.85rem;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #dc2626;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 0.85rem;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 98;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* =========================================== */
        /* MOBILE RESPONSIVE - HAMBURGER MENU */
        /* =========================================== */
        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 15px;
            }
            
            /* SHOW HAMBURGER MENU ON MOBILE */
            .hamburger {
                display: flex;
            }
            
            /* HIDE SIDEBAR BY DEFAULT ON MOBILE */
            .sidebar {
                transform: translateX(-100%);
            }
            
            /* SHOW SIDEBAR WHEN OPEN CLASS IS ADDED */
            .sidebar.open {
                transform: translateX(0);
            }
            
            /* ADJUST MAIN CONTENT MARGIN */
            .main-content {
                margin-left: 0;
            }
            
            /* FORM ROWS STACK ON MOBILE */
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            /* HIDE SEARCH ON MOBILE */
            .search-area {
                display: none;
            }
            
            /* HIDE PROFILE NAME ON MOBILE */
            .profile-name {
                display: none;
            }
            
            /* ADJUST CARD PADDING */
            .edit-profile-card {
                padding: 20px;
            }
            
            .edit-profile-card h2 {
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            .edit-profile-card {
                padding: 18px;
            }
            .btn-save, .btn-cancel {
                padding: 10px 18px;
                font-size: 0.8rem;
            }
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
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Settings</a>
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
        <div class="edit-profile-card">
            <h2>Edit Profile</h2>
            <p class="subtitle">Update your personal information</p>
            
            <?php if ($success_message): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>
                    <div class="form-group">
                        <label>Birth Date</label>
                        <input type="date" name="birth_date" value="<?php echo htmlspecialchars($birth_date); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?php echo htmlspecialchars($address); ?></textarea>
                </div>
                
                <div>
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="profile.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
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

        // Toggle Sidebar - Three Lines Menu
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

        // Close Sidebar
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

        // Close Profile Dropdown when clicking outside
        function closeProfileDropdown(e) {
            if (profileWrapper && !profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }

        // Dark Mode Toggle
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
                body.dark-mode .edit-profile-card { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .edit-profile-card h2 { color: #fecaca; }
                body.dark-mode .form-group input,
                body.dark-mode .form-group textarea { background: #3d3d3d; border-color: #991b1b; color: white; }
                body.dark-mode .profile-dropdown { background: #2d2d2d; border-color: #991b1b; }
                body.dark-mode .profile-dropdown a { color: #fecaca; }
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            
            // Close sidebar on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            // Close sidebar when window is resized to desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html>