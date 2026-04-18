<?php
session_start();
include("../config/db.php");
include("includes/archived_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Handle restore request (if you have this function)
if(isset($_POST['restore_thesis'])) {
    $restore_thesis_id = $_POST['thesis_id'];
    
    $update = $conn->prepare("UPDATE thesis_table SET status = 'pending' WHERE thesis_id = ? AND student_id = (SELECT student_id FROM student_table WHERE user_id = ?)");
    $update->bind_param("ii", $restore_thesis_id, $user_id);
    
    if($update->execute()) {
        $_SESSION['success'] = "Thesis restored successfully!";
    } else {
        $_SESSION['error'] = "Failed to restore thesis.";
    }
    $update->close();
    
    header("Location: archived.php");
    exit();
}

// Get user data
$userData = getUserData($conn, $user_id);
$fullName = $userData['fullName'];
$initials = $userData['initials'];

// Get notifications
$notificationData = getNotifications($conn, $user_id);
$unreadCount = $notificationData['unreadCount'];
$recentNotifications = $notificationData['notifications'];

// Mark notification as read via AJAX
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    $notif_id = intval($_POST['notif_id']);
    markNotificationAsRead($conn, $notif_id, $user_id);
    echo json_encode(['success' => true]);
    exit;
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    markAllNotificationsAsRead($conn, $user_id);
    echo json_encode(['success' => true]);
    exit;
}

// Get archived theses
$archived = getArchivedTheses($conn, $user_id);

