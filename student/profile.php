<?php
session_start();
include("../config/db.php");
include("includes/profile_functions.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get user data using function from profile_functions.php
$user = getUserData($conn, $user_id);
if (!$user) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

// Process user data
$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$full  = trim($first . " " . $last);
$initials = getInitials($first, $last);

$profilePicUrl = $user["profile_picture"]
    ? "../uploads/profile_pictures/" . $user["profile_picture"]
    : "";

$email   = trim($user["email"] ?? "");
$contact = trim($user["contact_number"] ?? "");
$address = trim($user["address"] ?? "");
$birth   = trim($user["birth_date"] ?? "");

// Get notification count
$notificationCount = getNotificationCount($conn, $user_id);

$pageTitle = "My Profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archive</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>

<!-- OVERLAY -->
<div class="overlay" id="overlay"></div>

<!-- MOBILE MENU BUTTON -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">

    <!-- SIDEBAR -->
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

    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </div>
                <h1>My Profile</h1>
            </div>

            <div class="user-info">
                <!-- Notification Bell -->
                <a href="notification.php" class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </a>
                
                <!-- Avatar Dropdown -->
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

        <!-- Profile Content -->
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

                <a href="edit_profile.php" class="edit-btn">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
            </div>

            <!-- Progress Card -->
            <div class="profile-card stats">
                <h3>Thesis Progress</h3>

                <div class="progress-item">
                    <span>Overall Progress</span>
                    <div class="progress-bar"><div class="fill" style="width:0%"></div></div>
                </div>

                <div class="progress-item">
                    <span>Proposal</span>
                    <div class="progress-bar"><div class="fill" style="width:0%"></div></div>
                </div>

                <div class="progress-item">
                    <span>Final Manuscript</span>
                    <div class="progress-bar"><div class="fill" style="width:0%"></div></div>
                </div>
            </div>

        </div>

    </main>

</div>

<script src="js/profile.js"></script>
</body>
</html>