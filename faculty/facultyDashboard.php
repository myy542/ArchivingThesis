<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// ==================== HANDLE FORWARD TO DEAN ====================
if (isset($_POST['forward_to_dean']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    $thesis_title = $_POST['thesis_title'] ?? '';
    
    $updateQuery = "UPDATE thesis_table SET status = 'Forwarded_to_dean', forwarded_to_dean_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $thesis_id);
    
    if ($stmt->execute()) {
        $studentQuery = "SELECT u.first_name, u.last_name, u.user_id 
                         FROM thesis_table t 
                         JOIN user_table u ON t.student_id = u.user_id 
                         WHERE t.thesis_id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $thesis_id);
        $studentStmt->execute();
        $student = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
        
        $deanQuery = "SELECT user_id FROM user_table WHERE role_id = 4";
        $deanResult = $conn->query($deanQuery);
        
        if ($deanResult && $deanResult->num_rows > 0) {
            $message = "📢 A thesis has been forwarded for your approval: \"" . $thesis_title . "\" from student " . ($student['first_name'] ?? '') . " " . ($student['last_name'] ?? '');
            $link = "../departmentDeanDashboard/reviewThesis.php?id=" . $thesis_id;
            
            while ($dean = $deanResult->fetch_assoc()) {
                $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                            VALUES (?, ?, ?, 'dean_forward', ?, 0, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("iiss", $dean['user_id'], $thesis_id, $message, $link);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// ==================== HANDLE APPROVE THESIS ====================
if (isset($_POST['approve_thesis']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    
    $updateQuery = "UPDATE thesis_table SET status = 'approved', approved_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $thesis_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// ==================== HANDLE REJECT THESIS ====================
if (isset($_POST['reject_thesis']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    $reason = $_POST['reason'] ?? '';
    
    $updateQuery = "UPDATE thesis_table SET status = 'rejected', rejection_reason = ?, rejected_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $reason, $thesis_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// GET USER DATA
$user_query = "SELECT * FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

// MAKE SURE NOTIFICATIONS TABLE EXISTS
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
)");

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

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list_query = "SELECT notification_id as id, user_id, thesis_id, message, type, link, is_read, created_at 
                     FROM notifications 
                     WHERE user_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 10";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();

// GET STATISTICS
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$archivedCount = 0;
$forwardedCount = 0;
$totalCount = 0;

// Monthly data for chart
$monthlyData = [];
for ($i = 6; $i >= 0; $i--) {
    $monthName = date('M', strtotime("-$i months"));
    $monthlyData[$monthName] = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
}

$table_check = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($table_check && $table_check->num_rows > 0) {
    $countsQuery = "SELECT 
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived,
        SUM(CASE WHEN status = 'Forwarded_to_dean' THEN 1 ELSE 0 END) as forwarded,
        COUNT(*) as total
    FROM thesis_table";
    
    $countsResult = $conn->query($countsQuery);
    if ($countsResult) {
        $counts = $countsResult->fetch_assoc();
        $pendingCount = $counts['pending'] ?? 0;
        $approvedCount = $counts['approved'] ?? 0;
        $rejectedCount = $counts['rejected'] ?? 0;
        $archivedCount = $counts['archived'] ?? 0;
        $forwardedCount = $counts['forwarded'] ?? 0;
        $totalCount = $counts['total'] ?? 0;
    }
    
    $monthlyQuery = "SELECT 
        DATE_FORMAT(date_submitted, '%b %Y') as month,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM thesis_table 
    WHERE date_submitted >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date_submitted, '%b %Y')
    ORDER BY MIN(date_submitted) ASC";
    
    $monthlyResult = $conn->query($monthlyQuery);
    if ($monthlyResult) {
        while ($row = $monthlyResult->fetch_assoc()) {
            $monthlyData[$row['month']] = [
                'pending' => $row['pending'],
                'approved' => $row['approved'],
                'rejected' => $row['rejected']
            ];
        }
    }
}

// GET PENDING THESES
$pendingTheses = [];
$query = "SELECT t.*, u.first_name, u.last_name, u.email 
          FROM thesis_table t
          JOIN user_table u ON t.student_id = u.user_id
          WHERE t.status = 'pending'
          ORDER BY t.date_submitted DESC 
          LIMIT 10";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pendingTheses[] = $row;
    }
}

// GET ALL SUBMISSIONS
$allSubmissions = [];
$currentFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT 
        t.*, 
        u.first_name, 
        u.last_name, 
        u.email
        FROM thesis_table t
        JOIN user_table u ON t.student_id = u.user_id";

if ($currentFilter != 'all') {
    $sql .= " WHERE t.status = '" . $conn->real_escape_string($currentFilter) . "'";
}

$sql .= " ORDER BY t.date_submitted DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $allSubmissions[] = $row;
    }
}

$pageTitle = "Faculty Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* Your existing CSS - keep it the same */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }
        body.dark-mode { background: #1a1a1a; color: #e0e0e0; }
        
        .top-nav { position: fixed; top: 0; right: 0; left: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #fee2e2; }
        body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        body.dark-mode .logo { color: #fecaca; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 200px; }
        .nav-right { display: flex; align-items: center; gap: 20px; position: relative; }
        
        .notification-container { position: relative; }
        .notification-icon { position: relative; cursor: pointer; width: 40px; height: 40px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .notification-icon:hover { background: #fee2e2; }
        .notification-icon i { font-size: 1.2rem; color: #dc2626; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 600; min-width: 18px; height: 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
        .notification-dropdown { position: absolute; top: 55px; right: 0; width: 380px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; overflow: hidden; z-index: 100; border: 1px solid #fee2e2; }
        .notification-dropdown.show { display: block; animation: fadeSlideDown 0.2s ease; }
        .notification-header { padding: 16px 20px; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; }
        .notification-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; }
        .mark-all-read { font-size: 0.7rem; color: #dc2626; cursor: pointer; }
        .mark-all-read:hover { text-decoration: underline; }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { display: flex; gap: 12px; padding: 12px 20px; border-bottom: 1px solid #fef2f2; cursor: pointer; transition: background 0.2s; }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-item.empty { justify-content: center; color: #9ca3af; cursor: default; }
        .notif-icon { width: 36px; height: 36px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.8rem; color: #1f2937; margin-bottom: 4px; line-height: 1.4; }
        .notif-time { font-size: 0.65rem; color: #9ca3af; }
        .notification-footer { padding: 12px 20px; border-top: 1px solid #fee2e2; text-align: center; }
        .notification-footer a { color: #dc2626; text-decoration: none; font-size: 0.8rem; }
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .notification-header { border-bottom-color: #991b1b; }
        body.dark-mode .notification-header h3 { color: #fecaca; }
        body.dark-mode .notification-item { border-bottom-color: #3d3d3d; }
        body.dark-mode .notification-item:hover { background: #3d3d3d; }
        body.dark-mode .notification-item.unread { background: #3a1a1a; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .profile-name { font-weight: 500; color: #1f2937; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #5b3b3b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 100; border: 1px solid #fee2e2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        
        .sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
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
        
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 20px; border: 1px solid #fee2e2; transition: transform 0.3s, box-shadow 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        body.dark-mode .stat-card { background: #2d2d2d; border-color: #991b1b; }
        .stat-icon { width: 60px; height: 60px; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; background: #fef2f2; color: #dc2626; }
        .stat-content h3 { font-size: 2rem; font-weight: 700; color: #ba0202; }
        .stat-content p { font-size: 0.8rem; color: #6c757d; }
        
        .chart-container { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #fee2e2; }
        body.dark-mode .chart-container { background: #2d2d2d; border-color: #991b1b; }
        .chart-container h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .chart-wrapper { position: relative; height: 350px; }
        
        .theses-card, .submissions-card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; border: 1px solid #fee2e2; }
        body.dark-mode .theses-card, body.dark-mode .submissions-card { background: #2d2d2d; border-color: #991b1b; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .card-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; display: flex; align-items: center; gap: 8px; }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-btn { padding: 6px 12px; background: #fef2f2; color: #dc2626; text-decoration: none; border-radius: 20px; font-size: 0.75rem; font-weight: 500; transition: all 0.3s; }
        .filter-btn:hover, .filter-btn.active { background: #dc2626; color: white; }
        .theses-list { display: flex; flex-direction: column; gap: 12px; }
        .thesis-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #fef2f2; border-radius: 16px; transition: transform 0.2s; }
        .thesis-item:hover { transform: translateX(5px); }
        body.dark-mode .thesis-item { background: #3d3d3d; }
        .thesis-title { font-weight: 600; color: #1f2937; margin-bottom: 5px; }
        .thesis-meta { display: flex; gap: 15px; font-size: 0.7rem; color: #6b7280; }
        .action-buttons { display: flex; gap: 8px; }
        .review-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #dc2626; color: white; text-decoration: none; border-radius: 30px; font-size: 0.75rem; transition: all 0.3s; }
        .review-btn:hover { background: #991b1b; transform: scale(1.05); }
        .table-responsive { overflow-x: auto; }
        .theses-table { width: 100%; border-collapse: collapse; }
        .theses-table th { text-align: left; padding: 12px 8px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #fee2e2; }
        .theses-table td { padding: 12px 8px; border-bottom: 1px solid #fef2f2; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 500; }
        .status-badge.pending { background: #fef3c7; color: #d97706; }
        .status-badge.approved { background: #d4edda; color: #155724; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }
        .status-badge.archived { background: #d1ecf1; color: #0c5460; }
        .status-badge.Forwarded_to_dean { background: #cce5ff; color: #004085; }
        .btn-review-small, .btn-view-small { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; text-decoration: none; border-radius: 20px; font-size: 0.7rem; transition: all 0.3s; }
        .btn-review-small { background: #dc2626; color: white; }
        .btn-view-small { background: #6c757d; color: white; }
        .btn-review-small:hover, .btn-view-small:hover { transform: scale(1.05); }
        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #dc2626; }
        
        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .toast-message.success { background: #10b981; color: white; }
        .toast-message.error { background: #ef4444; color: white; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .search-area { display: none; }
            .profile-name { display: none; }
            .notification-dropdown { width: 320px; right: -20px; }
            .chart-wrapper { height: 250px; }
            .action-buttons { flex-direction: column; }
        }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search theses...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge"><?= $notificationCount > 0 ? $notificationCount : '' ?></span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <span class="mark-all-read" id="markAllReadBtn">Mark all as read</span>
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
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>" data-thesis-id="<?= $notif['thesis_id'] ?>">
                                    <div class="notif-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time">
                                            <i class="far fa-clock"></i> 
                                            <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer">
                        <a href="notification.php">View all notifications <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">RESEARCH ADVISER</div>
        </div>
        <div class="nav-menu">
            <a href="facultyDashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span>
                <?php if ($pendingCount > 0): ?><span style="margin-left: auto; background: #ff6b6b; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="notification.php" class="nav-item"><i class="fas fa-bell"></i><span>Notifications</span>
                <?php if ($notificationCount > 0): ?><span style="margin-left: auto; background: #ff6b6b; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;"><?= $notificationCount ?></span><?php endif; ?>
            </a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <span>Light Mode</span>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>Research Adviser Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($first_name) ?>!</p>
            </div>
            <div class="faculty-info">
                <div class="faculty-name"><?= htmlspecialchars($fullName) ?></div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= number_format($pendingCount) ?></h3><p>Pending Review</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-content"><h3><?= number_format($approvedCount) ?></h3><p>Approved</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-content"><h3><?= number_format($rejectedCount) ?></h3><p>Rejected</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-content"><h3><?= number_format($archivedCount) ?></h3><p>Archived</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-share"></i></div><div class="stat-content"><h3><?= number_format($forwardedCount) ?></h3><p>Forwarded to Dean</p></div></div>
        </div>

        <!-- Chart Section -->
        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Thesis Submission Trends (Last 7 Months)</h3>
            <div class="chart-wrapper">
                <canvas id="submissionChart"></canvas>
            </div>
        </div>

        <!-- Pending Theses Section - UPDATED: Review button only -->
        <div class="theses-card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Theses Waiting for Review</h3>
            </div>
            <?php if (empty($pendingTheses)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No pending theses to review. Great job!</p>
                </div>
            <?php else: ?>
                <div class="theses-list">
                    <?php foreach ($pendingTheses as $thesis): ?>
                    <div class="thesis-item">
                        <div class="thesis-info">
                            <div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div>
                            <div class="thesis-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span>
                                <span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></span>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="review-btn">
                                <i class="fas fa-chevron-right"></i> Review Thesis
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Submissions Table -->
        <div class="submissions-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> All Thesis Submissions</h3>
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-btn <?= $currentFilter == 'all' ? 'active' : '' ?>">All (<?= $totalCount ?>)</a>
                    <a href="?status=pending" class="filter-btn <?= $currentFilter == 'pending' ? 'active' : '' ?>">Pending (<?= $pendingCount ?>)</a>
                    <a href="?status=approved" class="filter-btn <?= $currentFilter == 'approved' ? 'active' : '' ?>">Approved (<?= $approvedCount ?>)</a>
                    <a href="?status=rejected" class="filter-btn <?= $currentFilter == 'rejected' ? 'active' : '' ?>">Rejected (<?= $rejectedCount ?>)</a>
                    <a href="?status=Forwarded_to_dean" class="filter-btn <?= $currentFilter == 'Forwarded_to_dean' ? 'active' : '' ?>">Forwarded (<?= $forwardedCount ?>)</a>
                </div>
            </div>
            <div class="table-responsive">
                <?php if (empty($allSubmissions)): ?>
                    <div class="empty-state"><i class="fas fa-folder-open"></i><p>No thesis submissions yet.</p></div>
                <?php else: ?>
                    <table class="theses-table">
                        <thead>
                            <tr><th>Thesis Title</th><th>Student</th><th>Date Submitted</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allSubmissions as $submission): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(substr($submission['title'], 0, 50)) . (strlen($submission['title']) > 50 ? '...' : '') ?></strong></td>
                                <td><?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($submission['date_submitted'])) ?></td>
                                <td><span class="status-badge <?= strtolower($submission['status']) ?>"><?= $submission['status'] == 'Forwarded_to_dean' ? 'Forwarded to Dean' : ucfirst($submission['status']) ?></span></td>
                                <td>
                                    <?php if ($submission['status'] == 'pending'): ?>
                                        <a href="reviewThesis.php?id=<?= $submission['thesis_id'] ?>" class="btn-review-small"><i class="fas fa-check-circle"></i> Review</a>
                                    <?php else: ?>
                                        <a href="reviewThesis.php?id=<?= $submission['thesis_id'] ?>" class="btn-view-small"><i class="fas fa-eye"></i> View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Prepare chart data from PHP
        const monthlyLabels = <?php 
            $labels = [];
            $pendingData = [];
            $approvedData = [];
            $rejectedData = [];
            foreach ($monthlyData as $month => $data) {
                $labels[] = $month;
                $pendingData[] = $data['pending'];
                $approvedData[] = $data['approved'];
                $rejectedData[] = $data['rejected'];
            }
            echo json_encode($labels);
        ?>;
        
        const pendingChartData = <?php echo json_encode($pendingData); ?>;
        const approvedChartData = <?php echo json_encode($approvedData); ?>;
        const rejectedChartData = <?php echo json_encode($rejectedData); ?>;
        
        let submissionChart;
        
        function initChart() {
            const ctx = document.getElementById('submissionChart').getContext('2d');
            const isDarkMode = document.body.classList.contains('dark-mode');
            const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
            const textColor = isDarkMode ? '#cbd5e1' : '#4a5568';
            
            submissionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: monthlyLabels,
                    datasets: [
                        { label: 'Pending', data: pendingChartData, borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#f59e0b', pointBorderColor: '#fff', pointBorderWidth: 2 },
                        { label: 'Approved', data: approvedChartData, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2 },
                        { label: 'Rejected', data: rejectedChartData, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#ef4444', pointBorderColor: '#fff', pointBorderWidth: 2 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: textColor, font: { size: 12, weight: '600' }, usePointStyle: true, boxWidth: 10 } },
                        tooltip: { mode: 'index', intersect: false, backgroundColor: isDarkMode ? '#1f2937' : '#ffffff', titleColor: isDarkMode ? '#f3f4f6' : '#1f2937', bodyColor: isDarkMode ? '#d1d5db' : '#4b5563', borderColor: '#dc2626', borderWidth: 1, callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + ctx.raw + ' thesis/es'; } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: gridColor }, title: { display: true, text: 'Number of Theses', color: textColor, font: { weight: '600', size: 12 } }, ticks: { stepSize: 1, color: textColor, callback: function(value) { return value + ' thesis'; } } },
                        x: { grid: { color: gridColor }, title: { display: true, text: 'Month', color: textColor, font: { weight: '600', size: 12 } }, ticks: { color: textColor } }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                }
            });
        }

        // Keep the functions but they won't be used (no buttons calling them)
        function forwardToDean(thesisId, thesisTitle) {
            // Function kept but no button uses it
        }

        function approveThesis(thesisId) {
            // Function kept but no button uses it
        }

        function rejectThesis(thesisId) {
            // Function kept but no button uses it
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = 'toast-message ' + type;
            toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message;
            document.body.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
        }

        const darkToggle = document.getElementById('darkmode');
        if (darkToggle) {
            darkToggle.addEventListener('change', () => {
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode', darkToggle.checked);
                updateChartColors();
            });
            if (localStorage.getItem('darkMode') === 'true') { darkToggle.checked = true; document.body.classList.add('dark-mode'); }
        }

        function updateChartColors() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
            const textColor = isDarkMode ? '#cbd5e1' : '#4a5568';
            if (submissionChart) {
                submissionChart.options.scales.y.grid.color = gridColor;
                submissionChart.options.scales.x.grid.color = gridColor;
                submissionChart.options.scales.y.ticks.color = textColor;
                submissionChart.options.scales.x.ticks.color = textColor;
                submissionChart.options.scales.y.title.color = textColor;
                submissionChart.options.scales.x.title.color = textColor;
                submissionChart.options.plugins.legend.labels.color = textColor;
                submissionChart.update();
            }
        }

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        function toggleSidebar() { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); }
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', toggleSidebar);

        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileWrapper) {
            profileWrapper.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        }
        document.addEventListener('click', (e) => {
            if (!profileWrapper?.contains(e.target) && profileDropdown) profileDropdown.classList.remove('show');
        });

        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationBadge = document.getElementById('notificationBadge');
        const notificationList = document.getElementById('notificationList');
        const markAllReadBtn = document.getElementById('markAllReadBtn');

        if (notificationIcon) {
            notificationIcon.addEventListener('click', function(e) { e.stopPropagation(); notificationDropdown.classList.toggle('show'); });
        }
        document.addEventListener('click', function(e) {
            if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) notificationDropdown.classList.remove('show');
        });

        function markAsRead(notifId, element) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_read=1&notif_id=' + notifId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    let currentCount = parseInt(notificationBadge.textContent) || 0;
                    if (currentCount > 0) {
                        currentCount--;
                        if (currentCount === 0) { notificationBadge.textContent = ''; notificationBadge.style.display = 'none'; if (markAllReadBtn) markAllReadBtn.style.display = 'none'; }
                        else { notificationBadge.textContent = currentCount; }
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
                    notificationBadge.textContent = ''; notificationBadge.style.display = 'none';
                    if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
        }

        if (notificationList) {
            notificationList.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                if (notificationItem && !notificationItem.classList.contains('empty')) {
                    const notifId = notificationItem.dataset.id;
                    const thesisId = notificationItem.dataset.thesisId;
                    if (notifId) markAsRead(notifId, notificationItem);
                    if (thesisId) setTimeout(() => { window.location.href = 'reviewThesis.php?id=' + thesisId; }, 300);
                }
            });
        }

        if (markAllReadBtn) markAllReadBtn.addEventListener('click', function(e) { e.stopPropagation(); markAllAsRead(); });
        if (notificationBadge && notificationBadge.textContent === '') notificationBadge.style.display = 'none';

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.theses-table tbody tr');
                rows.forEach(row => { row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none'; });
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() { initChart(); });
    </script>
</body>
</html>