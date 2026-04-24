<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get all notifications
$notifications = [];
$notif_query = "SELECT n.*, t.title as thesis_title 
                FROM notifications n
                LEFT JOIN thesis_table t ON n.thesis_id = t.thesis_id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $update_query = "UPDATE notifications SET status = 'read' WHERE id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $notif_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    header("Location: notifications.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $update_query = "UPDATE notifications SET status = 'read' WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    header("Location: notifications.php");
    exit;
}

$unread_count = 0;
$count_query = "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND status = 'unread'";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$currentPage = basename($_SERVER['PHP_SELF']);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Notifications | Coordinator Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/notification.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">RESEARCH COORDINATOR</div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="notification.php" class="nav-item active"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="container">
            <a href="coordinatorDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            
            <div class="card">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                
                <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn-mark-all">Mark All as Read</a>
                <?php endif; ?>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notifications yet</p></div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['status'] == 'unread' ? 'unread' : '' ?>">
                            <div class="notification-content">
                                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notification-date"><i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                            </div>
                            <div class="notification-actions">
                                <?php if ($notif['status'] == 'unread'): ?>
                                    <a href="notifications.php?mark_read=1&id=<?= $notif['id'] ?>" class="btn-mark">Mark Read</a>
                                <?php endif; ?>
                                <?php if ($notif['thesis_id']): ?>
                                    <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="btn-view">View Thesis</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/notification.js"></script>
</body>
</html>