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
$librarian_department_name = 'All Departments';
$librarian_department_code = 'ALL';

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
    $librarian_department_id = $user_data['department_id'];
    if ($librarian_department_id) {
        $librarian_department_name = $user_data['department_name'] ?? 'Department';
        $librarian_department_code = $user_data['department_code'] ?? '';
    }
}

$librarian_since = date('F Y');

// Create notifications table if not exists
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

// Get all departments
$departments = [];
$dept_query = "SELECT department_id, department_name, department_code FROM department_table ORDER BY department_name";
$dept_result = $conn->query($dept_query);
if ($dept_result && $dept_result->num_rows > 0) {
    while ($dept = $dept_result->fetch_assoc()) {
        $departments[] = $dept;
    }
}

$dept_colors = [
    'BSIT' => '#3b82f6', 'BSCRIM' => '#10b981', 'BSHTM' => '#f59e0b',
    'BSED' => '#8b5cf6', 'BSBA' => '#ef4444'
];
$default_color = '#6b7280';

// ==================== GET PENDING THESES (DEPARTMENT EXCLUSIVE) ====================
$pending_theses = [];
if ($librarian_department_id) {
    // Librarian with specific department - only see theses from that department
    $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                      FROM thesis_table t
                      JOIN user_table u ON t.student_id = u.user_id
                      LEFT JOIN department_table d ON t.department_id = d.department_id
                      WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                      AND t.department_id = ?
                      ORDER BY t.date_submitted DESC";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->bind_param("i", $librarian_department_id);
} else {
    // Librarian with no department (all departments)
    $pending_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                      FROM thesis_table t
                      JOIN user_table u ON t.student_id = u.user_id
                      LEFT JOIN department_table d ON t.department_id = d.department_id
                      WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                      ORDER BY t.date_submitted DESC";
    $pending_stmt = $conn->prepare($pending_query);
}
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
if ($pending_result && $pending_result->num_rows > 0) {
    while ($row = $pending_result->fetch_assoc()) {
        $pending_theses[] = $row;
    }
}
$pending_stmt->close();

// Prepare pending by department for display
$pending_by_dept = [];
if (!$librarian_department_id) {
    foreach ($pending_theses as $thesis) {
        $dept_code = $thesis['department_code'] ?? 'N/A';
        if (!isset($pending_by_dept[$dept_code])) $pending_by_dept[$dept_code] = [];
        $pending_by_dept[$dept_code][] = $thesis;
    }
    uksort($pending_by_dept, function($a, $b) {
        $order = ['BSIT', 'BSCRIM', 'BSHTM', 'BSED', 'BSBA'];
        $pos_a = array_search($a, $order); $pos_b = array_search($b, $order);
        if ($pos_a === false) $pos_a = 999; if ($pos_b === false) $pos_b = 999;
        return $pos_a - $pos_b;
    });
}

// GET FILTERS
$selected_department_code = isset($_GET['department']) ? $_GET['department'] : '';
$selected_year = isset($_GET['year']) ? $_GET['year'] : '';
$selected_sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// ==================== GET ARCHIVED THESES (DEPARTMENT EXCLUSIVE) ====================
$archived_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                    FROM thesis_table t
                    JOIN user_table u ON t.student_id = u.user_id
                    LEFT JOIN department_table d ON t.department_id = d.department_id
                    WHERE t.is_archived = 1";

if ($librarian_department_id) {
    $archived_query .= " AND t.department_id = " . intval($librarian_department_id);
}
if (!empty($selected_department_code)) {
    $dept_id_query = "SELECT department_id FROM department_table WHERE department_code = ?";
    $dept_id_stmt = $conn->prepare($dept_id_query);
    $dept_id_stmt->bind_param("s", $selected_department_code);
    $dept_id_stmt->execute();
    $dept_id_result = $dept_id_stmt->get_result();
    if ($dept_row = $dept_id_result->fetch_assoc()) {
        $archived_query .= " AND t.department_id = " . $dept_row['department_id'];
    }
    $dept_id_stmt->close();
}
if (!empty($selected_year)) {
    $archived_query .= " AND YEAR(t.date_submitted) = '" . $conn->real_escape_string($selected_year) . "'";
}

switch ($selected_sort) {
    case 'date_asc': $archived_query .= " ORDER BY t.archived_date ASC"; break;
    case 'title_asc': $archived_query .= " ORDER BY t.title ASC"; break;
    case 'title_desc': $archived_query .= " ORDER BY t.title DESC"; break;
    default: $archived_query .= " ORDER BY t.archived_date DESC"; break;
}

$archived_result = $conn->query($archived_query);
$archived_theses = [];
if ($archived_result && $archived_result->num_rows > 0) {
    while ($row = $archived_result->fetch_assoc()) {
        $archived_theses[] = $row;
    }
}

