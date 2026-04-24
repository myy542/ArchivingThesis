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

// GET ALL DEPARTMENTS FROM department_table
$departments = [];
$dept_query = "SELECT department_id, department_name, department_code FROM department_table ORDER BY department_name";
$dept_result = $conn->query($dept_query);
if ($dept_result && $dept_result->num_rows > 0) {
    while ($dept = $dept_result->fetch_assoc()) {
        $departments[$dept['department_id']] = [
            'id' => $dept['department_id'],
            'name' => $dept['department_name'],
            'code' => $dept['department_code']
        ];
    }
}

$dept_keys = array_keys($departments);

// PROCESS FORM SUBMISSIONS
$message = '';
$message_type = '';

// ADD THESIS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $title = trim($_POST['title']);
    $author_name = trim($_POST['author']);
    $department_id = trim($_POST['department_id']);
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
    
    $stmt = $conn->prepare("INSERT INTO thesis_table (student_id, title, abstract, keywords, department_id, year, adviser, file_path, date_submitted, is_archived) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
    $keywords = "";
    $adviser = "";
    $file_path = "";
    $stmt->bind_param("isssisssi", $student_id, $title, $abstract, $keywords, $department_id, $year, $adviser, $file_path, $is_archived);
    
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $dept_name = isset($departments[$department_id]) ? $departments[$department_id]['name'] : 'Unknown';
        logAdminAction($conn, $user_id, "Added Thesis", "thesis_table", $new_id, "Added new thesis: $title ($dept_name)");
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
    $department_id = trim($_POST['department_id']);
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
    
    $stmt = $conn->prepare("UPDATE thesis_table SET student_id=?, title=?, abstract=?, department_id=?, year=?, is_archived=? WHERE thesis_id=?");
    $stmt->bind_param("issssii", $student_id, $title, $abstract, $department_id, $year, $is_archived, $thesis_id);
    
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

// GET THESIS FOR EDIT (AJAX) - UPDATED with department join
if (isset($_GET['get_thesis'])) {
    $get_id = $_GET['get_thesis'];
    $stmt = $conn->prepare("SELECT t.thesis_id, t.title, t.abstract, t.department_id, t.year, t.is_archived,
                                  COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') as author,
                                  d.department_name, d.department_code
                           FROM thesis_table t
                           LEFT JOIN user_table u ON t.student_id = u.user_id
                           LEFT JOIN department_table d ON t.department_id = d.department_id
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
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php'],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php'],
    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php'],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php'],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php'],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php']
];

// GET FILTERS
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$archive_filter = isset($_GET['archive']) ? $_GET['archive'] : '';

