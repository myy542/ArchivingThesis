<?php

function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("SELECT first_name, last_name, email, department_id FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

// Get notification count using is_read
function getNotificationCount($conn, $user_id) {
    try {
        $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
        if ($col_check && $col_check->num_rows > 0) {
            $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
        } else {
            $notif_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND status = 0";
        }
        $stmt = $conn->prepare($notif_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifResult = $stmt->get_result()->fetch_assoc();
        $count = $notifResult['total'] ?? 0;
        $stmt->close();
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

// Get department name from department_id
function getDepartmentName($conn, $department_id) {
    if (empty($department_id)) return null;
    $stmt = $conn->prepare("SELECT department_name FROM department_table WHERE department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dept = $result->fetch_assoc();
    $stmt->close();
    return $dept ? $dept['department_name'] : null;
}

// Get recent submissions using department join
function getRecentSubmissions($conn, $user_id) {
    $submissions = [];
    try {
        $student_id = $user_id;
        
        $recentQuery = "SELECT t.thesis_id, t.title, t.file_path, t.date_submitted as created_at,
                               d.department_name, d.department_code,
                               CASE WHEN t.is_archived = 1 THEN 'archived' ELSE 'pending' END as status
                       FROM thesis_table t
                       LEFT JOIN department_table d ON t.department_id = d.department_id
                       WHERE t.student_id = ? 
                       ORDER BY t.date_submitted DESC 
                       LIMIT 5";
        $stmt = $conn->prepare($recentQuery);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $submissions[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Recent submissions error: " . $e->getMessage());
    }
    return $submissions;
}

?>