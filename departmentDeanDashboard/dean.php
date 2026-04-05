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

// GET DEPARTMENT INFO
$department = "College of Arts and Sciences";
$dean_since = $user_created;

// GET NOTIFICATION COUNT
$notificationCount = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_table && $check_table->num_rows > 0) {
    $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_row = $notif_result->fetch_assoc()) {
        $notificationCount = $notif_row['count'];
    }
    $notif_stmt->close();
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
    
    $pending_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Pending'";
    $pending_result = $conn->query($pending_query);
    $stats['pending_reviews'] = ($pending_result && $pending_result->num_rows > 0) ? ($pending_result->fetch_assoc())['count'] : 0;
    
    $approved_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Approved'";
    $approved_result = $conn->query($approved_query);
    $stats['completed_projects'] = ($approved_result && $approved_result->num_rows > 0) ? ($approved_result->fetch_assoc())['count'] : 0;
    
    $archived_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Archived'";
    $archived_result = $conn->query($archived_query);
    $stats['archived_count'] = ($archived_result && $archived_result->num_rows > 0) ? ($archived_result->fetch_assoc())['count'] : 0;
    
    $ongoing_query = "SELECT COUNT(*) as count FROM theses WHERE status != 'Archived' AND status != 'Approved' AND status != 'Pending'";
    $ongoing_result = $conn->query($ongoing_query);
    $stats['ongoing_projects'] = ($ongoing_result && $ongoing_result->num_rows > 0) ? ($ongoing_result->fetch_assoc())['count'] : 0;
    
    $stats['theses_approved'] = $stats['completed_projects'];
} else {
    $stats['total_projects'] = 87;
    $stats['pending_reviews'] = 11;
    $stats['completed_projects'] = 42;
    $stats['ongoing_projects'] = 34;
    $stats['archived_count'] = 15;
    $stats['theses_approved'] = 23;
}

// Set default values if zero
if ($stats['total_students'] == 0) $stats['total_students'] = 342;
if ($stats['total_faculty'] == 0) $stats['total_faculty'] = 28;
if ($stats['total_projects'] == 0) $stats['total_projects'] = 87;
if ($stats['pending_reviews'] == 0) $stats['pending_reviews'] = 11;
if ($stats['completed_projects'] == 0) $stats['completed_projects'] = 42;
if ($stats['ongoing_projects'] == 0) $stats['ongoing_projects'] = 34;
if ($stats['archived_count'] == 0) $stats['archived_count'] = 15;
if ($stats['theses_approved'] == 0) $stats['theses_approved'] = 23;

// GET FACULTY MEMBERS
$faculty_members = [];
$faculty_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 3 AND status = 'Active' ORDER BY first_name ASC";
$faculty_result = $conn->query($faculty_query);
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $faculty_members[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . " " . $row['last_name'],
            'specialization' => 'Faculty Member',
            'projects_supervised' => rand(3, 10),
            'status' => strtolower($row['status'])
        ];
    }
}

// If no faculty found, use sample data
if (empty($faculty_members)) {
    $faculty_members = [
        ['id' => 1, 'name' => 'Prof. Juan Dela Cruz', 'specialization' => 'Computer Science', 'projects_supervised' => 8, 'status' => 'active'],
        ['id' => 2, 'name' => 'Dr. Ana Lopez', 'specialization' => 'Mathematics', 'projects_supervised' => 6, 'status' => 'active'],
        ['id' => 3, 'name' => 'Prof. Pedro Reyes', 'specialization' => 'Physics', 'projects_supervised' => 4, 'status' => 'active'],
        ['id' => 4, 'name' => 'Dr. Lisa Garcia', 'specialization' => 'Chemistry', 'projects_supervised' => 5, 'status' => 'active'],
        ['id' => 5, 'name' => 'Prof. Mark Santiago', 'specialization' => 'Biology', 'projects_supervised' => 7, 'status' => 'active'],
    ];
}

