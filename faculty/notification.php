<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: ../authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

$roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 3) {
    header("Location: ../authentication/login.php");
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$first = trim($faculty["first_name"] ?? "");
$last  = trim($faculty["last_name"] ?? "");
$fullName = trim($first . " " . $last);
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "FA";

// ==================== HANDLE MARK AS READ (GET REQUEST) ====================
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = (int)$_GET['id'];
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $update->bind_param("ii", $notif_id, $user_id);
    $update->execute();
    $update->close();
    header("Location: notification.php");
    exit;
}

// ==================== HANDLE MARK ALL AS READ (GET REQUEST) ====================
if (isset($_GET['mark_all_read'])) {
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();
    header("Location: notification.php");
    exit;
}

// ==================== GET NOTIFICATIONS ====================
$notifications = [];
try {
    $query = "SELECT n.*, t.title as thesis_title, t.thesis_id,
                     u.first_name as student_first, u.last_name as student_last
              FROM notifications n
              LEFT JOIN thesis_table t ON n.thesis_id = t.thesis_id
              LEFT JOIN user_table u ON t.student_id = u.user_id
              WHERE n.user_id = ? 
              ORDER BY n.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Notification error: " . $e->getMessage());
}

// ==================== GET UNREAD COUNT ====================
$unreadCount = 0;
try {
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $countResult = $stmt->get_result()->fetch_assoc();
    $unreadCount = $countResult['total'] ?? 0;
    $stmt->close();
} catch (Exception $e) {
    error_log("Count error: " . $e->getMessage());
}

$pageTitle = "Notifications";
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
    <link rel="stylesheet" href="css/notification.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header"><h2>Theses Archive</h2><p>Faculty Portal</p></div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="reviewThesis.php" class="nav-link"><i class="fas fa-book-reader"></i> Review Theses</a>
            <a href="facultyFeedback.php" class="nav-link"><i class="fas fa-comment-dots"></i> My Feedback</a>
            <a href="notification.php" class="nav-link active"><i class="fas fa-bell"></i> Notifications<?php if ($unreadCount > 0): ?><span class="badge"><?= $unreadCount ?></span><?php endif; ?></a>
        </nav>
        <div class="sidebar-footer"><a href="../authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </aside>

    <main class="main-content">
        <header class="topbar"><h1>Notifications</h1><div class="user-info"><div class="avatar"><?= htmlspecialchars($initials) ?></div></div></header>
        <div class="notification-container">
            <div class="notification-header"><h2><i class="fas fa-bell"></i> All Notifications</h2><?php if ($unreadCount > 0): ?><a href="?mark_all_read=1" class="btn-mark-all"><i class="fas fa-check-double"></i> Mark All as Read</a><?php endif; ?></div>
            <div class="notification-list" id="notificationList">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications"><i class="fas fa-bell-slash"></i><h3>No notifications yet</h3><p>When you receive notifications about thesis submissions and reviews, they'll appear here.</p></div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-notification-id="<?= $notif['notification_id'] ?>" data-thesis-id="<?= $notif['thesis_id'] ?? 0 ?>">
                            <div class="notification-icon"><i class="fas fa-<?= $notif['is_read'] == 0 ? 'bell' : 'bell-slash' ?>"></i></div>
                            <div class="notification-content">
                                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notification-meta"><span><i class="fas fa-clock"></i> <?= date('F d, Y h:i A', strtotime($notif['created_at'])) ?></span><?php if (!empty($notif['thesis_title'])): ?><span><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></span><?php endif; ?><?php if (!empty($notif['student_first'])): ?><span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($notif['student_first'] . ' ' . $notif['student_last']) ?></span><?php endif; ?></div>
                            </div>
                            <div class="notification-actions"><?php if ($notif['is_read'] == 0): ?><a href="?mark_read=1&id=<?= $notif['notification_id'] ?>" class="btn-mark"><i class="fas fa-check"></i> Mark Read</a><?php endif; ?><?php if (!empty($notif['thesis_id'])): ?><a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a><?php endif; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            unreadCount: <?php echo $unreadCount; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/notification.js"></script>
</body>
</html>