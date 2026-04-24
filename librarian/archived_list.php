<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA WITH DEPARTMENT
$user_query = "SELECT u.first_name, u.last_name, u.email, u.department_id, d.department_name, d.department_code 
               FROM user_table u
               LEFT JOIN department_table d ON u.department_id = d.department_id
               WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

$librarian_department_id = null;
if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $librarian_department_id = $user_data['department_id'];
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows) {
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
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, is_read, created_at, link FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    if ($row['thesis_id']) {
        $thesis_q = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
        $thesis_q->bind_param("i", $row['thesis_id']);
        $thesis_q->execute();
        $thesis_title = $thesis_q->get_result()->fetch_assoc();
        $row['thesis_title'] = $thesis_title['title'] ?? 'Unknown';
        $thesis_q->close();
    }
    $recentNotifications[] = $row;
}
$notif_list->close();

// MARK NOTIFICATION AS READ
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $update->bind_param("ii", $notif_id, $user_id);
    $update->execute();
    $update->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL AS READ
if (isset($_POST['mark_all_read'])) {
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET ARCHIVED THESES GROUPED BY DEPARTMENT (DEPARTMENT EXCLUSIVE)
$archived_by_dept = [];
$departments = ['BSIT', 'BSCRIM', 'BSHTM', 'BSED', 'BSBA'];

$dept_colors = [
    'BSIT' => '#3b82f6',
    'BSCRIM' => '#10b981',
    'BSHTM' => '#f59e0b',
    'BSED' => '#8b5cf6',
    'BSBA' => '#ef4444'
];

if ($librarian_department_id) {
    // Librarian with specific department - only see theses from that department
    // FIXED: Gi-remove ang ORDER BY t.department_code
    $archived_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                       FROM thesis_table t
                       JOIN user_table u ON t.student_id = u.user_id
                       LEFT JOIN department_table d ON t.department_id = d.department_id
                       WHERE t.is_archived = 1
                       AND t.department_id = ?
                       ORDER BY t.archived_date DESC";
    $archived_stmt = $conn->prepare($archived_query);
    $archived_stmt->bind_param("i", $librarian_department_id);
} else {
    // Librarian with no department - see all departments
    // FIXED: Gi-remove ang ORDER BY t.department_code
    $archived_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                       FROM thesis_table t
                       JOIN user_table u ON t.student_id = u.user_id
                       LEFT JOIN department_table d ON t.department_id = d.department_id
                       WHERE t.is_archived = 1
                       ORDER BY t.archived_date DESC";
    $archived_stmt = $conn->prepare($archived_query);
}
$archived_stmt->execute();
$archived_result = $archived_stmt->get_result();

if ($archived_result && $archived_result->num_rows > 0) {
    while ($row = $archived_result->fetch_assoc()) {
        $dept = $row['department_code'] ?? 'N/A';
        if (!isset($archived_by_dept[$dept])) {
            $archived_by_dept[$dept] = [];
        }
        $archived_by_dept[$dept][] = $row;
    }
}
$archived_stmt->close();

// Sort departments
$sorted_archived = [];
if ($librarian_department_id) {
    foreach ($archived_by_dept as $dept => $theses) {
        $sorted_archived[$dept] = $theses;
    }
} else {
    foreach ($departments as $dept) {
        if (isset($archived_by_dept[$dept])) {
            $sorted_archived[$dept] = $archived_by_dept[$dept];
        }
    }
    foreach ($archived_by_dept as $dept => $theses) {
        if (!in_array($dept, $departments)) {
            $sorted_archived[$dept] = $theses;
        }
    }
}

$total_archived = 0;
foreach ($sorted_archived as $theses) {
    $total_archived += count($theses);
}

$pageTitle = "Archived Theses List";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/archived_list.css">
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
                        <span class="notification-badge" id="notificationBadge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <button class="mark-all-read" id="markAllReadBtn">Mark all as read</button>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty"><div class="notif-icon"><i class="far fa-bell-slash"></i></div><div class="notif-content"><div class="notif-message">No notifications yet</div></div></div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <a href="<?= $notif['link'] ?? 'librarian_dashboard.php' ?>" class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>">
                                    <div class="notif-icon"><i class="fas fa-bell"></i></div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time"><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer"><a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a></div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="#"><i class="fas fa-cog"></i> Settings</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">LIBRARIAN</div></div>
        <div class="nav-menu">
            <a href="librarian_dashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="archived_list.php" class="nav-item active"><i class="fas fa-folder-open"></i><span>Archived List</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-archive"></i> Archived Theses</h1>
            <p>List of all archived theses organized by department</p>
            <?php if ($librarian_department_id): ?>
                <p class="dept-info"><i class="fas fa-building"></i> Showing theses from your assigned department only</p>
            <?php endif; ?>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($total_archived) ?></h3><p>Total Archived</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-building"></i></div><div class="stat-details"><h3><?= number_format(count($sorted_archived)) ?></h3><p>Departments</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar"></i></div><div class="stat-details"><h3><?= date('Y') ?></h3><p>Current Year</p></div></div>
        </div>

        <?php if (empty($sorted_archived)): ?>
            <div class="dept-archive-section">
                <div class="empty-state"><i class="fas fa-archive"></i><p>No archived theses yet.</p></div>
            </div>
        <?php else: ?>
            <?php foreach ($sorted_archived as $dept => $theses): 
                $dept_color = $dept_colors[$dept] ?? '#6b7280';
            ?>
            <div class="dept-archive-section">
                <div class="dept-archive-header">
                    <span class="dept-dot" style="background: <?= $dept_color ?>;"></span>
                    <h3><?= htmlspecialchars($dept) ?></h3>
                    <span class="badge"><?= count($theses) ?> theses</span>
                </div>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr><th>ID</th><th>Thesis Title</th><th>Author</th><th>Student</th><th>Archived Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($theses as $thesis): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['adviser'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></td>
                                <td><?= isset($thesis['archived_date']) ? date('M d, Y', strtotime($thesis['archived_date'])) : date('M d, Y', strtotime($thesis['date_submitted'])) ?></td>
                                <td><span class="status-badge archived"><i class="fas fa-check-circle"></i> Archived</span></td>
                                <td><a href="view_thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            notificationCount: <?php echo $notificationCount; ?>
        };
    </script>
    
    <script src="js/archived_list.js"></script>
</body>
</html>