<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// GET USER DATA FROM DATABASE
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

$first_name = '';
$last_name = '';
$username = '';
$user_email = '';

if ($user_data) {
    $first_name = $user_data['first_name'] ?? '';
    $last_name = $user_data['last_name'] ?? '';
    $username = $user_data['username'] ?? '';
    $user_email = $user_data['email'] ?? '';
}

$fullName = trim($first_name . " " . $last_name);
if (empty($fullName)) $fullName = !empty($username) ? $username : "Coordinator";

$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) : 
            (!empty($first_name) ? strtoupper(substr($first_name, 0, 1)) : "CO");

// GET COORDINATOR DATA
$department_name = "Research Department";
$position = "Research Coordinator";
$assigned_date = date('F Y');

// ==================== CHECK IF THESIS_TABLE EXISTS ====================
$thesis_table_exists = false;
$check_thesis = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis && $check_thesis->num_rows > 0) {
    $thesis_table_exists = true;
}

// ==================== NOTIFICATION SYSTEM ====================
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    thesis_id INT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255) NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['cnt'];
}
$notif_stmt->close();

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list_query = "SELECT notification_id, user_id, thesis_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();

// ==================== FUNCTION TO NOTIFY DEAN ====================
function notifyDean($conn, $thesis_id, $thesis_title, $student_name, $coordinator_name) {
    $dean_query = "SELECT user_id FROM user_table WHERE role_id = 4";
    $dean_result = $conn->query($dean_query);
    
    if ($dean_result && $dean_result->num_rows > 0) {
        while ($dean = $dean_result->fetch_assoc()) {
            $message = "📋 Thesis ready for Dean approval: \"" . $thesis_title . "\" from student " . $student_name . ". Forwarded by Coordinator: " . $coordinator_name;
            $link = "../departmentDeanDashboard/reviewThesis.php?id=" . $thesis_id;
            $insert = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) VALUES (?, ?, ?, 'dean_forward', ?, 0, NOW())";
            $stmt = $conn->prepare($insert);
            $stmt->bind_param("iisss", $dean['user_id'], $thesis_id, $message, $link);
            $stmt->execute();
            $stmt->close();
        }
    }
    return true;
}

// GET PENDING THESES FROM THESIS_TABLE (status = 'pending_coordinator')
$pending_theses = [];
if ($thesis_table_exists) {
    $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                      FROM thesis_table t
                      JOIN user_table u ON t.student_id = u.user_id
                      WHERE t.status = 'pending_coordinator'
                      ORDER BY t.date_submitted DESC";
    $pending_result = $conn->query($pending_query);
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $pending_theses[] = $row;
        }
    }
}

// MARK NOTIFICATION AS READ
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update_query = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
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

