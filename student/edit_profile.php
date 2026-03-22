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

// Get notification count
$notificationCount = getNotificationCount($conn, $user_id);

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
                <h1>Edit Profile</h1>
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

        <!-- EDIT PROFILE CONTENT -->
        <div class="profile-container">
            <div class="profile-card main">
                <h2 class="profile-title">Edit Profile</h2>

                <?php if ($error): ?>
                    <div class="alert">
                        <?= htmlspecialchars($error) ?>
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
                            <span class="file-name">No file chosen</span>
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

<script src="js/edit_profile.js"></script>
</body>
</html>