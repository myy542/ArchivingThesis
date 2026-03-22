<?php
function getUserData($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT first_name, last_name, email, contact_number, address, birth_date, profile_picture
        FROM user_table
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user;
}

function getInitials($first, $last) {
    $initials = "";
    if ($first && $last) {
        $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    } elseif ($first) {
        $initials = strtoupper(substr($first, 0, 1));
    } else {
        $initials = "U";
    }
    return $initials;
}

function getNotificationCount($conn, $user_id) {
    $count = 0;
    try {
        $notif_query = "SELECT COUNT(*) as total FROM notification_table WHERE user_id = ? AND status != 'read'";
        $stmt = $conn->prepare($notif_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifResult = $stmt->get_result()->fetch_assoc();
        $count = $notifResult['total'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        $count = 0;
    }
    return $count;
}

// Keep your existing edit profile functions here...
function handleProfileUpdate($conn, $user_id, $post, $files) {
    // ... existing code from edit_profile ...
}

function uploadProfilePicture($file, $user_id) {
    // ... existing code from edit_profile ...
}

function updateUserProfile($conn, $user_id, $data) {
    // ... existing code from edit_profile ...
}
?>