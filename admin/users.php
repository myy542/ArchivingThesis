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

// AUDIT LOGS FUNCTION
function logAdminAction($conn, $user_id, $action, $table, $record_id, $description) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if ($ip_address == '::1') $ip_address = '127.0.0.1';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// DASHBOARDS
$dashboards = [
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php', 'role_id' => 1],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php', 'role_id' => 2],
    3 => ['name' => 'Faculty', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php', 'role_id' => 3],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php', 'role_id' => 4],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php', 'role_id' => 5],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php', 'role_id' => 6]
];

// GET SEARCH
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// BUILD QUERY
$query = "SELECT * FROM user_table WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($role_filter)) {
    $query .= " AND role_id = ?";
    $params[] = $role_filter;
    $types .= "i";
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY user_id DESC";

// EXECUTE QUERY
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $role_name = '';
    $role_color = '#6b7280';
    $role_icon = 'fa-user';
    foreach ($dashboards as $id => $dash) {
        if ($dash['role_id'] == $row['role_id']) {
            $role_name = $dash['name'];
            $role_color = $dash['color'];
            $role_icon = $dash['icon'];
            break;
        }
    }
    
    $users[] = [
        'id' => $row['user_id'],
        'username' => $row['username'],
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'email' => $row['email'],
        'role' => $role_name,
        'role_id' => $row['role_id'],
        'role_color' => $role_color,
        'role_icon' => $role_icon,
        'status' => $row['status'],
        'joined' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : 'Recently'
    ];
}
$stmt->close();

// STATISTICS
$total_users = $conn->query("SELECT COUNT(*) as c FROM user_table")->fetch_assoc()['c'];
$active_users = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE status = 'Active'")->fetch_assoc()['c'];
$inactive_users = $total_users - $active_users;

