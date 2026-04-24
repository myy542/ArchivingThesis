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

// LOGGED-IN USER INFO
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET ACTIVE SECTION FROM URL
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// GET USER DATA FROM DATABASE (with department_id)
$user_query = "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role_id, u.status, 
                      u.department_id, d.department_name, d.department_code 
               FROM user_table u
               LEFT JOIN department_table d ON u.department_id = d.department_id
               WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $user_email = $user_data['email'];
    $username = $user_data['username'];
    $dean_department_id = $user_data['department_id'];
    $dean_department_name = $user_data['department_name'] ?? 'Unknown Department';
    $dean_department_code = $user_data['department_code'] ?? 'N/A';
}

// GET DEPARTMENT INFO BASED ON DEAN'S DEPARTMENT ID
$department_id = $dean_department_id;
$department_name = $dean_department_name;
$department_code = $dean_department_code;
$dean_since = date('F Y');

// CREATE NOTIFICATIONS TABLE IF NOT EXISTS
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

// Determine the correct ID column name
$id_column = 'notification_id';
$check_id_col = $conn->query("SHOW COLUMNS FROM notifications LIKE 'id'");
if ($check_id_col && $check_id_col->num_rows > 0) {
    $id_column = 'id';
}

// GET NOTIFICATION COUNT - using is_read
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
        $thesis_q = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
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

// CHECK IF THESIS_TABLE EXISTS
$thesis_table_exists = false;
$check_thesis_table = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis_table && $check_thesis_table->num_rows > 0) {
    $thesis_table_exists = true;
}

// ==================== GET STATISTICS - FILTERED BY DEAN'S DEPARTMENT ====================
$stats = [];

