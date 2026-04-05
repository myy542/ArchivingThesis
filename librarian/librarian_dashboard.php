<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// LOGIN VALIDATION - CHECK IF USER IS LOGGED IN
// ============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// CHECK IF USER ROLE IS LIBRARIAN
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] == 'coordinator') {
            header("Location: /ArchivingThesis/coordinator/coordinatorDashboard.php");
        } elseif ($_SESSION['role'] == 'faculty') {
            header("Location: /ArchivingThesis/faculty/facultyDashboard.php");
        } elseif ($_SESSION['role'] == 'student') {
            header("Location: /ArchivingThesis/student/studentDashboard.php");
        } elseif ($_SESSION['role'] == 'dean') {
            header("Location: /ArchivingThesis/dean/deanDashboard.php");
        } else {
            header("Location: /ArchivingThesis/authentication/login.php");
        }
    } else {
        header("Location: /ArchivingThesis/authentication/login.php");
    }
    exit;
}

// ============================================
// GET LOGGED-IN USER INFO
// ============================================
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET ADDITIONAL USER DATA FROM DATABASE
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
} else {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}
$user_stmt->close();

// GET ACTIVE SECTION FROM URL
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// Get notification count
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

// Check if theses table exists
$theses_table_exists = false;
$check_theses = $conn->query("SHOW TABLES LIKE 'theses'");
if ($check_theses && $check_theses->num_rows > 0) {
    $theses_table_exists = true;
}

// Get all departments
$departments = [];
$dept_query = "SELECT department_id, department_name, department_code FROM department_table";
$dept_result = $conn->query($dept_query);
if ($dept_result && $dept_result->num_rows > 0) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// If no departments found, use sample
if (empty($departments)) {
    $departments = [
        ['department_id' => 1, 'department_name' => 'College of Computer Studies', 'department_code' => 'CCS'],
        ['department_id' => 2, 'department_name' => 'College of Engineering', 'department_code' => 'COE'],
        ['department_id' => 3, 'department_name' => 'College of Business Administration', 'department_code' => 'CBA'],
        ['department_id' => 4, 'department_name' => 'College of Arts and Sciences', 'department_code' => 'CAS'],
        ['department_id' => 5, 'department_name' => 'College of Education', 'department_code' => 'CED'],
    ];
}

// Get statistics
$stats = [
    'total_faculty' => 0,
    'total_students' => 0,
    'active_projects' => 0,
    'upcoming_defenses' => 4,
    'pending_reviews' => 0,
    'approved_this_sem' => 0,
    'forwarded_to_dean' => 15,
    'rejected' => 8,
    'archived' => 0
];

// Get total faculty
$faculty_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 3";
$faculty_result = $conn->query($faculty_query);
$stats['total_faculty'] = ($faculty_result && $faculty_result->num_rows > 0) ? ($faculty_result->fetch_assoc())['count'] : 28;

// Get total students
$students_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 2";
$students_result = $conn->query($students_query);
$stats['total_students'] = ($students_result && $students_result->num_rows > 0) ? ($students_result->fetch_assoc())['count'] : 342;

// Get theses statistics - FIXED: using correct column names
if ($theses_table_exists) {
    $active_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Pending' OR status = 'Approved'";
    $active_result = $conn->query($active_query);
    $stats['active_projects'] = ($active_result && $active_result->num_rows > 0) ? ($active_result->fetch_assoc())['count'] : 87;
    
    $pending_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Pending'";
    $pending_result = $conn->query($pending_query);
    $stats['pending_reviews'] = ($pending_result && $pending_result->num_rows > 0) ? ($pending_result->fetch_assoc())['count'] : 23;
    
    $approved_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Approved'";
    $approved_result = $conn->query($approved_query);
    $stats['approved_this_sem'] = ($approved_result && $approved_result->num_rows > 0) ? ($approved_result->fetch_assoc())['count'] : 15;
    
    $archived_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Archived'";
    $archived_result = $conn->query($archived_query);
    $stats['archived'] = ($archived_result && $archived_result->num_rows > 0) ? ($archived_result->fetch_assoc())['count'] : 45;
    
    $stats['forwarded_to_dean'] = $stats['pending_reviews'];
    $stats['rejected'] = 0;
} else {
    $stats['active_projects'] = 87;
    $stats['pending_reviews'] = 23;
    $stats['approved_this_sem'] = 15;
    $stats['forwarded_to_dean'] = 15;
    $stats['rejected'] = 8;
    $stats['archived'] = 45;
}