// USER MANAGEMENT HANDLERS
// Update Status
if (isset($_POST['update_status']) && isset($_POST['user_id'])) {
    $target_user_id = intval($_POST['user_id']);
    $new_status = $_POST['status'] == 'Active' ? 'Active' : 'Inactive';
    
    // Get user details
    $user_details = $conn->query("SELECT first_name, last_name FROM user_table WHERE user_id = $target_user_id")->fetch_assoc();
    $user_name = $user_details['first_name'] . ' ' . $user_details['last_name'];
    
    $update_stmt = $conn->prepare("UPDATE user_table SET status = ? WHERE user_id = ?");
    $update_stmt->bind_param("si", $new_status, $target_user_id);
    if ($update_stmt->execute()) {
        logAdminAction($conn, $user_id, "User status changed", "user_table", $target_user_id, "Changed status of $user_name to $new_status");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    $update_stmt->close();
    exit;
}

// Delete User
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $target_user_id = intval($_POST['user_id']);
    if ($target_user_id != $user_id) {
        $user_details = $conn->query("SELECT first_name, last_name FROM user_table WHERE user_id = $target_user_id")->fetch_assoc();
        $user_name = $user_details['first_name'] . ' ' . $user_details['last_name'];
        
        $delete_stmt = $conn->prepare("DELETE FROM user_table WHERE user_id = ?");
        $delete_stmt->bind_param("i", $target_user_id);
        if ($delete_stmt->execute()) {
            logAdminAction($conn, $user_id, "User deleted", "user_table", $target_user_id, "Deleted user: $user_name");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        $delete_stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Cannot delete own account']);
    }
    exit;
}

// Add User
if (isset($_POST['add_user'])) {
    $new_first_name = trim($_POST['first_name']);
    $new_last_name = trim($_POST['last_name']);
    $new_email = trim($_POST['email']);
    $new_username = trim($_POST['username']);
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $new_role_id = intval($_POST['role_id']);
    
    $check = $conn->prepare("SELECT user_id FROM user_table WHERE email = ? OR username = ?");
    $check->bind_param("ss", $new_email, $new_username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email or username already exists']);
        exit;
    }
    
    $insert = $conn->prepare("INSERT INTO user_table (username, password, email, first_name, last_name, role_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())");
    $insert->bind_param("sssssi", $new_username, $new_password, $new_email, $new_first_name, $new_last_name, $new_role_id);
    if ($insert->execute()) {
        logAdminAction($conn, $user_id, "User added", "user_table", $insert->insert_id, "Added user: $new_first_name $new_last_name");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    $insert->close();
    exit;
}

// Edit User
if (isset($_POST['edit_user']) && isset($_POST['user_id'])) {
    $target_user_id = intval($_POST['user_id']);
    $edit_first_name = trim($_POST['first_name']);
    $edit_last_name = trim($_POST['last_name']);
    $edit_email = trim($_POST['email']);
    $edit_role_id = intval($_POST['role_id']);
    
    // Get user details for log
    $old_details = $conn->query("SELECT first_name, last_name FROM user_table WHERE user_id = $target_user_id")->fetch_assoc();
    $old_name = $old_details['first_name'] . ' ' . $old_details['last_name'];
    
    $update = $conn->prepare("UPDATE user_table SET first_name = ?, last_name = ?, email = ?, role_id = ? WHERE user_id = ?");
    $update->bind_param("sssii", $edit_first_name, $edit_last_name, $edit_email, $edit_role_id, $target_user_id);
    if ($update->execute()) {
        logAdminAction($conn, $user_id, "User updated", "user_table", $target_user_id, "Updated user: $old_name");
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    $update->close();
    exit;
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows) {
    $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $n->bind_param("i", $user_id);
    $n->execute();
    $notificationCount = $n->get_result()->fetch_assoc()['c'];
    $n->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; }
        
        /* Top Navigation */
        .top-nav { position: fixed; top: 0; right: 0; left: 280px; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #ffcdd2; }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: none; flex-direction: column; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; padding: 12px; }
        .hamburger span { display: block; width: 20px; height: 2px; background: #dc2626; border-radius: 2px; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #d32f2f; }
        .logo span { color: #d32f2f; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; border: 1px solid #ffcdd2; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 220px; }
        .nav-right { display: flex; align-items: center; gap: 20px; }
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
        
        /* Sidebar */
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: linear-gradient(180deg, #b71c1c 0%, #d32f2f 100%); display: flex; flex-direction: column; z-index: 100; transition: left 0.3s; box-shadow: 4px 0 15px rgba(0,0,0,0.1); }
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
        
        /* Main Content */
        .main-content { margin-left: 280px; margin-top: 70px; padding: 30px; transition: margin-left 0.3s; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #d32f2f; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: #6b7280; margin-top: 5px; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 20px; padding: 22px 20px; display: flex; align-items: center; gap: 18px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(211,47,47,0.1); }
        .stat-icon { width: 55px; height: 55px; background: #ffebee; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #d32f2f; }
        .stat-details h3 { font-size: 1.8rem; font-weight: 700; color: #d32f2f; margin-bottom: 5px; }
        .stat-details p { font-size: 0.8rem; color: #6b7280; }
        
        /* Filter Bar */
        .filter-bar { background: white; border-radius: 20px; padding: 20px; margin-bottom: 25px; border: 1px solid #ffcdd2; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .filter-select { padding: 10px 16px; border-radius: 40px; border: 1px solid #ffcdd2; background: #f8f9fa; font-size: 0.85rem; cursor: pointer; }
        .filter-btn { background: #d32f2f; color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .filter-btn:hover { background: #b71c1c; transform: translateY(-2px); }
        .clear-btn { background: #fef2f2; color: #6b7280; border: 1px solid #ffcdd2; padding: 10px 20px; border-radius: 40px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .clear-btn:hover { background: #fee2e2; }
        
        /* Users Table */
        .users-section { background: white; border-radius: 20px; padding: 25px; border: 1px solid #ffcdd2; }
        .table-responsive { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; }
        .users-table th { text-align: left; padding: 14px 12px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #ffcdd2; }
        .users-table td { padding: 14px 12px; border-bottom: 1px solid #ffebee; font-size: 0.85rem; vertical-align: middle; }
        .users-table tr:hover td { background: #ffebee; }
        .user-name-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar-small { width: 34px; height: 34px; background: linear-gradient(135deg, #d32f2f, #b71c1c); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.8rem; }
        .role-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 500; }
        .status-badge { display: inline-block; padding: 5px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 500; }
        .status-badge.active { background: #e8f5e9; color: #2e7d32; }
        .status-badge.inactive { background: #ffebee; color: #c62828; }
        .action-btn { background: none; border: none; cursor: pointer; font-size: 1rem; padding: 6px; margin: 0 3px; border-radius: 8px; transition: all 0.2s; }
        .action-btn.edit { color: #3b82f6; }
        .action-btn.edit:hover { background: #eff6ff; transform: scale(1.05); }
        .action-btn.toggle-status { color: #f59e0b; }
        .action-btn.toggle-status:hover { background: #fffbeb; transform: scale(1.05); }
        .action-btn.delete { color: #ef4444; }
        .action-btn.delete:hover { background: #fef2f2; transform: scale(1.05); }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 500px; max-width: 90%; animation: slideUp 0.3s; }
        .modal-header { padding: 20px; border-bottom: 1px solid #ffcdd2; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 1.2rem; font-weight: 600; color: #d32f2f; }
        .close-modal { font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .close-modal:hover { color: #d32f2f; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.85rem; color: #1f2937; }
        .form-group input, .form-group select { width: 100%; padding: 10px 14px; border: 1px solid #ffcdd2; border-radius: 10px; font-size: 0.85rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .modal-footer { padding: 20px; border-top: 1px solid #ffcdd2; display: flex; justify-content: flex-end; gap: 12px; }
        .btn-cancel { background: #fef2f2; color: #6b7280; border: none; padding: 8px 22px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-cancel:hover { background: #fee2e2; }
        .btn-save { background: #d32f2f; color: white; border: none; padding: 8px 22px; border-radius: 8px; cursor: pointer; font-weight: 500; }
        .btn-save:hover { background: #b71c1c; }
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .empty-state { text-align: center; padding: 60px; color: #6b7280; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #d32f2f; }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .top-nav { left: 0; padding: 0 16px; }
            .hamburger { display: flex; }
            .sidebar { left: -280px; }
            .sidebar.open { left: 0; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-select, .filter-btn, .clear-btn { width: 100%; }
            .search-area { display: none; }
            .profile-name { display: none; }
            .form-row { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) { .main-content { padding: 16px; } .stat-card { padding: 16px; } .stat-icon { width: 45px; height: 45px; font-size: 1.2rem; } .stat-details h3 { font-size: 1.4rem; } }
        
        /* Dark Mode */
        body.dark-mode { background: #0f172a; }
        body.dark-mode .top-nav { background: #1e293b; border-bottom-color: #334155; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #334155; border-color: #475569; }
        body.dark-mode .search-area input { color: white; }
        body.dark-mode .profile-name { color: #e5e7eb; }
        body.dark-mode .stat-card, body.dark-mode .filter-bar, body.dark-mode .users-section { background: #1e293b; border-color: #334155; }
        body.dark-mode .stat-details h3 { color: #fecaca; }
        body.dark-mode .users-table td { color: #e5e7eb; border-bottom-color: #334155; }
        body.dark-mode .users-table th { color: #94a3b8; border-bottom-color: #334155; }
        body.dark-mode .users-table tr:hover td { background: #334155; }
        body.dark-mode .profile-dropdown, body.dark-mode .modal-content { background: #1e293b; border-color: #334155; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .profile-dropdown a:hover { background: #334155; }
        body.dark-mode .filter-select { background: #334155; border-color: #475569; color: white; }
        body.dark-mode .clear-btn { background: #334155; color: #e5e7eb; border-color: #475569; }
        body.dark-mode .clear-btn:hover { background: #475569; }
        body.dark-mode .form-group input, body.dark-mode .form-group select { background: #334155; border-color: #475569; color: white; }
        body.dark-mode .form-group label { color: #e5e7eb; }
        body.dark-mode .btn-cancel { background: #334155; color: #e5e7eb; }
        body.dark-mode .btn-cancel:hover { background: #475569; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search users..."></div>
        </div>
        <div class="nav-right">
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
            <a href="users.php" class="nav-item active"><i class="fas fa-users"></i><span>Users</span></a>
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
            <h1><i class="fas fa-users"></i> User Management</h1>
            <p>Manage all system users - view, edit, activate/deactivate, and delete user accounts</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-details"><h3><?= number_format($total_users) ?></h3><p>Total Users</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-details"><h3><?= number_format($active_users) ?></h3><p>Active Users</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-ban"></i></div><div class="stat-details"><h3><?= number_format($inactive_users) ?></h3><p>Inactive Users</p></div></div>
        </div>
        
        <div class="filter-bar">
            <input type="text" id="searchInputFilter" class="filter-select" placeholder="Search by name, email, username..." value="<?= htmlspecialchars($search) ?>" style="flex: 2;">
            <select id="roleFilter" class="filter-select">
                <option value="">All Roles</option>
                <?php foreach ($dashboards as $id => $dash): ?>
                    <option value="<?= $id ?>" <?= $role_filter == $id ? 'selected' : '' ?>><?= $dash['name'] ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" class="filter-select">
                <option value="">All Status</option>
                <option value="Active" <?= $status_filter == 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $status_filter == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <button id="applyFilters" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
            <button id="clearFilters" class="clear-btn"><i class="fas fa-times"></i> Clear</button>
            <button id="addUserBtn" class="filter-btn" style="background: #28a745;"><i class="fas fa-plus"></i> Add User</button>
        </div>
        
        <div class="users-section">
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        60
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </thead>
                    <tbody id="usersTableBody">
                        <?php if (empty($users)): ?>
                        60<td colspan="8" class="empty-state"><i class="fas fa-user-slash"></i><p>No users found</p>60</td>60
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?= $user['id'] ?>">
                            <td>#<?= $user['id'] ?></td>
                            <td><div class="user-name-cell"><div class="user-avatar-small"><?= strtoupper(substr($user['name'], 0, 1)) ?></div><span><?= htmlspecialchars($user['name']) ?></span></div></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><span class="role-badge" style="background: <?= $user['role_color'] ?>20; color: <?= $user['role_color'] ?>"><i class="fas <?= $user['role_icon'] ?>"></i> <?= $user['role'] ?></span></td>
                            <td><span class="status-badge <?= strtolower($user['status']) ?>"><?= $user['status'] ?></span></td>
                            <td><?= $user['joined'] ?></td>
                            <td>
                                <button class="action-btn edit" onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['username']) ?>', <?= $user['role_id'] ?>)"><i class="fas fa-edit"></i></button>
                                <button class="action-btn toggle-status" data-id="<?= $user['id'] ?>" data-status="<?= $user['status'] ?>"><i class="fas <?= $user['status'] == 'Active' ? 'fa-ban' : 'fa-check-circle' ?>"></i></button>
                                <button class="action-btn delete" data-id="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['name']) ?>"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Add New User</h3><span class="close-modal">&times;</span></div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="form-row"><div class="form-group"><label>First Name</label><input type="text" name="first_name" required></div><div class="form-group"><label>Last Name</label><input type="text" name="last_name" required></div></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
                    <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Role</label><select name="role_id" required><?php foreach ($dashboards as $id => $dash): ?><option value="<?= $id ?>"><?= $dash['name'] ?></option><?php endforeach; ?></select></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn-cancel">Cancel</button><button class="btn-save" id="submitAddUser">Add User</button></div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Edit User</h3><span class="close-modal">&times;</span></div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="form-row"><div class="form-group"><label>First Name</label><input type="text" name="first_name" id="edit_first_name" required></div><div class="form-group"><label>Last Name</label><input type="text" name="last_name" id="edit_last_name" required></div></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" required></div>
                    <div class="form-group"><label>Username</label><input type="text" name="username" id="edit_username" required readonly style="background:#f0f0f0; cursor:not-allowed;"></div>
                    <div class="form-group"><label>Role</label><select name="role_id" id="edit_role_id" required><?php foreach ($dashboards as $id => $dash): ?><option value="<?= $id ?>"><?= $dash['name'] ?></option><?php endforeach; ?></select></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn-cancel">Cancel</button><button class="btn-save" id="submitEditUser">Save Changes</button></div>
        </div>
    </div>
    
    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInputFilter = document.getElementById('searchInputFilter');
        const roleFilter = document.getElementById('roleFilter');
        const statusFilter = document.getElementById('statusFilter');
        const applyFilters = document.getElementById('applyFilters');
        const clearFilters = document.getElementById('clearFilters');
        const addUserBtn = document.getElementById('addUserBtn');
        const addUserModal = document.getElementById('addUserModal');
        const editUserModal = document.getElementById('editUserModal');
        const submitAddUser = document.getElementById('submitAddUser');
        const submitEditUser = document.getElementById('submitEditUser');
        const closeModalBtns = document.querySelectorAll('.close-modal, .btn-cancel');
        
        function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', function(e) { e.stopPropagation(); sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { if (sidebar.classList.contains('open')) closeSidebar(); if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); closeAllModals(); } });
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
        
        if (profileWrapper && profileDropdown) {
            profileWrapper.addEventListener('click', function(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
            document.addEventListener('click', function(e) { if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); });
        }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
            if (darkModeToggle) darkModeToggle.addEventListener('change', function() { if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); } else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); } });
        }
        
        function applyFilter() {
            const search = searchInputFilter ? searchInputFilter.value.trim() : '';
            const role = roleFilter ? roleFilter.value : '';
            const status = statusFilter ? statusFilter.value : '';
            let url = window.location.pathname + '?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (role) url += 'role=' + encodeURIComponent(role) + '&';
            if (status) url += 'status=' + encodeURIComponent(status);
            window.location.href = url;
        }
        
        function clearAllFilters() { window.location.href = window.location.pathname; }
        
        if (applyFilters) applyFilters.addEventListener('click', applyFilter);
        if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);
        
        function openModal(modal) { if (modal) modal.classList.add('show'); }
        function closeModal(modal) { if (modal) modal.classList.remove('show'); }
        function closeAllModals() { closeModal(addUserModal); closeModal(editUserModal); }
        
        closeModalBtns.forEach(btn => { btn.addEventListener('click', closeAllModals); });
        window.addEventListener('click', function(e) { if (e.target.classList && e.target.classList.contains('modal')) closeAllModals(); });
        if (addUserBtn) addUserBtn.addEventListener('click', () => openModal(addUserModal));
        
        if (submitAddUser) {
            submitAddUser.addEventListener('click', function() {
                const form = document.getElementById('addUserForm');
                const formData = new FormData(form);
                formData.append('add_user', '1');
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) { alert('User added successfully!'); location.reload(); } else { alert('Error: ' + (data.message || 'Failed to add user')); } })
                    .catch(error => { console.error('Error:', error); alert('An error occurred'); });
            });
        }
        
        window.editUser = function(id, name, email, username, roleId) {
            const nameParts = name.split(' ');
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role_id').value = roleId;
            openModal(editUserModal);
        };
        
        if (submitEditUser) {
            submitEditUser.addEventListener('click', function() {
                const form = document.getElementById('editUserForm');
                const formData = new FormData(form);
                formData.append('edit_user', '1');
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) { alert('User updated successfully!'); location.reload(); } else { alert('Error: ' + (data.message || 'Failed to update user')); } })
                    .catch(error => { console.error('Error:', error); alert('An error occurred'); });
            });
        }
        
        document.querySelectorAll('.toggle-status').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const userId = this.dataset.id;
                const currentStatus = this.dataset.status;
                const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
                if (confirm(`Are you sure you want to ${newStatus === 'Active' ? 'activate' : 'deactivate'} this user?`)) {
                    const formData = new FormData();
                    formData.append('update_status', '1');
                    formData.append('user_id', userId);
                    formData.append('status', newStatus);
                    fetch(window.location.href, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => { if (data.success) location.reload(); else alert('Error: Failed to update status'); });
                }
            });
        });
        
        document.querySelectorAll('.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const userId = this.dataset.id;
                const userName = this.dataset.name;
                if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                    const formData = new FormData();
                    formData.append('delete_user', '1');
                    formData.append('user_id', userId);
                    fetch(window.location.href, { method: 'POST', body: formData })
                        .then(response => response.json())
                        .then(data => { if (data.success) location.reload(); else alert('Error: ' + (data.message || 'Failed to delete user')); });
                }
            });
        });
        
        initDarkMode();
    </script>
</body>
</html>