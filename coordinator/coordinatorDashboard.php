<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'coordinator') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// GET USER DATA FROM DATABASE with department info
$user_query = "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role_id, u.department_id, d.department_name, d.department_code 
               FROM user_table u
               LEFT JOIN department_table d ON u.department_id = d.department_id
               WHERE u.user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

$first_name = '';
$last_name = '';
$username = '';
$user_email = '';
$coordinator_department_id = null;
$coordinator_department_name = '';

if ($user_data) {
    $first_name = $user_data['first_name'] ?? '';
    $last_name = $user_data['last_name'] ?? '';
    $username = $user_data['username'] ?? '';
    $user_email = $user_data['email'] ?? '';
    $coordinator_department_id = $user_data['department_id'] ?? null;
    $coordinator_department_name = $user_data['department_name'] ?? 'All Departments';
    $coordinator_department_code = $user_data['department_code'] ?? '';
}

$fullName = trim($first_name . " " . $last_name);
if (empty($fullName)) $fullName = !empty($username) ? $username : "Coordinator";

$initials = !empty($first_name) && !empty($last_name) ? strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) : 
            (!empty($first_name) ? strtoupper(substr($first_name, 0, 1)) : "CO");

// GET ALL DEPARTMENTS for display
$all_departments = [];
$dept_query = "SELECT department_id, department_name, department_code FROM department_table ORDER BY department_name";
$dept_result = $conn->query($dept_query);
if ($dept_result && $dept_result->num_rows > 0) {
    while ($dept = $dept_result->fetch_assoc()) {
        $all_departments[$dept['department_id']] = [
            'name' => $dept['department_name'],
            'code' => $dept['department_code']
        ];
    }
}

$dept_colors = [
    'BSIT' => '#3b82f6',
    'BSCRIM' => '#10b981',
    'BSHTM' => '#f59e0b',
    'BSED' => '#8b5cf6',
    'BSBA' => '#ef4444'
];

$dept_icons = [
    'BSIT' => 'fa-laptop-code',
    'BSCRIM' => 'fa-gavel',
    'BSHTM' => 'fa-utensils',
    'BSED' => 'fa-chalkboard-user',
    'BSBA' => 'fa-chart-line'
];

$position = "Research Coordinator";
$assigned_date = date('F Y');

// CHECK IF THESIS TABLE EXISTS
$thesis_table_exists = false;
$check_thesis = $conn->query("SHOW TABLES LIKE 'thesis_table'");
if ($check_thesis && $check_thesis->num_rows > 0) {
    $thesis_table_exists = true;
}

// CREATE NOTIFICATIONS TABLE IF NOT EXISTS
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    thesis_id INT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info',
    link VARCHAR(255) NULL,
    is_read TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// GET NOTIFICATION COUNT
$notificationCount = 0;
$notif_query = "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
if ($notif_row = $notif_result->fetch_assoc()) {
    $notificationCount = $notif_row['cnt'];
}
$notif_stmt->close();

// GET RECENT NOTIFICATIONS
$recentNotifications = [];
$notif_list = $conn->prepare("SELECT notification_id, user_id, thesis_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$notif_list->bind_param("i", $user_id);
$notif_list->execute();
$notif_result = $notif_list->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $recentNotifications[] = $row;
}
$notif_list->close();

// FUNCTION TO NOTIFY DEAN
function notifyDean($conn, $thesis_id, $thesis_title, $student_name, $coordinator_name, $department_id) {
    $dept_name_query = "SELECT department_name FROM department_table WHERE department_id = ?";
    $dept_name_stmt = $conn->prepare($dept_name_query);
    $dept_name_stmt->bind_param("i", $department_id);
    $dept_name_stmt->execute();
    $dept_name_result = $dept_name_stmt->get_result();
    $dept = $dept_name_result->fetch_assoc();
    $dept_display = $dept['department_name'] ?? "Department";
    $dept_name_stmt->close();
    
    $dean = null;
    $role_ids = [2, 3, 4, 5];
    
    foreach ($role_ids as $role_id) {
        $dean_query = "SELECT user_id FROM user_table WHERE role_id = ? AND department_id = ? LIMIT 1";
        $dean_stmt = $conn->prepare($dean_query);
        $dean_stmt->bind_param("ii", $role_id, $department_id);
        $dean_stmt->execute();
        $dean_result = $dean_stmt->get_result();
        if ($dean_result && $dean_result->num_rows > 0) {
            $dean = $dean_result->fetch_assoc();
            $dean_stmt->close();
            break;
        }
        $dean_stmt->close();
    }
    
    if ($dean) {
        $message = "📋 Thesis ready for Dean approval: \"" . $thesis_title . "\" from student " . $student_name . ". Forwarded by Coordinator: " . $coordinator_name . " (" . $dept_display . " Department)";
        $link = "../departmentDeanDashboard/reviewThesis.php?id=" . $thesis_id;
        
        // FIXED: 6 placeholders, 6 bind variables
        $insert = "INSERT INTO notifications (user_id, thesis_id, message, type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())";
        $stmt = $conn->prepare($insert);
        $stmt->bind_param("iisss", $dean['user_id'], $thesis_id, $message, 'dean_forward', $link);
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'department' => $dept_display];
    }
    return ['success' => false, 'message' => 'No dean found'];
}

