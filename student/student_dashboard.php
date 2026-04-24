<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id'])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// GET USER DATA
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data) {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// CHECK IF ROLE IS STUDENT (role_id = 2)
if ($user_data['role_id'] != 2) {
    if ($user_data['role_id'] == 3) {
        header("Location: /ArchivingThesis/faculty/facultyDashboard.php");
    } else {
        header("Location: /ArchivingThesis/authentication/login.php");
    }
    exit;
}

$first_name = $user_data['first_name'] ?? '';
$last_name = $user_data['last_name'] ?? '';
$fullName = trim($first_name . " " . $last_name);
$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) : 
            (!empty($first_name) ? strtoupper(substr($first_name, 0, 1)) : "U");

// GET STUDENT ID (user_id is the student_id)
$student_id = $user_id;

// ==================== GET THESIS COUNTS (NO 'status' column - use is_archived only) ====================
$thesis_table_exists = false;
$check_thesis = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis && $check_thesis->num_rows > 0) {
    $thesis_table_exists = true;
}

// Initialize counts
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$archivedCount = 0;
$totalCount = 0;

if ($thesis_table_exists) {
    // Pending count (is_archived = 0)
    $pending_query = "SELECT COUNT(*) as count FROM thesis_table WHERE student_id = ? AND (is_archived = 0 OR is_archived IS NULL)";
    $pending_stmt = $conn->prepare($pending_query);
    $pending_stmt->bind_param("i", $student_id);
    $pending_stmt->execute();
    $pending_result = $pending_stmt->get_result();
    $pendingCount = $pending_result->fetch_assoc()['count'] ?? 0;
    $pending_stmt->close();
    
    // Approved count - since wala approved, set to 0 (or adjust based on your logic)
    $approvedCount = 0;
    
    // Rejected count - since wala rejected, set to 0
    $rejectedCount = 0;
    
    // Archived count (is_archived = 1)
    $archived_query = "SELECT COUNT(*) as count FROM thesis_table WHERE student_id = ? AND is_archived = 1";
    $archived_stmt = $conn->prepare($archived_query);
    $archived_stmt->bind_param("i", $student_id);
    $archived_stmt->execute();
    $archived_result = $archived_stmt->get_result();
    $archivedCount = $archived_result->fetch_assoc()['count'] ?? 0;
    $archived_stmt->close();
    
    // Total count (all theses)
    $total_query = "SELECT COUNT(*) as count FROM thesis_table WHERE student_id = ?";
    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param("i", $student_id);
    $total_stmt->execute();
    $total_result = $total_stmt->get_result();
    $totalCount = $total_result->fetch_assoc()['count'] ?? 0;
    $total_stmt->close();
}

// Member since
$member_since = date('F Y');
$check_created = $conn->query("SHOW COLUMNS FROM user_table LIKE 'created_at'");
if ($check_created && $check_created->num_rows > 0) {
    $created_query = "SELECT created_at FROM user_table WHERE user_id = ?";
    $created_stmt = $conn->prepare($created_query);
    $created_stmt->bind_param("i", $user_id);
    $created_stmt->execute();
    $created_result = $created_stmt->get_result();
    if ($created_row = $created_result->fetch_assoc()) {
        $member_since = date('F Y', strtotime($created_row['created_at']));
    }
    $created_stmt->close();
}

// ==================== NOTIFICATION SYSTEM ====================
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

// GET NOTIFICATION COUNT
$unreadCount = 0;
$notif_query = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $unreadCount = $notif_row['cnt'];
}
$notif_stmt->close();

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    if ($row['thesis_id'] && $thesis_table_exists) {
        $thesis_q = $conn->prepare("SELECT title FROM thesis_table WHERE thesis_id = ?");
        $thesis_q->bind_param("i", $row['thesis_id']);
        $thesis_q->execute();
        $thesis_title = $thesis_q->get_result()->fetch_assoc();
        $row['thesis_title'] = $thesis_title['title'] ?? '';
        $thesis_q->close();
    }
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