// Total students in department
$students_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 2 AND status = 'Active' AND department_id = ?";
$students_stmt = $conn->prepare($students_query);
$students_stmt->bind_param("i", $department_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
$stats['total_students'] = ($students_result && $students_result->num_rows > 0) ? ($students_result->fetch_assoc())['count'] : 0;
$students_stmt->close();

// Total faculty in department
$faculty_query = "SELECT COUNT(*) as count FROM user_table WHERE role_id = 3 AND status = 'Active' AND department_id = ?";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $department_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$stats['total_faculty'] = ($faculty_result && $faculty_result->num_rows > 0) ? ($faculty_result->fetch_assoc())['count'] : 0;
$faculty_stmt->close();

if ($thesis_table_exists) {
    // Total projects in department (using is_archived instead of status)
    $projects_query = "SELECT COUNT(*) as count FROM thesis_table WHERE department_id = ?";
    $projects_stmt = $conn->prepare($projects_query);
    $projects_stmt->bind_param("i", $department_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    $stats['total_projects'] = ($projects_result && $projects_result->num_rows > 0) ? ($projects_result->fetch_assoc())['count'] : 0;
    $projects_stmt->close();
    
    // Ongoing projects (non-archived)
    $ongoing_query = "SELECT COUNT(*) as count FROM thesis_table WHERE department_id = ? AND (is_archived = 0 OR is_archived IS NULL)";
    $ongoing_stmt = $conn->prepare($ongoing_query);
    $ongoing_stmt->bind_param("i", $department_id);
    $ongoing_stmt->execute();
    $ongoing_result = $ongoing_stmt->get_result();
    $stats['ongoing_projects'] = ($ongoing_result && $ongoing_result->num_rows > 0) ? ($ongoing_result->fetch_assoc())['count'] : 0;
    $ongoing_stmt->close();
    
    // Archived projects
    $archived_query = "SELECT COUNT(*) as count FROM thesis_table WHERE department_id = ? AND is_archived = 1";
    $archived_stmt = $conn->prepare($archived_query);
    $archived_stmt->bind_param("i", $department_id);
    $archived_stmt->execute();
    $archived_result = $archived_stmt->get_result();
    $stats['archived_count'] = ($archived_result && $archived_result->num_rows > 0) ? ($archived_result->fetch_assoc())['count'] : 0;
    $archived_stmt->close();
    
    $stats['theses_approved'] = $stats['ongoing_projects'];
    $stats['pending_reviews'] = 0;
    $stats['completed_projects'] = $stats['ongoing_projects'];
} else {
    $stats['total_projects'] = 0;
    $stats['pending_reviews'] = 0;
    $stats['completed_projects'] = 0;
    $stats['ongoing_projects'] = 0;
    $stats['archived_count'] = 0;
    $stats['theses_approved'] = 0;
}

// ==================== GET FORWARDED THESES FOR DEAN REVIEW (WALA NAY STATUS COLUMN) ====================
$forwarded_theses = [];
// Since wala nay status column, ang forwarded theses kay ang mga non-archived thesis
// I-note lang nga wala tay way ma-determine kung forwarded na ba o hindi

// ==================== GET FACULTY MEMBERS IN DEPARTMENT ====================
$faculty_members = [];
$faculty_query = "SELECT user_id, first_name, last_name, email, status FROM user_table WHERE role_id = 3 AND status = 'Active' AND department_id = ? ORDER BY first_name ASC";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $department_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
if ($faculty_result && $faculty_result->num_rows > 0) {
    while ($row = $faculty_result->fetch_assoc()) {
        $project_count = 0;
        if ($thesis_table_exists) {
            $proj_q = $conn->prepare("SELECT COUNT(*) as c FROM thesis_table WHERE adviser LIKE ? AND department_id = ?");
            $search_name = '%' . $row['first_name'] . '%';
            $proj_q->bind_param("si", $search_name, $department_id);
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
$faculty_stmt->close();

// ==================== GET STUDENTS IN DEPARTMENT ====================
$students_list = [];
$students_query = "SELECT user_id, first_name, last_name, email, status, contact_number, address, birth_date FROM user_table WHERE role_id = 2 AND department_id = ? ORDER BY first_name ASC";
$students_stmt = $conn->prepare($students_query);
$students_stmt->bind_param("i", $department_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $theses_count = 0;
        if ($thesis_table_exists) {
            $thesis_q = $conn->prepare("SELECT COUNT(*) as c FROM thesis_table WHERE student_id = ? AND department_id = ?");
            $thesis_q->bind_param("ii", $row['user_id'], $department_id);
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
            'status' => $row['status'],
            'contact_number' => $row['contact_number'] ?? 'Not provided',
            'address' => $row['address'] ?? 'Not provided',
            'birth_date' => $row['birth_date'] ?? ''
        ];
    }
}
$students_stmt->close();

// ==================== GET DEPARTMENT PROJECTS ====================
$department_projects = [];
$archived_projects = [];
if ($thesis_table_exists) {
    $projects_query = "SELECT t.thesis_id, t.title, t.adviser, t.year, t.date_submitted, t.is_archived,
                              u.first_name, u.last_name, d.department_name, d.department_code
                       FROM thesis_table t
                       JOIN user_table u ON t.student_id = u.user_id
                       LEFT JOIN department_table d ON t.department_id = d.department_id
                       WHERE t.department_id = ?
                       ORDER BY t.date_submitted DESC";
    $projects_stmt = $conn->prepare($projects_query);
    $projects_stmt->bind_param("i", $department_id);
    $projects_stmt->execute();
    $projects_result = $projects_stmt->get_result();
    if ($projects_result && $projects_result->num_rows > 0) {
        while ($row = $projects_result->fetch_assoc()) {
            $project = [
                'id' => $row['thesis_id'],
                'title' => $row['title'],
                'student' => $row['first_name'] . ' ' . $row['last_name'],
                'adviser' => $row['adviser'] ?? 'N/A',
                'department' => $row['department_name'] ?? $department_name,
                'department_code' => $row['department_code'] ?? $department_code,
                'year' => $row['year'] ?? 'N/A',
                'submitted' => isset($row['date_submitted']) ? date('M d, Y', strtotime($row['date_submitted'])) : date('M d, Y'),
                'status' => ($row['is_archived'] == 1) ? 'archived' : 'pending'
            ];
            $department_projects[] = $project;
            if ($project['status'] == 'archived') {
                $archived_projects[] = $project;
            }
        }
    }
    $projects_stmt->close();
}

// ==================== GET RECENT ACTIVITIES ====================
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
    
    <link rel="stylesheet" href="css/dean.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search..."></div>
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
                                <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>">
                                    <div class="notif-icon"><?php if(strpos($notif['message'], 'approved') !== false) echo '<i class="fas fa-check-circle"></i>'; elseif(strpos($notif['message'], 'forwarded') !== false) echo '<i class="fas fa-arrow-right"></i>'; elseif(strpos($notif['message'], 'revision') !== false) echo '<i class="fas fa-edit"></i>'; elseif(strpos($notif['message'], 'archived') !== false) echo '<i class="fas fa-archive"></i>'; else echo '<i class="fas fa-bell"></i>'; ?></div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time"><i class="far fa-clock"></i> <?php $date = new DateTime($notif['created_at']); $now = new DateTime(); $diff = $now->diff($date); if($diff->days == 0) echo 'Today, ' . $date->format('h:i A'); elseif($diff->days == 1) echo 'Yesterday, ' . $date->format('h:i A'); else echo $date->format('M d, Y h:i A'); ?></div>
                                        <?php if (isset($notif['thesis_title'])): ?>
                                            <div class="notif-thesis" style="font-size:0.7rem; color:#6b7280; margin-top:4px;"><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
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
            <a href="dean.php?section=dashboard" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="dean.php?section=department" class="nav-item <?= $section == 'department' ? 'active' : '' ?>"><i class="fas fa-building"></i><span>Department</span></a>
            <a href="dean.php?section=faculty" class="nav-item <?= $section == 'faculty' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Faculty</span></a>
            <a href="dean.php?section=students" class="nav-item <?= $section == 'students' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i><span>Students</span></a>
            <a href="dean.php?section=projects" class="nav-item <?= $section == 'projects' ? 'active' : '' ?>"><i class="fas fa-project-diagram"></i><span>Projects</span></a>
            <a href="dean.php?section=archive" class="nav-item <?= $section == 'archive' ? 'active' : '' ?>"><i class="fas fa-archive"></i><span>Archived</span></a>
            <a href="dean.php?section=reports" class="nav-item <?= $section == 'reports' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i><span>Reports</span></a>
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
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($stats['archived_count']) ?></h3><p>Archived</p></div></div>
        </div>

        <div class="dept-stats">
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div><div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div><div class="dept-stat-label">active projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-archive"></i><span>Archived</span></div><div class="dept-stat-value"><?= number_format($stats['archived_count']) ?></div><div class="dept-stat-label">archived projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-chart-simple"></i><span>Total</span></div><div class="dept-stat-value"><?= number_format($stats['total_projects']) ?></div><div class="dept-stat-label">total projects</div></div>
        </div>

        <div class="charts-section">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> Project Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="projectStatusChart"></canvas>
                </div>
                <div class="status-labels">
                    <div class="status-label-item"><span class="status-color pending"></span><span>Ongoing (<?= $stats['ongoing_projects'] ?>)</span></div>
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
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> Department Faculty</h2><a href="dean.php?section=faculty" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="faculty-grid">
                <?php if (count($faculty_members) > 0): ?>
                    <?php foreach (array_slice($faculty_members, 0, 4) as $faculty): ?>
                    <div class="faculty-card">
                        <div class="faculty-header"><div class="faculty-avatar"><?= strtoupper(substr($faculty['name'], 0, 1) . (strpos($faculty['name'], ' ') !== false ? substr(explode(' ', $faculty['name'])[1] ?? '', 0, 1) : '')) ?></div><div><div class="faculty-name"><?= htmlspecialchars($faculty['name']) ?></div><div class="faculty-spec"><?= htmlspecialchars($faculty['specialization']) ?></div></div></div>
                        <div class="faculty-stats"><div class="faculty-stat"><div class="faculty-stat-value"><?= $faculty['projects_supervised'] ?></div><div class="faculty-stat-label">Projects</div></div><div class="faculty-stat"><div class="faculty-stat-value"><span class="status-badge <?= $faculty['status'] ?>"><?= ucfirst($faculty['status']) ?></span></div><div class="faculty-stat-label">Status</div></div></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-users"></i><p>No faculty members found</p></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="projects-section">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-project-diagram"></i> Recent Department Projects</h2><a href="dean.php?section=projects" class="view-all">View All <i class="fas fa-arrow-right"></i></a></div>
            <div class="table-responsive">
                <?php if (count($department_projects) > 0): ?>
                <table class="theses-table">
                    <thead><tr><th>PROJECT TITLE</th><th>AUTHOR</th><th>DEPARTMENT</th><th>STATUS</th><th>ACTION</th></thead>
                    <tbody>
                        <?php foreach (array_slice($department_projects, 0, 4) as $project): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['department']) ?> (<?= htmlspecialchars($project['department_code']) ?>)</span></td>
                            <td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td>
                            <td><a href="reviewThesis.php?id=<?= $project['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
                <?php endif; ?>
            </div>
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
                    <div class="empty-state"><i class="fas fa-clock"></i><p>No recent activities</p></div>
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
                    <div class="empty-state"><i class="fas fa-chart-line"></i><p>No faculty workload data available</p></div>
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
        <!-- FACULTY VIEW -->
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
                <table class="theses-table">
                    <thead>
                        <tr><th>Student Name</th><th>Email</th><th>Theses Count</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students_list as $student): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($student['name']) ?></strong></td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td><?= $student['theses_count'] ?></td>
                            <td><span class="status-badge <?= strtolower($student['status']) ?>"><?= $student['status'] ?></span></td>
                            <td><a href="view_student.php?id=<?= $student['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-user-graduate"></i><p>No students found</p></div>
            <?php endif; ?>
        </div>

        <?php elseif ($section == 'projects'): ?>
        <!-- ALL PROJECTS VIEW -->
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
                <table class="theses-table">
                    <thead>
                        <tr><th>Project Title</th><th>Student</th><th>Department</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($department_projects as $project): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                            <td><?= htmlspecialchars($project['student']) ?></td>
                            <td><?= htmlspecialchars($project['department']) ?> (<?= htmlspecialchars($project['department_code']) ?>)</span></td>
                            <td><span class="status-dot <?= $project['status'] ?>"></span><?= ucfirst($project['status']) ?></td>
                            <td><a href="reviewThesis.php?id=<?= $project['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-folder-open"></i><p>No projects found</p></div>
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
                <table class="theses-table">
                    <thead>
                        <tr><th>Project Title</th><th>Student</th><th>Department</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archived_projects as $project): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($project['title']) ?></strong></td>
                            <td><?= htmlspecialchars($project['student']) ?><td>
                            <td><?= htmlspecialchars($project['department']) ?> (<?= htmlspecialchars($project['department_code']) ?>)</span></td>
                            <td><span class="status-dot archived"></span>Archived</span></td>
                            <td><a href="reviewThesis.php?id=<?= $project['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><i class="fas fa-archive"></i><p>No archived projects found</p></div>
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
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-spinner"></i><span>Ongoing</span></div><div class="dept-stat-value"><?= number_format($stats['ongoing_projects']) ?></div><div class="dept-stat-label">active projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-archive"></i><span>Archived</span></div><div class="dept-stat-value"><?= number_format($stats['archived_count']) ?></div><div class="dept-stat-label">archived projects</div></div>
            <div class="dept-stat-card"><div class="dept-stat-header"><i class="fas fa-chart-simple"></i><span>Total</span></div><div class="dept-stat-value"><?= number_format($stats['total_projects']) ?></div><div class="dept-stat-label">total projects</div></div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        window.chartData = {
            status: {
                ongoing: <?= $stats['ongoing_projects'] ?? 0 ?>,
                archived: <?= $stats['archived_count'] ?? 0 ?>
            },
            workload_labels: <?= json_encode($workload_labels) ?>,
            workload_data: <?= json_encode($workload_data) ?>
        };
        window.userData = {
            fullName: '<?= htmlspecialchars($fullName) ?>',
            initials: '<?= htmlspecialchars($initials) ?>',
            department: '<?= htmlspecialchars($department_name) ?>',
            departmentCode: '<?= htmlspecialchars($department_code) ?>'
        };
    </script>
    
    <script src="js/dean.js"></script>
</body>
</html>