<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS A COORDINATOR
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// GET LOGGED-IN USER INFO FROM SESSION
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA FROM DATABASE
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
}

// Check if created_at column exists
$user_created = date('F Y');
$check_created_column = $conn->query("SHOW COLUMNS FROM user_table LIKE 'created_at'");
if ($check_created_column && $check_created_column->num_rows > 0) {
    $user_query_full = "SELECT created_at FROM user_table WHERE user_id = ?";
    $user_stmt_full = $conn->prepare($user_query_full);
    $user_stmt_full->bind_param("i", $user_id);
    $user_stmt_full->execute();
    $user_result_full = $user_stmt_full->get_result();
    if ($user_row = $user_result_full->fetch_assoc()) {
        $user_created = date('F Y', strtotime($user_row['created_at']));
    }
    $user_stmt_full->close();
}

// GET COORDINATOR DATA FROM DEPARTMENT_COORDINATOR TABLE
$department_name = "Research Department";
$department_code = "RD";
$position = "Research Coordinator";
$assigned_date = $user_created;

$coordinator_query = "
    SELECT dc.*, d.department_name, d.department_code
    FROM department_coordinator dc
    JOIN department_table d ON dc.department_id = d.department_id
    WHERE dc.user_id = ?
";
$coordinator_stmt = $conn->prepare($coordinator_query);
$coordinator_stmt->bind_param("i", $user_id);
$coordinator_stmt->execute();
$coordinator_result = $coordinator_stmt->get_result();
$coordinator_data = $coordinator_result->fetch_assoc();

if ($coordinator_data) {
    $department_name = $coordinator_data['department_name'] ?? $department_name;
    $department_code = $coordinator_data['department_code'] ?? $department_code;
    $position = $coordinator_data['position'] ?? $position;
    $assigned_date = isset($coordinator_data['assigned_date']) ? date('F Y', strtotime($coordinator_data['assigned_date'])) : $user_created;
}
$coordinator_stmt->close();