// FORWARD THESIS TO DEAN
if (isset($_POST['forward_to_dean']) && isset($_POST['thesis_id']) && $thesis_table_exists) {
    header('Content-Type: application/json');
    $thesis_id = intval($_POST['thesis_id']);
    $thesis_title = $_POST['thesis_title'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    $coordinator_name = $fullName;
    
    $update_thesis = "UPDATE thesis_table SET status = 'forwarded_to_dean' WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_thesis);
    $update_stmt->bind_param("i", $thesis_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    notifyDean($conn, $thesis_id, $thesis_title, $student_name, $coordinator_name);
    
    echo json_encode(['success' => true, 'message' => 'Thesis forwarded to Dean successfully']);
    exit;
}

// REJECT THESIS
if (isset($_POST['reject_thesis']) && isset($_POST['thesis_id']) && $thesis_table_exists) {
    header('Content-Type: application/json');
    $thesis_id = intval($_POST['thesis_id']);
    $reason = $_POST['reason'] ?? 'No reason provided';
    
    $update_thesis = "UPDATE thesis_table SET status = 'rejected' WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_thesis);
    $update_stmt->bind_param("i", $thesis_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Thesis rejected successfully']);
    exit;
}

// GET THESIS DATA FOR STATS AND DISPLAY
$allSubmissions = [
    'pending_coordinator' => [],
    'forwarded_to_dean' => [],
    'rejected' => []
];

if ($thesis_table_exists) {
    $pending_query = "SELECT thesis_id, title, adviser, department, year, status, date_submitted 
                      FROM thesis_table 
                      WHERE status = 'pending_coordinator'
                      ORDER BY date_submitted DESC";
    $pending_result = $conn->query($pending_query);
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $allSubmissions['pending_coordinator'][] = [
                'title' => $row['title'],
                'author' => $row['adviser'] ?? 'Unknown',
                'date' => isset($row['date_submitted']) ? date('M d, Y', strtotime($row['date_submitted'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
    
    $forwarded_query = "SELECT thesis_id, title, adviser, department, year, status, date_submitted FROM thesis_table WHERE status = 'forwarded_to_dean' ORDER BY date_submitted DESC";
    $forwarded_result = $conn->query($forwarded_query);
    if ($forwarded_result && $forwarded_result->num_rows > 0) {
        while ($row = $forwarded_result->fetch_assoc()) {
            $allSubmissions['forwarded_to_dean'][] = [
                'title' => $row['title'],
                'author' => $row['adviser'] ?? 'Unknown',
                'date' => isset($row['date_submitted']) ? date('M d, Y', strtotime($row['date_submitted'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
    
    $rejected_query = "SELECT thesis_id, title, adviser, department, year, status, date_submitted FROM thesis_table WHERE status = 'rejected' ORDER BY date_submitted DESC";
    $rejected_result = $conn->query($rejected_query);
    if ($rejected_result && $rejected_result->num_rows > 0) {
        while ($row = $rejected_result->fetch_assoc()) {
            $allSubmissions['rejected'][] = [
                'title' => $row['title'],
                'author' => $row['adviser'] ?? 'Unknown',
                'date' => isset($row['date_submitted']) ? date('M d, Y', strtotime($row['date_submitted'])) : date('M d, Y'),
                'id' => $row['thesis_id']
            ];
        }
    }
}

$stats = [
    'forwarded' => count($allSubmissions['forwarded_to_dean']),
    'rejected'  => count($allSubmissions['rejected']),
    'pending'   => count($allSubmissions['pending_coordinator'])
];

$pendingTheses = $allSubmissions['pending_coordinator'];

$allThesesWithStatus = [];
foreach ($allSubmissions as $status => $theses) {
    foreach ($theses as $thesis) {
        $thesis['status'] = $status;
        $allThesesWithStatus[] = $thesis;
    }
}

// Monthly submissions data for graph
$monthly_data = array_fill(0, 12, 0);
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

if ($thesis_table_exists) {
    $monthly_query = "SELECT MONTH(date_submitted) as month, COUNT(*) as count FROM thesis_table WHERE YEAR(date_submitted) = YEAR(CURDATE()) GROUP BY MONTH(date_submitted)";
    $monthly_result = $conn->query($monthly_query);
    if ($monthly_result && $monthly_result->num_rows > 0) {
        while ($row = $monthly_result->fetch_assoc()) {
            $monthly_data[$row['month'] - 1] = $row['count'];
        }
    }
}

$hasMonthlyData = false;
foreach ($monthly_data as $val) {
    if ($val > 0) {
        $hasMonthlyData = true;
        break;
    }
}

$sample_monthly_data = [3, 4, 5, 6, 8, 10, 12, 9, 7, 5, 4, 2];
$sample_stats = [
    'forwarded' => 5,
    'rejected'  => 2,
    'pending'   => 8
];

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
        
        .top-nav { 
            position: fixed; 
            top: 0; 
            right: 0; 
            left: 0; 
            height: 70px; 
            background: white; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 0 32px; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05); 
            z-index: 99; 
            border-bottom: 1px solid #fee2e2; 
        }
        
        .nav-left { display: flex; align-items: center; gap: 24px; }
        
        .hamburger { 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            gap: 5px; 
            width: 40px; 
            height: 40px; 
            background: #fef2f2; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
        }
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
        .notification-badge { 
            position: absolute; 
            top: -5px; 
            right: -5px; 
            background: #ef4444; 
            color: white; 
            font-size: 0.6rem; 
            font-weight: 600; 
            min-width: 18px; 
            height: 18px; 
            border-radius: 10px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0 5px; 
        }
        
        .notification-dropdown { 
            position: absolute; 
            top: 55px; 
            right: 0; 
            width: 380px; 
            max-width: 90vw;
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
            display: none; 
            overflow: hidden; 
            z-index: 1000; 
            border: 1px solid #fee2e2; 
        }
        .notification-dropdown.show { display: block; animation: fadeSlideDown 0.2s ease; }
        .notification-header { padding: 16px 20px; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; }
        .notification-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; }
        .mark-all-read { font-size: 0.7rem; color: #dc2626; cursor: pointer; background: none; border: none; }
        .mark-all-read:hover { text-decoration: underline; }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { 
            display: flex; 
            gap: 12px; 
            padding: 12px 20px; 
            border-bottom: 1px solid #fef2f2; 
            cursor: pointer; 
            transition: background 0.2s; 
            text-decoration: none; 
            color: inherit;
        }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-item.empty { justify-content: center; color: #9ca3af; cursor: default; }
        .notif-icon { width: 36px; height: 36px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.8rem; color: #1f2937; margin-bottom: 4px; line-height: 1.4; }
        .notif-time { font-size: 0.65rem; color: #9ca3af; }
        .notification-footer { padding: 12px 20px; border-top: 1px solid #fee2e2; text-align: center; }
        .notification-footer a { color: #dc2626; text-decoration: none; font-size: 0.8rem; }
        .notification-footer a:hover { text-decoration: underline; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #5b3b3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 100; border: 1px solid #fee2e2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        
        .sidebar { 
            position: fixed; 
            top: 0; 
            left: -300px; 
            width: 280px; 
            height: 100%; 
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); 
            display: flex; 
            flex-direction: column; 
            z-index: 1000; 
            transition: left 0.3s ease; 
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        .sidebar.open { left: 0; }
        
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 6px; }
        
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; font-weight: 500; transition: all 0.2s; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 12px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
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
        
        .charts-row { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 28px; 
            margin-bottom: 32px; 
        }
        
        .chart-card { 
            background: white; 
            border-radius: 24px; 
            padding: 24px; 
            border: 1px solid #fee2e2; 
            transition: all 0.2s;
            display: flex;
            flex-direction: column;
        }
        
        .chart-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.08); 
        }
        
        .chart-card h3 { 
            font-size: 1rem; 
            font-weight: 600; 
            color: #991b1b; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }
        
        .chart-container { 
            height: 260px; 
            position: relative; 
            width: 100%;
            min-height: 250px;
        }
        
        .status-labels { 
            display: flex; 
            justify-content: center; 
            gap: 24px; 
            margin-top: 20px; 
            flex-wrap: wrap; 
            padding-top: 16px;
            border-top: 1px solid #fee2e2;
        }
        
        .status-label-item { 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.75rem; 
            color: #6b7280; 
            font-weight: 500;
        }
        
        .status-color { 
            width: 12px; 
            height: 12px; 
            border-radius: 50%; 
        }
        
        .status-color.pending { background: #f59e0b; }
        .status-color.forwarded { background: #3b82f6; }
        .status-color.rejected { background: #ef4444; }
        
        .monthly-stats { 
            display: grid; 
            grid-template-columns: repeat(6, 1fr); 
            gap: 12px; 
            margin-top: 20px; 
            padding-top: 16px; 
            border-top: 1px solid #fee2e2; 
        }
        
        .month-item { 
            text-align: center; 
            font-size: 0.7rem; 
        }
        
        .month-name { 
            color: #6b7280; 
            display: block; 
            margin-bottom: 4px; 
            font-weight: 500;
        }
        
        .month-count { 
            font-weight: 700; 
            color: #dc2626; 
            font-size: 0.9rem; 
            background: #fef2f2;
            padding: 4px 8px;
            border-radius: 20px;
            display: inline-block;
            min-width: 35px;
        }
        
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
        .status-badge.pending_coordinator { background: #fef3c7; color: #d97706; }
        .status-badge.forwarded_to_dean { background: #dbeafe; color: #2563eb; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }
        .btn-view { 
            display: inline-flex; 
            align-items: center; 
            gap: 5px; 
            padding: 5px 12px; 
            background: #dc2626; 
            color: white; 
            text-decoration: none; 
            border-radius: 20px; 
            font-size: 0.7rem; 
            transition: all 0.3s; 
        }
        .btn-view:hover { background: #991b1b; transform: scale(1.05); }
        
        .notification-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid #fee2e2;
        }
        .notification-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notification-item-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .notification-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: #fef2f2;
            border-radius: 16px;
            border-left: 3px solid #dc2626;
            flex-wrap: wrap;
            gap: 12px;
        }
        .notification-list-item:hover {
            background: #fee2e2;
        }
        .notification-info {
            flex: 1;
            min-width: 200px;
        }
        .notification-message-text {
            font-weight: 500;
            font-size: 0.85rem;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .notification-time {
            font-size: 0.65rem;
            color: #9ca3af;
        }
        .notification-actions-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn-forward {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .btn-forward:hover {
            background: #059669;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .btn-reject:hover {
            background: #dc2626;
        }
        .reject-input {
            padding: 8px;
            border: 1px solid #fee2e2;
            border-radius: 8px;
            width: 100%;
            font-size: 0.8rem;
        }
        
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        .toast-message {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 0.85rem;
            z-index: 1001;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .toast-message.error {
            background: #ef4444;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 1024px) { 
            .stats-grid, .charts-row { 
                grid-template-columns: repeat(2, 1fr); 
            } 
            .monthly-stats { 
                grid-template-columns: repeat(4, 1fr); 
                gap: 15px;
            } 
        }
        
        @media (max-width: 768px) {
            .top-nav { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid, .charts-row { grid-template-columns: 1fr; }
            .search-area { display: none; }
            .profile-name { display: none; }
            .welcome-banner { flex-direction: column; text-align: center; }
            .coordinator-info { text-align: center; }
            .monthly-stats { grid-template-columns: repeat(3, 1fr); }
            .notification-dropdown { width: 320px; right: -20px; }
            .chart-container { height: 220px; }
            .notification-list-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .notification-actions-group {
                width: 100%;
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 480px) { 
            .main-content { padding: 16px; } 
            .stat-card { padding: 16px; } 
            .stat-icon { width: 45px; height: 45px; font-size: 1.3rem; } 
            .stat-content h3 { font-size: 1.5rem; } 
            .monthly-stats { grid-template-columns: repeat(2, 1fr); gap: 10px; } 
            .chart-container { height: 200px; }
            .status-labels { gap: 16px; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #3d3d3d; }
        body.dark-mode .profile-name { color: #fecaca; }
        body.dark-mode .stat-card, body.dark-mode .chart-card, body.dark-mode .theses-card, body.dark-mode .submissions-card, body.dark-mode .notification-card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .stat-content h3 { color: #fecaca; }
        body.dark-mode .thesis-item { background: #3d3d3d; }
        body.dark-mode .thesis-title { color: #e5e7eb; }
        body.dark-mode .theses-table td { color: #e5e7eb; border-bottom-color: #3d3d3d; }
        body.dark-mode .profile-dropdown, body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .btn-view { background: #dc2626; color: white; }
        body.dark-mode .notification-list-item { background: #3d3d3d; }
        body.dark-mode .notification-message-text { color: #e5e7eb; }
        body.dark-mode .status-labels,
        body.dark-mode .monthly-stats {
            border-top-color: #991b1b;
        }
        body.dark-mode .month-count {
            background: #3d3d3d;
            color: #fecaca;
        }
        body.dark-mode .status-label-item {
            color: #9ca3af;
        }
        body.dark-mode .chart-card h3 {
            color: #fecaca;
        }
        body.dark-mode .btn-reject {
            background: #dc2626;
            color: white;
        }
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
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: <?= $notificationCount > 0 ? 'flex' : 'none' ?>;">
                        <?= $notificationCount ?>
                    </span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <button class="mark-all-read" id="markAllRead">Mark all as read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty">
                                <div class="notif-icon"><i class="far fa-bell-slash"></i></div>
                                <div class="notif-content">
                                    <div class="notif-message">No notifications yet</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>">
                                    <div class="notif-icon">
                                        <?php 
                                        if(strpos($notif['message'], 'submitted') !== false || strpos($notif['message'], 'forwarded') !== false) 
                                            echo '<i class="fas fa-file-alt"></i>'; 
                                        elseif(strpos($notif['message'], 'approved') !== false) 
                                            echo '<i class="fas fa-check-circle"></i>'; 
                                        elseif(strpos($notif['message'], 'rejected') !== false) 
                                            echo '<i class="fas fa-times-circle"></i>'; 
                                        else 
                                            echo '<i class="fas fa-bell"></i>'; 
                                        ?>
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time">
                                            <i class="far fa-clock"></i> 
                                            <?php 
                                            $date = new DateTime($notif['created_at']); 
                                            $now = new DateTime(); 
                                            $diff = $now->diff($date); 
                                            if($diff->days == 0) 
                                                echo 'Today, ' . $date->format('h:i A'); 
                                            elseif($diff->days == 1) 
                                                echo 'Yesterday, ' . $date->format('h:i A'); 
                                            else 
                                                echo $date->format('M d, Y h:i A'); 
                                            ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">RESEARCH COORDINATOR</div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info"><h1>Research Coordinator Dashboard</h1><p><strong>COORDINATOR</strong> • Welcome back, <?= htmlspecialchars($first_name) ?>! • <?= htmlspecialchars($department_name) ?></p></div>
            <div class="coordinator-info"><div class="coordinator-name"><?= htmlspecialchars($fullName) ?></div><div class="coordinator-position"><?= htmlspecialchars($position) ?></div><div class="coordinator-since">Since <?= $assigned_date ?></div></div>
        </div>

        <!-- PENDING THESES FROM FACULTY SECTION -->
        <?php if (!empty($pending_theses)): ?>
        <div class="notification-card">
            <h3><i class="fas fa-bell"></i> Pending Theses for Dean Forwarding (<?= count($pending_theses) ?>)</h3>
            <div class="notification-item-list" id="facultyNotificationsList">
                <?php foreach ($pending_theses as $thesis): ?>
                <div class="notification-list-item" data-thesis-id="<?= $thesis['thesis_id'] ?>" data-thesis-title="<?= htmlspecialchars($thesis['title']) ?>" data-student-name="<?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?>">
                    <div class="notification-info">
                        <div class="notification-message-text">
                            📢 <strong><?= htmlspecialchars($thesis['title']) ?></strong><br>
                            Student: <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?>
                        </div>
                        <div class="notification-time">
                            <i class="far fa-clock"></i> Submitted: <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?>
                        </div>
                    </div>
                    <div class="notification-actions-group">
                        <button class="btn-forward" onclick="forwardToDean(this)">Forward to Dean <i class="fas fa-arrow-right"></i></button>
                        <button class="btn-reject" onclick="showRejectReason(this)">Reject <i class="fas fa-times"></i></button>
                    </div>
                    <div class="reject-reason" style="display: none; width: 100%;">
                        <input type="text" placeholder="Enter rejection reason..." class="reject-input">
                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                            <button class="btn-reject" onclick="confirmReject(this)">Confirm Reject</button>
                            <button class="btn-forward" onclick="cancelReject(this)">Cancel</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="notification-card">
            <h3><i class="fas fa-bell"></i> Pending Theses for Dean Forwarding</h3>
            <div class="empty-state" style="padding: 20px;">
                <i class="fas fa-check-circle"></i>
                <p>No pending theses from faculty to review.</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-right"></i></div><div class="stat-content"><h3><?= number_format($stats['forwarded']) ?></h3><p>Forwarded to Dean</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-content"><h3><?= number_format($stats['rejected']) ?></h3><p>Rejected</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= number_format($stats['pending']) ?></h3><p>Pending Review</p></div></div>
        </div>

        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Thesis Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart" style="max-height: 250px; width: 100%;"></canvas>
                </div>
                <div class="status-labels">
                    <div class="status-label-item"><span class="status-color pending"></span><span>Pending Review (<?= $stats['pending'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color forwarded"></span><span>Forwarded to Dean (<?= $stats['forwarded'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color rejected"></span><span>Rejected (<?= $stats['rejected'] ?>)</span></div>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Thesis Submissions</h3>
                <div class="chart-container">
                    <canvas id="monthlyChart" style="max-height: 250px; width: 100%;"></canvas>
                </div>
                <div class="monthly-stats">
                    <?php for ($i = 0; $i < 12; $i++): ?>
                    <div class="month-item">
                        <span class="month-name"><?= $months[$i] ?>:</span>
                        <span class="month-count"><?= $monthly_data[$i] ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="theses-card">
            <div class="card-header"><h3><i class="fas fa-clock"></i> Theses Waiting for Your Review</h3><a href="reviewThesis.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <?php if (empty($pendingTheses)): ?>
                <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending theses to review</p></div>
            <?php else: ?>
                <div class="theses-list">
                    <?php foreach ($pendingTheses as $thesis): ?>
                    <div class="thesis-item">
                        <div class="thesis-info">
                            <div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div>
                            <div class="thesis-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['author']) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= $thesis['date'] ?></span>
                            </div>
                        </div>
                        <a href="reviewThesis.php?id=<?= $thesis['id'] ?>" class="review-btn">Review <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="submissions-card">
            <div class="card-header"><h3><i class="fas fa-file-alt"></i> All Thesis Submissions</h3><div class="search-area-small"><i class="fas fa-search"></i><input type="text" id="thesisSearchInput" placeholder="Search theses..."></div></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead>
                        <tr>
                            <th>Thesis Title</th>
                            <th>Author</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="thesisTableBody">
                        <?php foreach ($allThesesWithStatus as $thesis): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                            <td><?= htmlspecialchars($thesis['author']) ?></td>
                            <td><?= $thesis['date'] ?></td>
                            <td>
                                <span class="status-badge <?= $thesis['status'] ?>">
                                    <?php 
                                    $status_text = ucfirst(str_replace('_', ' ', $thesis['status'])); 
                                    if ($status_text == 'Pending_coordinator') $status_text = 'Pending Review'; 
                                    echo $status_text; 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <a href="reviewThesis.php?id=<?= $thesis['id'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        window.chartData = { 
            status: { 
                pending: <?= $stats['pending'] ?>, 
                forwarded: <?= $stats['forwarded'] ?>, 
                rejected: <?= $stats['rejected'] ?> 
            }, 
            monthly: <?= json_encode($monthly_data) ?>, 
            months: <?= json_encode($months) ?> 
        };
        
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInput = document.getElementById('searchInput');
        const thesisSearchInput = document.getElementById('thesisSearchInput');
        const thesisTableBody = document.getElementById('thesisTableBody');
        const markAllReadBtn = document.getElementById('markAllRead');
        
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast-message' + (isError ? ' error' : '');
            toast.innerHTML = '<i class="fas ' + (isError ? 'fa-exclamation-circle' : 'fa-check-circle') + '"></i> ' + message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
        
        window.forwardToDean = function(button) {
            const container = button.closest('.notification-list-item');
            const thesisId = container.dataset.thesisId;
            const thesisTitle = container.dataset.thesisTitle;
            const studentName = container.dataset.studentName;
            
            if (confirm('Are you sure you want to forward this thesis to the Dean?')) {
                button.disabled = true;
                button.innerHTML = 'Processing...';
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'forward_to_dean=1&thesis_id=' + thesisId + '&thesis_title=' + encodeURIComponent(thesisTitle) + '&student_name=' + encodeURIComponent(studentName)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message);
                        const notifItem = button.closest('.notification-list-item');
                        if (notifItem) notifItem.remove();
                        const badge = document.getElementById('notificationBadge');
                        if (badge) {
                            let c = parseInt(badge.textContent);
                            if (c > 0) {
                                c--;
                                if (c === 0) badge.style.display = 'none';
                                else badge.textContent = c;
                            }
                        }
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('Error: ' + (data.message || 'Unknown error'), true);
                        button.disabled = false;
                        button.innerHTML = 'Forward to Dean <i class="fas fa-arrow-right"></i>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', true);
                    button.disabled = false;
                    button.innerHTML = 'Forward to Dean <i class="fas fa-arrow-right"></i>';
                });
            }
        };
        
        window.showRejectReason = function(button) {
            const container = button.closest('.notification-list-item');
            const actionsGroup = container.querySelector('.notification-actions-group');
            const rejectReasonDiv = container.querySelector('.reject-reason');
            
            actionsGroup.style.display = 'none';
            rejectReasonDiv.style.display = 'block';
        };
        
        window.cancelReject = function(button) {
            const container = button.closest('.notification-list-item');
            const actionsGroup = container.querySelector('.notification-actions-group');
            const rejectReasonDiv = container.querySelector('.reject-reason');
            const reasonInput = container.querySelector('.reject-input');
            
            actionsGroup.style.display = 'flex';
            rejectReasonDiv.style.display = 'none';
            if (reasonInput) reasonInput.value = '';
        };
        
        window.confirmReject = function(button) {
            const container = button.closest('.notification-list-item');
            const thesisId = container.dataset.thesisId;
            const reasonInput = container.querySelector('.reject-input');
            const reason = reasonInput ? reasonInput.value : '';
            
            if (!reason.trim()) {
                showToast('Please enter a reason for rejection', true);
                return;
            }
            
            button.disabled = true;
            button.innerHTML = 'Processing...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'reject_thesis=1&thesis_id=' + thesisId + '&reason=' + encodeURIComponent(reason)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message);
                    const notifItem = button.closest('.notification-list-item');
                    if (notifItem) notifItem.remove();
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        let c = parseInt(badge.textContent);
                        if (c > 0) {
                            c--;
                            if (c === 0) badge.style.display = 'none';
                            else badge.textContent = c;
                        }
                    }
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error: ' + (data.message || 'Unknown error'), true);
                    button.disabled = false;
                    button.innerHTML = 'Confirm Reject';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please try again.', true);
                button.disabled = false;
                button.innerHTML = 'Confirm Reject';
            });
        };
        
        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function toggleSidebar(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (sidebar.classList.contains('open')) closeSidebar();
                if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
            }
        });
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });
        
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            if (notificationDropdown) notificationDropdown.classList.remove('show');
        }
        
        function closeProfileDropdown(e) {
            if (profileWrapper && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show');
        }
        
        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }
        
        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            if (notificationDropdown) {
                if (profileDropdown && profileDropdown.classList.contains('show')) {
                    profileDropdown.classList.remove('show');
                }
                notificationDropdown.classList.toggle('show');
            }
        }
        
        function closeNotificationDropdown(e) {
            const nc = document.querySelector('.notification-container');
            if (nc && !nc.contains(e.target) && notificationDropdown && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        }
        
        if (notificationIcon) {
            notificationIcon.addEventListener('click', toggleNotificationDropdown);
        }
        document.addEventListener('click', closeNotificationDropdown);
        
        function markNotificationAsRead(notifId, element) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_read=1&notif_id=' + notifId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        let c = parseInt(badge.textContent);
                        if (c > 0) {
                            c--;
                            if (c === 0) badge.style.display = 'none';
                            else badge.textContent = c;
                        }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function markAllAsRead() {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                    const badge = document.getElementById('notificationBadge');
                    if (badge) badge.style.display = 'none';
                    if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                    showToast('All notifications marked as read');
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function initNotifications() {
            document.querySelectorAll('.notification-item').forEach(item => {
                if (!item.classList.contains('empty')) {
                    item.addEventListener('click', function(e) {
                        const id = this.dataset.id;
                        if (id) {
                            markNotificationAsRead(id, this);
                        }
                    });
                }
            });
            
            if (markAllReadBtn) {
                markAllReadBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    markAllAsRead();
                });
            }
        }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                    }
                });
            }
        }
        
        function initCharts() {
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx && window.chartData) {
                if (window.statusChartInstance) window.statusChartInstance.destroy();
                
                window.statusChartInstance = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending Review', 'Forwarded to Dean', 'Rejected'],
                        datasets: [{
                            data: [
                                window.chartData.status.pending, 
                                window.chartData.status.forwarded, 
                                window.chartData.status.rejected
                            ],
                            backgroundColor: ['#f59e0b', '#3b82f6', '#ef4444'],
                            borderWidth: 0,
                            cutout: '60%',
                            hoverOffset: 10,
                            borderRadius: 8,
                            spacing: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        const val = ctx.raw || 0;
                                        const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                        return `${ctx.label}: ${val} (${pct}%)`;
                                    }
                                },
                                backgroundColor: '#1f2937',
                                titleColor: '#fef2f2',
                                bodyColor: '#fef2f2',
                                padding: 10,
                                cornerRadius: 8
                            }
                        },
                        layout: {
                            padding: { top: 10, bottom: 10, left: 10, right: 10 }
                        }
                    }
                });
            }
            
            const monthlyCtx = document.getElementById('monthlyChart');
            if (monthlyCtx && window.chartData) {
                if (window.monthlyChartInstance) window.monthlyChartInstance.destroy();
                
                const maxValue = Math.max(...window.chartData.monthly, 1);
                const yAxisMax = Math.ceil(maxValue * 1.2);
                
                window.monthlyChartInstance = new Chart(monthlyCtx, {
                    type: 'line',
                    data: {
                        labels: window.chartData.months,
                        datasets: [{
                            label: 'Submissions',
                            data: window.chartData.monthly,
                            borderColor: '#dc2626',
                            backgroundColor: 'rgba(220, 38, 38, 0.05)',
                            borderWidth: 3,
                            pointBackgroundColor: '#dc2626',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#991b1b',
                            fill: true,
                            tension: 0.3,
                            spanGaps: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: { label: function(ctx) { return `Submissions: ${ctx.raw}`; } },
                                backgroundColor: '#1f2937',
                                titleColor: '#fef2f2',
                                bodyColor: '#fef2f2',
                                padding: 10,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: yAxisMax,
                                grid: { color: '#fee2e2', drawBorder: true, borderDash: [5, 5], lineWidth: 1 },
                                ticks: { stepSize: 1, precision: 0, color: '#6b7280', font: { size: 11, weight: '500' } },
                                title: { display: true, text: 'Number of Submissions', color: '#9ca3af', font: { size: 10, weight: '500' }, padding: { bottom: 10 } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { color: '#6b7280', font: { size: 11, weight: '500' }, maxRotation: 45, minRotation: 45 },
                                title: { display: true, text: 'Months', color: '#9ca3af', font: { size: 10, weight: '500' }, padding: { top: 10 } }
                            }
                        },
                        elements: {
                            line: { borderJoin: 'round', borderCap: 'round' },
                            point: { hitRadius: 10, hoverRadius: 8 }
                        },
                        layout: { padding: { top: 15, bottom: 15, left: 10, right: 10 } }
                    }
                });
            }
        }
        
        function initSearch() {
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    document.querySelectorAll('.thesis-item').forEach(item => {
                        const title = item.querySelector('.thesis-title')?.textContent.toLowerCase() || '';
                        const author = item.querySelector('.thesis-meta')?.textContent.toLowerCase() || '';
                        item.style.display = (title.includes(term) || author.includes(term)) ? '' : 'none';
                    });
                });
            }
            
            if (thesisSearchInput && thesisTableBody) {
                thesisSearchInput.addEventListener('input', function() {
                    const term = this.value.toLowerCase();
                    thesisTableBody.querySelectorAll('tr').forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                    });
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initCharts();
            initSearch();
            initNotifications();
            console.log('Coordinator Dashboard Initialized - Using thesis_table');
        });
    </script>
</body>
</html>