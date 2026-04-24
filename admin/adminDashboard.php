<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

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
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

// Member since - default date if no created_at
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

$check_ip = $conn->query("SHOW COLUMNS FROM audit_logs LIKE 'ip_address'");
if (!$check_ip || $check_ip->num_rows == 0) {
    $conn->query("ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) AFTER description");
}

function logAdminAction($conn, $user_id, $action, $table, $record_id, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    if ($ip == '::1') $ip = '127.0.0.1';
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $log_stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip);
    $log_stmt->execute();
    $log_stmt->close();
}

// ==================== NOTIFICATION HANDLERS ====================
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

if (isset($_POST['mark_all_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    echo json_encode(['success' => true]);
    exit;
}

// DASHBOARDS
$dashboards = [
    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php', 'role_id' => 1],
    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php', 'role_id' => 2],
    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php', 'role_id' => 3],
    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php', 'role_id' => 4],
    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php', 'role_id' => 5],
    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php', 'role_id' => 6]
];

// STATISTICS
$stats = [];
foreach ($dashboards as $id => $dash) {
    $result = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE role_id = $id AND status = 'Active'");
    $row = $result->fetch_assoc();
    $stats[$dash['name']] = $row['c'];
}
$total = $conn->query("SELECT COUNT(*) as c FROM user_table WHERE status = 'Active'")->fetch_assoc()['c'];
$stats['Total Users'] = $total;

$all_users_count = $conn->query("SELECT COUNT(*) as c FROM user_table")->fetch_assoc()['c'];
$active_users = $stats['Total Users'];
$inactive_users = $all_users_count - $active_users;

// MONTHLY DATA
$monthly = array_fill(0, 12, 0);
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$has_created = $conn->query("SHOW COLUMNS FROM user_table LIKE 'created_at'");
if ($has_created && $has_created->num_rows > 0) {
    $m = $conn->query("SELECT MONTH(created_at) as mo, COUNT(*) as c FROM user_table WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)");
    if ($m && $m->num_rows > 0) {
        while ($r = $m->fetch_assoc()) {
            $monthly[$r['mo']-1] = $r['c'];
        }
    }
}

$hasMonthlyData = false;
foreach ($monthly as $val) {
    if ($val > 0) {
        $hasMonthlyData = true;
        break;
    }
}

$sample_monthly_data = [3, 5, 7, 9, 12, 15, 18, 20, 16, 12, 8, 4];
if (!$hasMonthlyData) {
    $monthly = $sample_monthly_data;
}

// GET AUDIT LOGS COUNT
$logs_count = 0;
$logs_result = $conn->query("SELECT COUNT(*) as c FROM audit_logs");
if ($logs_result) {
    $logs_count = $logs_result->fetch_assoc()['c'];
}

// GET THESES COUNT
$theses_count = 0;
$check_theses_table = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_theses_table && $check_theses_table->num_rows > 0) {
    $theses_result = $conn->query("SELECT COUNT(*) as c FROM thesis_table");
    if ($theses_result) {
        $theses_count = $theses_result->fetch_assoc()['c'];
    }
}

// NOTIFICATION SYSTEM
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

$check_is_read = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
if (!$check_is_read || $check_is_read->num_rows == 0) {
    $conn->query("ALTER TABLE notifications ADD COLUMN is_read TINYINT DEFAULT 0");
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows > 0) {
    $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $n->bind_param("i", $user_id);
    $n->execute();
    $result = $n->get_result();
    if ($row = $result->fetch_assoc()) {
        $notificationCount = $row['c'];
    }
    $n->close();
}

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- External CSS -->
    <link rel="stylesheet" href="css/admindashboard.css">
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
                <input type="text" id="searchInput" placeholder="Search...">
            </div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge" id="notificationBadge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <?php if ($notificationCount > 0): ?>
                            <a href="#" id="markAllReadBtn">Mark all as read</a>
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
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>" data-link="<?= htmlspecialchars($notif['link'] ?? '#') ?>">
                                    <div class="notif-icon">
                                        <?php if(strpos($notif['message'], 'registration') !== false): ?>
                                            <i class="fas fa-user-plus"></i>
                                        <?php elseif(strpos($notif['message'], 'thesis') !== false): ?>
                                            <i class="fas fa-file-alt"></i>
                                        <?php elseif(strpos($notif['message'], 'approved') !== false): ?>
                                            <i class="fas fa-check-circle"></i>
                                        <?php else: ?>
                                            <i class="fas fa-bell"></i>
                                        <?php endif; ?>
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
                                </div>
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
            <a href="admindashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
            <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
            <a href="theses.php" class="theses-link"><i class="fas fa-file-alt"></i><span>Theses</span></a>
            <a href="backup_management.php" class="nav-item"><i class="fas fa-database"></i><span>Backup</span></a>
        </div>
        <div class="dashboard-links">
            <div class="dashboard-links-header">
                <i class="fas fa-chalkboard-user"></i><span>Quick Access</span>
            </div>
            <?php foreach ($dashboards as $dashboard): ?>
            <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank">
                <i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i>
                <span><?= $dashboard['name'] ?> Dashboard</span>
                <i class="fas fa-external-link-alt link-icon"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                    <span class="slider"></span>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </div>
    </aside>
    
    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($first_name) ?>! • System Overview</p>
            </div>
            <div class="admin-info">
                <div class="admin-name"><?= htmlspecialchars($fullName) ?></div>
                <div class="admin-since">Admin since <?= $user_created ?></div>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Total Users']) ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Student']) ?></h3>
                    <p>Students</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Research Adviser']) ?></h3>
                    <p>Research Advisers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-details">
                    <h3><?= number_format($stats['Dean']) ?></h3>
                    <p>Deans</p>
                </div>
            </div>
        </div>
        
        <div class="stats-grid-second">
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-book-reader"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Librarian']) ?></h4>
                    <p>Librarians</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Coordinator']) ?></h4>
                    <p>Coordinators</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-user-shield"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($stats['Admin']) ?></h4>
                    <p>Admins</p>
                </div>
            </div>
            <div class="stat-card-small">
                <div class="stat-icon-small"><i class="fas fa-file-alt"></i></div>
                <div class="stat-details-small">
                    <h4><?= number_format($theses_count) ?></h4>
                    <p>Theses</p>
                </div>
            </div>
        </div>
        
        <div class="charts-row">
            <div class="chart-card">
                <h3><i class="fas fa-chart-pie"></i> User Distribution by Role</h3>
                <div class="chart-container">
                    <canvas id="userDistributionChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3><i class="fas fa-chart-line"></i> User Registration Trend</h3>
                <div class="chart-container">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="cards-row">
            <div class="info-card">
                <h3><i class="fas fa-users"></i> User Management</h3>
                <div class="info-stats">
                    <div class="info-stat">
                        <div class="number"><?= $active_users ?></div>
                        <div class="label">Active</div>
                    </div>
                    <div class="info-stat">
                        <div class="number"><?= $inactive_users ?></div>
                        <div class="label">Inactive</div>
                    </div>
                    <div class="info-stat">
                        <div class="number"><?= $all_users_count ?></div>
                        <div class="label">Total</div>
                    </div>
                </div>
                <a href="users.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> Manage Users</a>
            </div>
            <div class="info-card">
                <h3><i class="fas fa-file-alt"></i> Theses Management</h3>
                <div class="info-stats">
                    <div class="info-stat">
                        <div class="number"><?= $theses_count ?></div>
                        <div class="label">Total Theses</div>
                    </div>
                </div>
                <a href="theses.php" class="btn-view-all"><i class="fas fa-arrow-right"></i> Manage Theses</a>
            </div>
        </div>
    </main>
    
    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <span class="close-modal" onclick="closeAddUserModal()">&times;</span>
            </div>
            <form id="addUserForm" method="POST" action="users.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span style="color:#d32f2f;">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span style="color:#d32f2f;">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:#d32f2f;">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Username <span style="color:#d32f2f;">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span id="passwordRequired" style="color:#d32f2f;">*</span></label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password (min. 6 characters)">
                        <small id="passwordNote" style="font-size:0.7rem; color:#6b7280; display:none;">Leave blank to keep current password</small>
                    </div>
                    <div class="form-group">
                        <label>Role <span style="color:#d32f2f;">*</span></label>
                        <select name="role_id" id="roleSelect" class="form-control" required>
                            <option value="1">Admin</option>
                            <option value="2">Student</option>
                            <option value="3">Research Adviser</option>
                            <option value="4">Dean</option>
                            <option value="5">Librarian</option>
                            <option value="6">Coordinator</option>
                        </select>
                    </div>
                    <div class="form-group" id="departmentGroup" style="display: none;">
                        <label>Department <span style="color:#d32f2f;">*</span></label>
                        <select name="department" id="departmentSelect" class="form-control">
                            <option value="">-- Select Department --</option>
                            <option value="BSIT">BS Information Technology (BSIT)</option>
                            <option value="BSCRIM">BS Criminology (BSCRIM)</option>
                            <option value="BSHTM">BS Hospitality Management (BSHTM)</option>
                            <option value="BSED">BS Education (BSED)</option>
                            <option value="BSBA">BS Business Administration (BSBA)</option>
                        </select>
                        <small style="font-size:0.7rem; color:#6b7280;">Required for Students and Research Advisers only</small>
                    </div>
                    <div class="form-group">
                        <label>Status <span style="color:#d32f2f;">*</span></label>
                        <select name="status" class="form-control">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        window.userData = {
            stats: { 
                students: <?= $stats['Student'] ?? 0 ?>, 
                research_advisers: <?= $stats['Research Adviser'] ?? 0 ?>, 
                deans: <?= $stats['Dean'] ?? 0 ?>, 
                librarians: <?= $stats['Librarian'] ?? 0 ?>, 
                coordinators: <?= $stats['Coordinator'] ?? 0 ?>, 
                admins: <?= $stats['Admin'] ?? 0 ?> 
            },
            monthlyData: <?= json_encode($monthly) ?>, 
            months: <?= json_encode($months) ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/admindashboard.js"></script>
</body>
</html>