<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN AND IS A DEAN
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// GET LOGGED-IN USER INFO FROM SESSION
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET ACTIVE SECTION FROM URL
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

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

// GET DEPARTMENT INFO FROM DEPARTMENT_TABLE (walay dean_name)
$department_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 1;
$department_name = "College of Arts and Sciences";
$department_code = "CAS";

$dept_query = "SELECT department_id, department_name, department_code FROM department_table WHERE department_id = ?";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->bind_param("i", $department_id);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
if ($dept_row = $dept_result->fetch_assoc()) {
    $department_name = $dept_row['department_name'];
    $department_code = $dept_row['department_code'];
}
$dept_stmt->close();

$dean_since = $user_created;

// CREATE NOTIFICATIONS TABLE IF NOT EXISTS
$check_notif_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if (!$check_notif_table || $check_notif_table->num_rows == 0) {
    $create_notif_table = "
        CREATE TABLE IF NOT EXISTS notifications (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $conn->query($create_notif_table);
}

// Determine the correct ID column name
$id_column = 'notification_id';
$check_id_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'id'");
if ($check_id_col && $check_id_col->num_rows > 0) {
    $id_column = 'id';
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

// GET RECENT NOTIFICATIONS FOR DROPDOWN
$recentNotifications = [];
$notif_list_query = "SELECT $id_column as id, user_id, thesis_id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    if ($row['thesis_id']) {
        $thesis_q = $conn->prepare("SELECT title FROM theses WHERE thesis_id = ?");
        $thesis_q->bind_param("i", $row['thesis_id']);
        $thesis_q->execute();
        $thesis_result = $thesis_q->get_result();
        if ($thesis_row = $thesis_result->fetch_assoc()) {
            $row['thesis_title'] = $thesis_row['title'];
        }
        $thesis_q->close();
    }
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();

// MARK NOTIFICATION AS READ (via AJAX)
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update_query = "UPDATE notifications SET is_read = 1 WHERE $id_column = ? AND user_id = ?";
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

// GET STATISTICS FROM DATABASE
$stats = [];

// Total students
$students_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 2 AND status = 'Active'";
$students_result = $conn->query($students_query);
$stats['total_students'] = ($students_result && $students_result->num_rows > 0) ? ($students_result->fetch_assoc())['count'] : 0;

// Total faculty
$faculty_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 3 AND status = 'Active'";
$faculty_result = $conn->query($faculty_query);
$stats['total_faculty'] = ($faculty_result && $faculty_result->num_rows > 0) ? ($faculty_result->fetch_assoc())['count'] : 0;

// Check if theses table exists
$theses_table_exists = false;
$check_theses = $conn->query("SHOW TABLES LIKE 'theses'");
if ($check_theses && $check_theses->num_rows > 0) {
    $theses_table_exists = true;
    
    $projects_query = "SELECT COUNT(*) as count FROM theses";
    $projects_result = $conn->query($projects_query);
    $stats['total_projects'] = ($projects_result && $projects_result->num_rows > 0) ? ($projects_result->fetch_assoc())['count'] : 0;
    
    // COMPLETED = Approved theses
    $completed_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Approved'";
    $completed_result = $conn->query($completed_query);
    $stats['completed_projects'] = ($completed_result && $completed_result->num_rows > 0) ? ($completed_result->fetch_assoc())['count'] : 0;
    
    // ONGOING = Pending theses (waiting for approval)
    $ongoing_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Pending'";
    $ongoing_result = $conn->query($ongoing_query);
    $stats['ongoing_projects'] = ($ongoing_result && $ongoing_result->num_rows > 0) ? ($ongoing_result->fetch_assoc())['count'] : 0;
    
    // DEFENSES = Upcoming defenses (if may defense_date)
    $check_defense_col = $conn->query("SHOW COLUMNS FROM theses LIKE 'defense_date'");
    $has_defense_date = ($check_defense_col && $check_defense_col->num_rows > 0);
    if ($has_defense_date) {
        $defenses_query = "SELECT COUNT(*) as count FROM theses WHERE defense_date IS NOT NULL AND defense_date >= CURDATE()";
        $defenses_result = $conn->query($defenses_query);
        $stats['upcoming_defenses'] = ($defenses_result && $defenses_result->num_rows > 0) ? ($defenses_result->fetch_assoc())['count'] : 0;
    } else {
        $stats['upcoming_defenses'] = 0;
    }
    
    // Archived count
    $archived_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Archived'";
    $archived_result = $conn->query($archived_query);
    $stats['archived_count'] = ($archived_result && $archived_result->num_rows > 0) ? ($archived_result->fetch_assoc())['count'] : 0;
    
    // Pending reviews
    $stats['pending_reviews'] = $stats['ongoing_projects'];
    $stats['theses_approved'] = $stats['completed_projects'];
} else {
    $stats['total_projects'] = 0;
    $stats['pending_reviews'] = 0;
    $stats['completed_projects'] = 0;
    $stats['ongoing_projects'] = 0;
    $stats['archived_count'] = 0;
    $stats['theses_approved'] = 0;
    $stats['upcoming_defenses'] = 0;
}

// GET FACULTY MEMBERS
$faculty_members = [];
$faculty_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 3 AND status = 'Active' ORDER BY first_name ASC";
$faculty_result = $conn->query($faculty_query);
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $project_count = 0;
        if ($theses_table_exists) {
            $proj_q = $conn->prepare("SELECT COUNT(*) as c FROM theses WHERE submitted_by = ? OR author = ?");
            $name = $row['first_name'] . " " . $row['last_name'];
            $proj_q->bind_param("is", $row['user_id'], $name);
            $proj_q->execute();
            $proj_result = $proj_q->get_result();
            $project_count = $proj_result->fetch_assoc()['c'] ?? 0;
            $proj_q->close();
        }
        $faculty_members[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . " " . $row['last_name'],
            'specialization' => 'Faculty Member',
            'projects_supervised' => $project_count,
            'status' => strtolower($row['status'])
        ];
    }
}

