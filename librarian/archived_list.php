<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'librarian') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// GET USER DATA
$user_query = "SELECT first_name, last_name, email FROM user_table WHERE user_id = ?";
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

// GET NOTIFICATION COUNT - FIXED: 'status' to 'is_read'
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

// GET RECENT NOTIFICATIONS - FIXED: 'status' to 'is_read'
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

// MARK NOTIFICATION AS READ - FIXED: 'status' to 'is_read'
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $update->bind_param("ii", $notif_id, $user_id);
    $update->execute();
    $update->close();
    echo json_encode(['success' => true]);
    exit;
}

// MARK ALL AS READ - FIXED: 'status' to 'is_read'
if (isset($_POST['mark_all_read'])) {
    $update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();
    $update->close();
    echo json_encode(['success' => true]);
    exit;
}

// GET ARCHIVED THESES
$archived_theses = [];
$archived_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                   FROM thesis_table t
                   JOIN user_table u ON t.student_id = u.user_id
                   WHERE t.status = 'archived'
                   ORDER BY t.archived_date DESC";
$archived_result = $conn->query($archived_query);
if ($archived_result && $archived_result->num_rows > 0) {
    while ($row = $archived_result->fetch_assoc()) {
        $archived_theses[] = $row;
    }
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
        
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%); display: flex; flex-direction: column; z-index: 100; transform: translateX(-100%); transition: transform 0.3s ease; box-shadow: 2px 0 10px rgba(0,0,0,0.05); }
        .sidebar.open { transform: translateX(0); }
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
        
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 99; display: none; }
        .sidebar-overlay.show { display: block; }
        
        .main-content { margin-left: 0; margin-top: 70px; padding: 32px; transition: margin-left 0.3s ease; }
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 1.8rem; font-weight: 700; color: #991b1b; display: flex; align-items: center; gap: 12px; }
        .page-header p { color: #6b7280; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px; display: flex; align-items: center; gap: 18px; border: 1px solid #ffcdd2; transition: all 0.3s; }
        .stat-icon { width: 55px; height: 55px; background: #fef2f2; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #dc2626; }
        .stat-details h3 { font-size: 1.8rem; font-weight: 700; color: #991b1b; margin-bottom: 5px; }
        .stat-details p { font-size: 0.8rem; color: #6b7280; }
        
        .theses-section { background: white; border-radius: 24px; padding: 24px; border: 1px solid #ffcdd2; }
        .table-responsive { overflow-x: auto; }
        .theses-table { width: 100%; border-collapse: collapse; }
        .theses-table th { text-align: left; padding: 12px; color: #6b7280; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #ffcdd2; }
        .theses-table td { padding: 12px; border-bottom: 1px solid #fef2f2; font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 30px; font-size: 0.7rem; font-weight: 500; }
        .status-badge.archived { background: #d1ecf1; color: #0c5460; }
        .btn-view { background: #dc2626; color: white; padding: 6px 16px; border-radius: 20px; text-decoration: none; font-size: 0.75rem; font-weight: 500; transition: all 0.2s; }
        .btn-view:hover { background: #991b1b; transform: translateY(-2px); }
        .empty-state { text-align: center; padding: 40px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; color: #dc2626; }
        
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .search-area, .profile-name { display: none; }
            .notification-dropdown { width: 320px; right: -10px; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav, body.dark-mode .stat-card, body.dark-mode .theses-section, body.dark-mode .notification-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .stat-details h3, body.dark-mode .page-header h1 { color: #fecaca; }
        body.dark-mode .theses-table td { color: #e5e7eb; border-bottom-color: #3d3d3d; }
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
            <a href="librarian_archive.php" class="nav-item"><i class="fas fa-archive"></i><span>Archive Theses</span></a>
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
            <p>List of all archived theses in the system</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format(count($archived_theses)) ?></h3><p>Total Archived</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar"></i></div><div class="stat-details"><h3><?= number_format(count($archived_theses)) ?></h3><p>Archived Theses</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-book"></i></div><div class="stat-details"><h3><?= number_format(count($archived_theses)) ?></h3><p>Total Records</p></div></div>
        </div>

        <div class="theses-section">
            <h3 style="margin-bottom: 20px; color: #991b1b;"><i class="fas fa-folder-open"></i> Archived Theses List</h3>
            <?php if (empty($archived_theses)): ?>
                <div class="empty-state"><i class="fas fa-archive"></i><p>No archived theses yet.</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr><th>ID</th><th>Thesis Title</th><th>Author</th><th>Student</th><th>Department</th><th>Archived Date</th><th>Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($archived_theses as $thesis): ?>
                            <tr>
                                <td><?= $counter++ ?></td>
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['adviser'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></td>
                                <td><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></td>
                                <td><?= isset($thesis['archived_date']) ? date('M d, Y', strtotime($thesis['archived_date'])) : date('M d, Y', strtotime($thesis['date_submitted'])) ?></td>
                                <td><span class="status-badge archived">Archived</span></td>
                                <td><a href="view_thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
            console.log("Archived List Page Loaded - Total Archived: <?= count($archived_theses) ?>");
        });
    </script>
</body>
</html>