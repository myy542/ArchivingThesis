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

// Get thesis ID from URL
$thesis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($thesis_id == 0) {
    header("Location: librarian_dashboard.php");
    exit;
}

// Get thesis details
$thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                 FROM thesis_table t
                 JOIN user_table u ON t.student_id = u.user_id
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

// Get archive details if exists (check if table exists first)
$archive = null;
$check_archive_table = $conn->query("SHOW TABLES LIKE 'archive_table'");
if ($check_archive_table && $check_archive_table->num_rows > 0) {
    $archive_query = "SELECT * FROM archive_table WHERE thesis_id = ?";
    $archive_stmt = $conn->prepare($archive_query);
    $archive_stmt->bind_param("i", $thesis_id);
    $archive_stmt->execute();
    $archive_result = $archive_stmt->get_result();
    $archive = $archive_result->fetch_assoc();
    $archive_stmt->close();
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($notif_check && $notif_check->num_rows) {
    $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND status = 0");
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
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, status, created_at, link FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
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
    $update = $conn->prepare("UPDATE notifications SET status = 1 WHERE notification_id = ? AND user_id = ?");
    $update->bind_param("ii", $notif_id, $user_id);
    $update->execute();
    $update->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL AS READ
if (isset($_POST['mark_all_read'])) {
    $update = $conn->prepare("UPDATE notifications SET status = 1 WHERE user_id = ?");
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; overflow-x: hidden; }

        .top-nav {
            position: fixed; top: 0; right: 0; left: 0; height: 70px;
            background: white; display: flex; align-items: center;
            justify-content: space-between; padding: 0 32px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05); z-index: 99;
            border-bottom: 1px solid #fee2e2;
        }
        .nav-left { display: flex; align-items: center; gap: 24px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px;
            background: #fef2f2; border: none; border-radius: 8px; cursor: pointer;
            align-items: center; justify-content: center; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        .hamburger:hover { background: #fee2e2; }
        .logo { font-size: 1.3rem; font-weight: 700; color: #991b1b; }
        .logo span { color: #dc2626; }
        .search-area { display: flex; align-items: center; background: #fef2f2; padding: 8px 16px; border-radius: 40px; gap: 10px; }
        .search-area i { color: #dc2626; }
        .search-area input { border: none; background: none; outline: none; font-size: 0.85rem; width: 200px; }
        
        .nav-right { display: flex; align-items: center; gap: 20px; position: relative; }
        
        .notification-container { position: relative; }
        .notification-icon { position: relative; cursor: pointer; width: 40px; height: 40px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
        .notification-icon:hover { background: #fee2e2; }
        .notification-icon i { font-size: 1.2rem; color: #dc2626; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 600; min-width: 18px; height: 18px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
        
        .notification-dropdown { position: absolute; top: 55px; right: 0; width: 380px; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: none; overflow: hidden; z-index: 1000; border: 1px solid #ffcdd2; animation: fadeSlideDown 0.2s ease; }
        .notification-dropdown.show { display: block; }
        @keyframes fadeSlideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .notification-header { padding: 16px 20px; border-bottom: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center; }
        .notification-header h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin: 0; }
        .mark-all-read { font-size: 0.7rem; color: #dc2626; cursor: pointer; background: none; border: none; }
        .notification-list { max-height: 400px; overflow-y: auto; }
        .notification-item { display: flex; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #fef2f2; cursor: pointer; transition: background 0.2s; text-decoration: none; color: inherit; }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-item.empty { justify-content: center; color: #9ca3af; cursor: default; }
        .notif-icon { width: 36px; height: 36px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #dc2626; flex-shrink: 0; }
        .notif-content { flex: 1; }
        .notif-message { font-size: 0.8rem; color: #1f2937; margin-bottom: 4px; line-height: 1.4; }
        .notif-time { font-size: 0.65rem; color: #9ca3af; }
        .notification-footer { padding: 12px 20px; border-top: 1px solid #fee2e2; text-align: center; }
        .notification-footer a { color: #dc2626; text-decoration: none; font-size: 0.8rem; }
        
        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 5px 10px; border-radius: 40px; }
        .profile-trigger:hover { background: #fee2e2; }
        .profile-name { font-weight: 500; font-size: 0.9rem; }
        .profile-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #dc2626, #991b1b); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; background: white; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); min-width: 200px; display: none; overflow: hidden; z-index: 1000; border: 1px solid #ffcdd2; }
        .profile-dropdown.show { display: block; }
        .profile-dropdown a { display: flex; align-items: center; gap: 12px; padding: 12px 18px; text-decoration: none; color: #1f2937; transition: 0.2s; font-size: 0.85rem; }
        .profile-dropdown a:hover { background: #fef2f2; color: #dc2626; }
        .profile-dropdown hr { margin: 5px 0; border-color: #ffcdd2; }
        
        .sidebar { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex; flex-direction: column; z-index: 1000; transition: left 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .logo-container .logo { color: white; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 6px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 4px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; transition: all 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.15); }
        .theme-toggle { margin-bottom: 12px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #dc2626; text-decoration: none; margin-bottom: 20px; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        
        .thesis-card { background: white; border-radius: 24px; padding: 32px; margin-bottom: 32px; border: 1px solid #fee2e2; }
        .thesis-header { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f0f0f0; }
        .thesis-title { font-size: 1.5rem; font-weight: 700; color: #1f2937; }
        .status-badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-left: 15px; }
        .status-archived { background: #d1ecf1; color: #0c5460; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px; }
        .info-item { background: #f8fafc; padding: 16px; border-radius: 12px; }
        .info-label { font-size: 0.75rem; color: #6b7280; margin-bottom: 5px; }
        .info-value { font-size: 0.95rem; font-weight: 500; color: #1f2937; }
        
        .abstract-section { margin-bottom: 24px; }
        .abstract-section h3 { font-size: 1rem; font-weight: 600; color: #991b1b; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .abstract-text { background: #f8fafc; padding: 20px; border-radius: 12px; line-height: 1.6; }
        
        .file-section { background: #f8fafc; border-radius: 16px; padding: 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .file-info { display: flex; align-items: center; gap: 12px; }
        .file-info i { font-size: 2rem; color: #dc2626; }
        .download-btn { background: #dc2626; color: white; padding: 10px 20px; border-radius: 30px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; }
        .download-btn:hover { background: #991b1b; transform: translateY(-2px); }
        
        .pdf-viewer { margin-top: 1rem; border-radius: 12px; overflow: hidden; border: 1px solid #fee2e2; background: white; }
        .pdf-viewer iframe { width: 100%; height: 600px; border: none; }
        .pdf-viewer .pdf-error { padding: 40px; text-align: center; color: #9ca3af; background: #fef2f2; }
        
        .archive-info { background: #e8f5e9; border-radius: 16px; padding: 20px; margin-top: 20px; border-left: 4px solid #10b981; }
        .archive-info h4 { font-size: 0.9rem; font-weight: 600; color: #2e7d32; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .archive-details { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 10px; }
        
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .info-grid { grid-template-columns: 1fr; }
            .archive-details { grid-template-columns: 1fr; }
            .search-area, .profile-name { display: none; }
            .pdf-viewer iframe { height: 400px; }
        }
        
        @media (max-width: 480px) {
            .thesis-card { padding: 20px; }
            .thesis-title { font-size: 1.2rem; }
            .pdf-viewer iframe { height: 300px; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav, body.dark-mode .thesis-card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .thesis-title { color: #fecaca; }
        body.dark-mode .info-item, body.dark-mode .abstract-text, body.dark-mode .file-section { background: #3d3d3d; }
        body.dark-mode .info-value { color: #e5e7eb; }
        body.dark-mode .abstract-text { color: #cbd5e1; }
        body.dark-mode .archive-info { background: #1a3a1a; }
        body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .notification-item:hover { background: #3d3d3d; }
        body.dark-mode .notification-item.unread { background: #3a2a2a; }
    </style>
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
                                <a href="<?= $notif['link'] ?? 'view_thesis.php?id=' . $notif['thesis_id'] ?>" class="notification-item <?= $notif['status'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>">
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
                <h1 class="thesis-title">
                    <?= htmlspecialchars($thesis['title']) ?>
                    <span class="status-badge status-<?= $thesis['status'] ?>"><?= ucfirst($thesis['status']) ?></span>
                </h1>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user"></i> Student Name</div>
                    <div class="info-value"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="info-value"><?= htmlspecialchars($thesis['email']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-tie"></i> Adviser</div>
                    <div class="info-value"><?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-building"></i> Department</div>
                    <div class="info-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar"></i> Date Submitted</div>
                    <div class="info-value"><?= date('F d, Y', strtotime($thesis['date_submitted'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-tags"></i> Keywords</div>
                    <div class="info-value"><?= htmlspecialchars($thesis['keywords'] ?? 'N/A') ?></div>
                </div>
            </div>

            <div class="abstract-section">
                <h3><i class="fas fa-align-left"></i> Abstract</h3>
                <div class="abstract-text">
                    <?= nl2br(htmlspecialchars($thesis['abstract'])) ?>
                </div>
            </div>

            <!-- Manuscript File Section -->
            <div class="file-section">
                <div class="file-info">
                    <i class="fas fa-file-pdf"></i>
                    <div class="file-name"><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></div>
                </div>
                <?php if (!empty($thesis['file_path'])): ?>
                <a href="<?= htmlspecialchars('../' . $thesis['file_path']) ?>" class="download-btn" download><i class="fas fa-download"></i> Download PDF</a>
                <?php endif; ?>
            </div>

            <!-- PDF Viewer -->
            <?php if (!empty($thesis['file_path'])): 
                $full_file_path = '../' . $thesis['file_path'];
                if (file_exists($full_file_path)):
            ?>
            <div class="pdf-viewer">
                <iframe src="<?= htmlspecialchars($full_file_path) ?>"></iframe>
            </div>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>PDF file not found on server.</p></div>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <div class="pdf-viewer">
                <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>No manuscript file uploaded.</p></div>
            </div>
            <?php endif; ?>

            <!-- Archive Information -->
            <?php if ($thesis['status'] == 'archived' && $archive): ?>
            <div class="archive-info">
                <h4><i class="fas fa-archive"></i> Archive Information</h4>
                <div class="archive-details">
                    <div>
                        <div class="info-label">Archived Date</div>
                        <div class="info-value"><?= isset($thesis['archived_date']) ? date('F d, Y', strtotime($thesis['archived_date'])) : 'N/A' ?></div>
                    </div>
                    <div>
                        <div class="info-label">Retention Period</div>
                        <div class="info-value"><?= $archive['retention_period'] ?? '5' ?> years</div>
                    </div>
                    <div>
                        <div class="info-label">Access Level</div>
                        <div class="info-value"><?= ucfirst($archive['access_level'] ?? 'Public') ?></div>
                    </div>
                </div>
                <?php if (!empty($archive['archive_notes'])): ?>
                <div style="margin-top: 12px;">
                    <div class="info-label">Archive Notes</div>
                    <div class="info-value"><?= htmlspecialchars($archive['archive_notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php elseif ($thesis['status'] == 'archived' && !$archive): ?>
            <div class="archive-info">
                <h4><i class="fas fa-archive"></i> Archive Information</h4>
                <div>
                    <div class="info-label">Archived Date</div>
                    <div class="info-value"><?= isset($thesis['archived_date']) ? date('F d, Y', strtotime($thesis['archived_date'])) : 'N/A' ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');
        const darkModeToggle = document.getElementById('darkmode');
        const notificationIcon = document.getElementById('notificationIcon');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllReadBtn = document.getElementById('markAllReadBtn');

        function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
        function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
        function toggleSidebar(e) { e.stopPropagation(); if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); }
        
        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
        
        document.addEventListener('keydown', function(e) { if (e.key === 'Escape') {
            if (sidebar.classList.contains('open')) closeSidebar();
            if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
            if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
        }});
        
        window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
        
        function toggleProfileDropdown(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show'); }
        function closeProfileDropdown(e) { if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); }
        if (profileWrapper) { profileWrapper.addEventListener('click', toggleProfileDropdown); document.addEventListener('click', closeProfileDropdown); }
        
        function toggleNotificationDropdown(e) { e.stopPropagation(); notificationDropdown.classList.toggle('show'); if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); }
        function closeNotificationDropdown(e) { if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) notificationDropdown.classList.remove('show'); }
        if (notificationIcon) { notificationIcon.addEventListener('click', toggleNotificationDropdown); document.addEventListener('click', closeNotificationDropdown); }
        
        function markNotificationAsRead(notifId, element) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_read=1&notif_id=' + notifId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    const badge = document.getElementById('notificationBadge');
                    if (badge) {
                        let c = parseInt(badge.textContent);
                        if (c > 0) { c--; if (c === 0) badge.style.display = 'none'; else badge.textContent = c; }
                    }
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function markAllAsRead() {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
                    const badge = document.getElementById('notificationBadge');
                    if (badge) badge.style.display = 'none';
                    if (markAllReadBtn) markAllReadBtn.style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
        }
        
        function initNotifications() {
            document.querySelectorAll('.notification-item').forEach(item => {
                if (!item.classList.contains('empty')) {
                    item.addEventListener('click', function(e) {
                        if (e.target.closest('.notification-footer')) return;
                        const id = this.dataset.id;
                        if (id && this.classList.contains('unread')) markNotificationAsRead(id, this);
                    });
                }
            });
            if (markAllReadBtn) markAllReadBtn.addEventListener('click', function(e) { e.stopPropagation(); markAllAsRead(); });
        }
        
        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); }
                    else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); }
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            initDarkMode();
            initNotifications();
        });
    </script>
</body>
</html>