// PROCESS FORWARD FORM
$forward_success = null;
$forward_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forward_to_dean']) && isset($_POST['thesis_id'])) {
    $thesis_id = intval($_POST['thesis_id']);
    $thesis_title = $_POST['thesis_title'] ?? '';
    $student_name = $_POST['student_name'] ?? '';
    
    $dept_query = "SELECT department_id FROM thesis_table WHERE thesis_id = ?";
    $dept_stmt = $conn->prepare($dept_query);
    $dept_stmt->bind_param("i", $thesis_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    $thesis_dept = $dept_result->fetch_assoc();
    $department_id = $thesis_dept['department_id'] ?? null;
    $dept_stmt->close();
    
    if (empty($department_id)) {
        $forward_success = false;
        $forward_message = 'Thesis has no department assigned';
    } else {
        $result = notifyDean($conn, $thesis_id, $thesis_title, $student_name, $fullName, $department_id);
        
        if ($result['success']) {
            $forward_success = true;
            $forward_message = '✓ Thesis forwarded to ' . $result['department'] . ' Dean';
        } else {
            $forward_success = false;
            $forward_message = '✗ ' . $result['message'];
        }
    }
}

// GET DEPARTMENT COUNTS
$dept_counts = [];
if ($thesis_table_exists) {
    $dept_query = "SELECT department_id, COUNT(*) as count FROM thesis_table WHERE (is_archived = 0 OR is_archived IS NULL) GROUP BY department_id";
    $dept_result = $conn->query($dept_query);
    if ($dept_result && $dept_result->num_rows > 0) {
        while ($row = $dept_result->fetch_assoc()) {
            $dept_id = $row['department_id'];
            if (isset($all_departments[$dept_id])) {
                $dept_counts[$all_departments[$dept_id]['code']] = $row['count'];
            }
        }
    }
}
$total_theses = array_sum($dept_counts);
$max_dept_count = max(array_values($dept_counts) ?: [1]);

// GET PENDING THESES
$pending_theses_by_dept = [];
foreach ($all_departments as $dept_id => $dept_info) {
    $pending_theses_by_dept[$dept_info['code']] = [];
}

if ($thesis_table_exists) {
    $pending_query = "SELECT t.*, u.first_name, u.last_name, d.department_code, d.department_name
                      FROM thesis_table t
                      JOIN user_table u ON t.student_id = u.user_id
                      LEFT JOIN department_table d ON t.department_id = d.department_id
                      WHERE (t.is_archived = 0 OR t.is_archived IS NULL)
                      ORDER BY t.date_submitted DESC";
    $pending_result = $conn->query($pending_query);
    
    if ($pending_result && $pending_result->num_rows > 0) {
        while ($row = $pending_result->fetch_assoc()) {
            $dept_code = $row['department_code'] ?? 'N/A';
            if (isset($pending_theses_by_dept[$dept_code])) {
                $pending_theses_by_dept[$dept_code][] = $row;
            }
        }
    }
}

$total_pending = 0;
foreach ($pending_theses_by_dept as $theses) {
    $total_pending += count($theses);
}