// ==================== GET RECENT FEEDBACK ====================
$recentFeedback = [];
$check_feedback = $conn->query("SHOW TABLES LIKE 'feedback'");
if ($check_feedback && $check_feedback->num_rows > 0) {
    $feedback_query = "SELECT f.*, t.title as thesis_title, u.first_name as faculty_first, u.last_name as faculty_last 
                       FROM feedback f
                       JOIN thesis_table t ON f.thesis_id = t.thesis_id
                       JOIN user_table u ON f.faculty_id = u.user_id
                       WHERE t.student_id = ?
                       ORDER BY f.created_at DESC LIMIT 5";
    $feedback_stmt = $conn->prepare($feedback_query);
    $feedback_stmt->bind_param("i", $student_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    while ($row = $feedback_result->fetch_assoc()) {
        $recentFeedback[] = $row;
    }
    $feedback_stmt->close();
}

// Chart data for JavaScript
$chart_data = [
    'pending' => $pendingCount,
    'approved' => $approvedCount,
    'rejected' => $rejectedCount,
    'archived' => $archivedCount
];

$pageTitle = "Student Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="css/student_dashboard.css">
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>ThesisManager</h2>
        <p>STUDENT</p>
    </div>
    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="projects.php" class="nav-link"><i class="fas fa-folder-open"></i> My Projects</a>
        <a href="submission.php" class="nav-link"><i class="fas fa-upload"></i> Submit Thesis</a>
        <a href="archived.php" class="nav-link"><i class="fas fa-archive"></i> Archived Theses</a>
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
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
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
                            <a href="#" id="markAllRead">Mark all as read</a>
                        </div>
                        <div class="notification-list">
                            <?php if (empty($recentNotifications)): ?>
                                <div class="notification-item"><div class="no-notifications">No notifications</div></div>
                            <?php else: ?>
                                <?php foreach ($recentNotifications as $notif): ?>
                                    <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-notification-id="<?= $notif['notification_id'] ?>" data-thesis-id="<?= $notif['thesis_id'] ?? 0 ?>">
                                        <div class="notif-message">
                                            <?php if(strpos($notif['message'], 'feedback') !== false): ?>
                                                <i class="fas fa-comment"></i>
                                            <?php elseif(strpos($notif['message'], 'approved') !== false): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php elseif(strpos($notif['message'], 'rejected') !== false): ?>
                                                <i class="fas fa-times-circle"></i>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($notif['message'] ?? '') ?>
                                        </div>
                                        <?php if (!empty($notif['thesis_title'])): ?>
                                            <div class="notif-thesis"><i class="fas fa-book"></i> <?= htmlspecialchars($notif['thesis_title']) ?></div>
                                        <?php endif; ?>
                                        <div class="notif-time"><?= date('M d, h:i A', strtotime($notif['created_at'])) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="notification-footer"><a href="notifications.php">View all notifications</a></div>
                    </div>
                </div>
                <div class="avatar-dropdown">
                    <div class="avatar" id="avatarBtn"><?= htmlspecialchars($initials) ?></div>
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
            <h2>Welcome, <?= htmlspecialchars($first_name) ?>!</h2>
            <p>Here's an overview of your thesis submissions. Member since <?= $member_since ?></p>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card pending"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Active Theses</div></div>
            <div class="stat-card approved"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?= $approvedCount ?></div><div class="stat-label">Approved</div></div>
            <div class="stat-card rejected"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-value"><?= $rejectedCount ?></div><div class="stat-label">Rejected</div></div>
            <div class="stat-card archived"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-value"><?= $archivedCount ?></div><div class="stat-label">Archived</div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-layer-group"></i></div><div class="stat-value"><?= $totalCount ?></div><div class="stat-label">Total Submissions</div></div>
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
                <table class="feedback-table">
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
                            <td><?= date('M d, Y', strtotime($fb['created_at'])) ?></td>
                            <td><a href="projects.php?thesis_id=<?= $fb['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
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
// Pass PHP data to JavaScript
const chartData = {
    pending: <?= $pendingCount ?>,
    approved: <?= $approvedCount ?>,
    rejected: <?= $rejectedCount ?>,
    archived: <?= $archivedCount ?>
};
console.log('Chart Data:', chartData);
</script>
<script src="js/student_dashboard.js"></script>
</body>
</html>