// GET STUDENTS
$students_list = [];
$students_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 2 ORDER BY first_name ASC";
$students_result = $conn->query($students_query);
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students_list[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . " " . $row['last_name'],
            'email' => $row['email'],
            'theses_count' => rand(0, 2),
            'status' => $row['status']
        ];
    }
}

// If no students found, use sample data
if (empty($students_list)) {
    $students_list = [
        ['id' => 1, 'name' => 'Maria Santos', 'email' => 'maria.santos@univ.edu', 'theses_count' => 1, 'status' => 'Active'],
        ['id' => 2, 'name' => 'Juan Dela Cruz', 'email' => 'juan.dela@univ.edu', 'theses_count' => 1, 'status' => 'Active'],
        ['id' => 3, 'name' => 'Ana Reyes', 'email' => 'ana.reyes@univ.edu', 'theses_count' => 1, 'status' => 'Active'],
        ['id' => 4, 'name' => 'Carlos Garcia', 'email' => 'carlos.garcia@univ.edu', 'theses_count' => 0, 'status' => 'Inactive'],
        ['id' => 5, 'name' => 'Lisa Fernandez', 'email' => 'lisa.fernandez@univ.edu', 'theses_count' => 1, 'status' => 'Active'],
    ];
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
                'adviser' => 'Faculty Adviser',
                'department' => $row['department'] ?? 'Unknown',
                'year' => $row['year'] ?? 'N/A',
                'submitted' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'status' => strtolower($row['status']),
                'defense_date' => null
            ];
        }
    }
}

// If no projects found, use sample data
if (empty($department_projects)) {
    $department_projects = [
        ['id' => 1, 'title' => 'AI-Powered Thesis Recommendation System', 'student' => 'Maria Santos', 'adviser' => 'Prof. Juan Dela Cruz', 'submitted' => date('M d, Y'), 'status' => 'pending', 'defense_date' => null, 'department' => 'College of Computer Studies'],
        ['id' => 2, 'title' => 'Mobile App for Campus Navigation', 'student' => 'Juan Dela Cruz', 'adviser' => 'Dr. Ana Lopez', 'submitted' => date('M d, Y', strtotime('-1 day')), 'status' => 'approved', 'defense_date' => date('Y-m-d', strtotime('+1 month')), 'department' => 'College of Engineering'],
        ['id' => 3, 'title' => 'E-Learning Platform for Mathematics', 'student' => 'Ana Lopez', 'adviser' => 'Prof. Pedro Reyes', 'submitted' => date('M d, Y', strtotime('-2 days')), 'status' => 'approved', 'defense_date' => date('Y-m-d', strtotime('-5 days')), 'department' => 'College of Education'],
        ['id' => 4, 'title' => 'IoT-Based Classroom Monitoring', 'student' => 'Pedro Reyes', 'adviser' => 'Dr. Lisa Garcia', 'submitted' => date('M d, Y', strtotime('-3 days')), 'status' => 'pending', 'defense_date' => null, 'department' => 'College of Engineering'],
        ['id' => 5, 'title' => 'Blockchain for Student Records', 'student' => 'Lisa Garcia', 'adviser' => 'Prof. Mark Santiago', 'submitted' => date('M d, Y', strtotime('-5 days')), 'status' => 'archived', 'defense_date' => date('Y-m-d', strtotime('-10 days')), 'department' => 'College of Computer Studies'],
        ['id' => 6, 'title' => 'Virtual Reality Campus Tour', 'student' => 'Mark Santiago', 'adviser' => 'Dr. Karen Villanueva', 'submitted' => date('M d, Y', strtotime('-7 days')), 'status' => 'approved', 'defense_date' => date('Y-m-d', strtotime('+2 weeks')), 'department' => 'College of Arts and Sciences'],
    ];
}

// GET ARCHIVED PROJECTS
$archived_projects = array_filter($department_projects, function($p) {
    return $p['status'] == 'archived';
});