// Get theses ready for archiving - FIXED: using correct column names (title, author, department)
$ready_for_archive = [];
if ($theses_table_exists) {
    $archive_query = "SELECT thesis_id, title, author, department, year, status, created_at FROM theses WHERE status = 'Approved' ORDER BY created_at DESC LIMIT 10";
    $archive_result = $conn->query($archive_query);
    if ($archive_result && $archive_result->num_rows > 0) {
        while ($row = $archive_result->fetch_assoc()) {
            $ready_for_archive[] = [
                'id' => $row['thesis_id'],
                'title' => $row['title'],
                'author' => $row['author'] ?? 'Unknown',
                'department' => $row['department'] ?? 'Unknown',
                'year' => $row['year'] ?? 'N/A',
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y')
            ];
        }
    }
}

// If no ready for archive, use sample
if (empty($ready_for_archive)) {
    $ready_for_archive = [
        ['id' => 1, 'title' => 'AI-Powered Thesis Recommendation System', 'author' => 'Maria Santos', 'department' => 'College of Computer Studies', 'year' => '2025', 'date' => 'Mar 15, 2026'],
        ['id' => 2, 'title' => 'Mobile App for Campus Navigation', 'author' => 'Juan Dela Cruz', 'department' => 'College of Engineering', 'year' => '2025', 'date' => 'Mar 14, 2026'],
        ['id' => 3, 'title' => 'E-Learning Platform for Mathematics', 'author' => 'Ana Lopez', 'department' => 'College of Education', 'year' => '2025', 'date' => 'Mar 12, 2026'],
        ['id' => 4, 'title' => 'IoT-Based Classroom Monitoring', 'author' => 'Pedro Reyes', 'department' => 'College of Engineering', 'year' => '2025', 'date' => 'Mar 10, 2026'],
        ['id' => 5, 'title' => 'Blockchain for Student Records', 'author' => 'Lisa Garcia', 'department' => 'College of Computer Studies', 'year' => '2025', 'date' => 'Mar 8, 2026'],
    ];
}

// Get archived theses - FIXED: using correct column names
$archived_theses = [];
if ($theses_table_exists) {
    $archived_query = "SELECT thesis_id, title, author, department, year, created_at FROM theses WHERE status = 'Archived' ORDER BY created_at DESC LIMIT 10";
    $archived_result = $conn->query($archived_query);
    if ($archived_result && $archived_result->num_rows > 0) {
        while ($row = $archived_result->fetch_assoc()) {
            $archived_theses[] = [
                'id' => $row['thesis_id'],
                'title' => $row['title'],
                'author' => $row['author'] ?? 'Unknown',
                'department' => $row['department'] ?? 'Unknown',
                'year' => $row['year'] ?? 'N/A',
                'date' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y')
            ];
        }
    }
}

