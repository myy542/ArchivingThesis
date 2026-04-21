<?php
session_start();
include("../config/db.php");

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in and is a librarian
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Debug: Log POST data (optional, remove in production)
error_log("POST data: " . print_r($_POST, true));

// Check if thesis_id is provided
if (!isset($_POST['thesis_id']) || empty($_POST['thesis_id'])) {
    echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
    exit;
}

$thesis_id = intval($_POST['thesis_id']);
$librarian_id = $_SESSION['user_id'];
$librarian_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$current_date = date('Y-m-d H:i:s');

// Check if thesis_table exists
$check_theses = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if (!$check_theses || $check_theses->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Thesis table not found']);
    exit;
}

// Get all columns in thesis_table
$theses_columns = [];
$col_query = "SHOW COLUMNS FROM thesis_table";
$col_result = $conn->query($col_query);
if ($col_result && $col_result->num_rows > 0) {
    while ($col = $col_result->fetch_assoc()) {
        $theses_columns[] = $col['Field'];
    }
}

// Check if thesis exists and get its details
$check_query = "SELECT thesis_id, title, student_id, status FROM thesis_table WHERE thesis_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $thesis_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$thesis = $result->fetch_assoc();

if (!$thesis) {
    echo json_encode(['success' => false, 'message' => 'Thesis not found']);
    exit;
}

// Check if status is already archived
if ($thesis['status'] == 'archived') {
    echo json_encode(['success' => false, 'message' => 'This thesis is already archived.']);
    exit;
}

// Check if status is approved or forwarded_to_dean (can be archived)
if ($thesis['status'] !== 'approved' && $thesis['status'] !== 'forwarded_to_dean') {
    echo json_encode(['success' => false, 'message' => 'Only approved theses can be archived. Current status: ' . $thesis['status']]);
    exit;
}

// Update thesis status to 'archived'
$update_query = "UPDATE thesis_table SET status = 'archived', archived_date = ?";
$params = [$current_date];
$types = "s";

// Add archived_by column if it exists
if (in_array('archived_by', $theses_columns)) {
    $update_query .= ", archived_by = ?";
    $params[] = $librarian_name;
    $types .= "s";
}

// Add archived_by_id column if it exists
if (in_array('archived_by_id', $theses_columns)) {
    $update_query .= ", archived_by_id = ?";
    $params[] = $librarian_id;
    $types .= "i";
}

$update_query .= " WHERE thesis_id = ?";
$params[] = $thesis_id;
$types .= "i";

$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param($types, ...$params);

if ($update_stmt->execute()) {
    // Get student_id from thesis data
    $student_id = $thesis['student_id'];
    
    // Insert notification for student
    if ($student_id) {
        $message = "📚 Your thesis '" . $thesis['title'] . "' has been ARCHIVED successfully by Librarian " . $librarian_name;
        
        // Check if notifications table exists
        $check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($check_notif && $check_notif->num_rows > 0) {
            // Check what columns exist in notifications table
            $notif_columns = [];
            $notif_col_query = "SHOW COLUMNS FROM notifications";
            $notif_col_result = $conn->query($notif_col_query);
            if ($notif_col_result && $notif_col_result->num_rows > 0) {
                while ($col = $notif_col_result->fetch_assoc()) {
                    $notif_columns[] = $col['Field'];
                }
            }
            
            // Use 'is_read' if exists, otherwise use 'status'
            $read_column = in_array('is_read', $notif_columns) ? 'is_read' : (in_array('status', $notif_columns) ? 'status' : null);
            
            if ($read_column) {
                $notif_query = "INSERT INTO notifications (user_id, thesis_id, message, type, $read_column, created_at) VALUES (?, ?, ?, 'archive', 0, NOW())";
                $notif_stmt = $conn->prepare($notif_query);
                $notif_stmt->bind_param("iiss", $student_id, $thesis_id, $message);
                $notif_stmt->execute();
                $notif_stmt->close();
            } else {
                $notif_query = "INSERT INTO notifications (user_id, thesis_id, message, type, created_at) VALUES (?, ?, ?, 'archive', NOW())";
                $notif_stmt = $conn->prepare($notif_query);
                $notif_stmt->bind_param("iiss", $student_id, $thesis_id, $message);
                $notif_stmt->execute();
                $notif_stmt->close();
            }
        }
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