$pageTitle = "Archived Theses";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        background: #f5f5f5;
        color: #000000;
        line-height: 1.6;
    }

    body.dark-mode {
        background: #2d2d2d;
        color: #e0e0e0;
    }

    /* Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: -300px;
        width: 280px;
        height: 100vh;
        background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
        color: white;
        display: flex;
        flex-direction: column;
        z-index: 1000;
        transition: left 0.3s ease;
        box-shadow: 5px 0 20px rgba(0,0,0,0.3);
    }

    .sidebar.show {
        left: 0;
    }

    .sidebar-header {
        padding: 2rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }

    .sidebar-header h2 {
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
        color: white;
    }

    .sidebar-header p {
        font-size: 0.875rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .sidebar-nav {
        flex: 1;
        padding: 1.5rem 0.5rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-radius: 8px;
        margin-bottom: 0.25rem;
        transition: all 0.2s;
        font-weight: 500;
    }

    .nav-link i {
        width: 20px;
        color: white;
    }

    .nav-link:hover, .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    .sidebar-footer {
        padding: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.2);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1rem;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        border-radius: 8px;
    }

    .logout-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .theme-toggle {
        margin-bottom: 1rem;
    }

    .theme-toggle input {
        display: none;
    }

    .toggle-label {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 30px;
        cursor: pointer;
        position: relative;
    }

    .toggle-label i {
        font-size: 1rem;
        color: white;
    }

    .overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }

    .overlay.show {
        display: block;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        margin-left: 0;
        min-height: 100vh;
        padding: 2rem;
    }

    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
    }

    body.dark-mode .topbar {
        background: #3a3a3a;
    }

    .topbar h1 {
        font-size: 1.5rem;
        color: #732529;
    }

    body.dark-mode .topbar h1 {
        color: #FE4853;
    }

    .hamburger-menu {
        font-size: 1.5rem;
        cursor: pointer;
        color: #FE4853;
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.3s ease;
    }

    .hamburger-menu:hover {
        background: rgba(254, 72, 83, 0.1);
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    /* Notifications */
    .notification-container {
        position: relative;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        width: 40px;
        height: 40px;
        background: #fef2f2;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .notification-bell:hover {
        background: #fee2e2;
    }

    .notification-bell i {
        font-size: 1.2rem;
        color: #dc2626;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 0.6rem;
        font-weight: 600;
        min-width: 18px;
        height: 18px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 5px;
    }

    .notification-dropdown {
        position: absolute;
        top: 55px;
        right: 0;
        width: 350px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        display: none;
        overflow: hidden;
        z-index: 100;
        border: 1px solid #fee2e2;
    }

    .notification-dropdown.show {
        display: block;
        animation: fadeSlideDown 0.2s ease;
    }

    @keyframes fadeSlideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .notification-header {
        padding: 12px 16px;
        border-bottom: 1px solid #fee2e2;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h4 {
        font-size: 0.9rem;
        font-weight: 600;
        color: #991b1b;
    }

    .notification-header a {
        font-size: 0.7rem;
        color: #dc2626;
        text-decoration: none;
    }

    .notification-list {
        max-height: 350px;
        overflow-y: auto;
    }

    .notification-item {
        padding: 12px 16px;
        border-bottom: 1px solid #fef2f2;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #fef2f2;
    }

    .notification-item.unread {
        background: #fff5f5;
        border-left: 3px solid #dc2626;
    }

    .notif-message {
        font-size: 0.8rem;
        color: #1f2937;
        margin-bottom: 4px;
    }

    .notif-time {
        font-size: 0.65rem;
        color: #9ca3af;
    }

    .no-notifications {
        text-align: center;
        padding: 20px;
        color: #9ca3af;
        font-size: 0.8rem;
    }

    .notification-footer {
        padding: 10px 16px;
        border-top: 1px solid #fee2e2;
        text-align: center;
    }

    .notification-footer a {
        font-size: 0.75rem;
        color: #dc2626;
        text-decoration: none;
    }

    /* Avatar Dropdown */
    .avatar-dropdown {
        position: relative;
        cursor: pointer;
    }

    .avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1rem;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        top: 55px;
        right: 0;
        background: white;
        min-width: 200px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 100;
    }

    body.dark-mode .dropdown-content {
        background: #3a3a3a;
    }

    .dropdown-content.show {
        display: block;
    }

    .dropdown-content a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        color: #333;
        text-decoration: none;
        transition: background 0.2s;
    }

    body.dark-mode .dropdown-content a {
        color: #e0e0e0;
    }

    .dropdown-content a:hover {
        background: #f5f5f5;
    }

    body.dark-mode .dropdown-content a:hover {
        background: #4a4a4a;
    }

    .dropdown-content hr {
        margin: 0;
        border: none;
        border-top: 1px solid #e0e0e0;
    }

    /* Mobile Menu Button */
    .mobile-menu-btn {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 1001;
        border: none;
        background: #FE4853;
        color: white;
        padding: 12px 15px;
        border-radius: 10px;
        cursor: pointer;
        display: none;
        font-size: 1.2rem;
    }

    /* Alerts */
    .alert-success, .alert-error {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
    }

    /* Archived Container */
    .archived-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .archive-empty {
        text-align: center;
        padding: 4rem;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
    }

    body.dark-mode .archive-empty {
        background: #3a3a3a;
    }

    .archive-empty i {
        font-size: 4rem;
        color: #FE4853;
        margin-bottom: 1rem;
    }

    .archive-empty p {
        color: #6E6E6E;
        font-size: 1rem;
    }

    .archive-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        transition: all 0.3s ease;
        border-left: 4px solid #10b981;
    }

    body.dark-mode .archive-card {
        background: #3a3a3a;
    }

    .archive-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
    }

    .archive-card h2 {
        font-size: 1.2rem;
        font-weight: 600;
        color: #732529;
        margin-bottom: 0.5rem;
    }

    body.dark-mode .archive-card h2 {
        color: #FE4853;
    }

    .archive-meta {
        display: flex;
        gap: 1.5rem;
        margin-bottom: 1rem;
        font-size: 0.8rem;
        color: #6E6E6E;
        flex-wrap: wrap;
    }

    body.dark-mode .archive-meta {
        color: #e0e0e0;
    }

    .archive-meta span {
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }

    .archive-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    body.dark-mode .archive-actions {
        border-top-color: #555;
    }

    .btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        cursor: pointer;
        border: none;
    }

    .btn.primary {
        background: #FE4853;
        color: white;
    }

    .btn.primary:hover {
        background: #732529;
        transform: translateY(-2px);
    }

    .btn.secondary {
        background: #f0f0f0;
        color: #333;
    }

    body.dark-mode .btn.secondary {
        background: #4a4a4a;
        color: #e0e0e0;
    }

    .btn.secondary:hover {
        background: #e0e0e0;
        transform: translateY(-2px);
    }

    .btn.restore {
        background: #10b981;
        color: white;
    }

    .btn.restore:hover {
        background: #059669;
        transform: translateY(-2px);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .mobile-menu-btn {
            display: block;
        }
        
        .main-content {
            padding: 1rem;
            margin-top: 60px;
        }
        
        .topbar {
            display: none;
        }
        
        .archive-meta {
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .archive-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
        
        .notification-dropdown {
            width: 320px;
            right: -20px;
        }
    }

    @media (max-width: 480px) {
        .archive-card {
            padding: 1rem;
        }
        
        .archive-card h2 {
            font-size: 1rem;
        }
        
        .notification-dropdown {
            width: 300px;
        }
    }
  </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <h2>Theses Archive</h2>
    <p>Student Portal</p>
  </div>

  <nav class="sidebar-nav">
    <a href="student_dashboard.php" class="nav-link">
      <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="projects.php" class="nav-link">
      <i class="fas fa-folder-open"></i> My Projects
    </a>
    <a href="submission.php" class="nav-link">
      <i class="fas fa-upload"></i> Submit Thesis
    </a>
    <a href="archived.php" class="nav-link active">
      <i class="fas fa-archive"></i> Archived Theses
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="theme-toggle">
      <input type="checkbox" id="darkmode" />
      <label for="darkmode" class="toggle-label">
        <i class="fas fa-sun"></i>
        <i class="fas fa-moon"></i>
        <span class="slider"></span>
      </label>
    </div>
    <a href="profile.php" class="nav-link" style="margin-bottom: 0.5rem;">
      <i class="fas fa-user-circle"></i> Profile
    </a>
    <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</aside>

<div class="layout">
  <main class="main-content">

    <header class="topbar">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="hamburger-menu" id="hamburgerBtn">
          <i class="fas fa-bars"></i>
        </div>
        <h1>Archived Theses</h1>
      </div>

      <div class="user-info">
        <div class="notification-container">
          <div class="notification-bell" id="notificationBell">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
              <span class="notification-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
          </div>
          
          <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
              <h4>Notifications</h4>
              <?php if ($unreadCount > 0): ?>
                <a href="#" id="markAllRead">Mark all as read</a>
              <?php endif; ?>
            </div>
            <div class="notification-list">
              <?php if (empty($recentNotifications)): ?>
                <div class="notification-item">
                  <div class="no-notifications">No new notifications</div>
                </div>
              <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                  <div class="notification-item <?= ($notif['status'] == 0) ? 'unread' : '' ?>" data-id="<?= $notif['notification_id'] ?>">
                    <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                    <div class="notif-time"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="notification-footer">
              <a href="notifications.php">View all notifications</a>
            </div>
          </div>
        </div>

        <div class="avatar-dropdown">
          <div class="avatar" id="avatarBtn">
            <?= htmlspecialchars($initials) ?>
          </div>
          <div class="dropdown-content" id="dropdownMenu">
            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <hr>
            <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </div>
        </div>
      </div>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
      </div>
    <?php endif; ?>

    <div class="archived-container">
      <?php if (count($archived) === 0): ?>
        <div class="archive-empty">
          <i class="fas fa-archive"></i>
          <p>No archived theses yet.</p>
        </div>
      <?php else: ?>
        <?php foreach ($archived as $a): ?>
          <div class="archive-card">
            <h2><?= htmlspecialchars($a["title"] ?? "Untitled") ?></h2>

            <div class="archive-meta">
              <?php if (!empty($a["adviser"])): ?>
                <span><i class="fas fa-user-tie"></i> <b>Adviser:</b> <?= htmlspecialchars($a["adviser"]) ?></span>
              <?php endif; ?>

              <?php if (!empty($a["date_submitted"])): ?>
                <span><i class="fas fa-calendar"></i> <b>Submitted:</b> <?= date("F d, Y", strtotime($a["date_submitted"])) ?></span>
              <?php endif; ?>

              <?php if (!empty($a["archived_date"])): ?>
                <span><i class="fas fa-archive"></i> <b>Archived:</b> <?= date("F d, Y", strtotime($a["archived_date"])) ?></span>
              <?php endif; ?>
            </div>

            <div class="archive-actions">
              <?php if (!empty($a["file_path"])): ?>
                <a href="../<?= htmlspecialchars($a["file_path"]) ?>" class="btn primary" target="_blank">
                  <i class="fas fa-file-pdf"></i> View PDF
                </a>
              <?php endif; ?>

              <?php if (!empty($a["abstract"])): ?>
                <button class="btn secondary" type="button" onclick="showAbstract('<?= htmlspecialchars(addslashes($a['abstract'])) ?>')">
                  <i class="fas fa-align-left"></i> View Abstract
                </button>
              <?php endif; ?>

              <form method="POST" style="display: inline;">
                <input type="hidden" name="thesis_id" value="<?= $a['thesis_id'] ?>">
                <button type="submit" name="restore_thesis" class="btn restore"
                        onclick="return confirm('Restore this thesis? It will be moved back to active projects.')">
                  <i class="fas fa-undo"></i> Restore
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    function toggleSidebar(e) {
        e.stopPropagation();
        if (sidebar.classList.contains('show')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
    if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // Dark mode toggle
    const darkToggle = document.getElementById('darkmode');
    if (darkToggle) {
        darkToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', darkToggle.checked);
        });
        if (localStorage.getItem('darkMode') === 'true') {
            darkToggle.checked = true;
            document.body.classList.add('dark-mode');
        }
    }

    // Profile dropdown
    const avatarBtn = document.getElementById('avatarBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (avatarBtn) {
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
    }

    document.addEventListener('click', (e) => {
        if (!avatarBtn?.contains(e.target) && dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }
    });

    // Notification dropdown
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');

    if (notificationBell) {
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });
    }

    document.addEventListener('click', (e) => {
        if (!notificationBell?.contains(e.target) && notificationDropdown) {
            notificationDropdown.classList.remove('show');
        }
    });

    // Mark notification as read
    function markAsRead(notifId, element) {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_read=1&notif_id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                element.classList.remove('unread');
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    let c = parseInt(badge.textContent);
                    if (c > 0) {
                        c--;
                        if (c === 0) {
                            badge.style.display = 'none';
                        } else {
                            badge.textContent = c;
                        }
                    }
                }
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Mark all as read
    function markAllAsRead() {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_all_read=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.style.display = 'none';
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Notification item click
    document.querySelectorAll('.notification-item').forEach(item => {
        if (!item.classList.contains('no-notifications')) {
            item.addEventListener('click', function(e) {
                const notifId = this.dataset.id;
                if (notifId && this.classList.contains('unread')) {
                    markAsRead(notifId, this);
                }
            });
        }
    });

    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            markAllAsRead();
        });
    }

    // Show abstract modal
    function showAbstract(abstract) {
        alert(abstract);
    }
</script>

</body>
</html>