<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION["user_id"];

$roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($roleQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData || $userData['role_id'] != 3) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

if ($action === 'mark_read') {
    $notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
    
    if ($notification_id > 0) {
        $query = "UPDATE notifications SET status = 'read' WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notification_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    }
} 
elseif ($action === 'mark_all_read') {
    $query = "UPDATE notifications SET status = 'read' WHERE user_id = ? AND status = 'unread'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
}
else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>