// GET STUDENTS
$students_list = [];
$students_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 2 ORDER BY first_name ASC";
$students_result = $conn->query($students_query);
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $theses_count = 0;
        if ($theses_table_exists) {
            $thesis_q = $conn->prepare("SELECT COUNT(*) as c FROM theses WHERE author = ? OR author_id = ?");
            $name = $row['first_name'] . " " . $row['last_name'];
            $thesis_q->bind_param("si", $name, $row['user_id']);
            $thesis_q->execute();
            $thesis_result = $thesis_q->get_result();
            $theses_count = $thesis_result->fetch_assoc()['c'] ?? 0;
            $thesis_q->close();
        }
        $students_list[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . " " . $row['last_name'],
            'email' => $row['email'],
            'theses_count' => $theses_count,
            'status' => $row['status']
        ];
    }
}

// GET DEPARTMENT PROJECTS
$department_projects = [];
if ($theses_table_exists) {
    $projects_query = "SELECT thesis_id, title, author, department, year, status, created_at FROM theses ORDER BY created_at DESC LIMIT 10";
    $projects_result = $conn->query($projects_query);
    if ($projects_result && $projects_result->num_rows > 0) {
        while ($row = $projects_result->fetch_assoc()) {
            $department_projects[] = [
                'id' => $row['thesis_id'],
                'title' => $row['title'],
                'student' => $row['author'] ?? 'Unknown',
                'adviser' => 'N/A',
                'department' => $row['department'] ?? $department_name,
                'year' => $row['year'] ?? 'N/A',
                'submitted' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'status' => strtolower($row['status']),
                'defense_date' => null
            ];
        }
    }
}

// GET ARCHIVED PROJECTS
$archived_projects = array_filter($department_projects, function($p) {
    return $p['status'] == 'archived';
});

// GET UPCOMING DEFENSES
$upcoming_defenses = [];
if ($theses_table_exists) {
    $check_defense_col = $conn->query("SHOW COLUMNS FROM theses LIKE 'defense_date'");
    $has_defense_date = ($check_defense_col && $check_defense_col->num_rows > 0);
    
    if ($has_defense_date) {
        $defenses_query = "SELECT thesis_id, title, author, defense_date, defense_time, panelists FROM theses WHERE defense_date IS NOT NULL AND defense_date >= CURDATE() ORDER BY defense_date ASC LIMIT 5";
        $defenses_result = $conn->query($defenses_query);
        if ($defenses_result && $defenses_result->num_rows > 0) {
            while ($row = $defenses_result->fetch_assoc()) {
                $upcoming_defenses[] = [
                    'id' => $row['thesis_id'],
                    'student' => $row['author'] ?? 'Unknown',
                    'title' => $row['title'],
                    'date' => $row['defense_date'],
                    'time' => $row['defense_time'] ?? 'TBA',
                    'panelists' => $row['panelists'] ?? 'To be announced'
                ];
            }
        }
    }
}

// GET RECENT ACTIVITIES
$department_activities = [];
$check_activities = $conn->query("SHOW TABLES LIKE 'department_activities'");
if ($check_activities && $check_activities->num_rows > 0) {
    $activities_query = "SELECT icon, description, user_name, created_at FROM department_activities WHERE department_id = ? ORDER BY created_at DESC LIMIT 6";
    $activities_stmt = $conn->prepare($activities_query);
    $activities_stmt->bind_param("i", $department_id);
    $activities_stmt->execute();
    $activities_result = $activities_stmt->get_result();
    if ($activities_result && $activities_result->num_rows > 0) {
        while ($row = $activities_result->fetch_assoc()) {
            $department_activities[] = [
                'icon' => $row['icon'] ?? 'bell',
                'description' => $row['description'],
                'user' => $row['user_name'] ?? 'System',
                'created_at' => date('M d, Y h:i A', strtotime($row['created_at']))
            ];
        }
    }
    $activities_stmt->close();
}

