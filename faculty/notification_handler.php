<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle GET requests (for links)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($action === 'mark_read' && $notification_id > 0) {
        $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: notification.php");
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
        
        header("Location: notification.php");
        exit;
    }
}

// Handle POST requests (for AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? $_POST['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notification_id = isset($data['notification_id']) ? (int)$data['notification_id'] : 0;
        
        if ($notification_id > 0) {
            $query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
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
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
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
}
$conn->close();
?>