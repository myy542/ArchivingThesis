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

// Check if thesis_id is provided
if (!isset($_POST['thesis_id']) || empty($_POST['thesis_id'])) {
    echo json_encode(['success' => false, 'message' => 'Thesis ID is required']);
    exit;
}

$thesis_id = intval($_POST['thesis_id']);
$librarian_id = $_SESSION['user_id'];
$librarian_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$current_date = date('Y-m-d H:i:s');

// Check if theses table exists
$check_theses = $conn->query("SHOW TABLES LIKE 'theses'");
if (!$check_theses || $check_theses->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Theses table not found']);
    exit;
}

// Check if thesis exists and is approved
$check_query = "SELECT thesis_id, title, student_name, status FROM theses WHERE thesis_id = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $thesis_id);
$check_stmt->execute();
$result = $check_stmt->get_result();
$thesis = $result->fetch_assoc();

if (!$thesis) {
    echo json_encode(['success' => false, 'message' => 'Thesis not found']);
    exit;
}

if ($thesis['status'] !== 'Approved') {
    echo json_encode(['success' => false, 'message' => 'Only approved theses can be archived']);
    exit;
}

// Check what columns exist in theses table
$theses_columns = [];
$col_query = "SHOW COLUMNS FROM theses";
$col_result = $conn->query($col_query);
if ($col_result && $col_result->num_rows > 0) {
    while ($col = $col_result->fetch_assoc()) {
        $theses_columns[] = $col['Field'];
    }
}

// Update thesis status to Archived
$update_query = "UPDATE theses SET status = 'Archived', archived_date = ?";
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
    // Create notification for the student
    $student_id = null;
    
    // Check if student_id column exists
    if (in_array('student_id', $theses_columns)) {
        $student_query = "SELECT student_id FROM theses WHERE thesis_id = ?";
        $student_stmt = $conn->prepare($student_query);
        $student_stmt->bind_param("i", $thesis_id);
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        if ($student_row = $student_result->fetch_assoc()) {
            $student_id = $student_row['student_id'];
        }
        $student_stmt->close();
    }
    
    // Insert notification
    if ($student_id) {
        $message = "Your thesis '" . $thesis['title'] . "' has been archived successfully!";
        
        // Check if notifications table exists
        $check_notif = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($check_notif && $check_notif->num_rows > 0) {
            $notif_query = "INSERT INTO notifications (user_id, message, type, created_at, is_read) VALUES (?, ?, 'archive', ?, 0)";
            $notif_stmt = $conn->prepare($notif_query);
            $notif_stmt->bind_param("iss", $student_id, $message, $current_date);
            $notif_stmt->execute();
            $notif_stmt->close();
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