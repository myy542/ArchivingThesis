<?php
session_start();
include("../config/db.php");
include("includes/notification_functions.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

handleNotificationActions($conn, $user_id);

$notificationData = getNotifications($conn, $user_id);
$notifications = $notificationData['notifications'];
$unreadCount = $notificationData['unreadCount'];
$initials = $notificationData['initials'];

$pageTitle = "Notifications";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/notification.css">
</head>
<body>
    <div class="container">
        <div class="top-bar">
            <a href="student_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
        </div>
        
        <div class="notif-header">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <?php if ($unreadCount > 0): ?>
                <a href="?mark_all_read=1" class="mark-all"><i class="fas fa-check-double"></i> Mark all as read</a>
            <?php endif; ?>
        </div>
        
        <div class="notif-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <div class="notif-item <?= ($notif['is_read'] == 0) ? 'unread' : '' ?>">
                        <div class="notif-icon">
                            <?php if ($notif['type'] == 'thesis_invitation'): ?>
                                <i class="fas fa-user-plus"></i>
                            <?php elseif ($notif['type'] == 'invitation_accepted'): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php elseif ($notif['type'] == 'invitation_declined'): ?>
                                <i class="fas fa-times-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-bell"></i>
                            <?php endif; ?>
                        </div>
                        <div class="notif-content">
                            <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                            <?php if (!empty($notif['thesis_title'])): ?>
                                <div class="notif-thesis"><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></div>
                            <?php endif; ?>
                            <div class="notif-time"><i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                            
                            <?php if ($notif['type'] == 'thesis_invitation' && $notif['is_read'] == 0): ?>
                                <div class="notif-actions">
                                    <a href="?accept_invite=<?= $notif['notification_id'] ?>&thesis_id=<?= $notif['thesis_id'] ?>" class="btn-accept" onclick="return confirm('Accept this invitation?')">
                                        <i class="fas fa-check"></i> Accept Invitation
                                    </a>
                                    <a href="?decline_invite=<?= $notif['notification_id'] ?>&thesis_id=<?= $notif['thesis_id'] ?>" class="btn-decline" onclick="return confirm('Decline this invitation?')">
                                        <i class="fas fa-times"></i> Decline
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($notif['is_read'] == 0): ?>
                                <a href="?mark_read=<?= $notif['notification_id'] ?>" class="btn-view"><i class="fas fa-check"></i> Mark read</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="js/notification.js"></script>
</body>
</html>