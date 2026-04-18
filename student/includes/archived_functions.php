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
    
    // Get unread count
    $countQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND status = 0";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $result = $countStmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $unreadCount = $row['count'];
    }
    $countStmt->close();
    
    // Get recent notifications
    $notifQuery = "SELECT notification_id, message, status, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
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
    
    // First get the student_id from student_table
    $studentQuery = "SELECT student_id FROM student_table WHERE user_id = ? LIMIT 1";
    $studentStmt = $conn->prepare($studentQuery);
    $studentStmt->bind_param("i", $user_id);
    $studentStmt->execute();
    $studentResult = $studentStmt->get_result();
    $student = $studentResult->fetch_assoc();
    $studentStmt->close();
    
    if (!$student) {
        return $archived;
    }
    
    $student_id = $student['student_id'];
    
    // Get archived theses for this student only
    $query = "SELECT thesis_id, title, adviser, abstract, file_path, date_submitted, archived_date, status 
              FROM thesis_table 
              WHERE student_id = ? AND status = 'archived'
              ORDER BY archived_date DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $archived[] = $row;
    }
    $stmt->close();
    
    return $archived;
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET status = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

function markAllNotificationsAsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET status = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    return true;
}

?>