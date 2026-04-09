<?php
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, username FROM user_table WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $fullName = trim(($u["first_name"] ?? "") . " " . ($u["last_name"] ?? ""));
    if ($fullName === "") $fullName = $u["username"] ?? "User";

    $fi = strtoupper(substr(($u["first_name"] ?? $fullName), 0, 1));
    $li = strtoupper(substr(($u["last_name"] ?? $fullName), 0, 1));
    $initials = trim($fi . $li);

    return [
        'fullName' => $fullName,
        'initials' => $initials,
        'username' => $u['username'] ?? ''
    ];
}

function getNotifications($conn, $user_id) {
    $unreadCount = 0;
    $recentNotifications = [];

    try {
        // Get unread count - gamita ang is_read column (0 = unread, 1 = read)
        $countQuery = "SELECT COUNT(*) as total FROM notifications 
                       WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $unreadCount = $countResult['total'] ?? 0;
        $stmt->close();
        
        // Get recent notifications - sakto na ang table name kay "notifications"
        $notifQuery = "SELECT notification_id, message, type, link, is_read, created_at, thesis_id
                       FROM notifications 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 5";
        $stmt = $conn->prepare($notifQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            // Convert is_read to readable format
            $row['status'] = ($row['is_read'] == 0) ? 'unread' : 'read';
            $recentNotifications[] = $row;
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }

    return [
        'unreadCount' => $unreadCount,
        'notifications' => $recentNotifications
    ];
}

// Optional: Function para mag-create og bag-ong notification
function createNotification($conn, $user_id, $thesis_id, $message, $type = 'info', $link = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->bind_param("iisss", $user_id, $thesis_id, $message, $type, $link);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Create notification error: " . $e->getMessage());
        return false;
    }
}

// Optional: Function para mark as read ang notification
function markNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE notification_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Mark as read error: " . $e->getMessage());
        return false;
    }
}

// Optional: Function para mark all as read
function markAllNotificationsAsRead($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("Mark all as read error: " . $e->getMessage());
        return false;
    }
}

function getArchivedTheses($conn, $user_id) {
    $archived = [];
    
    $stmt = $conn->prepare("
        SELECT thesis_id, title, abstract, adviser, file_path, date_submitted, status
        FROM thesis_table
        WHERE student_id = ?
        AND LOWER(status) = 'archived'
        ORDER BY date_submitted DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    while ($row = $res->fetch_assoc()) {
        $row['file_path'] = '/ArchivingThesis/' . $row['file_path'];
        $archived[] = $row;
    }
    $stmt->close();
    
    return $archived;
}
?>