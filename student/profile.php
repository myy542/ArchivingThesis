<?php
session_start();
include("../config/db.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Get user data
$stmt = $conn->prepare("SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture FROM user_table WHERE user_id = ?");
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

$profilePicUrl = $user["profile_picture"] ? "../uploads/profile_pictures/" . $user["profile_picture"] : "";
$email   = trim($user["email"] ?? "");
$contact = trim($user["contact_number"] ?? "");
$address = trim($user["address"] ?? "");
$birth   = trim($user["birth_date"] ?? "");

// Get notification count
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archive</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/profile.css">
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

    <script src="js/profile.js"></script>
</body>
</html>