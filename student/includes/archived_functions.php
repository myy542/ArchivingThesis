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
        // Detect notification table columns
        $notif_columns = $conn->query("SHOW COLUMNS FROM notification_table");
        $notif_user_column = 'user_id';
        $notif_status_column = 'status';
        $notif_message_column = 'message';
        $notif_date_column = 'created_at';
        
        while ($col = $notif_columns->fetch_assoc()) {
            $field = $col['Field'];
            if (strpos($field, 'user') !== false) {
                $notif_user_column = $field;
            }
            if (strpos($field, 'status') !== false || strpos($field, 'is_read') !== false) {
                $notif_status_column = $field;
            }
            if (strpos($field, 'message') !== false) {
                $notif_message_column = $field;
            }
            if (strpos($field, 'created_at') !== false || strpos($field, 'date') !== false) {
                $notif_date_column = $field;
            }
        }
        
        // Get unread count
        $countQuery = "SELECT COUNT(*) as total FROM notification_table 
                       WHERE $notif_user_column = ? AND $notif_status_column = 'unread'";
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $countResult = $stmt->get_result()->fetch_assoc();
        $unreadCount = $countResult['total'] ?? 0;
        $stmt->close();
        
        // Get recent notifications
        $notifQuery = "SELECT $notif_message_column as message, $notif_status_column as status, 
                              $notif_date_column as created_at
                       FROM notification_table 
                       WHERE $notif_user_column = ? 
                       ORDER BY $notif_date_column DESC 
                       LIMIT 5";
        $stmt = $conn->prepare($notifQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
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