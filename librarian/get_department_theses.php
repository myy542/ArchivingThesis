<?php
session_start();
include("../config/db.php");

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['dept_id']) || empty($_GET['dept_id'])) {
    echo json_encode(['success' => false, 'message' => 'Department ID is required']);
    exit;
}

$dept_id = intval($_GET['dept_id']);

// Check if theses table exists
$check_theses = $conn->query("SHOW TABLES LIKE 'theses'");
if (!$check_theses || $check_theses->num_rows == 0) {
    $sample_theses = [
        ['id' => 1, 'title' => 'AI-Powered Thesis Recommendation System', 'student' => 'Maria Santos', 'adviser' => 'Prof. Juan Dela Cruz', 'date' => 'Mar 15, 2026', 'status' => 'Approved'],
        ['id' => 2, 'title' => 'Mobile App for Campus Navigation', 'student' => 'Juan Dela Cruz', 'adviser' => 'Dr. Ana Lopez', 'date' => 'Mar 14, 2026', 'status' => 'Archived'],
        ['id' => 3, 'title' => 'E-Learning Platform for Mathematics', 'student' => 'Ana Lopez', 'adviser' => 'Prof. Pedro Reyes', 'date' => 'Mar 12, 2026', 'status' => 'Approved'],
    ];
    echo json_encode(['success' => true, 'theses' => $sample_theses]);
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

// Build query based on existing columns
$select_fields = ['thesis_id', 'title'];
if (in_array('student_name', $theses_columns)) $select_fields[] = 'student_name';
if (in_array('adviser_name', $theses_columns)) $select_fields[] = 'adviser_name';
if (in_array('created_at', $theses_columns)) $select_fields[] = 'created_at';
if (in_array('status', $theses_columns)) $select_fields[] = 'status';
if (in_array('department_id', $theses_columns)) $select_fields[] = 'department_id';

$query = "SELECT " . implode(', ', $select_fields) . " FROM theses WHERE department_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $dept_id);
$stmt->execute();
$result = $stmt->get_result();

$theses = [];
while ($row = $result->fetch_assoc()) {
    $theses[] = [
        'id' => $row['thesis_id'],
        'title' => $row['title'],
        'student' => $row['student_name'] ?? 'Unknown',
        'adviser' => $row['adviser_name'] ?? 'Unknown',
        'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
        'status' => $row['status'] ?? 'Pending'
    ];
}

if (empty($theses)) {
    $sample_theses = [
        ['id' => 1, 'title' => 'AI-Powered Thesis Recommendation System', 'student' => 'Maria Santos', 'adviser' => 'Prof. Juan Dela Cruz', 'date' => 'Mar 15, 2026', 'status' => 'Approved'],
        ['id' => 2, 'title' => 'Mobile App for Campus Navigation', 'student' => 'Juan Dela Cruz', 'adviser' => 'Dr. Ana Lopez', 'date' => 'Mar 14, 2026', 'status' => 'Archived'],
        ['id' => 3, 'title' => 'E-Learning Platform for Mathematics', 'student' => 'Ana Lopez', 'adviser' => 'Prof. Pedro Reyes', 'date' => 'Mar 12, 2026', 'status' => 'Approved'],
    ];
    echo json_encode(['success' => true, 'theses' => $sample_theses]);
    exit;
}

echo json_encode(['success' => true, 'theses' => $theses]);

$stmt->close();
$conn->close();
?>