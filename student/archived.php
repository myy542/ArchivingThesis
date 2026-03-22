<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");
include("includes/archived_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$archive = new ArchiveManager($conn);
$user_id = (int)$_SESSION["user_id"];

// Handle restore request
if(isset($_POST['restore_thesis'])) {
    $restore_thesis_id = $_POST['thesis_id'];
    
    if($archive->restoreThesis($restore_thesis_id, $_SESSION['user_id'])) {
        $_SESSION['success'] = "Thesis restored successfully!";
        header("Location: archived.php");
        exit();
    } else {
        $_SESSION['error'] = implode("<br>", $archive->getErrors());
        header("Location: archived.php");
        exit();
    }
}

// Get user data
$userData = getUserData($conn, $user_id);
$fullName = $userData['fullName'];
$initials = $userData['initials'];

// Get notifications
$notificationData = getNotifications($conn, $user_id);
$unreadCount = $notificationData['unreadCount'];
$recentNotifications = $notificationData['notifications'];

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
  <link rel="stylesheet" href="css/archived.css">
</head>
<body>

<!-- OVERLAY -->
<div class="overlay" id="overlay"></div>

<!-- MOBILE MENU BUTTON -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- SIDEBAR -->
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
    <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</aside>

<div class="layout">
  <main class="main-content">

    <!-- TOPBAR -->
    <header class="topbar">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="hamburger-menu" id="hamburgerBtn">
          <i class="fas fa-bars"></i>
        </div>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
      </div>

      <div class="user-info">
        <!-- NOTIFICATIONS -->
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
              <a href="#" id="markAllRead">Mark all as read</a>
            </div>
            <div class="notification-list">
              <?php if (empty($recentNotifications)): ?>
                <div class="notification-item">
                  <div class="no-notifications">No new notifications</div>
                </div>
              <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                  <div class="notification-item <?= ($notif['status'] != 'read') ? 'unread' : '' ?>">
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

        <!-- AVATAR -->
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

    <!-- ALERTS -->
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

    <!-- ARCHIVED THESES LIST -->
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
                <span><b>Adviser:</b> <?= htmlspecialchars($a["adviser"]) ?></span>
              <?php endif; ?>

              <?php if (!empty($a["date_submitted"])): ?>
                <span class="date">
                  <b>Date:</b> <?= date("F d, Y", strtotime($a["date_submitted"])) ?>
                </span>
              <?php endif; ?>
            </div>

            <div class="archive-actions">
              <?php if (!empty($a["file_path"])): ?>
                <a href="<?= htmlspecialchars($a["file_path"]) ?>" class="btn primary" target="_blank">
                  <i class="fas fa-file-pdf"></i> View PDF
                </a>
              <?php endif; ?>

              <?php if (!empty($a["abstract"])): ?>
                <button class="btn secondary" type="button"
                        onclick="alert(<?= json_encode($a['abstract']) ?>)">
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

<!-- SCRIPTS -->
<script src="js/archived.js"></script>
</body>
</html>