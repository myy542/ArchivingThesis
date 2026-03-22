<?php
function getUserData($conn, $user_id) {
    $roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($roleQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $userData;
}

function getUserDetails($conn, $user_id) {
    // Find the correct user_id column
    $user_columns = $conn->query("SHOW COLUMNS FROM user_table");
    $user_id_column = 'user_id';
    while ($column = $user_columns->fetch_assoc()) {
        if (strpos($column['Field'], 'user') !== false || strpos($column['Field'], 'id') !== false) {
            $user_id_column = $column['Field'];
            break;
        }
    }
    
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE $user_id_column = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function getStudentId($conn, $user_id) {
    $student_id = $user_id;
    $studentQuery = "SELECT student_id FROM student_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($studentQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $studentResult = $stmt->get_result();
    $studentData = $studentResult->fetch_assoc();
    $stmt->close();
    
    if ($studentData) {
        $student_id = $studentData['student_id'];
    }
    return $student_id;
}

function getThesisCount($conn, $student_id, $status) {
    $count = 0;
    try {
        $query = "SELECT COUNT(*) as total FROM thesis_table 
                  WHERE student_id = ? AND status = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $student_id, $status);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['total'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Count error for $status: " . $e->getMessage());
    }
    return $count;
}

function getRejectedCount($conn, $student_id) {
    $count = 0;
    try {
        $query = "SELECT COUNT(DISTINCT thesis_id) as total FROM thesis_table 
                  WHERE student_id = ? AND status = 'rejected'";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['total'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Rejected count error: " . $e->getMessage());
    }
    return $count;
}

function getArchivedCount($conn, $student_id) {
    $count = 0;
    try {
        $query = "SELECT COUNT(*) as total FROM thesis_table 
                  WHERE student_id = ? AND status IN ('archived', 'completed', 'finished')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['total'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Archived count error: " . $e->getMessage());
    }
    return $count;
}

function getTotalCount($conn, $student_id) {
    $count = 0;
    try {
        $query = "SELECT COUNT(*) as total FROM thesis_table 
                  WHERE student_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = $result['total'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        error_log("Total count error: " . $e->getMessage());
    }
    return $count;
}

function getNotifications($conn, $user_id) {
    $unreadCount = 0;
    $recentNotifications = [];
    
    try {
        $notifQuery = "SELECT 
                        notification_id as id, 
                        message, 
                        status,
                        created_at,
                        thesis_id
                       FROM notification_table 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC 
                       LIMIT 10";
        $stmt = $conn->prepare($notifQuery);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $recentNotifications = [];
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['thesis_id'])) {
                $titleQuery = "SELECT title FROM thesis_table WHERE thesis_id = ?";
                $titleStmt = $conn->prepare($titleQuery);
                $titleStmt->bind_param("i", $row['thesis_id']);
                $titleStmt->execute();
                $titleResult = $titleStmt->get_result();
                if ($titleRow = $titleResult->fetch_assoc()) {
                    $row['thesis_title'] = $titleRow['title'];
                }
                $titleStmt->close();
            } else {
                $row['thesis_title'] = '';
            }
            $row['is_read'] = $row['status'];
            $recentNotifications[] = $row;
        }
        $stmt->close();
        
        $unreadCount = 0;
        foreach ($recentNotifications as $notif) {
            if ($notif['status'] == 'unread') {
                $unreadCount++;
            }
        }
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
    
    return [
        'unread_count' => $unreadCount,
        'notifications' => $recentNotifications
    ];
}

function getRecentFeedback($conn, $student_id) {
    $recentFeedback = [];
    try {
        $feedbackQuery = "SELECT 
                            f.*, 
                            t.title as thesis_title, 
                            t.thesis_id,
                            u.first_name as faculty_first, 
                            u.last_name as faculty_last,
                            t.status as thesis_status,
                            f.comments as feedback_text,
                            f.feedback_date
                          FROM feedback_table f
                          JOIN thesis_table t ON f.thesis_id = t.thesis_id
                          JOIN user_table u ON f.faculty_id = u.user_id
                          WHERE t.student_id = ?
                          ORDER BY f.feedback_date DESC
                          LIMIT 5";
        $stmt = $conn->prepare($feedbackQuery);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $recentFeedback = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) {
        error_log("Feedback fetch error: " . $e->getMessage());
    }
    return $recentFeedback;
}

function countFeedbackNotifications($recentNotifications) {
    $count = 0;
    foreach ($recentNotifications as $notif) {
        if (strpos($notif['message'], 'feedback') !== false) {
            $count++;
        }
    }
    return $count;
}
?>