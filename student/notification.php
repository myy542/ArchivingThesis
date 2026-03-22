<?php
session_start();
include("../config/db.php");
include("includes/notification_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Handle notification actions
handleNotificationActions($conn, $user_id);

// Get user data
$userData = getUserData($conn, $user_id);
$initials = $userData['initials'];

// Get notifications
$notificationData = getNotifications($conn, $user_id);
$notifications = $notificationData['notifications'];
$unreadCount = $notificationData['unreadCount'];

$pageTitle = "Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/notification.css">
</head>
<body>

<div class="layout">
    <main class="main-content">

        <header class="topbar">
            <h1>Notifications</h1>
            <div class="user-info">
                <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            </div>
        </header>

        <a href="student_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="notifications-container">
            <div class="notifications-header">
                <h2>
                    <i class="fas fa-bell"></i>
                    All Notifications
                    <?php if ($unreadCount > 0): ?>
                        <span class="unread-badge">
                            <?= $unreadCount ?> unread
                        </span>
                    <?php endif; ?>
                </h2>
                <div class="header-actions">
                    <?php if ($unreadCount > 0): ?>
                        <a href="?mark_all_read=1" class="btn btn-primary">
                            <i class="fas fa-check-double"></i> Mark All as Read
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="notification-list">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You don't have any notifications at the moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['status'] == 'unread' ? 'unread' : '' ?>">
                            <div class="notification-icon">
                                <i class="fas <?= $notif['status'] == 'unread' ? 'fa-bell' : 'fa-bell-slash' ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?= htmlspecialchars($notif['message']) ?>
                                </div>
                                
                                <?php if (!empty($notif['thesis_title'])): ?>
                                    <div class="notification-thesis">
                                        <i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="notification-meta">
                                    <span>
                                        <i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($notif['created_at'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock"></i> <?= date('h:i A', strtotime($notif['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php if ($notif['status'] == 'unread'): ?>
                                    <a href="?mark_read=<?= $notif['notification_id'] ?>" class="action-btn mark-read">
                                        <i class="fas fa-check"></i> Mark Read
                                    </a>
                                <?php endif; ?>
                                <a href="?delete=<?= $notif['notification_id'] ?>" class="action-btn delete" onclick="return confirm('Delete this notification?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<script src="js/notification.js"></script>
</body>
</html>