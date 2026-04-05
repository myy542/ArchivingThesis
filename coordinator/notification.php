<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get all notifications
$notifications = [];
$notif_query = "SELECT n.*, t.title as thesis_title 
                FROM notifications n
                LEFT JOIN theses t ON n.thesis_id = t.thesis_id
                WHERE n.user_id = ? 
                ORDER BY n.created_at DESC";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notif_stmt->close();

// Mark notification as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $notif_id = intval($_GET['id']);
    $update_query = "UPDATE notifications SET status = 'read' WHERE id = ? AND user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $notif_id, $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    header("Location: notifications.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $update_query = "UPDATE notifications SET status = 'read' WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
    header("Location: notifications.php");
    exit;
}

$unread_count = 0;
$count_query = "SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND status = 'unread'";
$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = $count_stmt->get_result()->fetch_assoc()['c'];
$count_stmt->close();

$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Notifications | Coordinator Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #fef2f2;
            color: #1f2937;
        }
        .container { max-width: 900px; margin: 50px auto; padding: 20px; }
        .card { background: white; border-radius: 20px; padding: 30px; border: 1px solid #fee2e2; }
        h1 { font-size: 1.8rem; color: #991b1b; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .notification-item { 
            padding: 15px; 
            border-bottom: 1px solid #fee2e2; 
            display: flex; 
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .notification-item:hover { background: #fef2f2; }
        .notification-item.unread { background: #fff5f5; border-left: 3px solid #dc2626; }
        .notification-content { flex: 1; }
        .notification-message { font-size: 0.9rem; color: #1f2937; margin-bottom: 5px; }
        .notification-date { font-size: 0.7rem; color: #9ca3af; }
        .notification-actions { display: flex; gap: 10px; }
        .btn-mark { background: #dc2626; color: white; border: none; padding: 5px 12px; border-radius: 20px; cursor: pointer; font-size: 0.7rem; text-decoration: none; display: inline-block; }
        .btn-view { background: #fef2f2; color: #dc2626; text-decoration: none; padding: 5px 12px; border-radius: 20px; font-size: 0.7rem; display: inline-block; }
        .btn-mark-all { background: #991b1b; color: white; border: none; padding: 10px 20px; border-radius: 30px; cursor: pointer; margin-bottom: 20px; display: inline-block; text-decoration: none; }
        .empty-state { text-align: center; padding: 60px; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: #dc2626; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #dc2626; text-decoration: none; }
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100%;
            background: linear-gradient(180deg, #991b1b 0%, #dc2626 100%);
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
        }
        .sidebar.open { left: 0; }
        .logo-container { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .logo-container .logo { color: white; font-size: 1.3rem; }
        .logo-container .logo span { color: #fecaca; }
        .logo-sub { font-size: 0.7rem; color: #fecaca; margin-top: 5px; }
        .nav-menu { flex: 1; padding: 24px 16px; display: flex; flex-direction: column; gap: 6px; }
        .nav-item { display: flex; align-items: center; gap: 14px; padding: 12px 16px; border-radius: 12px; text-decoration: none; color: #fecaca; transition: all 0.2s; font-weight: 500; }
        .nav-item i { width: 22px; }
        .nav-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .nav-item.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.2); }
        .theme-toggle { margin-bottom: 15px; }
        .theme-toggle input { display: none; }
        .toggle-label { display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .toggle-label i { font-size: 1rem; color: #fecaca; }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; text-decoration: none; color: #fecaca; border-radius: 10px; }
        .logout-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        .sidebar-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); z-index: 999; display: none; }
        .sidebar-overlay.show { display: block; }
        .main-content { margin-left: 0; margin-top: 0; padding: 20px; }
        .hamburger { display: flex; flex-direction: column; gap: 5px; width: 40px; height: 40px; background: #fef2f2; border: none; border-radius: 8px; cursor: pointer; align-items: center; justify-content: center; position: fixed; top: 20px; left: 20px; z-index: 1001; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #dc2626; border-radius: 2px; }
        @media (max-width: 768px) {
            .container { margin: 80px auto 20px; }
        }
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode h1 { color: #fecaca; }
        body.dark-mode .notification-item { border-bottom-color: #3d3d3d; }
        body.dark-mode .notification-message { color: #e5e7eb; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <button class="hamburger" id="hamburgerBtn">
        <span></span><span></span><span></span>
    </button>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container">
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="logo-sub">RESEARCH COORDINATOR</div>
        </div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="notifications.php" class="nav-item active"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode">
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i><i class="fas fa-moon"></i>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="container">
            <a href="coordinatorDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            
            <div class="card">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                
                <?php if ($unread_count > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn-mark-all">Mark All as Read</a>
                <?php endif; ?>
                
                <?php if (empty($notifications)): ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?= $notif['status'] == 'unread' ? 'unread' : '' ?>">
                            <div class="notification-content">
                                <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                <div class="notification-date">
                                    <i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?>
                                </div>
                            </div>
                            <div class="notification-actions">
                                <?php if ($notif['status'] == 'unread'): ?>
                                    <a href="notifications.php?mark_read=1&id=<?= $notif['id'] ?>" class="btn-mark">Mark Read</a>
                                <?php endif; ?>
                                <?php if ($notif['thesis_id']): ?>
                                    <a href="reviewThesis.php?id=<?= $notif['thesis_id'] ?>" class="btn-view">View Thesis</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const darkModeToggle = document.getElementById('darkmode');

        function openSidebar() {
            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        function toggleSidebar(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
        });

        function initDarkMode() {
            const isDark = localStorage.getItem('darkMode') === 'true';
            if (isDark) {
                document.body.classList.add('dark-mode');
                if (darkModeToggle) darkModeToggle.checked = true;
            }
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        document.body.classList.add('dark-mode');
                        localStorage.setItem('darkMode', 'true');
                    } else {
                        document.body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                    }
                });
            }
        }

        initDarkMode();
    </script>
</body>
</html>