<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_POST['thesis_id']) || empty($_POST['thesis_id'])) {
    echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
    exit;
}

$thesis_id = intval($_POST['thesis_id']);
$librarian_id = $_SESSION['user_id'];
$current_date = date('Y-m-d H:i:s');
$retention_period = isset($_POST['retention_period']) ? intval($_POST['retention_period']) : 5;
$archive_notes = isset($_POST['archive_notes']) ? trim($_POST['archive_notes']) : '';

// Check if thesis exists
$check_query = "SELECT thesis_id, title, student_id, is_archived FROM thesis_table WHERE thesis_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $thesis_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$thesis = $result->fetch_assoc();

if (!$thesis) {
    echo json_encode(['success' => false, 'message' => 'Thesis not found']);
    exit;
}

if ($thesis['is_archived'] == 1) {
    echo json_encode(['success' => false, 'message' => 'This thesis is already archived.']);
    exit;
}

// Update thesis to archived
$update_query = "UPDATE thesis_table SET 
                 is_archived = 1, 
                 archived_date = ?, 
                 archived_by = ?, 
                 retention_period = ?, 
                 archive_notes = ? 
                 WHERE thesis_id = ?";

$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param("isiis", $current_date, $librarian_id, $retention_period, $archive_notes, $thesis_id);

if ($update_stmt->execute()) {
    // Insert notification for student
    $student_id = $thesis['student_id'];
    if ($student_id) {
        $message = "Your thesis '" . addslashes($thesis['title']) . "' has been ARCHIVED successfully.";
        $notif_query = "INSERT INTO notifications (user_id, thesis_id, message, type, is_read, created_at) VALUES (?, ?, ?, 'archive', 0, NOW())";
        $notif_stmt = $conn->prepare($notif_query);
        $notif_stmt->bind_param("iis", $student_id, $thesis_id, $message);
        $notif_stmt->execute();
        $notif_stmt->close();
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Thesis archived successfully',
        'thesis_title' => $thesis['title']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to archive thesis: ' . $conn->error]);
}

$update_stmt->close();
$check_stmt->close();
$conn->close();
?>