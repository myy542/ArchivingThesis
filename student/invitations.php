<?php
session_start();
include("../config/db.php");

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$notif_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action == 'accept' && $notif_id > 0) {
    $notifQuery = "SELECT thesis_id, message FROM notifications WHERE notification_id = ? AND user_id = ?";
    $notifStmt = $conn->prepare($notifQuery);
    $notifStmt->bind_param("ii", $notif_id, $user_id);
    $notifStmt->execute();
    $notif = $notifStmt->get_result()->fetch_assoc();
    $notifStmt->close();
    
    if ($notif && $notif['thesis_id']) {
        $thesis_id = $notif['thesis_id'];
        
        $checkCollab = $conn->prepare("SELECT * FROM thesis_collaborators WHERE thesis_id = ? AND user_id = ?");
        $checkCollab->bind_param("ii", $thesis_id, $user_id);
        $checkCollab->execute();
        $existing = $checkCollab->get_result()->fetch_assoc();
        $checkCollab->close();
        
        if (!$existing) {
            $collabQuery = "INSERT INTO thesis_collaborators (thesis_id, user_id, role) VALUES (?, ?, 'co-author')";
            $collabStmt = $conn->prepare($collabQuery);
            $collabStmt->bind_param("ii", $thesis_id, $user_id);
            $collabStmt->execute();
            $collabStmt->close();
            
            $updateNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
            $updateNotif->bind_param("i", $notif_id);
            $updateNotif->execute();
            $updateNotif->close();
            
            $ownerQuery = "SELECT student_id FROM thesis_table WHERE thesis_id = ?";
            $ownerStmt = $conn->prepare($ownerQuery);
            $ownerStmt->bind_param("i", $thesis_id);
            $ownerStmt->execute();
            $owner = $ownerStmt->get_result()->fetch_assoc();
            $ownerStmt->close();
            
            if ($owner) {
                $userQuery = "SELECT first_name, last_name FROM user_table WHERE user_id = ?";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bind_param("i", $user_id);
                $userStmt->execute();
                $userData = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();
                
                $notifMessage = "✅ " . $userData['first_name'] . " " . $userData['last_name'] . " has accepted your invitation to collaborate on thesis ID: " . $thesis_id;
                $insertNotif = $conn->prepare("INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'collaborator_joined', 0, NOW())");
                $insertNotif->bind_param("iis", $owner['student_id'], $thesis_id, $notifMessage);
                $insertNotif->execute();
                $insertNotif->close();
            }
        }
        
        $_SESSION['success'] = "You have successfully joined the thesis as a co-author!";
    }
    
    header("Location: projects.php");
    exit;
}

if ($action == 'decline' && $notif_id > 0) {
    $updateNotif = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $updateNotif->bind_param("ii", $notif_id, $user_id);
    $updateNotif->execute();
    $updateNotif->close();
    
    $_SESSION['info'] = "You have declined the invitation.";
    header("Location: projects.php");
    exit;
}

header("Location: projects.php");
?>