// GET UNIQUE YEARS
$years = [];
$year_query = "SELECT DISTINCT YEAR(date_submitted) as year 
               FROM thesis_table 
               WHERE date_submitted IS NOT NULL";
if ($librarian_department_id) {
    $year_query .= " AND department_id = " . intval($librarian_department_id);
}
$year_query .= " ORDER BY year DESC";
$year_result = $conn->query($year_query);
if ($year_result && $year_result->num_rows > 0) {
    while ($row = $year_result->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

$stats = [
    'pending_archive' => count($pending_theses),
    'archived' => count($archived_theses),
    'total_archived' => count($archived_theses)
];

$pageTitle = "Librarian Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/librarian_dashboard.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
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
                                <?php $link = ($notif['thesis_id'] > 0) ? 'view_thesis.php?id=' . $notif['thesis_id'] : ($notif['link'] ?? 'librarian_dashboard.php'); ?>
                                <a href="<?= htmlspecialchars($link) ?>" class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>" data-link="<?= htmlspecialchars($link) ?>">
                                    <div class="notif-icon"><?php if(strpos($notif['message'], 'archive') !== false): ?><i class="fas fa-archive"></i><?php elseif(strpos($notif['message'], 'approved') !== false): ?><i class="fas fa-check-circle"></i><?php else: ?><i class="fas fa-bell"></i><?php endif; ?></div>
                                    <div class="notif-content"><div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div><div class="notif-time"><i class="far fa-clock"></i> <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></div></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer"><a href="notifications.php">View all notifications <i class="fas fa-arrow-right"></i></a></div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="librarian_profile.php"><i class="fas fa-user"></i> Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">LIBRARIAN</div></div>
        <div class="nav-menu">
            <a href="librarian_dashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="archived_list.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Archived List</span></a>
        </div>
        <div class="nav-footer"><a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
    </aside>

    <main class="main-content">
        <div class="librarian-banner">
            <div class="librarian-info">
                <h1>Librarian Dashboard</h1>
                <p>Manage and archive approved theses</p>
                <?php if ($librarian_department_id): ?>
                    <p class="dept-badge" style="margin-top: 8px; font-size: 0.8rem;"><i class="fas fa-building"></i> Managing: <?= htmlspecialchars($librarian_department_name) ?> (<?= htmlspecialchars($librarian_department_code) ?>)</p>
                <?php else: ?>
                    <p class="dept-badge" style="margin-top: 8px; font-size: 0.8rem;"><i class="fas fa-globe"></i> Managing: All Departments</p>
                <?php endif; ?>
            </div>
            <div class="librarian-details"><div class="librarian-name"><?= htmlspecialchars($fullName) ?></div><div class="librarian-since">Librarian since <?= htmlspecialchars($librarian_since) ?></div></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-details"><h3><?= number_format($stats['pending_archive']) ?></h3><p>Pending Archive</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($stats['archived']) ?></h3><p>Archived Theses</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-details"><h3><?= number_format($stats['total_archived']) ?></h3><p>Total Archived</p></div></div>
        </div>

        <div class="pending-card">
            <h3><i class="fas fa-clock"></i> Theses Pending for Archiving (<?= count($pending_theses) ?>)</h3>
            <?php if (empty($pending_theses)): ?>
                <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending theses for archiving</p></div>
            <?php else: ?>
                <?php if ($librarian_department_id): ?>
                    <div class="pending-dept-list">
                        <?php foreach ($pending_theses as $thesis): ?>
                        <div class="pending-item">
                            <div class="pending-info"><div class="pending-title"><?= htmlspecialchars($thesis['title']) ?></div><div class="pending-meta"><span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span><span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></span></div></div>
                            <div class="button-group"><a href="view_thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a><button type="button" class="btn-archive" onclick="openArchiveModal(<?= $thesis['thesis_id'] ?>)"><i class="fas fa-archive"></i> Archive</button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_by_dept as $dept_code => $theses): 
                        $dept_color = $dept_colors[$dept_code] ?? $default_color;
                    ?>
                    <div class="pending-dept-section">
                        <div class="pending-dept-header"><span class="pending-dept-dot" style="background: <?= $dept_color ?>;"></span><h4><?= htmlspecialchars($dept_code) ?></h4><span class="pending-dept-badge"><?= count($theses) ?> pending</span></div>
                        <div class="pending-dept-list">
                            <?php foreach ($theses as $thesis): ?>
                            <div class="pending-item">
                                <div class="pending-info"><div class="pending-title"><?= htmlspecialchars($thesis['title']) ?></div><div class="pending-meta"><span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span><span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></span></div></div>
                                <div class="button-group"><a href="view_thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a><button type="button" class="btn-archive" onclick="openArchiveModal(<?= $thesis['thesis_id'] ?>)"><i class="fas fa-archive"></i> Archive</button></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="filter-bar">
            <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; width: 100%;">
                <?php if (!$librarian_department_id): ?>
                <select name="department" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php 
                    // FIXED: Check if $departments array has data before looping
                    if (!empty($departments) && is_array($departments)):
                        foreach ($departments as $dept): 
                            $dept_code = isset($dept['department_code']) ? $dept['department_code'] : '';
                            $dept_name = isset($dept['department_name']) ? $dept['department_name'] : '';
                    ?>
                        <option value="<?= htmlspecialchars($dept_code) ?>" <?= $selected_department_code == $dept_code ? 'selected' : '' ?>><?= htmlspecialchars($dept_name) ?> (<?= htmlspecialchars($dept_code) ?>)</option>
                    <?php 
                        endforeach;
                    else:
                    ?>
                        <option value="">No departments available</option>
                    <?php endif; ?>
                </select>
                <?php endif; ?>
                <select name="year" class="filter-select" onchange="this.form.submit()">
                    <option value="">All Years</option>
                    <?php if (!empty($years) && is_array($years)): ?>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= $selected_year == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="date_desc" <?= $selected_sort == 'date_desc' ? 'selected' : '' ?>>Latest First</option>
                    <option value="date_asc" <?= $selected_sort == 'date_asc' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="title_asc" <?= $selected_sort == 'title_asc' ? 'selected' : '' ?>>Title A-Z</option>
                    <option value="title_desc" <?= $selected_sort == 'title_desc' ? 'selected' : '' ?>>Title Z-A</option>
                </select>
                <?php if (!empty($selected_department_code) || !empty($selected_year) || $selected_sort != 'date_desc'): ?>
                    <a href="librarian_dashboard.php" class="clear-btn"><i class="fas fa-times"></i> Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="archived-section">
            <h3><i class="fas fa-archive"></i> Archived Theses <span class="archived-stats">(<?= count($archived_theses) ?> found)</span></h3>
            <?php if (empty($archived_theses)): ?>
                <div class="empty-state"><i class="fas fa-archive"></i><p>No archived theses found</p><?php if (!empty($selected_department_code) || !empty($selected_year)): ?><p style="margin-top: 10px;"><a href="librarian_dashboard.php" style="color: #dc2626;">Clear filters</a> to see all archives</p><?php endif; ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr><th>Thesis Title</th><th>Author</th><th>Department</th><th>Archived Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($archived_theses as $thesis): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></td>
                                <td><?= htmlspecialchars($thesis['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($thesis['department_code'] ?? 'N/A') ?>)</span></td>
                                <td><?= isset($thesis['archived_date']) ? date('M d, Y', strtotime($thesis['archived_date'])) : date('M d, Y', strtotime($thesis['date_submitted'])) ?></td>
                                <td><span class="status-badge"><i class="fas fa-check-circle"></i> Archived</span></td>
                                <td><a href="view_thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <div id="archiveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3><i class="fas fa-archive"></i> Archive Thesis</h3><span class="close-modal" onclick="closeArchiveModal()">&times;</span></div>
            <div class="modal-body">
                <input type="hidden" id="archive_thesis_id" value="">
                <div class="form-group">
                    <label>Retention Period</label>
                    <select id="retention_period">
                        <option value="5">5 years</option>
                        <option value="10">10 years</option>
                        <option value="20">20 years</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Archive Notes</label>
                    <textarea id="archive_notes" rows="3" placeholder="Optional notes about this archive..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeArchiveModal()">Cancel</button>
                <button type="button" class="btn-confirm" onclick="confirmArchive()"><i class="fas fa-archive"></i> Confirm Archive</button>
            </div>
        </div>
    </div>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            notificationCount: <?php echo $notificationCount; ?>,
            librarianDepartmentId: <?php echo $librarian_department_id ? $librarian_department_id : 'null'; ?>
        };
        
        function openArchiveModal(thesisId) {
            document.getElementById('archive_thesis_id').value = thesisId;
            document.getElementById('archiveModal').classList.add('show');
        }
        
        function closeArchiveModal() {
            document.getElementById('archiveModal').classList.remove('show');
            document.getElementById('archive_thesis_id').value = '';
            document.getElementById('archive_notes').value = '';
        }
        
        function confirmArchive() {
            var thesisId = document.getElementById('archive_thesis_id').value;
            var retentionPeriod = document.getElementById('retention_period').value;
            var archiveNotes = document.getElementById('archive_notes').value;
            
            if (!thesisId) {
                alert('Invalid thesis ID');
                return;
            }
            
            var btn = document.querySelector('#archiveModal .btn-confirm');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Archiving...';
            btn.disabled = true;
            
            fetch('librarian_archive.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'thesis_id=' + thesisId + '&retention_period=' + retentionPeriod + '&archive_notes=' + encodeURIComponent(archiveNotes)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Thesis archived successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + (data.message || 'Failed to archive thesis'));
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('archiveModal');
            if (event.target == modal) {
                closeArchiveModal();
            }
        }
    </script>
    
    <script src="js/librarian_dashboard.js"></script>
</body>
</html>