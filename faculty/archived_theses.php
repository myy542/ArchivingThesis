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

// GET USER DATA
$user_query = "SELECT user_id, username, email, first_name, last_name, role_id, status FROM user_table WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    $first_name = $user_data['first_name'];
    $last_name = $user_data['last_name'];
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

// GET NOTIFICATION COUNT
$notificationCount = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
if ($check_table && $check_table->num_rows > 0) {
    $notif_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $notif_stmt = $conn->prepare($notif_query);
    $notif_stmt->bind_param("i", $user_id);
    $notif_stmt->execute();
    $notif_result = $notif_stmt->get_result();
    if ($notif_row = $notif_result->fetch_assoc()) {
        $notificationCount = $notif_row['count'];
    }
    $notif_stmt->close();
}

// Get archived theses from database (is_archived = 1)
$filters = [
    'department' => $_GET['department'] ?? '',
    'year' => $_GET['year'] ?? '',
    'archived_from' => $_GET['archived_from'] ?? '',
    'archived_to' => $_GET['archived_to'] ?? ''
];

// Build query for archived theses (is_archived = 1)
$query = "SELECT thesis_id, title, adviser as author, department, year, abstract, is_archived, date_submitted as created_at 
          FROM thesis_table 
          WHERE is_archived = 1";

$params = [];
$types = "";

if (!empty($filters['department'])) {
    $query .= " AND department = ?";
    $params[] = $filters['department'];
    $types .= "s";
}

if (!empty($filters['year'])) {
    $query .= " AND year = ?";
    $params[] = $filters['year'];
    $types .= "s";
}

if (!empty($filters['archived_from'])) {
    $query .= " AND date_submitted >= ?";
    $params[] = $filters['archived_from'];
    $types .= "s";
}

if (!empty($filters['archived_to'])) {
    $query .= " AND date_submitted <= ?";
    $params[] = $filters['archived_to'] . ' 23:59:59';
    $types .= "s";
}

$query .= " ORDER BY date_submitted DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$archived = $stmt->get_result();

// Get summary statistics
$total_archived = $conn->query("SELECT COUNT(*) as c FROM thesis_table WHERE is_archived = 1")->fetch_assoc()['c'];

// Get departments list for filter
$dept_query = "SELECT DISTINCT department FROM thesis_table WHERE department IS NOT NULL AND department != '' AND is_archived = 1";
$dept_result = $conn->query($dept_query);
$departments = [];
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}

// Handle restore thesis (change is_archived to 0)
if (isset($_POST['restore_thesis'])) {
    $thesis_id = $_POST['thesis_id'];
    
    $update_query = "UPDATE thesis_table SET is_archived = 0 WHERE thesis_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $thesis_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Thesis restored successfully!";
        header("Location: archived_theses.php");
        exit();
    }
    $update_stmt->close();
}

$pageTitle = "Archived Theses";
$currentPage = basename($_SERVER['PHP_SELF']);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/archived_theses.css">
</head>
<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <header class="top-nav">
        <div class="nav-left">
            <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
            <div class="logo">Thesis<span>Manager</span></div>
            <div class="search-area"><i class="fas fa-search"></i><input type="text" id="topSearchInput" placeholder="Search..."></div>
        </div>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span><div class="profile-avatar"><?= htmlspecialchars($initials) ?></div></div>
                <div class="profile-dropdown" id="profileDropdown"><a href="facultyProfile.php"><i class="fas fa-user"></i> Profile</a><a href="facultyEditProfile.php"><i class="fas fa-edit"></i> Edit Profile</a><hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <div class="logo-container"><div class="logo">Thesis<span>Manager</span></div><div class="logo-sub">FACULTY</div></div>
        <div class="nav-menu">
            <a href="facultyDashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
            <a href="reviewThesis.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Review Theses</span></a>
            <a href="archived_theses.php" class="nav-item active"><i class="fas fa-archive"></i><span>Archived</span></a>
            <a href="facultyFeedback.php" class="nav-item"><i class="fas fa-comment"></i><span>Feedback</span></a>
        </div>
        <div class="nav-footer">
            <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i></label></div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-archive"></i> Archived Theses</h1>
            <p>View and manage archived academic theses</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-archive"></i></div><div class="stat-details"><h3><?= number_format($total_archived) ?></h3><p>Total Archived</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-range"></i></div><div class="stat-details"><h3><?= date('Y') ?></h3><p>Current Year</p></div></div>
        </div>

        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-input" placeholder="Search by title, author, department..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
            <select id="departmentFilter" class="filter-select">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= htmlspecialchars($dept) ?>" <?= ($filters['department'] ?? '') == $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="yearFilter" class="filter-select">
                <option value="">All Years</option>
                <option value="2024" <?= ($filters['year'] ?? '') == '2024' ? 'selected' : '' ?>>2024</option>
                <option value="2023" <?= ($filters['year'] ?? '') == '2023' ? 'selected' : '' ?>>2023</option>
                <option value="2022" <?= ($filters['year'] ?? '') == '2022' ? 'selected' : '' ?>>2022</option>
            </select>
            <input type="date" id="dateFrom" class="date-input" placeholder="Date From" value="<?= htmlspecialchars($filters['archived_from'] ?? '') ?>">
            <input type="date" id="dateTo" class="date-input" placeholder="Date To" value="<?= htmlspecialchars($filters['archived_to'] ?? '') ?>">
            <button id="applyFilters" class="filter-btn"><i class="fas fa-filter"></i> Apply Filters</button>
            <button id="clearFilters" class="clear-btn"><i class="fas fa-times"></i> Clear</button>
        </div>

        <div class="theses-container">
            <div class="theses-grid" id="thesesGrid">
                <?php if ($archived->num_rows == 0): ?>
                    <div class="no-results"><i class="fas fa-archive"></i><h3>No archived theses found</h3><p>Try adjusting your search or filter criteria</p></div>
                <?php else: ?>
                    <?php while ($thesis = $archived->fetch_assoc()): ?>
                        <div class="thesis-card">
                            <div class="thesis-header"><span class="status-badge archived"><i class="fas fa-check-circle"></i> Archived</span><h3 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h3></div>
                            <div class="thesis-meta"><span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['author'] ?? 'Unknown') ?></span><span><i class="fas fa-building"></i> <?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></span><span><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($thesis['created_at'])) ?></span><span><i class="fas fa-calendar-alt"></i> Year: <?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></span></div>
                            <p class="thesis-abstract"><?= htmlspecialchars(substr($thesis['abstract'] ?? '', 0, 150)) ?>...</p>
                            <div class="thesis-actions"><a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View Details</a><form method="POST" class="restore-form" style="display:inline;"><input type="hidden" name="thesis_id" value="<?= $thesis['thesis_id'] ?>"><button type="submit" name="restore_thesis" class="btn-restore" onclick="return confirm('Restore this thesis? It will no longer be archived.')"><i class="fas fa-undo-alt"></i> Restore</button></form></div>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        window.userData = {
            fullName: '<?php echo addslashes($fullName); ?>',
            initials: '<?php echo addslashes($initials); ?>',
            notificationCount: <?php echo $notificationCount; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/archived_theses.js"></script>
</body>
</html>