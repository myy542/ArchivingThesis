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
    $stmt = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function getStudentId($conn, $user_id) {
    // Diretso na lang - user_id na ang student_id
    return $user_id;
}

// FIXED: Gamit ang is_archived para sa status
function getThesisCount($conn, $student_id, $status) {
    $count = 0;
    try {
        if ($status == 'pending') {
            // Pending means not archived
            $query = "SELECT COUNT(*) as total FROM thesis_table 
                      WHERE student_id = ? AND (is_archived = 0 OR is_archived IS NULL)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
        } elseif ($status == 'approved') {
            // Approved - wala tay column para ani, so 0 sa pagkakaron
            // Kung naa kay approval system, i-update ni
            $query = "SELECT COUNT(*) as total FROM thesis_table 
                      WHERE student_id = ? AND 1=0"; // temporary: walay approved
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
        } elseif ($status == 'rejected') {
            // Rejected - wala tay column para ani
            $query = "SELECT COUNT(*) as total FROM thesis_table 
                      WHERE student_id = ? AND 1=0";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
        } else {
            $query = "SELECT COUNT(*) as total FROM thesis_table WHERE student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $student_id);
        }
        
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
    // Temporary: 0 until may rejection system
    return 0;
}

function getArchivedCount($conn, $student_id) {
    $count = 0;
    try {
        $query = "SELECT COUNT(*) as total FROM thesis_table 
                  WHERE student_id = ? AND is_archived = 1";
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
        // Use is_read column
        $notifQuery = "SELECT 
                        notification_id as id, 
                        message, 
                        is_read,
                        created_at,
                        thesis_id
                       FROM notifications
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
            $row['status'] = $row['is_read'] == 0 ? 'unread' : 'read';
            $recentNotifications[] = $row;
        }
        $stmt->close();
        
        // Count unread using is_read
        $unreadCount = 0;
        foreach ($recentNotifications as $notif) {
            if ($notif['is_read'] == 0) {
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
                            t.is_archived as thesis_status,
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

// ==================== ADD THESIS STATUS UPDATE FUNCTIONS ====================

// Function to approve a thesis
function approveThesis($conn, $thesis_id, $faculty_id) {
    $query = "UPDATE thesis_table SET thesis_status = 'approved', approved_by = ?, approved_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $faculty_id, $thesis_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Send notification to student
    if ($result) {
        $studentQuery = "SELECT student_id, title FROM thesis_table WHERE thesis_id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $thesis_id);
        $studentStmt->execute();
        $thesis = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
        
        if ($thesis) {
            $message = "✅ Your thesis \"" . $thesis['title'] . "\" has been approved!";
            $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) 
                          VALUES (?, ?, ?, 'thesis_approved', 0, NOW())";
            $notifStmt = $conn->prepare($notifQuery);
            $notifStmt->bind_param("iis", $thesis['student_id'], $thesis_id, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }
    return $result;
}

// Function to reject a thesis
function rejectThesis($conn, $thesis_id, $faculty_id, $reason) {
    $query = "UPDATE thesis_table SET thesis_status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE thesis_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isi", $faculty_id, $reason, $thesis_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Send notification to student
    if ($result) {
        $studentQuery = "SELECT student_id, title FROM thesis_table WHERE thesis_id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $thesis_id);
        $studentStmt->execute();
        $thesis = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
        
        if ($thesis) {
            $message = "❌ Your thesis \"" . $thesis['title'] . "\" has been rejected. Reason: " . $reason;
            $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) 
                          VALUES (?, ?, ?, 'thesis_rejected', 0, NOW())";
            $notifStmt = $conn->prepare($notifQuery);
            $notifStmt->bind_param("iis", $thesis['student_id'], $thesis_id, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }
    return $result;
}

// Function to archive a thesis
function archiveThesis($conn, $thesis_id, $faculty_id) {
    $query = "UPDATE thesis_table SET is_archived = 1, archived_by = ?, archived_date = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $faculty_id, $thesis_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // Send notification to student
    if ($result) {
        $studentQuery = "SELECT student_id, title FROM thesis_table WHERE thesis_id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $thesis_id);
        $studentStmt->execute();
        $thesis = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
        
        if ($thesis) {
            $message = "📦 Your thesis \"" . $thesis['title'] . "\" has been archived.";
            $notifQuery = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) 
                          VALUES (?, ?, ?, 'thesis_archived', 0, NOW())";
            $notifStmt = $conn->prepare($notifQuery);
            $notifStmt->bind_param("iis", $thesis['student_id'], $thesis_id, $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
    }
    return $result;
}
?>