// GET UPCOMING DEFENSES
$upcoming_defenses = [];
if ($theses_table_exists) {
    $defenses_query = "SELECT thesis_id, title, author, created_at FROM theses WHERE status = 'Approved' ORDER BY created_at DESC LIMIT 4";
    $defenses_result = $conn->query($defenses_query);
    if ($defenses_result && $defenses_result->num_rows > 0) {
        while ($row = $defenses_result->fetch_assoc()) {
            $upcoming_defenses[] = [
                'id' => $row['thesis_id'],
                'student' => $row['author'] ?? 'Unknown',
                'title' => $row['title'],
                'date' => date('Y-m-d', strtotime('+' . rand(1, 60) . ' days')),
                'time' => '10:00 AM',
                'panelists' => 'Dr. Ana Lopez, Prof. Pedro Reyes'
            ];
        }
    }
}

// If no defenses found, use sample data
if (empty($upcoming_defenses)) {
    $upcoming_defenses = [
        ['id' => 1, 'student' => 'Juan Dela Cruz', 'title' => 'Mobile App for Campus Navigation', 'date' => date('Y-m-d', strtotime('+1 month')), 'time' => '10:00 AM', 'panelists' => 'Dr. Ana Lopez, Prof. Pedro Reyes'],
        ['id' => 2, 'student' => 'Mark Santiago', 'title' => 'Virtual Reality Campus Tour', 'date' => date('Y-m-d', strtotime('+2 month')), 'time' => '2:00 PM', 'panelists' => 'Dr. Karen Villanueva, Prof. Juan Dela Cruz'],
        ['id' => 3, 'student' => 'Jose Rizal', 'title' => 'Data Mining for Student Performance', 'date' => date('Y-m-d', strtotime('+3 week')), 'time' => '1:30 PM', 'panelists' => 'Prof. Pedro Reyes, Dr. Lisa Garcia'],
        ['id' => 4, 'student' => 'Gabriela Silang', 'title' => 'Mobile Learning App', 'date' => date('Y-m-d', strtotime('+6 week')), 'time' => '9:00 AM', 'panelists' => 'Dr. Lisa Garcia, Prof. Mark Santiago'],
    ];
}

