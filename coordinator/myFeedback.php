<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get feedback from database
$feedbacks = [];

// Check if feedback table exists
$check_feedback_table = $conn->query("SHOW TABLES LIKE 'feedback'");
if ($check_feedback_table && $check_feedback_table->num_rows > 0) {
    $feedback_query = "SELECT f.*, t.title as thesis_title 
                       FROM feedback f 
                       LEFT JOIN thesis_table t ON f.thesis_id = t.thesis_id 
                       WHERE f.coordinator_id = ? 
                       ORDER BY f.created_at DESC";
    $feedback_stmt = $conn->prepare($feedback_query);
    $feedback_stmt->bind_param("i", $user_id);
    $feedback_stmt->execute();
    $feedback_result = $feedback_stmt->get_result();
    
    while ($row = $feedback_result->fetch_assoc()) {
        $feedbacks[] = [
            'id' => $row['id'],
            'thesis_id' => $row['thesis_id'],
            'thesis' => $row['thesis_title'] ?? 'Unknown Thesis',
            'message' => $row['message'],
            'date' => $row['created_at'],
            'read' => $row['is_read'] == 1
        ];
    }
    $feedback_stmt->close();
}

// Handle delete feedback
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    $delete_query = "DELETE FROM feedback WHERE id = ? AND coordinator_id = ?";
    $delete_stmt = $conn->prepare($delete_query);
    $delete_stmt->bind_param("ii", $delete_id, $user_id);
    $delete_stmt->execute();
    $delete_stmt->close();
    
    header("Location: myFeedback.php?deleted=1");
    exit;
}

// Handle mark as read
if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    $read_id = intval($_GET['id']);
    $read_query = "UPDATE feedback SET is_read = 1 WHERE id = ? AND coordinator_id = ?";
    $read_stmt = $conn->prepare($read_query);
    $read_stmt->bind_param("ii", $read_id, $user_id);
    $read_stmt->execute();
    $read_stmt->close();
    
    header("Location: myFeedback.php");
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>My Feedback | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/myFeedback.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search feedback..."></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><a href="editProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">RESEARCH COORDINATOR</div></div>
        <div class="nav-menu">
            <a href="coordinatorDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="myFeedback.php" class="nav-item active"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header"><h2>My Feedback</h2></div>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><span>Feedback deleted successfully!</span></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-comment-dots"></i></div><div class="stat-content"><h3><?= count($feedbacks) ?></h3><p>Total Feedback</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-envelope"></i></div><div class="stat-content"><h3><?= count(array_filter($feedbacks, function($f) { return !$f['read']; })) ?></h3><p>Unread</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-content"><h3><?= count(array_filter($feedbacks, function($f) { return $f['read']; })) ?></h3><p>Read</p></div></div>
        </div>

        <div class="feedback-list" id="feedbackList">
            <?php if (empty($feedbacks)): ?>
                <div class="empty-state"><i class="fas fa-comment-slash"></i><p>No feedback sent yet. When you request revisions for a thesis, feedback will appear here.</p></div>
            <?php else: ?>
                <?php foreach ($feedbacks as $feedback): ?>
                    <div class="feedback-item <?= $feedback['read'] ? 'read' : 'unread' ?>">
                        <div class="feedback-header">
                            <div class="thesis-title"><i class="fas fa-book"></i><h3><?= htmlspecialchars($feedback['thesis']) ?></h3></div>
                            <div class="date"><i class="far fa-calendar-alt"></i><span><?= date('F d, Y', strtotime($feedback['date'])) ?></span></div>
                        </div>
                        <div class="feedback-message"><i class="fas fa-quote-left"></i><p><?= nl2br(htmlspecialchars($feedback['message'])) ?></p></div>
                        <div class="feedback-actions">
                            <?php if (!$feedback['read']): ?>
                                <a href="myFeedback.php?mark_read=1&id=<?= $feedback['id'] ?>" class="action-btn"><i class="fas fa-check-circle"></i> Mark as Read</a>
                            <?php endif; ?>
                            <a href="myFeedback.php?delete=1&id=<?= $feedback['id'] ?>" class="action-btn" onclick="return confirm('Are you sure you want to delete this feedback?')"><i class="fas fa-trash-alt"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/myFeedback.js"></script>
</body>
</html>