// If no archived theses, use sample
if (empty($archived_theses)) {
    $archived_theses = [
        ['id' => 101, 'title' => 'Machine Learning in Healthcare', 'author' => 'John Doe', 'department' => 'College of Computer Studies', 'year' => '2024', 'date' => 'Mar 1, 2026'],
        ['id' => 102, 'title' => 'Renewable Energy Solutions', 'author' => 'Jane Smith', 'department' => 'College of Engineering', 'year' => '2024', 'date' => 'Feb 28, 2026'],
        ['id' => 103, 'title' => 'Digital Marketing Strategies', 'author' => 'Mike Johnson', 'department' => 'College of Business Administration', 'year' => '2024', 'date' => 'Feb 25, 2026'],
        ['id' => 104, 'title' => 'Climate Change Impact', 'author' => 'Sarah Williams', 'department' => 'College of Arts and Sciences', 'year' => '2024', 'date' => 'Feb 20, 2026'],
        ['id' => 105, 'title' => 'Online Learning Effectiveness', 'author' => 'David Brown', 'department' => 'College of Education', 'year' => '2024', 'date' => 'Feb 15, 2026'],
    ];
}

// Get faculty workload
$faculty_workload = [
    ['name' => 'Dr. Maria Santos', 'projects' => 10, 'initials' => 'MS'],
    ['name' => 'Prof. Juan Cruz', 'projects' => 9, 'initials' => 'JC'],
    ['name' => 'Dr. Ana Reyes', 'projects' => 8, 'initials' => 'AR'],
    ['name' => 'Prof. Pedro Garcia', 'projects' => 7, 'initials' => 'PG'],
    ['name' => 'Dr. Lisa Villanueva', 'projects' => 6, 'initials' => 'LV']
];

// Monthly data for chart
$monthly_data = [3, 5, 8, 6, 10, 12, 8, 15, 18, 14, 20, 25];
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Recent activities
$recent_activities = [
    ['description' => 'New thesis submitted by John Doe', 'time' => '2 minutes ago'],
    ['description' => 'Thesis approved: "Machine Learning in Education"', 'time' => '1 hour ago'],
    ['description' => 'Feedback given on thesis by Dr. Santos', 'time' => '3 hours ago'],
    ['description' => 'New faculty account created', 'time' => '1 day ago'],
    ['description' => 'Thesis archived: "Web Development Framework"', 'time' => '2 days ago']
];

