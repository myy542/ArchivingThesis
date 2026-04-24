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

// Get librarian's department
$librarian_query = "SELECT department_id FROM user_table WHERE user_id = ?";
$librarian_stmt = $conn->prepare($librarian_query);
$librarian_stmt->bind_param("i", $user_id);
$librarian_stmt->execute();
$librarian_result = $librarian_stmt->get_result();
$librarian_data = $librarian_result->fetch_assoc();
$librarian_department_id = $librarian_data['department_id'] ?? null;
$librarian_stmt->close();

// Get thesis ID from URL
$thesis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($thesis_id == 0) {
    header("Location: librarian_dashboard.php");
    exit;
}

// Get thesis details with department check
$thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email, d.department_name, d.department_code
                 FROM thesis_table t
                 JOIN user_table u ON t.student_id = u.user_id
                 LEFT JOIN department_table d ON t.department_id = d.department_id
                 WHERE t.thesis_id = ?";
$thesis_stmt = $conn->prepare($thesis_query);
$thesis_stmt->bind_param("i", $thesis_id);
$thesis_stmt->execute();
$thesis_result = $thesis_stmt->get_result();
$thesis = $thesis_result->fetch_assoc();
$thesis_stmt->close();

if (!$thesis) {
    header("Location: librarian_dashboard.php");
    exit;
}

// Check if librarian has access to this thesis (department check)
if ($librarian_department_id && $thesis['department_id'] != $librarian_department_id) {
    $_SESSION['error_message'] = "You are not authorized to view this thesis. This thesis belongs to a different department.";
    header("Location: librarian_dashboard.php");
    exit;
}

// Determine thesis status based on is_archived
$thesis_status = ($thesis['is_archived'] == 1) ? 'archived' : 'pending';

// Get archive details if exists
$archive = null;
$archive_query = "SELECT * FROM archive_table WHERE thesis_id = ?";
$archive_stmt = $conn->prepare($archive_query);
$archive_stmt->bind_param("i", $thesis_id);
$archive_stmt->execute();
$archive_result = $archive_stmt->get_result();
$archive = $archive_result->fetch_assoc();
$archive_stmt->close();

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

$pageTitle = "View Thesis Details";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/view_thesis.css">
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
                    <div class="notification-header"><h3>Notifications</h3><?php if ($notificationCount > 0): ?><button class="mark-all-read" id="markAllReadBtn">Mark all as read</button><?php endif; ?></div>
                    <div class="notification-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty"><div class="notif-icon"><i class="far fa-bell-slash"></i></div><div class="notif-content"><div class="notif-message">No notifications yet</div></div></div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <a href="<?= $notif['link'] ?? 'view_thesis.php?id=' . $notif['thesis_id'] ?>" class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>">
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
            <a href="archived_list.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Archived List</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <a href="javascript:history.back()" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>

        <div class="thesis-card">
            <div class="thesis-header">
                <h1 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?><span class="status-badge status-<?= $thesis_status ?>"><?= ucfirst($thesis_status) ?></span></h1>
            </div>

            <div class="info-grid">
                <div class="info-item"><div class="info-label"><i class="fas fa-user"></i> Student Name</div><div class="info-value"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-envelope"></i> Email</div><div class="info-value"><?= htmlspecialchars($thesis['email']) ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-user-tie"></i> Adviser</div><div class="info-value"><?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-building"></i> Department</div><div class="info-value"><?= htmlspecialchars($thesis['department_name'] ?? 'N/A') ?> (<?= htmlspecialchars($thesis['department_code'] ?? 'N/A') ?>)</div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-calendar"></i> Date Submitted</div><div class="info-value"><?= date('F d, Y', strtotime($thesis['date_submitted'])) ?></div></div>
                <div class="info-item"><div class="info-label"><i class="fas fa-tags"></i> Keywords</div><div class="info-value"><?= htmlspecialchars($thesis['keywords'] ?? 'N/A') ?></div></div>
            </div>

            <div class="abstract-section"><h3><i class="fas fa-align-left"></i> Abstract</h3><div class="abstract-text"><?= nl2br(htmlspecialchars($thesis['abstract'])) ?></div></div>

            <div class="file-section">
                <div class="file-info"><i class="fas fa-file-pdf"></i><div class="file-name"><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></div></div>
                <?php if (!empty($thesis['file_path'])): ?>
                <a href="<?= htmlspecialchars('../' . $thesis['file_path']) ?>" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>
                <?php endif; ?>
            </div>

            <?php if (!empty($thesis['file_path'])): 
                $full_file_path = '../' . $thesis['file_path'];
                if (file_exists($full_file_path)):
            ?>
            <div class="pdf-viewer"><iframe src="<?= htmlspecialchars($full_file_path) ?>"></iframe></div>
            <?php else: ?>
            <div class="pdf-viewer"><div class="pdf-error"><i class="fas fa-file-pdf"></i><p>PDF file not found on server.</p></div></div>
            <?php endif; ?>
            <?php else: ?>
            <div class="pdf-viewer"><div class="pdf-error"><i class="fas fa-file-pdf"></i><p>No manuscript file uploaded.</p></div></div>
            <?php endif; ?>

            <?php if ($thesis_status == 'archived'): ?>
            <div class="archive-info">
                <h4><i class="fas fa-archive"></i> Archive Information</h4>
                <div class="archive-details">
                    <div><div class="info-label">Archived Date</div><div class="info-value"><?= isset($thesis['archived_date']) ? date('F d, Y', strtotime($thesis['archived_date'])) : 'N/A' ?></div></div>
                    <div><div class="info-label">Retention Period</div><div class="info-value"><?= $archive['retention_period'] ?? '5' ?> years</div></div>
                    <div><div class="info-label">Status</div><div class="info-value">Archived</div></div>
                </div>
                <?php if (!empty($archive['archive_notes'])): ?>
                <div style="margin-top: 12px;"><div class="info-label">Archive Notes</div><div class="info-value"><?= htmlspecialchars($archive['archive_notes']) ?></div></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            notificationCount: <?php echo $notificationCount; ?>
        };
    </script>
    
    <script src="js/view_thesis.js"></script>
</body>
</html>