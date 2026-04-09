<?php
function handleNotificationActions($conn, $user_id) {
    // Mark single notification as read
    if (isset($_GET['mark_read'])) {
        $notif_id = (int)$_GET['mark_read'];
        markNotificationAsRead($conn, $notif_id, $user_id);
        header("Location: notification.php");
        exit;
    }

    // Mark all as read
    if (isset($_GET['mark_all_read'])) {
        markAllNotificationsAsRead($conn, $user_id);
        header("Location: notification.php");
        exit;
    }

    // Delete notification
    if (isset($_GET['delete'])) {
        $notif_id = (int)$_GET['delete'];
        deleteNotification($conn, $notif_id, $user_id);
        header("Location: notification.php");
        exit;
    }
}

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $first = trim($user["first_name"] ?? "");
    $last  = trim($user["last_name"] ?? "");
    $initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "U";

    return [
        'first' => $first,
        'last' => $last,
        'initials' => $initials
    ];
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    $updateQuery = "UPDATE notifications SET status = 'read' WHERE notification_id = ? AND user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function markAllNotificationsAsRead($conn, $user_id) {
    $updateQuery = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function deleteNotification($conn, $notification_id, $user_id) {
    $deleteQuery = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function getNotifications($conn, $user_id) {
    $notifications = [];
    $unreadCount = 0;

    try {
        // Get unread count
        $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 'unread'";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $unreadCount = $countResult['total'] ?? 0;
        $stmt->close();
        
        // Get all notifications with thesis details
        $query = "SELECT n.*, t.title as thesis_title 
                  FROM notifications n
                  LEFT JOIN thesis_table t ON n.thesis_id = t.thesis_id
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
        error_log("Notifications error: " . $e->getMessage());
    }

    return [
        'notifications' => $notifications,
        'unreadCount' => $unreadCount
    ];
}
?>