// AJAX HANDLERS
if (isset($_POST['mark_read']) && isset($_POST['notif_id'])) {
    header('Content-Type: application/json');
    $notif_id = intval($_POST['notif_id']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $notif_id AND user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

if (isset($_POST['mark_all_read'])) {
    header('Content-Type: application/json');
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    echo json_encode(['success' => true]);
    exit;
}

$pageTitle = "Coordinator Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/coordinatorDashboard.css">
    <script src="js/coordinatorDashboard.js"></script>
</head>
<body>

<?php if ($forward_success !== null): ?>
<div id="messageModal" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <div style="background:white;padding:30px;border-radius:16px;text-align:center;min-width:300px;">
        <div style="font-size:50px;margin-bottom:15px;color:<?= $forward_success ? '#10b981' : '#ef4444' ?>">
            <i class="fas <?= $forward_success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
        </div>
        <p style="margin-bottom:20px;font-size:16px;"><?= htmlspecialchars($forward_message) ?></p>
        <button onclick="document.getElementById('messageModal').style.display='none';window.history.replaceState({},document.title,window.location.pathname);" style="background:#3b82f6;color:white;border:none;padding:10px 30px;border-radius:8px;cursor:pointer;">OK</button>
    </div>
</div>
<?php endif; ?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<header class="top-nav">
    <div class="nav-left">
        <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
        <div class="logo">Thesis<span>Manager</span></div>
    </div>
    <div class="nav-right">
        <div class="notification-container">
            <div class="notification-icon" id="notificationIcon">
                <i class="far fa-bell"></i>
                <?php if($notificationCount>0):?>
                <span class="notification-badge"><?=$notificationCount?></span>
                <?php endif;?>
            </div>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <?php if($notificationCount>0):?>
                    <button class="mark-all-read" id="markAllReadBtn">Mark all</button>
                    <?php endif;?>
                </div>
                <div class="notification-list" id="notificationList">
                    <?php if(empty($recentNotifications)):?>
                    <div class="notification-item empty">No notifications</div>
                    <?php else:?>
                        <?php foreach($recentNotifications as $notif):?>
                        <div class="notification-item <?=$notif['is_read']==0?'unread':''?>" 
                             data-id="<?=$notif['notification_id']?>" 
                             data-link="<?=htmlspecialchars($notif['link'] ?? '#')?>">
                            <div class="notif-message"><?=htmlspecialchars($notif['message'])?></div>
                            <div class="notif-time"><?=date('M d, Y h:i A',strtotime($notif['created_at']))?></div>
                        </div>
                        <?php endforeach;?>
                    <?php endif;?>
                </div>
                <div class="notification-footer"><a href="notifications.php">View all</a></div>
            </div>
        </div>
        <div class="profile-wrapper" id="profileWrapper">
            <div class="profile-trigger"><span class="profile-name"><?=htmlspecialchars($fullName)?></span><div class="profile-avatar"><?=htmlspecialchars($initials)?></div></div>
            <div class="profile-dropdown" id="profileDropdown"><a href="profile.php"><i class="fas fa-user"></i> Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
        </div>
    </div>
</header>

<aside class="sidebar" id="sidebar">
    <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">COORDINATOR</div></div>
    <div class="nav-menu">
        <a href="coordinatorDashboard.php" class="nav-item active"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
        <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
        <a href="forwardedTheses.php" class="nav-item"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
    </div>
    <div class="nav-footer">
        <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-moon"></i> Dark Mode</label></div>
        <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="welcome-banner">
        <div class="welcome-info">
            <h1>Coordinator Dashboard</h1>
            <p>Welcome back, <?=htmlspecialchars($first_name)?>!</p>
        </div>
        <div class="coordinator-info">
            <div class="coordinator-name"><?=htmlspecialchars($fullName)?></div>
            <div class="coordinator-position"><?=$position?></div>
            <div class="coordinator-since">Since <?=$assigned_date?></div>
        </div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-file-alt"></i></div><div class="stat-content"><h3><?=number_format($total_theses)?></h3><p>Total Theses</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-content"><h3><?=number_format($total_pending)?></h3><p>Pending Review</p></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-content"><h3><?=number_format($total_theses-$total_pending)?></h3><p>Archived</p></div></div>
    </div>
    
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Thesis Submissions by Department</h3>
        <div class="dept-cards-grid">
            <?php foreach($all_departments as $dept_id => $dept): 
                $code = $dept['code'];
                $name = $dept['name'];
                $count = isset($dept_counts[$code]) ? $dept_counts[$code] : 0; 
                $percentage = $max_dept_count > 0 ? ($count / $max_dept_count) * 100 : 0;
                $color = isset($dept_colors[$code]) ? $dept_colors[$code] : '#6c757d';
                $icon = isset($dept_icons[$code]) ? $dept_icons[$code] : 'fa-building';
            ?>
            <div class="dept-card" data-dept="<?=$code?>">
                <div class="dept-card-icon" style="background:<?=$color?>20;"><i class="fas <?=$icon?>" style="color:<?=$color?>"></i></div>
                <div class="dept-card-content">
                    <h4><?=htmlspecialchars($name)?></h4>
                    <div class="dept-card-stats"><span class="dept-card-count"><?=$count?></span><span class="dept-card-label">Theses</span></div>
                    <div class="progress-bar-small"><div class="progress-fill-small" style="width:<?=$percentage?>%;background:<?=$color?>"></div></div>
                </div>
            </div>
            <?php endforeach;?>
        </div>
        <div class="dept-total-footer"><strong>Total Theses: <?=$total_theses?></strong></div>
    </div>
    
    <!-- Theses Ready for Dean Forwarding -->
    <div class="chart-card">
        <h3><i class="fas fa-paper-plane"></i> Theses Ready for Dean Forwarding</h3>
        <?php 
        $has_pending = false;
        foreach($all_departments as $dept_id => $dept): 
            $code = $dept['code'];
            $name = $dept['name'];
            if($coordinator_department_id && $dept_id != $coordinator_department_id) {
                continue;
            }
            $dept_theses = isset($pending_theses_by_dept[$code]) ? $pending_theses_by_dept[$code] : []; 
            if(empty($dept_theses)) continue; 
            $has_pending = true; 
            $color = isset($dept_colors[$code]) ? $dept_colors[$code] : '#6c757d';
            $icon = isset($dept_icons[$code]) ? $dept_icons[$code] : 'fa-building';
        ?>
        <div class="dept-section" data-dept="<?=$code?>">
            <div class="dept-section-header">
                <span class="dept-dot" style="background:<?=$color?>"></span>
                <h4><?=htmlspecialchars($name)?></h4>
                <span class="badge"><?=count($dept_theses)?> theses</span>
            </div>
            <div class="dept-section-content">
                <?php foreach($dept_theses as $thesis):?>
                <div class="thesis-item">
                    <div class="thesis-info">
                        <div class="thesis-title"><?=htmlspecialchars($thesis['title'])?></div>
                        <div class="thesis-meta">
                            <span><i class="fas fa-user"></i> <?=htmlspecialchars($thesis['first_name'].' '.$thesis['last_name'])?></span>
                            <span><i class="fas fa-calendar"></i> <?=date('M d, Y',strtotime($thesis['date_submitted']))?></span>
                            <span class="dept-badge" style="background:<?=$color?>20; color:<?=$color?>; padding:2px 8px; border-radius:12px; font-size:0.65rem;">
                                <i class="fas <?=$icon?>"></i> <?=htmlspecialchars($code)?>
                            </span>
                        </div>
                    </div>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Forward thesis \"<?=addslashes(htmlspecialchars($thesis['title']))?>\" to the <?=$code?> Dean?')">
                        <input type="hidden" name="forward_to_dean" value="1">
                        <input type="hidden" name="thesis_id" value="<?=$thesis['thesis_id']?>">
                        <input type="hidden" name="thesis_title" value="<?=htmlspecialchars($thesis['title'])?>">
                        <input type="hidden" name="student_name" value="<?=htmlspecialchars($thesis['first_name'].' '.$thesis['last_name'])?>">
                        <input type="hidden" name="department_code" value="<?=$code?>">
                        <button type="submit" class="btn-forward">
                            <i class="fas fa-paper-plane"></i> Forward to <?=$code?> Dean
                        </button>
                    </form>
                </div>
                <?php endforeach;?>
            </div>
        </div>
        <?php endforeach; if(!$has_pending):?>
        <div style="padding:40px;text-align:center;color:#9ca3af"><i class="fas fa-check-circle"></i><p>No pending theses for your department</p></div>
        <?php endif;?>
    </div>
    
    <!-- Theses Waiting for Review -->
    <div class="chart-card">
        <h3><i class="fas fa-clock"></i> Theses Waiting for Review</h3>
        <?php 
        $has_waiting = false; 
        foreach($all_departments as $dept_id => $dept): 
            $code = $dept['code'];
            $name = $dept['name'];
            if($coordinator_department_id && $dept_id != $coordinator_department_id) {
                continue;
            }
            $dept_theses = isset($pending_theses_by_dept[$code]) ? $pending_theses_by_dept[$code] : []; 
            if(empty($dept_theses)) continue; 
            $has_waiting = true;
            $color = isset($dept_colors[$code]) ? $dept_colors[$code] : '#6c757d';
        ?>
        <div class="dept-section">
            <div class="dept-section-header">
                <span class="dept-dot" style="background:<?=$color?>"></span>
                <h4><?=htmlspecialchars($name)?></h4>
                <span class="badge"><?=count($dept_theses)?> waiting</span>
            </div>
            <div class="dept-section-content">
                <?php foreach($dept_theses as $thesis):?>
                <div class="thesis-item">
                    <div class="thesis-info">
                        <div class="thesis-title"><?=htmlspecialchars($thesis['title'])?></div>
                        <div class="thesis-meta">
                            <span><i class="fas fa-user"></i> <?=htmlspecialchars($thesis['first_name'].' '.$thesis['last_name'])?></span>
                            <span><i class="fas fa-calendar"></i> <?=date('M d, Y',strtotime($thesis['date_submitted']))?></span>
                        </div>
                    </div>
                    <a href="reviewThesis.php?id=<?=$thesis['thesis_id']?>" class="review-btn"><i class="fas fa-chevron-right"></i> Review</a>
                </div>
                <?php endforeach;?>
            </div>
        </div>
        <?php endforeach; if(!$has_waiting):?>
        <div style="padding:40px;text-align:center;color:#9ca3af"><i class="fas fa-check-circle"></i><p>No waiting theses for your department</p></div>
        <?php endif;?>
    </div>
</main>

</body>
</html>