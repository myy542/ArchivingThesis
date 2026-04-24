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

// PROCESS FORM SUBMISSIONS
$message = '';
$message_type = '';

// ADD USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id'];
    $status = $_POST['status'];
    $department_id = isset($_POST['department_id']) && !empty($_POST['department_id']) ? $_POST['department_id'] : NULL;
    
    $check = $conn->prepare("SELECT user_id FROM user_table WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $message = "Username or email already exists!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO user_table (first_name, last_name, email, username, password, role_id, department_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssis", $first_name, $last_name, $email, $username, $password, $role_id, $department_id, $status);
        
        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            logAdminAction($conn, $_SESSION['user_id'], "Added User", "user_table", $new_id, "Added new user: $first_name $last_name");
            $message = "User added successfully!";
            $message_type = "success";
        } else {
            $message = "Error adding user: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// EDIT USER
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $user_id_edit = $_POST['user_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $role_id = $_POST['role_id'];
    $status = $_POST['status'];
    $department_id = isset($_POST['department_id']) && !empty($_POST['department_id']) ? $_POST['department_id'] : NULL;
    
    $check = $conn->prepare("SELECT user_id FROM user_table WHERE (username = ? OR email = ?) AND user_id != ?");
    $check->bind_param("ssi", $username, $email, $user_id_edit);
    $check->execute();
    $check->store_result();
    
    if ($check->num_rows > 0) {
        $message = "Username or email already exists for another user!";
        $message_type = "error";
    } else {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE user_table SET first_name=?, last_name=?, email=?, username=?, password=?, role_id=?, department_id=?, status=? WHERE user_id=?");
            $stmt->bind_param("ssssssisi", $first_name, $last_name, $email, $username, $password, $role_id, $department_id, $status, $user_id_edit);
        } else {
            $stmt = $conn->prepare("UPDATE user_table SET first_name=?, last_name=?, email=?, username=?, role_id=?, department_id=?, status=? WHERE user_id=?");
            $stmt->bind_param("sssssisi", $first_name, $last_name, $email, $username, $role_id, $department_id, $status, $user_id_edit);
        }
        
        if ($stmt->execute()) {
            logAdminAction($conn, $_SESSION['user_id'], "Edited User", "user_table", $user_id_edit, "Edited user: $first_name $last_name");
            $message = "User updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating user: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
    $check->close();
}

// DELETE USER
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    
    if ($delete_id == $_SESSION['user_id']) {
        $message = "You cannot delete your own account!";
        $message_type = "error";
    } else {
        $user_info = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ?");
        $user_info->bind_param("i", $delete_id);
        $user_info->execute();
        $user_info->bind_result($del_first, $del_last);
        $user_info->fetch();
        $user_info->close();
        
        $stmt = $conn->prepare("DELETE FROM user_table WHERE user_id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            logAdminAction($conn, $_SESSION['user_id'], "Deleted User", "user_table", $delete_id, "Deleted user: $del_first $del_last");
            $message = "User deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting user: " . $conn->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// TOGGLE USER STATUS
if (isset($_GET['toggle'])) {
    $toggle_id = $_GET['toggle'];
    $new_status = $_GET['status'];
    
    $stmt = $conn->prepare("UPDATE user_table SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_status, $toggle_id);
    
    if ($stmt->execute()) {
        $user_info = $conn->prepare("SELECT first_name, last_name FROM user_table WHERE user_id = ?");
        $user_info->bind_param("i", $toggle_id);
        $user_info->execute();
        $user_info->bind_result($toggle_first, $toggle_last);
        $user_info->fetch();
        $user_info->close();
        
        logAdminAction($conn, $_SESSION['user_id'], "Toggle User Status", "user_table", $toggle_id, "Changed user $toggle_first $toggle_last status to $new_status");
        $message = "User status updated to $new_status!";
        $message_type = "success";
    } else {
        $message = "Error updating status: " . $conn->error;
        $message_type = "error";
    }
    $stmt->close();
}

// GET USER FOR EDIT (AJAX) - UPDATED with department join
if (isset($_GET['get_user'])) {
    $get_id = $_GET['get_user'];
    $user_query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.username, u.role_id, u.department_id, u.status, d.department_name, d.department_code 
                   FROM user_table u
                   LEFT JOIN department_table d ON u.department_id = d.department_id
                   WHERE u.user_id = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $get_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    echo json_encode(['success' => true, 'user' => $user]);
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

// ROLE MAPPING
$roles = [
    1 => 'Admin',
    2 => 'Student',
    3 => 'Research Adviser',
    4 => 'Dean',
    5 => 'Librarian',
    6 => 'Coordinator'
];

// GET ALL USERS - UPDATED with department join
$query = "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role_id, u.department_id, u.status, 
                 d.department_name, d.department_code
          FROM user_table u
          LEFT JOIN department_table d ON u.department_id = d.department_id
          ORDER BY u.role_id, u.user_id";
$result = $conn->query($query);
$all_users = [];
while ($row = $result->fetch_assoc()) {
    $all_users[] = $row;
}

// Group users by role
$users_by_role = [];
foreach ($roles as $role_id => $role_name) {
    $users_by_role[$role_id] = [
        'name' => $role_name,
        'users' => [],
        'count' => 0
    ];
}

foreach ($all_users as $user) {
    $role_id = $user['role_id'];
    if (isset($users_by_role[$role_id])) {
        $users_by_role[$role_id]['users'][] = $user;
        $users_by_role[$role_id]['count']++;
    }
}

// GET STATISTICS
$total_users = count($all_users);
$active_users = 0;
foreach ($all_users as $user) {
    if ($user['status'] == 'Active') $active_users++;
}
$inactive_users = $total_users - $active_users;

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

function getDepartmentDisplay($dept_id, $conn) {
    if (!$dept_id) return null;
    $query = "SELECT department_name, department_code FROM department_table WHERE department_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $dept = $result->fetch_assoc();
    $stmt->close();
    if ($dept) {
        return $dept['department_name'] . ' (' . $dept['department_code'] . ')';
    }
    return null;
}

// For display purposes, we need to reopen connection since we closed it
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
    <link rel="stylesheet" href="css/users.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="topSearchInput" placeholder="Search users..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-icon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
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
            <div class="admin-label">ADMINISTRATOR</div>
        </div>
        <div class="nav-menu">
            <a href="admindashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item active"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="theses.php" class="nav-item"><i class="fas fa-book"></i><span>Theses</span></a>
            <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
            <a href="backup_management.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header"><i class="fas fa-chalkboard-user"></i><span>Quick Access</span></div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank">
                <i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i>
                <span><?= $dashboard['name'] ?> Dashboard</span>
                <i class="fas fa-external-link-alt link-icon"></i>
            </a>
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
            <p>Manage all system users - organized by role for easier management</p>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?>">
            <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-number"><?= $total_users ?></div><div class="stat-label">Total Users</div></div>
            <div class="stat-card"><div class="stat-number"><?= $active_users ?></div><div class="stat-label">Active Users</div></div>
            <div class="stat-card"><div class="stat-number"><?= $inactive_users ?></div><div class="stat-label">Inactive Users</div></div>
        </div>
        
        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-input" placeholder="Search by name, email, username...">
            <select id="statusFilter" class="filter-select">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
            <button id="applyFilters" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
            <button id="clearFilters" class="clear-btn"><i class="fas fa-times"></i> Clear</button>
            <button id="addUserBtn" class="add-user-btn"><i class="fas fa-plus"></i> Add User</button>
        </div>
        
        <div class="role-tabs" id="roleTabs">
            <?php foreach ($users_by_role as $role_id => $role_data): ?>
                <button class="role-tab" data-role="<?= $role_id ?>">
                    <?php 
                        $icon = '';
                        if ($role_id == 1) $icon = 'fa-user-shield';
                        elseif ($role_id == 2) $icon = 'fa-user-graduate';
                        elseif ($role_id == 3) $icon = 'fa-chalkboard-user';
                        elseif ($role_id == 4) $icon = 'fa-user-tie';
                        elseif ($role_id == 5) $icon = 'fa-book-reader';
                        else $icon = 'fa-clipboard-list';
                    ?>
                    <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($role_data['name']) ?>
                    <span class="role-count-badge"><?= $role_data['count'] ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        
        <?php foreach ($users_by_role as $role_id => $role_data): ?>
        <div class="role-content" data-role-content="<?= $role_id ?>">
            <div class="role-stats">
                <div class="role-stat-card"><div class="role-stat-number"><?= $role_data['count'] ?></div><div class="role-stat-label">Total <?= htmlspecialchars($role_data['name']) ?> Users</div></div>
                <?php 
                    $active_role_count = 0;
                    foreach ($role_data['users'] as $u) {
                        if ($u['status'] == 'Active') $active_role_count++;
                    }
                ?>
                <div class="role-stat-card"><div class="role-stat-number"><?= $active_role_count ?></div><div class="role-stat-label">Active <?= htmlspecialchars($role_data['name']) ?> Users</div></div>
            </div>
            
            <?php if (empty($role_data['users'])): ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <p>No <?= htmlspecialchars($role_data['name']) ?> users found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr><th>#</th><th>User Name</th><th>Email</th><th>Username</th><th>Department</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($role_data['users'] as $user): ?>
                        <tr data-user-id="<?= $user['user_id'] ?>" data-name="<?= strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['email'] . ' ' . $user['username']) ?>" data-status="<?= $user['status'] ?>">
                            <td><?= $counter++ ?></td>
                            <td class="user-name-cell">
                                <div class="user-avatar-small"><?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?></div>
                                <span><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <?php 
                                if ($user['department_id']) {
                                    $dept_display = $user['department_name'] . ' (' . $user['department_code'] . ')';
                                } else {
                                    $dept_display = null;
                                }
                                if ($user['role_id'] == 2 || $user['role_id'] == 3): 
                                    if ($dept_display):
                                ?>
                                    <span class="department-badge dept-assigned">
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($dept_display) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="department-badge dept-warning">
                                        <i class="fas fa-exclamation-triangle"></i> No Department Assigned
                                    </span>
                                <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($dept_display): ?>
                                    <span class="department-badge dept-assigned">
                                        <i class="fas fa-building"></i> <?= htmlspecialchars($dept_display) ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="department-badge dept-all">
                                        <i class="fas fa-check-circle"></i> All Departments
                                    </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </span>
                            </td>
                            <td><span class="status-badge <?= strtolower($user['status']) ?>"><?= $user['status'] ?></span></td>
                            <td class="action-buttons">
                                <button class="action-btn edit" onclick="editUser(<?= $user['user_id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <button class="action-btn toggle-status" onclick="toggleStatus(<?= $user['user_id'] ?>, '<?= $user['status'] ?>')"><i class="fas <?= $user['status'] == 'Active' ? 'fa-ban' : 'fa-check-circle' ?>"></i> <?= $user['status'] == 'Active' ? 'Deactivate' : 'Activate' ?></button>
                                <button class="action-btn delete" onclick="deleteUser(<?= $user['user_id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
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
    
    <!-- ADD/EDIT USER MODAL -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">
                    <i class="fas fa-user-plus"></i> 
                    Add New User
                </h3>
                <span class="close-modal" onclick="closeModal()">&times;</span>
            </div>
            <form id="userForm" method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" id="user_id" name="user_id">
                    <input type="hidden" id="form_action" name="action" value="add">
                    
                    <!-- First Name & Last Name Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" placeholder="Enter first name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" placeholder="Enter last name" required>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" id="email" name="email" placeholder="Enter email address" required>
                    </div>
                    
                    <!-- Username -->
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username" placeholder="Enter username" required>
                    </div>
                    
                    <!-- Password -->
                    <div class="form-group">
                        <label>Password <span id="passwordRequired" class="required">*</span></label>
                        <input type="password" id="password" name="password" placeholder="Enter password (min. 6 characters)">
                        <small id="passwordNote" class="form-note" style="display: none;">Leave blank to keep current password</small>
                    </div>
                    
                    <!-- Role -->
                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select id="role_id" name="role_id" required onchange="updateDepartmentRequirement()">
                            <option value="1">Admin</option>
                            <option value="2">Student</option>
                            <option value="3">Research Adviser</option>
                            <option value="4">Dean</option>
                            <option value="5">Librarian</option>
                            <option value="6">Coordinator</option>
                        </select>
                        <small class="form-note">Role determines dashboard access and permissions</small>
                    </div>
                    
                    <!-- Department Field - Using department_id from department_table -->
                    <div class="form-group" id="departmentGroup">
                        <label>Department <span id="deptRequired" class="required"></span></label>
                        <select id="department_id" name="department_id">
                            <option value="">-- Select Department --</option>
                            <?php
                            // Reopen connection for department dropdown
                            include("../config/db.php");
                            $dept_query = "SELECT department_id, department_name, department_code FROM department_table ORDER BY department_name";
                            $dept_result = $conn->query($dept_query);
                            if ($dept_result && $dept_result->num_rows > 0) {
                                while ($dept = $dept_result->fetch_assoc()) {
                                    echo '<option value="' . $dept['department_id'] . '">' . htmlspecialchars($dept['department_name']) . ' (' . htmlspecialchars($dept['department_code']) . ')</option>';
                                }
                            }
                            ?>
                        </select>
                        <small id="deptNote" class="form-note">Optional for Admins, Deans, Librarians, Coordinators. Required for Students and Research Advisers.</small>
                    </div>
                    
                    <!-- Status -->
                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select id="status" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                        <small class="form-note">Inactive users cannot login to the system</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        window.rolesData = <?php echo json_encode($users_by_role); ?>;
        window.notificationCount = <?php echo $notificationCount; ?>;
    </script>
    <script src="js/users.js"></script>
</body>
</html>