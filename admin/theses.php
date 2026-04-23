<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - ONLY ADMIN CAN ACCESS
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

// AUDIT LOGS TABLE
$check_audit_table = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if (!$check_audit_table || $check_audit_table->num_rows == 0) {
    $conn->query("CREATE TABLE audit_logs (
        audit_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(255),
        table_name VARCHAR(100),
        record_id INT,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

function logAdminAction($conn, $user_id, $action, $table, $record_id, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if ($ip == '::1') $ip = '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip);
    $log_stmt->execute();
    $log_stmt->close();
}

// ==================== DEPARTMENT LIST ====================
$departments = [
    'BSIT' => 'BS Information Technology',
    'BSBA' => 'BS Business Administration',
    'BSCRIM' => 'BS Criminology',
    'BSED' => 'BS Education',
    'BSHTM' => 'BS Hospitality Management'
];

$dept_keys = array_keys($departments);

// Helper function to map department codes
function mapDepartmentCode($dept) {
    if ($dept === null) return 'BSIT';
    $dept_upper = strtoupper(trim($dept));
    switch ($dept_upper) {
        case 'IT':
        case 'BSIT': return 'BSIT';
        case 'BUS':
        case 'BSBA':
        case 'BA': return 'BSBA';
        case 'CRIM':
        case 'BSCRIM': return 'BSCRIM';
        case 'EDUC':
        case 'BSED':
        case 'ENG': return 'BSED';
        case 'HTM':
        case 'BSHTM': return 'BSHTM';
        default: return 'BSIT';
    }
}

// ==================== PROCESS FORM SUBMISSIONS ====================
$message = '';
$message_type = '';

// ADD THESIS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $title = trim($_POST['title']);
    $author_name = trim($_POST['author']);
    $department = trim($_POST['department']);
    $year = trim($_POST['year']);
    $abstract = trim($_POST['abstract']);
    $is_archived = $_POST['is_archived'] ?? 0;
    
    // Find student_id from author name
    $student_id = null;
    $name_parts = explode(' ', $author_name, 2);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';
    
    if (!empty($first_name)) {
        $find_student = $conn->prepare("SELECT user_id FROM user_table WHERE first_name LIKE ? AND (last_name LIKE ? OR last_name IS NULL) AND role_id = 2 LIMIT 1");
        $like_first = "%$first_name%";
        $like_last = "%$last_name%";
        $find_student->bind_param("ss", $like_first, $like_last);
        $find_student->execute();
        $find_student->bind_result($student_id);
        $find_student->fetch();
        $find_student->close();
    }
    
    // FIXED: INSERT without status column, use is_archived
    $stmt = $conn->prepare("INSERT INTO thesis_table (student_id, title, abstract, keywords, department, year, adviser, file_path, date_submitted, is_archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $keywords = "";
    $adviser = "";
    $file_path = "";
    $stmt->bind_param("isssssssi", $student_id, $title, $abstract, $keywords, $department, $year, $adviser, $file_path, $is_archived);
    
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        logAdminAction($conn, $user_id, "Added Thesis", "thesis_table", $new_id, "Added new thesis: $title ($department)");
        $message = "Thesis added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding thesis: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// EDIT THESIS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $thesis_id = $_POST['thesis_id'];
    $title = trim($_POST['title']);
    $author_name = trim($_POST['author']);
    $department = trim($_POST['department']);
    $year = trim($_POST['year']);
    $abstract = trim($_POST['abstract']);
    $is_archived = $_POST['is_archived'] ?? 0;
    
    $student_id = null;
    $name_parts = explode(' ', $author_name, 2);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';
    
    if (!empty($first_name)) {
        $find_student = $conn->prepare("SELECT user_id FROM user_table WHERE first_name LIKE ? AND (last_name LIKE ? OR last_name IS NULL) AND role_id = 2 LIMIT 1");
        $like_first = "%$first_name%";
        $like_last = "%$last_name%";
        $find_student->bind_param("ss", $like_first, $like_last);
        $find_student->execute();
        $find_student->bind_result($student_id);
        $find_student->fetch();
        $find_student->close();
    }
    
    // FIXED: UPDATE without status column
    $stmt = $conn->prepare("UPDATE thesis_table SET student_id=?, title=?, abstract=?, department=?, year=?, is_archived=? WHERE thesis_id=?");
    $stmt->bind_param("issssii", $student_id, $title, $abstract, $department, $year, $is_archived, $thesis_id);
    
    if ($stmt->execute()) {
        logAdminAction($conn, $user_id, "Edited Thesis", "thesis_table", $thesis_id, "Edited thesis: $title");
        $message = "Thesis updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating thesis: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// DELETE THESIS
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    $thesis_info = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
    $thesis_info->bind_param("i", $delete_id);
    $thesis_info->execute();
    $thesis_info->bind_result($thesis_title);
    $thesis_info->fetch();
    $thesis_info->close();
    
    $stmt = $conn->prepare("DELETE FROM thesis_table WHERE thesis_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        logAdminAction($conn, $user_id, "Deleted Thesis", "thesis_table", $delete_id, "Deleted thesis: $thesis_title");
        $message = "Thesis deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting thesis: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// UPDATE THESIS ARCHIVE STATUS
if (isset($_GET['update_archive'])) {
    $thesis_id = $_GET['update_archive'];
    $new_archive_status = $_GET['is_archived'];
    
    $stmt = $conn->prepare("UPDATE thesis_table SET is_archived = ? WHERE thesis_id = ?");
    $stmt->bind_param("ii", $new_archive_status, $thesis_id);
    
    if ($stmt->execute()) {
        $thesis_info = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
        $thesis_info->bind_param("i", $thesis_id);
        $thesis_info->execute();
        $thesis_info->bind_result($thesis_title);
        $thesis_info->fetch();
        $thesis_info->close();
        
        $status_text = ($new_archive_status == 1) ? 'Archived' : 'Active';
        logAdminAction($conn, $user_id, "Updated Thesis Status", "thesis_table", $thesis_id, "Changed thesis '$thesis_title' status to $status_text");
        $message = "Thesis status updated to $status_text!";
        $message_type = "success";
    } else {
        $message = "Error updating status: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// GET THESIS FOR EDIT (AJAX) - FIXED: removed status
if (isset($_GET['get_thesis'])) {
    $get_id = $_GET['get_thesis'];
    $stmt = $conn->prepare("SELECT t.thesis_id, t.title, t.abstract, t.department, t.year, t.is_archived,
                                  COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') as author
                           FROM thesis_table t
                           LEFT JOIN user_table u ON t.student_id = u.user_id
                           WHERE t.thesis_id = ?");
    $stmt->bind_param("i", $get_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $thesis = $result->fetch_assoc();
    echo json_encode(['success' => true, 'thesis' => $thesis]);
    exit;
}

// DASHBOARDS
$dashboards = [
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php', 'role_id' => 1],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php', 'role_id' => 2],
    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php', 'role_id' => 3],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php', 'role_id' => 4],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php', 'role_id' => 5],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php', 'role_id' => 6]
];

// GET FILTERS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$archive_filter = isset($_GET['archive']) ? $_GET['archive'] : '';

// ==================== BUILD QUERY - FIXED: no status column ====================
$query = "SELECT 
            t.thesis_id, 
            t.title, 
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') as author,
            t.department, 
            t.year, 
            t.abstract, 
            t.is_archived,
            t.date_submitted as created_at,
            t.file_path
          FROM thesis_table t
          LEFT JOIN user_table u ON t.student_id = u.user_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (t.title LIKE ? OR COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') LIKE ? OR t.year LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if ($archive_filter !== '') {
    $query .= " AND t.is_archived = ?";
    $params[] = $archive_filter;
    $types .= "i";
}

$query .= " ORDER BY t.thesis_id DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Group theses by department - ONLY THE 5 MAIN DEPARTMENTS
$theses_by_dept = [];
foreach ($dept_keys as $dept) {
    $theses_by_dept[$dept] = [
        'name' => $departments[$dept],
        'code' => $dept,
        'theses' => [],
        'count' => 0
    ];
}

// Fetch all theses and group them
while ($thesis = $result->fetch_assoc()) {
    $dept = $thesis['department'];
    $mapped_dept = mapDepartmentCode($dept);
    
    // Only add to our 5 main departments
    if (in_array($mapped_dept, $dept_keys)) {
        // Add a virtual status field for display
        $thesis['status'] = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';
        $theses_by_dept[$mapped_dept]['theses'][] = $thesis;
        $theses_by_dept[$mapped_dept]['count']++;
    }
}
$stmt->close();

// GET STATISTICS - FIXED: using is_archived
$total_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table")->fetch_assoc()['c'];
$pending_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table WHERE (is_archived = 0 OR is_archived IS NULL)")->fetch_assoc()['c'];
$approved_theses = 0; // No approved column yet
$archived_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table WHERE is_archived = 1")->fetch_assoc()['c'];

// Get statistics by department
$dept_stats = [];
foreach ($dept_keys as $dept) {
    $dept_stats[$dept] = $theses_by_dept[$dept]['count'];
}

// ==================== NOTIFICATION COUNT ====================
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
    if ($col_check && $col_check->num_rows > 0) {
        $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->bind_param("i", $user_id);
        $n->execute();
        $result = $n->get_result();
        if ($row = $result->fetch_assoc()) {
            $notificationCount = $row['c'];
        }
        $n->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theses Management | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }
        
        .top-nav { position: fixed; top: 0; right: 0; left: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #ffcdd2; }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; padding: 12px; align-items: center; justify-content: center; }
        .hamburger span { display: block; width: 20px; height: 2px; background: #dc2626; border-radius: 2px; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #d32f2f; }
        .logo span { color: #d32f2f; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; border: 1px solid #ffcdd2; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 220px; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
        .notification-icon { position: relative; cursor: pointer; width: 40px; height: 40px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .notification-icon:hover { background: #fee2e2; }
        .notification-icon i { font-size: 1.2rem; color: #dc2626; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 600; min-width: 18px; height: 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 12px; border-radius: 40px; transition: background 0.3s; }
        .profile-trigger:hover { background: #ffebee; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 38px; height: 38px; background: linear-gradient(135deg, #dc2626, #5b3b3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 100; border: 1px solid #ffcdd2; }
        .profile-dropdown.show { display: block; animation: fadeIn 0.2s; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; transition: 0.2s; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #ffebee; color: #dc2626; }
        .profile-dropdown hr { margin: 0; border-color: #ffcdd2; }
        
        .sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: linear-gradient(180deg, #b71c1c 0%, #d32f2f 100%); display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.2); text-align: center; }
        .logo-container .logo { color: white; font-size: 1.4rem; }
        .logo-container .logo span { color: #ffcdd2; }
        .admin-label { font-size: 0.7rem; color: #ffcdd2; margin-top: 5px; letter-spacing: 1px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 6px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #ffebee; transition: all 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; font-size: 1.1rem; }
        .nav-item:hover { background: rgba(255,255,255,0.2); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.25); color: white; }
        .dashboard-links { padding: 16px; border-top: 1px solid rgba(255,255,255,0.15); border-bottom: 1px solid rgba(255,255,255,0.15); margin: 5px 0; }
        .dashboard-links-header { display: flex; align-items: center; gap: 10px; padding: 8px 12px; color: #ffcdd2; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .dashboard-link { display: flex; align-items: center; gap: 12px; padding: 8px 12px; border-radius: 10px; text-decoration: none; color: #ffebee; font-size: 0.8rem; transition: all 0.2s; }
        .dashboard-link:hover { background: rgba(255,255,255,0.15); transform: translateX(5px); }
        .dashboard-link .link-icon { margin-left: auto; font-size: 0.7rem; opacity: 0.7; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 15px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; position: relative; width: 55px; height: 28px; background: rgba(255,255,255,0.25); border-radius: 30px; }
        .toggle-label i { position: absolute; top: 50%; transform: translateY(-50%); font-size: 12px; z-index: 1; }
        .toggle-label i:first-child { left: 8px; color: #f39c12; }
        .toggle-label i:last-child { right: 8px; color: #f1c40f; }
        .toggle-label .slider { position: absolute; top: 3px; left: 3px; width: 22px; height: 22px; background: white; border-radius: 50%; transition: transform 0.3s; }
        #darkmode:checked + .toggle-label .slider { transform: translateX(27px); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #ffebee; border-radius: 10px; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 30px; transition: margin-left 0.3s; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #d32f2f; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: #6b7280; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; display: flex; align-items: center; gap: 15px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(211,47,47,0.1); }
        .stat-icon { width: 50px; height: 50px; background: #ffebee; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #d32f2f; }
        .stat-details h3 { font-size: 1.6rem; font-weight: 700; color: #d32f2f; margin-bottom: 5px; }
        .stat-details p { font-size: 0.8rem; color: #6b7280; }
        
        .dept-stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 30px; }
        .dept-stat-card { background: white; border-radius: 16px; padding: 15px; text-align: center; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .dept-stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(211,47,47,0.1); }
        .dept-stat-card h4 { font-size: 0.85rem; color: #6b7280; margin-bottom: 8px; }
        .dept-stat-card .number { font-size: 1.5rem; font-weight: 700; color: #d32f2f; }
        
        .filter-bar { background: white; border-radius: 16px; padding: 20px; margin-bottom: 30px; border: 1px solid #ffcdd2; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .filter-select { padding: 10px 16px; border-radius: 40px; border: 1px solid #ffcdd2; background: #f8f9fa; font-size: 0.85rem; cursor: pointer; }
        .filter-input { padding: 10px 16px; border-radius: 40px; border: 1px solid #ffcdd2; background: #f8f9fa; font-size: 0.85rem; flex: 2; min-width: 200px; }
        .filter-btn { background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .filter-btn:hover { background: #b71c1c; transform: translateY(-2px); }
        .clear-btn { background: #fef2f2; color: #6b7280; border: 1px solid #ffcdd2; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; }
        .clear-btn:hover { background: #fee2e2; }
        .add-thesis-btn { background: #d32f2f; color: white; border: none; padding: 10px 24px; border-radius: 40px; cursor: pointer; font-weight: 500; margin-left: auto; display: flex; align-items: center; gap: 8px; }
        .add-thesis-btn:hover { background: #b71c1c; transform: translateY(-2px); }
        
        .dept-tabs { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; border-bottom: 2px solid #ffcdd2; padding-bottom: 10px; }
        .dept-tab { padding: 12px 24px; background: none; border: none; font-weight: 600; font-size: 0.9rem; color: #6b7280; cursor: pointer; transition: all 0.3s; border-radius: 30px; }
        .dept-tab:hover { background: #fee2e2; color: #d32f2f; }
        .dept-tab.active { background: #d32f2f; color: white; }
        .dept-count-badge { background: #ffcdd2; color: #d32f2f; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; margin-left: 8px; }
        .dept-tab.active .dept-count-badge { background: rgba(255,255,255,0.3); color: white; }
        
        .dept-content { display: none; }
        .dept-content.active { display: block; }
        
        .dept-stats { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .dept-stats-card { background: white; border-radius: 12px; padding: 15px 20px; border: 1px solid #ffcdd2; }
        .dept-stats-number { font-size: 1.5rem; font-weight: 700; color: #d32f2f; }
        .dept-stats-label { font-size: 0.7rem; color: #6b7280; }
        
        .theses-table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; border: 1px solid #ffcdd2; }
        .theses-table th { background: #fef2f2; text-align: left; padding: 14px 16px; color: #d32f2f; font-weight: 600; font-size: 0.8rem; border-bottom: 1px solid #ffcdd2; }
        .theses-table td { padding: 14px 16px; border-bottom: 1px solid #ffebee; font-size: 0.85rem; vertical-align: middle; }
        .theses-table tr:last-child td { border-bottom: none; }
        .theses-table tr:hover td { background: #ffebee; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.pending { background: #fff3e0; color: #ed6c02; }
        .status-badge.archived { background: #e3f2fd; color: #0288d1; }
        
        .file-link { color: #d32f2f; text-decoration: none; font-size: 0.8rem; }
        .file-link:hover { text-decoration: underline; }
        
        .action-buttons { display: flex; gap: 8px; }
        .action-btn { background: none; border: none; cursor: pointer; font-size: 1rem; padding: 6px; border-radius: 8px; transition: all 0.2s; }
        .action-btn.edit { color: #3b82f6; }
        .action-btn.edit:hover { background: #eff6ff; transform: scale(1.05); }
        .action-btn.update-status { color: #f59e0b; }
        .action-btn.update-status:hover { background: #fffbeb; transform: scale(1.05); }
        .action-btn.delete { color: #ef4444; }
        .action-btn.delete:hover { background: #fef2f2; transform: scale(1.05); }
        
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 16px; border: 1px solid #ffcdd2; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #d32f2f; opacity: 0.5; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1100; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 550px; max-width: 90%; animation: slideUp 0.3s; }
        .modal-header { padding: 20px; border-bottom: 1px solid #ffcdd2; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; color: #d32f2f; }
        .close-modal { font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .close-modal:hover { color: #d32f2f; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.85rem; color: #1f2937; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #ffcdd2; border-radius: 10px; font-size: 0.85rem; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #d32f2f; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .modal-footer { padding: 20px; border-top: 1px solid #ffcdd2; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { background: #fef2f2; color: #6b7280; border: none; padding: 8px 22px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-cancel:hover { background: #fee2e2; }
        .btn-save { background: #d32f2f; color: white; border: none; padding: 8px 22px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-save:hover { background: #b71c1c; }
        
        .alert { padding: 12px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert i { font-size: 1.2rem; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        body.dark-mode { background: #0f172a; }
        body.dark-mode .top-nav { background: #1e293b; border-bottom-color: #334155; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #334155; border-color: #475569; }
        body.dark-mode .search-area input { color: white; }
        body.dark-mode .profile-name { color: #e5e7eb; }
        body.dark-mode .stat-card, body.dark-mode .dept-stat-card, body.dark-mode .filter-bar, body.dark-mode .dept-stats-card { background: #1e293b; border-color: #334155; }
        body.dark-mode .stat-details h3 { color: #fecaca; }
        body.dark-mode .theses-table { background: #1e293b; border-color: #334155; }
        body.dark-mode .theses-table th { background: #334155; color: #fecaca; border-bottom-color: #475569; }
        body.dark-mode .theses-table td { color: #e5e7eb; border-bottom-color: #334155; }
        body.dark-mode .theses-table tr:hover td { background: #334155; }
        body.dark-mode .profile-dropdown { background: #1e293b; border-color: #334155; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .profile-dropdown a:hover { background: #334155; }
        body.dark-mode .filter-select, body.dark-mode .filter-input { background: #334155; border-color: #475569; color: white; }
        body.dark-mode .clear-btn { background: #334155; color: #e5e7eb; border-color: #475569; }
        body.dark-mode .dept-tab { color: #94a3b8; }
        body.dark-mode .dept-tab:hover { background: #334155; color: #fecaca; }
        body.dark-mode .dept-tab.active { background: #d32f2f; color: white; }
        body.dark-mode .modal-content { background: #1e293b; }
        body.dark-mode .modal-header { border-bottom-color: #334155; }
        body.dark-mode .modal-header h3 { color: #fecaca; }
        body.dark-mode .form-group label { color: #e5e7eb; }
        body.dark-mode .form-group input, body.dark-mode .form-group select, body.dark-mode .form-group textarea { background: #334155; border-color: #475569; color: white; }
        body.dark-mode .btn-cancel { background: #334155; color: #e5e7eb; }
        body.dark-mode .empty-state { background: #1e293b; border-color: #334155; color: #94a3b8; }
        
        @media (max-width: 768px) {
            .top-nav { padding: 0 16px; }
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .dept-stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-input, .filter-select, .filter-btn, .clear-btn, .add-thesis-btn { width: 100%; margin-left: 0; }
            .search-area { display: none; }
            .profile-name { display: none; }
            .form-row { grid-template-columns: 1fr; }
            .dept-tabs { flex-wrap: wrap; }
            .theses-table th, .theses-table td { display: block; width: 100%; }
            .theses-table td { padding: 10px 12px; }
            .action-buttons { justify-content: flex-start; }
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .dept-stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="topSearchInput" placeholder="Search theses..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-icon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="#"><i class="fas fa-cog"></i> Settings</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>
    
    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="admin-label">ADMINISTRATOR</div></div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="theses.php" class="nav-item active"><i class="fas fa-file-alt"></i><span>Theses</span></a>
            <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header"><i class="fas fa-chalkboard-user"></i><span>Quick Access</span></div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank"><i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i><span><?= $dashboard['name'] ?> Dashboard</span><i class="fas fa-external-link-alt link-icon"></i></a>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> Theses Management</h1>
            <p>Manage all thesis submissions - organized by department</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-details"><h3><?= $total_theses ?></h3><p>Total Theses</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-details"><h3><?= $pending_theses ?></h3><p>Active</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-details"><h3><?= $approved_theses ?></h3><p>Approved</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= $archived_theses ?></h3><p>Archived</p></div></div>
        </div>
        
        <!-- Department Statistics - 5 small boxes -->
        <div class="dept-stats-grid">
            <div class="dept-stat-card"><h4>BS Information Technology</h4><div class="number"><?= $dept_stats['BSIT'] ?? 0 ?></div></div>
            <div class="dept-stat-card"><h4>BS Business Administration</h4><div class="number"><?= $dept_stats['BSBA'] ?? 0 ?></div></div>
            <div class="dept-stat-card"><h4>BS Criminology</h4><div class="number"><?= $dept_stats['BSCRIM'] ?? 0 ?></div></div>
            <div class="dept-stat-card"><h4>BS Education</h4><div class="number"><?= $dept_stats['BSED'] ?? 0 ?></div></div>
            <div class="dept-stat-card"><h4>BS Hospitality Management</h4><div class="number"><?= $dept_stats['BSHTM'] ?? 0 ?></div></div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-input" placeholder="Search by title, author, year..." value="<?= htmlspecialchars($search) ?>">
            <select id="archiveFilter" class="filter-select">
                <option value="">All Status</option>
                <option value="0" <?= $archive_filter == '0' ? 'selected' : '' ?>>Active</option>
                <option value="1" <?= $archive_filter == '1' ? 'selected' : '' ?>>Archived</option>
            </select>
            <button id="applyFilters" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
            <button id="clearFilters" class="clear-btn"><i class="fas fa-times"></i> Clear</button>
            <button id="addThesisBtn" class="add-thesis-btn"><i class="fas fa-plus"></i> Add Thesis</button>
        </div>
        
        <!-- Department Tabs -->
        <div class="dept-tabs" id="deptTabs">
            <?php foreach ($theses_by_dept as $dept_code => $dept_data): ?>
                <button class="dept-tab" data-dept="<?= $dept_code ?>">
                    <?php 
                        $icon = '';
                        if ($dept_code == 'BSIT') $icon = 'fa-laptop-code';
                        elseif ($dept_code == 'BSBA') $icon = 'fa-chart-line';
                        elseif ($dept_code == 'BSCRIM') $icon = 'fa-gavel';
                        elseif ($dept_code == 'BSED') $icon = 'fa-chalkboard-user';
                        else $icon = 'fa-utensils';
                    ?>
                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($dept_data['name']) ?>
                    <span class="dept-count-badge"><?= $dept_data['count'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Department Content Sections -->
        <?php foreach ($theses_by_dept as $dept_code => $dept_data): ?>
        <div class="dept-content" data-dept-content="<?= $dept_code ?>">
            <!-- Department Stats -->
            <div class="dept-stats">
                <div class="dept-stats-card"><div class="dept-stats-number"><?= $dept_data['count'] ?></div><div class="dept-stats-label">Total <?= htmlspecialchars($dept_data['name']) ?> Theses</div></div>
                <?php 
                    $pending_count = 0;
                    $archived_count = 0;
                    foreach ($dept_data['theses'] as $t) {
                        if (strtolower($t['status'] ?? '') == 'pending') $pending_count++;
                        if (strtolower($t['status'] ?? '') == 'archived') $archived_count++;
                    }
                ?>
                <div class="dept-stats-card"><div class="dept-stats-number"><?= $pending_count ?></div><div class="dept-stats-label">Active</div></div>
                <div class="dept-stats-card"><div class="dept-stats-number"><?= $archived_count ?></div><div class="dept-stats-label">Archived</div></div>
            </div>
            
            <!-- Theses Table -->
            <?php if (empty($dept_data['theses'])): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>No theses found in <?= htmlspecialchars($dept_data['name']) ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr><th>#</th><th>Title</th><th>Author</th><th>Year</th><th>Status</th><th>Date Submitted</th><th>File</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($dept_data['theses'] as $thesis): ?>
                            <tr data-thesis-id="<?= $thesis['thesis_id'] ?>" data-title="<?= strtolower($thesis['title'] ?? '') ?>" data-author="<?= strtolower($thesis['author'] ?? '') ?>" data-year="<?= $thesis['year'] ?? '' ?>" data-status="<?= strtolower($thesis['status'] ?? '') ?>">
                                <td><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars(substr($thesis['title'] ?? '', 0, 80)) ?><?= strlen($thesis['title'] ?? '') > 80 ? '...' : '' ?></strong></td>
                                <td><?= htmlspecialchars($thesis['author'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($thesis['year'] ?? '') ?></td>
                                <td><span class="status-badge <?= strtolower($thesis['status'] ?? 'pending') ?>"><?= ucfirst($thesis['status'] ?? 'Active') ?></span></td>
                                <td><?= date('M d, Y', strtotime($thesis['created_at'] ?? 'now')) ?></td>
                                <td><?php if (!empty($thesis['file_path'])): ?><a href="/ArchivingThesis/<?= $thesis['file_path'] ?>" target="_blank" class="file-link"><i class="fas fa-file-pdf"></i> View PDF</a><?php else: ?><span class="file-link" style="color:#9ca3af;">No file</span><?php endif; ?></td>
                                <td class="action-buttons">
                                    <button class="action-btn edit" onclick="editThesis(<?= $thesis['thesis_id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button class="action-btn update-status" onclick="updateArchiveStatus(<?= $thesis['thesis_id'] ?>, <?= $thesis['is_archived'] ?? 0 ?>)"><i class="fas fa-exchange-alt"></i></button>
                                    <button class="action-btn delete" onclick="deleteThesis(<?= $thesis['thesis_id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </main>
    
    <!-- Add/Edit Thesis Modal -->
    <div id="thesisModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3 id="modalTitle">Add New Thesis</h3><span class="close-modal" onclick="closeModal()">&times;</span></div>
            <form id="thesisForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="thesis_id" name="thesis_id">
                    <input type="hidden" id="form_action" name="action" value="add">
                    <div class="form-group"><label>Thesis Title</label><input type="text" id="title" name="title" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Author (Student Name)</label><input type="text" id="author" name="author" placeholder="e.g., Juan Dela Cruz" required></div>
                        <div class="form-group"><label>Department</label>
                            <select id="department" name="department" required>
                                <option value="">Select Department</option>
                                <option value="BSIT">BS Information Technology</option>
                                <option value="BSBA">BS Business Administration</option>
                                <option value="BSCRIM">BS Criminology</option>
                                <option value="BSED">BS Education</option>
                                <option value="BSHTM">BS Hospitality Management</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Year</label><input type="text" id="year" name="year" placeholder="e.g., 2024"></div>
                        <div class="form-group"><label>Status</label>
                            <select id="is_archived" name="is_archived">
                                <option value="0">Active</option>
                                <option value="1">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group"><label>Abstract</label><textarea id="abstract" name="abstract" rows="4"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button><button type="submit" class="btn-save">Save Thesis</button></div>
            </form>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Update Thesis Status</h3><span class="close-modal" onclick="closeStatusModal()">&times;</span></div>
            <div class="modal-body">
                <input type="hidden" id="status_thesis_id">
                <div class="form-group"><label>Select New Status</label>
                    <select id="new_status" class="filter-select">
                        <option value="0">Active</option>
                        <option value="1">Archived</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn-cancel" onclick="closeStatusModal()">Cancel</button><button type="button" class="btn-save" onclick="saveStatusUpdate()">Update Status</button></div>
        </div>
    </div>
    
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInput = document.getElementById('searchInput');
        const topSearchInput = document.getElementById('topSearchInput');
        const archiveFilter = document.getElementById('archiveFilter');
        const applyFilters = document.getElementById('applyFilters');
        const clearFilters = document.getElementById('clearFilters');
        const addThesisBtn = document.getElementById('addThesisBtn');
        const thesisModal = document.getElementById('thesisModal');
        const statusModal = document.getElementById('statusModal');
        const modalTitle = document.getElementById('modalTitle');
        const thesisForm = document.getElementById('thesisForm');
        const formAction = document.getElementById('form_action');
        
        let currentDeptTab = null;
        
        // Department Tab Functionality
        function initDeptTabs() {
            const tabs = document.querySelectorAll('.dept-tab');
            const contents = document.querySelectorAll('.dept-content');
            
            if (tabs.length > 0) {
                if (!currentDeptTab) {
                    tabs[0].classList.add('active');
                    const firstDeptCode = tabs[0].getAttribute('data-dept');
                    document.querySelector(`.dept-content[data-dept-content="${firstDeptCode}"]`).classList.add('active');
                    currentDeptTab = firstDeptCode;
                }
                
                tabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        const deptCode = this.getAttribute('data-dept');
                        tabs.forEach(t => t.classList.remove('active'));
                        contents.forEach(c => c.classList.remove('active'));
                        this.classList.add('active');
                        document.querySelector(`.dept-content[data-dept-content="${deptCode}"]`).classList.add('active');
                        currentDeptTab = deptCode;
                    });
                });
            }
        }
        
        // Filter Function
        function applyFilter() {
            const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const archive = archiveFilter ? archiveFilter.value : '';
            
            const activeTab = document.querySelector('.dept-tab.active');
            if (!activeTab) return;
            
            const deptCode = activeTab.getAttribute('data-dept');
            const deptContent = document.querySelector(`.dept-content[data-dept-content="${deptCode}"]`);
            const rows = deptContent.querySelectorAll('tbody tr');
            
            let visibleCount = 0;
            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const author = row.getAttribute('data-author') || '';
                const year = row.getAttribute('data-year') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                
                let matchesSearch = true;
                let matchesArchive = true;
                
                if (searchTerm) {
                    matchesSearch = title.includes(searchTerm) || author.includes(searchTerm) || year.includes(searchTerm);
                }
                if (archive !== '') {
                    const isArchived = (rowStatus === 'archived') ? '1' : '0';
                    matchesArchive = isArchived === archive;
                }
                
                if (matchesSearch && matchesArchive) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            const countBadge = activeTab.querySelector('.dept-count-badge');
            if (countBadge) {
                const totalRows = rows.length;
                if (searchTerm || archive) {
                    countBadge.textContent = visibleCount;
                } else {
                    countBadge.textContent = totalRows;
                }
            }
        }
        
        function clearAllFilters() {
            if (searchInput) searchInput.value = '';
            if (topSearchInput) topSearchInput.value = '';
            if (archiveFilter) archiveFilter.value = '';
            applyFilter();
        }
        
        if (topSearchInput && searchInput) {
            topSearchInput.value = searchInput.value;
            topSearchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchInput.value = this.value;
                    applyFilter();
                }
            });
            topSearchInput.addEventListener('input', function() {
                searchInput.value = this.value;
                applyFilter();
            });
        }
        
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') applyFilter(); });
            searchInput.addEventListener('input', function() { applyFilter(); });
        }
        
        if (archiveFilter) {
            archiveFilter.addEventListener('change', applyFilter);
        }
        
        if (applyFilters) applyFilters.addEventListener('click', applyFilter);
        if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);
        
        function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleSidebar(e) { e.stopPropagation(); if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); }
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                if (thesisModal.classList.contains('show')) closeModal();
                if (statusModal.classList.contains('show')) closeStatusModal();
            }
        });
        
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
        
        if (profileWrapper && profileDropdown) {
            profileWrapper.addEventListener('click', function(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
            document.addEventListener('click', function(e) { if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); });
        }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); }
                    else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); }
                });
            }
        }
        
        function openModal() { thesisModal.classList.add('show'); }
        function closeModal() { thesisModal.classList.remove('show'); thesisForm.reset(); document.getElementById('thesis_id').value = ''; formAction.value = 'add'; modalTitle.textContent = 'Add New Thesis'; }
        function openStatusModal(thesisId, currentStatus) { document.getElementById('status_thesis_id').value = thesisId; document.getElementById('new_status').value = currentStatus; statusModal.classList.add('show'); }
        function closeStatusModal() { statusModal.classList.remove('show'); }
        function saveStatusUpdate() { const thesisId = document.getElementById('status_thesis_id').value; const newStatus = document.getElementById('new_status').value; window.location.href = 'theses.php?update_archive=' + thesisId + '&is_archived=' + newStatus; }
        
        function editThesis(thesisId) {
            fetch('theses.php?get_thesis=' + thesisId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('thesis_id').value = data.thesis.thesis_id;
                        document.getElementById('title').value = data.thesis.title || '';
                        document.getElementById('author').value = data.thesis.author || '';
                        document.getElementById('department').value = data.thesis.department || '';
                        document.getElementById('year').value = data.thesis.year || '';
                        document.getElementById('abstract').value = data.thesis.abstract || '';
                        document.getElementById('is_archived').value = data.thesis.is_archived || 0;
                        formAction.value = 'edit';
                        modalTitle.textContent = 'Edit Thesis';
                        openModal();
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function updateArchiveStatus(thesisId, currentStatus) { 
            const newStatus = currentStatus == 0 ? 1 : 0;
            if (confirm('Change thesis status to ' + (newStatus == 1 ? 'Archived' : 'Active') + '?')) {
                window.location.href = 'theses.php?update_archive=' + thesisId + '&is_archived=' + newStatus;
            }
        }
        
        function deleteThesis(thesisId) { if (confirm('Are you sure you want to delete this thesis? This action cannot be undone.')) { window.location.href = 'theses.php?delete=' + thesisId; } }
        
        if (addThesisBtn) {
            addThesisBtn.addEventListener('click', function() { thesisForm.reset(); document.getElementById('thesis_id').value = ''; formAction.value = 'add'; modalTitle.textContent = 'Add New Thesis'; openModal(); });
        }
        
        window.addEventListener('click', function(e) { if (e.target === thesisModal) closeModal(); if (e.target === statusModal) closeStatusModal(); });
        
        initDarkMode();
        initDeptTabs();
        console.log('Theses Management Page - Using is_archived column');
    </script>
</body>
</html>