<?php
session_start();
include("../config/db.php");
include("includes/student_functions.php"); // Atong functions

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Authentication and data fetching
if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];
$userData = getUserData($conn, $user_id);

if (!$userData) {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// Check role
if ($userData['role_id'] != 2) {
    if ($userData['role_id'] == 3) {
        header("Location: /ArchivingThesis/faculty/facultyDashboard.php");
    } else {
        header("Location: /ArchivingThesis/authentication/login.php");
    }
    exit;
}

// Get user details
$user = getUserDetails($conn, $user_id);
if (!$user) {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$displayName = trim($first . " " . $last);
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "U";

// Get student ID
$student_id = getStudentId($conn, $user_id);

// Get all counts
$pendingCount = getThesisCount($conn, $student_id, 'pending');
$approvedCount = getThesisCount($conn, $student_id, 'approved');
$rejectedCount = getRejectedCount($conn, $student_id);
$archivedCount = getArchivedCount($conn, $student_id);
$totalCount = getTotalCount($conn, $student_id);

// Get notifications and feedback
$notifications = getNotifications($conn, $user_id);
$unreadCount = $notifications['unread_count'];
$recentNotifications = $notifications['notifications'];

$recentFeedback = getRecentFeedback($conn, $student_id);
$feedbackNotificationCount = countFeedbackNotifications($recentNotifications);

$pageTitle = "Student Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- External CSS -->
  <link rel="stylesheet" href="css/student_dashboard.css">
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
    <h2>ThesisManager</h2>
    <p>STUDENT</p>
  </div>

  <nav class="sidebar-nav">
    <a href="student_dashboard.php" class="nav-link active">
      <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="projects.php" class="nav-link">
      <i class="fas fa-folder-open"></i> My Projects
    </a>
    <a href="submission.php" class="nav-link">
      <i class="fas fa-upload"></i> Submit Thesis
    </a>
    <a href="archived.php" class="nav-link">
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

    <header class="topbar">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <div class="hamburger-menu" id="hamburgerBtn">
          <i class="fas fa-bars"></i>
        </div>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
      </div>

      <div class="user-info">
        <!-- Notification Container with Dropdown -->
        <div class="notification-container">
          <div class="notification-bell" id="notificationBell">
            <i class="fas fa-bell"></i>
            <?php if ($unreadCount > 0): ?>
              <span class="notification-badge"><?= $unreadCount ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Notification Dropdown -->
          <div class="notification-dropdown" id="notificationDropdown">
            <div class="notification-header">
              <h4>Notifications</h4>
              <a href="#" id="markAllRead">Mark all as read</a>
            </div>
            <div class="notification-list">
              <?php if (empty($recentNotifications)): ?>
                <div class="notification-item">
                  <div class="no-notifications">No notifications</div>
                </div>
              <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                  <div class="notification-item <?= $notif['status'] == 'unread' ? 'unread' : '' ?>"
                       data-notification-id="<?= $notif['id'] ?? '' ?>"
                       data-thesis-id="<?= $notif['thesis_id'] ?? 0 ?>"
                       onclick="markAsRead(this)">
                    <div class="notif-message">
                      <?php if (strpos($notif['message'], 'feedback') !== false): ?>
                        <i class="fas fa-comment"></i>
                      <?php elseif (strpos($notif['message'], 'approved') !== false): ?>
                        <i class="fas fa-check-circle" style="color: #81c784;"></i>
                      <?php elseif (strpos($notif['message'], 'rejected') !== false): ?>
                        <i class="fas fa-times-circle" style="color: #b71c1c;"></i>
                      <?php endif; ?>
                      <?= htmlspecialchars($notif['message'] ?? '') ?>
                    </div>
                    <?php if (!empty($notif['thesis_title'])): ?>
                      <div class="notif-thesis">
                        <i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?>
                      </div>
                    <?php endif; ?>
                    <div class="notif-time"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
            <div class="notification-footer">
              <a href="notification.php">View all notifications</a>
            </div>
          </div>
        </div>

        <!-- Avatar Dropdown -->
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

    <div class="welcome-section">
      <h2>Welcome, <?= htmlspecialchars($first) ?>!</h2>
      <p>Here's an overview of your thesis submissions.</p>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
      <div class="stat-card pending">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-value"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Review</div>
      </div>

      <div class="stat-card approved">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $approvedCount ?></div>
        <div class="stat-label">Approved</div>
      </div>

      <div class="stat-card rejected">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-value"><?= $rejectedCount ?></div>
        <div class="stat-label">Rejected</div>
      </div>

      <div class="stat-card archived">
        <div class="stat-icon"><i class="fas fa-archive"></i></div>
        <div class="stat-value"><?= $archivedCount ?></div>
        <div class="stat-label">Archived</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
        <div class="stat-value"><?= $totalCount ?></div>
        <div class="stat-label">Total Submissions</div>
      </div>
    </div>

    <!-- CHARTS SECTION -->
    <div class="charts-section">
      <div class="chart-card">
        <div class="chart-header">
          <h3>Project Status Distribution</h3>
          <select id="chartPeriod">
            <option>All Time</option>
            <option>This Semester</option>
            <option>This Year</option>
          </select>
        </div>
        <div class="chart-container">
          <canvas id="projectStatusChart"></canvas>
        </div>
      </div>
      <div class="chart-card">
        <div class="chart-header">
          <h3>Submission Timeline</h3>
          <select id="timelinePeriod">
            <option>Last 6 Months</option>
            <option>Last Year</option>
          </select>
        </div>
        <div class="chart-container">
          <canvas id="timelineChart"></canvas>
        </div>
      </div>
    </div>
    
    <!-- Recent Feedback -->
    <?php if (!empty($recentFeedback)): ?>
    <div class="recent-feedback">
      <h3><i class="fas fa-comments"></i> Recent Feedback from Research Adviser</h3>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>PROJECT TITLE</th>
              <th>FROM</th>
              <th>FEEDBACK</th>
              <th>DATE</th>
              <th>ACTION</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentFeedback as $fb): ?>
            <tr>
              <td><?= htmlspecialchars($fb['thesis_title']) ?></td>
              <td><?= htmlspecialchars($fb['faculty_first'] . ' ' . $fb['faculty_last']) ?></td>
              <td class="feedback-preview"><?= htmlspecialchars(substr($fb['feedback_text'], 0, 100)) ?><?= strlen($fb['feedback_text']) > 100 ? '...' : '' ?></td>
              <td><?= date('M d, Y', strtotime($fb['feedback_date'])) ?></td>
              <td>
                <a href="projects.php?thesis_id=<?= $fb['thesis_id'] ?>" class="btn-view">
                  <i class="fas fa-eye"></i> View
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <a href="feedback_history.php" class="view-all-link">View all feedback <i class="fas fa-arrow-right"></i></a>
    </div>
    <?php endif; ?>

  </main>
</div>

<script>
// Pass PHP data to JavaScript - I-DOUBLE CHECK NI BAI!
const chartData = {
    pending: <?= $pendingCount ?? 0 ?>,
    approved: <?= $approvedCount ?? 0 ?>,
    rejected: <?= $rejectedCount ?? 0 ?>,
    archived: <?= $archivedCount ?? 0 ?>
};
console.log('Chart Data:', chartData); // I-check sa console kung naay data
</script>
<script src="js/student_dashboard.js"></script>
</body>
</html>