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
    
    $pending_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Pending' OR status = 'For Review'";
    $pending_result = $conn->query($pending_query);
    $stats['pending_reviews'] = ($pending_result && $pending_result->num_rows > 0) ? ($pending_result->fetch_assoc())['count'] : 0;
    
    $completed_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Approved' OR status = 'Completed'";
    $completed_result = $conn->query($completed_query);
    $stats['completed_projects'] = ($completed_result && $completed_result->num_rows > 0) ? ($completed_result->fetch_assoc())['count'] : 0;
    
    $ongoing_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'In Progress' OR status = 'Ongoing'";
    $ongoing_result = $conn->query($ongoing_query);
    $stats['ongoing_projects'] = ($ongoing_result && $ongoing_result->num_rows > 0) ? ($ongoing_result->fetch_assoc())['count'] : 0;
    
    $archived_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Archived'";
    $archived_result = $conn->query($archived_query);
    $stats['archived_count'] = ($archived_result && $archived_result->num_rows > 0) ? ($archived_result->fetch_assoc())['count'] : 0;
    
    $theses_approved_query = "SELECT COUNT(*) as count FROM theses WHERE status = 'Approved'";
    $theses_approved_result = $conn->query($theses_approved_query);
    $stats['theses_approved'] = ($theses_approved_result && $theses_approved_result->num_rows > 0) ? ($theses_approved_result->fetch_assoc())['count'] : 0;
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
        $projects_count = 0;
        if ($theses_table_exists) {
            $check_advisor = $conn->query("SHOW COLUMNS FROM theses LIKE 'faculty_adviser_id'");
            if ($check_advisor && $check_advisor->num_rows > 0) {
                $advisor_stmt = $conn->prepare("SELECT COUNT(*) as count FROM theses WHERE faculty_adviser_id = ?");
                $advisor_stmt->bind_param("i", $row['user_id']);
                $advisor_stmt->execute();
                $advisor_result = $advisor_stmt->get_result();
                if ($advisor_row = $advisor_result->fetch_assoc()) {
                    $projects_count = $advisor_row['count'];
                }
                $advisor_stmt->close();
            }
        }
        
        $faculty_members[] = [
            'id' => $row['user_id'],
            'name' => $row['first_name'] . " " . $row['last_name'],
            'specialization' => 'Faculty Member',
            'projects_supervised' => $projects_count,
            'status' => $row['status']
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
        ['id' => 6, 'name' => 'Dr. Karen Villanueva', 'specialization' => 'Literature', 'projects_supervised' => 3, 'status' => 'active'],
    ];
}

