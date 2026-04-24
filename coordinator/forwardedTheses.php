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

// Get coordinator department (para exclusive by department)
$coord_query = "SELECT department_id FROM user_table WHERE user_id = ?";
$coord_stmt = $conn->prepare($coord_query);
$coord_stmt->bind_param("i", $user_id);
$coord_stmt->execute();
$coord_result = $coord_stmt->get_result();
$coord_data = $coord_result->fetch_assoc();
$coordinator_dept_id = $coord_data['department_id'] ?? null;
$coord_stmt->close();

// GET FORWARDED THESES (may laman na ang forwarded_to_dean array)
// Since wala pang status column, gagamit tayo ng temporary array para sa forwarded theses
// Sa ngayon, empty muna dahil wala pang status column
$forwardedTheses = [];

$totalForwarded = count($forwardedTheses);
$withDeanCount = 0;
$archivedCount = 0;

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Forwarded to Dean | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/forwardedTheses.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search forwarded theses..."></div>
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
            <a href="myFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>My Feedback</span></a>
            <a href="forwardedTheses.php" class="nav-item active"><i class="fas fa-arrow-right"></i><span>Forwarded to Dean</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h2>Forwarded to Dean</h2>
            <a href="coordinatorDashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-arrow-right"></i></div><div class="stat-content"><h3><?= $totalForwarded ?></h3><p>Total Theses</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-gavel"></i></div><div class="stat-content"><h3><?= $withDeanCount ?></h3><p>With Dean</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-content"><h3><?= $archivedCount ?></h3><p>Archived</p></div></div>
        </div>

        <div class="filter-tabs">
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="with-dean">With Dean</button>
            <button class="filter-btn" data-filter="archived">Archived</button>
        </div>

        <div class="forwarded-list">
            <?php if (empty($forwardedTheses)): ?>
                <div class="empty-state"><i class="fas fa-inbox"></i><p>No theses forwarded to Dean yet.</p><p style="font-size:14px; margin-top:10px;">When you forward a thesis from the review page, it will appear here.</p></div>
            <?php else: ?>
                <table class="forwarded-table" id="thesesTable">
                    <thead>
                        <tr><th>Thesis Title</th><th>Adviser</th><th>Department</th><th>Date Forwarded</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($forwardedTheses as $thesis): ?>
                            <tr data-status="<?= $thesis['status_class'] ?>">
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['adviser']) ?></td>
                                <td><?= htmlspecialchars($thesis['department']) ?> (<?= htmlspecialchars($thesis['department_code']) ?>)</td>
                                <td><?= date('M d, Y', strtotime($thesis['date_forwarded'])) ?></td>
                                <td><span class="status-badge status-<?= $thesis['status_class'] ?>"><i class="fas <?= $thesis['status_icon'] ?>"></i> <?= $thesis['status'] ?></span></td>
                                <td><a href="reviewThesis.php?id=<?= $thesis['id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>'
        };
    </script>
    
    <script src="js/forwardedTheses.js"></script>
</body>
</html>