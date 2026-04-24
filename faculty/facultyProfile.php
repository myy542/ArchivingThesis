<?php
session_start();
include("../config/db.php"); 

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$faculty_id = (int)$_SESSION["user_id"];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

$stmt = $conn->prepare("SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture, role_id FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $faculty_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    session_destroy();
    header("Location: ../authentication/login.php");
    exit;
}

$first = trim($faculty["first_name"] ?? "");
$last  = trim($faculty["last_name"] ?? "");
$email = trim($faculty["email"] ?? "");
$contact = trim($faculty["contact_number"] ?? "Not provided");
$address = trim($faculty["address"] ?? "Not provided");
$birthDate = trim($faculty["birth_date"] ?? "");
$role_id = $faculty["role_id"] ?? 3;

$position = "Faculty Member";
if ($role_id == 1) $position = "Administrator";
elseif ($role_id == 2) $position = "Student";
elseif ($role_id == 3) $position = "Faculty Member";
elseif ($role_id == 4) $position = "Dean";

$department = "College of Computer Studies";
$memberSince = date('F Y');

try {
    $pendingQuery = "SELECT COUNT(*) as total FROM thesis_table WHERE is_archived = 0";
    $pendingResult = $conn->query($pendingQuery);
    $pendingCount = $pendingResult->fetch_assoc()['total'] ?? 0;
    
    $approvedQuery = "SELECT COUNT(*) as total FROM thesis_table WHERE thesis_status = 'approved'";
    $approvedResult = $conn->query($approvedQuery);
    $approvedCount = $approvedResult->fetch_assoc()['total'] ?? 0;
    
    $rejectedQuery = "SELECT COUNT(*) as total FROM thesis_table WHERE thesis_status = 'rejected'";
    $rejectedResult = $conn->query($rejectedQuery);
    $rejectedCount = $rejectedResult->fetch_assoc()['total'] ?? 0;
    
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;
}

$unreadCount = 0;
$recentNotifications = [];

try {
    $notif_columns = $conn->query("SHOW COLUMNS FROM notifications");
    $notif_user_column = 'user_id';
    $notif_read_column = 'is_read';
    $notif_message_column = 'message';
    $notif_date_column = 'created_at';
    
    while ($col = $notif_columns->fetch_assoc()) {
        $field = $col['Field'];
        if (strpos($field, 'user') !== false && strpos($field, 'sender') === false) $notif_user_column = $field;
        if (strpos($field, 'read') !== false || strpos($field, 'status') !== false) $notif_read_column = $field;
        if (strpos($field, 'message') !== false) $notif_message_column = $field;
        if (strpos($field, 'created_at') !== false || strpos($field, 'date') !== false) $notif_date_column = $field;
    }
    
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE $notif_user_column = ? AND $notif_read_column = 0";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $countResult = $stmt->get_result()->fetch_assoc();
    $unreadCount = $countResult['total'] ?? 0;
    $stmt->close();
    
    $notifQuery = "SELECT $notif_message_column as message, $notif_read_column as is_read, $notif_date_column as created_at
                   FROM notifications WHERE $notif_user_column = ? ORDER BY $notif_date_column DESC LIMIT 5";
    $stmt = $conn->prepare($notifQuery);
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
    $unreadCount = 0;
    $recentNotifications = [];
}

