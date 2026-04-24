<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get faculty department_id and department info
$faculty_query = "SELECT u.department_id, d.department_name, d.department_code 
                  FROM user_table u
                  LEFT JOIN department_table d ON u.department_id = d.department_id
                  WHERE u.user_id = ?";
$faculty_stmt = $conn->prepare($faculty_query);
$faculty_stmt->bind_param("i", $user_id);
$faculty_stmt->execute();
$faculty_result = $faculty_stmt->get_result();
$faculty_data = $faculty_result->fetch_assoc();
$faculty_department_id = $faculty_data['department_id'] ?? null;
$faculty_department_name = $faculty_data['department_name'] ?? 'N/A';
$faculty_department_code = $faculty_data['department_code'] ?? 'N/A';
$faculty_stmt->close();

// ==================== HANDLE FORWARD TO DEAN ====================
if (isset($_POST['forward_to_dean']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    $thesis_title = $_POST['thesis_title'] ?? '';
    
    // Get thesis department
    $thesis_dept_query = "SELECT department_id FROM thesis_table WHERE thesis_id = ?";
    $thesis_dept_stmt = $conn->prepare($thesis_dept_query);
    $thesis_dept_stmt->bind_param("i", $thesis_id);
    $thesis_dept_stmt->execute();
    $thesis_dept_result = $thesis_dept_stmt->get_result();
    $thesis_dept = $thesis_dept_result->fetch_assoc();
    $thesis_dept_stmt->close();
    
    if (!$thesis_dept || empty($thesis_dept['department_id'])) {
        echo json_encode(['success' => false, 'message' => 'Thesis has no department assigned']);
        exit;
    }
    
    // Only get dean with matching department_id
    $deanQuery = "SELECT user_id FROM user_table WHERE role_id = 4 AND department_id = ?";
    $deanStmt = $conn->prepare($deanQuery);
    $deanStmt->bind_param("i", $thesis_dept['department_id']);
    $deanStmt->execute();
    $deanResult = $deanStmt->get_result();
    $deanStmt->close();
    
    $updateQuery = "UPDATE thesis_table SET thesis_status = 'Forwarded_to_dean', forwarded_to_dean_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $thesis_id);
    
    if ($stmt->execute()) {
        $studentQuery = "SELECT u.first_name, u.last_name, u.user_id 
                         FROM thesis_table t 
                         JOIN user_table u ON t.student_id = u.user_id 
                         WHERE t.thesis_id = ?";
        $studentStmt = $conn->prepare($studentQuery);
        $studentStmt->bind_param("i", $thesis_id);
        $studentStmt->execute();
        $student = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
        
        if ($deanResult && $deanResult->num_rows > 0) {
            $message = "📢 A thesis has been forwarded for your approval: \"" . $thesis_title . "\" from student " . ($student['first_name'] ?? '') . " " . ($student['last_name'] ?? '');
            $link = "../departmentDeanDashboard/reviewThesis.php?id=" . $thesis_id;
            
            while ($dean = $deanResult->fetch_assoc()) {
                $notifSql = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) 
                            VALUES (?, ?, ?, 'dean_forward', ?, 0, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("iiss", $dean['user_id'], $thesis_id, $message, $link);
                $notifStmt->execute();
                $notifStmt->close();
            }
        }
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// ==================== HANDLE APPROVE THESIS ====================
if (isset($_POST['approve_thesis']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    
    $updateQuery = "UPDATE thesis_table SET thesis_status = 'approved', approved_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $thesis_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// ==================== HANDLE REJECT THESIS ====================
if (isset($_POST['reject_thesis']) && isset($_POST['thesis_id'])) {
    header('Content-Type: application/json');
    
    $thesis_id = intval($_POST['thesis_id']);
    $reason = $_POST['reason'] ?? '';
    
    $updateQuery = "UPDATE thesis_table SET thesis_status = 'rejected', rejection_reason = ?, rejected_at = NOW() WHERE thesis_id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $reason, $thesis_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// GET USER DATA
$user_query = "SELECT * FROM user_table WHERE user_id = ?";
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

// MAKE SURE NOTIFICATIONS TABLE EXISTS
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

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['count'];
}
$notif_stmt->close();

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list_query = "SELECT notification_id as id, user_id, thesis_id, message, type, link, is_read, created_at 
                     FROM notifications 
                     WHERE user_id = ? 
                     ORDER BY created_at DESC 
                     LIMIT 10";
$notif_list_stmt = $conn->prepare($notif_list_query);
$notif_list_stmt->bind_param("i", $user_id);
$notif_list_stmt->execute();
$notif_list_result = $notif_list_stmt->get_result();
while ($row = $notif_list_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list_stmt->close();

// ==================== GET STATISTICS - FILTERED BY DEPARTMENT_ID ====================
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;
$archivedCount = 0;
$forwardedCount = 0;
$totalCount = 0;

// Monthly data for chart - FILTERED BY DEPARTMENT_ID
$monthlyData = [];
for ($i = 6; $i >= 0; $i--) {
    $monthName = date('M', strtotime("-$i months"));
    $monthlyData[$monthName] = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
}

$table_check = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($table_check && $table_check->num_rows > 0) {
    // Check if thesis_status column exists
    $col_check = $conn->query("SHOW COLUMNS FROM thesis_table LIKE 'thesis_status'");
    if ($col_check && $col_check->num_rows > 0) {
        // Use thesis_status column with department_id filter
        $countsQuery = "SELECT 
            SUM(CASE WHEN thesis_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN thesis_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN thesis_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived,
            SUM(CASE WHEN thesis_status = 'Forwarded_to_dean' THEN 1 ELSE 0 END) as forwarded,
            COUNT(*) as total
        FROM thesis_table
        WHERE department_id = ?";
    } else {
        // Use is_archived logic
        $countsQuery = "SELECT 
            SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) as pending,
            0 as approved,
            0 as rejected,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived,
            0 as forwarded,
            COUNT(*) as total
        FROM thesis_table
        WHERE department_id = ?";
    }
    
    $countsResult = $conn->prepare($countsQuery);
    $countsResult->bind_param("i", $faculty_department_id);
    $countsResult->execute();
    $counts = $countsResult->get_result()->fetch_assoc();
    $pendingCount = $counts['pending'] ?? 0;
    $approvedCount = $counts['approved'] ?? 0;
    $rejectedCount = $counts['rejected'] ?? 0;
    $archivedCount = $counts['archived'] ?? 0;
    $forwardedCount = $counts['forwarded'] ?? 0;
    $totalCount = $counts['total'] ?? 0;
    $countsResult->close();
    
    // Monthly query - FILTERED BY DEPARTMENT_ID
    $col_check2 = $conn->query("SHOW COLUMNS FROM thesis_table LIKE 'thesis_status'");
    if ($col_check2 && $col_check2->num_rows > 0) {
        $monthlyQuery = "SELECT 
            DATE_FORMAT(date_submitted, '%b %Y') as month,
            SUM(CASE WHEN thesis_status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN thesis_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN thesis_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM thesis_table 
        WHERE department_id = ? AND date_submitted >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_submitted, '%b %Y')
        ORDER BY MIN(date_submitted) ASC";
    } else {
        $monthlyQuery = "SELECT 
            DATE_FORMAT(date_submitted, '%b %Y') as month,
            SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) as pending,
            0 as approved,
            0 as rejected
        FROM thesis_table 
        WHERE department_id = ? AND date_submitted >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(date_submitted, '%b %Y')
        ORDER BY MIN(date_submitted) ASC";
    }
    
    $monthlyStmt = $conn->prepare($monthlyQuery);
    $monthlyStmt->bind_param("i", $faculty_department_id);
    $monthlyStmt->execute();
    $monthlyResult = $monthlyStmt->get_result();
    if ($monthlyResult) {
        while ($row = $monthlyResult->fetch_assoc()) {
            $monthlyData[$row['month']] = [
                'pending' => $row['pending'],
                'approved' => $row['approved'],
                'rejected' => $row['rejected']
            ];
        }
    }
    $monthlyStmt->close();
}

// GET PENDING THESES - FILTERED BY DEPARTMENT_ID
$pendingTheses = [];
$col_check3 = $conn->query("SHOW COLUMNS FROM thesis_table LIKE 'thesis_status'");
if ($col_check3 && $col_check3->num_rows > 0) {
    $query = "SELECT t.*, u.first_name, u.last_name, u.email 
              FROM thesis_table t
              JOIN user_table u ON t.student_id = u.user_id
              WHERE t.thesis_status = 'pending' AND t.department_id = ?
              ORDER BY t.date_submitted DESC 
              LIMIT 10";
    $pendingStmt = $conn->prepare($query);
    $pendingStmt->bind_param("i", $faculty_department_id);
} else {
    $query = "SELECT t.*, u.first_name, u.last_name, u.email 
              FROM thesis_table t
              JOIN user_table u ON t.student_id = u.user_id
              WHERE t.is_archived = 0 AND t.department_id = ?
              ORDER BY t.date_submitted DESC 
              LIMIT 10";
    $pendingStmt = $conn->prepare($query);
    $pendingStmt->bind_param("i", $faculty_department_id);
}
$pendingStmt->execute();
$result = $pendingStmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!isset($row['thesis_status'])) {
            $row['thesis_status'] = $row['is_archived'] == 0 ? 'pending' : 'archived';
        }
        $pendingTheses[] = $row;
    }
}
$pendingStmt->close();

// GET ALL SUBMISSIONS - FILTERED BY DEPARTMENT_ID
$allSubmissions = [];
$currentFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

$col_check4 = $conn->query("SHOW COLUMNS FROM thesis_table LIKE 'thesis_status'");
if ($col_check4 && $col_check4->num_rows > 0) {
    $sql = "SELECT 
            t.*, 
            u.first_name, 
            u.last_name, 
            u.email
            FROM thesis_table t
            JOIN user_table u ON t.student_id = u.user_id
            WHERE t.department_id = ?";

    if ($currentFilter != 'all') {
        if ($currentFilter == 'archived') {
            $sql .= " AND t.is_archived = 1";
        } else {
            $sql .= " AND t.thesis_status = ?";
        }
    }

    $sql .= " ORDER BY t.date_submitted DESC";
    
    if ($currentFilter != 'all' && $currentFilter != 'archived') {
        $allStmt = $conn->prepare($sql);
        $allStmt->bind_param("is", $faculty_department_id, $currentFilter);
    } else {
        $allStmt = $conn->prepare($sql);
        $allStmt->bind_param("i", $faculty_department_id);
    }
} else {
    $sql = "SELECT 
            t.*, 
            u.first_name, 
            u.last_name, 
            u.email
            FROM thesis_table t
            JOIN user_table u ON t.student_id = u.user_id
            WHERE t.department_id = ?";

    if ($currentFilter != 'all') {
        if ($currentFilter == 'archived') {
            $sql .= " AND t.is_archived = 1";
        } elseif ($currentFilter == 'pending') {
            $sql .= " AND t.is_archived = 0";
        }
    }

    $sql .= " ORDER BY t.date_submitted DESC";
    $allStmt = $conn->prepare($sql);
    $allStmt->bind_param("i", $faculty_department_id);
}

$allStmt->execute();
$result = $allStmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!isset($row['thesis_status'])) {
            if ($row['is_archived'] == 1) {
                $row['thesis_status'] = 'archived';
            } else {
                $row['thesis_status'] = 'pending';
            }
        }
        $allSubmissions[] = $row;
    }
}
$allStmt->close();

