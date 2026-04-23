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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }

        body.dark-mode {
            background: #2d2d2d;
            color: #e0e0e0;
        }

        .layout {
            min-height: 100vh;
            display: flex;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            color: white;
            font-weight: 700;
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

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: 600;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 0.875rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .logout-btn i {
            color: white;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            min-height: 100vh;
            padding: 2rem;
        }

        .topbar {
            background: white;
            border-radius: 12px;
            padding: 1rem 2rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        body.dark-mode .topbar {
            background: #3a3a3a;
        }

        .topbar h1 {
            color: #732529;
            font-size: 1.8rem;
        }

        body.dark-mode .topbar h1 {
            color: #FE4853;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
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
            cursor: pointer;
            border: 2px solid white;
        }

        .notification-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .notification-header h2 {
            color: #732529;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .notification-header h2 {
            color: #FE4853;
        }

        .notification-header h2 i {
            color: #FE4853;
        }

        .btn-mark-all {
            background: #FE4853;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-mark-all:hover {
            background: #732529;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3);
        }

        .notification-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(110, 110, 110, 0.1);
            overflow: hidden;
        }

        body.dark-mode .notification-list {
            background: #3a3a3a;
        }

        .notification-item {
            display: flex;
            align-items: center;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            transition: background 0.2s;
        }

        body.dark-mode .notification-item {
            border-bottom-color: #6E6E6E;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background: #fff3f3;
            border-left: 4px solid #FE4853;
        }

        body.dark-mode .notification-item.unread {
            background: #4a2a2a;
        }

        .notification-item:hover {
            background: #f8f9fa;
        }

        body.dark-mode .notification-item:hover {
            background: #4a4a4a;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #FE4853;
        }

        body.dark-mode .notification-icon {
            background: #2d2d2d;
        }

        .notification-content {
            flex: 1;
        }

        .notification-message {
            color: #333;
            font-size: 1rem;
            margin-bottom: 0.3rem;
            font-weight: 500;
        }

        body.dark-mode .notification-message {
            color: #e0e0e0;
        }

        .notification-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #6E6E6E;
            flex-wrap: wrap;
        }

        body.dark-mode .notification-meta {
            color: #94a3b8;
        }

        .notification-meta i {
            margin-right: 0.3rem;
            color: #FE4853;
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-mark, .btn-view {
            padding: 0.4rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-mark {
            background: #e2e8f0;
            color: #475569;
        }

        .btn-mark:hover {
            background: #cbd5e1;
        }

        body.dark-mode .btn-mark {
            background: #4a4a4a;
            color: #e0e0e0;
        }

        .btn-view {
            background: #FE4853;
            color: white;
        }

        .btn-view:hover {
            background: #732529;
        }

        .no-notifications {
            text-align: center;
            padding: 4rem 2rem;
            color: #6E6E6E;
        }

        .no-notifications i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #FE4853;
            opacity: 0.5;
        }

        .no-notifications h3 {
            color: #732529;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        body.dark-mode .no-notifications h3 {
            color: #FE4853;
        }

        .badge {
            background: #FE4853;
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: 0.5rem;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .notification-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .notification-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            .notification-actions {
                width: 100%;
                justify-content: flex-end;
            }
            .notification-meta {
                flex-direction: column;
                gap: 0.3rem;
            }
        }

        @media (max-width: 480px) {
            .notification-item {
                padding: 1rem;
            }
            .notification-icon {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            .btn-mark, .btn-view {
                padding: 0.3rem 0.8rem;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>Theses Archive</h2>
            <p>Faculty Portal</p>
        </div>
        <nav class="sidebar-nav">
            <a href="facultyDashboard.php" class="nav-link">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="reviewThesis.php" class="nav-link">
                <i class="fas fa-book-reader"></i> Review Theses
            </a>
            <a href="facultyFeedback.php" class="nav-link">
                <i class="fas fa-comment-dots"></i> My Feedback
            </a>
            <a href="notification.php" class="nav-link active">
                <i class="fas fa-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="../authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <h1>Notifications</h1>
            <div class="user-info">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
        </header>

        <div class="notification-container">
            <div class="notification-header">
                <h2><i class="fas fa-bell"></i> All Notifications</h2>
                <?php if ($unreadCount > 0): ?>
                    <a href="?mark_all_read=1" class="btn-mark-all">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>

            <div class="notification-list" id="notificationList">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No notifications yet</h3>
                        <p>When you receive notifications about thesis submissions and reviews, they'll appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" 
                             data-notification-id="<?= $notif['notification_id'] ?>"
                             data-thesis-id="<?= $notif['thesis_id'] ?? 0 ?>">
                            <div class="notification-icon">
                                <i class="fas fa-<?= $notif['is_read'] == 0 ? 'bell' : 'bell-slash' ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </div>
                                <div class="notification-meta">
                                    <span><i class="fas fa-clock"></i> <?= date('F d, Y h:i A', strtotime($notif['created_at'])) ?></span>
                                    <?php if (!empty($notif['thesis_title'])): ?>
                                        <span><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($notif['student_first'])): ?>
                                        <span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($notif['student_first'] . ' ' . $notif['student_last']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if ($notif['is_read'] == 0): ?>
                                    <a href="?mark_read=1&id=<?= $notif['notification_id'] ?>" class="btn-mark">
                                        <i class="fas fa-check"></i> Mark Read
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($notif['thesis_id'])): ?>
                                    <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Dark mode toggle
        const toggle = document.getElementById('darkmode');
        if (toggle) {
            toggle.addEventListener('change', () => {
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode', toggle.checked);
            });
            if (localStorage.getItem('darkMode') === 'true') {
                toggle.checked = true;
                document.body.classList.add('dark-mode');
            }
        }

        // Click on notification item to view
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.closest('a') || e.target.closest('.btn-mark')) return;
                
                const thesisId = this.getAttribute('data-thesis-id');
                
                if (thesisId && thesisId > 0 && thesisId != '0') {
                    window.location.href = 'reviewThesis.php?id=' + thesisId;
                }
            });
        });
    </script>
</body>
</html>