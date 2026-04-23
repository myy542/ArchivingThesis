<?php
// Function to handle notification actions
function handleNotificationActions($conn, $user_id) {
    // Mark single notification as read
    if (isset($_GET['mark_read'])) {
        $notif_id = (int)$_GET['mark_read'];
        $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notification.php");
        exit;
    }
    
    // Mark all notifications as read
    if (isset($_GET['mark_all_read'])) {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notification.php");
        exit;
    }
    
    // Delete notification
    if (isset($_GET['delete'])) {
        $notif_id = (int)$_GET['delete'];
        $query = "DELETE FROM notifications WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notif_id, $user_id);
        $stmt->execute();
        $stmt->close();
        header("Location: notification.php");
        exit;
    }
    
    // Accept co-author invitation
    if (isset($_GET['accept_invite']) && isset($_GET['thesis_id'])) {
        $thesis_id = (int)$_GET['thesis_id'];
        $notif_id = (int)$_GET['accept_invite'];
        
        // Update the thesis collaborators table
        $check_table = $conn->query("SHOW TABLES LIKE 'thesis_collaborators'");
        if ($check_table && $check_table->num_rows > 0) {
            $query = "UPDATE thesis_collaborators SET status = 'approved', approved_at = NOW() WHERE thesis_id = ? AND collaborator_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $thesis_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Mark notification as read
        $update_notif = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
        $stmt2 = $conn->prepare($update_notif);
        $stmt2->bind_param("i", $notif_id);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['message'] = "You have accepted the co-author invitation!";
        header("Location: notification.php");
        exit;
    }
    
    // Decline co-author invitation
    if (isset($_GET['decline_invite']) && isset($_GET['thesis_id'])) {
        $thesis_id = (int)$_GET['thesis_id'];
        $notif_id = (int)$_GET['decline_invite'];
        
        $check_table = $conn->query("SHOW TABLES LIKE 'thesis_collaborators'");
        if ($check_table && $check_table->num_rows > 0) {
            $query = "DELETE FROM thesis_collaborators WHERE thesis_id = ? AND collaborator_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $thesis_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $update_notif = "UPDATE notifications SET is_read = 1 WHERE notification_id = ?";
        $stmt2 = $conn->prepare($update_notif);
        $stmt2->bind_param("i", $notif_id);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['message'] = "You have declined the co-author invitation.";
        header("Location: notification.php");
        exit;
    }
}

// Function to send co-author invitation
function sendCoAuthorInvitation($conn, $thesis_id, $owner_id, $co_author_email) {
    // Check if co-author exists
    $user_query = "SELECT user_id, first_name, last_name FROM user_table WHERE email = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("s", $co_author_email);
    $user_stmt->execute();
    $co_author = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    if (!$co_author) {
        return "User with email " . $co_author_email . " does not exist.";
    }
    
    $co_author_id = $co_author['user_id'];
    
    if ($co_author_id == $owner_id) {
        return "You cannot add yourself as a co-author.";
    }
    
    // Check if already a collaborator
    $check_table = $conn->query("SHOW TABLES LIKE 'thesis_collaborators'");
    if ($check_table && $check_table->num_rows > 0) {
        $check_query = "SELECT * FROM thesis_collaborators WHERE thesis_id = ? AND collaborator_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $thesis_id, $co_author_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            if ($existing['status'] == 'pending') {
                return "Invitation already sent to this user.";
            } elseif ($existing['status'] == 'approved') {
                return "This user is already a co-author.";
            }
        }
        
        $insert = "INSERT INTO thesis_collaborators (thesis_id, collaborator_id, status, invited_at) VALUES (?, ?, 'pending', NOW())";
        $insert_stmt = $conn->prepare($insert);
        $insert_stmt->bind_param("ii", $thesis_id, $co_author_id);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    
    // Get thesis title
    $thesis_query = "SELECT title FROM thesis_table WHERE thesis_id = ?";
    $thesis_stmt = $conn->prepare($thesis_query);
    $thesis_stmt->bind_param("i", $thesis_id);
    $thesis_stmt->execute();
    $thesis = $thesis_stmt->get_result()->fetch_assoc();
    $thesis_stmt->close();
    
    // Get owner name
    $owner_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
    $owner_stmt = $conn->prepare($owner_query);
    $owner_stmt->bind_param("i", $owner_id);
    $owner_stmt->execute();
    $owner = $owner_stmt->get_result()->fetch_assoc();
    $owner_stmt->close();
    
    $owner_name = ($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '');
    $message = $owner_name . " invited you to be a co-author on thesis: " . $thesis['title'];
    
    // Create notification for co-author
    $notif_insert = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'thesis_invitation', 0, NOW())";
    $notif_stmt = $conn->prepare($notif_insert);
    $notif_stmt->bind_param("iis", $co_author_id, $thesis_id, $message);
    $notif_stmt->execute();
    $notif_stmt->close();
    
    return false;
}

// Function to get notifications
function getNotifications($conn, $user_id) {
    $notifications = [];
    
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
        $row['status'] = $row['is_read'] == 0 ? 'unread' : 'read';
        $notifications[] = $row;
    }
    $stmt->close();
    
    // Get unread count using is_read
    $count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $unreadCount = $count_result->fetch_assoc()['count'] ?? 0;
    $count_stmt->close();
    
    // Get user initials
    $user_query = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    $initials = "";
    if ($user && isset($user['first_name']) && isset($user['last_name'])) {
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    } else {
        $initials = "U";
    }
    
    return [
        'notifications' => $notifications,
        'unreadCount' => $unreadCount,
        'initials' => $initials
    ];
}

// Function to get user data
function getUserData($conn, $user_id) {
    $query = "SELECT first_name, last_name, email FROM user_table WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $initials = "";
    if ($user && isset($user['first_name']) && isset($user['last_name'])) {
        $initials = strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1));
    } else {
        $initials = "U";
    }
    
    return [
        'user' => $user,
        'initials' => $initials
    ];
}
?>