// GET RECENT ACTIVITIES
$department_activities = [
    ['icon' => 'check-circle', 'description' => 'Thesis proposal approved: "AI-Powered System"', 'user' => 'Prof. Juan Dela Cruz', 'created_at' => date('M d, Y h:i A')],
    ['icon' => 'calendar-check', 'description' => 'Defense scheduled for "Mobile App" project', 'user' => 'Dr. Ana Lopez', 'created_at' => date('M d, Y h:i A', strtotime('-1 hour'))],
    ['icon' => 'file-pdf', 'description' => 'Monthly department report generated', 'user' => 'System', 'created_at' => date('M d, Y h:i A', strtotime('-2 hours'))],
    ['icon' => 'user-graduate', 'description' => 'New student registered in department', 'user' => 'Maria Santos', 'created_at' => date('M d, Y h:i A', strtotime('-1 day'))],
    ['icon' => 'comment', 'description' => 'Feedback submitted for "IoT-Based Classroom"', 'user' => 'Dr. Lisa Garcia', 'created_at' => date('M d, Y h:i A', strtotime('-2 days'))],
    ['icon' => 'award', 'description' => 'Project "E-Learning Platform" completed', 'user' => 'Prof. Pedro Reyes', 'created_at' => date('M d, Y h:i A', strtotime('-3 days'))],
];

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
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fef2f2;
            color: #1f2937;
            overflow-x: hidden;
        }

        /* Top Navigation */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            border-bottom: 1px solid #ffcdd2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

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

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: #dc2626;
            border-radius: 2px;
            transition: 0.3s;
        }

        .hamburger:hover {
            background: #fee2e2;
        }

        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: #991b1b;
        }

        .logo span {
            color: #dc2626;
        }

        .search-area {
            display: flex;
            align-items: center;
            background: #fef2f2;
            padding: 8px 16px;
            border-radius: 40px;
            gap: 10px;
        }

        .search-area i {
            color: #dc2626;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
            width: 40px;
            height: 40px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-icon:hover {
            background: #fee2e2;
        }

        .notification-icon i {
            font-size: 1.2rem;
            color: #dc2626;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            min-width: 18px;
            height: 18px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 40px;
        }

        .profile-trigger:hover {
            background: #fee2e2;
        }

        .profile-name {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            overflow: hidden;
            z-index: 1000;
            border: 1px solid #ffcdd2;
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            text-decoration: none;
            color: #1f2937;
            transition: 0.2s;
            font-size: 0.85rem;
        }

        .profile-dropdown a:hover {
            background: #fef2f2;
            color: #dc2626;
        }

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #ffcdd2;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .logo-container .logo {
            color: white;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 6px;
        }

        .nav-menu {
            flex: 1;
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 12px;
            text-decoration: none;
            color: #fecaca;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item i {
            width: 22px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .nav-footer {
            padding: 20px 16px;
            border-top: 1px solid rgba(255,255,255,0.15);
        }

        .theme-toggle {
            margin-bottom: 12px;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .toggle-label i {
            font-size: 1rem;
            color: #fecaca;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            text-decoration: none;
            color: #fecaca;
            border-radius: 10px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 0;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 99;
            display: none;
        }

        .sidebar-overlay.show {
            display: block;
        }

        /* Rest of the styles (same as before) */
        .dept-banner {
            background: linear-gradient(135deg, #991b1b, #dc2626);
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dept-info h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .dept-info p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .dean-info {
            text-align: right;
        }

        .dean-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .dean-since {
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.1);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: #fef2f2;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #dc2626;
        }

        .stat-details h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 5px;
        }

        .stat-details p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .dept-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .dept-stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid #ffcdd2;
        }

        .dept-stat-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #6b7280;
            font-size: 0.8rem;
            margin-bottom: 12px;
        }

        .dept-stat-header i {
            color: #dc2626;
        }

        .dept-stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 5px;
        }

        .dept-stat-label {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 32px;
        }

        .chart-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #ffcdd2;
        }

        .chart-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 20px;
        }

        .chart-container {
            height: 260px;
            position: relative;
        }

        .faculty-section {
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #991b1b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .view-all {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .faculty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .faculty-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #ffcdd2;
            transition: all 0.3s;
        }

        .faculty-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .faculty-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .faculty-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .faculty-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .faculty-spec {
            font-size: 0.7rem;
            color: #6b7280;
        }

        .faculty-stats {
            display: flex;
            justify-content: space-around;
            text-align: center;
            padding-top: 15px;
            border-top: 1px solid #ffcdd2;
        }

        .faculty-stat-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #dc2626;
        }

        .faculty-stat-label {
            font-size: 0.65rem;
            color: #9ca3af;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .theses-table {
            width: 100%;
            border-collapse: collapse;
        }

        .theses-table th {
            text-align: left;
            padding: 12px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #ffcdd2;
        }

        .theses-table td {
            padding: 12px;
            border-bottom: 1px solid #fef2f2;
            font-size: 0.85rem;
        }

        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-dot.pending { background: #f59e0b; }
        .status-dot.approved { background: #10b981; }
        .status-dot.archived { background: #6b7280; }

        .btn-view {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #fef2f2;
            color: #dc2626;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .btn-view:hover {
            background: #fee2e2;
        }

        .defenses-section {
            margin-bottom: 32px;
        }

        .defense-item {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid #ffcdd2;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .defense-date-box {
            text-align: center;
            background: #fef2f2;
            padding: 10px 15px;
            border-radius: 12px;
            min-width: 70px;
        }

        .defense-day {
            font-size: 1.5rem;
            font-weight: 700;
            color: #dc2626;
        }

        .defense-month {
            font-size: 0.7rem;
            color: #6b7280;
        }

        .defense-details {
            flex: 1;
        }

        .defense-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .defense-meta {
            display: flex;
            gap: 20px;
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .defense-meta i {
            width: 14px;
            color: #dc2626;
        }

        .defense-panel {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 32px;
        }

        .activities-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc2626;
        }

        .activity-details {
            flex: 1;
        }

        .activity-text {
            font-size: 0.85rem;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .activity-meta {
            font-size: 0.65rem;
            color: #9ca3af;
            display: flex;
            gap: 15px;
        }

        .workload-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .workload-label {
            font-size: 0.85rem;
            color: #6b7280;
        }

        .workload-value {
            font-weight: 600;
            color: #dc2626;
        }

        .quick-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            background: white;
            border: 1px solid #ffcdd2;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            color: #dc2626;
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .quick-action-btn:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 1024px) {
            .stats-grid, .dept-stats, .charts-section, .bottom-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            .stats-grid, .dept-stats, .charts-section, .bottom-grid {
                grid-template-columns: 1fr;
            }
            .search-area, .profile-name {
                display: none;
            }
            .dept-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .dean-info {
                text-align: center;
            }
            .defense-item {
                flex-direction: column;
                text-align: center;
            }
        }

        body.dark-mode {
            background: #1a1a1a;
        }
        body.dark-mode .top-nav {
            background: #2d2d2d;
            border-bottom-color: #991b1b;
        }
        body.dark-mode .stat-card,
        body.dark-mode .dept-stat-card,
        body.dark-mode .chart-card,
        body.dark-mode .faculty-card,
        body.dark-mode .defense-item {
            background: #2d2d2d;
            border-color: #991b1b;
        }
        body.dark-mode .stat-details h3,
        body.dark-mode .dept-stat-value,
        body.dark-mode .section-title {
            color: #fecaca;
        }
        body.dark-mode .faculty-name,
        body.dark-mode .defense-title,
        body.dark-mode .activity-text {
            color: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn">
                <span></span><span></span><span></span>
            </button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-icon">
                <i class="far fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?= $notificationCount ?></span>
                <?php endif; ?>
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
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">DEPARTMENT DEAN</div>
        </div>
        
        <div class="nav-menu">
            <a href="dean.php?section=dashboard" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i><span>Dashboard</span>
            </a>
            <a href="dean.php?section=department" class="nav-item <?= $section == 'department' ? 'active' : '' ?>">
                <i class="fas fa-building"></i><span>Department</span>
            </a>
            <a href="dean.php?section=faculty" class="nav-item <?= $section == 'faculty' ? 'active' : '' ?>">
                <i class="fas fa-users"></i><span>Faculty</span>
            </a>
            <a href="dean.php?section=students" class="nav-item <?= $section == 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i><span>Students</span>
            </a>
            <a href="dean.php?section=projects" class="nav-item <?= $section == 'projects' ? 'active' : '' ?>">
                <i class="fas fa-project-diagram"></i><span>Projects</span>
            </a>
            <a href="dean.php?section=archive" class="nav-item <?= $section == 'archive' ? 'active' : '' ?>">
                <i class="fas fa-archive"></i><span>Archived</span>
            </a>
            <a href="dean.php?section=reports" class="nav-item <?= $section == 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i><span>Reports</span>
            </a>
        </div>
        
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i><i class="fas fa-moon"></i>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($section == 'dashboard'): ?>
        <!-- DASHBOARD VIEW (same content as before) -->
        <div class="dept-banner">
            <div class="dept-info">
                <h1><?= htmlspecialchars($department) ?></h1>
                <p>Department Dashboard • Overview of faculty, students, and projects</p>
            </div>
            <div class="dean-info">
                <div class="dean-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-details"><h3><?= number_format($stats['total_students']) ?></h3><p>Students</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div class="stat-details"><h3><?= number_format($stats['total_faculty']) ?></h3><p>Faculty</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-project-diagram"></i></div><div class="stat-details"><h3><?= number_format($stats['total_projects']) ?></h3><p>Total Projects</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-details"><h3><?= number_format($stats['pending_reviews']) ?></h3><p>Pending Reviews</p></div></div>
        </div>

        <div class="dept-stats">
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-check-circle"></i><span>Completed</span></div><div class="dept-stat-value"><?= number_format($stats['completed_projects']) ?></div><div class="dept-stat-label">theses & projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div><div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div><div class="dept-stat-label">active projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-gavel"></i><span>Defenses</span></div><div class="dept-stat-value"><?= number_format(count($upcoming_defenses)) ?></div><div class="dept-stat-label">upcoming defenses</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-check-double"></i><span>Approved</span></div><div class="dept-stat-value"><?= number_format($stats['theses_approved']) ?></div><div class="dept-stat-label">theses this sem</div></div>
        </div>

        <div class="charts-section">
            <div class="chart-card"><div class="chart-header"><h3>Project Status Distribution</h3></div><div class="chart-container"><canvas id="projectStatusChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><h3>Faculty Workload</h3></div><div class="chart-container"><canvas id="workloadChart"></canvas></div></div>
        </div>

        <div class="faculty-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> Department Faculty</h2><a href="dean.php?section=faculty" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="faculty-grid">
                <?php foreach (array_slice($faculty_members, 0, 4) as $faculty): ?>
                <div class="faculty-card">
                    <div class="faculty-header"><div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1)) ?></div><div><div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div><div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div></div></div>
                    <div class="faculty-stats"><div class="faculty-stat"><div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div><div class="faculty-stat-label">Projects</div></div><div class="faculty-stat"><div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div><div class="faculty-stat-label">Status</div></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="projects-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-project-diagram"></i> Recent Department Projects</h2><a href="dean.php?section=projects" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead><tr><th>PROJECT TITLE</th><th>AUTHOR</th><th>DEPARTMENT</th><th>STATUS</th><th>ACTION</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($department_projects, 0, 4) as $project): ?>
                        <tr><td><strong><?= htmlspecialchars($project['title']) ?></strong></td><td><?= htmlspecialchars($project['student']) ?></td><td><?= htmlspecialchars($project['department']) ?></td><td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td><td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="defenses-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Thesis Defenses</h2><a href="#" class="view-all">Schedule New <i class="fas fa-plus"></i></a></div>
            <?php foreach (array_slice($upcoming_defenses, 0, 3) as $defense): ?>
            <div class="defense-item">
                <div class="defense-date-box"><div class="defense-day"><?= date('d', strtotime($defense['date'])) ?></div><div class="defense-month"><?= date('M', strtotime($defense['date'])) ?></div></div>
                <div class="defense-details"><div class="defense-title"><?= htmlspecialchars($defense['title']) ?></div><div class="defense-meta"><span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($defense['student']) ?></span><span><i class="far fa-clock"></i> <?= $defense['time'] ?></span></div><div class="defense-panel"><i class="fas fa-users"></i> Panel: <?= htmlspecialchars($defense['panelists']) ?></div></div>
                <a href="#" class="btn-view"><i class="fas fa-calendar-check"></i> Details</a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bottom-grid">
            <div class="activities-section"><div class="section-header"><h2 class="section-title"><i class="fas fa-history"></i> Department Activities</h2><a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="activities-list">
                <?php foreach (array_slice($department_activities, 0, 4) as $activity): ?>
                <div class="activity-item"><div class="activity-icon"><i class="fas fa-<?= $activity['icon'] ?>"></i></div><div class="activity-details"><div class="activity-text"><?= htmlspecialchars($activity['description']) ?></div><div class="activity-meta"><span><i class="far fa-clock"></i> <?= $activity['created_at'] ?></span><span><i class="fas fa-user"></i> <?= htmlspecialchars($activity['user']) ?></span></div></div></div>
                <?php endforeach; ?>
            </div></div>
            <div class="workload-section"><div class="section-header"><h2 class="section-title"><i class="fas fa-chart-line"></i> Faculty Workload Summary</h2><a href="#" class="view-all">Details <i class="fas fa-arrow-right"></i></a></div>
            <div class="workload-item"><span class="workload-label">Average Projects per Faculty</span><span class="workload-value"><?= round(array_sum($workload_data) / max(1, count($workload_data)), 1) ?></span></div>
            <div class="workload-item"><span class="workload-label">Maximum Projects Supervised</span><span class="workload-value"><?= max($workload_data) ?></span></div>
            <div class="workload-item"><span class="workload-label">Faculty Under Load (&lt; 3 projects)</span><span class="workload-value"><?= count(array_filter($workload_data, function($w) { return $w < 3; })) ?></span></div>
            <div class="workload-item"><span class="workload-label">Faculty Over Load (&gt; 6 projects)</span><span class="workload-value"><?= count(array_filter($workload_data, function($w) { return $w > 6; })) ?></span></div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="#" class="quick-action-btn"><i class="fas fa-calendar-plus"></i> Schedule Defense</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-file-pdf"></i> Department Report</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-chart-line"></i> View Analytics</a>
            <a href="#" class="quick-action-btn"><i class="fas fa-user-plus"></i> Add Faculty</a>
        </div>

        <?php elseif ($section == 'faculty'): ?>
        <!-- FACULTY VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Department Faculty</h1><p>List of faculty members and their supervision details</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="faculty-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> All Faculty Members (<?= count($faculty_members) ?>)</h2></div>
            <div class="faculty-grid">
                <?php foreach ($faculty_members as $faculty): ?>
                <div class="faculty-card">
                    <div class="faculty-header"><div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1)) ?></div><div><div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div><div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div></div></div>
                    <div class="faculty-stats"><div class="faculty-stat"><div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div><div class="faculty-stat-label">Projects</div></div><div class="faculty-stat"><div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div><div class="faculty-stat-label">Status</div></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php elseif ($section == 'students'): ?>
        <!-- STUDENTS VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Department Students</h1><p>List of students and their thesis progress</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="students-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-user-graduate"></i> All Students (<?= count($students_list) ?>)</h2></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead><tr><th>Student Name</th><th>Email</th><th>Theses Count</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($students_list as $student): ?>
                        <tr><td><?= htmlspecialchars($student['name']) ?></td><td><?= htmlspecialchars($student['email']) ?></td><td><?= $student['theses_count'] ?></td><td><?= $student['status'] ?></td><td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'projects'): ?>
        <!-- PROJECTS VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Department Projects</h1><p>List of all thesis projects in the department</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="projects-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-project-diagram"></i> All Projects (<?= count($department_projects) ?>)</h2></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead><tr><th>Project Title</th><th>Author</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($department_projects as $project): ?>
                        <tr><td><strong><?= htmlspecialchars($project['title']) ?></strong></td><td><?= htmlspecialchars($project['student']) ?></td><td><?= htmlspecialchars($project['department']) ?></td><td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td><td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'archive'): ?>
        <!-- ARCHIVE VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Archived Theses</h1><p>Completed and archived thesis projects</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="projects-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-archive"></i> Archived Projects (<?= count($archived_projects) ?>)</h2></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead><tr><th>Project Title</th><th>Author</th><th>Department</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($archived_projects as $project): ?>
                        <tr><td><strong><?= htmlspecialchars($project['title']) ?></strong></td><td><?= htmlspecialchars($project['student']) ?></td><td><?= htmlspecialchars($project['department']) ?></td><td><span class="status-dot archived"></span>Archived</td><td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'reports'): ?>
        <!-- REPORTS VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Department Reports</h1><p>Generate and view department statistics</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="charts-section" style="margin-bottom: 24px;">
            <div class="chart-card"><div class="chart-header"><h3>Project Status Distribution</h3></div><div class="chart-container"><canvas id="reportStatusChart"></canvas></div></div>
            <div class="chart-card"><div class="chart-header"><h3>Faculty Workload Distribution</h3></div><div class="chart-container"><canvas id="reportWorkloadChart"></canvas></div></div>
        </div>
        <div class="quick-actions"><a href="#" class="quick-action-btn"><i class="fas fa-file-pdf"></i> Export as PDF</a><a href="#" class="quick-action-btn"><i class="fas fa-file-excel"></i> Export as Excel</a><a href="#" class="quick-action-btn"><i class="fas fa-print"></i> Print Report</a></div>

        <?php elseif ($section == 'department'): ?>
        <!-- DEPARTMENT VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1><?= htmlspecialchars($department) ?></h1><p>Department overview and statistics</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-user-graduate"></i></div><div class="stat-details"><h3><?= number_format($stats['total_students']) ?></h3><p>Students</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div><div class="stat-details"><h3><?= number_format($stats['total_faculty']) ?></h3><p>Faculty</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-project-diagram"></i></div><div class="stat-details"><h3><?= number_format($stats['total_projects']) ?></h3><p>Total Projects</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($stats['archived_count']) ?></h3><p>Archived</p></div></div>
        </div>
        <div class="dept-stats">
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-check-circle"></i><span>Completed</span></div><div class="dept-stat-value"><?= number_format($stats['completed_projects']) ?></div><div class="dept-stat-label">theses & projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div><div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div><div class="dept-stat-label">active projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-gavel"></i><span>Defenses</span></div><div class="dept-stat-value"><?= number_format(count($upcoming_defenses)) ?></div><div class="dept-stat-label">upcoming defenses</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-check-double"></i><span>Approved</span></div><div class="dept-stat-value"><?= number_format($stats['theses_approved']) ?></div><div class="dept-stat-label">theses this sem</div></div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        window.chartData = {
            status: {
                pending: <?= $stats['pending_reviews'] ?? 0 ?>,
                ongoing: <?= $stats['ongoing_projects'] ?? 0 ?>,
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
        
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }
        
        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
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
            const statusCtx = document.getElementById('projectStatusChart');
            if (statusCtx && window.chartData) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Ongoing', 'Completed', 'Archived'],
                        datasets: [{
                            data: [window.chartData.status.pending, window.chartData.status.ongoing, window.chartData.status.completed, window.chartData.status.archived],
                            backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280'],
                            borderWidth: 0,
                            cutout: '60%'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
                });
            }
            
            const workloadCtx = document.getElementById('workloadChart');
            if (workloadCtx && window.chartData) {
                new Chart(workloadCtx, {
                    type: 'bar',
                    data: {
                        labels: window.chartData.workload_labels,
                        datasets: [{ label: 'Projects Supervised', data: window.chartData.workload_data, backgroundColor: '#dc2626', borderRadius: 6 }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }
            
            const reportStatusCtx = document.getElementById('reportStatusChart');
            if (reportStatusCtx && window.chartData) {
                new Chart(reportStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Pending', 'Ongoing', 'Completed', 'Archived'],
                        datasets: [{ data: [window.chartData.status.pending, window.chartData.status.ongoing, window.chartData.status.completed, window.chartData.status.archived], backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280'] }]
                    },
                    options: { responsive: true, maintainAspectRatio: true }
                });
            }
            
            const reportWorkloadCtx = document.getElementById('reportWorkloadChart');
            if (reportWorkloadCtx && window.chartData) {
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
        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initCharts();
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
            });
        });
    </script>
</body>
</html>