// GET STUDENTS
$students_list = [];
$students_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 2 ORDER BY first_name ASC";
$students_result = $conn->query($students_query);
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $theses_count = 0;
        if ($theses_table_exists) {
            $theses_stmt = $conn->prepare("SELECT COUNT(*) as count FROM theses WHERE student_id = ?");
            $theses_stmt->bind_param("i", $row['user_id']);
            $theses_stmt->execute();
            $theses_result = $theses_stmt->get_result();
            if ($theses_row = $theses_result->fetch_assoc()) {
                $theses_count = $theses_row['count'];
            }
            $theses_stmt->close();
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
    $projects_query = "SELECT thesis_id, title, student_name, adviser_name, created_at, status, defense_date FROM theses ORDER BY created_at DESC LIMIT 10";
    $projects_result = $conn->query($projects_query);
    if ($projects_result && $projects_result->num_rows > 0) {
        while ($row = $projects_result->fetch_assoc()) {
            $department_projects[] = [
                'id' => $row['thesis_id'],
                'title' => $row['title'],
                'student' => $row['student_name'] ?? 'Unknown',
                'adviser' => $row['adviser_name'] ?? 'Unknown',
                'submitted' => isset($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : date('M d, Y'),
                'status' => strtolower($row['status']),
                'defense_date' => $row['defense_date']
            ];
        }
    }
}

// If no projects found, use sample data
if (empty($department_projects)) {
    $department_projects = [
        ['id' => 1, 'title' => 'AI-Powered Thesis Recommendation System', 'student' => 'Maria Santos', 'adviser' => 'Prof. Juan Dela Cruz', 'submitted' => date('M d, Y'), 'status' => 'pending', 'defense_date' => null],
        ['id' => 2, 'title' => 'Mobile App for Campus Navigation', 'student' => 'Juan Dela Cruz', 'adviser' => 'Dr. Ana Lopez', 'submitted' => date('M d, Y', strtotime('-1 day')), 'status' => 'in-progress', 'defense_date' => date('Y-m-d', strtotime('+1 month'))],
        ['id' => 3, 'title' => 'E-Learning Platform for Mathematics', 'student' => 'Ana Lopez', 'adviser' => 'Prof. Pedro Reyes', 'submitted' => date('M d, Y', strtotime('-2 days')), 'status' => 'completed', 'defense_date' => date('Y-m-d', strtotime('-5 days'))],
        ['id' => 4, 'title' => 'IoT-Based Classroom Monitoring', 'student' => 'Pedro Reyes', 'adviser' => 'Dr. Lisa Garcia', 'submitted' => date('M d, Y', strtotime('-3 days')), 'status' => 'pending', 'defense_date' => null],
        ['id' => 5, 'title' => 'Blockchain for Student Records', 'student' => 'Lisa Garcia', 'adviser' => 'Prof. Mark Santiago', 'submitted' => date('M d, Y', strtotime('-5 days')), 'status' => 'archived', 'defense_date' => date('Y-m-d', strtotime('-10 days'))],
        ['id' => 6, 'title' => 'Virtual Reality Campus Tour', 'student' => 'Mark Santiago', 'adviser' => 'Dr. Karen Villanueva', 'submitted' => date('M d, Y', strtotime('-7 days')), 'status' => 'approved', 'defense_date' => date('Y-m-d', strtotime('+2 weeks'))],
    ];
}

// GET ARCHIVED PROJECTS
$archived_projects = array_filter($department_projects, function($p) {
    return $p['status'] == 'archived';
});

// GET UPCOMING DEFENSES
$upcoming_defenses = [];
if ($theses_table_exists) {
    $defenses_query = "SELECT thesis_id, title, student_name, defense_date, defense_time, panelists FROM theses WHERE defense_date >= CURDATE() AND status = 'Approved' ORDER BY defense_date ASC LIMIT 4";
    $defenses_result = $conn->query($defenses_query);
    if ($defenses_result && $defenses_result->num_rows > 0) {
        while ($row = $defenses_result->fetch_assoc()) {
            $upcoming_defenses[] = [
                'id' => $row['thesis_id'],
                'student' => $row['student_name'],
                'title' => $row['title'],
                'date' => $row['defense_date'],
                'time' => $row['defense_time'] ?? '10:00 AM',
                'panelists' => $row['panelists'] ?? 'To be announced'
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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/deanDashboard.css">
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
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="dean.php?section=department" class="nav-item <?= $section == 'department' ? 'active' : '' ?>">
                <i class="fas fa-building"></i>
                <span>Department</span>
            </a>
            <a href="dean.php?section=faculty" class="nav-item <?= $section == 'faculty' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>Faculty</span>
            </a>
            <a href="dean.php?section=students" class="nav-item <?= $section == 'students' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="dean.php?section=projects" class="nav-item <?= $section == 'projects' ? 'active' : '' ?>">
                <i class="fas fa-project-diagram"></i>
                <span>Projects</span>
            </a>
            <a href="dean.php?section=archive" class="nav-item <?= $section == 'archive' ? 'active' : '' ?>">
                <i class="fas fa-archive"></i>
                <span>Archived</span>
            </a>
            <a href="dean.php?section=reports" class="nav-item <?= $section == 'reports' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
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
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <?php if ($section == 'dashboard'): ?>
        <!-- DASHBOARD VIEW -->
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
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['total_students']) ?></h3>
                    <p>Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['total_faculty']) ?></h3>
                    <p>Faculty</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-project-diagram"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['total_projects']) ?></h3>
                    <p>Total Projects</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['pending_reviews']) ?></h3>
                    <p>Pending Reviews</p>
                </div>
            </div>
        </div>

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
                <div class="dept-stat-value"><?= number_format(count($upcoming_defenses)) ?></div>
                <div class="dept-stat-label">upcoming defenses</div>
            </div>
            <div class="dept-stat-card">
                <div class="dept-stat-header"><i class="fas fa-check-double"></i><span>Approved</span></div>
                <div class="dept-stat-value"><?= number_format($stats['theses_approved']) ?></div>
                <div class="dept-stat-label">theses this sem</div>
            </div>
        </div>

        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header"><h3>Project Status Distribution</h3></div>
                <div class="chart-container">
                    <canvas id="projectStatusChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3>Faculty Workload</h3></div>
                <div class="chart-container">
                    <canvas id="workloadChart"></canvas>
                </div>
            </div>
        </div>

        <div class="faculty-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-users"></i> Department Faculty</h2>
                <a href="dean.php?section=faculty" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="faculty-grid">
                <?php foreach (array_slice($faculty_members, 0, 4) as $faculty): ?>
                <div class="faculty-card">
                    <div class="faculty-header">
                        <div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1)) ?></div>
                        <div>
                            <div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div>
                            <div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div>
                        </div>
                    </div>
                    <div class="faculty-stats">
                        <div class="faculty-stat">
                            <div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div>
                            <div class="faculty-stat-label">Projects</div>
                        </div>
                        <div class="faculty-stat">
                            <div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div>
                            <div class="faculty-stat-label">Status</div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="projects-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-project-diagram"></i> Recent Department Projects</h2>
                <a href="dean.php?section=projects" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead>
                        <tr><th>PROJECT TITLE</th><th>STUDENT</th><th>ADVISER</th><th>STATUS</th><th>ACTION</th></thead>
                    <tbody>
                        <?php foreach (array_slice($department_projects, 0, 4) as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['title']) ?></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['adviser']) ?></td>
                            <td><div class="status"><span class="status-dot <?= $project['status'] ?>"></span><span><?= ucfirst(str_replace('-', ' ', $project['status'])) ?></span></div></td>
                            <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="defenses-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Thesis Defenses</h2>
                <a href="dean.php?section=defenses" class="view-all">Schedule New <i class="fas fa-plus"></i></a>
            </div>
            <?php foreach (array_slice($upcoming_defenses, 0, 3) as $defense): ?>
            <div class="defense-item">
                <div class="defense-date-box">
                    <div class="defense-day"><?= date('d', strtotime($defense['date'])) ?></div>
                    <div class="defense-month"><?= date('M', strtotime($defense['date'])) ?></div>
                </div>
                <div class="defense-details">
                    <div class="defense-title"><?= htmlspecialchars($defense['title']) ?></div>
                    <div class="defense-meta"><span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($defense['student']) ?></span><span><i class="far fa-clock"></i> <?= $defense['time'] ?></span></div>
                    <div class="defense-panel"><i class="fas fa-users"></i> Panel: <?= htmlspecialchars($defense['panelists']) ?></div>
                </div>
                <a href="#" class="btn-view"><i class="fas fa-calendar-check"></i> Details</a>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="bottom-grid">
            <div class="activities-section">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-history"></i> Department Activities</h2><a href="#" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
                <div class="activities-list">
                    <?php foreach (array_slice($department_activities, 0, 4) as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-<?= $activity['icon'] ?>"></i></div>
                        <div class="activity-details">
                            <div class="activity-text"><?= htmlspecialchars($activity['description']) ?></div>
                            <div class="activity-meta"><span><i class="far fa-clock"></i> <?= $activity['created_at'] ?></span><span><i class="fas fa-user"></i> <?= htmlspecialchars($activity['user']) ?></span></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="workload-section">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-line"></i> Faculty Workload Summary</h2><a href="#" class="view-all">Details <i class="fas fa-arrow-right"></i></a></div>
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
                <table class="students-table">
                    <thead><tr><th>Student Name</th><th>Email</th><th>Theses Count</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($students_list as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['name']) ?></td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td><?= $student['theses_count'] ?></td>
                            <td><span class="status-badge <?= $student['status'] ?>"><?= $student['status'] ?></span></td>
                            <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
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
                    <thead><tr><th>Project Title</th><th>Student</th><th>Adviser</th><th>Status</th><th>Defense Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($department_projects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['title']) ?></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['adviser']) ?></td>
                            <td><div class="status"><span class="status-dot <?= $project['status'] ?>"></span><span><?= ucfirst(str_replace('-', ' ', $project['status'])) ?></span></div></td>
                            <td><?= $project['defense_date'] ? date('M d, Y', strtotime($project['defense_date'])) : 'Not scheduled' ?></td>
                            <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php elseif ($section == 'defenses'): ?>
        <!-- DEFENSES VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Upcoming Thesis Defenses</h1><p>Schedule and track thesis defenses</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="defenses-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Defenses (<?= count($upcoming_defenses) ?>)</h2><a href="#" class="view-all">Schedule New <i class="fas fa-plus"></i></a></div>
            <?php foreach ($upcoming_defenses as $defense): ?>
            <div class="defense-item">
                <div class="defense-date-box"><div class="defense-day"><?= date('d', strtotime($defense['date'])) ?></div><div class="defense-month"><?= date('M', strtotime($defense['date'])) ?></div></div>
                <div class="defense-details"><div class="defense-title"><?= htmlspecialchars($defense['title']) ?></div><div class="defense-meta"><span><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($defense['student']) ?></span><span><i class="far fa-clock"></i> <?= $defense['time'] ?></span></div><div class="defense-panel"><i class="fas fa-users"></i> Panel: <?= htmlspecialchars($defense['panelists']) ?></div></div>
                <a href="#" class="btn-view"><i class="fas fa-calendar-check"></i> Details</a>
            </div>
            <?php endforeach; ?>
        </div>

        <?php elseif ($section == 'archive'): ?>
        <!-- ARCHIVE VIEW -->
        <div class="dept-banner"><div class="dept-info"><h1>Archived Theses</h1><p>Completed and archived thesis projects</p></div><div class="dean-info"><div class="dean-name"><?= htmlspecialchars($fullName) ?></div><div class="dean-since">Dean since <?= htmlspecialchars($dean_since) ?></div></div></div>
        <div class="projects-section" style="margin-top: 0;">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-archive"></i> Archived Projects (<?= count($archived_projects) ?>)</h2></div>
            <div class="table-responsive">
                <table class="theses-table">
                    <thead><tr><th>Project Title</th><th>Student</th><th>Adviser</th><th>Status</th><th>Defense Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($archived_projects as $project): ?>
                        <tr>
                            <td><?= htmlspecialchars($project['title']) ?></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['adviser']) ?></td>
                            <td><div class="status"><span class="status-dot archived"></span><span>Archived</span></div></td>
                            <td><?= $project['defense_date'] ? date('M d, Y', strtotime($project['defense_date'])) : 'Not scheduled' ?></td>
                            <td><a href="#" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
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
                in_progress: <?= $stats['ongoing_projects'] ?? 0 ?>,
                completed: <?= $stats['completed_projects'] ?? 0 ?>,
                archived: <?= $stats['archived_count'] ?? 0 ?>
            },
            workload_labels: <?= json_encode($workload_labels) ?>,
            workload_data: <?= json_encode($workload_data) ?>
        };
    </script>
    <script src="js/deanDashboard.js"></script>
</body>
</html>