// BUILD QUERY - UPDATED with department join
$query = "SELECT 
            t.thesis_id, 
            t.title, 
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') as author,
            t.department_id,
            t.year, 
            t.abstract, 
            t.is_archived,
            t.date_submitted as created_at,
            t.file_path,
            d.department_name,
            d.department_code
          FROM thesis_table t
          LEFT JOIN user_table u ON t.student_id = u.user_id
          LEFT JOIN department_table d ON t.department_id = d.department_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (t.title LIKE ? OR COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unknown') LIKE ? OR t.year LIKE ? OR d.department_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
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

// Group theses by department
$theses_by_dept = [];
foreach ($departments as $dept_id => $dept_info) {
    $theses_by_dept[$dept_id] = [
        'name' => $dept_info['name'],
        'code' => $dept_info['code'],
        'theses' => [],
        'count' => 0
    ];
}

while ($thesis = $result->fetch_assoc()) {
    $dept_id = $thesis['department_id'];
    
    // If department_id is null, assign to "Unknown" department
    if ($dept_id === null || !isset($theses_by_dept[$dept_id])) {
        if (!isset($theses_by_dept['unknown'])) {
            $theses_by_dept['unknown'] = [
                'name' => 'Unknown Department',
                'code' => 'N/A',
                'theses' => [],
                'count' => 0
            ];
        }
        $thesis['status'] = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';
        $theses_by_dept['unknown']['theses'][] = $thesis;
        $theses_by_dept['unknown']['count']++;
    } else {
        $thesis['status'] = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';
        $theses_by_dept[$dept_id]['theses'][] = $thesis;
        $theses_by_dept[$dept_id]['count']++;
    }
}
$stmt->close();

// GET STATISTICS
$total_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table")->fetch_assoc()['c'];
$pending_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table WHERE (is_archived = 0 OR is_archived IS NULL)")->fetch_assoc()['c'];
$approved_theses = 0;
$archived_theses = $conn->query("SELECT COUNT(*) as c FROM thesis_table WHERE is_archived = 1")->fetch_assoc()['c'];

$dept_stats = [];
foreach ($departments as $dept_id => $dept_info) {
    $dept_stats[$dept_info['code']] = $theses_by_dept[$dept_id]['count'] ?? 0;
}

// NOTIFICATION COUNT
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theses Management | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/theses.css">
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
            <a href="backup_management.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
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
        
        <!-- Department Statistics -->
        <div class="dept-stats-grid">
            <?php foreach ($departments as $dept_id => $dept_info): ?>
            <div class="dept-stat-card">
                <h4><?= htmlspecialchars($dept_info['name']) ?></h4>
                <div class="number"><?= $dept_stats[$dept_info['code']] ?? 0 ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-input" placeholder="Search by title, author, year, department..." value="<?= htmlspecialchars($search) ?>">
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
            <?php foreach ($theses_by_dept as $dept_id => $dept_data): ?>
                <?php 
                    $icon = '';
                    $code = $dept_data['code'];
                    if ($code == 'BSIT') $icon = 'fa-laptop-code';
                    elseif ($code == 'BSBA') $icon = 'fa-chart-line';
                    elseif ($code == 'BSCRIM') $icon = 'fa-gavel';
                    elseif ($code == 'BSED') $icon = 'fa-chalkboard-user';
                    elseif ($code == 'BSHTM') $icon = 'fa-utensils';
                    else $icon = 'fa-building';
                ?>
                <button class="dept-tab" data-dept="<?= $dept_id ?>">
                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($dept_data['name']) ?>
                    <span class="dept-count-badge"><?= $dept_data['count'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        
        <!-- Department Content Sections -->
        <?php foreach ($theses_by_dept as $dept_id => $dept_data): ?>
        <div class="dept-content" data-dept-content="<?= $dept_id ?>">
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
                    <div class="form-group"><label>Thesis Title <span class="required">*</span></label><input type="text" id="title" name="title" required></div>
                    <div class="form-row">
                        <div class="form-group"><label>Author (Student Name)</label><input type="text" id="author" name="author" placeholder="e.g., Juan Dela Cruz" required></div>
                        <div class="form-group"><label>Department <span class="required">*</span></label>
                            <select id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept_id => $dept_info): ?>
                                    <option value="<?= $dept_id ?>"><?= htmlspecialchars($dept_info['name']) ?> (<?= htmlspecialchars($dept_info['code']) ?>)</option>
                                <?php endforeach; ?>
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
    
    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.searchParams = {
            search: '<?php echo addslashes($search); ?>',
            archive: '<?php echo addslashes($archive_filter); ?>'
        };
        window.notificationCount = <?php echo $notificationCount; ?>;
        
        function editThesis(id) {
            fetch('theses.php?get_thesis=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('thesis_id').value = data.thesis.thesis_id;
                        document.getElementById('title').value = data.thesis.title;
                        document.getElementById('author').value = data.thesis.author;
                        document.getElementById('department_id').value = data.thesis.department_id;
                        document.getElementById('year').value = data.thesis.year;
                        document.getElementById('abstract').value = data.thesis.abstract;
                        document.getElementById('is_archived').value = data.thesis.is_archived;
                        document.getElementById('form_action').value = 'edit';
                        document.getElementById('modalTitle').innerHTML = 'Edit Thesis';
                        document.getElementById('thesisModal').classList.add('show');
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        function deleteThesis(id) {
            if (confirm('Are you sure you want to delete this thesis? This action cannot be undone.')) {
                window.location.href = 'theses.php?delete=' + id;
            }
        }
        
        function updateArchiveStatus(id, currentStatus) {
            document.getElementById('status_thesis_id').value = id;
            document.getElementById('new_status').value = currentStatus == 1 ? 0 : 1;
            document.getElementById('statusModal').classList.add('show');
        }
        
        function saveStatusUpdate() {
            const thesisId = document.getElementById('status_thesis_id').value;
            const newStatus = document.getElementById('new_status').value;
            window.location.href = 'theses.php?update_archive=' + thesisId + '&is_archived=' + newStatus;
        }
        
        function closeModal() {
            document.getElementById('thesisModal').classList.remove('show');
            document.getElementById('thesisForm').reset();
            document.getElementById('form_action').value = 'add';
            document.getElementById('modalTitle').innerHTML = 'Add New Thesis';
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('show');
        }
        
        // Add Thesis Button
        document.getElementById('addThesisBtn').addEventListener('click', function() {
            document.getElementById('thesisForm').reset();
            document.getElementById('form_action').value = 'add';
            document.getElementById('modalTitle').innerHTML = 'Add New Thesis';
            document.getElementById('thesisModal').classList.add('show');
        });
        
        // Filter functionality
        document.getElementById('applyFilters').addEventListener('click', function() {
            const search = document.getElementById('searchInput').value;
            const archive = document.getElementById('archiveFilter').value;
            window.location.href = 'theses.php?search=' + encodeURIComponent(search) + '&archive=' + archive;
        });
        
        document.getElementById('clearFilters').addEventListener('click', function() {
            window.location.href = 'theses.php';
        });
        
        // Department tabs
        const deptTabs = document.querySelectorAll('.dept-tab');
        const deptContents = document.querySelectorAll('.dept-content');
        
        deptTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const deptId = this.getAttribute('data-dept');
                deptTabs.forEach(t => t.classList.remove('active'));
                deptContents.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                document.querySelector(`.dept-content[data-dept-content="${deptId}"]`).classList.add('active');
            });
        });
        
        // Open first tab by default
        if (deptTabs.length > 0) {
            deptTabs[0].click();
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('thesisModal');
            const statusModal = document.getElementById('statusModal');
            if (event.target == modal) closeModal();
            if (event.target == statusModal) closeStatusModal();
        }
    </script>
    
    <script src="js/theses.js"></script>
</body>
</html>