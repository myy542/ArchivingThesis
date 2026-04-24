<?php

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $fullName = trim(($user['first_name'] ?? '') . " " . ($user['last_name'] ?? ''));
    $initials = !empty($user['first_name']) && !empty($user['last_name']) 
                ? strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) 
                : "U";
    
    return [
        'fullName' => $fullName,
        'initials' => $initials,
        'first_name' => $user['first_name'] ?? '',
        'last_name' => $user['last_name'] ?? ''
    ];
}

function getNotifications($conn, $user_id) {
    $unreadCount = 0;
    $notifications = [];
    
    $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $result = $countStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $unreadCount = $row['count'];
    }
    $countStmt->close();
    
    $notifQuery = "SELECT notification_id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $notifStmt = $conn->prepare($notifQuery);
    $notifStmt->bind_param("i", $user_id);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    while ($row = $notifResult->fetch_assoc()) {
        $notifications[] = $row;
    }
    $notifStmt->close();
    
    return [
        'unreadCount' => $unreadCount,
        'notifications' => $notifications
    ];
}

function getArchivedTheses($conn, $user_id) {
    $archived = [];
    $student_id = $user_id;
    
    $query = "SELECT thesis_id, title, adviser, abstract, file_path, date_submitted, archived_date, is_archived 
              FROM thesis_table 
              WHERE student_id = ? AND is_archived = 1
              ORDER BY archived_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $row['status'] = 'archived';
        $archived[] = $row;
    }
    $stmt->close();
    
    return $archived;
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function markAllNotificationsAsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function handleNotificationActions($conn, $user_id) {
    if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
        $notif_id = (int)$_GET['mark_read'];
        markNotificationAsRead($conn, $notif_id, $user_id);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
    
    if (isset($_GET['mark_all_read'])) {
        markAllNotificationsAsRead($conn, $user_id);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
    
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $notif_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}
?>