// ==================== NOTIFICATION SYSTEM ====================
// CREATE NOTIFICATIONS TABLE IF NOT EXISTS (no hardcoded data)
$check_notif_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check_notif_table || $check_notif_table->num_rows == 0) {
    $create_notif_table = "
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(50) DEFAULT 'info',
            link VARCHAR(255) NULL,
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (user_id),
            INDEX (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($create_notif_table);
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['count'];
}
$notif_stmt->close();

// GET NOTIFICATIONS LIST
$recentNotifications = [];
$notif_list_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();

// MARK NOTIFICATION AS READ (via AJAX)
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update_query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $notif_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL NOTIFICATIONS AS READ
if (isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}
// ============================================================

// CHECK IF THESES TABLE EXISTS
$theses_table_exists = false;
$check_theses = $conn->query("SHOW TABLES LIKE 'theses'");
if ($check_theses && $check_theses->num_rows > 0) {
    $theses_table_exists = true;
}

// GET THESIS DATA FROM DATABASE
$allSubmissions = [
    'pending_coordinator' => [],
    'forwarded_to_dean' => [],
    'rejected' => []
];

if ($theses_table_exists) {
    $pending_query = "SELECT * FROM theses WHERE status = 'Forwarded to Coordinator' OR status = 'Pending' ORDER BY created_at DESC";
    $pending_result = $conn->query($pending_query);
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $allSubmissions['pending_coordinator'][] = [
                'title' => $row['title'],
                'author' => $row['student_name'] ?? 'Unknown',
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
    
    $forwarded_query = "SELECT * FROM theses WHERE status = 'Forwarded to Dean' OR status = 'Dean Review' ORDER BY created_at DESC";
    $forwarded_result = $conn->query($forwarded_query);
    if ($forwarded_result && $forwarded_result->num_rows > 0) {
        while ($row = $forwarded_result->fetch_assoc()) {
            $allSubmissions['forwarded_to_dean'][] = [
                'title' => $row['title'],
                'author' => $row['student_name'] ?? 'Unknown',
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
    
    $rejected_query = "SELECT * FROM theses WHERE status = 'Rejected' ORDER BY created_at DESC";
    $rejected_result = $conn->query($rejected_query);
    if ($rejected_result && $rejected_result->num_rows > 0) {
        while ($row = $rejected_result->fetch_assoc()) {
            $allSubmissions['rejected'][] = [
                'title' => $row['title'],
                'author' => $row['student_name'] ?? 'Unknown',
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
}

// Statistics from actual database
$stats = [
    'forwarded' => count($allSubmissions['forwarded_to_dean']),
    'rejected'  => count($allSubmissions['rejected']),
    'pending'   => count($allSubmissions['pending_coordinator'])
];

$pendingTheses = $allSubmissions['pending_coordinator'];

// Flatten all theses
$allThesesWithStatus = [];
foreach ($allSubmissions as $status => $theses) {
    foreach ($theses as $thesis) {
        $thesis['status'] = $status;
        $allThesesWithStatus[] = $thesis;
    }
}

// Monthly submissions data
$monthly_data = array_fill(0, 12, 0);
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

if ($theses_table_exists) {
    $monthly_query = "SELECT MONTH(created_at) as month, COUNT(*) as count FROM theses WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)";
    $monthly_result = $conn->query($monthly_query);
    if ($monthly_result && $monthly_result->num_rows > 0) {
        while ($row = $monthly_result->fetch_assoc()) {
            $monthly_data[$row['month'] - 1] = $row['count'];
        }
    }
}

// CHECK IF MAY DATA PARA SA GRAPH - KUNG WALA, MAG-ADD OG SAMPLE DATA PARA MAKITA ANG GRAPH
$hasMonthlyData = false;
foreach ($monthly_data as $val) {
    if ($val > 0) {
        $hasMonthlyData = true;
        break;
    }
}

// SAMPLE DATA PARA SA GRAPH (para makita ang graph bisan wala pay actual data)
$sample_monthly_data = [3, 4, 5, 6, 8, 10, 12, 9, 7, 5, 4, 2];
$sample_stats = [
    'forwarded' => 5,
    'rejected'  => 2,
    'pending'   => 8
];

// GAMITON ANG SAMPLE DATA KUNG WALAY ACTUAL DATA
if (!$hasMonthlyData) {
    $monthly_data = $sample_monthly_data;
}

if ($stats['pending'] == 0 && $stats['forwarded'] == 0 && $stats['rejected'] == 0) {
    $stats = $sample_stats;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Research Coordinator Dashboard | Thesis Management System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }
        .top-nav { position: fixed; top: 0; right: 0; left: 280px; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #fee2e2; }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: none; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 200px; }
        .nav-right { display: flex; align-items: center; gap: 20px; position: relative; }
        .notification-container { position: relative; }
        .notification-icon { position: relative; cursor: pointer; width: 40px; height: 40px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .notification-icon:hover { background: #fee2e2; }
        .notification-icon i { font-size: 1.2rem; color: #dc2626; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 600; min-width: 18px; height: 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
        .notification-dropdown { position: absolute; top: 55px; right: 0; width: 360px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); display: none; overflow: hidden; z-index: 100; border: 1px solid #fee2e2; }
        .notification-dropdown.show { display: block; animation: fadeSlideDown 0.2s ease; }
        .notification-header { padding: 16px 20px; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; }
        .notification-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; }
        .mark-all-read { font-size: 0.7rem; color: #dc2626; cursor: pointer; }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { display: flex; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #fef2f2; cursor: pointer; transition: background 0.2s; }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-item.empty { justify-content: center; color: #9ca3af; cursor: default; }
        .notif-icon { width: 36px; height: 36px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.8rem; color: #1f2937; margin-bottom: 4px; }
        .notif-time { font-size: 0.65rem; color: #9ca3af; }
        .notification-footer { padding: 12px 20px; border-top: 1px solid #fee2e2; text-align: center; }
        .notification-footer a { color: #dc2626; text-decoration: none; font-size: 0.8rem; }
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #5b3b3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 100; border: 1px solid #fee2e2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex; flex-direction: column; z-index: 100; transition: transform 0.3s ease; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 6px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; font-weight: 500; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 12px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        .main-content { margin-left: 280px; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        .welcome-banner { background: linear-gradient(135deg, #851313, #900c0c); border-radius: 28px; padding: 32px 36px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; color: white; }
        .welcome-info h1 { font-size: 1.6rem; font-weight: 700; margin-bottom: 8px; }
        .welcome-info p { opacity: 0.8; font-size: 0.85rem; }
        .coordinator-info { text-align: right; }
        .coordinator-name { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
        .coordinator-position { font-size: 0.8rem; opacity: 0.9; }
        .coordinator-since { font-size: 0.7rem; opacity: 0.7; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; border: 1px solid #fee2e2; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .stat-icon { width: 60px; height: 60px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #fef2f2; color: #dc2626; }
        .stat-content h3 { font-size: 2rem; font-weight: 700; color: #ba0202; }
        .stat-content p { font-size: 0.85rem; color: #6b7280; margin-top: 4px; }
        .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
        .chart-card { background: white; border-radius: 24px; padding: 24px; border: 1px solid #fee2e2; transition: all 0.2s; }
        .chart-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .chart-card h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .chart-container { height: 250px; position: relative; margin-bottom: 20px; }
        .status-labels { display: flex; justify-content: center; gap: 24px; margin-top: 16px; flex-wrap: wrap; }
        .status-label-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #6b7280; }
        .status-color { width: 12px; height: 12px; border-radius: 50%; }
        .status-color.pending { background: #f59e0b; }
        .status-color.forwarded { background: #3b82f6; }
        .status-color.rejected { background: #ef4444; }
        .monthly-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #fee2e2; }
        .month-item { text-align: center; font-size: 0.7rem; }
        .month-name { color: #6b7280; display: block; margin-bottom: 4px; }
        .month-count { font-weight: 600; color: #dc2626; font-size: 0.9rem; }
        .theses-card, .submissions-card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #fee2e2; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .card-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; display: flex; align-items: center; gap: 8px; }
        .view-all { color: #dc2626; text-decoration: none; font-size: 0.8rem; font-weight: 500; }
        .view-all:hover { text-decoration: underline; }
        .search-area-small { display: flex; align-items: center; background: #fef2f2; padding: 6px 12px; border-radius: 40px; gap: 8px; }
        .search-area-small input { border: none; background: none; outline: none; font-size: 0.8rem; width: 180px; }
        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #dc2626; }
        .theses-list { display: flex; flex-direction: column; gap: 12px; }
        .thesis-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #fef2f2; border-radius: 16px; }
        .thesis-item:hover { background: #fee2e2; transform: translateX(5px); }
        .thesis-title { font-weight: 600; color: #1f2937; margin-bottom: 5px; }
        .thesis-meta { display: flex; gap: 15px; font-size: 0.7rem; color: #6b7280; }
        .review-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #dc2626; color: white; text-decoration: none; border-radius: 30px; font-size: 0.75rem; }
        .review-btn:hover { background: #991b1b; }
        .table-responsive { overflow-x: auto; }
        .theses-table { width: 100%; border-collapse: collapse; }
        .theses-table th { text-align: left; padding: 12px 8px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #fee2e2; }
        .theses-table td { padding: 12px 8px; border-bottom: 1px solid #fef2f2; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 500; }
        .status-badge.pending_coordinator, .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.forwarded_to_dean, .status-badge.forwarded { background: #dbeafe; color: #2563eb; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }
        .btn-view { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; background: #fef2f2; color: #dc2626; text-decoration: none; border-radius: 20px; font-size: 0.7rem; }
        .btn-view:hover { background: #fee2e2; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99; display: none; }
        .sidebar-overlay.show { display: block; }
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 1024px) { .stats-grid, .charts-row { grid-template-columns: repeat(2, 1fr); } .monthly-stats { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 768px) { .top-nav { left: 0; padding: 0 16px; } .hamburger { display: flex; } .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; padding: 20px; } .stats-grid, .charts-row { grid-template-columns: 1fr; } .search-area { display: none; } .profile-name { display: none; } .welcome-banner { flex-direction: column; text-align: center; } .coordinator-info { text-align: center; } .monthly-stats { grid-template-columns: repeat(3, 1fr); } .notification-dropdown { width: 320px; right: -20px; } }
        @media (max-width: 480px) { .main-content { padding: 16px; } .stat-card { padding: 16px; } .stat-icon { width: 45px; height: 45px; font-size: 1.3rem; } .stat-content h3 { font-size: 1.5rem; } .monthly-stats { grid-template-columns: repeat(2, 1fr); } }
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #3d3d3d; }
        body.dark-mode .profile-name { color: #fecaca; }
        body.dark-mode .stat-card, body.dark-mode .chart-card, body.dark-mode .theses-card, body.dark-mode .submissions-card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .stat-content h3 { color: #fecaca; }
        body.dark-mode .thesis-item { background: #3d3d3d; }
        body.dark-mode .thesis-title { color: #e5e7eb; }
        body.dark-mode .theses-table td { color: #e5e7eb; border-bottom-color: #3d3d3d; }
        body.dark-mode .profile-dropdown, body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .btn-view { background: #3d3d3d; color: #fecaca; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search theses..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header"><h3>Notifications</h3><?php if ($notificationCount > 0): ?><span class="mark-all-read" id="markAllRead">Mark all as read</span><?php endif; ?></div>
                    <div class="notification-list">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty"><div class="notif-icon"><i class="far fa-bell-slash"></i></div><div class="notif-content"><div class="notif-message">No notifications yet</div></div></div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>" data-link="<?= $notif['link'] ?? '#' ?>">
                                    <div class="notif-icon"><?php if(strpos($notif['message'], 'submitted') !== false) echo '<i class="fas fa-file-alt"></i>'; elseif(strpos($notif['message'], 'approved') !== false) echo '<i class="fas fa-check-circle"></i>'; elseif(strpos($notif['message'], 'rejected') !== false) echo '<i class="fas fa-times-circle"></i>'; elseif(strpos($notif['message'], 'forwarded') !== false) echo '<i class="fas fa-arrow-right"></i>'; else echo '<i class="fas fa-bell"></i>'; ?></div>
                                    <div class="notif-content"><div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div><div class="notif-time"><i class="far fa-clock"></i> <?php $date = new DateTime($notif['created_at']); $now = new DateTime(); $diff = $now->diff($date); if($diff->days == 0) echo 'Today, ' . $date->format('h:i A'); elseif($diff->days == 1) echo 'Yesterday, ' . $date->format('h:i A'); else echo $date->format('M d, Y h:i A'); ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer"><a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a></div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper"><div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div><div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div></div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">RESEARCH COORDINATOR</div></div>
        <div class="nav-menu"><a href="coordinatorDashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a><a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a><a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a><a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a></div>
        <div class="nav-footer"><div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div><a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
    </aside>

    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info"><h1>Research Coordinator Dashboard</h1><p><strong>COORDINATOR</strong> • Welcome back, <?= htmlspecialchars($first_name) ?>! • <?= htmlspecialchars($department_name) ?></p></div>
            <div class="coordinator-info"><div class="coordinator-name"><?= htmlspecialchars($fullName) ?></div><div class="coordinator-position"><?= htmlspecialchars($position) ?></div><div class="coordinator-since">Since <?= $assigned_date ?></div></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-right"></i></div><div class="stat-content"><h3><?= number_format($stats['forwarded']) ?></h3><p>Forwarded to Dean</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-content"><h3><?= number_format($stats['rejected']) ?></h3><p>Rejected</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= number_format($stats['pending']) ?></h3><p>Pending Review</p></div></div>
        </div>

        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Thesis Status Distribution</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                <div class="status-labels"><div class="status-label-item"><span class="status-color pending"></span><span>Pending Review (<?= $stats['pending'] ?>)</span></div><div class="status-label-item"><span class="status-color forwarded"></span><span>Forwarded to Dean (<?= $stats['forwarded'] ?>)</span></div><div class="status-label-item"><span class="status-color rejected"></span><span>Rejected (<?= $stats['rejected'] ?>)</span></div></div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Thesis Submissions</h3>
                <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
                <div class="monthly-stats"><?php for ($i = 0; $i < 12; $i++): ?><div class="month-item"><span class="month-name"><?= $months[$i] ?>:</span><span class="month-count"><?= $monthly_data[$i] ?></span></div><?php endfor; ?></div>
            </div>
        </div>

        <div class="theses-card">
            <div class="card-header"><h3><i class="fas fa-clock"></i> Theses Waiting for Your Review</h3><a href="reviewThesis.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <?php if (empty($pendingTheses)): ?><div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending theses to review</p></div>
            <?php else: ?><div class="theses-list"><?php foreach ($pendingTheses as $thesis): ?><div class="thesis-item"><div class="thesis-info"><div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div><div class="thesis-meta"><span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['author']) ?></span><span><i class="fas fa-calendar"></i> <?= $thesis['date'] ?></span></div></div><a href="reviewThesis.php?id=<?= $thesis['id'] ?? urlencode($thesis['title']) ?>" class="review-btn">Review <i class="fas fa-arrow-right"></i></a></div><?php endforeach; ?></div><?php endif; ?>
        </div>

        <div class="submissions-card">
            <div class="card-header"><h3><i class="fas fa-file-alt"></i> All Thesis Submissions</h3><div class="search-area-small"><i class="fas fa-search"></i><input type="text" id="thesisSearchInput" placeholder="Search theses..."></div></div>
            <div class="table-responsive"><table class="theses-table"><thead><tr><th>Thesis Title</th><th>Author</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody id="thesisTableBody"><?php foreach ($allThesesWithStatus as $thesis): ?><tr><td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td><td><?= htmlspecialchars($thesis['author']) ?></td><td><?= $thesis['date'] ?></td><td><span class="status-badge <?= $thesis['status'] ?>"><?php $status_text = ucfirst(str_replace('_', ' ', $thesis['status'])); if ($status_text == 'Pending_coordinator') $status_text = 'Pending Review'; echo $status_text; ?></span></td><td><a href="reviewThesis.php?id=<?= $thesis['id'] ?? urlencode($thesis['title']) ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
    </main>

    <script>
        window.chartData = { status: { pending: <?= $stats['pending'] ?>, forwarded: <?= $stats['forwarded'] ?>, rejected: <?= $stats['rejected'] ?> }, monthly: <?= json_encode($monthly_data) ?>, months: <?= json_encode($months) ?> };
        const hamburgerBtn = document.getElementById('hamburgerBtn'), sidebar = document.getElementById('sidebar'), sidebarOverlay = document.getElementById('sidebarOverlay'), profileWrapper = document.getElementById('profileWrapper'), profileDropdown = document.getElementById('profileDropdown'), notificationIcon = document.getElementById('notificationIcon'), notificationDropdown = document.getElementById('notificationDropdown'), darkModeToggle = document.getElementById('darkmode'), searchInput = document.getElementById('searchInput'), thesisSearchInput = document.getElementById('thesisSearchInput'), thesisTableBody = document.getElementById('thesisTableBody'), markAllReadBtn = document.getElementById('markAllRead');
        function toggleSidebar() { sidebar.classList.toggle('open'); if (sidebar.classList.contains('open')) { sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; } else { sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; } }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleProfileDropdown(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); if (notificationDropdown) notificationDropdown.classList.remove('show'); }
        function closeProfileDropdown(e) { if (profileWrapper && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); }
        function toggleNotificationDropdown(e) { e.stopPropagation(); if (notificationDropdown) { notificationDropdown.classList.toggle('show'); if (profileDropdown) profileDropdown.classList.remove('show'); } }
        function closeNotificationDropdown(e) { const nc = document.querySelector('.notification-container'); if (nc && !nc.contains(e.target)) if (notificationDropdown) notificationDropdown.classList.remove('show'); }
        function markNotificationAsRead(notifId, element) { fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_read=1&notif_id=' + notifId }).then(r => r.json()).then(d => { if (d.success) { element.classList.remove('unread'); const badge = document.querySelector('.notification-badge'); if (badge) { let c = parseInt(badge.textContent); if (c > 0) { c--; if (c === 0) badge.style.display = 'none'; else badge.textContent = c; } } } }); }
        function markAllAsRead() { fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_all_read=1' }).then(r => r.json()).then(d => { if (d.success) { document.querySelectorAll('.notification-item.unread').forEach(i => i.classList.remove('unread')); const badge = document.querySelector('.notification-badge'); if (badge) badge.style.display = 'none'; if (markAllReadBtn) markAllReadBtn.style.display = 'none'; } }); }
        function initDarkMode() { const isDark = localStorage.getItem('darkMode') === 'true'; if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; } if (darkModeToggle) { darkModeToggle.addEventListener('change', function() { if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); } else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); } }); } }
        function initCharts() { const statusCtx = document.getElementById('statusChart'); if (statusCtx && window.chartData) { new Chart(statusCtx, { type: 'doughnut', data: { labels: ['Pending Review', 'Forwarded to Dean', 'Rejected'], datasets: [{ data: [window.chartData.status.pending, window.chartData.status.forwarded, window.chartData.status.rejected], backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444'], borderWidth: 0, cutout: '65%', hoverOffset: 10 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { const v = c.raw || 0; const t = c.dataset.data.reduce((a,b)=>a+b,0); const p = t > 0 ? Math.round((v/t)*100) : 0; return `${c.label}: ${v} (${p}%)`; } } } } } }); } const monthlyCtx = document.getElementById('monthlyChart'); if (monthlyCtx && window.chartData) { new Chart(monthlyCtx, { type: 'line', data: { labels: window.chartData.months, datasets: [{ label: 'Thesis Submissions', data: window.chartData.monthly, borderColor: '#dc2626', backgroundColor: 'rgba(220,38,38,0.1)', borderWidth: 2, pointBackgroundColor: '#dc2626', pointBorderColor: 'white', pointRadius: 4, pointHoverRadius: 6, fill: true, tension: 0.3 }] }, options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return `Submissions: ${c.raw}`; } } } }, scales: { y: { beginAtZero: true, grid: { color: '#fee2e2' }, ticks: { stepSize: 1, precision: 0 }, title: { display: true, text: 'Number of Submissions', color: '#6b7280', font: { size: 10 } } }, x: { grid: { display: false }, ticks: { color: '#6b7280' } } } } }); } }
        function initSearch() { if (searchInput) { searchInput.addEventListener('input', function() { const t = this.value.toLowerCase(); document.querySelectorAll('.thesis-item').forEach(i => { const ti = i.querySelector('.thesis-title')?.textContent.toLowerCase() || ''; const au = i.querySelector('.thesis-meta')?.textContent.toLowerCase() || ''; i.style.display = (ti.includes(t) || au.includes(t)) ? '' : 'none'; }); }); } if (thesisSearchInput && thesisTableBody) { thesisSearchInput.addEventListener('input', function() { const t = this.value.toLowerCase(); thesisTableBody.querySelectorAll('tr').forEach(r => { r.style.display = r.textContent.toLowerCase().includes(t) ? '' : 'none'; }); }); } }
        function initNotifications() { document.querySelectorAll('.notification-item').forEach(i => { if (!i.classList.contains('empty')) { i.addEventListener('click', function(e) { e.stopPropagation(); const id = this.dataset.id; const link = this.dataset.link; if (id) markNotificationAsRead(id, this); if (link && link !== '#') window.location.href = link; }); } }); if (markAllReadBtn) markAllReadBtn.addEventListener('click', function(e) { e.stopPropagation(); markAllAsRead(); }); }
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        if (profileWrapper) { profileWrapper.addEventListener('click', toggleProfileDropdown); document.addEventListener('click', closeProfileDropdown); }
        if (notificationIcon) { notificationIcon.addEventListener('click', toggleNotificationDropdown); document.addEventListener('click', closeNotificationDropdown); }
        document.addEventListener('DOMContentLoaded', function() { initDarkMode(); initCharts(); initSearch(); initNotifications(); document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { if (sidebar.classList.contains('open')) closeSidebar(); if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show'); } }); window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); }); });
    </script>
</body>
</html>