// FACULTY WORKLOAD DATA FOR CHART
$workload_labels = [];
$workload_data = [];
foreach ($faculty_members as $faculty) {
    $workload_labels[] = explode(' ', $faculty['name'])[0];
    $workload_data[] = $faculty['projects_supervised'];
}

$pageTitle = "Department Dean Dashboard";
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }

        .top-nav { position: fixed; top: 0; left: 0; right: 0; height: 70px; background: white; display: flex; align-items: center; justify-content: space-between; padding: 0 32px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); z-index: 99; border-bottom: 1px solid #ffcdd2; }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; transition: 0.3s; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 200px; }
        
        .nav-right { display: flex; align-items: center; gap: 20px; position: relative; }
        
        .notification-container { position: relative; }
        .notification-icon { position: relative; cursor: pointer; width: 40px; height: 40px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .notification-icon:hover { background: #fee2e2; }
        .notification-icon i { font-size: 1.2rem; color: #dc2626; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 600; min-width: 18px; height: 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
        
        .notification-dropdown { position: absolute; top: 55px; right: 0; width: 380px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; overflow: hidden; z-index: 1000; border: 1px solid #ffcdd2; animation: fadeSlideDown 0.2s ease; }
        .notification-dropdown.show { display: block; }
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notification-header { padding: 16px 20px; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; }
        .notification-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin: 0; }
        .mark-all-read { font-size: 0.7rem; color: #dc2626; cursor: pointer; background: none; border: none; }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { display: flex; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #fef2f2; cursor: pointer; transition: background 0.2s; }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-item.empty { justify-content: center; color: #9ca3af; cursor: default; }
        .notif-icon { width: 36px; height: 36px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; flex-shrink: 0; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.8rem; color: #1f2937; margin-bottom: 4px; line-height: 1.4; }
        .notif-time { font-size: 0.65rem; color: #9ca3af; }
        .notification-footer { padding: 12px 20px; border-top: 1px solid #fee2e2; text-align: center; }
        .notification-footer a { color: #dc2626; text-decoration: none; font-size: 0.8rem; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 10px; border-radius: 40px; }
        .profile-trigger:hover { background: #fee2e2; }
        .profile-name { font-weight: 500; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #991b1b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 1000; border: 1px solid #ffcdd2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; transition: 0.2s; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        .profile-dropdown hr { margin: 5px 0; border-color: #ffcdd2; }
        
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex; flex-direction: column; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar.open { transform: translateX(0); }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 6px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; transition: all 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 12px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
        .dept-banner { background: linear-gradient(135deg, #991b1b, #dc2626); border-radius: 24px; padding: 32px; margin-bottom: 32px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .dept-info h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 8px; }
        .dept-info p { opacity: 0.9; font-size: 0.9rem; }
        .dean-info { text-align: right; }
        .dean-name { font-size: 1rem; font-weight: 600; margin-bottom: 4px; }
        .dean-since { font-size: 0.7rem; opacity: 0.8; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 18px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(220, 38, 38, 0.1); }
        .stat-icon { width: 55px; height: 55px; background: #fef2f2; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #dc2626; }
        .stat-details h3 { font-size: 1.8rem; font-weight: 700; color: #991b1b; margin-bottom: 5px; }
        .stat-details p { font-size: 0.8rem; color: #6b7280; }
        
        .dept-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 32px; }
        .dept-stat-card { background: white; border-radius: 20px; padding: 20px; text-align: center; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .dept-stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .dept-stat-header { display: flex; align-items: center; justify-content: center; gap: 8px; color: #6b7280; font-size: 0.8rem; margin-bottom: 12px; }
        .dept-stat-header i { color: #dc2626; }
        .dept-stat-value { font-size: 2rem; font-weight: 700; color: #991b1b; margin-bottom: 5px; }
        .dept-stat-label { font-size: 0.7rem; color: #9ca3af; }
        
        .charts-section { display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px; margin-bottom: 32px; }
        .chart-card { background: white; border-radius: 24px; padding: 24px; border: 1px solid #ffcdd2; transition: all 0.2s; display: flex; flex-direction: column; }
        .chart-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .chart-card h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; justify-content: center; text-align: center; }
        .chart-container { height: 300px; position: relative; width: 100%; display: flex; justify-content: center; align-items: center; }
        .chart-container canvas { max-width: 100%; max-height: 100%; margin: 0 auto; display: block; }
        .status-labels { display: flex; justify-content: center; gap: 24px; margin-top: 20px; flex-wrap: wrap; padding-top: 16px; border-top: 1px solid #fee2e2; }
        .status-label-item { display: flex; align-items: center; gap: 8px; font-size: 0.75rem; color: #6b7280; font-weight: 500; }
        .status-color { width: 12px; height: 12px; border-radius: 50%; }
        .status-color.pending { background: #f59e0b; }
        .status-color.completed { background: #10b981; }
        .status-color.archived { background: #6b7280; }
        
        .faculty-section { margin-bottom: 32px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 1.2rem; font-weight: 600; color: #991b1b; display: flex; align-items: center; gap: 10px; }
        .view-all { color: #dc2626; text-decoration: none; font-size: 0.85rem; font-weight: 500; }
        .faculty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .faculty-card { background: white; border-radius: 20px; padding: 20px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .faculty-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .faculty-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
        .faculty-avatar { width: 50px; height: 50px; background: linear-gradient(135deg, #dc2626, #991b1b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 1.1rem; }
        .faculty-name { font-weight: 600; color: #1f2937; margin-bottom: 4px; }
        .faculty-spec { font-size: 0.7rem; color: #6b7280; }
        .faculty-stats { display: flex; justify-content: space-around; text-align: center; padding-top: 15px; border-top: 1px solid #ffcdd2; }
        .faculty-stat-value { font-size: 1.2rem; font-weight: 700; color: #dc2626; }
        .faculty-stat-label { font-size: 0.65rem; color: #9ca3af; }
        
        .table-responsive { overflow-x: auto; }
        .theses-table { width: 100%; border-collapse: collapse; }
        .theses-table th { text-align: left; padding: 12px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #ffcdd2; }
        .theses-table td { padding: 12px; border-bottom: 1px solid #fef2f2; font-size: 0.85rem; }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; }
        .status-dot.pending { background: #f59e0b; }
        .status-dot.approved { background: #10b981; }
        .status-dot.archived { background: #6b7280; }
        
        .btn-view { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; background: #fef2f2; color: #dc2626; text-decoration: none; border-radius: 20px; font-size: 0.7rem; font-weight: 500; }
        .btn-view:hover { background: #fee2e2; }
        
        .defenses-section { margin-bottom: 32px; }
        .defense-item { background: white; border-radius: 16px; padding: 20px; margin-bottom: 16px; border: 1px solid #ffcdd2; display: flex; align-items: center; gap: 20px; }
        .defense-date-box { text-align: center; background: #fef2f2; padding: 10px 15px; border-radius: 12px; min-width: 70px; }
        .defense-day { font-size: 1.5rem; font-weight: 700; color: #dc2626; }
        .defense-month { font-size: 0.7rem; color: #6b7280; }
        .defense-details { flex: 1; }
        .defense-title { font-weight: 600; color: #1f2937; margin-bottom: 8px; }
        .defense-meta { display: flex; gap: 20px; font-size: 0.75rem; color: #6b7280; margin-bottom: 5px; }
        .defense-meta i { width: 14px; color: #dc2626; }
        .defense-panel { font-size: 0.7rem; color: #9ca3af; }
        
        .bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 32px; }
        .activities-list { display: flex; flex-direction: column; gap: 12px; }
        .activity-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #fef2f2; }
        .activity-icon { width: 32px; height: 32px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; }
        .activity-details { flex: 1; }
        .activity-text { font-size: 0.85rem; color: #1f2937; margin-bottom: 4px; }
        .activity-meta { font-size: 0.65rem; color: #9ca3af; display: flex; gap: 15px; }
        
        .workload-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #fef2f2; }
        .workload-label { font-size: 0.85rem; color: #6b7280; }
        .workload-value { font-weight: 600; color: #dc2626; }
        
        .quick-actions { display: flex; gap: 16px; flex-wrap: wrap; }
        .quick-action-btn { background: white; border: 1px solid #ffcdd2; padding: 10px 20px; border-radius: 40px; text-decoration: none; color: #dc2626; font-size: 0.85rem; font-weight: 500; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .quick-action-btn:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        
        @media (max-width: 1024px) { .stats-grid, .dept-stats, .charts-section, .bottom-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .stats-grid, .dept-stats, .charts-section, .bottom-grid { grid-template-columns: 1fr; }
            .search-area, .profile-name { display: none; }
            .dept-banner { flex-direction: column; text-align: center; gap: 15px; }
            .dean-info { text-align: center; }
            .defense-item { flex-direction: column; text-align: center; }
            .notification-dropdown { width: 320px; right: -10px; }
            .chart-container { height: 250px; }
        }
        @media (max-width: 480px) { 
            .notification-dropdown { width: 300px; right: -5px; }
            .chart-container { height: 220px; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav, body.dark-mode .stat-card, body.dark-mode .dept-stat-card, body.dark-mode .chart-card, body.dark-mode .faculty-card, body.dark-mode .defense-item, body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .stat-details h3, body.dark-mode .dept-stat-value, body.dark-mode .section-title, body.dark-mode .notification-header h3 { color: #fecaca; }
        body.dark-mode .faculty-name, body.dark-mode .defense-title, body.dark-mode .activity-text, body.dark-mode .notif-message { color: #e5e7eb; }
        body.dark-mode .notification-item:hover { background: #3d3d3d; }
        body.dark-mode .notification-item.unread { background: #3a2a2a; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <button class="mark-all-read" id="markAllReadBtn">Mark all as read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty"><div class="notif-icon"><i class="far fa-bell-slash"></i></div><div class="notif-content"><div class="notif-message">No notifications yet</div></div></div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>">
                                    <div class="notif-icon"><?php if(strpos($notif['message'], 'approved') !== false) echo '<i class="fas fa-check-circle"></i>'; elseif(strpos($notif['message'], 'forwarded') !== false) echo '<i class="fas fa-arrow-right"></i>'; elseif(strpos($notif['message'], 'revision') !== false) echo '<i class="fas fa-edit"></i>'; elseif(strpos($notif['message'], 'archived') !== false) echo '<i class="fas fa-archive"></i>'; else echo '<i class="fas fa-bell"></i>'; ?></div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time"><i class="far fa-clock"></i> <?php $date = new DateTime($notif['created_at']); $now = new DateTime(); $diff = $now->diff($date); if($diff->days == 0) echo 'Today, ' . $date->format('h:i A'); elseif($diff->days == 1) echo 'Yesterday, ' . $date->format('h:i A'); else echo $date->format('M d, Y h:i A'); ?></div>
                                        <?php if (isset($notif['thesis_title'])): ?>
                                            <div class="notif-thesis"><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer"><a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a></div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">DEPARTMENT DEAN</div></div>
        <div class="nav-menu">
            <a href="dean.php?section=dashboard&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="dean.php?section=department&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'department' ? 'active' : '' ?>"><i class="fas fa-building"></i><span>Department</span></a>
            <a href="dean.php?section=faculty&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'faculty' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Faculty</span></a>
            <a href="dean.php?section=students&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'students' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i><span>Students</span></a>
            <a href="dean.php?section=projects&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'projects' ? 'active' : '' ?>"><i class="fas fa-project-diagram"></i><span>Projects</span></a>
            <a href="dean.php?section=archive&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'archive' ? 'active' : '' ?>"><i class="fas fa-archive"></i><span>Archived</span></a>
            <a href="dean.php?section=reports&dept_id=<?= $department_id ?>" class="nav-item <?= $section == 'reports' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
            <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i><span>Notifications</span><?php if ($notificationCount > 0): ?><span style="background:#ef4444; color:white; border-radius:50%; padding:2px 6px; font-size:0.7rem; margin-left:auto;"><?= $notificationCount ?></span><?php endif; ?></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($section == 'dashboard'): ?>
        <!-- DASHBOARD VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>Department Dashboard • Overview of faculty, students, and projects</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-details"><h3><?= number_format($stats['total_students']) ?></h3><p>Students</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div class="stat-details"><h3><?= number_format($stats['total_faculty']) ?></h3><p>Faculty</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-project-diagram"></i></div><div class="stat-details"><h3><?= number_format($stats['total_projects']) ?></h3><p>Total Projects</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-details"><h3><?= number_format($stats['pending_reviews']) ?></h3><p>Pending Reviews</p></div></div>
        </div>

        <!-- Department Stats: Completed, Ongoing, Defenses, Total -->
        <div class="dept-stats">
            <div class="dept-stat-card">
                <div class="dept-stat-header"><i class="fas fa-check-circle"></i><span>Completed</span></div>
                <div class="dept-stat-value"><?= number_format($stats['completed_projects']) ?></div>
                <div class="dept-stat-label">theses & projects</div>
            </div>
            <div class="dept-stat-card">
                <div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div>
                <div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div>
                <div class="dept-stat-label">active projects</div>
            </div>
            <div class="dept-stat-card">
                <div class="dept-stat-header"><i class="fas fa-gavel"></i><span>Defenses</span></div>
                <div class="dept-stat-value"><?= number_format($stats['upcoming_defenses']) ?></div>
                <div class="dept-stat-label">upcoming defenses</div>
            </div>
            <div class="dept-stat-card">
                <div class="dept-stat-header"><i class="fas fa-chart-simple"></i><span>Total</span></div>
                <div class="dept-stat-value"><?= number_format($stats['total_projects']) ?></div>
                <div class="dept-stat-label">total projects</div>
            </div>
        </div>

        <!-- Rest of the dashboard content (same as before) -->
        <div class="charts-section">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Project Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="projectStatusChart"></canvas>
                </div>
                <div class="status-labels">
                    <div class="status-label-item"><span class="status-color pending"></span><span>Pending (<?= $stats['ongoing_projects'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color completed"></span><span>Completed (<?= $stats['completed_projects'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color archived"></span><span>Archived (<?= $stats['archived_count'] ?>)</span></div>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-bar"></i> Faculty Workload</h3>
                <div class="chart-container">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>
        </div>

        <div class="faculty-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> Department Faculty</h2><a href="dean.php?section=faculty&dept_id=<?= $department_id ?>" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="faculty-grid">
                <?php if (count($faculty_members) > 0): ?>
                    <?php foreach (array_slice($faculty_members, 0, 4) as $faculty): ?>
                    <div class="faculty-card">
                        <div class="faculty-header"><div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . (strpos($faculty['name'], ' ') !== false ? substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1) : '')) ?></div><div><div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div><div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div></div></div>
                        <div class="faculty-stats"><div class="faculty-stat"><div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div><div class="faculty-stat-label">Projects</div></div><div class="faculty-stat"><div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div><div class="faculty-stat-label">Status</div></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-users"></i><p>No faculty members found</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="projects-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-project-diagram"></i> Recent Department Projects</h2><a href="dean.php?section=projects&dept_id=<?= $department_id ?>" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive">
                <?php if (count($department_projects) > 0): ?>
                <table class="theses-table">
                    <thead><tr><th>PROJECT TITLE</th><th>AUTHOR</th><th>DEPARTMENT</th><th>STATUS</th><th>ACTION</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($department_projects, 0, 4) as $project): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['department']) ?></td>
                            <td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td>
                            <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="defenses-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Thesis Defenses</h2><a href="#" class="view-all">Schedule New <i class="fas fa-plus"></i></a></div>
            <?php if (count($upcoming_defenses) > 0): ?>
                <?php foreach (array_slice($upcoming_defenses, 0, 3) as $defense): ?>
                <div class="defense-item">
                    <div class="defense-date-box"><div class="defense-day"><?= date('d', strtotime($defense['date'])) ?></div><div class="defense-month"><?= date('M', strtotime($defense['date'])) ?></div></div>
                    <div class="defense-details"><div class="defense-title"><?= htmlspecialchars($defense['title']) ?></div><div class="defense-meta"><span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($defense['student']) ?></span><span><i class="far fa-clock"></i> <?= $defense['time'] ?></span></div><div class="defense-panel"><i class="fas fa-users"></i> Panel: <?= htmlspecialchars($defense['panelists']) ?></div></div>
                    <a href="#" class="btn-view"><i class="fas fa-calendar-check"></i> Details</a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-calendar-alt"></i><p>No upcoming defenses scheduled</p></div>
            <?php endif; ?>
        </div>

        <div class="bottom-grid">
            <div class="activities-section">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-history"></i> Department Activities</h2><a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
                <?php if (count($department_activities) > 0): ?>
                <div class="activities-list">
                    <?php foreach (array_slice($department_activities, 0, 4) as $activity): ?>
                    <div class="activity-item"><div class="activity-icon"><i class="fas fa-<?= $activity['icon'] ?>"></i></div><div class="activity-details"><div class="activity-text"><?= htmlspecialchars($activity['description']) ?></div><div class="activity-meta"><span><i class="far fa-clock"></i> <?= $activity['created_at'] ?></span><span><i class="fas fa-user"></i> <?= htmlspecialchars($activity['user']) ?></span></div></div></div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                    <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-clock"></i><p>No recent activities</p></div>
                <?php endif; ?>
            </div>
            <div class="workload-section">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-line"></i> Faculty Workload Summary</h2><a href="#" class="view-all">Details <i class="fas fa-arrow-right"></i></a></div>
                <?php if (count($workload_data) > 0): ?>
                <div class="workload-item"><span class="workload-label">Average Projects per Faculty</span><span class="workload-value"><?= round(array_sum($workload_data) / max(1, count($workload_data)), 1) ?></span></div>
                <div class="workload-item"><span class="workload-label">Maximum Projects Supervised</span><span class="workload-value"><?= max($workload_data) ?></span></div>
                <div class="workload-item"><span class="workload-label">Faculty Under Load (&lt; 3 projects)</span><span class="workload-value"><?= count(array_filter($workload_data, function($w) { return $w < 3; })) ?></span></div>
                <div class="workload-item"><span class="workload-label">Faculty Over Load (&gt; 6 projects)</span><span class="workload-value"><?= count(array_filter($workload_data, function($w) { return $w > 6; })) ?></span></div>
                <?php else: ?>
                    <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-chart-line"></i><p>No faculty workload data available</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="quick-actions">
            <a href="#" class="quick-action-btn"><i class="fas fa-calendar-plus"></i> Schedule Defense</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-file-pdf"></i> Department Report</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-chart-line"></i> View Analytics</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-user-plus"></i> Add Faculty</a>
        </div>

        <?php elseif ($section == 'faculty'): ?>
        <!-- FACULTY VIEW (simplified for brevity) -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>List of faculty members and their supervision details</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="faculty-section" style="margin-top:0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> All Faculty Members (<?= count($faculty_members) ?>)</h2></div>
            <div class="faculty-grid">
                <?php foreach ($faculty_members as $faculty): ?>
                <div class="faculty-card">
                    <div class="faculty-header"><div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . (strpos($faculty['name'], ' ') !== false ? substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1) : '')) ?></div><div><div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div><div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div></div></div>
                    <div class="faculty-stats"><div class="faculty-stat"><div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div><div class="faculty-stat-label">Projects</div></div><div class="faculty-stat"><div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div><div class="faculty-stat-label">Status</div></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php elseif ($section == 'students'): ?>
        <!-- STUDENTS VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>List of students and their thesis progress</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="students-section" style="margin-top:0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-user-graduate"></i> All Students (<?= count($students_list) ?>)</h2></div>
            <?php if (count($students_list) > 0): ?>
            <div class="table-responsive">
                <table class="theses-table"><thead><tr><th>Student Name</th><th>Email</th><th>Theses Count</th><th>Status</th><th>Action</th></tr></thead>
                <tbody><?php foreach ($students_list as $student): ?><tr>
                    <td><?= htmlspecialchars($student['name']) ?></td>
                    <td><?= htmlspecialchars($student['email']) ?></td>
                    <td><?= $student['theses_count'] ?></td>
                    <td><?= $student['status'] ?></td>
                    <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
            <?php endif; ?>
        </div>

        <?php elseif ($section == 'projects'): ?>
        <!-- PROJECTS VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>List of all thesis projects in the department</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="projects-section" style="margin-top:0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-project-diagram"></i> All Projects (<?= count($department_projects) ?>)</h2></div>
            <?php if (count($department_projects) > 0): ?>
            <div class="table-responsive">
                <table class="theses-table"><thead><tr><th>Project Title</th><th>Author</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
                <tbody><?php foreach ($department_projects as $project): ?><tr>
                    <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                    <td><?= htmlspecialchars($project['student']) ?></td>
                    <td><?= htmlspecialchars($project['department']) ?></td>
                    <td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td>
                    <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
            <?php endif; ?>
        </div>

        <?php elseif ($section == 'archive'): ?>
        <!-- ARCHIVE VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>Completed and archived thesis projects</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="projects-section" style="margin-top:0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-archive"></i> Archived Projects (<?= count($archived_projects) ?>)</h2></div>
            <?php if (count($archived_projects) > 0): ?>
            <div class="table-responsive">
                <table class="theses-table"><thead><tr><th>Project Title</th><th>Author</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
                <tbody><?php foreach ($archived_projects as $project): ?><tr>
                    <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                    <td><?= htmlspecialchars($project['student']) ?></td>
                    <td><?= htmlspecialchars($project['department']) ?></td>
                    <td><span class="status-dot archived"></span>Archived</span></td>
                    <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                </tr><?php endforeach; ?></tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:40px; color:#9ca3af;"><i class="fas fa-archive"></i><p>No archived projects found</p></div>
            <?php endif; ?>
        </div>

        <?php elseif ($section == 'reports'): ?>
        <!-- REPORTS VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>Generate and view department statistics</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="charts-section" style="margin-bottom:24px;">
            <div class="chart-card"><div class="chart-header"><h3>Project Status Distribution</h3></div><div class="chart-container"><canvas id="reportStatusChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><h3>Faculty Workload Distribution</h3></div><div class="chart-container"><canvas id="reportWorkloadChart"></canvas></div></div>
        </div>
        <div class="quick-actions"><a href="#" class="quick-action-btn"><i class="fas fa-file-pdf"></i> Export as PDF</a><a href="#" class="quick-action-btn"><i class="fas fa-file-excel"></i> Export as Excel</a><a href="#" class="quick-action-btn"><i class="fas fa-print"></i> Print Report</a></div>

        <?php elseif ($section == 'department'): ?>
        <!-- DEPARTMENT VIEW -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department_name) ?> <span style="font-size: 1rem; opacity: 0.8;">(<?= htmlspecialchars($department_code) ?>)</span></h1>
                <p>Department overview and statistics</p>
            </div>
            <div class="dean-info">
                <div class="dean-name">Dean: <?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-details"><h3><?= number_format($stats['total_students']) ?></h3><p>Students</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div class="stat-details"><h3><?= number_format($stats['total_faculty']) ?></h3><p>Faculty</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-project-diagram"></i></div><div class="stat-details"><h3><?= number_format($stats['total_projects']) ?></h3><p>Total Projects</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($stats['archived_count']) ?></h3><p>Archived</p></div></div>
        </div>
        <div class="dept-stats">
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-check-circle"></i><span>Completed</span></div><div class="dept-stat-value"><?= number_format($stats['completed_projects']) ?></div><div class="dept-stat-label">theses & projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div><div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div><div class="dept-stat-label">active projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-gavel"></i><span>Defenses</span></div><div class="dept-stat-value"><?= number_format($stats['upcoming_defenses']) ?></div><div class="dept-stat-label">upcoming defenses</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-chart-simple"></i><span>Total</span></div><div class="dept-stat-value"><?= number_format($stats['total_projects']) ?></div><div class="dept-stat-label">total projects</div></div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        window.chartData = {
            status: {
                pending: <?= $stats['ongoing_projects'] ?? 0 ?>,
                completed: <?= $stats['completed_projects'] ?? 0 ?>,
                archived: <?= $stats['archived_count'] ?? 0 ?>
            },
            workload_labels: <?= json_encode($workload_labels) ?>,
            workload_data: <?= json_encode($workload_data) ?>
        };
        
        const hamburger = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
        function toggleSidebar() { sidebar.classList.toggle('open'); overlay.classList.toggle('show'); document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : ''; }
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleProfileDropdown(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show'); }
        function closeProfileDropdown(e) { if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); }
        function toggleNotificationDropdown(e) { e.stopPropagation(); notificationDropdown.classList.toggle('show'); if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); }
        function closeNotificationDropdown(e) { if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) notificationDropdown.classList.remove('show'); }
        
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
                    const badge = document.querySelector('.notification-badge');
                    if (badge) {
                        let c = parseInt(badge.textContent);
                        if (c > 0) { c--; if (c === 0) badge.style.display = 'none'; else badge.textContent = c; }
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
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                    if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function initNotifications() {
            document.querySelectorAll('.notification-item').forEach(item => {
                if (!item.classList.contains('empty')) {
                    item.addEventListener('click', function(e) {
                        if (e.target.closest('.notification-footer')) return;
                        const id = this.dataset.id;
                        if (id && this.classList.contains('unread')) markNotificationAsRead(id, this);
                    });
                }
            });
            if (markAllReadBtn) markAllReadBtn.addEventListener('click', function(e) { e.stopPropagation(); markAllAsRead(); });
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
        
        function initCharts() {
            const statusCtx = document.getElementById('projectStatusChart');
            if (statusCtx && window.chartData) {
                if (window.statusChartInstance) window.statusChartInstance.destroy();
                window.statusChartInstance = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Completed', 'Archived'],
                        datasets: [{
                            data: [window.chartData.status.pending, window.chartData.status.completed, window.chartData.status.archived],
                            backgroundColor: ['#f59e0b', '#10b981', '#6b7280'],
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
                        layout: { padding: { top: 10, bottom: 10, left: 10, right: 10 } }
                    }
                });
            }
            
            const workloadCtx = document.getElementById('workloadChart');
            if (workloadCtx && window.chartData && window.chartData.workload_labels.length > 0) {
                if (window.workloadChartInstance) window.workloadChartInstance.destroy();
                const maxValue = Math.max(...window.chartData.workload_data, 1);
                const yAxisMax = Math.ceil(maxValue * 1.2);
                window.workloadChartInstance = new Chart(workloadCtx, {
                    type: 'bar',
                    data: {
                        labels: window.chartData.workload_labels,
                        datasets: [{ label: 'Projects Supervised', data: window.chartData.workload_data, backgroundColor: '#dc2626', borderRadius: 6 }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, max: yAxisMax, ticks: { stepSize: 1, precision: 0 } } }
                    }
                });
            } else if (workloadCtx) {
                new Chart(workloadCtx, {
                    type: 'bar',
                    data: { labels: ['No Data'], datasets: [{ label: 'Projects Supervised', data: [0], backgroundColor: '#dc2626' }] },
                    options: { responsive: true, maintainAspectRatio: true }
                });
            }
            
            const reportStatusCtx = document.getElementById('reportStatusChart');
            if (reportStatusCtx && window.chartData) {
                new Chart(reportStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Pending', 'Completed', 'Archived'],
                        datasets: [{ data: [window.chartData.status.pending, window.chartData.status.completed, window.chartData.status.archived], backgroundColor: ['#f59e0b', '#10b981', '#6b7280'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: true }
                });
            }
            
            const reportWorkloadCtx = document.getElementById('reportWorkloadChart');
            if (reportWorkloadCtx && window.chartData && window.chartData.workload_labels.length > 0) {
                new Chart(reportWorkloadCtx, {
                    type: 'bar',
                    data: {
                        labels: window.chartData.workload_labels,
                        datasets: [{ label: 'Projects Supervised', data: window.chartData.workload_data, backgroundColor: '#dc2626' }]
                    },
                    options: { responsive: true, maintainAspectRatio: true }
                });
            }
        }
        
        if (hamburger) hamburger.addEventListener('click', toggleSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        if (profileWrapper) { profileWrapper.addEventListener('click', toggleProfileDropdown); document.addEventListener('click', closeProfileDropdown); }
        if (notificationIcon) { notificationIcon.addEventListener('click', toggleNotificationDropdown); document.addEventListener('click', closeNotificationDropdown); }
        
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initCharts();
            initNotifications();
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (sidebar.classList.contains('open')) closeSidebar();
                    if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
                    if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>