$pageTitle = "Faculty Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="css/facultyDashboard.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search theses..."></div>
        </div>
        <div class="nav-right">
            <div class="notification-container">
                <div class="notification-icon" id="notificationIcon">
                    <i class="far fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge"><?= $notificationCount > 0 ? $notificationCount : '' ?></span>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <?php if ($notificationCount > 0): ?>
                            <span class="mark-all-read" id="markAllReadBtn">Mark all as read</span>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <?php if (empty($recentNotifications)): ?>
                            <div class="notification-item empty">
                                <div class="notif-icon"><i class="far fa-bell-slash"></i></div>
                                <div class="notif-content">
                                    <div class="notif-message">No notifications yet</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <div class="notification-item <?= $notif['is_read'] == 0 ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>" data-thesis-id="<?= $notif['thesis_id'] ?>">
                                    <div class="notif-icon"><i class="fas fa-file-alt"></i></div>
                                    <div class="notif-content">
                                        <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notif-time"><i class="far fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="notification-footer"><a href="notification.php">View all notifications <i class="fas fa-arrow-right"></i></a></div>
                </div>
            </div>
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a>
                    <hr>
                    <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">RESEARCH ADVISER</div></div>
        <div class="nav-menu">
            <a href="facultyDashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span>
                <?php if ($pendingCount > 0): ?><span style="margin-left: auto; background: #ff6b6b; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;"><?= $pendingCount ?></span><?php endif; ?>
            </a>
            <a href="notification.php" class="nav-item"><i class="fas fa-bell"></i><span>Notifications</span>
                <?php if ($notificationCount > 0): ?><span style="margin-left: auto; background: #ff6b6b; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem;"><?= $notificationCount ?></span><?php endif; ?>
            </a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><span>Light Mode</span></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>Research Adviser Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($first_name) ?>! Department: <strong><?= htmlspecialchars($faculty_department_name) ?> (<?= htmlspecialchars($faculty_department_code) ?>)</strong></p>
            </div>
            <div class="faculty-info"><div class="faculty-name"><?= htmlspecialchars($fullName) ?></div></div>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?= number_format($pendingCount) ?></h3><p>Pending Review</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-content"><h3><?= number_format($approvedCount) ?></h3><p>Approved</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-times-circle"></i></div><div class="stat-content"><h3><?= number_format($rejectedCount) ?></h3><p>Rejected</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-content"><h3><?= number_format($archivedCount) ?></h3><p>Archived</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-share"></i></div><div class="stat-content"><h3><?= number_format($forwardedCount) ?></h3><p>Forwarded to Dean</p></div></div>
        </div>

        <div class="chart-container">
            <h3><i class="fas fa-chart-line"></i> Thesis Submission Trends (Last 7 Months) - <?= htmlspecialchars($faculty_department_name) ?> Department</h3>
            <div class="chart-wrapper"><canvas id="submissionChart"></canvas></div>
        </div>

        <div class="theses-card">
            <div class="card-header"><h3><i class="fas fa-clock"></i> Theses Waiting for Review</h3></div>
            <?php if (empty($pendingTheses)): ?>
                <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending theses to review. Great job!</p></div>
            <?php else: ?>
                <div class="theses-list">
                    <?php foreach ($pendingTheses as $thesis): ?>
                    <div class="thesis-item">
                        <div class="thesis-info">
                            <div class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></div>
                            <div class="thesis-meta"><span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span><span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['date_submitted'])) ?></span></div>
                        </div>
                        <div class="action-buttons"><a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="review-btn"><i class="fas fa-chevron-right"></i> Review Thesis</a></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="submissions-card">
            <div class="card-header">
                <h3><i class="fas fa-file-alt"></i> All Thesis Submissions - <?= htmlspecialchars($faculty_department_name) ?> Department</h3>
                <div class="filter-tabs">
                    <a href="?status=all" class="filter-btn <?= $currentFilter == 'all' ? 'active' : '' ?>">All (<?= $totalCount ?>)</a>
                    <a href="?status=pending" class="filter-btn <?= $currentFilter == 'pending' ? 'active' : '' ?>">Pending (<?= $pendingCount ?>)</a>
                    <a href="?status=approved" class="filter-btn <?= $currentFilter == 'approved' ? 'active' : '' ?>">Approved (<?= $approvedCount ?>)</a>
                    <a href="?status=rejected" class="filter-btn <?= $currentFilter == 'rejected' ? 'active' : '' ?>">Rejected (<?= $rejectedCount ?>)</a>
                    <a href="?status=archived" class="filter-btn <?= $currentFilter == 'archived' ? 'active' : '' ?>">Archived (<?= $archivedCount ?>)</a>
                    <a href="?status=Forwarded_to_dean" class="filter-btn <?= $currentFilter == 'Forwarded_to_dean' ? 'active' : '' ?>">Forwarded (<?= $forwardedCount ?>)</a>
                </div>
            </div>
            <div class="table-responsive">
                <?php if (empty($allSubmissions)): ?>
                    <div class="empty-state"><i class="fas fa-folder-open"></i><p>No thesis submissions yet for your department.</p></div>
                <?php else: ?>
                    <table class="theses-table">
                        <thead><tr><th>Thesis Title</th><th>Student</th><th>Date Submitted</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($allSubmissions as $submission): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(substr($submission['title'], 0, 50)) . (strlen($submission['title']) > 50 ? '...' : '') ?></strong></td>
                                <td><?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($submission['date_submitted'])) ?></td>
                                <td><span class="status-badge <?= isset($submission['thesis_status']) ? strtolower($submission['thesis_status']) : 'pending' ?>"><?= isset($submission['thesis_status']) ? ($submission['thesis_status'] == 'Forwarded_to_dean' ? 'Forwarded to Dean' : ucfirst($submission['thesis_status'])) : ($submission['is_archived'] == 1 ? 'Archived' : 'Pending') ?></span></td>
                                <td><a href="reviewThesis.php?id=<?= $submission['thesis_id'] ?>" class="btn-review-small"><i class="fas fa-eye"></i> <?= (isset($submission['thesis_status']) && $submission['thesis_status'] == 'pending') ? 'Review' : 'View' ?></a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        window.chartData = {
            labels: <?php 
                $labels = [];
                $pendingData = [];
                $approvedData = [];
                $rejectedData = [];
                foreach ($monthlyData as $month => $data) {
                    $labels[] = $month;
                    $pendingData[] = $data['pending'];
                    $approvedData[] = $data['approved'];
                    $rejectedData[] = $data['rejected'];
                }
                echo json_encode($labels);
            ?>,
            pendingData: <?php echo json_encode($pendingData); ?>,
            approvedData: <?php echo json_encode($approvedData); ?>,
            rejectedData: <?php echo json_encode($rejectedData); ?>
        };
        window.userData = {
            notificationCount: <?php echo $notificationCount; ?>,
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <script src="js/facultyDashboard.js"></script>
</body>
</html>