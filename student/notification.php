<?php
session_start();
include("../config/db.php");
include("includes/notification_functions.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Handle notification actions (including co-author invites)
handleNotificationActions($conn, $user_id);

// Get notifications
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; }
        
        .container { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #dc2626; text-decoration: none; margin-bottom: 1.5rem; }
        .back-link:hover { text-decoration: underline; }
        
        .notif-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .notif-header h1 { font-size: 1.5rem; color: #991b1b; display: flex; align-items: center; gap: 0.5rem; }
        .mark-all { color: #dc2626; text-decoration: none; font-size: 0.85rem; }
        .mark-all:hover { text-decoration: underline; }
        
        .notif-list { display: flex; flex-direction: column; gap: 1rem; }
        .notif-item { background: white; border-radius: 16px; padding: 1rem; border: 1px solid #fee2e2; transition: all 0.2s; display: flex; gap: 1rem; align-items: flex-start; }
        .notif-item:hover { transform: translateX(5px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .notif-item.unread { background: #fff5f5; border-left: 4px solid #dc2626; }
        .notif-icon { width: 45px; height: 45px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .notif-icon i { font-size: 1.2rem; color: #dc2626; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.9rem; color: #1f2937; margin-bottom: 0.25rem; line-height: 1.4; }
        .notif-thesis { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; }
        .notif-time { font-size: 0.7rem; color: #9ca3af; margin-top: 0.5rem; }
        .notif-actions { display: flex; gap: 0.75rem; margin-top: 0.75rem; }
        .btn-accept { background: #10b981; color: white; border: none; padding: 0.4rem 1rem; border-radius: 20px; cursor: pointer; font-size: 0.7rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-accept:hover { background: #059669; transform: scale(1.05); }
        .btn-decline { background: #ef4444; color: white; border: none; padding: 0.4rem 1rem; border-radius: 20px; cursor: pointer; font-size: 0.7rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-decline:hover { background: #dc2626; transform: scale(1.05); }
        .btn-view { background: #dc2626; color: white; border: none; padding: 0.4rem 1rem; border-radius: 20px; cursor: pointer; font-size: 0.7rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-view:hover { background: #991b1b; transform: scale(1.05); }
        
        .empty-state { text-align: center; padding: 3rem; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #dc2626; }
        
        /* Top Bar */
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #fee2e2; }
        .user-avatar { width: 45px; height: 45px; background: linear-gradient(135deg, #dc2626, #991b1b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1rem; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .notif-item { flex-direction: column; }
            .notif-actions { width: 100%; justify-content: flex-start; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .notif-item { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .notif-item.unread { background: #3a1a1a; }
        body.dark-mode .notif-message { color: #e5e7eb; }
        body.dark-mode .notif-header h1 { color: #fecaca; }
    </style>
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
    
    <script>
        const isDark = localStorage.getItem('darkMode') === 'true';
        if (isDark) document.body.classList.add('dark-mode');
    </script>
</body>
</html>