$pageTitle = "Librarian Dashboard";
$currentPage = basename($_SERVER['PHP_SELF']);
$conn->close();
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
            right: 0;
            left: 280px;
            height: 70px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            z-index: 99;
            transition: left 0.3s ease;
            border-bottom: 1px solid #fee2e2;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        /* HAMBURGER MENU - THREE LINES */
        .hamburger {
            display: none;
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
            transition: all 0.3s ease;
        }

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: #dc2626;
            border-radius: 2px;
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
            font-size: 0.9rem;
        }

        .search-area input {
            border: none;
            background: none;
            outline: none;
            font-size: 0.85rem;
            width: 200px;
            color: #1f2937;
        }

        .search-area input::placeholder {
            color: #9ca3af;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
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
            transition: all 0.2s;
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
            padding: 0 4px;
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
            transition: background 0.2s;
        }

        .profile-trigger:hover {
            background: #fee2e2;
        }

        .profile-name {
            font-weight: 500;
            color: #1f2937;
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
            cursor: pointer;
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
            border: 1px solid #fee2e2;
        }

        .profile-dropdown.show {
            display: block;
            animation: fadeSlideDown 0.2s ease;
        }

        @keyframes fadeSlideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .profile-dropdown a i {
            width: 20px;
            color: #6b7280;
        }

        .profile-dropdown hr {
            margin: 5px 0;
            border-color: #fee2e2;
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
            transition: transform 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }

        .logo-container {
            padding: 28px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
        }

        .logo-container .logo {
            color: white;
            font-size: 1.3rem;
        }

        .logo-container .logo span {
            color: #fecaca;
        }

        .logo-sub {
            font-size: 0.7rem;
            color: #fecaca;
            margin-top: 6px;
            letter-spacing: 1px;
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
            font-size: 1.1rem;
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
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.15);
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 32px;
            transition: margin-left 0.3s ease;
        }

        /* Welcome Header */
        .welcome-header {
            margin-bottom: 32px;
        }

        .welcome-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #991b1b;
            margin-bottom: 8px;
        }

        .welcome-header p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .role-badge {
            color: #dc2626;
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            border: 1px solid #fee2e2;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
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

        .chart-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .chart-container {
            position: relative;
            height: 260px;
            width: 100%;
            margin-bottom: 20px;
            flex-shrink: 0;
        }

        .status-labels {
            display: flex;
            justify-content: center;
            gap: 28px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #fee2e2;
        }

        .status-label-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: #6b7280;
        }

        .status-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-color.pending { background: #f59e0b; }
        .status-color.forwarded { background: #10b981; }
        .status-color.rejected { background: #ef4444; }

        .monthly-stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 6px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #fee2e2;
        }

        .month-item {
            text-align: center;
            font-size: 0.65rem;
        }

        .month-name {
            color: #6b7280;
            font-weight: 500;
        }

        .month-count {
            display: block;
            color: #dc2626;
            font-weight: 600;
            font-size: 0.75rem;
            margin-top: 2px;
        }

        /* Bottom Grid */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 32px;
        }

        .info-card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            border: 1px solid #fee2e2;
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid #fee2e2;
        }

        /* Faculty List */
        .faculty-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .faculty-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .faculty-item:last-child {
            border-bottom: none;
        }

        .faculty-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .faculty-avatar-small {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
        }

        .faculty-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 0.85rem;
        }

        .faculty-projects {
            background: #fef2f2;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            color: #dc2626;
        }

        /* Activity List */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #fef2f2;
        }

        .activity-item:last-child {
            border-bottom: none;
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
            font-size: 0.7rem;
        }

        .activity-details {
            flex: 1;
        }

        .activity-description {
            font-size: 0.85rem;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .activity-time {
            font-size: 0.65rem;
            color: #9ca3af;
        }

        /* Archive Section */
        .archive-section, .archived-section {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid #fee2e2;
        }

        .section-header {
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 5px;
        }

        .section-header p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .view-all {
            color: #dc2626;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .theses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .thesis-card {
            background: #fef2f2;
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s;
            border: 1px solid #fee2e2;
        }

        .thesis-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: #dc2626;
        }

        .thesis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .thesis-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #059669;
        }

        .thesis-info p {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .thesis-info i {
            width: 18px;
            color: #dc2626;
        }

        .thesis-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #fee2e2;
        }

        .btn-archive {
            width: 100%;
            padding: 8px 16px;
            background: #dc2626;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-archive:hover {
            background: #991b1b;
            transform: translateY(-2px);
        }

        .archived-table {
            width: 100%;
            border-collapse: collapse;
        }

        .archived-table th {
            text-align: left;
            padding: 12px 8px;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            border-bottom: 1px solid #fee2e2;
        }

        .archived-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #fef2f2;
            font-size: 0.85rem;
        }

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
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }

        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .department-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid #fee2e2;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .department-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            border-color: #dc2626;
        }

        .department-icon {
            width: 50px;
            height: 50px;
            background: #fef2f2;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .department-icon i {
            font-size: 1.5rem;
            color: #dc2626;
        }

        .department-info {
            flex: 1;
        }

        .department-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 3px;
        }

        .department-code {
            font-size: 0.7rem;
            color: #9ca3af;
        }

        .department-stats .stat-badge {
            background: #fef2f2;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            color: #dc2626;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 24px;
            width: 90%;
            max-width: 700px;
            max-height: 80vh;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid #fee2e2;
            background: white;
        }

        .modal-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #991b1b;
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            transition: color 0.2s;
        }

        .close:hover {
            color: #dc2626;
        }

        .modal-body {
            padding: 20px 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .theses-list-modal {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .thesis-item-modal {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #fef2f2;
            border-radius: 16px;
            transition: all 0.2s;
        }

        .thesis-item-modal:hover {
            background: #fee2e2;
        }

        .thesis-info-modal h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .thesis-info-modal p {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .thesis-info-modal i {
            width: 14px;
            color: #dc2626;
        }

        .thesis-actions-modal {
            display: flex;
            gap: 10px;
        }

        .thesis-actions-modal .btn-view {
            padding: 6px 12px;
            background: white;
            color: #dc2626;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .thesis-actions-modal .btn-archive {
            padding: 6px 12px;
            font-size: 0.7rem;
            width: auto;
        }

        .loading, .empty-state, .error-state {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .loading i, .empty-state i, .error-state i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #dc2626;
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

        /* =========================================== */
        /* MOBILE RESPONSIVE - HAMBURGER MENU */
        /* =========================================== */
        
        /* DESKTOP VIEW - Normal sidebar, no hamburger */
        @media (min-width: 769px) {
            .hamburger {
                display: none !important;
            }
            .sidebar {
                transform: translateX(0) !important;
                position: fixed;
            }
            .sidebar-overlay {
                display: none !important;
            }
        }
        
        /* MOBILE VIEW - Hide sidebar, show hamburger */
        @media (max-width: 768px) {
            .top-nav {
                left: 0;
                padding: 0 16px;
            }
            
            .hamburger {
                display: flex !important;
            }
            
            .sidebar {
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease;
                position: fixed;
                z-index: 1000;
            }
            
            .sidebar.open {
                transform: translateX(0) !important;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            
            .monthly-stats {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .search-area {
                display: none;
            }
            
            .profile-name {
                display: none;
            }
            
            .chart-container {
                height: 220px;
            }
            
            .theses-grid, .departments-grid {
                grid-template-columns: 1fr;
            }
            
            .thesis-item-modal {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .thesis-actions-modal {
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        @media (max-width: 480px) {
            .main-content { padding: 16px; }
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-header h1 { font-size: 1.3rem; }
            .chart-container { height: 200px; }
            .monthly-stats { grid-template-columns: repeat(3, 1fr); }
            .status-labels { gap: 16px; }
        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .top-nav {
            background: #2d2d2d;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .logo {
            color: #fecaca;
        }

        body.dark-mode .search-area {
            background: #3d3d3d;
        }

        body.dark-mode .search-area input {
            background: #3d3d3d;
            color: white;
        }

        body.dark-mode .profile-name {
            color: #fecaca;
        }

        body.dark-mode .profile-avatar {
            background: linear-gradient(135deg, #fecaca, #dc2626);
        }

        body.dark-mode .profile-dropdown {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }

        body.dark-mode .profile-dropdown a:hover {
            background: #3d3d3d;
        }

        body.dark-mode .stat-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .stat-number {
            color: #fecaca;
        }

        body.dark-mode .chart-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .chart-card h3 {
            color: #fecaca;
        }

        body.dark-mode .info-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .info-card h3 {
            color: #fecaca;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .faculty-item {
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .faculty-name {
            color: #e5e7eb;
        }

        body.dark-mode .activity-item {
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .activity-description {
            color: #e5e7eb;
        }

        body.dark-mode .archive-section,
        body.dark-mode .archived-section {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .thesis-card {
            background: #3d3d3d;
            border-color: #991b1b;
        }

        body.dark-mode .thesis-header h3 {
            color: #fecaca;
        }

        body.dark-mode .department-card {
            background: #2d2d2d;
            border-color: #991b1b;
        }

        body.dark-mode .department-info h3 {
            color: #fecaca;
        }

        body.dark-mode .modal-content {
            background: #2d2d2d;
        }

        body.dark-mode .modal-header {
            border-bottom-color: #991b1b;
        }

        body.dark-mode .thesis-item-modal {
            background: #3d3d3d;
        }

        body.dark-mode .thesis-info-modal h4 {
            color: #fecaca;
        }

        body.dark-mode .thesis-info-modal p {
            color: #9ca3af;
        }

        body.dark-mode .archived-table th {
            color: #9ca3af;
            border-bottom-color: #991b1b;
        }

        body.dark-mode .archived-table td {
            color: #e5e7eb;
            border-bottom-color: #3d3d3d;
        }

        body.dark-mode .archived-row:hover {
            background: #3d3d3d;
        }

        body.dark-mode .btn-view {
            background: #3d3d3d;
            color: #fecaca;
        }

        body.dark-mode .btn-view:hover {
            background: #4a4a4a;
        }

        body.dark-mode .btn-archive {
            background: #991b1b;
        }

        body.dark-mode .btn-archive:hover {
            background: #dc2626;
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
                <input type="text" id="searchInput" placeholder="Search faculty, activities...">
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
                    <div class="profile-avatar" id="profileAvatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="librarian_profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
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
            <div class="logo-sub">LIBRARIAN</div>
        </div>
        
        <div class="nav-menu">
            <a href="librarian_dashboard.php?section=dashboard" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="librarian_dashboard.php?section=archive" class="nav-item <?= $section == 'archive' ? 'active' : '' ?>">
                <i class="fas fa-archive"></i>
                <span>Archived</span>
            </a>
            <a href="librarian_dashboard.php?section=departments" class="nav-item <?= $section == 'departments' ? 'active' : '' ?>">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
        </div>
        
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($section == 'dashboard'): ?>
        <!-- DASHBOARD VIEW -->
        <div class="welcome-header">
            <h1>Welcome back, <?= htmlspecialchars($first_name) ?>!</h1>
            <p><span class="role-badge">LIBRARIAN</span> · Dashboard Overview</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?= $stats['total_faculty'] ?></div><div class="stat-label">TOTAL FACULTY</div></div>
            <div class="stat-card"><div class="stat-number"><?= $stats['total_students'] ?></div><div class="stat-label">TOTAL STUDENTS</div></div>
            <div class="stat-card"><div class="stat-number"><?= $stats['active_projects'] ?></div><div class="stat-label">ACTIVE PROJECTS</div></div>
            <div class="stat-card"><div class="stat-number"><?= $stats['upcoming_defenses'] ?></div><div class="stat-label">UPCOMING DEFENSES</div></div>
            <div class="stat-card"><div class="stat-number"><?= $stats['pending_reviews'] ?></div><div class="stat-label">PENDING REVIEWS</div></div>
            <div class="stat-card"><div class="stat-number"><?= $stats['approved_this_sem'] ?></div><div class="stat-label">APPROVED THIS SEM</div></div>
        </div>

        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Theses Status Distribution</h3>
                <div class="chart-container"><canvas id="statusChart"></canvas></div>
                <div class="status-labels">
                    <div class="status-label-item"><span class="status-color pending"></span><span>Pending Review (<?= $stats['pending_reviews'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color forwarded"></span><span>Forwarded to Dean (<?= $stats['forwarded_to_dean'] ?>)</span></div>
                    <div class="status-label-item"><span class="status-color rejected"></span><span>Rejected (<?= $stats['rejected'] ?>)</span></div>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> Monthly Thesis Submissions</h3>
                <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
                <div class="monthly-stats">
                    <?php for ($i = 0; $i < 12; $i++): ?>
                    <div class="month-item"><span class="month-name"><?= $months[$i] ?>:</span><span class="month-count"><?= $monthly_data[$i] ?></span></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="bottom-grid">
            <div class="info-card">
                <h3><i class="fas fa-chalkboard-user"></i> Faculty Workload</h3>
                <div class="faculty-list">
                    <?php foreach ($faculty_workload as $faculty): ?>
                    <div class="faculty-item"><div class="faculty-info"><div class="faculty-avatar-small"><?= $faculty['initials'] ?></div><span class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></span></div><span class="faculty-projects"><?= $faculty['projects'] ?> projects</span></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <div class="activity-list">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item"><div class="activity-icon"><i class="fas fa-circle"></i></div><div class="activity-details"><div class="activity-description"><?= htmlspecialchars($activity['description']) ?></div><div class="activity-time"><?= $activity['time'] ?></div></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php elseif ($section == 'archive'): ?>
        <!-- ARCHIVE VIEW -->
        <div class="welcome-header"><h1>Archive Theses</h1><p><span class="role-badge">LIBRARIAN</span> · Archive approved theses by department</p></div>

        <div class="archive-section">
            <div class="section-header"><h2><i class="fas fa-file-alt"></i> Theses Ready for Archiving</h2><p>These theses are approved and ready to be archived</p></div>
            <div class="theses-grid">
                <?php foreach ($ready_for_archive as $thesis): ?>
                <div class="thesis-card">
                    <div class="thesis-header"><h3><?= htmlspecialchars($thesis['title']) ?></h3><span class="status-badge approved">Approved</span></div>
                    <div class="thesis-info">
                        <p><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($thesis['author']) ?></p>
                        <p><i class="fas fa-building"></i> <?= htmlspecialchars($thesis['department']) ?></p>
                        <p><i class="fas fa-calendar"></i> <?= $thesis['date'] ?></p>
                    </div>
                    <div class="thesis-actions"><button class="btn-archive" onclick="archiveThesis(<?= $thesis['id'] ?>, '<?= htmlspecialchars($thesis['title']) ?>')"><i class="fas fa-archive"></i> Archive Thesis</button></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="archived-section">
            <div class="section-header"><h2><i class="fas fa-archive"></i> Recently Archived Theses</h2><a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive">
                <table class="archived-table">
                    <thead>
                        <tr><th>Thesis Title</th><th>Author</th><th>Department</th><th>Archived Date</th><th>Action</th> </thead>
                    <tbody>
                        <?php foreach ($archived_theses as $thesis): ?>
                        <tr class="archived-row">        <td><strong><?= htmlspecialchars($thesis['title']) ?></strong>\\         \\<?= htmlspecialchars($thesis['author']) ?>\\         \\<?= htmlspecialchars($thesis['department']) ?>\\         \\<?= $thesis['date'] ?>\\         \\<a href="view_thesis.php?id=<?= $thesis['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a>\\       \)
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'departments'): ?>
        <!-- DEPARTMENTS VIEW -->
        <div class="welcome-header"><h1>Departments</h1><p><span class="role-badge">LIBRARIAN</span> · View theses by department</p></div>
        <div class="departments-grid">
            <?php foreach ($departments as $dept): ?>
            <div class="department-card" onclick="viewDepartmentTheses(<?= $dept['department_id'] ?>, '<?= htmlspecialchars($dept['department_name']) ?>')">
                <div class="department-icon"><i class="fas fa-building"></i></div>
                <div class="department-info"><h3><?= htmlspecialchars($dept['department_name']) ?></h3><p class="department-code"><?= htmlspecialchars($dept['department_code']) ?></p></div>
                <div class="department-stats"><span class="stat-badge">View Theses <i class="fas fa-arrow-right"></i></span></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div id="deptThesesModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h3 id="modalTitle">Department Theses</h3><span class="close">&times;</span></div>
                <div class="modal-body" id="modalBody"><div class="loading">Loading theses...</div></div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Chart Data
        window.chartData = {
            status: {
                pending: <?= $stats['pending_reviews'] ?>,
                forwarded: <?= $stats['forwarded_to_dean'] ?>,
                rejected: <?= $stats['rejected'] ?>
            },
            monthly: <?= json_encode($monthly_data) ?>,
            months: <?= json_encode($months) ?>
        };
        
        // DOM Elements
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const searchInput = document.getElementById('searchInput');
        
        // Toggle Sidebar - For Hamburger Menu
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        // Toggle Profile Dropdown
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        }
        
        function closeProfileDropdown(e) {
            if (!profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        }
        
        // Dark Mode
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
        
        // Search functionality
        function handleSearch() {
            const term = searchInput.value.toLowerCase();
            const facultyItems = document.querySelectorAll('.faculty-item');
            const activityItems = document.querySelectorAll('.activity-item');
            
            facultyItems.forEach(item => {
                const name = item.querySelector('.faculty-name')?.textContent.toLowerCase() || '';
                if (name.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
            
            activityItems.forEach(item => {
                const description = item.querySelector('.activity-description')?.textContent.toLowerCase() || '';
                if (description.includes(term)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Initialize Charts
        function initCharts() {
            const statusCtx = document.getElementById('statusChart');
            if (statusCtx && window.chartData) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending Review', 'Forwarded to Dean', 'Rejected'],
                        datasets: [{
                            data: [window.chartData.status.pending, window.chartData.status.forwarded, window.chartData.status.rejected],
                            backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                            borderWidth: 0,
                            cutout: '60%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { legend: { display: false } }
                    }
                });
            }
            
            const monthlyCtx = document.getElementById('monthlyChart');
            if (monthlyCtx && window.chartData) {
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: window.chartData.months,
                        datasets: [{
                            label: 'Submissions',
                            data: window.chartData.monthly,
                            backgroundColor: '#dc2626',
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true } }
                    }
                });
            }
        }
        
        // Archive Thesis Function
        function archiveThesis(thesisId, thesisTitle) {
            if (confirm(`Are you sure you want to archive "${thesisTitle}"?`)) {
                fetch('archive_thesis.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'thesis_id=' + thesisId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Thesis archived successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => { alert('An error occurred.'); });
            }
        }
        
        // View Department Theses
        function viewDepartmentTheses(deptId, deptName) {
            const modal = document.getElementById('deptThesesModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.textContent = deptName + ' - Theses';
            modalBody.innerHTML = '<div class="loading">Loading theses...</div>';
            modal.style.display = 'block';
            
            fetch('get_department_theses.php?dept_id=' + deptId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.theses && data.theses.length > 0) {
                        let html = '<div class="theses-list-modal">';
                        data.theses.forEach(thesis => {
                            html += `
                                <div class="thesis-item-modal">
                                    <div class="thesis-info-modal">
                                        <h4>${escapeHtml(thesis.title)}</h4>
                                        <p><i class="fas fa-user-graduate"></i> ${escapeHtml(thesis.author)}</p>
                                        <p><i class="fas fa-building"></i> ${escapeHtml(thesis.department)}</p>
                                        <p><i class="fas fa-calendar"></i> ${thesis.date}</p>
                                        <p><span class="status-badge ${thesis.status.toLowerCase()}">${thesis.status}</span></p>
                                    </div>
                                    <div class="thesis-actions-modal">
                                        <a href="view_thesis.php?id=${thesis.id}" class="btn-view">View</a>
                                        ${thesis.status === 'Approved' ? `<button class="btn-archive" onclick="archiveThesis(${thesis.id}, '${escapeHtml(thesis.title)}')">Archive</button>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = '<div class="empty-state"><i class="fas fa-folder-open"></i><p>No theses found.</p></div>';
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = '<div class="error-state"><i class="fas fa-exclamation-circle"></i><p>Error loading theses.</p></div>';
                });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        // Modal close
        const modal = document.getElementById('deptThesesModal');
        const closeBtn = document.querySelector('.close');
        if (closeBtn) {
            closeBtn.onclick = function() { modal.style.display = 'none'; }
        }
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Notification click
        const notificationIcon = document.querySelector('.notification-icon');
        if (notificationIcon) {
            notificationIcon.addEventListener('click', function() {
                window.location.href = 'notifications.php';
            });
        }
        
        // Event Listeners
        if (hamburgerBtn) {
            hamburgerBtn.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        if (profileWrapper) {
            profileWrapper.addEventListener('click', toggleProfileDropdown);
            document.addEventListener('click', closeProfileDropdown);
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', handleSearch);
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initCharts();
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        });
        
        // Make functions global for inline onclick
        window.archiveThesis = archiveThesis;
        window.viewDepartmentTheses = viewDepartmentTheses;
    </script>
</body>
</html>