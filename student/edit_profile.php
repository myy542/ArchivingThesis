<?php
session_start();
include("../config/db.php");
include("includes/profile_functions.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$error = "";

// Get user data
$user = getUserData($conn, $user_id);
if (!$user) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$fullName = trim($first . " " . $last);
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "U";

$profilePicUrl = $user["profile_picture"] 
    ? "../uploads/profile_pictures/" . $user["profile_picture"] 
    : "";

// Get notification count - FIXED using is_read
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifResult = $notif_stmt->get_result()->fetch_assoc();
$notificationCount = $notifResult['total'] ?? 0;
$notif_stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $error = handleProfileUpdate($conn, $user_id, $_POST, $_FILES);
    if (!$error) {
        header("Location: profile.php");
        exit;
    }
}

$pageTitle = "Edit Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archive</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/edit_profile.css">
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
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .theme-toggle {
            margin-bottom: 1rem;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            cursor: pointer;
            position: relative;
        }

        .toggle-label i {
            font-size: 1rem;
            color: white;
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
            gap: 1.5rem;
        }

        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            font-size: 1.2rem;
            color: #6E6E6E;
            text-decoration: none;
            transition: color 0.3s;
        }

        .notification-bell:hover {
            color: #FE4853;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #FE4853;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .avatar-container {
            position: relative;
        }

        .avatar-dropdown {
            position: relative;
            cursor: pointer;
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

        .dropdown-content {
            display: none;
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        body.dark-mode .dropdown-content {
            background: #3a3a3a;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }

        body.dark-mode .dropdown-content a {
            color: #e0e0e0;
        }

        .dropdown-content a:hover {
            background: #f5f5f5;
        }

        body.dark-mode .dropdown-content a:hover {
            background: #4a4a4a;
        }

        .dropdown-content hr {
            margin: 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }

        /* Profile Container */
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
            margin-bottom: 2rem;
        }

        body.dark-mode .profile-card {
            background: #3a3a3a;
        }

        .profile-title {
            font-size: 1.5rem;
            color: #732529;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #fee2e2;
        }

        body.dark-mode .profile-title {
            color: #FE4853;
            border-bottom-color: #555;
        }

        .alert {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Edit Form */
        .edit-form .form-group {
            margin-bottom: 1.5rem;
        }

        .edit-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        body.dark-mode .edit-form label {
            color: #e0e0e0;
        }

        .edit-form input,
        .edit-form textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        body.dark-mode .edit-form input,
        body.dark-mode .edit-form textarea {
            background: #4a4a4a;
            border-color: #6E6E6E;
            color: #e0e0e0;
        }

        .edit-form input:focus,
        .edit-form textarea:focus {
            outline: none;
            border-color: #FE4853;
            box-shadow: 0 0 0 3px rgba(254, 72, 83, 0.1);
        }

        .edit-form textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Picture Group */
        .picture-group {
            text-align: center;
        }

        .current-picture {
            margin-bottom: 1rem;
        }

        .current-picture img {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #FE4853;
        }

        .current-picture.placeholder {
            width: 150px;
            height: 150px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            color: #999;
        }

        body.dark-mode .current-picture.placeholder {
            background: #4a4a4a;
        }

        .file-upload-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-upload-wrapper input[type="file"] {
            display: none;
        }

        .file-upload-btn {
            background: #FE4853;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
        }

        .file-upload-btn:hover {
            background: #732529;
            transform: translateY(-2px);
        }

        .file-name {
            margin-left: 0.5rem;
            font-size: 0.8rem;
            color: #6E6E6E;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        body.dark-mode .form-actions {
            border-top-color: #555;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn.primary {
            background: #FE4853;
            color: white;
            flex: 1;
        }

        .btn.primary:hover {
            background: #732529;
            transform: translateY(-2px);
        }

        .btn.secondary {
            background: #f0f0f0;
            color: #333;
            flex: 1;
            justify-content: center;
        }

        body.dark-mode .btn.secondary {
            background: #4a4a4a;
            color: #e0e0e0;
        }

        .btn.secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        /* Mobile Menu Button */
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
            
            .profile-card {
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn.primary,
            .btn.secondary {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .profile-title {
                font-size: 1.3rem;
            }
            
            .current-picture img,
            .current-picture.placeholder {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Theses Archive</h2>
        <p>Student Portal</p>
    </div>

    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="projects.php" class="nav-link">
            <i class="fas fa-folder-open"></i> My Projects
        </a>
        <a href="submission.php" class="nav-link">
            <i class="fas fa-upload"></i> Submit Thesis
        </a>
        <a href="archived.php" class="nav-link">
            <i class="fas fa-archive"></i> Archived Theses
        </a>
        <a href="profile.php" class="nav-link active">
            <i class="fas fa-user-circle"></i> Profile
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="theme-toggle">
            <input type="checkbox" id="darkmode" />
            <label for="darkmode" class="toggle-label">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
                <span class="slider"></span>
            </label>
        </div>
        <a href="../authentication/logout.php" class="logout-btn">
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
                <h1>Edit Profile</h1>
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
                            <a href="profile.php">
                                <i class="fas fa-user-circle"></i> Profile
                            </a>
                            <a href="settings.php">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <hr>
                            <a href="../authentication/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="profile-container">
            <div class="profile-card main">
                <h2 class="profile-title">Edit Profile</h2>

                <?php if ($error): ?>
                    <div class="alert">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="edit-form">

                    <div class="form-group picture-group">
                        <label>Profile Picture</label>

                        <?php if ($profilePicUrl && file_exists(__DIR__ . "/../uploads/profile_pictures/" . $user["profile_picture"])): ?>
                            <div class="current-picture">
                                <img src="<?= htmlspecialchars($profilePicUrl) ?>?v=<?= time() ?>" alt="Current profile picture">
                            </div>
                        <?php else: ?>
                            <div class="current-picture placeholder">
                                <span>No picture set</span>
                            </div>
                        <?php endif; ?>

                        <div class="file-upload-wrapper">
                            <input type="file" name="profile_picture" accept="image/jpeg,image/png" id="profile_picture">
                            <label for="profile_picture" class="file-upload-btn">
                                <i class="fas fa-upload"></i> Choose File
                            </label>
                            <span class="file-name" id="fileName">No file chosen</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($first) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($last) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user["email"]) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" value="<?= htmlspecialchars($user["contact_number"] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="birth_date">Birth Date</label>
                        <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($user["birth_date"] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="4"><?= htmlspecialchars($user["address"] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="profile.php" class="btn secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>
</div>

<script>
    // File input display
    const fileInput = document.getElementById('profile_picture');
    const fileNameSpan = document.getElementById('fileName');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                fileNameSpan.innerHTML = '<i class="fas fa-check-circle"></i> ' + this.files[0].name;
                fileNameSpan.style.color = '#10b981';
            } else {
                fileNameSpan.innerHTML = 'No file chosen';
                fileNameSpan.style.color = '#6E6E6E';
            }
        });
    }

    // Dark Mode
    const darkToggle = document.getElementById('darkmode');
    if (darkToggle) {
        darkToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', darkToggle.checked);
        });
        if (localStorage.getItem('darkMode') === 'true') {
            darkToggle.checked = true;
            document.body.classList.add('dark-mode');
        }
    }

    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');

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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Avatar Dropdown
    const avatarBtn = document.getElementById('avatarBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (avatarBtn) {
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
    }

    document.addEventListener('click', (e) => {
        if (!avatarBtn?.contains(e.target) && dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }
    });
</script>

</body>
</html>