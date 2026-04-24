<?php
session_start();
include("../config/db.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$role = $_SESSION['role'] ?? 'student';

// Get user data
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM user_table WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

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

$email = trim($user["email"] ?? "");

// Get notification count
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifResult = $notif_stmt->get_result()->fetch_assoc();
$notificationCount = $notifResult['total'] ?? 0;
$notif_stmt->close();

// Handle password change
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirm password do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        $pass_query = "SELECT password FROM user_table WHERE user_id = ?";
        $pass_stmt = $conn->prepare($pass_query);
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $user_pass = $pass_stmt->get_result()->fetch_assoc();
        $pass_stmt->close();
        
        if ($user_pass && password_verify($current_password, $user_pass['password'])) {
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE user_table SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $new_hashed_password, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Password changed successfully!";
                $_POST = array();
            } else {
                $error_message = "Failed to update password. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
}

$pageTitle = "Change Password";
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/change_password.css">
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
        <div class="password-container">
            <div class="password-card">
                <h2>Change Password</h2>
                <p class="subtitle">Update your account password</p>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="current_password" placeholder="Enter your current password" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="input-icon">
                            <i class="fas fa-key"></i>
                            <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="input-icon">
                            <i class="fas fa-check-circle"></i>
                            <input type="password" name="confirm_password" placeholder="Confirm your new password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-change">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                    <a href="profile.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                </form>
            </div>
        </div>
    </main>

    <script src="js/change_password.js"></script>
</body>
</html>