$pageTitle = "Faculty Profile";
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/facultyProfile.css">
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header"><h2>Theses Archive</h2><p>Faculty Portal</p></div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="facultyProfile.php" class="nav-link active"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="reviewThesis.php" class="nav-link"><i class="fas fa-book-reader"></i> Review Theses<?php if ($pendingCount > 0): ?><span class="badge"><?= $pendingCount ?></span><?php endif; ?></a>
            <a href="facultyFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> My Feedback</a>
        </nav>
        <div class="sidebar-footer"><div class="theme-toggle"><input type="checkbox" id="darkmode" /><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div><a href="../authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>
    <div class="layout"><main class="main-content">
        <header class="topbar"><div style="display: flex; align-items: center; gap: 1rem;"><div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div><h1><?= htmlspecialchars($pageTitle) ?></h1></div>
        <div class="user-info">
            <div class="notification-container"><div class="notification-bell" id="notificationBell"><i class="fas fa-bell"></i><?php if ($unreadCount > 0): ?><span class="notification-badge"><?= $unreadCount ?></span><?php endif; ?></div>
            <div class="notification-dropdown" id="notificationDropdown"><div class="notification-header"><h4>Notifications</h4><a href="#" id="markAllRead">Mark all as read</a></div>
            <div class="notification-list"><?php if (empty($recentNotifications)): ?><div class="notification-item"><div class="no-notifications">No new notifications</div></div>
            <?php else: ?><?php foreach ($recentNotifications as $notif): ?><div class="notification-item <?= isset($notif['is_read']) && !$notif['is_read'] ? 'unread' : '' ?>"><div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div><div class="notif-time"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></div></div><?php endforeach; ?><?php endif; ?></div>
            <div class="notification-footer"><a href="notifications.php">View all notifications</a></div></div></div>
            <div class="avatar-dropdown"><div class="avatar" id="avatarBtn"><?= htmlspecialchars($initials) ?></div><div class="dropdown-content" id="dropdownMenu"><a href="facultyProfile.php"><i class="fas fa-user-circle"></i> Profile</a><a href="settings.php"><i class="fas fa-cog"></i> Settings</a><hr><a href="../authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div></div>
        </div></header>
        <div class="profile-container">
            <div class="profile-header"><div class="profile-avatar-large"><?= htmlspecialchars($initials) ?></div><h2><?= htmlspecialchars($fullName) ?></h2><p><?= htmlspecialchars($position) ?></p></div>
            <div class="profile-card">
                <div class="stats-grid-small"><div class="stat-card-small"><div class="value"><?= $pendingCount ?></div><div class="label">Pending</div></div><div class="stat-card-small"><div class="value"><?= $approvedCount ?></div><div class="label">Approved</div></div><div class="stat-card-small"><div class="value"><?= $rejectedCount ?></div><div class="label">Rejected</div></div></div>
                <div class="profile-section"><h3><i class="fas fa-id-card"></i> Personal Information</h3><div class="info-grid">
                    <div class="info-item"><i class="fas fa-user info-icon"></i><div class="info-content"><div class="info-label">Full Name</div><div class="info-value"><?= htmlspecialchars($fullName) ?></div></div></div>
                    <div class="info-item"><i class="fas fa-envelope info-icon"></i><div class="info-content"><div class="info-label">Email Address</div><div class="info-value"><?= htmlspecialchars($email) ?></div></div></div>
                    <div class="info-item"><i class="fas fa-phone info-icon"></i><div class="info-content"><div class="info-label">Phone Number</div><div class="info-value"><?= htmlspecialchars($contact) ?></div></div></div>
                    <div class="info-item"><i class="fas fa-briefcase info-icon"></i><div class="info-content"><div class="info-label">Position</div><div class="info-value"><?= htmlspecialchars($position) ?></div></div></div>
                    <div class="info-item"><i class="fas fa-building info-icon"></i><div class="info-content"><div class="info-label">Department/College</div><div class="info-value"><?= htmlspecialchars($department) ?></div></div></div>
                    <div class="info-item"><i class="fas fa-calendar info-icon"></i><div class="info-content"><div class="info-label">Member Since</div><div class="info-value"><?= htmlspecialchars($memberSince) ?></div></div></div>
                </div></div>
                <div class="profile-section"><h3><i class="fas fa-cog"></i> Account Settings</h3><div class="action-buttons"><a href="facultyEditProfile.php" class="btn-edit"><i class="fas fa-edit"></i> Edit Profile</a><a href="changePassword.php" class="btn-edit btn-edit-secondary"><i class="fas fa-key"></i> Change Password</a></div></div>
            </div>
        </div>
    </main></div>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            unreadCount: <?php echo $unreadCount; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/facultyProfile.js"></script>
</body>
</html>